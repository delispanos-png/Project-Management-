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

    // Ensure the multi-project schema exists (safe/idempotent), then seed the
    // primary project from the legacy token on first run.
    Db::install();

    if (!$token && !Db::primaryProject()) {
        echo '<div class="alert alert-warning">Set your <strong>Hetzner API Token</strong> in this addon\'s settings (or add a Project), then reload.</div>';
        return;
    }

    $sync = new Sync($vars);

    // ---- POST handlers ----
    $flash = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $flash = hetznercloud_handlePost($sync, $_POST);
    }

    // ---- Tab nav ----
    $tabs = ['availability' => 'Availability', 'pricing' => 'Pricing & Mapping', 'import' => 'Import / Adopt', 'projects' => 'Projects', 'fleet' => 'Fleet', 'suspension' => '💤 Αναστολές', 'logs' => 'Logs'];
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
            case 'projects':
                hetznercloud_tabProjects($sync, $modulelink);
                break;
            case 'fleet':
                hetznercloud_tabFleet($sync, $modulelink);
                break;
            case 'suspension':
                hetznercloud_tabSuspension($modulelink);
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
    $adminId = (int) ($_SESSION['adminid'] ?? 0);
    if (($post['hzaction'] ?? '') === 'suspsave') {
        $en = !empty($post['susp_enabled']) ? 'on' : '';
        Capsule::table('tblconfiguration')->where('setting', 'AutoSuspension')->update(['value' => $en]);
        $days = (int) ($post['susp_days'] ?? 0);
        if ($days >= 1 && $days <= 60) {
            Capsule::table('tblconfiguration')->where('setting', 'AutoSuspensionDays')->update(['value' => (string) $days]);
        }
        logActivity('HetznerCloud: AutoSuspension → ' . ($en ?: 'off') . " (admin #$adminId)");
        return '<div class="alert alert-success">Αποθηκεύτηκε — Auto-Suspension: <b>' . ($en ? 'ΕΝΕΡΓΟ' : 'ανενεργό') . '</b></div>';
    }
    if (($post['hzaction'] ?? '') === 'suspclient') {
        $cid = (int) ($post['clientid'] ?? 0);
        $on = !empty($post['exclude']);
        if ($cid && Capsule::table('tblclients')->where('id', $cid)->exists()) {
            $n = Capsule::table('tblhosting')->where('userid', $cid)
                ->whereIn('domainstatus', ['Active', 'Suspended'])
                ->update(['overideautosuspend' => $on ? 1 : 0,
                    'overidesuspenduntil' => $on ? '2099-12-31' : '0000-00-00']);
            $ex = Capsule::table('tbladdonmodules')->where('module', 'hetznercloud')->where('setting', 'suspend_exclude_clients');
            $cur = array_filter(array_map('intval', explode(',', (string) ($ex->value('value') ?? ''))));
            $cur = $on ? array_unique(array_merge($cur, [$cid])) : array_values(array_diff($cur, [$cid]));
            if (Capsule::table('tbladdonmodules')->where('module', 'hetznercloud')->where('setting', 'suspend_exclude_clients')->exists()) {
                Capsule::table('tbladdonmodules')->where('module', 'hetznercloud')
                    ->where('setting', 'suspend_exclude_clients')->update(['value' => implode(',', $cur)]);
            } else {
                Capsule::table('tbladdonmodules')->insert(['module' => 'hetznercloud',
                    'setting' => 'suspend_exclude_clients', 'value' => implode(',', $cur)]);
            }
            logActivity('HetznerCloud: εξαίρεση αναστολής πελάτη #' . $cid . ' → ' . ($on ? 'ΝΑΙ' : 'όχι') . " ($n υπηρεσίες, admin #$adminId)");
            return '<div class="alert alert-success">' . ($on ? 'Εξαιρέθηκαν' : 'Επανήλθαν') . ' <b>' . $n . '</b> υπηρεσίες του πελάτη #' . $cid . '</div>';
        }
        return '<div class="alert alert-danger">Άκυρος πελάτης.</div>';
    }
    $action = $post['do'] ?? '';
    try {
        if ($action === 'vmaction') {
            $pid = (int) ($post['pid'] ?? 0);
            $sid = (int) ($post['sid'] ?? 0);
            $act = preg_replace('/[^a-z]/', '', strtolower($post['act'] ?? ''));
            $allowed = ['poweron' => 'powerOn', 'poweroff' => 'powerOff', 'reboot' => 'reboot', 'shutdown' => 'shutdown'];
            if (!$sid || !isset($allowed[$act])) {
                return '<div class="alert alert-danger">Μη έγκυρη ενέργεια.</div>';
            }
            $proj = $pid ? Db::project($pid) : Db::primaryProject();
            if (!$proj || empty($proj->api_token)) {
                return '<div class="alert alert-danger">Λείπει το token του project.</div>';
            }
            $api = new \WHMCS\Module\Server\HetznerCloud\Api(
                \WHMCS\Module\Server\HetznerCloud\Api::normalizeToken($proj->api_token)
            );
            $method = $allowed[$act];
            $api->$method($sid);
            Db::log("fleet: $act on server #$sid (project #$pid)", 'info');
            return '<div class="alert alert-success">Η εντολή «' . htmlspecialchars($act)
                . '» στάλθηκε στο server #' . $sid . '.</div>';
        }
        if ($action === 'vmlink') {
            $sid = (int) ($post['sid'] ?? 0);
            $pid = (int) ($post['pid'] ?? 0);
            $svc = (int) ($post['svc'] ?? 0);
            $unlink = !empty($post['unlink']);
            if (!$sid) {
                return '<div class="alert alert-danger">Μη έγκυρο αίτημα.</div>';
            }
            if ($unlink) {
                Capsule::table('mod_hetzner_instances')->where('server_id', $sid)->delete();
                // καθάρισε και το custom field όποιου service έδειχνε εδώ
                $fids = Capsule::table('tblcustomfields')
                    ->whereIn('fieldname', ['vpsid', 'hetzner_server_id'])->pluck('id')->all();
                if ($fids) {
                    Capsule::table('tblcustomfieldsvalues')->whereIn('fieldid', $fids)
                        ->where('value', (string) $sid)->update(['value' => '']);
                }
                logActivity("HetznerCloud fleet: unlink server #$sid (admin #" . (int) ($_SESSION['adminid'] ?? 0) . ')');
                return '<div class="alert alert-success">Η ταύτιση του server #' . $sid . ' αφαιρέθηκε.</div>';
            }
            $h = Capsule::table('tblhosting as h')
                ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
                ->where('h.id', $svc)->where('p.servertype', 'hetznercloud')
                ->first(['h.id', 'h.domain', 'h.packageid']);
            if (!$h) {
                return '<div class="alert alert-danger">Το service #' . $svc . ' δεν βρέθηκε ή δεν είναι Hetzner Cloud προϊόν.</div>';
            }
            // ένα service ↔ ένας server: καθάρισε παλιές εγγραφές και των δύο πλευρών
            Capsule::table('mod_hetzner_instances')->where('server_id', $sid)->orWhere('service_id', $svc)->delete();
            Capsule::table('mod_hetzner_instances')->insert(['service_id' => $svc,
                'project_id' => $pid ?: (int) (Db::primaryProject()->id ?? 0),
                'server_id' => $sid, 'created_at' => date('Y-m-d H:i:s')]);
            // γράψε και το custom field του service (vpsid ή hetzner_server_id)
            $fid = Capsule::table('tblcustomfields')->where('relid', $h->packageid)
                ->whereIn('fieldname', ['hetzner_server_id', 'vpsid'])->value('id');
            if ($fid) {
                $exists = Capsule::table('tblcustomfieldsvalues')->where('fieldid', $fid)->where('relid', $svc);
                if ($exists->exists()) {
                    $exists->update(['value' => (string) $sid]);
                } else {
                    Capsule::table('tblcustomfieldsvalues')->insert(['fieldid' => $fid, 'relid' => $svc, 'value' => (string) $sid]);
                }
            }
            logActivity("HetznerCloud fleet: match server #$sid ↔ svc#$svc ({$h->domain}) (admin #" . (int) ($_SESSION['adminid'] ?? 0) . ')');
            return '<div class="alert alert-success">Ταυτίστηκε: server #' . $sid . ' ↔ <b>svc#' . $svc . '</b> (' . htmlspecialchars($h->domain ?: '—') . ')</div>';
        }
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
            if (array_key_exists('project_id', $post)) {
                $projId = (int) $post['project_id'];
                Capsule::table(Db::T_MAP)->where('whmcs_pid', $pid)
                    ->update(['project_id' => $projId ?: null]);
            }
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
            // serverid arrives as "projectId:serverId" from the grouped select.
            $combo = (string) ($post['serverid'] ?? '');
            $pid = 0;
            $sid = 0;
            if (strpos($combo, ':') !== false) {
                $parts = explode(':', $combo, 2);
                $pid = (int) $parts[0];
                $sid = (int) $parts[1];
            } else {
                $sid = (int) $combo;
                $pid = (int) ($post['projectid'] ?? 0);
            }
            if ($sid <= 0) {
                return '<div class="alert alert-warning">Choose a server to link.</div>';
            }
            $res = $sync->linkService((int) $post['serviceid'], $sid, $pid);
            if ($res === true) {
                return '<div class="alert alert-success">Service #' . (int) $post['serviceid'] . ' linked.</div>';
            }
            return '<div class="alert alert-danger">Link failed: ' . htmlspecialchars($res) . '</div>';
        }
        if ($action === 'addproject') {
            $name = trim((string) ($post['proj_name'] ?? ''));
            $raw  = trim((string) ($post['proj_token'] ?? ''));
            if ($name === '' || $raw === '') {
                return '<div class="alert alert-warning">Name and API token are both required.</div>';
            }
            // Live-test the token before storing so we never save a dud.
            try {
                $api = new \WHMCS\Module\Server\HetznerCloud\Api($raw);
                $r = $api->request('GET', '/servers', ['per_page' => 1]);
                $count = (int) ($r['meta']['pagination']['total_entries'] ?? 0);
            } catch (\Throwable $e) {
                return '<div class="alert alert-danger">Token test failed — project <strong>not</strong> added: '
                    . htmlspecialchars($e->getMessage()) . '</div>';
            }
            $enc = function_exists('encrypt') ? encrypt($raw) : $raw;
            $id = Db::addProject($name, $enc);
            Db::log("Project '$name' added (#$id), $count VMs reachable", 'info');
            return '<div class="alert alert-success">Project <strong>' . htmlspecialchars($name)
                . '</strong> added — <strong>' . $count . '</strong> VM(s) reachable.'
                . ($count === 0 ? ' (token OK; project currently empty)' : '') . '</div>';
        }
        if ($action === 'setprimary') {
            Db::setPrimary((int) $post['projectid']);
            return '<div class="alert alert-success">Primary project updated. New orders (without a per-product override) go here.</div>';
        }
        if ($action === 'toggleproject') {
            $p = Db::project((int) $post['projectid']);
            if ($p) {
                if ($p->is_primary && $p->enabled) {
                    return '<div class="alert alert-warning">Can\'t disable the primary project. Set another project as primary first.</div>';
                }
                Db::updateProject($p->id, ['enabled' => $p->enabled ? 0 : 1]);
                return '<div class="alert alert-success">Project ' . ($p->enabled ? 'disabled' : 'enabled') . '.</div>';
            }
        }
        if ($action === 'delproject') {
            $p = Db::project((int) $post['projectid']);
            if (!$p) {
                return '<div class="alert alert-warning">Project not found.</div>';
            }
            if ($p->is_primary) {
                return '<div class="alert alert-warning">Can\'t delete the primary project. Set another as primary first.</div>';
            }
            $linked = Capsule::table(Db::T_INSTANCES)->where('project_id', $p->id)->count();
            if ($linked > 0) {
                return '<div class="alert alert-warning">Project has <strong>' . $linked
                    . '</strong> linked VM(s). Unlink/move them first to avoid orphaning.</div>';
            }
            Db::deleteProject($p->id);
            return '<div class="alert alert-success">Project <strong>' . htmlspecialchars($p->name) . '</strong> deleted.</div>';
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
 * Projects tab — manage the Hetzner projects (one API token each). Unlimited.
 * New orders provision into the primary project; imports keep each VM on its
 * own project's token via mod_hetzner_instances.
 */
function hetznercloud_tabProjects(Sync $sync, $modulelink)
{
    $projects   = Db::projects();
    $instCounts = Db::instanceCountByProject();
    $action     = $modulelink . '&tab=projects';

    echo '<div class="alert alert-info" style="margin-bottom:15px">'
        . 'Κάθε Hetzner <strong>project</strong> έχει δικό του API token. Οι <strong>νέες παραγγελίες</strong> '
        . 'δημιουργούνται στο <strong>primary</strong> project (εκτός αν κάποιο product το παρακάμπτει στο Pricing &amp; Mapping). '
        . 'Τα εισαγόμενα VMs χρησιμοποιούν αυτόματα το token του δικού τους project. Χωρίς όριο projects.</div>';

    echo '<table class="table table-bordered table-condensed">';
    echo '<thead><tr><th>Project</th><th>API Token</th><th class="text-center">Primary</th>'
        . '<th class="text-center">Enabled</th><th class="text-center">Live VMs</th>'
        . '<th class="text-center">Linked</th><th>Actions</th></tr></thead><tbody>';

    foreach ($projects as $p) {
        $tok  = '';
        $live = '<span class="text-muted">—</span>';
        try {
            $tok = \WHMCS\Module\Server\HetznerCloud\Api::normalizeToken($p->api_token);
            $api = new \WHMCS\Module\Server\HetznerCloud\Api($tok);
            $r   = $api->request('GET', '/servers', ['per_page' => 1]);
            $live = '<span class="text-success"><strong>' . (int) ($r['meta']['pagination']['total_entries'] ?? 0) . '</strong></span>';
        } catch (\Throwable $e) {
            $live = '<span class="text-danger" title="' . htmlspecialchars($e->getMessage()) . '">error</span>';
        }
        $mask   = '••••' . htmlspecialchars(substr($tok, -4));
        $linked = (int) ($instCounts[(int) $p->id] ?? 0);

        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($p->name) . '</strong> <small class="text-muted">#' . (int) $p->id . '</small></td>';
        echo '<td><code>' . $mask . '</code></td>';
        echo '<td class="text-center">';
        if ($p->is_primary) {
            echo '<span class="label label-primary">PRIMARY</span>';
        } else {
            echo '<form method="post" action="' . $action . '" style="display:inline">'
                . '<input type="hidden" name="do" value="setprimary"><input type="hidden" name="projectid" value="' . (int) $p->id . '">'
                . '<button class="btn btn-xs btn-default">Set primary</button></form>';
        }
        echo '</td>';
        echo '<td class="text-center">' . ($p->enabled
            ? '<span class="label label-success">on</span>'
            : '<span class="label label-default">off</span>') . '</td>';
        echo '<td class="text-center">' . $live . '</td>';
        echo '<td class="text-center">' . $linked . '</td>';
        echo '<td>';
        if (!$p->is_primary) {
            echo '<form method="post" action="' . $action . '" style="display:inline;margin-right:4px">'
                . '<input type="hidden" name="do" value="toggleproject"><input type="hidden" name="projectid" value="' . (int) $p->id . '">'
                . '<button class="btn btn-xs btn-default">' . ($p->enabled ? 'Disable' : 'Enable') . '</button></form>';
            echo '<form method="post" action="' . $action . '" style="display:inline">'
                . '<input type="hidden" name="do" value="delproject"><input type="hidden" name="projectid" value="' . (int) $p->id . '">'
                . '<button class="btn btn-xs btn-danger" onclick="return confirm(\'Delete project ' . htmlspecialchars($p->name, ENT_QUOTES) . '?\')">Delete</button></form>';
        } else {
            echo '<span class="text-muted">—</span>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<div class="panel panel-default"><div class="panel-heading"><strong>Add Project</strong></div><div class="panel-body">';
    echo '<form method="post" action="' . $action . '" class="form-inline">';
    echo '<input type="hidden" name="do" value="addproject">';
    echo '<div class="form-group" style="margin-right:8px"><input type="text" name="proj_name" class="form-control" placeholder="Project name (label)" required></div>';
    echo '<div class="form-group" style="margin-right:8px"><input type="text" name="proj_token" class="form-control" size="46" placeholder="Read&amp;Write API token" required autocomplete="off"></div>';
    echo '<button class="btn btn-primary">Add &amp; Test</button>';
    echo '</form>';
    echo '<p class="text-muted" style="margin-top:8px">Hetzner Console → επίλεξε το project → Security → API Tokens → Generate (Read &amp; Write). '
        . 'Το token ελέγχεται live και αποθηκεύεται κρυπτογραφημένο.</p>';
    echo '</div></div>';
}

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

    // Per-product project override is only meaningful with more than one project.
    $projects = Db::projects();
    $multiProject = count($projects) > 1;
    $primaryProjId = 0;
    foreach ($projects as $pp) {
        if ($pp->is_primary) { $primaryProjId = (int) $pp->id; }
    }

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
                : (!$known ? ' <span class="label label-danger">άγνωστος</span>' : ''));
        if ($multiProject) {
            $sel = (int) ($m->project_id ?? 0);
            echo '<br><small class="text-muted">Project:</small> '
                . '<select name="project_id" class="form-control input-sm" style="width:150px;display:inline-block">'
                . '<option value="0"' . ($sel === 0 ? ' selected' : '') . '>Primary (default)</option>';
            foreach ($projects as $pp) {
                echo '<option value="' . (int) $pp->id . '"' . ($sel === (int) $pp->id ? ' selected' : '') . '>'
                    . htmlspecialchars($pp->name) . ($pp->is_primary ? ' ★' : '') . '</option>';
            }
            echo '</select>';
        }
        echo '</td>';

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
    echo '<p>Match already-sold WHMCS services to their existing Hetzner servers <strong>across all projects</strong>. '
        . 'Linked services immediately gain full control-panel management — nothing is recreated. '
        . 'The chosen server\'s project is recorded so lifecycle actions always use the right token.</p>';

    // Build a select of all live servers, grouped by project. Value = "pid:sid".
    $optsByProject = [];
    foreach ($data['servers'] as $s) {
        $ip  = $s['public_net']['ipv4']['ip'] ?? '';
        $pid = (int) ($s['_project_id'] ?? 0);
        $pname = $s['_project_name'] ?? '(default)';
        $optsByProject[$pname][] = '<option value="' . $pid . ':' . (int) $s['id'] . '">#' . (int) $s['id']
            . ' ' . htmlspecialchars($s['name']) . ($ip ? ' (' . htmlspecialchars($ip) . ')' : '') . '</option>';
    }
    $buildSelect = function ($guessPid, $guessSid) use ($optsByProject) {
        $guessVal = ($guessSid ? ($guessPid . ':' . $guessSid) : '');
        $html = '<select name="serverid" class="form-control input-sm"><option value="">— choose —</option>';
        foreach ($optsByProject as $pname => $opts) {
            $html .= '<optgroup label="' . htmlspecialchars($pname) . '">';
            foreach ($opts as $o) {
                if ($guessVal !== '' && strpos($o, 'value="' . $guessVal . '"') !== false) {
                    $o = str_replace('value="' . $guessVal . '"', 'value="' . $guessVal . '" selected', $o);
                }
                $html .= $o;
            }
            $html .= '</optgroup>';
        }
        return $html . '</select>';
    };

    echo '<div class="table-responsive"><table class="table table-condensed table-bordered">';
    echo '<thead><tr><th>Service</th><th>Domain</th><th>IP</th><th>Project</th><th>Status</th><th>Suggested match</th><th>Link to server</th></tr></thead><tbody>';
    foreach ($data['services'] as $row) {
        echo '<tr>';
        echo '<td>#' . (int) $row['serviceid'] . '</td>';
        echo '<td>' . htmlspecialchars($row['domain']) . '</td>';
        echo '<td>' . htmlspecialchars($row['ip']) . '</td>';
        echo '<td>' . (!empty($row['project']) ? '<span class="label label-info">' . htmlspecialchars($row['project']) . '</span>' : '<span class="text-muted">—</span>') . '</td>';
        echo '<td>' . ($row['linked'] ? '<span class="label label-success">linked</span>' : '<span class="label label-warning">unlinked</span>') . '</td>';
        echo '<td>' . ($row['guess_id'] ? ('#' . (int) $row['guess_id'] . ' <small>by ' . htmlspecialchars($row['guess_by']) . '</small>') : '—') . '</td>';
        echo '<td>';
        if (!$row['linked']) {
            echo '<form method="post" class="form-inline" style="margin:0">';
            echo '<input type="hidden" name="do" value="link"><input type="hidden" name="serviceid" value="' . (int) $row['serviceid'] . '">';
            echo $buildSelect((int) $row['guess_pid'], (int) $row['guess_id']) . ' ';
            echo '<button class="btn btn-xs btn-primary">Link</button>';
            echo '</form>';
        } else {
            echo '<em>done</em>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function hetznercloud_tabFleet(Sync $sync, $modulelink)
{
    echo '<p>Όλα τα live VMs <strong>όλων των projects</strong> — άμεση διαχείριση (power on/off, reboot) '
        . '<strong>χωρίς</strong> να απαιτείται WHMCS service. Ιδανικό για δικά σας/infra servers.</p>';

    // server_id → linked WHMCS service (για ένδειξη)
    $linkedMap = [];
    foreach (Capsule::table('mod_hetzner_instances')->get(['server_id', 'service_id']) as $i) {
        $linkedMap[(int) $i->server_id] = (int) $i->service_id;
    }
    // Και ταυτίσεις μέσω custom field (vpsid / hetzner_server_id) ή username hz-N,
    // ώστε χειροκίνητες ταυτίσεις από την καρτέλα υπηρεσίας να φαίνονται κι αυτές.
    foreach (Capsule::table('tblcustomfieldsvalues as v')
        ->join('tblcustomfields as f', 'f.id', '=', 'v.fieldid')
        ->join('tblhosting as h', 'h.id', '=', 'v.relid')
        ->whereIn('f.fieldname', ['vpsid', 'hetzner_server_id'])
        ->whereIn('h.domainstatus', ['Active', 'Suspended'])
        ->where('v.value', 'REGEXP', '^[0-9]+$')
        ->get(['v.value', 'v.relid']) as $cf) {
        $linkedMap[(int) $cf->value] = $linkedMap[(int) $cf->value] ?? (int) $cf->relid;
    }
    foreach (Capsule::table('tblhosting')->where('username', 'like', 'hz-%')
        ->whereIn('domainstatus', ['Active', 'Suspended'])->get(['id', 'username']) as $hu) {
        $sidU = (int) substr($hu->username, 3);
        if ($sidU) {
            $linkedMap[$sidU] = $linkedMap[$sidU] ?? (int) $hu->id;
        }
    }
    // Πληροφορίες υπηρεσιών (domain + πελάτης) για ανθρώπινη εμφάνιση
    $svcMeta = [];
    foreach (Capsule::table('tblhosting as h')
        ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
        ->join('tblclients as c', 'c.id', '=', 'h.userid')
        ->where('p.servertype', 'hetznercloud')
        ->whereIn('h.domainstatus', ['Active', 'Suspended'])
        ->orderBy('c.companyname')->orderBy('c.lastname')
        ->get(['h.id', 'h.domain', 'h.domainstatus', 'c.firstname', 'c.lastname', 'c.companyname']) as $sm) {
        $svcMeta[(int) $sm->id] = [
            'domain' => $sm->domain,
            'client' => $sm->companyname ?: trim($sm->firstname . ' ' . $sm->lastname),
            'status' => $sm->domainstatus,
        ];
    }
    $linkedSvcIds = array_values($linkedMap);
    $svcOptions = '';
    foreach ($svcMeta as $sid2 => $m2) {
        if (in_array($sid2, $linkedSvcIds, true)) {
            continue;   // ήδη δεμένη με άλλον server
        }
        $lbl = $m2['client'] . ($m2['domain'] ? ' — ' . $m2['domain'] : '') . ' (#' . $sid2 . ')';
        $svcOptions .= '<option value="' . $sid2 . '">' . htmlspecialchars($lbl) . '</option>';
    }

    $projects = Db::enabledProjects()->all();
    echo '<div class="table-responsive"><table class="table table-condensed table-bordered">';
    echo '<thead><tr><th>VM</th><th>IP</th><th>Type</th><th>Location</th><th>Project</th>'
        . '<th>Status</th><th>WHMCS</th><th style="width:210px">Ενέργειες</th></tr></thead><tbody>';

    $total = 0;
    foreach ($projects as $proj) {
        try {
            $tok = \WHMCS\Module\Server\HetznerCloud\Api::normalizeToken($proj->api_token);
            $api = ($proj->id && $tok !== '') ? new \WHMCS\Module\Server\HetznerCloud\Api($tok) : $sync->api();
            $page = 1;
            do {
                $res = $api->request('GET', '/servers', ['per_page' => 50, 'page' => $page]);
                foreach (($res['servers'] ?? []) as $s) {
                    $total++;
                    $sid    = (int) $s['id'];
                    $ip     = $s['public_net']['ipv4']['ip'] ?? '';
                    $status = $s['status'] ?? '?';
                    $sCls   = $status === 'running' ? 'success' : ($status === 'off' ? 'default' : 'warning');
                    $linked = $linkedMap[$sid] ?? 0;
                    $btn = function ($act, $label, $cls) use ($proj, $sid) {
                        return '<form method="post" style="display:inline;margin:0" onsubmit="return confirm(\'' . $label . ' #' . $sid . ';\')">'
                            . '<input type="hidden" name="do" value="vmaction">'
                            . '<input type="hidden" name="pid" value="' . (int) $proj->id . '">'
                            . '<input type="hidden" name="sid" value="' . $sid . '">'
                            . '<input type="hidden" name="act" value="' . $act . '">'
                            . '<button class="btn btn-xs btn-' . $cls . '">' . $label . '</button></form> ';
                    };
                    echo '<tr>'
                        . '<td><strong>' . htmlspecialchars($s['name']) . '</strong></td>'
                        . '<td>' . htmlspecialchars($ip) . '</td>'
                        . '<td>' . htmlspecialchars($s['server_type']['name'] ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($s['datacenter']['location']['city'] ?? '') . '</td>'
                        . '<td><span class="label label-info">' . htmlspecialchars($proj->name) . '</span></td>'
                        . '<td><span class="label label-' . $sCls . '">' . htmlspecialchars($status) . '</span></td>'
                        . '<td style="white-space:nowrap">'
                            . ($linked
                                ? '<span class="label label-success">svc#' . $linked . '</span> '
                                    . '<b style="font-size:12px">' . htmlspecialchars($svcMeta[$linked]['client'] ?? '') . '</b>'
                                    . (!empty($svcMeta[$linked]['domain']) ? ' <span class="text-muted" style="font-size:11px">' . htmlspecialchars($svcMeta[$linked]['domain']) . '</span>' : '')
                                    . (($svcMeta[$linked]['status'] ?? '') === 'Suspended' ? ' <span class="label label-warning">Suspended</span>' : '')
                                    . ' <form method="post" style="display:inline;margin:0" onsubmit="return confirm(\'Αφαίρεση ταύτισης server #' . $sid . ';\')">'
                                    . '<input type="hidden" name="do" value="vmlink"><input type="hidden" name="sid" value="' . $sid . '">'
                                    . '<input type="hidden" name="unlink" value="1">'
                                    . '<button class="btn btn-xs btn-link" title="Αφαίρεση ταύτισης" style="padding:0 3px">✕</button></form>'
                                : '<form method="post" style="display:inline-flex;gap:4px;margin:0;vertical-align:middle">'
                                    . '<input type="hidden" name="do" value="vmlink">'
                                    . '<input type="hidden" name="pid" value="' . (int) $proj->id . '">'
                                    . '<input type="hidden" name="sid" value="' . $sid . '">'
                                    . '<select name="svc" style="font-size:11px;max-width:260px;padding:1px 3px">'
                                    . '<option value="">— ταύτιση με υπηρεσία —</option>' . $svcOptions . '</select>'
                                    . '<button class="btn btn-xs btn-primary" title="Ταύτιση">🔗</button></form>')
                        . '</td>'
                        . '<td>' . $btn('poweron', 'On', 'success') . $btn('poweroff', 'Off', 'danger') . $btn('reboot', 'Reboot', 'warning') . '</td>'
                        . '</tr>';
                }
                $page = $res['meta']['pagination']['next_page'] ?? null;
            } while ($page);
        } catch (\Throwable $e) {
            echo '<tr><td colspan="8" class="text-danger">Project «' . htmlspecialchars($proj->name) . '»: '
                . htmlspecialchars($e->getMessage()) . '</td></tr>';
        }
    }
    echo '</tbody></table></div>';
    echo '<p class="text-muted">Σύνολο: <strong>' . $total . '</strong> VMs σε ' . count($projects) . ' projects.</p>';
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


/* ═══════════ Tab: 💤 Αναστολές (auto-suspension control) ═══════════ */
function hetznercloud_tabSuspension($modulelink)
{
    $enabled = Capsule::table('tblconfiguration')->where('setting', 'AutoSuspension')->value('value') === 'on';
    $days = (int) Capsule::table('tblconfiguration')->where('setting', 'AutoSuspensionDays')->value('value');
    $term = Capsule::table('tblconfiguration')->where('setting', 'AutoTermination')->value('value') === 'on';

    echo '<form method="post" style="margin-bottom:20px"><input type="hidden" name="hzaction" value="suspsave">
    <div class="panel panel-default"><div class="panel-heading"><b>⏻ Αυτόματη αναστολή υπηρεσιών (καθολικό WHMCS)</b></div>
    <div class="panel-body">
      <label style="display:flex;gap:8px;align-items:center;font-size:14px">
        <input type="checkbox" name="susp_enabled" value="1"' . ($enabled ? ' checked' : '') . '>
        <b>Auto-Suspension</b> — power off σε VM & πάγωμα υπηρεσιών με απλήρωτα (τα δεδομένα ΔΕΝ χάνονται)</label>
      <div style="margin-top:10px">Ημέρες μετά τη λήξη: <input type="number" name="susp_days" min="1" max="60" value="' . $days . '" style="width:70px"> ·
        Auto-Termination: <span class="label label-' . ($term ? 'danger">⚠ ΕΝΕΡΓΟ' : 'success">κλειστό') . '</span></div>
      <button class="btn btn-primary" style="margin-top:12px">Αποθήκευση</button>
    </div></div></form>';

    // Εξαιρεμένοι πελάτες (τρέχουσα κατάσταση overrides)
    $exc = Capsule::table('tblhosting as h')
        ->join('tblclients as c', 'c.id', '=', 'h.userid')
        ->where('h.overideautosuspend', 1)
        ->whereIn('h.domainstatus', ['Active', 'Suspended'])
        ->groupBy('h.userid', 'c.firstname', 'c.lastname', 'c.companyname')
        ->get(['h.userid', 'c.firstname', 'c.lastname', 'c.companyname', Capsule::raw('COUNT(*) as cnt')]);
    echo '<div class="panel panel-default"><div class="panel-heading"><b>🛡️ Εξαιρέσεις ανά πελάτη</b>
      <span class="label label-default">' . count($exc) . '</span> — δεν αναστέλλονται ποτέ, ό,τι κι αν χρωστούν· καλύπτει αυτόματα και νέες υπηρεσίες τους (cron 15΄)</div>
    <div class="panel-body">
    <form method="post" class="form-inline" style="margin-bottom:12px">
      <input type="hidden" name="hzaction" value="suspclient"><input type="hidden" name="exclude" value="1">
      <select name="clientid" class="form-control" style="min-width:320px">
        <option value="">— διάλεξε πελάτη —</option>';
    foreach (Capsule::table('tblclients as c')
        ->join('tblhosting as h', 'h.userid', '=', 'c.id')
        ->whereIn('h.domainstatus', ['Active', 'Suspended'])
        ->groupBy('c.id', 'c.firstname', 'c.lastname', 'c.companyname')
        ->orderBy('c.companyname')->orderBy('c.lastname')
        ->get(['c.id', 'c.firstname', 'c.lastname', 'c.companyname']) as $cl) {
        $nm = $cl->companyname ?: trim($cl->firstname . ' ' . $cl->lastname);
        echo '<option value="' . (int) $cl->id . '">' . htmlspecialchars($nm) . ' (#' . (int) $cl->id . ')</option>';
    }
    echo '</select> <button class="btn btn-default">+ Εξαίρεση</button></form>';
    if (count($exc)) {
        echo '<table class="table table-condensed"><thead><tr><th>Πελάτης</th><th>Υπηρεσίες</th><th></th></tr></thead><tbody>';
        foreach ($exc as $x) {
            $nm = $x->companyname ?: trim($x->firstname . ' ' . $x->lastname);
            echo '<tr><td><b>' . htmlspecialchars($nm) . '</b> #' . (int) $x->userid . '</td><td>' . (int) $x->cnt . '</td>
              <td><form method="post" style="display:inline"><input type="hidden" name="hzaction" value="suspclient">
                <input type="hidden" name="clientid" value="' . (int) $x->userid . '">
                <button class="btn btn-xs btn-default" onclick="return confirm(\'Αφαίρεση εξαίρεσης;\')">✕ αφαίρεση</button></form></td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="text-muted">Καμία εξαίρεση.</p>';
    }
    echo '</div></div>';

    // Ποιοι θα ανασταλούν αν ενεργοποιηθεί τώρα
    $cut = date('Y-m-d', strtotime('-' . max(0, $days) . ' days'));
    $risk = [];
    foreach (Capsule::table('tblhosting as h')
        ->join('tblclients as c', 'c.id', '=', 'h.userid')
        ->where('h.domainstatus', 'Active')->where('h.overideautosuspend', 0)
        ->where('h.nextduedate', '>', '0000-00-00')->where('h.nextduedate', '<=', $cut)
        ->whereNotIn('h.billingcycle', ['One Time', 'Free Account'])
        ->orderBy('h.nextduedate')
        ->get(['h.id', 'h.userid', 'h.domain', 'h.nextduedate', 'c.firstname', 'c.lastname', 'c.companyname']) as $r) {
        $unp = (float) Capsule::table('tblinvoiceitems as ii')
            ->join('tblinvoices as i', 'i.id', '=', 'ii.invoiceid')
            ->where('ii.type', 'Hosting')->where('ii.relid', $r->id)
            ->where('i.status', 'Unpaid')->sum('i.total');
        if ($unp <= 0) {
            continue;
        }
        $risk[] = [$r, $unp];
        if (count($risk) >= 40) {
            break;
        }
    }
    echo '<div class="panel panel-' . (count($risk) ? 'warning' : 'default') . '">
      <div class="panel-heading"><b>⚠️ Θα ανασταλούν αν το ενεργοποιήσεις τώρα</b> <span class="label label-default">' . count($risk) . '</span></div>
      <div class="panel-body">';
    if ($risk) {
        echo '<table class="table table-condensed"><thead><tr><th>Πελάτης</th><th>Υπηρεσία</th><th>Λήξη</th><th>Οφειλή</th><th></th></tr></thead><tbody>';
        foreach ($risk as [$r, $unp]) {
            $nm = $r->companyname ?: trim($r->firstname . ' ' . $r->lastname);
            echo '<tr><td><b>' . htmlspecialchars($nm) . '</b></td><td>' . htmlspecialchars($r->domain ?: 'svc #' . $r->id) . '</td>
              <td>' . htmlspecialchars($r->nextduedate) . '</td><td><span class="label label-danger">' . number_format($unp, 2) . ' €</span></td>
              <td><form method="post" style="display:inline"><input type="hidden" name="hzaction" value="suspclient">
                <input type="hidden" name="clientid" value="' . (int) $r->userid . '"><input type="hidden" name="exclude" value="1">
                <button class="btn btn-xs btn-default" title="Εξαίρεση πελάτη">🛡️ εξαίρεση</button></form></td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="text-muted">Κανένα — όλα πληρωμένα ή εξαιρεμένα 🎉</p>';
    }
    echo '</div></div>';
}
