<?php
/**
 * CloudOn Agent — ΔΡΟΜΟΛΟΓΗΣΗ ΚΑΤΑ ΠΕΛΑΤΗ (πριν από τη ρεσεψιόν).
 *
 * ΤΟ ΝΟΗΜΑ (απόφαση 20/09/2026): ο τηλεφωνικός κατάλογος του panel λέει τι
 * έχει ο κάθε πελάτης από εμάς και αν καλύπτεται από τεχνική υποστήριξη.
 * Με αυτά, μια κλήση από γνωστό αριθμό μπορεί να πάει ΚΑΤΕΥΘΕΙΑΝ στην ουρά
 * του προϊόντος, χωρίς ρεσεψιόν. Στην αμφιβολία → ρεσεψιόν (902).
 *
 * ΣΤΑΔΙΟ 1 — ΣΚΙΩΔΕΣ: για κάθε εισερχόμενη καταγράφουμε τι ΘΑ αποφασίζαμε
 * (mod_cpm_pbx_route_log, applied=0) χωρίς να δρομολογούμε. Έτσι ελέγχεται
 * ο κατάλογος με πραγματικές κλήσεις πριν αγγίξουμε το 3CX.
 * ΣΤΑΔΙΟ 2: το call flow script ρωτά decide() σε κάθε κλήση.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Route
{
    /**
     * Προϊόν → [ετικέτα, ουρά]. Η ΛΙΣΤΑ είναι του Παναγιώτη (20/09/2026) — δεν
     * συμπληρώνεται αυτόματα από πουθενά. Οι ουρές είναι του Blueprint::TOPICS.
     * PharmacyOne CY και τα τρία e-commerce πάνε προσωρινά σε Support/CloudOn
     * μέχρι να οριστεί δική τους σειρά εσωτερικών.
     */
    const PRODUCTS = [
        'cloud'          => ['Cloud services',   '813'],
        'softone'        => ['SoftOne',          '810'],
        'pharmacyone_gr' => ['PharmacyOne GR',   '810'],
        'pharmacyone_cy' => ['PharmacyOne CY',   '810'],
        '3cx'            => ['3CX',              '812'],
        'yeastar'        => ['Yeastar',          '812'],
        'caron'          => ['CarOn',            '807'],
        'rxvision'       => ['RxVision',         '814'],
        'boxvisio'       => ['BoxVisio',         '814'],
        'ecommerce'      => ['E-Commerce',       '811'],
        'marketplace'    => ['Marketplace',      '811'],
        'courier'        => ['Courier module',   '811'],
    ];

    const AI_DN = '902';

    /** Οι ουρές που μπορεί να διαλέξει κανείς ως «πάντα σε» — από το σχέδιο. */
    public static function queues()
    {
        $out = [];
        foreach (Pbx3cxBlueprint::TOPICS as $dn => $t) { $out[$dn] = $t['name'] . ' (' . implode('→', $t['agents']) . ')'; }
        return $out;
    }

    /**
     * ΠΑΛΙΑ slug που δεν έχουν πια δικό τους προϊόν. Δεν τα πετάμε: υπάρχουν
     * καρτέλες που τα κρατούν, και ένα άγνωστο slug θα σβηνόταν σιωπηλά.
     */
    const ALIASES = ['pharmacyone_cy' => 'pharmacyone_gr'];

    /**
     * Ο κατάλογος προϊόντων ΑΠΟ ΤΗ ΒΑΣΗ — μία λίστα για όλο το σύστημα.
     *
     * Πριν (20/09/2026) ήταν σκληρή λίστα εδώ, οπότε κάθε νέο προϊόν απαιτούσε
     * αλλαγή κώδικα για να φανεί στον τηλεφωνικό κατάλογο, και οι τρεις λίστες
     * του συστήματος απέκλιναν μεταξύ τους. Τώρα συντηρείται από τις Ρυθμίσεις.
     *
     * @return array<string,array{0:string,1:string}> slug => [ετικέτα, ουρά]
     */
    public static function products()
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }

        $rows = Capsule::table('mod_cpm_products')->where('active', 1)
            ->orderBy('sort')->orderBy('id')->get(['id', 'name', 'parent_id', 'route_dn', 'book_key']);
        if (!count($rows)) { return $cache = self::PRODUCTS; }   // άδεια βάση: η παλιά λίστα

        $names = $dns = [];
        foreach ($rows as $r) { $names[(int) $r->id] = (string) $r->name; $dns[(int) $r->id] = (string) $r->route_dn; }

        $out = [];
        foreach ($rows as $r) {
            $key = trim((string) $r->book_key) ?: self::slug((string) $r->name, (int) $r->id);
            $label = $r->parent_id && isset($names[(int) $r->parent_id])
                ? $names[(int) $r->parent_id] . ' › ' . $r->name
                : (string) $r->name;
            /* Η υποκατηγορία κληρονομεί την ουρά του γονέα αν δεν έχει δική της. */
            $dn = (string) $r->route_dn ?: ($r->parent_id ? ($dns[(int) $r->parent_id] ?? '') : '');
            $out[$key] = [$label, $dn];
        }
        return $cache = $out;
    }

    /** Σταθερό slug από όνομα· σε σύγκρουση ή κενό, πέφτει στο id. */
    private static function slug($name, $id)
    {
        $n = mb_strtolower(trim($name));
        $n = strtr($n, ['ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω']);
        $n = preg_replace('/[^a-z0-9]+/u', '_', $n);
        $n = trim((string) $n, '_');
        return $n !== '' && preg_match('/^[a-z0-9_]+$/', $n) ? $n : ('p' . $id);
    }

    public static function productList()
    {
        $q = self::queues();
        $out = [];
        foreach (self::products() as $k => [$label, $dn]) {
            $out[] = ['key' => $k, 'label' => $label, 'dn' => $dn, 'queue' => $q[$dn] ?? $dn];
        }
        return $out;
    }

    public static function parseProducts($v)
    {
        $out = [];
        foreach (explode(',', (string) $v) as $p) {
            $p = trim($p);
            if ($p === '') { continue; }
            if (isset(self::ALIASES[$p])) { $p = self::ALIASES[$p]; }   // παλιό slug → σημερινό
            if (isset(self::products()[$p])) { $out[] = $p; }
        }
        return array_values(array_unique($out));
    }

    /**
     * Η απόφαση για έναν αριθμό, ΤΩΡΑ.
     *
     * decision: queue (κατευθείαν σε ουρά) · ai (ρεσεψιόν) · ai_nocover (ρεσεψιόν,
     * δεν καλύπτεται — θα πάρει την «καθυστέρηση») · drop (μαύρη λίστα, μελλοντικό)
     */
    public static function decide($e164, $ts = null)
    {
        $e164 = (string) $e164;
        $mode = Pbx3cxBlueprint::agentMode($ts);
        $res = ['e164' => $e164, 'mode' => $mode, 'decision' => 'ai', 'dn' => self::AI_DN,
            'reason' => '', 'book' => 0, 'name' => '', 'products' => [], 'cover' => null];

        if ($e164 === '' || strlen(preg_replace('/\D/', '', $e164)) < 6) {
            $res['reason'] = 'ανώνυμος ή άκυρος αριθμός';
            return $res;
        }
        $hit = Book::resolve($e164);
        if (!$hit) {
            if (Capsule::table('mod_cpm_phone_skip')->where('e164', $e164)->exists()) {
                $res['reason'] = 'σημειωμένος ως «δεν είναι πελάτης»';
            } else {
                $res['reason'] = 'άγνωστος αριθμός — δεν είναι στον κατάλογο';
            }
            return $res;
        }
        $b = Capsule::table('mod_cpm_book')->where('id', (int) $hit['id'])->first();
        $res['book'] = (int) $b->id;
        $res['name'] = Book::label((array) $b);
        $res['products'] = self::parseProducts($b->products ?? '');
        $res['cover'] = $b->support_cover === null ? null : (int) $b->support_cover;

        if ($mode !== 'office') {
            $res['reason'] = 'εκτός ωραρίου — η ρεσεψιόν καταχωρεί αίτημα (ή απόγευμα/Σάββατο → ουρά Emergency)';
            return $res;
        }
        if ($res['cover'] === 0) {
            $res['decision'] = 'ai_nocover';
            $res['reason'] = 'δεν καλύπτεται από τεχνική υποστήριξη';
            return $res;
        }
        if ($res['cover'] === null) {
            $res['reason'] = 'δεν έχει σημειωθεί αν καλύπτεται';
            return $res;
        }
        /* Καλύπτεται. Ρητή επιλογή «πάντα σε» υπερισχύει. */
        $override = trim((string) ($b->route_dn ?? ''));
        if ($override !== '' && isset(Pbx3cxBlueprint::TOPICS[$override])) {
            $res['decision'] = 'queue'; $res['dn'] = $override;
            $res['reason'] = 'καλύπτεται · «πάντα σε» ' . Pbx3cxBlueprint::TOPICS[$override]['name'];
            return $res;
        }
        if (!$res['products']) {
            $res['reason'] = 'καλύπτεται, αλλά δεν έχει σημειωθεί προϊόν';
            return $res;
        }
        $dns = [];
        foreach ($res['products'] as $p) { $dns[self::PRODUCTS[$p][1]] = true; }
        if (count($dns) === 1) {
            $dn = (string) array_key_first($dns);
            $res['decision'] = 'queue'; $res['dn'] = $dn;
            $res['reason'] = 'καλύπτεται · ' . implode(', ', array_map(function ($p) { return self::PRODUCTS[$p][0]; }, $res['products']))
                . ' → ' . Pbx3cxBlueprint::TOPICS[$dn]['name'];
            return $res;
        }
        $res['reason'] = 'καλύπτεται, αλλά έχει προϊόντα σε διαφορετικές ουρές — η ρεσεψιόν ρωτά';
        return $res;
    }

    /** Καταγραφή απόφασης. applied=0 → σκιώδης (δεν δρομολογήθηκε). */
    public static function log(array $r, $applied = 0, $hist = null, $at = null)
    {
        try {
            Capsule::table('mod_cpm_pbx_route_log')->insert([
                'e164' => mb_substr((string) $r['e164'], 0, 24), 'book_id' => $r['book'] ?: null,
                'mode' => $r['mode'], 'decision' => $r['decision'], 'dn' => (string) $r['dn'],
                'reason' => mb_substr((string) $r['reason'], 0, 200), 'applied' => (int) $applied,
                'history_id' => $hist ? mb_substr((string) $hist, 0, 64) : null,
                'created_at' => $at ?: date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) { /* η καταγραφή δεν χαλάει ποτέ τη ροή */ }
    }

    /** Σκιώδης απόφαση για μια εισερχόμενη που μόλις γράφτηκε (από το Report). */
    public static function shadow(array $row, $hist)
    {
        if (($row['direction'] ?? '') !== 'in') { return; }
        if (Capsule::table('mod_cpm_pbx_route_log')->where('history_id', $hist)->exists()) { return; }
        $ts = !empty($row['started_at']) ? strtotime($row['started_at']) : null;
        self::log(self::decide((string) ($row['other_e164'] ?? ''), $ts), 0, $hist, $row['started_at'] ?? null);
    }
}
