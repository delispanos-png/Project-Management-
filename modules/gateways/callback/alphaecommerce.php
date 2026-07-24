<?php
/**
 * Alpha Bank / Nexi (Alpha e-Commerce, Modirum) — return callback.
 *
 * The bank redirects the browser back here (POST) with a signed result.
 * We verify the response digest, then mark the invoice paid and send the
 * client back to the WHMCS invoice page.
 *
 * @package WHMCS\Module\Gateway\AlphaEcommerce
 */

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = 'alphaecommerce';
$gatewayParams     = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die('Module Not Activated');
}

$secret   = (string) $gatewayParams['sharedSecret'];
$post     = $_POST;
$received = isset($post['digest']) ? (string) $post['digest'] : '';

/** Response fields, in the order used for the response digest (per Alpha e-Commerce). */
$responseOrder = [
    'version', 'mid', 'orderid', 'status', 'orderAmount', 'currency',
    'paymentTotal', 'message', 'riskScore', 'payMethod', 'txId', 'paymentRef',
];

// recompute digest over the documented response fields + shared secret
$digestString = '';
foreach ($responseOrder as $k) {
    $digestString .= isset($post[$k]) ? (string) $post[$k] : '';
}
$digestString .= $secret;
$expected = base64_encode(hash('sha256', $digestString, true));

$orderId   = isset($post['orderid']) ? (string) $post['orderid'] : '';
$invoiceId = (int) explode('-', $orderId)[0];
$status    = isset($post['status']) ? strtoupper((string) $post['status']) : '';
$txId      = isset($post['txId']) ? (string) $post['txId'] : ($post['paymentRef'] ?? $orderId);
$amount    = isset($post['paymentTotal']) ? (float) $post['paymentTotal'] : (float) ($post['orderAmount'] ?? 0);

$systemUrl  = rtrim($gatewayParams['systemurl'], '/');
$invoiceUrl = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;

// ---- verify signature (constant-time) ----
if ($received === '' || !hash_equals($expected, $received)) {
    logTransaction($gatewayParams['name'], $post, 'Digest Verification Failed');
    header('Location: ' . $invoiceUrl . '&paymentfailed=true');
    exit;
}

// ---- validate invoice ----
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
checkCbTransID($txId);

// ---- apply result ----
$success = in_array($status, ['CAPTURED', 'AUTHORIZED', 'SUCCESS'], true);

if ($success) {
    addInvoicePayment($invoiceId, $txId, $amount, 0, $gatewayModuleName);
    logTransaction($gatewayParams['name'], $post, 'Success (' . $status . ')');
    header('Location: ' . $invoiceUrl . '&paymentsuccess=true');
    exit;
}

logTransaction($gatewayParams['name'], $post, 'Declined/Failed (' . ($status ?: 'unknown') . ')');
header('Location: ' . $invoiceUrl . '&paymentfailed=true');
exit;
