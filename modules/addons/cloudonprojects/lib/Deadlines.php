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
        return $sent;
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

            $proj = Capsule::table('mod_cpm_projects')->where('id', $t->project_id)->first(['id', 'name', 'manager_id']);
            $to = self::recipients($t, $proj, $level);
            if (!$to) {
                continue;   // ορφανή εργασία — δεν έχει νόημα ειδοποίηση σε κανέναν
            }

            $title = mb_substr((string) $t->title, 0, 90);
            $what = self::phrase($days);
            $subj = ($days < 0 ? '⛔ Εκπρόθεσμη εργασία: ' : '⏳ Προθεσμία εργασίας: ') . $title;
            $line = $title . ' — ' . $what . ($proj ? ' · ' . $proj->name : '');
            $url = '/project/#/board/' . (int) $t->project_id;

            foreach ($to as $adminId => $role) {
                if (!self::claim('task', (int) $t->id, $level, (int) $adminId, $dry)) {
                    continue;
                }
                if (!$dry) {
                    Db::pushNotification((int) $adminId, $days < 0 ? 'overdue' : 'due',
                        ($days < 0 ? '⛔ ' : '⏳ ') . $line, $url);
                    /* Email μόνο στην κλιμάκωση: ο υπεύθυνος πρέπει να το δει
                       ακόμη κι αν δεν ανοίξει την εφαρμογή σήμερα. */
                    if ($level === 'over' && $role === 'manager') {
                        Notify::send((int) $adminId,
                            $subj,
                            '<p><b>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</b></p>'
                            . '<p>' . htmlspecialchars($what, ENT_QUOTES, 'UTF-8') . '.'
                            . ($proj ? ' Έργο: ' . htmlspecialchars($proj->name, ENT_QUOTES, 'UTF-8') . '.' : '')
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
