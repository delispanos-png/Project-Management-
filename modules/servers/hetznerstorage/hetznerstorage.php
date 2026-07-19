<?php
/**
 * Hetzner Storage Box — WHMCS provisioning module (white-label).
 *
 * Reuses the API client shipped with the hetznercloud module. Provides basic
 * lifecycle (create / suspend / unsuspend / terminate) plus a client area that
 * shows connection details and lets the client reset the password. No provider
 * branding is shown to clients.
 *
 * @package WHMCS\Module\Server\HetznerCloud
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\HetznerCloud\Api;
use WHMCS\Module\Server\HetznerCloud\ApiException;
use WHMCS\Module\Server\HetznerCloud\Helper;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/../hetznercloud/lib/Api.php';
require_once __DIR__ . '/../hetznercloud/lib/Helper.php';

function hetznerstorage_MetaData()
{
    return [
        'DisplayName'    => 'Storage Box (Hetzner-backed)',
        'APIVersion'     => '1.1',
        'RequiresServer' => true,
    ];
}

function hetznerstorage_catalogue()
{
    $types = ['' => '— set token in addon —'];
    $locations = ['' => 'auto'];
    $token = Helper::globalToken();
    if ($token) {
        try {
            $api = new Api($token);
            $types = [];
            foreach ($api->storageBoxTypes() as $t) {
                $size = isset($t['size']) ? round($t['size'] / (1024 ** 4), 1) . ' TB' : ($t['name'] ?? '');
                $types[$t['name']] = strtoupper($t['name']) . ' — ' . $size;
            }
            $locations = [];
            foreach ($api->locations() as $l) {
                $locations[$l['name']] = ($l['city'] ?? $l['name']) . ', ' . ($l['country'] ?? '');
            }
        } catch (ApiException $e) {
            // keep placeholders
        }
    }
    return [$types, $locations];
}

function hetznerstorage_ConfigOptions()
{
    list($types, $locations) = hetznerstorage_catalogue();
    return [
        'Storage Box Type' => ['Type' => 'dropdown', 'Options' => $types],
        'Location'         => ['Type' => 'dropdown', 'Options' => $locations],
        'Enable SSH/SFTP'  => ['Type' => 'yesno', 'Default' => 'on'],
        'Enable Samba'     => ['Type' => 'yesno'],
        'Enable WebDAV'    => ['Type' => 'yesno'],
        'Brand Name'       => ['Type' => 'text', 'Size' => '30', 'Description' => 'White-label name shown to clients.'],
    ];
}

function hetznerstorage_TestConnection(array $params)
{
    try {
        return Helper::api($params)->testConnection();
    } catch (\Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function hetznerstorage_storageId(array $params)
{
    $v = Helper::readCustomField($params, Helper::FIELD_STORAGE_ID);
    if ($v) {
        return (int) $v;
    }
    if (!empty($params['username']) && strpos($params['username'], 'sb-') === 0) {
        return (int) substr($params['username'], 3);
    }
    return 0;
}

function hetznerstorage_CreateAccount(array $params)
{
    try {
        $api = Helper::api($params);
        $name = 'whmcs-sb-' . (int) $params['serviceid'];

        // Adopt if already present.
        $existing = hetznerstorage_storageId($params);
        if ($existing && $api->getStorageBox($existing)) {
            return 'success';
        }

        $password = hetznerstorage_randomPassword();
        $data = [
            'name'              => $name,
            'storage_box_type'  => $params['configoption1'],
            'location'          => $params['configoption2'] ?: null,
            'password'          => $password,
            'access_settings'   => [
                'ssh_enabled'    => ($params['configoption3'] !== 'off'),
                'samba_enabled'  => ($params['configoption4'] === 'on'),
                'webdav_enabled' => ($params['configoption5'] === 'on'),
                'reachable_externally' => true,
            ],
            'labels' => ['whmcs_service' => (string) (int) $params['serviceid']],
        ];
        if (empty($data['location'])) {
            unset($data['location']);
        }

        $res = $api->createStorageBox($data);
        $box = $res['storage_box'] ?? null;
        // Some responses return only an action; fetch by name as a fallback.
        if (!$box) {
            foreach ($api->storageBoxes() as $b) {
                if (($b['name'] ?? '') === $name) { $box = $b; break; }
            }
        }
        if (!$box) {
            return 'Storage Box created but could not be read back.';
        }

        if (!Helper::saveCustomField($params, Helper::FIELD_STORAGE_ID, $box['id'])) {
            Capsule::table('tblhosting')->where('id', (int) $params['serviceid'])
                ->update(['username' => 'sb-' . $box['id']]);
        }
        Helper::saveIpAndPassword($params, $box['server'] ?? null, $password);
        return 'success';
    } catch (ApiException $e) {
        return 'Storage error: ' . $e->getMessage();
    } catch (\Throwable $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function hetznerstorage_SuspendAccount(array $params)
{
    try {
        $id = hetznerstorage_storageId($params);
        if ($id) {
            Helper::api($params)->updateStorageBoxAccess($id, [
                'ssh_enabled' => false, 'samba_enabled' => false, 'webdav_enabled' => false,
                'reachable_externally' => false,
            ]);
        }
        return 'success';
    } catch (ApiException $e) {
        return $e->getMessage();
    }
}

function hetznerstorage_UnsuspendAccount(array $params)
{
    try {
        $id = hetznerstorage_storageId($params);
        if ($id) {
            Helper::api($params)->updateStorageBoxAccess($id, [
                'ssh_enabled'    => ($params['configoption3'] !== 'off'),
                'samba_enabled'  => ($params['configoption4'] === 'on'),
                'webdav_enabled' => ($params['configoption5'] === 'on'),
                'reachable_externally' => true,
            ]);
        }
        return 'success';
    } catch (ApiException $e) {
        return $e->getMessage();
    }
}

function hetznerstorage_TerminateAccount(array $params)
{
    try {
        $id = hetznerstorage_storageId($params);
        if ($id) {
            try {
                Helper::api($params)->deleteStorageBox($id);
            } catch (ApiException $e) {
                if ($e->getCode() !== 404) { throw $e; }
            }
        }
        return 'success';
    } catch (ApiException $e) {
        return $e->getMessage();
    }
}

function hetznerstorage_ClientArea(array $params)
{
    $brand = Helper::brand($params);
    $id = hetznerstorage_storageId($params);

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['hzaction'] ?? '') === 'resetpw' && $id) {
        try {
            $pw = hetznerstorage_randomPassword();
            Helper::api($params)->resetStorageBoxPassword($id, $pw);
            Helper::saveIpAndPassword($params, null, $pw);
            $notice = 'New password: ' . $pw;
        } catch (ApiException $e) {
            $error = $e->getMessage();
        }
    }

    $box = null;
    if ($id) {
        try { $box = Helper::api($params)->getStorageBox($id); } catch (ApiException $e) {}
    }

    return [
        'templatefile' => 'clientarea',
        'vars' => [
            'brand'   => $brand,
            'notice'  => $notice ?? '',
            'error'   => $error ?? '',
            'ready'   => (bool) $box,
            'server'  => $box['server'] ?? '',
            'username' => $box['username'] ?? '',
            'location' => $box['location']['city'] ?? '',
            'ssh'     => !empty($box['access_settings']['ssh_enabled']),
            'samba'   => !empty($box['access_settings']['samba_enabled']),
            'webdav'  => !empty($box['access_settings']['webdav_enabled']),
        ],
    ];
}

function hetznerstorage_randomPassword($len = 20)
{
    $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#%^*-_';
    $pw = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $len; $i++) {
        $pw .= $alphabet[random_int(0, $max)];
    }
    return $pw;
}
