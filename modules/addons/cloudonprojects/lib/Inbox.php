<?php
/**
 * CloudOn Project Manager — Εισερχόμενα: «τι ήρθε σε μένα, από ποιον, πότε».
 *
 * ΜΙΑ ΓΡΑΜΜΗ ΑΝΑ (ΑΝΘΡΩΠΟ, ΕΡΓΑΣΙΑ). Δεν είναι ημερολόγιο γεγονότων — αυτό είναι
 * το mod_cpm_activity. Εδώ κρατάμε μόνο το ΤΕΛΕΥΤΑΙΟ που έφερε την εργασία μπροστά
 * σου (μπάλα, μήνυμα, κατάσταση…), ποιος το έκανε, πότε, και αν το είδες.
 *
 * ΠΑΡΑΛΗΠΤΗΣ = Ο ΚΑΤΟΧΟΣ ΤΗΣ ΜΠΑΛΑΣ (action_user, αλλιώς ο ανάδοχος) τη στιγμή του
 * γεγονότος — ποτέ «και οι δύο», ποτέ «τα μέλη». Ό,τι κάνει κανείς στη ΔΙΚΗ του
 * εργασία δεν του έρχεται ως νέο.
 *
 * Η ΟΘΟΝΗ ΚΡΙΝΕΙ ΞΑΝΑ ΤΟΝ ΚΑΤΟΧΟ (cnp_scope_mine) τη στιγμή που ανοίγει: αν η μπάλα
 * έφυγε από σένα, η γραμμή μένει στη βάση αλλά δεν φαίνεται. Έτσι δεν χρειάζεται να
 * «καθαρίζει» κάθε σημείο του κώδικα που αλλάζει μπάλα.
 *
 * ΣΥΓΧΩΝΕΥΣΗ: μια απάντηση = μήνυμα + μπάλα + κατάσταση, τρία γεγονότα στο ίδιο
 * δευτερόλεπτο. Ό,τι έρχεται από τον ίδιο άνθρωπο μέσα σε MERGE_SEC και δεν έχει
 * διαβαστεί ακόμη, ενώνεται σε μία γραμμή — αλλιώς το τελευταίο θα έσβηνε το πρώτο.
 *
 * Τίποτα εδώ δεν σπάει την πράξη που το κάλεσε: κάθε σφάλμα καταπίνεται.
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Inbox
{
    const T = 'mod_cpm_inbox';
    const MERGE_SEC = 20;

    /** Βάρος για τη συγχώνευση: κερδίζει το πιο «δυνατό» είδος. */
    const RANK = ['ball' => 6, 'new' => 5, 'msg' => 4, 'status' => 3, 'assign' => 2, 'collab' => 1, 'edit' => 1];

    public static function install()
    {
        $s = Capsule::schema();
        if (!$s->hasTable(self::T)) {
            $s->create(self::T, function ($t) {
                $t->engine = 'InnoDB';   // μέσα στη συναλλαγή της απάντησης: rollback = καμία ψεύτικη γραμμή
                $t->increments('id');
                $t->integer('admin_id')->unsigned();               // παραλήπτης (ο κάτοχος)
                $t->integer('task_id')->unsigned()->index();
                $t->string('kind', 12)->default('edit');           // ball|new|msg|status|assign|collab|edit
                $t->string('what', 255)->nullable();               // «Κατάσταση: Α → Β · Λήξη»
                $t->string('excerpt', 255)->nullable();            // απόσπασμα μηνύματος
                $t->integer('by_admin')->unsigned()->nullable();   // ποιος το έκανε (null = σύστημα)
                $t->dateTime('at');                                // πότε ήρθε σε μένα
                $t->dateTime('read_at')->nullable();               // πότε το άνοιξα (null = αδιάβαστο)
                $t->unique(['admin_id', 'task_id']);
                $t->index(['admin_id', 'read_at']);
            });
            self::backfill();
        }
    }

    /**
     * Ένα γεγονός πάνω σε εργασία από τον $actorId. Πηγαίνει στον ΤΩΡΙΝΟ κάτοχο.
     */
    public static function event($taskId, $actorId, $kind, $what = '', $excerpt = null)
    {
        try {
            $t = Capsule::table('mod_cpm_tasks')->where('id', (int) $taskId)
                ->first(['id', 'action_user', 'assignee', 'completed_at']);
            if (!$t || !empty($t->completed_at)) { return; }
            $to = (int) ($t->action_user ?: $t->assignee);
            $actorId = (int) $actorId;
            if (!$to || $to === $actorId) { return; }
            $now = date('Y-m-d H:i:s');
            $what = mb_substr(trim((string) $what), 0, 255);
            $excerpt = $excerpt !== null ? self::clip($excerpt) : null;
            $row = Capsule::table(self::T)->where('admin_id', $to)->where('task_id', (int) $taskId)->first();
            $sameBurst = $row && $row->read_at === null && (int) $row->by_admin === $actorId
                && strtotime((string) $row->at) >= time() - self::MERGE_SEC;
            /* Αδιάβαστη μπάλα/μήνυμα δεν σκεπάζεται από μια μικρότερη αλλαγή τρίτου (π.χ.
               κάποιος άλλαξε τη λήξη): ο λόγος που πρέπει να κινηθείς μένει ο τίτλος, η
               νέα αλλαγή μπαίνει ως λεπτομέρεια και η γραμμή ανεβαίνει στην κορυφή. */
            /* ΜΟΝΟ για μικρές αλλαγές (κατάσταση, ανάθεση, πεδία): ένα ΜΗΝΥΜΑ ή μια μπάλα είναι
               δικό τους γεγονός, με δικό τους συντάκτη — δεν «κολλάνε» κάτω από το όνομα άλλου. */
            $keepStrong = $row && $row->read_at === null && (self::RANK[$kind] ?? 0) <= self::RANK['status']
                && (self::RANK[$row->kind] ?? 0) > (self::RANK[$kind] ?? 0);
            if ($sameBurst || $keepStrong) {
                if ((self::RANK[$row->kind] ?? 0) > (self::RANK[$kind] ?? 0)) { $kind = $row->kind; }
                $parts = array_filter(array_map('trim', explode(' · ', (string) $row->what . ' · ' . $what)), 'strlen');
                $what = mb_substr(implode(' · ', array_values(array_unique($parts))), 0, 255);
                if ($excerpt === null || $excerpt === '') { $excerpt = $row->excerpt; }
                if ($keepStrong && !$sameBurst) { $actorId = (int) $row->by_admin; }   // ο τίτλος μένει του αρχικού
            }
            $vals = ['kind' => $kind, 'what' => $what !== '' ? $what : null, 'excerpt' => $excerpt ?: null,
                'by_admin' => $actorId ?: null, 'at' => $now, 'read_at' => null];
            if ($row) {
                Capsule::table(self::T)->where('id', $row->id)->update($vals);
            } else {
                Capsule::table(self::T)->insert($vals + ['admin_id' => $to, 'task_id' => (int) $taskId]);
            }
            /* Η εργασία ΗΡΘΕ σε μένα (μπάλα, ανάθεση, νέα) → ειδοποίηση. Ένα σχόλιο χωρίς αλλαγή
               μπάλας δεν ειδοποιεί: φαίνεται στα Εισερχόμενα, δεν διακόπτει. */
            if (in_array($kind, ['ball', 'new', 'assign'], true)) { self::queue($to, (int) $taskId); }
        } catch (\Throwable $e) {
            /* τα Εισερχόμενα δεν ρίχνουν ποτέ την πράξη */
        }
    }

    /**
     * Από το mod_cpm_activity (Db::logActivity): μεταφράζει την ενέργεια σε είδος.
     * Ό,τι δεν ζητά τίποτα από τον κάτοχο (χρονόμετρα, συγχρονισμός GoodDay,
     * check-in) δεν μπαίνει. Το σκέτο «Επεξεργασία (web app)» δεν λέει τι άλλαξε —
     * το save_task στέλνει δικό του, ακριβές γεγονός (fromSave).
     */
    public static function fromActivity($taskId, $actorId, $action, $detail)
    {
        $detail = (string) $detail;
        if (in_array($action, ['time', 'update', 'checkin', 'call'], true)) { return; }
        if ($action === 'edit' && $detail === 'Επεξεργασία (web app)') { return; }
        $map = ['status' => 'status', 'move' => 'status', 'create' => 'new', 'auto' => 'new',
            'assign' => 'assign', 'collab' => 'collab', 'comment' => 'msg'];
        $kind = $map[$action] ?? 'edit';
        if ($action === 'edit' && strpos($detail, 'Μπάλα:') === 0) { $kind = 'ball'; }
        if ($action === 'status') { $detail = 'Κατάσταση: ' . $detail; }
        if ($kind === 'new') { $detail = ''; }
        self::event($taskId, $actorId, $kind, $detail, $action === 'comment' ? $detail : null);
    }

    /** Νέο μήνυμα/ενέργεια στη ροή της εργασίας (Db::addCheckItem). */
    public static function fromMessage($taskId, $actorId, $body)
    {
        if (!(int) $actorId) { return; }   // αυτόματα βήματα (χωρίς συντάκτη) δεν είναι «μου έγραψαν»
        self::event($taskId, $actorId, 'msg', '', $body);
    }

    /**
     * save_task: τι άλλαξε ΠΡΑΓΜΑΤΙΚΑ ($data σε σχέση με την εργασία πριν).
     * Μπάλα σε μένα → ball· νέα ανάθεση → assign· οτιδήποτε άλλο → edit με τα πεδία.
     */
    public static function fromSave($old, array $data, $actorId)
    {
        $labels = ['title' => 'τίτλος', 'descr' => 'ζητούμενο', 'due_date' => 'λήξη', 'due_time' => 'ώρα λήξης',
            'start_date' => 'έναρξη', 'schedule_date' => 'deadline', 'priority' => 'προτεραιότητα',
            'estimate_minutes' => 'εκτίμηση', 'type_id' => 'τύπος', 'project_id' => 'έργο', 'dept_id' => 'department'];
        $chg = [];
        foreach ($labels as $col => $lbl) {
            if (array_key_exists($col, $data) && (string) $data[$col] !== (string) ($old->$col ?? '')) { $chg[] = $lbl; }
        }
        $what = $chg ? 'Άλλαξε: ' . implode(', ', $chg) : '';
        $ballNew = array_key_exists('action_user', $data) ? (int) $data['action_user'] : (int) $old->action_user;
        if (array_key_exists('action_user', $data) && $ballNew && $ballNew !== (int) $old->action_user) {
            self::event($old->id, $actorId, 'ball', $what);
        } elseif (array_key_exists('assignee', $data) && (int) $data['assignee'] && (int) $data['assignee'] !== (int) $old->assignee) {
            self::event($old->id, $actorId, 'assign', $what);
        } elseif ($what !== '') {
            self::event($old->id, $actorId, 'edit', $what);
        }
    }

    /* ── Ειδοποίηση «ήρθε σε σένα» (bell + Windows/κινητό), 23/10/2026 ─────────────────────
       Στέλνεται ΣΤΟ ΤΕΛΟΣ του αιτήματος, όχι τη στιγμή του γεγονότος:
       • μια απάντηση = μήνυμα + μπάλα + κατάσταση· στο τέλος η γραμμή έχει ΟΛΟ το νόημα (και το απόσπασμα)·
       • η απάντηση τρέχει σε συναλλαγή — αν ακυρωθεί, η γραμμή δεν υπάρχει και δεν στέλνεται τίποτα·
       • η συσκευή ζητά «την τελευταία ειδοποίηση» — πρέπει να είναι ήδη γραμμένη (commit). */
    private static $queue = [];
    private static function queue($to, $taskId)
    {
        if (!self::$queue) { register_shutdown_function([self::class, 'flush']); }
        self::$queue[$to . ':' . $taskId] = [$to, $taskId, date('Y-m-d H:i:s', time() - 2)];
    }
    public static function flush()
    {
        $q = self::$queue; self::$queue = [];
        if (!$q) { return; }
        if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }   // η απάντηση φεύγει πρώτα — το push μετά
        foreach ($q as [$to, $taskId, $since]) {
            try {
                $row = Capsule::table(self::T)->where('admin_id', $to)->where('task_id', $taskId)->first();
                $t = Capsule::table('mod_cpm_tasks')->where('id', $taskId)->first(['id', 'title', 'action_user', 'assignee', 'completed_at']);
                if (!$row || !$t || $row->read_at !== null || !empty($t->completed_at)) { continue; }   // ακυρώθηκε / το είδε ήδη
                if ((int) ($t->action_user ?: $t->assignee) !== (int) $to) { continue; }             // η μπάλα έφυγε ήδη
                [$title, $body] = self::notifText($row, $t);
                $url = '/project/#/task/' . (int) $taskId;
                /* Η παλιά σκέτη «Σου ανατέθηκε…» του ίδιου αιτήματος περισσεύει — αυτή λέει περισσότερα. */
                Capsule::table('mod_cpm_notifications')->where('admin_id', $to)->whereIn('type', ['assign', 'action'])
                    ->where('url', $url)->where('created_at', '>=', $since)->delete();
                Db::pushNotification($to, 'inbox', $title, $url, $body, false);
                Db::pushDevices($to);
            } catch (\Throwable $e) { /* ποτέ δεν σπάει τίποτα */ }
        }
    }
    /** «Νέα εργασία #782 · Τίτλος» / «#782 · Τίτλος» + «Ποιος τι · «απόσπασμα»». Λίγα και καθαρά. */
    public static function notifText($row, $t)
    {
        $tt = mb_substr(trim((string) $t->title), 0, 80);
        $isNew = in_array($row->kind, ['new', 'assign'], true);
        $title = ($isNew ? 'Νέα εργασία #' : '#') . (int) $t->id . ' · ' . $tt;
        $by = $row->by_admin ? Db::adminName((int) $row->by_admin) : '';
        $verb = ['ball' => 'σου πέρασε τη μπάλα', 'new' => 'σου έδωσε νέα εργασία', 'assign' => 'σου την ανέθεσε'][$row->kind] ?? 'σου την έστειλε';
        $line = $by !== '' ? $by . ' ' . $verb : ($isNew ? 'Σου ανατέθηκε' : 'Η μπάλα είναι σε σένα');
        $st = array_values(array_filter(explode(' · ', (string) $row->what), function ($p) { return strpos($p, 'Κατάσταση:') === 0; }));
        if ($st) { $line .= ' · κατάσταση ' . trim(preg_replace('/^Κατάσταση:\s*.*→\s*/u', '→ ', $st[0])); }
        $body = $line . ($row->excerpt ? "\n«" . mb_substr((string) $row->excerpt, 0, 140) . (mb_strlen((string) $row->excerpt) > 140 ? '…' : '') . '»' : '');
        return [$title, $body];
    }

    /** Το άνοιξα → διαβάστηκε. */
    public static function markRead($adminId, $taskId = null)
    {
        try {
            $q = Capsule::table(self::T)->where('admin_id', (int) $adminId)->whereNull('read_at');
            if ($taskId !== null) { $q->where('task_id', (int) $taskId); }
            return $q->update(['read_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function clip($html)
    {
        $s = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', (string) $html)), ENT_QUOTES, 'UTF-8');
        /* Η βάση είναι utf8mb3: emoji γίνονται «????». Τα πετάμε από το απόσπασμα. */
        $s = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        return mb_substr($s, 0, 200);
    }

    /**
     * Μία φορά, στη δημιουργία: για κάθε ανοιχτή εργασία με κάτοχο, το τελευταίο
     * που έκανε ΑΛΛΟΣ πάνω της (ενέργεια ή μήνυμα) — ως ΔΙΑΒΑΣΜΕΝΟ. Χωρίς αυτό η
     * οθόνη θα ξεκινούσε με όλες τις παλιές εργασίες «από το πουθενά», και με
     * αδιάβαστα θα έδινε 40 κόκκινα την πρώτη μέρα — που κανείς δεν θα πίστευε.
     */
    public static function backfill()
    {
        $closed = Db::closedStatusIds() ?: [0];
        $tasks = Capsule::table('mod_cpm_tasks')->whereNull('completed_at')->whereNotIn('status_id', $closed)
            ->get(['id', 'action_user', 'assignee']);
        foreach ($tasks as $t) {
            $to = (int) ($t->action_user ?: $t->assignee);
            if (!$to) { continue; }
            $a = Capsule::table('mod_cpm_activity')->where('task_id', $t->id)->whereNotNull('admin_id')
                ->where('admin_id', '!=', $to)->whereNotIn('action', ['time', 'update', 'checkin', 'call'])
                ->orderByDesc('id')->first();
            $m = Capsule::table('mod_cpm_checklist')->where('task_id', $t->id)->whereNotNull('created_by')
                ->where('created_by', '!=', $to)->where('created_by', '>', 0)->orderByDesc('id')->first();
            if (!$a && !$m) { continue; }
            if ($m && (!$a || strcmp((string) $m->created_at, (string) $a->created_at) > 0)) {
                $row = ['kind' => 'msg', 'what' => null, 'excerpt' => self::clip($m->title) ?: null,
                    'by_admin' => (int) $m->created_by, 'at' => $m->created_at];
            } else {
                $d = (string) $a->detail;
                $kind = $a->action === 'status' ? 'status' : ($a->action === 'edit' && strpos($d, 'Μπάλα:') === 0 ? 'ball'
                    : (in_array($a->action, ['create', 'auto'], true) ? 'new' : 'edit'));
                if ($d === 'Επεξεργασία (web app)' || $kind === 'new') { $d = ''; }
                $row = ['kind' => $kind, 'what' => $d !== '' ? mb_substr(($a->action === 'status' ? 'Κατάσταση: ' : '') . $d, 0, 255) : null,
                    'excerpt' => null, 'by_admin' => (int) $a->admin_id, 'at' => $a->created_at];
            }
            if (!$row['at']) { continue; }
            Capsule::table(self::T)->insert($row + ['admin_id' => $to, 'task_id' => (int) $t->id, 'read_at' => $row['at']]);
        }
    }
}
