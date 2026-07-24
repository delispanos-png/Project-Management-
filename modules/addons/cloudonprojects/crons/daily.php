<?php
/**
 * CloudOn Project Manager — ημερήσιο cron (Φ3.3 / Φ3.4).
 *   1) Δημιουργεί tasks από ενεργά recurring με next_run <= σήμερα και προχωρά το next_run.
 *   2) Στέλνει το ημερήσιο digest εκπρόθεσμων/σημερινών ανά χειριστή.
 *
 * Τρέξιμο: /opt/plesk/php/8.3/bin/php daily.php   (flock-guarded από το cron.d)
 * CPM_DRY=1 = dry-run (καμία εγγραφή/email).
 */

define('WHMCS', true);
require __DIR__ . '/../../../../init.php';

use WHMCS\Module\Addon\CloudonProjects\Db;
use WHMCS\Module\Addon\CloudonProjects\Notify;

require_once __DIR__ . '/../lib/Db.php';
require_once __DIR__ . '/../lib/Notify.php';

$dry = getenv('CPM_DRY') === '1';
$today = date('Y-m-d');
$log = function ($m) {
    echo '[' . date('H:i:s') . '] ' . $m . "\n";
};

$log(($dry ? '(DRY) ' : '') . 'CPM daily cron — ' . $today);

/* ---- 1. Recurring tasks ---- */
$due = Db::dueRecurring($today);
$log('Recurring προς εκτέλεση: ' . count($due));
foreach ($due as $r) {
    // guard: αν το project αρχειοθετήθηκε, παράλειψη (χωρίς advance ώστε να φανεί αν επανέλθει)
    $p = Db::project($r->project_id);
    if (!$p || $p->status !== 'active') {
        $log("  skip #{$r->id} «{$r->title}» — project ανενεργό");
        continue;
    }
    $dueDate = $r->due_days > 0 ? date('Y-m-d', strtotime($today . ' +' . (int) $r->due_days . ' days')) : null;
    $next = Db::nextRun($r->next_run, $r->freq, $r->every);
    // αν το next_run έχει μείνει πολύ πίσω (π.χ. cron down), προχώρα μέχρι το μέλλον χωρίς να σωρεύσεις tasks
    while ($next <= $today) {
        $next = Db::nextRun($next, $r->freq, $r->every);
    }
    if ($dry) {
        $log("  (dry) θα δημιουργούσε «{$r->title}» στο {$p->name}, due=" . ($dueDate ?: '—') . ", next={$next}");
        continue;
    }
    $taskId = Db::saveTask(0, [
        'project_id' => (int) $r->project_id,
        'title'      => $r->title,
        'descr'      => (string) $r->descr,
        'status_id'  => Db::firstStatusId(),
        'priority'   => (int) $r->priority,
        'assignee'   => $r->assignee ? (int) $r->assignee : null,
        'due_date'   => $dueDate,
    ], null);
    Db::logActivity($taskId, null, 'auto', 'Δημιουργήθηκε από επαναλαμβανόμενο πρόγραμμα #' . $r->id);
    Db::saveRecurring($r->id, ['next_run' => $next, 'last_run' => $today]);
    Notify::recurringCreated($taskId, $r->assignee);
    $log("  ✓ task #$taskId «{$r->title}» ({$p->name}), next: {$next}");
    if (function_exists('logActivity')) {
        logActivity('CPM: recurring #' . $r->id . ' δημιούργησε task #' . $taskId . ' («' . $r->title . '»)');
    }
}

/* ---- 1b. Snapshot προόδου projects (τάση στο portfolio) ---- */
if (!$dry) {
    $log('Snapshots: ' . Db::snapshotAll() . ' projects');
}

/* ---- 2. Daily digest ---- */
if ($dry) {
    $log('(dry) παράλειψη digest');
} else {
    $sent = Notify::dailyDigest();
    $log('Digest emails: ' . $sent);
}

$log('Τέλος.');

/* 🌅 Πρωινό ατομικό πλάνο: top-3 tickets ανά χειριστή (καμπανάκι + email) */
try {
    $now = time();
    $slaBy = [];
    if (Capsule::schema()->hasTable('mod_supportcontracts_tickets')) {
        foreach (Capsule::table('mod_supportcontracts_tickets')->whereNotNull('sla_due')->get() as $st) {
            $slaBy[(int) $st->ticketid] = $st;
        }
    }
    $byAgent = [];
    $open = Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])
        ->where('flag', '>', 0)->get(['id', 'tid', 'title', 'urgency', 'lastreply', 'flag']);
    $ids = array_map(function ($r) { return (int) $r->id; }, $open->all());
    $lastAdmin = [];
    if ($ids) {
        foreach (Capsule::table('tblticketreplies')->whereIn('tid', $ids)->orderBy('id')->get(['tid', 'admin']) as $r) {
            $lastAdmin[(int) $r->tid] = trim((string) $r->admin) !== '';
        }
    }
    foreach ($open as $t) {
        $u = ['High' => 30, 'Medium' => 15, 'Low' => 5][$t->urgency] ?? 10;
        $w = !($lastAdmin[(int) $t->id] ?? false) ? min(30, max(0, ($now - strtotime($t->lastreply)) / 3600) * 1.5) : 0;
        $sv = 0;
        if (isset($slaBy[(int) $t->id]) && !$slaBy[(int) $t->id]->first_response_at) {
            $left = (strtotime($slaBy[(int) $t->id]->sla_due) - $now) / 3600;
            $sv = $left < 0 ? 40 : ($left < 2 ? 30 : ($left < 8 ? 15 : 5));
        }
        $byAgent[(int) $t->flag][] = ['t' => $t, 's' => round($u + $w + $sv, 1)];
    }
    $sentPlan = 0;
    foreach ($byAgent as $aid => $items) {
        if ($dry || Db::pref($aid, 'digest', 'on') !== 'on') {
            if ($dry) { echo "DRY: πλάνο για admin #$aid (" . count($items) . " tickets)\n"; }
            continue;
        }
        usort($items, function ($a, $b) { return $b['s'] <=> $a['s']; });
        $top = array_slice($items, 0, 3);
        $txt = implode(' · ', array_map(function ($x) { return '#' . $x['t']->tid; }, $top));
        Db::pushNotification($aid, 'due', '🌅 Το πλάνο σου: ' . $txt, '/project/#/inbox');
        $h = '<p>Καλημέρα! Τα πιο σημαντικά σου tickets για σήμερα:</p><ol>';
        foreach ($top as $x) {
            $h .= '<li><b>#' . htmlspecialchars($x['t']->tid) . '</b> — ' . htmlspecialchars($x['t']->title)
                . ' <small>(σκορ ' . $x['s'] . ')</small></li>';
        }
        $h .= '</ol><p>Άνοιξέ τα στο <a href="https://my.cloudon.gr/project/">CloudOn Projects</a>.</p>';
        Notify::send($aid, '🌅 Το πλάνο της ημέρας σου', $h);
        $sentPlan++;
    }
    if ($sentPlan) {
        logActivity("CPM daily: πρωινό πλάνο ημέρας σε $sentPlan χειριστές");
    }
} catch (\Throwable $e) {
    logActivity('CPM daily plan error: ' . $e->getMessage());
}
