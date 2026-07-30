<?php
/**
 * Viva.com Smart Checkout — gateway πληρωμών για το WHMCS.
 *
 * Μία μόνο σελίδα πληρωμής καλύπτει κάρτες (Visa/Mastercard/Maestro), Apple Pay,
 * Google Pay, IRIS και δόσεις: ό,τι είναι ενεργό στον λογαριασμό Viva εμφανίζεται
 * αυτόματα στον πελάτη. Δεν χρειάζεται ξεχωριστό module ανά μέθοδο.
 *
 * Αρχεία:
 *   modules/gateways/vivapayments.php               — αυτό (ρυθμίσεις, κουμπί, επιστροφές)
 *   modules/gateways/vivapayments/lib/Api.php       — επικοινωνία με τη Viva
 *   modules/gateways/vivapayments/lib/Db.php        — πίνακες παραγγελιών/log
 *   modules/gateways/callback/vivapayments.php      — εκκίνηση, επιστροφή, webhook
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/vivapayments/lib/Db.php';
require_once __DIR__ . '/vivapayments/lib/Api.php';

use CloudOn\Viva\Api as VivaApi;
use CloudOn\Viva\ApiException as VivaApiException;
use CloudOn\Viva\Db as VivaDb;

function vivapayments_MetaData()
{
    return [
        'DisplayName'                 => 'Viva.com — Κάρτες, Apple Pay, Google Pay, IRIS',
        'APIVersion'                  => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage'            => false,
    ];
}

function vivapayments_config()
{
    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'Viva.com — Κάρτες, Apple Pay, Google Pay, IRIS',
        ],
        'environment' => [
            'FriendlyName' => 'Περιβάλλον',
            'Type'         => 'dropdown',
            'Options'      => 'Παραγωγή,Demo (δοκιμές)',
            'Default'      => 'Παραγωγή',
            'Description'  => 'Τα credentials του demo ΔΕΝ δουλεύουν στην παραγωγή και το αντίστροφο.',
        ],
        'clientId' => [
            'FriendlyName' => 'Client ID',
            'Type'         => 'text',
            'Size'         => '60',
            'Description'  => 'Viva portal → Ρυθμίσεις → API access → Smart Checkout credentials.',
        ],
        'clientSecret' => [
            'FriendlyName' => 'Client Secret',
            'Type'         => 'password',
            'Size'         => '60',
            'Description'  => 'Εμφανίζεται μία μόνο φορά κατά τη δημιουργία· αν χαθεί, φτιάξε νέο.',
        ],
        'merchantId' => [
            'FriendlyName' => 'Merchant ID',
            'Type'         => 'text',
            'Size'         => '45',
            'Description'  => 'Viva portal → Ρυθμίσεις → API access. Χρειάζεται για επιστροφές χρημάτων και webhooks.',
        ],
        'apiKey' => [
            'FriendlyName' => 'API key',
            'Type'         => 'password',
            'Size'         => '45',
            'Description'  => 'Ο κωδικός δίπλα στο Merchant ID (Basic auth). Χωρίς αυτόν δεν γίνονται επιστροφές.',
        ],
        'sourceCode' => [
            'FriendlyName' => 'Κωδικός πηγής πληρωμής',
            'Type'         => 'text',
            'Size'         => '20',
            'Default'      => 'Default',
            'Description'  => 'Viva portal → Πωλήσεις → Πηγές πληρωμών. Εκεί ορίζονται και οι διευθύνσεις επιτυχίας/αποτυχίας.',
        ],
        'maxInstallments' => [
            'FriendlyName' => 'Μέγιστες δόσεις',
            'Type'         => 'text',
            'Size'         => '4',
            'Default'      => '0',
            'Description'  => '0 = χωρίς δόσεις. Ισχύει μόνο αν ο λογαριασμός Viva υποστηρίζει δόσεις.',
        ],
        'paymentTimeout' => [
            'FriendlyName' => 'Διάρκεια ισχύος (δευτ.)',
            'Type'         => 'text',
            'Size'         => '6',
            'Default'      => '1800',
            'Description'  => 'Πόσο μένει ενεργός ο σύνδεσμος πληρωμής. 1800 = 30 λεπτά.',
        ],
        'checkoutColor' => [
            'FriendlyName' => 'Χρώμα σελίδας πληρωμής',
            'Type'         => 'text',
            'Size'         => '8',
            'Default'      => '',
            'Description'  => 'Έξι δεκαεξαδικά ψηφία χωρίς #, π.χ. 1F6FEB. Κενό = προεπιλογή Viva.',
        ],
        'disableWallet' => [
            'FriendlyName' => 'Απενεργοποίηση Viva Wallet',
            'Type'         => 'yesno',
            'Description'  => 'Κρύβει την πληρωμή μέσω υπολοίπου Viva Wallet. Δεν επηρεάζει Apple/Google Pay ή IRIS.',
        ],
        'disableCash' => [
            'FriendlyName' => 'Απενεργοποίηση μετρητών',
            'Type'         => 'yesno',
            'Default'      => 'on',
            'Description'  => 'Κρύβει την πληρωμή με μετρητά σε δίκτυο συνεργατών.',
        ],
        'buttonText' => [
            'FriendlyName' => 'Κείμενο κουμπιού',
            'Type'         => 'text',
            'Size'         => '40',
            'Default'      => 'Πληρωμή με κάρτα / Apple Pay / Google Pay / IRIS',
        ],
        'convertto' => [
            'FriendlyName' => 'Μετατροπή σε νόμισμα',
            'Type'         => 'dropdown',
            'Options'      => vivapayments_currencyOptions(),
            'Description'  => 'Ο λογαριασμός Viva χρεώνει σε EUR. Άφησέ το κενό μόνο αν όλα τα τιμολόγια είναι ήδη σε EUR.',
        ],
    ];
}

/** Λίστα νομισμάτων του WHMCS για το πεδίο convertto. */
function vivapayments_currencyOptions()
{
    $opts = ['' => ''];
    try {
        foreach (WHMCS\Database\Capsule::table('tblcurrencies')->orderBy('code')->get() as $c) {
            $opts[$c->id] = $c->code;
        }
    } catch (Throwable $e) {
        // Αν η βάση δεν είναι διαθέσιμη (π.χ. πρώτη φόρτωση), δείξε τουλάχιστον EUR.
        $opts = ['' => '', '1' => 'EUR'];
    }
    return $opts;
}

/**
 * Υπογραφή που συνοδεύει το κουμπί πληρωμής, ώστε το callback να δέχεται μόνο
 * αιτήματα που ξεκίνησαν από το WHMCS.
 */
function vivapayments_token($invoiceId, $amount, $currency, $secret)
{
    $payload = (int) $invoiceId . '|' . number_format((float) $amount, 2, '.', '') . '|' . strtoupper((string) $currency);
    return hash_hmac('sha256', $payload, (string) $secret);
}

/** Το κουμπί που βλέπει ο πελάτης πάνω στο τιμολόγιο. */
function vivapayments_link($params)
{
    $url = rtrim($params['systemurl'], '/') . '/modules/gateways/callback/vivapayments.php';
    $label = trim((string) ($params['buttonText'] ?? '')) ?: 'Πληρωμή τώρα';

    if (empty($params['clientSecret'])) {
        return '<div class="alert alert-warning">Η πληρωμή με κάρτα δεν είναι διαθέσιμη αυτή τη στιγμή. '
            . 'Επικοινώνησε μαζί μας στο 210 7222560.</div>';
    }

    $amount = number_format((float) $params['amount'], 2, '.', '');
    $currency = strtoupper((string) $params['currency']);
    $token = vivapayments_token($params['invoiceid'], $amount, $currency, $params['clientSecret']);

    $html = '<form method="post" action="' . htmlspecialchars($url, ENT_QUOTES) . '">'
        . '<input type="hidden" name="action" value="start">'
        . '<input type="hidden" name="invoiceid" value="' . (int) $params['invoiceid'] . '">'
        . '<input type="hidden" name="amount" value="' . htmlspecialchars($amount, ENT_QUOTES) . '">'
        . '<input type="hidden" name="currency" value="' . htmlspecialchars($currency, ENT_QUOTES) . '">'
        . '<input type="hidden" name="token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">'
        . '<input type="submit" class="btn btn-primary" value="' . htmlspecialchars($label, ENT_QUOTES) . '">'
        . '</form>';

    return $html;
}

/** Επιστροφή χρημάτων από τη διαχείριση του WHMCS. */
function vivapayments_refund($params)
{
    try {
        $api = VivaApi::fromGatewayParams($params);
        $cents = (int) round(((float) $params['amount']) * 100);
        $res = $api->refund($params['transid'], $cents, (string) ($params['sourceCode'] ?? ''));

        VivaDb::log('refund', 'Επιστροφή ' . $cents . ' λεπτών για συναλλαγή ' . $params['transid'], (int) ($params['invoiceid'] ?? 0));

        return [
            'status'  => 'success',
            'rawdata' => $res,
            'transid' => (string) ($res['transactionid'] ?? $params['transid']),
            'fees'    => 0,
        ];
    } catch (VivaApiException $e) {
        VivaDb::log('refund-error', $e->getMessage(), (int) ($params['invoiceid'] ?? 0));
        return ['status' => 'declined', 'rawdata' => $e->getMessage()];
    } catch (Throwable $e) {
        return ['status' => 'error', 'rawdata' => $e->getMessage()];
    }
}
