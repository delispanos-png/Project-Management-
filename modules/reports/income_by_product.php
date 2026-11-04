<?php

use WHMCS\Carbon;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Builds the core cash‑basis revenue query using proportional allocation.
 *
 * This query:
 * - Filters invoice items by type, paid‑date month range, and client currency
 * - Includes only real cash payments (billingnoteid = 0), excluding “Mark Paid” entries
 * - For fully paid invoices: returns full pre‑tax item amounts
 * - For partially paid invoices: allocates real cash proportionally across all items
 * - Returns both a dynamic units counter (via $unitsAlias) and cash‑basis amount
 *
 * @param string|array $types       Invoice item type(s) to include
 * @param Carbon       $dateRange   Month range used for filtering datepaid
 * @param int          $currencyid  Client currency filter
 * @param string       $unitsAlias  Alias for the units column (default: 'unitsSold')
 * @return \Illuminate\Database\Query\Builder
 */
function cashBasisQuery($types, $dateRange, $currencyid, $unitsAlias = 'unitsSold')
{
    // Pre-loaded real payments (billingnoteid = 0)
    $realPayments = Capsule::table('tblaccounts')
        ->select(
            'invoiceid',
            Capsule::raw('SUM(amountin) AS cash_received')
        )
        ->where('billingnoteid', 0)
        ->groupBy('invoiceid');

    return Capsule::table('tblinvoiceitems')
        ->join('tblinvoices', 'tblinvoices.id', '=', 'tblinvoiceitems.invoiceid')
        ->join('tblclients', 'tblclients.id', '=', 'tblinvoices.userid')

        // Join real payments
        ->leftJoinSub($realPayments, 'realPayments', function ($join) {
            $join->on('realPayments.invoiceid', '=', 'tblinvoices.id');
        })

        // Only include invoices with real payments (exclude fully Mark Paid)
        ->whereNotNull('realPayments.cash_received')

        ->whereBetween('tblinvoices.datepaid', [
            $dateRange->startOfMonth()->toDateTimeString(),
            $dateRange->endOfMonth()->toDateTimeString(),
        ])
        // Match exact types or wildcard types (LIKE) depending on the pattern
        ->where(function ($query) use ($types) {
            foreach ((array) $types as $invoiceItemType) {
                $isWildcardType = str_ends_with($invoiceItemType, '%');
                if ($isWildcardType) {
                    $query->orWhere('tblinvoiceitems.type', 'LIKE', $invoiceItemType);
                } else {
                    $query->orWhere('tblinvoiceitems.type', '=', $invoiceItemType);
                }
            }
        })

        ->where('tblclients.currency', $currencyid)

        ->select([
            // Count unitsSold only for invoices with real payments
            Capsule::raw("COUNT(DISTINCT tblinvoiceitems.id) AS {$unitsAlias}"),

            // Cash-basis revenue: fully paid items show full pre-tax amount,
            // partially paid items show proportionally allocated pre-tax amount.
            Capsule::raw("
                SUM(
                    CASE
                        -- Fully paid invoice: use full item amount (pre-tax)
                        WHEN COALESCE(realPayments.cash_received, 0) >= tblinvoices.total
                            THEN tblinvoiceitems.amount

                        -- Partially paid: proportional allocation for normal items
                        ELSE tblinvoiceitems.amount *
                            COALESCE(
                                COALESCE(realPayments.cash_received, 0) /
                                NULLIF(tblinvoices.total, 0),
                                0
                            )
                    END
                ) AS amount
            ")
        ]);
}

$pmonth = str_pad((int)$month, 2, "0", STR_PAD_LEFT);

$reportdata["title"] = "Income by Product for ".$months[(int)$month]." ".$year;
$reportdata["description"] = "This report provides a breakdown per product/service of invoices paid in a given month. Please note this excludes overpayments & other payments made to deposit funds (credit), and includes invoices paid from credit added in previous months, and thus may not match the income total for the month.";
$reportdata["currencyselections"] = true;

$reportdata["tableheadings"] = array("Product Name","Units Sold","Value");

$products = $addons = array();
$dateRange = Carbon::create(
    $year,
    $month,
    1
);

# Loop Through Products
$result = cashBasisQuery(
    ['Hosting', 'Setup', 'ProrataProduct%'],
    $dateRange,
    $currencyid
)
    ->join('tblhosting', 'tblhosting.id', '=', 'tblinvoiceitems.relid')
    ->groupBy('tblhosting.packageid')
    ->addSelect(Capsule::raw('tblhosting.packageid as packageId'))
    ->get();

foreach ($result as $data) {
    $products[$data->packageId] = [
        'amount' => $data->amount,
        'unitssold' => $data->unitsSold,
    ];
}

$result = cashBasisQuery('PromoHosting', $dateRange, $currencyid)
    ->join('tblhosting', 'tblhosting.id', '=', 'tblinvoiceitems.relid')
    ->groupBy('tblhosting.packageid')
    ->addSelect(Capsule::raw('tblhosting.packageid as packageId'))
    ->get();

foreach ($result as $data) {
    $products[$data->packageId]["amount"] += $data->amount;
}

# Loop Through Addons
$result = cashBasisQuery('Addon', $dateRange, $currencyid)
    ->join('tblhostingaddons', 'tblhostingaddons.id', '=', 'tblinvoiceitems.relid')
    ->groupBy('tblhostingaddons.addonid')
    ->addSelect(Capsule::raw('tblhostingaddons.addonid as addonId'))
    ->get();

foreach ($result as $data) {
    $addons[$data->addonId] = [
        'amount' => $data->amount,
        'unitssold' => $data->unitsSold,
    ];
}

$total = 0;
$itemtotal = 0;
$firstdone = false;
$result = Capsule::table('tblproducts')
    ->join(
        'tblproductgroups',
        'tblproductgroups.id',
        '=',
        'tblproducts.gid'
    )
    ->orderBy('tblproductgroups.order')
    ->orderBy('tblproducts.order')
    ->orderBy('tblproducts.name')
    ->get(
        [
            'tblproducts.id',
            'tblproducts.name',
            Capsule::raw('`tblproductgroups`.`name` as groupname')
        ]
    )
    ->all();
foreach ($result as $data) {
    $pid = $data->id;
    $group = $data->groupname;
    $prodname = $data->name;

    if ($group!=$prevgroup) {
        $total += $itemtotal;
        if ($firstdone) {
            $reportdata["tablevalues"][] = array('','<strong>Sub-Total</strong>','<strong>'.formatCurrency($itemtotal).'</strong>');
            $chartdata['rows'][] = array('c'=>array(array('v'=>$prevgroup),array('v'=>$itemtotal,'f'=>formatCurrency($itemtotal))));
        }
        $reportdata["tablevalues"][] = array("**<strong>$group</strong>");
        $itemtotal = 0;
    }

    $amount = $products[$pid]["amount"];
    $number = $products[$pid]["unitssold"];

    $itemtotal += $amount;

    if (!$amount) $amount="0.00";
    if (!$number) $number="0";
    $amount = formatCurrency($amount);

    $reportdata["tablevalues"][] = array($prodname,$number,$amount);

    $prevgroup = $group;
    $firstdone = true;

}

$total += $itemtotal;
$reportdata["tablevalues"][] = array('','<strong>Sub-Total</strong>','<strong>'.formatCurrency($itemtotal).'</strong>');
$chartdata['rows'][] = array('c'=>array(array('v'=>$group),array('v'=>$itemtotal,'f'=>formatCurrency($itemtotal))));

$reportdata["tablevalues"][] = array("**<strong>Addons</strong>");

$itemtotal = 0;
$result = Capsule::table('tbladdons')
    ->orderBy('name')
    ->get(
        [
            'id',
            'name',
        ]
    )
    ->all();
foreach ($result as $data) {
    $addonid = $data->id;
    $prodname = $data->name;

    $amount = $addons[$addonid]["amount"];
    $number = $addons[$addonid]["unitssold"];

    $itemtotal += $amount;

    if (!$amount) $amount="0.00";
    if (!$number) $number="0";
    $amount = formatCurrency($amount);

    $reportdata["tablevalues"][] = array($prodname,$number,$amount);

    $prevgroup = $group;

}

$itemtotal += $addons[0]["amount"];
$number = $addons[0]["unitssold"];
$amount = $addons[0]["amount"];
if (!$amount) $amount="0.00";
if (!$number) $number="0";
$reportdata["tablevalues"][] = array('Miscellaneous Custom Addons',$number,formatCurrency($amount));

$total += $itemtotal;
$reportdata["tablevalues"][] = array('','<strong>Sub-Total</strong>','<strong>'.formatCurrency($itemtotal).'</strong>');
$chartdata['rows'][] = array('c'=>array(array('v'=>"Addons"),array('v'=>$itemtotal,'f'=>formatCurrency($itemtotal))));

$itemtotal = 0;
$reportdata["tablevalues"][] = array("**<strong>Miscellaneous</strong>");

# Loop Through Billable Items
$data = cashBasisQuery('Item', $dateRange, $currencyid, 'number')
    ->first();

$itemtotal += $data->amount;
$number = $data->number;
$amount = $data->amount;
if (!$amount) $amount="0.00";
if (!$number) $number="0";
$reportdata["tablevalues"][] = array('Billable Items',$number,formatCurrency($amount));

# Loop Through Custom Invoice Line Items
$data = cashBasisQuery([''], $dateRange, $currencyid, 'number')
    ->first();

$itemtotal += $data->amount;
$number = $data->number;
$amount = $data->amount;
$reportdata["tablevalues"][] = array('Custom Invoice Line Items',$number,formatCurrency($amount));

$total += $itemtotal;
$reportdata["tablevalues"][] = array('','<strong>Sub-Total</strong>','<strong>'.formatCurrency($itemtotal).'</strong>');
$chartdata['rows'][] = array('c'=>array(array('v'=>"Miscellaneous"),array('v'=>$itemtotal,'f'=>formatCurrency($itemtotal))));

$total = formatCurrency($total);

$chartdata['cols'][] = array('label'=>'Days Range','type'=>'string');
$chartdata['cols'][] = array('label'=>'Value','type'=>'number');

$args = array();
$args['legendpos'] = 'right';

$reportdata["footertext"] = $chart->drawChart('Pie',$chartdata,$args,'300px');

$reportdata["monthspagination"] = true;
