<?php
/**
 * Hetzner Cloud — auto-reconcile cron.
 *
 * Runs Sync::reconcileAll(write) across ALL enabled projects and auto-links
 * every live VM that matches a WHMCS service on a hetznercloud product but
 * isn't linked yet. This makes "add a project → its VMs just work" true
 * without per-VM manual adoption. Non-destructive: only ever ADDS instance
 * mappings + sets username=hz-<id>; never powers off / deletes anything.
 *
 * Suggested schedule (every 15 min is plenty; hourly is fine too):
 *   *\/15 * * * * <user> /opt/plesk/php/8.3/bin/php -q \
 *     /var/www/vhosts/cloudon.gr/my.cloudon.gr/modules/addons/hetznercloud/crons/reconcile.php >/dev/null 2>&1
 *
 * @package WHMCS\Module\Addon\HetznerCloud
 */

$whmcsRoot = dirname(__DIR__, 4); // crons → hetznercloud → addons → modules → <root>
require_once $whmcsRoot . '/init.php';

require_once dirname(__DIR__) . '/lib/Db.php';
require_once dirname(__DIR__) . '/lib/Sync.php'; // pulls in the server-module Api + Pricing

use WHMCS\Module\Addon\HetznerCloud\Db;
use WHMCS\Module\Addon\HetznerCloud\Sync;

$lockFile = sys_get_temp_dir() . '/hetzner_reconcile.lock';
$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // previous run still going
}

try {
    if (!\WHMCS\Database\Capsule::schema()->hasTable('mod_hetzner_projects')) {
        exit(0); // addon not installed
    }
    $sync = new Sync([]);
    // write=true, autoMigrate=true → self-service: new projects' genuine VPS
    // auto-adopt (product auto-created + service migrated + linked), no manual work.
    $r = $sync->reconcileAll(true, true);
    // reconcileAll() already writes a summary line to mod_hetzner_log.
} catch (\Throwable $e) {
    try {
        Db::log('reconcile cron fatal: ' . $e->getMessage(), 'error');
    } catch (\Throwable $e2) {
        error_log('hetzner reconcile cron fatal: ' . $e->getMessage());
    }
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

/* Εξαιρέσεις αναστολής: κάλυψε αυτόματα και νέες υπηρεσίες των εξαιρεμένων πελατών */
try {
    $exclHz = (string) (\WHMCS\Database\Capsule::table('tbladdonmodules')->where('module', 'hetznercloud')
        ->where('setting', 'suspend_exclude_clients')->value('value') ?? '');
    $idsHz = array_filter(array_map('intval', explode(',', $exclHz)));
    if ($idsHz) {
        $nHz = \WHMCS\Database\Capsule::table('tblhosting')->whereIn('userid', $idsHz)
            ->whereIn('domainstatus', ['Active', 'Suspended'])
            ->where('overideautosuspend', 0)
            ->update(['overideautosuspend' => 1, 'overidesuspenduntil' => '2099-12-31']);
        if ($nHz && function_exists('logActivity')) {
            logActivity("HetznerCloud reconcile: εξαίρεση αναστολής εφαρμόστηκε σε $nHz νέες υπηρεσίες");
        }
    }
} catch (\Throwable $e) {
}
