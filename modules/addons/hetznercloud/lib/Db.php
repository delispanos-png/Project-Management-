<?php
/**
 * Schema management for the Hetzner Cloud addon.
 *
 * @package WHMCS\Module\Addon\HetznerCloud
 */

namespace WHMCS\Module\Addon\HetznerCloud;

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class Db
{
    const T_MAP = 'mod_hetzner_map';
    const T_LOG = 'mod_hetzner_log';

    public static function install()
    {
        if (!Capsule::schema()->hasTable(self::T_MAP)) {
            Capsule::schema()->create(self::T_MAP, function ($t) {
                $t->increments('id');
                $t->integer('whmcs_pid')->index();       // WHMCS product id
                $t->string('server_type', 64);           // e.g. cx22 ; or storagebox type
                $t->string('kind', 20)->default('server'); // server|storagebox
                $t->string('location', 32)->nullable();  // price varies per location
                $t->decimal('markup', 8, 2)->nullable(); // per-mapping override %
                $t->boolean('include_ipv4')->default(true);
                $t->boolean('include_backup')->default(false);
                $t->decimal('last_cost', 12, 4)->default(0);
                $t->decimal('last_price', 12, 4)->default(0);
                $t->timestamp('updated_at')->nullable();
                $t->unique(['whmcs_pid']);
            });
        }

        if (!Capsule::schema()->hasTable(self::T_LOG)) {
            Capsule::schema()->create(self::T_LOG, function ($t) {
                $t->increments('id');
                $t->timestamp('ts')->nullable();
                $t->string('level', 12)->default('info');
                $t->text('message');
            });
        }
    }

    public static function uninstall()
    {
        // Deliberately keep tables on deactivate to avoid data loss.
        // Manual drop only.
    }

    public static function log($message, $level = 'info')
    {
        try {
            Capsule::table(self::T_LOG)->insert([
                'ts'      => Capsule::raw('NOW()'),
                'level'   => $level,
                'message' => is_string($message) ? $message : json_encode($message),
            ]);
        } catch (\Exception $e) {
            // logging must never break the caller
        }
    }

    public static function recentLogs($limit = 40)
    {
        try {
            return Capsule::table(self::T_LOG)->orderBy('id', 'desc')->limit($limit)->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    public static function mappings()
    {
        return Capsule::table(self::T_MAP)->get();
    }

    public static function mappingForProduct($pid)
    {
        return Capsule::table(self::T_MAP)->where('whmcs_pid', (int) $pid)->first();
    }

    public static function saveMapping(array $data)
    {
        $pid = (int) $data['whmcs_pid'];
        $data['updated_at'] = date('Y-m-d H:i:s');
        if (Capsule::table(self::T_MAP)->where('whmcs_pid', $pid)->exists()) {
            Capsule::table(self::T_MAP)->where('whmcs_pid', $pid)->update($data);
        } else {
            Capsule::table(self::T_MAP)->insert($data);
        }
    }

    public static function deleteMapping($pid)
    {
        Capsule::table(self::T_MAP)->where('whmcs_pid', (int) $pid)->delete();
    }

    /**
     * Store just the (optional) type + markup override for a product, leaving
     * cached prices intact. Empty type/null markup mean "auto from product".
     */
    public static function saveTypeOverride($pid, $type, $markup)
    {
        $pid = (int) $pid;
        $data = [
            'server_type' => (string) $type,
            'markup'      => ($markup === null || $markup === '') ? null : (float) $markup,
            'kind'        => 'server',
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        if (Capsule::table(self::T_MAP)->where('whmcs_pid', $pid)->exists()) {
            Capsule::table(self::T_MAP)->where('whmcs_pid', $pid)->update($data);
        } else {
            Capsule::table(self::T_MAP)->insert(array_merge($data, [
                'whmcs_pid' => $pid, 'include_ipv4' => 1, 'last_cost' => 0, 'last_price' => 0,
            ]));
        }
    }

    /**
     * Cache the last computed cost/price for a product without disturbing the
     * type/markup override.
     */
    public static function touchPrices($pid, $cost, $price)
    {
        $pid = (int) $pid;
        $data = ['last_cost' => $cost, 'last_price' => $price, 'updated_at' => date('Y-m-d H:i:s')];
        if (Capsule::table(self::T_MAP)->where('whmcs_pid', $pid)->exists()) {
            Capsule::table(self::T_MAP)->where('whmcs_pid', $pid)->update($data);
        } else {
            Capsule::table(self::T_MAP)->insert(array_merge($data, [
                'whmcs_pid' => $pid, 'server_type' => '', 'kind' => 'server', 'include_ipv4' => 1,
            ]));
        }
    }
}
