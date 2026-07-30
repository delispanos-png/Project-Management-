<?php
/**
 * Παλμός κρίσιμων hooks.
 *
 * ΓΙΑΤΙ ΥΠΑΡΧΕΙ: μετά από αναβάθμιση WHMCS, ένα hook που μετονομάστηκε ή άλλαξε
 * υπογραφή **δεν βγάζει σφάλμα** — ο κώδικάς μας απλώς δεν καλείται ποτέ ξανά.
 * Οι πληρωμές Viva σταματούν να καταχωρούνται, η χρέωση PayPal παύει να μπαίνει,
 * και δεν το προσέχει κανείς για μέρες. Ο έλεγχος αρχείων δεν πιάνει τέτοια βλάβη
 * γιατί τα αρχεία υπάρχουν κανονικά.
 *
 * Εδώ κάθε κρίσιμος χειριστής αφήνει χρονοσήμανση. Το scripts/healthcheck.php
 * προειδοποιεί όταν κάποιος σιωπά περισσότερο απ' όσο επιτρέπεται.
 *
 * ΚΟΣΤΟΣ: μία εγγραφή ανά hook ανά 10 λεπτά, όχι ανά αίτημα.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

const CNP_HEARTBEAT_TABLE = 'mod_cnp_heartbeat';

/** Κάθε πόσο το πολύ γράφουμε στη βάση, ανά hook. */
const CNP_HEARTBEAT_THROTTLE = 600;

/**
 * Ανώτατη ανεκτή σιωπή ανά παλμό, σε ώρες.
 * Χαμηλή τιμή = περιμένουμε συχνή δραστηριότητα· υψηλή = σπάνιο γεγονός.
 */
const CNP_HEARTBEAT_MAX_SILENCE = [
    'clientarea'      => 24,      // κάθε επίσκεψη πελάτη
    'cron'            => 2,       // κάθε 5 λεπτά
    'cart_footer'     => 24 * 14, // όταν κάποιος φτάνει στο ταμείο
    'invoice_created' => 24 * 7,  // με κάθε νέο τιμολόγιο
    'gateway_change'  => 24 * 30, // όταν πελάτης αλλάζει τρόπο πληρωμής
    'invoice_paid'    => 24 * 7,  // με κάθε εξόφληση
    'viva_reconcile'  => 2,       // σε κάθε cron, εφόσον η Viva είναι ρυθμισμένη
];

/** Καταγράφει ότι ο συγκεκριμένος χειριστής όντως εκτελέστηκε. */
function cnp_beat($name)
{
    static $done = [];
    if (isset($done[$name])) {
        return;   // μία φορά ανά αίτημα
    }
    $done[$name] = true;

    try {
        if (!Capsule::schema()->hasTable(CNP_HEARTBEAT_TABLE)) {
            Capsule::statement('CREATE TABLE `' . CNP_HEARTBEAT_TABLE . '` (
                `hook` VARCHAR(64) NOT NULL,
                `last_at` DATETIME NOT NULL,
                `hits` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`hook`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        }

        $row = Capsule::table(CNP_HEARTBEAT_TABLE)->where('hook', $name)->first();
        $now = time();

        if (!$row) {
            Capsule::table(CNP_HEARTBEAT_TABLE)->insert([
                'hook' => $name, 'last_at' => date('Y-m-d H:i:s', $now), 'hits' => 1,
            ]);
            return;
        }

        // Στραγγαλισμός: δεν γράφουμε σε κάθε φόρτωση σελίδας.
        if ($now - strtotime($row->last_at) < CNP_HEARTBEAT_THROTTLE) {
            return;
        }

        Capsule::table(CNP_HEARTBEAT_TABLE)->where('hook', $name)->update([
            'last_at' => date('Y-m-d H:i:s', $now),
            'hits'    => (int) $row->hits + 1,
        ]);
    } catch (\Throwable $e) {
        // Ο παλμός δεν επιτρέπεται ποτέ να ρίξει σελίδα.
    }
}

/*
 * Οι παλμοί μπαίνουν σε ΔΙΚΟΥΣ ΜΑΣ χειριστές, όχι σε ξεχωριστά hooks — έτσι
 * αποδεικνύεται ότι εκτελέστηκε ο κώδικάς μας, όχι απλώς ότι πυροδοτήθηκε το
 * γεγονός. Οι παρακάτω καλύπτουν όσα δεν έχουν δικό τους σημείο αλλού.
 */
add_hook('ClientAreaPage', 99, function () { cnp_beat('clientarea'); });
add_hook('AfterCronJob', 99, function () { cnp_beat('cron'); });
add_hook('InvoicePaid', 99, function () { cnp_beat('invoice_paid'); });
