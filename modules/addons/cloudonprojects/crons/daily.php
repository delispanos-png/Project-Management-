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
/* ΧΩΡΙΣ αυτό το import, κάθε μπλοκ που αγγίζει απευθείας τη βάση σκάει με
   «Class Capsule not found» — και επειδή είναι τυλιγμένα σε try/catch, έσκαγε
   ΣΙΩΠΗΛΑ: το πρωινό πλάνο δεν στάλθηκε ποτέ από 23/07/2026 ώς 16/09/2026. */
use Illuminate\Database\Capsule\Manager as Capsule;

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

/* ---- Η ραχοκοκαλιά πελάτης → προϊόν → τμήμα μένει ζωντανή μόνη της ----
   Νέα υπηρεσία στο WHMCS = νέο προϊόν στην καρτέλα· νέο ticket με δηλωμένη
   υπηρεσία = ταξινομημένο ticket· έργο σε προϊόν που λείπει = προϊόν στην καρτέλα. */
try {
    require_once __DIR__ . '/../lib/Catalog.php';
    if (!$dry) {
        $a = \WHMCS\Module\Addon\CloudonProjects\Catalog::syncClientProducts();
        $b = \WHMCS\Module\Addon\CloudonProjects\Catalog::backfillTickets(false);
        $c = \WHMCS\Module\Addon\CloudonProjects\Catalog::reconcile(false);
        $log("Κατάλογος: +$a προϊόντα πελατών · +$b tickets με προϊόν · +$c συμφιλιώσεις");
        if ($a + $b + $c) { logActivity("CPM daily: κατάλογος προϊόντων +$a/+$b/+$c"); }
    } else {
        $log('(DRY) κατάλογος προϊόντων — παραλείπεται');
    }
} catch (\Throwable $e) {
    logActivity('CPM daily catalog error: ' . $e->getMessage());
}

/* ---- 4. Επικεφαλής ομάδων: προειδοποίηση συσσώρευσης ----------------------
   Το πρόβλημα δεν είναι ότι μια εργασία αργεί, αλλά ότι η ομάδα κουβαλάει
   κάθε μέρα περισσότερα απ' όσα βάζει. Χωρίς αυτό, η συσσώρευση φαίνεται μόνο
   αν κάποιος ανοίξει την οθόνη — δηλαδή ακριβώς όταν δεν προλαβαίνει. */
try {
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $leaders = Capsule::table('mod_cpm_team_members as m')
        ->join('mod_cpm_teams as t', 't.id', '=', 'm.team_id')
        ->where('m.is_leader', 1)->get(['m.admin_id', 'm.team_id', 't.name']);
    $log('Επικεφαλής ομάδων: ' . count($leaders));

    foreach ($leaders as $L) {
        $members = Capsule::table('mod_cpm_team_members')->where('team_id', $L->team_id)
            ->pluck('admin_id')->all();
        if (!$members) { continue; }

        $base = Capsule::table('mod_cpm_tasks')->whereIn('assignee', $members)
            ->whereNotIn('status_id', $doneIds);
        $planned = (int) (clone $base)->where(function ($w) use ($today) {
            $w->where('schedule_date', $today)->orWhere('due_date', $today);
        })->count();
        $carried = (int) (clone $base)->whereNotNull('schedule_date')
            ->where('schedule_date', '<', $today)->count();
        $late = (int) (clone $base)->whereNotNull('due_date')->where('due_date', '<', $today)->count();

        /* Κατώφλι: σιωπή όταν η μέρα είναι φυσιολογική. Χτυπάει μόνο όταν η
           μεταφορά είναι ΚΑΙ αισθητή (>=4) ΚΑΙ δυσανάλογη (διπλάσια του πλάνου). */
        if ($carried < 4 || $carried <= $planned * 2) {
            $log("  {$L->name}: μεταφορά $carried / πλάνο $planned — εντός ορίων");
            continue;
        }
        $msg = 'Η ομάδα «' . $L->name . '» έχει ' . $carried . ' εργασίες με περασμένο deadline'
            . ($planned ? ' ενώ έβαλε ' . $planned . ' για σήμερα' : ' και δεν έβαλε καμία για σήμερα')
            . ($late ? ' · ' . $late . ' έχουν ξεπεράσει προθεσμία' : '');
        $log("  {$L->name}: ΕΙΔΟΠΟΙΗΣΗ — $msg");
        if ($dry) { continue; }

        Db::pushNotification((int) $L->admin_id, 'pileup', mb_substr($msg, 0, 240), '/project/#/myteam');
        try {
            $to = Notify::adminEmail((int) $L->admin_id);
            if ($to) {
                Notify::sendTo($to, 'Συσσώρευση στην ομάδα «' . $L->name . '»',
                    '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<p>Δεν είναι κάθε καθυστέρηση πρόβλημα — αλλά όταν η μεταφορά είναι '
                    . 'διπλάσια από το πλάνο, η μέρα σχεδιάζεται με βάση κάτι που δεν συμβαίνει.</p>'
                    . '<p><a href="' . htmlspecialchars(Notify::baseUrl() . '/project/#/myteam', ENT_QUOTES, 'UTF-8')
                    . '" style="background:#0090dd;color:#fff;padding:9px 16px;border-radius:8px;text-decoration:none;display:inline-block">Η ομάδα μου</a></p>');
            }
        } catch (\Throwable $e) { /* το email δεν σταματά το cron */ }
    }
} catch (\Throwable $e) {
    $log('Σφάλμα στη συσσώρευση ομάδων: ' . $e->getMessage());
    logActivity('CPM daily pileup error: ' . $e->getMessage());
}

/* ---- 5. Εργασίες χωρίς χρόνο υλοποίησης γυρίζουν σε όποιον τις άνοιξε -------
   Κανόνας: δουλειά χωρίς έναρξη+λήξη δεν κάθεται σε άλλον. Ο δημιουργός την
   κρατά πρόχειρη όσο θέλει· για να την ξαναδώσει, βάζει ημερομηνίες. Ο έλεγχος
   στην αποθήκευση καλύπτει τη συνήθη διαδρομή — αυτό εδώ πιάνει ό,τι μπήκε από
   αλλού (import, cron, παλιά δεδομένα). */
try {
    $doneIds5 = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $stray = Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $doneIds5)
        ->whereNotNull('assignee')->where('assignee', '<>', 0)
        ->whereNotNull('created_by')->where('created_by', '<>', 0)
        ->whereColumn('assignee', '<>', 'created_by')
        ->where(function ($w) { $w->whereNull('start_date')->orWhereNull('due_date'); })
        ->get(['id', 'title', 'assignee', 'created_by']);
    $log('Εργασίες χωρίς χρόνο σε τρίτον: ' . count($stray));

    $backTo = [];
    foreach ($stray as $t) {
        $log(sprintf('  #%d «%s» %s → %s', $t->id, mb_substr($t->title, 0, 40),
            Db::adminName($t->assignee), Db::adminName($t->created_by)));
        if ($dry) { continue; }
        Capsule::table('mod_cpm_tasks')->where('id', $t->id)
            ->update(['assignee' => (int) $t->created_by]);
        /* Καταγράφουμε ΠΟΙΟΝ είχε, ώστε η κίνηση να μπορεί να αναιρεθεί. */
        Db::logActivity((int) $t->id, 0, 'assign',
            'Επέστρεψε στον δημιουργό (' . Db::adminName($t->created_by) . '): χωρίς έναρξη/λήξη '
            . 'δεν ανατίθεται σε τρίτον. Είχε ανατεθεί στον/στην ' . Db::adminName($t->assignee) . '.');
        $backTo[(int) $t->created_by][] = '#' . (int) $t->id . ' ' . mb_substr($t->title, 0, 40);
    }
    /* Μία σύνοψη ανά άτομο — όχι ένα μήνυμα ανά εργασία. */
    foreach ($backTo as $aid => $list) {
        $msg = count($list) . ' εργασίες επέστρεψαν σε εσένα: δεν έχουν έναρξη/λήξη, '
            . 'οπότε δεν μπορούν να μείνουν σε άλλον. Βάλε ημερομηνίες για να τις ξαναδώσεις.';
        Db::pushNotification($aid, 'action', mb_substr($msg, 0, 240), '/project/#/myday');
        try {
            $to5 = Notify::adminEmail($aid);
            if ($to5) {
                Notify::sendTo($to5, count($list) . ' εργασίες επέστρεψαν σε εσένα',
                    '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p><ul><li>'
                    . implode('</li><li>', array_map(function ($x) {
                        return htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
                    }, $list)) . '</li></ul>');
            }
        } catch (\Throwable $e) { /* το email δεν σταματά το cron */ }
    }
} catch (\Throwable $e) {
    $log('Σφάλμα στην επιστροφή εργασιών: ' . $e->getMessage());
    logActivity('CPM daily stray-assign error: ' . $e->getMessage());
}

/* ── 6) Χρονόμετρα αποσυνδεδεμένων ─────────────────────────────────────────
   Ο σφυγμός της εφαρμογής τα κλείνει μέσα στη μέρα, αλλά αν φύγουν όλοι με ένα
   ανοιχτό, κανείς δεν χτυπάει τον server μέχρι το πρωί. Εδώ κλείνει ό,τι έμεινε.
   Η ώρα λήξης είναι πάντα ο τελευταίος παλμός, οπότε η καθυστέρηση δεν αλλοιώνει
   τίποτα — απλώς δεν το αφήνουμε να τρέχει όλη νύχτα. */
try {
    $ghosts = Db::closeGhostTimers();
    if ($ghosts && function_exists('logActivity')) {
        logActivity('CPM daily: έκλεισαν ' . $ghosts . ' χρονόμετρα αποσυνδεδεμένων χειριστών');
    }
} catch (\Throwable $e) {
    if (function_exists('logActivity')) { logActivity('CPM daily ghost timers: ' . $e->getMessage()); }
}

/* ── 7) Συγχρονισμός δομής 3CX ─────────────────────────────────────────────
   Νέο extension, αλλαγή ονόματος, νέα ουρά: να τα ξέρει το CloudOn χωρίς να
   το θυμηθεί κανείς. Η χειροκίνητη αντιστοίχιση δεν πειράζεται ποτέ. */
try {
    require_once __DIR__ . '/../lib/Pbx3cx/Client.php';
    require_once __DIR__ . '/../lib/Pbx3cx/Sync.php';
    if (\WHMCS\Module\Addon\CloudonProjects\Pbx3cxClient::configured()) {
        $sy = \WHMCS\Module\Addon\CloudonProjects\Pbx3cxSync::run();
        /* Ο κατάλογος φτάνει στο 3CX στο παρασκήνιο (ό,τι άλλαξε ή δεν πρόλαβε):
           από εκεί η AI ρεσεψιόν αναγνωρίζει τον καλούντα. Αθόρυβο, ποτέ δεν ρίχνει το cron. */
        require_once __DIR__ . '/../lib/Pbx3cx/Cdr.php';
        require_once __DIR__ . '/../lib/Book.php';
        try {
            $bk = \WHMCS\Module\Addon\CloudonProjects\Book::pushPending(300);
            if (function_exists('logActivity') && ($bk['sent'] || $bk['failed'])) {
                logActivity('CPM daily: κατάλογος → 3CX — στάλθηκαν ' . $bk['sent'] . ($bk['failed'] ? ', απέτυχαν ' . $bk['failed'] : ''));
            }
        } catch (\Throwable $e) { echo "  κατάλογος → 3CX: " . $e->getMessage() . "\n"; }
        \WHMCS\Module\Addon\CloudonProjects\Book::refreshLastCall();
        if (function_exists('logActivity')) {
            logActivity('CPM daily: 3CX δομή — νέα ' . $sy['new'] . ', ενημ. ' . $sy['updated']
                . ', αντιστοιχισμένα ' . $sy['matched']
                . ($sy['errors'] ? ' · ΣΦΑΛΜΑΤΑ: ' . implode(' | ', $sy['errors']) : ''));
        }
    }
} catch (\Throwable $e) {
    if (function_exists('logActivity')) { logActivity('CPM daily 3CX sync: ' . $e->getMessage()); }
}
