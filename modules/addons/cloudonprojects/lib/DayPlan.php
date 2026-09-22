<?php
/**
 * ═══════════ Κάρτες διαχείρισης — «Daily Driver» (22/9/2026) ═══════════
 *
 * Γιατί υπάρχει: δεν περιμένουμε από τους ανθρώπους του project management να
 * οργανώσουν μόνοι τους τη μέρα τους. Κάθε πρωί τους βγάζουμε ΚΑΡΤΕΣ ΑΠΟΦΑΣΗΣ —
 * όχι παρατηρήσεις, αλλά ερωτήσεις με κουμπιά που εκτελούν επί τόπου.
 *
 * Τρεις κανόνες που διαφοροποιούν αυτόν τον μηχανισμό από κάθε dashboard:
 *   1. ΣΚΟΠΙΑ = ΟΛΟ ΤΟ ΣΥΣΤΗΜΑ. Ό,τι θέλει διαχείριση, όχι μόνο τα έργα που
 *      είναι πάνω τους. Κάθε κάρτα όμως έχει ΕΝΑΝ ιδιοκτήτη· ό,τι δεν βρίσκει
 *      ιδιοκτήτη πάει σε κοινή δεξαμενή («αζήτητα») που τη βλέπουν όλοι οι PM.
 *   2. ΕΠΙΧΕΙΡΗΜΑΤΙΚΟ ΒΑΡΟΣ. Ποτέ «η #244 είναι εκπρόθεσμη», πάντα «ο πελάτης Χ
 *      περιμένει 11 ημέρες, το deadline ήταν 10/09».
 *   3. Η ΗΛΙΚΙΑ ΕΙΝΑΙ ΠΑΝΤΑ ΓΝΩΣΤΗ. Ό,τι δεν έχει ημερομηνία μετριέται από την
 *      ΚΑΤΑΧΩΡΗΣΗ: «μπήκε πριν Χ ημέρες, δεν το έπιασε κανείς». Έτσι ο μηχανισμός
 *      δουλεύει από την πρώτη μέρα, χωρίς να περιμένει να συμπληρωθεί τίποτα.
 *
 * Οι κανόνες είναι ΝΤΕΤΕΡΜΙΝΙΣΤΙΚΟΙ — καμία AI. Το AI μπορεί αργότερα να αλλάξει
 * τη διατύπωση, ποτέ το τι ανιχνεύεται.
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class DayPlan
{
    /** Μέγιστες κάρτες ανά άνθρωπο ανά ημέρα — πάνω από αυτό γίνεται θόρυβος και τις αγνοούν όλοι. */
    const MAX_PER_OWNER = 7;

    /** Πόσες απορρίψεις του ΙΔΙΟΥ κανόνα από τον ίδιο άνθρωπο πριν σιωπήσει γι' αυτόν. */
    const MUTE_AFTER = 3;

    /* ─────────────── ρυθμίσεις ─────────────── */

    private static function setting($k)
    {
        return Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', $k)->value('value');
    }

    public static function setSetting($k, $v)
    {
        if (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', $k)->exists()) {
            Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', $k)->update(['value' => (string) $v]);
        } else {
            Capsule::table('tbladdonmodules')->insert(['module' => 'cloudonprojects', 'setting' => $k, 'value' => (string) $v]);
        }
    }

    /** Ενεργό εκτός αν κλείσει ρητά. */
    public static function enabled()
    {
        $v = self::setting('cards_on');
        return $v === null || $v === 'on';
    }

    /** Η ομάδα που ΠΑΙΡΝΕΙ τις κάρτες (project management). Αν δεν οριστεί, βρίσκεται από το όνομα. */
    public static function teamId()
    {
        $v = (int) self::setting('cards_team');
        if ($v) { return $v; }
        $t = Capsule::table('mod_cpm_teams')->where('name', 'like', '%project manager%')->value('id');
        return (int) $t;
    }

    /** Η ομάδα στην οποία ΚΛΙΜΑΚΩΝΟΝΤΑΙ όσες δεν απαντήθηκαν. */
    public static function escTeamId()
    {
        $v = (int) self::setting('cards_esc_team');
        if ($v) { return $v; }
        $t = Capsule::table('mod_cpm_teams')->where('name', 'like', '%manager%')
            ->where('name', 'not like', '%project%')->value('id');
        return (int) $t;
    }

    /** Τα κατώφλια των κανόνων, όλα ρυθμιζόμενα χωρίς κώδικα. */
    public static function thresholds()
    {
        return [
            'age'      => max(1, (int) (self::setting('cards_age_days') ?: 14)),
            'idle'     => max(1, (int) (self::setting('cards_idle_days') ?: 7)),
            'unassign' => max(0, (int) (self::setting('cards_unassigned_days') ?: 1)),
        ];
    }

    /** Ποιοι παίρνουν κάρτες. */
    public static function owners()
    {
        $tid = self::teamId();
        if (!$tid) { return []; }
        return array_values(array_map('intval', Capsule::table('mod_cpm_team_members as m')
            ->join('tbladmins as a', 'a.id', '=', 'm.admin_id')
            ->where('m.team_id', $tid)->where('a.disabled', 0)->pluck('m.admin_id')->all()));
    }

    /** Ποιοι βλέπουν την κλιμάκωση. */
    public static function escalateTo()
    {
        $tid = self::escTeamId();
        if (!$tid) { return []; }
        return array_values(array_map('intval', Capsule::table('mod_cpm_team_members as m')
            ->join('tbladmins as a', 'a.id', '=', 'm.admin_id')
            ->where('m.team_id', $tid)->where('a.disabled', 0)->pluck('m.admin_id')->all()));
    }

    /* ─────────────── βοηθητικά ─────────────── */

    private static function days($from, $to = null)
    {
        $to = $to ?: time();
        if (!$from) { return 0; }
        $ts = is_numeric($from) ? (int) $from : strtotime((string) $from);
        return (int) floor(($to - $ts) / 86400);
    }

    /** «11 ημέρες» / «1 ημέρα» — η διατύπωση μετράει όσο και ο αριθμός. */
    private static function dLbl($n)
    {
        $n = (int) $n;
        return $n . ($n === 1 ? ' ημέρα' : ' ημέρες');
    }

    /** «πέρασε 1 ημέρα» / «πέρασαν 4 ημέρες» — σωστή συμφωνία ρήματος. */
    private static function passed($n)
    {
        return ((int) $n === 1 ? 'πέρασε ' : 'πέρασαν ') . self::dLbl($n);
    }

    private static function clientName($cid)
    {
        if (!$cid) { return ''; }
        static $c = [];
        if (isset($c[$cid])) { return $c[$cid]; }
        $r = Capsule::table('tblclients')->where('id', (int) $cid)->first(['companyname', 'firstname', 'lastname']);
        if (!$r) { return $c[$cid] = ''; }
        /* Τα ονόματα του WHMCS έρχονται html-escaped («&amp;») — στην κάρτα διαβάζονται από άνθρωπο. */
        $n = html_entity_decode(trim($r->companyname ?: trim($r->firstname . ' ' . $r->lastname)), ENT_QUOTES, 'UTF-8');
        return $c[$cid] = preg_replace('/\s{2,}/u', ' ', $n);
    }

    private static function doneIds()
    {
        static $d = null;
        if ($d === null) {
            $d = array_map('intval', Capsule::table('mod_cpm_statuses')->whereIn('phase', ['done', 'cancel'])->pluck('id')->all());
            if (!$d) { $d = array_map('intval', Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all()); }
            if (!$d) { $d = [0]; }
        }
        return $d;
    }

    /** Τελευταία κίνηση ανά εργασία (mod_cpm_activity) — «ακίνητη» σημαίνει καμία καταγραφή. */
    private static function lastMove()
    {
        static $m = null;
        if ($m === null) {
            $m = [];
            foreach (Capsule::table('mod_cpm_activity')->selectRaw('task_id, MAX(created_at) l')->groupBy('task_id')->get() as $r) {
                $m[(int) $r->task_id] = $r->l;
            }
        }
        return $m;
    }

    /**
     * Ποιος παίρνει την κάρτα. Ο υπεύθυνος του έργου αν είναι στην ομάδα PM·
     * αλλιώς 0 = κοινή δεξαμενή, ώστε να μη μένει τίποτα χωρίς άνθρωπο.
     */
    private static function ownerFor($managerId, array $owners)
    {
        $managerId = (int) $managerId;
        return ($managerId && in_array($managerId, $owners, true)) ? $managerId : 0;
    }

    /** Κανόνες που έχει σιγήσει ο συγκεκριμένος άνθρωπος (3 απορρίψεις = σιωπή, και καταγράφεται). */
    private static function mutedKinds($ownerId)
    {
        if (!$ownerId) { return []; }
        $out = [];
        foreach (Capsule::table('mod_cpm_cards')->where('owner_id', $ownerId)->where('state', 'dismissed')
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 30 * 86400))
            ->selectRaw('kind, COUNT(*) n')->groupBy('kind')->get() as $r) {
            if ((int) $r->n >= self::MUTE_AFTER) { $out[] = $r->kind; }
        }
        return $out;
    }

    /* ─────────────── οι κανόνες ─────────────── */

    /**
     * Παράγει τις κάρτες της ημέρας. Επιστρέφει πίνακα υποψηφίων (δεν γράφει).
     * Κάθε κάρτα: kind, sev (0..2), owner, ref_type, ref_id, title, body, acts[]
     */
    public static function collect(array $owners)
    {
        $th = self::thresholds();
        $today = date('Y-m-d');
        $now = time();
        $done = self::doneIds();
        $moves = self::lastMove();
        $cards = [];

        /* Έργα: κρατάμε υπεύθυνο & πελάτη για να δίνουμε επιχειρηματικό πλαίσιο σε κάθε εργασία. */
        $projects = [];
        foreach (Capsule::table('mod_cpm_projects')->get(['id', 'name', 'clientid', 'manager_id', 'due_date', 'pstatus', 'kind']) as $p) {
            $projects[(int) $p->id] = $p;
        }

        /* ── Κανόνας 2 & 1 & 3 & 4: ανά ανοιχτή εργασία ── */
        foreach (Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $done)
            ->get(['id', 'title', 'created_at', 'due_date', 'schedule_date', 'assignee', 'action_user', 'project_id', 'dept_id', 'estimate_minutes']) as $t) {

            $pid = (int) $t->project_id;
            $pr = $pid && isset($projects[$pid]) ? $projects[$pid] : null;
            $cl = $pr ? self::clientName($pr->clientid) : '';
            $who = (int) $t->action_user ?: (int) $t->assignee;
            $whoName = $who ? Db::adminName($who) : '';
            $owner = self::ownerFor($pr ? $pr->manager_id : 0, $owners);
            $ctx = ($cl ? 'Πελάτης ' . $cl : ($pr ? 'Έργο ' . $pr->name : 'Χωρίς έργο'));
            $ref = ['task', (int) $t->id];

            /* (4) Αζήτητα — κανείς δεν την έχει αναλάβει. Προηγείται όλων: δεν θα κινηθεί μόνη της. */
            if (!$t->assignee && !$t->action_user) {
                $age = self::days($t->created_at, $now);
                if ($age >= $th['unassign']) {
                    $cards[] = ['kind' => 'unassigned', 'sev' => $cl ? 2 : 1, 'w' => $age, 'owner' => $owner, 'ref' => $ref,
                        'title' => 'Δεν την έχει αναλάβει κανείς — ' . $t->title,
                        'body' => $ctx . '. Μπήκε πριν ' . self::dLbl($age) . ' και δεν έχει ούτε ανάδοχο ούτε μπάλα.'
                            . ($cl ? ' Ο πελάτης περιμένει χωρίς να το ξέρει κανείς.' : ''),
                        'acts' => ['assign', 'schedule', 'dismiss']];
                    continue;   // μία κάρτα ανά εργασία — η ανάθεση είναι το πρώτο πρόβλημα
                }
            }

            /* (2) Εκπρόθεσμη με επιχειρηματικό βάρος. */
            if ($t->due_date && $t->due_date < $today) {
                $late = self::days($t->due_date, $now);
                $dl = $t->schedule_date;
                $cards[] = ['kind' => 'overdue', 'sev' => ($cl ? 2 : ($late >= 7 ? 2 : 1)), 'w' => $late, 'owner' => $owner, 'ref' => $ref,
                    'title' => ($cl ? $cl . ' περιμένει ' . self::dLbl($late) : 'Εκπρόθεσμη ' . self::dLbl($late)) . ' — ' . $t->title,
                    'body' => 'Η λήξη ήταν ' . date('d/m', strtotime($t->due_date)) . ' και ' . self::passed($late) . '. '
                        . ($whoName ? 'Ανάδοχος: ' . $whoName . '.' : 'Χωρίς ανάδοχο.')
                        . ($dl && $dl < $today ? ' Και το deadline (' . date('d/m', strtotime($dl)) . ') έχει περάσει.' : '')
                        . ' ' . $ctx . '.',
                    'acts' => ['schedule', 'ask', 'close', 'dismiss']];
                continue;
            }

            /* (3) Ακίνητη — έχει άνθρωπο, αλλά καμία κίνηση. */
            $lm = $moves[(int) $t->id] ?? $t->created_at;
            $idle = self::days($lm, $now);
            if ($idle >= $th['idle']) {
                $cards[] = ['kind' => 'idle', 'sev' => $cl ? 1 : 0, 'w' => $idle, 'owner' => $owner, 'ref' => $ref,
                    'title' => 'Ακίνητη ' . self::dLbl($idle) . ' — ' . $t->title,
                    'body' => 'Καμία κίνηση από ' . date('d/m', strtotime($lm)) . '. '
                        . ($whoName ? 'Ανάδοχος: ' . $whoName . '. ' : 'Χωρίς ανάδοχο. ') . $ctx . '.',
                    'acts' => ['ask', 'schedule', 'dismiss']];
                continue;
            }

            /* (1) Χωρίς καμία ημερομηνία — μετράμε από την ΚΑΤΑΧΩΡΗΣΗ. */
            if (!$t->due_date && !$t->schedule_date) {
                $age = self::days($t->created_at, $now);
                if ($age >= $th['age']) {
                    $cards[] = ['kind' => 'age', 'sev' => $cl ? 1 : 0, 'w' => $age, 'owner' => $owner, 'ref' => $ref,
                        'title' => 'Καταχωρήθηκε πριν ' . self::dLbl($age) . ' και δεν μπήκε ποτέ σε πλάνο — ' . $t->title,
                        'body' => 'Δεν έχει ούτε λήξη ούτε deadline, άρα δεν εμφανίζεται πουθενά ως εκκρεμότητα. '
                            . ($whoName ? 'Ανάδοχος: ' . $whoName . '. ' : '') . $ctx . '.',
                        'acts' => ['schedule', 'assign', 'close', 'dismiss']];
                }
            }
        }

        /* ── Έργα εκπρόθεσμα: η παράδοση στον πελάτη είναι το βαρύτερο που έχουμε ── */
        foreach ($projects as $p) {
            if (!$p->due_date || $p->due_date >= $today || ($p->pstatus ?? '') === 'done') { continue; }
            $late = self::days($p->due_date, $now);
            $cl = self::clientName($p->clientid);
            $open = (int) Capsule::table('mod_cpm_tasks')->where('project_id', $p->id)->whereNotIn('status_id', $done)->count();
            $tot = (int) Capsule::table('mod_cpm_tasks')->where('project_id', $p->id)->count();
            $cards[] = ['kind' => 'project_late', 'sev' => 2, 'w' => $late + 50, 'owner' => self::ownerFor($p->manager_id, $owners),
                'ref' => ['project', (int) $p->id],
                'title' => ($cl ?: 'Εσωτερικό') . ': η παράδοση «' . $p->name . '» άργησε ' . self::dLbl($late),
                'body' => 'Ημερομηνία παράδοσης ' . date('d/m', strtotime($p->due_date)) . '. '
                    . ($tot ? 'Μένουν ' . $open . ' από ' . $tot . ' εργασίες ανοιχτές.' : 'Δεν έχει καμία εργασία.')
                    . ($p->manager_id ? ' Υπεύθυνος: ' . Db::adminName($p->manager_id) . '.' : ' Χωρίς υπεύθυνο.'),
                'acts' => ['schedule_project', 'ask', 'dismiss']];
        }

        /* ── (5) Ροή ουράς: ανοίγουν περισσότερα από όσα κλείνουν; Μία κάρτα, στη δεξαμενή. ── */
        $w0 = date('Y-m-d H:i:s', $now - 7 * 86400);
        $opened = (int) Capsule::table('mod_cpm_tasks')->where('created_at', '>=', $w0)->count();
        $closed = (int) Capsule::table('mod_cpm_tasks')->where('completed_at', '>=', $w0)->count();
        if ($opened > $closed && ($opened - $closed) >= 10) {
            $openNow = (int) Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $done)->count();
            $cards[] = ['kind' => 'flow', 'sev' => 1, 'w' => 0, 'owner' => 0, 'ref' => ['none', 0],
                'title' => 'Η ουρά μεγαλώνει: +' . $opened . ' νέες, −' . $closed . ' έκλεισαν αυτή την εβδομάδα',
                'body' => 'Καθαρή αύξηση ' . ($opened - $closed) . ' εργασιών. Ανοιχτές τώρα: ' . $openNow
                    . '. Με τον ίδιο ρυθμό, σε έναν μήνα θα είναι περίπου ' . ($openNow + 4 * ($opened - $closed)) . '.',
                'acts' => ['open_list', 'dismiss']];
        }

        /* ── (6) Θεμελίωση: χωρίς αυτά δεν υπάρχει ούτε πλάνο ούτε πρόβλεψη ── */
        $noEst = (int) Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $done)
            ->where(function ($q) { $q->whereNull('estimate_minutes')->orWhere('estimate_minutes', 0); })->count();
        $totOpen = (int) Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $done)->count();
        if ($totOpen && $noEst >= max(5, (int) round($totOpen * 0.3))) {
            $cards[] = ['kind' => 'no_estimate', 'sev' => 1, 'w' => 0, 'owner' => 0, 'ref' => ['none', 0],
                'title' => $noEst . ' από ' . $totOpen . ' ανοιχτές εργασίες δεν έχουν εκτίμηση',
                'body' => 'Χωρίς εκτίμηση δεν μπαίνουν σε πλάνο, δεν φαίνεται ο φόρτος κανενός και δεν μπορεί να προβλεφθεί καμία παράδοση.',
                'acts' => ['open_list', 'dismiss']];
        }
        $noDue = (int) Capsule::table('mod_cpm_projects')->whereNull('due_date')->where(function ($q) {
            $q->whereNull('pstatus')->orWhere('pstatus', '!=', 'done');
        })->count();
        if ($noDue >= 5) {
            $cards[] = ['kind' => 'no_deadline', 'sev' => 0, 'w' => 0, 'owner' => 0, 'ref' => ['none', 0],
                'title' => $noDue . ' έργα χωρίς ημερομηνία παράδοσης',
                'body' => 'Ποια από αυτά έχουν πραγματικό deadline πελάτη; Όσα έχουν, πρέπει να το δηλώσουν — αλλιώς κανείς δεν ξέρει ότι άργησαν.',
                'acts' => ['open_projects', 'dismiss']];
        }

        return $cards;
    }

    /* ─────────────── παραγωγή & αποθήκευση ─────────────── */

    /**
     * Χτίζει τις κάρτες της ημέρας. Idempotent: ξανατρέξιμο την ίδια μέρα ΔΕΝ διπλασιάζει
     * και ΔΕΝ ξαναβγάζει ό,τι έχει ήδη απαντηθεί ή αναβληθεί.
     *
     * @return array ['created'=>int, 'owners'=>int]
     */
    public static function build($dry = false)
    {
        if (!self::enabled()) { return ['created' => 0, 'owners' => 0, 'skip' => 'off']; }
        $owners = self::owners();
        if (!$owners) { return ['created' => 0, 'owners' => 0, 'skip' => 'no-team']; }

        $day = date('Y-m-d');
        $cards = self::collect($owners);

        /* Ταξινόμηση: πρώτα η σοβαρότητα, μετά η σειρά των κανόνων. */
        $order = ['unassigned' => 0, 'project_late' => 1, 'overdue' => 2, 'idle' => 3, 'age' => 4,
            'flow' => 5, 'no_estimate' => 6, 'no_deadline' => 7];
        /* Σοβαρότητα, μετά «πόσο καίει» (ημέρες), μετά η σειρά των κανόνων. Χωρίς το δεύτερο κριτήριο
           μια παράδοση 11 ημερών θα έμπαινε κάτω από μία 1 ημέρας. */
        usort($cards, function ($a, $b) use ($order) {
            if ($a['sev'] !== $b['sev']) { return $b['sev'] <=> $a['sev']; }
            $aw = (int) ($a['w'] ?? 0); $bw = (int) ($b['w'] ?? 0);
            if ($aw !== $bw) { return $bw <=> $aw; }
            return ($order[$a['kind']] ?? 9) <=> ($order[$b['kind']] ?? 9);
        });

        /* Ήδη ανοιχτές/αναβληθείσες κάρτες για το ίδιο πράγμα: δεν ξαναβγαίνουν. */
        $live = [];
        foreach (Capsule::table('mod_cpm_cards')->whereIn('state', ['open', 'snoozed'])->get(['kind', 'ref_type', 'ref_id', 'owner_id']) as $c) {
            $live[$c->kind . ':' . $c->ref_type . ':' . (int) $c->ref_id] = true;
        }
        /* Και ό,τι τακτοποιήθηκε/απορρίφθηκε ΣΗΜΕΡΑ δεν επανέρχεται σήμερα. */
        foreach (Capsule::table('mod_cpm_cards')->where('day', $day)->whereIn('state', ['done', 'dismissed'])->get(['kind', 'ref_type', 'ref_id']) as $c) {
            $live[$c->kind . ':' . $c->ref_type . ':' . (int) $c->ref_id] = true;
        }

        $muted = [];
        foreach ($owners as $o) { $muted[$o] = self::mutedKinds($o); }

        $perOwner = [];
        foreach (Capsule::table('mod_cpm_cards')->whereIn('state', ['open'])->where('day', $day)
            ->selectRaw('owner_id, COUNT(*) n')->groupBy('owner_id')->get() as $r) {
            $perOwner[(int) $r->owner_id] = (int) $r->n;
        }

        $made = 0;
        $dueAt = date('Y-m-d H:i:s', strtotime($day . ' 18:00:00'));
        foreach ($cards as $c) {
            $key = $c['kind'] . ':' . $c['ref'][0] . ':' . $c['ref'][1];
            if (isset($live[$key])) { continue; }
            $own = (int) $c['owner'];
            if ($own && in_array($c['kind'], $muted[$own] ?? [], true)) { $own = 0; }   // σίγαση → δεξαμενή, όχι σβήσιμο
            if (($perOwner[$own] ?? 0) >= self::MAX_PER_OWNER) { continue; }
            if (!$dry) {
                Capsule::table('mod_cpm_cards')->insert([
                    'day' => $day, 'owner_id' => $own, 'kind' => $c['kind'], 'sev' => (int) $c['sev'],
                    'ref_type' => $c['ref'][0], 'ref_id' => (int) $c['ref'][1],
                    'weight' => (int) ($c['w'] ?? 0),
                    'title' => mb_substr($c['title'], 0, 255), 'body' => mb_substr($c['body'], 0, 1000),
                    'acts' => implode(',', $c['acts']), 'state' => 'open',
                    'due_at' => $dueAt, 'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $perOwner[$own] = ($perOwner[$own] ?? 0) + 1;
            $live[$key] = true;
            $made++;
        }

        /* Ειδοποίηση ΜΙΑ φορά ανά άνθρωπο ανά ημέρα. Το cron τρέχει κάθε 10΄ και το build είναι
           idempotent, αλλά χωρίς αυτόν τον φύλακα κάθε νέα κάρτα μέσα στη μέρα ξαναχτυπούσε
           ειδοποίηση σε όλους — σε δύο μέρες θα τις αγνοούσαν (22/9/2026). */
        if (!$dry && $made) {
            foreach ($owners as $o) {
                if (Db::pref($o, 'cards_notified', '') === $day) { continue; }
                $n = (int) Capsule::table('mod_cpm_cards')->where('day', $day)->where('state', 'open')
                    ->where(function ($q) use ($o) { $q->where('owner_id', $o)->orWhere('owner_id', 0); })->count();
                if ($n) {
                    Db::setPref($o, 'cards_notified', $day);
                    Db::pushNotification($o, 'action', 'Η ουρά σου σήμερα: ' . $n . ' αποφάσεις που θέλουν απάντηση', '/project/#/cards');
                }
            }
        }
        return ['created' => $made, 'owners' => count($owners)];
    }

    /**
     * Κλιμάκωση: ό,τι έμεινε αναπάντητο μετά την προθεσμία ανεβαίνει στους Manager —
     * ΜΙΑ σύνοψη ανά παραλήπτη, όχι μία ειδοποίηση ανά κάρτα.
     */
    public static function escalate($dry = false)
    {
        if (!self::enabled()) { return 0; }
        $now = date('Y-m-d H:i:s');
        $rows = Capsule::table('mod_cpm_cards')->where('state', 'open')->whereNull('escalated_at')
            ->where('due_at', '<', $now)->get(['id', 'owner_id', 'title', 'sev']);
        if (!count($rows)) { return 0; }

        $byOwner = [];
        foreach ($rows as $r) { $byOwner[(int) $r->owner_id][] = $r; }
        $to = self::escalateTo();
        if ($to && !$dry) {
            foreach ($byOwner as $own => $list) {
                $nm = $own ? Db::adminName($own) : 'αζήτητες (κοινή δεξαμενή)';
                $hard = count(array_filter($list, function ($x) { return (int) $x->sev >= 2; }));
                $msg = $nm . ': ' . count($list) . ' κάρτες χωρίς απάντηση' . ($hard ? ', οι ' . $hard . ' κρίσιμες' : '');
                foreach ($to as $m) {
                    if ($m === $own) { continue; }
                    Db::pushNotification($m, 'action', $msg, '/project/#/cards');
                }
            }
        }
        if (!$dry) {
            Capsule::table('mod_cpm_cards')->whereIn('id', array_map(function ($r) { return (int) $r->id; }, $rows->all()))
                ->update(['escalated_at' => $now]);
        }
        return count($rows);
    }
}
