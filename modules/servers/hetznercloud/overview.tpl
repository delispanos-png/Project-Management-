{*
  White-label cloud server control panel.
  No provider branding is ever shown to the client — {$brand} is used throughout.
*}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@novnc/novnc@1.4.0/app/styles/base.css" onerror="this.remove()">

<div class="hz-panel">
{if $fatal}
    <div class="alert alert-info">{$fatal}</div>
{else}

    {if $notice}<div class="alert alert-success" style="white-space:pre-wrap">{$notice}</div>{/if}
    {if $error}<div class="alert alert-danger">{$error}</div>{/if}

    <div class="row" style="margin-bottom:15px">
        <div class="col-md-8">
            <h3 style="margin-top:0">{$brand}</h3>
            <span class="label label-{if $status eq 'running'}success{elseif $status eq 'off'}default{else}warning{/if}"
                  id="hz-status-badge" style="font-size:90%">
                {if $status eq 'running'}Σε λειτουργία{elseif $status eq 'off'}Εκτός λειτουργίας{else}{$status}{/if}
            </span>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <table class="table table-condensed">
                <tr><th style="width:40%">Πλάνο</th><td>{$typeLabel}</td></tr>
                <tr><th>vCPU</th><td>{$cores}</td></tr>
                <tr><th>Μνήμη</th><td>{$memory} GB</td></tr>
                <tr><th>Δίσκος</th><td>{$disk} GB</td></tr>
                <tr><th>Λειτουργικό</th><td>{if $os}{$os}{else}—{/if}</td></tr>
                <tr><th>Τοποθεσία</th><td>{if $location}{$location}{else}—{/if}</td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-condensed">
                <tr><th style="width:40%">IPv4</th><td>{if $ipv4}<code>{$ipv4}</code>{else}—{/if}</td></tr>
                <tr><th>IPv6</th><td>{if $ipv6}<code>{$ipv6}</code>{else}—{/if}</td></tr>
                {if $includedTraffic}<tr><th>Κίνηση (περιλαμβ.)</th><td>{$includedTraffic} TB</td></tr>{/if}
                {if $outgoingTraffic ne null}<tr><th>Εξερχόμενη</th><td>{$outgoingTraffic} GB</td></tr>{/if}
            </table>
        </div>
    </div>

    <div class="btn-toolbar" style="margin-bottom:20px">
        <form method="post" style="display:inline-block;margin:0 4px 4px 0">
            <input type="hidden" name="hzaction" value="power">
            <input type="hidden" name="op" value="on">
            <input type="hidden" name="token" value="{$token}">
            <button class="btn btn-success"><i class="fa fa-play"></i> Εκκίνηση</button>
        </form>
        <form method="post" style="display:inline-block;margin:0 4px 4px 0" onsubmit="return confirm('Τερματισμός του server;');">
            <input type="hidden" name="hzaction" value="power">
            <input type="hidden" name="op" value="off">
            <input type="hidden" name="token" value="{$token}">
            <button class="btn btn-warning"><i class="fa fa-power-off"></i> Τερματισμός</button>
        </form>
        <form method="post" style="display:inline-block;margin:0 4px 4px 0" onsubmit="return confirm('Επανεκκίνηση του server;');">
            <input type="hidden" name="hzaction" value="power">
            <input type="hidden" name="op" value="reboot">
            <input type="hidden" name="token" value="{$token}">
            <button class="btn btn-default"><i class="fa fa-refresh"></i> Επανεκκίνηση</button>
        </form>
        <button class="btn btn-default" id="hz-console-btn" style="margin-bottom:4px"><i class="fa fa-terminal"></i> Κονσόλα</button>
    </div>

    {* ---- Getting started ---- *}
    <div class="panel panel-default hz-getstarted">
        <div class="panel-heading" style="cursor:pointer" onclick="var b=this.nextElementSibling;b.style.display=b.style.display==='none'?'block':'none';">
            <strong><i class="fa fa-rocket"></i> Πρώτα Βήματα</strong>
            <small class="pull-right text-muted">(κλικ για εμφάνιση/απόκρυψη)</small>
        </div>
        <div class="panel-body">
            <ol style="padding-left:18px;margin-bottom:0">
                <li style="margin-bottom:6px"><strong>Σύνδεση SSH:</strong>
                    {if $ipv4}<code>ssh root@{$ipv4}</code>{else}μόλις ετοιμαστεί το IP{/if}
                    — ο κωδικός <strong>root</strong> είναι στο email καλωσορίσματος (ή κάνε «Επαναφορά κωδικού root» πιο κάτω).</li>
                <li style="margin-bottom:6px"><strong>Κλείδωμα / πρόβλημα;</strong> Χρησιμοποίησε την <strong>Κονσόλα</strong> (πρόσβαση χωρίς SSH) ή τη <strong>Λειτουργία Διάσωσης</strong>.</li>
                <li style="margin-bottom:6px"><strong>Αλλαγή λειτουργικού:</strong> «Επανεγκατάσταση Λειτουργικού» (⚠️ διαγράφει τα δεδομένα).</li>
                <li style="margin-bottom:6px"><strong>Αναβάθμιση/υποβάθμιση:</strong> «Αλλαγή Πλάνου».</li>
                <li><strong>Αντίγραφο ασφαλείας:</strong> «Δημιουργία Snapshot» πριν από μεγάλες αλλαγές.</li>
            </ol>
        </div>
    </div>

    {* ---- Live metrics ---- *}
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>Χρήση (τελευταία ώρα)</strong>
            <div class="btn-group btn-group-xs pull-right hz-metric-tabs">
                <button class="btn btn-default active" data-metric="cpu">CPU</button>
                <button class="btn btn-default" data-metric="disk">Disk</button>
                <button class="btn btn-default" data-metric="network">Network</button>
            </div>
        </div>
        <div class="panel-body"><canvas id="hz-chart" height="90"></canvas></div>
    </div>

    <div class="row">
        {* ---- Reverse DNS (primary + each extra IP) ---- *}
        {if $rdnsIps}
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Ανάστροφο DNS (rDNS)</strong></div>
                <div class="panel-body">
                    {foreach $rdnsIps as $rip}
                    <form method="post" style="margin-bottom:10px">
                        <input type="hidden" name="hzaction" value="rdns">
                        <input type="hidden" name="token" value="{$token}">
                        <input type="hidden" name="ip" value="{$rip.ip}">
                        <input type="hidden" name="fipid" value="{$rip.fipid}">
                        <div style="margin-bottom:3px">
                            <span class="label label-{if $rip.fipid}info{else}default{/if}">{if $rip.fipid}Extra IP{else}Κύριο{/if}</span>
                            <code>{$rip.ip}</code>
                        </div>
                        <div class="input-group">
                            <input type="text" name="ptr" class="form-control" value="{$rip.ptr}" placeholder="host.example.com">
                            <span class="input-group-btn"><button class="btn btn-default">Αποθήκευση</button></span>
                        </div>
                    </form>
                    {/foreach}
                </div>
            </div>
        </div>
        {/if}

        {* ---- Snapshot / password ---- *}
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Συντήρηση</strong></div>
                <div class="panel-body">
                    <form method="post" style="margin-bottom:8px">
                        <input type="hidden" name="hzaction" value="snapshot">
                        <input type="hidden" name="token" value="{$token}">
                        <div class="input-group">
                            <input type="text" name="description" class="form-control" placeholder="Όνομα snapshot">
                            <span class="input-group-btn"><button class="btn btn-default">Δημιουργία Snapshot</button></span>
                        </div>
                    </form>
                    {if $snapshots}
                    <table class="table table-condensed" style="margin-bottom:8px">
                        {foreach $snapshots as $snap}
                        <tr>
                            <td style="vertical-align:middle">{$snap.description}<br><small class="text-muted">{$snap.created} {$snap.size}</small></td>
                            <td style="text-align:right;white-space:nowrap;vertical-align:middle">
                                <form method="post" style="display:inline" onsubmit="return confirm('Επαναφορά από αυτό το snapshot; ΘΑ ΔΙΑΓΡΑΦΟΥΝ τα τρέχοντα δεδομένα.');">
                                    <input type="hidden" name="hzaction" value="snapshot_restore">
                                    <input type="hidden" name="token" value="{$token}">
                                    <input type="hidden" name="image_id" value="{$snap.id}">
                                    <button class="btn btn-xs btn-warning">Επαναφορά</button>
                                </form>
                                <form method="post" style="display:inline" onsubmit="return confirm('Διαγραφή αυτού του snapshot;');">
                                    <input type="hidden" name="hzaction" value="snapshot_delete">
                                    <input type="hidden" name="token" value="{$token}">
                                    <input type="hidden" name="image_id" value="{$snap.id}">
                                    <button class="btn btn-xs btn-danger" title="Διαγραφή">×</button>
                                </form>
                            </td>
                        </tr>
                        {/foreach}
                    </table>
                    {/if}
                    <form method="post" onsubmit="return confirm('Επαναφορά κωδικού root;');">
                        <input type="hidden" name="hzaction" value="resetpw">
                        <input type="hidden" name="token" value="{$token}">
                        <button class="btn btn-default btn-block">Επαναφορά κωδικού root</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {* ---- Rescue mode ---- *}
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Λειτουργία Διάσωσης</strong></div>
                <div class="panel-body">
                    {if $rescueEnabled}
                        <div class="alert alert-info" style="padding:8px;margin-bottom:8px">Η λειτουργία διάσωσης είναι <strong>ΕΝΕΡΓΗ</strong>.</div>
                        <form method="post" onsubmit="return confirm('Απενεργοποίηση διάσωσης και επιστροφή στο κανονικό;');">
                            <input type="hidden" name="hzaction" value="rescue">
                            <input type="hidden" name="op" value="disable">
                            <input type="hidden" name="token" value="{$token}">
                            <button class="btn btn-default btn-block">Απενεργοποίηση Διάσωσης</button>
                        </form>
                    {else}
                        <p class="help-block" style="margin-top:0">Εκκίνηση σε σύστημα διάσωσης για να διορθώσεις έναν χαλασμένο server — κλείδωμα SSH, πρόβλημα boot/GRUB ή λάθος ρύθμιση — χωρίς υποστήριξη.</p>
                        <form method="post" onsubmit="return confirm('Ενεργοποίηση διάσωσης; Ο server θα επανεκκινήσει στο σύστημα διάσωσης.');">
                            <input type="hidden" name="hzaction" value="rescue">
                            <input type="hidden" name="op" value="enable">
                            <input type="hidden" name="token" value="{$token}">
                            <button class="btn btn-default btn-block"><i class="fa fa-life-ring"></i> Ενεργοποίηση Διάσωσης</button>
                        </form>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {* ---- Rebuild / reinstall ---- *}
        {if $allowRebuild && $images}
        <div class="col-md-6">
            <div class="panel panel-warning">
                <div class="panel-heading"><strong>Επανεγκατάσταση Λειτουργικού</strong></div>
                <div class="panel-body">
                    <form method="post" onsubmit="return confirm('ΘΑ ΔΙΑΓΡΑΦΟΥΝ ΟΛΑ τα δεδομένα του server. Συνέχεια;');">
                        <input type="hidden" name="hzaction" value="rebuild">
                        <input type="hidden" name="token" value="{$token}">
                        <select name="image" class="form-control" style="margin-bottom:8px">
                            {foreach $images as $val => $label}<option value="{$val}"{if $val eq $currentOs} selected{/if}>{$label}{if $val eq $currentOs} — τρέχον{/if}</option>{/foreach}
                        </select>
                        <button class="btn btn-warning btn-block">Επανεγκατάσταση</button>
                    </form>
                </div>
            </div>
        </div>
        {/if}

        {* ---- Resize / upgrade ---- *}
        {if $allowResize && $resizeOptions}
        <div class="col-md-6">
            <div class="panel panel-info">
                <div class="panel-heading"><strong>Αλλαγή Πλάνου</strong></div>
                <div class="panel-body">
                    <form method="post" onsubmit="return confirm('Ο server θα τερματιστεί στιγμιαία για την αλλαγή. Συνέχεια;');">
                        <input type="hidden" name="hzaction" value="resize">
                        <input type="hidden" name="token" value="{$token}">
                        <select name="server_type" class="form-control" style="margin-bottom:8px">
                            {foreach $resizeOptions as $val => $label}<option value="{$val}">{$label}</option>{/foreach}
                        </select>
                        <label style="font-weight:normal;display:block;margin-bottom:8px">
                            <input type="checkbox" name="keepdisk" value="1"> Αλλαγή μόνο CPU/RAM — διατήρηση του τρέχοντος δίσκου
                        </label>
                        <button class="btn btn-info btn-block">Αλλαγή Πλάνου</button>
                        <p class="help-block" style="margin-bottom:0">↑ αναβάθμιση · ↓ υποβάθμιση. Η χρέωση προσαρμόζεται στο επόμενο τιμολόγιο.</p>
                    </form>
                </div>
            </div>
        </div>
        {/if}
    </div>

{/if}
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var HZ_SID = {$serviceId};
var HZ_BRAND = "{$brand|escape:'javascript'}";
</script>
{literal}
<script>
(function(){
    var base = 'clientarea.php?action=productdetails&id=' + HZ_SID;
    // ---- Status auto-refresh ----
    var badge = document.getElementById('hz-status-badge');
    function refreshStatus(){
        fetch(base + '&hzajax=status', {credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(d){
                if(!d.ok || !badge) return;
                var map = {running:['Σε λειτουργία','success'], off:['Εκτός λειτουργίας','default']};
                var s = map[d.status] || [d.status,'warning'];
                badge.textContent = s[0];
                badge.className = 'label label-' + s[1];
            }).catch(function(){});
    }
    setInterval(refreshStatus, 15000);

    // ---- Metrics chart ----
    var chart = null, current = 'cpu';
    function drawMetrics(type){
        fetch(base + '&hzajax=metrics&type=' + type, {credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(d){
                if(!d.ok) return;
                var ts = d.metrics.time_series || {};
                var keys = Object.keys(ts);
                if(!keys.length) return;
                var labels = [], datasets = [];
                keys.forEach(function(k, i){
                    var vals = ts[k].values || [];
                    if(i===0){ labels = vals.map(function(v){ var dt=new Date(v[0]*1000); return dt.getHours()+':'+('0'+dt.getMinutes()).slice(-2); }); }
                    datasets.push({ label:k.replace(/\./g,' '), data: vals.map(function(v){ return parseFloat(v[1]); }),
                        borderWidth:2, fill:false, tension:0.3 });
                });
                var ctx = document.getElementById('hz-chart');
                if(chart){ chart.destroy(); }
                chart = new Chart(ctx, { type:'line', data:{labels:labels, datasets:datasets},
                    options:{ responsive:true, plugins:{legend:{display:true, position:'bottom'}}, elements:{point:{radius:0}} } });
            }).catch(function(){});
    }
    drawMetrics(current);
    document.querySelectorAll('.hz-metric-tabs button').forEach(function(b){
        b.addEventListener('click', function(){
            document.querySelectorAll('.hz-metric-tabs button').forEach(function(x){x.classList.remove('active');});
            b.classList.add('active'); current = b.getAttribute('data-metric'); drawMetrics(current);
        });
    });

    // ---- White-label VNC console ----
    var cbtn = document.getElementById('hz-console-btn');
    if(cbtn){ cbtn.addEventListener('click', function(){
        cbtn.disabled = true; cbtn.textContent = 'Σύνδεση…';
        fetch(base + '&hzajax=console', {credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(d){
                cbtn.disabled = false; cbtn.innerHTML = '<i class="fa fa-terminal"></i> Κονσόλα';
                if(!d.ok || !d.wss_url){ alert('Η κονσόλα δεν είναι διαθέσιμη αυτή τη στιγμή.'); return; }
                openConsole(d.wss_url, d.password);
            }).catch(function(){ cbtn.disabled=false; cbtn.innerHTML='<i class="fa fa-terminal"></i> Κονσόλα'; });
    }); }

    function openConsole(wss, password){
        // Popup renders its own noVNC client so the visible page stays on our brand.
        var w = window.open('', '_blank', 'width=1024,height=720');
        if(!w){ alert('Επίτρεψε τα popups για να ανοίξει η κονσόλα.'); return; }
        var html = ''
          + '<!doctype html><html><head><meta charset="utf-8"><title>' + HZ_BRAND + ' — Κονσόλα</title>'
          + '<style>html,body{margin:0;height:100%;background:#111;color:#ccc;font-family:sans-serif}'
          + '#screen{width:100%;height:100%}#msg{padding:12px}</style></head><body>'
          + '<div id="msg">Σύνδεση στην κονσόλα…</div><div id="screen"></div>'
          + '<script type="module">'
          + 'import RFB from "https://cdn.jsdelivr.net/npm/@novnc/novnc@1.4.0/core/rfb.js";'
          + 'try{'
          + '  var rfb = new RFB(document.getElementById("screen"), ' + JSON.stringify(wss) + ', {credentials:{password:' + JSON.stringify(password) + '}});'
          + '  rfb.scaleViewport = true; rfb.resizeSession = true;'
          + '  rfb.addEventListener("connect", function(){ document.getElementById("msg").style.display="none"; });'
          + '  rfb.addEventListener("disconnect", function(){ document.getElementById("msg").style.display="block"; document.getElementById("msg").textContent="Disconnected."; });'
          + '}catch(e){ document.getElementById("msg").textContent = "Console error: " + e; }'
          + '<' + '/script></body></html>';
        w.document.write(html);
        w.document.close();
    }
})();
</script>

<style>
/* Dark-theme friendly styling for the control panel (matches the horn theme). */
.hz-panel .panel{ background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.09); border-radius:8px; }
.hz-panel .panel-heading{ padding:9px 13px; background:rgba(255,255,255,.05); border-bottom:1px solid rgba(255,255,255,.08); color:#eceff1; border-radius:8px 8px 0 0; }
.hz-panel .panel-body{ color:#cfd3d8; }
.hz-panel h3{ color:#fff; margin-bottom:6px; }
.hz-panel .table>tbody>tr>th, .hz-panel .table>tbody>tr>td{ border-color:rgba(255,255,255,.06); color:#d4d7db; padding:6px 10px; }
.hz-panel .table>tbody>tr>th{ color:#9aa0a6; font-weight:600; }
.hz-panel code{ font-size:90%; background:rgba(255,255,255,.09); color:#8ab4f8; padding:2px 6px; border-radius:4px; }
.hz-panel .form-control{ background:rgba(0,0,0,.22); border:1px solid rgba(255,255,255,.14); color:#eceff1; box-shadow:none; }
.hz-panel .form-control::placeholder{ color:#7b8085; }
.hz-panel .help-block{ color:#9aa0a6; }
.hz-panel .btn-default{ background:rgba(255,255,255,.08); border-color:rgba(255,255,255,.14); color:#eceff1; }
.hz-panel .btn-default:hover{ background:rgba(255,255,255,.15); color:#fff; }
.hz-panel .panel-warning{ border-color:rgba(240,173,78,.4); }
.hz-panel .panel-warning>.panel-heading{ background:rgba(240,173,78,.13); color:#f0ad4e; border-bottom-color:rgba(240,173,78,.3); }
.hz-panel .panel-info{ border-color:rgba(91,192,222,.4); }
.hz-panel .panel-info>.panel-heading{ background:rgba(91,192,222,.13); color:#5bc0de; border-bottom-color:rgba(91,192,222,.3); }
.hz-panel .alert-info{ background:rgba(91,192,222,.12); border-color:rgba(91,192,222,.3); color:#bce8f1; }
</style>
{/literal}
