<?php
/**
 * Χρέωση διεκπεραίωσης PayPal.
 *
 * Το PayPal κρατάει περίπου 5,4% + πάγιο σε κάθε είσπραξη — πολλαπλάσιο της
 * Viva. Όταν ο πελάτης επιλέγει PayPal ως τρόπο πληρωμής, προσθέτουμε στο
 * τιμολόγιο γραμμή με το κόστος αυτό, ώστε το περιθώριο να μη χάνεται.
 *
 * ΝΟΜΙΚΟ ΠΛΑΙΣΙΟ — διάβασέ το πριν αλλάξεις ρυθμίσεις:
 *  - Η προσαύξηση επιτρέπεται μόνο εφόσον ΔΕΝ ξεπερνά το πραγματικό κόστος
 *    (Οδηγία 2011/83/ΕΕ άρ. 19). Γι' αυτό τα ποσοστά παρακάτω προέκυψαν από
 *    τα ίδια τα fee που έχει χρεώσει το PayPal στον λογαριασμό μας.
 *  - Απαγορεύεται σε ΚΑΡΤΕΣ καταναλωτών ΕΕ και σε SEPA (PSD2 άρ. 62(4),
 *    ν. 4537/2018). Γι' αυτό η Viva ΔΕΝ μπαίνει ποτέ στη λίστα παρακάτω.
 *  - Πρέπει να φαίνεται στον πελάτη ΠΡΙΝ πληρώσει — γι' αυτό μπαίνει ως
 *    ορατή γραμμή στο τιμολόγιο και όχι ως κρυφή προσαύξηση.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Ποιες μέθοδοι επιβαρύνονται και με τι.
 *
 * Τα νούμερα προήλθαν από γραμμική παλινδρόμηση στις πραγματικές χρεώσεις
 * του PayPal των τελευταίων μηνών: 5,4% + 0,31 EUR. Στρογγυλοποιήθηκαν προς
 * το δημοσιευμένο τιμολόγιο του PayPal για Ελλάδα.
 *
 * ΠΟΤΕ μη βάλεις εδώ vivapayments ή banktransfer.
 */
const CNP_SURCHARGE = [
    'paypal' => [
        'percent'  => 5.40,   // ποσοστό επί του ποσού είσπραξης
        'fixed'    => 0.35,   // πάγιο ανά συναλλαγή, σε EUR
        'grossUp'  => true,   // το PayPal χρεώνει και πάνω στη χρέωση· κάλυψέ το
        'taxed'    => false,  // η χρέωση δεν επιβαρύνεται με ΦΠΑ
        'labelEl'  => 'Χρέωση διεκπεραίωσης PayPal',
        'labelEn'  => 'PayPal handling fee',
    ],
];

/** Σήμα αναγνώρισης της γραμμής μας μέσα στο τιμολόγιο. */
const CNP_SURCHARGE_TYPE = 'cnp_gwfee';

/**
 * Υπολογίζει τη χρέωση για δεδομένη βάση.
 *
 * Με grossUp, λύνουμε ως προς F την εξίσωση F = p(B+F) + f, ώστε μετά την
 * κράτηση του PayPal να μας μένει ακέραιο το B. Χωρίς αυτό, το PayPal χρεώνει
 * και πάνω στη χρέωση και μένουμε πάλι πίσω.
 */
function cnp_surcharge_amount($base, array $cfg)
{
    $p = ((float) $cfg['percent']) / 100;
    $f = (float) $cfg['fixed'];

    if (!empty($cfg['grossUp']) && $p < 1) {
        $fee = ($p * $base + $f) / (1 - $p);
    } else {
        $fee = $p * $base + $f;
    }

    return round($fee, 2);
}

/** Σβήνει τυχόν υπάρχουσα γραμμή χρέωσης από το τιμολόγιο. */
function cnp_surcharge_remove($invoiceId)
{
    return Capsule::table('tblinvoiceitems')
        ->where('invoiceid', (int) $invoiceId)
        ->where('type', CNP_SURCHARGE_TYPE)
        ->delete();
}

/**
 * Συγχρονίζει τη γραμμή χρέωσης ενός τιμολογίου με τον επιλεγμένο τρόπο
 * πληρωμής. Πρώτα σβήνει ό,τι υπάρχει, μετά προσθέτει αν χρειάζεται — έτσι
 * η εναλλαγή μεταξύ μεθόδων δεν αφήνει ποτέ διπλές ή ξεχασμένες γραμμές.
 */
function cnp_surcharge_sync($invoiceId, $gateway)
{
    require_once ROOTDIR . '/includes/invoicefunctions.php';

    $invoice = Capsule::table('tblinvoices')->where('id', (int) $invoiceId)->first();
    if (!$invoice) {
        return;
    }

    // Εξοφλημένα, ακυρωμένα ή διαγραμμένα δεν τα πειράζουμε ποτέ.
    if (!in_array($invoice->status, ['Unpaid', 'Draft', 'Payment Pending'], true)) {
        return;
    }

    $had = cnp_surcharge_remove($invoiceId);
    $cfg = CNP_SURCHARGE[$gateway] ?? null;

    if (!$cfg) {
        if ($had) {
            updateInvoiceTotal($invoiceId);
        }
        return;
    }

    // Βάση = ό,τι θα εισπράξουμε στην πράξη: σύνολο μετά ΦΠΑ, μείον πίστωση,
    // χωρίς την προηγούμενη χρέωση (μόλις σβήστηκε).
    updateInvoiceTotal($invoiceId);
    $inv = Capsule::table('tblinvoices')->where('id', (int) $invoiceId)->first();
    $base = (float) $inv->total - (float) $inv->credit;

    if ($base <= 0) {
        return;
    }

    $client = Capsule::table('tblclients')->where('id', $invoice->userid)->first();
    $isEn = strtolower((string) ($client->language ?? '')) === 'english';

    Capsule::table('tblinvoiceitems')->insert([
        'invoiceid'     => (int) $invoiceId,
        'userid'        => (int) $invoice->userid,
        'type'          => CNP_SURCHARGE_TYPE,
        'relid'         => 0,
        'description'   => $isEn ? $cfg['labelEn'] : $cfg['labelEl'],
        'amount'        => number_format(cnp_surcharge_amount($base, $cfg), 2, '.', ''),
        'taxed'         => !empty($cfg['taxed']) ? 1 : 0,
        'duedate'       => $invoice->duedate,
        'paymentmethod' => '',
        'notes'         => '',
    ]);

    updateInvoiceTotal($invoiceId);
}

/**
 * Ο πελάτης άλλαξε τρόπο πληρωμής πάνω στο τιμολόγιο. Το WHMCS καλεί αυτό το
 * hook ακριβώς γι' αυτόν τον σκοπό.
 */
add_hook('InvoiceChangeGateway', 1, function ($vars) {
    if (!empty($vars['invoiceid'])) {
        cnp_surcharge_sync($vars['invoiceid'], (string) ($vars['paymentmethod'] ?? ''));
    }
});

/**
 * Νέο τιμολόγιο — π.χ. αυτόματη ανανέωση πελάτη που έχει PayPal ως προεπιλογή.
 * Χωρίς αυτό, η χρέωση θα έμπαινε μόνο σε όσους αλλάζουν μέθοδο χειροκίνητα.
 */
add_hook('InvoiceCreated', 1, function ($vars) {
    $invoiceId = (int) ($vars['invoiceid'] ?? 0);
    if (!$invoiceId) {
        return;
    }
    $inv = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if ($inv) {
        cnp_surcharge_sync($invoiceId, (string) $inv->paymentmethod);
    }
});
