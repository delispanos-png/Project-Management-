<?php
/**
 * CloudOn PM — Web Push (VAPID), χωρίς κρυπτογραφημένο payload.
 * Στέλνει «κενό» push που ξυπνά τον service worker· εκείνος τραβά το περιεχόμενο
 * (push_latest) και δείχνει την ειδοποίηση. Έτσι αποφεύγεται όλη η aes128gcm
 * κρυπτογράφηση και μένει καθαρός PHP + openssl (ES256 VAPID JWT).
 */
namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Push
{
    private static function cfg($k)
    {
        return (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', $k)->value('value') ?: '');
    }
    private static function b64u($x) { return rtrim(strtr(base64_encode($x), '+/', '-_'), '='); }

    public static function enabled() { return self::cfg('vapid_private') !== '' && self::cfg('vapid_public') !== ''; }
    public static function publicKey() { return self::cfg('vapid_public'); }

    /** VAPID JWT (ES256) για το origin του endpoint. */
    private static function jwtFor($endpoint)
    {
        $priv = self::cfg('vapid_private');
        $pub = self::cfg('vapid_public');
        $sub = self::cfg('vapid_subject') ?: 'mailto:support@cloudon.gr';
        if ($priv === '' || $pub === '') { return null; }
        $u = parse_url($endpoint);
        if (!$u || empty($u['host'])) { return null; }
        $aud = ($u['scheme'] ?? 'https') . '://' . $u['host'];
        $header = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64u(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => $sub]));
        $input = $header . '.' . $claims;
        $der = '';
        if (!openssl_sign($input, $der, $priv, OPENSSL_ALGO_SHA256)) { return null; }
        $raw = self::derToRaw($der);
        if ($raw === null) { return null; }
        return ['jwt' => $input . '.' . self::b64u($raw), 'pub' => $pub];
    }

    /** DER ECDSA (SEQUENCE{INTEGER r, INTEGER s}) → raw 64 bytes (R||S). */
    private static function derToRaw($der)
    {
        $o = 0;
        if (($der[$o++] ?? '') !== "\x30") { return null; }
        $l = ord($der[$o++]);
        if ($l & 0x80) { $o += ($l & 0x7f); }
        $r = self::readInt($der, $o); if ($r === null) { return null; }
        $s = self::readInt($der, $o); if ($s === null) { return null; }
        $r = ltrim($r, "\0"); $s = ltrim($s, "\0");
        if (strlen($r) > 32 || strlen($s) > 32) { return null; }
        return str_pad($r, 32, "\0", STR_PAD_LEFT) . str_pad($s, 32, "\0", STR_PAD_LEFT);
    }
    private static $rp = 0;
    private static function readInt($der, &$o)
    {
        if (($der[$o++] ?? '') !== "\x02") { return null; }
        $len = ord($der[$o++]);
        $val = substr($der, $o, $len);
        $o += $len;
        return $val;
    }

    /** Στέλνει σε όλες τις συσκευές ενός χρήστη· κλαδεύει τις νεκρές (404/410). */
    public static function send($adminId)
    {
        if (!self::enabled()) { return; }
        foreach (Capsule::table('mod_cpm_push_subs')->where('admin_id', (int) $adminId)->get() as $sub) {
            $code = self::sendOne($sub->endpoint);
            if ($code === 404 || $code === 410) {
                Capsule::table('mod_cpm_push_subs')->where('id', $sub->id)->delete();
            } elseif ($code >= 200 && $code < 300) {
                Capsule::table('mod_cpm_push_subs')->where('id', $sub->id)->update(['used_at' => date('Y-m-d H:i:s')]);
            }
        }
    }

    public static function sendOne($endpoint)
    {
        $v = self::jwtFor($endpoint);
        if (!$v) { return 0; }
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => [
                'Authorization: vapid t=' . $v['jwt'] . ', k=' . $v['pub'],
                'TTL: 2419200',
                'Content-Length: 0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }
}
