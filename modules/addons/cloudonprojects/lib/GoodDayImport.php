<?php
/**
 * Εισαγωγή εργασίας από το GoodDay (9/10/2026).
 *
 * Μεταφορά σε PHP του goodday_reader.py (πακέτο του Νίκου): ίδιο «φάκελο» ανάκτησης
 * (schema_version, source, external_task_id, source_url, retrieved_at, task, messages,
 * users, warnings) — μόνο ΑΝΑΓΝΩΣΗ από το GoodDay, τίποτα δεν γράφεται εκεί.
 *
 * Κανόνες που δεν αλλάζουν χωρίς απόφαση:
 *  - Το token ζει ΜΟΝΟ στον server (κρυπτογραφημένο στις ρυθμίσεις). Δεν μπαίνει σε
 *    απάντηση, σε log, σε μήνυμα σφάλματος ή στα δεδομένα της εργασίας.
 *  - Ο host του API είναι σταθερός εδώ· η φόρμα στέλνει μόνο το task (id ή URL).
 *  - Καμία ακολούθηση redirect (το token δεν πάει αλλού), timeouts παντού.
 *  - Συγγραφέας μηνύματος = συνάδελφος ΜΟΝΟ αν το email του αντιστοιχεί σε ΕΝΑΝ ενεργό
 *    χρήστη. Αλλιώς μένει ο αρχικός συγγραφέας του GoodDay, ορατά αναντιστοίχιστος.
 *    Ποτέ δεν «δανείζεται» το όνομα του χρήστη που κάνει την εισαγωγή.
 *  - Ώρες GoodDay χωρίς ζώνη: ΡΗΤΗ ρύθμιση (goodday_tz). Επιβεβαιώθηκε UTC στις 9/10/2026
 *    με δύο ανεξάρτητα σημεία (email 12:53 Ελλάδας → GoodDay 09:54· εργασία #121 12:32
 *    Ελλάδας → «Μεταφέρθηκε στο 121» GoodDay 09:39).
 *  - Τα μηνύματα μένουν ΙΣΤΟΡΙΚΟ μόνο-ανάγνωσης (mod_cpm_ext_msgs) με τον αρχικό
 *    συγγραφέα και ώρα — όχι ψεύτικες «ενέργειες» με αλλαγμένη ημερομηνία.
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use Illuminate\Database\Capsule\Manager as Capsule;

class GoodDayImport
{
    const BASE = 'https://api.goodday.work/2.0';
    const SOURCE = 'goodday';
    const INTEGRATION = 'default';      // μία ρυθμισμένη σύνδεση (ένας οργανισμός GoodDay)
    const SCHEMA = '1.0';
    const LOCAL_TZ = 'Europe/Athens';
    const STAGE_TTL = 7200;             // προεπισκόπηση ισχύει 2 ώρες
    const MAX_SUBS = 80;                // όριο υποεργασιών ανά εισαγωγή (προστασία από ατέρμονο δέντρο)

    /** Χρήστες GoodDay που διαβάστηκαν ήδη σε αυτό το αίτημα — οι υποεργασίες έχουν τους ίδιους ανθρώπους. */
    private static $userCache = [];

    /* ════════════════ Σχήμα ════════════════ */

    public static function ensure()
    {
        static $done = false;
        if ($done) { return; }
        $done = true;
        $s = Capsule::schema();
        if (!$s->hasTable('mod_cpm_ext_imports')) {
            $s->create('mod_cpm_ext_imports', function ($t) {
                $t->increments('id');
                $t->string('integration', 32);
                $t->string('source', 16);
                $t->string('ext_task_id', 64);
                $t->unsignedInteger('task_id')->nullable();
                $t->string('source_url', 255)->nullable();
                $t->longText('envelope');                // ο φάκελος ανάκτησης, ΜΙΑ φορά
                $t->text('meta')->nullable();            // κατάσταση πηγής, ό,τι δεν αντιστοιχίστηκε, χάρτης χρηστών
                $t->text('warnings')->nullable();
                $t->unsignedInteger('imported_by')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->unique(['integration', 'source', 'ext_task_id'], 'cpm_ext_imp_uq');
                $t->index('task_id');
            });
        }
        if (!$s->hasTable('mod_cpm_ext_msgs')) {
            $s->create('mod_cpm_ext_msgs', function ($t) {
                $t->increments('id');
                $t->unsignedInteger('import_id');
                $t->unsignedInteger('task_id');
                $t->string('ext_msg_id', 64);
                $t->unsignedInteger('seq')->default(0);
                $t->string('kind', 12);                  // text | status | time | empty
                $t->string('author_ext', 64)->nullable();
                $t->string('author_name', 190)->nullable();
                $t->string('author_email', 190)->nullable();
                $t->unsignedInteger('admin_id')->nullable();   // ΜΟΝΟ αν αντιστοιχίστηκε μονοσήμαντα
                $t->string('to_ext', 64)->nullable();
                $t->unsignedInteger('to_admin')->nullable();
                $t->dateTime('at')->nullable();          // τοπική ώρα (Europe/Athens)
                $t->string('at_src', 40)->nullable();    // όπως ήρθε από το GoodDay
                $t->dateTime('edit_at')->nullable();
                $t->string('edit_src', 40)->nullable();
                $t->string('status_ext', 64)->nullable();
                $t->mediumText('html')->nullable();      // καθαρισμένο, στο δικό μας HTML
                $t->string('flags', 255)->nullable();
                $t->text('attachments')->nullable();     // μόνο μεταδεδομένα, κανένα κατέβασμα
                $t->unique(['import_id', 'ext_msg_id'], 'cpm_ext_msg_uq');
                $t->index('task_id');
            });
        }
        if (!$s->hasTable('mod_cpm_ext_stage')) {
            $s->create('mod_cpm_ext_stage', function ($t) {
                $t->increments('id');
                $t->string('nonce', 40);
                $t->unsignedInteger('admin_id');
                $t->string('ext_task_id', 64);
                $t->longText('envelope');
                $t->timestamp('created_at')->nullable();
                $t->unique('nonce');
            });
        }
    }

    /* ════════════════ Ανάκτηση (goodday_reader.py) ════════════════ */

    /** ID ή URL /t/{id}. Το shortId (π.χ. 14297) ΔΕΝ είναι API id. */
    public static function extractTaskId($ref)
    {
        $v = trim((string) $ref);
        /* Δεκτό και χωρίς σχήμα (www.goodday.work/t/6lyyHw) — συμπληρώνεται https. */
        if (preg_match('#^(www\.)?goodday\.work/#i', $v)) { $v = 'https://' . $v; }
        if (preg_match('#^https?://#i', $v)) {
            $p = parse_url($v);
            $parts = explode('/', trim((string) ($p['path'] ?? ''), '/'));
            if (strtolower((string) ($p['scheme'] ?? '')) !== 'https'
                || !in_array(strtolower((string) ($p['host'] ?? '')), ['www.goodday.work', 'goodday.work'], true)
                || isset($p['user']) || isset($p['pass']) || isset($p['port'])
                || count($parts) !== 2 || $parts[0] !== 't') {
                throw new \InvalidArgumentException('Δώσε σύνδεσμο εργασίας GoodDay όπως https://www.goodday.work/t/6lyyHw');
            }
            $v = $parts[1];
        }
        if ($v === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $v)) {
            throw new \InvalidArgumentException('Δώσε το ID εργασίας GoodDay (π.χ. 6lyyHw) ή τον σύνδεσμό της.');
        }
        if (preg_match('/^\d+$/', $v)) {
            throw new \InvalidArgumentException('Αυτό μοιάζει με τον αριθμό εμφάνισης (#' . $v . '), όχι με το ID της εργασίας. Αντίγραψε τον σύνδεσμο της εργασίας από το GoodDay (…/t/xxxxxx).');
        }
        return $v;
    }

    /** GET με το token· σφάλματα ΧΩΡΙΣ το token και χωρίς σώμα απάντησης. */
    private static function get($path, $token)
    {
        $ch = curl_init(self::BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['gd-api-token: ' . $token, 'Accept: application/json', 'User-Agent: CloudOnProjects-GoodDayImport/1.0'],
            CURLOPT_FOLLOWLOCATION => false,                 // το token δεν ακολουθεί redirect
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($raw === false || $errno) {
            throw new GoodDayError('Δεν απάντησε το GoodDay (σύνδεση/χρόνος). Δοκίμασε ξανά σε λίγο.', 0);
        }
        if ($code < 200 || $code >= 300) {
            $hints = [401 => 'Το token του GoodDay δεν γίνεται δεκτό — έλεγξέ το στις Ρυθμίσεις.',
                403 => 'Το GoodDay αρνήθηκε την πρόσβαση — έλεγξε το token (Ρυθμίσεις → GoodDay), το ID της εργασίας και ότι ο λογαριασμός του token βλέπει αυτό το έργο.',   // το GoodDay δίνει 403 και σε άκυρο token
                404 => 'Δεν βρέθηκε στο GoodDay — έλεγξε το ID της εργασίας.',
                429 => 'Το GoodDay περιορίζει τα αιτήματα — δοκίμασε ξανά σε λίγο.'];
            $where = preg_replace('#^/(task|user)/[^/]+#', '/$1/{id}', $path);   // διαδρομή χωρίς ids
            throw new GoodDayError(($hints[$code] ?? ($code >= 300 && $code < 400 ? 'Το GoodDay απάντησε με ανακατεύθυνση — δεν την ακολουθούμε.' : 'Αποτυχία αιτήματος.'))
                . ' (GET ' . $where . ': HTTP ' . $code . ')', $code);
        }
        $j = json_decode((string) $raw, true);
        if ($j === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new GoodDayError('Το GoodDay δεν επέστρεψε JSON.', $code);
        }
        return $j;
    }

    /** Βήματα 1–7 του reader: εργασία + μηνύματα (αποτυχία = στοπ), χρήστες (αποτυχία = προειδοποίηση). */
    public static function retrieve($ref, $token)
    {
        $taskId = self::extractTaskId($ref);
        $token = trim((string) $token);
        if ($token === '') {
            throw new GoodDayError('Δεν έχει οριστεί token GoodDay — Ρυθμίσεις → Διασυνδέσεις → GoodDay.', -1);   // -1 = ρύθμιση, όχι δίκτυο
        }
        $path = '/task/' . rawurlencode($taskId);
        $details = self::get($path, $token);
        if (!is_array($details) || (string) ($details['id'] ?? '') !== $taskId) {
            throw new GoodDayError('Το GoodDay δεν επέστρεψε την εργασία ' . $taskId . ' (κενή απάντηση — συνήθως ο λογαριασμός του token δεν είναι μέλος του έργου).', 0);
        }
        $messages = self::get($path . '/messages', $token);
        if (!is_array($messages) || ($messages && array_keys($messages) !== range(0, count($messages) - 1))) {
            throw new GoodDayError('Το GoodDay δεν επέστρεψε λίστα μηνυμάτων.', 0);
        }
        foreach ($messages as $m) {
            if (!is_array($m)) { throw new GoodDayError('Μη αναμενόμενη μορφή μηνύματος GoodDay.', 0); }
        }
        $ref = [];
        foreach (['assignedToUserId', 'createdByUserId', 'actionRequiredUserId'] as $k) {
            if (!empty($details[$k])) { $ref[(string) $details[$k]] = 1; }
        }
        foreach ($messages as $m) {
            foreach (['fromUserId', 'toUserId', 'editByUserId'] as $k) {
                if (!empty($m[$k])) { $ref[(string) $m[$k]] = 1; }
            }
        }
        $ids = array_keys($ref);
        sort($ids, SORT_STRING);
        $users = [];
        $warnings = [];
        foreach ($ids as $uid) {
            $uid = (string) $uid;
            $idn = ['external_id' => $uid, 'name' => null, 'email' => null];
            try {
                $u = self::$userCache[$uid] ?? (self::$userCache[$uid] = self::get('/user/' . rawurlencode($uid), $token));
                if (!is_array($u) || (string) ($u['id'] ?? '') !== $uid) {
                    throw new GoodDayError('Μη αναμενόμενη απάντηση χρήστη GoodDay.', 0);
                }
                $idn['name'] = isset($u['name']) && is_string($u['name']) ? $u['name'] : null;
                $em = $u['primaryEmail'] ?? null;
                $idn['email'] = is_string($em) && trim($em) !== '' ? trim($em) : null;
                if ($idn['email'] === null) {
                    $warnings[] = ['code' => 'user_email_missing', 'user_id' => $uid,
                        'message' => 'Το GoodDay δεν έδωσε email — χρειάζεται χειροκίνητη αντιστοίχιση.'];
                }
            } catch (GoodDayError $e) {
                $warnings[] = ['code' => 'user_lookup_failed', 'user_id' => $uid,
                    'message' => $e->getMessage(), 'http_status' => $e->status ?: null];
            }
            $users[$uid] = $idn;
        }
        return [
            'schema_version' => self::SCHEMA,
            'source' => self::SOURCE,
            'external_task_id' => $taskId,
            'source_url' => 'https://www.goodday.work/t/' . $taskId,
            'retrieved_at' => gmdate('Y-m-d\TH:i:s') . '+00:00',
            'task' => $details,
            'messages' => $messages,
            'users' => $users,
            'warnings' => $warnings,
        ];
    }

    /**
     * Όλες οι υποεργασίες μιας εργασίας GoodDay (και οι δικές τους, αν έχουν), ΠΛΗΡΕΙΣ
     * — εργασία + μηνύματα + χρήστες, ίδιος φάκελος με την κύρια. Εδώ υπάρχει ΕΝΑ επίπεδο
     * υποεργασιών, οπότε το δέντρο ισιώνει κάτω από την κύρια (με σημείωση στον τίτλο του
     * «γονιού» τους). Αποτυχία ανάκτησης έστω μίας = στοπ: δεν μεταφέρουμε μισή πληροφορία.
     * @return array [['env' => φάκελος, 'via' => τίτλος ενδιάμεσης υποεργασίας|null], ...]
     */
    public static function retrieveSubtasks(array $parentEnv, $token)
    {
        $queue = [];
        foreach ((array) ($parentEnv['task']['subtasks'] ?? []) as $sid) { $queue[] = [(string) $sid, null]; }
        $seen = [(string) $parentEnv['external_task_id'] => 1];
        $out = [];
        while ($queue) {
            [$sid, $via] = array_shift($queue);
            if ($sid === '' || isset($seen[$sid])) { continue; }
            $seen[$sid] = 1;
            if (count($out) >= self::MAX_SUBS) {
                throw new GoodDayError('Περισσότερες από ' . self::MAX_SUBS . ' υποεργασίες — δεν γίνεται εισαγωγή με τη μία. Πες μας να το δούμε.', 0);
            }
            try {
                $env = self::retrieve($sid, $token);
            } catch (GoodDayError $e) {
                throw new GoodDayError('Υποεργασία ' . $sid . ': ' . $e->getMessage(), $e->status);
            }
            $out[] = ['env' => $env, 'via' => $via];
            foreach ((array) ($env['task']['subtasks'] ?? []) as $sub2) {
                $queue[] = [(string) $sub2, (string) ($env['task']['name'] ?? $sid)];
            }
        }
        return $out;
    }

    /**
     * Κατάσταση υποεργασίας εδώ (απόφαση 9/10/2026): κλειστή στο GoodDay (momentClosed)
     * → «Ολοκληρώθηκε» (ή «Ακυρωμένο» αν λέγεται cancel), αλλιώς η πρώτη κατάσταση.
     * Επαληθευμένο: Completed ↔ systemStatus 5 + momentClosed· Not started ↔ 1 + null.
     */
    public static function subStatus(array $task)
    {
        $closed = !empty($task['momentClosed']);
        $name = (string) ($task['status']['name'] ?? '');
        if ($closed && preg_match('/cancel/i', $name)) {
            $c = (int) Capsule::table('mod_cpm_statuses')->where('phase', 'cancel')->orderBy('sort')->value('id');
            if ($c) { return ['id' => $c, 'closed' => true]; }
        }
        if ($closed) { return ['id' => Db::doneStatusId(), 'closed' => true]; }
        return ['id' => Db::firstStatusId(), 'closed' => false];
    }

    /** Σύνοψη υποεργασίας για την προεπισκόπηση (χωρίς να γραφτεί τίποτα). */
    public static function subSummary(array $sub, array $plan)
    {
        $env = $sub['env'];
        $st = self::subStatus((array) $env['task']);
        $ex = self::existing((string) $env['external_task_id']);
        $al = $ex ? null : self::alreadyHere((string) $env['external_task_id'], $env)['block'];
        $stRow = Db::status($st['id']);
        return ['ext' => (string) $env['external_task_id'], 'title' => $plan['fields']['title'], 'via' => $sub['via'],
            'srcStatus' => $plan['sourceStatus']['name'], 'status' => $stRow ? (string) $stRow->title : '', 'closed' => $st['closed'],
            'assignee' => $plan['assignee'], 'msgs' => count(array_filter($plan['messages'], function ($m) { return $m['kind'] === 'text'; })),
            'skip' => $ex ? 'έχει ήδη εισαχθεί ως #' . $ex['task'] : ($al ? $al['why'] : null),
            'skipTask' => $ex ? $ex['task'] : ($al['task'] ?? null)];
    }

    /**
     * Μία υποεργασία → υποεργασία της $parent εδώ, με το δικό της ιστορικό.
     * Ήδη εισηγμένη / ήδη ανοιγμένη εδώ → παραλείπεται (δεν διπλασιάζουμε).
     * Καλείται ΜΕΣΑ στο transaction του καλούντα.
     * @return array ['id' => int] ή ['skipped' => λόγος]
     */
    public static function createChild($parent, array $sub, $adminId, callable $clean, callable $adminName, $tzSetting)
    {
        $env = $sub['env'];
        $ext = (string) $env['external_task_id'];
        if ($ex = self::existing($ext)) { return ['skipped' => 'έχει ήδη εισαχθεί ως #' . $ex['task'], 'ext' => $ext]; }
        $al = self::alreadyHere($ext, $env)['block'];
        if ($al) { return ['skipped' => $al['why'], 'ext' => $ext]; }
        $plan = self::plan($env, $clean, $adminName, $tzSetting);
        $st = self::subStatus((array) $env['task']);
        $f = $plan['fields'];
        $title = $f['title'] !== '' ? $f['title'] : 'Υποεργασία GoodDay ' . $ext;
        $row = ['project_id' => $parent->project_id ?: null, 'dept_id' => $parent->dept_id ?: null,
            'internal' => (int) $parent->internal, 'parent_id' => (int) $parent->id,
            'title' => self::txt($title, 200), 'status_id' => $st['id'], 'action_user' => null,
            'due_date' => $f['due'], 'start_date' => $f['start'], 'estimate_minutes' => $f['estimate']];
        if ($plan['assignee'] && $plan['assignee']['admin']) { $row['assignee'] = (int) $plan['assignee']['admin']; }
        if ($st['closed']) {
            $row['completed_at'] = self::toLocal($env['task']['momentClosed'] ?? null, self::sourceTz($tzSetting)) ?: date('Y-m-d H:i:s');
            $row['completed_by'] = (int) $adminId;
            $row['completed_note'] = 'Ολοκληρώθηκε στο GoodDay (' . self::txt((string) ($env['task']['status']['name'] ?? ''), 60) . ')';
        }
        $tid = Db::saveTask(0, $row, $adminId);
        $impId = self::persist($tid, $adminId, $env, $plan);
        Db::logActivity($tid, $adminId, 'create', 'Εισαγωγή από GoodDay ' . $ext . ' ως υποεργασία της #' . (int) $parent->id
            . ($sub['via'] ? ' (στο GoodDay ήταν κάτω από «' . self::txt($sub['via'], 80) . '»)' : '')
            . ' · ' . count($plan['messages']) . ' μηνύματα ιστορικού');
        return ['id' => $tid, 'ext' => $ext, 'imp' => $impId];
    }

    /** Όλες οι εισαγωγές μιας εργασίας ΚΑΙ των υποεργασιών της (για τα αρχεία). */
    public static function importIdsOfTree($taskId)
    {
        $ids = array_merge([(int) $taskId], array_map('intval', Capsule::table('mod_cpm_tasks')->where('parent_id', (int) $taskId)->pluck('id')->all()));
        return array_map('intval', Capsule::table('mod_cpm_ext_imports')->whereIn('task_id', $ids)->pluck('id')->all());
    }

    /* ════════════════ Ώρες ════════════════ */

    /** Η ζώνη στην οποία ΓΡΑΦΕΙ το GoodDay ώρες χωρίς offset (ρύθμιση· προεπιλογή UTC — βλ. κεφαλίδα). */
    public static function sourceTz($setting)
    {
        $tz = trim((string) $setting) ?: 'UTC';
        try { new \DateTimeZone($tz); } catch (\Throwable $e) { $tz = 'UTC'; }
        return $tz;
    }

    /** Πηγή → τοπική ώρα 'Y-m-d H:i:s'. Με offset: σεβόμαστε το offset. Χωρίς: η ρητή ζώνη πηγής. */
    public static function toLocal($src, $srcTz)
    {
        if (!is_string($src) || trim($src) === '') { return null; }
        $s = trim($src);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d{1,9})?)?)?(Z|[+-]\d{2}:?\d{2})?$/', $s)) { return null; }
        $s = preg_replace('/(\.\d{6})\d+/', '$1', $s);          // > μικροδευτερόλεπτα δεν τα διαβάζει η PHP
        try {
            $hasOff = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $s);
            $dt = $hasOff ? new \DateTime($s) : new \DateTime($s, new \DateTimeZone($srcTz));
            $dt->setTimezone(new \DateTimeZone(self::LOCAL_TZ));
            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Ημερομηνία (χωρίς ώρα) — μόνο αν είναι έγκυρη. */
    public static function dateOnly($v)
    {
        if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) { return null; }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $m[1] . '-' . $m[2] . '-' . $m[3] : null;
    }

    /* ════════════════ Χρήστες ════════════════ */

    /**
     * email GoodDay → συνάδελφος. Ίδιος κανόνας σύγκρισης με την υπόλοιπη εφαρμογή
     * (trim + πεζά), μόνο ΕΝΕΡΓΟΙ χρήστες. Ένας → αντιστοίχιση. Κανείς/πολλοί → τίποτα.
     * @return array uid => ['admin'=>id|null, 'state'=>matched|unknown|ambiguous|no_email|lookup_failed, ...]
     */
    public static function matchUsers(array $users, array $warnings)
    {
        $byEmail = [];
        foreach (Capsule::table('tbladmins')->where('disabled', 0)->get(['id', 'email', 'firstname', 'lastname']) as $a) {
            $k = mb_strtolower(trim((string) $a->email));
            if ($k !== '') { $byEmail[$k][] = (int) $a->id; }
        }
        $failed = [];
        foreach ($warnings as $w) {
            if (($w['code'] ?? '') === 'user_lookup_failed') { $failed[(string) $w['user_id']] = 1; }
        }
        $out = [];
        foreach ($users as $uid => $u) {
            $uid = (string) $uid;
            $email = isset($u['email']) && is_string($u['email']) ? trim($u['email']) : '';
            $r = ['ext' => $uid, 'name' => isset($u['name']) ? (string) $u['name'] : null,
                'email' => $email !== '' ? $email : null, 'admin' => null, 'state' => 'unknown'];
            if (isset($failed[$uid])) {
                $r['state'] = 'lookup_failed';
            } elseif ($email === '') {
                $r['state'] = 'no_email';
            } else {
                $hits = $byEmail[mb_strtolower($email)] ?? [];
                if (count($hits) === 1) { $r['admin'] = $hits[0]; $r['state'] = 'matched'; }
                elseif (count($hits) > 1) { $r['state'] = 'ambiguous'; }
            }
            $out[$uid] = $r;
        }
        return $out;
    }

    /* ════════════════ Rich text (Lexical του GoodDay) → δικό μας HTML ════════════════ */

    /**
     * Ντετερμινιστική μετατροπή. Δεν «μεταφυτεύουμε» το JSON: κάθε κόμβος γίνεται
     * ρητά ένα από τα στοιχεία που δέχεται ο editor μας (p, br, b, i, u, s, code,
     * mark, a, ul/ol/li, h3/h4, blockquote, pre). Άγνωστος κόμβος → κρατάμε το
     * αναγνώσιμο κείμενο των παιδιών του και γράφουμε προειδοποίηση.
     * Το αποτέλεσμα περνά ΚΑΙ από το cnp_clean_html πριν αποθηκευτεί.
     *
     * Επαληθευμένα στο πραγματικό δείγμα (6lyyHw): root, custom-paragraph, text
     * (format 0/1/16), linebreak, link, autolink, list (ol/ul, φωλιασμένες), listitem, mention.
     */
    public static function lexicalToHtml($rtf, callable $mention, array &$warn)
    {
        if (!is_array($rtf)) { return null; }
        $root = $rtf['content']['root'] ?? null;
        if (!is_array($root)) { $warn[] = 'rtf_no_root'; return null; }
        if (($rtf['v'] ?? '') !== 'lexical-1') { $warn[] = 'rtf_version:' . substr((string) ($rtf['v'] ?? '?'), 0, 20); }
        $html = self::lexNode($root, $mention, $warn, 0);
        return trim($html) === '' ? null : $html;
    }

    private static function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    private static function lexKids($n, callable $mention, array &$warn, $depth)
    {
        $o = '';
        foreach ((array) ($n['children'] ?? []) as $c) {
            if (is_array($c)) { $o .= self::lexNode($c, $mention, $warn, $depth + 1); }
        }
        return $o;
    }

    private static function safeUrl($u)
    {
        $u = trim((string) $u);
        if ($u === '' || mb_strlen($u) > 2000 || preg_match('/[\x00-\x20\x7f]/', $u)) { return null; }
        if (!preg_match('#^(https?://[^\s/$.?\#].\S*|mailto:[^\s@]+@[^\s@]+|tel:[0-9+()\-. ]+)$#i', $u)) { return null; }
        return $u;
    }

    private static function lexNode(array $n, callable $mention, array &$warn, $depth)
    {
        if ($depth > 60) { $warn[] = 'rtf_too_deep'; return ''; }
        $type = (string) ($n['type'] ?? '');
        switch ($type) {
            case 'root':
                return self::lexKids($n, $mention, $warn, $depth);
            case 'paragraph':
            case 'custom-paragraph':
                $in = self::lexKids($n, $mention, $warn, $depth);
                return '<p>' . (trim(strip_tags($in, '<br>')) === '' && strpos($in, '<br') === false ? '<br>' : $in) . '</p>';
            case 'heading':
                $tag = in_array(($n['tag'] ?? ''), ['h1', 'h2', 'h3'], true) ? 'h3' : 'h4';
                return '<' . $tag . '>' . self::lexKids($n, $mention, $warn, $depth) . '</' . $tag . '>';
            case 'quote':
                return '<blockquote>' . self::lexKids($n, $mention, $warn, $depth) . '</blockquote>';
            case 'code':
                return '<pre>' . self::lexKids($n, $mention, $warn, $depth) . '</pre>';
            case 'linebreak':
                return '<br>';
            case 'tab':
                return ' ';
            case 'text':
                $t = self::e($n['text'] ?? '');
                $f = (int) ($n['format'] ?? 0);
                /* Bitmask του Lexical: 1 έντονα, 2 πλάγια, 4 διαγράμμιση, 8 υπογράμμιση,
                   16 κώδικας, 128 επισήμανση. Δείκτης/εκθέτης (32/64) → απλό κείμενο. */
                if ($f & 16) { $t = '<code>' . $t . '</code>'; }
                if ($f & 128) { $t = '<mark>' . $t . '</mark>'; }
                if ($f & 8) { $t = '<u>' . $t . '</u>'; }
                if ($f & 4) { $t = '<s>' . $t . '</s>'; }
                if ($f & 2) { $t = '<i>' . $t . '</i>'; }
                if ($f & 1) { $t = '<b>' . $t . '</b>'; }
                if ($f & ~(1 | 2 | 4 | 8 | 16 | 32 | 64 | 128)) { $warn[] = 'text_format:' . $f; }
                return $t;
            case 'link':
            case 'autolink':
                $inner = self::lexKids($n, $mention, $warn, $depth);
                $u = self::safeUrl($n['url'] ?? '');
                if ($u === null) { $warn[] = 'link_dropped'; return $inner; }
                return '<a href="' . self::e($u) . '">' . ($inner !== '' ? $inner : self::e($u)) . '</a>';
            case 'list':
                $tag = (($n['listType'] ?? '') === 'number' || ($n['tag'] ?? '') === 'ol') ? 'ol' : 'ul';
                if (($n['listType'] ?? '') === 'check') { $warn[] = 'checklist_as_bullets'; }
                $st = (int) ($n['start'] ?? 1);
                $sa = ($tag === 'ol' && $st > 1 && $st < 10000) ? ' start="' . $st . '"' : '';
                return '<' . $tag . $sa . '>' . self::lexKids($n, $mention, $warn, $depth) . '</' . $tag . '>';
            case 'listitem':
                $in = self::lexKids($n, $mention, $warn, $depth);
                if (isset($n['checked']) && $n['checked'] !== null) { $in = ($n['checked'] ? '☑ ' : '☐ ') . $in; }
                /* Κενό στοιχείο (υπάρχει στο 6lyyHw: «5.» χωρίς κείμενο) — κρατά τη θέση του στην αρίθμηση. */
                return '<li>' . ($in === '' ? '<br>' : $in) . '</li>';
            case 'mention':
                $md = (array) ($n['mentionData'] ?? []);
                $mid = (string) ($md['id'] ?? ($md['data']['id'] ?? ''));
                $mname = (string) ($md['name'] ?? ($md['data']['name'] ?? ($n['text'] ?? '')));
                $who = $mention($mid, $mname);              // όνομα συναδέλφου αν αντιστοιχίστηκε, αλλιώς null
                return '<b>@' . self::e($who !== null ? $who : ($mname !== '' ? $mname : 'άγνωστος')) . '</b>';
            default:
                $warn[] = 'unknown_node:' . substr($type ?: '?', 0, 30);
                if (!empty($n['children'])) { return self::lexKids($n, $mention, $warn, $depth); }
                if (isset($n['text']) && is_string($n['text'])) { return self::e($n['text']); }
                return '';
        }
    }

    /** Το «message» του GoodDay έρχεται με HTML entities (&amp;) — ξεκλειδώνουμε και ξαναγράφουμε ασφαλώς. */
    public static function plainToHtml($msg)
    {
        $t = html_entity_decode((string) $msg, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = trim(str_replace(["\r\n", "\r"], "\n", $t));
        if ($t === '') { return null; }
        $paras = preg_split('/\n{2,}/', $t);
        return implode('', array_map(function ($p) {
            return '<p>' . nl2br(self::e($p), false) . '</p>';
        }, $paras));
    }

    /* ════════════════ Σχέδιο εισαγωγής (κοινό για προεπισκόπηση και δημιουργία) ════════════════ */

    /**
     * Από τον φάκελο → ό,τι θα δείξει η προεπισκόπηση και ό,τι θα γραφτεί.
     * $clean = cnp_clean_html (τελικό φίλτρο της εφαρμογής). Ντετερμινιστικό: ίδιος φάκελος → ίδιο αποτέλεσμα.
     */
    public static function plan(array $env, callable $clean, callable $adminName, $srcTzSetting)
    {
        $srcTz = self::sourceTz($srcTzSetting);
        $task = (array) ($env['task'] ?? []);
        $umap = self::matchUsers((array) ($env['users'] ?? []), (array) ($env['warnings'] ?? []));
        $who = function ($uid) use ($umap, $adminName) {
            $uid = (string) $uid;
            if ($uid === '') { return null; }
            $u = $umap[$uid] ?? ['ext' => $uid, 'name' => null, 'email' => null, 'admin' => null, 'state' => 'unknown'];
            return ['ext' => $uid, 'name' => $u['name'] ?: ('GoodDay ' . $uid), 'email' => $u['email'],
                'admin' => $u['admin'], 'adminName' => $u['admin'] ? $adminName($u['admin']) : null, 'state' => $u['state']];
        };
        $mentionFn = function ($mid, $mname) use ($umap, $adminName) {
            $u = $umap[(string) $mid] ?? null;
            return ($u && $u['admin']) ? $adminName($u['admin']) : null;
        };

        $msgs = [];
        $convWarn = [];
        $lastAt = null;
        foreach (array_values((array) ($env['messages'] ?? [])) as $i => $m) {
            if (!is_array($m)) { continue; }
            $mid = (string) ($m['id'] ?? '');
            $flags = [];
            if ($mid === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $mid)) { $mid = 'idx' . $i; $flags[] = 'no_id'; }
            $w = [];
            $html = null;
            if (!empty($m['messageRTF'])) {
                $html = self::lexicalToHtml($m['messageRTF'], $mentionFn, $w);
                if ($html !== null) { $html = $clean($html); if (trim(strip_tags($html)) === '') { $html = null; } }
                if ($html === null && trim((string) ($m['message'] ?? '')) !== '') { $flags[] = 'fallback_plain'; }
            }
            if ($html === null) {
                $p = self::plainToHtml($m['message'] ?? '');
                $html = $p !== null ? $clean($p) : null;
            }
            foreach (array_unique($w) as $x) { $flags[] = $x; $convWarn[] = ['code' => 'rtf', 'message_id' => $mid, 'detail' => $x]; }
            $kind = $html !== null ? 'text' : (!empty($m['timeReportId']) ? 'time' : (!empty($m['taskStatusId']) ? 'status' : 'empty'));
            if ($kind === 'empty') { $flags[] = 'empty'; }
            $atSrc = isset($m['dateCreated']) && is_string($m['dateCreated']) ? $m['dateCreated'] : null;
            $at = self::toLocal($atSrc, $srcTz);
            if ($atSrc !== null && $at === null) { $flags[] = 'bad_date'; }
            $editSrc = isset($m['editDate']) && is_string($m['editDate']) ? $m['editDate'] : null;
            $att = $m['attachments'] ?? null;
            $msgs[] = ['ext' => $mid, 'idx' => $i,
                /* Σταθερή σειρά: ώρα (χωρίς ώρα → η ώρα του προηγούμενου) και μετά η αρχική θέση. */
                'sortAt' => $at ?: $lastAt ?: '0000-00-00 00:00:00',
                'kind' => $kind, 'html' => $html,
                'from' => $who($m['fromUserId'] ?? ''), 'to' => $who($m['toUserId'] ?? ''),
                'at' => $at, 'atSrc' => $atSrc,
                'editAt' => self::toLocal($editSrc, $srcTz), 'editSrc' => $editSrc,
                'editBy' => $who($m['editByUserId'] ?? ''),
                'statusExt' => !empty($m['taskStatusId']) ? (string) $m['taskStatusId'] : null,
                'timeReport' => !empty($m['timeReportId']) ? (string) $m['timeReportId'] : null,
                'attachments' => is_array($att) && $att ? $att : null,
                'flags' => array_values(array_unique($flags))];
            if ($at) { $lastAt = $at; }
        }
        usort($msgs, function ($a, $b) { return strcmp($a['sortAt'], $b['sortAt']) ?: ($a['idx'] - $b['idx']); });

        $st = (array) ($task['status'] ?? []);
        $est = $task['estimate'] ?? null;
        $prog = $task['progress'] ?? null;
        /* Χαρτογράφηση πεδίων: ΜΟΝΟ όσα έχουν σαφές αντίστοιχο σε μας. Τα υπόλοιπα
           μένουν αυτούσια στον φάκελο + στο «meta.unmapped» — δεν αντιγράφονται ids. */
        $fields = [
            'title' => mb_substr(trim(html_entity_decode((string) ($task['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 200),
            'due' => self::dateOnly($task['deadline'] ?? null),
            'start' => self::dateOnly($task['startDate'] ?? null),
            /* estimate του GoodDay = λεπτά (επαληθευμένο: 660 ↔ «Εκτίμηση υλοποίησης 11 ώρες» στο 6lyyHw). */
            'estimate' => is_numeric($est) && $est >= 0 && $est <= 1000000 ? (int) $est : null,
        ];
        $unmapped = [
            'endDate' => $task['endDate'] ?? null, 'scheduleDate' => $task['scheduleDate'] ?? null,
            'progress' => is_numeric($prog) ? (float) $prog : null, 'priority' => $task['priority'] ?? null,
            'storyPoints' => $task['storyPoints'] ?? null, 'reportedTime' => $task['reportedTime'] ?? null,
            'taskType' => $task['taskType'] ?? null, 'projectId' => $task['projectId'] ?? null,
            'parentTaskId' => $task['parentTaskId'] ?? null, 'subtasks' => $task['subtasks'] ?? null,
            'customFieldsData' => $task['customFieldsData'] ?? null, 'shortId' => $task['shortId'] ?? null,
            'momentCreated' => $task['momentCreated'] ?? null, 'momentClosed' => $task['momentClosed'] ?? null,
        ];
        $warnings = [];
        foreach ((array) ($env['warnings'] ?? []) as $w) { $warnings[] = $w; }
        foreach ($umap as $u) {
            if ($u['state'] !== 'matched') {
                $warnings[] = ['code' => 'user_' . $u['state'], 'user_id' => $u['ext'],
                    'message' => ($u['name'] ?: $u['ext']) . ($u['email'] ? ' <' . $u['email'] . '>' : '') . ' — '
                        . ['unknown' => 'δεν υπάρχει ενεργός συνάδελφος με αυτό το email',
                           'ambiguous' => 'το email αντιστοιχεί σε περισσότερους από έναν συναδέλφους',
                           'no_email' => 'χωρίς email από το GoodDay',
                           'lookup_failed' => 'δεν διαβάστηκε από το GoodDay'][$u['state']]
                        . ' · τα μηνύματά του μένουν με το όνομα του GoodDay'];
            }
        }
        foreach ($convWarn as $w) { $warnings[] = $w; }
        if (!empty($task['subtasks'])) {
            $warnings[] = ['code' => 'subtasks_not_imported', 'message' => count((array) $task['subtasks'])
                . ' υποεργασία(ες) στο GoodDay ΔΕΝ εισάγονται (ούτε το ιστορικό τους).'];
        }
        $attN = 0;
        foreach ($msgs as $m) { if ($m['attachments']) { $attN++; } }
        if ($attN) {
            $warnings[] = ['code' => 'attachments_metadata_only', 'message' => $attN . ' μήνυμα(τα) έχουν συνημμένα — κρατάμε μόνο τα στοιχεία τους, τα αρχεία δεν κατεβαίνουν.'];
        }
        return ['ext' => (string) ($env['external_task_id'] ?? ''), 'url' => (string) ($env['source_url'] ?? ''),
            'retrievedAt' => (string) ($env['retrieved_at'] ?? ''),
            'srcTz' => $srcTz, 'fields' => $fields,
            'sourceStatus' => ['id' => isset($st['id']) ? (string) $st['id'] : null, 'name' => isset($st['name']) ? (string) $st['name'] : null],
            'assignee' => $who($task['assignedToUserId'] ?? ''), 'creator' => $who($task['createdByUserId'] ?? ''),
            'actionRequired' => $who($task['actionRequiredUserId'] ?? ''),
            'created' => self::toLocal($task['momentCreated'] ?? null, $srcTz),
            'descrSource' => isset($task['description']) && is_string($task['description']) ? 'description' : null,
            'descr' => isset($task['description']) && is_string($task['description']) ? $clean((string) self::plainToHtml($task['description'])) : null,
            'unmapped' => $unmapped, 'users' => array_values($umap),
            'messages' => $msgs, 'warnings' => $warnings];
    }

    /** Υπάρχει ήδη εισαγωγή της ίδιας εργασίας; → η εργασία μας (αν δεν έχει σβηστεί). */
    public static function existing($extTaskId)
    {
        self::ensure();
        $r = Capsule::table('mod_cpm_ext_imports')->where('integration', self::INTEGRATION)
            ->where('source', self::SOURCE)->where('ext_task_id', (string) $extTaskId)->first();
        if (!$r) { return null; }
        $t = $r->task_id ? Capsule::table('mod_cpm_tasks')->where('id', $r->task_id)->first(['id', 'title']) : null;
        if (!$t) {
            /* Η εργασία σβήστηκε μετά την εισαγωγή → η εισαγωγή δεν «κλειδώνει» πια τίποτα. */
            self::forgetTask((int) $r->task_id);
            Capsule::table('mod_cpm_ext_imports')->where('id', $r->id)->delete();
            return null;
        }
        return ['task' => (int) $t->id, 'title' => (string) $t->title, 'at' => $r->created_at];
    }

    /**
     * «Ό,τι έχει ανοιχτεί ξανά, μην το ξαναπερνάς» (απόφαση 9/10/2026).
     * Πριν από αυτή τη λειτουργία οι εργασίες μεταφέρθηκαν ΧΕΙΡΟΚΙΝΗΤΑ, και το σύστημα
     * δεν το ήξερε. Αναγνωρίζουμε την ήδη ανοιγμένη από (με σειρά βεβαιότητας):
     *  1. εργασία μας με τον σύνδεσμο /t/{id} στο ΖΗΤΟΥΜΕΝΟ (έτσι γίνονταν οι μεταφορές — π.χ. #121),
     *  2. task του GoodDay που ήταν καθρέφτης ticket (παλιός συγχρονισμός) — το ticket υπάρχει ήδη εδώ,
     *  3. μήνυμα στο GoodDay «Μεταφέρθηκε στο N» με N υπαρκτή εργασία μας,
     *  4. κατάσταση στο GoodDay «TRANSFERRED…».
     * Οποιοδήποτε → ΔΕΝ εισάγεται. Σύνδεσμος μόνο μέσα σε ενέργεια (όχι στο ζητούμενο) =
     * απλή αναφορά, γίνεται προειδοποίηση — εκεί συχνά παραπέμπουν σε σχετική, όχι την ίδια.
     * @return array ['block' => null|[...], 'mentions' => [[task, title], ...]]
     */
    public static function alreadyHere($extTaskId, array $env = null)
    {
        $ext = (string) $extTaskId;
        $like = '%goodday.work/t/' . str_replace(['%', '_'], ['\%', '\_'], $ext) . '%';
        $exact = function ($text) use ($ext) {     // ακριβές id, όχι πρόθεμα άλλου (abc ≠ abcd)
            return (bool) preg_match('#goodday\.work/t/' . preg_quote($ext, '#') . '(?![A-Za-z0-9_-])#', (string) $text);
        };
        foreach (Capsule::table('mod_cpm_tasks')->where('descr', 'like', $like)->orderBy('id')->get(['id', 'title', 'descr']) as $t) {
            if ($exact($t->descr)) {
                return ['block' => ['kind' => 'descr', 'task' => (int) $t->id, 'title' => (string) $t->title,
                    'why' => 'Έχει ήδη ανοιχτεί εδώ ως #' . (int) $t->id . ' (ο σύνδεσμος GoodDay είναι στο ζητούμενό της).'], 'mentions' => []];
            }
        }
        if (Capsule::schema()->hasTable('mod_gooddaysync_tickets')) {
            $g = Capsule::table('mod_gooddaysync_tickets')->where('task_id', $ext)->first(['ticketid', 'tid']);
            if ($g && Capsule::table('tbltickets')->where('id', (int) $g->ticketid)->exists()) {
                $pt = Capsule::table('mod_cpm_tasks')->where('ticketid', (int) $g->ticketid)->orderBy('id')->first(['id', 'title']);
                return ['block' => ['kind' => 'ticket', 'task' => $pt ? (int) $pt->id : null, 'title' => $pt ? (string) $pt->title : '',
                    'ticket' => (int) $g->ticketid, 'tid' => (string) $g->tid,
                    'why' => 'Αυτό το task του GoodDay ήταν αντίγραφο του ticket #' . $g->tid . ', που υπάρχει ήδη εδώ'
                        . ($pt ? ' (και έχει εργασία #' . (int) $pt->id . ').' : '.')], 'mentions' => []];
            }
        }
        if ($env) {
            foreach ((array) ($env['messages'] ?? []) as $m) {
                $txt = html_entity_decode((string) ($m['message'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match('/Μεταφέρθηκε\s+στο\s+(?:task\s+)?#?(\d{1,7})\b/iu', $txt, $mm)) {
                    $t = Capsule::table('mod_cpm_tasks')->where('id', (int) $mm[1])->first(['id', 'title']);
                    if ($t) {
                        return ['block' => ['kind' => 'message', 'task' => (int) $t->id, 'title' => (string) $t->title,
                            'why' => 'Στο GoodDay γράφει «Μεταφέρθηκε στο ' . (int) $t->id . '» — υπάρχει εδώ ως #' . (int) $t->id . '.'], 'mentions' => []];
                    }
                }
            }
            $stn = (string) ($env['task']['status']['name'] ?? '');
            if (preg_match('/transferred/i', $stn)) {
                return ['block' => ['kind' => 'status', 'task' => null, 'title' => '',
                    'why' => 'Στο GoodDay είναι σημειωμένη «' . $stn . '» — έχει ήδη μεταφερθεί. Βρες την εδώ με αναζήτηση τίτλου.'], 'mentions' => []];
            }
        }
        $mentions = [];
        foreach (Capsule::table('mod_cpm_checklist as c')->join('mod_cpm_tasks as t', 't.id', '=', 'c.task_id')
                     ->where('c.title', 'like', $like)->orderBy('t.id')->get(['t.id', 't.title', 'c.title as body']) as $r) {
            if ($exact($r->body) && !isset($mentions[(int) $r->id])) { $mentions[(int) $r->id] = ['task' => (int) $r->id, 'title' => (string) $r->title]; }
        }
        return ['block' => null, 'mentions' => array_values($mentions)];
    }

    /* ════════════════ Αποθήκευση ════════════════ */

    /** Φάκελος → JSON ASCII-safe (η σύνδεση της βάσης είναι utf8mb3: καμία 4-byte ακολουθία δεν περνά ωμή). */
    public static function json($v)
    {
        return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    public static function stagePut($adminId, array $env, array $subs = [])
    {
        self::ensure();
        Capsule::table('mod_cpm_ext_stage')->where('created_at', '<', date('Y-m-d H:i:s', time() - self::STAGE_TTL))->delete();
        $nonce = bin2hex(random_bytes(16));
        Capsule::table('mod_cpm_ext_stage')->insert(['nonce' => $nonce, 'admin_id' => (int) $adminId,
            'ext_task_id' => (string) $env['external_task_id'],
            'envelope' => self::json($subs ? ['__main' => $env, '__subs' => $subs] : $env),
            'created_at' => date('Y-m-d H:i:s')]);
        return $nonce;
    }

    /** Μόνο ο ίδιος χρήστης, μόνο φρέσκια προεπισκόπηση. */
    public static function stageGet($adminId, $nonce)
    {
        self::ensure();
        if (!preg_match('/^[a-f0-9]{32}$/', (string) $nonce)) { return null; }
        $r = Capsule::table('mod_cpm_ext_stage')->where('nonce', $nonce)->where('admin_id', (int) $adminId)
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - self::STAGE_TTL))->first();
        if (!$r) { return null; }
        $env = json_decode((string) $r->envelope, true);
        if (!is_array($env)) { return null; }
        if (isset($env['__main'])) { return ['row' => $r, 'env' => $env['__main'], 'subs' => (array) $env['__subs']]; }
        return ['row' => $r, 'env' => $env, 'subs' => []];
    }

    public static function stageDrop($nonce)
    {
        Capsule::table('mod_cpm_ext_stage')->where('nonce', (string) $nonce)->delete();
    }

    /** Τίτλος/όνομα χωρίς 4-byte χαρακτήρες (utf8mb3). */
    public static function txt($s, $max)
    {
        $s = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string) $s);
        return mb_substr($s, 0, $max);
    }

    /** Γράφει import + ιστορικό. Καλείται ΜΕΣΑ σε transaction από το api.php. insertOrIgnore = επανάληψη χωρίς διπλά. */
    public static function persist($taskId, $adminId, array $env, array $plan)
    {
        $impId = (int) Capsule::table('mod_cpm_ext_imports')->insertGetId([
            'integration' => self::INTEGRATION, 'source' => self::SOURCE,
            'ext_task_id' => (string) $env['external_task_id'], 'task_id' => (int) $taskId,
            'source_url' => self::txt($env['source_url'] ?? '', 255),
            'envelope' => self::json($env),
            'meta' => self::json(['sourceStatus' => $plan['sourceStatus'], 'srcTz' => $plan['srcTz'],
                'unmapped' => $plan['unmapped'], 'users' => $plan['users'],
                'assignee' => $plan['assignee'], 'creator' => $plan['creator'], 'created' => $plan['created']]),
            'warnings' => self::json($plan['warnings']),
            'imported_by' => (int) $adminId, 'created_at' => date('Y-m-d H:i:s')]);
        $seq = 0;
        foreach ($plan['messages'] as $m) {
            Capsule::table('mod_cpm_ext_msgs')->insertOrIgnore([
                'import_id' => $impId, 'task_id' => (int) $taskId, 'ext_msg_id' => $m['ext'], 'seq' => ++$seq,
                'kind' => $m['kind'],
                'author_ext' => $m['from'] ? self::txt($m['from']['ext'], 64) : null,
                'author_name' => $m['from'] ? self::txt($m['from']['name'], 190) : null,
                'author_email' => $m['from'] && $m['from']['email'] ? self::txt($m['from']['email'], 190) : null,
                'admin_id' => $m['from'] && $m['from']['admin'] ? (int) $m['from']['admin'] : null,
                'to_ext' => $m['to'] ? self::txt($m['to']['ext'], 64) : null,
                'to_admin' => $m['to'] && $m['to']['admin'] ? (int) $m['to']['admin'] : null,
                'at' => $m['at'], 'at_src' => $m['atSrc'] ? self::txt($m['atSrc'], 40) : null,
                'edit_at' => $m['editAt'], 'edit_src' => $m['editSrc'] ? self::txt($m['editSrc'], 40) : null,
                'status_ext' => $m['statusExt'] ? self::txt($m['statusExt'], 64) : null,
                'html' => $m['html'],
                'flags' => self::txt(implode(',', $m['flags']), 255) ?: null,
                'attachments' => $m['attachments'] ? self::json($m['attachments']) : null]);
        }
        return $impId;
    }

    /* ════════════════ Συνημμένα αρχεία ════════════════ */

    const MAX_FILE = 209715200;          // 200 MB ανά αρχείο

    /** Μόνο https προς το S3 του GoodDay ή το ίδιο το GoodDay — καμία άλλη διεύθυνση δεν ανοίγεται από τον server. */
    private static function fileUrlOk($u)
    {
        $p = parse_url((string) $u);
        if (!$p || strtolower((string) ($p['scheme'] ?? '')) !== 'https' || isset($p['user']) || isset($p['port'])) { return false; }
        $h = strtolower((string) ($p['host'] ?? ''));
        return (bool) preg_match('/(^|\.)amazonaws\.com$|(^|\.)goodday\.work$/', $h);
    }

    /** Κατέβασμα με όριο μεγέθους, χωρίς redirect, χωρίς το token (το link είναι ήδη υπογεγραμμένο). */
    private static function download($url)
    {
        if (!self::fileUrlOk($url)) { return [null, 'μη επιτρεπτή διεύθυνση']; }
        $ch = curl_init($url);
        $buf = '';
        $too = false;
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 180, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$buf, &$too) {
                $buf .= $chunk;
                if (strlen($buf) > self::MAX_FILE) { $too = true; return 0; }
                return strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($too) { return [null, 'πάνω από 200 MB']; }
        if ($code < 200 || $code >= 300) { return [null, $code === 403 ? 'έληξε το link' : 'HTTP ' . $code]; }
        return [$buf, null];
    }

    /** Ποια αρχεία ενός μηνύματος έχουν ήδη αποθηκευτεί εδώ (κλειδί: fileId του GoodDay). */
    private static function storedFileIds($msgRowId)
    {
        $ids = [];
        foreach (Capsule::table('mod_cpm_storage')->where('module', 'task')->where('ref_type', 'gdmsg')
                     ->where('ref_id', (int) $msgRowId)->get(['meta']) as $r) {
            $m = json_decode((string) $r->meta, true);
            if (!empty($m['gdFileId'])) { $ids[(string) $m['gdFileId']] = 1; }
        }
        return $ids;
    }

    /**
     * Κατεβάζει ΟΛΑ τα συνημμένα μιας εισαγωγής στη δική μας αποθήκευση (επαναλήψιμο: ό,τι
     * υπάρχει ήδη δεν ξανακατεβαίνει). Αν δοθεί token, όταν ένα link έχει λήξει (ισχύει 7 ημέρες)
     * ξαναδιαβάζουμε τα μηνύματα για φρέσκο link. Τρέχει ΜΕΤΑ το commit της εργασίας — η εργασία
     * και το ιστορικό δεν εξαρτώνται από το αν κατέβηκε ένα αρχείο, αλλά ό,τι λείπει φαίνεται.
     * @return array ['saved' => n, 'missing' => [[name, why], ...]]
     */
    public static function fetchFiles($importId, $adminId, $token = '')
    {
        if (function_exists('set_time_limit')) { @set_time_limit(600); }
        $imp = Capsule::table('mod_cpm_ext_imports')->where('id', (int) $importId)->first();
        if (!$imp) { return ['saved' => 0, 'missing' => []]; }
        $fresh = null;                       // μηνύματα από ζωντανή ανάκτηση (μόνο αν χρειαστεί)
        $saved = 0;
        $missing = [];
        foreach (Capsule::table('mod_cpm_ext_msgs')->where('import_id', $imp->id)->whereNotNull('attachments')->get() as $row) {
            $atts = json_decode((string) $row->attachments, true);
            if (!is_array($atts) || !$atts) { continue; }
            $have = self::storedFileIds($row->id);
            foreach ($atts as $a) {
                if (!is_array($a)) { continue; }
                $fid = (string) ($a['fileId'] ?? md5(json_encode($a)));
                if (isset($have[$fid])) { continue; }
                $name = self::txt((string) ($a['name'] ?? 'αρχείο'), 255) ?: 'αρχείο';
                $url = (string) ($a['downloadUrl'] ?? '');
                [$bytes, $why] = $url !== '' ? self::download($url) : [null, 'το GoodDay δεν δίνει link λήψης'];
                if ($bytes === null && $token !== '' && $why !== 'πάνω από 200 MB') {
                    if ($fresh === null) {
                        $fresh = [];
                        try {
                            foreach ((array) self::get('/task/' . rawurlencode($imp->ext_task_id) . '/messages', $token) as $fm) {
                                if (is_array($fm) && isset($fm['id'])) { $fresh[(string) $fm['id']] = $fm; }
                            }
                        } catch (\Throwable $e) { }
                    }
                    foreach ((array) ($fresh[$row->ext_msg_id]['attachments'] ?? []) as $fa) {
                        if (is_array($fa) && (string) ($fa['fileId'] ?? '') === $fid && !empty($fa['downloadUrl'])) {
                            [$bytes, $why] = self::download((string) $fa['downloadUrl']);
                        }
                    }
                }
                if ($bytes === null) { $missing[] = ['name' => $name, 'why' => $why ?: 'αποτυχία λήψης']; continue; }
                $mime = (string) ($a['mime'] ?? '') ?: 'application/octet-stream';
                try {
                    Storage::store(['module' => 'task', 'ref_type' => 'gdmsg', 'ref_id' => (int) $row->id,
                        'orig_name' => $name, 'mime' => $mime, 'contents' => $bytes, 'uploaded_by' => (int) $adminId,
                        'meta' => ['source' => 'goodday', 'gdFileId' => $fid, 'gdMsg' => $row->ext_msg_id, 'gdTask' => $imp->ext_task_id]]);
                    $saved++;
                } catch (\Throwable $e) {
                    $missing[] = ['name' => $name, 'why' => 'αποτυχία αποθήκευσης'];
                }
                unset($bytes);
            }
        }
        return ['saved' => $saved, 'missing' => $missing];
    }

    /** Για την καρτέλα εργασίας: εισαγωγή + ιστορικό (μόνο-ανάγνωση). Ποτέ ο φάκελος. */
    public static function forTask($taskId, callable $adminName)
    {
        static $has = null;
        if ($has === null) { $has = Capsule::schema()->hasTable('mod_cpm_ext_imports'); }
        if (!$has) { return null; }
        $imp = Capsule::table('mod_cpm_ext_imports')->where('task_id', (int) $taskId)->first();
        if (!$imp) { return null; }
        $meta = json_decode((string) $imp->meta, true) ?: [];
        $msgs = [];
        $stored = [];
        foreach (Capsule::table('mod_cpm_storage as f')->join('mod_cpm_ext_msgs as m', 'm.id', '=', 'f.ref_id')
                     ->where('f.module', 'task')->where('f.ref_type', 'gdmsg')->where('m.import_id', $imp->id)
                     ->orderBy('f.id')->get(['f.id', 'f.ref_id', 'f.orig_name', 'f.size', 'f.meta']) as $f) {
            $fm = json_decode((string) $f->meta, true) ?: [];
            $stored[(int) $f->ref_id][(string) ($fm['gdFileId'] ?? '')] = ['id' => (int) $f->id, 'name' => (string) $f->orig_name,
                'size' => (int) $f->size, 'url' => 'api.php?a=file_get&id=' . (int) $f->id];
        }
        $missingN = 0;
        foreach (Capsule::table('mod_cpm_ext_msgs')->where('import_id', $imp->id)->orderBy('seq')->get() as $m) {
            $att = $m->attachments ? json_decode((string) $m->attachments, true) : null;
            $msgs[] = ['id' => (int) $m->id, 'ext' => $m->ext_msg_id, 'kind' => $m->kind,
                'by' => $m->admin_id ? $adminName((int) $m->admin_id) : (string) $m->author_name,
                'byId' => $m->admin_id ? (int) $m->admin_id : 0,
                'srcName' => (string) $m->author_name, 'srcEmail' => (string) $m->author_email,
                'toName' => $m->to_admin ? $adminName((int) $m->to_admin) : null,
                'at' => $m->at, 'atSrc' => $m->at_src, 'editAt' => $m->edit_at,
                'statusExt' => $m->status_ext, 'html' => $m->html,
                'flags' => $m->flags ? explode(',', $m->flags) : [],
                /* Αρχείο που κατέβηκε → σύνδεσμος ΔΙΚΟΣ ΜΑΣ (file_get, έλεγχος πρόσβασης)· αλλιώς μένει ως «λείπει». */
                'attachments' => is_array($att) ? array_values(array_map(function ($a) use ($stored, $m, &$missingN) {
                    $a = is_array($a) ? $a : [];
                    $fid = (string) ($a['fileId'] ?? md5(json_encode($a)));
                    $got = $stored[(int) $m->id][$fid] ?? null;
                    if (!$got) { $missingN++; }
                    return $got ?: ['id' => 0, 'name' => (string) ($a['name'] ?? 'αρχείο'),
                        'size' => isset($a['size']) && is_numeric($a['size']) ? (int) $a['size'] : null, 'url' => null];
                }, $att)) : []];
        }
        $subIds = array_values(array_filter(array_map('strval', (array) ($meta['unmapped']['subtasks'] ?? []))));
        $subIn = $subIds ? Capsule::table('mod_cpm_ext_imports')->where('integration', self::INTEGRATION)
            ->where('source', self::SOURCE)->whereIn('ext_task_id', $subIds)->count() : 0;
        return ['ext' => $imp->ext_task_id, 'url' => $imp->source_url, 'at' => $imp->created_at,
            'subs' => ['total' => count($subIds), 'imported' => (int) $subIn],
            'filesMissing' => $missingN,
            'by' => $imp->imported_by ? $adminName((int) $imp->imported_by) : '',
            'sourceStatus' => $meta['sourceStatus'] ?? null, 'srcTz' => $meta['srcTz'] ?? null,
            'warnings' => count(json_decode((string) $imp->warnings, true) ?: []),
            'messages' => $msgs];
    }

    /** Σβήσιμο εργασίας → φεύγει και η εισαγωγή (ώστε να μπορεί να ξαναγίνει). */
    public static function forgetTask($taskId)
    {
        if (!Capsule::schema()->hasTable('mod_cpm_ext_imports')) { return; }
        foreach (Capsule::table('mod_cpm_ext_imports')->where('task_id', (int) $taskId)->pluck('id')->all() as $iid) {
            /* Τα αρχεία των μηνυμάτων φεύγουν μαζί (αλλιώς μένουν ορφανά στην αποθήκευση). */
            $mids = Capsule::table('mod_cpm_ext_msgs')->where('import_id', $iid)->pluck('id')->all();
            if ($mids) {
                foreach (Capsule::table('mod_cpm_storage')->where('module', 'task')->where('ref_type', 'gdmsg')->whereIn('ref_id', $mids)->pluck('id')->all() as $fid) {
                    try { Storage::delete((int) $fid); } catch (\Throwable $e) { }
                }
            }
            Capsule::table('mod_cpm_ext_msgs')->where('import_id', $iid)->delete();
            Capsule::table('mod_cpm_ext_imports')->where('id', $iid)->delete();
        }
    }
}

class GoodDayError extends \RuntimeException
{
    public $status;

    public function __construct($message, $status = 0)
    {
        parent::__construct($message);
        $this->status = (int) $status;
    }
}
