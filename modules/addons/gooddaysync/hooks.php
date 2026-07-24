<?php
/**
 * WHMCS → GoodDay event hooks (instant, zero polling).
 *
 *   TicketOpen        → create GoodDay task (+ custom fields + initial message)
 *   TicketUserReply   → add GoodDay comment
 *   TicketAdminReply  → add GoodDay comment  (loop-guarded: replies injected by
 *                       the reconciler from GoodDay are NOT pushed back)
 *   TicketStatusChange→ status → GoodDay custom field
 *
 * Every handler is wrapped in try/catch so a sync error can never break the
 * WHMCS ticket flow. In DRY_RUN nothing is written — it only computes + logs.
 * There is deliberately NO TicketDelete handler (full-ticket-delete disabled).
 *
 * @package WHMCS\Module\Addon\GoodDaySync
 */

use WHMCS\Module\Addon\GoodDaySync\Db;
use WHMCS\Module\Addon\GoodDaySync\SyncState;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Db.php';
require_once __DIR__ . '/lib/Formatter.php';
require_once __DIR__ . '/lib/GoodDayClient.php';
require_once __DIR__ . '/lib/SyncState.php';

/**
 * Build a sync engine from the stored addon settings, or null if the module
 * is not configured with an API token (avoids doing work when unset).
 */
function gooddaysync_engine()
{
    if (!function_exists('gooddaysync_settings')) {
        require_once __DIR__ . '/gooddaysync.php';
    }
    $settings = gooddaysync_settings();
    if (empty($settings)) {
        return null;
    }
    // In DRY_RUN we still run (shadow phase). If no token AND not dry-run, skip.
    $dry = SyncState::flag($settings, 'dry_run', true);
    if (!$dry && trim((string) ($settings['gd_api_token'] ?? '')) === '') {
        return null;
    }
    return SyncState::fromSettings($settings);
}

add_hook('TicketOpen', 1, function ($vars) {
    try {
        $ticketid = (int) ($vars['ticketid'] ?? 0);
        if ($ticketid <= 0) {
            return;
        }
        $engine = gooddaysync_engine();
        if ($engine) {
            $engine->createTaskForTicket($ticketid);
        }
    } catch (\Throwable $e) {
        Db::log('hook.TicketOpen.error', $e->getMessage(), 'error', $vars['ticketid'] ?? null);
    }
});

$gooddaysync_reply_hook = function ($vars) {
    try {
        $ticketid = (int) ($vars['ticketid'] ?? 0);
        if ($ticketid <= 0) {
            return;
        }
        $engine = gooddaysync_engine();
        if ($engine) {
            $engine->pushReply($ticketid, $vars['replyid'] ?? null);
        }
    } catch (\Throwable $e) {
        Db::log('hook.TicketReply.error', $e->getMessage(), 'error', $vars['ticketid'] ?? null);
    }
};

add_hook('TicketUserReply', 1, $gooddaysync_reply_hook);
add_hook('TicketAdminReply', 1, $gooddaysync_reply_hook);

add_hook('TicketStatusChange', 1, function ($vars) {
    try {
        $ticketid = (int) ($vars['ticketid'] ?? 0);
        $status   = (string) ($vars['status'] ?? '');
        if ($ticketid <= 0 || $status === '') {
            return;
        }
        $engine = gooddaysync_engine();
        if ($engine) {
            $engine->pushStatus($ticketid, $status);
        }
    } catch (\Throwable $e) {
        Db::log('hook.TicketStatusChange.error', $e->getMessage(), 'error', $vars['ticketid'] ?? null);
    }
});
