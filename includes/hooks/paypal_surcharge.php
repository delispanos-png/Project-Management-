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

/** Όλες οι ετικέτες που χρησιμοποιούμε, για αναγνώριση γραμμών από το καλάθι. */
function cnp_surcharge_labels()
{
    $out = [];
    foreach (CNP_SURCHARGE as $cfg) {
        $out[] = $cfg['labelEl'];
        $out[] = $cfg['labelEn'];
    }
    return $out;
}

/**
 * Σβήνει τυχόν υπάρχουσα γραμμή χρέωσης από το τιμολόγιο.
 *
 * Ψάχνει και με ετικέτα, όχι μόνο με type: όταν η παραγγελία έρχεται από το
 * καλάθι, τη γραμμή τη δημιουργεί το WHMCS και δεν φέρει το δικό μας type.
 * Χωρίς αυτό θα προσθέταμε δεύτερη και θα διπλοχρεώναμε.
 */
function cnp_surcharge_remove($invoiceId)
{
    return Capsule::table('tblinvoiceitems')
        ->where('invoiceid', (int) $invoiceId)
        ->where(function ($q) {
            $q->where('type', CNP_SURCHARGE_TYPE)
              ->orWhereIn('description', cnp_surcharge_labels());
        })
        ->delete();
}

/** Ο τρόπος πληρωμής που έχει επιλεγεί αυτή τη στιγμή στο καλάθι. */
function cnp_surcharge_cart_gateway()
{
    return (string) ($_REQUEST['paymentmethod'] ?? ($_SESSION['cart']['paymentmethod'] ?? ''));
}

/**
 * Ξαναϋπολογίζει τα σύνολα τιμολογίου από τις γραμμές του.
 *
 * ΓΙΑΤΙ ΔΕΝ ΧΡΗΣΙΜΟΠΟΙΟΥΜΕ ΤΟ updateInvoiceTotal() ΤΟΥ WHMCS: δοκιμάστηκε και
 * ΔΕΝ ξαναϋπολογίζει το υποσύνολο από τις γραμμές — άφηνε τιμολόγιο με γραμμές
 * 11,92 να δείχνει υποσύνολο 10,79, δηλαδή η χρέωση δεν εισπραττόταν ποτέ.
 */
function cnp_invoice_recalc($invoiceId)
{
    $inv = Capsule::table('tblinvoices')->where('id', (int) $invoiceId)->first();
    if (!$inv) {
        return;
    }

    $subtotal = 0.0;
    $taxable  = 0.0;
    foreach (Capsule::table('tblinvoiceitems')->where('invoiceid', (int) $invoiceId)->get() as $it) {
        $subtotal += (float) $it->amount;
        if ((int) $it->taxed === 1) {
            $taxable += (float) $it->amount;
        }
    }

    $rate1 = (float) $inv->taxrate;
    $rate2 = (float) $inv->taxrate2;

    // Ο λογαριασμός δουλεύει με φόρο «Exclusive»: ο ΦΠΑ προστίθεται επάνω.
    $tax  = round($taxable * $rate1 / 100, 2);
    $tax2 = round($taxable * $rate2 / 100, 2);

    $total = round($subtotal + $tax + $tax2 - (float) $inv->credit, 2);

    Capsule::table('tblinvoices')->where('id', (int) $invoiceId)->update([
        'subtotal' => number_format($subtotal, 2, '.', ''),
        'tax'      => number_format($tax, 2, '.', ''),
        'tax2'     => number_format($tax2, 2, '.', ''),
        'total'    => number_format($total, 2, '.', ''),
    ]);
}

/**
 * Συγχρονίζει τη γραμμή χρέωσης ενός τιμολογίου με τον επιλεγμένο τρόπο
 * πληρωμής. Πρώτα σβήνει ό,τι υπάρχει, μετά προσθέτει αν χρειάζεται — έτσι
 * η εναλλαγή μεταξύ μεθόδων δεν αφήνει ποτέ διπλές ή ξεχασμένες γραμμές.
 */
function cnp_surcharge_sync($invoiceId, $gateway)
{
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
            cnp_invoice_recalc($invoiceId);
        }
        return;
    }

    /* Βάση = ό,τι θα εισπράξουμε ΣΤΗΝ ΠΡΑΞΗ μέσω της πύλης: σύνολο μετά ΦΠΑ,
       μείον πίστωση, μείον όσα έχουν ήδη τακτοποιηθεί ΕΣΩΤΕΡΙΚΑ (πιστωτικά
       σημειώματα, επιστροφές πίστωσης — εγγραφές χωρίς gateway).

       ΓΙΑΤΙ: όταν ο πελάτης ακυρώνει υπηρεσία «στη λήξη της περιόδου», το WHMCS
       ΔΕΝ σβήνει τη γραμμή· της αφαιρεί type/relid και εκδίδει πιστωτικό για το
       ποσό της. Το σύνολο μένει ίδιο, οπότε η χρέωση διεκπεραίωσης υπολογιζόταν
       πάνω σε ποσό που δεν θα εισπραχθεί ποτέ — και το τιμολόγιο έμενε με
       υπόλοιπο-φάντασμα που δεν μηδενιζόταν με τίποτα.

       Οι πραγματικές πληρωμές πύλης ΔΕΝ αφαιρούνται: αν τις μετρούσαμε, μετά
       την πληρωμή η χρέωση θα μηδενιζόταν αναδρομικά. */
    cnp_invoice_recalc($invoiceId);
    $inv = Capsule::table('tblinvoices')->where('id', (int) $invoiceId)->first();
    $internal = (float) Capsule::table('tblaccounts')->where('invoiceid', (int) $invoiceId)
        ->where(function ($q) { $q->whereNull('gateway')->orWhere('gateway', ''); })
        ->sum(Capsule::raw('amountin - amountout'));
    $base = (float) $inv->total - (float) $inv->credit - $internal;

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

    cnp_invoice_recalc($invoiceId);
}

/**
 * Το τιμολόγιο άλλαξε μετά τη δημιουργία του — τυπικά όταν ακυρώνεται γραμμή
 * ανανέωσης επειδή ο πελάτης ακύρωσε υπηρεσία «στη λήξη της περιόδου».
 *
 * Χωρίς αυτό, η χρέωση διεκπεραίωσης έμενε υπολογισμένη στο παλιό, μεγαλύτερο
 * ποσό και το τιμολόγιο δεν μπορούσε να μηδενιστεί ποτέ.
 *
 * Ο φρουρός αναδρομής είναι απαραίτητος: ο συγχρονισμός γράφει στο τιμολόγιο
 * και θα ξανακαλούσε τον εαυτό του.
 */
add_hook('UpdateInvoiceTotal', 1, function ($vars) {
    static $busy = [];
    $invoiceId = (int) ($vars['invoiceid'] ?? 0);
    if (!$invoiceId || isset($busy[$invoiceId])) {
        return;
    }
    $inv = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if (!$inv || !in_array($inv->status, ['Unpaid', 'Draft', 'Payment Pending'], true)) {
        return;
    }
    if (!isset(CNP_SURCHARGE[(string) $inv->paymentmethod])) {
        return;   // δεν είναι πύλη με χρέωση — δεν αγγίζουμε τίποτα
    }
    $busy[$invoiceId] = true;
    try {
        cnp_surcharge_sync($invoiceId, (string) $inv->paymentmethod);
    } catch (\Throwable $e) {
        // Ποτέ δεν σπάμε τη ροή τιμολόγησης για μια χρέωση διεκπεραίωσης.
    }
    unset($busy[$invoiceId]);
});

/**
 * Ο πελάτης άλλαξε τρόπο πληρωμής πάνω στο τιμολόγιο. Το WHMCS καλεί αυτό το
 * hook ακριβώς γι' αυτόν τον σκοπό.
 */
add_hook('InvoiceChangeGateway', 1, function ($vars) {
    if (function_exists('cnp_beat')) { cnp_beat('gateway_change'); }
    if (!empty($vars['invoiceid'])) {
        cnp_surcharge_sync($vars['invoiceid'], (string) ($vars['paymentmethod'] ?? ''));
    }
});

/**
 * Νέο τιμολόγιο — π.χ. αυτόματη ανανέωση πελάτη που έχει PayPal ως προεπιλογή.
 * Χωρίς αυτό, η χρέωση θα έμπαινε μόνο σε όσους αλλάζουν μέθοδο χειροκίνητα.
 */
add_hook('InvoiceCreated', 1, function ($vars) {
    if (function_exists('cnp_beat')) { cnp_beat('invoice_created'); }
    $invoiceId = (int) ($vars['invoiceid'] ?? 0);
    if (!$invoiceId) {
        return;
    }
    $inv = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if ($inv) {
        cnp_surcharge_sync($invoiceId, (string) $inv->paymentmethod);
    }
});

/*
 * ΓΙΑΤΙ ΔΕΝ ΧΡΗΣΙΜΟΠΟΙΟΥΜΕ ΤΟ CartTotalAdjustment
 *
 * Θα ήταν ο «σωστός» τρόπος, αλλά το πρότυπο horn δεν εμφανίζει καθόλου
 * προσαρμογές καλαθιού — η σύνοψη έχει μόνο υποσύνολο, έκπτωση, ΦΠΑ, σύνολο.
 * Η χρέωση θα άλλαζε το σύνολο ΑΟΡΑΤΑ, που είναι και παραπλανητικό για τον
 * πελάτη και θα μετριόταν δύο φορές μαζί με το script παρακάτω.
 *
 * Αντ' αυτού: στο ταμείο τη δείχνει το script (μία πηγή αλήθειας για την
 * οθόνη), και στο τιμολόγιο τη βάζει το InvoiceCreated (μία πηγή αλήθειας
 * για τη χρέωση). Τα δύο συμφωνούν γιατί χρησιμοποιούν τον ίδιο τύπο.
 */

/**
 * Στο ταμείο, η αλλαγή τρόπου πληρωμής δεν ξαναφορτώνει τη σελίδα — άρα ο
 * πελάτης δεν θα έβλεπε τη χρέωση να εμφανίζεται. Ενημερώνουμε τη σύνοψη
 * επιτόπου, με τον ίδιο τύπο που χρησιμοποιεί ο server.
 */
add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    // Το templatefile στο ταμείο δεν είναι σταθερό ανά έκδοση/πρότυπο, οπότε
    // κρινόμαστε από το ίδιο το script: το ταμείο ζει πάντα στο cart.php.
    $onCart = strpos((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 'cart.php') !== false;
    if (!$onCart) {
        return '';
    }
    if (function_exists('cnp_beat')) { cnp_beat('cart_footer'); }

    $cfgJson = json_encode(CNP_SURCHARGE, JSON_UNESCAPED_UNICODE);

    return <<<HTML
<script>
(function () {
  var CFG = {$cfgJson};
  var total = document.getElementById('totalDueToday');
  if (!total) return;

  // "13,38 €" ή "€13.38" → 13.38
  function num(t) {
    t = (t || '').replace(/[^0-9.,-]/g, '').trim();
    if (t.indexOf(',') > -1 && t.indexOf('.') > -1) {
      t = t.lastIndexOf(',') > t.lastIndexOf('.') ? t.replace(/\./g, '').replace(',', '.') : t.replace(/,/g, '');
    } else if (t.indexOf(',') > -1) {
      t = t.replace(',', '.');
    }
    return parseFloat(t) || 0;
  }
  function money(v) { return v.toFixed(2).replace('.', ',') + ' €'; }

  var baseText = total.textContent, base = num(baseText);
  // Κράτα τον διαχωριστή του προτύπου (26.76 ή 26,76) για να μη «χτυπάει».
  var dec = baseText.indexOf(',') > baseText.indexOf('.') ? ',' : '.';
  function fmt(v) { return v.toFixed(2).replace('.', dec) + ' €'; }

  var row = null, last = null;

  function selected() {
    var el = document.querySelector('input[name="paymentmethod"]:checked');
    return el ? el.value : '';
  }

  function render(gw) {
    var cfg = CFG[gw] || null;

    if (row && row.parentNode) { row.parentNode.removeChild(row); }
    row = null;

    if (!cfg || !base) { total.textContent = baseText; return; }

    var p = cfg.percent / 100, f = cfg.fixed;
    var fee = cfg.grossUp && p < 1 ? (p * base + f) / (1 - p) : p * base + f;
    fee = Math.round(fee * 100) / 100;

    var anchor = total.parentNode;
    row = document.createElement('div');
    row.className = 'subtotal clearfix cnp-fee-row';
    row.style.cssText = 'padding:4px 0';
    row.innerHTML = '<span class="pull-left">' + cfg.labelEl + '</span>'
                  + '<span class="pull-right">' + fmt(fee) + '</span>';
    anchor.parentNode.insertBefore(row, anchor);

    total.textContent = fmt(base + fee);
  }

  function sync() {
    var gw = selected();
    if (gw !== last) { last = gw; render(gw); }
  }

  // Τα γεγονότα δεν αρκούν: αν το πρότυπο επιλέγει το radio μέσω JavaScript,
  // το change δεν πυροδοτείται ποτέ. Γι' αυτό ελέγχουμε και περιοδικά.
  document.addEventListener('change', sync, true);
  document.addEventListener('click', function () { setTimeout(sync, 0); }, true);
  setInterval(sync, 400);
  sync();
})();
</script>
HTML;
});
