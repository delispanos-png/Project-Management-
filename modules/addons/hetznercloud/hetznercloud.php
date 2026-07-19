<?php
/**
 * Hetzner Cloud — WHMCS addon module.
 *
 * Control centre for the white-label cloud offering:
 *   • Global API token & branding
 *   • Availability dashboard (types × locations)
 *   • Automatic %-markup pricing sync into WHMCS products
 *   • Import / adopt already-sold services into the automation
 *
 * @package WHMCS\Module\Addon\HetznerCloud
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\HetznerCloud\Db;
use WHMCS\Module\Addon\HetznerCloud\Sync;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Db.php';
require_once __DIR__ . '/lib/Sync.php';

function hetznercloud_config()
{
    return [
        'name'        => 'Hetzner Cloud Control Centre',
        'description' => 'White-label cloud provisioning control centre: API token, automatic %-markup pricing sync, availability and import of already-sold services.',
        'version'     => '1.0.0',
        'author'      => 'Cloudon',
        'language'    => 'english',
        'fields'      => [
            'api_token' => [
                'FriendlyName' => 'Hetzner API Token',
                'Type'         => 'password',
                'Size'         => '60',
                'Description'  => 'Read/write token from Hetzner Cloud Console → Security → API Tokens. Used for catalogue, pricing and imports.',
            ],
            'brand_name' => [
                'FriendlyName' => 'Brand Name (white-label)',
                'Type'         => 'text',
                'Size'         => '30',
                'Default'      => 'Cloud Server',
                'Description'  => 'Shown to clients everywhere instead of "Hetzner".',
            ],
            'default_markup' => [
                'FriendlyName' => 'Default Markup %',
                'Type'         => 'text',
                'Size'         => '6',
                'Default'      => '40',
                'Description'  => 'Applied over Hetzner cost, e.g. 40 = +40%. Per-product overrides available below.',
            ],
            'price_basis' => [
                'FriendlyName' => 'Cost Basis',
                'Type'         => 'dropdown',
                'Options'      => 'net,gross',
                'Default'      => 'net',
                'Description'  => 'Use Hetzner net (ex-VAT) or gross prices as the cost baseline.',
            ],
            'rounding' => [
                'FriendlyName' => 'Price Rounding',
                'Type'         => 'dropdown',
                'Options'      => 'up_cent,nearest_cent,up_10cent,up_euro,psych_99,none',
                'Default'      => 'up_cent',
                'Description'  => 'up_cent = round up to nearest cent; psych_99 = x.99 pricing.',
            ],
            'billing_cycle' => [
                'FriendlyName' => 'Billing Cycle to Update',
                'Type'         => 'dropdown',
                'Options'      => 'monthly,quarterly,semiannually,annually,biennially,triennially',
                'Default'      => 'monthly',
                'Description'  => 'Which WHMCS recurring price column the sync writes to.',
            ],
            'currency_id' => [
                'FriendlyName' => 'WHMCS Currency ID',
                'Type'         => 'text',
                'Size'         => '4',
                'Default'      => '1',
                'Description'  => 'Currency id to update (Setup → Currencies). Usually 1 for the default.',
            ],
            'auto_apply' => [
                'FriendlyName' => 'Fully Automatic Pricing',
                'Type'         => 'yesno',
                'Default'      => 'on',
                'Description'  => 'When ticked, the daily cron writes new prices to products automatically. Untick to review before applying.',
            ],
            'sync_stock' => [
                'FriendlyName' => 'Sync Stock with Availability',
                'Type'         => 'yesno',
                'Default'      => 'on',
                'Description'  => 'Enable stock control and set qty from live Hetzner availability: available → the qty below, unavailable → 0 (blocks ordering).',
            ],
            'available_stock' => [
                'FriendlyName' => 'Stock qty when available',
                'Type'         => 'text',
                'Size'         => '5',
                'Default'      => '1',
                'Description'  => 'Quantity to set while the type is available (e.g. 1). Kept at this value after each sale.',
            ],
            'sync_description' => [
                'FriendlyName' => 'Auto-generate Product Description',
                'Type'         => 'dropdown',
                'Options'      => 'if_empty,always,off',
                'Default'      => 'if_empty',
                'Description'  => 'Auto-fill the product HTML description from the Hetzner specs (CPU/RAM/disk/traffic/OS/location) using the site template. if_empty = only when the description is blank (safe for new products, never overwrites); always = rewrite on every sync; off = never.',
            ],
            'annual_multiplier' => [
                'FriendlyName' => 'Prepay Cycle Multiplier',
                'Type'         => 'text',
                'Size'         => '6',
                'Default'      => '12',
                'Description'  => 'When updating annually etc., monthly sell price × this number. 12 = no discount.',
            ],
        ],
    ];
}

function hetznercloud_activate()
{
    try {
        Db::install();
        return ['status' => 'success', 'description' => 'Hetzner Cloud tables created. Configure the API token, then map products under the Pricing tab.'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Could not create tables: ' . $e->getMessage()];
    }
}

function hetznercloud_deactivate()
{
    // Keep data; drop nothing automatically.
    return ['status' => 'success', 'description' => 'Deactivated. Data tables retained.'];
}

/**
 * Admin dashboard.
 */
function hetznercloud_output($vars)
{
    $modulelink = $vars['modulelink'];
    $token = $vars['api_token'] ?? '';
    $tab = $_GET['tab'] ?? 'availability';

    if (!$token) {
        echo '<div class="alert alert-warning">Set your <strong>Hetzner API Token</strong> in this addon\'s settings, then reload.</div>';
        return;
    }

    $sync = new Sync($vars);

    // ---- POST handlers ----
    $flash = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $flash = hetznercloud_handlePost($sync, $_POST);
    }

    // ---- Tab nav ----
    $tabs = ['availability' => 'Availability', 'pricing' => 'Pricing & Mapping', 'import' => 'Import / Adopt', 'logs' => 'Logs'];
    echo '<ul class="nav nav-tabs" style="margin-bottom:15px">';
    foreach ($tabs as $k => $label) {
        $active = $tab === $k ? ' class="active"' : '';
        echo '<li' . $active . '><a href="' . $modulelink . '&tab=' . $k . '">' . $label . '</a></li>';
    }
    echo '</ul>';

    if ($flash) {
        echo $flash;
    }

    try {
        switch ($tab) {
            case 'pricing':
                hetznercloud_tabPricing($sync, $modulelink);
                break;
            case 'import':
                hetznercloud_tabImport($sync, $modulelink);
                break;
            case 'logs':
                hetznercloud_tabLogs();
                break;
            default:
                hetznercloud_tabAvailability($sync, $modulelink);
        }
    } catch (\Throwable $e) {
        echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

function hetznercloud_handlePost(Sync $sync, array $post)
{
    $action = $post['do'] ?? '';
    try {
        if ($action === 'savemap') {
            Db::saveMapping([
                'whmcs_pid'      => (int) $post['whmcs_pid'],
                'server_type'    => preg_replace('/[^a-z0-9\-]/i', '', $post['server_type']),
                'kind'           => 'server',
                'location'       => preg_replace('/[^a-z0-9\-]/i', '', $post['location'] ?? '') ?: null,
                'markup'         => ($post['markup'] === '' ? null : (float) $post['markup']),
                'include_ipv4'   => !empty($post['include_ipv4']) ? 1 : 0,
                'include_backup' => !empty($post['include_backup']) ? 1 : 0,
            ]);
            return '<div class="alert alert-success">Mapping saved.</div>';
        }
        if ($action === 'delmap') {
            Db::deleteMapping((int) $post['whmcs_pid']);
            return '<div class="alert alert-success">Mapping removed.</div>';
        }
        if ($action === 'savesync') {
            $pid = (int) $post['whmcs_pid'];
            $type = strtolower(preg_replace('/[^a-z0-9\-]/i', '', $post['server_type'] ?? ''));
            $markup = (isset($post['markup']) && $post['markup'] !== '') ? (float) $post['markup'] : null;
            Db::saveTypeOverride($pid, $type, $markup);
            $res = $sync->applyOne($pid);
            if (!empty($res['error'])) {
                return '<div class="alert alert-danger">#' . $pid . ': ' . htmlspecialchars($res['error']) . '</div>';
            }
            $stockMsg = '';
            if (array_key_exists('stock', $res) && $res['stock'] !== null) {
                $stockMsg = ' · Stock: <strong>' . (int) $res['stock'] . '</strong> ('
                    . (!empty($res['available']) ? 'διαθέσιμο' : 'μη διαθέσιμο') . ')';
            }
            return '<div class="alert alert-success">#' . $pid . ' (' . strtoupper($type) . '): τιμή <strong>'
                . number_format($res['price'], 2) . '€</strong> '
                . ($res['applied'] ? 'εφαρμόστηκε ✓' : '<span class="text-danger">ΔΕΝ εφαρμόστηκε</span>')
                . ' (κόστος ' . number_format($res['cost'], 2) . '€)' . $stockMsg . '.</div>';
        }
        if ($action === 'syncone') {
            $res = $sync->applyOne((int) $post['whmcs_pid']);
            if (!empty($res['error'])) {
                return '<div class="alert alert-danger">' . htmlspecialchars($res['error']) . '</div>';
            }
            return '<div class="alert alert-success">Product #' . (int) $post['whmcs_pid']
                . ': νέα τιμή <strong>' . number_format($res['price'], 2) . '</strong> '
                . ($res['applied'] ? 'εφαρμόστηκε ✓' : '<span class="text-danger">δεν εφαρμόστηκε</span>')
                . ' (κόστος ' . number_format($res['cost'], 2) . ').</div>';
        }
        if ($action === 'syncnow') {
            $apply = !empty($post['apply']);
            $report = $sync->run($apply);
            $applied = count(array_filter($report, function ($r) { return !empty($r['applied']); }));
            return '<div class="alert alert-success">Sync complete. ' . count($report) . ' product(s) processed, ' . $applied . ' price(s) updated.</div>';
        }
        if ($action === 'link') {
            $res = $sync->linkService((int) $post['serviceid'], (int) $post['serverid']);
            if ($res === true) {
                return '<div class="alert alert-success">Service #' . (int) $post['serviceid'] . ' linked.</div>';
            }
            return '<div class="alert alert-danger">Link failed: ' . htmlspecialchars($res) . '</div>';
        }
    } catch (\Throwable $e) {
        return '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    return '';
}

// ---------------------------------------------------------------------
// Tabs
// ---------------------------------------------------------------------

/**
 * Classify a Hetzner server type into the same buckets the Hetzner console uses,
 * so admins can find the right type at a glance.
 *
 * Returns [orderIndex, label] keyed by the type-name prefix.
 */
function hetznercloud_typeCategory($name)
{
    $n = strtolower($name);
    if (strpos($n, 'ccx') === 0) {
        return [1, 'General Purpose — Dedicated vCPU (CCX · x86 AMD)'];
    }
    if (strpos($n, 'cpx') === 0) {
        return [2, 'Regular Performance — Shared (CPX · x86 AMD)'];
    }
    if (strpos($n, 'cax') === 0) {
        return [3, 'Cost-Optimized — Shared (CAX · Arm64)'];
    }
    if (strpos($n, 'cx') === 0) {
        return [4, 'Cost-Optimized — Shared (CX · x86 Intel)'];
    }
    return [9, 'Other'];
}

function hetznercloud_tabAvailability(Sync $sync, $modulelink)
{
    $cat = $sync->catalogue();
    $meta = $cat['meta'];
    echo '<p><strong>Currency:</strong> ' . htmlspecialchars($meta['currency']) . ' &nbsp; '
        . '<strong>Basis:</strong> ' . htmlspecialchars($meta['basis']) . ' &nbsp; '
        . '<strong>IPv4/mo:</strong> ' . number_format($meta['ipv4'], 2) . '</p>';

    // Bucket types by Hetzner-console category.
    $groups = [];
    foreach ($cat['types'] as $t) {
        if ($t['deprecated']) {
            continue;
        }
        list($ord, $label) = hetznercloud_typeCategory($t['name']);
        $groups[$ord]['label'] = $label;
        $groups[$ord]['rows'][] = $t;
    }
    ksort($groups);

    foreach ($groups as $g) {
        // Sort within a group by price.
        usort($g['rows'], function ($a, $b) {
            $pa = $a['prices'] ? min($a['prices']) : 0;
            $pb = $b['prices'] ? min($b['prices']) : 0;
            return $pa <=> $pb;
        });
        echo '<h4 style="margin-top:20px">' . htmlspecialchars($g['label'])
            . ' <span class="badge">' . count($g['rows']) . '</span></h4>';
        echo '<div class="table-responsive"><table class="table table-condensed table-bordered">';
        echo '<thead><tr><th style="width:120px">Type</th><th>vCPU</th><th>RAM</th><th>Disk</th><th>From /mo</th><th>Available locations</th></tr></thead><tbody>';
        foreach ($g['rows'] as $t) {
            $min = $t['prices'] ? min($t['prices']) : 0;
            $avail = $t['available_in'] ? implode(' ', array_map(function ($l) {
                return '<span class="label label-success">' . htmlspecialchars($l) . '</span>';
            }, $t['available_in'])) : '<span class="label label-default">none</span>';
            echo '<tr><td><strong>' . htmlspecialchars(strtoupper($t['name'])) . '</strong> <small class="text-muted">' . htmlspecialchars($t['architecture']) . '</small></td>'
                . '<td>' . (int) $t['cores'] . '</td><td>' . (int) $t['memory'] . ' GB</td><td>' . (int) $t['disk'] . ' GB</td>'
                . '<td>' . number_format($min, 2) . '</td><td>' . $avail . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

function hetznercloud_tabPricing(Sync $sync, $modulelink)
{
    $cat = $sync->catalogue();

    // Datalist of valid Hetzner type names (with specs) for the inline field.
    $valid = [];
    $datalist = '<datalist id="hztypes">';
    foreach ($cat['types'] as $t) {
        if ($t['deprecated']) { continue; }
        $valid[strtolower($t['name'])] = $t;
    }
    // sort by category then price for a tidy suggestion list
    uasort($valid, function ($a, $b) {
        list($oa) = hetznercloud_typeCategory($a['name']);
        list($ob) = hetznercloud_typeCategory($b['name']);
        if ($oa !== $ob) { return $oa <=> $ob; }
        $pa = $a['prices'] ? min($a['prices']) : 0;
        $pb = $b['prices'] ? min($b['prices']) : 0;
        return $pa <=> $pb;
    });
    foreach ($valid as $t) {
        $min = $t['prices'] ? min($t['prices']) : 0;
        $datalist .= '<option value="' . htmlspecialchars($t['name']) . '">'
            . strtoupper($t['name']) . ' — ' . (int) $t['cores'] . 'C/' . (int) $t['memory'] . 'G/' . (int) $t['disk'] . 'G · ' . number_format($min, 2) . '€</option>';
    }
    $datalist .= '</datalist>';
    echo $datalist;

    echo '<p class="text-muted" style="margin-bottom:12px">'
        . 'Ο τύπος Hetzner διαβάζεται <strong>αυτόματα</strong> από κάθε προϊόν (Module Settings → Server Type). '
        . 'Απλά συμπλήρωσε/διόρθωσε το όνομα (π.χ. <code>ccx63</code>) και πάτα <strong>Save &amp; Sync</strong> — το module κάνει μόνο του την αντιστοίχιση και γράφει την τιμή.</p>';

    $mappings = $sync->effectiveMappings();

    $stockOn = $sync->stockSyncEnabled();
    $stockQty = $sync->availableStockQty();
    $defMarkup = $sync->defaultMarkup();

    echo '<div class="table-responsive"><table class="table table-condensed table-bordered">';
    echo '<thead><tr><th style="width:22%">Product</th><th>Hetzner Type</th><th>Location</th>'
        . '<th>Markup%</th><th>Cost/mo</th><th>Sell/mo</th><th>Stock</th><th></th></tr></thead><tbody>';

    if (!count($mappings)) {
        echo '<tr><td colspan="8" class="alert-info">Κανένα προϊόν δεν χρησιμοποιεί ακόμα το module «Cloud Servers». '
            . 'Άλλαξε το Module ενός προϊόντος σε «Cloud Servers» και θα εμφανιστεί εδώ αυτόματα.</td></tr>';
    }

    foreach ($mappings as $m) {
        $hasType = ($m->server_type !== '');
        $known = $hasType && isset($valid[strtolower($m->server_type)]);
        $cost = $known ? $sync->costFor($m, $cat) : null;
        $price = $cost !== null ? $sync->sellFor($cost, $m) : null;
        $available = $known ? $sync->availableAnywhere($m, $cat) : false;
        $effMarkup = ($m->markup === null || $m->markup === '') ? $defMarkup : (float) $m->markup;

        // Location label (effective location, prettified from the catalogue).
        $locName = $m->location ?: '';
        $locLabel = $locName !== '' ? ($cat['locations'][$locName] ?? $locName) : 'cheapest';

        echo '<tr>';
        echo '<td>#' . (int) $m->whmcs_pid . ' ' . htmlspecialchars($m->name) . '</td>';

        // Form opens here and closes at the action cell; the display cells in
        // between sit inside the form region, which is fine.
        echo '<td><form method="post" style="margin:0">'
            . '<input type="hidden" name="do" value="savesync">'
            . '<input type="hidden" name="whmcs_pid" value="' . (int) $m->whmcs_pid . '">'
            . '<input type="text" name="server_type" list="hztypes" autocomplete="off" '
            . 'class="form-control input-sm" style="width:130px;display:inline-block" placeholder="π.χ. ccx63" '
            . 'value="' . htmlspecialchars($m->server_type) . '">'
            . (!$hasType ? ' <span class="label label-warning">όρισε τύπο</span>'
                : (!$known ? ' <span class="label label-danger">άγνωστος</span>' : ''))
            . '</td>';

        // Location
        echo '<td>' . ($known ? '<span class="label label-default">' . htmlspecialchars($locLabel) . '</span>' : '—') . '</td>';

        // Markup% (editable override; placeholder shows the default in use)
        echo '<td><input type="text" name="markup" class="form-control input-sm" size="4" '
            . 'placeholder="' . number_format($defMarkup, 0) . ' (default)" '
            . 'value="' . ($m->markup === null ? '' : htmlspecialchars($m->markup)) . '"> '
            . '<small class="text-muted">= ' . number_format($effMarkup, 0) . '%</small></td>';

        // Cost / Sell
        echo '<td>' . ($cost !== null ? number_format($cost, 2) . '€' : '—') . '</td>';
        echo '<td><strong>' . ($price !== null ? number_format($price, 2) . '€' : '—') . '</strong></td>';

        // Stock (availability-driven)
        if (!$known) {
            echo '<td>—</td>';
        } elseif (!$stockOn) {
            echo '<td><span class="text-muted" title="Stock sync ανενεργό">off</span></td>';
        } elseif ($available) {
            echo '<td><span class="label label-success">' . $stockQty . ' ✓</span></td>';
        } else {
            echo '<td><span class="label label-danger">0 ✗</span></td>';
        }

        echo '<td style="white-space:nowrap">'
            . '<button class="btn btn-xs btn-success" title="Αποθήκευση & ενημέρωση τιμής/stock"><i class="fa fa-refresh"></i> Save &amp; Sync</button>'
            . '</form></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';

    echo '<form method="post" style="margin-top:10px">';
    echo '<input type="hidden" name="do" value="syncnow">';
    echo '<label><input type="checkbox" name="apply" value="1" checked> Apply prices to products</label> ';
    echo '<button class="btn btn-success">Sync ΟΛΩΝ των τιμών τώρα</button>';
    echo '<span class="text-muted"> — το ημερήσιο cron το κάνει αυτόματα όταν το «Fully Automatic Pricing» είναι ON.</span>';
    echo '</form>';
}

function hetznercloud_tabImport(Sync $sync, $modulelink)
{
    $data = $sync->importCandidates();
    echo '<p>Match already-sold WHMCS services to their existing Hetzner servers. Linked services immediately gain full control-panel management — nothing is recreated.</p>';

    // Build a select of all live servers.
    $serverOpts = '';
    foreach ($data['servers'] as $s) {
        $ip = $s['public_net']['ipv4']['ip'] ?? '';
        $serverOpts .= '<option value="' . (int) $s['id'] . '">#' . (int) $s['id'] . ' ' . htmlspecialchars($s['name'])
            . ($ip ? ' (' . htmlspecialchars($ip) . ')' : '') . '</option>';
    }

    echo '<div class="table-responsive"><table class="table table-condensed table-bordered">';
    echo '<thead><tr><th>Service</th><th>Domain</th><th>IP</th><th>Status</th><th>Suggested match</th><th>Link to server</th></tr></thead><tbody>';
    foreach ($data['services'] as $row) {
        echo '<tr>';
        echo '<td>#' . (int) $row['serviceid'] . '</td>';
        echo '<td>' . htmlspecialchars($row['domain']) . '</td>';
        echo '<td>' . htmlspecialchars($row['ip']) . '</td>';
        echo '<td>' . ($row['linked'] ? '<span class="label label-success">linked</span>' : '<span class="label label-warning">unlinked</span>') . '</td>';
        echo '<td>' . ($row['guess_id'] ? ('#' . (int) $row['guess_id'] . ' <small>by ' . htmlspecialchars($row['guess_by']) . '</small>') : '—') . '</td>';
        echo '<td>';
        if (!$row['linked']) {
            echo '<form method="post" class="form-inline" style="margin:0">';
            echo '<input type="hidden" name="do" value="link"><input type="hidden" name="serviceid" value="' . (int) $row['serviceid'] . '">';
            $sel = str_replace('value="' . (int) $row['guess_id'] . '"', 'value="' . (int) $row['guess_id'] . '" selected', $serverOpts);
            echo '<select name="serverid" class="form-control input-sm">' . $sel . '</select> ';
            echo '<button class="btn btn-xs btn-primary">Link</button>';
            echo '</form>';
        } else {
            echo '<em>done</em>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function hetznercloud_tabLogs()
{
    echo '<div class="table-responsive"><table class="table table-condensed table-striped">';
    echo '<thead><tr><th style="width:160px">Time</th><th style="width:70px">Level</th><th>Message</th></tr></thead><tbody>';
    foreach (Db::recentLogs(60) as $l) {
        $cls = $l->level === 'error' ? 'text-danger' : ($l->level === 'warning' ? 'text-warning' : '');
        echo '<tr class="' . $cls . '"><td>' . htmlspecialchars($l->ts) . '</td><td>' . htmlspecialchars($l->level) . '</td><td>' . htmlspecialchars($l->message) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
