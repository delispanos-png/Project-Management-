{* Support Contracts — client-facing prepaid-time consumption ledger *}
<div class="sc-ledger">
  {if !$scHasContract}
    <div class="alert alert-info">Δεν υπάρχει ενεργό συμβόλαιο υποστήριξης στον λογαριασμό σας.</div>
  {else}
    <div class="sc-cards">
      <div class="sc-card sc-card--{$scBalanceLow|default:''}">
        <div class="sc-card-l">Υπόλοιπο προαγορασμένου χρόνου</div>
        <div class="sc-card-v">{$scBalance}</div>
      </div>
      <div class="sc-card">
        <div class="sc-card-l">Χρόνος απόκρισης (SLA)</div>
        <div class="sc-card-v">{$scResponse}</div>
      </div>
      <div class="sc-card">
        <div class="sc-card-l">Ώρες εξυπηρέτησης</div>
        <div class="sc-card-v sc-card-v--sm">{$scHours}</div>
      </div>
    </div>

    <h3 class="sc-h">Αναλυτική κατανάλωση</h3>
    <div class="table-responsive">
      <table class="table table-striped sc-table">
        <thead>
          <tr>
            <th>Ημερομηνία</th>
            <th>Κίνηση</th>
            <th>Αίτημα</th>
            <th class="text-right">Χρόνος</th>
            <th class="text-right">Υπόλοιπο</th>
            <th>Σημείωση</th>
          </tr>
        </thead>
        <tbody>
          {if !$scLedger}
            <tr><td colspan="6" class="text-center text-muted">Καμία κίνηση ακόμη.</td></tr>
          {/if}
          {foreach from=$scLedger item=row}
            <tr>
              <td>{$row.date}</td>
              <td>
                {if $row.type == 'topup'}<span class="sc-tag sc-tag--add">Προσθήκη</span>
                {elseif $row.type == 'usage'}<span class="sc-tag sc-tag--use">Χρήση</span>
                {else}<span class="sc-tag">Προσαρμογή</span>{/if}
              </td>
              <td>{if $row.ticketid}<a href="{$WEB_ROOT}/viewticket.php?tid={$row.ticketid}">#{$row.ticketid}</a>{else}—{/if}</td>
              <td class="text-right {if $row.positive}sc-pos{else}sc-neg{/if}">{if $row.positive}+{/if}{$row.minutes}</td>
              <td class="text-right"><strong>{$row.balance}</strong></td>
              <td>{$row.note}</td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  {/if}
</div>

{literal}
<style>
.sc-ledger{max-width:1000px}
.sc-cards{display:flex;flex-wrap:wrap;gap:14px;margin:8px 0 22px}
.sc-card{flex:1 1 200px;padding:18px 20px;border-radius:14px;background:rgba(255,255,255,.04);border:1px solid rgba(146,180,209,.25)}
.sc-card--low{border-color:rgba(224,160,32,.55)}
.sc-card--empty{border-color:rgba(217,45,58,.6)}
.sc-card-l{font-size:12px;text-transform:uppercase;letter-spacing:.03em;opacity:.7;margin-bottom:6px}
.sc-card-v{font-size:28px;font-weight:800;line-height:1.1}
.sc-card--low .sc-card-v{color:#e0a020}
.sc-card--empty .sc-card-v{color:#d92d3a}
.sc-card-v--sm{font-size:17px;font-weight:700}
.sc-h{font-size:17px;font-weight:700;margin:18px 0 12px}
.sc-tag{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700}
.sc-tag--add{background:rgba(31,157,87,.16);color:#2ecc71}
.sc-tag--use{background:rgba(217,45,58,.16);color:#ff7a7a}
.sc-pos{color:#2ecc71;font-weight:700}
.sc-neg{color:#ff7a7a;font-weight:700}
.sc-table td{vertical-align:middle}
</style>
{/literal}
