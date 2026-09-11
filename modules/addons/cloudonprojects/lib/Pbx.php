<?php
/**
 * CloudOn Project Manager — κοστολόγηση & προσφορά ΤΗΛΕΦΩΝΙΚΟΥ ΚΕΝΤΡΟΥ (3CX / Yeastar).
 *
 * Ξεχωριστή μηχανή από το PharmacyOne (lib/Pharmacy.php) — δεν μοιράζεται κώδικα,
 * τιμοκατάλογο ή ρυθμίσεις. Ήρθε από τον αυτόνομο «Διαμορφωτή Προσφορών» (HTML):
 * ίδια είδη, ίδιες κατηγορίες, ίδιο έγγραφο· τώρα ο υπολογισμός γίνεται ΜΟΝΟ εδώ
 * (ο client ρωτάει), η προσφορά γεννιέται κανονικά στο κύκλωμα Προσφορών, και
 * στέλνεται/παρακολουθείται όπως κάθε άλλη.
 *
 * Κανόνες:
 *  - Καμία τιμή δεν επινοείται. Τα 3CX είναι από την επαληθευμένη προσφορά 11/8/2026.
 *    Τα Yeastar είναι ΕΠΙΤΗΔΕΣ 0,00 € μέχρι να τα συμπληρώσει ο διαχειριστής στον
 *    βασικό τιμοκατάλογο — και το έγγραφο ΔΕΝ βγαίνει με είδος χωρίς τιμή.
 *  - Ποσό προσφοράς = 1ο έτος προ ΦΠΑ (ετήσια + εφάπαξ, μετά την έκπτωση).
 *  - Οι δόσεις πληρωμής είναι ΠΛΗΡΩΤΕΑ ποσά (με ΦΠΑ), όπως και στο PharmacyOne.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Pbx
{
    const SETTING = 'pbx_catalog';       // tbladdonmodules: override του βασικού τιμοκαταλόγου
    const PAY_METHODS = ['Τραπεζική κατάθεση', 'Μετρητά', 'Κάρτα (POS / e-banking)', 'Επιταγή'];
    const CATS = ['Ετήσιες άδειες', 'Φιλοξενία & ομιλία', 'Υπηρεσίες (εφάπαξ)', 'Εξοπλισμός (εφάπαξ)'];
    const EETT = '20-091';               // Αρ. Μητρώου Ε.Ε.Τ.Τ. (CloudOn IKE)

    /* ───────────────────────── πλατφόρμες ───────────────────────── */

    public static function platforms()
    {
        return [
            '3cx' => ['name' => '3CX',
                'desc' => 'Λογισμικό IP PBX σε cloud server. Άδειες ανά ταυτόχρονες κλήσεις.',
                'letter' => 'εγκατάσταση και παραμετροποίηση του τηλεφωνικού κέντρου 3CX, καθώς και τη δικτυακή υποδομή, τη φιλοξενία και την τεχνική υποστήριξη αυτού'],
            'yeastar' => ['name' => 'Yeastar',
                'desc' => 'Σειρά P — συσκευή στον χώρο ή cloud. Άδειες ανά πλάνο και χρήστες.',
                'letter' => 'εγκατάσταση και παραμετροποίηση τηλεφωνικού κέντρου Yeastar σειράς P, καθώς και τη δικτυακή υποδομή και την τεχνική υποστήριξη αυτού'],
        ];
    }

    /* ───────────────────────── τιμοκατάλογος ───────────────────────── */

    /**
     * Εργοστασιακός τιμοκατάλογος. Κάθε είδος: id, plat, cat, name, note, price,
     * rec (ετήσιο επαναλαμβανόμενο), perExt (ποσότητα = εσωτερικά), qty (προεπιλογή), on.
     */
    public static function factoryItems()
    {
        return [
            // ── 3CX — τιμές από την επαληθευμένη προσφορά 11/08/2026 ──
            ['id' => '3cx_lic',   'plat' => '3cx', 'cat' => 0, 'name' => '3CX Phone System — άδεια ετήσιας συνδρομής',
                'note' => 'Annual Simultaneous Calls, έκδοση Standard', 'price' => 385, 'rec' => true, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => '3cx_trunk', 'plat' => '3cx', 'cat' => 0, 'name' => 'InTrunk — επιπλέον κανάλι φωνής',
                'note' => 'Αυξάνει τις ταυτόχρονες εξωτερικές συνομιλίες', 'price' => 26, 'rec' => true, 'perExt' => false, 'qty' => 6, 'on' => true],
            ['id' => '3cx_did',   'plat' => '3cx', 'cat' => 0, 'name' => 'DID — αριθμός απευθείας κλήσης',
                'note' => '', 'price' => 26, 'rec' => true, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => '3cx_cli',   'plat' => '3cx', 'cat' => 0, 'name' => 'CLI — αριθμός αναγνώρισης',
                'note' => '', 'price' => 0, 'rec' => true, 'perExt' => false, 'qty' => 0, 'on' => true],
            ['id' => '3cx_host',  'plat' => '3cx', 'cat' => 1, 'name' => 'Cloud Server Hosting',
                'note' => '2 CPU @3700MHz · 4 GB RAM · 40 GB · 1× IPv4 · Linux · Ελλάδα', 'price' => 250, 'rec' => true, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => '3cx_air',   'plat' => '3cx', 'cat' => 1, 'name' => 'Προαγορασμένος χρόνος ομιλίας',
                'note' => 'Επιβαρύνεται με τέλος σταθερής τηλεφωνίας', 'price' => 50, 'rec' => true, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => '3cx_cfg',   'plat' => '3cx', 'cat' => 2, 'name' => 'Παραμετροποίηση ανά εσωτερικό',
                'note' => '', 'price' => 10, 'rec' => false, 'perExt' => true, 'qty' => 0, 'on' => true],
            ['id' => '3cx_inst',  'plat' => '3cx', 'cat' => 2, 'name' => 'Εγκατάσταση συστήματος',
                'note' => '', 'price' => 150, 'rec' => false, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => '3cx_port',  'plat' => '3cx', 'cat' => 2, 'name' => 'Φορητότητα αριθμών',
                'note' => 'Εφ’ άπαξ καταβολή', 'price' => 35, 'rec' => false, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => '3cx_dect',  'plat' => '3cx', 'cat' => 3, 'name' => 'Yealink W71P / W73P DECT',
                'note' => 'Ασύρματη συσκευή με βάση', 'price' => 105, 'rec' => false, 'perExt' => false, 'qty' => 3, 'on' => true],
            ['id' => '3cx_ip',    'plat' => '3cx', 'cat' => 3, 'name' => 'Επιτραπέζια συσκευή IP',
                'note' => '', 'price' => 0, 'rec' => false, 'perExt' => false, 'qty' => 0, 'on' => true],
            // ── Yeastar — τιμές ΚΕΝΕΣ επίτηδες: τις συμπληρώνει ο διαχειριστής ──
            ['id' => 'ys_lic',    'plat' => 'yeastar', 'cat' => 0, 'name' => 'Yeastar P-Series — ετήσιο πλάνο',
                'note' => 'Standard / Enterprise / Ultimate', 'price' => 0, 'rec' => true, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => 'ys_user',   'plat' => 'yeastar', 'cat' => 0, 'name' => 'Άδεια ανά χρήστη',
                'note' => 'Εφόσον το πλάνο χρεώνει ανά χρήστη', 'price' => 0, 'rec' => true, 'perExt' => true, 'qty' => 0, 'on' => true],
            ['id' => 'ys_trunk',  'plat' => 'yeastar', 'cat' => 0, 'name' => 'Επιπλέον κανάλι φωνής (trunk)',
                'note' => '', 'price' => 0, 'rec' => true, 'perExt' => false, 'qty' => 0, 'on' => true],
            ['id' => 'ys_did',    'plat' => 'yeastar', 'cat' => 0, 'name' => 'DID — αριθμός απευθείας κλήσης',
                'note' => '', 'price' => 0, 'rec' => true, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => 'ys_host',   'plat' => 'yeastar', 'cat' => 1, 'name' => 'Yeastar Cloud — ετήσια φιλοξενία',
                'note' => 'Εναλλακτικά της συσκευής στον χώρο', 'price' => 0, 'rec' => true, 'perExt' => false, 'qty' => 0, 'on' => true],
            ['id' => 'ys_air',    'plat' => 'yeastar', 'cat' => 1, 'name' => 'Προαγορασμένος χρόνος ομιλίας',
                'note' => '', 'price' => 0, 'rec' => true, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => 'ys_cfg',    'plat' => 'yeastar', 'cat' => 2, 'name' => 'Παραμετροποίηση ανά εσωτερικό',
                'note' => '', 'price' => 0, 'rec' => false, 'perExt' => true, 'qty' => 0, 'on' => true],
            ['id' => 'ys_inst',   'plat' => 'yeastar', 'cat' => 2, 'name' => 'Εγκατάσταση συστήματος',
                'note' => '', 'price' => 0, 'rec' => false, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => 'ys_port',   'plat' => 'yeastar', 'cat' => 2, 'name' => 'Φορητότητα αριθμών',
                'note' => '', 'price' => 0, 'rec' => false, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => 'ys_appl',   'plat' => 'yeastar', 'cat' => 3, 'name' => 'Συσκευή Yeastar P-Series',
                'note' => 'P550 / P560 / P570 — εγκατάσταση στον χώρο', 'price' => 0, 'rec' => false, 'perExt' => false, 'qty' => 1, 'on' => true],
            ['id' => 'ys_dect',   'plat' => 'yeastar', 'cat' => 3, 'name' => 'Ασύρματη συσκευή DECT',
                'note' => '', 'price' => 0, 'rec' => false, 'perExt' => false, 'qty' => 0, 'on' => true],
        ];
    }

    private static function catalogOverride()
    {
        static $loaded = false; static $data = [];
        if ($loaded) { return $data; }
        $loaded = true;
        try {
            $raw = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
                ->where('setting', self::SETTING)->value('value');
            $d = json_decode((string) $raw, true);
            if (is_array($d)) { $data = $d; }
        } catch (\Throwable $e) {
        }
        return $data;
    }

    /** Ο ισχύων βασικός τιμοκατάλογος (εργοστασιακός + όσα άλλαξε ο διαχειριστής). */
    public static function defaultItems()
    {
        $ov = self::catalogOverride();
        $items = [];
        foreach (self::factoryItems() as $it) {
            $o = is_array($ov[$it['id']] ?? null) ? $ov[$it['id']] : [];
            if (isset($o['price']) && is_numeric($o['price'])) { $it['price'] = max(0, round((float) $o['price'], 2)); }
            if (isset($o['name']) && trim((string) $o['name']) !== '') { $it['name'] = mb_substr(trim((string) $o['name']), 0, 120); }
            if (isset($o['note'])) { $it['note'] = mb_substr(trim((string) $o['note']), 0, 160); }
            if (isset($o['qty']) && is_numeric($o['qty'])) { $it['qty'] = max(0, (int) $o['qty']); }
            $items[] = $it;
        }
        return $items;
    }

    /** Ευρετήριο id → είδος. */
    public static function itemMap()
    {
        $m = [];
        foreach (self::defaultItems() as $it) { $m[$it['id']] = $it; }
        return $m;
    }

    /** Αποθήκευση βασικού τιμοκαταλόγου — μόνο γνωστά είδη, αριθμητικά, μη-αρνητικά. */
    public static function saveBaseCatalog(array $items)
    {
        $ov = [];
        foreach (self::factoryItems() as $f) {
            $x = is_array($items[$f['id']] ?? null) ? $items[$f['id']] : null;
            if ($x === null) { continue; }
            $row = [];
            if (isset($x['price']) && is_numeric($x['price'])) { $row['price'] = max(0, round((float) $x['price'], 2)); }
            if (isset($x['name']) && trim((string) $x['name']) !== '' && trim((string) $x['name']) !== $f['name']) { $row['name'] = mb_substr(trim((string) $x['name']), 0, 120); }
            if (isset($x['note']) && trim((string) $x['note']) !== $f['note']) { $row['note'] = mb_substr(trim((string) $x['note']), 0, 160); }
            if (isset($x['qty']) && is_numeric($x['qty'])) { $row['qty'] = max(0, (int) $x['qty']); }
            if ($row) { $ov[$f['id']] = $row; }
        }
        $json = json_encode($ov, JSON_UNESCAPED_UNICODE);
        $t = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', self::SETTING);
        if ($t->exists()) { $t->update(['value' => $json]); }
        else { Capsule::table('tbladdonmodules')->insert(['module' => 'cloudonprojects', 'setting' => self::SETTING, 'value' => $json]); }
        return true;
    }

    public static function resetBaseCatalog()
    {
        Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', self::SETTING)->delete();
        return true;
    }

    /* ───────────────────────── ρύθμιση προσφοράς ───────────────────────── */

    const DEFAULT_INTRO = 'Σε συνέχεια της συνομιλίας μας, σας αποστέλλουμε την προσφορά μας, η οποία αναφέρεται στην {ΚΑΤΗΓΟΡΙΑ}.';
    const DEFAULT_DELIVERY = 'Μία (1) εβδομάδα — πέντε (5) εργάσιμες ημέρες από την αποδοχή.';

    /**
     * Κανονικοποίηση — ΠΟΤΕ δεν εμπιστεύεται τον client. Άγνωστα είδη πετιούνται,
     * τιμές/ποσότητες γίνονται μη-αρνητικοί αριθμοί, κείμενα κόβονται.
     * Σχήμα: plat, ext, sc, disc(%), qty{id}, on{id}, price{id} (τιμή ΜΟΝΟ για αυτή
     * την προσφορά), o{στοιχεία εγγράφου}.
     */
    public static function normalize($cfg)
    {
        $cfg = is_array($cfg) ? $cfg : [];
        $plats = self::platforms();
        $plat = isset($plats[$cfg['plat'] ?? '']) ? (string) $cfg['plat'] : '3cx';
        $items = self::itemMap();
        $qty = []; $on = []; $price = [];
        foreach ($items as $id => $it) {
            $q = $cfg['qty'][$id] ?? null;
            $qty[$id] = is_numeric($q) ? max(0, min(9999, (int) $q)) : (int) $it['qty'];
            $o = $cfg['on'][$id] ?? null;
            $on[$id] = $o === null ? ($it['on'] ? 1 : 0) : (!empty($o) ? 1 : 0);
            $p = $cfg['price'][$id] ?? null;
            $price[$id] = is_numeric($p) ? max(0, round((float) $p, 2)) : (float) $it['price'];
        }
        $o = is_array($cfg['o'] ?? null) ? $cfg['o'] : [];
        $s = function ($k, $max = 200, $def = '') use ($o) {
            return isset($o[$k]) ? mb_substr(trim((string) $o[$k]), 0, $max) : $def;
        };
        $date = $s('date', 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }
        $pm = $s('payMethod', 60, self::PAY_METHODS[0]);
        if (!in_array($pm, self::PAY_METHODS, true)) { $pm = self::PAY_METHODS[0]; }
        return [
            'plat' => $plat,
            'ext' => max(0, min(9999, (int) ($cfg['ext'] ?? 5))),
            'sc' => max(0, min(9999, (int) ($cfg['sc'] ?? 8))),
            'disc' => max(0, min(100, round((float) ($cfg['disc'] ?? 0), 2))),
            'qty' => $qty, 'on' => $on, 'price' => $price,
            'o' => [
                'client' => $s('client'), 'afm' => preg_replace('/\D+/', '', $s('afm', 12)),
                'attn' => $s('attn', 120), 'cphone' => $s('cphone', 40), 'cemail' => $s('cemail', 120),
                'address' => $s('address'), 'doy' => $s('doy', 80), 'city' => $s('city', 80, 'Αθήνα'),
                'protocol' => $s('protocol', 40), 'date' => $date,
                'seller' => $s('seller', 80), 'validDays' => max(1, min(365, (int) ($o['validDays'] ?? 30))),
                'vat' => max(0, min(100, round((float) ($o['vat'] ?? 24), 2))),
                'intro' => $s('intro', 1200), 'full' => !empty($o['full']) ? 1 : 0,
                'payMethod' => $pm, 'delivery' => $s('delivery', 200, self::DEFAULT_DELIVERY),
                'plan' => self::normPlan($o),
            ],
        ];
    }

    /** Δόσεις πληρωμής: [{t:'pct'|'eur', v, w}] — προεπιλογή «όλο με την αποδοχή». */
    private static function normPlan(array $o)
    {
        $rows = [];
        if (is_array($o['plan'] ?? null)) {
            foreach ($o['plan'] as $r) {
                if (!is_array($r)) { continue; }
                $t = (($r['t'] ?? 'pct') === 'eur') ? 'eur' : 'pct';
                $v = is_numeric($r['v'] ?? null) ? max(0, (float) $r['v']) : 0;
                $w = mb_substr(trim((string) ($r['w'] ?? '')), 0, 140);
                if ($v <= 0 && $w === '') { continue; }
                $rows[] = ['t' => $t, 'v' => $v, 'w' => $w];
                if (count($rows) >= 12) { break; }
            }
        }
        return $rows ?: [['t' => 'pct', 'v' => 100, 'w' => 'Με την αποδοχή της προσφοράς']];
    }

    /** Τα ευρώ κάθε δόσης πάνω στο πληρωτέο ποσό· η τελευταία απορροφά τα λεπτά. */
    public static function planRows(array $plan, $final)
    {
        $out = []; $sum = 0;
        foreach ($plan as $r) {
            $eur = round($r['t'] === 'eur' ? $r['v'] : $final * $r['v'] / 100, 2);
            $sum += $eur;
            $out[] = ['lab' => $r['t'] === 'eur' ? self::fmtEur($r['v']) : self::fmtPct($r['v']), 'eur' => $eur, 'w' => $r['w']];
        }
        $d = round($final - $sum, 2);
        if ($out && abs($d) > 0.001 && abs($d) <= 0.05) {
            $out[count($out) - 1]['eur'] = round($out[count($out) - 1]['eur'] + $d, 2);
            $sum = round($sum + $d, 2);
        }
        return ['rows' => $out, 'sum' => round($sum, 2), 'rest' => round($final - $sum, 2)];
    }

    /* ───────────────────────── υπολογισμός ───────────────────────── */

    /**
     * @return array{cfg:array, lines:array, rec:float, once:float, raw:float, discAmt:float,
     *   net:float, vat:float, gross:float, y2net:float, y2:float, missing:array}
     */
    public static function calc($cfg)
    {
        $c = self::normalize($cfg);
        $items = self::defaultItems();
        $lines = []; $rec = 0; $once = 0; $missing = [];
        foreach ($items as $it) {
            if ($it['plat'] !== $c['plat']) { continue; }
            $id = $it['id'];
            $on = !empty($c['on'][$id]);
            $q = $it['perExt'] ? (int) $c['ext'] : (int) $c['qty'][$id];
            $p = (float) $c['price'][$id];
            $tot = $on ? round($q * $p, 2) : 0;
            if ($on && $q > 0 && $p <= 0) { $missing[] = $it['name']; }
            if ($on) { if ($it['rec']) { $rec += $tot; } else { $once += $tot; } }
            $lines[] = ['id' => $id, 'cat' => (int) $it['cat'], 'catName' => self::CATS[(int) $it['cat']],
                'name' => $it['name'], 'note' => $it['note'], 'rec' => (bool) $it['rec'], 'perExt' => (bool) $it['perExt'],
                'on' => $on, 'qty' => $q, 'price' => $p, 'total' => $tot, 'needs' => $on && $q > 0 && $p <= 0];
        }
        $raw = round($rec + $once, 2);
        $d = $c['disc'] / 100;
        $discAmt = round($raw * $d, 2);
        $net = round($raw - $discAmt, 2);
        $vat = round($net * $c['o']['vat'] / 100, 2);
        $y2net = round($rec * (1 - $d), 2);
        return ['cfg' => $c, 'lines' => $lines, 'rec' => round($rec, 2), 'once' => round($once, 2), 'raw' => $raw,
            'discAmt' => $discAmt, 'net' => $net, 'vat' => $vat, 'gross' => round($net + $vat, 2),
            'y2net' => $y2net, 'y2' => round($y2net * (1 + $c['o']['vat'] / 100), 2), 'missing' => $missing];
    }

    /** Ποσό προσφοράς: 1ο έτος προ ΦΠΑ, μετά την έκπτωση. */
    public static function offerAmount($cfg)
    {
        return (float) self::calc($cfg)['net'];
    }

    /* ───────────────────────── μορφοποίηση ───────────────────────── */

    private static function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
    private static function fmtEur($v) { return number_format((float) $v, 2, ',', '.') . ' €'; }
    private static function fmtNum($v) { return number_format((float) $v, 2, ',', '.'); }
    private static function fmtPct($v) { return rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') . '%'; }
    private static function grDate($iso)
    {
        $ts = strtotime($iso ?: 'now') ?: time();
        $mo = ['', 'Ιανουαρίου', 'Φεβρουαρίου', 'Μαρτίου', 'Απριλίου', 'Μαΐου', 'Ιουνίου',
            'Ιουλίου', 'Αυγούστου', 'Σεπτεμβρίου', 'Οκτωβρίου', 'Νοεμβρίου', 'Δεκεμβρίου'];
        return (int) date('j', $ts) . ' ' . $mo[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }

    /* ───────────────────────── έγγραφο ───────────────────────── */

    const LOGO_CLOUDON = '/project/doc-assets/cloudon.svg';

    public static function docCss()
    {
        return <<<'CSS'
:root{
  --paper:#FFFFFF; --surface:#F4F7F9; --surface-2:#E9EFF3; --shell:#E6EBEF;
  --ink:#111C24; --ink-2:#31454F; --muted:#5E7382;
  --rule:#DCE4EA; --rule-strong:#B7C6D0;
  --accent:#0094D4; --accent-deep:#00567F; --accent-wash:#E8F4FA;
  --good:#17795A; --good-wash:#E4F2ED;
  --alert:#9A5B12; --alert-wash:#FBF0E2; --alert-rule:#C79A3C;
}
*{box-sizing:border-box}
body{margin:0;padding:22px;background:var(--shell);color:var(--ink);
  font:10.5pt/1.55 "Segoe UI Variable Text","Segoe UI",Inter,system-ui,-apple-system,"Helvetica Neue",Arial,sans-serif;
  -webkit-font-smoothing:antialiased;-webkit-print-color-adjust:exact;print-color-adjust:exact}
#offer-doc{max-width:210mm;margin:0 auto;background:var(--paper);
  box-shadow:0 0 0 1px rgba(17,28,36,.06),0 12px 40px rgba(17,28,36,.10)}
.od-pad{padding:0 16mm}
.od-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;
  padding-top:14mm;padding-bottom:5mm;border-bottom:3px solid var(--accent)}
.od-head img{width:44mm;height:auto;display:block}
.od-tag{font-size:8.5pt;letter-spacing:.16em;text-transform:uppercase;color:var(--accent-deep);font-weight:600;margin-top:6px}
.od-cert{text-align:right;font-size:8pt;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-2);font-weight:600;display:grid;gap:4px}
.od-cert .reg{font-size:8.5pt;letter-spacing:0;text-transform:none;color:var(--muted);font-weight:400}
.od-addr{display:grid;grid-template-columns:auto 1fr;gap:5px 16px;padding-top:8mm;font-size:10.5pt;margin:0}
.od-addr dt{font-size:8pt;letter-spacing:.11em;text-transform:uppercase;color:var(--muted);font-weight:700;padding-top:3px}
.od-addr dd{margin:0}
.od-addr .tod{color:var(--ink-2);font-size:9.5pt;line-height:1.5}
.od-meta{display:flex;gap:22px;flex-wrap:wrap;margin-top:6mm;padding-top:4mm;
  border-top:1px solid var(--rule);font-size:9.5pt;color:var(--muted)}
.od-meta b{color:var(--ink);font-weight:600;font-variant-numeric:tabular-nums}
.od-subj{margin-top:7mm;padding:12px 18px;background:var(--accent-wash);border-left:4px solid var(--accent)}
.od-subj .l{font-size:8pt;letter-spacing:.13em;text-transform:uppercase;color:var(--accent-deep);font-weight:700}
.od-subj h1{font-family:Georgia,"Times New Roman",serif;font-weight:700;font-size:18pt;margin:4px 0 0;line-height:1.2}
.od-letter{padding-top:7mm;color:var(--ink-2)}
.od-letter p{margin:0 0 10px}
.od-sec{padding-top:9mm;break-inside:avoid}
.od-sec.flow{break-inside:auto}
.od-sec h2{font-family:Georgia,"Times New Roman",serif;font-weight:700;font-size:15.5pt;margin:0 0 4px;color:var(--ink)}
.od-sec .sub{color:var(--muted);font-size:10pt;margin:0 0 12px}
.od-sec h3{font-size:10.5pt;font-weight:700;margin:14px 0 6px}
.od-sec ul{margin:0 0 10px;padding-left:18px;display:grid;gap:4px;color:var(--ink-2)}
.od-tiles{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--rule);border:1px solid var(--rule)}
.od-tiles.t3{grid-template-columns:repeat(3,1fr)}
.od-tile{background:var(--paper);padding:11px 14px}
.od-tile .k{font-size:8pt;letter-spacing:.11em;text-transform:uppercase;color:var(--muted);font-weight:700}
.od-tile .v{font-family:Georgia,"Times New Roman",serif;font-weight:700;font-size:16pt;margin-top:4px;font-variant-numeric:tabular-nums}
.od-tile .v.a{color:var(--accent-deep)}
.od-tile .n{font-size:8.5pt;color:var(--muted);margin-top:2px}
.od-twrap{border:1px solid var(--rule-strong)}
table.od-price{width:100%;border-collapse:collapse;font-size:10pt}
table.od-price caption{caption-side:top;text-align:left;padding:10px 14px;background:var(--accent-deep);
  color:#fff;font-size:8pt;letter-spacing:.15em;text-transform:uppercase;font-weight:700}
table.od-price th{text-align:left;padding:8px 12px;background:var(--surface-2);
  font-size:8pt;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-2);font-weight:700;
  border-bottom:1px solid var(--rule-strong)}
table.od-price th.num,table.od-price td.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
table.od-price td{padding:8px 12px;border-bottom:1px solid var(--rule);vertical-align:top}
table.od-price tr{break-inside:avoid}
table.od-price tr.g td{background:var(--surface);font-weight:700;color:var(--accent-deep);
  font-size:8pt;letter-spacing:.11em;text-transform:uppercase}
table.od-price td small{display:block;color:var(--muted);font-size:8.5pt;margin-top:2px;line-height:1.45}
table.od-price tr.s td{border-top:2px solid var(--rule-strong);font-weight:600;background:var(--surface)}
table.od-price tr.s.d td{color:var(--good)}
table.od-price tr.t td{background:var(--accent-deep);color:#fff;font-weight:700;font-size:12pt;border-bottom:0}
.od-fine{font-size:8.5pt;color:var(--muted);margin-top:8px}
.od-terms{display:grid;border:1px solid var(--rule);margin:0}
.od-term{display:grid;grid-template-columns:48mm 1fr;border-bottom:1px solid var(--rule);break-inside:avoid}
.od-term:last-child{border-bottom:0}
.od-term dt{padding:10px 14px;background:var(--surface);font-size:8pt;letter-spacing:.09em;
  text-transform:uppercase;color:var(--ink-2);font-weight:700}
.od-term dd{padding:10px 14px;margin:0;color:var(--ink-2);font-size:10pt}
table.od-plan{width:100%;border-collapse:collapse;font-size:9.5pt;margin-top:6px}
table.od-plan td{padding:4px 0;border-bottom:1px dotted var(--rule)}
table.od-plan td.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap;font-weight:600}
table.od-plan td.pct{color:var(--muted);width:42px}
table.od-plan tr:last-child td{border-bottom:0}
.od-note{border:1px solid var(--rule);border-left:3px solid var(--alert-rule);background:var(--surface);
  padding:10px 14px;font-size:9.5pt;color:var(--ink-2);margin-top:10px;break-inside:avoid}
.od-warn{border:1px solid var(--alert-rule);background:var(--alert-wash);padding:10px 14px;font-size:9.5pt;
  color:var(--alert);margin-top:10px;font-weight:600}
.od-accept{margin-top:10mm;padding:7mm 0 10mm;border-top:3px solid var(--accent);break-inside:avoid}
.od-accept h2{font-family:Georgia,"Times New Roman",serif;font-size:14pt;margin:0 0 14px}
.od-sigs{display:grid;grid-template-columns:1fr 1fr;gap:26px}
.od-sig .w{font-size:8pt;letter-spacing:.13em;text-transform:uppercase;color:var(--muted);font-weight:700}
.od-sig .o{font-weight:600;margin:5px 0 34px;font-size:10pt}
.od-sig .ln{border-top:1px solid var(--rule-strong);padding-top:6px;font-size:9pt;color:var(--muted)}
.od-foot{background:var(--surface);border-top:1px solid var(--rule);padding:12px 16mm;
  font-size:8.5pt;color:var(--muted);display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.od-foot b{color:var(--ink-2);font-weight:600}
@page{size:A4;margin:12mm 0 14mm}
@media print{
  body{background:#fff;padding:0}
  #offer-doc{box-shadow:none;max-width:none}
  .od-head{padding-top:2mm}
}
CSS;
    }

    /** Τα στοιχεία του παραλήπτη — μόνο όσα έχουν συμπληρωθεί. */
    private static function toDetails(array $o)
    {
        $bits = [];
        if ($o['address'] !== '') { $bits[] = self::e($o['address']); }
        $tax = [];
        if ($o['afm'] !== '') { $tax[] = 'ΑΦΜ ' . self::e($o['afm']); }
        if ($o['doy'] !== '') { $tax[] = 'Δ.Ο.Υ. ' . self::e($o['doy']); }
        if ($tax) { $bits[] = implode(' · ', $tax); }
        $con = [];
        if ($o['cphone'] !== '') { $con[] = 'τηλ. ' . self::e($o['cphone']); }
        if ($o['cemail'] !== '') { $con[] = self::e($o['cemail']); }
        if ($con) { $bits[] = implode(' · ', $con); }
        return $bits ? '<div class="tod">' . implode('<br>', $bits) . '</div>' : '';
    }

    private static function techSection()
    {
        return '<div class="od-sec"><h2>Δυνατότητες συστήματος</h2>
      <p class="sub">Οι βασικές λειτουργίες που παραδίδονται με το σύστημα.</p>
      <h3>Δρομολόγηση και υποδοχή</h3>
      <ul><li>Αυτόματη υποδοχή με απεριόριστη δρομολόγηση, ωράριο, αργίες και εφημερίες</li>
      <li>Ουρά αναμονής με αναγγελία θέσης και επιστροφή κλήσης</li>
      <li>Μεταγωγή, αναμονή με μουσική και διαφημιστικά μηνύματα</li></ul>
      <h3>Μηνύματα και τεκμηρίωση</h3>
      <ul><li>Φωνητικό ταχυδρομείο με αποστολή στο email</li>
      <li>Ενσωματωμένος FAX server για λήψη σε ηλεκτρονική μορφή</li>
      <li>Πλήρες ιστορικό κλήσεων, στατιστικά και διαχείριση χρεώσεων</li></ul>
      <h3>Κινητικότητα και ασφάλεια</h3>
      <ul><li>Εφαρμογές Android και iOS — το εσωτερικό στο κινητό</li>
      <li>Web client μέσα από τον browser, χωρίς εγκατάσταση</li>
      <li>Κρυπτογράφηση σηματοδοσίας και φωνής, προστασία από απάτη κλήσεων</li>
      <li>24ωρη υπηρεσία αντιγράφων ασφαλείας</li></ul>
    </div>
    <div class="od-sec"><h2>Απαιτήσεις χώρου εγκατάστασης</h2>
      <ul><li>Καθαρός, αεριζόμενος και ασφαλής χώρος — συνιστάται το δωμάτιο υπολογιστών</li>
      <li>5 λήψεις ρεύματος 220V AC/50Hz μέσω ανεξάρτητου ασφαλειοδιακόπτη 16A, με UPS τουλάχιστον 2200 VA</li>
      <li>Αγωγός γείωσης με αντίσταση 3 Ohm — συνιστάται «καθαρή γη»</li>
      <li>Χωρίς υγροψυκτικά μηχανήματα στον χώρο</li>
      <li>Ένα καλώδιο UTP cat 5e ανά θέση, κοινό για DATA και VOICE</li></ul>
    </div>';
    }

    public static function docHtml($cfg)
    {
        $r = self::calc($cfg);
        $c = $r['cfg']; $o = $c['o'];
        $P = self::platforms()[$c['plat']];
        $e = [self::class, 'e'];
        $until = date('Y-m-d', strtotime($o['date'] . ' +' . $o['validDays'] . ' days'));
        $intro = str_replace('{ΚΑΤΗΓΟΡΙΑ}', $P['letter'], $o['intro'] !== '' ? $o['intro'] : self::DEFAULT_INTRO);

        /* πίνακας κόστους ανά κατηγορία — μόνο ενεργά είδη με ποσότητα */
        $rows = '';
        foreach (self::CATS as $ci => $cat) {
            $g = array_filter($r['lines'], function ($l) use ($ci) { return $l['cat'] === $ci && $l['on'] && $l['qty'] > 0; });
            if (!$g) { continue; }
            $rows .= '<tr class="g"><td colspan="4">' . self::e($cat) . '</td></tr>';
            foreach ($g as $l) {
                $rows .= '<tr><td>' . self::e($l['name']) . ($l['note'] !== '' ? '<small>' . self::e($l['note']) . '</small>' : '') . '</td>'
                    . '<td class="num">' . (int) $l['qty'] . '</td><td class="num">' . self::fmtNum($l['price']) . '</td>'
                    . '<td class="num">' . self::fmtNum($l['total']) . '</td></tr>';
            }
        }
        $sums = '<tr class="s"><td colspan="3">Ετήσιο καθαρό ποσό</td><td class="num">' . self::fmtNum($r['rec']) . '</td></tr>'
            . '<tr class="s"><td colspan="3">Καθαρό ποσό εφάπαξ καταβολής</td><td class="num">' . self::fmtNum($r['once']) . '</td></tr>';
        if ($r['discAmt'] > 0.004) {
            $sums .= '<tr class="s d"><td colspan="3">Έκπτωση ' . self::fmtPct($c['disc']) . '</td><td class="num">− ' . self::fmtNum($r['discAmt']) . '</td></tr>';
        }
        $sums .= '<tr class="s"><td colspan="3">Γενικό σύνολο προ ΦΠΑ</td><td class="num">' . self::fmtNum($r['net']) . '</td></tr>'
            . '<tr class="s"><td colspan="3">Φ.Π.Α. ' . self::fmtPct($o['vat']) . '</td><td class="num">' . self::fmtNum($r['vat']) . '</td></tr>'
            . '<tr class="t"><td colspan="3">Γενικό σύνολο με ΦΠΑ</td><td class="num">' . self::fmtEur($r['gross']) . '</td></tr>';

        /* δόσεις: πληρωτέα ποσά (με ΦΠΑ) */
        $pl = self::planRows($o['plan'], $r['gross']);
        $planHtml = '';
        if (count($pl['rows']) > 1 || ($pl['rows'] && $pl['rows'][0]['w'] !== 'Με την αποδοχή της προσφοράς')) {
            $planHtml = '<table class="od-plan">';
            foreach ($pl['rows'] as $i => $pr) {
                $planHtml .= '<tr><td class="pct">' . self::e($pr['lab']) . '</td><td>' . self::e($pr['w'] ?: ('Δόση ' . ($i + 1))) . '</td>'
                    . '<td class="num">' . self::fmtEur($pr['eur']) . '</td></tr>';
            }
            $planHtml .= '</table><div class="od-fine">Τα ποσά των δόσεων περιλαμβάνουν ΦΠΑ ' . self::fmtPct($o['vat']) . ' — είναι τα ποσά που καταβάλλονται.'
                . (abs($pl['rest']) > 0.01 ? ' <b style="color:var(--alert)">Προσοχή: οι δόσεις δεν καλύπτουν το σύνολο (υπόλοιπο ' . self::fmtEur($pl['rest']) . ').</b>' : '') . '</div>';
        }

        $warn = $r['missing'] ? '<div class="od-warn">Λείπουν τιμές: ' . self::e(implode(' · ', $r['missing']))
            . ' — τα είδη αυτά ΔΕΝ μετρούν στο σύνολο. Συμπλήρωσέ τις στον τιμοκατάλογο πριν σταλεί η προσφορά.</div>' : '';

        return '<div id="offer-doc"><div class="od-pad">
    <div class="od-head">
      <div><img src="' . self::LOGO_CLOUDON . '" alt="CloudOn"><div class="od-tag">VoIP up your Life</div></div>
      <div class="od-cert"><span>Microsoft Certified Partner</span><span class="reg">Αρ. Μητρώου Ε.Ε.Τ.Τ.: ' . self::EETT . '</span></div>
    </div>
    <dl class="od-addr">
      <dt>Προς</dt><dd>' . ($o['client'] !== '' ? self::e($o['client']) : '—') . self::toDetails($o) . '</dd>'
      . ($o['attn'] !== '' ? '<dt>Υπόψη</dt><dd>' . self::e($o['attn']) . '</dd>' : '') . '
    </dl>
    <div class="od-meta">
      <span>' . self::e($o['city'] ?: 'Αθήνα') . ', <b>' . self::e(self::grDate($o['date'])) . '</b></span>'
      . ($o['protocol'] !== '' ? '<span>Αρ. πρωτοκόλλου <b>' . self::e($o['protocol']) . '</b></span>' : '') . '
      <span>Ισχύς έως <b>' . self::e(self::grDate($until)) . '</b></span>
    </div>
    <div class="od-subj"><div class="l">Θέμα</div>
      <h1>Οικονομική προσφορά τηλεφωνικού κέντρου ' . self::e($P['name']) . '</h1></div>
    <div class="od-letter">
      <p>' . ($o['attn'] !== '' ? 'Αγαπητέ/ή ' . self::e($o['attn']) . ',' : 'Αγαπητοί συνεργάτες,') . '</p>
      <p>' . nl2br(self::e($intro)) . '</p>
      <p>Παραμένουμε στη διάθεσή σας για οποιαδήποτε διευκρίνιση.</p>
    </div>

    <div class="od-sec">
      <h2>Σύνοψη</h2>
      <div class="od-tiles">
        <div class="od-tile"><div class="k">Πλατφόρμα</div><div class="v">' . self::e($P['name']) . '</div></div>
        <div class="od-tile"><div class="k">Εσωτερικά</div><div class="v">' . (int) $c['ext'] . '</div></div>
        <div class="od-tile"><div class="k">Ταυτόχρονες κλήσεις</div><div class="v">' . (int) $c['sc'] . '</div></div>
        <div class="od-tile"><div class="k">Σύνολο με ΦΠΑ</div><div class="v a">' . self::fmtEur($r['gross']) . '</div><div class="n">1ο έτος</div></div>
      </div>
    </div>

    <div class="od-sec flow">
      <h2>Οικονομική προσφορά</h2>
      <p class="sub">Διαχωρισμένη σε ετήσια επαναλαμβανόμενα κόστη και εφάπαξ κόστη έναρξης.</p>
      <div class="od-twrap">
        <table class="od-price"><caption>Ανάλυση κόστους</caption>
        <thead><tr><th>Περιγραφή</th><th class="num">Ποσότητα</th><th class="num">Τιμή €</th><th class="num">Σύνολο €</th></tr></thead>
        <tbody>' . $rows . $sums . '</tbody></table>
      </div>
      <p class="od-fine">Όλες οι τιμές δίνονται σε EURO και επιβαρύνονται με τον νόμιμο Φ.Π.Α.</p>' . $warn . '
    </div>

    <div class="od-sec">
      <h2>Κόστος επόμενων ετών</h2>
      <p class="sub">Ενδεικτικά, με βάση τον σημερινό τιμοκατάλογο — τα ετήσια είδη αναπροσαρμόζονται σύμφωνα με τον εκάστοτε ισχύοντα τιμοκατάλογο του κατασκευαστή.</p>
      <div class="od-tiles t3">
        <div class="od-tile"><div class="k">1ο έτος</div><div class="v">' . self::fmtEur($r['gross']) . '</div><div class="n">με ΦΠΑ · περιλαμβάνει εφάπαξ</div></div>
        <div class="od-tile"><div class="k">2ο έτος και μετά</div><div class="v a">' . self::fmtEur($r['y2']) . '</div><div class="n">με ΦΠΑ · μόνο ετήσια</div></div>
        <div class="od-tile"><div class="k">Διαφορά</div><div class="v">' . self::fmtEur($r['gross'] - $r['y2']) . '</div><div class="n">τα εφάπαξ δεν επαναλαμβάνονται</div></div>
      </div>
    </div>
    ' . ($o['full'] ? self::techSection() : '') . '
    <div class="od-sec flow">
      <h2>Όροι και προϋποθέσεις</h2>
      <dl class="od-terms">
        <div class="od-term"><dt>Τιμές</dt><dd>Σε EURO, επιβαρύνονται με τον νόμιμο Φ.Π.Α.</dd></div>
        <div class="od-term"><dt>Ισχύς προσφοράς</dt><dd>' . (int) $o['validDays'] . ' ημέρες — έως ' . self::e(self::grDate($until)) . '.</dd></div>
        <div class="od-term"><dt>Τρόπος πληρωμής</dt><dd>' . self::e($o['payMethod']) . ($planHtml ? '' : ', με την αποδοχή της προσφοράς') . '.' . $planHtml . '</dd></div>
        <div class="od-term"><dt>Χρόνος παράδοσης</dt><dd>' . self::e($o['delivery']) . '</dd></div>
        <div class="od-term"><dt>Εγκατάσταση</dt><dd>Από έμπειρους τεχνικούς της CLOUDON IKE, σε έτοιμο, ελεγμένο και αριθμημένο καλωδιακό σύστημα.</dd></div>
      </dl>
      <div class="od-note"><b>Όρος ολοκλήρωσης.</b> Εάν η εγκατάσταση και λειτουργία του συστήματος δεν ολοκληρωθεί πλήρως λόγω υπαιτιότητας του ΟΤΕ, λόγω υπαιτιότητας της εταιρίας σας, ή λόγω άλλων αιτιών για τις οποίες δεν ευθύνεται η εταιρία μας, η εγκατάσταση θεωρείται αποπερατωμένη και η εξόφληση θα γίνει κανονικά.</div>
    </div>

    <div class="od-accept">
      <h2>Αποδοχή προσφοράς</h2>
      <div class="od-sigs">
        <div class="od-sig"><div class="w">Μετά τιμής</div><div class="o">Για την CloudOn IKE' . ($o['seller'] !== '' ? ' — ' . self::e($o['seller']) : ' — Τμήμα Πωλήσεων') . '</div><div class="ln">Υπογραφή και σφραγίδα</div></div>
        <div class="od-sig"><div class="w">Ο Πελάτης</div><div class="o">' . ($o['client'] !== '' ? self::e($o['client']) : '—') . '</div><div class="ln">Υπογραφή, σφραγίδα και ημερομηνία</div></div>
      </div>
    </div>
  </div>
  <div class="od-foot"><span><b>CloudOn IKE</b> · Λύσεις e-business, cloud &amp; επικοινωνιών</span>
    <span>Αρ. Μητρώου Ε.Ε.Τ.Τ.: <b>' . self::EETT . '</b></span></div></div>';
    }
}
