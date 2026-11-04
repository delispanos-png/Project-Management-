<?php

use WHMCS\Billing\BillingNote\ItemType;
use WHMCS\Billing\BillingNote\NoteType;
use WHMCS\Carbon;
use WHMCS\Database\Capsule;

function getLateFeeTotals(array $invoiceIds): array
{
    if (empty($invoiceIds)) {
        return [];
    }

    $lateFees = Capsule::table('tblaccounts')
        ->join('tblbillingnotes', 'tblbillingnotes.id', '=', 'tblaccounts.billingnoteid')
        ->join('tblbillingnoteitems', 'tblbillingnoteitems.billingnote_id', '=', 'tblbillingnotes.id')
        ->whereIn('tblaccounts.invoiceid', $invoiceIds)
        ->where('tblbillingnotes.note_type', NoteType::Debit->value)
        ->where('tblbillingnoteitems.type', ItemType::InvoiceLateFee->value)
        ->select(
            'tblaccounts.invoiceid',
            Capsule::raw('SUM(tblbillingnotes.subtotal) as subtotal'),
            Capsule::raw('SUM(tblbillingnotes.tax) as tax'),
            Capsule::raw('SUM(tblbillingnotes.tax2) as tax2'),
            Capsule::raw('SUM(tblbillingnotes.total) as total')
        )
        ->groupBy('tblaccounts.invoiceid')
        ->get();

    $map = [];
    foreach ($lateFees as $fee) {
        $map[$fee->invoiceid] = $fee;
    }

    return $map;
}

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$reportdata["title"] = "Sales Tax Liability";
$reportdata["description"] = "This report shows sales tax liability for the selected period";

$reportdata["currencyselections"] = true;

$range = App::getFromRequest('range');
if (!$range) {
    $today = Carbon::today()->endOfDay();
    $lastWeek = Carbon::today()->subDays(6)->startOfDay();
    $range = $lastWeek->toAdminDateFormat() . ' - ' . $today->toAdminDateFormat();
}
$currencyID = (int) $currencyid;

$reportdata['headertext'] = '';
if (!$print) {
    $reportdata['headertext'] = <<<HTML
<form method="post" action="reports.php?report={$report}&currencyid={$currencyid}&calculate=true">
    <div class="report-filters-wrapper">
        <div class="inner-container">
            <h3>Filters</h3>
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="inputFilterDate">{$dateRangeText}</label>
                        <div class="form-group date-picker-prepend-icon">
                            <label for="inputFilterDate" class="field-icon">
                                <i class="fal fa-calendar-alt"></i>
                            </label>
                            <input id="inputFilterDate"
                                   type="text"
                                   name="range"
                                   value="{$range}"
                                   class="form-control date-picker-search"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                {$aInt->lang('reports', 'generateReport')}
            </button>
        </div>
    </div>
</form>
HTML;
}

if ($calculate) {
    $dateRange = Carbon::parseDateRangeValue($range);
    $queryStartDate = $dateRange['from']->toDateTimeString();
    $queryEndDate = $dateRange['to']->toDateTimeString();

    // Fetch invoiceID, userID, total, credit, tax, tax2 for PAID invoices 
    $paidInvoices = Capsule::table('tblinvoices')
        ->select(
            'tblinvoices.id',
            'tblinvoices.userid',
            'tblinvoices.total',
            'tblinvoices.credit',
            'tblinvoices.tax',
            'tblinvoices.tax2'
        )
        ->join('tblclients', 'tblclients.id', '=', 'tblinvoices.userid')
        ->leftJoin('tblinvoiceitems', function ($join) {
            $join->on('tblinvoiceitems.invoiceid', '=', 'tblinvoices.id');
            $join->on(function ($join) {
                $join->on('tblinvoiceitems.type', '=', Capsule::raw('"Add Funds"'))
                     ->orOn('tblinvoiceitems.type', '=', Capsule::raw('"Invoice"'));
            });
        })
        ->whereBetween('tblinvoices.datepaid', [$queryStartDate, $queryEndDate])
        ->where('tblinvoices.status', '=', 'Paid')
        ->where('tblclients.currency', '=', $currencyID)
        ->whereNull('tblinvoiceitems.id')
        ->get();

    $invoiceCount = $paidInvoices->count();
    $grandTotal = 0;
    $grandTax = 0;
    $grandTax2 = 0;

    $invoiceIds = $paidInvoices->pluck('id')
        ->all();

    // Step 1: Preload late-fee debit note totals for all relevant invoices to avoid N+1 queries.
    $lateFeeTotals = getLateFeeTotals($invoiceIds);

    // Step 2: Loop through each paid invoice
    foreach ($paidInvoices as $inv) {
        // Step 3: Add invoice totals
        $grandTotal += ($inv->total + $inv->credit);
        $grandTax += $inv->tax;
        $grandTax2 += $inv->tax2;

        // Step 4: Add late-fee debit note totals for this invoice (if they exist)
        if (isset($lateFeeTotals[$inv->id])) {
            $lateFeeNote = $lateFeeTotals[$inv->id];
            $grandTotal += $lateFeeNote->total;
            $grandTax += $lateFeeNote->tax;
            $grandTax2 += $lateFeeNote->tax2;
        }
    }

    $html = <<<HTML
    <br>%d Invoices Found<br>
    <b>Total Invoiced:</b> %s &nbsp;
    <b>Tax Level 1 Liability:</b> %s &nbsp;
    <b>Tax Level 2 Liability:</b> %s
    HTML;

    $reportdata['headertext'] .= sprintf(
        $html,
        $invoiceCount,
        formatCurrency($grandTotal),
        formatCurrency($grandTax),
        formatCurrency($grandTax2)
    );
}

$reportdata['headertext'] .= "</center>";

$reportdata['tableheadings'] = array(
    $aInt->lang('fields', 'invoiceid'),
    $aInt->lang('fields', 'clientname'),
    $aInt->lang('fields', 'invoicedate'),
    $aInt->lang('fields', 'datepaid'),
    $aInt->lang('fields', 'subtotal'),
    $aInt->lang('fields', 'tax'),
    $aInt->lang('fields', 'credit'),
    $aInt->lang('fields', 'total'),
);

$results = Capsule::table('tblinvoices')
    ->select('tblinvoices.*', 'tblclients.firstname', 'tblclients.lastname')
    ->distinct()
    ->join('tblclients', 'tblclients.id', '=', 'tblinvoices.userid')
    ->leftJoin('tblinvoiceitems', function ($join) {
        $join->on('tblinvoiceitems.invoiceid', '=', 'tblinvoices.id');
        $join->on(function ($join) {
            $join
                ->on('tblinvoiceitems.type', '=', Capsule::raw('"Add Funds"'))
                ->orOn('tblinvoiceitems.type', '=', Capsule::raw('"Invoice"'));
        });
    })
    ->whereBetween('tblinvoices.datepaid', [$queryStartDate, $queryEndDate])
    ->where('tblinvoices.status', '=', 'Paid')
    ->where('tblclients.currency', '=', $currencyID)
    ->whereNull('tblinvoiceitems.id')
    ->orderBy('date', 'asc')
    ->get()
    ->all();

foreach ($results as $result) {
    // use the preloaded late-fee totals above to avoid duplicate queries.
    $lateFeeSubtotal = $lateFeeTotals[$result->id]->subtotal ?? 0;
    $lateFeeTax = $lateFeeTotals[$result->id]->tax ?? 0;
    $lateFeeTax2 = $lateFeeTotals[$result->id]->tax2 ?? 0;
    $lateFeeTotal = $lateFeeTotals[$result->id]->total ?? 0;

    $client = "{$result->firstname} {$result->lastname}";
    $date = fromMySQLDate($result->date);
    $datepaid = fromMySQLDate($result->datepaid);
    $currency = getCurrency($result->userid);

    $subtotal = $result->subtotal + $lateFeeSubtotal;
    $credit = $result->credit;
    $tax = ($result->tax + $result->tax2 + $lateFeeTax + $lateFeeTax2);
    $total = ($result->total + $credit + $lateFeeTotal);

    $reportdata["tablevalues"][] = [
        "{$result->id}",
        "{$client}",
        "{$date}",
        "{$datepaid}",
        format_as_currency($subtotal),
        format_as_currency($tax),
        format_as_currency($credit),
        format_as_currency($total),
    ];
}

$data['footertext'] = 'This report excludes invoices that affect a clients credit balance '
    . 'since this income will be counted and reported when it is applied to invoices for products/services.';
