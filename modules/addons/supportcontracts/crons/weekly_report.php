<?php
/**
 * Support Contracts — weekly client statement.
 * At the end of each week, email every client whose prepaid-time balance moved
 * in the last 7 days: the week's movements (top-ups + usage), which ticket each
 * usage was for, totals, and the current balance.
 *
 * Schedule (Friday 18:00):
 *   0 18 * * 5 <user> /opt/plesk/php/8.3/bin/php -q .../crons/weekly_report.php >/dev/null 2>&1
 *
 * Dry-run (compose but DON'T send, print a summary):  SC_DRY=1 php weekly_report.php
 *
 * @package WHMCS\Module\Addon\SupportContracts
 */

$whmcsRoot = dirname(__DIR__, 4);
require_once $whmcsRoot . '/init.php';
require_once dirname(__DIR__) . '/lib/Db.php';
require_once dirname(__DIR__) . '/lib/Format.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\SupportContracts\Db;

$dry = (getenv('SC_DRY') === '1');

try {
    if (!Capsule::schema()->hasTable('mod_supportcontracts_ledger')) {
        exit(0);
    }
    // gate: enabled unless explicitly off (dry-run always runs)
    $enabled = Capsule::table('tbladdonmodules')->where('module', 'supportcontracts')
        ->where('setting', 'weekly_report')->value('value');
    if (!$dry && $enabled === 'off') {
        exit(0);
    }

    $adminUser = Capsule::table('tbladmins')->where('disabled', 0)->orderBy('id')->value('username');
    $templateName = supportcontracts_ensure_email_template(); // editable WHMCS email template
    $since = date('Y-m-d H:i:s', strtotime('-7 days'));
    $fromLbl = date('d/m/Y', strtotime('-7 days'));
    $toLbl   = date('d/m/Y');

    $userIds = array_unique(array_merge(
        array_map('intval', Capsule::table('mod_supportcontracts_ledger')
            ->where('created_at', '>=', $since)->distinct()->pluck('userid')->all()),
        array_map('intval', Capsule::table('mod_supportcontracts_worklog')
            ->where('created_at', '>=', $since)->distinct()->pluck('userid')->all())
    ));

    $sent = 0;
    foreach ($userIds as $uid) {
        $uid = (int) $uid;
        $c = Db::contract($uid);
        if (!$c || !$c->enabled) {
            continue;
        }
        // topups/adjusts from the ledger + ALL work entries (billable + non-billable) from the worklog
        $ledgerRows = Capsule::table('mod_supportcontracts_ledger')
            ->where('userid', $uid)->where('created_at', '>=', $since)
            ->where('type', '<>', 'usage')->orderBy('id')->get();
        $workRows = Db::worklogSince($uid, $since);
        if (!count($ledgerRows) && !count($workRows)) {
            continue;
        }
        $events = [];
        foreach ($ledgerRows as $r) {
            $events[] = ['at' => $r->created_at, 'kind' => 'ledger', 'r' => $r];
        }
        foreach ($workRows as $w) {
            $events[] = ['at' => $w->created_at, 'kind' => 'work', 'r' => $w];
        }
        usort($events, function ($a, $b) { return strcmp($a['at'], $b['at']); });

        $body = '';
        $added = 0;            // hours purchased/added this week
        $billableTotal = 0;    // charged minutes this week
        $nonbillableTotal = 0; // non-billable worked minutes this week
        $rowsCount = count($events);
        $td = 'style="padding:6px;border-bottom:1px solid #eee"';
        foreach ($events as $ev) {
            $r = $ev['r'];
            if ($ev['kind'] === 'ledger') {
                $typeL = ['topup' => 'Αγορά', 'adjust' => 'Προσαρμογή'][$r->type] ?? $r->type;
                if ((int) $r->minutes > 0) {
                    $added += (int) $r->minutes;
                }
                $color = ((int) $r->minutes < 0) ? '#c0392b' : '#157347';
                $timeCell = '<b style="color:' . $color . '">' . ((int) $r->minutes > 0 ? '+' : '')
                    . supportcontracts_fmt_minutes((int) $r->minutes) . '</b>';
                $ticket = '—';
            } else {
                $ticket = '—';
                if ($r->ticketid) {
                    $tk = Capsule::table('tbltickets')->where('id', $r->ticketid)->first(['tid', 'title']);
                    if ($tk) {
                        $ticket = '#' . (int) $tk->tid . ' ' . htmlspecialchars($tk->title);
                    }
                }
                if ((int) $r->billable) {
                    $billableTotal += (int) $r->charged_minutes;
                    $typeL = 'Εργασία (χρεώσιμη)';
                    $timeCell = '<b style="color:#c0392b">-' . supportcontracts_fmt_minutes((int) $r->charged_minutes) . '</b>';
                    if ((int) $r->charged_minutes !== (int) $r->worked_minutes) {
                        $timeCell .= ' <span style="color:#888;font-size:12px">(εργασία ' . supportcontracts_fmt_minutes((int) $r->worked_minutes) . ')</span>';
                    }
                } else {
                    $nonbillableTotal += (int) $r->worked_minutes;
                    $typeL = 'Εργασία (χωρίς χρέωση)';
                    $timeCell = '<b style="color:#157347">' . supportcontracts_fmt_minutes((int) $r->worked_minutes) . '</b>'
                        . ' <span style="color:#888;font-size:12px">δωρεάν</span>';
                }
            }
            $body .= '<tr><td ' . $td . '>' . substr($r->created_at, 0, 16) . '</td>'
                . '<td ' . $td . '>' . $typeL . '</td>'
                . '<td ' . $td . '>' . $ticket . '</td>'
                . '<td ' . $td . ' align="right">' . $timeCell . '</td></tr>';
        }

        // --- merge vars for the editable WHMCS email template ---
        $tableHtml = '<table style="width:100%;border-collapse:collapse;font-size:14px">'
            . '<tr style="background:#f4f7fc"><th align="left" style="padding:6px">Ημ/νία</th><th align="left" style="padding:6px">Κίνηση</th>'
            . '<th align="left" style="padding:6px">Αίτημα</th><th align="right" style="padding:6px">Χρόνος</th></tr>'
            . $body . '</table>';
        $balance = supportcontracts_fmt_minutes($c->balance_minutes);
        $lowNotice = ((int) $c->balance_minutes <= 120)
            ? '<p style="color:#c0392b"><b>Το υπόλοιπό σας είναι χαμηλό.</b> Μπορείτε να προμηθευτείτε επιπλέον ώρες υποστήριξης μέσα από τον λογαριασμό σας.</p>'
            : '';
        $clientName = trim((string) Capsule::table('tblclients')->where('id', $uid)->value('companyname'));
        if ($clientName === '') {
            $cl = Capsule::table('tblclients')->where('id', $uid)->first(['firstname', 'lastname']);
            $clientName = $cl ? trim($cl->firstname . ' ' . $cl->lastname) : 'πελάτη';
        }
        $mergeVars = [
            'client_name'        => $clientName,
            'period'             => $fromLbl . ' – ' . $toLbl,
            'movements_table'    => $tableHtml,
            'used_total'         => supportcontracts_fmt_minutes($billableTotal), // back-compat
            'added_total'        => supportcontracts_fmt_minutes($added),
            'billable_total'     => supportcontracts_fmt_minutes($billableTotal),
            'nonbillable_total'  => supportcontracts_fmt_minutes($nonbillableTotal),
            'balance'            => $balance,
            'low_balance_notice' => $lowNotice,
        ];

        // custom recipients from the contract (else the client's own account email)
        $custom = [];
        if (!empty($c->report_email)) {
            foreach (preg_split('/[,;\s]+/', $c->report_email) as $e) {
                $e = trim($e);
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $custom[] = $e;
                }
            }
        }

        if ($dry) {
            $who = $custom ? implode(', ', $custom) : ('[λογαριασμός] ' . Capsule::table('tblclients')->where('id', $uid)->value('email'));
            echo '  [DRY] → ' . $who . ' | κινήσεις=' . $rowsCount
                . ' | με χρέωση=' . supportcontracts_fmt_minutes($billableTotal)
                . ' | χωρίς χρέωση=' . supportcontracts_fmt_minutes($nonbillableTotal)
                . ' | υπόλοιπο=' . $balance . "\n";
            $sent++;
            continue;
        }

        if ($custom) {
            // custom recipients → render the SAME template ourselves, send via mail()
            $tpl = Capsule::table('tblemailtemplates')->where('name', $templateName)->first(['subject', 'message']);
            $subject = $tpl->subject ?? 'Εβδομαδιαία κίνηση προαγορασμένου χρόνου υποστήριξης';
            $bodyHtml = $tpl->message ?? '';
            foreach ($mergeVars as $k => $v) {
                $subject  = str_replace('{$' . $k . '}', $v, $subject);
                $bodyHtml = str_replace('{$' . $k . '}', $v, $bodyHtml);
            }
            $from = Capsule::table('tblconfiguration')->where('setting', 'Email')->value('value')
                ?: ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $fromName = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value') ?: 'Support';
            $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
                . 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . ">\r\n";
            $htmlBody = '<html><body style="font-family:Arial,Helvetica,sans-serif;color:#222">' . $bodyHtml . '</body></html>';
            $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            foreach ($custom as $to) {
                @mail($to, $encSubject, $htmlBody, $headers);
            }
        } else {
            // client account email → proper WHMCS template send (SMTP, logged, editable)
            try {
                localAPI('SendEmail', [
                    'messagename' => $templateName,
                    'id'          => $uid,
                    'customvars'  => base64_encode(serialize($mergeVars)),
                ], $adminUser);
            } catch (\Throwable $e) {
                // non-fatal
            }
        }
        $sent++;
    }
    if ($dry) {
        echo "  [DRY] σύνολο πελατών με κίνηση αυτή την εβδομάδα: $sent (κανένα email δεν στάλθηκε)\n";
    }
} catch (\Throwable $e) {
    error_log('supportcontracts weekly_report: ' . $e->getMessage());
}
