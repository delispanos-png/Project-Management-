<?php
/**
 * CloudOn Project Manager — Υπέρβαση εκτίμησης (overrun watch).
 *
 * Μια εργασία ή ένα έργο έχει εκτίμηση σε ΩΡΕΣ (estimate_minutes / est_hours) και
 * συχνά πλάνο σε ΗΜΕΡΕΣ (έναρξη → deadline). Όταν ο καταγεγραμμένος χρόνος ή οι
 * ημέρες που πέρασαν ξεπεράσουν την εκτίμηση κατά ένα ποσοστό (προεπιλογή 10%),
 * ειδοποιείται ο ΕΠΙΚΕΦΑΛΗΣ της ομάδας του ανθρώπου που τη δουλεύει — όχι για να
 * τιμωρήσει, αλλά για να ρωτήσει «τι γίνεται; χρειάζεσαι βοήθεια;» πριν χαθεί
 * και το deadline.
 *
 * Δύο άξονες, ίδια κλίμακα:
 *   ώρες   : καταγεγραμμένος χρόνος (μαζί με χρονόμετρο που τρέχει) / εκτίμηση
 *   ημέρες : ημέρες από την έναρξη / προγραμματισμένη διάρκεια (deadline − έναρξη)
 *            — η αναγωγή: έργο 3 ημερών με 10% = 0,3 ημέρα → ειδοποίηση την 1η
 *            ημέρα υπέρβασης (ποτέ λιγότερο από μία ημέρα).
 *
 * Τρία σκαλιά, καθένα ΜΙΑ φορά ανά παραλήπτη (unique στο mod_cpm_deadline_alerts):
 *   l1 = το ποσοστό της ρύθμισης (10%) · l2 = +50% · l3 = +100% (διπλάσιο)
 *
 * Παραλήπτες: οι επικεφαλής των ομάδων του ανθρώπου που τη δουλεύει (ποτέ ο ίδιος),
 * συν ο υπεύθυνος του έργου· αν δεν υπάρχει κανείς, οι επικεφαλής των ομάδων που
 * εξυπηρετούν το τμήμα της εργασίας.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Overrun
{
    const KIND_TASK_H = 'ovh_task';
    const KIND_TASK_D = 'ovd_task';
    const KIND_PROJ_H = 'ovh_proj';
    const KIND_PROJ_D = 'ovd_proj';

    /* ───────────────────────── ρυθμίσεις ───────────────────────── */

    private static function setting($k)
    {
        return Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', $k)->value('value');
    }

    /** Ενεργό εκτός αν ο διαχειριστής το έκλεισε ρητά. */
    public static function enabled()
    {
        $v = self::setting('overrun_on');
        return $v === null || $v === 'on';   // '' = ο διακόπτης του UI έκλεισε
    }

    /** Το ποσοστό του πρώτου σκαλιού (προεπιλογή 10, όρια 1–100). */
    public static function pct()
    {
        $v = (float) (self::setting('overrun_pct') ?: 10);
        return max(1, min(100, $v));
    }

    /** Τα σκαλιά με τα ποσοστά τους — το l1 από τη ρύθμιση, τα άλλα δύο σταθερά πάνω του. */
    public static function levels()
    {
        $p = self::pct();
        return ['l1' => $p, 'l2' => max($p + 1, 50), 'l3' => max($p + 2, 100)];
    }

    public static function levelLabel($level)
    {
        $lv = self::levels();
        return ['l1' => '+' . self::fmtPct($lv['l1']), 'l2' => '+' . self::fmtPct($lv['l2']), 'l3' => 'διπλάσιο (+' . self::fmtPct($lv['l3']) . ')'][$level] ?? $level;
    }

    private static function levelFor($pctOver)
    {
        $lv = self::levels();
        if ($pctOver >= $lv['l3']) { return 'l3'; }
        if ($pctOver >= $lv['l2']) { return 'l2'; }
        if ($pctOver >= $lv['l1']) { return 'l1'; }
        return null;
    }

    /* ───────────────────────── μετρήσεις ───────────────────────── */

    /** Λεπτά που τρέχουν ΤΩΡΑ στο(α) χρονόμετρο(α) των εργασιών — η υπέρβαση συμβαίνει ζωντανά. */
    private static function runningMinutes(array $taskIds, $now)
    {
        if (!$taskIds) { return 0; }
        $m = 0;
        foreach (Capsule::table('mod_cpm_timelogs')->whereIn('task_id', $taskIds)->where('running', 1)->get(['started_at']) as $r) {
            $s = strtotime((string) $r->started_at);
            if ($s) { $m += max(0, (int) floor(($now - $s) / 60)); }
        }
        return $m;
    }

    /** Καταγεγραμμένος + τρέχων χρόνος μιας εργασίας. */
    public static function taskSpent($taskId, $now = null)
    {
        $now = $now ?: time();
        return Db::taskMinutes($taskId) + self::runningMinutes([(int) $taskId], $now);
    }

    /** Καταγεγραμμένος + τρέχων χρόνος όλων των εργασιών ενός έργου. */
    public static function projectSpent($projectId, $now = null)
    {
        $now = $now ?: time();
        $ids = Capsule::table('mod_cpm_tasks')->where('project_id', (int) $projectId)->pluck('id')->all();
        if (!$ids) { return 0; }
        $done = (int) Capsule::table('mod_cpm_timelogs')->whereIn('task_id', $ids)->where('running', 0)->sum('minutes');
        return $done + self::runningMinutes(array_map('intval', $ids), $now);
    }

    /**
     * Άξονας ωρών. null αν δεν υπάρχει εκτίμηση ή δεν έχει ξεπεραστεί το 1ο σκαλί.
     * @return array{est:int,spent:int,over:int,pct:float,level:string}|null
     */
    public static function hoursAxis($estMinutes, $spentMinutes)
    {
        $est = (int) $estMinutes; $spent = (int) $spentMinutes;
        if ($est <= 0 || $spent <= $est) { return null; }
        $pct = ($spent - $est) / $est * 100;
        $lv = self::levelFor($pct);
        if ($lv === null) { return null; }
        return ['est' => $est, 'spent' => $spent, 'over' => $spent - $est, 'pct' => round($pct, 1), 'level' => $lv];
    }

    /**
     * Άξονας ημερών. Έναρξη = start_date (αλλιώς η ημέρα δημιουργίας), λήξη = due.
     * Διάρκεια = max(1, λήξη − έναρξη). Υπέρβαση μετρά μόνο ΜΕΤΑ το deadline, και
     * ποτέ κάτω από μία ολόκληρη ημέρα — «10% των 3 ημερών» σημαίνει την επόμενη μέρα.
     * @return array{planned:int,elapsed:int,over:int,pct:float,level:string,due:string}|null
     */
    public static function daysAxis($start, $due, $createdAt, $today = null)
    {
        $today = $today ?: strtotime('today');
        $due = (string) $due;
        if ($due === '' || strpos($due, '0000') === 0) { return null; }
        $s = (string) $start;
        if ($s === '' || strpos($s, '0000') === 0) { $s = substr((string) $createdAt, 0, 10); }
        if ($s === '' || strpos($s, '0000') === 0) { return null; }
        $st = strtotime($s . ' 00:00:00'); $dt = strtotime($due . ' 00:00:00');
        if (!$st || !$dt) { return null; }
        $planned = max(1, (int) round(($dt - $st) / 86400));
        $elapsed = (int) round(($today - $st) / 86400);
        $over = (int) round(($today - $dt) / 86400);
        if ($over < 1) { return null; }
        $pct = $over / $planned * 100;
        $lv = self::levelFor($pct);
        if ($lv === null) { return null; }
        return ['planned' => $planned, 'elapsed' => $elapsed, 'over' => $over, 'pct' => round($pct, 1), 'level' => $lv, 'due' => $due];
    }

    /** Και οι δύο άξονες μιας εργασίας — για την καρτέλα και τον cron. */
    public static function taskStatus($t, $now = null)
    {
        $now = $now ?: time();
        if (!empty($t->completed_at)) { return ['hours' => null, 'days' => null]; }
        $h = self::hoursAxis((int) ($t->estimate_minutes ?? 0), self::taskSpent((int) $t->id, $now));
        $d = self::daysAxis($t->start_date ?? null, $t->due_date ?? null, $t->created_at ?? null, strtotime('today', $now));
        return ['hours' => $h, 'days' => $d];
    }

    public static function projectStatus($p, $now = null)
    {
        $now = $now ?: time();
        if (in_array((string) ($p->status ?? ''), ['archived'], true) || (string) ($p->pstatus ?? '') === 'done') {
            return ['hours' => null, 'days' => null];
        }
        $h = self::hoursAxis((int) round(((float) ($p->est_hours ?? 0)) * 60), self::projectSpent((int) $p->id, $now));
        $d = self::daysAxis($p->start_date ?? null, $p->due_date ?? null, $p->created_at ?? null, strtotime('today', $now));
        return ['hours' => $h, 'days' => $d];
    }

    /* ───────────────────────── ποιος δουλεύει, ποιος ρωτά ───────────────────────── */

    /** Ο άνθρωπος που «κρατά» την εργασία: ανάδοχος, αλλιώς η μπάλα, αλλιώς ο δημιουργός. */
    public static function taskAgent($t)
    {
        foreach (['assignee', 'action_user', 'created_by'] as $f) {
            if (!empty($t->$f)) { return (int) $t->$f; }
        }
        return 0;
    }

    public static function projectAgent($p)
    {
        if (!empty($p->manager_id)) { return (int) $p->manager_id; }
        return (int) (Capsule::table('mod_cpm_project_members')->where('project_id', (int) $p->id)->orderBy('id')->value('admin_id') ?: 0);
    }

    /** Οι επικεφαλής των ομάδων στις οποίες ανήκει κάποιος — ποτέ ο ίδιος. */
    public static function leadersOf($adminId)
    {
        $teams = Capsule::table('mod_cpm_team_members')->where('admin_id', (int) $adminId)->pluck('team_id')->all();
        if (!$teams) { return []; }
        return array_values(array_diff(array_map('intval', Capsule::table('mod_cpm_team_members')
            ->whereIn('team_id', $teams)->where('is_leader', 1)->distinct()->pluck('admin_id')->all()), [(int) $adminId]));
    }

    /** Οι επικεφαλής των ομάδων που εξυπηρετούν ένα τμήμα. */
    private static function deptLeaders($deptId, $except)
    {
        if (!$deptId || !Capsule::schema()->hasTable('mod_cpm_team_depts')) { return []; }
        $ids = Capsule::table('mod_cpm_team_depts as td')->join('mod_cpm_team_members as m', 'm.team_id', '=', 'td.team_id')
            ->where('td.dept_id', (int) $deptId)->where('m.is_leader', 1)->distinct()->pluck('m.admin_id')->all();
        return array_values(array_diff(array_map('intval', $ids), [(int) $except]));
    }

    /**
     * Ποιοι ειδοποιούνται για υπέρβαση: adminId => ρόλος. Πρώτα οι επικεφαλής της
     * ομάδας του ανθρώπου, μετά ο υπεύθυνος έργου· αν δεν βρεθεί κανείς, οι
     * επικεφαλής του τμήματος. Ο ίδιος ο άνθρωπος δεν ειδοποιείται — αυτόν τον
     * ΡΩΤΑΝΕ, δεν του στέλνουμε «ξεπέρασες».
     */
    public static function recipients($agentId, $managerId = 0, $deptId = 0)
    {
        $to = [];
        foreach (self::leadersOf($agentId) as $l) { $to[$l] = 'lead'; }
        if ($managerId && (int) $managerId !== (int) $agentId) { $to[(int) $managerId] = $to[(int) $managerId] ?? 'manager'; }
        /* ΟΤΑΝ ΔΕΝ ΥΠΑΡΧΕΙ ΚΑΝΕΙΣ ΑΠΟ ΠΑΝΩ (23/09/2026).
           Η εφεδρεία ήταν «οι επικεφαλής του department». Επειδή το Support το
           εξυπηρετούν όλες οι ομάδες, αυτό σήμαινε «όλοι οι επικεφαλής»: μια
           υπέρβαση του ίδιου του διαχειριστή ξυπνούσε τέσσερα άτομα. Η κλιμάκωση
           όμως σημαίνει «πες το σε κάποιον από πάνω» — αν δεν υπάρχει κανείς από
           πάνω, πάει στους πλήρεις διαχειριστές, που είναι λίγοι και είναι η
           δουλειά τους. Ποτέ σε ολόκληρο department. */
        if (!$to) {
            foreach (Db::fullAccessAdminIds() as $l) {
                /* Όχι λογαριασμοί συστήματος: κανείς δεν τους διαβάζει. */
                if (preg_match('/\b(bot|test|debug|system|support team|cloud on)\b/i', Db::adminName((int) $l))) { continue; }
                $to[(int) $l] = 'admin';
            }
        }
        unset($to[(int) $agentId], $to[0]);
        return $to;
    }

    /** Μπορεί αυτός να «ρωτήσει τι γίνεται» για τον άνθρωπο; (επικεφαλής του, υπεύθυνος έργου, Full) */
    public static function canAsk($adminId, $isFull, $agentId, $managerId = 0)
    {
        if ($isFull) { return (int) $adminId !== (int) $agentId; }
        if ((int) $adminId === (int) $agentId) { return false; }
        if ($managerId && (int) $managerId === (int) $adminId) { return true; }
        return in_array((int) $adminId, self::leadersOf($agentId), true);
    }

    /* ───────────────────────── cron ───────────────────────── */

    /**
     * Τρέχει κάθε 10΄ από το pulse. Κάθε (είδος, id, σκαλί, παραλήπτης) ΜΙΑ φορά.
     * @return int πόσες ειδοποιήσεις έφυγαν
     */
    public static function run($dry = false, $silent = false)
    {
        if (!self::enabled()) { return 0; }
        $now = time();
        $n = 0;
        $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];

        /* ── εργασίες: μόνο όσες έχουν εκτίμηση ή πλάνο ημερών, και δεν έκλεισαν ── */
        $tasks = Capsule::table('mod_cpm_tasks')->whereNull('completed_at')->whereNotIn('status_id', $doneIds)
            ->where(function ($q) {
                $q->where('estimate_minutes', '>', 0)->orWhere(function ($w) {
                    $w->whereNotNull('due_date')->where('due_date', '!=', '0000-00-00');
                });
            })->get();
        foreach ($tasks as $t) {
            $st = self::taskStatus($t, $now);
            if (!$st['hours'] && !$st['days']) { continue; }
            $agent = self::taskAgent($t);
            if (!$agent) { continue; }
            $proj = $t->project_id ? Capsule::table('mod_cpm_projects')->where('id', (int) $t->project_id)->first(['id', 'name', 'manager_id']) : null;
            $to = self::recipients($agent, $proj ? (int) $proj->manager_id : 0, (int) ($t->dept_id ?? 0));
            if (!$to) { continue; }
            $n += self::dispatch('task', $t, $st, $agent, $to, $proj ? (string) $proj->name : '', $dry, $silent);
        }

        /* ── έργα: εκτίμηση ωρών ή πλάνο ημερών, ανοιχτά ── */
        $projs = Capsule::table('mod_cpm_projects')->where('status', '!=', 'archived')
            ->where(function ($q) { $q->whereNull('pstatus')->orWhere('pstatus', '!=', 'done'); })
            ->where(function ($q) {
                $q->where('est_hours', '>', 0)->orWhere(function ($w) {
                    $w->whereNotNull('due_date')->where('due_date', '!=', '0000-00-00');
                });
            })->get();
        foreach ($projs as $p) {
            $st = self::projectStatus($p, $now);
            if (!$st['hours'] && !$st['days']) { continue; }
            $agent = self::projectAgent($p);
            if (!$agent) { continue; }
            $to = self::recipients($agent, 0, (int) ($p->deptid ?? 0));
            if (!$to) { continue; }
            $n += self::dispatch('project', $p, $st, $agent, $to, '', $dry, $silent);
        }
        return $n;
    }

    /** Στέλνει ό,τι σκαλί δεν έχει ξανασταλεί, σε όποιον δεν το έχει πάρει. */
    /**
     * Πρώτη ενεργοποίηση: ό,τι ΗΔΗ ξεπερνά την εκτίμηση σημειώνεται ως «ειπωμένο» χωρίς
     * να σταλεί τίποτα — αλλιώς την πρώτη μέρα οι επικεφαλής θα έπαιρναν δεκάδες
     * ειδοποιήσεις μαζεμένες. Οι τωρινές υπερβάσεις φαίνονται έτσι κι αλλιώς στη
     * «Μέρα μου»· ειδοποίηση φεύγει μόνο για ό,τι ξεπεραστεί από εδώ και πέρα.
     */
    public static function seed()
    {
        return self::run(false, true);
    }

    private static function dispatch($what, $row, array $st, $agentId, array $to, $where, $dry, $silent = false)
    {
        $n = 0;
        $isTask = $what === 'task';
        $title = mb_substr((string) ($isTask ? $row->title : $row->name), 0, 90);
        $url = $isTask ? '/project/#/task/' . (int) $row->id : '/project/#/board/' . (int) $row->id;
        $agentName = Db::adminName($agentId);
        foreach (['hours', 'days'] as $axis) {
            $ax = $st[$axis];
            if (!$ax) { continue; }
            $kind = $isTask ? ($axis === 'hours' ? self::KIND_TASK_H : self::KIND_TASK_D)
                            : ($axis === 'hours' ? self::KIND_PROJ_H : self::KIND_PROJ_D);
            $line = self::describe($axis, $ax);
            $head = ($isTask ? 'Υπέρβαση εκτίμησης εργασίας: «' : 'Υπέρβαση εκτίμησης έργου: «') . $title . '»';
            $bell = '⚠ ' . $head . ' — ' . $line . ' · ' . $agentName . '. Ρώτα τι γίνεται — χρειάζεται βοήθεια;';
            foreach ($to as $adminId => $role) {
                if (!self::claim($kind, (int) $row->id, $ax['level'], (int) $adminId, $dry)) { continue; }
                if (!$dry && !$silent) {
                    Db::pushNotification((int) $adminId, 'overrun', $bell, $url);
                    self::email((int) $adminId, $head, $line, $agentName, $where, $url, $isTask, $ax['level']);
                }
                $n++;
            }
        }
        return $n;
    }

    /** «2ω 24΄ αντί ~2ω (+20%)» / «5 ημέρες αντί 3 (+67%)» */
    public static function describe($axis, array $ax)
    {
        if ($axis === 'hours') {
            return self::fmtMin($ax['spent']) . ' αντί ~' . self::fmtMin($ax['est']) . ' (+' . self::fmtPct($ax['pct']) . ')';
        }
        return $ax['elapsed'] . ' ημέρες αντί ' . $ax['planned'] . ' (+' . self::fmtPct($ax['pct']) . ' · ' . $ax['over'] . ($ax['over'] === 1 ? ' ημέρα' : ' ημέρες') . ' μετά το deadline)';
    }

    private static function email($adminId, $head, $line, $agentName, $where, $url, $isTask, $level)
    {
        /* Ο καθολικός διακόπτης email του module είναι κλειστός· αυτή η ειδοποίηση
           ζητήθηκε ρητά να φτάνει, οπότε φεύγει απευθείας (σεβόμενη μόνο την
           προσωπική εξαίρεση του χρήστη). */
        if (Db::pref($adminId, 'notify_email', 'on') !== 'on') { return; }
        $to = Notify::adminEmail($adminId);
        if (!$to) { return; }
        $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        $link = Notify::baseUrl() . $url;
        $html = '<p><b>' . $e($head) . '</b></p>'
            . '<p>' . $e($line) . ($where !== '' ? ' · έργο: ' . $e($where) : '') . '</p>'
            . '<p>Τη δουλεύει: <b>' . $e($agentName) . '</b>' . ($level !== 'l1' ? ' — σκαλί ' . $e(self::levelLabel($level)) : '') . '</p>'
            . '<p>Δες τι γίνεται και ρώτησέ τον αν χρειάζεται βοήθεια: ίσως η εκτίμηση ήταν λάθος, ίσως κόλλησε κάπου, ίσως άλλαξε το ζητούμενο. '
            . 'Το κουμπί «Ρώτα τι γίνεται» μέσα στην ' . ($isTask ? 'εργασία' : 'καρτέλα του έργου') . ' του στέλνει την ερώτηση και σου γυρίζει την απάντηση.</p>'
            . '<p><a href="' . $e($link) . '" style="background:#0090dd;color:#fff;padding:9px 18px;border-radius:8px;text-decoration:none;font-weight:700">Άνοιξέ το</a></p>';
        Notify::sendTo($to, '⚠ ' . $head, $html);
    }

    private static function claim($kind, $refId, $level, $adminId, $dry)
    {
        if ($adminId <= 0) { return false; }
        $exists = Capsule::table('mod_cpm_deadline_alerts')->where('kind', $kind)->where('ref_id', $refId)
            ->where('level', $level)->where('admin_id', $adminId)->exists();
        if ($exists) { return false; }
        if ($dry) { return true; }
        try {
            Capsule::table('mod_cpm_deadline_alerts')->insert(['kind' => $kind, 'ref_id' => $refId, 'level' => $level,
                'admin_id' => $adminId, 'sent_on' => date('Y-m-d'), 'created_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            return false;   // duplicate key = κάποιος άλλος cron πρόλαβε
        }
        return true;
    }

    /* ───────────────────────── λίστα για τους επικεφαλής ───────────────────────── */

    /**
     * Οι ΤΩΡΙΝΕΣ υπερβάσεις που αφορούν κάποιον ως επικεφαλή/υπεύθυνο (Full: όλες),
     * με την τελευταία ερώτηση «τι γίνεται» και την απάντησή της.
     */
    public static function openList($adminId, $isFull)
    {
        $now = time();
        $out = [];
        $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
        $tasks = Capsule::table('mod_cpm_tasks')->whereNull('completed_at')->whereNotIn('status_id', $doneIds)
            ->where(function ($q) {
                $q->where('estimate_minutes', '>', 0)->orWhere(function ($w) { $w->whereNotNull('due_date')->where('due_date', '!=', '0000-00-00'); });
            })->get();
        foreach ($tasks as $t) {
            $st = self::taskStatus($t, $now);
            if (!$st['hours'] && !$st['days']) { continue; }
            $agent = self::taskAgent($t);
            $mgr = $t->project_id ? (int) (Capsule::table('mod_cpm_projects')->where('id', (int) $t->project_id)->value('manager_id') ?: 0) : 0;
            if (!self::canAsk($adminId, $isFull, $agent, $mgr)) { continue; }
            $out[] = self::listRow('task', $t, $st, $agent);
        }
        $projs = Capsule::table('mod_cpm_projects')->where('status', '!=', 'archived')
            ->where(function ($q) { $q->whereNull('pstatus')->orWhere('pstatus', '!=', 'done'); })
            ->where(function ($q) {
                $q->where('est_hours', '>', 0)->orWhere(function ($w) { $w->whereNotNull('due_date')->where('due_date', '!=', '0000-00-00'); });
            })->get();
        foreach ($projs as $p) {
            $st = self::projectStatus($p, $now);
            if (!$st['hours'] && !$st['days']) { continue; }
            $agent = self::projectAgent($p);
            if (!self::canAsk($adminId, $isFull, $agent, 0)) { continue; }
            $out[] = self::listRow('project', $p, $st, $agent);
        }
        usort($out, function ($a, $b) { return $b['worst'] <=> $a['worst']; });
        return $out;
    }

    private static function listRow($what, $row, array $st, $agent)
    {
        $isTask = $what === 'task';
        $worst = max($st['hours']['pct'] ?? 0, $st['days']['pct'] ?? 0);
        return ['what' => $what, 'id' => (int) $row->id, 'title' => (string) ($isTask ? $row->title : $row->name),
            'agent' => $agent, 'agentName' => Db::adminName($agent),
            'hours' => $st['hours'], 'days' => $st['days'], 'worst' => round($worst, 1),
            'hoursText' => $st['hours'] ? self::describe('hours', $st['hours']) : null,
            'daysText' => $st['days'] ? self::describe('days', $st['days']) : null,
            'checkin' => self::lastCheckin($what, (int) $row->id)];
    }

    /** Η τελευταία ερώτηση «τι γίνεται» για μια εργασία/έργο, με την απάντηση αν ήρθε. */
    public static function lastCheckin($what, $id)
    {
        $q = Capsule::table('mod_cpm_help')->where('kind', 'checkin');
        if ($what === 'task') { $q->where('task_id', (int) $id); } else { $q->where('project_id', (int) $id); }
        $h = $q->orderBy('id', 'desc')->first();
        if (!$h) { return null; }
        return ['id' => (int) $h->id, 'by' => Db::adminName((int) $h->from_admin), 'byId' => (int) $h->from_admin,
            'to' => Db::adminName((int) $h->to_admin), 'toId' => (int) $h->to_admin, 'at' => $h->created_at,
            'message' => (string) $h->message, 'status' => (string) $h->status,
            'answer' => (string) ($h->answer ?? ''), 'answerNote' => (string) ($h->answer_note ?? ''), 'doneAt' => $h->done_at];
    }

    /* ───────────────────────── μορφοποίηση ───────────────────────── */

    public static function fmtMin($m)
    {
        $m = (int) $m;
        $h = intdiv($m, 60); $r = $m % 60;
        if ($h && $r) { return $h . 'ω ' . $r . '΄'; }
        if ($h) { return $h . 'ω'; }
        return $r . '΄';
    }

    public static function fmtPct($v)
    {
        $v = (float) $v;
        return (abs($v - round($v)) < 0.05 ? (string) (int) round($v) : number_format($v, 1, ',', '')) . '%';
    }
}
