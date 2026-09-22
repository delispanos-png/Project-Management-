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
            $per = [];
            foreach ($rows as $t) {
                $who = (int) ($t->action_user ?: $t->assignee);
                $per[$who] = ($per[$who] ?? 0) + 1;
            }
            arsort($per);
            $bits = [];
            foreach (array_slice($per, 0, 4, true) as $w => $n) {
                $bits[] = Db::adminName($w) . ' ' . $n;
            }
            $out[] = ['lvl' => 'bad', 'icon' => '🔴', 'refs' => self::taskRefs($rows),
                'text' => (count($rows) > 1 ? count($rows) . ' εκπρόθεσμες' : 'Μία εκπρόθεσμη')
                    . ' στην ομάδα — ' . implode(' · ', $bits) . '.'];
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
