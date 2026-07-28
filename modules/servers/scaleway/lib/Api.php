<?php
/**
 * Scaleway API client (Instance v1 + Marketplace v2 + IAM v1alpha1).
 *
 * Thin, dependency-free wrapper. One instance is bound to a single
 * (secret key, project id, zone) triple — the Scaleway equivalent of a
 * Hetzner "project token".
 *
 * Docs: https://www.scaleway.com/en/developers/api/instance/
 *
 * Βασικές διαφορές από Hetzner (τις χειρίζεται αυτή η κλάση):
 *  • auth header: X-Auth-Token (όχι Bearer)
 *  • κάθε κλήση Instance API είναι ΑΝΑ ZONE (/instance/v1/zones/{zone}/…)
 *  • απαιτείται project_id σε create/list
 *  • τα instances δημιουργούνται ΣΒΗΣΤΑ → χρειάζεται action poweron
 *  • δεν επιστρέφεται root password → μπαίνει μέσω cloud-init
 *
 * @package WHMCS\Module\Server\Scaleway
 */

namespace WHMCS\Module\Server\Scaleway;

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
    const BASE_URL = 'https://api.scaleway.com';

    /** Διαθέσιμες zones (product availability διαφέρει ανά zone). */
    const ZONES = [
        'fr-par-1' => 'Paris 1, France',
        'fr-par-2' => 'Paris 2, France',
        'fr-par-3' => 'Paris 3, France',
        'nl-ams-1' => 'Amsterdam 1, Netherlands',
        'nl-ams-2' => 'Amsterdam 2, Netherlands',
        'nl-ams-3' => 'Amsterdam 3, Netherlands',
        'pl-waw-1' => 'Warsaw 1, Poland',
        'pl-waw-2' => 'Warsaw 2, Poland',
        'pl-waw-3' => 'Warsaw 3, Poland',
        'it-mil-1' => 'Milan 1, Italy',
    ];

    /** @var string Secret key (X-Auth-Token). */
    private $secretKey;

    /** @var string Project UUID. */
    private $projectId;

    /** @var string Default zone. */
    private $zone;

    /** @var int */
    private $timeout;

    /** @var callable|null function(string $action, mixed $request, mixed $response) */
    private $logger;

    public function __construct($secretKey, $projectId = '', $zone = 'fr-par-1', $timeout = 60)
    {
        $this->secretKey = trim((string) $secretKey);
        $this->projectId = trim((string) $projectId);
        $this->zone = self::normalizeZone($zone);
        $this->timeout = (int) $timeout;
    }

    public function projectId()
    {
        return $this->projectId;
    }

    public function zone()
    {
        return $this->zone;
    }

    /** Επιστρέφει νέο client δεμένο σε άλλη zone (ίδια credentials). */
    public function forZone($zone)
    {
        return new self($this->secretKey, $this->projectId, $zone, $this->timeout);
    }

    public static function normalizeZone($zone)
    {
        $zone = strtolower(trim((string) $zone));
        return isset(self::ZONES[$zone]) ? $zone : 'fr-par-1';
    }

    /**
     * Normalise a stored secret key that may be plaintext or WHMCS-encrypted.
     *
     * Το WHMCS αποθηκεύει τα "password" settings άλλοτε plaintext, άλλοτε
     * κρυπτογραφημένα. Το Scaleway secret key είναι UUID v4, οπότε μπορούμε να
     * ξεχωρίσουμε: αν η αποκρυπτογράφηση δώσει UUID, η πηγή ήταν encrypted.
     */
    public static function normalizeSecret($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        $isUuid = function ($s) {
            return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim((string) $s));
        };
        if ($isUuid($raw)) {
            return $raw;
        }
        if (function_exists('localAPI')) {
            try {
                $dec = localAPI('DecryptPassword', ['password2' => $raw]);
                if (!empty($dec['password']) && $isUuid($dec['password'])) {
                    return trim($dec['password']);
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }
        return $raw;
    }

    public function setLogger(callable $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    private function log($action, $request, $response)
    {
        if ($this->logger) {
            call_user_func($this->logger, $action, $this->scrub($request), $response);
        }
    }

    private function scrub($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        foreach (['password', 'root_password', 'secret_key', 'user_data'] as $k) {
            if (isset($data[$k])) {
                $data[$k] = '***';
            }
        }
        return $data;
    }

    /* ─────────────────────────── HTTP ─────────────────────────── */

    /**
     * Raw request. $path is absolute-from-host (π.χ. /instance/v1/zones/fr-par-1/servers).
     *
     * @param string $method GET|POST|PUT|PATCH|DELETE
     * @param string $path
     * @param array  $data   query (GET) ή JSON body
     * @param array  $opt    ['raw_body' => string, 'content_type' => string]
     * @return array Decoded body ([] για 204)
     * @throws ApiException
     */
    public function request($method, $path, array $data = [], array $opt = [])
    {
        if ($this->secretKey === '') {
            throw new ApiException('Το Scaleway secret key δεν έχει ρυθμιστεί.');
        }

        $url = self::BASE_URL . $path;
        $method = strtoupper($method);
        $body = null;
        $contentType = $opt['content_type'] ?? 'application/json';

        if (array_key_exists('raw_body', $opt)) {
            $body = (string) $opt['raw_body'];
        } elseif ($method === 'GET' || $method === 'DELETE') {
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
                'X-Auth-Token: ' . $this->secretKey,
                'Content-Type: ' . $contentType,
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
            throw new ApiException('Αποτυχία σύνδεσης με το Scaleway API: ' . $curlErr);
        }

        $decoded = $raw === '' ? [] : json_decode($raw, true);
        if ($raw !== '' && $decoded === null) {
            throw new ApiException('Μη έγκυρο JSON από το Scaleway API (HTTP ' . $httpCode . ').', $httpCode);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new ApiException(self::errorMessage($decoded, $httpCode), $httpCode, is_array($decoded) ? $decoded : []);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** Το Scaleway επιστρέφει σφάλματα σε αρκετά διαφορετικά σχήματα. */
    private static function errorMessage($decoded, $httpCode)
    {
        if (is_array($decoded)) {
            if (!empty($decoded['message'])) {
                $msg = $decoded['message'];
                if (!empty($decoded['fields']) && is_array($decoded['fields'])) {
                    $bits = [];
                    foreach ($decoded['fields'] as $f => $errs) {
                        $bits[] = $f . ': ' . (is_array($errs) ? implode(', ', $errs) : $errs);
                    }
                    if ($bits) {
                        $msg .= ' (' . implode(' · ', $bits) . ')';
                    }
                }
                return $msg;
            }
            foreach (['error_message', 'error', 'detail', 'type'] as $k) {
                if (!empty($decoded[$k]) && is_string($decoded[$k])) {
                    return $decoded[$k];
                }
            }
        }
        return 'Το Scaleway API επέστρεψε HTTP ' . $httpCode;
    }

    /** Instance API path helper (per-zone). */
    private function zpath($suffix, $zone = null)
    {
        return '/instance/v1/zones/' . self::normalizeZone($zone ?: $this->zone) . $suffix;
    }

    /* ─────────────────────── Catalogue / discovery ─────────────────────── */

    public function testConnection()
    {
        try {
            $this->serverTypes();
            if ($this->projectId === '') {
                return ['success' => false, 'error' => 'Λείπει το Project ID.'];
            }
            return ['success' => true, 'error' => ''];
        } catch (ApiException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Διαθέσιμοι τύποι instance της zone.
     * @return array name => ['ncpus','ram','volumes_constraint','hourly_price','arch','baremetal']
     */
    public function serverTypes($zone = null)
    {
        $res = $this->request('GET', $this->zpath('/products/servers', $zone), ['per_page' => 100]);
        return $res['servers'] ?? [];
    }

    /** Διαθεσιμότητα ανά τύπο (available / scarce / shortage). */
    public function serverAvailability($zone = null)
    {
        try {
            $res = $this->request('GET', $this->zpath('/products/servers/availability', $zone));
            return $res['servers'] ?? [];
        } catch (ApiException $e) {
            return [];
        }
    }

    /**
     * Δημόσιες εικόνες από το Marketplace (labels όπως "ubuntu_jammy").
     * @return array label => description
     */
    public function marketplaceImages()
    {
        $out = [];
        $page = 1;
        do {
            $res = $this->request('GET', '/marketplace/v2/images', ['page' => $page, 'page_size' => 100]);
            $items = $res['images'] ?? [];
            foreach ($items as $img) {
                if (empty($img['label'])) {
                    continue;
                }
                $out[$img['label']] = $img['name'] ?: $img['label'];
            }
            $page++;
            $total = (int) ($res['total_count'] ?? 0);
        } while ($items && count($out) < $total && $page <= 10);
        return $out;
    }

    /** Εικόνες του project (snapshots/custom) της zone. */
    public function images($zone = null)
    {
        $res = $this->request('GET', $this->zpath('/images', $zone), [
            'per_page' => 100, 'project' => $this->projectId,
        ]);
        return $res['images'] ?? [];
    }

    /** SSH keys του project (IAM). */
    public function sshKeys()
    {
        try {
            $res = $this->request('GET', '/iam/v1alpha1/ssh-keys', [
                'project_id' => $this->projectId, 'page_size' => 100,
            ]);
            return $res['ssh_keys'] ?? [];
        } catch (ApiException $e) {
            return [];
        }
    }

    /* ─────────────────────────── Servers ─────────────────────────── */

    public function getServer($id, $zone = null)
    {
        try {
            $res = $this->request('GET', $this->zpath('/servers/' . rawurlencode($id), $zone));
            return $res['server'] ?? null;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /** Αναζήτηση instance με ακριβές όνομα μέσα στο project. */
    public function findServerByName($name, $zone = null)
    {
        $res = $this->request('GET', $this->zpath('/servers', $zone), [
            'name' => $name, 'project' => $this->projectId, 'per_page' => 100,
        ]);
        foreach ($res['servers'] ?? [] as $srv) {
            if (($srv['name'] ?? '') === $name) {
                return $srv;
            }
        }
        return null;
    }

    /** Όλα τα instances του project στη zone (για sync/import). */
    public function listServers($zone = null)
    {
        $out = [];
        $page = 1;
        do {
            $res = $this->request('GET', $this->zpath('/servers', $zone), [
                'project' => $this->projectId, 'per_page' => 100, 'page' => $page,
            ]);
            $items = $res['servers'] ?? [];
            $out = array_merge($out, $items);
            $page++;
        } while (count($items) === 100 && $page <= 20);
        return $out;
    }

    /**
     * Δημιουργία instance. ΠΡΟΣΟΧΗ: το Scaleway το δημιουργεί ΣΒΗΣΤΟ.
     * @return array server object
     */
    public function createServer(array $data, $zone = null)
    {
        if (empty($data['project']) && $this->projectId !== '') {
            $data['project'] = $this->projectId;
        }
        $res = $this->request('POST', $this->zpath('/servers', $zone), $data);
        return $res['server'] ?? [];
    }

    /** Ενέργεια instance: poweron|poweroff|reboot|stop_in_place|terminate|backup */
    public function serverAction($id, $action, array $extra = [], $zone = null)
    {
        $body = array_merge(['action' => $action], $extra);
        return $this->request('POST', $this->zpath('/servers/' . rawurlencode($id) . '/action', $zone), $body);
    }

    public function powerOn($id, $zone = null)    { return $this->serverAction($id, 'poweron', [], $zone); }
    public function powerOff($id, $zone = null)   { return $this->serverAction($id, 'poweroff', [], $zone); }
    public function reboot($id, $zone = null)     { return $this->serverAction($id, 'reboot', [], $zone); }
    /** Soft stop (κρατά RAM/volumes, σταματά τη χρέωση compute). */
    public function stopInPlace($id, $zone = null) { return $this->serverAction($id, 'stop_in_place', [], $zone); }
    /** Διαγράφει instance + volumes + απελευθερώνει IP. */
    public function terminate($id, $zone = null)  { return $this->serverAction($id, 'terminate', [], $zone); }

    public function updateServer($id, array $data, $zone = null)
    {
        $res = $this->request('PATCH', $this->zpath('/servers/' . rawurlencode($id), $zone), $data);
        return $res['server'] ?? [];
    }

    public function deleteServer($id, $zone = null)
    {
        return $this->request('DELETE', $this->zpath('/servers/' . rawurlencode($id), $zone));
    }

    /** Αλλαγή τύπου (resize) — το instance πρέπει να είναι σβηστό. */
    public function changeCommercialType($id, $commercialType, $zone = null)
    {
        return $this->updateServer($id, ['commercial_type' => $commercialType], $zone);
    }

    /** cloud-init user data (raw text — ΟΧΙ JSON). */
    public function setCloudInit($id, $script, $zone = null)
    {
        return $this->request('PATCH',
            $this->zpath('/servers/' . rawurlencode($id) . '/user_data/cloud-init', $zone),
            [], ['raw_body' => (string) $script, 'content_type' => 'text/plain']);
    }

    public function getCloudInit($id, $zone = null)
    {
        try {
            return $this->request('GET', $this->zpath('/servers/' . rawurlencode($id) . '/user_data/cloud-init', $zone));
        } catch (ApiException $e) {
            return [];
        }
    }

    /* ─────────────────────────── Volumes ─────────────────────────── */

    public function createVolume(array $data, $zone = null)
    {
        if (empty($data['project']) && $this->projectId !== '') {
            $data['project'] = $this->projectId;
        }
        $res = $this->request('POST', $this->zpath('/volumes', $zone), $data);
        return $res['volume'] ?? [];
    }

    public function deleteVolume($id, $zone = null)
    {
        return $this->request('DELETE', $this->zpath('/volumes/' . rawurlencode($id), $zone));
    }

    public function listVolumes($zone = null)
    {
        $res = $this->request('GET', $this->zpath('/volumes', $zone), [
            'project' => $this->projectId, 'per_page' => 100,
        ]);
        return $res['volumes'] ?? [];
    }

    /* ─────────────────────────── Flexible IPs ─────────────────────────── */

    /** @param string $type routed_ipv4|routed_ipv6 (ή nat) */
    public function createIp($serverId = null, $type = 'routed_ipv4', array $tags = [], $zone = null)
    {
        $data = ['project' => $this->projectId, 'type' => $type];
        if ($serverId) {
            $data['server'] = $serverId;
        }
        if ($tags) {
            $data['tags'] = $tags;
        }
        $res = $this->request('POST', $this->zpath('/ips', $zone), $data);
        return $res['ip'] ?? [];
    }

    public function attachIp($ipId, $serverId, $zone = null)
    {
        $res = $this->request('PATCH', $this->zpath('/ips/' . rawurlencode($ipId), $zone), ['server' => $serverId]);
        return $res['ip'] ?? [];
    }

    public function detachIp($ipId, $zone = null)
    {
        $res = $this->request('PATCH', $this->zpath('/ips/' . rawurlencode($ipId), $zone), ['server' => null]);
        return $res['ip'] ?? [];
    }

    public function deleteIp($ipId, $zone = null)
    {
        return $this->request('DELETE', $this->zpath('/ips/' . rawurlencode($ipId), $zone));
    }

    public function listIps($zone = null)
    {
        $res = $this->request('GET', $this->zpath('/ips', $zone), [
            'project' => $this->projectId, 'per_page' => 100,
        ]);
        return $res['ips'] ?? [];
    }

    /** Reverse DNS σε flexible IP. */
    public function setIpReverse($ipId, $reverse, $zone = null)
    {
        $res = $this->request('PATCH', $this->zpath('/ips/' . rawurlencode($ipId), $zone), ['reverse' => $reverse]);
        return $res['ip'] ?? [];
    }

    /* ─────────────────────────── Snapshots / backups ─────────────────────────── */

    public function createSnapshot($volumeId, $name, array $tags = [], $zone = null)
    {
        $data = ['project' => $this->projectId, 'volume_id' => $volumeId, 'name' => $name];
        if ($tags) {
            $data['tags'] = $tags;
        }
        $res = $this->request('POST', $this->zpath('/snapshots', $zone), $data);
        return $res['snapshot'] ?? [];
    }

    public function listSnapshots($zone = null)
    {
        $res = $this->request('GET', $this->zpath('/snapshots', $zone), [
            'project' => $this->projectId, 'per_page' => 100,
        ]);
        return $res['snapshots'] ?? [];
    }

    public function deleteSnapshot($id, $zone = null)
    {
        return $this->request('DELETE', $this->zpath('/snapshots/' . rawurlencode($id), $zone));
    }

    /** Πλήρες backup (image) του instance. */
    public function backupServer($id, $name, $zone = null)
    {
        return $this->serverAction($id, 'backup', ['name' => $name], $zone);
    }

    public function deleteImage($id, $zone = null)
    {
        return $this->request('DELETE', $this->zpath('/images/' . rawurlencode($id), $zone));
    }
}
