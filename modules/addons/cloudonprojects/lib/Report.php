<?php
/**
 * CloudOn Project Manager — αναφορά προαγορασμένου χρόνου προς τον πελάτη.
 *
 * Μία πηγή για τρεις χρήσεις: την προεπισκόπηση μέσα στο PM, τη χειροκίνητη
 * αποστολή, και το cron. Η ανάλυση είναι **ανά έργο/υπηρεσία** με τις επιμέρους
 * εγγραφές από κάτω — ο πελάτης βλέπει πρώτα πού πήγε ο χρόνος και μετά τη
 * λεπτομέρεια. Κλείνει πάντα με το τι του απομένει.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

require_once __DIR__ . '/Cover.php';

class Report
{
    const TEMPLATE = 'Support Contract Statement';

    /** Το επεξεργάσιμο template του WHMCS (idempotent). */
    public static function ensureTemplate()
    {
        $tbl = Capsule::table('tblemailtemplates');
        if ($tbl->where('name', self::TEMPLATE)->exists()) {
            return self::TEMPLATE;
        }
        $msg = <<<'HTML'
<p>Αγαπητέ {$client_name},</p>
<p>Ακολουθεί η ανάλυση της <strong>υποστήριξης και των εργασιών</strong> που έγιναν
για λογαριασμό σας την περίοδο <strong>{$period}</strong>.</p>
{$breakdown_table}
{$topups_table}
<p style="font-size:17px;margin-top:16px">Διαθέσιμο υπόλοιπο προαγοράς: <strong>{$balance}</strong></p>
{$offer_notice}
{$open_notice}
{$low_balance_notice}
<p style="margin-top:16px;color:#666;font-size:13px">Είμαστε στη διάθεσή σας για οποιαδήποτε διευκρίνιση.</p>
HTML;
        $tbl->insert(['type' => 'general', 'name' => self::TEMPLATE,
            'subject' => 'Ανάλυση υποστήριξης και υπόλοιπο προαγοράς — {$period}',
            'message' => $msg, 'custom' => 1, 'language' => '', 'copyto' => '',
            'disabled' => 0, 'plaintext' => 0]);
        return self::TEMPLATE;
    }

    /* ══════════════ σύνθεση ══════════════ */

    /**
     * Χτίζει την αναφορά μιας περιόδου.
     *
     * @param string $from ημερομηνία (Y-m-d), περιλαμβάνεται
     * @param string $to   ημερομηνία (Y-m-d), περιλαμβάνεται
     * @return array{subject:string, html:string, to:array, vars:array, empty:bool}
     */
    public static function build($userid, $from, $to)
    {
        $userid = (int) $userid;
        $f = $from . ' 00:00:00';
        $t = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
        $bd = Cover::breakdown($userid, $f, $t);
        $tops = Cover::topups($userid, $f, $t);
        $st = Cover::clientState($userid);
        $period = date('d/m/Y', strtotime($from)) . ' – ' . date('d/m/Y', strtotime($to));

        $td = 'style="padding:7px 9px;border-bottom:1px solid #eee"';
        $th = 'style="padding:7px 9px;text-align:left;font-size:12px;color:#667;letter-spacing:.4px"';

        /* ── ανά έργο/υπηρεσία, με τη λεπτομέρεια από κάτω ── */
        $rows = '';
        foreach ($bd['groups'] as $g) {
            $tags = [];
            if ($g['prepaid']) { $tags[] = self::tag('από την προαγορά ' . Cover::fmt($g['prepaid']), '#157347'); }
            if ($g['offer'])   { $tags[] = self::tag('από προσφορά ' . Cover::fmt($g['offer']), '#0d6efd'); }
            if ($g['open'])    { $tags[] = self::tag('εκτός κάλυψης ' . Cover::fmt($g['open']), '#c0392b'); }
            if ($g['free'])    { $tags[] = self::tag('χωρίς χρέωση ' . Cover::fmt($g['free']), '#6c757d'); }
            $rows .= '<tr style="background:#f4f7fc"><td ' . $td . ' colspan="2"><strong>'
                . htmlspecialchars($g['name']) . '</strong><div style="margin-top:3px">'
                . implode(' ', $tags) . '</div></td>'
                . '<td ' . $td . ' align="right"><strong>' . Cover::fmt($g['charged'] ?: $g['worked'])
                . '</strong></td></tr>';
            foreach ($g['items'] as $i) {
                $amt = $i['billable'] ? Cover::fmt($i['charged'])
                    : '<span style="color:#6c757d">' . Cover::fmt($i['worked']) . ' — χωρίς χρέωση</span>';
                $rows .= '<tr><td ' . $td . ' style="padding-left:22px;color:#666;white-space:nowrap">'
                    . date('d/m', strtotime($i['at'])) . '</td>'
                    . '<td ' . $td . '>' . htmlspecialchars($i['what'])
                    . ($i['note'] ? ' <span style="color:#888">· ' . htmlspecialchars($i['note']) . '</span>' : '')
                    . '</td><td ' . $td . ' align="right">' . $amt . '</td></tr>';
            }
        }
        $T = $bd['totals'];
        $table = $rows
            ? '<table style="width:100%;border-collapse:collapse;font-size:14px;margin-top:10px">'
                . '<tr><th ' . $th . ' style="width:64px">Ημ/νία</th><th ' . $th . '>Εργασία</th>'
                . '<th ' . $th . ' style="text-align:right">Χρόνος</th></tr>' . $rows
                . '<tr><td ' . $td . ' colspan="2" style="border-top:2px solid #ddd"><strong>Σύνολο περιόδου</strong>'
                . '<div style="color:#666;font-size:13px;margin-top:2px">δουλεμένος χρόνος '
                . Cover::fmt($T['worked']) . ($T['free'] ? ' · χωρίς χρέωση ' . Cover::fmt($T['free']) : '') . '</div></td>'
                . '<td ' . $td . ' align="right" style="border-top:2px solid #ddd"><strong style="font-size:16px">'
                . Cover::fmt($T['charged']) . '</strong></td></tr></table>'
            : '<p style="color:#666">Δεν καταγράφηκε χρόνος αυτή την περίοδο.</p>';

        /* ── πιστώσεις της περιόδου ── */
        $topTable = '';
        if ($tops) {
            $tr = '';
            foreach ($tops as $x) {
                $tr .= '<tr><td ' . $td . ' style="white-space:nowrap;color:#666">'
                    . date('d/m/Y', strtotime($x['at'])) . '</td>'
                    . '<td ' . $td . '>' . htmlspecialchars($x['note'] ?: ($x['type'] === 'topup' ? 'Αγορά χρόνου' : 'Διόρθωση')) . '</td>'
                    . '<td ' . $td . ' align="right"><strong style="color:' . ($x['minutes'] < 0 ? '#c0392b' : '#157347') . '">'
                    . ($x['minutes'] > 0 ? '+' : '') . Cover::fmt($x['minutes']) . '</strong></td></tr>';
            }
            $topTable = '<h3 style="margin:18px 0 4px;font-size:15px">Πιστώσεις περιόδου</h3>'
                . '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $tr . '</table>';
        }

        /* ── τι απομένει ── */
        $offerNotice = '';
        if ($st['offerLeft'] > 0) {
            $offerNotice = '<p style="color:#0d6efd">Επιπλέον, <strong>' . Cover::fmt($st['offerLeft'])
                . '</strong> καλύπτονται από εγκεκριμένες προσφορές σε ισχύ.</p>';
        }
        $openNotice = '';
        if ($T['open'] > 0) {
            $openNotice = '<p style="color:#c0392b"><strong>' . Cover::fmt($T['open'])
                . '</strong> από τις παραπάνω εργασίες δεν καλύπτονται από την προαγορά σας.'
                . ' Θα λάβετε σχετική προσφορά.</p>';
        }
        $lowMin = (int) round(((float) (Capsule::table('tbladdonmodules')->where('module', 'supportcontracts')
            ->where('setting', 'low_balance_hours')->value('value') ?: 2)) * 60);
        $lowNotice = ($st['contract'] && $st['balance'] <= $lowMin)
            ? '<p style="color:#c0392b"><strong>Το υπόλοιπό σας είναι χαμηλό.</strong> Μπορείτε να'
                . ' προμηθευτείτε επιπλέον ώρες υποστήριξης μέσα από τον λογαριασμό σας.</p>'
            : '';

        $vars = [
            'client_name'      => self::clientName($userid),
            'period'           => $period,
            'breakdown_table'  => $table,
            'topups_table'     => $topTable,
            'balance'          => Cover::fmt($st['balance']),
            'offer_notice'     => $offerNotice,
            'open_notice'      => $openNotice,
            'low_balance_notice' => $lowNotice,
            // Συμβατότητα με το παλιό εβδομαδιαίο template.
            'movements_table'  => $table,
            'billable_total'   => Cover::fmt($T['charged']),
            'nonbillable_total' => Cover::fmt($T['free']),
            'used_total'       => Cover::fmt($T['charged']),
            'added_total'      => Cover::fmt(array_sum(array_map(function ($x) {
                return max(0, $x['minutes']);
            }, $tops))),
        ];

        $html = '<p>Αγαπητέ ' . htmlspecialchars($vars['client_name']) . ',</p>'
            . '<p>Ακολουθεί η ανάλυση της <strong>υποστήριξης και των εργασιών</strong> που έγιναν'
            . ' για λογαριασμό σας την περίοδο <strong>' . $period . '</strong>.</p>'
            . $table . $topTable
            . '<p style="font-size:17px;margin-top:16px">Διαθέσιμο υπόλοιπο προαγοράς: <strong>'
            . Cover::fmt($st['balance']) . '</strong></p>'
            . $offerNotice . $openNotice . $lowNotice;

        return ['subject' => 'Ανάλυση υποστήριξης και υπόλοιπο προαγοράς — ' . $period,
            'html' => $html, 'to' => self::recipients($userid), 'vars' => $vars,
            'empty' => !$rows && !$tops];
    }

    private static function tag($txt, $color)
    {
        return '<span style="display:inline-block;font-size:12px;color:' . $color
            . ';border:1px solid ' . $color . '55;border-radius:9px;padding:1px 7px;margin-right:4px">'
            . $txt . '</span>';
    }

    public static function clientName($userid)
    {
        $c = Capsule::table('tblclients')->where('id', (int) $userid)
            ->first(['companyname', 'firstname', 'lastname']);
        if (!$c) {
            return 'πελάτη';
        }
        return trim($c->companyname) ?: (trim($c->firstname . ' ' . $c->lastname) ?: 'πελάτη');
    }

    /** Παραλήπτες: ό,τι λέει το συμβόλαιο, αλλιώς το email του λογαριασμού. */
    public static function recipients($userid)
    {
        $custom = '';
        if (Capsule::schema()->hasTable('mod_supportcontracts_clients')) {
            $custom = (string) Capsule::table('mod_supportcontracts_clients')
                ->where('userid', (int) $userid)->value('report_email');
        }
        $list = array_values(array_filter(array_map('trim', explode(',', $custom)),
            function ($e) { return filter_var($e, FILTER_VALIDATE_EMAIL); }));
        if ($list) {
            return $list;
        }
        $mail = (string) Capsule::table('tblclients')->where('id', (int) $userid)->value('email');
        return filter_var($mail, FILTER_VALIDATE_EMAIL) ? [$mail] : [];
    }

    /* ══════════════ αποστολή ══════════════ */

    /**
     * Στέλνει την αναφορά. Όταν οι παραλήπτες είναι το email του λογαριασμού,
     * περνάει από το template του WHMCS (SMTP, καταγραφή, επεξεργάσιμο).
     * Όταν είναι δικοί μας παραλήπτες, στέλνεται απευθείας.
     */
    public static function send($userid, $from, $to, $adminUser = null)
    {
        $r = self::build($userid, $from, $to);
        if (!$r['to']) {
            return ['ok' => false, 'error' => 'Δεν υπάρχει παραλήπτης'];
        }
        $acct = (string) Capsule::table('tblclients')->where('id', (int) $userid)->value('email');
        $viaTemplate = (count($r['to']) === 1 && strcasecmp($r['to'][0], $acct) === 0);
        if ($viaTemplate && function_exists('localAPI')) {
            self::ensureTemplate();
            $adminUser = $adminUser ?: Capsule::table('tbladmins')->where('disabled', 0)
                ->orderBy('id')->value('username');
            try {
                localAPI('SendEmail', ['messagename' => self::TEMPLATE, 'id' => (int) $userid,
                    'customvars' => base64_encode(serialize($r['vars']))], $adminUser);
                return ['ok' => true, 'to' => $r['to'], 'via' => 'template'];
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }
        $fromMail = Capsule::table('tblconfiguration')->where('setting', 'Email')->value('value')
            ?: ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromName = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value') ?: 'Support';
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
            . 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromMail . ">\r\n";
        $body = '<html><body style="font-family:Arial,Helvetica,sans-serif;color:#222">' . $r['html'] . '</body></html>';
        $subj = '=?UTF-8?B?' . base64_encode($r['subject']) . '?=';
        $ok = false;
        foreach ($r['to'] as $addr) {
            $ok = @mail($addr, $subj, $body, $headers) || $ok;
        }
        return ['ok' => $ok, 'to' => $r['to'], 'via' => 'mail'];
    }

    /** Ποιοι πελάτες θέλουν αναφορά με αυτή τη συχνότητα. */
    public static function dueClients($freq)
    {
        if (!Capsule::schema()->hasTable('mod_supportcontracts_clients')) {
            return [];
        }
        $out = [];
        foreach (Capsule::table('mod_supportcontracts_clients')->where('enabled', 1)->get() as $c) {
            $f = Capsule::schema()->hasColumn('mod_supportcontracts_clients', 'report_freq')
                ? ($c->report_freq ?: 'monthly') : 'monthly';
            if ($f === 'off') {
                continue;
            }
            if ($f === $freq || $f === 'both') {
                $out[] = (int) $c->userid;
            }
        }
        return $out;
    }
}
