<?php
/**
 * CloudOn Project Manager — ειδοποιήσεις email σε χειριστές (Φ3.4).
 *
 * Στέλνει απλά HTML emails στους admins (tbladmins.email):
 *   • ανάθεση task
 *   • νέο σχόλιο σε task που τους έχει ανατεθεί
 *   • δημιουργία task από recurring
 *   • ημερήσιο digest εκπρόθεσμων / σημερινών (από το cron)
 *
 * Ελέγχεται από τη ρύθμιση notify_email του module (default on).
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Notify
{
    public static function enabled()
    {
        $v = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'notify_email')->value('value');
        return $v === null || $v === 'on'; // default on
    }

    public static function adminEmail($adminId)
    {
        if (!$adminId) {
            return null;
        }
        $a = Capsule::table('tbladmins')->where('id', (int) $adminId)
            ->where('disabled', 0)->first(['email']);
        return ($a && filter_var($a->email, FILTER_VALIDATE_EMAIL)) ? $a->email : null;
    }

    public static function baseUrl()
    {
        $url = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
        return rtrim((string) $url, '/');
    }

    public static function taskLink($taskId)
    {
        return self::baseUrl() . '/cloudonadminpanel/addonmodules.php?module=cloudonprojects&tab=task&id=' . (int) $taskId;
    }

    /** Αποστολή σε συγκεκριμένο admin. Returns bool. */
    public static function send($adminId, $subject, $html)
    {
        if (!self::enabled()) {
            return false;
        }
        if (Db::pref($adminId, 'notify_email', 'on') !== 'on') {   // προσωπική εξαίρεση χρήστη
            return false;
        }
        $to = self::adminEmail($adminId);
        if (!$to) {
            return false;
        }
        return self::sendTo($to, $subject, $html);
    }

    public static function sendTo($email, $subject, $html)
    {
        $body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#243447;max-width:640px">'
            . $html
            . '<hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">'
            . '<small style="color:#8291a9">CloudOn Project Manager — αυτόματη ειδοποίηση</small></div>';
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
            . "From: CloudOn Projects <noreply@cloudon.gr>\r\n";
        return @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }

    /* ---- Συγκεκριμένες ειδοποιήσεις ---- */

    public static function assigned($taskId, $assigneeId, $byAdminId)
    {
        if (!$assigneeId || (int) $assigneeId === (int) $byAdminId) {
            return; // αυτο-ανάθεση: όχι email
        }
        $t = Db::task($taskId);
        if (!$t) {
            return;
        }
        $p = Db::project($t->project_id);
        Db::pushNotification($assigneeId, 'assign', 'Σου ανατέθηκε: ' . $t->title,
            'addonmodules.php?module=cloudonprojects&tab=task&id=' . (int) $taskId);
        self::send($assigneeId, 'Σου ανατέθηκε task: ' . $t->title,
            '<p><b>' . htmlspecialchars(Db::adminName($byAdminId)) . '</b> σου ανέθεσε το task:</p>'
            . '<p style="font-size:16px"><a href="' . self::taskLink($taskId) . '"><b>' . htmlspecialchars($t->title) . '</b></a></p>'
            . '<p>Project: <b>' . htmlspecialchars($p->name ?? '—') . '</b>'
            . ($t->due_date ? ' · Λήξη: <b>' . date('d/m/Y', strtotime($t->due_date)) . '</b>' : '') . '</p>');
    }

    public static function commented($taskId, $byAdminId, $comment)
    {
        $t = Db::task($taskId);
        if (!$t || !$t->assignee || (int) $t->assignee === (int) $byAdminId) {
            return;
        }
        Db::pushNotification($t->assignee, 'comment', 'Σχόλιο από ' . Db::adminName($byAdminId) . ': ' . $t->title,
            'addonmodules.php?module=cloudonprojects&tab=task&id=' . (int) $taskId . '#comments');
        self::send($t->assignee, 'Νέο σχόλιο στο task: ' . $t->title,
            '<p><b>' . htmlspecialchars(Db::adminName($byAdminId)) . '</b> σχολίασε στο '
            . '<a href="' . self::taskLink($taskId) . '"><b>' . htmlspecialchars($t->title) . '</b></a>:</p>'
            . '<blockquote style="border-left:3px solid #0097e4;margin:8px 0;padding:4px 12px;color:#44566c">'
            . nl2br(htmlspecialchars(mb_substr($comment, 0, 800))) . '</blockquote>');
    }

    /**
     * Ο χειριστής δήλωσε ολοκλήρωση εργασίας → ενημέρωση ΟΛΩΝ των διαχειριστών
     * (καμπανάκι + email).
     */
    public static function workDone($byAdminId, $what, $url)
    {
        $by = Db::adminName($byAdminId);
        foreach (Db::fullAccessAdminIds() as $mgr) {
            if ((int) $mgr === (int) $byAdminId) {
                continue;
            }
            Db::pushNotification($mgr, 'done', '✅ ' . $by . ' ολοκλήρωσε: ' . $what, $url);
            self::send($mgr, 'Ολοκλήρωση εργασίας: ' . $what,
                '<p><b>' . htmlspecialchars($by) . '</b> δήλωσε ότι ολοκλήρωσε την εργασία:</p>'
                . '<p style="font-size:16px"><a href="' . self::baseUrl() . '/cloudonadminpanel/' . $url . '"><b>'
                . htmlspecialchars($what) . '</b></a></p>');
        }
    }

    /** Καμπανάκι σε όλους τους watchers ενός task (εκτός του δράστη). */
    public static function watchers($taskId, $exceptAdminId, $title, $url = null)
    {
        foreach (Db::watcherIds($taskId) as $w) {
            if ((int) $w !== (int) $exceptAdminId) {
                Db::pushNotification($w, 'info', '👁 ' . $title,
                    $url ?: 'addonmodules.php?module=cloudonprojects&tab=task&id=' . (int) $taskId);
            }
        }
    }

    /** «Ζήτα ενημέρωση»: ping στον assignee (bell + email). */
    public static function requestUpdate($taskId, $byAdminId)
    {
        $t = Db::task($taskId);
        if (!$t || !$t->assignee) {
            return false;
        }
        $url = 'addonmodules.php?module=cloudonprojects&tab=task&id=' . (int) $taskId . '#comments';
        Db::pushNotification($t->assignee, 'due', '❓ ' . Db::adminName($byAdminId) . ' ζητά ενημέρωση: ' . $t->title, $url);
        self::send($t->assignee, 'Ζητήθηκε ενημέρωση: ' . $t->title,
            '<p><b>' . htmlspecialchars(Db::adminName($byAdminId)) . '</b> ρωτά πού βρίσκεται η εργασία:</p>'
            . '<p style="font-size:16px"><a href="' . self::baseUrl() . '/cloudonadminpanel/' . $url . '"><b>'
            . htmlspecialchars($t->title) . '</b></a></p><p>Απάντησε με ένα σχόλιο στο task.</p>');
        return true;
    }

    /** Στοχευμένο σχόλιο: «προς» άτομο (id) ή διαχειριστές (-1). */
    public static function commentTo($taskId, $byAdminId, $comment, $toAdmin)
    {
        $t = Db::task($taskId);
        if (!$t) {
            return;
        }
        $url = 'addonmodules.php?module=cloudonprojects&tab=task&id=' . (int) $taskId . '#comments';
        $targets = ((int) $toAdmin === -1) ? Db::fullAccessAdminIds() : [(int) $toAdmin];
        foreach ($targets as $aid) {
            if ((int) $aid === (int) $byAdminId) {
                continue;
            }
            Db::pushNotification($aid, 'comment', '💬 ' . Db::adminName($byAdminId) . ' προς εσένα: ' . mb_substr($comment, 0, 80), $url);
            self::send($aid, 'Μήνυμα από ' . Db::adminName($byAdminId) . ': ' . $t->title,
                '<p><b>' . htmlspecialchars(Db::adminName($byAdminId)) . '</b> σου έγραψε στο '
                . '<a href="' . self::baseUrl() . '/cloudonadminpanel/' . $url . '"><b>' . htmlspecialchars($t->title) . '</b></a>:</p>'
                . '<blockquote style="border-left:3px solid #0097e4;margin:8px 0;padding:4px 12px;color:#44566c">'
                . nl2br(htmlspecialchars(mb_substr($comment, 0, 800))) . '</blockquote>');
        }
    }

    /** Αποστολή υπενθύμισης (από το pulse cron). */
    public static function reminder($r)
    {
        $title = '⏰ Υπενθύμιση' . ($r->note ? ': ' . $r->note : '');
        $url = $r->task_id ? 'addonmodules.php?module=cloudonprojects&tab=task&id=' . (int) $r->task_id : null;
        Db::pushNotification($r->admin_id, 'due', $title, $url);
        $t = $r->task_id ? Db::task($r->task_id) : null;
        self::send($r->admin_id, 'Υπενθύμιση' . ($t ? ': ' . $t->title : ''),
            '<p>⏰ ' . htmlspecialchars($r->note ?: 'Υπενθύμιση που όρισες.') . '</p>'
            . ($t ? '<p><a href="' . self::baseUrl() . '/cloudonadminpanel/addonmodules.php?module=cloudonprojects&tab=task&id=' . (int) $t->id . '"><b>'
                . htmlspecialchars($t->title) . '</b></a></p>' : ''));
    }

    public static function recurringCreated($taskId, $assigneeId)
    {
        if (!$assigneeId) {
            return;
        }
        $t = Db::task($taskId);
        if (!$t) {
            return;
        }
        $p = Db::project($t->project_id);
        Db::pushNotification($assigneeId, 'recurring', 'Προγραμματισμένη εργασία: ' . $t->title,
            'addonmodules.php?module=cloudonprojects&tab=task&id=' . (int) $taskId);
        self::send($assigneeId, 'Προγραμματισμένη εργασία: ' . $t->title,
            '<p>Δημιουργήθηκε προγραμματισμένη (επαναλαμβανόμενη) εργασία για σένα:</p>'
            . '<p style="font-size:16px"><a href="' . self::taskLink($taskId) . '"><b>' . htmlspecialchars($t->title) . '</b></a></p>'
            . '<p>Project: <b>' . htmlspecialchars($p->name ?? '—') . '</b>'
            . ($t->due_date ? ' · Λήξη: <b>' . date('d/m/Y', strtotime($t->due_date)) . '</b>' : '') . '</p>');
    }

    /** Ημερήσιο digest: εκπρόθεσμα + σημερινά ανά admin. Returns πόσα emails στάλθηκαν. */
    public static function dailyDigest()
    {
        if (!self::enabled()) {
            return 0;
        }
        $today = date('Y-m-d');
        $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
        $rows = Capsule::table('mod_cpm_tasks as t')
            ->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
            ->select('t.id', 't.title', 't.due_date', 't.assignee', 'p.name as project_name')
            ->whereNotNull('t.assignee')->whereNotNull('t.due_date')
            ->where('t.due_date', '<=', $today)
            ->whereNotIn('t.status_id', $doneIds)
            ->orderBy('t.due_date')->get();
        $byAdmin = [];
        foreach ($rows as $r) {
            $byAdmin[(int) $r->assignee][] = $r;
        }

        // follow-ups πωλήσεων (leads με «επόμενη ενέργεια» σήμερα ή εκπρόθεσμη)
        $followByAdmin = [];
        try {
            if (Capsule::schema()->hasTable('mod_cpm_leads')) {
                foreach (Capsule::table('mod_cpm_leads')
                    ->whereNotNull('assignee')->whereNotNull('next_action')
                    ->where('next_action', '<=', $today)
                    ->whereNotIn('stage', ['won', 'lost'])->orderBy('next_action')->get() as $ld) {
                    $followByAdmin[(int) $ld->assignee][] = $ld;
                }
            }
            // follow-ups ΠΕΛΑΤΩΝ από καταγεγραμμένες επικοινωνίες (CRM)
            if (Capsule::schema()->hasTable('mod_cpm_interactions')) {
                foreach (Capsule::table('mod_cpm_interactions')
                    ->whereNotNull('followup_date')->where('followup_done', 0)
                    ->whereNull('lead_id')->whereNotNull('clientid')->whereNotNull('admin_id')
                    ->where('followup_date', '<=', $today)->orderBy('followup_date')->get() as $fi) {
                    $cl = Capsule::table('tblclients')->where('id', $fi->clientid)->first(['firstname', 'lastname', 'companyname']);
                    $followByAdmin[(int) $fi->admin_id][] = (object) [
                        'id'          => null,
                        'company'     => $cl ? ($cl->companyname ?: trim($cl->firstname . ' ' . $cl->lastname)) : ('Πελάτης #' . $fi->clientid),
                        'contact'     => '',
                        'phone'       => '',
                        'next_action' => $fi->followup_date,
                        'next_note'   => trim(($fi->followup_note ?: $fi->summary) . ''),
                        'clientid'    => $fi->clientid,
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        $sent = 0;
        foreach (array_unique(array_merge(array_keys($byAdmin), array_keys($followByAdmin))) as $adminId) {
            if (Db::pref($adminId, 'digest', 'on') !== 'on') {     // προσωπική εξαίρεση digest
                continue;
            }
            $tasks = $byAdmin[$adminId] ?? [];
            $follows = $followByAdmin[$adminId] ?? [];
            $h = '<p>Καλημέρα!</p>';
            if ($tasks) {
                $h .= '<p><b>Εργασίες</b> που λήγουν σήμερα ή είναι εκπρόθεσμες:</p><ul>';
                foreach ($tasks as $r) {
                    $over = $r->due_date < $today;
                    $h .= '<li><a href="' . self::taskLink($r->id) . '">' . htmlspecialchars($r->title) . '</a>'
                        . ' <small>(' . htmlspecialchars($r->project_name) . ')</small> — '
                        . ($over ? '<b style="color:#c0392b">εκπρόθεσμο από ' . date('d/m', strtotime($r->due_date)) . '</b>'
                                 : '<b>λήγει σήμερα</b>') . '</li>';
                }
                $h .= '</ul>';
            }
            if ($follows) {
                $base = self::baseUrl() . '/cloudonadminpanel/addonmodules.php?module=cloudonprojects';
                $h .= '<p><b>Follow-ups πωλήσεων</b>:</p><ul>';
                foreach ($follows as $ld) {
                    $over = $ld->next_action < $today;
                    $url = !empty($ld->id) ? $base . '&tab=lead&id=' . (int) $ld->id
                        : $base . '&tab=client&client=' . (int) ($ld->clientid ?? 0);
                    $h .= '<li><a href="' . $url . '">' . htmlspecialchars($ld->company ?: $ld->contact) . '</a>'
                        . ($ld->phone ? ' <small>' . htmlspecialchars($ld->phone) . '</small>' : '')
                        . ($ld->next_note ? ' — ' . htmlspecialchars($ld->next_note) : '')
                        . ($over ? ' <b style="color:#c0392b">(από ' . date('d/m', strtotime($ld->next_action)) . ')</b>' : ' <b>(σήμερα)</b>') . '</li>';
                }
                $h .= '</ul>';
            }
            $n = count($tasks) + count($follows);
            if ($n && self::send($adminId, 'Η μέρα σου: ' . count($tasks) . ' εργασίες, ' . count($follows) . ' follow-ups', $h)) {
                $sent++;
            }
        }
        return $sent;
    }
}
