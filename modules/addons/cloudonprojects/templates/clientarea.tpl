{* CloudOn Project Manager — πρόοδος projects πελάτη (Φ5.2) *}
{if $cnpNoAccess}
    <div class="alert alert-warning">Απαιτείται σύνδεση.</div>
{else}

<style>
.cnp-cl-card{border:1px solid var(--border, #e2e8f0);border-radius:12px;margin-bottom:20px;overflow:hidden;background:var(--surface, #fff)}
.cnp-cl-head{padding:14px 18px;border-left:5px solid;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.cnp-cl-head h4{margin:0;font-weight:700}
.cnp-cl-prog{flex:0 0 220px}
.cnp-cl-bar{height:9px;border-radius:999px;background:rgba(130,145,169,.2);overflow:hidden}
.cnp-cl-bar > span{display:block;height:100%;border-radius:999px;background:#1f9d57}
.cnp-cl-body{padding:6px 18px 14px}
.cnp-cl-row{display:flex;gap:10px;align-items:baseline;padding:6px 0;border-bottom:1px dashed var(--border, #e9edf3);font-size:14px}
.cnp-cl-row:last-child{border-bottom:none}
.cnp-cl-badge{border-radius:999px;color:#fff;font-size:11px;padding:2px 10px;white-space:nowrap}
.cnp-cl-muted{color:var(--text-muted, #8291a9);font-size:12px}
.cnp-cl-done{opacity:.7}
</style>

<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-bottom:18px">
  <h3 style="margin:0;flex:1">Τα Projects μου</h3>
  {if $cnpSc}
    <div style="border:1px solid var(--border, #e2e8f0);border-radius:12px;padding:9px 16px;background:var(--surface, #fff)">
      <span class="cnp-cl-muted">Υπόλοιπο ωρών υποστήριξης:</span>
      <b style="color:{if $cnpSc.low}#d92d3a{else}#1f9d57{/if};font-size:16px"> {$cnpSc.txt}</b>
    </div>
  {/if}
  <a href="submitticket.php" class="btn btn-primary" style="border-radius:10px">+ Νέο αίτημα</a>
</div>

{if $cnpTickets}
<div class="cnp-cl-card">
  <div class="cnp-cl-head" style="border-left-color:#0097e4"><h4>🎫 Τα αιτήματά μου</h4></div>
  <div class="cnp-cl-body">
    {foreach $cnpTickets as $t}
    <div class="cnp-cl-row {if !$t.open}cnp-cl-done{/if}">
      <a href="viewticket.php?tid={$t.tid}&c={$t.c}" style="flex:1">#{$t.tid} — {$t.title}</a>
      <span class="cnp-cl-muted">{$t.last}</span>
      <span class="cnp-cl-badge" style="background:{if $t.open}#0097e4{else}#8291a9{/if}">{$t.status}</span>
    </div>
    {/foreach}
  </div>
</div>
{/if}

{if !$cnpProjects}
    <div class="alert alert-info">Δεν υπάρχουν ενεργά projects αυτή τη στιγμή. Για οτιδήποτε χρειαστείτε, ανοίξτε ένα <a href="submitticket.php">ticket υποστήριξης</a>.</div>
{/if}

{foreach $cnpProjects as $p}
<div class="cnp-cl-card">
    <div class="cnp-cl-head" style="border-left-color:{$p.color}">
        <div>
            <h4>{$p.name}</h4>
            {if $p.descr}<div class="cnp-cl-muted">{$p.descr}</div>{/if}
        </div>
        <div class="cnp-cl-prog">
            <div class="cnp-cl-muted" style="display:flex;justify-content:space-between;margin-bottom:3px">
                <span>Πρόοδος</span><span><b>{$p.done}/{$p.total}</b> ({$p.pct}%)</span>
            </div>
            <div class="cnp-cl-bar"><span style="width:{$p.pct}%"></span></div>
        </div>
    </div>
    <div class="cnp-cl-body">
        {if $p.open}
            <div class="cnp-cl-muted" style="margin:8px 0 2px;text-transform:uppercase;letter-spacing:.4px">Σε εξέλιξη</div>
            {foreach $p.open as $t}
            <div class="cnp-cl-row">
                <span style="flex:1">{$t.title}</span>
                {if $t.due}<span class="cnp-cl-muted">έως {$t.due}</span>{/if}
                <span class="cnp-cl-badge" style="background:{$t.color}">{$t.status}</span>
            </div>
            {/foreach}
        {/if}
        {if $p.recent}
            <div class="cnp-cl-muted" style="margin:12px 0 2px;text-transform:uppercase;letter-spacing:.4px">Ολοκληρώθηκαν πρόσφατα</div>
            {foreach $p.recent as $t}
            <div class="cnp-cl-row cnp-cl-done">
                <span style="flex:1"><i class="fas fa-check" style="color:#1f9d57"></i> {$t.title}</span>
                <span class="cnp-cl-muted">{$t.date}</span>
            </div>
            {/foreach}
        {/if}
        {if !$p.open && !$p.recent}
            <div class="cnp-cl-muted" style="padding:8px 0">Δεν υπάρχουν καταχωρημένες εργασίες ακόμη.</div>
        {/if}
    </div>
</div>
{/foreach}

{/if}
