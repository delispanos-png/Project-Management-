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

  {* ---- KPI summary cards — each links to its section ---- *}
  <div class="hz-kpis" role="navigation" aria-label="{$LANG.hzDashKpisAria}">
    <a class="hz-kpi" href="{$WEB_ROOT}/clientarea.php?action=services">
      <span class="hz-kpi-ic hz-kpi-ic--svc"><i class="fas fa-cloud" aria-hidden="true"></i></span>
      <span class="hz-kpi-meta"><span class="hz-kpi-v">{$clientsstats.productsnumactive|default:0}</span><span class="hz-kpi-l">{$LANG.hzKpiServices}</span></span>
      <i class="fas fa-chevron-right hz-kpi-go" aria-hidden="true"></i>
    </a>
    <a class="hz-kpi" href="{$WEB_ROOT}/clientarea.php?action=domains">
      <span class="hz-kpi-ic hz-kpi-ic--dom"><i class="fas fa-globe" aria-hidden="true"></i></span>
      <span class="hz-kpi-meta"><span class="hz-kpi-v">{$clientsstats.numactivedomains|default:0}</span><span class="hz-kpi-l">{$LANG.hzKpiDomains}</span></span>
      <i class="fas fa-chevron-right hz-kpi-go" aria-hidden="true"></i>
    </a>
    <a class="hz-kpi{if $clientsstats.numactivetickets > 0} hz-kpi--warn{/if}" href="{$WEB_ROOT}/supporttickets.php">
      <span class="hz-kpi-ic hz-kpi-ic--tik"><i class="fas fa-life-ring" aria-hidden="true"></i></span>
      <span class="hz-kpi-meta"><span class="hz-kpi-v">{$clientsstats.numactivetickets|default:0}</span><span class="hz-kpi-l">{$LANG.hzKpiTickets}</span></span>
      <i class="fas fa-chevron-right hz-kpi-go" aria-hidden="true"></i>
    </a>
    <a class="hz-kpi{if $clientsstats.numunpaidinvoices > 0} hz-kpi--alert{/if}" href="{if $hzUnpaidInvoice}{$WEB_ROOT}/viewinvoice.php?id={$hzUnpaidInvoice.id}{else}{$WEB_ROOT}/clientarea.php?action=invoices{/if}">
      <span class="hz-kpi-ic hz-kpi-ic--inv"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i></span>
      <span class="hz-kpi-meta">
        <span class="hz-kpi-v">{$clientsstats.numunpaidinvoices|default:0}</span>
        <span class="hz-kpi-l">{$LANG.hzKpiInvoices}{if $clientsstats.numunpaidinvoices > 0 && $hzUnpaidTotal} · <b class="hz-kpi-amt">&euro;{$hzUnpaidTotal}</b>{/if}</span>
      </span>
      {if $clientsstats.numunpaidinvoices > 0}<span class="hz-kpi-pay">{$LANG.hzKpiPay}</span>{else}<i class="fas fa-chevron-right hz-kpi-go" aria-hidden="true"></i>{/if}
    </a>
    <a class="hz-kpi hz-kpi--nav" href="{$WEB_ROOT}/clientarea.php?action=details">
      <span class="hz-kpi-ic hz-kpi-ic--acc"><i class="fas fa-user-gear" aria-hidden="true"></i></span>
      <span class="hz-kpi-meta"><span class="hz-kpi-l hz-kpi-l--strong">{$LANG.hzKpiAccount}</span><span class="hz-kpi-sub">{$LANG.hzKpiManage}</span></span>
      <i class="fas fa-chevron-right hz-kpi-go" aria-hidden="true"></i>
    </a>
    <a class="hz-kpi hz-kpi--nav{if $hzNewAnnouncements > 0} hz-kpi--hasnew{/if}" href="{$WEB_ROOT}/announcements.php">
      <span class="hz-kpi-ic hz-kpi-ic--ann"><i class="fas fa-bullhorn" aria-hidden="true"></i>{if $hzNewAnnouncements > 0}<span class="hz-kpi-badge" aria-label="{$hzNewAnnouncements} {$LANG.hzNew}">{$hzNewAnnouncements}</span>{/if}</span>
      <span class="hz-kpi-meta"><span class="hz-kpi-l hz-kpi-l--strong">{$LANG.hzKpiAnnounce}</span><span class="hz-kpi-sub">{if $hzNewAnnouncements > 0}{$hzNewAnnouncements} {$LANG.hzNew}{else}{$LANG.hzViewAll}{/if}</span></span>
      <i class="fas fa-chevron-right hz-kpi-go" aria-hidden="true"></i>
    </a>
  </div>

  {* ---- Preserve any addon-injected output ---- *}
  {foreach from=$addons_html item=addon_html}<div class="hz-addon">{$addon_html}</div>{/foreach}

  {* ---- Support contract: prepaid time balance + SLA agreement ---- *}
  {if $scHomeContract}
  <div class="hz-sec-head"><h2>{$LANG.scHomeTitle}</h2></div>
  <a class="sc-home sc-home--{$scHomeLow}" href="{$WEB_ROOT}/index.php?m=supportcontracts">
    <div class="sc-home-main">
      <span class="sc-home-ic" aria-hidden="true"><i class="fas fa-hourglass-half"></i></span>
      <span class="sc-home-bal">
        <span class="sc-home-v">{$scHomeBalance}</span>
        <span class="sc-home-l">{$LANG.scHomeBalance}</span>
      </span>
    </div>
    <div class="sc-home-sla">
      <div class="sc-home-row"><span class="sc-home-k">{$LANG.scHomeResponse}</span><span class="sc-home-val">{$scHomeResponse}</span></div>
      <div class="sc-home-row"><span class="sc-home-k">{$LANG.scHomeHours}</span><span class="sc-home-val">{$scHomeHours}</span></div>
      {if $scHomePriority > 0}<div class="sc-home-row"><span class="sc-home-k">{$LANG.scHomePriority}</span><span class="sc-pill sc-pill--p{$scHomePriority}">{if $scHomePriority == 2}{$LANG.scPrioCritical}{else}{$LANG.scPrioHigh}{/if}</span></div>{/if}
    </div>
    <span class="sc-home-go">{$LANG.scHomeDetails} <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
  </a>
  {/if}

  {if $hzHasServices}

    {* P1 attention + P2 health were moved INTO the payments card below to
       reclaim vertical space (see the .hz-payrel card). *}

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

    {* ---- Consolidated payments + status card (συνέπεια, attention, health) ---- *}
    <div class="hz-sec-head"><h2>{$LANG.hzPayTitle}</h2></div>
    <div class="hz-payrel{if $hzPayReliability} hz-payrel--{$hzPayReliability.level}{/if}">

      {if $hzPayReliability}
      <div class="hz-payrel-top">
        <div class="hz-payrel-face" aria-hidden="true">
          <i class="fas {if $hzPayReliability.level == 'ok'}fa-smile-beam{elseif $hzPayReliability.level == 'warn'}fa-meh{else}fa-frown{/if}"></i>
        </div>
        <div class="hz-payrel-gauge" style="--pct:{$hzPayReliability.rate}" role="img"
             aria-label="{$hzPayReliability.rate}% {$LANG.hzPayOnTime}">
          <div class="hz-payrel-gauge-in"><span class="hz-payrel-pct">{$hzPayReliability.rate}%</span></div>
        </div>
        <div class="hz-payrel-body">
          <div class="hz-payrel-label">
            {if $hzPayReliability.level == 'ok'}{$LANG.hzPayExcellent}
            {elseif $hzPayReliability.level == 'warn'}{$LANG.hzPayGood}
            {else}{$LANG.hzPayAttention}{/if}
          </div>
          <div class="hz-payrel-sub">{$hzPayReliability.onTime}/{$hzPayReliability.total} {$LANG.hzPayOnTime}</div>
        </div>
        {if $hzLastPayment}
        <div class="hz-payrel-last">
          <div class="hz-payrel-last-l">{$LANG.hzPayLast}</div>
          <div class="hz-payrel-last-v">{$hzLastPayment.date}</div>
          <div class="hz-payrel-last-amt">&euro;{$hzLastPayment.amount}</div>
        </div>
        {/if}
      </div>
      {/if}

      {* attention (moved in) — unpaid invoice / open tickets + Pay now *}
      {if $hzUnpaidInvoice || $clientsstats.numactivetickets > 0}
      <div class="hz-payrel-alert" role="region" aria-label="{$LANG.hzAttention}">
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

      {if $hzOpenByMonth}
      <div class="hz-open">
        <div class="hz-open-head">
          <span class="hz-open-l">{$LANG.hzOpenBalance}</span>
          <span class="hz-open-total">&euro;{$hzUnpaidTotal}</span>
        </div>
        <div class="hz-open-chart" role="img" aria-label="{$LANG.hzOpenBalance}: &euro;{$hzUnpaidTotal}">
          {foreach from=$hzOpenByMonth item=mo}
          <div class="hz-open-col">
            <div class="hz-open-track">
              <div class="hz-open-bar{if $mo.overdue} hz-open-bar--over{/if}" style="--h:{$mo.pct}"
                   title="{$mo.ym} · &euro;{$mo.amount}"></div>
            </div>
            <div class="hz-open-x">{$mo.short}</div>
          </div>
          {/foreach}
        </div>
      </div>
      {/if}

      {* health (moved in) — infrastructure status, clickable to services *}
      <a class="hz-payrel-foot hz-health hz-health--{$hzHealth.level} hz-health--link" href="{$WEB_ROOT}/clientarea.php?action=services">
        <span class="hz-dot"></span>
        {if $hzHealth.level == 'ok'}{$LANG.hzHealthOk}
        {elseif $hzHealth.level == 'warn'}{$hzHealth.count} {$LANG.hzHealthWarn}
        {else}{$hzHealth.count} {$LANG.hzHealthBad}{/if}
        <i class="fas fa-arrow-right hz-health-go" aria-hidden="true"></i>
      </a>
    </div>

    {* ---- P4 · Quick actions (icon buttons) ---- *}
    <div class="hz-sec-head"><h2>{$LANG.hzQuickActions}</h2></div>
    <nav class="hz-quick" aria-label="{$LANG.hzQuickActions}">
      <a class="hz-quick-btn" href="{$WEB_ROOT}/submitticket.php"><i class="fas fa-headset" aria-hidden="true"></i> <span>{$LANG.hzQuickNewTicket}</span></a>
      <a class="hz-quick-btn" href="{$WEB_ROOT}/clientarea.php?action=invoices"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> <span>{$LANG.invoices}</span></a>
      <a class="hz-quick-btn" href="{$WEB_ROOT}/clientarea.php?action=domains"><i class="fas fa-globe" aria-hidden="true"></i> <span>{$LANG.hzMyDomains}</span></a>
      <a class="hz-quick-btn" href="{$WEB_ROOT}/knowledgebase.php"><i class="fas fa-book" aria-hidden="true"></i> <span>{$LANG.knowledgebasetitle}</span></a>
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
