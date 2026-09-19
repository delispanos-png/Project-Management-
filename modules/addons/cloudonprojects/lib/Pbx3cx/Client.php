<?php
/**
 * CloudOn Agent — 3CX Integration Layer · πελάτης σύνδεσης.
 *
 * ΜΟΝΟ αυτή η κλάση ξέρει ότι από κάτω υπάρχει 3CX. Η υπόλοιπη εφαρμογή μιλάει
 * σε επίπεδο «κλήση / χειριστής / πελάτης» και δεν εξαρτάται από το PBX API.
 *
 * Auth: OAuth2 client_credentials στο /connect/token — επαληθεύτηκε από το
 * /.well-known/openid-configuration του δικού μας PBX (19/09/2026):
 *   grant_types_supported: authorization_code, refresh_token, client_credentials
 *   token_endpoint_auth_methods_supported: none, client_secret_post, private_key_jwt
 *
 * Το secret ΔΕΝ αποθηκεύεται ποτέ σε καθαρό κείμενο: περνά από το ίδιο
 * AES-256-GCM vault που χρησιμοποιούν ήδη οι κωδικοί (cnp_vault_enc).
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Pbx3cxClient
{
    /** Ρυθμίσεις από tbladdonmodules — ποτέ από το repo. */
    public static function cfg($key, $default = '')
    {
        $v = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'pbx3cx_' . $key)->value('value');
        return $v === null ? $default : (string) $v;
    }

    public static function setCfg($key, $value)
    {
        Capsule::table('tbladdonmodules')->updateOrInsert(
            ['module' => 'cloudonprojects', 'setting' => 'pbx3cx_' . $key],
            ['value' => (string) $value]);
    }

    /** Η βάση του PBX, πάντα https και χωρίς κατάληξη «/». */
    public static function baseUrl()
    {
        $u = trim(self::cfg('url'));
        if ($u === '') { return ''; }
        if (!preg_match('#^https?://#i', $u)) { $u = 'https://' . $u; }
        return rtrim($u, '/');
    }

    public static function configured()
    {
        return self::baseUrl() !== '' && self::cfg('client_id') !== '' && self::cfg('secret') !== '';
    }

    /**
     * Το κλειδί του vault — ΤΟ ΙΔΙΟ που χρησιμοποιούν οι κωδικοί (pm_vault_key).
     * Υλοποιείται εδώ και όχι μέσω cnp_vault_dec() του api.php, γιατί η κλάση
     * πρέπει να δουλεύει ΚΑΙ από cron/worker, όπου το api.php δεν φορτώνεται.
     * (Βρέθηκε στη δοκιμή: από CLI η αποκρυπτογράφηση γύριζε κενό και το 3CX
     * απαντούσε invalid_client — σιωπηλή αποτυχία που θα χτυπούσε στη Φάση 5.)
     */
    private static function vaultKey()
    {
        $v = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'pm_vault_key')->value('value');
        return $v ? base64_decode($v) : '';
    }

    /** Το secret αποκρυπτογραφημένο — μένει στη μνήμη, δεν φεύγει ποτέ στο UI. */
    private static function secret()
    {
        $blob = self::cfg('secret');
        if ($blob === '') { return ''; }
        $key = self::vaultKey();
        if ($key === '') { return ''; }
        $raw = base64_decode($blob);
        if (strlen($raw) < 28) { return ''; }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct = substr($raw, 28);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : (string) $plain;
    }

    /**
     * Access token με cache. Ανανεώνεται 60΄΄ πριν λήξει, ώστε να μη σκάσει
     * αίτημα πάνω στη λήξη.
     */
    public static function token($force = false)
    {
        static $mem = null;
        $now = time();
        if (!$force && $mem && $mem['exp'] > $now + 60) { return $mem['token']; }
        if (!$force) {
            $cached = self::cfg('token_cache');
            if ($cached !== '') {
                $c = json_decode($cached, true);
                if (is_array($c) && ($c['exp'] ?? 0) > $now + 60) {
                    $mem = $c;
                    return $c['token'];
                }
            }
        }
        if (!self::configured()) {
            throw new \RuntimeException('Η διασύνδεση 3CX δεν έχει ρυθμιστεί');
        }
        $r = self::http('POST', self::baseUrl() . '/connect/token', [
            'grant_type' => 'client_credentials',
            'client_id' => self::cfg('client_id'),
            'client_secret' => self::secret(),
        ], null, true);
        if ($r['code'] !== 200) {
            self::log('auth', 'error', 'Token HTTP ' . $r['code'] . ' — ' . mb_substr((string) $r['body'], 0, 300));
            throw new \RuntimeException('Απέτυχε η ταυτοποίηση στο 3CX (HTTP ' . $r['code'] . ')');
        }
        $j = json_decode($r['body'], true);
        if (!is_array($j) || empty($j['access_token'])) {
            throw new \RuntimeException('Το 3CX δεν επέστρεψε token');
        }
        $mem = ['token' => $j['access_token'], 'exp' => $now + max(60, (int) ($j['expires_in'] ?? 3600))];
        self::setCfg('token_cache', json_encode($mem));
        self::log('auth', 'ok', 'Νέο token, ισχύει ' . (int) ($j['expires_in'] ?? 0) . '΄΄');
        return $mem['token'];
    }

    /**
     * Κλήση στο XAPI. Ένα retry σε 401 (ληγμένο token) — πέρα από αυτό,
     * σφάλμα προς τα πάνω· δεν κρύβουμε αποτυχίες.
     */
    public static function xapi($path, array $query = [], $timeout = 25)
    {
        $url = self::baseUrl() . '/xapi/v1/' . ltrim($path, '/');
        if ($query) { $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query); }
        for ($try = 0; $try < 2; $try++) {
            $r = self::http('GET', $url, null, self::token($try > 0), false, $timeout);
            if ($r['code'] === 401 && $try === 0) { continue; }
            if ($r['code'] === 403) {
                self::log('xapi', 'error', $path . ' → 403 (ο ρόλος του API client δεν το επιτρέπει)');
                throw new \RuntimeException('δεν το επιτρέπει ο ρόλος του API client (403)');
            }
            if ($r['code'] < 200 || $r['code'] >= 300) {
                self::log('xapi', 'error', $path . ' → HTTP ' . $r['code']);
                throw new \RuntimeException('3CX XAPI HTTP ' . $r['code'] . ' στο ' . $path);
            }
            return json_decode($r['body'], true);
        }
        throw new \RuntimeException('3CX XAPI: αποτυχία ταυτοποίησης');
    }

    /**
     * Γράψιμο στο XAPI — POST / PATCH / DELETE.
     *
     * Ξεχωριστή από το xapi() επίτηδες: το διάβασμα είναι ακίνδυνο και γίνεται
     * παντού, το γράψιμο αλλάζει το τηλεφωνικό κέντρο και πρέπει να φαίνεται
     * στον κώδικα ποιος το κάνει. Κάθε επιτυχία καταγράφεται.
     *
     * @return array|null  το σώμα της απάντησης, ή null σε 204
     */
    public static function xwrite($method, $path, array $body = null, $timeout = 25)
    {
        $method = strtoupper($method);
        if (!in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
            throw new \RuntimeException('Μη επιτρεπτή μέθοδος: ' . $method);
        }
        $url = self::baseUrl() . '/xapi/v1/' . ltrim($path, '/');
        for ($try = 0; $try < 2; $try++) {
            $r = self::http($method, $url, $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE),
                self::token($try > 0), true, $timeout);
            if ($r['code'] === 401 && $try === 0) { continue; }
            if ($r['code'] === 403) {
                self::log('xapi', 'error', $method . ' ' . $path . ' → 403 (ο ρόλος δεν επιτρέπει εγγραφή)');
                throw new \RuntimeException('Ο ρόλος του API client δεν επιτρέπει εγγραφή στο 3CX (403)');
            }
            if ($r['code'] < 200 || $r['code'] >= 300) {
                self::log('xapi', 'error', $method . ' ' . $path . ' → HTTP ' . $r['code']
                    . ($r['body'] ? ' · ' . mb_substr(preg_replace('/\s+/', ' ', $r['body']), 0, 160) : ''));
                throw new \RuntimeException('Το 3CX απάντησε HTTP ' . $r['code']);
            }
            return $r['body'] !== '' ? json_decode($r['body'], true) : null;
        }
        throw new \RuntimeException('3CX XAPI: αποτυχία ταυτοποίησης');
    }

    /**
     * Ανέβασμα αρχείων (multipart) — ΜΟΝΟ για τη βάση γνώσης των AI agents.
     *
     * ΜΕΤΡΗΘΗΚΕ από τον web client του 3CX (chunk 4146): POST
     * /xapi/v1/AiSettings/UploadVectorFiles με πεδίο «files» (πολλά), απάντηση
     * λίστα {FileName, Status, ExternalFileId}. Δεν υπάρχει στο OData $metadata.
     *
     * @param array $files  [όνομα => τοπική διαδρομή]
     * @return array        η απάντηση του PBX (μία γραμμή ανά αρχείο)
     */
    public static function upload($path, array $files, $timeout = 120)
    {
        $url = self::baseUrl() . '/xapi/v1/' . ltrim($path, '/');
        $post = [];
        foreach ($files as $name => $local) {
            $post['files[' . count($post) . ']'] = new \CURLFile($local, 'text/markdown', $name);
        }
        for ($try = 0; $try < 2; $try++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . self::token($try > 0)],
            ]);
            $body = (string) curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 401 && $try === 0) { continue; }
            if ($code < 200 || $code >= 300) {
                self::log('xapi', 'error', 'UPLOAD ' . $path . ' → HTTP ' . $code . ' · ' . mb_substr(preg_replace('/\s+/', ' ', $body), 0, 160));
                throw new \RuntimeException('Το 3CX απάντησε HTTP ' . $code . ' στο ανέβασμα');
            }
            return json_decode($body, true) ?: [];
        }
        throw new \RuntimeException('3CX XAPI: αποτυχία ταυτοποίησης');
    }

    /**
     * ΦΑΣΗ 0 — «πάγωμα συμβολαίου».
     * Ρωτάει το PBX τι ΑΚΡΙΒΩΣ υποστηρίζει, ώστε να μη σχεδιάζουμε στα τυφλά.
     * Δεν πετάει ποτέ: κάθε έλεγχος γυρίζει τη δική του κατάσταση.
     */
    public static function probe()
    {
        $out = ['url' => self::baseUrl(), 'at' => date('Y-m-d H:i:s'), 'checks' => []];
        /* ΚΡΙΣΙΜΟ vs ΠΡΟΑΙΡΕΤΙΚΟ: ο ρόλος του API client στο 3CX δεν δίνει τα
           πάντα ταυτόχρονα — με τον ρόλο που διαβάζει ιστορικό, χάνονται κάποιες
           διαγνωστικές αναγνώσεις (SystemStatus, CDRSettings). Αυτό ΔΕΝ είναι
           βλάβη: η διασύνδεση δουλεύει. Γι' αυτό μόνο τα κρίσιμα μετράνε στο
           σκορ· τα προαιρετικά εμφανίζονται ως πληροφορία. */
        $add = function ($key, $label, $cb, $critical = true) use (&$out) {
            $t0 = microtime(true);
            try {
                $v = $cb();
                $out['checks'][$key] = ['label' => $label, 'ok' => true, 'info' => $v,
                    'critical' => $critical, 'ms' => (int) round((microtime(true) - $t0) * 1000)];
            } catch (\Throwable $e) {
                $out['checks'][$key] = ['label' => $label, 'ok' => false, 'critical' => $critical,
                    'info' => $e->getMessage(), 'ms' => (int) round((microtime(true) - $t0) * 1000)];
            }
        };
        $add('oidc', 'Ανακάλυψη OAuth', function () {
            $r = self::http('GET', self::baseUrl() . '/.well-known/openid-configuration');
            if ($r['code'] !== 200) { throw new \RuntimeException('HTTP ' . $r['code']); }
            $j = json_decode($r['body'], true);
            return 'grants: ' . implode(', ', $j['grant_types_supported'] ?? []);
        });
        $add('token', 'Ταυτοποίηση', function () {
            $t = self::token(true);
            return 'token μήκους ' . strlen($t);
        });
        /* Το Version είναι ΙΔΙΟΤΗΤΑ του singleton SystemStatus — όχι bound function.
           (Το GetVersionType() είναι IsBound σε Pbx.SystemStatus· δεν καλείται από τη ρίζα.) */
        $add('version', 'Έκδοση PBX', function () {
            $j = self::xapi('SystemStatus');
            $v = $j['Version'] ?? '';
            if ($v === '') { throw new \RuntimeException('δεν επιστράφηκε έκδοση'); }
            self::setCfg('pbx_version', $v);
            return 'v' . $v . ' · ' . (int) ($j['ExtensionsTotal'] ?? 0) . ' extensions, '
                . (int) ($j['TrunksRegistered'] ?? 0) . '/' . (int) ($j['TrunksTotal'] ?? 0) . ' trunks';
        }, false);
        $add('users', 'Χρήστες / extensions', function () {
            $j = self::xapi('Users', ['$top' => 1, '$count' => 'true']);
            return ($j['@odata.count'] ?? count($j['value'] ?? [])) . ' extensions προς χαρτογράφηση';
        });
        $add('map', 'Χαρτογράφηση DN → χειριστές', function () {
            /* Το $select ΠΡΕΠΕΙ να περιλαμβάνει Id και το $top να μένει λογικό —
               αλλιώς το XAPI απαντά 400 (επαληθεύτηκε στη δοκιμή). */
            $j = self::xapi('Users', ['$top' => 50, '$select' => 'Id,Number,FirstName,LastName,EmailAddress']);
            $mails = [];
            foreach (Capsule::table('tbladmins')->where('disabled', 0)->get(['email']) as $a) {
                $mails[mb_strtolower(trim((string) $a->email))] = true;
            }
            $hit = 0; $tot = 0;
            foreach ($j['value'] ?? [] as $u) {
                $tot++;
                $em = mb_strtolower(trim((string) ($u['EmailAddress'] ?? '')));
                if ($em !== '' && isset($mails[$em])) { $hit++; }
            }
            return $hit . ' από ' . $tot . ' ταυτίζονται αυτόματα με email';
        });
        $add('history', 'Τελευταία κλήση στο PBX', function () {
            /* ΔΕΝ αγγίζουμε το CallHistoryView. Είναι αρχείο ~123.000 γραμμών και
               κάθε ερώτημα πάνω του σαρώνει τα πάντα — ακόμη και $top=1 κάνει
               timeout. Στις 19/09/2026 τέτοια ερωτήματα έριξαν την PostgreSQL
               του PBX: τα τηλέφωνα χτυπούσαν, αλλά κανείς δεν μπορούσε να
               συνδεθεί (HTTP 500 σε κάθε έκδοση token, web login και API).
               Ο LastCdrAndChatMessageTimestamp δίνει την ίδια πληροφορία
               ακαριαία. Τα τρέχοντα δεδομένα έρχονται από το CDR. */
            $l = self::xapi('LastCdrAndChatMessageTimestamp', [], 10);
            $last = (string) ($l['value'][0]['LastCdrStartedAt'] ?? '');
            if ($last === '') { throw new \RuntimeException('δεν επιστράφηκε χρόνος'); }
            return substr(str_replace('T', ' ', $last), 0, 16);
        }, false);
        $add('cdr', 'CDR (εφεδρικός δρόμος)', function () {
            $j = self::xapi('CDRSettings');
            if (empty($j['Enabled'])) { throw new \RuntimeException('απενεργοποιημένο'); }
            return 'ενεργό · ' . ($j['LogType'] ?? '—');
        }, false);
        $add('ai', 'AI (περίληψη/απομαγνητοφώνηση)', function () {
            $j = self::xapi('AISettings');
            if (empty($j['Enabled'])) { throw new \RuntimeException('απενεργοποιημένο στο PBX'); }
            return 'ενεργό · ' . ($j['Provider'] ?? '—');
        });
        $add('callcontrol', 'Call Control API', function () {
            $r = self::http('GET', self::baseUrl() . '/callcontrol', null, self::token());
            if ($r['code'] === 401 || $r['code'] === 403) { throw new \RuntimeException('δεν το επιτρέπει ο ρόλος (HTTP ' . $r['code'] . ')'); }
            if ($r['code'] >= 400) { throw new \RuntimeException('HTTP ' . $r['code']); }
            return 'διαθέσιμο';
        });
        $ok = 0; $tot = 0;
        foreach ($out['checks'] as $c) {
            if (empty($c['critical'])) { continue; }
            $tot++;
            if ($c['ok']) { $ok++; }
        }
        $out['ok'] = $ok;
        $out['total'] = $tot;
        self::setCfg('last_probe', json_encode($out, JSON_UNESCAPED_UNICODE));
        return $out;
    }

    /** Τεχνικό ημερολόγιο — για debugging χωρίς να ψάχνουμε σε δύο συστήματα. */
    public static function log($channel, $status, $message)
    {
        try {
            Capsule::table('mod_cpm_pbx_log')->insert([
                'channel' => mb_substr($channel, 0, 16), 'status' => mb_substr($status, 0, 10),
                'message' => mb_substr((string) $message, 0, 500), 'created_at' => date('Y-m-d H:i:s')]);
            /* Κράτα τις 500 τελευταίες — το log δεν γίνεται αρχείο. */
            $min = Capsule::table('mod_cpm_pbx_log')->orderBy('id', 'desc')->skip(500)->take(1)->value('id');
            if ($min) { Capsule::table('mod_cpm_pbx_log')->where('id', '<=', $min)->delete(); }
        } catch (\Throwable $e) { /* το log δεν χαλάει ποτέ τη ροή */ }
    }

    /** Ένα σημείο για κάθε HTTP — ώστε timeouts/TLS να ρυθμίζονται μία φορά. */
    private static function http($method, $url, $form = null, $token = null, $isAuth = false, $timeout = 25)
    {
        $ch = curl_init($url);
        $h = ['Accept: application/json'];
        if ($token) { $h[] = 'Authorization: Bearer ' . $token; }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(5, (int) $timeout),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($form !== null) {
            /* Πίνακας = OAuth (form-encoded). Έτοιμο κείμενο = JSON για το XAPI:
               το /connect/token θέλει form, τα Contacts θέλουν JSON. */
            if (is_string($form)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
                $h[] = 'Content-Type: application/json';
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
                $h[] = 'Content-Type: application/x-www-form-urlencoded';
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) { return ['code' => 0, 'body' => $err]; }
        return ['code' => $code, 'body' => (string) $body];
    }
}
