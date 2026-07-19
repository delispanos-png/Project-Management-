<?php
/**
 * Hetzner Cloud addon hooks.
 *
 * Runs the automatic pricing/availability sync on the WHMCS daily cron.
 * Honours the "Fully Automatic Pricing" setting: when off, prices are
 * recomputed and logged but not written to products.
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

/**
 * Read all settings the addon saved, decrypting the token.
 */
function hetznercloud_addon_settings()
{
    $out = [];
    try {
        $rows = Capsule::table('tbladdonmodules')->where('module', 'hetznercloud')->get();
        foreach ($rows as $r) {
            $out[$r->setting] = $r->value;
        }
        // Handle plaintext or encrypted storage uniformly.
        if (!empty($out['api_token'])) {
            $out['api_token'] = \WHMCS\Module\Server\HetznerCloud\Api::normalizeToken($out['api_token']);
        }
    } catch (\Exception $e) {
        // return whatever we have
    }
    return $out;
}

add_hook('DailyCronJob', 1, function ($vars) {
    $cfg = hetznercloud_addon_settings();
    if (empty($cfg['api_token'])) {
        return;
    }
    try {
        $sync = new Sync($cfg);
        $apply = ($cfg['auto_apply'] ?? 'on') === 'on';
        $report = $sync->run($apply);
        $changed = count(array_filter($report, function ($r) { return !empty($r['changed']); }));
        Db::log('Daily sync: ' . count($report) . ' mapping(s), ' . $changed . ' price change(s), apply=' . ($apply ? 'yes' : 'no'), 'info');
    } catch (\Throwable $e) {
        Db::log('Daily sync failed: ' . $e->getMessage(), 'error');
    }
});
