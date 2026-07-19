<?php
/**
 * Dashboard Redesign 2.0 — read-only data provider for the redesigned
 * clientareahome. Queries the logged-in client's services and derives the
 * metrics defined in the approved Blueprint (Real Data Mapping):
 *   hzServices, hzMonthlySpend, hzNextRenewal, hzHealth (+counts), hzHasServices.
 * SELECT-only, upgrade-safe hook. Runs only on the client-area home.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ClientAreaPage', 1, function ($vars) {
    // Only on the logged-in client-area home page.
    if (($vars['templatefile'] ?? '') !== 'clientareahome') {
        return [];
    }
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        return ['hzHasServices' => false];
    }

    try {
        $rows = Capsule::table('tblhosting as h')
            ->leftJoin('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->where('h.userid', $uid)
            ->whereNotIn('h.domainstatus', ['Cancelled', 'Terminated', 'Fraud'])
            ->orderByRaw("FIELD(h.domainstatus,'Suspended','Pending','Active')")
            ->orderBy('h.nextduedate')
            ->get(['h.id', 'h.domain', 'h.domainstatus', 'h.nextduedate', 'h.amount', 'h.billingcycle', 'p.name']);
    } catch (\Throwable $e) {
        return ['hzHasServices' => false];
    }

    $factor = ['Monthly' => 1, 'Quarterly' => 3, 'Semi-Annually' => 6, 'Annually' => 12, 'Biennially' => 24];
    $services = [];
    $monthly = 0.0;
    $counts = ['Active' => 0, 'Pending' => 0, 'Suspended' => 0];
    $nextRenewal = null;

    foreach ($rows as $r) {
        $status = $r->domainstatus;
        $counts[$status] = ($counts[$status] ?? 0) + 1;
        $services[] = [
            'id'      => $r->id,
            'name'    => $r->name ?: $r->domain,
            'domain'  => $r->domain,
            'status'  => $status,
            'nextdue' => $r->nextduedate,
            'amount'  => $r->amount,
        ];
        if ($status === 'Active') {
            if (isset($factor[$r->billingcycle])) {
                $monthly += (float) $r->amount / $factor[$r->billingcycle];
            }
            if ($r->nextduedate && $r->nextduedate !== '0000-00-00'
                && ($nextRenewal === null || $r->nextduedate < $nextRenewal)) {
                $nextRenewal = $r->nextduedate;
            }
        }
    }

    // Overall infrastructure health.
    if ($counts['Suspended'] > 0) {
        $health = ['level' => 'bad',  'count' => $counts['Suspended']];
    } elseif ($counts['Pending'] > 0) {
        $health = ['level' => 'warn', 'count' => $counts['Pending']];
    } else {
        $health = ['level' => 'ok',   'count' => $counts['Active']];
    }

    // Earliest unpaid invoice for the P1 attention banner (reliable — not
    // dependent on the template's $invoices assignment).
    $unpaid = null;
    try {
        $row = Capsule::table('tblinvoices')
            ->where('userid', $uid)->where('status', 'Unpaid')
            ->orderBy('duedate')->first(['id', 'total', 'duedate']);
        if ($row) {
            $unpaid = ['id' => $row->id, 'total' => number_format((float) $row->total, 2), 'duedate' => $row->duedate];
        }
    } catch (\Throwable $e) {}

    return [
        'hzUnpaidInvoice' => $unpaid,
        'hzHasServices'  => count($services) > 0,
        'hzServices'     => $services,
        'hzMonthlySpend' => number_format($monthly, 2),
        'hzNextRenewal'  => $nextRenewal,
        'hzHealth'       => $health,
        'hzActiveCount'  => $counts['Active'],
    ];
});
