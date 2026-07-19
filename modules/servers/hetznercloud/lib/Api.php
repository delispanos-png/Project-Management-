<?php
/**
 * Hetzner Cloud API client.
 *
 * Thin, dependency-free wrapper over the Hetzner Cloud REST API (v1) and the
 * Storage Box API. One instance is bound to a single API token, which in
 * Hetzner terms maps to a single Cloud "project".
 *
 * Docs: https://docs.hetzner.cloud/
 *
 * @package WHMCS\Module\Server\HetznerCloud
 */

namespace WHMCS\Module\Server\HetznerCloud;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class ApiException extends \Exception
{
    /** @var array Decoded error body from the API, if any. */
    public $body = [];

    public function __construct($message, $code = 0, array $body = [])
    {
        parent::__construct($message, $code);
        $this->body = $body;
    }
}

class Api
{
    const BASE_URL = 'https://api.hetzner.cloud/v1';

    /** @var string */
    private $token;

    /** @var int */
    private $timeout;

    /** @var callable|null Optional logger: function(string $action, mixed $request, mixed $response). */
    private $logger;

    public function __construct($token, $timeout = 60)
    {
        $this->token = trim((string) $token);
        $this->timeout = (int) $timeout;
    }

    /**
     * Normalise a stored token that may be plaintext or WHMCS-encrypted.
     *
     * WHMCS stores addon "password" settings inconsistently across setups
     * (sometimes plaintext, sometimes encrypted). A Hetzner Cloud token is a
     * 40–80 char alphanumeric string, so we can tell them apart: if decrypting
     * yields a token-shaped value the source was encrypted; otherwise the raw
     * value already is the token.
     */
    public static function normalizeToken($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        $looksToken = function ($s) {
            return (bool) preg_match('/^[A-Za-z0-9]{40,80}$/', (string) $s);
        };
        if (function_exists('decrypt')) {
            $dec = trim((string) decrypt($raw));
            if ($looksToken($dec)) {
                return $dec; // was encrypted
            }
        }
        return $raw; // plaintext (or unknown — use verbatim)
    }

    /**
     * Register a logging callback. WHMCS passes logModuleCall here.
     */
    public function setLogger(callable $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    // ---------------------------------------------------------------------
    // Catalogue / read-only endpoints
    // ---------------------------------------------------------------------

    /** Fetch every server type (paginated). */
    public function serverTypes()
    {
        return $this->collect('server_types', 'server_types');
    }

    /** Fetch all locations. */
    public function locations()
    {
        return $this->collect('locations', 'locations');
    }

    /**
     * Fetch all datacenters. Each datacenter exposes which server types are
     * currently available for new servers — this is our availability source.
     */
    public function datacenters()
    {
        return $this->collect('datacenters', 'datacenters');
    }

    /** Full pricing catalogue (currency, vat, per-type prices, backups, traffic). */
    public function pricing()
    {
        $res = $this->request('GET', '/pricing');
        return isset($res['pricing']) ? $res['pricing'] : [];
    }

    /**
     * System / snapshot images.
     *
     * @param string $type   one of system|snapshot|backup|app
     * @param string $arch   x86|arm (optional filter)
     */
    public function images($type = 'system', $arch = null)
    {
        $query = ['type' => $type, 'per_page' => 50];
        if ($arch) {
            $query['architecture'] = $arch;
        }
        return $this->collect('images', 'images', $query);
    }

    /** All SSH keys registered in the project. */
    public function sshKeys()
    {
        return $this->collect('ssh_keys', 'ssh_keys');
    }

    // ---------------------------------------------------------------------
    // Servers
    // ---------------------------------------------------------------------

    public function getServer($id)
    {
        $res = $this->request('GET', '/servers/' . (int) $id);
        return isset($res['server']) ? $res['server'] : null;
    }

    /** Find a server by its exact name (Hetzner allows duplicates, we take the first). */
    public function findServerByName($name)
    {
        $res = $this->request('GET', '/servers', ['name' => $name]);
        if (!empty($res['servers'][0])) {
            return $res['servers'][0];
        }
        return null;
    }

    /**
     * Create a server.
     *
     * @param array $data server_type, image, location|datacenter, name,
     *                     ssh_keys, user_data, start_after_create, public_net...
     * @return array {server, action, root_password, next_actions}
     */
    public function createServer(array $data)
    {
        return $this->request('POST', '/servers', $data);
    }

    public function deleteServer($id)
    {
        return $this->request('DELETE', '/servers/' . (int) $id);
    }

    /** Generic power/lifecycle action helper. */
    private function serverAction($id, $action, array $body = [])
    {
        return $this->request('POST', '/servers/' . (int) $id . '/actions/' . $action, $body);
    }

    public function powerOn($id)      { return $this->serverAction($id, 'poweron'); }
    public function powerOff($id)     { return $this->serverAction($id, 'poweroff'); }
    public function shutdown($id)     { return $this->serverAction($id, 'shutdown'); } // ACPI soft
    public function reboot($id)       { return $this->serverAction($id, 'reboot'); }   // soft
    public function reset($id)        { return $this->serverAction($id, 'reset'); }    // hard

    public function resetPassword($id)
    {
        return $this->serverAction($id, 'reset_password'); // returns root_password
    }

    public function rebuild($id, $image)
    {
        return $this->serverAction($id, 'rebuild', ['image' => $image]); // returns root_password
    }

    public function enableRescue($id, $type = 'linux64', array $sshKeys = [])
    {
        $body = ['type' => $type];
        if ($sshKeys) {
            $body['ssh_keys'] = $sshKeys;
        }
        return $this->serverAction($id, 'enable_rescue', $body); // returns root_password
    }

    public function disableRescue($id)
    {
        return $this->serverAction($id, 'disable_rescue');
    }

    /**
     * Change server type (upgrade / downgrade). Server must be powered off.
     * upgrade_disk=false keeps the smaller disk so the change is reversible.
     */
    public function changeType($id, $serverType, $upgradeDisk = true)
    {
        return $this->serverAction($id, 'change_type', [
            'server_type'  => $serverType,
            'upgrade_disk' => (bool) $upgradeDisk,
        ]);
    }

    /** Request a noVNC console: returns {wss_url, password}. */
    public function requestConsole($id)
    {
        return $this->serverAction($id, 'request_console');
    }

    /** Create a snapshot image from the server's disk. */
    public function createSnapshot($id, $description = '', array $labels = [])
    {
        $body = ['type' => 'snapshot', 'description' => $description];
        if ($labels) {
            $body['labels'] = $labels;
        }
        return $this->serverAction($id, 'create_image', $body);
    }

    /** List snapshot images, optionally filtered by a label selector. */
    public function snapshots($labelSelector = null)
    {
        $query = ['type' => 'snapshot', 'per_page' => 50];
        if ($labelSelector) {
            $query['label_selector'] = $labelSelector;
        }
        return $this->collect('images', 'images', $query);
    }

    /** Delete an image (snapshot). */
    public function deleteImage($imageId)
    {
        return $this->request('DELETE', '/images/' . (int) $imageId);
    }

    public function enableBackup($id)  { return $this->serverAction($id, 'enable_backup'); }
    public function disableBackup($id) { return $this->serverAction($id, 'disable_backup'); }

    /** Set reverse DNS for one of the server's IPs. */
    public function changeReverseDns($id, $ip, $dnsPtr)
    {
        return $this->serverAction($id, 'change_dns_ptr', [
            'ip'      => $ip,
            'dns_ptr' => $dnsPtr ?: null,
        ]);
    }

    /**
     * Time-series metrics for the client-area graphs.
     *
     * @param string $type  cpu|disk|network (comma separated allowed)
     * @param int    $start unix timestamp
     * @param int    $end   unix timestamp
     */
    public function serverMetrics($id, $type, $start, $end)
    {
        return $this->request('GET', '/servers/' . (int) $id . '/metrics', [
            'type'  => $type,
            'start' => gmdate('Y-m-d\TH:i:s\Z', $start),
            'end'   => gmdate('Y-m-d\TH:i:s\Z', $end),
        ]);
    }

    // ---------------------------------------------------------------------
    // Floating IPs (used for customer-selected "Extra IPs")
    // ---------------------------------------------------------------------

    /**
     * Create a Floating IP and (optionally) assign it to a server.
     *
     * @param string   $type       ipv4|ipv6
     * @param int|null $serverId    assign to this server on creation
     * @param string   $location    required if no server given
     * @param array    $labels      e.g. ['whmcs_service' => '123']
     * @return array {floating_ip, action}
     */
    public function createFloatingIp($type = 'ipv4', $serverId = null, $location = null, $description = '', array $labels = [])
    {
        $data = ['type' => $type];
        if ($serverId) {
            $data['server'] = (int) $serverId;
        } elseif ($location) {
            $data['home_location'] = $location;
        }
        if ($description !== '') {
            $data['description'] = $description;
        }
        if ($labels) {
            $data['labels'] = $labels;
        }
        return $this->request('POST', '/floating_ips', $data);
    }

    public function assignFloatingIp($floatingIpId, $serverId)
    {
        return $this->request('POST', '/floating_ips/' . (int) $floatingIpId . '/actions/assign', [
            'server' => (int) $serverId,
        ]);
    }

    public function deleteFloatingIp($floatingIpId)
    {
        return $this->request('DELETE', '/floating_ips/' . (int) $floatingIpId);
    }

    /** Set reverse DNS on a Floating IP (separate endpoint from server IPs). */
    public function changeFloatingIpReverseDns($floatingIpId, $ip, $dnsPtr)
    {
        return $this->request('POST', '/floating_ips/' . (int) $floatingIpId . '/actions/change_dns_ptr', [
            'ip'      => $ip,
            'dns_ptr' => $dnsPtr ?: null,
        ]);
    }

    /** List Floating IPs, optionally filtered by a label selector. */
    public function floatingIps($labelSelector = null)
    {
        $query = [];
        if ($labelSelector) {
            $query['label_selector'] = $labelSelector;
        }
        return $this->collect('floating_ips', 'floating_ips', $query);
    }

    // ---------------------------------------------------------------------
    // Storage Boxes (separate product line, same token/auth)
    // ---------------------------------------------------------------------

    public function storageBoxTypes()
    {
        return $this->collect('storage_box_types', 'storage_box_types');
    }

    public function storageBoxes()
    {
        return $this->collect('storage_boxes', 'storage_boxes');
    }

    public function getStorageBox($id)
    {
        $res = $this->request('GET', '/storage_boxes/' . (int) $id);
        return isset($res['storage_box']) ? $res['storage_box'] : null;
    }

    public function createStorageBox(array $data)
    {
        return $this->request('POST', '/storage_boxes', $data);
    }

    public function deleteStorageBox($id)
    {
        return $this->request('DELETE', '/storage_boxes/' . (int) $id);
    }

    public function updateStorageBoxAccess($id, array $data)
    {
        return $this->request('POST', '/storage_boxes/' . (int) $id . '/actions/update_access_settings', $data);
    }

    public function resetStorageBoxPassword($id, $password)
    {
        return $this->request('POST', '/storage_boxes/' . (int) $id . '/actions/reset_password', [
            'password' => $password,
        ]);
    }

    // ---------------------------------------------------------------------
    // Actions polling
    // ---------------------------------------------------------------------

    /** Poll an action id until it is no longer "running" or the timeout elapses. */
    public function waitForAction($actionId, $maxSeconds = 60)
    {
        $deadline = time() + $maxSeconds;
        do {
            $res = $this->request('GET', '/actions/' . (int) $actionId);
            $action = isset($res['action']) ? $res['action'] : [];
            $status = isset($action['status']) ? $action['status'] : 'running';
            if ($status !== 'running') {
                return $action;
            }
            // Deliberately cheap poll; Hetzner actions are usually sub-second.
            usleep(750000);
        } while (time() < $deadline);

        return ['status' => 'running', 'timeout' => true];
    }

    // ---------------------------------------------------------------------
    // Connectivity check
    // ---------------------------------------------------------------------

    /**
     * Validate the token. Returns [success=>bool, error=>string].
     */
    public function testConnection()
    {
        try {
            $this->request('GET', '/server_types', ['per_page' => 1]);
            return ['success' => true, 'error' => ''];
        } catch (ApiException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------------
    // HTTP plumbing
    // ---------------------------------------------------------------------

    /**
     * Walk every page of a list endpoint and merge the results.
     */
    private function collect($path, $key, array $query = [])
    {
        $items = [];
        $page = 1;
        $query['per_page'] = isset($query['per_page']) ? $query['per_page'] : 50;

        do {
            $query['page'] = $page;
            $res = $this->request('GET', '/' . ltrim($path, '/'), $query);
            if (!empty($res[$key]) && is_array($res[$key])) {
                foreach ($res[$key] as $row) {
                    $items[] = $row;
                }
            }
            $next = isset($res['meta']['pagination']['next_page'])
                ? $res['meta']['pagination']['next_page']
                : null;
            $page = $next;
        } while ($next);

        return $items;
    }

    /**
     * Perform a single HTTP request and decode JSON.
     *
     * @throws ApiException on transport error or non-2xx response.
     */
    public function request($method, $path, array $data = [])
    {
        if ($this->token === '') {
            throw new ApiException('Hetzner API token is not configured.');
        }

        $url = self::BASE_URL . $path;
        $method = strtoupper($method);
        $body = null;

        if ($method === 'GET') {
            if ($data) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
            }
        } elseif ($data) {
            $body = json_encode($data);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => array_filter([
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'Accept: application/json',
                $body !== null ? 'Content-Length: ' . strlen($body) : null,
            ]),
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $this->log($method . ' ' . $path, $data, ['http' => $httpCode, 'body' => $raw, 'curl' => $curlErr]);

        if ($raw === false) {
            throw new ApiException('Connection to Hetzner API failed: ' . $curlErr);
        }

        // 204 No Content (e.g. delete/some actions) => empty success.
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        if ($raw !== '' && $decoded === null) {
            throw new ApiException('Invalid JSON from Hetzner API (HTTP ' . $httpCode . ').', $httpCode);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = isset($decoded['error']['message'])
                ? $decoded['error']['message']
                : 'Hetzner API returned HTTP ' . $httpCode;
            $errCode = isset($decoded['error']['code']) ? $decoded['error']['code'] : $httpCode;
            throw new ApiException($msg . ' (' . $errCode . ')', $httpCode, $decoded);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function log($action, $request, $response)
    {
        if ($this->logger) {
            // Mask the bearer token if it ever leaks into logged structures.
            call_user_func($this->logger, $action, $request, $response);
        }
    }
}
