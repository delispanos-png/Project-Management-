<?php
/**
 * CloudOn Project Manager — ΑΝΘΡΩΠΟΙ, ΡΟΛΟΙ ΚΑΙ ΕΙΔΙΚΟΤΗΤΕΣ.
 *
 * Το στατικό έδαφος πάνω στο οποίο θα πατήσει αργότερα η δεξαμενή εργασιών.
 * Χωρίς αυτό ο μηχανισμός δεν μπορεί να σερβίρει τίποτα: δεν ξέρει ποιος
 * παίζει, τι ξέρει ο καθένας, και πόσο χωράει η μέρα του.
 *
 * Τρία πράγματα, και μόνο αυτά:
 *
 *   1. Η ΚΑΡΤΑ  (mod_cpm_agents)        — μπαίνει στη δεξαμενή; τι χαρακτήρα
 *                                         έχει η μέρα του; πόσο χωράει;
 *   2. Ο ΧΑΡΤΗΣ (mod_cpm_agent_skills)  — ποιος ξέρει ποιο ΠΡΟΪΟΝ, και πόσο.
 *   3. Η ΕΙΚΟΝΑ (today)                 — οι Χ χειριστές σήμερα, τι κρατάει
 *                                         ο καθένας και τι θα του σερβίραμε.
 *
 * Το λεξιλόγιο των ειδικοτήτων ΔΕΝ είναι καινούργιο: είναι ο κατάλογος
 * προϊόντων του Catalog (PharmacyOne, SoftOne/ERP, Website/Hosting…). Το
 * φαρμακείο, η λογιστική και το e-commerce είναι ήδη εκεί.
 *
 * ΠΡΟΣΟΧΗ — τίποτα εδώ δεν αναθέτει. Η κλάση υπολογίζει «τι θα έδινα σε
 * ποιον» και το δείχνει· η ανάθεση παραμένει ανθρώπινη μέχρι να συμφωνήσουμε
 * ότι ο χάρτης λέει αλήθεια.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Pool
{
    /** Ο χαρακτήρας της μέρας. Ορίζει ΑΝ και ΠΩΣ παίζει η λογική της ώρας. */
    const MODES = [
        'front' => 'Πρώτη γραμμή',   // όλη μέρα μικρά — τον διακόπτουν, αυτό είναι η δουλειά
        'mixed' => 'Μικτός',         // μικρά ως το όριο, βάθος μετά
        'deep'  => 'Βάθος',          // ποτέ μικρά — μόνο έργα. ΔΕΝ παίζει η ώρα
    ];

    /** Πόσο ξέρει κάποιος ένα προϊόν. Το κενό σημαίνει «ποτέ αυτόματα». */
    const LEVELS = [
        'main'  => 'Κύριος',    // του το δίνουμε πρώτου
        'can'   => 'Μπορεί',    // αν ο κύριος δεν χωράει
        'learn' => 'Μαθαίνει',  // ΜΟΝΟ με το χέρι, από τον επικεφαλής
    ];

    /** Τα «Μαθαίνει» δεν σερβίρονται αυτόματα — απόφαση, όχι παράλειψη. */
    const AUTO_LEVELS = ['main', 'can'];

    /* Προεπιλογές για χειριστή που δεν έχει ακόμη κάρτα. */
    const DEF_MODE  = 'mixed';
    const DEF_HOURS = 6.0;    // ωφέλιμες, όχι 8
    const DEF_SMALL = 30;     // λεπτά — τι θεωρείται «μικρό»
    const DEF_DEEP  = '11:00';

    /* ══════════════ σχήμα ══════════════ */

    /** Προσθετικό και επαναλήψιμο — τρέχει από το Db::install(). */
    public static function install()
    {
        $s = Capsule::schema();

        /* Η κάρτα. Μία γραμμή ανά χειριστή — και μόνο για όσους έχουν ρυθμιστεί:
           η απουσία γραμμής σημαίνει «δεν μπαίνει στη δεξαμενή», που είναι το
           ασφαλές. Κανείς δεν βρίσκεται μέσα χωρίς να το έχει αποφασίσει άνθρωπος. */
        if (!$s->hasTable('mod_cpm_agents')) {
            $s->create('mod_cpm_agents', function ($t) {
                $t->increments('id');
                $t->integer('admin_id')->unsigned()->unique();
                $t->tinyInteger('in_pool')->default(0);
                $t->string('day_mode', 8)->default(self::DEF_MODE);
                $t->time('deep_from')->nullable();                 // μόνο για mixed
                $t->decimal('hours_day', 4, 1)->default(self::DEF_HOURS);
                $t->integer('small_min')->unsigned()->default(self::DEF_SMALL);
                $t->text('note')->nullable();
                $t->integer('updated_by')->unsigned()->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }

        /* Ο χάρτης: άνθρωπος × προϊόν × βαθμός. Τον γεμίζει ο επικεφαλής της
           ομάδας, ΟΧΙ ο καθένας τον εαυτό του — αλλιώς γράφουν όλοι «Κύριος»
           παντού και ο χάρτης γίνεται άχρηστος. */
        if (!$s->hasTable('mod_cpm_agent_skills')) {
            $s->create('mod_cpm_agent_skills', function ($t) {
                $t->increments('id');
                $t->integer('admin_id')->unsigned()->index();
                $t->integer('product_id')->unsigned()->index();
                $t->string('level', 8);
                $t->integer('updated_by')->unsigned()->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->unique(['admin_id', 'product_id'], 'as_uniq');
            });
        }
    }

    /* ══════════════ η κάρτα ══════════════ */

    /** Η κάρτα ενός χειριστή — πάντα γεμάτη, με προεπιλογές όπου λείπει. */
    public static function card($adminId)
    {
        $r = Capsule::table('mod_cpm_agents')->where('admin_id', (int) $adminId)->first();
        return [
            'admin_id'  => (int) $adminId,
            'in_pool'   => $r ? (int) $r->in_pool : 0,
            'day_mode'  => $r && isset(self::MODES[$r->day_mode]) ? $r->day_mode : self::DEF_MODE,
            'deep_from' => $r && $r->deep_from ? substr($r->deep_from, 0, 5) : self::DEF_DEEP,
            'hours_day' => $r ? (float) $r->hours_day : self::DEF_HOURS,
            'small_min' => $r ? (int) $r->small_min : self::DEF_SMALL,
            'note'      => $r ? (string) $r->note : '',
            'set'       => (bool) $r,       // έχει ρυθμιστεί ή τρέχει με προεπιλογές;
        ];
    }

    /** Αποθήκευση κάρτας. Επιστρέφει την κάρτα όπως έμεινε. */
    public static function saveCard($adminId, array $in, $by = 0)
    {
        $adminId = (int) $adminId;
        $mode = isset($in['day_mode'], self::MODES[$in['day_mode']]) ? $in['day_mode'] : self::DEF_MODE;
        $hours = isset($in['hours_day']) ? round((float) $in['hours_day'], 1) : self::DEF_HOURS;
        if ($hours < 0.5) { $hours = 0.5; }
        if ($hours > 12)  { $hours = 12; }
        $small = isset($in['small_min']) ? (int) $in['small_min'] : self::DEF_SMALL;
        if ($small < 5)   { $small = 5; }
        if ($small > 480) { $small = 480; }

        $deep = null;
        if ($mode === 'mixed') {
            $d = isset($in['deep_from']) ? trim((string) $in['deep_from']) : self::DEF_DEEP;
            $deep = preg_match('/^\d{1,2}:\d{2}$/', $d) ? $d . ':00' : self::DEF_DEEP . ':00';
        }

        $row = [
            'in_pool'    => !empty($in['in_pool']) ? 1 : 0,
            'day_mode'   => $mode,
            'deep_from'  => $deep,
            'hours_day'  => $hours,
            'small_min'  => $small,
            'note'       => isset($in['note']) ? mb_substr(trim((string) $in['note']), 0, 500) : '',
            'updated_by' => (int) $by,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $ex = Capsule::table('mod_cpm_agents')->where('admin_id', $adminId)->first();
        if ($ex) {
            Capsule::table('mod_cpm_agents')->where('admin_id', $adminId)->update($row);
        } else {
            $row['admin_id'] = $adminId;
            Capsule::table('mod_cpm_agents')->insert($row);
        }
        return self::card($adminId);
    }

    /* ══════════════ ο χάρτης ══════════════ */

    /** Οι δεξιότητες ενός χειριστή: [product_id => level]. */
    public static function skills($adminId)
    {
        $out = [];
        foreach (Capsule::table('mod_cpm_agent_skills')->where('admin_id', (int) $adminId)
                    ->get(['product_id', 'level']) as $r) {
            $out[(int) $r->product_id] = (string) $r->level;
        }
        return $out;
    }

    /**
     * Ένα κελί του χάρτη. Κενό level σβήνει τη γραμμή — «δεν του το δίνουμε»
     * δεν είναι βαθμός, είναι απουσία βαθμού.
     */
    public static function setSkill($adminId, $productId, $level, $by = 0)
    {
        $adminId = (int) $adminId;
        $productId = (int) $productId;
        $q = Capsule::table('mod_cpm_agent_skills')
            ->where('admin_id', $adminId)->where('product_id', $productId);

        if (!isset(self::LEVELS[$level])) { $q->delete(); return ''; }

        $row = ['level' => $level, 'updated_by' => (int) $by, 'updated_at' => date('Y-m-d H:i:s')];
        if ($q->first()) {
            $q->update($row);
        } else {
            Capsule::table('mod_cpm_agent_skills')->insert(
                $row + ['admin_id' => $adminId, 'product_id' => $productId]);
        }
        return $level;
    }

    /**
     * Τα ΚΕΝΑ του χάρτη — ανά προϊόν, ποιος το ξέρει και πόσο επικίνδυνο είναι.
     *
     * Αυτό δεν είναι για τον μηχανισμό, είναι για τον ιδιοκτήτη: «PharmacyOne:
     * μόνο ο Χ. Αν λείψει, δεν το παίρνει κανείς.»
     *
     * @return array<int,array{product_id:int,name:string,main:array,can:array,learn:array,risk:string}>
     */
    public static function coverage()
    {
        $cards = [];
        foreach (Capsule::table('mod_cpm_agents')->where('in_pool', 1)->get(['admin_id']) as $r) {
            $cards[(int) $r->admin_id] = true;
        }
        $by = [];
        foreach (Capsule::table('mod_cpm_agent_skills')->get(['admin_id', 'product_id', 'level']) as $r) {
            $by[(int) $r->product_id][(string) $r->level][] = (int) $r->admin_id;
        }
        /* Το δέντρο, ισοπεδωμένο: η ΥΠΟΚΑΤΗΓΟΡΙΑ είναι κι αυτή ειδικότητα και
           έχει τη δική της κάλυψη — μπορεί το PharmacyOne να καλύπτεται και η
           συνταγογράφησή του να κρέμεται από έναν άνθρωπο. */
        $flat = [];
        foreach (Catalog::tree(true) as $t) {
            $flat[] = $t;
            foreach ($t['kids'] as $k) { $flat[] = $k; }
        }
        $out = [];
        foreach ($flat as $p) {
            $pid = (int) $p['id'];
            $g = ['main' => [], 'can' => [], 'learn' => []];
            foreach ($g as $lv => $_) {
                foreach (isset($by[$pid][$lv]) ? $by[$pid][$lv] : [] as $aid) {
                    /* Μετράει μόνο όποιος ΟΝΤΩΣ παίζει: ένας κύριος εκτός
                       δεξαμενής δεν καλύπτει τίποτα αυτόματα. */
                    if (isset($cards[$aid])) { $g[$lv][] = $aid; }
                }
            }
            $auto = count($g['main']) + count($g['can']);
            if ($auto === 0)      { $risk = 'none';   }   // κανείς — ακάλυπτο
            elseif ($auto === 1)  { $risk = 'single'; }   // ένας — αν λείψει, στοπ
            elseif ($auto === 2)  { $risk = 'thin';   }   // οριακά
            else                  { $risk = 'ok';     }
            $out[] = ['product_id' => $pid, 'name' => (string) $p['full'],
                      'color' => (string) $p['color'], 'parent_id' => (int) $p['parent_id'],
                      'risk' => $risk] + $g;
        }
        return $out;
    }

    /* ══════════════ ποιος μπορεί να το πάρει ══════════════ */

    /**
     * Οι υποψήφιοι για μια εργασία, ταξινομημένοι: Κύριος πριν από Μπορεί.
     * Δεν αναθέτει — απαντά «σε ποιους θα ταίριαζε».
     *
     * @param  int  $productId  η ειδικότητα που ζητάει η εργασία
     * @param  bool $withLearn  να μπουν και οι «Μαθαίνει» (μόνο χειροκίνητα)
     * @return array<int,array{admin_id:int,level:string}>
     */
    public static function candidates($productId, $withLearn = false)
    {
        $productId = (int) $productId;
        if (!$productId) { return []; }
        $ok = $withLearn ? array_keys(self::LEVELS) : self::AUTO_LEVELS;
        $rank = ['main' => 0, 'can' => 1, 'learn' => 2];
        $out = [];
        foreach (Capsule::table('mod_cpm_agent_skills as s')
                    ->join('mod_cpm_agents as a', 'a.admin_id', '=', 's.admin_id')
                    ->where('s.product_id', $productId)->where('a.in_pool', 1)
                    ->whereIn('s.level', $ok)
                    ->get(['s.admin_id', 's.level']) as $r) {
            $out[] = ['admin_id' => (int) $r->admin_id, 'level' => (string) $r->level];
        }
        usort($out, function ($x, $y) use ($rank) {
            return $rank[$x['level']] <=> $rank[$y['level']];
        });
        return $out;
    }

    /**
     * Ταιριάζει αυτή η εργασία στην ώρα του ανθρώπου;
     *
     * Εδώ ζει ο κανόνας «μικρά το πρωί, βάθος μετά τις 11» — και το ότι ο
     * τύπος «Βάθος» ΔΕΝ συμμετέχει καθόλου σε αυτή τη λογική.
     *
     * @return array{ok:bool,why:string}
     */
    public static function fitsNow(array $card, $minutes, $at = null)
    {
        $at = $at ?: date('H:i');
        $small = $minutes > 0 && $minutes <= $card['small_min'];

        if ($card['day_mode'] === 'front') {
            return $small || !$minutes
                ? ['ok' => true, 'why' => 'πρώτη γραμμή']
                : ['ok' => false, 'why' => 'μεγάλο για πρώτη γραμμή'];
        }
        if ($card['day_mode'] === 'deep') {
            return $small
                ? ['ok' => false, 'why' => 'δουλεύει σε βάθος — όχι μικρά']
                : ['ok' => true, 'why' => 'βάθος'];
        }
        /* mixed: πριν το όριο μόνο μικρά, μετά μόνο βάθος. */
        $before = strcmp($at, $card['deep_from']) < 0;
        if ($before) {
            return $small || !$minutes
                ? ['ok' => true, 'why' => 'πρωινά μικρά']
                : ['ok' => false, 'why' => 'μεγάλο — μετά τις ' . $card['deep_from']];
        }
        return $small
            ? ['ok' => false, 'why' => 'ώρα για βάθος — τα μικρά το πρωί']
            : ['ok' => true, 'why' => 'ώρα βάθους'];
    }

    /* ══════════════ η καθημερινή εικόνα ══════════════ */

    /**
     * Οι χειριστές ΣΗΜΕΡΑ: ποιος παίζει, ποιος λείπει, τι κρατάει ο καθένας,
     * πόσο έχει γεμίσει η μέρα του και τι θα του σερβίραμε στη συνέχεια.
     *
     * Είναι η οθόνη-καθρέφτης: τρέχει ΟΛΟ το μυαλό της δεξαμενής χωρίς να
     * αναθέτει τίποτα. Έτσι φαίνεται αν ο χάρτης λέει αλήθεια, ΠΡΙΝ αφεθεί ο
     * μηχανισμός να μοιράζει μόνος του.
     *
     * @return array{date:string,at:string,rows:array,pool:array,unlabelled:int}
     */
    public static function today($date = null, $at = null)
    {
        $date = $date ?: date('Y-m-d');
        $at   = $at ?: date('H:i');
        $open = Db::statusIds(['wait', 'work', 'after']);
        $work = Db::statusIds(['work']);
        $away = Leave::awayOn($date);

        /* Η δεξαμενή: ανοιχτές εργασίες που ΔΕΝ τις κρατάει κανείς. */
        $pool = [];
        $unlabelled = 0;
        foreach (Capsule::table('mod_cpm_tasks as t')
                    ->leftJoin('mod_cpm_products as p', 'p.id', '=', 't.product_id')
                    ->whereIn('t.status_id', $open)
                    ->where(function ($q) { $q->whereNull('t.action_user')->orWhere('t.action_user', 0); })
                    ->orderBy('t.priority', 'desc')->orderBy('t.created_at')
                    ->get(['t.id', 't.title', 't.product_id', 't.estimate_minutes', 't.due_date',
                           't.priority', 't.created_at', 'p.name as product']) as $r) {
            if (!$r->product_id) { $unlabelled++; continue; }   // χωρίς ειδικότητα → Διαλογή
            $pool[] = ['id' => (int) $r->id, 'title' => (string) $r->title,
                       'product_id' => (int) $r->product_id, 'product' => (string) $r->product,
                       'minutes' => (int) $r->estimate_minutes, 'due' => $r->due_date,
                       'priority' => (int) $r->priority];
        }

        /* Ποιος ξέρει τι — μία ανάγνωση, όχι μία ανά άνθρωπο. */
        $skill = [];
        foreach (Capsule::table('mod_cpm_agent_skills')->get(['admin_id', 'product_id', 'level']) as $r) {
            $skill[(int) $r->admin_id][(int) $r->product_id] = (string) $r->level;
        }

        $rows = [];
        foreach (Capsule::table('mod_cpm_agents')->where('in_pool', 1)->get(['admin_id']) as $a) {
            $aid  = (int) $a->admin_id;
            $card = self::card($aid);
            $mine = isset($skill[$aid]) ? $skill[$aid] : [];

            /* Τι κρατάει τώρα — ο κανόνας της μπάλας: ΕΝΑ πράγμα κάθε φορά. */
            $holding = Capsule::table('mod_cpm_tasks as t')
                ->leftJoin('mod_cpm_products as p', 'p.id', '=', 't.product_id')
                ->where('t.action_user', $aid)->whereIn('t.status_id', $work)
                ->orderBy('t.updated_at', 'desc')
                ->first(['t.id', 't.title', 't.estimate_minutes', 'p.name as product']);

            /* Πόσο έχει πιάσει η μέρα του: ό,τι κρατάει + ό,τι είναι
               προγραμματισμένο για σήμερα. Σε λεπτά, από τις εκτιμήσεις. */
            $booked = (int) Capsule::table('mod_cpm_tasks')
                ->where('action_user', $aid)->whereIn('status_id', $open)
                ->where(function ($q) use ($date) {
                    $q->where('schedule_date', $date)->orWhere('start_date', $date);
                })->sum('estimate_minutes');
            if ($holding && !$booked) { $booked = (int) $holding->estimate_minutes; }
            $cap = (int) round($card['hours_day'] * 60);

            /* Τι θα του σερβίραμε. Η ΣΕΙΡΑ υπολογίζεται πάντα — είναι
               πληροφορία, όχι ανάθεση. Το αν θα του δοθεί ΤΩΡΑ είναι άλλο
               ερώτημα: όσο κρατάει μπάλα δεν παίρνει δεύτερη (ο κανόνας «μία
               εργασία, ένας άνθρωπος»), οπότε η σειρά του είναι «τα επόμενα». */
            $queue = [];
            $serving = !$holding && !isset($away[$aid]);
            if (!isset($away[$aid])) {
                foreach ($pool as $t) {
                    $lv = isset($mine[$t['product_id']]) ? $mine[$t['product_id']] : '';
                    if (!in_array($lv, self::AUTO_LEVELS, true)) { continue; }
                    $fit = self::fitsNow($card, $t['minutes'], $at);
                    if (!$fit['ok']) { continue; }
                    if ($booked + $t['minutes'] > $cap) { continue; }   // δεν χωράει η μέρα
                    $queue[] = $t + ['level' => $lv, 'why' => $fit['why']];
                    if (count($queue) >= 4) { break; }
                }
                usort($queue, function ($x, $y) {
                    if ($x['level'] !== $y['level']) { return $x['level'] === 'main' ? -1 : 1; }
                    $dx = $x['due'] ?: '9999-12-31';
                    $dy = $y['due'] ?: '9999-12-31';
                    return $dx === $dy ? $y['priority'] <=> $x['priority'] : strcmp($dx, $dy);
                });
            }

            $rows[] = [
                'admin_id' => $aid,
                'name'     => Db::adminName($aid),
                'mode'     => $card['day_mode'],
                'mode_lbl' => self::MODES[$card['day_mode']],
                'deep_from' => $card['deep_from'],
                'away'     => isset($away[$aid]) ? $away[$aid] : '',
                'booked'   => $booked,
                'cap'      => $cap,
                'serving'  => $serving,      // θα του δοθεί ΤΩΡΑ ή είναι «τα επόμενα»;
                'holding'  => $holding ? ['id' => (int) $holding->id, 'title' => (string) $holding->title,
                                          'product' => (string) $holding->product] : null,
                'queue'    => $queue,
                'skills'   => count($mine),
            ];
        }

        usort($rows, function ($x, $y) { return strcoll($x['name'], $y['name']); });

        return ['date' => $date, 'at' => $at, 'rows' => $rows,
                'pool' => count($pool), 'unlabelled' => $unlabelled];
    }
}
