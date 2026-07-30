<?php
/**
 * Viva.com — να μην εξαρτάται η εξόφληση από το αν θα μας βρει η Viva.
 *
 * Το CloudOn και το RxVision μοιράζονται έναν λογαριασμό Viva. Η «Default»
 * πηγή και το webhook ανήκουν στο RxVision, οπότε ούτε η επιστροφή του πελάτη
 * ούτε το webhook φτάνουν αξιόπιστα εδώ. Αντί να παλεύουμε γι' αυτά, ρωτάμε
 * εμείς τη Viva:
 *
 *   1. ClientAreaPage → μόλις ο πελάτης ανοίξει το τιμολόγιό του, ελέγχεται
 *      αμέσως η εκκρεμής παραγγελία. Πρακτικά ακαριαίο για τον πελάτη.
 *   2. AfterCronJob   → δίχτυ για όσους δεν ξαναμπαίνουν καθόλου.
 *
 * Έτσι, ό,τι κι αν κάνει η Viva με τα redirect και τα webhook, η πληρωμή
 * καταχωρείται.
 */

use CloudOn\Viva\Settle;
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/** Φορτώνει το module και επιστρέφει τα params, ή null αν δεν είναι διαθέσιμο. */
function viva_hook_params()
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $cached = null;

    $lib = ROOTDIR . '/modules/gateways/vivapayments/lib/Settle.php';
    if (!is_file($lib)) {
        return null;
    }
    require_once ROOTDIR . '/includes/gatewayfunctions.php';
    require_once ROOTDIR . '/includes/invoicefunctions.php';
    require_once $lib;

    $params = getGatewayVariables('vivapayments');
    if (empty($params['type']) || empty($params['merchantId']) || empty($params['apiKey'])) {
        return null;
    }

    return $cached = $params;
}

/**
 * Ο πελάτης άνοιξε τιμολόγιο: ρώτα τη Viva αμέσως αν πληρώθηκε.
 * Τρέχει μόνο όταν υπάρχει εκκρεμής παραγγελία γι' αυτό το τιμολόγιο, οπότε
 * δεν επιβαρύνει τις υπόλοιπες σελίδες.
 */
add_hook('ClientAreaPage', 1, function ($vars) {
    if (($vars['templatefile'] ?? '') !== 'viewinvoice') {
        return;
    }

    $invoiceId = (int) ($vars['invoiceid'] ?? ($_GET['id'] ?? 0));
    if (!$invoiceId) {
        return;
    }

    // Γρήγορος έλεγχος πριν φορτώσουμε οτιδήποτε άλλο.
    try {
        $pending = Capsule::table('mod_viva_orders')
            ->where('invoice_id', $invoiceId)->where('status', 'pending')->exists();
    } catch (Throwable $e) {
        return;   // ο πίνακας μπορεί να μην υπάρχει ακόμη
    }
    if (!$pending) {
        return;
    }

    $params = viva_hook_params();
    if (!$params) {
        return;
    }

    try {
        if (Settle::checkInvoice($invoiceId, $params) === 'paid') {
            // Ξαναφόρτωσε ώστε να δει το τιμολόγιο εξοφλημένο, όχι ανεξόφλητο.
            header('Location: ' . rtrim($params['systemurl'], '/')
                . '/viewinvoice.php?id=' . $invoiceId . '&paymentsuccess=true');
            exit;
        }
    } catch (Throwable $e) {
        logActivity('Viva: αποτυχία άμεσου ελέγχου τιμολογίου ' . $invoiceId . ' — ' . $e->getMessage());
    }
});

/** Δίχτυ ασφαλείας σε κάθε εκτέλεση του cron. */
add_hook('AfterCronJob', 1, function () {
    $params = viva_hook_params();
    if (!$params) {
        return;
    }
    if (function_exists('cnp_beat')) { cnp_beat('viva_reconcile'); }
    try {
        Settle::reconcile($params);
    } catch (Throwable $e) {
        logActivity('Viva: αποτυχία ελέγχου συμφωνίας — ' . $e->getMessage());
    }
});
