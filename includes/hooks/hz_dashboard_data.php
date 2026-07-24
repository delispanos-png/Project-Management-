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

    // Total amount across all unpaid invoices (for the invoices KPI detail).
    $unpaidTotal = 0.0;
    try {
        $unpaidTotal = (float) Capsule::table('tblinvoices')
            ->where('userid', $uid)->where('status', 'Unpaid')->sum('total');
    } catch (\Throwable $e) {}

    // Recent published announcements (last 14 days) — powers the "new" badge.
    $newAnnounce = 0;
    try {
        $newAnnounce = (int) Capsule::table('tblannouncements')
            ->where('published', 1)
            ->where('date', '>=', date('Y-m-d H:i:s', strtotime('-14 days')))
            ->count();
    } catch (\Throwable $e) {}

    // Payment reliability (on-time %) — how punctually the client pays.
    $payRel = null;
    try {
        $paid = Capsule::table('tblinvoices')
            ->where('userid', $uid)->where('status', 'Paid')
            ->get(['duedate', 'datepaid']);
        $paidCount = count($paid);
        if ($paidCount > 0) {
            $onTime = 0;
            foreach ($paid as $inv) {
                $dp = ($inv->datepaid && $inv->datepaid !== '0000-00-00 00:00:00') ? strtotime($inv->datepaid) : 0;
                $du = ($inv->duedate  && $inv->duedate  !== '0000-00-00')          ? strtotime($inv->duedate)  : 0;
                if ($dp && $du && ($dp - $du) <= 86400) { // paid on/before due (1-day grace) = on time
                    $onTime++;
                }
            }
            $rate  = (int) round($onTime / $paidCount * 100);
            $level = $rate >= 90 ? 'ok' : ($rate >= 70 ? 'warn' : 'bad');
            $payRel = ['rate' => $rate, 'level' => $level, 'onTime' => $onTime, 'total' => $paidCount];
        }
    } catch (\Throwable $e) {}

    // Last actual payment received (transaction date + amount).
    $lastPayment = null;
    try {
        $acc = Capsule::table('tblaccounts')->where('userid', $uid)
            ->where('amountin', '>', 0)->orderBy('date', 'desc')->first(['date', 'amountin']);
        if ($acc) {
            $lastPayment = ['date' => substr((string) $acc->date, 0, 10), 'amount' => number_format((float) $acc->amountin, 2)];
        }
    } catch (\Throwable $e) {}

    // Open-balance aging — unpaid invoices grouped by due month, so the client
    // sees FROM WHICH month debt is open and HOW it escalates over time.
    $openByMonth = [];
    try {
        $u = Capsule::table('tblinvoices')->where('userid', $uid)->where('status', 'Unpaid')
            ->get(['duedate', 'total']);
        $byM = [];
        $curYm = date('Y-m');
        foreach ($u as $inv) {
            $ym = ($inv->duedate && $inv->duedate !== '0000-00-00') ? substr((string) $inv->duedate, 0, 7) : $curYm;
            $byM[$ym] = ($byM[$ym] ?? 0) + (float) $inv->total;
        }
        if ($byM) {
            ksort($byM);
            if (count($byM) > 12) {
                $byM = array_slice($byM, -12, 12, true); // keep the 12 most recent months readable
            }
            $max = max($byM);
            foreach ($byM as $ym => $amt) {
                $openByMonth[] = [
                    'ym'      => $ym,
                    'short'   => substr($ym, 5, 2) . '/' . substr($ym, 2, 2), // MM/YY (language-neutral)
                    'amount'  => number_format($amt, 2),
                    'pct'     => $max > 0 ? (int) round($amt / $max * 100) : 0,
                    'overdue' => ($ym < $curYm),
                ];
            }
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
        'hzUnpaidTotal'      => number_format($unpaidTotal, 2),
        'hzNewAnnouncements' => $newAnnounce,
        'hzPayReliability'   => $payRel,
        'hzLastPayment'      => $lastPayment,
        'hzOpenByMonth'      => $openByMonth,
    ];
});
