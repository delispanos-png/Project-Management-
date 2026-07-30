{* ==========================================================================
   Cloud On — Domains page (Dashboard 2.0 aesthetic).
   Replaces the WHMCS DataTable + bulk manager with clean domain cards.
   Per-domain management (incl. auto-renew toggle) remains on domaindetails.
   ========================================================================== *}
{if $warnings}
    {include file="$template/includes/alert.tpl" type="warning" msg=$warnings textcenter=true}
{/if}

<div class="hz-dash">

  <div class="hz-dash-head">
    <div class="hz-dash-headings">
      <h1 class="hz-dash-title">{$LANG.hzMyDomains}</h1>
    </div>
    <a class="btn btn-prussian hz-dash-cta" href="{$WEB_ROOT}/cart.php?a=add&domain=register"><i class="fas fa-plus"></i> {$LANG.hzNewService}</a>
  </div>

  {if $domains}
  <ul class="hz-services hz-services--full">
    {foreach key=num item=domain from=$domains}
    <li class="hz-service">
      <span class="hz-svc-icon"><i class="fas fa-globe"></i></span>
      <span class="hz-svc-name">{$domain.domain}<small>{$LANG.hzRenews} {$domain.nextduedate}</small></span>
      <span class="hz-svc-due">{if $domain.autorenew}<i class="fas fa-sync-alt"></i> {$LANG.domainsautorenewenabled}{else}{$LANG.domainsautorenewdisabled}{/if}</span>
      <span class="hz-badge hz-badge--{$domain.statusClass|lower}"><span class="hz-dot"></span>{$domain.statustext}</span>
      <a class="hz-svc-action" href="{$WEB_ROOT}/clientarea.php?action=domaindetails&amp;id={$domain.id}">{$LANG.hzManage}</a>
    </li>
    {/foreach}
  </ul>
  {else}
  <div class="hz-onboard">
    <div class="hz-onboard-icon"><i class="fas fa-globe"></i></div>
    <h2>{$LANG.hzWelcomeNew}</h2>
    <a class="btn btn-prussian hz-onboard-cta" href="{$WEB_ROOT}/cart.php?a=add&domain=register">{$LANG.hzExplorePlans} <i class="fas fa-arrow-right"></i></a>
  </div>
  {/if}
</div>
