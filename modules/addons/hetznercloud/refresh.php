<?php
/**
 * CLI-only availability refresher for the Hetzner Cloud addon.
 *
 * Updates the availability cache + product stock from live Hetzner data so the
 * store "Not Available" state and the order-form location filter stay current.
 * Scheduled from /etc/cron.d every ~15 minutes.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

$root = dirname(__DIR__, 3); // modules/addons/hetznercloud → WHMCS root
require $root . '/init.php';

use WHMCS\Database\Capsule;

require_once __DIR__ . '/lib/Db.php';
require_once __DIR__ . '/lib/Sync.php';

$cfg = [];
foreach (Capsule::table('tbladdonmodules')->where('module', 'hetznercloud')->get() as $r) {
    $cfg[$r->setting] = $r->value;
}
if (empty($cfg['api_token'])) {
    exit(0);
}

try {
    (new \WHMCS\Module\Addon\HetznerCloud\Sync($cfg))->refreshAvailability();
} catch (\Throwable $e) {
    \WHMCS\Module\Addon\HetznerCloud\Db::log('Availability refresh failed: ' . $e->getMessage(), 'error');
}
