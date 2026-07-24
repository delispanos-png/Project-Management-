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

require_once __DIR__ . '/../lib/Db.php';
require_once __DIR__ . '/../lib/Notify.php';
require_once __DIR__ . '/../lib/Auto.php';

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
