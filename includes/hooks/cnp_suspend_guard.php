<?php
/**
 * Guard αυτόματης αναστολής — ΔΕΝ αναστέλλεται υπηρεσία που δεν χρωστάει τίποτα.
 *
 * Το περιστατικό (11/9/2026): το «ccmde1.cloudon.gr» (service #991) έκλεινε κάθε
 * μήνα από τον cron, παρότι ο πελάτης ήταν πλήρως εξοφλημένος. Αιτία: η
 * `nextduedate` δεν προχωρούσε μετά την εξόφληση (έμενε πίσω μία περίοδο), οπότε
 * μετά από `AutoSuspensionDays` ημέρες ο cron τη θεωρούσε ληξιπρόθεσμη. Επειδή
 * στο module hetznercloud το «Suspend» σημαίνει **power off**, ο πελάτης έμενε
 * χωρίς μηχάνημα χωρίς κανέναν λόγο. Γινόταν χειροκίνητο unsuspend και
 * ξανασυνέβαινε τον επόμενο μήνα.
 *
 * Κανόνας: πριν από κάθε ΑΥΤΟΜΑΤΗ αναστολή ελέγχουμε αν υπάρχει πραγματική
 * οφειλή (ανεξόφλητο/ληξιπρόθεσμο τιμολόγιο της υπηρεσίας ή του πελάτη):
 *   • Υπάρχει οφειλή        → η αναστολή προχωρά κανονικά (καμία παρέμβαση).
 *   • ΔΕΝ υπάρχει οφειλή    → ακυρώνεται (abortcmd), διορθώνεται η nextduedate
 *                             από τη `nextinvoicedate` (= τέλος πληρωμένης
 *                             περιόδου) και ειδοποιούνται οι διαχειριστές.
 *
 * Η ΧΕΙΡΟΚΙΝΗΤΗ αναστολή από διαχειριστή δεν επηρεάζεται ποτέ — μόνο η αυτόματη.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/* ΠΡΟΣΟΧΗ: μην δηλώνεις εδώ `cnp_guard_notify` — το cnp_delivery_guard.php το
   ορίζει ΧΩΡΙΣ function_exists, οπότε διπλή δήλωση ρίχνει όλο το WHMCS (fatal).
   Χρησιμοποιούμε δικό μας όνομα που απλώς προωθεί στον κοινό notifier. */
if (!function_exists('cnp_suspend_guard_notify')) {
    function cnp_suspend_guard_notify($title, $url = null)
    {
        if (function_exists('cnp_guard_notify')) {
            cnp_guard_notify($title, $url);
            return;
        }
        if (function_exists('logActivity')) {
            logActivity('CloudOn Guard: ' . $title);
        }
    }
}

/** Υπάρχει πραγματική οφειλή για τη συγκεκριμένη υπηρεσία ή τον πελάτη της; */
if (!function_exists('cnp_service_has_debt')) {
function cnp_service_has_debt($serviceid, $userid)
{
    $bad = ['Unpaid', 'Overdue', 'Collections'];
    // α) τιμολόγιο που περιέχει ρητά αυτή την υπηρεσία
    $own = Capsule::table('tblinvoiceitems as ii')
        ->join('tblinvoices as i', 'i.id', '=', 'ii.invoiceid')
        ->where('ii.relid', (int) $serviceid)->where('ii.type', 'Hosting')
        ->whereIn('i.status', $bad)->count();
    if ($own > 0) {
        return true;
    }
    // β) οποιοδήποτε ανοιχτό τιμολόγιο του πελάτη (μπορεί να τιμολογείται ομαδικά)
    return Capsule::table('tblinvoices')->where('userid', (int) $userid)
        ->whereIn('status', $bad)->count() > 0;
}
}

add_hook('PreModuleSuspend', 1, function ($vars) {
    $serviceid = (int) ($vars['serviceid'] ?? ($vars['params']['serviceid'] ?? 0));
    if (!$serviceid) {
        return;
    }
    try {
        /* Χειροκίνητη ενέργεια διαχειριστή → δεν μπαίνουμε εμπόδιο. Ο guard
           αφορά αποκλειστικά τις αυτόματες αναστολές του cron. */
        if (!empty($_SESSION['adminid'])) {
            return;
        }
        $h = Capsule::table('tblhosting')->where('id', $serviceid)
            ->first(['id', 'userid', 'domain', 'nextduedate', 'nextinvoicedate']);
        if (!$h) {
            return;
        }
        if (cnp_service_has_debt($serviceid, (int) $h->userid)) {
            return;                         // πραγματική οφειλή → η αναστολή είναι σωστή
        }

        /* Καμία οφειλή: η αναστολή είναι λανθασμένη. Διορθώνουμε την αιτία ώστε
           να μην επανέλθει — η nextinvoicedate δείχνει το τέλος της πληρωμένης
           περιόδου, άρα είναι η σωστή επόμενη ημερομηνία χρέωσης. */
        $fixed = '';
        if ($h->nextinvoicedate && $h->nextinvoicedate !== '0000-00-00'
            && $h->nextinvoicedate > $h->nextduedate) {
            Capsule::table('tblhosting')->where('id', $serviceid)
                ->update(['nextduedate' => $h->nextinvoicedate]);
            $fixed = ' — nextduedate ' . $h->nextduedate . ' -> ' . $h->nextinvoicedate;
        }
        cnp_suspend_guard_notify('Ακυρώθηκε λανθασμένη αναστολή «' . (string) $h->domain . '» (service #'
            . $serviceid . '): ο πελάτης δεν έχει καμία οφειλή' . $fixed,
            'clientsservices.php?userid=' . (int) $h->userid . '&id=' . $serviceid);
        return ['abortcmd' => true];        // ΜΠΛΟΚ της αναστολής
    } catch (\Throwable $e) {
        if (function_exists('logActivity')) {
            logActivity('cnp_suspend_guard σφάλμα (PreModuleSuspend): ' . $e->getMessage());
        }
    }
});
