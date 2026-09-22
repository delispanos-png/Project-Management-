<?php
/* ΜΟΝΟ ΑΠΟ ΓΡΑΜΜΗ ΕΝΤΟΛΩΝ (20/09/2026).
   Ο φάκελος βρίσκεται μέσα στο webroot, οπότε το script ήταν εκτελέσιμο από
   ΟΠΟΙΟΝΔΗΠΟΤΕ στο internet με ένα απλό GET — μετρήθηκε: και τα οκτώ cron
   απαντούσαν HTTP 200 και έτρεχαν κανονικά. Αυτό σημαίνει ότι ένας ξένος
   μπορούσε να χτυπά το τηλεφωνικό κέντρο με συγχρονισμούς, να στέλνει
   ειδοποιήσεις στην ομάδα και αναφορές σε πελάτες, όσες φορές ήθελε.
   Όλα τα cron τρέχουν από το /etc/cron.d με CLI php, άρα δεν χάνεται τίποτα. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * CloudOn Project Manager — pulse cron (κάθε 10΄).
 * Στέλνει τις προσωπικές υπενθυμίσεις που έφτασε η ώρα τους (καμπανάκι + email).
 */

define('WHMCS', true);
require __DIR__ . '/../../../../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudonProjects\Db;
use WHMCS\Module\Addon\CloudonProjects\Notify;
use WHMCS\Module\Addon\CloudonProjects\Auto;
use WHMCS\Module\Addon\CloudonProjects\Deadlines;
use WHMCS\Module\Addon\CloudonProjects\TicketIdle;
use WHMCS\Module\Addon\CloudonProjects\Overrun;

require_once __DIR__ . '/../lib/Db.php';
require_once __DIR__ . '/../lib/Notify.php';
require_once __DIR__ . '/../lib/Auto.php';
require_once __DIR__ . '/../lib/Deadlines.php';
require_once __DIR__ . '/../lib/TicketIdle.php';
require_once __DIR__ . '/../lib/Overrun.php';

/* SLA breaches → automations (μία φορά ανά ticket, dedupe στο Auto::once) */
try {
    if (Capsule::schema()->hasTable('mod_supportcontracts_tickets')) {
        $breaches = Capsule::table('mod_supportcontracts_tickets as st')
            ->join('tbltickets as t', 't.id', '=', 'st.ticketid')
            ->whereNotIn('t.status', ['Closed', 'Cancelled'])
            ->whereNotNull('st.sla_due')->where('st.sla_due', '<', date('Y-m-d H:i:s'))
            ->whereNull('st.first_response_at')->pluck('st.ticketid')->all();
        foreach ($breaches as $bt) {
            Auto::run('sla_breach', ['ticketId' => (int) $bt]);
        }
        if (count($breaches)) {
            echo '[' . date('H:i:s') . '] SLA checks: ' . count($breaches) . " breaches\n";
        }
    }
} catch (\Throwable $e) {
}

$due = Db::dueReminders();
foreach ($due as $r) {
    Notify::reminder($r);
    Db::markReminderSent($r->id);
    echo '[' . date('H:i:s') . "] υπενθύμιση #{$r->id} → admin {$r->admin_id}\n";
}
if (count($due)) {
    echo count($due) . " υπενθυμίσεις εστάλησαν\n";
}

/* ⏰ Υπενθυμίσεις «Το πλάνο μου» (todos με ώρα) → καμπανάκι */
try {
    $todoDue = Capsule::table('mod_cpm_todos')->where('done', 0)->where('remind_sent', 0)
        ->whereNotNull('remind_at')->where('remind_at', '<=', date('Y-m-d H:i:s'))->get();
    foreach ($todoDue as $td) {
        Db::pushNotification((int) $td->admin_id, 'due', '⏰ Υπενθύμιση: ' . mb_substr($td->text, 0, 90), '/project/#/todos');
        Capsule::table('mod_cpm_todos')->where('id', $td->id)->update(['remind_sent' => 1]);
    }
    if (count($todoDue)) {
        echo count($todoDue) . " todo reminders\n";
    }
} catch (\Throwable $e) {
}

/* 📄 Έγγραφα βιβλιοθήκης που λήγουν (≤7 ημέρες ή έληξαν) — μία ειδοποίηση ανά έγγραφο */
try {
    $soon = date('Y-m-d', strtotime('+7 days'));
    $expDue = Capsule::table('mod_cpm_library')->where('exp_notified', 0)
        ->whereNotNull('expires_at')->where('expires_at', '<=', $soon)->get();
    foreach ($expDue as $lb) {
        $days = (int) floor((strtotime($lb->expires_at) - time()) / 86400);
        $msg = $days < 0 ? 'Έληξε: ' . $lb->title : ($days === 0 ? 'Λήγει σήμερα: ' . $lb->title : 'Λήγει σε ' . $days . ' ημ.: ' . $lb->title);
        Db::pushNotification((int) $lb->admin_id, 'due', $msg, '/project/#/library');
        Capsule::table('mod_cpm_library')->where('id', $lb->id)->update(['exp_notified' => 1]);
    }
    if (count($expDue)) {
        echo count($expDue) . " expiry alerts\n";
    }
} catch (\Throwable $e) {
}

/* ⏳ Προθεσμίες: προειδοποίηση πριν χαθούν και κλιμάκωση όταν χαθούν.
   Dedupe μέσα στην Deadlines (unique index) — ασφαλές να τρέχει κάθε 10΄. */
try {
    $dlSent = Deadlines::run(getenv('CPM_DRY') ? true : false);
    if ($dlSent) {
        echo '[' . date('H:i:s') . "] προθεσμίες: $dlSent ειδοποιήσεις\n";
    }
} catch (\Throwable $e) {
    echo '[' . date('H:i:s') . '] προθεσμίες ΣΦΑΛΜΑ: ' . $e->getMessage() . "\n";
}

/* ⚠ Υπέρβαση εκτίμησης (ώρες / ημέρες): ο επικεφαλής της ομάδας μαθαίνει ότι κάποιος
   ξεπέρασε την εκτίμησή του, για να ρωτήσει αν χρειάζεται βοήθεια. Dedupe ανά σκαλί. */
try {
    $ovSent = Overrun::run(getenv('CPM_DRY') ? true : false);
    if ($ovSent) {
        echo '[' . date('H:i:s') . "] υπερβάσεις: $ovSent ειδοποιήσεις\n";
    }
} catch (\Throwable $e) {
    echo '[' . date('H:i:s') . '] υπερβάσεις ΣΦΑΛΜΑ: ' . $e->getMessage() . "\n";
}

/* 🗂 Κάρτες διαχείρισης (22/9/2026): η ουρά αποφάσεων των PM χτίζεται ΜΙΑ φορά το πρωί
   (08:00–09:00) και η κλιμάκωση τρέχει σε κάθε πέρασμα, ώστε ό,τι έμεινε αναπάντητο μετά
   την προθεσμία να ανεβαίνει στους Manager. Η λογική ζει στο api.php (ίδια με την οθόνη). */
try {
    require_once __DIR__ . '/../../../../projectmanagement/boot.php';
    $hourC = (int) date('G');
    $kC = hash_hmac('sha256', 'sweep.' . date('YmdHi'), pm_secret());
    $chC = curl_init('https://my.cloudon.gr/projectmanagement/api.php?a=cards_build&k=' . $kC);
    curl_setopt_array($chC, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_USERAGENT => 'cpm-pulse']);
    $rC = json_decode((string) curl_exec($chC), true); curl_close($chC);
    if (!empty($rC['created']) || !empty($rC['escalated'])) {
        echo '[' . date('H:i:s') . '] κάρτες διαχείρισης: ' . (int) ($rC['created'] ?? 0) . ' νέες, '
            . (int) ($rC['escalated'] ?? 0) . " κλιμακώθηκαν\n";
    }
} catch (\Throwable $e) {
    echo '[' . date('H:i:s') . '] κάρτες διαχείρισης ΣΦΑΛΜΑ: ' . $e->getMessage() . "\n";
}

/* ☎ Κατάσταση → 3CX (21/9/2026): οι αυτόματες αλλαγές (σύσκεψη, Λείπω, Εκτός) περνούν στο
   κέντρο κι όταν κανείς δεν έχει την εφαρμογή ανοιχτή. Η λογική της κατάστασης ζει στο
   api.php, γι' αυτό καλείται μέσω HTTP με υπογραφή της ώρας (pm_secret). */
try {
    require_once __DIR__ . '/../../../../projectmanagement/boot.php';
    $kS = hash_hmac('sha256', 'sweep.' . date('YmdHi'), pm_secret());
    $ch = curl_init('https://my.cloudon.gr/projectmanagement/api.php?a=presence_sweep&k=' . $kS);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => 'cpm-pulse']);
    $rS = json_decode((string) curl_exec($ch), true); curl_close($ch);
    if (!empty($rS['pushed'])) { echo '[' . date('H:i:s') . '] 3CX κατάσταση: ' . (int) $rS['pushed'] . " αλλαγές\n"; }
    elseif (!is_array($rS)) { echo '[' . date('H:i:s') . "] 3CX κατάσταση: καμία απάντηση\n"; }
} catch (\Throwable $e) {
    echo '[' . date('H:i:s') . '] 3CX κατάσταση ΣΦΑΛΜΑ: ' . $e->getMessage() . "\n";
}

/* 🔕 Tickets που περιμένουν τον πελάτη: υπενθύμιση και μετά αυτόματο κλείσιμο.
   Στέλνει μηνύματα σε πελάτες — τρέχει ΜΟΝΟ αν έχει ενεργοποιηθεί ρητά η
   ρύθμιση `ticket_autoclose` του addon. */
try {
    if (TicketIdle::enabled()) {
        $ti = TicketIdle::run(getenv('CPM_DRY') ? true : false);
        if ($ti['warned'] || $ti['closed']) {
            echo '[' . date('H:i:s') . '] tickets: ' . count($ti['warned']) . ' υπενθυμίσεις, '
                . count($ti['closed']) . " κλεισίματα\n";
        }
    }
} catch (\Throwable $e) {
    echo '[' . date('H:i:s') . '] ticket autoclose ΣΦΑΛΜΑ: ' . $e->getMessage() . "\n";
}

/* ☎ Τηλεφωνική δραστηριότητα: τραβάμε τις κλήσεις από τις αναφορές του 3CX.
   Δύο ημέρες κάθε φορά, γιατί μια κλήση που ξεκίνησε αργά χθες συμπληρώνεται
   σήμερα. Η άντληση είναι ιδεμποτική — ό,τι έγραψε ο συνάδελφος μετά την κλήση
   (περίληψη, χρέωση, αιτιολογία) δεν πειράζεται.

   ΓΙΑΤΙ ΕΔΩ ΚΑΙ ΟΧΙ ΜΕ SOCKET: το CDR του 3CX θα ήθελε μόνιμη υπηρεσία που
   ακούει σε ανοιχτή θύρα. Οι αναφορές απαντούν σε δέκατα του δευτερολέπτου και
   δεν χρειάζονται τίποτα απ' αυτά. */
try {
    require_once __DIR__ . '/../lib/Pbx3cx/Client.php';
    require_once __DIR__ . '/../lib/Pbx3cx/Cdr.php';
    require_once __DIR__ . '/../lib/Pbx3cx/Report.php';
    if (\WHMCS\Module\Addon\CloudonProjects\Pbx3cxClient::configured()) {
        $cl = \WHMCS\Module\Addon\CloudonProjects\Pbx3cxReport::range(
            date('Y-m-d', strtotime('-1 day')), date('Y-m-d'));
        if ($cl['new'] || $cl['updated']) {
            echo '[' . date('H:i:s') . '] κλήσεις: ' . $cl['new'] . ' νέες, '
                . $cl['updated'] . " ενημερώθηκαν\n";
        }
        /* Η AI ρεσεψιόν δεν έχει ρολόι: της λέμε εμείς αν είμαστε ανοιχτά, σε
           έκτακτη γραμμή ή κλειστά. Ένα PATCH μόνο όταν αλλάζει η κατάσταση. */
        require_once __DIR__ . '/../lib/Pbx3cx/Blueprint.php';
        $am = \WHMCS\Module\Addon\CloudonProjects\Pbx3cxBlueprint::syncAgentMode();
        if ($am && $am['changed']) {
            echo '[' . date('H:i:s') . '] AI ρεσεψιόν: κατάσταση ' . $am['mode'] . "\n";
        }
        /* Δίχτυ ασφαλείας: ό,τι ζήτησε ο πελάτης από τη ρεσεψιόν και δεν έγινε
           ticket, γίνεται ticket από εμάς — από το κείμενο της κλήσης. */
        require_once __DIR__ . '/../lib/Book.php';
        require_once __DIR__ . '/../lib/Pbx3cx/AiTickets.php';
        $an = \WHMCS\Module\Addon\CloudonProjects\AiTickets::sweep();
        if ($an['created'] || $an['errors']) {
            echo '[' . date('H:i:s') . '] δίχτυ ρεσεψιόν: ' . $an['created'] . ' tickets'
                . ($an['errors'] ? ' · σφάλματα: ' . implode(' | ', $an['errors']) : '') . "\n";
        }
        /* Κατάλογος ↔ 3CX, διπλή κατεύθυνση (20/09/2026): ό,τι διορθώθηκε μέσα
           στο κέντρο έρχεται εδώ, ό,τι δεν έχει φύγει ακόμη από εδώ φεύγει.
           Route/Blueprint χρειάζονται: χωρίς αυτά η αποστολή θα έσβηνε τη
           σήμανση ουράς «[Support]» από τα ονόματα. */
        require_once __DIR__ . '/../lib/Pbx3cx/Route.php';
        try {
            $bp = \WHMCS\Module\Addon\CloudonProjects\Book::pullFromPbx();
            $bs = \WHMCS\Module\Addon\CloudonProjects\Book::pushPending(50);
            if ($bp['new'] || $bp['linked'] || $bp['updated'] || $bp['gone'] || $bs['sent'] || $bs['failed']) {
                echo '[' . date('H:i:s') . '] κατάλογος ↔ 3CX: ' . $bp['new'] . ' νέες, ' . $bp['linked'] . ' δέθηκαν, '
                    . $bp['updated'] . ' ήρθαν από 3CX, ' . $bp['gone'] . ' έλειπαν εκεί · στάλθηκαν '
                    . $bs['sent'] . ($bs['failed'] ? ', απέτυχαν ' . $bs['failed'] : '') . "\n";
                foreach ($bp['changes'] as $ch) { echo '    ' . $ch . "\n"; }
            }
        } catch (\Throwable $e) {
            echo '[' . date('H:i:s') . '] κατάλογος ↔ 3CX ΣΦΑΛΜΑ: ' . $e->getMessage() . "\n";
        }
    }
} catch (\Throwable $e) {
    echo '[' . date('H:i:s') . '] κλήσεις ΣΦΑΛΜΑ: ' . $e->getMessage() . "\n";
}
