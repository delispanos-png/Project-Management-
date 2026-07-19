<?php
/**
 * Pricing / availability sync engine and service-import logic for the
 * Hetzner Cloud addon.
 *
 * Reuses the API client and Pricing helper shipped with the provisioning
 * module so there is a single source of truth for API behaviour.
 *
 * @package WHMCS\Module\Addon\HetznerCloud
 */

namespace WHMCS\Module\Addon\HetznerCloud;

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\HetznerCloud\Api;
use WHMCS\Module\Server\HetznerCloud\ApiException;
use WHMCS\Module\Server\HetznerCloud\Pricing;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

// Pull in the shared library from the provisioning module.
require_once __DIR__ . '/../../../servers/hetznercloud/lib/Api.php';
require_once __DIR__ . '/../../../servers/hetznercloud/lib/Pricing.php';

class Sync
{
    /** @var array addon settings */
    private $cfg;

    /** @var Api */
    private $api;

    public function __construct(array $settings)
    {
        $this->cfg = $settings;
        $token = Api::normalizeToken($settings['api_token'] ?? '');
        // Fallback: if the passed value isn't token-shaped, read it straight
        // from the addon settings table and normalise that.
        if (!preg_match('/^[A-Za-z0-9]{40,80}$/', $token)) {
            $row = Capsule::table('tbladdonmodules')
                ->where('module', 'hetznercloud')->where('setting', 'api_token')->first();
            if ($row) {
                $token = Api::normalizeToken($row->value);
            }
        }
        $this->cfg['api_token'] = $token;
        $this->api = new Api($token);
    }

    public function api()
    {
        return $this->api;
    }

    // ---------------------------------------------------------------------
    // Catalogue
    // ---------------------------------------------------------------------

    /**
     * Build a normalised catalogue of server types with per-location monthly
     * cost and availability.
     *
     * @return array [types => [...], locations => [...], meta => [...]]
     */
    public function catalogue()
    {
        $basis = ($this->cfg['price_basis'] ?? 'net') === 'gross' ? 'gross' : 'net';
        $types = [];
        $availByType = $this->availabilityMap();

        foreach ($this->api->serverTypes() as $t) {
            $prices = [];
            $trafficLoc = [];
            foreach (($t['prices'] ?? []) as $p) {
                $loc = $p['location'] ?? '';
                $monthly = isset($p['price_monthly'])
                    ? Pricing::fromNode($p['price_monthly'], $basis) : 0.0;
                $prices[$loc] = $monthly;
                // included_traffic is location-specific (EU 20TB vs US/SG 1TB).
                if (isset($p['included_traffic'])) {
                    $trafficLoc[$loc] = (float) $p['included_traffic'];
                }
            }
            $trafficFallback = isset($t['included_traffic']) && $t['included_traffic']
                ? (float) $t['included_traffic']
                : ($trafficLoc ? max($trafficLoc) : 0.0);
            $types[] = [
                'name'         => $t['name'],
                'id'           => $t['id'],
                'cores'        => $t['cores'] ?? 0,
                'memory'       => $t['memory'] ?? 0,
                'disk'         => $t['disk'] ?? 0,
                'architecture' => $t['architecture'] ?? '',
                'cpu_type'     => $t['cpu_type'] ?? '',
                'storage_type' => $t['storage_type'] ?? 'local',
                'traffic'      => $trafficFallback,  // bytes (headline)
                'traffic_loc'  => $trafficLoc,       // bytes per location
                'deprecated'   => !empty($t['deprecation']),
                'prices'       => $prices,
                'available_in' => $availByType[$t['id']] ?? [],
            ];
        }

        return [
            'types'     => $types,
            'locations' => $this->locationList(),
            'meta'      => $this->pricingMeta($basis),
        ];
    }

    /** Map server_type id => list of datacenter/location names that offer it. */
    private function availabilityMap()
    {
        $map = [];
        try {
            foreach ($this->api->datacenters() as $dc) {
                $loc = $dc['location']['name'] ?? ($dc['name'] ?? '');
                foreach (($dc['server_types']['available'] ?? []) as $typeId) {
                    $map[$typeId][$loc] = true;
                }
            }
        } catch (ApiException $e) {
            Db::log('availability fetch failed: ' . $e->getMessage(), 'error');
        }
        foreach ($map as $id => $locs) {
            $map[$id] = array_keys($locs);
        }
        return $map;
    }

    private function locationList()
    {
        $out = [];
        try {
            foreach ($this->api->locations() as $l) {
                $out[$l['name']] = ($l['city'] ?? $l['name']) . ', ' . ($l['country'] ?? '');
            }
        } catch (ApiException $e) {
            // ignore
        }
        return $out;
    }

    private function pricingMeta($basis)
    {
        try {
            $p = $this->api->pricing();
            return [
                'currency'  => $p['currency'] ?? 'EUR',
                'vat_rate'  => $p['vat_rate'] ?? '0',
                'basis'     => $basis,
                'ipv4'      => isset($p['primary_ips'][0]['prices'][0]['price_monthly'])
                    ? Pricing::fromNode($p['primary_ips'][0]['prices'][0]['price_monthly'], $basis)
                    : (isset($p['server_backup']) ? 0 : 0.50),
                'backup_pct' => isset($p['server_backup']['percentage'])
                    ? (float) $p['server_backup']['percentage'] : 20.0,
            ];
        } catch (ApiException $e) {
            return ['currency' => 'EUR', 'vat_rate' => '0', 'basis' => $basis, 'ipv4' => 0.50, 'backup_pct' => 20.0];
        }
    }

    // ---------------------------------------------------------------------
    // Pricing computation & application
    // ---------------------------------------------------------------------

    /**
     * Compute the raw monthly cost for a mapping (type price at location
     * + optional IPv4 + optional backup surcharge).
     */
    public function costFor($mapping, array $catalogue)
    {
        $type = null;
        foreach ($catalogue['types'] as $t) {
            if ($t['name'] === $mapping->server_type) {
                $type = $t;
                break;
            }
        }
        if (!$type) {
            return null;
        }
        $loc = $mapping->location;
        // Fall back to the cheapest location if none specified.
        if ($loc && isset($type['prices'][$loc])) {
            $cost = $type['prices'][$loc];
        } else {
            $cost = $type['prices'] ? min($type['prices']) : 0;
        }
        if ($mapping->include_ipv4) {
            $cost += (float) ($catalogue['meta']['ipv4'] ?? 0.50);
        }
        if ($mapping->include_backup) {
            $cost += $cost * ((float) ($catalogue['meta']['backup_pct'] ?? 20) / 100);
        }
        return round($cost, 4);
    }

    /**
     * Effective mappings for EVERY product on the Cloud Servers module.
     *
     * The Hetzner type/location/IPv4/backup are read automatically from each
     * product's own Module Settings (tblproducts.configoptionN). A row in
     * mod_hetzner_map is used only for optional overrides (a typed type name
     * and/or a per-product markup) and to cache last prices.
     *
     * configoption1 = Server Type, 3 = Location, 4 = Enable IPv4, 5 = Backups.
     *
     * @return \stdClass[]
     */
    public function effectiveMappings()
    {
        $overrides = [];
        foreach (Db::mappings() as $m) {
            $overrides[(int) $m->whmcs_pid] = $m;
        }
        $products = Capsule::table('tblproducts')->where('servertype', 'hetznercloud')
            ->orderBy('name')
            ->get(['id', 'name', 'configoption1', 'configoption3', 'configoption4', 'configoption5']);

        $out = [];
        foreach ($products as $p) {
            $out[] = $this->buildMapping($p, $overrides[(int) $p->id] ?? null);
        }
        return $out;
    }

    /** Build one effective mapping object from a product row + optional override. */
    private function buildMapping($product, $override)
    {
        $m = new \stdClass();
        $m->whmcs_pid = (int) $product->id;
        $m->name = $product->name;

        // Type: an explicit override wins, otherwise the product's own config.
        $ovType = ($override && !empty($override->server_type)) ? strtolower(trim($override->server_type)) : '';
        $cfgType = strtolower(trim((string) $product->configoption1));
        $m->server_type = $ovType !== '' ? $ovType : $cfgType;

        $m->kind = 'server';
        $m->location = ($override && $override->location)
            ? $override->location
            : (trim((string) $product->configoption3) ?: null);
        $m->include_ipv4 = ($product->configoption4 === 'on') ? 1 : 0;
        $m->include_backup = ($product->configoption5 === 'on') ? 1 : 0;
        $m->markup = $override ? $override->markup : null;
        $m->last_cost = $override ? $override->last_cost : 0;
        $m->last_price = $override ? $override->last_price : 0;
        return $m;
    }

    /**
     * For stock / store purposes: is the type orderable in at LEAST ONE
     * location? Customers pick the location at order time, so a product should
     * be "available" if the type exists anywhere — only types with no available
     * location at all (e.g. sold out everywhere) count as unavailable.
     */
    public function availableAnywhere($mapping, array $catalogue)
    {
        foreach ($catalogue['types'] as $t) {
            if ($t['name'] === $mapping->server_type) {
                return empty($t['deprecated']) && !empty($t['available_in']);
            }
        }
        return false;
    }

    /**
     * Persist a compact availability map (type → available city names) that the
     * order-form hook reads to restrict the Location option to real locations.
     */
    public function writeAvailabilityCache(array $catalogue)
    {
        $locCity = [];
        foreach ($catalogue['locations'] as $slug => $label) {
            $locCity[$slug] = trim(explode(',', $label)[0]); // "Nuremberg, DE" → "Nuremberg"
        }
        $types = [];
        foreach ($catalogue['types'] as $t) {
            if (!empty($t['deprecated'])) {
                continue;
            }
            $cities = [];
            foreach ($t['available_in'] as $slug) {
                if (isset($locCity[$slug])) {
                    $cities[] = $locCity[$slug];
                }
            }
            $types[strtolower($t['name'])] = array_values(array_unique($cities));
        }
        $data = [
            'types'      => $types,
            'all_cities' => array_values(array_unique(array_values($locCity))),
            'updated'    => date('c'),
        ];
        $dir = __DIR__ . '/../cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($dir . '/availability.json', json_encode($data));
    }

    /**
     * Lightweight refresh: update the availability cache AND product stock from
     * live Hetzner availability, WITHOUT touching prices/descriptions. Meant to
     * run often (every ~15 min) so the store + location filter stay current.
     */
    public function refreshAvailability()
    {
        $cat = $this->catalogue();
        $this->writeAvailabilityCache($cat);
        foreach ($this->effectiveMappings() as $m) {
            if ($m->server_type === '') {
                continue;
            }
            $this->applyStock($m->whmcs_pid, $this->availableAnywhere($m, $cat));
        }
    }

    /** Read the availability cache written by writeAvailabilityCache(). */
    public static function readAvailabilityCache()
    {
        $file = __DIR__ . '/../cache/availability.json';
        if (!is_file($file)) {
            return null;
        }
        $d = json_decode(@file_get_contents($file), true);
        return is_array($d) ? $d : null;
    }

    /**
     * Location-specific availability: if the product pins a location, check
     * there; otherwise "available anywhere". (Used where a fixed location
     * matters.)
     */
    public function availabilityFor($mapping, array $catalogue)
    {
        foreach ($catalogue['types'] as $t) {
            if ($t['name'] === $mapping->server_type) {
                if ($t['deprecated']) {
                    return false;
                }
                $avail = $t['available_in'];
                if (!empty($mapping->location)) {
                    return in_array($mapping->location, $avail, true);
                }
                return !empty($avail);
            }
        }
        return false;
    }

    /**
     * Enable stock control on a product and set qty from availability:
     * available → configured qty, unavailable → 0. Returns the qty set, or null
     * if stock sync is disabled / failed.
     */
    public function applyStock($pid, $available)
    {
        if (($this->cfg['sync_stock'] ?? 'on') !== 'on') {
            return null;
        }
        $qty = (int) ($this->cfg['available_stock'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }
        $target = $available ? $qty : 0;
        try {
            Capsule::table('tblproducts')->where('id', (int) $pid)->update([
                'stockcontrol' => 1, // tinyint: 1 = enabled
                'qty'          => $target,
            ]);
            return $target;
        } catch (\Exception $e) {
            Db::log('applyStock failed #' . $pid . ': ' . $e->getMessage(), 'error');
            return null;
        }
    }

    public function defaultMarkup()
    {
        return (float) ($this->cfg['default_markup'] ?? 40);
    }

    /** Description auto-fill mode: 'if_empty' (default) | 'always' | 'off'. */
    public function descriptionMode()
    {
        $m = $this->cfg['sync_description'] ?? 'if_empty';
        return in_array($m, ['if_empty', 'always', 'off'], true) ? $m : 'if_empty';
    }

    private function productDescriptionEmpty($pid)
    {
        try {
            $d = Capsule::table('tblproducts')->where('id', (int) $pid)->value('description');
            return trim(strip_tags((string) $d)) === '';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Apply the auto-description for a product if the configured mode allows it.
     * Returns true if written.
     */
    public function maybeApplyDescription($mapping, array $catalogue)
    {
        $mode = $this->descriptionMode();
        if ($mode === 'off') {
            return false;
        }
        if ($mode === 'if_empty' && !$this->productDescriptionEmpty($mapping->whmcs_pid)) {
            return false; // keep the existing description
        }
        $html = $this->buildDescription($mapping, $catalogue);
        if ($html === null) {
            return false;
        }
        return $this->applyDescription($mapping->whmcs_pid, $html);
    }

    /** @var array|null cached location name => country code */
    private $locCountry = null;

    private function countryOf($locName)
    {
        if ($this->locCountry === null) {
            $this->locCountry = [];
            try {
                foreach ($this->api->locations() as $l) {
                    $this->locCountry[$l['name']] = $l['country'] ?? '';
                }
            } catch (ApiException $e) {
                // leave empty
            }
        }
        $code = $this->locCountry[$locName] ?? '';
        $names = ['DE' => 'Γερμανία', 'FI' => 'Φινλανδία', 'US' => 'ΗΠΑ', 'SG' => 'Σιγκαπούρη'];
        return $names[$code] ?? $code;
    }

    /**
     * Auto-generate the product HTML description from the Hetzner type specs,
     * matching the site's existing "list-info" template.
     */
    public function buildDescription($mapping, array $catalogue)
    {
        $type = null;
        foreach ($catalogue['types'] as $t) {
            if ($t['name'] === $mapping->server_type) {
                $type = $t;
                break;
            }
        }
        if (!$type) {
            return null;
        }

        // Type family → friendly CPU wording. (The store card only supports the
        // icon spec-list layout, so no free-text paragraphs here — they break it.)
        $n = strtolower($type['name']);
        $cores = (int) $type['cores'];
        if (strpos($n, 'ccx') === 0) {
            $cpu = $cores . ' αποκλειστικοί vCPU (AMD EPYC)';
        } elseif (strpos($n, 'cpx') === 0) {
            $cpu = $cores . ' vCPU (AMD EPYC)';
        } elseif (strpos($n, 'cax') === 0) {
            $cpu = $cores . ' vCPU (Ampere Arm64)';
        } elseif (strpos($n, 'cx') === 0) {
            $cpu = $cores . ' vCPU (Intel)';
        } else {
            $cpu = $cores . ' vCPU';
        }

        $diskType = ($type['storage_type'] === 'network') ? 'Network SSD' : 'NVMe SSD';

        $locName = $mapping->location ?: (array_key_first($type['prices'] ?: ['fsn1' => 0]));
        $country = $this->countryOf($locName) ?: 'Γερμανία';

        // Traffic is location-specific; use the mapping's location.
        $trafficBytes = $type['traffic_loc'][$locName] ?? $type['traffic'];
        $trafficTb = $trafficBytes > 0 ? round($trafficBytes / (1024 ** 4)) : 20;

        $rows = [
            ['icon-cpu', 'Επεξεργαστής', ' ' . $cpu],
            ['icon-ram', 'Μνήμη', 'RAM ' . (int) $type['memory'] . ' GB'],
            ['icon-drives', 'Δίσκος', (int) $type['disk'] . 'GB ' . $diskType],
            ['icon-goal', 'IP', 'IPV4 x1'],
            ['icon-network', 'Bandwith', '<strong>' . $trafficTb . 'TB*</strong> Κίνηση Δεδομένων'],
            ['icon-preferences', 'OS', '<strong>Λειτουργικό (OS)</strong> Linux'],
            ['icon-location', 'Τοποθεσία', '<strong>Τοποθεσία</strong> ' . htmlspecialchars($country, ENT_QUOTES, 'UTF-8')],
        ];

        $html = '<img class="svg" src="/templates/horn/assets/fonts/svg/vps.svg" alt="vps.svg" />' . "\n";
        foreach ($rows as $r) {
            $html .= '<div class="list-info"><i class="' . $r[0] . '"></i>'
                . '<span class="spec">' . $r[1] . '</span><span>' . $r[2] . '</span></div>' . "\n";
        }
        return $html;
    }

    public function applyDescription($pid, $html)
    {
        try {
            Capsule::table('tblproducts')->where('id', (int) $pid)->update(['description' => $html]);
            return true;
        } catch (\Exception $e) {
            Db::log('applyDescription failed #' . $pid . ': ' . $e->getMessage(), 'error');
            return false;
        }
    }

    public function stockSyncEnabled()
    {
        return ($this->cfg['sync_stock'] ?? 'on') === 'on';
    }

    public function availableStockQty()
    {
        $q = (int) ($this->cfg['available_stock'] ?? 1);
        return $q < 1 ? 1 : $q;
    }

    public function sellFor($cost, $mapping)
    {
        $defaultPct = (float) ($this->cfg['default_markup'] ?? 40);
        $pct = ($mapping->markup !== null && $mapping->markup !== '') ? (float) $mapping->markup : $defaultPct;
        $rounding = $this->cfg['rounding'] ?? 'up_cent';
        return Pricing::sell($cost, $pct, $rounding);
    }

    /**
     * Recompute prices for every mapping. When $apply is true, write the sell
     * price into WHMCS product pricing.
     *
     * @return array report rows
     */
    public function run($apply = true)
    {
        $report = [];
        try {
            $catalogue = $this->catalogue();
        } catch (ApiException $e) {
            Db::log('sync aborted, catalogue error: ' . $e->getMessage(), 'error');
            return [['error' => $e->getMessage()]];
        }

        $this->writeAvailabilityCache($catalogue);

        $currencyId = (int) ($this->cfg['currency_id'] ?? 1);
        $cycle = $this->cfg['billing_cycle'] ?? 'monthly';

        foreach ($this->effectiveMappings() as $m) {
            if ($m->server_type === '') {
                $report[] = ['pid' => $m->whmcs_pid, 'error' => 'no server type set on product'];
                continue;
            }
            $cost = $this->costFor($m, $catalogue);
            if ($cost === null) {
                $report[] = ['pid' => $m->whmcs_pid, 'error' => 'type ' . $m->server_type . ' not found'];
                continue;
            }
            $price = $this->sellFor($cost, $m);
            $changed = (abs((float) $m->last_price - $price) > 0.0001);
            $available = $this->availableAnywhere($m, $catalogue);

            Db::touchPrices($m->whmcs_pid, $cost, $price);

            $applied = false;
            $stock = null;
            if ($apply) {
                if ($changed) {
                    $applied = $this->applyPrice($m->whmcs_pid, $currencyId, $cycle, $price);
                    Db::log(sprintf(
                        'Product #%d %s: cost %.4f → price %.2f (%s)',
                        $m->whmcs_pid, $m->server_type, $cost, $price, $applied ? 'applied' : 'apply failed'
                    ), $applied ? 'info' : 'error');
                }
                $stock = $this->applyStock($m->whmcs_pid, $available);
                $this->maybeApplyDescription($m, $catalogue);
            }

            $report[] = [
                'pid'       => $m->whmcs_pid,
                'type'      => $m->server_type,
                'cost'      => $cost,
                'price'     => $price,
                'changed'   => $changed,
                'applied'   => $applied,
                'available' => $available,
                'stock'     => $stock,
            ];
        }
        return $report;
    }

    /**
     * Write a recurring price into tblpricing for the given product/cycle.
     */
    public function applyPrice($pid, $currencyId, $cycle, $price)
    {
        $column = in_array($cycle, [
            'monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially',
        ], true) ? $cycle : 'monthly';

        try {
            $exists = Capsule::table('tblpricing')
                ->where('type', 'product')->where('currency', $currencyId)->where('relid', (int) $pid)->exists();
            if ($exists) {
                Capsule::table('tblpricing')
                    ->where('type', 'product')->where('currency', $currencyId)->where('relid', (int) $pid)
                    ->update([$column => $price]);
            } else {
                Capsule::table('tblpricing')->insert([
                    'type' => 'product', 'currency' => $currencyId, 'relid' => (int) $pid,
                    $column => $price,
                ]);
            }
            return true;
        } catch (\Exception $e) {
            Db::log('applyPrice failed for #' . $pid . ': ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Recompute and immediately apply the price for a SINGLE product.
     * Powers the per-row "Sync now" button.
     *
     * @return array ['cost'=>float,'price'=>float,'applied'=>bool] or ['error'=>string]
     */
    public function applyOne($pid)
    {
        $product = Capsule::table('tblproducts')
            ->where('id', (int) $pid)->where('servertype', 'hetznercloud')
            ->first(['id', 'name', 'configoption1', 'configoption3', 'configoption4', 'configoption5']);
        if (!$product) {
            return ['error' => 'Product #' . (int) $pid . ' is not on the Cloud Servers module'];
        }
        $m = $this->buildMapping($product, Db::mappingForProduct($pid));
        if ($m->server_type === '') {
            return ['error' => 'Δεν έχει οριστεί τύπος Hetzner (γράψε τον εδώ ή στο προϊόν → Module Settings → Server Type)'];
        }
        $catalogue = $this->catalogue();
        $cost = $this->costFor($m, $catalogue);
        if ($cost === null) {
            return ['error' => 'Ο τύπος «' . $m->server_type . '» δεν βρέθηκε στο Hetzner'];
        }
        $price = $this->sellFor($cost, $m);
        $available = $this->availabilityFor($m, $catalogue);

        Db::touchPrices($m->whmcs_pid, $cost, $price);

        $currencyId = (int) ($this->cfg['currency_id'] ?? 1);
        $cycle = $this->cfg['billing_cycle'] ?? 'monthly';
        $applied = $this->applyPrice($m->whmcs_pid, $currencyId, $cycle, $price);
        $stock = $this->applyStock($m->whmcs_pid, $available);
        $this->maybeApplyDescription($m, $catalogue);
        Db::log(sprintf('Manual sync product #%d %s: cost %.4f -> price %.2f (%s), available=%s stock=%s',
            $m->whmcs_pid, $m->server_type, $cost, $price, $applied ? 'applied' : 'failed',
            $available ? 'yes' : 'no', $stock === null ? '-' : $stock),
            $applied ? 'info' : 'error');

        return ['cost' => $cost, 'price' => $price, 'applied' => $applied, 'available' => $available, 'stock' => $stock];
    }

    // ---------------------------------------------------------------------
    // Import / adopt already-sold services
    // ---------------------------------------------------------------------

    /**
     * List active WHMCS services on hetznercloud-linked products that are not
     * yet linked to a Hetzner server, alongside best-guess matches.
     */
    public function importCandidates()
    {
        // Products served by our provisioning module.
        $productIds = Capsule::table('tblproducts')
            ->where('servertype', 'hetznercloud')->pluck('id')->all();
        if (!$productIds) {
            return ['servers' => [], 'services' => []];
        }

        $services = Capsule::table('tblhosting')
            ->whereIn('packageid', $productIds)
            ->whereIn('domainstatus', ['Active', 'Suspended'])
            ->get(['id', 'userid', 'packageid', 'domain', 'dedicatedip', 'username']);

        // Live Hetzner servers keyed by IP and by name.
        $byIp = [];
        $byName = [];
        $allServers = [];
        try {
            $page = 1;
            do {
                $res = $this->api->request('GET', '/servers', ['per_page' => 50, 'page' => $page]);
                foreach (($res['servers'] ?? []) as $s) {
                    $allServers[] = $s;
                    $ip = $s['public_net']['ipv4']['ip'] ?? '';
                    if ($ip) { $byIp[$ip] = $s['id']; }
                    $byName[$s['name']] = $s['id'];
                }
                $page = $res['meta']['pagination']['next_page'] ?? null;
            } while ($page);
        } catch (ApiException $e) {
            Db::log('import: server list failed ' . $e->getMessage(), 'error');
        }

        $rows = [];
        foreach ($services as $svc) {
            // Already linked?
            $linked = (!empty($svc->username) && strpos($svc->username, 'hz-') === 0);
            $guessId = 0;
            $guessBy = '';
            if (!empty($svc->dedicatedip) && isset($byIp[$svc->dedicatedip])) {
                $guessId = $byIp[$svc->dedicatedip];
                $guessBy = 'IP';
            } elseif (isset($byName['whmcs-' . $svc->id])) {
                $guessId = $byName['whmcs-' . $svc->id];
                $guessBy = 'name';
            }
            $rows[] = [
                'serviceid' => $svc->id,
                'domain'    => $svc->domain,
                'ip'        => $svc->dedicatedip,
                'linked'    => $linked,
                'guess_id'  => $guessId,
                'guess_by'  => $guessBy,
            ];
        }

        return ['servers' => $allServers, 'services' => $rows];
    }

    /**
     * Link a WHMCS service to an existing Hetzner server id (adopt).
     */
    public function linkService($serviceId, $serverId)
    {
        $serviceId = (int) $serviceId;
        $serverId = (int) $serverId;
        try {
            $srv = $this->api->getServer($serverId);
            if (!$srv) {
                return 'Server not found on Hetzner.';
            }
            $ip = $srv['public_net']['ipv4']['ip'] ?? null;
            $update = ['username' => 'hz-' . $serverId];
            if ($ip) {
                $update['dedicatedip'] = $ip;
            }
            Capsule::table('tblhosting')->where('id', $serviceId)->update($update);
            Db::log("Linked WHMCS service #$serviceId to Hetzner server #$serverId", 'info');
            return true;
        } catch (ApiException $e) {
            return $e->getMessage();
        }
    }
}
