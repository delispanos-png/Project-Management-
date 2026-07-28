<?php
/**
 * Scaleway addon — συγχρονισμός καταλόγου/τιμών, import & reconcile.
 *
 * @package WHMCS\Module\Addon\Scaleway
 */

namespace WHMCS\Module\Addon\Scaleway;

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\Scaleway\Api;
use WHMCS\Module\Server\Scaleway\ApiException;
use WHMCS\Module\Server\Scaleway\Helper;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/../../../servers/scaleway/lib/Api.php';
require_once __DIR__ . '/../../../servers/scaleway/lib/Helper.php';

class Sync
{
    /** @var array addon settings */
    private $settings;

    /** Ώρες ανά μήνα για μετατροπή ωριαίας τιμής σε μηνιαία. */
    const HOURS_PER_MONTH = 730;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function setting($key, $default = '')
    {
        $v = $this->settings[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    /**
     * API client. Χωρίς όρισμα → πρωτεύον project (ή καθολικές ρυθμίσεις).
     */
    public function api($projectRow = null, $zone = null)
    {
        if ($projectRow === null) {
            $projectRow = Db::primaryProject();
        }
        if ($projectRow) {
            $secret = Db::projectSecret($projectRow);
            $api = new Api($secret, $projectRow->project_id, $zone ?: $projectRow->zone);
        } else {
            $api = new Api(
                Api::normalizeSecret($this->setting('secret_key')),
                $this->setting('project_id'),
                $zone ?: $this->setting('default_zone', 'fr-par-1')
            );
        }
        if (function_exists('logModuleCall')) {
            $api->setLogger(function ($action, $req, $res) {
                logModuleCall('scaleway', $action, $req, $res, '', ['secret_key', 'X-Auth-Token', 'password']);
            });
        }
        return $api;
    }

    /** Όλα τα ενεργά projects (ή pseudo-project από τις καθολικές ρυθμίσεις). */
    public function projectList()
    {
        $rows = Db::projects(true);
        if (count($rows)) {
            return $rows;
        }
        if ($this->setting('secret_key') !== '') {
            return [(object) [
                'id' => 0, 'name' => 'Καθολικές ρυθμίσεις',
                'secret_key' => $this->setting('secret_key'),
                'project_id' => $this->setting('project_id'),
                'zone' => $this->setting('default_zone', 'fr-par-1'),
                'is_primary' => 1, 'enabled' => 1,
            ]];
        }
        return [];
    }

    /* ─────────────────────────── Κατάλογος ─────────────────────────── */

    /**
     * Τύποι + διαθεσιμότητα για μια zone.
     * @return array ['types' => [...], 'availability' => [...]]
     */
    public function catalogue($zone = null)
    {
        static $cache = [];
        $zone = Api::normalizeZone($zone ?: $this->setting('default_zone', 'fr-par-1'));
        if (isset($cache[$zone])) {
            return $cache[$zone];
        }
        $types = [];
        $availability = [];
        try {
            $api = $this->api(null, $zone);
            $types = $api->serverTypes($zone);
            $availability = $api->serverAvailability($zone);
        } catch (ApiException $e) {
            Db::log('Αποτυχία ανάκτησης καταλόγου (' . $zone . '): ' . $e->getMessage(), 'error');
        }
        return $cache[$zone] = compact('types', 'availability');
    }

    /** Μηνιαίο κόστος (χωρίς ΦΠΑ) για μια αντιστοίχιση. */
    public function costFor($mapping, array $catalogue = null)
    {
        $zone = $mapping->zone ?: $this->setting('default_zone', 'fr-par-1');
        $cat = $catalogue ?: $this->catalogue($zone);
        $type = $cat['types'][$mapping->commercial_type] ?? null;
        if (!$type) {
            return null;
        }
        $hourly = (float) ($type['hourly_price'] ?? 0);
        $cost = $hourly * self::HOURS_PER_MONTH;

        // Δημόσια IPv4 (flexible IP) — χρεώνεται ξεχωριστά.
        if (!empty($mapping->include_ipv4)) {
            $cost += (float) $this->setting('ipv4_monthly', '0.99');
        }
        // Πρόσθετος block storage πέρα από τον περιλαμβανόμενο δίσκο.
        $diskGb = (int) ($mapping->disk_gb ?? 0);
        if ($diskGb > 0) {
            $included = 0;
            if (!empty($type['volumes_constraint']['min_size'])) {
                $included = (int) round($type['volumes_constraint']['min_size'] / 1000000000);
            }
            $extra = max(0, $diskGb - $included);
            if ($extra > 0) {
                $cost += $extra * (float) $this->setting('block_gb_monthly', '0.088');
            }
        }
        return round($cost, 4);
    }

    public function defaultMarkup()
    {
        return (float) $this->setting('markup', '30');
    }

    /** Τιμή πώλησης από κόστος + markup. */
    public function sellFor($cost, $mapping = null)
    {
        $markup = ($mapping && $mapping->markup !== null) ? (float) $mapping->markup : $this->defaultMarkup();
        $price = $cost * (1 + $markup / 100);
        $round = $this->setting('rounding', 'none');
        if ($round === 'up99') {
            $price = floor($price) + 0.99;
        } elseif ($round === 'int') {
            $price = ceil($price);
        } elseif ($round === 'half') {
            $price = ceil($price * 2) / 2;
        }
        return round($price, 2);
    }

    /* ─────────────────────────── Τιμολόγηση ─────────────────────────── */

    /**
     * Υπολογισμός (και προαιρετικά εφαρμογή) τιμών για όλες τις αντιστοιχίσεις.
     * @return array γραμμές αποτελέσματος για την οθόνη
     */
    public function run($apply = true)
    {
        $out = [];
        $currencyId = (int) $this->setting('currency', '1');
        $cycle = $this->setting('cycle', 'monthly');

        foreach (Db::mappings() as $m) {
            $product = Capsule::table('tblproducts')->where('id', (int) $m->whmcs_pid)->first();
            if (!$product) {
                continue;
            }
            $cost = $this->costFor($m);
            if ($cost === null) {
                $out[] = [
                    'pid' => $m->whmcs_pid, 'name' => $product->name,
                    'type' => $m->commercial_type, 'cost' => null, 'price' => null,
                    'status' => 'Ο τύπος δεν βρέθηκε στη zone ' . ($m->zone ?: 'default'),
                ];
                continue;
            }
            $price = $this->sellFor($cost, $m);
            $status = 'υπολογίστηκε';
            if ($apply) {
                $status = $this->applyPrice($m->whmcs_pid, $currencyId, $cycle, $price)
                    ? 'ενημερώθηκε' : 'αποτυχία εγγραφής';
                Db::recordPrice($m->whmcs_pid, $cost, $price);
            }
            $out[] = [
                'pid' => $m->whmcs_pid, 'name' => $product->name, 'type' => $m->commercial_type,
                'cost' => $cost, 'price' => $price, 'status' => $status,
            ];
        }
        if ($apply) {
            Db::log('Συγχρονισμός τιμών: ' . count($out) . ' προϊόντα.');
        }
        return $out;
    }

    /** Εγγραφή επαναλαμβανόμενης τιμής στο tblpricing. */
    public function applyPrice($pid, $currencyId, $cycle, $price)
    {
        $column = in_array($cycle, [
            'monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially',
        ], true) ? $cycle : 'monthly';
        try {
            $exists = Capsule::table('tblpricing')
                ->where('type', 'product')->where('currency', (int) $currencyId)
                ->where('relid', (int) $pid)->exists();
            if ($exists) {
                Capsule::table('tblpricing')
                    ->where('type', 'product')->where('currency', (int) $currencyId)
                    ->where('relid', (int) $pid)->update([$column => $price]);
            } else {
                Capsule::table('tblpricing')->insert([
                    'type' => 'product', 'currency' => (int) $currencyId,
                    'relid' => (int) $pid, $column => $price,
                ]);
            }
            return true;
        } catch (\Exception $e) {
            Db::log('Αποτυχία εγγραφής τιμής για product ' . $pid . ': ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /* ─────────────────────────── Import / στόλος ─────────────────────────── */

    /** Προϊόντα που χρησιμοποιούν το scaleway module. */
    public function scalewayProductIds()
    {
        try {
            return Capsule::table('tblproducts')->where('servertype', 'scaleway')->pluck('id')->all();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Ενεργές υπηρεσίες scaleway χωρίς συνδεδεμένο instance — υποψήφιες για import.
     */
    public function importCandidates()
    {
        $pids = $this->scalewayProductIds();
        if (!$pids) {
            return [];
        }
        $rows = [];
        try {
            $services = Capsule::table('tblhosting')
                ->whereIn('packageid', $pids)
                ->whereIn('domainstatus', ['Active', 'Suspended'])
                ->get(['id', 'userid', 'packageid', 'domain', 'domainstatus', 'dedicatedip']);
        } catch (\Exception $e) {
            return [];
        }

        // Όλα τα instances ανά project/zone, ώστε να τα ταιριάξουμε.
        $remote = $this->fleet();
        $byName = [];
        foreach ($remote as $r) {
            $byName[$r['name']] = $r;
        }

        foreach ($services as $s) {
            $linked = Capsule::table(Db::T_INSTANCES)->where('service_id', (int) $s->id)->first();
            if ($linked && !empty($linked->server_id)) {
                continue;   // ήδη συνδεδεμένο
            }
            $expected = 'whmcs-' . (int) $s->id;
            $match = $byName[$expected] ?? null;
            // εναλλακτικά ταίριασμα με IP
            if (!$match && $s->dedicatedip) {
                foreach ($remote as $r) {
                    if ($r['ip'] === $s->dedicatedip) {
                        $match = $r;
                        break;
                    }
                }
            }
            $rows[] = [
                'service_id' => (int) $s->id,
                'domain'     => $s->domain,
                'status'     => $s->domainstatus,
                'ip'         => $s->dedicatedip,
                'match'      => $match,
            ];
        }
        return $rows;
    }

    /** Όλα τα instances όλων των projects/zones. */
    public function fleet()
    {
        $out = [];
        foreach ($this->projectList() as $p) {
            $zones = array_unique([$p->zone, $this->setting('default_zone', 'fr-par-1')]);
            // Αν έχει οριστεί "scan_zones", σάρωσε όλες τις zones του Scaleway.
            if ($this->setting('scan_all_zones', '') === 'on') {
                $zones = array_keys(Api::ZONES);
            }
            foreach ($zones as $z) {
                try {
                    $api = $this->api($p->id ? $p : null, $z);
                    if (!$p->id) {
                        $api = new Api(Api::normalizeSecret($p->secret_key), $p->project_id, $z);
                    }
                    foreach ($api->listServers($z) as $srv) {
                        $serviceId = 0;
                        foreach ($srv['tags'] ?? [] as $tag) {
                            if (strpos($tag, 'whmcs_service=') === 0) {
                                $serviceId = (int) substr($tag, 14);
                            }
                        }
                        $out[] = [
                            'project'    => $p->name,
                            'project_id' => (int) $p->id,
                            'zone'       => $z,
                            'id'         => $srv['id'],
                            'name'       => $srv['name'] ?? '',
                            'type'       => $srv['commercial_type'] ?? '',
                            'state'      => $srv['state'] ?? '',
                            'ip'         => Helper::primaryIp($srv),
                            'service_id' => $serviceId,
                        ];
                    }
                } catch (ApiException $e) {
                    Db::log('Σάρωση στόλου απέτυχε (' . $p->name . '/' . $z . '): ' . $e->getMessage(), 'warn');
                }
            }
        }
        return $out;
    }

    /** Σύνδεση υπηρεσίας WHMCS με υπάρχον instance. */
    public function linkService($serviceId, $serverId, $projectId = 0, $zone = null)
    {
        $serviceId = (int) $serviceId;
        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service) {
            return 'Η υπηρεσία δεν βρέθηκε.';
        }
        Helper::recordInstance($serviceId, $projectId, $serverId, $zone);

        // Γράψε και στα custom fields ώστε να το βλέπει το server module.
        $params = ['serviceid' => $serviceId, 'pid' => (int) $service->packageid, 'customfields' => []];
        Helper::saveCustomField($params, Helper::FIELD_SERVER_ID, $serverId);
        Helper::saveCustomField($params, 'vpsid', $serverId);
        if ($zone) {
            Helper::saveCustomField($params, Helper::FIELD_ZONE, $zone);
        }

        // Ενημέρωσε IP από το API.
        try {
            $row = $projectId ? Db::project($projectId) : null;
            $api = $this->api($row, $zone);
            $srv = $api->getServer($serverId, $zone);
            if ($srv) {
                $ip = Helper::primaryIp($srv);
                if ($ip) {
                    Capsule::table('tblhosting')->where('id', $serviceId)->update(['dedicatedip' => $ip]);
                }
            }
        } catch (ApiException $e) {
            // μη κρίσιμο
        }
        Db::log('Σύνδεση υπηρεσίας #' . $serviceId . ' με instance ' . $serverId . '.');
        return '';
    }

    /**
     * Έλεγχος συνέπειας: ορφανά instances (χωρίς υπηρεσία) & υπηρεσίες χωρίς VM.
     */
    public function reconcile()
    {
        $fleet = $this->fleet();
        $orphans = [];
        $missing = [];

        $activeServiceIds = [];
        try {
            $pids = $this->scalewayProductIds();
            if ($pids) {
                $activeServiceIds = Capsule::table('tblhosting')
                    ->whereIn('packageid', $pids)
                    ->whereIn('domainstatus', ['Active', 'Suspended'])
                    ->pluck('id')->all();
            }
        } catch (\Exception $e) {
            // ignore
        }
        $activeServiceIds = array_map('intval', $activeServiceIds);

        $linkedServerIds = [];
        foreach ($fleet as $f) {
            if ($f['service_id'] > 0) {
                $linkedServerIds[$f['service_id']] = $f['id'];
            }
            // instance που δείχνει σε ανύπαρκτη/ακυρωμένη υπηρεσία
            if ($f['service_id'] > 0 && !in_array($f['service_id'], $activeServiceIds, true)) {
                $orphans[] = $f;
            } elseif ($f['service_id'] === 0 && strpos($f['name'], 'whmcs-') === 0) {
                $orphans[] = $f;
            }
        }
        foreach ($activeServiceIds as $sid) {
            if (!isset($linkedServerIds[$sid])) {
                $missing[] = $sid;
            }
        }

        return ['orphans' => $orphans, 'missing' => $missing, 'fleet' => count($fleet)];
    }
}
