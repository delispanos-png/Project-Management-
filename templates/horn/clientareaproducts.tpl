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
    {* ---- status filter (default: only Active) ---- *}
    {assign var=hzCounts value=[]}
    {foreach from=$services item=s}
      {assign var=st value=$s.status|lower}
      {if isset($hzCounts[$st])}{$hzCounts[$st]=$hzCounts[$st]+1}{else}{$hzCounts[$st]=1}{/if}
    {/foreach}
    <div class="hz-svc-filter" id="hzSvcFilter" role="tablist" aria-label="{$LANG.hzFltAria}">
      {foreach from=['active','suspended','pending','fraud','completed','terminated','cancelled'] item=st}
        {if isset($hzCounts[$st])}
        <button type="button" class="hz-flt" data-filter="{$st}">{if $st=='active'}{$LANG.hzFltActive}{elseif $st=='suspended'}{$LANG.hzFltSuspended}{elseif $st=='pending'}{$LANG.hzFltPending}{elseif $st=='terminated'}{$LANG.hzFltTerminated}{elseif $st=='cancelled'}{$LANG.hzFltCancelled}{elseif $st=='completed'}{$LANG.hzFltCompleted}{else}{$st|capitalize}{/if} <span class="hz-flt-n">{$hzCounts[$st]}</span></button>
        {/if}
      {/foreach}
      <button type="button" class="hz-flt" data-filter="all">{$LANG.hzFltAll} <span class="hz-flt-n">{$services|@count}</span></button>
    </div>
  <ul class="hz-services hz-services--full" id="hzSvcList">
    {foreach key=num item=service from=$services}
    <li class="hz-service" data-status="{$service.status|lower}">
      <span class="hz-svc-icon"><i class="fas {if isset($hzSvcIcons[$service.id])}{$hzSvcIcons[$service.id]}{else}fa-cube{/if}"></i></span>
      <span class="hz-svc-name">{$service.product}{if $service.domain}<small>{$service.domain}</small>{/if}</span>
      <span class="hz-svc-price">{$service.amount}<small>{$service.billingcycle}</small></span>
      <span class="hz-svc-due">{$LANG.hzRenews} {$service.nextduedate}</span>
      <span class="hz-badge hz-badge--{$service.status|lower}"><span class="hz-dot"></span>{$service.statustext}</span>
      <a class="hz-svc-action" href="{$WEB_ROOT}/clientarea.php?action=productdetails&amp;id={$service.id}">{$LANG.hzManage}</a>
    </li>
    {/foreach}
  </ul>
  <p class="hz-svc-empty" id="hzSvcEmpty" hidden>{$LANG.hzFltNone}</p>
  {literal}
  <script>
  (function(){
    var f=document.getElementById('hzSvcFilter');if(!f)return;
    var rows=[].slice.call(document.querySelectorAll('#hzSvcList .hz-service'));
    var empty=document.getElementById('hzSvcEmpty');
    function apply(v){
      var n=0;
      rows.forEach(function(r){var show=(v==='all'||r.getAttribute('data-status')===v);r.style.display=show?'':'none';if(show)n++;});
      if(empty)empty.hidden=n>0;
      [].forEach.call(f.querySelectorAll('.hz-flt'),function(b){b.classList.toggle('is-active',b.getAttribute('data-filter')===v);});
    }
    f.addEventListener('click',function(e){var b=e.target.closest('.hz-flt');if(b)apply(b.getAttribute('data-filter'));});
    var hasActive=rows.some(function(r){return r.getAttribute('data-status')==='active';});
    apply(hasActive?'active':'all');
  })();
  </script>
  {/literal}
  {else}
  <div class="hz-onboard">
    <div class="hz-onboard-icon"><i class="fas fa-rocket"></i></div>
    <h2>{$LANG.hzWelcomeNew}</h2>
    <a class="btn btn-prussian hz-onboard-cta" href="{$WEB_ROOT}/cart.php">{$LANG.hzExplorePlans} <i class="fas fa-arrow-right"></i></a>
  </div>
  {/if}
</div>
