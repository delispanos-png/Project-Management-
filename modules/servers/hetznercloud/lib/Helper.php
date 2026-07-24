<?php
/**
 * Shared helpers for the Hetzner Cloud WHMCS modules: token resolution,
 * remote-id persistence, white-label branding and config-option mapping.
 *
 * @package WHMCS\Module\Server\HetznerCloud
 */

namespace WHMCS\Module\Server\HetznerCloud;

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class Helper
{
    /** Custom-field / hosting-field keys we use to persist Hetzner references. */
    const FIELD_SERVER_ID = 'hetzner_server_id';   // custom field (per product)
    const FIELD_STORAGE_ID = 'hetzner_storagebox_id';

    /**
     * The client must never see "Hetzner". Resolve the white-label brand name
     * from the product config option, then the addon setting, then a default.
     */
    public static function brand(array $params)
    {
        if (!empty($params['configoption9'])) {
            return $params['configoption9'];
        }
        $setting = self::addonSetting('brand_name');
        return $setting !== null && $setting !== '' ? $setting : 'Cloud Server';
    }

    /**
     * Resolve the API token for a service. Priority:
     *   1. WHMCS Server "Access Hash" (per-project token) — the standard slot.
     *   2. WHMCS Server "Password" field.
     *   3. Global token from the addon settings (single-project setups).
     */
    public static function token(array $params)
    {
        if (!empty($params['serveraccesshash'])) {
            return trim($params['serveraccesshash']);
        }
        if (!empty($params['serverpassword'])) {
            return trim($params['serverpassword']);
        }
        // Multi-project: use the token of the project THIS service's VM lives in.
        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId > 0) {
            $t = self::projectTokenForService($serviceId);
            if ($t !== '') {
                return $t;
            }
        }
        return self::globalToken();
    }

    /**
     * Resolve the API token for a service from its Hetzner project mapping
     * (mod_hetzner_instances → mod_hetzner_projects). Falls back to the primary
     * project. Returns '' if the projects layer isn't available, so the caller
     * drops back to the legacy global token — existing single-project services
     * keep working unchanged.
     */
    public static function projectTokenForService($serviceId)
    {
        try {
            require_once __DIR__ . '/../../../addons/hetznercloud/lib/Db.php';
            $proj = \WHMCS\Module\Addon\HetznerCloud\Db::projectForService((int) $serviceId);
            if ($proj && !empty($proj->api_token)) {
                return Api::normalizeToken($proj->api_token);
            }
        } catch (\Throwable $e) {
            // fall through to global token
        }
        return '';
    }

    /** Token for a specific project id (used when provisioning into a target project). */
    public static function projectToken($projectId)
    {
        try {
            require_once __DIR__ . '/../../../addons/hetznercloud/lib/Db.php';
            $proj = \WHMCS\Module\Addon\HetznerCloud\Db::project((int) $projectId);
            if ($proj && !empty($proj->api_token)) {
                return Api::normalizeToken($proj->api_token);
            }
        } catch (\Throwable $e) {
        }
        return '';
    }

    /**
     * Which project a NEW order should provision into: the product's per-product
     * override (mod_hetzner_map.project_id) if set & enabled, else the primary
     * project. Returns 0 if the projects layer isn't available.
     */
    public static function targetProjectForCreate(array $params)
    {
        try {
            require_once __DIR__ . '/../../../addons/hetznercloud/lib/Db.php';
            $db = 'WHMCS\\Module\\Addon\\HetznerCloud\\Db';
            $pid = (int) ($params['pid'] ?? 0); // WHMCS product id
            if ($pid) {
                $map = $db::mappingForProduct($pid);
                if ($map && !empty($map->project_id)) {
                    $proj = $db::project((int) $map->project_id);
                    if ($proj && $proj->enabled) {
                        return (int) $proj->id;
                    }
                }
            }
            $prim = $db::primaryProject();
            return $prim ? (int) $prim->id : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** The project id a service is currently pinned to (0 if none). */
    public static function instanceProjectId($serviceId)
    {
        try {
            require_once __DIR__ . '/../../../addons/hetznercloud/lib/Db.php';
            $inst = \WHMCS\Module\Addon\HetznerCloud\Db::instanceForService((int) $serviceId);
            return $inst ? (int) $inst->project_id : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Persist / update the service → project → Hetzner-server-id mapping. */
    public static function recordInstance($serviceId, $projectId, $serverId = null)
    {
        if ((int) $projectId <= 0) {
            return;
        }
        try {
            require_once __DIR__ . '/../../../addons/hetznercloud/lib/Db.php';
            \WHMCS\Module\Addon\HetznerCloud\Db::saveInstance((int) $serviceId, (int) $projectId, $serverId);
        } catch (\Throwable $e) {
        }
    }

    /**
     * The global API token stored by the addon module. Handles both plaintext
     * and WHMCS-encrypted storage via Api::normalizeToken().
     */
    public static function globalToken()
    {
        return Api::normalizeToken(self::addonSetting('api_token'));
    }

    /**
     * Build a configured API client for a set of module params, wiring the
     * WHMCS module-call logger in automatically.
     */
    public static function api(array $params)
    {
        $api = new Api(self::token($params));
        if (function_exists('logModuleCall')) {
            $api->setLogger(function ($action, $request, $response) {
                // Never let the bearer token reach the activity log.
                logModuleCall(
                    'hetznercloud',
                    $action,
                    self::scrub($request),
                    self::scrub($response),
                    '',
                    ['serveraccesshash', 'serverpassword', 'password', 'token']
                );
            });
        }
        return $api;
    }

    /** Recursively strip obvious secrets before logging. */
    private static function scrub($data)
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                if (in_array(strtolower((string) $k), ['token', 'password', 'root_password'], true)) {
                    $out[$k] = '***';
                } else {
                    $out[$k] = self::scrub($v);
                }
            }
            return $out;
        }
        return $data;
    }

    // ---------------------------------------------------------------------
    // Remote-id persistence
    // ---------------------------------------------------------------------

    /**
     * Persist the Hetzner server id against the WHMCS service. Preference:
     * dedicated `hetzner_server_id` custom field → the project's `vpsid` custom
     * field (existing Cloudon convention) → hosting "username" fallback.
     */
    public static function saveServerId(array $params, $serverId)
    {
        $serviceId = (int) $params['serviceid'];
        $wroteField = self::saveCustomField($params, self::FIELD_SERVER_ID, $serverId);
        // Always mirror to vpsid when the field exists (admin UI shows it there).
        self::saveCustomField($params, 'vpsid', $serverId);
        if (!$wroteField && !self::customFieldId((int) $params['pid'], 'vpsid')) {
            Capsule::table('tblhosting')->where('id', $serviceId)
                ->update(['username' => 'hz-' . $serverId]);
        }
    }

    /** Read the stored Hetzner server id for a service. */
    public static function serverId(array $params)
    {
        foreach ([self::FIELD_SERVER_ID, 'vpsid'] as $field) {
            $v = self::readCustomField($params, $field);
            if ($v !== null && $v !== '' && (int) $v > 0) {
                return (int) $v;
            }
        }
        if (!empty($params['username']) && strpos($params['username'], 'hz-') === 0) {
            return (int) substr($params['username'], 3);
        }
        return 0;
    }

    public static function saveIpAndPassword(array $params, $ip, $rootPassword = null)
    {
        $serviceId = (int) $params['serviceid'];
        $update = [];
        if ($ip !== null) {
            $update['dedicatedip'] = $ip;
        }
        if ($rootPassword !== null && function_exists('encrypt')) {
            $update['password'] = encrypt($rootPassword);
        }
        if ($update) {
            Capsule::table('tblhosting')->where('id', $serviceId)->update($update);
        }
    }

    // ---------------------------------------------------------------------
    // Customer Configurable Options (Location / OS / Extra IPs / Backups)
    // ---------------------------------------------------------------------

    /** The customer's selected configurable options as [name => value]. */
    public static function options(array $params)
    {
        return (!empty($params['configoptions']) && is_array($params['configoptions']))
            ? $params['configoptions'] : [];
    }

    /** First configurable option whose name contains any of the needles. */
    public static function optionValue(array $params, array $needles)
    {
        foreach (self::options($params) as $name => $val) {
            foreach ($needles as $n) {
                if (stripos($name, $n) !== false) {
                    return $val;
                }
            }
        }
        return null;
    }

    /** How many Extra IPs the customer ordered (quantity option). */
    public static function extraIpsCount(array $params)
    {
        $v = self::optionValue($params, ['extra ip', 'extraip', 'additional ip', 'extra ips']);
        return $v === null ? 0 : max(0, (int) $v);
    }

    /** Whether the customer opted into backups (null = not offered as an option). */
    public static function wantsBackups(array $params)
    {
        $v = self::optionValue($params, ['backup']);
        if ($v === null) {
            return null; // option not offered → caller uses the product default
        }
        $v = strtolower(trim((string) $v));
        if ($v === '') {
            return false;
        }
        // Explicit negatives.
        if (in_array($v, ['no', 'off', '0', 'disable', 'disabled', 'false', 'none'], true)
            || strpos($v, 'without') !== false || strpos($v, 'no backup') !== false) {
            return false;
        }
        // A WHMCS Yes/No option passes its (non-empty) label text when enabled,
        // e.g. "Enabling Backups for your server will cost 20%…" → treat as yes.
        return true;
    }

    /**
     * Map a customer-facing location value (e.g. "Nuremberg") to a Hetzner
     * location slug (e.g. "nbg1"). Falls back to $default.
     */
    public static function mapLocation($value, Api $api, $default = null)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $default;
        }
        $needle = strtolower($value);
        $alias = [
            'nuremberg' => 'nbg1', 'nurnberg' => 'nbg1', 'nürnberg' => 'nbg1',
            'falkenstein' => 'fsn1', 'helsinki' => 'hel1',
            'ashburn' => 'ash', 'hillsboro' => 'hil', 'singapore' => 'sin',
        ];
        foreach ($alias as $k => $v) {
            if (strpos($needle, $k) !== false) {
                return $v;
            }
        }
        try {
            foreach ($api->locations() as $l) {
                if (strcasecmp($value, $l['name']) === 0) {
                    return $l['name'];
                }
                $city = strtolower($l['city'] ?? '');
                if ($city !== '' && strpos($needle, $city) !== false) {
                    return $l['name'];
                }
            }
        } catch (\Exception $e) {
            // fall through
        }
        return $default;
    }

    /**
     * Map a customer-facing OS value (e.g. "AlmaLinux 9") to a Hetzner image
     * name (e.g. "alma-9"). Windows is NOT available on Hetzner Cloud.
     */
    public static function mapImage($value, Api $api, $default = 'ubuntu-22.04')
    {
        // Collapse the double spaces seen in some labels (e.g. "CentOS  Stream 9").
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));
        if ($value === '') {
            return $default;
        }
        $low = strtolower($value);

        // Already a Hetzner-style slug (e.g. "alma-9", "ubuntu-24.04").
        if (strpos($low, ' ') === false && preg_match('/^[a-z0-9\-\.]+$/', $low)) {
            return $low;
        }

        // Generic distro + version → Hetzner slug.
        $slug = self::guessImageSlug($low);
        if ($slug) {
            // Prefer the exact slug the API confirms; otherwise trust the guess.
            try {
                foreach ($api->images('system') as $img) {
                    if (strcasecmp($slug, $img['name'] ?? '') === 0) {
                        return $img['name'];
                    }
                }
            } catch (\Exception $e) {
                // API unreachable — return the best-guess slug.
            }
            return $slug;
        }

        // Last resort: match the API image description (whitespace-normalised).
        try {
            foreach ($api->images('system') as $img) {
                $desc = strtolower(trim(preg_replace('/\s+/', ' ', $img['description'] ?? '')));
                if ($desc === $low || strcasecmp($value, $img['name'] ?? '') === 0) {
                    return $img['name'];
                }
            }
        } catch (\Exception $e) {
            // fall through
        }
        return $default;
    }

    /** Turn a human OS label into a Hetzner image slug, or null if unknown. */
    private static function guessImageSlug($low)
    {
        if (strpos($low, 'windows') !== false) {
            return null; // Hetzner Cloud has no Windows images
        }
        if (preg_match('/ubuntu\s*([0-9]{2}\.[0-9]{2})/', $low, $m)) {
            return 'ubuntu-' . $m[1];
        }
        if (preg_match('/debian\s*([0-9]+)/', $low, $m)) {
            return 'debian-' . $m[1];
        }
        if (preg_match('/fedora\s*([0-9]+)/', $low, $m)) {
            return 'fedora-' . $m[1];
        }
        if (preg_match('/(?:almalinux|alma)\s*([0-9]+)/', $low, $m)) {
            return 'alma-' . $m[1];
        }
        if (preg_match('/rocky(?:\s*linux)?\s*([0-9]+)/', $low, $m)) {
            return 'rocky-' . $m[1];
        }
        if (preg_match('/centos\s*stream\s*([0-9]+)/', $low, $m)) {
            return 'centos-stream-' . $m[1];
        }
        if (preg_match('/centos\s*([0-9]+)/', $low, $m)) {
            return 'centos-' . $m[1];
        }
        return null;
    }

    /**
     * Build a #cloud-config that configures customer Floating IPs inside the
     * server at first boot (adds each as /32 on the default interface and makes
     * it persistent via a oneshot systemd unit). Works across systemd distros.
     */
    public static function floatingIpCloudInit(array $ips)
    {
        $ips = array_values(array_filter(array_map('trim', $ips)));
        if (!$ips) {
            return '';
        }
        // Validate to keep the YAML safe.
        $ips = array_filter($ips, function ($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP) !== false;
        });
        if (!$ips) {
            return '';
        }
        $ipList = implode(' ', $ips);

        return "#cloud-config\n"
            . "write_files:\n"
            . "  - path: /usr/local/sbin/hetzner-floating-ips.sh\n"
            . "    permissions: '0755'\n"
            . "    content: |\n"
            . "      #!/bin/bash\n"
            . "      IFACE=\$(ip route show default 2>/dev/null | awk '/default/ {print \$5; exit}')\n"
            . "      [ -z \"\$IFACE\" ] && IFACE=eth0\n"
            . "      for ip in " . $ipList . "; do\n"
            . "        ip addr add \${ip}/32 dev \$IFACE 2>/dev/null\n"
            . "      done\n"
            . "  - path: /etc/systemd/system/hetzner-floating-ips.service\n"
            . "    permissions: '0644'\n"
            . "    content: |\n"
            . "      [Unit]\n"
            . "      Description=Configure Hetzner Floating IPs\n"
            . "      After=network-online.target\n"
            . "      Wants=network-online.target\n"
            . "      [Service]\n"
            . "      Type=oneshot\n"
            . "      ExecStart=/usr/local/sbin/hetzner-floating-ips.sh\n"
            . "      RemainAfterExit=yes\n"
            . "      [Install]\n"
            . "      WantedBy=multi-user.target\n"
            . "runcmd:\n"
            . "  - systemctl daemon-reload\n"
            . "  - systemctl enable --now hetzner-floating-ips.service\n";
    }

    /**
     * Write the delivery fields onto the WHMCS service after provisioning:
     * root username/password, primary IP, extra IPs and the vpsid custom field.
     */
    public static function saveDelivery(array $params, array $server, $rootPassword = null, array $extraIps = [])
    {
        $serviceId = (int) $params['serviceid'];
        $productId = (int) $params['pid'];
        $update = [];

        // Only claim the username column for "root" if the id lives in a custom field.
        $idInField = self::customFieldId($productId, self::FIELD_SERVER_ID) || self::customFieldId($productId, 'vpsid');
        if ($idInField) {
            $update['username'] = 'root';
        }

        $ip = $server['public_net']['ipv4']['ip'] ?? null;
        if ($ip) {
            $update['dedicatedip'] = $ip;
        }
        if ($rootPassword !== null && function_exists('encrypt')) {
            $update['password'] = encrypt($rootPassword);
        }
        if ($extraIps) {
            $update['assignedips'] = implode("\n", $extraIps);
        }
        if ($update) {
            Capsule::table('tblhosting')->where('id', $serviceId)->update($update);
        }
        if (isset($server['id'])) {
            self::saveCustomField($params, 'vpsid', $server['id']);
        }
    }

    // ---------------------------------------------------------------------
    // Custom field helpers
    // ---------------------------------------------------------------------

    private static function customFieldId($productId, $fieldName)
    {
        $row = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', (int) $productId)
            ->where('fieldname', $fieldName)
            ->first();
        return $row ? (int) $row->id : 0;
    }

    public static function saveCustomField(array $params, $fieldName, $value)
    {
        $productId = (int) $params['pid'];
        $serviceId = (int) $params['serviceid'];
        $fieldId = self::customFieldId($productId, $fieldName);
        if (!$fieldId) {
            return false;
        }
        $exists = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $fieldId)->where('relid', $serviceId)->exists();
        if ($exists) {
            Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $fieldId)->where('relid', $serviceId)
                ->update(['value' => (string) $value]);
        } else {
            Capsule::table('tblcustomfieldsvalues')->insert([
                'fieldid' => $fieldId,
                'relid'   => $serviceId,
                'value'   => (string) $value,
            ]);
        }
        return true;
    }

    public static function readCustomField(array $params, $fieldName)
    {
        // First honour anything WHMCS already injected into $params.
        if (!empty($params['customfields']) && is_array($params['customfields'])) {
            foreach ($params['customfields'] as $k => $v) {
                if (strcasecmp($k, $fieldName) === 0) {
                    return $v;
                }
            }
        }
        $productId = (int) $params['pid'];
        $serviceId = (int) $params['serviceid'];
        $fieldId = self::customFieldId($productId, $fieldName);
        if (!$fieldId) {
            return null;
        }
        $row = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $fieldId)->where('relid', $serviceId)->first();
        return $row ? $row->value : null;
    }

    // ---------------------------------------------------------------------
    // Addon settings bridge
    // ---------------------------------------------------------------------

    /** Read a setting saved by the hetznercloud addon module. */
    public static function addonSetting($setting)
    {
        try {
            $row = Capsule::table('tbladdonmodules')
                ->where('module', 'hetznercloud')
                ->where('setting', $setting)
                ->first();
            return $row ? $row->value : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * A deterministic, non-identifying server name for the Hetzner side.
     * Only admins ever see this; clients never do.
     */
    public static function remoteServerName(array $params)
    {
        return 'whmcs-' . (int) $params['serviceid'];
    }

    /**
     * Delete a service's Hetzner resources: Extra IPs (unassigned first so the
     * delete is accepted) then the server. Shared by TerminateAccount and the
     * status-change hook so both behave identically.
     */
    public static function deleteHetznerResources(Api $api, $serverId, $serviceId)
    {
        // Extra IPs first — unassign (Hetzner refuses to delete an assigned IP),
        // wait for the action, then delete. Done before the server so there is
        // no async race with the server's own unassignment.
        try {
            foreach ($api->floatingIps('whmcs_service=' . (int) $serviceId) as $fip) {
                try {
                    if (!empty($fip['server'])) {
                        $act = $api->request('POST', '/floating_ips/' . (int) $fip['id'] . '/actions/unassign');
                        if (!empty($act['action']['id'])) {
                            $api->waitForAction($act['action']['id'], 30);
                        }
                    }
                    $api->deleteFloatingIp($fip['id']);
                } catch (ApiException $e) {
                    // best-effort per IP
                }
            }
        } catch (ApiException $e) {
            // non-fatal
        }

        if ((int) $serverId > 0) {
            try {
                $api->deleteServer((int) $serverId);
            } catch (ApiException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Keep availability-driven stock "permanently N": WHMCS decrements product
     * stock on each sale, so after a successful provision we restore the product
     * quantity to the configured available level. No-op if stock sync is off.
     */
    public static function resetStock(array $params)
    {
        if (self::addonSetting('sync_stock') === 'off') {
            return; // explicitly disabled
        }
        $qty = (int) self::addonSetting('available_stock');
        if ($qty < 1) {
            $qty = 1;
        }
        try {
            Capsule::table('tblproducts')->where('id', (int) $params['pid'])->update([
                'stockcontrol' => 1, // tinyint: 1 = enabled
                'qty'          => $qty,
            ]);
        } catch (\Exception $e) {
            // stock is best-effort; never fail provisioning over it
        }
    }
}
