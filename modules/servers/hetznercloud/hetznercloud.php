<?php
/**
 * Hetzner Cloud — WHMCS provisioning module.
 *
 * White-label cloud server provisioning & full lifecycle management backed by
 * the Hetzner Cloud API. Clients never see the word "Hetzner": all client-facing
 * output uses the configurable brand name.
 *
 * Companion addon module "hetznercloud" handles pricing/availability sync and
 * bulk import of already-sold services.
 *
 * @package    WHMCS\Module\Server\HetznerCloud
 * @author     Cloudon
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\HetznerCloud\Api;
use WHMCS\Module\Server\HetznerCloud\ApiException;
use WHMCS\Module\Server\HetznerCloud\Helper;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Api.php';
require_once __DIR__ . '/lib/Helper.php';
require_once __DIR__ . '/lib/Pricing.php';

/**
 * Module metadata.
 */
function hetznercloud_MetaData()
{
    return [
        'DisplayName'                => 'Cloud Servers (Hetzner-backed)',
        'APIVersion'                 => '1.1',
        // Provisioning uses the global API token from the addon, so a WHMCS
        // "server" is not required. (Set to true only if you need per-project
        // tokens via multiple server entries.)
        'RequiresServer'             => false,
        'DefaultNonSSLPort'          => '',
        'DefaultSSLPort'             => '',
        'ServiceSingleSignOnLabel'   => 'Open Control Panel',
        'AdminSingleSignOnLabel'     => '',
        'ListAccountsUniqueIdentifierField' => 'domain',
    ];
}

/**
 * Populate the API-driven catalogue so ConfigOptions dropdowns are dynamic.
 * Uses the global addon token (product config screen has no server context).
 *
 * @return array [serverTypes, images, locations, sshKeys] (each id=>label)
 */
function hetznercloud_catalogue()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $serverTypes = ['' => '— set token in addon to load —'];
    $images = ['' => 'ubuntu-22.04'];
    $locations = ['' => '— set token in addon to load —'];
    $sshKeys = ['' => 'None (password auth)'];

    $token = Helper::globalToken();
    if ($token) {
        try {
            $api = new Api($token);
            foreach ($api->serverTypes() as $t) {
                if (!empty($t['deprecation'])) {
                    continue;
                }
                $label = strtoupper($t['name']) . ' — ' . (int) $t['cores'] . ' vCPU / '
                    . (int) $t['memory'] . ' GB / ' . (int) $t['disk'] . ' GB';
                $serverTypes[$t['name']] = $label;
            }
            $images = [];
            foreach ($api->images('system') as $img) {
                if (empty($img['name'])) {
                    continue;
                }
                $images[$img['name']] = $img['description'] ?: $img['name'];
            }
            $locations = [];
            foreach ($api->locations() as $loc) {
                $locations[$loc['name']] = ($loc['city'] ?? $loc['name']) . ', ' . ($loc['country'] ?? '');
            }
            $sshKeys = ['' => 'None (password auth)'];
            foreach ($api->sshKeys() as $key) {
                $sshKeys[$key['name']] = $key['name'];
            }
        } catch (ApiException $e) {
            // Leave placeholder options; admin sees the error on TestConnection.
        }
    }

    return $cache = compact('serverTypes', 'images', 'locations', 'sshKeys');
}

/**
 * Product configuration options.
 */
function hetznercloud_ConfigOptions()
{
    $cat = hetznercloud_catalogue();

    return [
        'Server Type' => [
            'Type'        => 'dropdown',
            'Options'     => $cat['serverTypes'],
            'Description' => 'Hetzner server type (internal — clients never see this).',
        ],
        'Operating System' => [
            'Type'        => 'dropdown',
            'Options'     => $cat['images'],
            'Description' => 'Default OS image installed on provisioning.',
        ],
        'Location' => [
            'Type'        => 'dropdown',
            'Options'     => $cat['locations'],
            'Description' => 'Datacenter location.',
        ],
        'Enable IPv4' => [
            'Type'        => 'yesno',
            'Description' => 'Assign a public IPv4 (adds primary-IP cost).',
            'Default'     => 'on',
        ],
        'Enable Backups' => [
            'Type'        => 'yesno',
            'Description' => 'Enable Hetzner automated backups (+20% cost).',
        ],
        'SSH Key' => [
            'Type'        => 'dropdown',
            'Options'     => $cat['sshKeys'],
            'Description' => 'Optional SSH key injected at build time.',
        ],
        'Client may rebuild OS' => [
            'Type'        => 'yesno',
            'Description' => 'Show the reinstall/rebuild control in the client area.',
            'Default'     => 'on',
        ],
        'Client may resize' => [
            'Type'        => 'yesno',
            'Description' => 'Allow upgrade/downgrade of the server type from the client area.',
        ],
        'Brand Name (white-label)' => [
            'Type'        => 'text',
            'Size'        => '30',
            'Default'     => '',
            'Description' => 'Shown to clients instead of "Hetzner". Falls back to the addon brand.',
        ],
    ];
}

/**
 * Test the stored server credentials from Setup > Servers.
 */
function hetznercloud_TestConnection(array $params)
{
    try {
        $api = Helper::api($params);
        $result = $api->testConnection();
        if ($result['success']) {
            return ['success' => true, 'error' => ''];
        }
        return ['success' => false, 'error' => $result['error']];
    } catch (\Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Provision (or adopt) a Hetzner cloud server.
 *
 * Idempotent: if the service is already linked, or a server with our
 * deterministic name already exists, we adopt it instead of creating a
 * duplicate. This is what lets already-sold services fold into the automation.
 */
function hetznercloud_CreateAccount(array $params)
{
    try {
        // Multi-project: pin this service to its target project BEFORE any API
        // call, so Helper::api() uses the right token. Don't clobber an existing
        // pin (retry / already-imported service).
        $serviceId = (int) ($params['serviceid'] ?? 0);
        $targetPid = Helper::targetProjectForCreate($params);
        $pinnedPid = Helper::instanceProjectId($serviceId);
        if (!$pinnedPid && $targetPid) {
            Helper::recordInstance($serviceId, $targetPid, null);
            $pinnedPid = $targetPid;
        }

        $api = Helper::api($params);
        $name = Helper::remoteServerName($params);

        // 1. Already linked?
        $existingId = Helper::serverId($params);
        if ($existingId) {
            $srv = $api->getServer($existingId);
            if ($srv) {
                hetznercloud_persistFromServer($params, $srv);
                if (($srv['status'] ?? '') === 'off') {
                    $api->powerOn($existingId);
                }
                Helper::resetStock($params);
                return 'success';
            }
        }

        // 2. A server with our name already exists on the project? Adopt it.
        $byName = $api->findServerByName($name);
        if ($byName) {
            Helper::saveServerId($params, $byName['id']);
            Helper::recordInstance($serviceId, $pinnedPid ?: $targetPid, $byName['id']);
            hetznercloud_persistFromServer($params, $byName);
            Helper::resetStock($params);
            return 'success';
        }

        // 3. Fresh build. Customer Configurable Options (Location / OS / Backups /
        //    Extra IPs) override the product-level defaults when present.
        $image = $params['configoption2'] ?: 'ubuntu-22.04';
        $osChoice = Helper::optionValue($params, ['operating system', 'os', 'template']);
        if ($osChoice) {
            if (stripos($osChoice, 'windows') !== false) {
                return 'Windows is not available on this platform. Please choose a Linux OS for this service.';
            }
            $image = Helper::mapImage($osChoice, $api, $image);
        }

        $location = $params['configoption3'] ?: null;
        $locChoice = Helper::optionValue($params, ['location', 'region', 'datacenter']);
        if ($locChoice) {
            $location = Helper::mapLocation($locChoice, $api, $location);
        }

        // Backups: an explicit customer option wins, else the product default.
        $wantsBackups = Helper::wantsBackups($params);
        if ($wantsBackups === null) {
            $wantsBackups = ($params['configoption5'] === 'on');
        }

        $data = [
            'name'                => $name,
            'server_type'         => $params['configoption1'],
            'image'               => $image,
            'location'            => $location ?: null,
            'start_after_create'  => true,
            'public_net'          => [
                'enable_ipv4' => ($params['configoption4'] !== 'off'),
                'enable_ipv6' => true,
            ],
            'labels' => [
                'whmcs_service' => (string) (int) $params['serviceid'],
                'whmcs_client'  => (string) (int) $params['userid'],
            ],
        ];
        if (empty($data['location'])) {
            unset($data['location']);
        }
        if (!empty($params['configoption6'])) {
            $data['ssh_keys'] = [$params['configoption6']];
        }

        $serviceId = (int) $params['serviceid'];
        $extraCount = Helper::extraIpsCount($params);
        $extraIps = [];
        $createdFips = [];

        // When the location is known up-front, pre-create the Extra IPs so we
        // can bake their in-OS configuration into the server's cloud-init.
        $preCreate = ($extraCount > 0 && !empty($location));
        if ($preCreate) {
            for ($i = 0; $i < $extraCount; $i++) {
                try {
                    $fip = $api->createFloatingIp('ipv4', null, $location,
                        'whmcs-' . $serviceId . '-extra',
                        ['whmcs_service' => (string) $serviceId]);
                    if (!empty($fip['floating_ip']['id'])) {
                        $createdFips[] = $fip['floating_ip']['id'];
                        if (!empty($fip['floating_ip']['ip'])) {
                            $extraIps[] = $fip['floating_ip']['ip'];
                        }
                    }
                } catch (ApiException $e) {
                    if (function_exists('logModuleCall')) {
                        logModuleCall('hetznercloud', 'ExtraIP-precreate', $location, $e->getMessage());
                    }
                }
            }
            if ($extraIps) {
                $data['user_data'] = Helper::floatingIpCloudInit($extraIps);
            }
        }

        $res = $api->createServer($data);
        $server = $res['server'] ?? null;
        if (!$server) {
            // Roll back any pre-created Extra IPs so nothing is orphaned/billed.
            foreach ($createdFips as $fid) {
                try { $api->deleteFloatingIp($fid); } catch (ApiException $e) { /* ignore */ }
            }
            return 'Hetzner did not return a server object.';
        }
        $serverId = $server['id'];
        Helper::saveServerId($params, $serverId);
        Helper::recordInstance($serviceId, $pinnedPid ?: $targetPid, $serverId);
        $rootPassword = $res['root_password'] ?? null;

        // Assign the pre-created Extra IPs to the new server.
        foreach ($createdFips as $fid) {
            try { $api->assignFloatingIp($fid, $serverId); } catch (ApiException $e) { /* non-fatal */ }
        }

        // Fallback path: extra IPs requested but no fixed location — create and
        // assign them now (cloud-init cannot include them in this case).
        if ($extraCount > 0 && !$preCreate) {
            $loc = $server['datacenter']['location']['name'] ?? null;
            for ($i = 0; $i < $extraCount; $i++) {
                try {
                    $fip = $api->createFloatingIp('ipv4', $serverId, $loc,
                        'whmcs-' . $serviceId . '-extra',
                        ['whmcs_service' => (string) $serviceId]);
                    if (!empty($fip['floating_ip']['ip'])) {
                        $extraIps[] = $fip['floating_ip']['ip'];
                    }
                } catch (ApiException $e) {
                    if (function_exists('logModuleCall')) {
                        logModuleCall('hetznercloud', 'ExtraIP', $extraCount, $e->getMessage());
                    }
                }
            }
        }

        // Backups.
        if ($wantsBackups) {
            try { $api->enableBackup($serverId); } catch (ApiException $e) { /* non-fatal */ }
        }

        // Write delivery fields (root user/pass, primary IP, extra IPs, vpsid).
        Helper::saveDelivery($params, $server, $rootPassword, $extraIps);

        Helper::resetStock($params);
        return 'success';
    } catch (ApiException $e) {
        return 'Hetzner error: ' . $e->getMessage();
    } catch (\Throwable $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/** Copy IP / password details from a Hetzner server object into WHMCS. */
function hetznercloud_persistFromServer(array $params, array $server, $rootPassword = null)
{
    $ip = $server['public_net']['ipv4']['ip'] ?? null;
    Helper::saveIpAndPassword($params, $ip, $rootPassword);
}

/**
 * Suspend => power the server off (billing stopped, data retained).
 */
function hetznercloud_SuspendAccount(array $params)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return 'No linked Hetzner server.';
        }
        Helper::api($params)->powerOff($id);
        return 'success';
    } catch (ApiException $e) {
        return 'Hetzner error: ' . $e->getMessage();
    }
}

/**
 * Unsuspend => power the server back on.
 */
function hetznercloud_UnsuspendAccount(array $params)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return 'No linked Hetzner server.';
        }
        Helper::api($params)->powerOn($id);
        return 'success';
    } catch (ApiException $e) {
        return 'Hetzner error: ' . $e->getMessage();
    }
}

/**
 * Terminate => delete the server permanently.
 */
function hetznercloud_TerminateAccount(array $params)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return 'success'; // nothing to delete
        }
        $api = Helper::api($params);
        Helper::deleteHetznerResources($api, $id, (int) $params['serviceid']);
        return 'success';
    } catch (ApiException $e) {
        return 'Hetzner error: ' . $e->getMessage();
    }
}

/**
 * Change package => resize the server type (upgrade/downgrade).
 * Hetzner requires the server to be powered off during change_type.
 */
function hetznercloud_ChangePackage(array $params)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return 'No linked Hetzner server.';
        }
        $api = Helper::api($params);
        $target = $params['configoption1'];
        $srv = $api->getServer($id);
        if (!$srv) {
            return 'Linked server not found.';
        }
        if (($srv['server_type']['name'] ?? '') === $target) {
            return 'success'; // nothing to do
        }

        $wasRunning = ($srv['status'] ?? '') === 'running';
        if ($wasRunning) {
            $off = $api->powerOff($id);
            if (!empty($off['action']['id'])) {
                $api->waitForAction($off['action']['id'], 60);
            }
        }
        // upgrade_disk=true is irreversible but gives clients the full disk.
        $act = $api->changeType($id, $target, true);
        if (!empty($act['action']['id'])) {
            $api->waitForAction($act['action']['id'], 120);
        }
        if ($wasRunning) {
            $api->powerOn($id);
        }
        return 'success';
    } catch (ApiException $e) {
        return 'Hetzner error: ' . $e->getMessage();
    }
}

/**
 * Admin area: expose a couple of quick actions on the service page.
 */
function hetznercloud_AdminCustomButtonArray(array $params)
{
    return [
        'Power On'  => 'adminPowerOn',
        'Power Off' => 'adminPowerOff',
        'Reboot'    => 'adminReboot',
        'Sync Info' => 'adminSync',
    ];
}

function hetznercloud_adminPowerOn(array $params)  { return hetznercloud_simpleAction($params, 'powerOn'); }
function hetznercloud_adminPowerOff(array $params) { return hetznercloud_simpleAction($params, 'powerOff'); }
function hetznercloud_adminReboot(array $params)   { return hetznercloud_simpleAction($params, 'reboot'); }

function hetznercloud_adminSync(array $params)
{
    try {
        $id = Helper::serverId($params);
        $srv = Helper::api($params)->getServer($id);
        if ($srv) {
            hetznercloud_persistFromServer($params, $srv);
        }
        return 'success';
    } catch (ApiException $e) {
        return $e->getMessage();
    }
}

function hetznercloud_simpleAction(array $params, $method)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return 'No linked server.';
        }
        Helper::api($params)->{$method}($id);
        return 'success';
    } catch (ApiException $e) {
        return $e->getMessage();
    }
}

/**
 * Admin service tab: show live Hetzner status without leaking to the client.
 */
function hetznercloud_AdminServicesTabFields(array $params)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return ['Hetzner Link' => 'Not linked yet'];
        }
        $srv = Helper::api($params)->getServer($id);
        if (!$srv) {
            return ['Hetzner Link' => 'Server #' . $id . ' not found'];
        }
        $ipv4 = $srv['public_net']['ipv4']['ip'] ?? '—';
        return [
            'Hetzner Server ID' => (string) $srv['id'],
            'Type'   => strtoupper($srv['server_type']['name'] ?? '?'),
            'Status' => $srv['status'] ?? '?',
            'IPv4'   => $ipv4,
            'OS'     => $srv['image']['description'] ?? ($srv['image']['name'] ?? '?'),
            'Location' => $srv['location']['city'] ?? ($srv['datacenter']['location']['city'] ?? '?'),
        ];
    } catch (ApiException $e) {
        return ['Hetzner Link' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Single Sign-On target for the "Open Control Panel" button — we keep clients
 * inside our own white-label panel rather than sending them to Hetzner.
 */
function hetznercloud_ServiceSingleSignOn(array $params)
{
    return [
        'success'  => true,
        'redirectTo' => 'clientarea.php?action=productdetails&id=' . (int) $params['serviceid'],
    ];
}

require_once __DIR__ . '/clientarea.php';
