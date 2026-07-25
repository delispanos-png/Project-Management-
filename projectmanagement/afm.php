<?php
/**
 * CloudOn — Δημόσιο endpoint άντλησης στοιχείων επιχείρησης από ΑΦΜ (ΑΑΔΕ RgWsPublic2).
 * Χρησιμοποιείται από τη φόρμα εγγραφής (register) όταν ο χρήστης δηλώνει «Επιχείρηση».
 * Δημόσιο (η register είναι δημόσια) → προστασία: έλεγχος ΑΦΜ (check-digit), throttle ανά IP,
 * cache 30 ημερών ώστε να μη χτυπάμε άσκοπα την ΑΑΔΕ. Επιστρέφει ΜΟΝΟ ασφαλή πεδία (ποτέ creds).
 */

$__cookies = $_COOKIE;
$_COOKIE = [];
@ini_set('session.use_cookies', '0');
@ini_set('session.use_only_cookies', '0');
define('WHMCS', true);
require __DIR__ . '/../init.php';
if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
$_COOKIE = $__cookies;

use WHMCS\Database\Capsule;
require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/Aade.php';
use WHMCS\Module\Addon\CloudonProjects\Aade;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function afm_out(array $p, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($p, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Είσοδος ──
$afm = preg_replace('/\D+/', '', (string) ($_REQUEST['afm'] ?? ''));
if ($afm === '') {
    afm_out(['ok' => false, 'error' => 'Λείπει το ΑΦΜ.'], 400);
}
if (!Aade::validAfm($afm)) {
    afm_out(['ok' => false, 'error' => 'Μη έγκυρο ΑΦΜ.', 'code' => 'AFM_INVALID'], 200);
}

// ── Throttle ανά IP (max 30 / ώρα) ──
$ip  = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
$ip  = trim(explode(',', $ip)[0]);
$now = time();
try {
    Capsule::table('mod_cpm_afm_hits')->where('ts', '<', $now - 3600)->delete();
    $recent = Capsule::table('mod_cpm_afm_hits')->where('ip', $ip)->where('ts', '>=', $now - 3600)->count();
    if ($recent >= 30) {
        afm_out(['ok' => false, 'error' => 'Πολλές αναζητήσεις. Δοκιμάστε αργότερα.', 'code' => 'THROTTLED'], 429);
    }
    Capsule::table('mod_cpm_afm_hits')->insert(['ip' => $ip, 'ts' => $now]);
} catch (\Throwable $e) { /* μη μπλοκάρεις σε σφάλμα throttle */ }

// ── Cache (30 ημέρες) ──
try {
    $c = Capsule::table('mod_cpm_afm_cache')->where('afm', $afm)->first();
    if ($c && ($now - (int) $c->ts) < 2592000) {
        $data = json_decode($c->data, true);
        if (is_array($data)) {
            afm_out(['ok' => true, 'data' => $data, 'cached' => true]);
        }
    }
} catch (\Throwable $e) { /* αγνόησε cache σφάλμα */ }

// ── Κλήση ΑΑΔΕ ──
if (!Aade::enabled()) {
    afm_out(['ok' => false, 'error' => 'Η υπηρεσία δεν είναι διαθέσιμη.', 'code' => 'NOT_CONFIGURED'], 503);
}
$r = Aade::lookup($afm);
if (!empty($r['ok'])) {
    try {
        Capsule::table('mod_cpm_afm_cache')->updateOrInsert(
            ['afm' => $afm],
            ['data' => json_encode($r['data'], JSON_UNESCAPED_UNICODE), 'ts' => $now]
        );
    } catch (\Throwable $e) { /* cache best-effort */ }
    afm_out(['ok' => true, 'data' => $r['data']]);
}
afm_out(['ok' => false, 'error' => $r['error'] ?? 'Αποτυχία άντλησης.', 'code' => $r['code'] ?? 'ERR'], 200);
