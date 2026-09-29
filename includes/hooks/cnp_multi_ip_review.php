<?php
/**
 * Χειροκίνητος έλεγχος VPS με πρόσθετες IP (απόφαση 29/9/2026).
 *
 * Αφορμή: λογαριασμοί που έπαιρναν VPS με 4-5 Extra IPs για αποστολή spam
 * (Spamhaus μέσω Hetzner abuse). Κάθε VPS με περισσότερες από μία IP
 * (Extra IPs >= 1) ΔΕΝ παραδίδεται αυτόματα με την πληρωμή:
 *   - η αυτόματη δημιουργία (setup on payment / cron) μπλοκάρεται,
 *   - η ομάδα ειδοποιείται στο Activity Log + καμπανάκι PM.
 * Παράδοση: διαχειριστής από Orders -> Accept (Run Module Create) ή από την
 * υπηρεσία -> Module Commands -> Create. Μόνο αυτές οι δύο οθόνες, με
 * συνδεδεμένο admin, περνούν (όχι π.χ. «Mark Paid» τιμολογίου).
 * Αναβαθμίσεις: δεν αφορούν, το ChangePackage του hetznercloud δεν προσθέτει IP.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

if (!function_exists('cnp_extra_ips')) {
function cnp_extra_ips($serviceid)
{
    $rows = Capsule::table('tblhostingconfigoptions as hc')
        ->join('tblproductconfigoptions as o', 'o.id', '=', 'hc.configid')
        ->where('hc.relid', (int) $serviceid)
        ->get(['o.optionname', 'hc.qty']);
    $n = 0;
    foreach ($rows as $r) {
        $name = strtolower(explode('|', (string) $r->optionname)[0]);
        if (strpos($name, 'extra ip') !== false || strpos($name, 'additional ip') !== false) {
            $n += (int) $r->qty;
        }
    }
    return $n;
}
}

if (!function_exists('cnp_multi_ip_admin_ok')) {
function cnp_multi_ip_admin_ok()
{
    if (empty($_SESSION['adminid'])) {
        return false;
    }
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    return in_array($script, ['clientsservices.php', 'orders.php'], true);
}
}

add_hook('PreModuleCreate', 2, function ($vars) {
    $serviceid = (int) ($vars['serviceid'] ?? ($vars['params']['serviceid'] ?? 0));
    if (!$serviceid) {
        return;
    }
    try {
        $svc = Capsule::table('tblhosting as h')
            ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->where('h.id', $serviceid)
            ->first(['h.domain', 'h.userid', 'p.servertype']);
        if (!$svc || $svc->servertype !== 'hetznercloud') {
            return;
        }
        $extra = cnp_extra_ips($serviceid);
        if ($extra < 1 || cnp_multi_ip_admin_ok()) {
            return;
        }
        $msg = 'VPS «' . $svc->domain . '» (service #' . $serviceid . ', πελάτης #' . $svc->userid . ') με '
            . ($extra + 1) . ' IP περιμένει ΧΕΙΡΟΚΙΝΗΤΟ έλεγχο — δεν παραδόθηκε αυτόματα';
        if (function_exists('cnp_guard_notify')) {
            cnp_guard_notify($msg, 'clientsservices.php?userid=' . $svc->userid . '&id=' . $serviceid);
        } elseif (function_exists('logActivity')) {
            logActivity('CloudOn Guard: ' . $msg);
        }
        return ['abortcmd' => true];
    } catch (\Throwable $e) {
        if (function_exists('logActivity')) {
            logActivity('cnp_multi_ip_review σφάλμα (PreModuleCreate): ' . $e->getMessage());
        }
    }
});
