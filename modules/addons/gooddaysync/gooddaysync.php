<?php
/**
 * GoodDay Sync — native WHMCS ↔ GoodDay integration.
 *
 * Replaces the external Python "whmcs-goodday-sync" middleware with an
 * event-driven WHMCS addon: hooks push WHMCS→GoodDay instantly; a 1-minute
 * cron reconciler pulls GoodDay→WHMCS and detects WHMCS reply edits/deletes.
 *
 * SAFETY:
 *   • Ships in DRY_RUN by default — computes + logs, writes nothing. Turn off
 *     ONLY after the old Python container is stopped (one writer at a time).
 *   • Full-ticket-delete is NEVER implemented — no DeleteTicket code path.
 *
 * @package WHMCS\Module\Addon\GoodDaySync
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\GoodDaySync\Db;
use WHMCS\Module\Addon\GoodDaySync\GoodDayClient;
use WHMCS\Module\Addon\GoodDaySync\SyncState;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Db.php';
require_once __DIR__ . '/lib/Formatter.php';
require_once __DIR__ . '/lib/GoodDayClient.php';
require_once __DIR__ . '/lib/SyncState.php';

/**
 * Load the addon settings as a flat array (used by hooks + cron).
 */
function gooddaysync_settings()
{
    $out = [];
    try {
        $rows = Capsule::table('tbladdonmodules')->where('module', 'gooddaysync')->get();
        foreach ($rows as $r) {
            $out[$r->setting] = $r->value;
        }
    } catch (\Throwable $e) {
    }
    return $out;
}

function gooddaysync_config()
{
    return [
        'name'        => 'GoodDay Sync',
        'description' => 'Native, event-driven WHMCS ↔ GoodDay tickets/tasks sync. Replaces the external Python middleware. Ships in DRY_RUN — enable live writes only after the old service is stopped.',
        'version'     => '1.0.0',
        'author'      => 'Cloudon',
        'language'    => 'english',
        'fields'      => [

            /* ---- Master safety switch ---- */
            'dry_run' => [
                'FriendlyName' => '1) DRY_RUN (safety)',
                'Type'         => 'dropdown',
                'Options'      => 'on,off',
                'Default'      => 'on',
                'Description'  => 'ON = compute + log only, NO writes to GoodDay or WHMCS. Set OFF only AFTER the old Python container is stopped.',
            ],

            /* ---- WHMCS side ---- */
            'whmcs_admin_username' => [
                'FriendlyName' => 'WHMCS Admin Username',
                'Type'         => 'text', 'Size' => '24', 'Default' => 'support',
                'Description'  => 'Used as adminusername for GoodDay→WHMCS reply writes.',
            ],
            'whmcs_statuses' => [
                'FriendlyName' => 'Ticket Statuses to Reconcile',
                'Type'         => 'text', 'Size' => '60', 'Default' => 'Open,Customer-Reply,Answered,In Progress',
                'Description'  => 'Comma-separated. The reconciler only scans mapped tickets in these statuses.',
            ],
            'whmcs_status_on_gd_public' => [
                'FriendlyName' => 'Status after GoodDay !public reply',
                'Type'         => 'text', 'Size' => '24', 'Default' => 'Answered',
            ],

            /* ---- GoodDay official API ---- */
            'gd_api_token' => [
                'FriendlyName' => 'GoodDay API Token (secret)',
                'Type'         => 'password', 'Size' => '48',
                'Description'  => 'Official v2 token (header gd-api-token). Stored encrypted.',
            ],
            'gd_api_base' => [
                'FriendlyName' => 'GoodDay API Base',
                'Type'         => 'text', 'Size' => '40', 'Default' => 'https://api.goodday.work/2.0',
            ],
            'gd_from_user_id' => [
                'FriendlyName' => 'GoodDay From/Bot User ID',
                'Type'         => 'text', 'Size' => '16', 'Default' => 'BtPcCg',
            ],
            'gd_to_user_id' => [
                'FriendlyName' => 'GoodDay To User ID (optional)',
                'Type'         => 'text', 'Size' => '16', 'Default' => '',
                'Description'  => 'Leave empty to omit toUserId on task creation.',
            ],
            'gd_project_id' => [
                'FriendlyName' => 'Default Project ID',
                'Type'         => 'text', 'Size' => '16', 'Default' => '2wCY2n',
            ],
            'gd_project_by_department' => [
                'FriendlyName' => 'Project by Department (JSON)',
                'Type'         => 'textarea', 'Rows' => '3', 'Cols' => '70',
                'Default'      => '{"technical department":"2wCY2n","sales department":"ZO4zru","accounting department":"1itzIk","pharmacyone":"gKzBz7"}',
                'Description'  => 'Lowercase department name → GoodDay project id. Falls back to Default Project ID.',
            ],
            'gd_task_type_id' => [
                'FriendlyName' => 'Task Type ID',
                'Type'         => 'text', 'Size' => '16', 'Default' => 'hB9A7F',
            ],

            /* ---- Custom fields ---- */
            'gd_cf_ticket'  => ['FriendlyName' => 'CF: Ticket number', 'Type' => 'text', 'Size' => '12', 'Default' => 'uojOkJ'],
            'gd_cf_created' => ['FriendlyName' => 'CF: Created date',   'Type' => 'text', 'Size' => '12', 'Default' => 'SQiqbD'],
            'gd_cf_subject' => ['FriendlyName' => 'CF: Subject',        'Type' => 'text', 'Size' => '12', 'Default' => 'ZvSBHp'],
            'gd_cf_dept'    => ['FriendlyName' => 'CF: Department',     'Type' => 'text', 'Size' => '12', 'Default' => 'mkKgtU'],
            'gd_cf_name'    => ['FriendlyName' => 'CF: Requestor name', 'Type' => 'text', 'Size' => '12', 'Default' => 'VpSUEj'],
            'gd_cf_email'   => ['FriendlyName' => 'CF: Requestor email','Type' => 'text', 'Size' => '12', 'Default' => 'TCJX4l'],
            'gd_cf_phone'   => ['FriendlyName' => 'CF: Requestor phone (optional)', 'Type' => 'text', 'Size' => '12', 'Default' => 'E4fpXg'],
            'gd_cf_status'  => ['FriendlyName' => 'CF: WHMCS status (optional)', 'Type' => 'text', 'Size' => '12', 'Default' => '',
                'Description' => 'Empty = WHMCS-status custom-field sync disabled.'],
            'gd_phone_prefix' => ['FriendlyName' => 'Phone Country Prefix', 'Type' => 'text', 'Size' => '8', 'Default' => '+357'],

            /* ---- GoodDay → WHMCS ---- */
            'gd_public_prefix' => ['FriendlyName' => 'Public Prefix', 'Type' => 'text', 'Size' => '12', 'Default' => '!public',
                'Description' => 'Only GoodDay messages starting with this become WHMCS replies.'],
            'gd_public_empty_body' => ['FriendlyName' => 'Public Empty-Body Placeholder', 'Type' => 'text', 'Size' => '30', 'Default' => ''],
            'gd_edit_note_prefix'  => ['FriendlyName' => 'WHMCS Edit Marker', 'Type' => 'text', 'Size' => '30', 'Default' => '[Edited in WHMCS]'],
            'gd_to_whmcs_status_map' => [
                'FriendlyName' => 'GoodDay→WHMCS Status Map (JSON)',
                'Type'         => 'textarea', 'Rows' => '2', 'Cols' => '70', 'Default' => '{}',
                'Description'  => 'Lowercase GoodDay status name → WHMCS status. Empty = disabled.',
            ],
            'gd_status_aliases' => [
                'FriendlyName' => 'Status Aliases (JSON, bidirectional)',
                'Type'         => 'textarea', 'Rows' => '3', 'Cols' => '70',
                'Default'      => '{"Offer Approved":"Offer Accepted","Resolve Issue":"Resolved","Answered":"Active","Customer-Reply":"Pending Client Feedback"}',
                'Description'  => 'WHMCS status title → GoodDay status name, for statuses whose names differ. Used in BOTH directions. Statuses with the same name in both systems sync automatically (no alias needed).',
            ],

            /* ---- GoodDay web API (fragile, off by default) ---- */
            'gd_web_enabled' => [
                'FriendlyName' => 'Enable Web API (edit/delete/attachments)',
                'Type'         => 'dropdown', 'Options' => 'off,on', 'Default' => 'off',
                'Description'  => 'Fragile scraped-JWT API. Prefer official API. Enable only if edits/deletes/attachments are needed.',
            ],
            'gd_company_id'  => ['FriendlyName' => 'Web: Company ID', 'Type' => 'text', 'Size' => '16', 'Default' => 'sNs5TG'],
            'gd_web_origin'  => ['FriendlyName' => 'Web: Origin', 'Type' => 'text', 'Size' => '32', 'Default' => 'https://www.goodday.work'],
            'gd_login_email' => ['FriendlyName' => 'Web: Login Email', 'Type' => 'text', 'Size' => '32', 'Default' => 'pdelis@cloudon.gr'],
            'gd_login_password' => ['FriendlyName' => 'Web: Login Password (secret)', 'Type' => 'password', 'Size' => '24',
                'Description' => '⚠ Prefer a long-lived Access Token below instead of a password.'],
            'gd_access_token' => ['FriendlyName' => 'Web: Access Token (JWT, secret)', 'Type' => 'password', 'Size' => '48'],
            'gd_tz_offset'   => ['FriendlyName' => 'Web: TZ Offset (minutes)', 'Type' => 'text', 'Size' => '6', 'Default' => '0'],

            /* ---- Direction flags ---- */
            'sync_create_task'              => ['FriendlyName' => 'Sync: create task', 'Type' => 'yesno', 'Default' => 'on'],
            'sync_replies'                  => ['FriendlyName' => 'Sync: replies → GoodDay', 'Type' => 'yesno', 'Default' => 'on'],
            'sync_edits_whmcs_to_goodday'   => ['FriendlyName' => 'Sync: WHMCS edit → GoodDay', 'Type' => 'yesno', 'Default' => 'on'],
            'sync_deletes_whmcs_to_goodday' => ['FriendlyName' => 'Sync: WHMCS reply delete → GoodDay', 'Type' => 'yesno', 'Default' => 'on'],
            'sync_status_to_goodday'        => ['FriendlyName' => 'Sync: status → GoodDay', 'Type' => 'yesno', 'Default' => 'on'],
            'sync_goodday_to_whmcs'         => ['FriendlyName' => 'Sync: GoodDay !public → WHMCS', 'Type' => 'yesno', 'Default' => 'on'],
            'sync_edits_goodday_to_whmcs'   => ['FriendlyName' => 'Sync: GoodDay edit → WHMCS', 'Type' => 'yesno', 'Default' => 'on'],
            'sync_deletes_goodday_to_whmcs' => ['FriendlyName' => 'Sync: GoodDay delete → WHMCS reply', 'Type' => 'yesno', 'Default' => 'on'],
            'gd_soft_delete_empty_heuristic'=> ['FriendlyName' => 'Treat empty GoodDay message as deleted', 'Type' => 'yesno', 'Default' => 'on'],
        ],
    ];
}

function gooddaysync_activate()
{
    try {
        Db::install();
        return ['status' => 'success', 'description' => 'GoodDay Sync tables created. Keep DRY_RUN=on, configure the API token, run Test Connection, then import state.json. Go live only after the old Python container is stopped.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Could not create tables: ' . $e->getMessage()];
    }
}

function gooddaysync_deactivate()
{
    // Keep all mapping data (never auto-drop — protects the mappings).
    return ['status' => 'success', 'description' => 'Deactivated. Hooks/cron inactive. Mapping tables retained.'];
}

/**
 * Admin dashboard.
 */
function gooddaysync_output($vars)
{
    $settings   = gooddaysync_settings();
    $modulelink = $vars['modulelink'];
    $dry        = SyncState::flag($settings, 'dry_run', true);

    $notice = '';

    // ---- actions ----
    $action = $_REQUEST['gdaction'] ?? '';
    if ($action === 'test') {
        $client = new GoodDayClient($settings, true);
        $res = $client->testConnection();
        $notice = '<div class="alert alert-' . ($res['ok'] ? 'success' : 'danger') . '">' . htmlspecialchars($res['message']) . '</div>';
    } elseif ($action === 'import') {
        $path = __DIR__ . '/state.json.import';
        if (is_readable($path)) {
            $json = json_decode(file_get_contents($path), true);
            if (is_array($json)) {
                $c = Db::importState($json);
                $notice = '<div class="alert alert-success">Imported from state.json.import — tickets: ' . $c['tickets'] . ', replies: ' . $c['replies'] . ', tombstones: ' . $c['tombstones'] . '. DB now holds ' . Db::countTickets() . ' mapped tickets.</div>';
            } else {
                $notice = '<div class="alert alert-danger">state.json.import is not valid JSON.</div>';
            }
        } else {
            $notice = '<div class="alert alert-warning">Place the file at <code>' . htmlspecialchars($path) . '</code> then click Import.</div>';
        }
    }

    // ---- stats ----
    // Count TICKETS, not individual sync operations: a ticket with 20 replies is
    // still one ticket. So the daily KPIs use COUNT(DISTINCT ticketid), not row counts.
    $syncEvents = "event NOT IN ('reconcile.run') AND event NOT LIKE 'dry.%' AND event NOT LIKE 'hook.%'";
    $todayOk    = (int) Capsule::table(Db::T_LOG)->whereRaw("DATE(ts)=CURDATE()")->where('level', 'info')->whereRaw($syncEvents)->whereNotNull('ticketid')->distinct()->count('ticketid');
    $todayErr   = (int) Capsule::table(Db::T_LOG)->whereRaw("DATE(ts)=CURDATE()")->whereIn('level', ['error', 'warn'])->whereNotNull('ticketid')->distinct()->count('ticketid');
    $lastRun    = Capsule::table(Db::T_LOG)->where('event', 'reconcile.run')->orderBy('id', 'desc')->value('ts');
    $totalTickets = Db::countTickets();

    // per-department breakdown of mapped tickets (+ active/closed split)
    $perDept = [];
    try {
        $rows = Capsule::table(Db::T_TICKETS . ' as g')
            ->leftJoin('tbltickets as t', 'g.ticketid', '=', 't.id')
            ->leftJoin('tblticketdepartments as d', 't.did', '=', 'd.id')
            ->selectRaw("COALESCE(d.name,'(unknown)') dept, COUNT(*) total, SUM(CASE WHEN t.status='Closed' THEN 1 ELSE 0 END) closed")
            ->groupBy('dept')->orderBy('total', 'desc')->get();
        $perDept = $rows;
    } catch (\Throwable $e) {
    }

    // last 7 days activity (ok vs err per day)
    $days = [];
    try {
        $rows = Capsule::table(Db::T_LOG)
            ->whereRaw("ts >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")
            ->selectRaw("DATE(ts) d,
                COUNT(DISTINCT CASE WHEN level='info' AND $syncEvents AND ticketid IS NOT NULL THEN ticketid END) ok,
                COUNT(DISTINCT CASE WHEN level IN ('error','warn') AND ticketid IS NOT NULL THEN ticketid END) err")
            ->groupBy('d')->orderBy('d', 'desc')->get();
        $days = $rows;
    } catch (\Throwable $e) {
    }

    $logs       = Db::recentLogs(50);
    $recentErr  = Db::recentLogs(8);
    $recentErr  = collect($recentErr)->filter(function ($l) { return in_array($l->level, ['error', 'warn'], true); })->take(6);

    ob_start();
    ?>
    <div style="margin-bottom:14px">
        <h2 style="margin:0 0 4px">GoodDay Sync
            <span class="label label-<?php echo $dry ? 'warning' : 'success'; ?>"><?php echo $dry ? 'DRY_RUN (no writes)' : 'LIVE'; ?></span>
        </h2>
        <p class="text-muted" style="margin:0">Event-driven WHMCS ↔ GoodDay · reconciler last ran: <strong><?php echo $lastRun ? htmlspecialchars($lastRun) : 'never'; ?></strong> · full-ticket-delete permanently disabled.</p>
    </div>
    <?php echo $notice; ?>

    <!-- ===== KPI row ===== -->
    <div class="row" style="margin-bottom:6px">
        <div class="col-sm-3"><div class="panel panel-default"><div class="panel-body text-center">
            <div style="font-size:30px;font-weight:700;color:#1f9d57"><?php echo $todayOk; ?></div>
            <div class="text-muted">Tickets OK σήμερα</div>
        </div></div></div>
        <div class="col-sm-3"><div class="panel panel-<?php echo $todayErr ? 'danger' : 'default'; ?>"><div class="panel-body text-center">
            <div style="font-size:30px;font-weight:700;color:<?php echo $todayErr ? '#c9453a' : '#999'; ?>"><?php echo $todayErr; ?></div>
            <div class="text-muted">Tickets με σφάλμα σήμερα</div>
        </div></div></div>
        <div class="col-sm-3"><div class="panel panel-default"><div class="panel-body text-center">
            <div style="font-size:30px;font-weight:700"><?php echo (int) $totalTickets; ?></div>
            <div class="text-muted">Συνδεδεμένα tickets</div>
        </div></div></div>
        <div class="col-sm-3"><div class="panel panel-default"><div class="panel-body text-center" style="padding:14px">
            <a class="btn btn-primary btn-sm btn-block" href="<?php echo $modulelink; ?>&gdaction=test">Test Connection</a>
            <a class="btn btn-default btn-sm btn-block" href="<?php echo $modulelink; ?>&gdaction=import" style="margin-top:6px">Import state.json</a>
        </div></div></div>
    </div>

    <div class="row">
        <!-- ===== Per department ===== -->
        <div class="col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Tickets ανά τμήμα</strong></div>
                <table class="table table-condensed" style="margin:0">
                    <thead><tr><th>Τμήμα</th><th class="text-right">Σύνολο</th><th class="text-right">Ανοιχτά</th><th class="text-right">Κλειστά</th></tr></thead>
                    <tbody>
                    <?php foreach ($perDept as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d->dept); ?></td>
                            <td class="text-right"><strong><?php echo (int) $d->total; ?></strong></td>
                            <td class="text-right"><?php echo (int) $d->total - (int) $d->closed; ?></td>
                            <td class="text-right text-muted"><?php echo (int) $d->closed; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($perDept) || count($perDept) === 0): ?><tr><td colspan="4" class="text-muted">No mapped tickets yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- ===== Last 7 days ===== -->
        <div class="col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Δραστηριότητα 7 ημερών</strong></div>
                <table class="table table-condensed" style="margin:0">
                    <thead><tr><th>Ημέρα</th><th class="text-right">Σωστά</th><th class="text-right">Σφάλματα</th></tr></thead>
                    <tbody>
                    <?php foreach ($days as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d->d); ?></td>
                            <td class="text-right" style="color:#1f9d57"><?php echo (int) $d->ok; ?></td>
                            <td class="text-right" style="color:<?php echo ((int)$d->err) ? '#c9453a' : '#999'; ?>"><?php echo (int) $d->err; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($days) || count($days) === 0): ?><tr><td colspan="3" class="text-muted">No activity yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($recentErr->count()): ?>
    <div class="panel panel-danger">
        <div class="panel-heading"><strong>⚠ Πρόσφατα σφάλματα</strong></div>
        <table class="table table-condensed" style="margin:0">
            <tbody>
            <?php foreach ($recentErr as $l): ?>
                <tr><td style="width:150px"><?php echo htmlspecialchars($l->ts); ?></td><td style="width:80px"><?php echo $l->ticketid ? '#' . (int) $l->ticketid : ''; ?></td><td><code><?php echo htmlspecialchars($l->event); ?></code></td><td><?php echo htmlspecialchars(mb_substr((string) $l->detail, 0, 140)); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Πρόσφατο sync log</strong></div>
        <table class="table table-condensed table-hover" style="margin:0">
            <thead><tr><th style="width:150px">Ώρα</th><th style="width:70px">Level</th><th style="width:70px">Ticket</th><th style="width:150px">Event</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?php echo htmlspecialchars($l->ts); ?></td>
                    <td><span class="label label-<?php echo $l->level === 'error' ? 'danger' : ($l->level === 'warn' ? 'warning' : ($l->level === 'dry' ? 'default' : 'success')); ?>"><?php echo htmlspecialchars($l->level); ?></span></td>
                    <td><?php echo $l->ticketid ? (int) $l->ticketid : ''; ?></td>
                    <td><code><?php echo htmlspecialchars($l->event); ?></code></td>
                    <td><?php echo htmlspecialchars(mb_substr((string) $l->detail, 0, 160)); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($logs->isEmpty()): ?><tr><td colspan="5" class="text-muted">No log entries yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    echo ob_get_clean();
}
