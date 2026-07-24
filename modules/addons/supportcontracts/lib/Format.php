<?php
/**
 * Shared formatter — used by both the addon (supportcontracts.php) and its
 * hooks (hooks.php). Guarded so loading it from both places never redeclares.
 */

if (!function_exists('supportcontracts_weekly_template_name')) {
    function supportcontracts_weekly_template_name()
    {
        return 'Support Contract Weekly Statement';
    }
}

if (!function_exists('supportcontracts_ensure_email_template')) {
    /** Create the editable WHMCS email template for the weekly statement (idempotent). */
    function supportcontracts_ensure_email_template()
    {
        $name = supportcontracts_weekly_template_name();
        $tbl = \WHMCS\Database\Capsule::table('tblemailtemplates');
        if ($tbl->where('name', $name)->exists()) {
            return $name;
        }
        $message = <<<'HTML'
<p>Αγαπητέ {$client_name},</p>
<p>Ακολουθεί η κίνηση του <strong>προαγορασμένου χρόνου υποστήριξης</strong> για την περίοδο <strong>{$period}</strong>:</p>
{$movements_table}
<p style="margin-top:14px">Προστέθηκαν αυτή την εβδομάδα: <strong>{$added_total}</strong></p>
<p style="font-size:15px"><strong>Σύνολο ωρών με χρέωση: {$billable_total}</strong> · Σύνολο ωρών χωρίς χρέωση: <strong>{$nonbillable_total}</strong></p>
<p style="font-size:17px;margin-top:10px">Τρέχον υπόλοιπο: <strong>{$balance}</strong></p>
{$low_balance_notice}
HTML;
        \WHMCS\Database\Capsule::table('tblemailtemplates')->insert([
            'type'      => 'general',
            'name'      => $name,
            'subject'   => 'Εβδομαδιαία κίνηση προαγορασμένου χρόνου υποστήριξης',
            'message'   => $message,
            'custom'    => 1,
            'language'  => '',
            'copyto'    => '',
            'disabled'  => 0,
            'plaintext' => 0,
        ]);
        return $name;
    }
}

if (!function_exists('supportcontracts_defaults')) {
    /** Default contract settings (from module config) for auto-created contracts. */
    function supportcontracts_defaults()
    {
        $cfg = [];
        foreach (\WHMCS\Database\Capsule::table('tbladdonmodules')->where('module', 'supportcontracts')->get() as $r) {
            $cfg[$r->setting] = $r->value;
        }
        $unit = ($cfg['default_response_unit'] ?? 'hours') === 'days' ? 'days' : 'hours';
        return [
            'sla_response_value' => (int) ($cfg['default_response_value'] ?? 8),
            'sla_response_unit'  => $unit,
            'biz_days'  => $cfg['default_biz_days'] ?? '1,2,3,4,5',
            'biz_start' => $cfg['default_biz_start'] ?? '09:00',
            'biz_end'   => $cfg['default_biz_end'] ?? '17:00',
            'biz_tz'    => $cfg['default_tz'] ?? 'Europe/Athens',
            'priority'  => 0,
        ];
    }
}

if (!function_exists('supportcontracts_fmt_minutes')) {
    /** Minutes → "Xω Y′" (Greek hours/minutes), signed. */
    function supportcontracts_fmt_minutes($mins)
    {
        $mins = (int) $mins;
        $neg = $mins < 0;
        $mins = abs($mins);
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        $s = $h > 0 ? ($m > 0 ? "{$h}ω {$m}′" : "{$h}ω") : "{$m}′";
        return ($neg ? '-' : '') . $s;
    }
}
