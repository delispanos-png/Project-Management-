<?php
/**
 * CloudOn Project Manager — Επαναλαμβανόμενες εργασίες (30/9/2026).
 *
 * Ένας ΚΑΝΟΝΑΣ (mod_cpm_recurring) γεννά μία εργασία ανά εμφάνιση:
 *   daily   = κάθε ΕΡΓΑΣΙΜΗ (Δευτέρα–Παρασκευή, όχι αργίες)
 *   weekly  = κάθε Ν εβδομάδες, την ημέρα της ημερομηνίας έναρξης
 *   monthly / yearly = όπως πριν
 *
 * ΠΟΙΟΣ ΤΗΝ ΚΑΝΕΙ — `people` (',2,21,'):
 *   ένας  → η εργασία πάει κατευθείαν σε αυτόν (ανάδοχος).
 *   πολλοί → η εργασία γεννιέται ΧΩΡΙΣ ανάδοχο και ειδοποιούνται ΟΛΟΙ μαζί.
 *            Ο πρώτος που την αναλαμβάνει (claim) τη παίρνει· για τους άλλους κλείνει.
 *   Όποιος είναι σε άδεια εκείνη τη μέρα παραλείπεται.
 *
 * ΜΟΝΟ Η ΣΗΜΕΡΙΝΗ ΜΕΤΡΑΕΙ: όταν γεννιέται η νέα εμφάνιση, όποια παλιά του ίδιου
 * κανόνα έμεινε ανοιχτή κλείνει ως «Ακυρωμένο — δεν έγινε» (δεν μετρά στις
 * ολοκληρώσεις, μένει όμως στο ιστορικό του κανόνα ως «δεν έγινε»).
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use Illuminate\Database\Capsule\Manager as Capsule;

class Recurring
{
    /** Οι άνθρωποι του κανόνα (admin ids). */
    public static function people($r)
    {
        return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($r->people ?? ''))))));
    }

    /** Εργάσιμη = Δευτέρα–Παρασκευή και όχι αργία του ελληνικού εορτολογίου. */
    public static function isWorkday($date)
    {
        $ts = strtotime($date);
        if ((int) date('N', $ts) >= 6) {
            return false;
        }
        try {
            if (!class_exists(__NAMESPACE__ . '\\Pbx3cxBlueprint')) {
                require_once __DIR__ . '/Pbx3cx/Blueprint.php';
            }
            return !Pbx3cxBlueprint::isHoliday($ts);
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function nextWorkday($date)
    {
        $d = date('Y-m-d', strtotime($date . ' +1 day'));
        for ($i = 0; $i < 20 && !self::isWorkday($d); $i++) {
            $d = date('Y-m-d', strtotime($d . ' +1 day'));
        }
        return $d;
    }

    /** Είναι σε άδεια ο άνθρωπος εκείνη τη μέρα; */
    public static function onLeave($adminId, $date)
    {
        try {
            $staff = Capsule::table('mod_cpm_leave_staff')->where('admin_id', (int) $adminId)->pluck('id')->all();
            if (!$staff) {
                return false;
            }
            return Capsule::table('mod_cpm_leaves')->whereIn('staff_id', $staff)
                ->where('date_from', '<=', $date)->where('date_to', '>=', $date)
                ->whereNotIn('status', ['rejected', 'cancelled', 'canceled', 'declined'])->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Επόμενη εμφάνιση μετά από $date. */
    public static function next($r, $date)
    {
        if ($r->freq === 'daily') {
            $d = $date;
            for ($i = 0; $i < max(1, (int) $r->every); $i++) {
                $d = self::nextWorkday($d);
            }
            return $d;
        }
        return Db::nextRun($date, $r->freq, $r->every);
    }

    /**
     * Τρέχει όσους κανόνες οφείλουν σήμερα. Επαναλήψιμο: αν η σημερινή εμφάνιση
     * υπάρχει ήδη, δεν ξαναγεννιέται. $onlyId = μόνο ένας κανόνας (αμέσως μετά την αποθήκευση).
     * Επιστρέφει [ruleId => taskId|null].
     */
    public static function run($today, $onlyId = null, $log = null, $dry = false)
    {
        $log = $log ?: function ($m) { };
        $out = [];
        $q = Capsule::table('mod_cpm_recurring')->where('active', 1)->where('next_run', '<=', $today);
        if ($onlyId) {
            $q->where('id', (int) $onlyId);
        }
        foreach ($q->get() as $r) {
            try {
                $out[(int) $r->id] = self::runOne($r, $today, $log, $dry);
            } catch (\Throwable $e) {
                $log('  ✗ #' . $r->id . ' «' . $r->title . '»: ' . $e->getMessage());
            }
        }
        return $out;
    }

    private static function runOne($r, $today, $log, $dry)
    {
        /* Έργο (αν δηλώθηκε) πρέπει να είναι ενεργό — αρχειοθετημένο = παύση χωρίς να χαθεί η σειρά. */
        if (!empty($r->project_id)) {
            $p = Db::project($r->project_id);
            if (!$p || $p->status !== 'active') {
                $log("  skip #{$r->id} «{$r->title}» — έργο ανενεργό");
                return null;
            }
        }
        $advance = function () use ($r, $today, $dry) {
            $next = self::next($r, $r->next_run);
            for ($i = 0; $next <= $today && $i < 800; $i++) {
                $next = self::next($r, $next);
            }
            if (!$dry) {
                Capsule::table('mod_cpm_recurring')->where('id', $r->id)->update(['next_run' => $next, 'last_run' => $today]);
            }
            return $next;
        };
        /* «Καθημερινή» = μόνο εργάσιμες: Σαββατοκύριακο/αργία περνά χωρίς εργασία. */
        if ($r->freq === 'daily' && !self::isWorkday($today)) {
            $n = $advance();
            $log("  skip #{$r->id} «{$r->title}» — μη εργάσιμη, επόμενη {$n}");
            return null;
        }
        /* Επαναλήψιμο: η σημερινή υπάρχει ήδη. */
        $exists = (int) Capsule::table('mod_cpm_tasks')->where('rec_id', $r->id)->where('rec_date', $today)->value('id');
        if ($exists) {
            $advance();
            return $exists;
        }
        $people = array_values(array_filter(self::people($r), function ($a) use ($today) {
            return Capsule::table('tbladmins')->where('id', $a)->where('disabled', 0)->exists() && !self::onLeave($a, $today);
        }));
        if ($dry) {
            $log("  (dry) «{$r->title}» → " . (count($people) ? implode(',', $people) : 'κανείς διαθέσιμος'));
            return null;
        }

        /* ΜΟΝΟ Η ΣΗΜΕΡΙΝΗ ΜΕΤΡΑΕΙ: ό,τι έμεινε ανοιχτό από προηγούμενη εμφάνιση κλείνει ως «δεν έγινε». */
        $closed = Db::closedStatusIds();
        $cancelId = (int) (Capsule::table('mod_cpm_statuses')->where('phase', 'cancel')->orderBy('sort')->value('id'));
        foreach (Capsule::table('mod_cpm_tasks')->where('rec_id', $r->id)->where('rec_date', '<', $today)
            ->whereNotIn('status_id', $closed)->get(['id']) as $old) {
            foreach (Capsule::table('mod_cpm_timelogs')->where('task_id', $old->id)->where('running', 1)->get(['id']) as $rl) {
                Db::stopTimer((int) $rl->id);
                Db::updateTimelog((int) $rl->id, ['note' => 'έκλεισε: νέα εμφάνιση επαναλαμβανόμενης']);
            }
            if ($cancelId) {
                Capsule::table('mod_cpm_tasks')->where('id', $old->id)->update(['status_id' => $cancelId,
                    'rec_missed' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            }
            Db::logActivity((int) $old->id, null, 'auto', 'Δεν έγινε — αντικαταστάθηκε από τη σημερινή εμφάνιση');
        }

        if (!$people) {
            $n = $advance();
            $log("  skip #{$r->id} «{$r->title}» — κανείς διαθέσιμος (άδεια/ανενεργοί), επόμενη {$n}");
            return null;
        }
        $solo = count($people) === 1;
        $st = $r->start_time ?: null;
        $taskId = Db::saveTask(0, [
            'project_id' => !empty($r->project_id) ? (int) $r->project_id : null,
            'dept_id'    => !empty($r->dept_id) ? (int) $r->dept_id : null,
            'title'      => $r->title,
            'descr'      => (string) $r->descr,
            'status_id'  => Db::firstStatusId(),
            'priority'   => (int) $r->priority,
            'assignee'   => $solo ? $people[0] : null,
            'start_date' => $today,
            'start_time' => $st,
            'due_date'   => $today,
            'due_time'   => $r->end_time ?: '18:00',
            'rec_id'     => (int) $r->id,
            'rec_date'   => $today,
        ], !empty($r->created_by) ? (int) $r->created_by : null);
        /* Τα βήματα του κανόνα γίνονται οι ενέργειες (checklist) της εργασίας. */
        $i = 0;
        foreach (preg_split('/\r?\n/', (string) ($r->steps ?? '')) as $line) {
            $line = trim($line);
            if ($line === '') { continue; }
            Capsule::table('mod_cpm_checklist')->insert(['task_id' => $taskId, 'title' => mb_substr($line, 0, 250),
                'done' => 0, 'sort' => ++$i, 'created_by' => !empty($r->created_by) ? (int) $r->created_by : null,
                'created_at' => date('Y-m-d H:i:s')]);
        }
        Db::logActivity($taskId, null, 'auto', 'Επαναλαμβανόμενη #' . $r->id . ($solo ? '' : ' — ανοιχτή για ' . count($people) . ' άτομα'));
        $url = '/project/#/task/' . $taskId;
        $when = $st ? ' · ' . substr($st, 0, 5) : '';
        foreach ($people as $a) {
            Db::pushNotification($a, 'recurring', ($solo ? '🔁 Σήμερα: ' : '🔁 Ποιος την αναλαμβάνει; ')
                . mb_substr((string) $r->title, 0, 90) . $when, $url);
        }
        $n = $advance();
        $log("  ✓ task #{$taskId} «{$r->title}» → " . ($solo ? 'ανάδοχος ' . $people[0] : count($people) . ' άτομα (ανάληψη)') . ", επόμενη {$n}");
        return $taskId;
    }

    /** Ανοιχτές ομαδικές εμφανίσεις που μπορεί να αναλάβει ο $adminId (σήμερα). */
    public static function claimable($adminId)
    {
        $closed = Db::closedStatusIds();
        return Capsule::table('mod_cpm_tasks as t')
            ->join('mod_cpm_recurring as r', 'r.id', '=', 't.rec_id')
            ->where('t.rec_date', date('Y-m-d'))
            ->where(function ($w) { $w->whereNull('t.assignee')->orWhere('t.assignee', 0); })
            ->where(function ($w) { $w->whereNull('t.action_user')->orWhere('t.action_user', 0); })
            ->whereNotIn('t.status_id', $closed)
            ->where('r.people', 'like', '%,' . (int) $adminId . ',%')
            ->get(['t.id', 't.title', 't.start_time', 't.due_time', 'r.people']);
    }

    /** Είναι ανοιχτή για ανάληψη από αυτόν; */
    public static function canClaim($task, $adminId)
    {
        if (!$task || empty($task->rec_id) || !empty($task->assignee) || !empty($task->action_user)) {
            return false;
        }
        $people = (string) Capsule::table('mod_cpm_recurring')->where('id', (int) $task->rec_id)->value('people');
        return strpos($people, ',' . (int) $adminId . ',') !== false;
    }

    /** Ανάληψη — ατομικά: κερδίζει ο πρώτος, οι υπόλοιποι παύουν να την έχουν στις ειδοποιήσεις. */
    public static function claim($taskId, $adminId)
    {
        $n = Capsule::table('mod_cpm_tasks')->where('id', (int) $taskId)
            ->where(function ($w) { $w->whereNull('assignee')->orWhere('assignee', 0); })
            ->update(['assignee' => (int) $adminId, 'updated_at' => date('Y-m-d H:i:s')]);
        if (!$n) {
            return false;
        }
        Db::logActivity((int) $taskId, (int) $adminId, 'assign', 'Την ανέλαβε ' . Db::adminName((int) $adminId));
        Capsule::table('mod_cpm_notifications')->where('url', '/project/#/task/' . (int) $taskId)
            ->where('admin_id', '<>', (int) $adminId)->where('type', 'recurring')->delete();
        return true;
    }
}
