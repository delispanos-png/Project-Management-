{* ==========================================================================
   Cloud On — Invoices page (Dashboard 2.0 aesthetic).
   Replaces the WHMCS DataTable with clean invoice cards. Preserves $invoices.
   ========================================================================== *}
<div class="hz-dash">

  <div class="hz-dash-head">
    <div class="hz-dash-headings">
      <h1 class="hz-dash-title">{$LANG.invoices}</h1>
    </div>
  </div>

  {if $invoices}
  <ul class="hz-services hz-services--full">
    {foreach key=num item=invoice from=$invoices}
    <li class="hz-service">
      <span class="hz-svc-icon"><i class="fas fa-file-invoice"></i></span>
      <span class="hz-svc-name">{$LANG.invoicestitle} #{$invoice.invoicenum}<small>{$invoice.datecreated}</small></span>
      <span class="hz-svc-due">{$LANG.hzDue} {$invoice.datedue}</span>
      <span class="hz-svc-price">{$invoice.total}</span>
      <span class="hz-badge hz-badge--{$invoice.statusClass|lower}"><span class="hz-dot"></span>{$invoice.status}</span>
      <a class="hz-svc-action" href="{$WEB_ROOT}/viewinvoice.php?id={$invoice.id}">{if $invoice.statusClass|lower == 'unpaid'}{$LANG.hzPayNow}{else}{$LANG.invoicesview}{/if}</a>
    </li>
    {/foreach}
  </ul>
  {else}
  <div class="hz-onboard">
    <div class="hz-onboard-icon"><i class="fas fa-file-invoice"></i></div>
    <h2>{$LANG.invoicesnorecordsfound}</h2>
  </div>
  {/if}
</div>
