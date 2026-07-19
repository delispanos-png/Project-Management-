<?php
/**
 * Client-area control panel for the Hetzner Cloud module.
 *
 * Everything here is white-label: no "Hetzner" string ever reaches the client.
 * Power actions use WHMCS custom client buttons; richer actions (rebuild,
 * resize, rDNS, snapshot) and live data (status, metrics, console) are handled
 * inside hetznercloud_ClientArea().
 *
 * @package WHMCS\Module\Server\HetznerCloud
 */

use WHMCS\Module\Server\HetznerCloud\Api;
use WHMCS\Module\Server\HetznerCloud\ApiException;
use WHMCS\Module\Server\HetznerCloud\Helper;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Simple power buttons rendered by WHMCS above the module output.
 */
function hetznercloud_ClientAreaCustomButtonArray(array $params)
{
    $buttons = [
        'Εκκίνηση'              => 'clientBoot',
        'Τερματισμός (ήπιος)'   => 'clientShutdown',
        'Επανεκκίνηση'          => 'clientReboot',
        'Σκληρή επανεκκίνηση'   => 'clientReset',
    ];
    return $buttons;
}

function hetznercloud_clientBoot(array $params)     { return hetznercloud_simpleAction($params, 'powerOn'); }
function hetznercloud_clientShutdown(array $params) { return hetznercloud_simpleAction($params, 'shutdown'); }
function hetznercloud_clientReboot(array $params)   { return hetznercloud_simpleAction($params, 'reboot'); }
function hetznercloud_clientReset(array $params)    { return hetznercloud_simpleAction($params, 'reset'); }

/**
 * Main control-panel renderer + action/AJAX router.
 */
function hetznercloud_ClientArea(array $params)
{
    $id = Helper::serverId($params);
    $brand = Helper::brand($params);

    try {
        $api = Helper::api($params);
    } catch (\Throwable $e) {
        return hetznercloud_caError($brand, 'Η υπηρεσία δεν είναι προσωρινά διαθέσιμη.');
    }

    // ---- AJAX endpoints (JSON) ----------------------------------------
    if (isset($_GET['hzajax'])) {
        hetznercloud_caAjax($api, $id, $params);
        // hetznercloud_caAjax always exits.
    }

    // ---- POST actions --------------------------------------------------
    $notice = '';
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['hzaction'])) {
        list($notice, $error) = hetznercloud_caHandlePost($api, $id, $params);
    }

    // ---- Render --------------------------------------------------------
    if (!$id) {
        return hetznercloud_caError($brand, 'Ο server σου ετοιμάζεται. Δοκίμασε ξανά σε λίγο.');
    }

    try {
        $srv = $api->getServer($id);
    } catch (ApiException $e) {
        return hetznercloud_caError($brand, 'Δεν ήταν δυνατή η επικοινωνία με τον server αυτή τη στιγμή.');
    }
    if (!$srv) {
        return hetznercloud_caError($brand, 'Ο server δεν βρέθηκε. Επικοινώνησε με την υποστήριξη.');
    }

    // Build the list of types the client can resize to (same location, available).
    $resizeOptions = [];
    $allowResize = ($params['configoption8'] ?? '') === 'on';
    if ($allowResize) {
        $resizeOptions = hetznercloud_resizeTargets($api, $srv);
    }

    // OS images for rebuild.
    $images = [];
    $allowRebuild = ($params['configoption7'] ?? 'on') !== 'off';
    if ($allowRebuild) {
        try {
            $arch = $srv['server_type']['architecture'] ?? null;
            foreach ($api->images('system', $arch) as $img) {
                if (!empty($img['name'])) {
                    $images[$img['name']] = $img['description'] ?: $img['name'];
                }
            }
        } catch (ApiException $e) {
            // rebuild simply unavailable this load
        }
    }

    $ipv4 = $srv['public_net']['ipv4']['ip'] ?? '';
    $ipv6 = $srv['public_net']['ipv6']['ip'] ?? '';
    $ptr = '';
    if (!empty($srv['public_net']['ipv4']['dns_ptr'])) {
        $ptr = $srv['public_net']['ipv4']['dns_ptr'];
    }

    // All addressable IPs the client may set reverse DNS on: the server's
    // primary IPv4 plus every Extra (Floating) IP for this service.
    $rdnsIps = [];
    if ($ipv4) {
        $rdnsIps[] = ['ip' => $ipv4, 'ptr' => $ptr, 'fipid' => 0, 'label' => 'Primary'];
    }
    try {
        foreach ($api->floatingIps('whmcs_service=' . (int) $params['serviceid']) as $f) {
            if (empty($f['ip'])) {
                continue;
            }
            $fptr = '';
            if (!empty($f['dns_ptr'][0]['dns_ptr'])) {
                $fptr = $f['dns_ptr'][0]['dns_ptr'];
            }
            $rdnsIps[] = ['ip' => $f['ip'], 'ptr' => $fptr, 'fipid' => (int) $f['id'], 'label' => 'Extra IP'];
        }
    } catch (ApiException $e) {
        // extra IPs simply not shown this load
    }

    // Rescue mode status + this service's snapshots.
    $rescueEnabled = !empty($srv['rescue_enabled']);
    $snapshots = [];
    try {
        foreach ($api->snapshots('whmcs_service=' . (int) $params['serviceid']) as $img) {
            $snapshots[] = [
                'id'          => (int) $img['id'],
                'description' => $img['description'] ?: ('snapshot-' . $img['id']),
                'created'     => str_replace('T', ' ', substr($img['created'] ?? '', 0, 16)),
                'size'        => (!empty($img['image_size'])) ? round($img['image_size'], 1) . ' GB' : '',
            ];
        }
    } catch (ApiException $e) {
        // snapshots not shown this load
    }

    return [
        'templatefile' => 'overview',
        'vars' => [
            'brand'        => $brand,
            'notice'       => $notice,
            'error'        => $error,
            'serviceId'    => (int) $params['serviceid'],
            'status'       => $srv['status'] ?? 'unknown',
            'serverName'   => $params['domain'] ?: ('Server #' . $params['serviceid']),
            'typeLabel'    => strtoupper($srv['server_type']['name'] ?? ''),
            'cores'        => $srv['server_type']['cores'] ?? '',
            'memory'       => $srv['server_type']['memory'] ?? '',
            'disk'         => $srv['server_type']['disk'] ?? '',
            'location'     => $srv['location']['city'] ?? ($srv['datacenter']['location']['city'] ?? ''),
            'os'           => $srv['image']['description'] ?? ($srv['image']['name'] ?? ''),
            'currentOs'    => $srv['image']['name'] ?? '',
            'ipv4'         => $ipv4,
            'ipv6'         => $ipv6,
            'ptr'          => $ptr,
            'rdnsIps'      => $rdnsIps,
            'rescueEnabled' => $rescueEnabled,
            'snapshots'    => $snapshots,
            'images'       => $images,
            'allowRebuild' => $allowRebuild,
            'allowResize'  => $allowResize,
            'resizeOptions' => $resizeOptions,
            'includedTraffic' => isset($srv['included_traffic'])
                ? round($srv['included_traffic'] / (1024 ** 4), 1) : null,
            'outgoingTraffic' => isset($srv['outgoing_traffic'])
                ? round(($srv['outgoing_traffic'] ?? 0) / (1024 ** 3), 1) : null,
        ],
    ];
}

/**
 * Compute which server types the client may resize to: same architecture,
 * available at the server's datacenter, not deprecated. Upgrades only unless
 * downgrades are acceptable (we allow both but disk cannot shrink).
 */
function hetznercloud_resizeTargets(Api $api, array $srv)
{
    $out = [];
    try {
        $currentName = strtolower($srv['server_type']['name'] ?? '');
        $currentDisk = (int) ($srv['server_type']['disk'] ?? 0);
        $arch = $srv['server_type']['architecture'] ?? null;
        $family = hetznercloud_typeFamily($currentName);
        $locName = $srv['location']['name'] ?? ($srv['datacenter']['location']['name'] ?? null);

        $availableNames = [];
        if ($locName) {
            $set = [];
            foreach ($api->datacenters() as $dc) {
                if (($dc['location']['name'] ?? '') === $locName) {
                    foreach ($dc['server_types']['available'] ?? [] as $tid) {
                        $set[$tid] = true;
                    }
                }
            }
            $availableNames = array_keys($set);
        }

        foreach ($api->serverTypes() as $t) {
            if (!empty($t['deprecation'])) {
                continue;
            }
            if ($arch && ($t['architecture'] ?? null) !== $arch) {
                continue;
            }
            // Only offer plans within the SAME product family (e.g. a CX server
            // sees only other CX plans — not CPX/CCX). Both larger and smaller
            // are shown; disk feasibility is enforced at resize time.
            if (hetznercloud_typeFamily(strtolower($t['name'] ?? '')) !== $family) {
                continue;
            }
            if ($availableNames && !in_array($t['id'], $availableNames, true)) {
                continue;
            }
            if (strtolower($t['name'] ?? '') === $currentName) {
                continue;
            }
            $bigger = ((int) $t['cores'] > (int) ($srv['server_type']['cores'] ?? 0)
                || (int) $t['disk'] > $currentDisk);
            $out[$t['name']] = ($bigger ? '↑ ' : '↓ ') . strtoupper($t['name']) . ' — ' . (int) $t['cores']
                . ' vCPU / ' . (int) $t['memory'] . ' GB / ' . (int) $t['disk'] . ' GB';
        }
    } catch (ApiException $e) {
        // best-effort
    }
    return $out;
}

/** The product family prefix of a Hetzner type name (ccx > cpx > cax > cx). */
function hetznercloud_typeFamily($name)
{
    $n = strtolower($name);
    foreach (['ccx', 'cpx', 'cax', 'cx'] as $f) {
        if (strpos($n, $f) === 0) {
            return $f;
        }
    }
    return $n;
}

/**
 * Handle POST form actions. Returns [notice, error].
 */
function hetznercloud_caHandlePost(Api $api, $id, array $params)
{
    if (!$id) {
        return ['', 'Ο server δεν είναι έτοιμος.'];
    }
    $action = $_POST['hzaction'];
    try {
        switch ($action) {
            case 'power':
                $op = $_POST['op'] ?? '';
                $map = ['on' => 'powerOn', 'off' => 'powerOff', 'reboot' => 'reboot'];
                if (!isset($map[$op])) {
                    return ['', 'Άγνωστη ενέργεια.'];
                }
                $api->{$map[$op]}($id);
                $labels = ['on' => 'Power on', 'off' => 'Power off', 'reboot' => 'Reboot'];
                return [$labels[$op] . ' command sent to your server.', ''];

            case 'rebuild':
                if (($params['configoption7'] ?? 'on') === 'off') {
                    return ['', 'Η επανεγκατάσταση είναι απενεργοποιημένη για αυτή την υπηρεσία.'];
                }
                $image = preg_replace('/[^a-z0-9\.\-_]/i', '', $_POST['image'] ?? '');
                if (!$image) {
                    return ['', 'Επίλεξε λειτουργικό σύστημα.'];
                }
                $res = $api->rebuild($id, $image);
                $pw = $res['root_password'] ?? null;
                if ($pw) {
                    Helper::saveIpAndPassword($params, null, $pw);
                    return ['Ο server επανεγκαθίσταται. Νέος κωδικός root: ' . $pw, ''];
                }
                return ['Ο server επανεγκαθίσταται.', ''];

            case 'resize':
                if (($params['configoption8'] ?? '') !== 'on') {
                    return ['', 'Η αλλαγή πλάνου είναι απενεργοποιημένη για αυτή την υπηρεσία.'];
                }
                $type = preg_replace('/[^a-z0-9\-]/i', '', $_POST['server_type'] ?? '');
                if (!$type) {
                    return ['', 'Επίλεξε πλάνο.'];
                }
                $keepDisk = !empty($_POST['keepdisk']); // change CPU/RAM only
                $srv = $api->getServer($id);
                $currentName = strtolower($srv['server_type']['name'] ?? '');
                $currentDisk = (int) ($srv['server_type']['disk'] ?? 0);
                if (strtolower($type) === $currentName) {
                    return ['', 'Είσαι ήδη σε αυτό το πλάνο — επίλεξε άλλο.'];
                }
                // Look up the target's disk to decide the upgrade_disk flag.
                $targetDisk = 0;
                foreach ($api->serverTypes() as $t) {
                    if (strtolower($t['name']) === strtolower($type)) {
                        $targetDisk = (int) $t['disk'];
                        break;
                    }
                }
                // Grow the disk only on an upgrade AND when the client didn't ask
                // to keep it. Downgrades never grow (and need the disk to fit).
                $upgradeDisk = (!$keepDisk && $targetDisk >= $currentDisk);

                $wasRunning = ($srv['status'] ?? '') === 'running';
                if ($wasRunning) {
                    $off = $api->powerOff($id);
                    if (!empty($off['action']['id'])) {
                        $api->waitForAction($off['action']['id'], 60);
                    }
                }
                try {
                    $act = $api->changeType($id, $type, $upgradeDisk);
                    if (!empty($act['action']['id'])) {
                        $api->waitForAction($act['action']['id'], 120);
                    }
                } catch (ApiException $e) {
                    if ($wasRunning) {
                        try { $api->powerOn($id); } catch (ApiException $e2) {}
                    }
                    $m = strtolower($e->getMessage());
                    if (strpos($m, 'senseless') !== false || strpos($m, 'invalid_server_type') !== false) {
                        return ['', 'Αυτή η αλλαγή δεν είναι δυνατή για τον server σου. Επίλεξε άλλο πλάνο.'];
                    }
                    if (strpos($m, 'disk') !== false) {
                        return ['', 'Ο δίσκος του server δεν μπορεί να μικρύνει, οπότε πλάνο με μικρότερο δίσκο δεν γίνεται. '
                            . 'Τσέκαρε «αλλαγή μόνο CPU/RAM» για να κρατήσεις τον δίσκο σου, ή διάλεξε πλάνο με ίσο/μεγαλύτερο δίσκο.'];
                    }
                    return ['', 'Η αλλαγή απέτυχε: ' . $e->getMessage()];
                }
                if ($wasRunning) {
                    $api->powerOn($id);
                }
                $extra = $keepDisk ? ' (μόνο CPU/RAM — ο δίσκος ίδιος)' : '';
                return ['Ο server άλλαξε σε ' . strtoupper($type) . $extra . '.', ''];

            case 'rdns':
                $ptr = trim($_POST['ptr'] ?? '');
                $ip = trim($_POST['ip'] ?? '');
                $fipid = (int) ($_POST['fipid'] ?? 0);
                if ($ip === '') {
                    return ['', 'Δεν δόθηκε IP.'];
                }
                // Verify the IP really belongs to this service before touching it.
                $srv = $api->getServer($id);
                $owned = [];
                $primary = $srv['public_net']['ipv4']['ip'] ?? '';
                if ($primary) {
                    $owned[$primary] = 0;
                }
                $fipMap = [];
                foreach ($api->floatingIps('whmcs_service=' . (int) $params['serviceid']) as $f) {
                    if (!empty($f['ip'])) {
                        $owned[$f['ip']] = (int) $f['id'];
                        $fipMap[(int) $f['id']] = $f['ip'];
                    }
                }
                if (!isset($owned[$ip])) {
                    return ['', 'Αυτό το IP δεν ανήκει στον server σου.'];
                }
                if ($fipid > 0) {
                    if (($fipMap[$fipid] ?? null) !== $ip) {
                        return ['', 'Μη έγκυρο IP.'];
                    }
                    $api->changeFloatingIpReverseDns($fipid, $ip, $ptr);
                } else {
                    $api->changeReverseDns($id, $ip, $ptr);
                }
                return ['Το rDNS ενημερώθηκε για ' . $ip . '.', ''];

            case 'snapshot':
                $desc = substr(trim($_POST['description'] ?? 'snapshot'), 0, 60);
                $api->createSnapshot($id, $desc, ['whmcs_service' => (string) (int) $params['serviceid']]);
                return ['Η δημιουργία snapshot ξεκίνησε.', ''];

            case 'snapshot_restore':
                $imgId = (int) ($_POST['image_id'] ?? 0);
                if (!hetznercloud_ownsSnapshot($api, $params, $imgId)) {
                    return ['', 'Το snapshot δεν βρέθηκε.'];
                }
                $api->rebuild($id, $imgId);
                return ['Επαναφορά από snapshot — ο server ανακατασκευάζεται. Τα τρέχοντα δεδομένα διαγράφονται.', ''];

            case 'snapshot_delete':
                $imgId = (int) ($_POST['image_id'] ?? 0);
                if (!hetznercloud_ownsSnapshot($api, $params, $imgId)) {
                    return ['', 'Το snapshot δεν βρέθηκε.'];
                }
                $api->deleteImage($imgId);
                return ['Το snapshot διαγράφηκε.', ''];

            case 'rescue':
                $op = $_POST['op'] ?? '';
                if ($op === 'enable') {
                    $res = $api->enableRescue($id, 'linux64');
                    $pw = $res['root_password'] ?? '';
                    try { $api->reset($id); } catch (ApiException $e) {}
                    return ['Η διάσωση ενεργοποιήθηκε — ο server επανεκκινεί σε αυτή. '
                        . ($pw ? 'Κωδικός root διάσωσης: ' . $pw : ''), ''];
                }
                if ($op === 'disable') {
                    $api->disableRescue($id);
                    try { $api->reset($id); } catch (ApiException $e) {}
                    return ['Η διάσωση απενεργοποιήθηκε — ο server επιστρέφει στο κανονικό.', ''];
                }
                return ['', 'Άγνωστη ενέργεια διάσωσης.'];

            case 'resetpw':
                $res = $api->resetPassword($id);
                $pw = $res['root_password'] ?? null;
                if ($pw) {
                    Helper::saveIpAndPassword($params, null, $pw);
                    return ['Νέος κωδικός root: ' . $pw, ''];
                }
                return ['Ζητήθηκε επαναφορά κωδικού.', ''];
        }
    } catch (ApiException $e) {
        return ['', 'Η ενέργεια απέτυχε: ' . $e->getMessage()];
    }
    return ['', 'Άγνωστη ενέργεια.'];
}

/** Verify a snapshot image belongs to this service (via its whmcs_service label). */
function hetznercloud_ownsSnapshot(Api $api, array $params, $imageId)
{
    if ((int) $imageId <= 0) {
        return false;
    }
    try {
        foreach ($api->snapshots('whmcs_service=' . (int) $params['serviceid']) as $img) {
            if ((int) $img['id'] === (int) $imageId) {
                return true;
            }
        }
    } catch (ApiException $e) {
        // fall through
    }
    return false;
}

/**
 * JSON AJAX router (status polling, metrics graphs, console). Always exits.
 */
function hetznercloud_caAjax(Api $api, $id, array $params)
{
    header('Content-Type: application/json');
    $what = $_GET['hzajax'];
    try {
        if (!$id) {
            throw new ApiException('Server not ready');
        }
        if ($what === 'status') {
            $srv = $api->getServer($id);
            echo json_encode([
                'ok'     => true,
                'status' => $srv['status'] ?? 'unknown',
            ]);
        } elseif ($what === 'metrics') {
            $type = in_array($_GET['type'] ?? '', ['cpu', 'disk', 'network'], true)
                ? $_GET['type'] : 'cpu';
            $end = time();
            $start = $end - 3600; // last hour
            $m = $api->serverMetrics($id, $type, $start, $end);
            echo json_encode(['ok' => true, 'metrics' => $m['metrics'] ?? []]);
        } elseif ($what === 'console') {
            $res = $api->requestConsole($id);
            echo json_encode([
                'ok'       => true,
                'wss_url'  => $res['wss_url'] ?? '',
                'password' => $res['password'] ?? '',
            ]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'unknown']);
        }
    } catch (ApiException $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

function hetznercloud_caError($brand, $message)
{
    return [
        'templatefile' => 'overview',
        'vars' => [
            'brand'    => $brand,
            'fatal'    => $message,
            'status'   => 'unknown',
        ],
    ];
}
