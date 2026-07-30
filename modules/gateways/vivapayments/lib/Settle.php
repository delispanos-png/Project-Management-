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

        $invoiceId = (int) $order['invoice_id'];

        // Ο πελάτης μπορεί να πατήσει «Πληρωμή» δύο φορές και να δημιουργηθούν
        // δύο παραγγελίες για το ΙΔΙΟ τιμολόγιο. Αν πληρώσει και τις δύο, τα
        // χρήματα έχουν χρεωθεί δύο φορές. Δεν προσθέτουμε δεύτερη πληρωμή —
        // το τιμολόγιο θα έβγαζε πιστωτικό υπόλοιπο και το λάθος θα περνούσε
        // απαρατήρητο. Το καταγράφουμε ώστε να γίνει επιστροφή.
        $inv = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if ($inv && $inv->status === 'Paid') {
            Db::orderMark($orderCode, 'duplicate', $transId);
            Db::log('ΔΙΠΛΗ ΧΡΕΩΣΗ', 'Το τιμολόγιο ' . $invoiceId . ' ήταν ήδη εξοφλημένο. Χρειάζεται'
                . ' επιστροφή ' . number_format($paidCents / 100, 2) . ' EUR για τη συναλλαγή ' . $transId,
                $invoiceId, $orderCode);
            logActivity('Viva: ΔΙΠΛΗ ΧΡΕΩΣΗ στο τιμολόγιο ' . $invoiceId . ' — συναλλαγή ' . $transId
                . ' (' . number_format($paidCents / 100, 2) . ' EUR) χρειάζεται επιστροφή.');
            return false;
        }

        $invoiceId = checkCbInvoiceID($invoiceId, 'vivapayments');
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

        // Τυχόν άλλες εκκρεμείς παραγγελίες του ίδιου τιμολογίου δεν έχουν πια
        // νόημα — σταματάμε να τις ρωτάμε στη Viva.
        Capsule::table(Db::T_ORDERS)
            ->where('invoice_id', $invoiceId)
            ->where('status', 'pending')
            ->update(['status' => 'superseded', 'updated_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    /**
     * Ρωτάει τη Viva για μία συγκεκριμένη παραγγελία και τακτοποιεί ό,τι βρει.
     * Επιστρέφει 'paid', 'expired', 'canceled', 'pending' ή null σε σφάλμα.
     */
    public static function checkOrder(array $order, Api $api, array $gatewayParams)
    {
        $orderCode = (string) $order['order_code'];

        // Σημάδεψε ότι ελέγχθηκε τώρα, ώστε παράλληλες κλήσεις να μην
        // χτυπήσουν το API της Viva δεκάδες φορές για την ίδια παραγγελία.
        Capsule::table(Db::T_ORDERS)->where('order_code', $orderCode)
            ->update(['updated_at' => date('Y-m-d H:i:s')]);

        try {
            $state = (int) (($api->orderInfo($orderCode))['stateid'] ?? 0);
        } catch (ApiException $e) {
            Db::log('reconcile-error', $e->getMessage(), (int) $order['invoice_id'], $orderCode);
            return null;
        }

        if ($state === 3) {
            try {
                foreach ($api->orderTransactions($orderCode) as $tx) {
                    if (self::apply($order, $tx, $gatewayParams)) {
                        return 'paid';
                    }
                }
            } catch (ApiException $e) {
                Db::log('reconcile-error', $e->getMessage(), (int) $order['invoice_id'], $orderCode);
                return null;
            }
            return 'pending';
        }

        if ($state === 1 || $state === 2) {
            $status = $state === 1 ? 'expired' : 'canceled';
            Db::orderMark($orderCode, $status);
            return $status;
        }

        return 'pending';
    }

    /**
     * Άμεσος έλεγχος για ένα τιμολόγιο — καλείται όταν ο πελάτης ανοίγει τη
     * σελίδα του. Έτσι η εξόφληση φαίνεται αμέσως, χωρίς αναμονή για cron ή
     * webhook. Με φραγμό ρυθμού ώστε τα refresh να μη γίνουν καταιγισμός.
     */
    public static function checkInvoice($invoiceId, array $gatewayParams, $minSecondsBetweenChecks = 15)
    {
        $row = Capsule::table(Db::T_ORDERS)
            ->where('invoice_id', (int) $invoiceId)
            ->where('status', 'pending')
            ->orderBy('id', 'desc')
            ->first();

        if (!$row || strtotime($row->updated_at) > time() - (int) $minSecondsBetweenChecks) {
            return null;
        }

        return self::checkOrder((array) $row, Api::fromGatewayParams($gatewayParams), $gatewayParams);
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
            $summary['ελέγχθηκαν']++;
            switch (self::checkOrder((array) $row, $api, $gatewayParams)) {
                case 'paid':
                    $summary['εξοφλήθηκαν']++;
                    break;
                case 'expired':
                case 'canceled':
                    $summary['έληξαν']++;
                    break;
                case null:
                    $summary['σφάλματα']++;
                    break;
            }
        }

        return $summary;
    }
}
