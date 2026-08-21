<?php
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

require_once __DIR__ . '/../lib/Db.php';
require_once __DIR__ . '/../lib/Notify.php';
require_once __DIR__ . '/../lib/Auto.php';
require_once __DIR__ . '/../lib/Deadlines.php';
require_once __DIR__ . '/../lib/TicketIdle.php';

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
