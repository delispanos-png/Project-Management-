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
            /* Προστέθηκαν 22/09/2026: υπήρχαν ΜΟΝΟ στον τηλεφωνικό κατάλογο, ενώ
               είναι ξεχωριστές ειδικότητες — άλλος ξέρει e-commerce και άλλος
               hosting. Χωρίς αυτά η δρομολόγηση θα τα έστελνε όλα στο ίδιο. */
            ['E-Commerce',           '#e2a33c', ['ecommerce', 'e-commerce', 'eshop']],
            ['Marketplace',          '#c2701a', ['marketplace', 'skroutz', 'bestprice']],
            ['Courier module',       '#8595ac', ['courier', 'voucher']],
        ];
    }

    /**
     * ΓΕΦΥΡΑ: το slug του ΤΗΛΕΦΩΝΙΚΟΥ ΚΑΤΑΛΟΓΟΥ → το προϊόν του καταλόγου μας.
     *
     * Υπάρχουν δύο λεξιλόγια και δεν ταυτίζονται: ο κατάλογος (Pbx3cx\Route::PRODUCTS)
     * φτιάχτηκε για τη ΔΡΟΜΟΛΟΓΗΣΗ ΚΛΗΣΕΩΝ, ο δικός μας για τη ΧΡΕΩΣΗ. Δύο slug
     * μπορούν να δείχνουν στο ίδιο προϊόν (PharmacyOne GR/CY), και το «cloud»
     * είναι ομπρέλα που πέφτει στο VPS/Server.
     *
     * Ταιριάζει με ΟΝΟΜΑ, όχι με id: τα id του καταλόγου δεν είναι σταθερά.
     */
    const BOOK_MAP = [
        'cloud'          => 'VPS / Server',
        'softone'        => 'SoftOne / ERP',
        'pharmacyone_gr' => 'PharmacyOne',
        'pharmacyone_cy' => 'PharmacyOne',
        '3cx'            => '3CX / Τηλεφωνία',
        'yeastar'        => '3CX / Τηλεφωνία',
        'caron'          => 'CarOn',
        'rxvision'       => 'RxVision',
        'boxvisio'       => 'BoxVisio',
        'ecommerce'      => 'E-Commerce',
        'marketplace'    => 'Marketplace',
        'courier'        => 'Courier module',
    ];

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

        /* ΤΟ ΠΡΟΪΟΝ ΞΕΡΕΙ ΠΟΥ ΔΡΟΜΟΛΟΓΕΙΤΑΙ. Πριν, η αντιστοίχιση προϊόν→ουρά 3CX
           ήταν σκληρή λίστα στον κώδικα (Pbx3cx\Route::PRODUCTS) — οπότε κάθε νέο
           προϊόν απαιτούσε αλλαγή κώδικα για να φανεί στον τηλεφωνικό κατάλογο.
           Τώρα ζει δίπλα στο προϊόν και ορίζεται από την οθόνη. */
        foreach (['route_dn' => 20, 'book_key' => 40] as $col => $len) {
            if ($s->hasTable('mod_cpm_products') && !$s->hasColumn('mod_cpm_products', $col)) {
                $s->table('mod_cpm_products', function ($t) use ($col, $len) {
                    $t->string($col, $len)->nullable();
                });
            }
        }

        /* ΥΠΟΚΑΤΗΓΟΡΙΕΣ. Ένα προϊόν δεν είναι μονοκόμματο: το PharmacyOne έχει
           συνταγογράφηση, αποθήκη, παραγγελίες — και δεν τα ξέρει ο ίδιος
           άνθρωπος. Δέντρο ΕΝΟΣ επιπέδου: προϊόν → υποκατηγορία, και τέλος.
           Δύο επίπεδα αρκούν και μένουν διαβάσιμα· τρία γίνονται λαβύρινθος.
           Η υποκατηγορία κληρονομεί τη δρομολόγηση του γονέα αν δεν την ορίσει. */
        if ($s->hasTable('mod_cpm_products') && !$s->hasColumn('mod_cpm_products', 'parent_id')) {
            $s->table('mod_cpm_products', function ($t) {
                $t->integer('parent_id')->unsigned()->nullable()->index();
            });
        }

        /* Τα τρία προϊόντα του e-commerce προστέθηκαν αργότερα από τα υπόλοιπα.
           Το seed() τρέχει ΜΟΝΟ σε άδειο κατάλογο, οπότε χωρίς αυτό δεν θα
           έμπαιναν ποτέ σε εγκατάσταση που ήδη δούλευε. Μπαίνουν ΟΝΟΜΑΣΤΙΚΑ και
           μόνο αν λείπουν — ώστε να μην ξαναγυρίζουν αν τα σβήσει κάποιος. */
        if ($s->hasTable('mod_cpm_products') && Capsule::table('mod_cpm_products')->count()) {
            foreach (['E-Commerce' => '#e2a33c', 'Marketplace' => '#c2701a',
                      'Courier module' => '#8595ac'] as $nm => $col) {
                if (Capsule::table('mod_cpm_products')->where('name', $nm)->exists()) { continue; }
                if (Capsule::table('mod_cpm_products')->where('name', 'like', '%' . $nm . '%')->exists()) { continue; }
                Capsule::table('mod_cpm_products')->insert([
                    'name' => $nm, 'color' => $col, 'sort' => 900, 'active' => 1,
                    'created_at' => date('Y-m-d H:i:s')]);
            }
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

    /* ══════════════ μία λίστα, παντού ══════════════ */

    /**
     * ΕΝΟΠΟΙΗΣΗ (22/09/2026). Υπήρχαν ΤΡΕΙΣ λίστες προϊόντων που απέκλιναν:
     *
     *   1. `mod_cpm_ticket_cats` kind=area — αυτή που βλέπει ο χρήστης στις
     *      Ρυθμίσεις· τη χρησιμοποιούν tickets και βάση γνώσης.
     *   2. `mod_cpm_products` — ο κατάλογος χρέωσης.
     *   3. `Pbx3cx\Route::PRODUCTS` — σκληρή λίστα στον κώδικα για τις ουρές.
     *
     * Αποτέλεσμα: «E-commerce» και «E-Commerce», «Marketplaces» και
     * «Marketplace» — ίδιο πράγμα, τρία ονόματα, καμία κοινή ταυτότητα.
     *
     * Κύρια λίστα γίνεται το `mod_cpm_products`: μόνο αυτό σηκώνει
     * υποκατηγορίες, δρομολόγηση και αντιστοίχιση με WHMCS. Οι «περιοχές»
     * μένουν και συντηρούνται ΑΠΟ ΕΔΩ, ώστε tickets και βάση γνώσης να μη
     * χάσουν τα id τους.
     *
     * Ταιριάζει με ΧΑΛΑΡΟ όνομα (πεζά, χωρίς κενά/παύλες/τόνους), ώστε
     * «Courier modul» και «Courier module» να θεωρηθούν το ίδιο.
     *
     * @return array{linked:int,products:int,areas:int,merged:int}
     */
    public static function unify($dry = true)
    {
        $out = ['linked' => 0, 'products' => 0, 'areas' => 0, 'merged' => 0];
        $key = function ($n) {
            $n = mb_strtolower(trim((string) $n));
            $n = strtr($n, ['ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο',
                            'ύ' => 'υ', 'ώ' => 'ω', 'ϊ' => 'ι', 'ϋ' => 'υ']);
            $n = preg_replace('/(module?s?|modul)$/u', '', $n);   // modul/module/modules
            $n = preg_replace('/s$/u', '', $n);                    // ενικός/πληθυντικός
            return preg_replace('/[^a-zα-ω0-9]/u', '', $n);
        };

        $areas = Capsule::table('mod_cpm_ticket_cats')->where('kind', 'area')
            ->orderBy('sort')->get(['id', 'name', 'color', 'sort']);
        $prods = Capsule::table('mod_cpm_products')->orderBy('sort')
            ->get(['id', 'name', 'color', 'sort', 'area_id']);

        $pByKey = [];
        foreach ($prods as $p) {
            $k = $key($p->name);
            /* Διπλότυπο προϊόν: κρατάμε το ΠΑΛΑΙΟΤΕΡΟ (μικρότερο id) — έχει τα
               δεδομένα επάνω του — και το νεότερο σβήνεται αν είναι αχρησιμοποίητο. */
            if (isset($pByKey[$k])) {
                $keep = $pByKey[$k]; $drop = $p;
                if ((int) $drop->id < (int) $keep->id) { [$keep, $drop] = [$drop, $keep]; }
                $used = Capsule::table('mod_cpm_tasks')->where('product_id', $drop->id)->count()
                    + Capsule::table('mod_cpm_client_products')->where('product_id', $drop->id)->count()
                    + Capsule::table('mod_cpm_agent_skills')->where('product_id', $drop->id)->count();
                $out['merged']++;
                if (!$dry && !$used) { Capsule::table('mod_cpm_products')->where('id', $drop->id)->delete(); }
                $pByKey[$k] = $keep;
                continue;
            }
            $pByKey[$k] = $p;
        }

        /* Κάθε περιοχή αποκτά προϊόν, και το προϊόν δείχνει πίσω στην περιοχή. */
        foreach ($areas as $a) {
            $k = $key($a->name);
            if (isset($pByKey[$k])) {
                $p = $pByKey[$k];
                if ((int) $p->area_id !== (int) $a->id || $p->name !== $a->name) {
                    $out['linked']++;
                    if (!$dry) {
                        Capsule::table('mod_cpm_products')->where('id', $p->id)
                            ->update(['area_id' => (int) $a->id, 'name' => $a->name,
                                      'sort' => (int) $a->sort * 10]);
                    }
                }
                continue;
            }
            $out['products']++;
            if ($dry) { continue; }
            $pid = Capsule::table('mod_cpm_products')->insertGetId([
                'name' => $a->name, 'color' => $a->color ?: '#0090dd', 'sort' => (int) $a->sort * 10,
                'active' => 1, 'area_id' => (int) $a->id, 'created_at' => date('Y-m-d H:i:s')]);
            $pByKey[$k] = (object) ['id' => $pid, 'name' => $a->name, 'area_id' => (int) $a->id];
        }

        /* ΤΑ SLUG ΤΟΥ ΚΑΤΑΛΟΓΟΥ. Οι καρτέλες κρατούν slug («pharmacyone_gr»),
           όχι id. Αν το προϊόν δεν δηλώσει το δικό του, ο τηλεφωνικός κατάλογος
           θα παρήγαγε καινούργιο από το όνομα και οι υπάρχοντες χαρακτηρισμοί
           θα γίνονταν άγνωστοι — δηλαδή θα σβήνονταν σιωπηλά. */
        foreach (self::BOOK_MAP as $slug => $pname) {
            if ($slug === 'pharmacyone_cy' || $slug === 'yeastar') { continue; }   // alias / δικό του πια
            $row = Capsule::table('mod_cpm_products')->where('name', $pname)->first(['id', 'book_key']);
            if (!$row || trim((string) $row->book_key) !== '') { continue; }
            $out['linked']++;
            if (!$dry) { Capsule::table('mod_cpm_products')->where('id', $row->id)->update(['book_key' => $slug]); }
        }
        foreach (['Yeastar / Τηλεφωνία' => 'yeastar', 'E-commerce' => 'ecommerce',
                  'Marketplaces' => 'marketplace', 'Courier modul' => 'courier'] as $pname => $slug) {
            $row = Capsule::table('mod_cpm_products')->where('name', $pname)->first(['id', 'book_key']);
            if (!$row || trim((string) $row->book_key) !== '') { continue; }
            if (!$dry) { Capsule::table('mod_cpm_products')->where('id', $row->id)->update(['book_key' => $slug]); }
        }

        /* ΟΙ ΟΥΡΕΣ. Ήταν κι αυτές στη σκληρή λίστα του Route· χωρίς μεταφορά, η
           δρομολόγηση των κλήσεων θα έμενε ξαφνικά κενή. Μπαίνουν μία φορά και
           από εκεί και πέρα αλλάζουν από την οθόνη.

           ΠΡΟΣΟΧΗ: η Route λέγεται `Route` αλλά ζει στο lib/Pbx3cx/Route.php —
           ο autoloader ΔΕΝ τη βρίσκει από το όνομα. Χωρίς αυτό το require, το
           Db::install() έσκαγε με «Class Route not found». */
        if (!class_exists(__NAMESPACE__ . '\\Route')) {
            $rf = __DIR__ . '/Pbx3cx/Route.php';
            if (is_file($rf)) { require_once $rf; }
        }
        foreach (class_exists(__NAMESPACE__ . '\\Route') ? Route::PRODUCTS : [] as $slug => [$lbl, $dn]) {
            $q = Capsule::table('mod_cpm_products')->where('book_key', $slug);
            $row = $q->first(['id', 'route_dn']);
            if (!$row && isset(self::BOOK_MAP[$slug])) {
                $row = Capsule::table('mod_cpm_products')->where('name', self::BOOK_MAP[$slug])
                    ->first(['id', 'route_dn']);
            }
            if (!$row || trim((string) $row->route_dn) !== '') { continue; }
            if (!$dry) { Capsule::table('mod_cpm_products')->where('id', $row->id)->update(['route_dn' => $dn]); }
        }

        /* Και αντίστροφα: προϊόν χωρίς περιοχή αποκτά μία, ώστε η λίστα των
           Ρυθμίσεων να είναι ΟΛΟΚΛΗΡΗ — αυτή βλέπει ο χρήστης. */
        $aByKey = [];
        foreach ($areas as $a) { $aByKey[$key($a->name)] = $a; }
        foreach ($pByKey as $k => $p) {
            if (isset($aByKey[$k])) { continue; }
            $out['areas']++;
            if ($dry) { continue; }
            $aid = Capsule::table('mod_cpm_ticket_cats')->insertGetId([
                'kind' => 'area', 'name' => $p->name, 'color' => $p->color ?? '#0090dd',
                'sort' => (int) Capsule::table('mod_cpm_ticket_cats')->where('kind', 'area')->max('sort') + 1]);
            Capsule::table('mod_cpm_products')->where('id', $p->id)->update(['area_id' => $aid]);
        }
        return $out;
    }

    /**
     * Κρατά την «περιοχή» του ticket ίδια με το προϊόν. Τρέχει όταν σώζεται
     * προϊόν από την οθόνη — οι δύο λίστες δεν επιτρέπεται να αποκλίνουν ξανά.
     * Οι ΥΠΟΚΑΤΗΓΟΡΙΕΣ δεν γίνονται περιοχές: τα tickets ταξινομούνται στο προϊόν.
     */
    public static function mirrorToArea($productId)
    {
        $p = Capsule::table('mod_cpm_products')->where('id', (int) $productId)->first();
        if (!$p || $p->parent_id) { return 0; }
        if ($p->area_id && Capsule::table('mod_cpm_ticket_cats')->where('id', $p->area_id)->exists()) {
            Capsule::table('mod_cpm_ticket_cats')->where('id', $p->area_id)
                ->update(['name' => $p->name, 'color' => $p->color]);
            return (int) $p->area_id;
        }
        $aid = Capsule::table('mod_cpm_ticket_cats')->insertGetId([
            'kind' => 'area', 'name' => $p->name, 'color' => $p->color,
            'sort' => (int) Capsule::table('mod_cpm_ticket_cats')->where('kind', 'area')->max('sort') + 1]);
        Capsule::table('mod_cpm_products')->where('id', $p->id)->update(['area_id' => $aid]);
        return $aid;
    }

    /**
     * Ο κατάλογος όπως τον χρειάζεται κάθε οθόνη: δέντρο ενός επιπέδου, με τα
     * παιδιά κάτω από τον γονέα τους και το πλήρες όνομα έτοιμο («Γονέας › Παιδί»).
     */
    public static function tree($onlyActive = true)
    {
        $q = Capsule::table('mod_cpm_products')->orderBy('sort')->orderBy('id');
        if ($onlyActive) { $q->where('active', 1); }
        $all = $q->get(['id', 'name', 'color', 'sort', 'active', 'parent_id', 'route_dn', 'area_id']);

        $kids = [];
        foreach ($all as $r) { if ($r->parent_id) { $kids[(int) $r->parent_id][] = $r; } }
        $out = [];
        foreach ($all as $r) {
            if ($r->parent_id) { continue; }
            $row = ['id' => (int) $r->id, 'name' => (string) $r->name, 'color' => (string) $r->color,
                'full' => (string) $r->name, 'parent_id' => 0, 'active' => (int) $r->active,
                'route_dn' => (string) $r->route_dn, 'kids' => []];
            foreach ($kids[(int) $r->id] ?? [] as $c) {
                $row['kids'][] = ['id' => (int) $c->id, 'name' => (string) $c->name,
                    'color' => (string) ($c->color ?: $r->color), 'full' => $r->name . ' › ' . $c->name,
                    'parent_id' => (int) $r->id, 'active' => (int) $c->active,
                    'route_dn' => (string) ($c->route_dn ?: $r->route_dn), 'kids' => []];
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Τα προϊόντα ενός πελάτη ΟΠΩΣ ΤΑ ΛΕΕΙ Ο ΤΗΛΕΦΩΝΙΚΟΣ ΚΑΤΑΛΟΓΟΣ.
     *
     * Ο κατάλογος είναι η πηγή αλήθειας (απόφαση 22/09/2026): τον γεμίζουμε εμείς
     * με το χέρι και ισχύει και για πελάτες που δεν έχουν ακόμη καρτέλα WHMCS.
     * Οι ενεργές υπηρεσίες του WHMCS μένουν ως ΣΥΜΠΛΗΡΩΜΑ — καλύπτουν όποιον ο
     * κατάλογος δεν έχει χαρακτηρίσει ακόμη.
     *
     * @return array<int,int> ids προϊόντων
     */
    public static function bookProducts($clientId)
    {
        $csv = Capsule::table('mod_cpm_book')->where('clientid', (int) $clientId)
            ->whereNotNull('products')->where('products', '<>', '')
            ->pluck('products')->all();
        if (!$csv) { return []; }

        $byName = [];
        foreach (Capsule::table('mod_cpm_products')->get(['id', 'name']) as $r) {
            $byName[mb_strtolower((string) $r->name)] = (int) $r->id;
        }
        $out = [];
        foreach ($csv as $line) {
            foreach (explode(',', (string) $line) as $slug) {
                $slug = trim($slug);
                if ($slug === '' || !isset(self::BOOK_MAP[$slug])) { continue; }
                $k = mb_strtolower(self::BOOK_MAP[$slug]);
                if (isset($byName[$k])) { $out[$byName[$k]] = $byName[$k]; }
            }
        }
        return array_values($out);
    }

    /**
     * Περνά στον πίνακα πελάτη→προϊόντων ό,τι λέει ο ΚΑΤΑΛΟΓΟΣ, με `source='book'`.
     *
     * Ιεραρχία: `manual` (το είπε άνθρωπος ρητά) > `book` (ο κατάλογος) >
     * `auto` (οι υπηρεσίες WHMCS). Μια γραμμή `auto` που ο κατάλογος επιβεβαιώνει
     * ΑΝΑΒΑΘΜΙΖΕΤΑΙ σε `book` — δεν διπλογράφεται.
     *
     * @param  bool     $dry      δοκιμή χωρίς εγγραφή
     * @param  int|null  $clientId μόνο αυτός ο πελάτης (null = όλοι)
     * @return array{n:int,clients:int}
     */
    public static function syncFromBook($dry = true, $clientId = null)
    {
        $out = ['n' => 0, 'clients' => 0];
        $q = Capsule::table('mod_cpm_book')->where('clientid', '>', 0)
            ->whereNotNull('products')->where('products', '<>', '');
        if ($clientId) { $q->where('clientid', (int) $clientId); }   // μία καρτέλα, όχι όλος ο κατάλογος
        $cids = $q->distinct()->pluck('clientid')->all();

        foreach ($cids as $cid) {
            $pids = self::bookProducts((int) $cid);
            if (!$pids) { continue; }
            $out['clients']++;
            foreach ($pids as $pid) {
                $ex = Capsule::table('mod_cpm_client_products')
                    ->where('clientid', (int) $cid)->where('product_id', $pid)->first();
                if ($ex && ($ex->source === 'book' || $ex->source === 'manual')) { continue; }
                $out['n']++;
                if ($dry) { continue; }
                if ($ex) {
                    Capsule::table('mod_cpm_client_products')->where('id', $ex->id)
                        ->update(['source' => 'book', 'status' => 'active']);
                } else {
                    Capsule::table('mod_cpm_client_products')->insert([
                        'clientid' => (int) $cid, 'product_id' => $pid, 'source' => 'book',
                        'status' => 'active', 'services' => 0,
                        'created_at' => date('Y-m-d H:i:s')]);
                }
            }
        }
        return $out;
    }

    /**
     * Τα προϊόντα ενός πελάτη με τη ΣΩΣΤΗ ΣΕΙΡΑ ΑΛΗΘΕΙΑΣ.
     *
     * Αν ο κατάλογος μιλά γι\' αυτόν τον πελάτη, ΜΟΝΟ αυτός μετράει: το να
     * ανακατεύαμε τις υπηρεσίες WHMCS από πάνω θα ξανάφερνε προϊόντα που εμείς
     * ρητά δεν του αναγνωρίζουμε. Σιωπή του καταλόγου → πέφτουμε στο WHMCS.
     *
     * @return array<int,int> ids προϊόντων
     */
    public static function clientProducts($clientId)
    {
        $book = self::bookProducts($clientId);
        if ($book) { return $book; }
        return array_map('intval', Capsule::table('mod_cpm_client_products')
            ->where('clientid', (int) $clientId)->where('status', 'active')
            ->pluck('product_id')->all());
    }

    /**
     * Δίνει ΕΙΔΙΚΟΤΗΤΑ στις εργασίες — το τρίτο σκέλος που λείπει για να μπορεί η
     * δεξαμενή να δρομολογήσει. Χωρίς προϊόν, μια εργασία δεν πάει σε κανέναν.
     *
     * Τρεις πηγές, με αυτή τη σειρά εμπιστοσύνης:
     *
     *   1. ΤΟ TICKET — ο πελάτης το είπε ο ίδιος όταν το άνοιξε. Η πιο αξιόπιστη.
     *   2. ΤΟ ΕΡΓΟ   — αν το έργο έχει προϊόν, το έχουν και οι εργασίες του.
     *   3. Ο ΠΕΛΑΤΗΣ — μόνο αν έχει ΕΝΑ ενεργό προϊόν. Με δύο δεν μαντεύουμε:
     *                  λάθος ετικέτα στέλνει τη δουλειά σε λάθος άνθρωπο, που
     *                  είναι χειρότερο από το να μείνει στη Διαλογή.
     *
     * @param  bool $dry δοκιμή χωρίς εγγραφή
     * @return array{n:int,ticket:int,project:int,client:int}
     */
    public static function labelTasks($dry = true)
    {
        $out = ['n' => 0, 'ticket' => 0, 'project' => 0, 'client' => 0];

        /* Τι λέει το ticket. */
        $byTicket = [];
        foreach (Capsule::table('mod_cpm_ticket_class')->whereNotNull('product_id')
                    ->get(['ticketid', 'product_id']) as $r) {
            $byTicket[(int) $r->ticketid] = (int) $r->product_id;
        }
        /* Τι λέει το έργο, και ποιος είναι ο πελάτης του. */
        $byProject = $projClient = [];
        foreach (Capsule::table('mod_cpm_projects')->get(['id', 'product_id', 'clientid']) as $r) {
            if ($r->product_id) { $byProject[(int) $r->id] = (int) $r->product_id; }
            if ($r->clientid)   { $projClient[(int) $r->id] = (int) $r->clientid; }
        }
        /* Πελάτες με ΕΝΑ και μόνο προϊόν — οι μόνοι όπου δεν μαντεύουμε.
           Η πηγή είναι ο ΤΗΛΕΦΩΝΙΚΟΣ ΚΑΤΑΛΟΓΟΣ όπου μιλά, αλλιώς οι υπηρεσίες
           WHMCS — δες clientProducts(). */
        $one = [];
        foreach (array_unique(array_filter($projClient)) as $c) {
            $pids = self::clientProducts((int) $c);
            $one[(int) $c] = count($pids) === 1 ? (int) $pids[0] : 0;
        }

        foreach (Capsule::table('mod_cpm_tasks')
                    ->where(function ($q) { $q->whereNull('product_id')->orWhere('product_id', 0); })
                    ->get(['id', 'ticketid', 'project_id']) as $t) {
            $pid = null; $src = '';
            if ($t->ticketid && isset($byTicket[(int) $t->ticketid])) {
                $pid = $byTicket[(int) $t->ticketid]; $src = 'ticket';
            } elseif ($t->project_id && isset($byProject[(int) $t->project_id])) {
                $pid = $byProject[(int) $t->project_id]; $src = 'project';
            } elseif ($t->project_id && isset($projClient[(int) $t->project_id])) {
                $c = $projClient[(int) $t->project_id];
                if (!empty($one[$c])) { $pid = $one[$c]; $src = 'client'; }
            }
            if (!$pid) { continue; }
            $out['n']++; $out[$src]++;
            if ($dry) { continue; }
            Capsule::table('mod_cpm_tasks')->where('id', $t->id)->update(['product_id' => $pid]);
        }
        return $out;
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
