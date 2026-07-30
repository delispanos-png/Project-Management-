<?php
/**
 * Viva.com — έλεγχος συμφωνίας πληρωμών σε κάθε εκτέλεση του cron του WHMCS.
 *
 * Γιατί χρειάζεται: η είδηση της πληρωμής μπορεί να μη φτάσει ποτέ — ο πελάτης
 * κλείνει το παράθυρο πριν επιστρέψει, η πηγή πληρωμής έχει λάθος διεύθυνση
 * επιστροφής, ή το webhook πέφτει. Τα χρήματα όμως έχουν χρεωθεί. Εδώ ρωτάμε
 * τη Viva για κάθε εκκρεμή παραγγελία και εξοφλούμε όσες πληρώθηκαν.
 */

use CloudOn\Viva\Settle;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('AfterCronJob', 1, function () {
    $module = ROOTDIR . '/modules/gateways/vivapayments.php';
    if (!is_file($module)) {
        return;
    }

    require_once ROOTDIR . '/includes/gatewayfunctions.php';
    require_once ROOTDIR . '/includes/invoicefunctions.php';
    require_once ROOTDIR . '/modules/gateways/vivapayments/lib/Settle.php';

    $params = getGatewayVariables('vivapayments');
    if (empty($params['type']) || empty($params['merchantId']) || empty($params['apiKey'])) {
        return;   // ανενεργό ή χωρίς τα κλειδιά που χρειάζεται ο έλεγχος
    }

    try {
        Settle::reconcile($params);
    } catch (Throwable $e) {
        logActivity('Viva: αποτυχία ελέγχου συμφωνίας — ' . $e->getMessage());
    }
});
