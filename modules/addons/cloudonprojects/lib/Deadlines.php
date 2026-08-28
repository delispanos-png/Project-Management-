<?php

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Notify.php';

/**
 * Κύκλωμα προθεσμιών: ειδοποιεί ΠΡΙΝ χαθεί η ημερομηνία, και κλιμακώνει όταν
 * χαθεί — ώστε να προλάβουμε να μιλήσουμε στον πελάτη για rescheduling αντί να
 * το μάθει εκείνος πρώτος.
 *
 * Κλίμακα:
 *   t-3  τρεις μέρες πριν   → όποιος τη δουλεύει (ανάθεση + μπάλα)
 *   t-1  μία μέρα πριν      → όποιος τη δουλεύει
 *   t0   ημέρα λήξης        → + υπεύθυνος έργου, + χειριστής του ticket
 *   over εκπρόθεσμο         → ίδιοι, μία φορά την ημέρα, με email στον υπεύθυνο
 *
 * Κάθε (εργασία, επίπεδο, παραλήπτης, ημέρα) στέλνεται ΜΙΑ φορά — ο cron τρέχει
 * κάθε 10΄ και χωρίς αυτό θα έστελνε την ίδια προειδοποίηση 144 φορές τη μέρα.
 */
class Deadlines
{
    /** Πόσες μέρες πριν χτυπάει η πρώτη προειδοποίηση. */
    const WARN_DAYS = [3, 1];

    /** Πόσες μέρες συνεχίζει να υπενθυμίζει ένα εκπρόθεσμο πριν σιωπήσει. */
    const OVERDUE_MAX_DAYS = 30;

    public static function run($dry = false)
    {
        $sent = 0;
        $sent += self::tasks($dry);
        $sent += self::projects($dry);
        $sent += self::offers($dry);
        return $sent;
    }

    /**
     * Προσφορά που λήγει χωρίς απάντηση. Είναι το πιο ακριβό «ξέχασμα» των
     * πωλήσεων: η ισχύς περνά, ο πελάτης δεν απάντησε, και χάνεται η ευκαιρία
     * να ξαναχτυπήσουμε όσο ήταν ζεστός.
     */
    private static function offers($dry)
    {
        $today = strtotime('today');
        $n = 0;

        $rows = Capsule::table('mod_cpm_offers as o')
            ->leftJoin('tblquotes as q', 'q.id', '=', 'o.quoteid')
            ->whereNotIn('o.stage', ['accepted', 'lost'])
            ->select('o.*', 'q.validuntil')->get();

        foreach ($rows as $o) {
            if ($o->reply === 'yes' || $o->reply === 'no') {
                continue;   // απαντήθηκε — δεν εκκρεμεί ειδοποίηση
            }
            /* Ισχύς από το WHMCS quote· αν η προσφορά δεν είναι δεμένη σε quote,
               μετράει η δική της ημερομηνία-στόχος — αλλιώς οι χειροκίνητες
               προσφορές δεν θα ειδοποιούσαν ποτέ. */
            $valid = (string) ($o->validuntil ?: '');
            if ($valid === '' || strpos($valid, '0000') === 0) {
                $valid = (string) ($o->expected_close ?: '');
            }
            if ($valid === '' || strpos($valid, '0000') === 0) {
                continue;   // χωρίς ημερομηνία δεν υπάρχει τι να λήξει
            }
            $days = (int) floor((strtotime(substr($valid, 0, 10)) - $today) / 86400);
            $level = self::level($days);
            if ($level === null || ($level === 'over' && $days < -3)) {
                continue;   // μετά από 3 μέρες λήξης έχει ειπωθεί — δεν επαναλαμβάνουμε
            }

            $to = [];
            if ($o->assignee) { $to[(int) $o->assignee] = 'assignee'; }
            if ($o->created_by) { $to[(int) $o->created_by] = 'owner'; }
            if (!$to) { continue; }

            $what = $days < 0 ? 'έληξε χωρίς απάντηση' : self::phrase($days) . ' χωρίς απάντηση';
            $line = 'Προσφορά «' . mb_substr((string) $o->title, 0, 60) . '» — ' . $what;
            foreach ($to as $adminId => $role) {
                if (!self::claim('offer', (int) $o->id, $level, (int) $adminId, $dry)) {
                    continue;
                }
                if (!$dry) {
                    Db::pushNotification((int) $adminId, $days < 0 ? 'overdue' : 'due',
                        ($days < 0 ? '⛔ ' : '⏳ ') . $line, '/project/#/offers');
                }
                $n++;
            }
        }
        return $n;
    }

    /* ───────────────────────── Εργασίες ───────────────────────── */

    private static function tasks($dry)
    {
        $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
        $today = strtotime('today');
        $n = 0;

        $rows = Capsule::table('mod_cpm_tasks')
            ->whereNotNull('due_date')->where('due_date', '!=', '0000-00-00')
            ->whereNotIn('status_id', $doneIds)->get();

        foreach ($rows as $t) {
            $days = (int) floor((strtotime((string) $t->due_date) - $today) / 86400);
            $level = self::level($days);
            if ($level === null) {
                continue;
            }
            // Πολύ παλιό εκπρόθεσμο: έχει ήδη ειπωθεί, δεν το κάνουμε θόρυβο.
            if ($level === 'over' && $days < -self::OVERDUE_MAX_DAYS) {
                continue;
            }

            $proj = $t->project_id
                ? Capsule::table('mod_cpm_projects')->where('id', $t->project_id)->first(['id', 'name', 'manager_id'])
                : null;
            /* Χωρίς έργο, το «πού ανήκει» είναι το department. */
            $where = $proj ? $proj->name
                : (isset($t->dept_id) && $t->dept_id
                    ? (string) Capsule::table('tblticketdepartments')->where('id', $t->dept_id)->value('name')
                    : '');
            $to = self::recipients($t, $proj, $level);
            if (!$to) {
                continue;   // ορφανή εργασία — δεν έχει νόημα ειδοποίηση σε κανέναν
            }

            $title = mb_substr((string) $t->title, 0, 90);
            $what = self::phrase($days);
            $subj = ($days < 0 ? '⛔ Εκπρόθεσμη εργασία: ' : '⏳ Προθεσμία εργασίας: ') . $title;
            $line = $title . ' — ' . $what . ($where !== '' ? ' · ' . $where : '');
            /* Εργασία χωρίς έργο δεν έχει board — στέλνουμε στο department της. */
            $url = $t->project_id
                ? '/project/#/board/' . (int) $t->project_id
                : (isset($t->dept_id) && $t->dept_id ? '/project/#/unit/' . (int) $t->dept_id : '/project/#/list');

            foreach ($to as $adminId => $role) {
                if (!self::claim('task', (int) $t->id, $level, (int) $adminId, $dry)) {
                    continue;
                }
                if (!$dry) {
                    Db::pushNotification((int) $adminId, $days < 0 ? 'overdue' : 'due',
                        ($days < 0 ? '⛔ ' : '⏳ ') . $line, $url);
                    /* Email μόνο στην κλιμάκωση: ο υπεύθυνος πρέπει να το δει
                       ακόμη κι αν δεν ανοίξει την εφαρμογή σήμερα. */
                    if ($level === 'over' && ($role === 'manager' || $role === 'lead')) {
                        Notify::send((int) $adminId,
                            $subj,
                            '<p><b>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</b></p>'
                            . '<p>' . htmlspecialchars($what, ENT_QUOTES, 'UTF-8') . '.'
                            . ($where !== '' ? ($proj ? ' Έργο: ' : ' Department: ')
                                . htmlspecialchars($where, ENT_QUOTES, 'UTF-8') . '.' : '')
                            . '</p><p>Χρειάζεται απόφαση: νέα ημερομηνία ή ενημέρωση του πελάτη.</p>');
                    }
                }
                $n++;
            }
        }
        return $n;
    }

    /* ───────────────────────── Έργα ───────────────────────── */

    private static function projects($dry)
    {
        $today = strtotime('today');
        $n = 0;

        foreach (Capsule::table('mod_cpm_projects')->whereNotNull('due_date')
                     ->where('due_date', '!=', '0000-00-00')->get() as $p) {
            if (in_array((string) $p->status, ['archived', 'done'], true)) {
                continue;
            }
            $days = (int) floor((strtotime((string) $p->due_date) - $today) / 86400);
            $level = self::level($days);
            if ($level === null || ($level === 'over' && $days < -self::OVERDUE_MAX_DAYS)) {
                continue;
            }

            /* Στο έργο ειδοποιείται ο υπεύθυνος· αν δεν έχει οριστεί, τα μέλη —
               αλλιώς η προθεσμία του έργου δεν θα την έβλεπε κανείς. */
            $to = [];
            if ($p->manager_id) {
                $to[(int) $p->manager_id] = 'manager';
            } else {
                foreach (Capsule::table('mod_cpm_project_members')->where('project_id', $p->id)
                             ->pluck('admin_id') as $m) {
                    $to[(int) $m] = 'member';
                }
            }
            if (!$to) {
                continue;
            }

            $line = 'Έργο «' . mb_substr((string) $p->name, 0, 70) . '» — ' . self::phrase($days);
            foreach ($to as $adminId => $role) {
                if (!self::claim('project', (int) $p->id, $level, (int) $adminId, $dry)) {
                    continue;
                }
                if (!$dry) {
                    Db::pushNotification((int) $adminId, $days < 0 ? 'overdue' : 'due',
                        ($days < 0 ? '⛔ ' : '⏳ ') . $line, '/project/#/board/' . (int) $p->id);
                }
                $n++;
            }
        }
        return $n;
    }

    /* ───────────────────────── Βοηθητικά ───────────────────────── */

    /** Σε ποιο σκαλί της κλίμακας βρισκόμαστε — null αν δεν χρειάζεται τίποτα. */
    private static function level($days)
    {
        if ($days < 0) {
            return 'over';
        }
        if ($days === 0) {
            return 't0';
        }
        return in_array($days, self::WARN_DAYS, true) ? 't-' . $days : null;
    }

    private static function phrase($days)
    {
        if ($days < 0) {
            $d = abs($days);
            return 'εκπρόθεσμη ' . $d . ($d === 1 ? ' ημέρα' : ' ημέρες');
        }
        if ($days === 0) {
            return 'λήγει σήμερα';
        }
        return $days === 1 ? 'λήγει αύριο' : 'λήγει σε ' . $days . ' ημέρες';
    }

    /**
     * Ποιοι ειδοποιούνται: πρώτα όποιος τη δουλεύει, και από την ημέρα λήξης και
     * μετά κλιμακώνει σε υπεύθυνο έργου και στον χειριστή του ticket — εκεί
     * κρίνεται αν θα ειδοποιηθεί ο πελάτης.
     */
    private static function recipients($t, $proj, $level)
    {
        $to = [];
        if ($t->assignee) {
            $to[(int) $t->assignee] = 'assignee';
        }
        if ($t->action_user) {
            $to[(int) $t->action_user] = 'ball';
        }
        if ($level === 't0' || $level === 'over') {
            if ($proj && $proj->manager_id) {
                $to[(int) $proj->manager_id] = 'manager';
            }
            if ($t->ticketid) {
                $flag = (int) Capsule::table('tbltickets')->where('id', $t->ticketid)->value('flag');
                if ($flag) {
                    $to[$flag] = isset($to[$flag]) ? $to[$flag] : 'ticket';
                }
            }
        }
        /* Εργασία χωρίς έργο δεν έχει manager να κλιμακώσει. Ανήκει όμως σε
           department, και το department το εξυπηρετούν ομάδες: ειδοποιούμε τους
           επικεφαλής τους. Αλλιώς μια χαμένη προθεσμία σε ticket-εργασία θα
           έφτανε μόνο στον ανάδοχο — δηλαδή σε αυτόν που ήδη την έχασε. */
        if ($level === 't0' || $level === 'over') {
            $did = isset($t->dept_id) ? (int) $t->dept_id : 0;
            if ($did && Capsule::schema()->hasTable('mod_cpm_team_depts')) {
                $leads = Capsule::table('mod_cpm_team_depts as td')
                    ->join('mod_cpm_team_members as m', 'm.team_id', '=', 'td.team_id')
                    ->where('td.dept_id', $did)->where('m.is_leader', 1)
                    ->distinct()->pluck('m.admin_id')->all();
                foreach ($leads as $lid) {
                    if (!isset($to[(int) $lid])) {
                        $to[(int) $lid] = 'lead';
                    }
                }
            }
        }
        // Ο ίδιος ο ενεργός χειριστής μπορεί να είναι και τα δύο — το array κλειδί το λύνει.
        return array_filter($to, function ($r, $id) { return $id > 0; }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Κρατάει τη θέση για (είδος, id, επίπεδο, παραλήπτης, σήμερα). Επιστρέφει
     * false αν έχει ήδη σταλεί. Το UNIQUE index είναι η πραγματική εγγύηση —
     * δύο cron που τρέχουν μαζί δεν μπορούν να στείλουν διπλό.
     */
    private static function claim($kind, $refId, $level, $adminId, $dry)
    {
        if ($adminId <= 0) {
            return false;
        }
        // Το «over» επαναλαμβάνεται μία φορά την ημέρα· τα υπόλοιπα μία και καλή.
        $day = date('Y-m-d');
        if ($level !== 'over') {
            $exists = Capsule::table('mod_cpm_deadline_alerts')->where('kind', $kind)
                ->where('ref_id', $refId)->where('level', $level)->where('admin_id', $adminId)->exists();
            if ($exists) {
                return false;
            }
        }
        if ($dry) {
            return true;
        }
        try {
            Capsule::table('mod_cpm_deadline_alerts')->insert([
                'kind' => $kind, 'ref_id' => $refId, 'level' => $level,
                'admin_id' => $adminId, 'sent_on' => $day, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return false;   // duplicate key = κάποιος άλλος πρόλαβε
        }
        return true;
    }
}
