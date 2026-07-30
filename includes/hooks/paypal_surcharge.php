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
  var row = null;

  function render() {
    var sel = document.querySelector('input[name=paymentmethod]:checked');
    var cfg = sel ? CFG[sel.value] : null;

    if (row) { row.parentNode.removeChild(row); row = null; }

    if (!cfg || !base) { total.textContent = baseText; return; }

    var p = cfg.percent / 100, f = cfg.fixed;
    var fee = cfg.grossUp && p < 1 ? (p * base + f) / (1 - p) : p * base + f;
    fee = Math.round(fee * 100) / 100;

    var anchor = total.parentNode;
    row = document.createElement('div');
    row.className = 'subtotal clearfix';
    row.style.cssText = 'padding:4px 0';
    row.innerHTML = '<span class="pull-left">' + cfg.labelEl + '</span>'
                  + '<span class="pull-right">' + money(fee) + '</span>';
    anchor.parentNode.insertBefore(row, anchor);

    total.textContent = money(base + fee);
  }

  document.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'paymentmethod') render();
  });
  render();
})();
</script>
HTML;
});
