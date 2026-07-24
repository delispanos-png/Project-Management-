<?php
/**
 * GoodDay Sync reconciler — run every 1 minute from system cron:
 *
 *   * * * * * /opt/plesk/php/8.3/bin/php -q \
 *     /var/www/vhosts/cloudon.gr/my.cloudon.gr/modules/addons/gooddaysync/crons/reconcile.php >/dev/null 2>&1
 *
 * Responsibilities (spec §4.3/§4.4 + §5):
 *   • GoodDay → WHMCS: apply !public message create/edit/delete + status.
 *   • WHMCS → GoodDay: detect reply edits/deletes (no WHMCS hooks exist for
 *     these) by comparing signatures, and propagate.
 *
 * Throttling: only mapped, non-deleted tickets in the configured statuses;
 * per-ticket try/catch + backoff so one failure never stops the batch.
 * Full-ticket-delete is NEVER performed.
 *
 * @package WHMCS\Module\Addon\GoodDaySync
 */

// ---- bootstrap WHMCS (Capsule + localAPI) ----
$whmcsRoot = dirname(__DIR__, 4); // crons → gooddaysync → addons → modules → <root>
require_once $whmcsRoot . '/init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\GoodDaySync\Db;
use WHMCS\Module\Addon\GoodDaySync\SyncState;

require_once dirname(__DIR__) . '/lib/Db.php';
require_once dirname(__DIR__) . '/lib/Formatter.php';
require_once dirname(__DIR__) . '/lib/GoodDayClient.php';
require_once dirname(__DIR__) . '/lib/SyncState.php';
require_once dirname(__DIR__) . '/gooddaysync.php';

// ---- single-instance lock (avoid overlapping runs) ----
$lockFile = sys_get_temp_dir() . '/gooddaysync_reconcile.lock';
$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // previous run still going
}

try {
    $settings = gooddaysync_settings();
    if (empty($settings) || trim((string) ($settings['gd_api_token'] ?? '')) === '') {
        // not configured yet
        flock($lock, LOCK_UN);
        exit(0);
    }

    $dry    = SyncState::flag($settings, 'dry_run', true);
    $engine = SyncState::fromSettings($settings);

    // Statuses to scan. We scan EVERY non-closed ticket (regardless of its
    // current WHMCS status) so a GoodDay status change is always detected on
    // the next run — a narrow allow-list used to skip custom statuses like
    // "Offer Approved"/"Take a Look", making GD→WHMCS status sync look slow.
    // The configured whmcs_statuses list is treated as an ALWAYS-scan set on
    // top of that (never used to exclude non-closed tickets).
    $statuses = array_filter(array_map('trim', explode(',', (string) ($settings['whmcs_statuses'] ?? ''))));
    $statusLc = array_map('strtolower', $statuses);

    Db::cleanupTombstones();

    $rows = Db::activeTickets(500);
    $processed = 0;
    foreach ($rows as $row) {
        try {
            // Skip only closed/cancelled tickets (cheap live status check).
            $st = Capsule::table('tbltickets')->where('id', (int) $row->ticketid)->value('status');
            if ($st !== null) {
                $stl = function_exists('mb_strtolower')
                    ? mb_strtolower(trim((string) $st))
                    : strtolower(trim((string) $st));
                $inAllowList = in_array($stl, $statusLc, true);
                $isClosed = ($stl === 'closed')
                    || (strpos($stl, 'κλειστ') !== false)
                    || (strpos($stl, 'cancel') !== false)
                    || (strpos($stl, 'ακυρ') !== false);
                if ($isClosed && !$inAllowList) {
                    continue;
                }
            }
            // GoodDay → WHMCS
            $engine->reconcileTicket($row);
            // WHMCS reply edits/deletes → GoodDay (no hooks for these)
            $engine->detectWhmcsEditsAndDeletes($row);
            $processed++;
        } catch (\Throwable $e) {
            Db::log('reconcile.ticket.error', $e->getMessage(), 'error', $row->ticketid);
        }
    }

    Db::log('reconcile.run', 'processed ' . $processed . ' ticket(s)' . ($dry ? ' [DRY_RUN]' : ''), 'info');
} catch (\Throwable $e) {
    Db::log('reconcile.fatal', $e->getMessage(), 'error');
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
