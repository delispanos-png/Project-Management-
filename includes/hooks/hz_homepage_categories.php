<?php
/**
 * Assigns a curated list of service categories (with "from €X" pricing) to the
 * client-area homepage so it can show clear category cards + Order CTAs.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ClientAreaPageHome', 1, function ($vars) {
    // Nicer display names for a few key groups; everything else uses its own
    // group name. Icons are auto-detected from the group name below.
    $override = [
        'virtual-servers-location-in-germany' => 'Cloud VPS',
        'kvm-virtual-servers'                 => 'High-Performance VPS',
        'vmware-virtual-servers'              => 'VPS (GR)',
    ];
    // Keyword → icon (first match wins). Fallback is a generic cube.
    $iconRules = [
        'vps' => 'fas fa-server', 'virtual server' => 'fas fa-server', 'cloud' => 'fas fa-cloud',
        'dedicated' => 'fas fa-hdd', 'colocation' => 'fas fa-warehouse', 'collocation' => 'fas fa-warehouse',
        'storage' => 'fas fa-database', 'backup' => 'fas fa-shield-alt',
        'domain' => 'fas fa-globe', 'ssl' => 'fas fa-lock', 'licens' => 'fas fa-key',
        'firewall' => 'fas fa-fire', 'ip address' => 'fas fa-network-wired',
        'voip' => 'fas fa-phone', 'pbx' => 'fas fa-phone', '3cx' => 'fas fa-phone',
        'hosting' => 'fas fa-globe', 'web' => 'fas fa-globe',
    ];
    // Keyword → theme SVG icon file (in templates/horn/assets/fonts/svg/).
    $svgRules = [
        'high-performance' => 'cpu.svg', 'kvm' => 'cpu.svg',
        'vps' => 'vps.svg', 'virtual server' => 'vps.svg', 'cloud on storage' => 'database.svg',
        'cloud' => 'cloudserver.svg', 'dedicated' => 'dedicated.svg',
        'colocation' => 'rack.svg', 'collocation' => 'rack.svg',
        'storage' => 'database.svg', 'backup' => 'security.svg',
        'domain' => 'domains.svg', 'ssl' => 'lock.svg', 'licens' => 'key.svg',
        'firewall' => 'firewall.svg', 'ip address' => 'network.svg',
        'voip' => 'phone.svg', 'pbx' => 'phone.svg', '3cx' => 'phone.svg',
        'hosting' => 'globe.svg', 'web' => 'globe.svg',
    ];
    // Groups kept off the public homepage (client-specific, internal, etc.).
    $exclude = ['pharmacyone', 'pharmacy'];

    $currency = 1;
    try {
        $currency = (int) (Capsule::table('tblclients')
            ->where('id', (int) ($_SESSION['uid'] ?? 0))->value('currency') ?: 1);
    } catch (\Exception $e) {
        $currency = 1;
    }

    $cards = [];
    $groups = Capsule::table('tblproductgroups')->where('hidden', 0)
        ->orderBy('order')->get(['id', 'name', 'slug']);
    foreach ($groups as $g) {
        if (empty($g->slug)) {
            continue;
        }
        $nameLc = strtolower($g->name);
        foreach ($exclude as $x) {
            if (strpos($nameLc, $x) !== false) {
                continue 2;
            }
        }
        if (!Capsule::table('tblproducts')->where('gid', $g->id)->where('hidden', 0)->exists()) {
            continue;
        }

        // Cheapest recurring price: monthly preferred, else annually.
        $priceQ = function ($col) use ($g, $currency) {
            return Capsule::table('tblproducts as p')
                ->join('tblpricing as pr', function ($j) use ($currency) {
                    $j->on('pr.relid', '=', 'p.id')->where('pr.type', 'product')->where('pr.currency', $currency);
                })
                ->where('p.gid', $g->id)->where('p.hidden', 0)
                ->where('pr.' . $col, '>', 0)->min('pr.' . $col);
        };
        $min = $priceQ('monthly');
        $cycle = 'month';
        if (!$min) {
            $min = $priceQ('annually');
            $cycle = 'year';
        }

        $label = $override[$g->slug] ?? $g->name;
        $labelLc = strtolower($label);
        $icon = 'fas fa-cube';
        foreach ($iconRules as $k => $v) {
            if (strpos($nameLc, $k) !== false || strpos($labelLc, $k) !== false) {
                $icon = $v;
                break;
            }
        }
        $svg = 'servers.svg';
        foreach ($svgRules as $k => $v) {
            if (strpos($nameLc, $k) !== false || strpos($labelLc, $k) !== false) {
                $svg = $v;
                break;
            }
        }

        $cards[] = [
            'label' => $label,
            'icon'  => $icon,
            'svg'   => $svg,
            'url'   => 'index.php/store/' . $g->slug,
            'from'  => $min ? (float) $min : null,
            'cycle' => $cycle,
        ];
    }

    // ---- Extra cards for categories not in the standard product store ----
    // Domains (separate pricing system).
    try {
        $dmin = Capsule::table('tbldomainpricing as d')
            ->join('tblpricing as pr', function ($j) use ($currency) {
                $j->on('pr.relid', '=', 'd.id')->where('pr.type', 'domainregister')->where('pr.currency', $currency);
            })
            ->where('pr.msetupfee', '>', 0)->min('pr.msetupfee');
    } catch (\Exception $e) {
        $dmin = null;
    }
    $cards[] = [
        'label' => 'Domains',
        'icon'  => 'fas fa-globe',
        'svg'   => 'domains.svg',
        'url'   => 'index.php/cart.php?a=add&domain=register',
        'from'  => $dmin ? (float) $dmin : null,
        'cycle' => 'year',
    ];

    // Dedicated Servers + SSL: groups may be hidden from listings but still
    // orderable via their store URL. Add them if they have any products.
    foreach ([
        ['dedicated-servers', 'Dedicated Servers', 'fas fa-hdd', 'dedicated.svg', 'monthly', 'month'],
        ['ssl-certificates', 'SSL Certificates', 'fas fa-lock', 'lock.svg', 'annually', 'year'],
    ] as $ex) {
        list($slug, $label, $icon, $svg, $col, $cyc) = $ex;
        $eg = Capsule::table('tblproductgroups')->where('slug', $slug)->first(['id']);
        if (!$eg) {
            continue;
        }
        // Only feature it if it has at least one VISIBLE product.
        if (!Capsule::table('tblproducts')->where('gid', $eg->id)->where('hidden', 0)->exists()) {
            continue;
        }
        $emin = Capsule::table('tblproducts as p')
            ->join('tblpricing as pr', function ($j) use ($currency) {
                $j->on('pr.relid', '=', 'p.id')->where('pr.type', 'product')->where('pr.currency', $currency);
            })
            ->where('p.gid', $eg->id)->where('p.hidden', 0)->where('pr.' . $col, '>', 0)->min('pr.' . $col);
        $cards[] = [
            'label' => $label,
            'icon'  => $icon,
            'svg'   => $svg,
            'url'   => 'index.php/store/' . $slug,
            'from'  => $emin ? (float) $emin : null,
            'cycle' => $cyc,
        ];
    }

    if (!$cards) {
        return [];
    }

    // Locked display order: flagship VPS first, then the rest. Cards whose URL
    // doesn't match any fragment keep their natural order at the end.
    $priority = [
        'virtual-servers-location-in-germany', 'kvm-virtual-servers', 'vmware-virtual-servers',
        'web-hosting', 'cloudon-storage', 'dedicated-servers', 'collocation', 'voip',
        'domain', 'ssl',
    ];
    $rank = function ($url) use ($priority) {
        foreach ($priority as $i => $frag) {
            if (strpos($url, $frag) !== false) {
                return $i;
            }
        }
        return count($priority) + 1;
    };
    usort($cards, function ($a, $b) use ($rank) {
        return $rank($a['url']) <=> $rank($b['url']);
    });

    // Hero "from €X": cheapest VPS (the hero + CTA are about cloud servers, not
    // storage/domains). Prefer the flagship VPS card, else the cheapest VPS-ish.
    $heroPrice = null;
    foreach ($cards as $c) {
        if (strpos($c['url'], 'virtual-servers-location-in-germany') !== false && $c['from'] !== null) {
            $heroPrice = $c['from'];
            break;
        }
    }
    if ($heroPrice === null) {
        foreach ($cards as $c) {
            if (stripos($c['label'], 'VPS') !== false && $c['from'] !== null
                && ($heroPrice === null || $c['from'] < $heroPrice)) {
                $heroPrice = $c['from'];
            }
        }
    }

    // Datacenter locations from the availability cache (falls back to a default).
    $locations = [];
    try {
        require_once __DIR__ . '/../../modules/addons/hetznercloud/lib/Db.php';
        require_once __DIR__ . '/../../modules/addons/hetznercloud/lib/Sync.php';
        $cache = \WHMCS\Module\Addon\HetznerCloud\Sync::readAvailabilityCache();
        if (!empty($cache['all_cities'])) {
            $locations = $cache['all_cities'];
        }
    } catch (\Throwable $e) {
        // ignore
    }
    if (!$locations) {
        $locations = ['Nuremberg', 'Falkenstein', 'Helsinki'];
    }

    return [
        'hzCategories' => $cards,
        'hzHeroPrice'  => $heroPrice,
        'hzLocations'  => $locations,
    ];
});
