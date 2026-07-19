<?php
/**
 * Restrict the "Location" configurable option on the order form to the
 * locations where the product's Hetzner server type is actually available.
 *
 * A type may exist in only some datacenters (availability differs per region);
 * this disables the unavailable location choices so customers can only order a
 * working combination. Availability comes from the cache the pricing sync writes.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    $a = $_REQUEST['a'] ?? '';
    if (!in_array($a, ['add', 'confproduct'], true)) {
        return '';
    }

    // Resolve the product id being configured (direct add, or a cart item).
    $pid = (int) ($_REQUEST['pid'] ?? 0);
    if (!$pid && $a === 'confproduct' && isset($_REQUEST['i'])) {
        $i = (int) $_REQUEST['i'];
        $pid = (int) ($_SESSION['cart']['products'][$i]['pid'] ?? 0);
    }
    if (!$pid) {
        return '';
    }

    $prod = Capsule::table('tblproducts')->where('id', $pid)->first(['servertype', 'configoption1']);
    if (!$prod || $prod->servertype !== 'hetznercloud' || empty($prod->configoption1)) {
        return '';
    }

    require_once __DIR__ . '/../../modules/addons/hetznercloud/lib/Db.php';
    require_once __DIR__ . '/../../modules/addons/hetznercloud/lib/Sync.php';
    $cache = \WHMCS\Module\Addon\HetznerCloud\Sync::readAvailabilityCache();
    if (!$cache || empty($cache['types'])) {
        return '';
    }

    $type = strtolower($prod->configoption1);
    if (!array_key_exists($type, $cache['types'])) {
        return '';
    }
    $availJson = json_encode(array_values($cache['types'][$type]));
    $allJson = json_encode(array_values($cache['all_cities'] ?? []));

    return <<<HTML
<script>
(function () {
    var avail = ($availJson || []).map(function (s) { return String(s).trim().toLowerCase(); });
    var all = ($allJson || []).map(function (s) { return String(s).trim().toLowerCase(); });
    function clean(t) { return String(t).replace(/\\s*\\(unavailable\\)\\s*$/i, '').trim().toLowerCase(); }
    function cityOf(text) {
        var t = clean(text);
        for (var k = 0; k < all.length; k++) { if (t.indexOf(all[k]) !== -1) return all[k]; }
        return null;
    }
    function apply() {
        var selects = document.querySelectorAll('select');
        for (var i = 0; i < selects.length; i++) {
            var sel = selects[i], opts = sel.options, isLoc = false;
            for (var j = 0; j < opts.length; j++) {
                if (cityOf(opts[j].text)) { isLoc = true; break; }
            }
            if (!isLoc) continue;
            var firstAvail = null, selectedDisabled = false;
            for (var j = 0; j < opts.length; j++) {
                var c = cityOf(opts[j].text);
                if (c && avail.indexOf(c) === -1) {
                    if (!opts[j].disabled) {
                        opts[j].disabled = true;
                        opts[j].text = opts[j].text.replace(/\\s*\\(unavailable\\)\\s*$/i, '') + ' (unavailable)';
                    }
                    if (opts[j].selected) selectedDisabled = true;
                } else if (avail.indexOf(c) !== -1 && firstAvail === null) {
                    firstAvail = opts[j];
                }
            }
            if (selectedDisabled && firstAvail) {
                firstAvail.selected = true;
                if (window.jQuery) { jQuery(sel).trigger('change'); }
            }
        }
    }
    apply();
    setTimeout(apply, 600);
    setTimeout(apply, 1500);
})();
</script>
HTML;
});
