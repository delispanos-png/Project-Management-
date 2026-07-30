<?php
/**
 * Viva.com — καταχώρηση πληρωμής στο τιμολόγιο.
 *
 * Κοινή για τις τρεις διαδρομές που μπορεί να φέρουν την είδηση της πληρωμής:
 * επιστροφή του πελάτη, webhook, και ο έλεγχος συμφωνίας (cron). Όποια φτάσει
 * πρώτη κερδίζει· η orderClaim() εγγυάται ότι δεν θα περαστεί δύο φορές.
 */

namespace CloudOn\Viva;

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Api.php';

class Settle
{
    /**
     * @param array $order Γραμμή του mod_viva_orders.
     * @param array $tx    Συναλλαγή από τη Viva (κλειδιά πεζά).
     * @return bool True μόνο αν η πληρωμή καταχωρήθηκε τώρα.
     */
    public static function apply(array $order, array $tx, array $gatewayParams)
    {
        $orderCode = (string) $order['order_code'];
        $transId = (string) ($tx['transactionid'] ?? '');

        if ($transId === '') {
            return false;
        }

        if (($tx['statusid'] ?? '') !== Api::STATUS_PAID) {
            Db::log('skip', 'Συναλλαγή σε κατάσταση ' . ($tx['statusid'] ?? '?'),
                (int) $order['invoice_id'], $orderCode);
            return false;
        }

        // Το ποσό έρχεται σε δεκαδικά (π.χ. 1.00), εμείς κρατάμε λεπτά.
        $paidCents = (int) round(((float) ($tx['amount'] ?? 0)) * 100);
        if ($paidCents < (int) $order['amount_cents']) {
            Db::log('mismatch', 'Ποσό Viva ' . $paidCents . ' < αναμενόμενο ' . $order['amount_cents'],
                (int) $order['invoice_id'], $orderCode);
            return false;
        }

        if (!Db::orderClaim($orderCode, $transId)) {
            return false;
        }

        $invoiceId = checkCbInvoiceID((int) $order['invoice_id'], 'vivapayments');
        checkCbTransID($transId);

        // Αν το τιμολόγιο είναι σε άλλο νόμισμα (convertto), πιστώνουμε το
        // υπόλοιπό του — έχουμε χρεώσει το ισοδύναμο συνολικό ποσό.
        $amount = $paidCents / 100;
        $inv = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if ($inv) {
            $client = Capsule::table('tblclients')->where('id', $inv->userid)->first();
            $invCurrency = $client
                ? Capsule::table('tblcurrencies')->where('id', $client->currency)->value('code')
                : null;
            if ($invCurrency && strtoupper($invCurrency) !== strtoupper((string) $order['currency'])) {
                $amount = (float) $inv->total - (float) $inv->credit;
            }
        }

        logTransaction($gatewayParams['name'], $tx, 'Successful');
        addInvoicePayment($invoiceId, $transId, $amount, 0, 'vivapayments');

        Db::log('paid', 'Εξόφληση τιμολογίου ' . $invoiceId . ' με συναλλαγή ' . $transId,
            $invoiceId, $orderCode);

        return true;
    }

    /**
     * Ελέγχει τις εκκρεμείς παραγγελίες στη Viva και εξοφλεί όσες πληρώθηκαν
     * χωρίς να μας φτάσει η είδηση. Επιστρέφει σύνοψη.
     *
     * @param int $olderThanMinutes Αγνόησε ολοφρέσκιες παραγγελίες που ακόμη πληρώνονται.
     * @param int $maxAgeDays       Πόσο πίσω κοιτάμε.
     */
    public static function reconcile(array $gatewayParams, $olderThanMinutes = 3, $maxAgeDays = 7)
    {
        $api = Api::fromGatewayParams($gatewayParams);
        $summary = ['ελέγχθηκαν' => 0, 'εξοφλήθηκαν' => 0, 'έληξαν' => 0, 'σφάλματα' => 0];

        $pending = Capsule::table(Db::T_ORDERS)
            ->where('status', 'pending')
            ->where('created_at', '<=', date('Y-m-d H:i:s', time() - 60 * (int) $olderThanMinutes))
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 86400 * (int) $maxAgeDays))
            ->orderBy('id')
            ->get();

        foreach ($pending as $row) {
            $order = (array) $row;
            $orderCode = (string) $order['order_code'];
            $summary['ελέγχθηκαν']++;

            try {
                $info = $api->orderInfo($orderCode);
                $state = (int) ($info['stateid'] ?? 0);

                if ($state === 3) {                       // πληρώθηκε
                    foreach ($api->orderTransactions($orderCode) as $tx) {
                        if (self::apply($order, $tx, $gatewayParams)) {
                            $summary['εξοφλήθηκαν']++;
                            break;
                        }
                    }
                } elseif ($state === 1 || $state === 2) {  // έληξε ή ακυρώθηκε
                    Db::orderMark($orderCode, $state === 1 ? 'expired' : 'canceled');
                    $summary['έληξαν']++;
                }
            } catch (ApiException $e) {
                $summary['σφάλματα']++;
                Db::log('reconcile-error', $e->getMessage(), (int) $order['invoice_id'], $orderCode);
            }
        }

        return $summary;
    }
}
