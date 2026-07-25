<?php
/**
 * Storage abstraction — γενικό layer αποθήκευσης αρχείων για όλα τα modules.
 * Drivers: 'local' (attachments/cloudon-storage) & 's3' (Hetzner Object Storage / οποιοδήποτε S3-compatible).
 *
 * Ρυθμίσεις (tbladdonmodules, module=cloudonprojects):
 *   storage_driver = local|s3 (default local)
 *   s3_endpoint, s3_region, s3_bucket, s3_key, s3_secret, s3_prefix (προαιρετικό)
 *
 * Μητρώο: mod_cpm_storage (driver/bucket/storage_key + metadata) — παλιά (local) & νέα (s3) συνυπάρχουν.
 * PII/ιδιωτικά: buckets ΠΑΝΤΑ private· σερβίρισμα με presigned URLs ή proxy μέσω authenticated API.
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;
use Aws\S3\S3Client;

class Storage
{
    /** Ρίζα του vhost (my.cloudon.gr). */
    public static function base()
    {
        return dirname(__DIR__, 4);
    }
    /** Τοπική ρίζα αποθήκευσης (private — .htaccess Deny). */
    public static function localRoot()
    {
        return self::base() . '/attachments/cloudon-storage';
    }

    public static function config($key, $default = '')
    {
        $v = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', $key)->value('value');
        return $v === null ? $default : trim((string) $v);
    }
    public static function driver()
    {
        return self::config('storage_driver', 'local') === 's3' ? 's3' : 'local';
    }
    public static function isS3()
    {
        return self::driver() === 's3';
    }
    public static function bucket()
    {
        return self::config('s3_bucket', '');
    }
    /** Έλεγχος ότι το S3 είναι ρυθμισμένο (endpoint/bucket/creds). */
    public static function s3Configured()
    {
        foreach (['s3_endpoint', 's3_bucket', 's3_key', 's3_secret'] as $k) {
            if (self::config($k, '') === '') { return false; }
        }
        return true;
    }

    /** @var S3Client|null */
    private static $s3;
    public static function s3()
    {
        if (self::$s3 === null) {
            require_once self::base() . '/vendor/autoload.php';
            self::$s3 = new S3Client([
                'version' => 'latest',
                'region' => self::config('s3_region', 'fsn1'),
                'endpoint' => self::config('s3_endpoint'),
                'use_path_style_endpoint' => true,          // Hetzner/MinIO compatibility
                'credentials' => ['key' => self::config('s3_key'), 'secret' => self::config('s3_secret')],
            ]);
        }
        return self::$s3;
    }

    /** Παράγει μοναδικό storage key: <module>/<Y>/<m>/<uuid>.<ext> (+ προαιρετικό prefix για S3). */
    public static function newKey($module, $ext, $forDriver = null)
    {
        $module = preg_replace('/[^a-z0-9_-]/i', '', $module) ?: 'misc';
        $ext = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $ext));
        $uuid = bin2hex(random_bytes(16));
        $key = $module . '/' . date('Y/m') . '/' . $uuid . ($ext ? '.' . $ext : '');
        $driver = $forDriver ?: self::driver();
        if ($driver === 's3') {
            $prefix = trim(self::config('s3_prefix', ''), '/');
            if ($prefix !== '') { $key = $prefix . '/' . $key; }
        }
        return $key;
    }

    public static function kindFromMime($mime)
    {
        $m = strtolower((string) $mime);
        if (strpos($m, 'image/') === 0) { return 'image'; }
        if (strpos($m, 'video/') === 0) { return 'video'; }
        if (strpos($m, 'audio/') === 0) { return 'audio'; }
        if ($m === 'application/pdf' || strpos($m, 'msword') !== false || strpos($m, 'officedocument') !== false || strpos($m, 'ms-excel') !== false) { return 'doc'; }
        return 'file';
    }

    /* ───────── Low-level (ανά storage_key/driver) ───────── */

    private static function localPath($key)
    {
        return self::localRoot() . '/' . ltrim($key, '/');
    }
    /** Αποθήκευση από τοπικό αρχείο. */
    public static function putFile($key, $srcPath, $mime, $driver = null, $bucket = null)
    {
        $driver = $driver ?: self::driver();
        if ($driver === 's3') {
            self::s3()->putObject(['Bucket' => $bucket ?: self::bucket(), 'Key' => $key, 'SourceFile' => $srcPath,
                'ContentType' => $mime ?: 'application/octet-stream', 'ACL' => 'private']);
            return true;
        }
        $dst = self::localPath($key);
        $dir = dirname($dst);
        if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
        return copy($srcPath, $dst);
    }
    /** Αποθήκευση από raw περιεχόμενο. */
    public static function putContents($key, $data, $mime, $driver = null, $bucket = null)
    {
        $driver = $driver ?: self::driver();
        if ($driver === 's3') {
            self::s3()->putObject(['Bucket' => $bucket ?: self::bucket(), 'Key' => $key, 'Body' => $data,
                'ContentType' => $mime ?: 'application/octet-stream', 'ACL' => 'private']);
            return true;
        }
        $dst = self::localPath($key);
        $dir = dirname($dst);
        if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
        return file_put_contents($dst, $data) !== false;
    }
    /** Επιστρέφει stream resource για ανάγνωση (ο caller κλείνει). */
    public static function readStream($key, $driver, $bucket = null)
    {
        if ($driver === 's3') {
            $r = self::s3()->getObject(['Bucket' => $bucket ?: self::bucket(), 'Key' => $key]);
            $body = $r['Body'];
            return $body->detach();   // php stream
        }
        $p = self::localPath($key);
        return is_file($p) ? fopen($p, 'rb') : false;
    }
    public static function exists($key, $driver, $bucket = null)
    {
        if ($driver === 's3') { return self::s3()->doesObjectExist($bucket ?: self::bucket(), $key); }
        return is_file(self::localPath($key));
    }
    public static function deleteKey($key, $driver, $bucket = null)
    {
        if ($driver === 's3') { self::s3()->deleteObject(['Bucket' => $bucket ?: self::bucket(), 'Key' => $key]); return true; }
        $p = self::localPath($key);
        return is_file($p) ? @unlink($p) : true;
    }
    /** Presigned GET URL (μόνο s3)· null για local (χρησιμοποίησε proxy). */
    public static function presignGetKey($key, $driver, $bucket, $ttl = 300, $downloadName = null)
    {
        if ($driver !== 's3') { return null; }
        $args = ['Bucket' => $bucket ?: self::bucket(), 'Key' => $key];
        if ($downloadName) { $args['ResponseContentDisposition'] = 'attachment; filename="' . addslashes($downloadName) . '"'; }
        $cmd = self::s3()->getCommand('GetObject', $args);
        return (string) self::s3()->createPresignedRequest($cmd, '+' . (int) $ttl . ' seconds')->getUri();
    }
    /** Presigned PUT URL για direct-to-S3 upload (μεγάλα/βίντεο)· null για local. */
    public static function presignPutKey($key, $mime, $ttl = 900, $bucket = null)
    {
        if (!self::isS3()) { return null; }
        $cmd = self::s3()->getCommand('PutObject', ['Bucket' => $bucket ?: self::bucket(), 'Key' => $key,
            'ContentType' => $mime ?: 'application/octet-stream', 'ACL' => 'private']);
        return (string) self::s3()->createPresignedRequest($cmd, '+' . (int) $ttl . ' seconds')->getUri();
    }

    /* ───────── High-level (μητρώο mod_cpm_storage) ───────── */

    /**
     * Αποθηκεύει αρχείο (από 'src' path ή 'contents') στον τρέχοντα driver + καταγράφει στο μητρώο.
     * $opts: module, ref_type, ref_id, orig_name, mime, src|contents, uploaded_by, meta(array)
     * Επιστρέφει την εγγραφή (array) ή ρίχνει \Throwable.
     */
    public static function store(array $opts)
    {
        $module = $opts['module'] ?? 'misc';
        $orig = (string) ($opts['orig_name'] ?? 'file');
        $mime = (string) ($opts['mime'] ?? 'application/octet-stream');
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $driver = self::driver();
        $bucket = $driver === 's3' ? self::bucket() : '';
        $key = self::newKey($module, $ext, $driver);

        if (!empty($opts['src'])) {
            $size = (int) @filesize($opts['src']);
            self::putFile($key, $opts['src'], $mime, $driver, $bucket);
        } else {
            $data = (string) ($opts['contents'] ?? '');
            $size = strlen($data);
            self::putContents($key, $data, $mime, $driver, $bucket);
        }
        $id = Capsule::table('mod_cpm_storage')->insertGetId([
            'module' => mb_substr($module, 0, 40), 'ref_type' => mb_substr((string) ($opts['ref_type'] ?? ''), 0, 40),
            'ref_id' => (int) ($opts['ref_id'] ?? 0), 'driver' => $driver, 'bucket' => $bucket, 'storage_key' => $key,
            'orig_name' => mb_substr($orig, 0, 255), 'mime' => mb_substr($mime, 0, 120), 'size' => $size,
            'kind' => self::kindFromMime($mime), 'uploaded_by' => (int) ($opts['uploaded_by'] ?? 0),
            'meta' => isset($opts['meta']) ? json_encode($opts['meta'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (array) Capsule::table('mod_cpm_storage')->where('id', $id)->first();
    }
    /** Καταχώρηση αρχείου που ανέβηκε ήδη με presigned PUT (χωρίς re-upload). */
    public static function registerExternal(array $opts)
    {
        $id = Capsule::table('mod_cpm_storage')->insertGetId([
            'module' => mb_substr($opts['module'] ?? 'misc', 0, 40), 'ref_type' => mb_substr((string) ($opts['ref_type'] ?? ''), 0, 40),
            'ref_id' => (int) ($opts['ref_id'] ?? 0), 'driver' => 's3', 'bucket' => self::bucket(),
            'storage_key' => (string) $opts['storage_key'], 'orig_name' => mb_substr((string) ($opts['orig_name'] ?? 'file'), 0, 255),
            'mime' => mb_substr((string) ($opts['mime'] ?? 'application/octet-stream'), 0, 120), 'size' => (int) ($opts['size'] ?? 0),
            'kind' => self::kindFromMime($opts['mime'] ?? ''), 'uploaded_by' => (int) ($opts['uploaded_by'] ?? 0),
            'meta' => isset($opts['meta']) ? json_encode($opts['meta'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (array) Capsule::table('mod_cpm_storage')->where('id', $id)->first();
    }

    public static function record($id)
    {
        $r = Capsule::table('mod_cpm_storage')->where('id', (int) $id)->first();
        return $r ? (array) $r : null;
    }
    /** Presigned GET (s3) ή null (local → proxy). */
    public static function presign($id, $ttl = 300, $asDownload = false)
    {
        $r = self::record($id);
        if (!$r || $r['driver'] !== 's3') { return null; }
        return self::presignGetKey($r['storage_key'], 's3', $r['bucket'], $ttl, $asDownload ? $r['orig_name'] : null);
    }
    /** Stream ανάγνωσης για proxy download (ο caller κάνει fpassthru + κλείσιμο). */
    public static function openRead($id)
    {
        $r = self::record($id);
        if (!$r) { return null; }
        return self::readStream($r['storage_key'], $r['driver'], $r['bucket']);
    }
    /** Απόλυτο τοπικό path (αν driver=local & υπάρχει) — π.χ. για AI/PDF ανάλυση. Αλλιώς null. */
    public static function localPathOf($id)
    {
        $r = self::record($id);
        if ($r && $r['driver'] === 'local') { $p = self::localPath($r['storage_key']); return is_file($p) ? $p : null; }
        return null;
    }
    /** Κατεβάζει το αρχείο σε προσωρινό local path (για S3 → π.χ. AI ανάλυση). Επιστρέφει path ή null. */
    public static function toTempFile($id)
    {
        $r = self::record($id);
        if (!$r) { return null; }
        if ($r['driver'] === 'local') { return self::localPathOf($id); }
        $tmp = tempnam(sys_get_temp_dir(), 'cpmstore');
        $in = self::readStream($r['storage_key'], $r['driver'], $r['bucket']);
        if (!$in) { return null; }
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($in); fclose($out);
        return $tmp;
    }
    public static function delete($id)
    {
        $r = self::record($id);
        if (!$r) { return false; }
        try { self::deleteKey($r['storage_key'], $r['driver'], $r['bucket']); } catch (\Throwable $e) {}
        Capsule::table('mod_cpm_storage')->where('id', (int) $id)->delete();
        return true;
    }

    /** Health check για το admin: επιστρέφει ['ok'=>bool,'msg'=>...]. */
    public static function s3Test()
    {
        if (!self::s3Configured()) { return ['ok' => false, 'msg' => 'Λείπουν ρυθμίσεις S3 (endpoint/bucket/key/secret)']; }
        try {
            $key = self::newKey('_healthcheck', 'txt', 's3');
            self::putContents($key, 'ok ' . date('c'), 'text/plain', 's3');
            $ok = self::exists($key, 's3');
            self::deleteKey($key, 's3');
            return $ok ? ['ok' => true, 'msg' => 'Σύνδεση S3 OK (put/get/delete)'] : ['ok' => false, 'msg' => 'Το αντικείμενο δεν επιβεβαιώθηκε'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => 'S3 error: ' . $e->getMessage()];
        }
    }
}
