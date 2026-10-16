<?php
/**
 * ΗΜΕΡΗΣΙΑ ΣΥΝΟΨΗ 08:00 (16/10/2026) — ΕΝΑ email ανά άνθρωπο, αντί για δεκάδες.
 *
 * Πριν: ένα email ανά υπέρβαση εκτίμησης (και μέσα στη νύχτα, όταν άλλαζε η μέρα),
 * ένα ανά «συσσώρευση», ένα ανά επιστροφή εργασιών… Τα σημαντικά χάνονταν μέσα στα
 * υπόλοιπα. Τώρα: ένα πρωινό μήνυμα με ό,τι χρειάζεται για την πρώτη ματιά της μέρας.
 *
 * Τι περιέχει (μόνο ό,τι υπάρχει — κενές ενότητες δεν εμφανίζονται):
 *   ΕΣΥ:     ανοιχτές με τη μπάλα σε σένα · εκπρόθεσμες · λήγουν σήμερα · πάνω από την
 *            εκτίμηση · χωρίς ημερομηνίες · αιτήματα/αναφορές που περιμένουν απάντηση
 *   ΟΜΑΔΑ:   (μόνο για επικεφαλής) ανά μέλος ανοιχτές/εκπρόθεσμες/υπερβάσεις + λίστα
 *            εκπρόθεσμων και υπερβάσεων με ποιος τις κρατά.
 *
 * Κανόνας της μπάλας: «δική μου» = action_user, αλλιώς ο ανάδοχος (ίδιο με cnp_scope_mine).
 * Προτιμήσεις: δεν φεύγει σε όποιον έχει «digest» ή «notify_email» = off.
 * Επανάληψη την ίδια μέρα: όχι (pref digest_sent_on).
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use Illuminate\Database\Capsule\Manager as Capsule;

class Digest
{
    const MAX_ROWS = 12;

    private static function doneIds()
    {
        static $ids = null;
        if ($ids === null) {
            $ids = array_map('intval', Capsule::table('mod_cpm_statuses')
                ->where(function ($q) { $q->where('is_done', 1)->orWhereIn('phase', ['done', 'cancel']); })
                ->pluck('id')->all()) ?: [0];
        }
        return $ids;
    }

    /** Ανοιχτές εργασίες που «κρατά» καθένας από τους $ids (κανόνας της μπάλας). */
    private static function openHeldBy(array $ids)
    {
        if (!$ids) { return []; }
        return Capsule::table('mod_cpm_tasks as t')
            ->leftJoin('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
            ->whereNotIn('t.status_id', self::doneIds())
            ->whereNull('t.completed_at')
            ->where(function ($w) use ($ids) {
                $w->whereIn('t.action_user', $ids)
                  ->orWhere(function ($x) use ($ids) {
                      $x->whereIn('t.assignee', $ids)->where(function ($y) { $y->whereNull('t.action_user')->orWhere('t.action_user', 0); });
                  });
            })
            ->orderByRaw("CASE WHEN t.due_date IS NULL OR t.due_date = '0000-00-00' THEN 1 ELSE 0 END, t.due_date, t.id")
            ->get(['t.*', 'p.name as pname'])->all();
    }

    private static function holder($t)
    {
        return (int) ($t->action_user ?: $t->assignee);
    }

    private static function due($t)
    {
        $d = (string) ($t->due_date ?? '');
        return ($d === '' || strpos($d, '0000') === 0) ? null : substr($d, 0, 10);
    }

    /** Για κάθε εργασία: ετικέτες (εκπρόθεσμη / σήμερα / υπέρβαση). */
    private static function classify(array $tasks, $today)
    {
        $out = ['open' => [], 'overdue' => [], 'today' => [], 'over' => [], 'nodates' => []];
        foreach ($tasks as $t) {
            $d = self::due($t);
            $out['open'][] = $t;
            if ($d !== null && $d < $today) { $out['overdue'][] = $t; }
            if ($d === $today) { $out['today'][] = $t; }
            if ($d === null && empty($t->start_date)) { $out['nodates'][] = $t; }
            try {
                $st = Overrun::taskStatus($t);
                $why = [];
                if ($st['hours']) { $why[] = Overrun::describe('hours', $st['hours']); }
                if ($st['days']) { $why[] = Overrun::describe('days', $st['days']); }
                if ($why) { $t->_over = implode(' · ', $why); $out['over'][] = $t; }
            } catch (\Throwable $e) { }
        }
        return $out;
    }

    private static function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    private static function dgr($d)
    {
        return $d ? date('d/m', strtotime($d)) : '';
    }

    private static function link($path)
    {
        return Notify::baseUrl() . $path;
    }

    /** Πίνακας εργασιών για το email. $who = δείξε ποιος την κρατά (ενότητα ομάδας). */
    private static function table(array $rows, $today, $who = false, $extra = null)
    {
        if (!$rows) { return ''; }
        $h = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13px">';
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $t) {
            $d = self::due($t);
            $late = $d !== null && $d < $today;
            $h .= '<tr><td style="padding:6px 0;border-bottom:1px solid #edf1f6">'
                . '<a href="' . self::e(self::link('/project/#/task/' . (int) $t->id)) . '" style="color:#131f30;text-decoration:none;font-weight:600">#'
                . (int) $t->id . ' ' . self::e(mb_substr((string) $t->title, 0, 90)) . '</a>'
                . '<div style="color:#8595ac;font-size:12px;margin-top:2px">' . self::e($t->pname ?: 'Χωρίς έργο')
                . ($who ? ' · ' . self::e(Db::adminName(self::holder($t))) : '')
                . ($extra && !empty($t->$extra) ? ' · <span style="color:#c0392b">' . self::e($t->$extra) . '</span>' : '')
                . '</div></td><td style="padding:6px 0 6px 10px;border-bottom:1px solid #edf1f6;text-align:right;white-space:nowrap;'
                . ($late ? 'color:#e2515f;font-weight:700' : 'color:#3e506a') . '">' . ($d ? self::e(self::dgr($d)) : '—') . '</td></tr>';
        }
        $h .= '</table>';
        if (count($rows) > self::MAX_ROWS) {
            $h .= '<div style="color:#8595ac;font-size:12px;margin-top:4px">+ ' . (count($rows) - self::MAX_ROWS) . ' ακόμη στην εφαρμογή</div>';
        }
        return $h;
    }

    private static function section($title, $count, $color, $body)
    {
        return '<div style="margin:18px 0 6px;font-size:14px;font-weight:800;color:' . $color . '">' . self::e($title)
            . ' <span style="display:inline-block;min-width:20px;padding:1px 7px;border-radius:10px;background:' . $color . '1a;font-size:12px">'
            . (int) $count . '</span></div>' . $body;
    }

    /** Η σύνοψη ενός ανθρώπου: ['subject','html','counts'] ή null αν δεν έχει τίποτα. */
    public static function build($adminId, $today = null)
    {
        $today = $today ?: date('Y-m-d');
        $adminId = (int) $adminId;
        $mine = self::classify(self::openHeldBy([$adminId]), $today);
        $asks = Capsule::schema()->hasTable('mod_cpm_help')
            ? Capsule::table('mod_cpm_help')->where('to_admin', $adminId)->where('status', 'open')->count() : 0;

        /* Ομάδα: μόνο όσοι είναι επικεφαλής ομάδας. Μέλη = όλων των ομάδων που διευθύνει. */
        $team = null;
        if (Capsule::schema()->hasTable('mod_cpm_team_members')) {
            $led = Capsule::table('mod_cpm_team_members')->where('admin_id', $adminId)->where('is_leader', 1)->pluck('team_id')->all();
            if ($led) {
                $members = array_values(array_diff(array_unique(array_map('intval', Capsule::table('mod_cpm_team_members')
                    ->whereIn('team_id', $led)->pluck('admin_id')->all())), [$adminId]));
                $active = $members ? array_map('intval', Capsule::table('tbladmins')->whereIn('id', $members)->where('disabled', 0)->pluck('id')->all()) : [];
                if ($active) {
                    $tAll = self::classify(self::openHeldBy($active), $today);
                    $per = [];
                    foreach ($active as $m) { $per[$m] = ['open' => 0, 'overdue' => 0, 'over' => 0]; }
                    foreach (['open', 'overdue', 'over'] as $k) {
                        foreach ($tAll[$k] as $t) { $h = self::holder($t); if (isset($per[$h])) { $per[$h][$k]++; } }
                    }
                    $team = ['all' => $tAll, 'per' => $per, 'teams' => Capsule::table('mod_cpm_teams')->whereIn('id', $led)->pluck('name')->all()];
                }
            }
        }

        $counts = ['open' => count($mine['open']), 'overdue' => count($mine['overdue']), 'today' => count($mine['today']),
            'over' => count($mine['over']), 'nodates' => count($mine['nodates']), 'asks' => $asks,
            'teamOverdue' => $team ? count($team['all']['overdue']) : 0, 'teamOver' => $team ? count($team['all']['over']) : 0];
        if (!array_sum($counts)) { return null; }

        $name = Db::adminName($adminId);
        $first = trim(explode(' ', (string) $name)[0]);
        $days = ['Κυριακή', 'Δευτέρα', 'Τρίτη', 'Τετάρτη', 'Πέμπτη', 'Παρασκευή', 'Σάββατο'];
        $tile = function ($n, $label, $color) {
            return '<td style="padding:10px 8px;text-align:center;background:#f4f7fb;border-radius:8px">'
                . '<div style="font-size:22px;font-weight:800;color:' . ($n ? $color : '#8595ac') . '">' . (int) $n . '</div>'
                . '<div style="font-size:11px;color:#3e506a">' . self::e($label) . '</div></td>';
        };
        $html = '<div style="font-size:15px;color:#131f30"><b>Καλημέρα ' . self::e($first) . '</b> — η σύνοψη της '
            . self::e($days[(int) date('w', strtotime($today))]) . ' ' . self::e(date('d/m', strtotime($today))) . '.</div>'
            . '<table cellpadding="0" cellspacing="6" style="width:100%;margin-top:10px"><tr>'
            . $tile($counts['open'], 'ανοιχτές σε σένα', '#0090dd') . $tile($counts['overdue'], 'εκπρόθεσμες', '#e2515f')
            . $tile($counts['today'], 'λήγουν σήμερα', '#eba63c') . $tile($counts['over'], 'πάνω από εκτίμηση', '#e0552b')
            . '</tr></table>';

        if ($mine['overdue']) { $html .= self::section('Εκπρόθεσμες', count($mine['overdue']), '#e2515f', self::table($mine['overdue'], $today)); }
        if ($mine['today']) { $html .= self::section('Λήγουν σήμερα', count($mine['today']), '#eba63c', self::table($mine['today'], $today)); }
        if ($mine['over']) { $html .= self::section('Πάνω από την εκτίμηση', count($mine['over']), '#e0552b', self::table($mine['over'], $today, false, '_over')); }
        if ($asks) {
            $html .= self::section('Σε ρωτούν — περιμένουν απάντηση', $asks, '#7b5cd6',
                '<div style="font-size:13px">Αιτήματα και @αναφορές που δεν έχεις απαντήσει. <a href="' . self::e(self::link('/project/#/requests')) . '">Άνοιξέ τα</a></div>');
        }
        if ($mine['nodates']) {
            $html .= self::section('Χωρίς ημερομηνίες', count($mine['nodates']), '#8595ac',
                '<div style="font-size:12.5px;color:#3e506a;margin-bottom:4px">Χωρίς έναρξη/λήξη δεν μπαίνουν σε κανένα πρόγραμμα — βάλε πότε γίνονται.</div>'
                . self::table($mine['nodates'], $today));
        }
        $rest = array_values(array_filter($mine['open'], function ($t) use ($mine) {
            foreach (['overdue', 'today', 'over', 'nodates'] as $k) { if (in_array($t, $mine[$k], true)) { return false; } }
            return true;
        }));
        if ($rest) { $html .= self::section('Υπόλοιπες ανοιχτές σε σένα', count($rest), '#0090dd', self::table($rest, $today)); }

        if ($team) {
            $html .= '<div style="margin:26px 0 4px;padding-top:14px;border-top:2px solid #e5eaf1;font-size:15px;font-weight:800;color:#131f30">Η ομάδα σου'
                . ' <span style="font-weight:400;color:#8595ac;font-size:12.5px">— ' . self::e(implode(', ', $team['teams'])) . '</span></div>';
            $rowsP = '';
            uasort($team['per'], function ($a, $b) { return ($b['overdue'] + $b['over']) <=> ($a['overdue'] + $a['over']) ?: $b['open'] <=> $a['open']; });
            foreach ($team['per'] as $m => $c) {
                $rowsP .= '<tr><td style="padding:5px 0;border-bottom:1px solid #edf1f6">' . self::e(Db::adminName($m)) . '</td>'
                    . '<td style="text-align:center;border-bottom:1px solid #edf1f6">' . $c['open'] . '</td>'
                    . '<td style="text-align:center;border-bottom:1px solid #edf1f6;' . ($c['overdue'] ? 'color:#e2515f;font-weight:700' : 'color:#8595ac') . '">' . $c['overdue'] . '</td>'
                    . '<td style="text-align:center;border-bottom:1px solid #edf1f6;' . ($c['over'] ? 'color:#e0552b;font-weight:700' : 'color:#8595ac') . '">' . $c['over'] . '</td></tr>';
            }
            $html .= '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13px;margin-top:6px">'
                . '<tr style="color:#8595ac;font-size:11.5px"><td>Μέλος</td><td style="text-align:center">ανοιχτές</td><td style="text-align:center">εκπρόθεσμες</td><td style="text-align:center">πάνω από εκτίμηση</td></tr>'
                . $rowsP . '</table>';
            if ($team['all']['overdue']) { $html .= self::section('Εκπρόθεσμες της ομάδας', count($team['all']['overdue']), '#e2515f', self::table($team['all']['overdue'], $today, true)); }
            if ($team['all']['over']) { $html .= self::section('Πάνω από την εκτίμηση — ρώτα αν χρειάζονται βοήθεια', count($team['all']['over']), '#e0552b', self::table($team['all']['over'], $today, true, '_over')); }
        }
        $html .= '<p style="margin-top:22px"><a href="' . self::e(self::link('/project/#/myday')) . '" style="background:#0090dd;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:700;display:inline-block">Άνοιξε «Η μέρα μου»</a></p>'
            . '<p style="color:#8595ac;font-size:11.5px">Αυτή η σύνοψη αντικαθιστά τα μεμονωμένα email για εκπρόθεσμες εργασίες και υπερβάσεις εκτίμησης. '
            . 'Τα ειδοποιούμε ακόμη στο καμπανάκι της εφαρμογής. Απενεργοποίηση: Το προφίλ μου → «Πρωινή σύνοψη».</p>';

        $bits = [];
        if ($counts['overdue']) { $bits[] = $counts['overdue'] . ' εκπρόθεσμες'; }
        if ($counts['today']) { $bits[] = $counts['today'] . ' λήγουν σήμερα'; }
        if ($counts['over']) { $bits[] = $counts['over'] . ' πάνω από εκτίμηση'; }
        if ($counts['teamOverdue'] || $counts['teamOver']) { $bits[] = 'ομάδα: ' . ($counts['teamOverdue'] + $counts['teamOver']) . ' θέλουν ματιά'; }
        $subject = '☀ Η μέρα σου ' . date('d/m', strtotime($today)) . ($bits ? ' — ' . implode(' · ', $bits) : ' — ' . $counts['open'] . ' ανοιχτές');
        return ['subject' => $subject, 'html' => $html, 'counts' => $counts];
    }

    /** Όλοι οι ενεργοί χρήστες· μία φορά τη μέρα ανά άνθρωπο. $only = μόνο αυτός (δοκιμή). */
    public static function run($dry = false, $only = 0, callable $log = null)
    {
        $log = $log ?: function () {};
        $today = date('Y-m-d');
        $sent = 0;
        $q = Capsule::table('tbladmins')->where('disabled', 0);
        if ($only) { $q->where('id', (int) $only); }
        foreach ($q->get(['id', 'email']) as $a) {
            $aid = (int) $a->id;
            if (!$only && (Db::pref($aid, 'digest', 'on') === 'off' || Db::pref($aid, 'notify_email', 'on') === 'off')) { continue; }
            if (!$dry && !$only && Db::pref($aid, 'digest_sent_on', '') === $today) { continue; }
            $to = Notify::adminEmail($aid);
            if (!$to) { continue; }
            try { $d = self::build($aid, $today); } catch (\Throwable $e) { $log('  #' . $aid . ' ΣΦΑΛΜΑ: ' . $e->getMessage()); continue; }
            if (!$d) { continue; }
            $log('  #' . $aid . ' ' . Db::adminName($aid) . ': ' . json_encode($d['counts']));
            if ($dry) { $sent++; continue; }
            Notify::sendTo($to, $d['subject'], $d['html']);
            if (!$only) { Db::setPref($aid, 'digest_sent_on', $today); }
            $sent++;
        }
        return $sent;
    }
}
