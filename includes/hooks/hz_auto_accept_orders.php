<?php
/**
 * Auto-accept orders once their invoice is PAID.
 *
 * With auto-provisioning ("setup on payment"), the VM is delivered but the
 * ORDER used to stay "Pending" until an admin clicked Accept — which broke
 * the delivery-flow start. This hook accepts the order automatically the
 * moment its invoice is paid.
 *
 * Safety:
 *  - Only orders in status "Pending" (Fraud/Cancelled are never touched).
 *  - autosetup=false → provisioning stays driven by the product's own
 *    "setup on payment" automation (no double-provisioning; the hetznercloud
 *    module is idempotent anyway).
 *  - Every auto-accept is written to the WHMCS Activity Log.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('InvoicePaid', 100, function ($vars) {
    $invoiceid = (int) ($vars['invoiceid'] ?? 0);
    if ($invoiceid <= 0) {
        return;
    }
    try {
        $orderIds = Capsule::table('tblorders')
            ->where('invoiceid', $invoiceid)
            ->where('status', 'Pending')
            ->pluck('id');
        if (!count($orderIds)) {
            return;
        }
        $adminUser = Capsule::table('tbladmins')->where('disabled', 0)->orderBy('id')->value('username');
        foreach ($orderIds as $oid) {
            $res = localAPI('AcceptOrder', [
                'orderid'   => (int) $oid,
                'autosetup' => false,
                'sendemail' => true,
            ], $adminUser);
            if (function_exists('logActivity')) {
                logActivity('Auto-Accept: η παραγγελία #' . $oid . ' έγινε αποδεκτή αυτόματα με την πληρωμή του τιμολογίου #' . $invoiceid
                    . ((($res['result'] ?? '') === 'success') ? '' : ' — ΑΠΟΤΥΧΙΑ: ' . json_encode($res)));
            }
        }
    } catch (\Throwable $e) {
        if (function_exists('logActivity')) {
            logActivity('Auto-Accept hook σφάλμα: ' . $e->getMessage());
        }
    }
});
