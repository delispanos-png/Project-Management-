<?php
/**
 * Scaleway addon — schema & logging.
 *
 * @package WHMCS\Module\Addon\Scaleway
 */

namespace WHMCS\Module\Addon\Scaleway;

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class Db
{
    const T_MAP = 'mod_scaleway_map';            // product ↔ commercial type mapping (τιμολόγηση)
    const T_LOG = 'mod_scaleway_log';
    const T_PROJECTS = 'mod_scaleway_projects';  // ένα row ανά Scaleway project (credentials)
    const T_INSTANCES = 'mod_scaleway_instances'; // σε ποιο project/zone ζει κάθε VM

    public static function install()
    {
        if (!Capsule::schema()->hasTable(self::T_PROJECTS)) {
            Capsule::schema()->create(self::T_PROJECTS, function ($t) {
                $t->increments('id');
                $t->string('name', 100);
                $t->text('secret_key');                  // WHMCS-encrypted
                $t->string('project_id', 64);            // Scaleway project UUID
                $t->string('zone', 20)->default('fr-par-1');
                $t->boolean('is_primary')->default(false);
                $t->boolean('enabled')->default(true);
                $t->integer('sort')->default(0);
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(self::T_INSTANCES)) {
            Capsule::schema()->create(self::T_INSTANCES, function ($t) {
                $t->increments('id');
                $t->integer('service_id')->unique();      // tblhosting.id
                $t->integer('project_id')->index();       // mod_scaleway_projects.id
                $t->string('server_id', 64)->nullable();  // Scaleway instance UUID
                $t->string('zone', 20)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(self::T_MAP)) {
            Capsule::schema()->create(self::T_MAP, function ($t) {
                $t->increments('id');
                $t->integer('whmcs_pid')->index();
                $t->string('commercial_type', 64);
                $t->string('zone', 20)->nullable();
                $t->integer('project_id')->nullable();
                $t->decimal('markup', 8, 2)->nullable();   // override % ανά αντιστοίχιση
                $t->boolean('include_ipv4')->default(true);
                $t->integer('disk_gb')->default(0);
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
        // Τα δεδομένα διατηρούνται σκόπιμα (απενεργοποίηση ≠ απώλεια αντιστοιχίσεων).
    }

    public static function log($message, $level = 'info')
    {
        try {
            Capsule::table(self::T_LOG)->insert([
                'ts'      => date('Y-m-d H:i:s'),
                'level'   => $level,
                'message' => mb_substr((string) $message, 0, 2000),
            ]);
            // κράτα το log μικρό
            $count = Capsule::table(self::T_LOG)->count();
            if ($count > 800) {
                $cutoff = Capsule::table(self::T_LOG)->orderBy('id', 'desc')
                    ->skip(500)->take(1)->value('id');
                if ($cutoff) {
                    Capsule::table(self::T_LOG)->where('id', '<=', $cutoff)->delete();
                }
            }
        } catch (\Exception $e) {
            // ποτέ μη σπάσεις ροή εξαιτίας logging
        }
    }

    public static function logs($limit = 200)
    {
        try {
            return Capsule::table(self::T_LOG)->orderBy('id', 'desc')->limit($limit)->get();
        } catch (\Exception $e) {
            return [];
        }
    }

    /* ─────────────────────────── Projects ─────────────────────────── */

    public static function projects($enabledOnly = false)
    {
        try {
            $q = Capsule::table(self::T_PROJECTS)->orderBy('sort')->orderBy('id');
            if ($enabledOnly) {
                $q->where('enabled', 1);
            }
            return $q->get();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function project($id)
    {
        try {
            return Capsule::table(self::T_PROJECTS)->where('id', (int) $id)->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function primaryProject()
    {
        try {
            return Capsule::table(self::T_PROJECTS)->where('is_primary', 1)->where('enabled', 1)->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function saveProject(array $data, $id = 0)
    {
        $row = [
            'name'       => mb_substr(trim((string) ($data['name'] ?? '')), 0, 100),
            'project_id' => trim((string) ($data['project_id'] ?? '')),
            'zone'       => trim((string) ($data['zone'] ?? 'fr-par-1')),
            'enabled'    => !empty($data['enabled']) ? 1 : 0,
        ];
        if (!empty($data['secret_key'])) {
            $row['secret_key'] = function_exists('encrypt') ? encrypt($data['secret_key']) : $data['secret_key'];
        }
        if ((int) $id > 0) {
            Capsule::table(self::T_PROJECTS)->where('id', (int) $id)->update($row);
            $id = (int) $id;
        } else {
            $row['created_at'] = date('Y-m-d H:i:s');
            $id = Capsule::table(self::T_PROJECTS)->insertGetId($row);
        }
        if (!empty($data['is_primary'])) {
            Capsule::table(self::T_PROJECTS)->update(['is_primary' => 0]);
            Capsule::table(self::T_PROJECTS)->where('id', $id)->update(['is_primary' => 1]);
        }
        return $id;
    }

    public static function deleteProject($id)
    {
        $inUse = Capsule::table(self::T_INSTANCES)->where('project_id', (int) $id)->count();
        if ($inUse > 0) {
            return 'Το project χρησιμοποιείται από ' . $inUse . ' υπηρεσίες — δεν διαγράφεται.';
        }
        Capsule::table(self::T_PROJECTS)->where('id', (int) $id)->delete();
        return '';
    }

    /** Αποκρυπτογραφημένο secret key ενός project row. */
    public static function projectSecret($row)
    {
        if (!$row) {
            return '';
        }
        $raw = is_object($row) ? ($row->secret_key ?? '') : ($row['secret_key'] ?? '');
        if ($raw === '') {
            return '';
        }
        if (function_exists('decrypt')) {
            $dec = decrypt($raw);
            if ($dec !== '') {
                return trim($dec);
            }
        }
        return trim($raw);
    }

    /* ─────────────────────────── Product mapping ─────────────────────────── */

    public static function mappings()
    {
        try {
            return Capsule::table(self::T_MAP)->orderBy('whmcs_pid')->get();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function mapping($productId)
    {
        try {
            return Capsule::table(self::T_MAP)->where('whmcs_pid', (int) $productId)->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function saveMapping($productId, array $data)
    {
        $row = [
            'commercial_type' => trim((string) ($data['commercial_type'] ?? '')),
            'zone'            => trim((string) ($data['zone'] ?? '')) ?: null,
            'project_id'      => !empty($data['project_id']) ? (int) $data['project_id'] : null,
            'markup'          => ($data['markup'] === '' || $data['markup'] === null) ? null : (float) $data['markup'],
            'include_ipv4'    => !empty($data['include_ipv4']) ? 1 : 0,
            'disk_gb'         => (int) ($data['disk_gb'] ?? 0),
            'updated_at'      => date('Y-m-d H:i:s'),
        ];
        $exists = Capsule::table(self::T_MAP)->where('whmcs_pid', (int) $productId)->exists();
        if ($exists) {
            Capsule::table(self::T_MAP)->where('whmcs_pid', (int) $productId)->update($row);
        } else {
            Capsule::table(self::T_MAP)->insert(array_merge($row, ['whmcs_pid' => (int) $productId]));
        }
    }

    public static function deleteMapping($productId)
    {
        Capsule::table(self::T_MAP)->where('whmcs_pid', (int) $productId)->delete();
    }

    public static function recordPrice($productId, $cost, $price)
    {
        try {
            Capsule::table(self::T_MAP)->where('whmcs_pid', (int) $productId)->update([
                'last_cost'  => (float) $cost,
                'last_price' => (float) $price,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // ignore
        }
    }

    /** Ρύθμιση του addon. */
    public static function setting($key, $default = null)
    {
        try {
            $row = Capsule::table('tbladdonmodules')
                ->where('module', 'scaleway')->where('setting', $key)->first();
            return $row ? $row->value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}
