<?php
/**
 * CloudOn Projects — isolated bootstrap.
 *
 * Το app ΔΕΝ μοιράζεται το WHMCS session (το WHMCS διαγράφει/regenerate-άρει
 * admin sessions εκτός του admin path — θα έκανε logout τον διαχειριστή).
 * Αντ' αυτού:
 *   1. Φορτώνει το WHMCS init.php ΜΟΝΟ για τη βάση (Capsule), με απομονωμένο
 *      throwaway session ώστε να ΜΗΝ πειράξει το πραγματικό admin cookie.
 *   2. Ξεκινά δικό του session (CNPPMSESS, scoped στο /projectmanagement/).
 *   3. Auth μέσω signed token handoff από το WHMCS admin (μία φορά) → app session.
 *
 * Ορίζει: pm_admin_id() και τη σταθερά PM_BOOTED.
 */

if (defined('PM_BOOTED')) {
    return;
}

/* ---- 1. Απομονωμένη φόρτωση WHMCS (μόνο για DB) ---- */
$__cookies = $_COOKIE;
$_COOKIE = [];                                   // init.php δεν βλέπει το admin cookie → throwaway session
@ini_set('session.use_cookies', '0');            // κανένα Set-Cookie από το WHMCS session
@ini_set('session.use_only_cookies', '0');

define('WHMCS', true);
require_once __DIR__ . '/../init.php';

if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();                       // κλείσε το throwaway WHMCS session
}
$_COOKIE = $__cookies;                            // επανάφερε τα cookies για το ΔΙΚΟ μας session

/* ---- 2. Δικό μας απομονωμένο session ---- */
@ini_set('session.use_cookies', '1');
@ini_set('session.use_only_cookies', '1');
session_name('CNPPMSESS');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',   // ισχύει και για /project/ και για /projectmanagement/
    'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
]);
if (!empty($_COOKIE['CNPPMSESS'])) {
    session_id($_COOKIE['CNPPMSESS']);
}
@session_start();

define('PM_BOOTED', 1);

/* ---- 3. Auth: shared secret + signed token ---- */
use WHMCS\Database\Capsule;

function pm_secret()
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $s = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', 'pm_secret')->value('value');
    if (!$s) {
        $s = bin2hex(random_bytes(24));
        Capsule::table('tbladdonmodules')->insert([
            'module' => 'cloudonprojects', 'setting' => 'pm_secret', 'value' => $s,
        ]);
    }
    return $s;
}

/** Μία γραμμή token: base64(adminid.exp).hmac — για handoff από το admin. */
function pm_mint_token($adminId, $ttl = 90)
{
    $payload = $adminId . '.' . (time() + $ttl);
    $sig = hash_hmac('sha256', $payload, pm_secret());
    return rtrim(strtr(base64_encode($payload . '.' . $sig), '+/', '-_'), '=');
}

function pm_verify_token($tok)
{
    $raw = base64_decode(strtr((string) $tok, '-_', '+/'));
    $p = explode('.', $raw);
    if (count($p) !== 3) {
        return 0;
    }
    [$aid, $exp, $sig] = $p;
    if (!hash_equals(hash_hmac('sha256', $aid . '.' . $exp, pm_secret()), $sig)) {
        return 0;
    }
    if ((int) $exp < time()) {
        return 0;
    }
    // ο admin πρέπει να υπάρχει & ενεργός
    return Capsule::table('tbladmins')->where('id', (int) $aid)->where('disabled', 0)->exists() ? (int) $aid : 0;
}

/* token handoff (?t=...) → εγκαθιστά app session */
if (isset($_GET['t'])) {
    $aid = pm_verify_token($_GET['t']);
    if ($aid) {
        session_regenerate_id(true);
        $_SESSION['pm_admin'] = $aid;
        $_SESSION['pm_ua'] = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 120);
    }
}

/** Τρέχων admin του app (0 = μη συνδεδεμένος). */
function pm_admin_id()
{
    $aid = (int) ($_SESSION['pm_admin'] ?? 0);
    if (!$aid) {
        return 0;
    }
    // ελαφρύ binding στο user-agent (κατά κλοπής cookie)
    if (($_SESSION['pm_ua'] ?? '') !== substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 120)) {
        return 0;
    }
    return $aid;
}

/** 🎥 CloudOn Meet: μακρόβια tokens δωματίου (guests/πελάτες). */
function pm_mint_meet($room, $ttl = 2592000)
{
    $payload = 'meet.' . $room . '.' . (time() + $ttl);
    return rtrim(strtr(base64_encode($payload . '.' . hash_hmac('sha256', $payload, pm_secret())), '+/', '-_'), '=');
}

function pm_verify_meet($tok)
{
    $raw = base64_decode(strtr((string) $tok, '-_', '+/'));
    $p = explode('.', (string) $raw);
    if (count($p) !== 4 || $p[0] !== 'meet' || (int) $p[2] < time()) {
        return false;
    }
    $payload = $p[0] . '.' . $p[1] . '.' . $p[2];
    return hash_equals(hash_hmac('sha256', $payload, pm_secret()), $p[3]) ? $p[1] : false;
}

/** ✔ RSVP πελάτη: token ανά event+client. */
function pm_mint_rsvp($eventId, $clientId, $ttl = 5184000)
{
    $payload = 'rsvp.' . (int) $eventId . '.' . (int) $clientId . '.' . (time() + $ttl);
    return rtrim(strtr(base64_encode($payload . '.' . hash_hmac('sha256', $payload, pm_secret())), '+/', '-_'), '=');
}

function pm_verify_rsvp($tok)
{
    $raw = base64_decode(strtr((string) $tok, '-_', '+/'));
    $p = explode('.', (string) $raw);
    if (count($p) !== 5 || $p[0] !== 'rsvp' || (int) $p[3] < time()) {
        return false;
    }
    $payload = implode('.', array_slice($p, 0, 4));
    return hash_equals(hash_hmac('sha256', $payload, pm_secret()), $p[4]) ? [(int) $p[1], (int) $p[2]] : false;
}
