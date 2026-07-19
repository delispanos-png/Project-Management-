<?php
/**
 * Adds a "My Cloud Servers" panel to the client-area homepage listing the
 * customer's active VPS services with a live status badge and a one-click link
 * into the control panel — so they can reach power/console/etc. without digging.
 */

use WHMCS\Database\Capsule;
use WHMCS\View\Menu\Item as MenuItem;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ClientAreaHomepagePanels', 1, function (MenuItem $panels) {
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if (!$uid) {
        return;
    }

    $pids = Capsule::table('tblproducts')->where('servertype', 'hetznercloud')->pluck('id')->all();
    if (!$pids) {
        return;
    }
    $svcs = Capsule::table('tblhosting')
        ->where('userid', $uid)
        ->whereIn('packageid', $pids)
        ->whereIn('domainstatus', ['Active', 'Suspended'])
        ->orderBy('id', 'desc')
        ->get(['id', 'domain', 'dedicatedip']);
    if ($svcs->isEmpty()) {
        return;
    }

    $rows = '';
    foreach ($svcs as $s) {
        $name = $s->domain ?: ('Server #' . $s->id);
        $ip = $s->dedicatedip ? '<small class="text-muted"><code>' . htmlspecialchars($s->dedicatedip) . '</code></small>' : '';
        $rows .= '<tr>'
            . '<td style="vertical-align:middle">' . htmlspecialchars($name) . '<br>' . $ip . '</td>'
            . '<td style="vertical-align:middle;text-align:center"><span class="label label-default hz-dash-status" data-id="' . (int) $s->id . '">…</span></td>'
            . '<td style="vertical-align:middle;text-align:right"><a href="clientarea.php?action=productdetails&id=' . (int) $s->id . '" class="btn btn-xs btn-primary"><i class="fas fa-cog"></i> Διαχείριση</a></td>'
            . '</tr>';
    }

    $html = '<table class="table table-condensed" style="margin-bottom:0">'
        . '<tbody>' . $rows . '</tbody></table>'
        . '<script>(function(){'
        . 'function upd(el,txt,cls){el.textContent=txt;el.className="label label-"+cls+" hz-dash-status";}'
        . 'document.querySelectorAll(".hz-dash-status").forEach(function(el){'
        . 'var id=el.getAttribute("data-id");'
        . 'fetch("clientarea.php?action=productdetails&id="+id+"&hzajax=status",{credentials:"same-origin"})'
        . '.then(function(r){return r.json();}).then(function(d){'
        . 'if(!d.ok){upd(el,"—","default");return;}'
        . 'var m={running:["Σε λειτουργία","success"],off:["Εκτός λειτουργίας","default"]};'
        . 'var v=m[d.status]||[d.status,"warning"];upd(el,v[0],v[1]);'
        . '}).catch(function(){upd(el,"—","default");});'
        . '});})();</script>';

    $panel = $panels->addChild('HetznerServers', [
        'name'  => 'HetznerServers',
        'label' => 'Οι Cloud Servers μου',
        'icon'  => 'fas fa-server',
        'order' => 5,
    ]);
    $panel->setBodyHtml($html);
});
