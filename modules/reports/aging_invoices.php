<?php

use WHMCS\Billing\BillingNote\Status as BillingNoteStatus;
use WHMCS\Billing\Invoice;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$reportdata["title"] = "Aging Invoices";
$reportdata["description"] = "A summary of outstanding invoices broken down "
    . "into the period of which they are overdue";

$reportdata["tableheadings"][] = "Period";

foreach ($currencies as $currencyid => $currencyname) {
    $reportdata["tableheadings"][] = "{$currencyname} Amount";
}

$totals = [];
for ( $day = 0; $day < 120; $day += 30) {
    $startdate = date(
        "Y-m-d",
        mktime(0, 0, 0, date("m"), (date("d") - $day), date("Y"))
    );
    $enddate = date(
        "Y-m-d",
        mktime(0, 0, 0, date("m"), (date("d") - ($day + 30)), date("Y"))
    );
    $rowdata = [];
    $rowdata[] = "{$day} - " . ($day + 30);

    $currencytotals = [];

    $query = <<<SQL
SELECT 
    `tblclients`.`currency`, 
    SUM(tblinvoices.total) AS `sum`, 
    (
        SELECT COALESCE(SUM(`tblaccounts`.`amountin` - `tblaccounts`.`amountout`), 0) FROM `tblaccounts`
            INNER JOIN `tblinvoices` ON `tblinvoices`.`id` = `tblaccounts`.`invoiceid`
            INNER JOIN `tblclients` AS `t2` ON `t2`.`id` = `tblinvoices`.`userid`
            LEFT JOIN `tblbillingnotes` ON `tblaccounts`.`billingnoteid` = `tblbillingnotes`.`id`                                                                              
        WHERE `tblinvoices`.`duedate` <= ?
            AND `tblinvoices`.`duedate` >= ?
            AND `tblinvoices`.`status` = ?
            AND `t2`.`currency` = `tblclients`.`currency`
            AND (
                `tblbillingnotes`.`id` IS NULL 
                    OR `tblbillingnotes`.`status` = ?
                )
    ) AS `sum2` 

FROM `tblinvoices`
    INNER JOIN `tblclients` ON `tblclients`.`id` = `tblinvoices`.`userid`

WHERE `tblinvoices`.`duedate` <= ?
  AND `tblinvoices`.`duedate` >= ?
  AND `tblinvoices`.`status` = ?

GROUP BY `tblclients`.`currency`;
SQL;

    $results = Capsule::select(
        $query,
        [
            $startdate,
            $enddate,
            Invoice::STATUS_UNPAID,
            BillingNoteStatus::Closed->value,
            $startdate,
            $enddate,
            Invoice::STATUS_UNPAID,
        ]
    );

    foreach ($results as $result) {
        $currencytotals[$result->currency] = ($result->sum - $result->sum2);
    }

    foreach ($currencies as $currencyid => $currencyname) {
        $currencyamount = $currencytotals[$currencyid] ?? 0;
        if (!isset($totals[$currencyid])) {
            $totals[$currencyid] = 0;
        }
        $totals[$currencyid] += $currencyamount;
        $currency = getCurrency(null, $currencyid);
        $rowdata[] = formatCurrency($currencyamount);
        if ($currencyid == $defaultcurrencyid) {
            $chartdata['rows'][] = [
                'c' => [
                    ['v' => "{$day} - " . ($day + 30)],
                    [
                        'v' => $currencyamount,
                        'f' => formatCurrency($currencyamount),
                    ],
                ]
            ];
        }
    }
    $reportdata["tablevalues"][] = $rowdata;
}

$startdate = date(
    "Y-m-d",
    mktime(0, 0, 0, date("m"), (date("d") - 120), date("Y"))
);
$rowdata = [];
$rowdata[] = "120 +";

$currencytotals = [];
$results = Capsule::table('tblinvoices')
    ->select(
        'tblclients.currency',
        Capsule::raw('sum(tblinvoices.total) as `sum`')
    )
    ->join('tblclients', 'tblclients.id', '=', 'tblinvoices.userid')
    ->where('tblinvoices.duedate', '<=', $startdate)
    ->where('tblinvoices.status', '=', 'Unpaid')
    ->groupBy('tblclients.currency')
    ->get()
    ->all();
foreach ($results as $result) {
    $currencytotals[$result->currency] = $result->sum;
}

foreach ($currencies as $currencyid => $currencyname) {
    $currencyamount = $currencytotals[$currencyid] ?? 0;
    if (!isset($totals[$currencyid])) {
        $totals[$currencyid] = 0;
    }
    $totals[$currencyid] += $currencyamount;
    $currency = getCurrency(null, $currencyid);
    $rowdata[] = formatCurrency($currencyamount);
    if ($currencyid == $defaultcurrencyid) {
        $chartdata['rows'][] = [
            'c' => [
                ['v' => "{$day} + "],
                [
                    'v' => $currencyamount,
                    'f' => formatCurrency($currencyamount),
                ],
            ]
        ];
    }

}
$reportdata["tablevalues"][] = $rowdata;

$rowdata = [];
$rowdata[] = "<b>Total</b>";
foreach ($currencies as $currencyid => $currencyname) {
    $currencytotal = $totals[$currencyid];
    if (!$currencytotal) {
        $currencytotal=0;
    }
    $currency = getCurrency(null, $currencyid);
    $rowdata[] = "<b>" . formatCurrency($currencytotal) . "</b>";
}
$reportdata["tablevalues"][] = $rowdata;

$chartdata['cols'][] = ['label'=>'Days Range', 'type'=>'string'];
$chartdata['cols'][] = ['label'=>'Value', 'type'=>'number'];

$args = [];
$args['legendpos'] = 'right';

$reportdata["footertext"] = $chart->drawChart('Pie', $chartdata, $args, '300px');
