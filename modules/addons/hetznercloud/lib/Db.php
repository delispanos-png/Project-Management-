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
    const T_PROJECTS = 'mod_hetzner_projects';    // one row per Hetzner project (token)
    const T_INSTANCES = 'mod_hetzner_instances';  // which project a provisioned VM lives in

    public static function install()
    {
        if (!Capsule::schema()->hasTable(self::T_PROJECTS)) {
            Capsule::schema()->create(self::T_PROJECTS, function ($t) {
                $t->increments('id');
                $t->string('name', 100);
                $t->text('api_token');                 // WHMCS-encrypted (encrypt()/decrypt())
                $t->boolean('is_primary')->default(false);
                $t->boolean('enabled')->default(true);
                $t->integer('sort')->default(0);
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(self::T_INSTANCES)) {
            Capsule::schema()->create(self::T_INSTANCES, function ($t) {
                $t->increments('id');
                $t->integer('service_id')->unique();   // tblhosting.id
                $t->integer('project_id')->index();    // mod_hetzner_projects.id
                $t->unsignedBigInteger('server_id')->nullable(); // Hetzner server id
                $t->timestamp('created_at')->nullable();
            });
        }

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

        // Per-product project override (empty = use primary).
        if (Capsule::schema()->hasTable(self::T_MAP)
            && !Capsule::schema()->hasColumn(self::T_MAP, 'project_id')) {
            Capsule::schema()->table(self::T_MAP, function ($t) {
                $t->integer('project_id')->nullable()->after('whmcs_pid');
            });
        }

        self::migrateSeedPrimary();
    }

    /**
     * One-time seed: if no projects exist yet but the legacy single addon
     * api_token is set, adopt it as the "Primary" project so the existing
     * single-project setup keeps working unchanged.
     */
    public static function migrateSeedPrimary()
    {
        try {
            if (!Capsule::schema()->hasTable(self::T_PROJECTS)) {
                return;
            }
            if (Capsule::table(self::T_PROJECTS)->count() > 0) {
                return;
            }
            $row = Capsule::table('tbladdonmodules')
                ->where('module', 'hetznercloud')->where('setting', 'api_token')->first();
            $tok = $row ? trim((string) $row->value) : '';
            if ($tok === '') {
                return;
            }
            Capsule::table(self::T_PROJECTS)->insert([
                'name'       => 'Primary',
                'api_token'  => $tok,          // stored exactly as the addon kept it (encrypted or plain)
                'is_primary' => 1,
                'enabled'    => 1,
                'sort'       => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // never break activation/output
        }
    }

    /* ---------------------------------------------------------------- */
    /* Projects                                                         */
    /* ---------------------------------------------------------------- */

    public static function projects()
    {
        return Capsule::table(self::T_PROJECTS)
            ->orderBy('is_primary', 'desc')->orderBy('sort')->orderBy('id')->get();
    }

    public static function enabledProjects()
    {
        return Capsule::table(self::T_PROJECTS)->where('enabled', 1)
            ->orderBy('is_primary', 'desc')->orderBy('sort')->orderBy('id')->get();
    }

    public static function project($id)
    {
        return Capsule::table(self::T_PROJECTS)->where('id', (int) $id)->first();
    }

    /** The project new orders default to (primary, else first enabled). */
    public static function primaryProject()
    {
        $p = Capsule::table(self::T_PROJECTS)->where('is_primary', 1)->where('enabled', 1)->first();
        if ($p) {
            return $p;
        }
        return Capsule::table(self::T_PROJECTS)->where('enabled', 1)
            ->orderBy('sort')->orderBy('id')->first();
    }

    public static function addProject($name, $encToken)
    {
        $first = Capsule::table(self::T_PROJECTS)->count() === 0;
        return Capsule::table(self::T_PROJECTS)->insertGetId([
            'name'       => mb_substr(trim($name), 0, 100),
            'api_token'  => $encToken,
            'is_primary' => $first ? 1 : 0,   // first project added becomes primary
            'enabled'    => 1,
            'sort'       => (int) Capsule::table(self::T_PROJECTS)->max('sort') + 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function updateProject($id, array $data)
    {
        Capsule::table(self::T_PROJECTS)->where('id', (int) $id)->update($data);
    }

    public static function deleteProject($id)
    {
        Capsule::table(self::T_PROJECTS)->where('id', (int) $id)->delete();
    }

    /** Make one project the primary (exactly one primary at a time). */
    public static function setPrimary($id)
    {
        Capsule::table(self::T_PROJECTS)->update(['is_primary' => 0]);
        Capsule::table(self::T_PROJECTS)->where('id', (int) $id)->update(['is_primary' => 1, 'enabled' => 1]);
    }

    /* ---------------------------------------------------------------- */
    /* Instances (service ↔ project ↔ Hetzner server)                   */
    /* ---------------------------------------------------------------- */

    public static function instanceForService($serviceId)
    {
        return Capsule::table(self::T_INSTANCES)->where('service_id', (int) $serviceId)->first();
    }

    public static function saveInstance($serviceId, $projectId, $serverId = null)
    {
        $serviceId = (int) $serviceId;
        $data = ['project_id' => (int) $projectId, 'server_id' => $serverId ? (int) $serverId : null];
        if (Capsule::table(self::T_INSTANCES)->where('service_id', $serviceId)->exists()) {
            Capsule::table(self::T_INSTANCES)->where('service_id', $serviceId)->update($data);
        } else {
            Capsule::table(self::T_INSTANCES)->insert(array_merge($data, [
                'service_id' => $serviceId, 'created_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }

    /** Resolve the project row a service belongs to (its instance, else primary). */
    public static function projectForService($serviceId)
    {
        $inst = self::instanceForService($serviceId);
        if ($inst && $inst->project_id) {
            $p = self::project($inst->project_id);
            if ($p) {
                return $p;
            }
        }
        return self::primaryProject();
    }

    public static function instanceCountByProject()
    {
        $out = [];
        foreach (Capsule::table(self::T_INSTANCES)->select('project_id')
                     ->selectRaw('COUNT(*) c')->groupBy('project_id')->get() as $r) {
            $out[(int) $r->project_id] = (int) $r->c;
        }
        return $out;
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
