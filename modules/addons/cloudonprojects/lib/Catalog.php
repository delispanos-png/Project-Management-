<?php
/**
 * CloudOn Project Manager — ο κατάλογος ΠΡΟΪΟΝΤΩΝ και τι έχει ο κάθε πελάτης.
 *
 * Η ραχοκοκαλιά όλης της παρακολούθησης είναι μία και ίδια παντού:
 *
 *     ΠΕΛΑΤΗΣ → ΠΡΟΪΟΝ ΤΟΥ → ΑΝΑΓΚΗ ανά DEPARTMENT → ΕΡΓΟ → εργασίες
 *
 * «Προϊόν» είναι αυτό που ο πελάτης αγόρασε από εμάς και έχει εξέλιξη — PharmacyOne,
 * 3CX, SoftOne. Τα Air Time, Extra Voice channels, DID Numbers ΔΕΝ είναι προϊόντα:
 * είναι υπηρεσίες/παρελκόμενα ΕΠΑΝΩ σε ένα προϊόν (εδώ: στο 3CX). Γι' αυτό ο
 * κατάλογος είναι δικός μας και από κάτω χαρτογραφούνται οι ομάδες προϊόντων του WHMCS.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Catalog
{
    /**
     * Ο προεπιλεγμένος κατάλογος: όνομα, χρώμα, και με ποιες ομάδες προϊόντων του
     * WHMCS ταιριάζει (υποσυμβολοσειρές, πεζά/κεφαλαία αδιάφορα). Ο χρήστης μπορεί
     * να τα αλλάξει όλα από την οθόνη — εδώ είναι μόνο η αφετηρία.
     */
    public static function defaults()
    {
        return [
            ['PharmacyOne',          '#0090dd', ['pharmacyone']],
            ['RxVision',             '#7b5cd6', ['rxvision']],
            ['BoxVisio',             '#00a8ff', ['box visio', 'boxvisio']],
            ['SoftOne / ERP',        '#3b9ae0', ['softone']],
            ['CarOn',                '#16a26a', ['caron']],
            ['3CX / Τηλεφωνία',      '#7b5cd6', ['3cx', 'vpbx', 'voip']],
            ['VPS / Server',         '#2dbd6e', ['virtual server', 'dedicated server', 'collocation',
                'storage', 'backup', 'bandwidth', 'ip address']],
            ['Website / Hosting',    '#e2a33c', ['web hosting', 'shopster']],
            ['Email / Mail server',  '#0090dd', ['mail', 'email']],
            ['Domain / DNS',         '#8595ac', ['domain']],
            ['SSL',                  '#16a26a', ['ssl']],
            ['Δίκτυο / Firewall',    '#e2515f', ['firewall']],
            ['Τεχνική υποστήριξη',   '#6b7a90', ['support', 'υποστήριξ', 'licenses', 'api develop']],
        ];
    }

    /* ══════════════ σχήμα ══════════════ */

    /** Προσθετικό και επαναλήψιμο — τρέχει από το Db::install(). */
    public static function install()
    {
        $s = Capsule::schema();

        if (!$s->hasTable('mod_cpm_products')) {
            $s->create('mod_cpm_products', function ($t) {
                $t->increments('id');
                $t->string('name', 80);
                $t->string('color', 7)->default('#0090dd');
                $t->integer('sort')->default(0);
                $t->tinyInteger('active')->default(1);
                $t->integer('dept_id')->unsigned()->nullable();   // ποιο τμήμα το «κατέχει»
                $t->integer('team_id')->unsigned()->nullable();   // ποια ομάδα το τρέχει
                $t->integer('area_id')->unsigned()->nullable();   // η παλιά κατηγορία ticket
                $t->text('descr')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        /* Ποιες ομάδες/προϊόντα του WHMCS ανήκουν σε ποιο δικό μας προϊόν. */
        if (!$s->hasTable('mod_cpm_product_whmcs')) {
            $s->create('mod_cpm_product_whmcs', function ($t) {
                $t->increments('id');
                $t->integer('product_id')->unsigned()->index();
                $t->integer('gid')->unsigned()->nullable();       // ομάδα προϊόντων WHMCS
                $t->integer('pid')->unsigned()->nullable();       // ή συγκεκριμένο προϊόν
            });
        }

        /* Τι έχει ο πελάτης. Προκύπτει από τις ενεργές υπηρεσίες του, αλλά μπορεί και
           να δηλωθεί με το χέρι (π.χ. έργο που τρέχει πριν βγει η χρέωση). */
        if (!$s->hasTable('mod_cpm_client_products')) {
            $s->create('mod_cpm_client_products', function ($t) {
                $t->increments('id');
                $t->integer('clientid')->unsigned()->index();
                $t->integer('product_id')->unsigned()->index();
                $t->string('source', 8)->default('auto');         // auto | manual
                $t->string('status', 10)->default('active');      // active | past
                $t->integer('services')->unsigned()->default(0);  // πόσες ενεργές υπηρεσίες
                $t->text('note')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->unique(['clientid', 'product_id'], 'cp_uniq');
            });
        }

        /* Το προϊόν γίνεται διάσταση παντού όπου υπάρχει δουλειά. */
        foreach ([['mod_cpm_projects', 'product_id'], ['mod_cpm_tasks', 'product_id'],
                  ['mod_cpm_offers', 'product_id'], ['mod_cpm_ticket_class', 'product_id']] as $c) {
            if ($s->hasTable($c[0]) && !$s->hasColumn($c[0], $c[1])) {
                $s->table($c[0], function ($t) use ($c) {
                    $t->integer($c[1])->unsigned()->nullable()->index();
                });
            }
        }
    }

    /* ══════════════ κατάλογος ══════════════ */

    /** Γεμίζει τον κατάλογο την πρώτη φορά και χαρτογραφεί τις ομάδες του WHMCS. */
    public static function seed()
    {
        if (Capsule::table('mod_cpm_products')->count()) { return 0; }
        $areas = Capsule::table('mod_cpm_ticket_cats')->where('kind', 'area')->get(['id', 'name']);
        $groups = Capsule::table('tblproductgroups')->get(['id', 'name']);
        $n = 0;
        foreach (self::defaults() as $i => $d) {
            [$name, $color, $needles] = $d;
            $area = null;
            foreach ($areas as $a) {                                  // δέσε με την παλιά κατηγορία
                if (self::alike($a->name, $name)) { $area = $a->id; break; }
            }
            $pid = Capsule::table('mod_cpm_products')->insertGetId([
                'name' => $name, 'color' => $color, 'sort' => ($i + 1) * 10, 'active' => 1,
                'area_id' => $area, 'created_at' => date('Y-m-d H:i:s')]);
            foreach ($groups as $g) {
                if (self::matches($g->name, $needles)) {
                    Capsule::table('mod_cpm_product_whmcs')->insert(['product_id' => $pid, 'gid' => $g->id]);
                }
            }
            $n++;
        }
        return $n;
    }

    /** Χαλαρή σύγκριση ονομάτων (αγνοεί πεζά, τόνους-σύμβολα και κενά). */
    private static function alike($a, $b)
    {
        $norm = function ($s) {
            $s = mb_strtolower(html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8'), 'UTF-8');
            return trim(preg_replace('/[^a-zα-ω0-9]+/u', ' ', $s));
        };
        $a = $norm($a); $b = $norm($b);
        return $a === $b || mb_strpos($a, $b) !== false || mb_strpos($b, $a) !== false;
    }

    private static function matches($groupName, array $needles)
    {
        $n = mb_strtolower(html_entity_decode((string) $groupName, ENT_QUOTES, 'UTF-8'), 'UTF-8');
        foreach ($needles as $x) {
            if (mb_strpos($n, mb_strtolower($x, 'UTF-8')) !== false) { return true; }
        }
        return false;
    }

    /** Ο κατάλογος, έτοιμος για την οθόνη. */
    public static function products($onlyActive = true)
    {
        $q = Capsule::table('mod_cpm_products')->orderBy('sort')->orderBy('name');
        if ($onlyActive) { $q->where('active', 1); }
        return $q->get()->map(function ($p) { return (array) $p; })->all();
    }

    /** WHMCS προϊόν (packageid) → δικό μας προϊόν. Χάρτης σε μνήμη, μία φορά. */
    public static function mapByPackage()
    {
        static $memo = null;
        if ($memo !== null) { return $memo; }
        $memo = [];
        $rows = Capsule::table('mod_cpm_product_whmcs')->get();
        $byGid = []; $byPid = [];
        foreach ($rows as $r) {
            if ($r->pid) { $byPid[(int) $r->pid] = (int) $r->product_id; }
            elseif ($r->gid) { $byGid[(int) $r->gid] = (int) $r->product_id; }
        }
        foreach (Capsule::table('tblproducts')->get(['id', 'gid']) as $p) {
            $memo[(int) $p->id] = $byPid[(int) $p->id] ?? ($byGid[(int) $p->gid] ?? null);
        }
        return $memo;
    }

    /**
     * Τα προϊόντα ΕΝΟΣ πελάτη, όπως προκύπτουν από τις υπηρεσίες του.
     *
     * @return array<int,array{product_id:int,name:string,color:string,services:int,active:int}>
     */
    public static function clientProductsLive($clientId)
    {
        $map = self::mapByPackage();
        $out = [];
        $rows = Capsule::table('tblhosting')->where('userid', (int) $clientId)
            ->get(['id', 'packageid', 'domainstatus']);
        foreach ($rows as $h) {
            $pid = $map[(int) $h->packageid] ?? null;
            if (!$pid) { continue; }
            if (!isset($out[$pid])) { $out[$pid] = ['product_id' => $pid, 'services' => 0, 'active' => 0]; }
            $out[$pid]['services']++;
            if ($h->domainstatus === 'Active') { $out[$pid]['active']++; }
        }
        $names = Capsule::table('mod_cpm_products')->get(['id', 'name', 'color'])->keyBy('id');
        foreach ($out as $pid => &$r) {
            $r['name'] = $names[$pid]->name ?? ('#' . $pid);
            $r['color'] = $names[$pid]->color ?? '#8595ac';
        }
        unset($r);
        uasort($out, function ($a, $b) { return $b['active'] <=> $a['active'] ?: strcmp($a['name'], $b['name']); });
        return array_values($out);
    }

    /**
     * Δίνει προϊόν στα tickets που έχουν δηλωμένη υπηρεσία WHMCS. Το ticket ήδη ξέρει
     * πελάτη και τμήμα — αυτό συμπληρώνει το τρίτο σκέλος χωρίς να ρωτήσει κανέναν.
     */
    public static function backfillTickets($dry = true)
    {
        $map = self::mapByPackage();
        $n = 0;
        foreach (Capsule::table('tbltickets')->where('service', 'like', 'S%')->get(['id', 'service']) as $t) {
            $pk = Capsule::table('tblhosting')->where('id', (int) substr($t->service, 1))->value('packageid');
            $pid = $pk ? ($map[(int) $pk] ?? null) : null;
            if (!$pid) { continue; }
            $ex = Capsule::table('mod_cpm_ticket_class')->where('ticketid', $t->id)->first();
            if ($ex && $ex->product_id) { continue; }
            $n++;
            if ($dry) { continue; }
            if ($ex) {
                Capsule::table('mod_cpm_ticket_class')->where('ticketid', $t->id)->update(['product_id' => $pid]);
            } else {
                Capsule::table('mod_cpm_ticket_class')->insert(['ticketid' => $t->id, 'product_id' => $pid]);
            }
        }
        return $n;
    }

    /**
     * Τα υπάρχοντα έργα κρατούν το όνομά τους· απλώς δένουν με το προϊόν όταν αυτό
     * προκύπτει καθαρά από το όνομα. Ό,τι δεν είναι σαφές μένει για το χέρι.
     *
     * @return array<string,string> id έργου => όνομα προϊόντος (ή '' όταν δεν βρέθηκε)
     */
    public static function backfillProjects($dry = true)
    {
        $out = [];
        $prods = Capsule::table('mod_cpm_products')->get(['id', 'name']);
        $needles = [];
        foreach (self::defaults() as $d) { $needles[$d[0]] = array_merge([$d[0]], $d[2]); }
        foreach (Capsule::table('mod_cpm_projects')->get(['id', 'name', 'product_id']) as $p) {
            if ($p->product_id) { continue; }
            $hit = null;
            foreach ($prods as $pr) {
                if (self::matches($p->name, $needles[$pr->name] ?? [$pr->name])) { $hit = $pr; break; }
            }
            $out[$p->id] = $hit ? $hit->name : '';
            if ($hit && !$dry) {
                Capsule::table('mod_cpm_projects')->where('id', $p->id)->update(['product_id' => $hit->id]);
            }
        }
        return $out;
    }

    /**
     * Κλείνει το κενό «έργο σε προϊόν που δεν φιγουράρει στον πελάτη». Συμβαίνει
     * νόμιμα: πουλήσαμε PharmacyOne και η υλοποίηση τρέχει πριν βγει η χρέωση. Χωρίς
     * αυτό το έργο θα ήταν αόρατο στην καρτέλα του πελάτη.
     *
     * @return int πόσες αναθέσεις προστέθηκαν
     */
    public static function reconcile($dry = true)
    {
        $pairs = [];
        foreach (Capsule::table('mod_cpm_projects')->whereNotNull('product_id')
            ->whereNotNull('clientid')->get(['clientid', 'product_id']) as $r) {
            $pairs[(int) $r->clientid . ':' . (int) $r->product_id] = [(int) $r->clientid, (int) $r->product_id];
        }
        foreach (Capsule::table('mod_cpm_ticket_class as tc')
            ->join('tbltickets as t', 't.id', '=', 'tc.ticketid')
            ->whereNotNull('tc.product_id')->where('t.userid', '>', 0)
            ->get(['t.userid', 'tc.product_id']) as $r) {
            $pairs[(int) $r->userid . ':' . (int) $r->product_id] = [(int) $r->userid, (int) $r->product_id];
        }
        $n = 0;
        foreach ($pairs as [$cid, $pid]) {
            $ex = Capsule::table('mod_cpm_client_products')
                ->where('clientid', $cid)->where('product_id', $pid)->first();
            if ($ex) { continue; }
            $n++;
            if ($dry) { continue; }
            Capsule::table('mod_cpm_client_products')->insert(['clientid' => $cid, 'product_id' => $pid,
                'source' => 'manual', 'status' => 'active', 'services' => 0,
                'note' => 'προέκυψε από έργο/ticket', 'created_at' => date('Y-m-d H:i:s')]);
        }
        return $n;
    }

    /** Συγχρονίζει τον πίνακα «τι έχει ο πελάτης» από τις υπηρεσίες του WHMCS. */
    public static function syncClientProducts($clientId = null)
    {
        $ids = $clientId ? [(int) $clientId]
            : Capsule::table('tblhosting')->distinct()->pluck('userid')->all();
        $n = 0;
        foreach ($ids as $cid) {
            foreach (self::clientProductsLive($cid) as $p) {
                $row = ['clientid' => (int) $cid, 'product_id' => $p['product_id'],
                    'services' => $p['active'], 'status' => $p['active'] > 0 ? 'active' : 'past'];
                $ex = Capsule::table('mod_cpm_client_products')
                    ->where('clientid', $cid)->where('product_id', $p['product_id'])->first();
                if ($ex) {
                    if ($ex->source !== 'manual') { Capsule::table('mod_cpm_client_products')->where('id', $ex->id)->update($row); }
                } else {
                    Capsule::table('mod_cpm_client_products')->insert($row + ['source' => 'auto',
                        'created_at' => date('Y-m-d H:i:s')]);
                    $n++;
                }
            }
        }
        return $n;
    }
}
