{* ==========================================================================
   Cloud On — Dashboard Redesign 2.0
   Rebuilt per the approved Blueprint (priority-driven IA). Preserves WHMCS
   bindings (flashmessage, addons, $clientsstats, $invoices) and consumes the
   read-only hz_dashboard_data hook (hzServices/hzMonthlySpend/hzHealth/…).
   ========================================================================== *}
{include file="$template/includes/flashmessage.tpl"}

<div class="hz-dash">

  {* ---- Header + primary CTA ---- *}
  <div class="hz-dash-head">
    <div class="hz-dash-headings">
      <h1 class="hz-dash-title">{$LANG.hzOverview}</h1>
      <p class="hz-dash-greet">{$LANG.hzGreeting}, {$clientsdetails.firstname}</p>
    </div>
    <a class="btn btn-prussian hz-dash-cta" href="{$WEB_ROOT}/cart.php"><i class="fas fa-plus"></i> {$LANG.hzNewService}</a>
  </div>

  {* ---- Preserve any addon-injected output ---- *}
  {foreach from=$addons_html item=addon_html}<div class="hz-addon">{$addon_html}</div>{/foreach}

  {if $hzHasServices}

    {* ---- P1 · Χρειάζονται ενέργεια (conditional) ---- *}
    {if $hzUnpaidInvoice || $clientsstats.numactivetickets > 0}
    <div class="hz-attention" role="region" aria-label="{$LANG.hzAttention}">
      <div class="hz-attn-icon"><i class="fas fa-exclamation-circle"></i></div>
      <div class="hz-attn-body">
        <strong>{$LANG.hzAttention}</strong>
        <span>
          {if $hzUnpaidInvoice}{$LANG.hzUnpaidInvoiceMsg}: <b>&euro;{$hzUnpaidInvoice.total}</b> · {$LANG.hzDue} {$hzUnpaidInvoice.duedate}{/if}
          {if $hzUnpaidInvoice && $clientsstats.numactivetickets > 0} · {/if}
          {if $clientsstats.numactivetickets > 0}{$clientsstats.numactivetickets} {$LANG.hzOpenTickets}{/if}
        </span>
      </div>
      {if $hzUnpaidInvoice}<a class="btn btn-prussian hz-attn-cta" href="{$WEB_ROOT}/viewinvoice.php?id={$hzUnpaidInvoice.id}">{$LANG.hzPayNow}</a>{/if}
    </div>
    {/if}

    {* ---- P2 · Infrastructure Health + Οι υπηρεσίες μου ---- *}
    <div class="hz-health hz-health--{$hzHealth.level}">
      <span class="hz-dot"></span>
      {if $hzHealth.level == 'ok'}{$LANG.hzHealthOk}
      {elseif $hzHealth.level == 'warn'}{$hzHealth.count} {$LANG.hzHealthWarn}
      {else}{$hzHealth.count} {$LANG.hzHealthBad}{/if}
    </div>

    <div class="hz-sec-head">
      <h2>{$LANG.hzMyServices}</h2>
      <a href="{$WEB_ROOT}/clientarea.php?action=services">{$LANG.hzViewAll} <i class="fas fa-arrow-right"></i></a>
    </div>
    <ul class="hz-services">
      {foreach $hzServices as $s}
      <li class="hz-service">
        <span class="hz-svc-icon"><i class="fas fa-server"></i></span>
        <span class="hz-svc-name">{$s.name}<small>{$s.domain}</small></span>
        <span class="hz-badge hz-badge--{$s.status|lower}"><span class="hz-dot"></span>
          {if $s.status == 'Active'}{$LANG.hzStActive}{elseif $s.status == 'Pending'}{$LANG.hzStPending}{elseif $s.status == 'Suspended'}{$LANG.hzStSuspended}{else}{$s.status}{/if}
        </span>
        <span class="hz-svc-due">{$LANG.hzRenews} {$s.nextdue}</span>
        <a class="hz-svc-action" href="{$WEB_ROOT}/clientarea.php?action=productdetails&amp;id={$s.id}">{$LANG.hzManage}</a>
      </li>
      {/foreach}
    </ul>

    {* ---- P3 · Οικονομική εικόνα ---- *}
    <div class="hz-sec-head"><h2>{$LANG.hzFinancial}</h2></div>
    <div class="hz-tiles">
      <div class="hz-tile">
        <div class="hz-tile-v">{if $hzNextRenewal}{$hzNextRenewal}{else}&mdash;{/if}</div>
        <div class="hz-tile-l">{$LANG.hzNextRenewal}</div>
      </div>
      <div class="hz-tile">
        <div class="hz-tile-v">&euro;{$hzMonthlySpend}</div>
        <div class="hz-tile-l">{$LANG.hzMonthlySpendL}</div>
      </div>
      <div class="hz-tile">
        <div class="hz-tile-v">{$clientsstats.creditbalance}</div>
        <div class="hz-tile-l">{$LANG.hzCreditL}</div>
      </div>
      <div class="hz-tile{if $clientsstats.numunpaidinvoices > 0} hz-tile--alert{/if}">
        <div class="hz-tile-v">{$clientsstats.numunpaidinvoices}</div>
        <div class="hz-tile-l">{$LANG.hzUnpaidL}</div>
      </div>
    </div>

    {* ---- P4 · Quick actions ---- *}
    <nav class="hz-quick" aria-label="{$LANG.hzQuickActions}">
      <a href="{$WEB_ROOT}/supporttickets.php">{$LANG.navsupport}</a>
      <a href="{$WEB_ROOT}/clientarea.php?action=invoices">{$LANG.invoices}</a>
      <a href="{$WEB_ROOT}/clientarea.php?action=domains">{$LANG.hzMyDomains}</a>
      <a href="{$WEB_ROOT}/knowledgebase.php">{$LANG.knowledgebasetitle}</a>
    </nav>

  {else}

    {* ---- Empty → Onboarding hero (no empty panels) ---- *}
    <div class="hz-onboard">
      <div class="hz-onboard-icon"><i class="fas fa-rocket"></i></div>
      <h2>{$LANG.hzWelcomeNew}</h2>
      <ol class="hz-steps">
        <li><span>1</span>{$LANG.hzOnbStep1}</li>
        <li><span>2</span>{$LANG.hzOnbStep2}</li>
        <li><span>3</span>{$LANG.hzOnbStep3}</li>
      </ol>
      <a class="btn btn-prussian hz-onboard-cta" href="{$WEB_ROOT}/cart.php">{$LANG.hzExplorePlans} <i class="fas fa-arrow-right"></i></a>
    </div>

  {/if}
</div>
