<?php
/**
 * Scaleway provisioning — shared plumbing.
 *
 * Αντίστοιχο του Hetzner Helper, προσαρμοσμένο στη Scaleway:
 *  • credentials = (secret key, project id, zone) αντί για ένα token
 *  • root password ΔΕΝ επιστρέφεται από το API → το παράγουμε και το περνάμε
 *    με cloud-init (chpasswd + ssh_pwauth)
 *  • multi-project routing όπως στο Hetzner (mod_scaleway_projects/_instances)
 *
 * @package WHMCS\Module\Server\Scaleway
 */

namespace WHMCS\Module\Server\Scaleway;

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class Helper
{
    const FIELD_SERVER_ID = 'scaleway_server_id';
    const FIELD_ZONE = 'scaleway_zone';
    const T_PROJECTS = 'mod_scaleway_projects';
    const T_INSTANCES = 'mod_scaleway_instances';

    /* ─────────────────────────── Branding ─────────────────────────── */

    public static function brand(array $params)
    {
        if (!empty($params['configoption9'])) {
            return $params['configoption9'];
        }
        $setting = self::addonSetting('brand_name');
        return $setting !== null && $setting !== '' ? $setting : 'Cloud Server';
    }

    /* ─────────────────────────── Credentials ─────────────────────────── */

    /**
     * Επιστρέφει τα credentials για ένα service.
     * Προτεραιότητα:
     *   1. WHMCS Server: Access Hash = secret key, Username = project id
     *   2. Το project στο οποίο είναι καρφωμένο το service (multi-project)
     *   3. Καθολικά credentials από το addon
     *
     * @return array ['secret' => string, 'project' => string, 'zone' => string]
     */
    public static function credentials(array $params)
    {
        // 1. Ρητά ορισμένα στον WHMCS server
        if (!empty($params['serveraccesshash'])) {
            return [
                'secret'  => Api::normalizeSecret($params['serveraccesshash']),
                'project' => trim((string) ($params['serverusername'] ?? '')) ?: self::addonSetting('project_id'),
                'zone'    => self::zoneFor($params),
            ];
        }

        // 2. Multi-project: το project όπου ζει το VM αυτού του service
        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId > 0) {
            $pid = self::instanceProjectId($serviceId);
            if ($pid) {
                $creds = self::projectCredentials($pid);
                if ($creds && $creds['secret'] !== '') {
                    $creds['zone'] = self::zoneFor($params, $creds['zone']);
                    return $creds;
                }
            }
        }

        // 3. Καθολικά
        return self::globalCredentials(self::zoneFor($params));
    }

    public static function globalCredentials($zone = null)
    {
        return [
            'secret'  => Api::normalizeSecret(self::addonSetting('secret_key')),
            'project' => trim((string) self::addonSetting('project_id')),
            'zone'    => Api::normalizeZone($zone ?: (self::addonSetting('default_zone') ?: 'fr-par-1')),
        ];
    }

    /**
     * Η zone για ένα service: ό,τι έχει καταγραφεί στο instance → custom field →
     * product config → default του addon.
     */
    public static function zoneFor(array $params, $fallback = null)
    {
        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId > 0) {
            try {
                $row = Capsule::table(self::T_INSTANCES)->where('service_id', $serviceId)->first();
                if ($row && !empty($row->zone)) {
                    return Api::normalizeZone($row->zone);
                }
            } catch (\Exception $e) {
                // ο πίνακας μπορεί να μην υπάρχει ακόμη
            }
        }
        $cf = self::readCustomField($params, self::FIELD_ZONE);
        if ($cf) {
            return Api::normalizeZone($cf);
        }
        if (!empty($params['configoption3'])) {
            return Api::normalizeZone($params['configoption3']);
        }
        return Api::normalizeZone($fallback ?: (self::addonSetting('default_zone') ?: 'fr-par-1'));
    }

    /** Credentials ενός καταχωρημένου project (multi-project). */
    public static function projectCredentials($projectRowId)
    {
        try {
            $row = Capsule::table(self::T_PROJECTS)->where('id', (int) $projectRowId)->first();
            if (!$row) {
                return null;
            }
            return [
                'secret'  => Api::normalizeSecret($row->secret_key),
                'project' => (string) $row->project_id,
                'zone'    => Api::normalizeZone($row->zone ?? 'fr-par-1'),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Σε ποιο project (row id) ζει το VM του service. */
    public static function instanceProjectId($serviceId)
    {
        try {
            $row = Capsule::table(self::T_INSTANCES)->where('service_id', (int) $serviceId)->first();
            return $row ? (int) $row->project_id : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Σε ποιο project πρέπει να δημιουργηθεί το VM:
     * override στο προϊόν (configoption10) → primary project → 0 (καθολικά).
     */
    public static function targetProjectForCreate(array $params)
    {
        if (!empty($params['configoption10'])) {
            $ovr = (int) $params['configoption10'];
            if ($ovr > 0) {
                return $ovr;
            }
        }
        try {
            $row = Capsule::table(self::T_PROJECTS)->where('is_primary', 1)->first();
            return $row ? (int) $row->id : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** Καταγραφή: ποιο service ζει σε ποιο project/zone και με ποιο server id. */
    public static function recordInstance($serviceId, $projectId, $serverId = null, $zone = null)
    {
        try {
            $data = ['project_id' => (int) $projectId, 'updated_at' => date('Y-m-d H:i:s')];
            if ($serverId !== null) {
                $data['server_id'] = (string) $serverId;
            }
            if ($zone !== null) {
                $data['zone'] = Api::normalizeZone($zone);
            }
            $exists = Capsule::table(self::T_INSTANCES)->where('service_id', (int) $serviceId)->exists();
            if ($exists) {
                Capsule::table(self::T_INSTANCES)->where('service_id', (int) $serviceId)->update($data);
            } else {
                Capsule::table(self::T_INSTANCES)->insert(array_merge($data, [
                    'service_id' => (int) $serviceId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]));
            }
        } catch (\Exception $e) {
            // μη κρίσιμο — ο πίνακας δημιουργείται από το addon
        }
    }

    /** Έτοιμος API client με ενεργοποιημένο logging. */
    public static function api(array $params)
    {
        $c = self::credentials($params);
        $api = new Api($c['secret'], $c['project'], $c['zone']);
        if (function_exists('logModuleCall')) {
            $api->setLogger(function ($action, $request, $response) {
                logModuleCall('scaleway', $action, self::scrub($request), self::scrub($response), '',
                    ['serveraccesshash', 'serverpassword', 'secret_key', 'password', 'X-Auth-Token']);
            });
        }
        return $api;
    }

    private static function scrub($data)
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                if (in_array(strtolower((string) $k), ['secret_key', 'password', 'root_password', 'user_data', 'token'], true)) {
                    $out[$k] = '***';
                } else {
                    $out[$k] = self::scrub($v);
                }
            }
            return $out;
        }
        return $data;
    }

    /* ─────────────────────── Remote-id persistence ─────────────────────── */

    public static function saveServerId(array $params, $serverId)
    {
        $serviceId = (int) $params['serviceid'];
        $wrote = self::saveCustomField($params, self::FIELD_SERVER_ID, $serverId);
        self::saveCustomField($params, 'vpsid', $serverId);
        if (!$wrote && !self::customFieldId((int) $params['pid'], 'vpsid')) {
            Capsule::table('tblhosting')->where('id', $serviceId)
                ->update(['username' => 'scw-' . $serverId]);
        }
    }

    /** Το Scaleway server id είναι UUID (όχι ακέραιος όπως στο Hetzner). */
    public static function serverId(array $params)
    {
        foreach ([self::FIELD_SERVER_ID, 'vpsid'] as $field) {
            $v = self::readCustomField($params, $field);
            if ($v !== null && trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }
        if (!empty($params['username']) && strpos($params['username'], 'scw-') === 0) {
            return substr($params['username'], 4);
        }
        // fallback: ο πίνακας instances
        try {
            $row = Capsule::table(self::T_INSTANCES)->where('service_id', (int) ($params['serviceid'] ?? 0))->first();
            if ($row && !empty($row->server_id)) {
                return (string) $row->server_id;
            }
        } catch (\Exception $e) {
            // ignore
        }
        return '';
    }

    public static function saveIpAndPassword(array $params, $ip, $rootPassword = null)
    {
        $serviceId = (int) $params['serviceid'];
        $update = [];
        if ($ip !== null && $ip !== '') {
            $update['dedicatedip'] = $ip;
        }
        if ($rootPassword !== null && function_exists('encrypt')) {
            $update['password'] = encrypt($rootPassword);
        }
        if ($update) {
            Capsule::table('tblhosting')->where('id', $serviceId)->update($update);
        }
    }

    /** Γράφει τα στοιχεία παράδοσης (root/IP/extra IPs/vpsid). */
    public static function saveDelivery(array $params, array $server, $rootPassword = null, array $extraIps = [])
    {
        $serviceId = (int) $params['serviceid'];
        $productId = (int) $params['pid'];
        $update = [];

        $idInField = self::customFieldId($productId, self::FIELD_SERVER_ID) || self::customFieldId($productId, 'vpsid');
        if ($idInField) {
            $update['username'] = 'root';
        }
        $ip = self::primaryIp($server);
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
        if (!empty($server['id'])) {
            self::saveCustomField($params, 'vpsid', $server['id']);
        }
    }

    /** Η δημόσια IPv4 ενός Scaleway server object. */
    public static function primaryIp(array $server)
    {
        if (!empty($server['public_ip']['address'])) {
            return $server['public_ip']['address'];
        }
        foreach ($server['public_ips'] ?? [] as $ip) {
            if (($ip['family'] ?? 'inet') === 'inet' && !empty($ip['address'])) {
                return $ip['address'];
            }
        }
        return null;
    }

    /** Όλες οι δημόσιες IPv4 πλην της κύριας (extra IPs). */
    public static function extraIpAddresses(array $server)
    {
        $primary = self::primaryIp($server);
        $out = [];
        foreach ($server['public_ips'] ?? [] as $ip) {
            $addr = $ip['address'] ?? '';
            if ($addr && $addr !== $primary && ($ip['family'] ?? 'inet') === 'inet') {
                $out[] = $addr;
            }
        }
        return $out;
    }

    /* ─────────────────── Root password μέσω cloud-init ─────────────────── */

    /**
     * Η Scaleway δεν επιστρέφει root password (πρόσβαση με SSH keys). Για
     * white-label VPS ο πελάτης περιμένει κωδικό, οπότε τον ορίζουμε εμείς.
     */
    public static function generatePassword($length = 18)
    {
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $sym = '!@#%^*_-+=';
        $out = '';
        for ($i = 0; $i < $length - 2; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $out .= $sym[random_int(0, strlen($sym) - 1)];
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        return $out;
    }

    /** cloud-config που ορίζει root password και επιτρέπει SSH με κωδικό. */
    public static function rootPasswordCloudInit($password, array $extraIps = [])
    {
        $pw = str_replace(['\\', '"', "\n"], ['\\\\', '\\"', ''], (string) $password);
        $yaml = "#cloud-config\n"
            . "disable_root: false\n"
            . "ssh_pwauth: true\n"
            . "chpasswd:\n"
            . "  expire: false\n"
            . "  list: |\n"
            . "    root:" . $pw . "\n"
            . "runcmd:\n"
            . "  - sed -i 's/^#\\?PermitRootLogin.*/PermitRootLogin yes/' /etc/ssh/sshd_config\n"
            . "  - sed -i 's/^#\\?PasswordAuthentication.*/PasswordAuthentication yes/' /etc/ssh/sshd_config\n"
            . "  - systemctl restart ssh || systemctl restart sshd || true\n";
        return $yaml;
    }

    /* ─────────────────── Configurable options ─────────────────── */

    public static function options(array $params)
    {
        return (!empty($params['configoptions']) && is_array($params['configoptions']))
            ? $params['configoptions'] : [];
    }

    public static function optionValue(array $params, array $needles)
    {
        foreach (self::options($params) as $name => $value) {
            $low = strtolower((string) $name);
            foreach ($needles as $n) {
                if (strpos($low, strtolower($n)) !== false) {
                    return is_array($value) ? reset($value) : $value;
                }
            }
        }
        return null;
    }

    public static function extraIpsCount(array $params)
    {
        $v = self::optionValue($params, ['extra ip', 'additional ip', 'επιπλέον ip']);
        return $v === null ? 0 : max(0, (int) $v);
    }

    /** true/false αν ο πελάτης επέλεξε ρητά backups· null αν δεν υπάρχει επιλογή. */
    public static function wantsBackups(array $params)
    {
        $v = self::optionValue($params, ['backup', 'αντίγραφ']);
        if ($v === null) {
            return null;
        }
        $low = strtolower(trim((string) $v));
        if ($low === '' || in_array($low, ['no', 'off', 'none', 'όχι', '0'], true)) {
            return false;
        }
        return true;
    }

    /** Αντιστοίχιση επιλογής πελάτη σε zone. */
    public static function mapZone($value, $default = 'fr-par-1')
    {
        // mb_strtolower: το strtolower είναι byte-based και ΔΕΝ πεζοποιεί ελληνικά
        $low = mb_strtolower(trim((string) $value), 'UTF-8');
        if ($low === '') {
            return $default;
        }
        if (isset(Api::ZONES[$low])) {
            return $low;
        }
        foreach (Api::ZONES as $z => $label) {
            if (strpos(strtolower($label), $low) !== false || strpos($low, $z) !== false) {
                return $z;
            }
        }
        // φιλικές ονομασίες
        $map = ['paris' => 'fr-par-1', 'france' => 'fr-par-1', 'γαλλία' => 'fr-par-1', 'παρίσι' => 'fr-par-1',
            'amsterdam' => 'nl-ams-1', 'netherlands' => 'nl-ams-1', 'ολλανδία' => 'nl-ams-1', 'άμστερνταμ' => 'nl-ams-1',
            'warsaw' => 'pl-waw-1', 'poland' => 'pl-waw-1', 'πολωνία' => 'pl-waw-1', 'βαρσοβία' => 'pl-waw-1',
            'milan' => 'it-mil-1', 'italy' => 'it-mil-1', 'ιταλία' => 'it-mil-1', 'μιλάνο' => 'it-mil-1'];
        foreach ($map as $k => $z) {
            if (strpos($low, $k) !== false) {
                return $z;
            }
        }
        return $default;
    }

    /** Αντιστοίχιση επιλογής πελάτη σε image label (π.χ. ubuntu_jammy). */
    public static function mapImage($value, Api $api, $default = 'ubuntu_jammy')
    {
        $low = mb_strtolower(trim((string) $value), 'UTF-8');
        if ($low === '') {
            return $default;
        }
        // UUID → χρησιμοποίησέ το ως έχει
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-/i', $low)) {
            return $value;
        }
        try {
            $images = $api->marketplaceImages();
        } catch (ApiException $e) {
            $images = [];
        }
        if (isset($images[$low])) {
            return $low;
        }
        foreach ($images as $label => $name) {
            if (strcasecmp($name, $value) === 0) {
                return $label;
            }
        }
        // χαλαρή αντιστοίχιση σε label
        $norm = preg_replace('/[^a-z0-9]+/', '', $low);
        foreach ($images as $label => $name) {
            if (strpos(preg_replace('/[^a-z0-9]+/', '', strtolower($label)), $norm) !== false
                || strpos(preg_replace('/[^a-z0-9]+/', '', strtolower($name)), $norm) !== false) {
                return $label;
            }
        }
        return self::guessImageLabel($low) ?: $default;
    }

    private static function guessImageLabel($low)
    {
        $known = [
            'ubuntu' => 'ubuntu_jammy', 'debian' => 'debian_bookworm', 'rocky' => 'rockylinux_9',
            'alma' => 'almalinux_9', 'centos' => 'centos_stream9', 'fedora' => 'fedora_39',
            'alpine' => 'alpine_3_20', 'arch' => 'archlinux', 'opensuse' => 'opensuse_leap_15',
        ];
        foreach ($known as $k => $label) {
            if (strpos($low, $k) !== false) {
                return $label;
            }
        }
        return null;
    }

    /* ─────────────────── Custom fields / settings ─────────────────── */

    private static function customFieldId($productId, $fieldName)
    {
        $row = Capsule::table('tblcustomfields')
            ->where('type', 'product')->where('relid', (int) $productId)
            ->where('fieldname', $fieldName)->first();
        return $row ? (int) $row->id : 0;
    }

    public static function saveCustomField(array $params, $fieldName, $value)
    {
        $fieldId = self::customFieldId((int) $params['pid'], $fieldName);
        if (!$fieldId) {
            return false;
        }
        $serviceId = (int) $params['serviceid'];
        $exists = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $fieldId)->where('relid', $serviceId)->exists();
        if ($exists) {
            Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $fieldId)->where('relid', $serviceId)
                ->update(['value' => (string) $value]);
        } else {
            Capsule::table('tblcustomfieldsvalues')->insert([
                'fieldid' => $fieldId, 'relid' => $serviceId, 'value' => (string) $value,
            ]);
        }
        return true;
    }

    public static function readCustomField(array $params, $fieldName)
    {
        if (!empty($params['customfields']) && is_array($params['customfields'])) {
            foreach ($params['customfields'] as $k => $v) {
                if (strcasecmp($k, $fieldName) === 0) {
                    return $v;
                }
            }
        }
        $fieldId = self::customFieldId((int) ($params['pid'] ?? 0), $fieldName);
        if (!$fieldId) {
            return null;
        }
        $row = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $fieldId)->where('relid', (int) ($params['serviceid'] ?? 0))->first();
        return $row ? $row->value : null;
    }

    public static function addonSetting($setting)
    {
        try {
            $row = Capsule::table('tbladdonmodules')
                ->where('module', 'scaleway')->where('setting', $setting)->first();
            return $row ? $row->value : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Ντετερμινιστικό, μη-αναγνωρίσιμο όνομα instance (μόνο admins το βλέπουν). */
    public static function remoteServerName(array $params)
    {
        return 'whmcs-' . (int) $params['serviceid'];
    }

    /**
     * Διαγραφή όλων των πόρων ενός service: extra IPs → instance (terminate,
     * που παίρνει μαζί volumes & κύρια IP).
     */
    public static function deleteResources(Api $api, $serverId, $serviceId, $zone = null)
    {
        // Extra flexible IPs με το tag μας
        try {
            foreach ($api->listIps($zone) as $ip) {
                $tags = $ip['tags'] ?? [];
                if (in_array('whmcs_service=' . (int) $serviceId, $tags, true)) {
                    try {
                        if (!empty($ip['server'])) {
                            $api->detachIp($ip['id'], $zone);
                        }
                        $api->deleteIp($ip['id'], $zone);
                    } catch (ApiException $e) {
                        // μη κρίσιμο
                    }
                }
            }
        } catch (ApiException $e) {
            // μη κρίσιμο
        }

        if (!$serverId) {
            return;
        }
        // terminate = σβήνει instance + volumes + απελευθερώνει IP
        try {
            $api->terminate($serverId, $zone);
        } catch (ApiException $e) {
            // Αν είναι ήδη σβηστό, δοκίμασε σκέτη διαγραφή
            try {
                $api->deleteServer($serverId, $zone);
            } catch (ApiException $e2) {
                throw $e;
            }
        }
    }

    /** Επαναφορά stock μετρητή προϊόντος (όπως στο Hetzner). */
    public static function resetStock(array $params)
    {
        try {
            $productId = (int) ($params['pid'] ?? 0);
            if ($productId > 0) {
                Capsule::table('tblproducts')->where('id', $productId)
                    ->where('stockcontrol', '')->update(['qty' => 0]);
            }
        } catch (\Exception $e) {
            // ignore
        }
    }
}
