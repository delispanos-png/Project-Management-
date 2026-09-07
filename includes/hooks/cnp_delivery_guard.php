<?php
/**
 * Guard παράδοσης VM — δεν παραδίδεται μηχάνημα αν το τιμολόγιό του δεν είναι
 * πλήρως εξοφλημένο.
 *
 * Κανόνας (απόφαση 5/9/2026):
 *   • Η VM παραδίδεται ΜΟΝΟ αν το τιμολόγιο ενεργοποίησης είναι **Paid**
 *     (πλήρης κάλυψη — το Client Credit ΜΕΤΡΑΕΙ κανονικά, αφού το WHMCS κάνει
 *     Paid μόνο όταν καλυφθεί 100%). Αν είναι Unpaid/Collections → ΜΠΛΟΚ (abortcmd).
 *   • Εξόφληση από credit που καλύπτει πλήρως = κανένα πρόβλημα, παραδίδεται.
 *
 * Αφορά τα VPS (module hetznercloud). Οι έξτρα IP/πόροι, ως μέρος του ίδιου
 * τιμολογίου, καλύπτονται από τον ίδιο έλεγχο. Όταν το credit εξαντληθεί, το
 * επόμενο τιμολόγιο μένει Unpaid και ο guard κόβει αυτόματα την παράδοση/ανανέωση.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/** Ειδοποίηση ομάδας: πάντα στο WHMCS Activity Log + best-effort PM bell. */
function cnp_guard_notify($title, $url = null)
{
    if (function_exists('logActivity')) {
        logActivity('CloudOn Guard: ' . $title);
    }
    try {
        $dbFile = __DIR__ . '/../../modules/addons/cloudonprojects/lib/Db.php';
        if (is_file($dbFile)) {
            require_once $dbFile;
            $cls = 'WHMCS\\Module\\Addon\\CloudonProjects\\Db';
            if (class_exists($cls) && method_exists($cls, 'pushNotification')) {
                $t = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string) $title);   // utf8mb3-safe
                foreach (Capsule::table('tbladmins')->where('disabled', 0)->pluck('id') as $aid) {
                    if ($cls::isFullAccess((int) $aid)) {
                        $cls::pushNotification((int) $aid, 'info', $t, $url);
                    }
                }
            }
        }
    } catch (\Throwable $e) {
    }
}

/** Το τιμολόγιο ενεργοποίησης μιας υπηρεσίας (μέσω της παραγγελίας της). */
function cnp_service_invoice($serviceid)
{
    $orderid = (int) Capsule::table('tblhosting')->where('id', (int) $serviceid)->value('orderid');
    if (!$orderid) {
        return null;
    }
    $invid = (int) Capsule::table('tblorders')->where('id', $orderid)->value('invoiceid');
    if (!$invid) {
        return null;
    }
    return Capsule::table('tblinvoices')->where('id', $invid)->first(['id', 'status', 'total', 'userid']);
}

/* ── 1) ΜΠΛΟΚ παράδοσης σε ανεξόφλητο τιμολόγιο ── */
add_hook('PreModuleCreate', 1, function ($vars) {
    // Μόνο VPS (hetznercloud)· ό,τι άλλο περνά ανέγγιχτο.
    $moduletype = (string) ($vars['moduletype'] ?? $vars['module'] ?? '');
    $serviceid = (int) ($vars['serviceid'] ?? ($vars['params']['serviceid'] ?? 0));
    if (!$serviceid) {
        return;
    }
    try {
        if ($moduletype !== '' && stripos($moduletype, 'hetzner') === false) {
            return;
        }
        $inv = cnp_service_invoice($serviceid);
        if (!$inv) {
            return;                         // χωρίς τιμολόγιο (π.χ. δωρεάν/χειροκίνητο) → δεν μπλοκάρουμε
        }
        if ($inv->status === 'Paid') {
            return;                         // πλήρως εξοφλημένο (credit μετράει) → παράδοση OK
        }
        if (in_array($inv->status, ['Unpaid', 'Collections'], true)) {
            $dom = (string) Capsule::table('tblhosting')->where('id', $serviceid)->value('domain');
            cnp_guard_notify('⛔ ΔΕΝ παραδόθηκε VM «' . $dom . '» (service #' . $serviceid
                . ') — τιμολόγιο #' . $inv->id . ' ΑΝΕΞΟΦΛΗΤΟ (' . $inv->status . ')',
                'invoices.php?action=edit&id=' . $inv->id);
            return ['abortcmd' => true];    // ΜΠΛΟΚ παράδοσης
        }
    } catch (\Throwable $e) {
        if (function_exists('logActivity')) {
            logActivity('cnp_delivery_guard σφάλμα (PreModuleCreate): ' . $e->getMessage());
        }
    }
});
