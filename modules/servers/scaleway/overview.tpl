{*
  White-label cloud server control panel (Scaleway-backed).
  Ο πάροχος δεν εμφανίζεται ποτέ στον πελάτη — χρησιμοποιείται το {$brand}.
*}
<div class="scw-panel">
{if $fatal}
    <div class="alert alert-info">{$fatal}</div>
{else}

    {if $notice}<div class="alert alert-success" style="white-space:pre-wrap">{$notice}</div>{/if}
    {if $error}<div class="alert alert-danger">{$error}</div>{/if}

    <div class="row" style="margin-bottom:15px">
        <div class="col-md-8">
            <h3 style="margin-top:0">{$brand}</h3>
            <span class="label label-{if $status eq 'running'}success{elseif $status eq 'stopped' or $status eq 'stopped in place'}default{else}warning{/if}"
                  id="scw-status-badge" style="font-size:90%">
                {if $status eq 'running'}Σε λειτουργία
                {elseif $status eq 'stopped'}Εκτός λειτουργίας
                {elseif $status eq 'stopped in place'}Σε παύση
                {elseif $status eq 'starting'}Ξεκινά…
                {elseif $status eq 'stopping'}Τερματίζεται…
                {else}{$status}{/if}
            </span>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <table class="table table-condensed">
                <tr><th style="width:40%">Πλάνο</th><td>{$typeLabel}</td></tr>
                <tr><th>vCPU</th><td>{if $cores}{$cores}{else}—{/if}</td></tr>
                <tr><th>Μνήμη</th><td>{if $memory}{$memory} GB{else}—{/if}</td></tr>
                <tr><th>Δίσκος</th><td>{if $disk}{$disk} GB{else}—{/if}</td></tr>
                <tr><th>Λειτουργικό</th><td>{if $os}{$os}{else}—{/if}</td></tr>
                <tr><th>Τοποθεσία</th><td>{if $location}{$location}{else}—{/if}</td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-condensed">
                <tr><th style="width:40%">Διεύθυνση IPv4</th>
                    <td><code id="scw-ipv4">{if $ipv4}{$ipv4}{else}—{/if}</code></td></tr>
                {if $ipv6}<tr><th>Διεύθυνση IPv6</th><td><code>{$ipv6}</code></td></tr>{/if}
                <tr><th>Χρήστης</th><td><code>root</code></td></tr>
                <tr><th>Σύνδεση</th><td><code>ssh root@{if $ipv4}{$ipv4}{else}…{/if}</code></td></tr>
            </table>
        </div>
    </div>

    {* ── Έλεγχος λειτουργίας ── *}
    <h4>Έλεγχος λειτουργίας</h4>
    <form method="post" style="margin-bottom:20px">
        <input type="hidden" name="scwaction" value="power">
        <button class="btn btn-success" name="op" value="on" {if $status eq 'running'}disabled{/if}>
            <i class="fas fa-play"></i> Εκκίνηση</button>
        <button class="btn btn-default" name="op" value="off" {if $status ne 'running'}disabled{/if}>
            <i class="fas fa-power-off"></i> Τερματισμός</button>
        <button class="btn btn-default" name="op" value="reboot" {if $status ne 'running'}disabled{/if}>
            <i class="fas fa-redo"></i> Επανεκκίνηση</button>
        <button class="btn btn-warning" name="op" value="hardoff" {if $status ne 'running'}disabled{/if}
                onclick="return confirm('Αναγκαστικός τερματισμός — ενδέχεται απώλεια μη αποθηκευμένων δεδομένων. Συνέχεια;')">
            <i class="fas fa-bolt"></i> Αναγκαστικός τερματισμός</button>
    </form>

    {* ── Reverse DNS ── *}
    {if $ipv4 && $primaryIpId}
    <h4>Reverse DNS</h4>
    <form method="post" class="form-inline" style="margin-bottom:20px">
        <input type="hidden" name="scwaction" value="rdns">
        <input type="hidden" name="ip_id" value="{$primaryIpId}">
        <div class="form-group">
            <label style="margin-right:8px">{$ipv4}</label>
            <input type="text" class="form-control" name="ptr" value="{$ptr}"
                   placeholder="server.domain.gr" style="min-width:260px">
        </div>
        <button class="btn btn-primary">Αποθήκευση</button>
        <p class="help-block">Άφησέ το κενό για επαναφορά στην προεπιλογή.</p>
    </form>
    {/if}

    {* ── Αναβάθμιση πλάνου ── *}
    {if $allowResize && $resizeOptions}
    <h4>Αναβάθμιση πλάνου</h4>
    <form method="post" class="form-inline" style="margin-bottom:20px"
          onsubmit="return confirm('Ο διακομιστής θα τερματιστεί προσωρινά κατά την αλλαγή. Συνέχεια;')">
        <input type="hidden" name="scwaction" value="resize">
        <select name="type" class="form-control" style="min-width:320px">
            {foreach from=$resizeOptions key=k item=v}
                <option value="{$k}" {if $k eq $typeLabel|lower}selected{/if}>{$v}</option>
            {/foreach}
        </select>
        <button class="btn btn-primary">Εφαρμογή</button>
        <p class="help-block">Ο δίσκος δεν μικραίνει. Η χρέωση προσαρμόζεται από την επόμενη περίοδο.</p>
    </form>
    {/if}

    {* ── Στιγμιότυπα ── *}
    <h4>Στιγμιότυπα (snapshots)</h4>
    <form method="post" class="form-inline" style="margin-bottom:10px">
        <input type="hidden" name="scwaction" value="snapshot">
        <input type="text" class="form-control" name="name" placeholder="Ονομασία (προαιρετικά)" style="min-width:240px">
        <button class="btn btn-default">Δημιουργία στιγμιότυπου</button>
    </form>
    {if $snapshots}
    <table class="table table-condensed table-striped" style="margin-bottom:20px">
        <thead><tr><th>Ονομασία</th><th>Μέγεθος</th><th>Κατάσταση</th><th>Δημιουργήθηκε</th><th></th></tr></thead>
        <tbody>
        {foreach from=$snapshots item=s}
            <tr>
                <td>{$s.name}</td>
                <td>{if $s.size}{$s.size} GB{else}—{/if}</td>
                <td>{$s.state}</td>
                <td>{$s.created}</td>
                <td style="text-align:right">
                    <form method="post" style="display:inline"
                          onsubmit="return confirm('Οριστική διαγραφή του στιγμιότυπου;')">
                        <input type="hidden" name="scwaction" value="snapshot_delete">
                        <input type="hidden" name="snapshot_id" value="{$s.id}">
                        <button class="btn btn-xs btn-danger">Διαγραφή</button>
                    </form>
                </td>
            </tr>
        {/foreach}
        </tbody>
    </table>
    {else}
        <p class="text-muted" style="margin-bottom:20px">Δεν υπάρχουν στιγμιότυπα.</p>
    {/if}

    {* ── Επανεγκατάσταση ── *}
    {if $allowRebuild && $images}
    <h4>Επανεγκατάσταση λειτουργικού</h4>
    <div class="alert alert-warning" style="margin-bottom:10px">
        <strong>Προσοχή:</strong> διαγράφονται <em>όλα</em> τα δεδομένα του διακομιστή.
        Η διεύθυνση IP διατηρείται και θα λάβεις νέο κωδικό <code>root</code>.
    </div>
    <form method="post" class="form-inline" style="margin-bottom:20px"
          onsubmit="return confirm('Θα διαγραφούν ΟΛΑ τα δεδομένα. Είσαι σίγουρος;')">
        <input type="hidden" name="scwaction" value="rebuild">
        <select name="image" class="form-control" style="min-width:320px">
            {foreach from=$images key=label item=name}
                <option value="{$label}">{$name}</option>
            {/foreach}
        </select>
        <button class="btn btn-danger">Επανεγκατάσταση</button>
    </form>
    {/if}

{/if}
</div>

<script>
(function () {
    // Ήπιο polling κατάστασης όσο ο διακομιστής μεταβαίνει.
    var badge = document.getElementById('scw-status-badge');
    if (!badge) { return; }
    var transitional = ['starting', 'stopping', 'unknown'];
    var txt = (badge.textContent || '').trim().toLowerCase();
    var tries = 0;
    function poll() {
        if (tries++ > 20) { return; }
        var url = window.location.href + (window.location.href.indexOf('?') === -1 ? '?' : '&') + 'scwajax=1';
        fetch(url, {ldelim}credentials: 'same-origin'{rdelim})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.ok && d.status && d.status !== 'starting' && d.status !== 'stopping') {
                    window.location.reload();
                } else {
                    setTimeout(poll, 5000);
                }
            })
            .catch(function () { /* σιωπηλά */ });
    }
    if (txt.indexOf('ξεκινά') !== -1 || txt.indexOf('τερματίζεται') !== -1) {
        setTimeout(poll, 5000);
    }
})();
</script>
