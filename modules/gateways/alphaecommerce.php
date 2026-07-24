<?php
/**
 * Alpha Bank / Nexi (Alpha e-Commerce, Modirum MPI) — WHMCS payment gateway.
 *
 * Redirect gateway: the client is POSTed to the bank's hosted payment page
 * (shophandlermpi) with a SHA-256 digest; the bank returns to our callback
 * with a signed result which we verify and then mark the invoice paid.
 *
 * Protocol = standard Alpha e-Commerce / Cardlink / Modirum "Redirect".
 * Digest   = base64( sha256( concat(values in send-order) . sharedSecret ) ).
 *
 * ⚠ Ships in TEST mode. Validate a full test payment against Alpha's test
 *   environment BEFORE switching Mode = Live. If the test page rejects the
 *   request with a digest error, the request field order must be aligned to
 *   YOUR Alpha e-Commerce integration guide (see $requestFieldOrder below).
 *
 * @package WHMCS\Module\Gateway\AlphaEcommerce
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function alphaecommerce_MetaData()
{
    return [
        'DisplayName' => 'Alpha Bank / Nexi (Alpha e-Commerce)',
        'APIVersion'  => '1.1',
    ];
}

function alphaecommerce_config()
{
    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'Alpha Bank / Nexi (Alpha e-Commerce)',
        ],
        'merchantId' => [
            'FriendlyName' => 'Merchant ID (mid)',
            'Type'         => 'text',
            'Size'         => '30',
            'Description'  => 'Your Alpha e-Commerce merchant id.',
        ],
        'sharedSecret' => [
            'FriendlyName' => 'Shared Secret',
            'Type'         => 'password',
            'Size'         => '40',
            'Description'  => 'The digest shared secret from the Alpha e-Commerce merchant portal (stored encrypted).',
        ],
        'mode' => [
            'FriendlyName' => 'Mode',
            'Type'         => 'dropdown',
            'Options'      => 'test,live',
            'Default'      => 'test',
            'Description'  => 'Keep TEST until a full test payment succeeds.',
        ],
        'testUrl' => [
            'FriendlyName' => 'Test Payment URL',
            'Type'         => 'text',
            'Size'         => '60',
            'Default'      => 'https://alphaecommerce-test.modirum.com/vpos/shophandlermpi',
        ],
        'liveUrl' => [
            'FriendlyName' => 'Live Payment URL',
            'Type'         => 'text',
            'Size'         => '60',
            'Default'      => 'https://www.alphaecommerce.gr/vpos/shophandlermpi',
        ],
        'transactionType' => [
            'FriendlyName' => 'Transaction Type',
            'Type'         => 'dropdown',
            'Options'      => '1,2',
            'Default'      => '1',
            'Description'  => '1 = Sale (capture immediately), 2 = Authorization only.',
        ],
        'lang' => [
            'FriendlyName' => 'Payment Page Language',
            'Type'         => 'text',
            'Size'         => '4',
            'Default'      => 'el',
        ],
        'cssUrl' => [
            'FriendlyName' => 'CSS URL (optional)',
            'Type'         => 'text',
            'Size'         => '60',
            'Description'  => 'Optional stylesheet URL for the hosted page.',
        ],
    ];
}

/**
 * Fields sent to the bank, in the ORDER used for the request digest.
 * This order MUST match your Alpha e-Commerce integration guide.
 */
function alphaecommerce_requestFieldOrder()
{
    return [
        'version', 'mid', 'lang', 'deviceCategory', 'orderid', 'orderDesc',
        'orderAmount', 'currency', 'payerEmail', 'billCountry', 'billState',
        'billZip', 'billCity', 'billAddress', 'cssUrl', 'confirmUrl', 'cancelUrl',
    ];
}

/** base64( sha256( concat(values in given order) . secret ) ). */
function alphaecommerce_digest(array $fields, array $order, $secret)
{
    $s = '';
    foreach ($order as $k) {
        $s .= isset($fields[$k]) ? (string) $fields[$k] : '';
    }
    $s .= $secret;
    return base64_encode(hash('sha256', $s, true));
}

function alphaecommerce_link($params)
{
    $url    = ($params['mode'] === 'live') ? trim($params['liveUrl']) : trim($params['testUrl']);
    $secret = (string) $params['sharedSecret'];

    // Unique order id per attempt but recoverable to the invoice: <invoiceid>-<time>
    $orderId = $params['invoiceid'] . '-' . time();

    $confirmUrl = rtrim($params['systemurl'], '/') . '/modules/gateways/callback/alphaecommerce.php';

    $fields = [
        'version'        => '2',
        'mid'            => trim($params['merchantId']),
        'lang'           => $params['lang'] ?: 'el',
        'deviceCategory' => '0',
        'orderid'        => $orderId,
        'orderDesc'      => 'Invoice #' . $params['invoiceid'] . ' ' . $params['companyname'],
        'orderAmount'    => number_format((float) $params['amount'], 2, '.', ''),
        'currency'       => $params['currency'],
        'payerEmail'     => $params['clientdetails']['email'],
        'billCountry'    => $params['clientdetails']['countrycode'],
        'billState'      => $params['clientdetails']['state'],
        'billZip'        => $params['clientdetails']['postcode'],
        'billCity'       => $params['clientdetails']['city'],
        'billAddress'    => $params['clientdetails']['address1'],
        'cssUrl'         => trim($params['cssUrl']),
        'confirmUrl'     => $confirmUrl,
        'cancelUrl'      => $confirmUrl,
        'trType'         => $params['transactionType'] ?: '1',
    ];
    $order = alphaecommerce_requestFieldOrder();
    $fields['digest'] = alphaecommerce_digest($fields, $order, $secret);

    // auto-submitting form
    $html  = '<form id="alphaecommerce_form" method="post" action="' . htmlspecialchars($url) . '">';
    foreach ($fields as $k => $v) {
        $html .= '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string) $v) . '" />';
    }
    $html .= '<input type="submit" value="' . htmlspecialchars($params['langpaynow']) . '" />';
    $html .= '</form>';
    $html .= '<script>document.getElementById("alphaecommerce_form").submit();</script>';

    return $html;
}
