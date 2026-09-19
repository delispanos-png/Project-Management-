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

    /** Το secret αποκρυπτογραφημένο — μένει στη μνήμη, δεν φεύγει ποτέ στο UI. */
    private static function secret()
    {
        $blob = self::cfg('secret');
        if ($blob === '') { return ''; }
        return function_exists('cnp_vault_dec') ? (string) cnp_vault_dec($blob) : '';
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
    public static function xapi($path, array $query = [])
    {
        $url = self::baseUrl() . '/xapi/v1/' . ltrim($path, '/');
        if ($query) { $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query); }
        for ($try = 0; $try < 2; $try++) {
            $r = self::http('GET', $url, null, self::token($try > 0));
            if ($r['code'] === 401 && $try === 0) { continue; }
            if ($r['code'] < 200 || $r['code'] >= 300) {
                self::log('xapi', 'error', $path . ' → HTTP ' . $r['code']);
                throw new \RuntimeException('3CX XAPI HTTP ' . $r['code'] . ' στο ' . $path);
            }
            return json_decode($r['body'], true);
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
        $add = function ($key, $label, $cb) use (&$out) {
            $t0 = microtime(true);
            try {
                $v = $cb();
                $out['checks'][$key] = ['label' => $label, 'ok' => true, 'info' => $v,
                    'ms' => (int) round((microtime(true) - $t0) * 1000)];
            } catch (\Throwable $e) {
                $out['checks'][$key] = ['label' => $label, 'ok' => false,
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
        $add('version', 'Έκδοση PBX', function () {
            $j = self::xapi('GetVersionType()');
            return is_array($j) ? json_encode($j['value'] ?? $j, JSON_UNESCAPED_UNICODE) : (string) $j;
        });
        $add('users', 'Χρήστες / extensions', function () {
            $j = self::xapi('Users', ['$top' => 1, '$count' => 'true']);
            return ($j['@odata.count'] ?? count($j['value'] ?? [])) . ' extensions';
        });
        $add('calls', 'Ιστορικό κλήσεων', function () {
            $j = self::xapi('ActiveCalls', ['$top' => 1]);
            return 'ActiveCalls OK (' . count($j['value'] ?? []) . ' ενεργές τώρα)';
        });
        $add('callcontrol', 'Call Control API', function () {
            $r = self::http('GET', self::baseUrl() . '/callcontrol', null, self::token());
            if ($r['code'] === 401 || $r['code'] === 403) { throw new \RuntimeException('δεν το επιτρέπει ο ρόλος (HTTP ' . $r['code'] . ')'); }
            if ($r['code'] >= 400) { throw new \RuntimeException('HTTP ' . $r['code']); }
            return 'διαθέσιμο';
        });
        $ok = 0;
        foreach ($out['checks'] as $c) { if ($c['ok']) { $ok++; } }
        $out['ok'] = $ok;
        $out['total'] = count($out['checks']);
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
    private static function http($method, $url, $form = null, $token = null, $isAuth = false)
    {
        $ch = curl_init($url);
        $h = ['Accept: application/json'];
        if ($token) { $h[] = 'Authorization: Bearer ' . $token; }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($form !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
            $h[] = 'Content-Type: application/x-www-form-urlencoded';
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
