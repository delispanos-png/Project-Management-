<?php

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

/**
 * «ΤΙ ΝΑ ΠΡΟΣΕΞΩ» — επόπτης και coach μαζί, ανά ρόλο.
 *
 * ΤΟ ΠΡΟΒΛΗΜΑ: το εργαλείο ήξερε ήδη τι πάει στραβά — ο coach της «Μέρας μου»
 * το έλεγε σωστά. Αλλά έπρεπε να ΑΝΟΙΞΕΙΣ τη «Μέρα μου» για να το δεις. Όποιος
 * δούλευε όλη μέρα μέσα στα tickets ή στο board δεν το έβλεπε ποτέ, και τα
 * προβλήματα μεγάλωναν αθόρυβα. Και ο επικεφαλής δεν είχε πουθενά μια εικόνα
 * «τι χρειάζεται προσοχή στην ΟΜΑΔΑ μου» — έπρεπε να την ψάξει άνθρωπο-άνθρωπο.
 *
 * ΤΡΕΙΣ ΟΠΤΙΚΕΣ, Η ΚΑΘΕΜΙΑ ΓΙΑ ΟΠΟΙΟΝ ΤΗΝ ΕΧΕΙ:
 *   me   — ο καθένας για τα δικά του (πάντα)
 *   team — ο επικεφαλής για την ομάδα του (is_leader)
 *   pm   — ο project manager για ό,τι θέλει ανάθεση ή απόφαση
 *
 * ΔΕΝ ΕΙΝΑΙ ΑΝΑΦΟΡΑ. Κάθε γραμμή κουβαλά τα ΣΥΓΚΕΚΡΙΜΕΝΑ αντικείμενα (refs),
 * ώστε να πηγαίνεις κατευθείαν εκεί. «3 εργασίες εκπρόθεσμες» χωρίς ποιες
 * είναι, είναι άγχος χωρίς ενέργεια.
 *
 * Ο ΚΑΝΟΝΑΣ ΤΗΣ ΜΠΑΛΑΣ ΙΣΧΥΕΙ ΠΑΝΤΟΥ: «δικό μου» = το κρατάω εγώ. Ό,τι έχω
 * παραδώσει και περιμένει άλλον δεν είναι δική μου εκκρεμότητα — δες
 * `cnp_scope_mine` / `Watch::mine()`.
 */
class Watch
{
    /** Πόσες ημέρες χωρίς κίνηση θεωρούνται «κόλλησε». */
    const STALE_DAYS = 7;
    /** Πόσες ώρες χωρίς απάντηση θεωρούνται «δεν απάντησε». */
    const UNANSWERED_HOURS = 24;
    /** Πόσα αντικείμενα κουβαλά το πολύ κάθε γραμμή. */
    const MAX_REFS = 6;
    /* Πόσο πίσω κοιτάμε για κλήσεις χωρίς χαρακτηρισμό. Πιο πίσω από βδομάδα, ο
       άνθρωπος δεν θυμάται τι είπε — και μια ερώτηση που δεν απαντιέται σωστά
       είναι χειρότερη από καμία. */
    const CALL_DAYS = 7;

    /** Λεπτά όπως τα λέει άνθρωπος: 95 → «1ω 35΄». */
    private static function hm($m)
    {
        $m = max(0, (int) $m);
        return $m < 60 ? $m . '΄' : intdiv($m, 60) . 'ω ' . ($m % 60 ? ($m % 60) . '΄' : '');
    }

    private static function doneIds()
    {
        static $d = null;
        if ($d === null) {
            $d = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
        }
        return $d;
    }

    /** Ο κανόνας της μπάλας, σε μορφή ερωτήματος. */
    private static function mine($q, $adminId)
    {
        return $q->where(function ($w) use ($adminId) {
            $w->where('action_user', $adminId)
              ->orWhere(function ($x) use ($adminId) {
                  $x->where('assignee', $adminId)
                    ->where(function ($y) { $y->whereNull('action_user')->orWhere('action_user', 0); });
              });
        });
    }

    private static function taskRefs($rows)
    {
        $out = [];
        foreach (array_slice($rows, 0, self::MAX_REFS) as $t) {
            $out[] = ['kind' => 'task', 'id' => (int) $t->id, 'label' => mb_substr((string) $t->title, 0, 44)];
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */

    /**
     * @return array ['n'=>int, 'lvl'=>string, 'groups'=>[...], 'at'=>string]
     */
    public static function forAdmin($adminId, $isFull = false)
    {
        $adminId = (int) $adminId;
        $groups = [];

        $me = self::personal($adminId);
        if ($me) {
            $groups[] = ['key' => 'me', 'title' => 'Τα δικά σου', 'items' => $me];
        }

        $ledTeams = [];
        if (Capsule::schema()->hasTable('mod_cpm_team_members')) {
            $ledTeams = array_map('intval', Capsule::table('mod_cpm_team_members')
                ->where('admin_id', $adminId)->where('is_leader', 1)->pluck('team_id')->all());
        }
        if ($ledTeams) {
            $team = self::team($adminId, $ledTeams);
            if ($team) {
                $groups[] = ['key' => 'team', 'title' => 'Η ομάδα σου', 'items' => $team];
            }
        }

        $isPm = in_array($adminId, DayPlan::owners(), true) || $isFull;
        /* Ο MANAGER ΒΛΕΠΕΙ ΤΟΥΣ ΟΜΑΔΑΡΧΕΣ. Το τρίτο επίπεδο του coach: ο
           χειριστής για τον εαυτό του, ο ομαδάρχης για την ομάδα του, ο
           manager για τους ομαδάρχες. Χωρίς αυτό ο manager έβλεπε μόνο
           εργασίες που θέλουν ανάθεση — δουλειά, όχι ανθρώπους.

           ΜΟΝΟ πλήρεις διαχειριστές, ΟΧΙ όλη η ομάδα PM: ο ομαδάρχης δεν
           επιβλέπει τους άλλους ομαδάρχες, ούτε τον manager του. Χωρίς αυτόν
           τον περιορισμό ο Θύμιος έβλεπε γραμμή «Ρώτα τι χρειάζεται» για τον
           προϊστάμενό του. */
        if ($isFull) {
            $leads = self::leads($adminId);
            if ($leads) {
                /* ΟΧΙ «οι ομαδάρχες σου»: η ενότητα δείχνει και ομάδες ΧΩΡΙΣ
                   επικεφαλή και ανθρώπους εκτός ομάδας. Ο τίτλος πρέπει να
                   λέει τι κάνει η γραμμή, όχι ποιον περίμενε να βρει. */
                $groups[] = ['key' => 'leads', 'title' => 'Ποιος θέλει το βλέμμα σου', 'items' => $leads];
            }
        }
        if ($isPm) {
            $pm = self::pm($adminId);
            if ($pm) {
                $groups[] = ['key' => 'pm', 'title' => 'Θέλουν ανάθεση ή απόφαση', 'items' => $pm];
            }
        }

        $n = 0;
        $lvl = 'ok';
        foreach ($groups as $g) {
            foreach ($g['items'] as $it) {
                $n++;
                if ($it['lvl'] === 'bad') { $lvl = 'bad'; }
                elseif ($it['lvl'] === 'warn' && $lvl !== 'bad') { $lvl = 'warn'; }
                elseif ($lvl === 'ok') { $lvl = 'tip'; }
            }
        }
        return ['n' => $n, 'lvl' => $lvl, 'groups' => $groups, 'at' => date('Y-m-d H:i:s')];
    }

    /* ------------------------------------------------------------------ */
    /* Τα δικά μου                                                        */
    /* ------------------------------------------------------------------ */

    private static function personal($adminId)
    {
        $today = date('Y-m-d');
        $done = self::doneIds();
        $out = [];

        /* ΚΛΗΣΕΙΣ ΧΩΡΙΣ ΧΑΡΑΚΤΗΡΙΣΜΟ. Ο χρόνος ομιλίας μετριέται μόνος του και
           μετράει στη μέρα σου έτσι κι αλλιώς — αυτό που λείπει είναι ΤΙ ΗΤΑΝ.
           Χωρίς υπενθύμιση δεν το συμπληρώνει κανείς: στη μέτρηση της 22/09/2026
           ήταν χαρακτηρισμένη 1 κλήση στις 232.

           Μετράμε ΧΘΕΣ ΚΑΙ ΠΙΣΩ: οι σημερινές είναι ακόμη φρέσκες και η ερώτηση
           θα ήταν γκρίνια πάνω στη δουλειά. */
        /* ΟΧΙ ΑΝΑΔΡΟΜΙΚΑ. Όταν άνοιξε το κύκλωμα υπήρχαν 15.986 παλιές κλήσεις·
           το να τις ζητούσαμε θα ήταν βουνό που κανείς δεν ανεβαίνει. Μετράει
           ό,τι έγινε από την έναρξη και μετά. */
        $callStart = (string) Db::pref(0, 'calls_label_from', date('Y-m-d'));
        $callFrom = max(date('Y-m-d 00:00:00', strtotime('-' . self::CALL_DAYS . ' days')),
                        $callStart . ' 00:00:00');
        $callQ = function () use ($adminId, $callFrom) {
            return Capsule::table('mod_cpm_calls')->where('admin_id', $adminId)
                ->where('talk_seconds', '>', 0)->whereNull('logged_at')
                ->where('started_at', '>=', $callFrom)
                ->where('started_at', '<', date('Y-m-d 00:00:00'));
        };
        $callN = (int) $callQ()->count();
        if ($callN) {
            $callMin = (int) round(((int) $callQ()->sum('talk_seconds')) / 60);
            $out[] = ['lvl' => 'warn', 'icon' => '📞', 'go' => 'calllog',
                'text' => $callN . ($callN > 1 ? ' κλήσεις σου δεν έχουν' : ' κλήση σου δεν έχει')
                    . ' χαρακτηριστεί — ' . self::hm($callMin)
                    . ' που δεν ξέρουμε πού πήγαν.'];
        }

        $overdue = self::mine(Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $done), $adminId)
            ->whereNotNull('due_date')->where('due_date', '<', $today)
            ->orderBy('due_date')->get(['id', 'title', 'due_date'])->all();
        if ($overdue) {
            $out[] = ['lvl' => 'bad', 'icon' => '🔴', 'refs' => self::taskRefs($overdue),
                'text' => count($overdue) . ' εκπρόθεσμ' . (count($overdue) > 1 ? 'ες εργασίες' : 'η εργασία')
                    . ' — κλείσε ή επαναπρογραμμάτισε ρεαλιστικά.'];
        }

        /* Ερωτήσεις που περιμένουν ΕΜΕΝΑ. Πρώτα απ' όλα: κάποιος περιμένει και
           δεν το ξέρει κανείς παρά μόνο το καμπανάκι, που καθαρίζεται εύκολα. */
        $ask = Capsule::table('mod_cpm_help')->where('to_admin', $adminId)->where('status', 'open')
            ->orderBy('created_at')->get()->all();
        if ($ask) {
            $late = 0;
            $refs = [];
            foreach (array_slice($ask, 0, self::MAX_REFS) as $h) {
                $hrs = (time() - strtotime((string) $h->created_at)) / 3600;
                if ($hrs >= self::UNANSWERED_HOURS) { $late++; }
                $refs[] = ['kind' => 'request', 'id' => (int) $h->id,
                    'label' => Db::adminName((int) $h->from_admin) . ': ' . mb_substr((string) $h->message, 0, 36)];
            }
            $out[] = ['lvl' => $late ? 'bad' : 'warn', 'icon' => '❓', 'refs' => $refs,
                'text' => (count($ask) > 1 ? count($ask) . ' ερωτήσεις περιμένουν' : 'Μία ερώτηση περιμένει')
                    . ' απάντησή σου' . ($late ? ' — ' . $late . ' πάνω από μέρα' : '') . '.'];
        }

        $stale = self::mine(
            Capsule::table('mod_cpm_tasks')->whereIn('status_id', Db::statusIds('work')), $adminId)
            ->where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-' . self::STALE_DAYS . ' days')))
            ->get(['id', 'title'])->all();
        if ($stale) {
            $out[] = ['lvl' => 'warn', 'icon' => '🐌', 'refs' => self::taskRefs($stale),
                'text' => (count($stale) > 1 ? count($stale) . ' εργασίες σε εξέλιξη' : 'Μία εργασία σε εξέλιξη')
                    . ' χωρίς κίνηση πάνω από ' . self::STALE_DAYS . ' ημέρες — δώσε ώθηση ή γύρνα τη μπάλα.'];
        }

        $dueTod = self::mine(Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $done), $adminId)
            ->where('due_date', $today)->get(['id', 'title'])->all();
        if ($dueTod) {
            $out[] = ['lvl' => 'tip', 'icon' => '📌', 'refs' => self::taskRefs($dueTod),
                'text' => (count($dueTod) > 1 ? count($dueTod) . ' εργασίες λήγουν' : 'Μία εργασία λήγει') . ' σήμερα.'];
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Η ομάδα μου (επικεφαλής)                                           */
    /* ------------------------------------------------------------------ */

    /**
     * ΓΙΑ ΤΟΝ MANAGER: ΟΙ ΟΜΑΔΕΣ ΚΑΙ ΟΙ ΑΝΘΡΩΠΟΙ ΤΟΥΣ.
     *
     * Το `pm()` του λέει ποιες ΕΡΓΑΣΙΕΣ θέλουν ανάθεση. Αυτό του λέει ποιος
     * ΑΝΘΡΩΠΟΣ θέλει το βλέμμα του — γιατί ο manager δεν τρέχει εργασίες,
     * τρέχει ανθρώπους.
     *
     * ΤΡΙΑ ΠΟΥ ΕΜΑΘΑ ΜΕ ΤΟΝ ΔΥΣΚΟΛΟ ΤΡΟΠΟ (23/09/2026):
     *
     *  1. Ομάδα ΧΩΡΙΣ ΕΠΙΚΕΦΑΛΗ δεν επιτρέπεται να εξαφανίζεται. Το Λογιστήριο
     *     δεν είχε, και η Δώρα — με 3 εκπρόθεσμες, οι περισσότερες της εταιρείας
     *     — ήταν αόρατη. Ακριβώς εκεί που δεν υπάρχει υπεύθυνος, ο manager ΕΙΝΑΙ
     *     ο υπεύθυνος.
     *  2. Δεν φτάνει «η ομάδα Χ έχει 4»: ο manager θέλει ΟΝΟΜΑΤΑ, όπως ακριβώς
     *     τα βλέπει ο ομαδάρχης για τους δικούς του.
     *  3. Άνθρωπος εκτός κάθε ομάδας είναι κι αυτός αόρατος — και συνήθως κατά
     *     λάθος. Βγαίνει χωριστά.
     *
     * Οι ομάδες που ηγείται Ο ΙΔΙΟΣ εξαιρούνται: τις βλέπει στο «Η ομάδα σου».
     */
    private static function leads($adminId)
    {
        $out = [];
        if (!Capsule::schema()->hasTable('mod_cpm_team_members')) { return $out; }
        $today = date('Y-m-d');
        $done = self::doneIds();

        $mine = array_map('intval', Capsule::table('mod_cpm_team_members')
            ->where('admin_id', $adminId)->where('is_leader', 1)->pluck('team_id')->all());

        /* ΠΟΤΕ ΠΑΝΩ. Ομάδα όπου είμαι ΑΠΛΟ ΜΕΛΟΣ είναι η ομάδα του δικού μου
           προϊσταμένου — δεν τον επιβλέπω. Χωρίς αυτό, η Emmanuela (πλήρης
           διαχειρίστρια, αλλά μέλος της ομάδας του Παναγιώτη) έβλεπε γραμμή
           «Ρώτα τι χρειάζεται» για τον προϊστάμενό της. */
        $above = array_map('intval', Capsule::table('mod_cpm_team_members')
            ->where('admin_id', $adminId)->where('is_leader', 0)->pluck('team_id')->all());

        /* Εκπρόθεσμες ανά άνθρωπο, μία φορά για όλους — όχι ένα ερώτημα ανά ομάδα. */
        $lateBy = [];
        $lateRows = [];
        foreach (Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $done)
            ->whereNotNull('due_date')->where('due_date', '<', $today)
            ->orderBy('due_date')->get(['id', 'title', 'assignee', 'action_user']) as $t) {
            $who = (int) ($t->action_user ?: $t->assignee);
            if (!$who) { continue; }
            $lateBy[$who] = ($lateBy[$who] ?? 0) + 1;
            $lateRows[$who][] = $t;
        }

        /* ΚΑΘΕ ΑΝΘΡΩΠΟΣ ΑΝΑΦΕΡΕΤΑΙ ΜΙΑ ΦΟΡΑ (23/09/2026).
           Ο Θέμιος είναι επικεφαλής του «Support» ΚΑΙ μέλος του «Project Manager».
           Οι δύο εκπρόθεσμές του έβγαιναν λοιπόν δύο φορές: μία στη δική του γραμμή
           και μία στη γραμμή του Βάκρινου — που διάβαζες ως «ο Βάκρινος έχει δύο
           εκπρόθεσμες», ενώ δεν ήταν δικές του ούτε κατ' όνομα.
           Όποιος είναι ο ίδιος επικεφαλής λογοδοτεί στη ΔΙΚΗ ΤΟΥ γραμμή· στη γραμμή
           του δικού του επικεφαλή δεν ξαναμετριέται. */
        $leadsSomewhere = array_map('intval', Capsule::table('mod_cpm_team_members')
            ->where('is_leader', 1)->distinct()->pluck('admin_id')->all());

        /* ΚΑΙ ΟΧΙ ΔΕΥΤΕΡΗ ΦΟΡΑ ΑΠΟ ΜΑΚΡΙΑ. Όποιον έχεις στη ΔΙΚΗ σου ομάδα τον
           βλέπεις ήδη στο «Η ομάδα σου». Αν τύχει να είναι και μέλος ή επικεφαλής
           αλλού, η ίδια εκπρόθεσμη ξαναερχόταν με άλλο όνομα από πάνω. */
        $mineTeamPeople = $mine ? array_map('intval', Capsule::table('mod_cpm_team_members')
            ->whereIn('team_id', $mine)->distinct()->pluck('admin_id')->all()) : [];

        /* ΕΝΑΣ ΑΝΘΡΩΠΟΣ, ΜΙΑ ΓΡΑΜΜΗ (23/09/2026).
           Η γραμμή έλεγε «Θέμιος (Support) — 7 εκπρόθεσμες: Kleon 5 · Θέμιος 2»:
           δύο ανθρώπων η δουλειά πλεγμένη σε μία πρόταση, με τα #id όλων μαζί από
           κάτω. Δεν ξέρεις ποιανού είναι ποιο, δεν μπορείς να ρωτήσεις έναν, και
           δεν φαίνεται ποιος φταίει. Κάθε άνθρωπος παίρνει τη δική του γραμμή, με
           ΤΙΣ ΔΙΚΕΣ ΤΟΥ εργασίες και δικό του κουμπί ερώτησης. Η ομάδα μένει μόνο
           ως συμφραζόμενο σε παρένθεση. */
        /* Και όταν κάποιος είναι επικεφαλής σε ΔΥΟ ομάδες (π.χ. «Support» και
           «Project Manager»), εμφανιζόταν δύο φορές με τις ίδιες εκπρόθεσμες.
           Ο κανόνας μένει ένας: ένας άνθρωπος, μία γραμμή — η πρώτη. */
        $seen = [];
        $line = function ($title, array $people, $noLead, $rowLead = 0)
            use ($lateBy, $lateRows, $leadsSomewhere, $mineTeamPeople, &$seen, &$out) {
            $per = [];
            foreach ($people as $pid) {
                if (empty($lateBy[$pid])) { continue; }
                if (isset($seen[(int) $pid])) { continue; }
                if ($pid !== (int) $rowLead && in_array((int) $pid, $leadsSomewhere, true)) { continue; }
                if (in_array((int) $pid, $mineTeamPeople, true)) { continue; }
                $per[$pid] = $lateBy[$pid];
                $seen[(int) $pid] = true;
            }
            if (!$per) { return; }
            arsort($per);
            foreach ($per as $pid => $n) {
                $out[] = [
                    'lvl' => 'bad',
                    'icon' => $noLead ? '⚠' : '🎯',
                    'refs' => self::taskRefs($lateRows[$pid]),
                    'ask' => (int) $pid,
                    'askName' => Db::adminName((int) $pid),
                    'text' => Db::adminName((int) $pid) . ' (' . $title . ') — '
                        . $n . ($n > 1 ? ' εκπρόθεσμες' : ' εκπρόθεσμη')
                        . ($noLead ? ' · η ομάδα δεν έχει επικεφαλής, είσαι εσύ.' : ''),
                ];
            }
        };

        $inTeam = [];
        foreach (Capsule::table('mod_cpm_teams')->orderBy('sort')->get(['id', 'name']) as $t) {
            $tid = (int) $t->id;
            $members = [];
            $lead = 0;
            foreach (Capsule::table('mod_cpm_team_members as m')
                ->join('tbladmins as a', 'a.id', '=', 'm.admin_id')
                ->where('m.team_id', $tid)->where('a.disabled', 0)
                ->get(['m.admin_id', 'm.is_leader']) as $m) {
                $members[] = (int) $m->admin_id;
                $inTeam[(int) $m->admin_id] = true;
                if ((int) $m->is_leader === 1) { $lead = (int) $m->admin_id; }
            }
            if (!$members || in_array($tid, $mine, true) || in_array($tid, $above, true)) { continue; }
            if ($lead === $adminId) { continue; }

            $line((string) $t->name, $members, !$lead, $lead);
        }

        /* Εκτός κάθε ομάδας: κανείς δεν τους κοιτάζει. */
        $loose = [];
        foreach (Capsule::table('tbladmins')->where('disabled', 0)->get(['id', 'firstname', 'lastname']) as $a) {
            $aid = (int) $a->id;
            if (isset($inTeam[$aid]) || $aid === $adminId || empty($lateBy[$aid])) { continue; }
            if (preg_match('/\b(bot|test|debug|system|support team|cloud on)\b/i',
                trim($a->firstname . ' ' . $a->lastname))) { continue; }
            $loose[] = $aid;
        }
        if ($loose) { $line('Εκτός ομάδας', $loose, true); }

        return $out;
    }

    private static function team($adminId, array $teamIds)
    {
        $today = date('Y-m-d');
        $done = self::doneIds();
        $out = [];

        $members = array_values(array_unique(array_map('intval', Capsule::table('mod_cpm_team_members as m')
            ->join('tbladmins as a', 'a.id', '=', 'm.admin_id')
            ->whereIn('m.team_id', $teamIds)->where('a.disabled', 0)
            ->where('m.admin_id', '<>', $adminId)->pluck('m.admin_id')->all())));
        if (!$members) { return $out; }

        /* Εκπρόθεσμες ΤΗΣ ΟΜΑΔΑΣ, ομαδοποιημένες ανά άνθρωπο: ο επικεφαλής δεν
           θέλει «12 εκπρόθεσμες», θέλει «ο Χ έχει 5». */
        $rows = Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $done)
            ->whereNotNull('due_date')->where('due_date', '<', $today)
            ->where(function ($w) use ($members) {
                $w->whereIn('action_user', $members)
                  ->orWhere(function ($x) use ($members) {
                      $x->whereIn('assignee', $members)
                        ->where(function ($y) { $y->whereNull('action_user')->orWhere('action_user', 0); });
                  });
            })->orderBy('due_date')->get(['id', 'title', 'assignee', 'action_user'])->all();
        if ($rows) {
            /* Ανά άνθρωπο, όχι «12 στην ομάδα»: ο επικεφαλής δεν ρωτά την ομάδα,
               ρωτά κάποιον — και πρέπει να βλέπει ΤΙ ακριβώς κρατά ο καθένας. */
            $per = [];
            foreach ($rows as $t) {
                $who = (int) ($t->action_user ?: $t->assignee);
                if (!$who) { continue; }
                $per[$who][] = $t;
            }
            uasort($per, function ($a, $b) { return count($b) - count($a); });
            foreach ($per as $who => $his) {
                $n = count($his);
                $out[] = ['lvl' => 'bad', 'icon' => '🔴', 'refs' => self::taskRefs($his),
                    'ask' => (int) $who, 'askName' => Db::adminName((int) $who),
                    'text' => Db::adminName((int) $who) . ' — ' . $n
                        . ($n > 1 ? ' εκπρόθεσμες' : ' εκπρόθεσμη') . ' στην ομάδα σου.'];
            }
        }

        $stale = Capsule::table('mod_cpm_tasks')->whereIn('status_id', Db::statusIds('work'))
            ->where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-' . self::STALE_DAYS . ' days')))
            ->where(function ($w) use ($members) {
                $w->whereIn('action_user', $members)
                  ->orWhere(function ($x) use ($members) {
                      $x->whereIn('assignee', $members)
                        ->where(function ($y) { $y->whereNull('action_user')->orWhere('action_user', 0); });
                  });
            })->get(['id', 'title'])->all();
        if ($stale) {
            $out[] = ['lvl' => 'warn', 'icon' => '🐌', 'refs' => self::taskRefs($stale),
                'text' => (count($stale) > 1 ? count($stale) . ' εργασίες σε εξέλιξη' : 'Μία εργασία σε εξέλιξη')
                    . ' χωρίς κίνηση πάνω από ' . self::STALE_DAYS . ' ημέρες — ρώτα τι '
                    . (count($stale) > 1 ? 'τις' : 'την') . ' κρατά.'];
        }

        /* Ερωτήσεις ΠΟΥ ΕΣΤΕΙΛΑ και δεν απαντήθηκαν. Ο κύκλος «ρωτάω → απαντά»
           κλείνει μόνο αν κάποιος τον παρακολουθεί· αλλιώς η ερώτηση χάνεται. */
        $noAns = Capsule::table('mod_cpm_help')->where('from_admin', $adminId)->where('status', 'open')
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-' . self::UNANSWERED_HOURS . ' hours')))
            ->get()->all();
        if ($noAns) {
            $refs = [];
            foreach (array_slice($noAns, 0, self::MAX_REFS) as $h) {
                $refs[] = ['kind' => 'request', 'id' => (int) $h->id,
                    'label' => Db::adminName((int) $h->to_admin) . ': ' . mb_substr((string) $h->message, 0, 36)];
            }
            $out[] = ['lvl' => 'warn', 'icon' => '⏳', 'refs' => $refs,
                'text' => (count($noAns) > 1
                    ? count($noAns) . ' ερωτήσεις σου δεν απαντήθηκαν'
                    : 'Μία ερώτησή σου δεν απαντήθηκε')
                    . ' πάνω από ' . self::UNANSWERED_HOURS . ' ώρες.'];
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Project management                                                 */
    /* ------------------------------------------------------------------ */

    private static function pm($adminId)
    {
        $done = self::doneIds();
        $out = [];

        /* ΧΩΡΙΣ ΑΝΘΡΩΠΟ: ούτε ανάδοχος ούτε μπάλα. Κανείς δεν θα την πιάσει από
           μόνος του — αυτές ακριβώς είναι η δουλειά του project manager. */
        $orphan = Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $done)
            ->where(function ($w) { $w->whereNull('assignee')->orWhere('assignee', 0); })
            ->where(function ($w) { $w->whereNull('action_user')->orWhere('action_user', 0); })
            ->orderByDesc('priority')->orderBy('created_at')->get(['id', 'title'])->all();
        if ($orphan) {
            $out[] = ['lvl' => 'bad', 'icon' => '👤', 'refs' => self::taskRefs($orphan),
                'text' => count($orphan) . ' εργασί' . (count($orphan) > 1 ? 'ες' : 'α')
                    . ' χωρίς άνθρωπο — δώσε τ' . (count($orphan) > 1 ? 'ες' : 'ην') . ' σε κάποιον.'];
        }

        /* ΧΩΡΙΣ ΠΡΟΘΕΣΜΙΑ ΚΑΙ ΣΕ ΕΞΕΛΙΞΗ: δουλεύεται κάτι που δεν έχει τέλος. */
        $noDue = Capsule::table('mod_cpm_tasks')->whereIn('status_id', Db::statusIds('work'))
            ->where(function ($w) { $w->whereNull('due_date')->orWhere('due_date', '0000-00-00'); })
            ->get(['id', 'title'])->all();
        if ($noDue) {
            $out[] = ['lvl' => 'warn', 'icon' => '🗓', 'refs' => self::taskRefs($noDue),
                'text' => (count($noDue) > 1 ? count($noDue) . ' εργασίες σε εξέλιξη' : 'Μία εργασία σε εξέλιξη')
                    . ' χωρίς προθεσμία — χωρίς ημερομηνία δεν υπάρχει καθυστέρηση.'];
        }

        /* Έργα που πέρασαν την προθεσμία τους και δεν έκλεισαν. */
        $lateP = Capsule::table('mod_cpm_projects')->whereNotNull('due_date')
            ->where('due_date', '<', date('Y-m-d'))->where('due_date', '<>', '0000-00-00')
            ->whereNotIn('status', ['archived', 'done'])->get(['id', 'name'])->all();
        if ($lateP) {
            $refs = [];
            foreach (array_slice($lateP, 0, self::MAX_REFS) as $p) {
                $refs[] = ['kind' => 'project', 'id' => (int) $p->id, 'label' => mb_substr((string) $p->name, 0, 44)];
            }
            $out[] = ['lvl' => 'bad', 'icon' => '📁', 'refs' => $refs,
                'text' => count($lateP) . ' έργ' . (count($lateP) > 1 ? 'α πέρασαν' : 'ο πέρασε')
                    . ' την προθεσμία και δεν έκλεισ' . (count($lateP) > 1 ? 'αν' : 'ε') . '.'];
        }
        return $out;
    }
}
