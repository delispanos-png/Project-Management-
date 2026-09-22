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
