<?php
/**
 * Category icons for the client "My Services" list — instead of a generic
 * server icon on every row, pick an icon that matches the service category
 * (VPS/VM, hosting, pharmacy, VoIP/3CX, firewall, SSL, domain, backup…).
 * Provides $hzSvcIcons (serviceid → FA icon class) to clientareaproducts.
 * SELECT-only, upgrade-safe.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

if (!function_exists('hz_service_icon_for')) {
    function hz_service_icon_for($name, $servertype = '')
    {
        $n = function_exists('mb_strtolower') ? mb_strtolower((string) $name) : strtolower((string) $name);
        // server module wins → it is definitely a VM
        if ($servertype === 'hetznercloud') {
            return 'fa-server';
        }
        $rules = [
            'fa-server'                 => '\bvps\b|\bvm\b|cloud server|virtual server|cpx|ccx|\bcx\d|proxmox|vmware|dedicated server|\bax\d|\bex\d|\bpx\d|\bsx\d',
            'fa-prescription-bottle-alt'=> 'pharmacy|φαρμ',
            'fa-briefcase'              => 'softone|soft1|\berp\b',
            'fa-phone-alt'              => '3cx|voip|trunk|\bdid\b|channel|cli number|air time|number portab|voice|\bpbx\b',
            'fa-shield-alt'             => 'firewall|\bfw\b',
            'fa-lock'                   => '\bssl\b|certificate|comodo|geotrust|rapidssl|secure site|quickssl',
            'fa-globe'                  => 'domain',
            'fa-window-maximize'        => 'plesk|cpanel|hosting|web admin|web pro|web host',
            'fa-database'               => 'backup|storage|owncloud|replication',
            'fa-chart-bar'              => 'boxvisio|analytics|\bbi\b',
            'fa-car'                    => 'caron',
            'fa-shopping-cart'          => 'shopster|ecommerce|\bshop\b',
            'fa-life-ring'              => 'support|υποστ|maintenance|τεχνικ|συντηρ',
            'fa-network-wired'          => 'ip address|extra ip|network|interconnection|διασυνδ',
            'fa-tachometer-alt'         => 'bandwidth',
            'fa-bolt'                   => '\bpower\b|kwhr',
            'fa-file-invoice'           => 'my data|setup|\bfee\b|\bcost\b|abuse',
        ];
        foreach ($rules as $icon => $pattern) {
            if (preg_match('/' . $pattern . '/u', $n)) {
                return $icon;
            }
        }
        return 'fa-cube';
    }
}

add_hook('ClientAreaPage', 1, function ($vars) {
    if (($vars['templatefile'] ?? '') !== 'clientareaproducts') {
        return [];
    }
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        return [];
    }
    try {
        $rows = Capsule::table('tblhosting as h')
            ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->where('h.userid', $uid)
            ->get(['h.id', 'p.name', 'p.servertype']);
    } catch (\Throwable $e) {
        return [];
    }
    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r->id] = hz_service_icon_for($r->name, $r->servertype);
    }
    return ['hzSvcIcons' => $map];
});
