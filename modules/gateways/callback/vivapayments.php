<?php
/**
 * Viva.com Smart Checkout — σημείο εισόδου για τρία πράγματα:
 *
 *  1. POST action=start          → φτιάχνει παραγγελία και στέλνει τον πελάτη στη Viva
 *  2. GET  ?t=…&s=…              → επιστροφή πελάτη· επαληθεύουμε και εξοφλούμε
 *  3. ?webhook=1                 → GET: κλειδί επαλήθευσης, POST: γεγονός πληρωμής
 *
 * Η εξόφληση γίνεται από όποιο από τα (2)/(3) φτάσει πρώτο· η orderClaim()
 * εγγυάται ότι δεν θα περαστεί δύο φορές.
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../vivapayments/lib/Db.php';
require_once __DIR__ . '/../vivapayments/lib/Api.php';
require_once __DIR__ . '/../vivapayments/lib/Settle.php';

use CloudOn\Viva\Api as VivaApi;
use CloudOn\Viva\ApiException as VivaApiException;
use CloudOn\Viva\Db as VivaDb;
use CloudOn\Viva\Settle as VivaSettle;
use WHMCS\Database\Capsule;

$gatewayModuleName = 'vivapayments';
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    http_response_code(503);
    exit('Το module πληρωμών Viva δεν είναι ενεργοποιημένο.');
}

$systemUrl = rtrim($gatewayParams['systemurl'], '/');
$api = VivaApi::fromGatewayParams($gatewayParams);

/* --------------------------------------------------------------------- */
/* Βοηθητικά                                                              */
/* --------------------------------------------------------------------- */

function viva_bail($message, $invoiceId = 0)
{
    global $systemUrl;
    VivaDb::log('error', $message, $invoiceId);
    $back = $invoiceId
        ? $systemUrl . '/viewinvoice.php?id=' . (int) $invoiceId . '&paymentfailed=1'
        : $systemUrl . '/clientarea.php';
    echo '<!doctype html><meta charset="utf-8"><title>Πληρωμή</title>'
        . '<div style="font:15px/1.6 system-ui,sans-serif;max-width:520px;margin:60px auto;padding:24px;'
        . 'border:1px solid #e3e6ea;border-radius:12px">'
        . '<h2 style="margin:0 0 10px">Η πληρωμή δεν ολοκληρώθηκε</h2>'
        . '<p>' . htmlspecialchars($message) . '</p>'
        . '<p>Αν το ποσό χρεώθηκε, επικοινώνησε μαζί μας στο <b>210 7222560</b>.</p>'
        . '<p><a href="' . htmlspecialchars($back, ENT_QUOTES) . '">Επιστροφή στο τιμολόγιο</a></p></div>';
    exit;
}

/* --------------------------------------------------------------------- */
/* 3. Webhook                                                             */
/* --------------------------------------------------------------------- */

if (isset($_GET['webhook'])) {

    // Η Viva ζητά πρώτα με GET ένα κλειδί, για να επιβεβαιώσει ότι η διεύθυνση
    // μας ανήκει. Το επιστρέφουμε αυτούσιο.
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            header('Content-Type: application/json');
            echo $api->webhookVerificationBody();
        } catch (VivaApiException $e) {
            http_response_code(500);
            VivaDb::log('webhook-error', $e->getMessage());
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    $raw = file_get_contents('php://input');
    $evt = VivaApi::lowerKeys((array) json_decode($raw, true, 512, JSON_BIGINT_AS_STRING));
    $type = (int) ($evt['eventtypeid'] ?? 0);
    $data = (array) ($evt['eventdata'] ?? []);

    http_response_code(200);   // πάντα 200, αλλιώς η Viva ξαναστέλνει επ' άπειρον

    $orderCode = (string) ($data['ordercode'] ?? '');
    $transId   = (string) ($data['transactionid'] ?? '');

    if ($orderCode === '') {
        VivaDb::log('webhook', 'Γεγονός ' . $type . ' χωρίς orderCode');
        exit;
    }

    $order = VivaDb::orderByCode($orderCode);
    if (!$order) {
        VivaDb::log('webhook', 'Άγνωστο orderCode σε γεγονός ' . $type, 0, $orderCode);
        exit;
    }

    if ($type === VivaApi::EVENT_PAYMENT_CREATED) {
        try {
            // Δεν εμπιστευόμαστε το payload: ρωτάμε τη Viva για την αλήθεια.
            $tx = $api->getTransaction($transId);
            if (($tx['statusid'] ?? '') === VivaApi::STATUS_PAID
                && (string) ($tx['ordercode'] ?? '') === $orderCode) {
                VivaSettle::apply($order, $tx, $gatewayParams);
            } else {
                VivaDb::log('webhook', 'Συναλλαγή σε κατάσταση ' . ($tx['statusid'] ?? '?'),
                    (int) $order['invoice_id'], $orderCode);
            }
        } catch (VivaApiException $e) {
            VivaDb::log('webhook-error', $e->getMessage(), (int) $order['invoice_id'], $orderCode);
        }
    } elseif ($type === VivaApi::EVENT_PAYMENT_FAILED) {
        if ($order['status'] === 'pending') {
            VivaDb::orderMark($orderCode, 'failed', $transId);
        }
        VivaDb::log('failed', 'Αποτυχία πληρωμής: ' . (string) ($data['statusid'] ?? ''),
            (int) $order['invoice_id'], $orderCode);
    } elseif ($type === VivaApi::EVENT_REVERSAL_CREATED) {
        VivaDb::log('reversal', 'Επιστροφή/ακύρωση συναλλαγής ' . $transId,
            (int) $order['invoice_id'], $orderCode);
    }

    exit;
}

/* --------------------------------------------------------------------- */
/* 1. Εκκίνηση πληρωμής                                                   */
/* --------------------------------------------------------------------- */

if (($_POST['action'] ?? '') === 'start') {

    $invoiceId = (int) ($_POST['invoiceid'] ?? 0);
    $amount    = number_format((float) ($_POST['amount'] ?? 0), 2, '.', '');
    $currency  = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($_POST['currency'] ?? '')));
    $token     = (string) ($_POST['token'] ?? '');

    $expected = hash_hmac('sha256', $invoiceId . '|' . $amount . '|' . $currency, (string) $gatewayParams['clientSecret']);
    if (!$invoiceId || !hash_equals($expected, $token)) {
        viva_bail('Μη έγκυρο αίτημα πληρωμής.', $invoiceId);
    }

    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if (!$invoice) {
        viva_bail('Το τιμολόγιο δεν βρέθηκε.', 0);
    }
    if ($invoice->status === 'Paid') {
        header('Location: ' . $systemUrl . '/viewinvoice.php?id=' . $invoiceId);
        exit;
    }

    // Αν υπάρχει συνδεδεμένος πελάτης, πρέπει να είναι ο κάτοχος του τιμολογίου.
    $sessionUid = (int) ($_SESSION['uid'] ?? 0);
    if ($sessionUid && $sessionUid !== (int) $invoice->userid) {
        viva_bail('Το τιμολόγιο δεν ανήκει στον λογαριασμό σου.', 0);
    }

    $client = Capsule::table('tblclients')->where('id', $invoice->userid)->first();

    $cents = (int) round(((float) $amount) * 100);
    $name = trim(($client->firstname ?? '') . ' ' . ($client->lastname ?? ''));
    $lang = strtolower((string) ($client->language ?? '')) === 'english' ? 'en-US' : 'el-GR';

    try {
        $orderCode = $api->createOrder($cents, [
            'customerTrns'    => 'CloudOn - Τιμολόγιο #' . $invoiceId,
            'merchantTrns'    => 'WHMCS invoice ' . $invoiceId,
            'customer'        => array_filter([
                'email'       => $client->email ?? '',
                'fullName'    => $name,
                'phone'       => preg_replace('/[^0-9+]/', '', (string) ($client->phonenumber ?? '')),
                'countryCode' => strtoupper(substr((string) ($client->country ?? 'GR'), 0, 2)),
                'requestLang' => $lang,
            ]),
            'paymentTimeout'  => (int) ($gatewayParams['paymentTimeout'] ?: 1800),
            'maxInstallments' => (int) ($gatewayParams['maxInstallments'] ?: 0),
            'disableWallet'   => ($gatewayParams['disableWallet'] ?? '') === 'on',
            'disableCash'     => ($gatewayParams['disableCash'] ?? '') === 'on',
            'sourceCode'      => trim((string) ($gatewayParams['sourceCode'] ?? '')),
            'tags'            => ['whmcs', 'invoice-' . $invoiceId],
        ]);
    } catch (VivaApiException $e) {
        viva_bail($e->getMessage(), $invoiceId);
    }

    VivaDb::orderCreate($orderCode, $invoiceId, (int) $invoice->userid, $cents, $currency, $api->isDemo());
    VivaDb::log('order', 'Δημιουργήθηκε παραγγελία ' . $orderCode . ' για ' . $cents . ' λεπτά', $invoiceId, $orderCode);

    header('Location: ' . $api->checkoutUrl($orderCode, (string) ($gatewayParams['checkoutColor'] ?? '')));
    exit;
}

/* --------------------------------------------------------------------- */
/* 2. Επιστροφή πελάτη από τη Viva                                        */
/* --------------------------------------------------------------------- */

$transId   = (string) ($_GET['t'] ?? '');
$orderCode = (string) ($_GET['s'] ?? '');

if ($orderCode === '') {
    header('Location: ' . $systemUrl . '/clientarea.php');
    exit;
}

$order = VivaDb::orderByCode($orderCode);
if (!$order) {
    viva_bail('Δεν βρέθηκε η παραγγελία πληρωμής.', 0);
}
$invoiceId = (int) $order['invoice_id'];

// Χωρίς transactionId η Viva μας γύρισε από αποτυχία/ακύρωση.
if ($transId === '') {
    if ($order['status'] === 'pending') {
        VivaDb::orderMark($orderCode, 'failed');
    }
    header('Location: ' . $systemUrl . '/viewinvoice.php?id=' . $invoiceId . '&paymentfailed=1');
    exit;
}

try {
    $tx = $api->getTransaction($transId);
} catch (VivaApiException $e) {
    // Δεν μπορέσαμε να επαληθεύσουμε τώρα — το webhook θα το τακτοποιήσει.
    VivaDb::log('verify-error', $e->getMessage(), $invoiceId, $orderCode);
    header('Location: ' . $systemUrl . '/viewinvoice.php?id=' . $invoiceId);
    exit;
}

if (($tx['statusid'] ?? '') !== VivaApi::STATUS_PAID || (string) ($tx['ordercode'] ?? '') !== $orderCode) {
    if ($order['status'] === 'pending') {
        VivaDb::orderMark($orderCode, 'failed', $transId);
    }
    logTransaction($gatewayParams['name'], $tx, 'Unsuccessful');
    header('Location: ' . $systemUrl . '/viewinvoice.php?id=' . $invoiceId . '&paymentfailed=1');
    exit;
}

VivaSettle::apply($order, $tx, $gatewayParams);

header('Location: ' . $systemUrl . '/viewinvoice.php?id=' . $invoiceId . '&paymentsuccess=true');
exit;
