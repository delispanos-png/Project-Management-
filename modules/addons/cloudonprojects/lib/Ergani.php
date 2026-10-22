<?php

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

/**
 * ΕΡΓΑΝΗ ΙΙ — Web API του Υπουργείου Εργασίας (22/10/2026).
 *
 * ΤΙ ΚΑΝΕΙ ΤΟ API: δέχεται δηλώσεις του εργοδότη (κάρτα εργασίας, ωράρια, ΑΔΕΙΕΣ στην
 * «Οργάνωση Χρόνου Εργασίας») και δίνει λίγες αναγνώσεις: στοιχεία εργοδότη/παραρτημάτων,
 * λίστες κωδικών, το PDF μιας δήλωσης που έγινε, και «υπηρεσίες εξαγωγής» (ServicesList /
 * ExecuteService). ΔΕΝ υπάρχει δημοσιευμένο «δώσε μου όλες τις άδειες» — γι' αυτό το
 * σύστημά μας μένει η πηγή των αδειών και ενημερώνει το ΕΡΓΑΝΗ, και ό,τι μπορεί να
 * διαβαστεί πίσω (π.χ. μηνιαίο αρχείο απασχόλησης) χρησιμοποιείται ως έλεγχος.
 *
 * ΠΕΡΙΒΑΛΛΟΝΤΑ: trial (trialv2eservices — ό,τι υποβάλλεται σφραγίζεται «ΑΚΥΡΟ») και
 * prod. Ίδια διαπιστευτήρια και στα δύο. Ξεκινάμε ΠΑΝΤΑ από το trial.
 *
 * ΣΥΝΔΕΣΗ: POST Authentication {Username, Password, UserType} → JWT (3 ώρες).
 * UserType 01 = εργοδότης με κωδικούς e-ΕΦΚΑ · 02 = χρήστης ΕΡΓΑΝΗ (παραρτήματος).
 * Ο κωδικός ζει ΚΡΥΠΤΟΓΡΑΦΗΜΕΝΟΣ στη βάση (το api τον αποκρυπτογραφεί και τον δίνει
 * εδώ) και δεν επιστρέφει ποτέ στον browser.
 */
class Ergani
{
    const HOSTS = [
        'trial' => 'https://trialv2eservices.yeka.gr/WebServicesAPI/api/',
        'prod'  => 'https://eservices.yeka.gr/WebServicesAPI/api/',
    ];

    /** Ρυθμίσεις (χωρίς τον κωδικό σε καθαρό κείμενο). */
    public static function cfg($key, $def = '')
    {
        $v = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'ergani_' . $key)->value('value');
        return $v === null ? $def : (string) $v;
    }

    public static function setCfg($key, $val)
    {
        $q = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', 'ergani_' . $key);
        if ($q->exists()) { $q->update(['value' => (string) $val]); }
        else { Capsule::table('tbladdonmodules')->insert(['module' => 'cloudonprojects', 'setting' => 'ergani_' . $key, 'value' => (string) $val]); }
    }

    public static function env()
    {
        return self::cfg('env', 'trial') === 'prod' ? 'prod' : 'trial';
    }

    /**
     * Ένα αίτημα προς το API. Επιστρέφει ['ok', 'status', 'json', 'raw', 'error'].
     * Τα μηνύματα σφάλματος του ΕΡΓΑΝΗ περνούν αυτούσια — είναι στα ελληνικά και λένε το γιατί.
     */
    public static function http($method, $path, $body = null, $token = null, $env = null)
    {
        $url = self::HOSTS[$env ?: self::env()] . ltrim($path, '/');
        $ch = curl_init($url);
        $h = ['Accept: application/json'];
        if ($token) { $h[] = 'Authorization: Bearer ' . $token; }
        if ($body !== null) { $h[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE)); }
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 10]);
        $raw = curl_exec($ch);
        $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) { return ['ok' => false, 'status' => 0, 'json' => null, 'raw' => '', 'error' => 'Δεν απάντησε το ΕΡΓΑΝΗ: ' . $err]; }
        $j = json_decode($raw, true);
        $ok = $st >= 200 && $st < 300;
        return ['ok' => $ok, 'status' => $st, 'json' => $j, 'raw' => mb_substr((string) $raw, 0, 4000),
            'error' => $ok ? '' : self::errText($st, $j, $raw)];
    }

    private static function errText($st, $j, $raw)
    {
        if (is_string($j) && trim($j) !== '') { return $j . ' (HTTP ' . $st . ')'; }   // π.χ. «Τα στοιχεία που εισάγατε δεν είναι σωστά.»
        if (is_array($j)) {
            foreach (['message', 'Message', 'error', 'title', 'detail'] as $k) {
                if (!empty($j[$k]) && is_string($j[$k])) { return $j[$k] . ' (HTTP ' . $st . ')'; }
            }
        }
        if ($st === 401) { return 'Λάθος στοιχεία σύνδεσης ή λάθος τύπος χρήστη (HTTP 401)'; }
        return 'HTTP ' . $st . ($raw ? ': ' . mb_substr(strip_tags((string) $raw), 0, 200) : '');
    }

    /**
     * Token για το τρέχον περιβάλλον. Κρατιέται (κρυπτογραφημένο από τον καλούντα δεν
     * χρειάζεται — λήγει σε 3 ώρες) ώστε να μη γίνεται login σε κάθε κλήση.
     */
    public static function token($user, $pass, $userType, $force = false)
    {
        $env = self::env();
        $cache = json_decode(self::cfg('token_cache', ''), true);
        if (!$force && is_array($cache) && ($cache['env'] ?? '') === $env && ($cache['user'] ?? '') === $user
            && ($cache['exp'] ?? 0) > time() + 120 && !empty($cache['t'])) {
            return ['ok' => true, 'token' => $cache['t']];
        }
        $r = self::http('POST', 'Authentication', ['Username' => $user, 'Password' => $pass, 'UserType' => $userType]);
        $t = is_array($r['json']) ? ($r['json']['accessToken'] ?? $r['json']['AccessToken'] ?? '') : '';
        if (!$r['ok'] || !$t) {
            return ['ok' => false, 'error' => $r['error'] ?: 'Δεν επιστράφηκε token'];
        }
        self::setCfg('token_cache', json_encode(['env' => $env, 'user' => $user, 't' => $t, 'exp' => time() + 3 * 3600 - 300]));
        return ['ok' => true, 'token' => $t];
    }

    public static function forgetToken()
    {
        self::setCfg('token_cache', '');
    }

    /**
     * «Τι μπορούμε να κάνουμε»: οι δηλώσεις που δέχεται (Lookup/Submissions) και οι
     * υπηρεσίες ανάγνωσης (WebServices/ServicesList). Από εδώ μαθαίνουμε τον ΑΚΡΙΒΗ κωδικό
     * της δήλωσης αδειών και αν υπάρχει εξαγωγή αδειών/μηνιαίου αρχείου.
     */
    public static function explore($token)
    {
        $out = [];
        foreach (['submissions' => 'Lookup/Submissions', 'services' => 'WebServices/ServicesList'] as $k => $p) {
            $r = self::http('GET', $p, null, $token);
            $out[$k] = $r['ok'] ? $r['json'] : ['error' => $r['error']];
        }
        return $out;
    }

    /** Το JSON πρότυπο μιας δήλωσης (π.χ. της δήλωσης αδειών) — για να χτιστεί σωστά η αποστολή. */
    public static function template($token, $code)
    {
        return self::http('GET', 'Documents/' . rawurlencode($code), null, $token);
    }

    /** Εκτέλεση υπηρεσίας ανάγνωσης (EX_BASE_01 εργοδότης, EX_BASE_02 παραρτήματα, EX_BASE_03 λίστες…). */
    public static function service($token, $code, array $params = [])
    {
        return self::http('POST', 'WebServices/ExecuteService', ['ServiceCode' => $code, 'Parameters' => $params], $token);
    }
}
