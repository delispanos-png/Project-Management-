<?php
/**
 * Assigns $hzAvail — the list of product-group slugs that have at least one
 * VISIBLE (non-hidden) product — to every client-area page, so the sidebar
 * menu (menu.tpl) can auto-hide categories with no active service (e.g. 3CX).
 * Fail-open: if this list is empty/unavailable, menu.tpl shows everything.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ClientAreaPage', 1, function ($vars) {
    try {
        $slugs = Capsule::table('tblproductgroups as g')
            ->whereExists(function ($q) {
                $q->select(Capsule::raw(1))->from('tblproducts as p')
                    ->whereColumn('p.gid', 'g.id')->where('p.hidden', 0);
            })
            ->pluck('slug')->filter()->values()->all();
    } catch (\Exception $e) {
        $slugs = [];
    }
    return ['hzAvail' => $slugs];
});
