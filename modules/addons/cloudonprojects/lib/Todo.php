<?php
/**
 * To-do λίστες εργασίας (25/11/2026).
 *
 * Μία εργασία έχει 0..N λίστες με τίτλο· κάθε λίστα έχει σημεία που τσεκάρονται, ώστε
 * να φαίνεται ΤΙ από τα ζητούμενα έχει γίνει. Διαφέρει από:
 *  - τις «ενέργειες» (mod_cpm_checklist) = η συζήτηση/χρονολόγιο της εργασίας·
 *  - τις υποεργασίες (mod_cpm_tasks.parent_id) = κανονικές εργασίες με ανάδοχο & χρόνο.
 * Ένα σημείο μπορεί να ΓΙΝΕΙ υποεργασία (sub_task_id) ή να δείχνει σε υπάρχουσα υποεργασία·
 * τότε το «έγινε» το ορίζει η κατάσταση της υποεργασίας, όχι το κουτάκι.
 *
 * GoodDay: το task payload φέρνει `todoList` = [{uid,title,items:[{uid,type,properties:{title,
 * description,completed,userId,deadline}}]}]. Κρατάμε το uid (ext_uid) ώστε το «↻ Ξανά από
 * GoodDay» να ενημερώνει τα ίδια σημεία αντί να τα διπλασιάζει — με τον ίδιο κανόνα που
 * ισχύει για τα πεδία: αλλαγή στο GoodDay περνά ΜΟΝΟ αν εδώ δεν την πείραξε κανείς.
 * Ό,τι διαγράφεται εδώ και ήρθε από GoodDay κρατιέται ως deleted=1, για να μην ξαναέρθει.
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use Illuminate\Database\Capsule\Manager as Capsule;

class Todo
{
    const MAX_LISTS = 30;
    const MAX_ITEMS = 300;          // ανά εργασία
    const LIST_LEN = 250;
    const ITEM_LEN = 2000;

    private static $ok = false;

    public static function ensure()
    {
        if (self::$ok) { return; }
        $s = Capsule::schema();
        if (!$s->hasTable('mod_cpm_todo_lists')) {
            $s->create('mod_cpm_todo_lists', function ($t) {
                $t->increments('id');
                $t->integer('task_id')->unsigned()->index();
                $t->string('title', 250);
                $t->integer('sort')->default(0);
                $t->string('ext_uid', 32)->nullable();
                $t->tinyInteger('deleted')->default(0);
                $t->integer('created_by')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
        if (!$s->hasTable('mod_cpm_todo_items')) {
            $s->create('mod_cpm_todo_items', function ($t) {
                $t->increments('id');
                $t->integer('list_id')->unsigned()->index();
                $t->integer('task_id')->unsigned()->index();
                $t->text('title');
                $t->text('note')->nullable();
                $t->tinyInteger('done')->default(0);
                $t->integer('done_by')->unsigned()->nullable();
                $t->dateTime('done_at')->nullable();
                $t->integer('assignee')->unsigned()->nullable();
                $t->date('due')->nullable();
                $t->integer('sub_task_id')->unsigned()->nullable()->index();
                $t->string('ext_uid', 32)->nullable();
                $t->tinyInteger('gone')->default(0);       // υπήρχε στο GoodDay, δεν υπάρχει πια (κρατήθηκε γιατί άλλαξε εδώ)
                $t->tinyInteger('deleted')->default(0);
                $t->integer('sort')->default(0);
                $t->integer('created_by')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
        self::$ok = true;
    }

    /** Απλό κείμενο (με αλλαγές γραμμής)· emoji → &#N; γιατί η βάση είναι utf8mb3. */
    public static function txt($s, $max)
    {
        $s = html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
        $s = trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+\n/', "\n", $s)));
        $s = mb_substr($s, 0, $max);
        return preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', function ($m) { return '&#' . mb_ord($m[0], 'UTF-8') . ';'; }, $s);
    }

    /* ════════════════ Ανάγνωση ════════════════ */

    /** Οι λίστες μιας εργασίας για την καρτέλα. */
    public static function lists($taskId)
    {
        self::ensure();
        $taskId = (int) $taskId;
        $lists = Capsule::table('mod_cpm_todo_lists')->where('task_id', $taskId)->where('deleted', 0)
            ->orderBy('sort')->orderBy('id')->get();
        if (!count($lists)) { return []; }
        $items = Capsule::table('mod_cpm_todo_items')->where('task_id', $taskId)->where('deleted', 0)
            ->orderBy('sort')->orderBy('id')->get();
        $subIds = [];
        foreach ($items as $it) { if ($it->sub_task_id) { $subIds[(int) $it->sub_task_id] = 1; } }
        $subs = [];
        if ($subIds) {
            $closed = Db::closedStatusIds();
            foreach (Capsule::table('mod_cpm_tasks')->whereIn('id', array_keys($subIds))
                ->get(['id', 'title', 'status_id', 'assignee', 'parent_id']) as $sb) {
                $subs[(int) $sb->id] = ['id' => (int) $sb->id, 'title' => (string) $sb->title, 'status' => (int) $sb->status_id,
                    'done' => in_array((int) $sb->status_id, $closed, true),
                    'assignee' => $sb->assignee ? (int) $sb->assignee : null,
                    'mine' => (int) $sb->parent_id === $taskId];
            }
        }
        $by = [];
        foreach ($items as $it) {
            $sub = $it->sub_task_id ? ($subs[(int) $it->sub_task_id] ?? null) : null;
            $by[(int) $it->list_id][] = [
                'id' => (int) $it->id, 'title' => (string) $it->title, 'note' => $it->note !== null && $it->note !== '' ? (string) $it->note : null,
                'done' => $sub ? $sub['done'] : (bool) $it->done,
                'doneBy' => $it->done && $it->done_by ? Db::adminName((int) $it->done_by) : null,
                'doneAt' => $it->done ? $it->done_at : null,
                'assignee' => $it->assignee ? (int) $it->assignee : null,
                'assigneeName' => $it->assignee ? Db::adminName((int) $it->assignee) : null,
                'due' => $it->due, 'sub' => $sub, 'ext' => $it->ext_uid ? true : false, 'gone' => (bool) $it->gone];
        }
        $out = [];
        foreach ($lists as $l) {
            $its = $by[(int) $l->id] ?? [];
            $out[] = ['id' => (int) $l->id, 'title' => (string) $l->title, 'ext' => $l->ext_uid ? true : false,
                'items' => $its, 'done' => count(array_filter($its, function ($x) { return $x['done']; })), 'total' => count($its)];
        }
        return $out;
    }

    /** Υπογραφή αλλαγών (μπαίνει στη «σφραγίδα» της εργασίας → ζωντανή ανανέωση στις ανοιχτές καρτέλες). */
    public static function sig($taskId)
    {
        if (!Capsule::schema()->hasTable('mod_cpm_todo_items')) { return ''; }
        $r = Capsule::table('mod_cpm_todo_items')->where('task_id', (int) $taskId)
            ->selectRaw('COUNT(*) n, COALESCE(MAX(updated_at),0) u, COALESCE(SUM(done),0) d, COALESCE(SUM(CRC32(CONCAT(title,sort,list_id,deleted,COALESCE(sub_task_id,0)))),0) c')->first();
        $l = Capsule::table('mod_cpm_todo_lists')->where('task_id', (int) $taskId)
            ->selectRaw('COUNT(*) n, COALESCE(SUM(CRC32(CONCAT(title,sort,deleted))),0) c')->first();
        return ($r ? $r->n . '.' . $r->d . '.' . $r->c . '.' . $r->u : '') . '/' . ($l ? $l->n . '.' . $l->c : '');
    }

    /** Διαγραφή εργασίας: φεύγουν και οι λίστες της. Οι υποεργασίες ΜΕΝΟΥΝ (άλλος κανόνας). */
    public static function forget($taskId)
    {
        if (!Capsule::schema()->hasTable('mod_cpm_todo_lists')) { return; }
        Capsule::table('mod_cpm_todo_items')->where('task_id', (int) $taskId)->delete();
        Capsule::table('mod_cpm_todo_lists')->where('task_id', (int) $taskId)->delete();
        /* σημεία ΑΛΛΗΣ εργασίας που έδειχναν σε αυτή ως υποεργασία: απλώς χάνουν τον σύνδεσμο */
        Capsule::table('mod_cpm_todo_items')->where('sub_task_id', (int) $taskId)->update(['sub_task_id' => null, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /* ════════════════ Εγγραφές ════════════════ */

    public static function listRow($taskId, $id)
    {
        self::ensure();
        return Capsule::table('mod_cpm_todo_lists')->where('id', (int) $id)->where('task_id', (int) $taskId)->where('deleted', 0)->first();
    }
    public static function itemRow($taskId, $id)
    {
        self::ensure();
        return Capsule::table('mod_cpm_todo_items')->where('id', (int) $id)->where('task_id', (int) $taskId)->where('deleted', 0)->first();
    }

    public static function saveList($taskId, $id, $title, $adminId)
    {
        self::ensure();
        $title = self::txt($title, self::LIST_LEN);
        if ($title === '') { throw new \InvalidArgumentException('Γράψε τίτλο για τη λίστα'); }
        $now = date('Y-m-d H:i:s');
        if ($id) {
            if (!self::listRow($taskId, $id)) { throw new \InvalidArgumentException('Η λίστα δεν βρέθηκε'); }
            Capsule::table('mod_cpm_todo_lists')->where('id', (int) $id)->update(['title' => $title, 'updated_at' => $now]);
            return (int) $id;
        }
        if (Capsule::table('mod_cpm_todo_lists')->where('task_id', (int) $taskId)->where('deleted', 0)->count() >= self::MAX_LISTS) {
            throw new \InvalidArgumentException('Μέχρι ' . self::MAX_LISTS . ' λίστες ανά εργασία');
        }
        $sort = 10 + (int) Capsule::table('mod_cpm_todo_lists')->where('task_id', (int) $taskId)->max('sort');
        return (int) Capsule::table('mod_cpm_todo_lists')->insertGetId(['task_id' => (int) $taskId, 'title' => $title, 'sort' => $sort,
            'created_by' => (int) $adminId ?: null, 'created_at' => $now, 'updated_at' => $now]);
    }

    /** Διαγραφή λίστας ΜΕ τα σημεία της. Οι υποεργασίες που έδειχναν μένουν. */
    public static function delList($taskId, $id)
    {
        $l = self::listRow($taskId, $id);
        if (!$l) { throw new \InvalidArgumentException('Η λίστα δεν βρέθηκε'); }
        $now = date('Y-m-d H:i:s');
        /* ό,τι ήρθε από GoodDay κρατιέται «σβησμένο» για να μην το ξαναφέρει ο συγχρονισμός */
        Capsule::table('mod_cpm_todo_items')->where('list_id', (int) $l->id)->whereNotNull('ext_uid')->update(['deleted' => 1, 'updated_at' => $now]);
        Capsule::table('mod_cpm_todo_items')->where('list_id', (int) $l->id)->whereNull('ext_uid')->delete();
        if ($l->ext_uid) { Capsule::table('mod_cpm_todo_lists')->where('id', (int) $l->id)->update(['deleted' => 1, 'updated_at' => $now]); }
        else { Capsule::table('mod_cpm_todo_lists')->where('id', (int) $l->id)->delete(); }
        return $l;
    }

    public static function saveItem($taskId, $listId, $id, $title, $adminId)
    {
        self::ensure();
        $title = self::txt($title, self::ITEM_LEN);
        if ($title === '') { throw new \InvalidArgumentException('Γράψε τι πρέπει να γίνει'); }
        $now = date('Y-m-d H:i:s');
        if ($id) {
            if (!self::itemRow($taskId, $id)) { throw new \InvalidArgumentException('Το σημείο δεν βρέθηκε'); }
            Capsule::table('mod_cpm_todo_items')->where('id', (int) $id)->update(['title' => $title, 'gone' => 0, 'updated_at' => $now]);
            return (int) $id;
        }
        if (!self::listRow($taskId, $listId)) { throw new \InvalidArgumentException('Η λίστα δεν βρέθηκε'); }
        self::roomFor($taskId);
        $sort = 10 + (int) Capsule::table('mod_cpm_todo_items')->where('list_id', (int) $listId)->max('sort');
        return (int) Capsule::table('mod_cpm_todo_items')->insertGetId(['list_id' => (int) $listId, 'task_id' => (int) $taskId,
            'title' => $title, 'sort' => $sort, 'created_by' => (int) $adminId ?: null, 'created_at' => $now, 'updated_at' => $now]);
    }

    private static function roomFor($taskId)
    {
        if (Capsule::table('mod_cpm_todo_items')->where('task_id', (int) $taskId)->where('deleted', 0)->count() >= self::MAX_ITEMS) {
            throw new \InvalidArgumentException('Μέχρι ' . self::MAX_ITEMS . ' σημεία ανά εργασία');
        }
    }

    public static function toggle($taskId, $id, $adminId)
    {
        $it = self::itemRow($taskId, $id);
        if (!$it) { throw new \InvalidArgumentException('Το σημείο δεν βρέθηκε'); }
        if ($it->sub_task_id && Capsule::table('mod_cpm_tasks')->where('id', (int) $it->sub_task_id)->exists()) {
            throw new \InvalidArgumentException('Αυτό το σημείο είναι υποεργασία — ολοκληρώνεται όταν κλείσει η υποεργασία #' . (int) $it->sub_task_id);
        }
        $done = $it->done ? 0 : 1;
        Capsule::table('mod_cpm_todo_items')->where('id', (int) $it->id)->update(['done' => $done,
            'done_by' => $done ? (int) $adminId : null, 'done_at' => $done ? date('Y-m-d H:i:s') : null, 'updated_at' => date('Y-m-d H:i:s')]);
        return $done;
    }

    public static function delItem($taskId, $id)
    {
        $it = self::itemRow($taskId, $id);
        if (!$it) { throw new \InvalidArgumentException('Το σημείο δεν βρέθηκε'); }
        if ($it->ext_uid) { Capsule::table('mod_cpm_todo_items')->where('id', (int) $it->id)->update(['deleted' => 1, 'updated_at' => date('Y-m-d H:i:s')]); }
        else { Capsule::table('mod_cpm_todo_items')->where('id', (int) $it->id)->delete(); }
        return $it;
    }

    /** Μετακίνηση σημείου (σύρσιμο): σε λίστα $listId, στη θέση $index (0 = πρώτο). */
    public static function move($taskId, $id, $listId, $index)
    {
        $it = self::itemRow($taskId, $id);
        if (!$it) { throw new \InvalidArgumentException('Το σημείο δεν βρέθηκε'); }
        if (!self::listRow($taskId, $listId)) { throw new \InvalidArgumentException('Η λίστα δεν βρέθηκε'); }
        $ids = array_map('intval', Capsule::table('mod_cpm_todo_items')->where('list_id', (int) $listId)->where('deleted', 0)
            ->where('id', '!=', (int) $it->id)->orderBy('sort')->orderBy('id')->pluck('id')->all());
        array_splice($ids, max(0, min((int) $index, count($ids))), 0, [(int) $it->id]);
        $now = date('Y-m-d H:i:s');
        foreach ($ids as $i => $iid) {
            Capsule::table('mod_cpm_todo_items')->where('id', $iid)->update(['sort' => ($i + 1) * 10, 'list_id' => (int) $listId, 'updated_at' => $now]);
        }
    }

    /** Σημείο ↔ υποεργασία: δένει (ή λύνει με 0) τον σύνδεσμο. */
    public static function setSub($taskId, $id, $subId)
    {
        $it = self::itemRow($taskId, $id);
        if (!$it) { throw new \InvalidArgumentException('Το σημείο δεν βρέθηκε'); }
        Capsule::table('mod_cpm_todo_items')->where('id', (int) $it->id)->update(['sub_task_id' => $subId ? (int) $subId : null, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** Υπάρχουσα υποεργασία → νέο σημείο στη λίστα (ο τίτλος της είναι ο τίτλος του σημείου). */
    public static function addSubItem($taskId, $listId, $sub, $adminId)
    {
        if (!self::listRow($taskId, $listId)) { throw new \InvalidArgumentException('Η λίστα δεν βρέθηκε'); }
        if (Capsule::table('mod_cpm_todo_items')->where('task_id', (int) $taskId)->where('deleted', 0)->where('sub_task_id', (int) $sub->id)->exists()) {
            throw new \InvalidArgumentException('Η υποεργασία #' . (int) $sub->id . ' είναι ήδη σε λίστα');
        }
        self::roomFor($taskId);
        $now = date('Y-m-d H:i:s');
        $sort = 10 + (int) Capsule::table('mod_cpm_todo_items')->where('list_id', (int) $listId)->max('sort');
        return (int) Capsule::table('mod_cpm_todo_items')->insertGetId(['list_id' => (int) $listId, 'task_id' => (int) $taskId,
            'title' => self::txt($sub->title, self::ITEM_LEN) ?: ('#' . (int) $sub->id), 'sub_task_id' => (int) $sub->id,
            'assignee' => $sub->assignee ?: null, 'sort' => $sort, 'created_by' => (int) $adminId ?: null, 'created_at' => $now, 'updated_at' => $now]);
    }

    /* ════════════════ GoodDay ════════════════ */

    /**
     * todoList του GoodDay → κανονική μορφή. $admin($gdUserId) → admin id ή null.
     * @return array [['uid','title','items'=>[['uid','title','note','done','admin','due']]]]
     */
    public static function fromGoodDay($todoList, callable $admin)
    {
        $out = [];
        foreach (is_array($todoList) ? $todoList : [] as $i => $l) {
            if (!is_array($l)) { continue; }
            $uid = self::uid($l['uid'] ?? '', 'l' . $i);
            $items = [];
            foreach ((array) ($l['items'] ?? []) as $j => $it) {
                if (!is_array($it)) { continue; }
                $p = (array) ($it['properties'] ?? []);
                $title = self::txt($p['title'] ?? ($it['title'] ?? ''), self::ITEM_LEN);
                if ($title === '') { continue; }
                $note = isset($p['description']) && is_string($p['description']) ? self::txt($p['description'], 4000) : '';
                $items[] = ['uid' => self::uid($it['uid'] ?? '', $uid . '-' . $j), 'title' => $title, 'note' => $note !== '' ? $note : null,
                    'done' => !empty($p['completed']),
                    'admin' => !empty($p['userId']) ? $admin((string) $p['userId']) : null,
                    'due' => GoodDayImport::dateOnly($p['deadline'] ?? null)];
            }
            $out[] = ['uid' => $uid, 'title' => self::txt($l['title'] ?? '', self::LIST_LEN) ?: 'To-do', 'items' => $items];
        }
        return $out;
    }

    private static function uid($v, $fallback)
    {
        $v = (string) $v;
        return preg_match('/^[A-Za-z0-9_-]{1,32}$/', $v) ? $v : mb_substr('x' . $fallback, 0, 32);
    }

    /**
     * Εφαρμογή λιστών GoodDay σε εργασία μας. $old = οι λίστες της ΠΡΟΗΓΟΥΜΕΝΗΣ ανάκτησης
     * (null στην πρώτη εισαγωγή). Κανόνας: τιμή αλλάζει μόνο αν άλλαξε στο GoodDay ΚΑΙ εδώ
     * είναι ακόμη ίση με την προηγούμενη ανάκτηση — αλλιώς κρατάμε τη δική μας.
     * @return array ['lists'=>νέες λίστες, 'new'=>νέα σημεία, 'updated'=>, 'removed'=>, 'kept'=>]
     */
    public static function syncFromGoodDay($taskId, array $new, $old, $adminId)
    {
        self::ensure();
        $taskId = (int) $taskId;
        $now = date('Y-m-d H:i:s');
        $rep = ['lists' => 0, 'new' => 0, 'updated' => 0, 'removed' => 0, 'kept' => 0];
        $oldItems = []; $oldLists = [];
        foreach ((array) $old as $l) {
            $oldLists[$l['uid']] = $l;
            foreach ($l['items'] as $it) { $oldItems[$it['uid']] = $it; }
        }
        $haveL = []; $haveI = [];
        foreach (Capsule::table('mod_cpm_todo_lists')->where('task_id', $taskId)->whereNotNull('ext_uid')->get() as $r) { $haveL[$r->ext_uid] = $r; }
        foreach (Capsule::table('mod_cpm_todo_items')->where('task_id', $taskId)->whereNotNull('ext_uid')->get() as $r) { $haveI[$r->ext_uid] = $r; }
        $baseSort = 0;
        if (!$haveL) { $baseSort = (int) Capsule::table('mod_cpm_todo_lists')->where('task_id', $taskId)->max('sort'); }
        $seenI = [];
        foreach ($new as $li => $l) {
            $lr = $haveL[$l['uid']] ?? null;
            if ($lr && $lr->deleted) {               // σβήστηκε εδώ — δεν ξανάρχεται, ούτε τα σημεία της
                foreach ($l['items'] as $it) { $seenI[$it['uid']] = 1; }
                continue;
            }
            $ol = $oldLists[$l['uid']] ?? null;
            if ($lr) {
                $up = ['sort' => $baseSort + ($li + 1) * 10];
                if ($ol && $ol['title'] !== $l['title'] && (string) $lr->title === $ol['title']) { $up['title'] = $l['title']; }
                elseif (!$ol && (string) $lr->title !== $l['title']) { $up['title'] = $l['title']; }
                Capsule::table('mod_cpm_todo_lists')->where('id', (int) $lr->id)->update($up + ['updated_at' => $now]);
                $listId = (int) $lr->id;
            } else {
                $listId = (int) Capsule::table('mod_cpm_todo_lists')->insertGetId(['task_id' => $taskId, 'title' => $l['title'],
                    'sort' => $baseSort + ($li + 1) * 10, 'ext_uid' => $l['uid'], 'created_by' => (int) $adminId ?: null,
                    'created_at' => $now, 'updated_at' => $now]);
                $rep['lists']++;
            }
            foreach ($l['items'] as $ii => $it) {
                $seenI[$it['uid']] = 1;
                $ir = $haveI[$it['uid']] ?? null;
                $oi = $oldItems[$it['uid']] ?? null;
                if ($ir && $ir->deleted) { continue; }
                if (!$ir) {
                    Capsule::table('mod_cpm_todo_items')->insert(['list_id' => $listId, 'task_id' => $taskId, 'title' => $it['title'],
                        'note' => $it['note'], 'done' => $it['done'] ? 1 : 0, 'done_at' => $it['done'] ? $now : null,
                        'assignee' => $it['admin'] ?: null, 'due' => $it['due'], 'ext_uid' => $it['uid'],
                        'sort' => ($ii + 1) * 10, 'created_by' => (int) $adminId ?: null, 'created_at' => $now, 'updated_at' => $now]);
                    $rep['new']++;
                    continue;
                }
                $up = ['list_id' => $listId, 'sort' => ($ii + 1) * 10, 'gone' => 0];
                $chg = false; $kept = false;
                /* πεδίο-πεδίο: [στήλη, νέα τιμή GoodDay, προηγούμενη τιμή GoodDay, τιμή εδώ] */
                $cmp = [
                    ['title', $it['title'], $oi ? $oi['title'] : null, (string) $ir->title],
                    ['note', (string) $it['note'], $oi ? (string) $oi['note'] : null, (string) $ir->note],
                    ['assignee', (int) $it['admin'], $oi ? (int) $oi['admin'] : null, (int) $ir->assignee],
                    ['due', (string) $it['due'], $oi ? (string) $oi['due'] : null, (string) $ir->due],
                ];
                if (!$ir->sub_task_id) { $cmp[] = ['done', $it['done'] ? 1 : 0, $oi ? ($oi['done'] ? 1 : 0) : null, (int) $ir->done]; }
                foreach ($cmp as [$col, $nv, $ov, $here]) {
                    if ($nv === $here) { continue; }
                    if ($ov !== null && $nv === $ov) { continue; }            // δεν άλλαξε στο GoodDay — άλλαξε εδώ, μένει
                    if ($ov !== null && $here !== $ov) { $kept = true; continue; }   // άλλαξαν και οι δύο: κρατάμε τη δική μας
                    if ($col === 'done') {
                        $up['done'] = $nv; $up['done_at'] = $nv ? $now : null; $up['done_by'] = null;
                    } elseif ($col === 'assignee') { $up['assignee'] = $nv ?: null; }
                    elseif ($col === 'due' || $col === 'note') { $up[$col] = $nv !== '' ? $nv : null; }
                    else { $up[$col] = $nv; }
                    $chg = true;
                }
                if ((int) $ir->list_id !== $listId || (int) $ir->gone) { $chg = true; }
                Capsule::table('mod_cpm_todo_items')->where('id', (int) $ir->id)->update($up + ($chg ? ['updated_at' => $now] : []));
                if ($chg) { $rep['updated']++; }
                if ($kept) { $rep['kept']++; }
            }
        }
        /* Σημεία που δεν υπάρχουν πια στο GoodDay: φεύγουν αν εδώ δεν τα άγγιξε κανείς, αλλιώς μένουν σημειωμένα. */
        foreach ($haveI as $uid => $ir) {
            if (isset($seenI[$uid]) || $ir->deleted) { continue; }
            $oi = $oldItems[$uid] ?? null;
            $untouched = $oi && !$ir->sub_task_id && (string) $ir->title === $oi['title'] && (int) $ir->done === ($oi['done'] ? 1 : 0);
            if ($untouched) { Capsule::table('mod_cpm_todo_items')->where('id', (int) $ir->id)->delete(); $rep['removed']++; }
            elseif (!$ir->gone) { Capsule::table('mod_cpm_todo_items')->where('id', (int) $ir->id)->update(['gone' => 1, 'updated_at' => $now]); }
        }
        $newL = [];
        foreach ($new as $l) { $newL[$l['uid']] = 1; }
        foreach ($haveL as $uid => $lr) {
            if (isset($newL[$uid]) || $lr->deleted) { continue; }
            if (!Capsule::table('mod_cpm_todo_items')->where('list_id', (int) $lr->id)->where('deleted', 0)->exists()) {
                Capsule::table('mod_cpm_todo_lists')->where('id', (int) $lr->id)->delete();
            }
        }
        return $rep;
    }
}
