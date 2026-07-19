{* ==========================================================================
   Cloud On — Services page (Dashboard 2.0 aesthetic).
   Replaces the WHMCS DataTable with clean service cards. Preserves the
   $services binding; the primary action is the per-card "Manage" link.
   ========================================================================== *}
<div class="hz-dash">

  <div class="hz-dash-head">
    <div class="hz-dash-headings">
      <h1 class="hz-dash-title">{$LANG.hzMyServices}</h1>
    </div>
    <a class="btn btn-prussian hz-dash-cta" href="{$WEB_ROOT}/cart.php"><i class="fas fa-plus"></i> {$LANG.hzNewService}</a>
  </div>

  {if $services}
  <ul class="hz-services hz-services--full">
    {foreach key=num item=service from=$services}
    <li class="hz-service">
      <span class="hz-svc-icon"><i class="fas fa-server"></i></span>
      <span class="hz-svc-name">{$service.product}{if $service.domain}<small>{$service.domain}</small>{/if}</span>
      <span class="hz-svc-price">{$service.amount}<small>{$service.billingcycle}</small></span>
      <span class="hz-svc-due">{$LANG.hzRenews} {$service.nextduedate}</span>
      <span class="hz-badge hz-badge--{$service.status|lower}"><span class="hz-dot"></span>{$service.statustext}</span>
      <a class="hz-svc-action" href="{$WEB_ROOT}/clientarea.php?action=productdetails&amp;id={$service.id}">{$LANG.hzManage}</a>
    </li>
    {/foreach}
  </ul>
  {else}
  <div class="hz-onboard">
    <div class="hz-onboard-icon"><i class="fas fa-rocket"></i></div>
    <h2>{$LANG.hzWelcomeNew}</h2>
    <a class="btn btn-prussian hz-onboard-cta" href="{$WEB_ROOT}/cart.php">{$LANG.hzExplorePlans} <i class="fas fa-arrow-right"></i></a>
  </div>
  {/if}
</div>
