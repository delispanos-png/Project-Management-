<?php
/**
 * Delete the Hetzner server + Extra IPs when a Cloud Servers service is set to
 * "Terminated" — including via the admin Status dropdown, which normally does
 * NOT run the module's Terminate command. Makes termination foolproof so no
 * server is ever left running (and billed) after a service is terminated.
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\HetznerCloud\Api;
use WHMCS\Module\Server\HetznerCloud\ApiException;
use WHMCS\Module\Server\HetznerCloud\Helper;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ServiceEdit', 1, function ($vars) {
    $sid = (int) ($vars['serviceid'] ?? 0);
    if (!$sid) {
        return;
    }

    $svc = Capsule::table('tblhosting')->where('id', $sid)
        ->first(['packageid', 'domainstatus', 'username']);
    if (!$svc || $svc->domainstatus !== 'Terminated') {
        return;
    }
    $type = Capsule::table('tblproducts')->where('id', $svc->packageid)->value('servertype');
    if ($type !== 'hetznercloud') {
        return;
    }

    require_once __DIR__ . '/../../modules/servers/hetznercloud/lib/Api.php';
    require_once __DIR__ . '/../../modules/servers/hetznercloud/lib/Helper.php';

    // Resolve the Hetzner server id.
    $serverId = (int) Capsule::table('tblcustomfieldsvalues as v')
        ->join('tblcustomfields as f', 'f.id', '=', 'v.fieldid')
        ->where('v.relid', $sid)
        ->whereIn('f.fieldname', ['hetzner_server_id', 'vpsid'])
        ->where('v.value', '<>', '')
        ->value('v.value');
    if (!$serverId && strpos((string) $svc->username, 'hz-') === 0) {
        $serverId = (int) substr($svc->username, 3);
    }
    if (!$serverId) {
        return;
    }

    $token = Helper::globalToken();
    if ($token === '') {
        return;
    }
    $api = new Api($token);

    // Only act if the server still exists — avoids re-running after the module
    // Terminate button (or a previous save) already removed it.
    try {
        if (!$api->getServer($serverId)) {
            return;
        }
    } catch (ApiException $e) {
        if ($e->getCode() === 404) {
            return;
        }
        return; // transient error — don't risk anything
    }

    try {
        Helper::deleteHetznerResources($api, $serverId, $sid);
        if (function_exists('logActivity')) {
            logActivity("Hetzner: deleted server #$serverId + extra IPs for terminated service #$sid");
        }
    } catch (\Throwable $e) {
        if (function_exists('logActivity')) {
            logActivity("Hetzner: failed to delete server #$serverId for service #$sid: " . $e->getMessage());
        }
    }
});
