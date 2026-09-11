<?php
/**
 * CloudOn Project Manager — κοστολόγηση PharmacyOne.
 *
 * Μεταφορά του υπολογιστικού φύλλου «PharmacyOne … .xlsx» (2026) σε κώδικα, ώστε
 * η προσφορά να μπαίνει κατευθείαν στο κύκλωμα Προσφορών αντί να ζει σε ένα
 * αυτόνομο αρχείο.
 *
 * **Ο υπολογισμός γίνεται ΕΔΩ, στον server, και μόνο εδώ.** Η οθόνη ζητά
 * `pharmacy_calc` για ζωντανή προεπισκόπηση· έτσι το ποσό που μπαίνει στην
 * προσφορά και οι αριθμοί του εγγράφου δεν μπορούν ποτέ να διαφέρουν.
 *
 * Οι τύποι είναι αντιγραφή 1:1 από τα κελιά του Excel — τα σχόλια κρατούν την
 * αναφορά κελιού για να μπορεί κάποιος να τους ελέγξει.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

class Pharmacy
{
    const HOUR_RATE = 80;                        // χρέωση ώρας υπηρεσιών
    /** Πώς εξοφλείται η προσφορά — η πρώτη τιμή είναι η προεπιλογή. */
    const PAY_METHODS = ['Τραπεζική κατάθεση', 'Μετρητά', 'Κάρτα (POS / e-banking)', 'Επιταγή'];
    /** Πώς τιμολογείται η ετήσια συνδρομή. */
    const SUB_CYCLES = ['Ετησίως προκαταβολικά', 'Ανά εξάμηνο', 'Μηνιαία'];
    /** Τα τρία ποσοστά έκπτωσης — αδειών CloudOn, υπηρεσιών, αδειών Soft1. */
    const DISC_CELLS = ['L4', 'J8', 'K8'];
    /** Γραμμές τιμοκαταλόγου που ΚΑΜΙΑ εκπτωτική πολιτική δεν αγγίζει (απόφαση
        11/9/2026): η ώρα τηλεφωνικής υποστήριξης χρεώνεται πάντα στην τιμή της. */
    const NO_DISCOUNT_CELLS = ['S37'];
    const SETUP_RATE = [15, 25, 25, 30];         // ανά έκδοση, ώρα παραμετροποίησης
    const MYDATA_SETUP = [200, 200, 250, 250];   // ανά έκδοση, στήσιμο myData

    /* ══════════════ προεπιλογές ══════════════ */

    public static function defaultParams()
    {
        /* Οι εκπτώσεις ξεκινούν όπως το φύλλο 2026 V2: αδειών CloudOn 0%, υπηρεσιών 20%,
           αδειών Soft1 15% — εκτός αν έχει οριστεί άλλη πολιτική στον βασικό τιμοκατάλογο. */
        $p = ['I4' => 4, 'M4' => 4, 'J4' => 1, 'K4' => 1, 'L4' => 0,
            'I6' => 0, 'J6' => 0, 'K6' => 0, 'L6' => 0,
            'I8' => 3, 'J8' => 0.2, 'K8' => 0.15, 'L8' => 0, 'M6' => 8,
            'I10' => 0, 'J10' => 0, 'K10' => 0, 'L10' => 0, 'I12' => 0];
        $d = self::catalogOverride()['disc'] ?? null;
        if (is_array($d)) {
            foreach (self::DISC_CELLS as $k) {
                if (isset($d[$k]) && is_numeric($d[$k])) { $p[$k] = max(0, min(0.99, (float) $d[$k])); }
            }
        }
        return $p;
    }

    /**
     * Τα πεδία της φόρμας: [κελί, ετικέτα, τύπος, διευκρίνιση, περιοχή].
     * Η σειρά είναι και η σειρά εμφάνισης· οι περιοχές μαζεύουν τα όμοια μεταξύ τους,
     * ώστε να μη χρειάζεται να σαρώνεις 18 κουτιά για να βρεις μια έκπτωση.
     */
    public static function paramDefs()
    {
        $A = 'Χρήστες & άδειες';
        $B = 'Εξοπλισμός & διασυνδέσεις';
        $C = 'Εκπτώσεις';
        return [
            ['I4', 'SoftOne user', 'num', 'ο 1ος περιλαμβάνεται στη βασική άδεια — χρεώνονται οι υπόλοιποι', $A],
            ['M4', 'PharmacyOne user', 'num', 'χρεώνονται όλοι, ανά χρήστη', $A],
            ['J4', 'Υποκαταστήματα', 'num', '', $A],
            ['K4', 'Εταιρείες', 'num', '', $A],
            ['I8', 'GB', 'num', 'επιπλέον χώρος cloud', $A],
            ['M6', 'Ώρες τηλεφωνικής υποστήριξης', 'num', 'όσες ώρες συμφωνηθούν — χρεώνονται με την τιμή ώρας του τιμοκαταλόγου', $A],

            ['I10', 'Αρ. Η/Υ', 'num', '', $B],
            ['K10', 'Αρ. εκτυπωτών', 'num', '', $B],
            ['J10', 'E-Shop', 'num', '', $B],
            ['J6', 'Courier Module', 'num', 'πόσες εταιρείες courier', $B],
            ['I6', 'Αριθμός Price Checker', 'num', '', $B],
            ['K6', 'Αρ. POS Connector', 'num', '', $B],
            ['L6', 'Διασυνδέσεις POS', 'num', '', $B],
            ['L10', 'Αρ. Picking & Packing modules', 'num', '', $B],
            ['I12', 'Cash Guard', 'num', '', $B],
            ['L8', 'Αριθμός RWA', 'num', '', $B],

            ['L4', 'Έκπτωση επί των αδειών CloudOn', 'pct', '', $C],
            ['J8', 'Έκπτωση επί των υπηρεσιών', 'pct', '', $C],
            ['K8', 'Έκπτωση επί των αδειών Soft1', 'pct', '', $C],
        ];
    }

    public static function defaultModules()
    {
        $o = [];
        foreach (self::moduleGroups() as $g) {
            foreach ($g['items'] as $it) { $o[$it[0]] = 0; }
        }
        return $o;
    }

    /** Ποσότητα ανά module (οι στήλες «Τεμ» του φύλλου). 0 = «όσο λέει ο τύπος». */
    public static function defaultQty()
    {
        return self::defaultModules();
    }

    public static function moduleGroups()
    {
        return [
            ['title' => 'SoftOne', 'items' => [
                ['J16', 'Ομάδες Εταιρειών'], ['J17', 'Sql Connector'], ['J18', 'ECOS']]],
            ['title' => 'CloudOn Modules', 'items' => [
                ['L16', 'Logi Scoup'], ['L17', 'Data Box'], ['L18', 'IQVIA'],
                ['L19', 'RWA module'], ['L20', 'Price Checker'], ['L21', 'Διασύνδεση με E-shop'],
                ['L22', 'Picking and Packing module'], ['L23', 'E-Label'], ['L24', 'Courier Module'],
                ['L25', 'WMS Lite']]],
            ['title' => 'CloudOn Marketplace', 'items' => [
                ['N16', 'Skroutz'], ['N17', 'einvoice Skroutz'], ['N18', 'Shopflix'],
                ['N19', 'Wolt'], ['N20', 'E-Food'], ['N21', 'Cash Guard'], ['N22', 'Skroutz FBS']]],
        ];
    }

    /** Οι εργοστασιακές τιμές (από το φύλλο) — η βάση πάνω στην οποία εφαρμόζεται
        ο αποθηκευμένος βασικός τιμοκατάλογος. */
    public static function factoryRates()
    {
        return ['S3' => 250, 'S4' => 250, 'S5' => 250, 'S6' => 250, 'S7' => 475, 'S8' => 50,
            'S9' => 1200, 'S10' => 120, 'S11' => 250, 'S12' => 250, 'S13' => 2500, 'S14' => 50,
            'S15' => 550, 'S16' => 250, 'S17' => 120, 'S19' => 150, 'S20' => 250, 'S21' => 350,
            'S22' => 550, 'S23' => 550, 'S24' => 550, 'S25' => 550, 'S26' => 30, 'S27' => 50,
            'S28' => 30, 'S29' => 250, 'S30' => 150, 'S31' => 60, 'S32' => 500, 'S33' => 25,
            'S34' => 120, 'S35' => 850, 'S36' => 350, 'S37' => 80, 'S38' => 100,
            /* Ετήσια αναπροσαρμογή: τι ποσοστό της τιμής ξαναχρεώνεται κάθε χρόνο.
               Όπου το φύλλο δεν ορίζει ποσοστό, η γραμμή είναι καθαρά ετήσια άδεια — 100%. */
            'T3' => 1, 'T4' => 1, 'T5' => 1, 'T6' => 1, 'T7' => 1, 'T8' => 1,
            'T10' => 1, 'T11' => 1, 'T12' => 1, 'T14' => 1, 'T16' => 1, 'T17' => 1,
            'T19' => 1, 'T20' => 1, 'T21' => 1, 'T27' => 1, 'T29' => 1, 'T30' => 1,
            'T34' => 1, 'T36' => 1, 'T37' => 1, 'T38' => 1,
            'T13' => 0.2, 'T15' => 0.3, 'T22' => 0.3, 'T23' => 0.3, 'T24' => 0.3, 'T25' => 0.3,
            'T35' => 0.3];
    }

    /** Ο ΙΣΧΥΩΝ βασικός τιμοκατάλογος = εργοστασιακά + αποθηκευμένες αλλαγές. */
    public static function defaultRates()
    {
        static $memo = null;
        if ($memo !== null) { return $memo; }
        $base = self::factoryRates();
        $ov = self::catalogOverride()['r'] ?? [];
        foreach ($base as $k => $v) {
            if (isset($ov[$k]) && is_numeric($ov[$k])) { $base[$k] = (float) $ov[$k]; }
        }
        return $memo = $base;
    }

    /** Οι ΠΡΟΕΠΙΛΕΓΜΕΝΕΣ εκπτώσεις ανά γραμμή τιμοκαταλόγου (0 αν δεν έχει οριστεί). */
    public static function defaultLineDiscounts()
    {
        $ov = self::catalogOverride()['rd'] ?? [];
        $out = [];
        foreach (self::factoryRates() as $k => $v) {
            if ($k[0] !== 'S') { continue; }
            $out[$k] = (isset($ov[$k]) && is_numeric($ov[$k])) ? max(0, min(0.99, (float) $ov[$k])) : 0;
        }
        return $out;
    }

    /** Ο αποθηκευμένος βασικός τιμοκατάλογος (setting pharmacy_catalog), αν υπάρχει. */
    private static function catalogOverride()
    {
        static $loaded = false; static $data = [];
        if ($loaded) { return $data; }
        $loaded = true;
        try {
            $raw = \WHMCS\Database\Capsule::table('tbladdonmodules')
                ->where('module', 'cloudonprojects')->where('setting', 'pharmacy_catalog')->value('value');
            $d = json_decode((string) $raw, true);
            if (is_array($d)) { $data = $d; }
        } catch (\Throwable $e) {
        }
        return $data;
    }

    /** Αποθηκεύει τον βασικό τιμοκατάλογο (τιμές γραμμών + τιμές εκδόσεων). Μόνο
        γνωστά κλειδιά, αριθμητικά, μη-αρνητικά. */
    public static function saveBaseCatalog(array $rates, array $editions, array $disc = [], array $lineDisc = [])
    {
        $r = [];
        foreach (self::factoryRates() as $k => $v) {
            if (isset($rates[$k]) && is_numeric($rates[$k])) {
                $r[$k] = ($k[0] === 'T') ? max(0, min(1, (float) $rates[$k])) : max(0, round((float) $rates[$k], 4));
            }
        }
        $ed = [];
        foreach (self::factoryEditions() as $i => $e) {
            $ed[$i] = [
                'price'     => isset($editions[$i]['price']) && is_numeric($editions[$i]['price']) ? max(0, (float) $editions[$i]['price']) : $e['price'],
                'extraUser' => isset($editions[$i]['extraUser']) && is_numeric($editions[$i]['extraUser']) ? max(0, (float) $editions[$i]['extraUser']) : $e['extraUser'],
            ];
        }
        $ds = [];
        foreach (self::DISC_CELLS as $k) {
            if (isset($disc[$k]) && is_numeric($disc[$k])) { $ds[$k] = max(0, min(0.99, (float) $disc[$k])); }
        }
        /* Και η έκπτωση ανά γραμμή: αν η πολιτική λέει «Courier πάντα -10%», να μην
           χρειάζεται να τη γράφει ξανά ο πωλητής σε κάθε προσφορά. */
        $rd = [];
        foreach (self::factoryRates() as $k => $v) {
            if (in_array($k, self::NO_DISCOUNT_CELLS, true)) { continue; }   // προστατευμένη γραμμή
            if ($k[0] === 'S' && isset($lineDisc[$k]) && is_numeric($lineDisc[$k]) && $lineDisc[$k] > 0) {
                $rd[$k] = max(0, min(0.99, (float) $lineDisc[$k]));
            }
        }
        $json = json_encode(['r' => $r, 'ed' => $ed, 'disc' => $ds, 'rd' => $rd], JSON_UNESCAPED_UNICODE);
        $t = \WHMCS\Database\Capsule::table('tbladdonmodules')
            ->where('module', 'cloudonprojects')->where('setting', 'pharmacy_catalog');
        if ($t->exists()) { $t->update(['value' => $json]); }
        else {
            \WHMCS\Database\Capsule::table('tbladdonmodules')
                ->insert(['module' => 'cloudonprojects', 'setting' => 'pharmacy_catalog', 'value' => $json]);
        }
        return true;
    }

    /** Επαναφορά εργοστασιακών (διαγραφή override). */
    public static function resetBaseCatalog()
    {
        \WHMCS\Database\Capsule::table('tbladdonmodules')
            ->where('module', 'cloudonprojects')->where('setting', 'pharmacy_catalog')->delete();
        return true;
    }

    /** Ο τιμοκατάλογος: [κελί τιμής, περιγραφή, κελί ετήσιας αναπροσαρμογής]. */
    public static function rateRows()
    {
        return [
            ['S3', 'PharmacyOne B User', 'T3'], ['S4', 'PharmacyOne G User', 'T4'],
            ['S5', 'PharmacyOne Plus B User', 'T5'], ['S6', 'PharmacyOne Plus G User', 'T6'],
            ['S7', 'Αξία Ομάδας Εταιρειών', 'T7'], ['S8', 'Κόστος ανά GB', 'T8'],
            ['S9', 'Βασικό Π.Σ. S1', null], ['S10', 'My Data Express', 'T10'],
            ['S11', 'My Data Business', 'T11'], ['S12', 'Sql Connector', 'T12'],
            ['S13', 'Διασύνδεση με E-shop', 'T13'], ['S14', 'Price Checker', 'T14'],
            ['S15', 'Courier Module', 'T15'], ['S16', 'Picking and Packing module', 'T16'],
            ['S17', 'DataBox', 'T17'], ['S19', 'Logi Scoup', 'T19'], ['S20', 'RWA module', 'T20'],
            ['S21', 'E-Label', 'T21'], ['S22', 'Skroutz', 'T22'], ['S23', 'Shopflix', 'T23'],
            ['S24', 'Wolt', 'T24'], ['S25', 'E-Food', 'T25'], ['S26', 'Printer', null],
            ['S27', 'Αρ. POS Connector', 'T27'], ['S28', 'PharmacyOne Conf Per User', null],
            ['S29', 'einvoice Skroutz', 'T29'], ['S30', 'IQVIA', 'T30'],
            ['S31', 'Παραμετροποίηση POS', null], ['S32', 'Παραμετροποίηση ECOS', null],
            ['S33', 'Pc', null], ['S34', 'Cash Guard', 'T34'], ['S35', 'Skroutz FBS', 'T35'],
            ['S36', 'WMS Lite', 'T36'],
            ['S37', 'Ώρα τηλεφωνικής υποστήριξης', 'T37'],
            /* Το S38 (πάγιο ανά εταιρεία/υποκατάστημα) καταργήθηκε 11/9/2026: η
               τηλεφωνική χρεώνεται πλέον ΜΟΝΟ ώρες × τιμή ώρας. Η τιμή μένει στα
               defaults για τις παλιές αποθηκευμένες προσφορές, αλλά δεν εμφανίζεται
               πια στον τιμοκατάλογο ούτε μπαίνει σε κανέναν υπολογισμό. */
        ];
    }

    /** Εργοστασιακές εκδόσεις (τιμές από το φύλλο). */
    public static function factoryEditions()
    {
        return [
            ['key' => 'B',  'name' => 'PharmacyOne I',       'soft1' => 'Soft1 Express',      'cat' => 'Β', 'price' => 750,  'extraUser' => 120],
            ['key' => 'C',  'name' => 'PharmacyOne II',      'soft1' => 'Soft1 Express Plus', 'cat' => 'Γ', 'price' => 850,  'extraUser' => 120],
            ['key' => 'D',  'name' => 'PharmacyOne Plus I',  'soft1' => 'Soft1 Business',     'cat' => 'Β', 'price' => 1300, 'extraUser' => 165],
            ['key' => 'E',  'name' => 'PharmacyOne Plus II', 'soft1' => 'Soft1 Business',     'cat' => 'Γ', 'price' => 1500, 'extraUser' => 165],
        ];
    }

    /** Οι ΙΣΧΥΟΥΣΕΣ εκδόσεις = εργοστασιακά + αποθηκευμένες τιμές. */
    public static function defaultEditions()
    {
        static $memo = null;
        if ($memo !== null) { return $memo; }
        $e = self::factoryEditions();
        $ov = self::catalogOverride()['ed'] ?? [];
        foreach ($e as $i => $row) {
            if (isset($ov[$i]['price']) && is_numeric($ov[$i]['price'])) { $e[$i]['price'] = (float) $ov[$i]['price']; }
            if (isset($ov[$i]['extraUser']) && is_numeric($ov[$i]['extraUser'])) { $e[$i]['extraUser'] = (float) $ov[$i]['extraUser']; }
        }
        return $memo = $e;
    }

    /** Λειτουργικότητα ανά έκδοση (A5:E24 του φύλλου). */
    public static function features()
    {
        return [
            ['Χρώμα & Μέγεθος', [1, 1, 1, 1]],
            ['Αγορές, Διαχείριση Προμηθευτών, Πιστωτών', [1, 1, 1, 1]],
            ['Γεν. Λογιστική', [0, 1, 0, 1]],
            ['Έσοδα – Έξοδα', [1, 0, 1, 0]],
            ['Οικ. Συναλλαγές', [1, 1, 1, 1]],
            ['GroupSets', [1, 1, 1, 1]],
            ['Παρτίδες', [0, 0, 1, 1]],
            ['Business Units', [0, 0, 1, 1]],
            ['Loyalty Schemes', [1, 1, 1, 1]],
            ['Τιμολογιακές Πολιτικές', [0, 0, 1, 1]],
            ['Web Services Connector', [1, 1, 1, 1]],
            ['Run time Rights', [1, 1, 1, 1]],
        ];
    }

    /** Οι πέντε «κάδοι» της προσφοράς — έτσι ομαδοποιούνται οι γραμμές στο έγγραφο. */
    public static function buckets()
    {
        return [
            1 => ['Cloud Licensing (Περιλαμβάνει)', 'Αξία Ετήσιας Συνδρομής Λογισμικού', ''],
            2 => ['Cloud Licensing', 'Ετήσιο Κόστος Αδειών CloudOn', 'Αξία αδείας € /έτος'],
            3 => ['Υπηρεσίες Παραμετροποίησης', 'Συνολική Αξία Υπηρεσιών Παραμετροποίησης', ''],
            4 => ['Υπηρεσίες διασύνδεσης/courier', 'Αξία Υπηρεσιών διασύνδεσης/courier', ''],
            5 => ['Υπηρεσίες Νέων Εκδόσεων/ Τηλ. Υποστήριξης/Ετήσιες χρεώσεις', 'Αξία Ετήσιων Υπηρεσιών', ''],
        ];
    }

    /* ══════════════ οι τύποι ══════════════ */

    private static function y($yn, $k) { return !empty($yn[$k]) ? 1 : 0; }

    /**
     * Η ΕΤΗΣΙΑ αξία μιας γραμμής τιμοκαταλόγου: η τιμή επί το ποσοστό ετήσιας
     * αναπροσαρμογής. Χωρίς δηλωμένο ποσοστό ξαναχρεώνεται ολόκληρη (100%) — έτσι
     * δουλεύουν οι καθαρές ετήσιες άδειες.
     */
    private static function ar($r, $k)
    {
        $t = 'T' . substr($k, 1);
        return $r[$k] * (isset($r[$t]) ? (float) $r[$t] : 1);
    }

    /** Η δηλωμένη ποσότητα ενός module· αν δεν δηλώθηκε, η προεπιλογή του τύπου. */
    private static function q($q, $k, $def = 1)
    {
        $v = isset($q[$k]) && is_numeric($q[$k]) ? (float) $q[$k] : 0;
        return $v > 0 ? $v : $def;
    }

    /** Οι χρήστες PharmacyOne (H6 του φύλλου) — όσοι ακριβώς γράφτηκαν. */
    private static function phUsers($p)
    {
        return isset($p['M4']) ? max(0, (float) $p['M4']) : 0;
    }

    /** Οι ώρες ετήσιας τηλεφωνικής υποστήριξης — όσες δηλώθηκαν (φύλλο: 2 ανά χρήστη). */
    private static function supHours($p)
    {
        return max(0, (float) ($p['M6'] ?? 0));
    }

    /** Ώρες υπηρεσιών: το φύλλο στρογγυλοποιεί ΠΑΝΤΑ προς τα πάνω (ROUNDUP(αξία/80)). */
    private static function hrs($v) { return (int) ceil(((float) $v) / self::HOUR_RATE - 0.00001); }

    private static function marketplaces($yn, $r, $q = [])
    {
        return self::y($yn, 'N16') * $r['S22'] * self::q($q, 'N16')
             + self::y($yn, 'N18') * $r['S23'] * self::q($q, 'N18')
             + self::y($yn, 'N19') * $r['S24'] * self::q($q, 'N19')
             + self::y($yn, 'N20') * $r['S25'] * self::q($q, 'N20');
    }

    /** Η ετήσια υποστήριξη marketplaces — κάθε κανάλι με το δικό του ποσοστό. */
    private static function marketplacesAnnual($yn, $r, $q = [])
    {
        return self::y($yn, 'N16') * self::ar($r, 'S22') * self::q($q, 'N16')
             + self::y($yn, 'N18') * self::ar($r, 'S23') * self::q($q, 'N18')
             + self::y($yn, 'N19') * self::ar($r, 'S24') * self::q($q, 'N19')
             + self::y($yn, 'N20') * self::ar($r, 'S25') * self::q($q, 'N20');
    }

    private static function fmtPct($v) { return round($v * 1000) / 10 . '%'; }
    private static function fmtEur($v)
    {
        return number_format((float) $v, 2, ',', '.') . ' €';
    }
    private static function fmtHrs($v) { return number_format($v, 1, ',', '.'); }

    /** Ποσότητα χωρίς περιττά δεκαδικά: 3 και όχι 3,0. */
    private static function fmtQty($v)
    {
        $v = (float) $v;
        return $v == (int) $v ? (string) (int) $v : number_format($v, 1, ',', '.');
    }

    /**
     * Ετήσιες γραμμές (κόστος αδειών & ετήσιων υπηρεσιών).
     * `b` = κάδος προσφοράς. `$i` = δείκτης έκδοσης 0..3.
     */
    private static function annualRows()
    {
        return [
            /* Τηλεφωνική υποστήριξη: ΑΠΛΗ χρέωση «ώρες × τιμή ώρας». Τις ώρες τις
               ορίζει ο χρήστης — δεν προκύπτουν από χρήστες/εταιρείες/υποκαταστήματα
               ούτε μπαίνει πάγιο (απόφαση 11/9/2026). Η τιμή ώρας είναι το S37 του
               τιμοκαταλόγου (εργοστασιακά 80 €) και δέχεται την έκπτωση γραμμής. */
            [29, 5, 'Soft1 Υπηρεσίες Ετήσιας Τηλεφωνικής Υποστήριξης',
                function ($p, $r) { return '× ' . self::fmtQty(self::supHours($p)) . ' ώρες · τιμή '
                    . self::fmtEur(self::ar($r, 'S37')) . ' / ώρα'; },
                function ($p, $r) { return self::supHours($p) * self::ar($r, 'S37'); }],
            [30, 2, 'CloudOn Module Συνταγογράφησης (PharmacyOne)',
                function ($p, $r) { return '× ' . self::phUsers($p) . ' χρήστες · τιμή ' . self::fmtEur($r['S3']) . ' · έκπτ. ' . self::fmtPct($p['L4']); },
                function ($p, $r) { return (self::ar($r, 'S3') * self::phUsers($p)) * (1 - $p['L4']); }],
            [31, 1, 'Soft1 Cloud extra user',
                function ($p, $r, $e) { return '× ' . max($p['I4'] - 1, 0) . ' τεμ. · τιμή ' . self::fmtEur($e['extraUser']) . ' · έκπτ. ' . self::fmtPct($p['K8']); },
                function ($p, $r, $e) { return $e['extraUser'] * ($p['I4'] - 1) * (1 - $p['K8']); }],
            [32, 1, 'Soft1 — βασικός συνδυασμός (περιλαμβάνει 1 χρήστη)',
                function ($p, $r, $e) { return '× 1 άδεια · τιμή ' . self::fmtEur($e['price']) . ' · έκπτ. ' . self::fmtPct($p['K8']); },
                function ($p, $r, $e) { return $e['price'] * (1 - $p['K8']); }],
            [33, 1, 'Soft1 OpEn myData Live',
                function ($p, $r, $e, $i) { return '× ' . $p['K4'] . ' τεμ. · τιμή ' . self::fmtEur($i < 2 ? $r['S10'] : $r['S11']); },
                function ($p, $r, $e, $i) { return self::ar($r, $i < 2 ? 'S10' : 'S11') * $p['K4']; }],
            [34, 1, 'Soft1 1GB Extra Cloud Disk',
                function ($p, $r) { return '× ' . $p['I8'] . ' GB · τιμή ' . self::fmtEur($r['S8']); },
                function ($p, $r) { return self::ar($r, 'S8') * $p['I8']; }],
            [35, 1, 'Soft1 Ομάδες Εταιρειών',
                function ($p, $r) { return '× 1 τεμ. · τιμή ' . self::fmtEur($r['S7']) . ' · έκπτ. ' . self::fmtPct($p['K8']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'J16') * self::ar($r, 'S7') * (1 - $p['K8']); }],
            [36, 1, 'Soft1 Sql Connector',
                function ($p, $r) { return '× 1 τεμ. · τιμή ' . self::fmtEur($r['S12']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'J17') * self::ar($r, 'S12') * (1 - $p['K8']); }],
            [37, 5, 'Ετήσια υπηρεσία υποστήριξης διασύνδεσης με E-shop',
                function ($p, $r) { return '× ' . $p['J10'] . ' · ' . self::fmtPct($r['T13']) . ' επί ' . self::fmtEur($r['S13']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L21') * self::ar($r, 'S13') * $p['J10'] * (1 - $p['J8']); }],
            /* Το B38 του φύλλου χρησιμοποιεί (1-L4), τα C/D/E (1-K8). Διατηρείται. */
            [38, 2, 'CloudOn Price Checker — ετήσια άδεια',
                function ($p, $r) { return '× ' . $p['I6'] . ' τεμ. · τιμή ' . self::fmtEur($r['S14']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L20') * self::ar($r, 'S14') * $p['I6'] * (1 - ($i === 0 ? $p['L4'] : $p['K8'])); }],
            [39, 5, 'Ετήσια παροχή υπηρεσιών για Courier Module',
                function ($p, $r) { return '× ' . $p['J6'] . ' · ' . self::fmtPct($r['T15']) . ' επί ' . self::fmtEur($r['S15']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L24') * self::ar($r, 'S15') * $p['J6'] * (1 - $p['L4']); }],
            [40, 5, 'Ετήσιο κόστος ενημέρωσης δεδομένων (OTC & παραφάρμακα)',
                function ($p, $r) { return 'Data Box / Logi Scoup · έκπτ. ' . self::fmtPct($p['L4']); },
                function ($p, $r, $e, $i, $yn, $q) { return (self::y($yn, 'L17') * self::ar($r, 'S17') * self::q($q, 'L17')
                    + self::y($yn, 'L16') * self::ar($r, 'S19') * self::q($q, 'L16')) * (1 - $p['L4']); }],
            [41, 2, 'CloudOn RWA Module — ετήσια άδεια',
                function ($p, $r) { return '× ' . $p['L8'] . ' τεμ. · τιμή ' . self::fmtEur($r['S20']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L19') * self::ar($r, 'S20') * $p['L8'] * (1 - $p['L4']); }],
            [42, 2, 'CloudOn E-Label — ετήσια άδεια',
                function ($p, $r, $e, $i, $yn, $q) { return '× ' . self::q($q, 'L23', $p['K4'] * $p['J4']) . ' τεμ. · τιμή ' . self::fmtEur($r['S21']); },
                function ($p, $r, $e, $i, $yn, $q) { return self::y($yn, 'L23') * self::ar($r, 'S21') * self::q($q, 'L23', $p['K4'] * $p['J4']) * (1 - $p['L4']); }],
            [43, 5, 'Ετήσια υπηρεσία υποστήριξης Marketplaces',
                function ($p, $r, $e, $i, $yn, $q) { return 'επί ' . self::fmtEur(self::marketplaces($yn, $r, $q)) . ' αξίας διασυνδέσεων'; },
                function ($p, $r, $e, $i, $yn, $q) { return self::marketplacesAnnual($yn, $r, $q) * (1 - $p['L4']); }],
            [44, 1, 'Soft1 POS Connector',
                function ($p, $r) { return '× ' . $p['K6'] . ' τεμ. · τιμή ' . self::fmtEur($r['S27']); },
                function ($p, $r) { return self::ar($r, 'S27') * $p['K6'] * (1 - $p['L4']); }],
            [45, 2, 'CloudOn einvoice Skroutz — ετήσια άδεια',
                function ($p, $r, $e, $i, $yn, $q) { return '× ' . self::q($q, 'N17', $p['K4']) . ' τεμ. · τιμή ' . self::fmtEur($r['S29']); },
                function ($p, $r, $e, $i, $yn, $q) { return self::y($yn, 'N17') * self::ar($r, 'S29') * self::q($q, 'N17', $p['K4']) * (1 - $p['L4']); }],
            [46, 2, 'CloudOn IQVIA — ετήσια άδεια',
                function ($p, $r, $e, $i, $yn, $q) { return '× ' . self::q($q, 'L18', $p['K4']) . ' τεμ. · τιμή ' . self::fmtEur($r['S30']); },
                function ($p, $r, $e, $i, $yn, $q) { return self::y($yn, 'L18') * self::ar($r, 'S30') * self::q($q, 'L18', $p['K4']) * (1 - $p['L4']); }],
            [47, 1, 'CloudOn Picking / Packing module',
                function ($p, $r, $e, $i, $yn, $q) { return '× ' . self::q($q, 'L22', $p['L10']) . ' τεμ. · τιμή ' . self::fmtEur($r['S16']); },
                function ($p, $r, $e, $i, $yn, $q) { return self::y($yn, 'L22') * self::ar($r, 'S16') * self::q($q, 'L22', $p['L10']) * (1 - $p['L4']); }],
            [48, 2, 'CloudOn Cash Guard — ετήσια άδεια',
                function ($p, $r) { return '× ' . $p['I12'] . ' τεμ. · τιμή ' . self::fmtEur($r['S34']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'N21') * self::ar($r, 'S34') * $p['I12'] * (1 - $p['L4']); }],
            [49, 5, 'Ετήσια υπηρεσία υποστήριξης Skroutz FBS',
                function ($p, $r) { return self::fmtPct($r['T35']) . ' επί ' . self::fmtEur($r['S35']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'N22') * self::ar($r, 'S35') * $p['K4'] * (1 - $p['L4']); }],
            [50, 2, 'CloudOn WMS Lite — ετήσια άδεια',
                function ($p, $r, $e, $i, $yn, $q) { return '× ' . self::q($q, 'L25') . ' τεμ. · τιμή ' . self::fmtEur($r['S36']); },
                function ($p, $r, $e, $i, $yn, $q) { return self::y($yn, 'L25') * self::ar($r, 'S36') * self::q($q, 'L25') * (1 - $p['K8']); }],
        ];
    }

    /**
     * Ποια γραμμή τιμοκαταλόγου τροφοδοτεί κάθε ετήσια γραμμή — μόνο για όσες η ετικέτα
     * τους δεν αναφέρει ήδη το ποσοστό (e-shop, courier, marketplaces, FBS το λένε μόνες).
     */
    private static function annualRateKey($k, $i)
    {
        if ($k === 33) { return $i < 2 ? 'S10' : 'S11'; }
        $m = [30 => 'S3', 34 => 'S8', 35 => 'S7', 36 => 'S12', 38 => 'S14', 41 => 'S20',
            42 => 'S21', 44 => 'S27', 45 => 'S29', 46 => 'S30', 47 => 'S16', 48 => 'S34',
            50 => 'S36'];
        return $m[$k] ?? null;
    }

    /** Εφάπαξ γραμμές. `hrs` = να εμφανιστούν αντίστοιχες ώρες υπηρεσιών. */
    private static function oneoffRows()
    {
        return [
            [29, 3, 1, 'Soft1 Υπηρεσίες Παραμετροποίησης Εμπορικού και Λογιστικού', null,
                function ($p, $r, $e, $i) { return (($r['S9'] * $p['J4'] * $p['K4']) + ($p['I4'] * 2) * self::SETUP_RATE[$i]) * (1 - $p['J8']); }],
            [30, 3, 1, 'Soft1 Υπηρεσίες Μεταναστεύσεως Δεδομένων', null,
                function ($p, $r) { return (250 * $p['K4'] * $p['J4']) * (1 - $p['J8']); }],
            [31, 3, 1, 'CloudOn Κατασκευή Εκτυπωτικών — Report Soft1', null,
                function ($p, $r) { return 150 * $p['J4'] * $p['K4'] * (1 - $p['J8']); }],
            [32, 3, 1, 'Soft1 Υπηρεσίες Παραμετροποίησης PharmacyOne', null,
                function ($p, $r) { return (self::phUsers($p) * $r['S28']) * (1 - $p['J8']) + 60 * (1 - $p['L4']); }],
            [33, 3, 1, 'Soft1 Εκπαίδευση', null,
                function ($p, $r) { return ($p['I4'] * 2) * 20 * (1 - $p['J8']) + 200 * (1 - $p['L4']); }],
            [34, 3, 1, 'Υπηρεσίες παραμετροποίησης myData', null,
                function ($p, $r, $e, $i) { return self::MYDATA_SETUP[$i] * $p['J4'] * $p['K4'] * (1 - $p['J8']) * (1 - $p['L4']); }],
            [35, 4, 0, 'CloudOn Διασύνδεση με E-shop',
                function ($p, $r) { return '× ' . $p['J10'] . ' τεμ. · τιμή ' . self::fmtEur($r['S13']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L21') * $r['S13'] * $p['J10'] * (1 - $p['L4']); }],
            [36, 4, 0, 'CloudOn Courier Module',
                function ($p, $r) { return '× ' . $p['J6'] . ' τεμ. · τιμή ' . self::fmtEur($r['S15']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L24') * $r['S15'] * $p['J6'] * (1 - $p['L4']); }],
            [44, 3, 1, 'Παραμετροποίηση WMS Lite', null,
                function ($p, $r, $e, $i, $yn, $q) { return self::y($yn, 'L25') * $r['S36'] * self::q($q, 'L25') * (1 - $p['J8']); }],
            [37, 4, 0, 'CloudOn modules Marketplaces — διαχείριση παραγγελιών',
                function ($p, $r) { return 'Skroutz / Shopflix / Wolt / E-Food'; },
                function ($p, $r, $e, $i, $yn, $q) { return self::marketplaces($yn, $r, $q) * (1 - $p['L4']); }],
            [38, 3, 0, 'Τεχνική υποστήριξη & παραμετροποίηση εξοπλισμού',
                function ($p, $r) { return $p['I10'] . ' Η/Υ · ' . $p['K10'] . ' εκτυπωτές'; },
                function ($p, $r) { return (($r['S33'] * $p['I10']) + ($r['S26'] * $p['K10'])) * (1 - $p['J8']); }],
            [39, 3, 0, 'Παραμετροποίηση Price Checker', null,
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L20') * $r['S14'] * 2 * (1 - $p['L4']); }],
            [40, 3, 0, 'Παραμετροποίηση POS',
                function ($p, $r) { return '× ' . $p['L6'] . ' διασυνδέσεις · τιμή ' . self::fmtEur($r['S31']); },
                function ($p, $r) { return $p['L6'] * $r['S31'] * (1 - $p['L4']); }],
            [41, 3, 0, 'Παραμετροποίηση παρόχου ηλεκτρονικής τιμολόγησης (ECOS)', null,
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'J18') * $r['S32'] * $p['K4'] * (1 - $p['J8']); }],
            [42, 3, 0, 'Παραμετροποίηση Cash Guard', null,
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'N21') * ($r['S34'] * 0.8) * $p['I12'] * (1 - $p['J8']); }],
            [43, 4, 0, 'CloudOn module Skroutz FBS',
                function ($p, $r) { return '× ' . $p['K4'] . ' τεμ. · τιμή ' . self::fmtEur($r['S35']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'N22') * $r['S35'] * $p['K4'] * (1 - $p['J8']); }],
        ];
    }

    /* ══════════════ υπολογισμός ══════════════ */

    /** Γεμίζει ό,τι λείπει από τη ρύθμιση με τις προεπιλογές. */
    public static function normalize($cfg)
    {
        $cfg = is_array($cfg) ? $cfg : [];
        $out = [];
        $out['p'] = [];
        foreach (self::defaultParams() as $k => $v) {
            $out['p'][$k] = isset($cfg['p'][$k]) && is_numeric($cfg['p'][$k]) ? (float) $cfg['p'][$k] : $v;
        }
        /* Οι προσφορές που φτιάχτηκαν πριν υπάρξει το πεδίο «PharmacyOne user» δεν το
           έχουν καθόλου· τότε χρεώνονταν όσοι και οι χρήστες Soft1. Το κρατάμε, ώστε
           ανοίγοντας μια παλιά προσφορά να μη μετακινηθεί ούτε ένα ευρώ. */
        if (!isset($cfg['p']['M4']) || !is_numeric($cfg['p']['M4'])) { $out['p']['M4'] = $out['p']['I4']; }
        /* Οι ώρες τηλεφωνικής ΔΕΝ παράγονται πλέον από τους χρήστες — τις ορίζει ο
           χρήστης. Όποια προσφορά δεν τις έχει, παίρνει την προεπιλογή του πεδίου. */
        $out['yn'] = [];
        foreach (self::defaultModules() as $k => $v) {
            $out['yn'][$k] = !empty($cfg['yn'][$k]) ? 1 : 0;
        }
        /* Ποσότητα ανά module (στήλη «Τεμ» του φύλλου). 0 = η προεπιλογή του τύπου,
           ώστε οι ήδη αποθηκευμένες προσφορές να μη μετακινηθούν ούτε κατά ένα ευρώ. */
        $out['q'] = [];
        foreach (self::defaultQty() as $k => $v) {
            $out['q'][$k] = isset($cfg['q'][$k]) && is_numeric($cfg['q'][$k]) ? max(0, (float) $cfg['q'][$k]) : 0;
        }
        $out['r'] = [];
        foreach (self::defaultRates() as $k => $v) {
            $out['r'][$k] = isset($cfg['r'][$k]) && is_numeric($cfg['r'][$k]) ? (float) $cfg['r'][$k] : $v;
        }
        // Έκπτωση ανά γραμμή τιμοκαταλόγου (μόνο για τιμές S*, όχι για τα T* που
        // είναι ποσοστά προμήθειας). Ποσοστό 0-0.99, εφαρμόζεται στην τιμή.
        $out['rd'] = [];
        $rdDef = self::defaultLineDiscounts();
        foreach (self::defaultRates() as $k => $v) {
            if (isset($k[0]) && $k[0] === 'S') {
                $d = isset($cfg['rd'][$k]) && is_numeric($cfg['rd'][$k]) ? (float) $cfg['rd'][$k] : ($rdDef[$k] ?? 0);
                $out['rd'][$k] = max(0, min(0.99, $d));
            }
        }
        $out['ed'] = [];
        foreach (self::defaultEditions() as $i => $e) {
            $out['ed'][$i] = $e;
            foreach (['price', 'extraUser'] as $f) {
                if (isset($cfg['ed'][$i][$f]) && is_numeric($cfg['ed'][$i][$f])) {
                    $out['ed'][$i][$f] = (float) $cfg['ed'][$i][$f];
                }
            }
        }
        $out['sel'] = isset($cfg['sel']) && (int) $cfg['sel'] >= 0 && (int) $cfg['sel'] < 4 ? (int) $cfg['sel'] : 2;
        $o = is_array($cfg['o'] ?? null) ? $cfg['o'] : [];
        $out['o'] = [
            'afm' => (string) ($o['afm'] ?? ''), 'doy' => (string) ($o['doy'] ?? ''),
            'address' => (string) ($o['address'] ?? ''), 'activity' => (string) ($o['activity'] ?? ''),
            'cphone' => (string) ($o['cphone'] ?? ''), 'cemail' => (string) ($o['cemail'] ?? ''),
            'attn' => (string) ($o['attn'] ?? ''),
            'protocol' => (string) ($o['protocol'] ?? ('CLD-' . date('Y') . '-')),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($o['date'] ?? '')) ? $o['date'] : date('Y-m-d'),
            'seller' => (string) ($o['seller'] ?? ''), 'acceptAttn' => (string) ($o['acceptAttn'] ?? ''),
            'greeting' => (string) ($o['greeting'] ?? 'Αξιότιμοι κύριοι,'),
            'city' => (string) ($o['city'] ?? 'Αθήνα'),
            'discount' => (float) ($o['discount'] ?? 0), 'vat' => (float) ($o['vat'] ?? 24),
            'validDays' => (int) ($o['validDays'] ?? 30), 'prepay' => (float) ($o['prepay'] ?? 50),
            'payMethod' => in_array((string) ($o['payMethod'] ?? ''), self::PAY_METHODS, true)
                ? (string) $o['payMethod'] : self::PAY_METHODS[0],
            'installments' => max(0, min(36, (int) ($o['installments'] ?? 0))),
            'subCycle' => in_array((string) ($o['subCycle'] ?? ''), self::SUB_CYCLES, true)
                ? (string) $o['subCycle'] : self::SUB_CYCLES[0],
            'plan' => self::normPlan($o),
            'tel' => (string) ($o['tel'] ?? '+30 210 72 22 560'),
            'fax' => (string) ($o['fax'] ?? '+30 210 63 91 532'),
            'client' => (string) ($o['client'] ?? ''),
        ];
        return $out;
    }

    /**
     * Ο διακανονισμός: όσες δόσεις θέλει η συμφωνία, καθεμιά σε ποσοστό ή σε ευρώ, με
     * δική της περιγραφή («με την ανάθεση», «60 ημέρες μετά την παράδοση», …).
     * Παλιές προσφορές δεν έχουν πίνακα — τους τον φτιάχνουμε από προκαταβολή/δόσεις,
     * ώστε το έντυπο να βγάζει ό,τι έβγαζε πάντα.
     *
     * @return array<int,array{t:string,v:float,w:string}>
     */
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
        if ($rows) { return $rows; }
        /* Χωρίς πίνακα: η παλιά λογική «προκαταβολή με την ανάθεση, υπόλοιπο στην παράδοση». */
        $pre = max(0, min(100, (float) ($o['prepay'] ?? 50)));
        $inst = max(0, min(36, (int) ($o['installments'] ?? 0)));
        $rest = 100 - $pre;
        $rows[] = ['t' => 'pct', 'v' => $pre, 'w' => 'Με την ανάθεση του έργου'];
        if ($rest > 0) {
            if ($inst > 1) {
                for ($x = 1; $x <= $inst; $x++) {
                    $rows[] = ['t' => 'pct', 'v' => $rest / $inst,
                        'w' => 'Δόση ' . $x . ' από ' . $inst . ' — μηνιαία, με πρώτη την παράδοση'];
                }
            } else {
                $rows[] = ['t' => 'pct', 'v' => $rest, 'w' => 'Με την παράδοση του έργου'];
            }
        }
        return $rows;
    }

    /** Τα ευρώ κάθε δόσης πάνω στην τελική αξία. */
    public static function planRows(array $plan, $final)
    {
        $out = []; $sum = 0;
        foreach ($plan as $r) {
            $eur = round($r['t'] === 'eur' ? $r['v'] : $final * $r['v'] / 100, 2);
            $sum += $eur;
            $out[] = ['lab' => $r['t'] === 'eur' ? self::fmtEur($r['v']) : self::fmtPct($r['v'] / 100),
                'eur' => $eur, 'w' => $r['w']];
        }
        /* Τα ποσοστά σπάνια δίνουν στρογγυλά λεπτά: η τελευταία δόση απορροφά τη διαφορά,
           ώστε οι δόσεις να αθροίζουν ΑΚΡΙΒΩΣ στο ποσό της προσφοράς. */
        $d = round($final - $sum, 2);
        if ($out && abs($d) > 0.001 && abs($d) <= 0.05) {
            $out[count($out) - 1]['eur'] = round($out[count($out) - 1]['eur'] + $d, 2);
            $sum = round($sum + $d, 2);
        }
        return ['rows' => $out, 'sum' => $sum, 'rest' => round($final - $sum, 2)];
    }

    /**
     * @return array{annual:array, oneoff:array, totals:array}
     *   annual/oneoff: πίνακας [γραμμή][έκδοση] με ποσά
     */
    /** Εφαρμόζει την έκπτωση ανά γραμμή στις τιμές (S*) — οι τύποι δουλεύουν με
        τις «καθαρές» τιμές, άρα η έκπτωση περνά παντού όπου χρησιμοποιείται η τιμή. */
    private static function effRates(array $r, array $rd)
    {
        foreach ($rd as $k => $d) {
            /* Προστατευμένες γραμμές: καμία έκπτωση (ούτε της προσφοράς ούτε της
               εκπτωτικής πολιτικής) δεν μειώνει την τιμή τους. */
            if (in_array($k, self::NO_DISCOUNT_CELLS, true)) { continue; }
            if ($d > 0 && isset($r[$k])) { $r[$k] = $r[$k] * (1 - $d); }
        }
        return $r;
    }

    public static function calc($cfg)
    {
        $c = self::normalize($cfg);
        $p = $c['p']; $r = $c['r']; $yn = $c['yn']; $q = $c['q'] ?? [];
        $rEff = self::effRates($r, $c['rd'] ?? []);
        $annual = [];
        foreach (self::annualRows() as $rw) {
            $line = [];
            foreach ($c['ed'] as $i => $e) { $line[] = (float) call_user_func($rw[4], $p, $rEff, $e, $i, $yn, $q); }
            $annual[] = $line;
        }
        $oneoff = [];
        foreach (self::oneoffRows() as $rw) {
            $line = [];
            foreach ($c['ed'] as $i => $e) { $line[] = (float) call_user_func($rw[5], $p, $rEff, $e, $i, $yn, $q); }
            $oneoff[] = $line;
        }
        $totals = [];
        foreach ($c['ed'] as $i => $e) {
            $a = 0; foreach ($annual as $row) { $a += $row[$i]; }
            $o = 0; foreach ($oneoff as $row) { $o += $row[$i]; }
            $div = $p['J4'] * $p['K4'];
            $annualPerPh = $div ? $a / $div : 0;
            $annualPerUsr = $p['I4'] ? $annualPerPh / $p['I4'] : 0;
            $totals[] = ['annual' => $a, 'oneoff' => $o, 'first' => $a + $o,
                'firstPerPharmacy' => $div ? ($a + $o) / $div : 0,
                'annualPerPharmacy' => $annualPerPh, 'annualPerUser' => $annualPerUsr,
                'monthlyPerUser' => $annualPerUsr / 12];
        }
        return ['annual' => $annual, 'oneoff' => $oneoff, 'totals' => $totals, 'cfg' => $c];
    }

    /** Οι γραμμές της επιλεγμένης έκδοσης, ομαδοποιημένες σε κάδους. */
    public static function bucketLines($res, $i)
    {
        $c = $res['cfg']; $p = $c['p']; $r = $c['r']; $yn = $c['yn']; $e = $c['ed'][$i]; $q = $c['q'] ?? [];
        $rEff = self::effRates($r, $c['rd'] ?? []);
        $out = [1 => [], 2 => [], 3 => [], 4 => [], 5 => []];
        foreach (self::annualRows() as $ri => $rw) {
            $v = $res['annual'][$ri][$i];
            if (abs($v) < 0.005) { continue; }
            $qty = $rw[3] ? call_user_func($rw[3], $p, $rEff, $e, $i, $yn, $q) : '';
            /* Αν η γραμμή δεν ξαναχρεώνεται ολόκληρη, πες το — αλλιώς ο πελάτης διαβάζει
               «3 τεμ. × 250 €» και δίπλα 375 € και δεν του βγαίνει. */
            $rk = self::annualRateKey($rw[0], $i);
            if ($rk !== null) {
                $tf = (float) ($rEff['T' . substr($rk, 1)] ?? 1);
                if (abs($tf - 1) > 0.0001) {
                    $qty .= ($qty ? ' · ' : '') . 'ετήσια αναπροσαρμογή ' . self::fmtPct($tf);
                }
            }
            $out[$rw[1]][] = ['k' => $rw[0], 'lab' => $rw[2], 'qty' => $qty, 'amount' => $v];
        }
        /* Το εφάπαξ στήσιμο μιας διασύνδεσης φέρνει μαζί του ετήσια υποστήριξη: courier
           550 € τώρα → 165 €/έτος μετά, e-shop 2.500 € → 400 €/έτος. Το γράφουμε πάνω στη
           γραμμή, γιατί ο πελάτης ρωτά «και του χρόνου τι πληρώνω;» πριν καν φύγει η προσφορά. */
        $rec = [35 => 37, 36 => 39, 37 => 43, 43 => 49];   // κλειδί εφάπαξ → κλειδί ετήσιας
        $annIx = [];
        foreach (self::annualRows() as $ai => $ar) { $annIx[$ar[0]] = $ai; }
        foreach (self::oneoffRows() as $ri => $rw) {
            $v = $res['oneoff'][$ri][$i];
            if (abs($v) < 0.005) { continue; }
            $qty = $rw[4] ? call_user_func($rw[4], $p, $rEff, $e, $i, $yn, $q)
                : ($rw[2] ? '× ' . self::hrs($v) . ' ώρες · τιμή ' . self::fmtEur(self::HOUR_RATE) : '');
            if (isset($rec[$rw[0]], $annIx[$rec[$rw[0]]])) {
                $av = $res['annual'][$annIx[$rec[$rw[0]]]][$i];
                if ($av > 0.005) {
                    $qty .= ($qty ? ' · ' : '') . 'ετήσια υποστήριξη από το 2ο έτος: '
                        . self::fmtEur($av) . ' / έτος';
                }
            }
            $out[$rw[1]][] = ['k' => $rw[0], 'lab' => $rw[3], 'qty' => $qty, 'amount' => $v];
        }
        return $out;
    }

    /** Το ποσό που μπαίνει στην προσφορά: πρώτο έτος, μετά την έκπτωση, χωρίς ΦΠΑ. */
    public static function offerAmount($cfg)
    {
        $res = self::calc($cfg);
        $c = $res['cfg'];
        $pre = $res['totals'][$c['sel']]['first'];
        return max(0, round($pre - (float) $c['o']['discount'], 2));
    }

    /**
     * Πόσα τεμάχια «πιάνει» κάθε module, όπως ακριβώς μπαίνουν στους τύπους. Δίνει στο
     * έντυπο τη δυνατότητα να γράψει «Courier Module · 2 τεμ.» χωρίς να ξαναϋπολογίζει.
     *
     * @return array<string,float> κελί module => τεμάχια (0 = δεν δίνεται)
     */
    public static function moduleQty(array $c)
    {
        $p = $c['p']; $q = $c['q'] ?? []; $yn = $c['yn'];
        $m = ['J16' => 1, 'J17' => 1, 'J18' => $p['K4'],
            'L16' => self::q($q, 'L16'), 'L17' => self::q($q, 'L17'),
            'L18' => self::q($q, 'L18', $p['K4']), 'L19' => $p['L8'], 'L20' => $p['I6'],
            'L21' => $p['J10'], 'L22' => self::q($q, 'L22', $p['L10']),
            'L23' => self::q($q, 'L23', $p['K4'] * $p['J4']), 'L24' => $p['J6'],
            'L25' => self::q($q, 'L25'), 'N16' => self::q($q, 'N16'),
            'N17' => self::q($q, 'N17', $p['K4']), 'N18' => self::q($q, 'N18'),
            'N19' => self::q($q, 'N19'), 'N20' => self::q($q, 'N20'),
            'N21' => $p['I12'], 'N22' => $p['K4']];
        foreach ($m as $k => $v) { if (empty($yn[$k])) { $m[$k] = 0; } }
        return $m;
    }

    public static function activeModules($cfg)
    {
        $c = self::normalize($cfg);
        $out = [];
        foreach (self::moduleGroups() as $g) {
            foreach ($g['items'] as $it) {
                if (!empty($c['yn'][$it[0]])) { $out[] = $it[1]; }
            }
        }
        return $out;
    }
    /**
     * Ο πίνακας «Soft1 ERP → Modules included» της πρότασης, όπως ακριβώς στο έντυπο.
     * 'g' = επικεφαλίδα ομάδας· 'r' = γραμμή με σημαία ανά έκδοση (Β, ΒΓ, Plus Β, Plus ΒΓ).
     */
    public static function soft1Table()
    {
        $all = [1, 1, 1, 1]; $no = [0, 0, 0, 0];
        return [
            ['g', 'Operations & CRM'],
            ['r', 'Ημερολόγιο - Διαχείριση Συνάντησης', $all],
            ['r', 'CRM - Sales & Marketing', $all],
            ['r', 'Contacts & Φυσικά Πρόσωπα', $all],
            ['r', 'Έργα', $all],
            ['r', 'Υπηρεσίες & Τεχνικοί', $all],
            ['g', 'Stock Management'],
            ['r', 'Διαχείριση Αποθήκης (Απεριόριστοι Αποθηκευτικοί Χώροι)', $all],
            ['r', 'Υποκαταστήματα', $all],
            ['r', 'Εναλλακτικά - Αντίστοιχα Είδη', $all],
            ['r', 'Χρώμα & Μέγεθος', [1, 1, 1, 1]],
            ['r', 'Group Sets', [1, 1, 1, 1]],
            ['r', 'Παρτίδες', [0, 0, 1, 1]],
            ['r', 'Θέσεις Αποθήκευσης - Ράφια', $all],
            ['r', 'Serial Numbers - Services', $no],
            ['g', 'Εμπορική Δραστηριότητα'],
            ['r', 'Πωλήσεις, Διαχείριση Λιανικής, Διαχείριση Πελατών, Χρεωστών', $all],
            ['r', 'Αγορές, Διαχείριση Προμηθευτών, Πιστωτών', [1, 1, 1, 1]],
            ['r', 'Παρακολούθηση Πωλητών, Εισπρακτόρων', $no],
            ['r', 'Loyalty Schemes', [1, 1, 1, 1]],
            ['r', 'Business Units', [0, 0, 1, 1]],
            ['r', 'Τιμολογιακές Πολιτικές', [0, 0, 1, 1]],
            ['r', 'Μέσα, Γεωγραφικά Σημεία', $no],
            ['r', 'Συγκεντρωτικές Καρτέλες & Ισοζύγια - Όμιλοι Εταιρειών', $no],
            ['g', 'Χρηματοοικονομική Διαχείριση – Δαπάνες'],
            ['r', 'Γενική Λογιστική', [0, 1, 0, 1]],
            ['r', 'Έσοδα – Έξοδα', [1, 0, 1, 0]],
            ['r', 'Οικ. Συναλλαγές: (Εισπράξεις - Πληρωμές, Αξιόγραφα, Ειδικές Συναλλαγές &amp; '
                . 'Χρεοπιστώσεις, Χρηματικοί &amp; Τραπεζικοί Λογαριασμοί - Ταμεία &amp; Εμβάσματα, '
                . 'Συμψηφισμοί Συναλλασσομένων, Αντιστοιχήσεις - Open Item, Διαχείριση Πιστωτικών Καρτών)',
                [1, 1, 1, 1], true],
            ['r', 'Διακανονισμοί & Δόσεις', $no],
            ['g', 'Reporting Tools'],
            ['r', 'Ελεύθερα Πεδία και Αθροιστές', $all],
            ['r', 'Διαχείριση Επισυναπτόμενων Ηλεκτρονικών Αρχείων (Έγγραφα)', $all],
            ['r', 'Report Generator – Basic', $all],
            ['r', 'Report Designer – Advanced', $all],
            ['r', 'Merging', $all],
            ['g', 'Customization Tools'],
            ['r', 'Σχεδιασμός Οθονών', $all],
            ['r', 'Run time Rights', [1, 1, 1, 1]],
            ['r', 'Remote Systems', $all],
            ['r', 'ALERT Systems', $all],
            ['g', 'WEB & Mobile apps'],
            ['r', 'Soft1 Web Report (5 users)', $no],
            ['r', 'Soft1 My Customer (50 customers)', $no],
            ['r', 'Soft1 Quick View & Soft1 My Portal', $no],
        ];
    }

    /* ══════════════ το έγγραφο ══════════════ */

    private static function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
    private static function longDate($iso)
    {
        $ts = strtotime($iso ?: 'now') ?: time();
        $wd = ['Κυριακή', 'Δευτέρα', 'Τρίτη', 'Τετάρτη', 'Πέμπτη', 'Παρασκευή', 'Σάββατο'];
        $mo = ['', 'Ιανουαρίου', 'Φεβρουαρίου', 'Μαρτίου', 'Απριλίου', 'Μαΐου', 'Ιουνίου',
            'Ιουλίου', 'Αυγούστου', 'Σεπτεμβρίου', 'Οκτωβρίου', 'Νοεμβρίου', 'Δεκεμβρίου'];
        return $wd[(int) date('w', $ts)] . ', ' . (int) date('j', $ts) . ' '
            . $mo[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }
    private static function plural($n, $one, $many) { return $n == 1 ? $one : $many; }

    /**
     * Τα στοιχεία του παραλήπτη κάτω από την επωνυμία. Μπαίνουν μόνο όσα έχουν
     * συμπληρωθεί — μια προσφορά με κενό «ΑΦΜ:» δείχνει πρόχειρη.
     */
    private static function toDetails(array $o)
    {
        $bits = [];
        if (trim((string) $o['address']) !== '') { $bits[] = self::e($o['address']); }
        $tax = [];
        if (trim((string) $o['afm']) !== '') { $tax[] = 'ΑΦΜ ' . self::e($o['afm']); }
        if (trim((string) $o['doy']) !== '') { $tax[] = 'Δ.Ο.Υ. ' . self::e($o['doy']); }
        if ($tax) { $bits[] = implode(' · ', $tax); }
        $con = [];
        if (trim((string) $o['cphone']) !== '') { $con[] = 'τηλ. ' . self::e($o['cphone']); }
        if (trim((string) $o['cemail']) !== '') { $con[] = self::e($o['cemail']); }
        if ($con) { $bits[] = implode(' · ', $con); }
        if (trim((string) $o['attn']) !== '') { $bits[] = 'Υπόψη: ' . self::e($o['attn']); }
        return $bits ? '<div class="tod">' . implode('<br>', $bits) . '</div>' : '';
    }

    /** Τα λογότυπα ζουν ως στατικά αρχεία — το έγγραφο μένει ελαφρύ σε κάθε προεπισκόπηση. */
    const LOGO_DIR     = '/project/doc-assets/';
    const LOGO_CLOUDON = '/project/doc-assets/cloudon.svg';

    /**
     * Το σήμα του συνεργάτη. Μετά τη συγχώνευση Entersoft–SoftOne το wordmark είναι
     * «ENTERSOFTONE», χωρίς το παλιό tagline. Αν το νέο αρχείο υπάρχει το χρησιμοποιούμε·
     * αλλιώς μένει το παλιό, ώστε το έντυπο να μη βγάλει ποτέ σπασμένη εικόνα.
     */
    private static function partnerLogo()
    {
        $root = dirname(__DIR__, 4) . '/projectmanagement/doc-assets/';
        foreach (['entersoftone.svg', 'entersoftone.png'] as $f) {
            if (is_file($root . $f)) {
                return ['src' => self::LOGO_DIR . $f, 'alt' => 'Entersoft One', 'tag' => '', 'w' => '68mm'];
            }
        }
        return ['src' => self::LOGO_DIR . 'softone.png', 'alt' => 'SoftOne',
            'tag' => 'more than software', 'w' => '44mm'];
    }

    /* Τα συμφραζόμενα της τρέχουσας κεφαλίδας/υποσέλιδου — γεμίζουν στην αρχή του docHtml. */
    private static $run = '';
    private static $ref = '';
    private static $pages = 13;

    /** Και τα δύο σήματα μαζί (εξώφυλλο). */
    private static function logos()
    {
        $lg = self::partnerLogo();
        return '<div class="clogos"><img class="c" src="' . self::LOGO_CLOUDON . '" alt="CloudOn">'
            . '<div class="s"><img src="' . $lg['src'] . '" alt="' . self::e($lg['alt'])
            . '" style="width:' . $lg['w'] . '">'
            . ($lg['tag'] ? '<span>' . self::e($lg['tag']) . '</span>' : '') . '</div></div>';
    }

    /** Τρέχουσα κεφαλίδα εσωτερικής σελίδας: σήμα αριστερά, τι διαβάζεις δεξιά. */
    private static function head()
    {
        return '<div class="lg"><img class="c" src="' . self::LOGO_CLOUDON . '" alt="CloudOn">'
            . '<div class="run">' . self::e(self::$run) . '</div></div>';
    }

    /** Μία σελίδα Α4 με κεφαλίδα, υποσέλιδο και αρίθμηση «ν / σύνολο». */
    private static function pg($body, $n, $cls = '')
    {
        $cover = strpos($cls, 'cover') !== false;
        return '<div class="page' . ($cls ? ' ' . $cls : '') . '">'
            . ($cover ? '' : self::head())
            . $body
            . '<div class="foot"><span>' . self::e(self::$ref) . '</span>'
            . '<span class="pn"><b>' . $n . '</b> / ' . self::$pages . '</span></div></div>';
    }

    /** Μια σελίδα προς σύνθεση — αριθμείται στο τέλος, όταν ξέρουμε πόσες βγήκαν. */
    private static function mk($body, $cls = '')
    {
        return ['b' => $body, 'c' => $cls];
    }

    /* Ύψη σε χιλιοστά, μετρημένα πάνω στο ίδιο το έντυπο (docCss). Αν αλλάξει το
       στυλ των πινάκων, αυτά είναι που πρέπει να ξαναμετρηθούν. */
    const MM_PAGE   = 251;   // περιεχόμενο μιας σελίδας Α4, μετά κεφαλίδα/υποσέλιδο
    const MM_HEAD   = 26;    // η τρέχουσα κεφαλίδα με το κενό της
    const MM_ROW    = 10.7;  // γραμμή πίνακα κόστους
    const MM_ROWQ   = 15.9;  // …με υπογραμμή ποσότητας
    const MM_THEAD  = 7.6;   // επικεφαλίδα πίνακα κόστους
    const MM_TOTAL  = 12;    // γραμμή συνόλου
    const MM_GAP    = 9;     // κενό κάτω από πίνακα
    const MM_MROW   = 9.5;   // γραμμή πίνακα ενοτήτων
    const MM_MGRP   = 10.1;  // …επικεφαλίδα ομάδας
    const MM_MTHEAD = 7.1;

    /** Πόσα χιλιοστά πιάνει μια γραμμή· η ποσότητα προσθέτει δεύτερη σειρά. */
    private static function rowMm($l)
    {
        return empty($l['qty']) ? self::MM_ROW : self::MM_ROWQ;
    }

    /**
     * Ο πίνακας ενός κάδου κομμένος σε όσα κομμάτια χρειάζεται για να χωρέσει.
     * Επιστρέφει [['u' => χιλιοστά, 'h' => html], …] — το σύνολο μπαίνει μόνο στο τελευταίο.
     * Χωρίς αυτό, μια προσφορά με πολλά modules ξεχείλιζε από το φύλλο Α4 και η
     * αρίθμηση των σελίδων έλεγε ψέματα.
     */
    private static function bucketChunks($bk, $lines, $budget, $extraTop = [])
    {
        $rows = array_merge($extraTop, $lines);
        if (!$rows) { return []; }
        $total = 0;
        foreach ($lines as $l) { $total += $l['amount'] ?? 0; }
        $head = '<table class="p"><thead><tr><th>' . $bk[0] . '</th><th class="n">'
            . self::e($bk[2] ?: 'Αξία') . '</th></tr></thead><tbody>';
        $cont = '<table class="p"><thead><tr><th>' . $bk[0]
            . ' <span class="cont">συνέχεια</span></th><th class="n">'
            . self::e($bk[2] ?: 'Αξία') . '</th></tr></thead><tbody>';
        $out = [];
        $body = ''; $u = self::MM_THEAD; $first = true;
        $n = count($rows);
        foreach ($rows as $x => $l) {
            $ru = self::rowMm($l);
            $last = ($x === $n - 1);
            $need = $u + $ru + ($last ? self::MM_TOTAL : 0);
            if ($body !== '' && $need > $budget) {
                $out[] = ['u' => $u, 'h' => ($first ? $head : $cont) . $body . '</tbody></table>'];
                $body = ''; $u = self::MM_THEAD; $first = false;
            }
            $sub = !empty($l['sub']);
            $body .= '<tr' . ($sub ? ' class="sub"' : '') . '>'
                . '<td class="d">' . self::e($l['lab'])
                . (!empty($l['qty']) ? '<span class="q">' . self::e($l['qty']) . '</span>' : '')
                . '</td><td class="n' . (array_key_exists('amount', $l) ? '' : ' nt') . '">'
                . (array_key_exists('amount', $l) ? self::fmtEur($l['amount']) : self::e($l['note'] ?? ''))
                . '</td></tr>';
            $u += $ru;
        }
        $body .= '<tr class="tot"><td>' . self::e($bk[1]) . '</td><td class="n">'
            . self::fmtEur($total) . '</td></tr>';
        $out[] = ['u' => $u + self::MM_TOTAL, 'h' => ($first ? $head : $cont) . $body . '</tbody></table>'];
        return $out;
    }

    /**
     * Πίνακας κόστους ενός κάδου, στη μορφή του εντύπου: μπάντα επικεφαλίδας,
     * γραμμές με ποσότητα από κάτω, και μπλε γραμμή συνόλου.
     */
    private static function bucketTable($bk, $lines, $extraTop = [])
    {
        $rows = array_merge($extraTop, $lines);
        if (!$rows) { return ''; }
        $total = 0;
        foreach ($lines as $l) { $total += $l['amount'] ?? 0; }
        $h = '<table class="p"><thead><tr><th>' . $bk[0] . '</th><th class="n">'
            . self::e($bk[2] ?: 'Αξία') . '</th></tr></thead><tbody>';
        foreach ($rows as $l) {
            $sub = !empty($l['sub']);
            $h .= '<tr' . ($sub ? ' class="sub"' : '') . '>'
                . '<td class="d">' . self::e($l['lab'])
                . (!empty($l['qty']) ? '<span class="q">' . self::e($l['qty']) . '</span>' : '')
                . '</td><td class="n' . (array_key_exists('amount', $l) ? '' : ' nt') . '">'
                . (array_key_exists('amount', $l) ? self::fmtEur($l['amount']) : self::e($l['note'] ?? ''))
                . '</td></tr>';
        }
        return $h . '<tr class="tot"><td>' . self::e($bk[1]) . '</td><td class="n">'
            . self::fmtEur($total) . '</td></tr></tbody></table>';
    }

    /** Το στυλ του εντύπου. Ζει δίπλα στο περιεχόμενο ώστε οθόνη και εκτύπωση να μη διαφέρουν. */
    public static function docCss()
    {
        return <<<'CSS'
:root{
  --ink:#0B1B2E; --body:#3B4B60; --mut:#7C8BA0; --faint:#A7B3C4;
  --line:#E4EAF2; --hair:#EFF3F8; --tint:#F6F9FD;
  --acc:#0090DD; --acc-d:#0A6FAF; --acc-t:#E9F4FC; --acc-w:#F4FAFE;
  --red:#DF2438; --navy:#0C2544;
}
*{box-sizing:border-box}
body{margin:0;padding:20px;background:#E9EDF3;color:var(--body);
  font:10pt/1.55 "Segoe UI Variable Text","Segoe UI",Inter,system-ui,-apple-system,"Helvetica Neue",Arial,sans-serif;
  font-feature-settings:"kern" 1,"liga" 1;-webkit-font-smoothing:antialiased;
  -webkit-print-color-adjust:exact;print-color-adjust:exact}
.page{position:relative;width:210mm;min-height:297mm;margin:0 auto 20px;padding:14mm 18mm 18mm;
  background:#fff;box-shadow:0 2px 4px rgba(11,27,46,.06),0 18px 40px -18px rgba(11,27,46,.28)}

/* ── τρέχουσα κεφαλίδα & υποσέλιδο ── */
.lg{display:flex;align-items:center;justify-content:space-between;gap:10mm;
  padding-bottom:4mm;border-bottom:1px solid var(--line);margin-bottom:9mm}
.lg img.c{width:34mm;height:auto;display:block}
.lg .run{font-size:8pt;letter-spacing:.09em;text-transform:uppercase;color:var(--faint);
  text-align:right;max-width:95mm;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.foot{position:absolute;left:18mm;right:18mm;bottom:9mm;display:flex;justify-content:space-between;
  align-items:baseline;padding-top:3mm;border-top:1px solid var(--hair);
  font-size:8pt;letter-spacing:.06em;color:var(--faint)}
.foot .pn{font-variant-numeric:tabular-nums;color:var(--mut)}
.foot .pn b{color:var(--ink);font-weight:700}

/* ── τυπογραφία ── */
.eyebrow{font-size:8pt;font-weight:700;letter-spacing:.18em;text-transform:uppercase;
  color:var(--acc);margin-bottom:3mm}
h1.sec{color:var(--ink);font-size:18pt;font-weight:700;line-height:1.18;letter-spacing:-.012em;
  margin:0 0 5mm;display:flex;align-items:baseline;gap:4mm;text-wrap:balance}
h2.sub{color:var(--ink);font-size:12.5pt;font-weight:700;line-height:1.25;letter-spacing:-.008em;
  margin:7mm 0 3.5mm;display:flex;align-items:baseline;gap:3.5mm}
h2.sub:first-of-type{margin-top:0}
h3.sub3{color:var(--ink);font-size:10.5pt;font-weight:700;margin:6mm 0 2.6mm;
  display:flex;align-items:baseline;gap:3mm}
.num{flex:none;font-variant-numeric:tabular-nums;font-weight:700;color:var(--acc);
  font-size:.68em;letter-spacing:.04em;padding:.9mm 2.2mm;border-radius:2mm;
  background:var(--acc-t);transform:translateY(-.6mm)}
p{margin:0 0 3mm;max-width:64em}
.lead{font-size:11pt;line-height:1.5;color:var(--ink);font-weight:400;margin-bottom:5mm}
ul{margin:0 0 3.4mm;padding-left:6mm}
li{margin-bottom:1.3mm;padding-left:1mm}
li::marker{color:var(--acc)}
ul.tick{list-style:none;padding-left:0}
ul.tick>li{position:relative;padding-left:7mm}
ul.tick>li::before{content:"";position:absolute;left:0;top:1.9mm;width:3.6mm;height:3.6mm;
  border-radius:50%;background:var(--acc-t);
  box-shadow:inset 0 0 0 .8mm var(--acc)}
.mut{color:var(--mut)}
b.br{color:var(--acc-d)}
b{color:var(--ink);font-weight:600}

/* ── εξώφυλλο ── */
.cover{padding:0;display:flex;flex-direction:column}
.cover .clogos{display:flex;align-items:center;justify-content:space-between;gap:10mm;
  padding:16mm 20mm 10mm;border-bottom:0}
.cover .clogos img.c{width:44mm;height:auto}
.cover .clogos .s img{height:auto;display:block}
.cover .clogos .s span{display:block;font-size:8pt;letter-spacing:.2em;color:var(--mut);
  margin-top:1.5mm;text-align:right}
.cover .hero{background:linear-gradient(135deg,var(--navy) 0%,#0B3D6E 52%,var(--acc-d) 100%);
  color:#fff;padding:20mm 20mm 18mm;position:relative;overflow:hidden}
.cover .hero::after{content:"";position:absolute;right:-30mm;top:-40mm;width:110mm;height:110mm;
  border-radius:50%;background:rgba(255,255,255,.055)}
.cover .hero .kick{font-size:8.5pt;font-weight:700;letter-spacing:.24em;text-transform:uppercase;
  color:rgba(255,255,255,.72);margin-bottom:7mm}
.cover .hero h1{margin:0;font-size:27pt;line-height:1.16;font-weight:700;letter-spacing:-.018em;
  max-width:135mm;color:#fff}
.cover .hero .prod{margin-top:7mm;padding-top:6mm;border-top:1px solid rgba(255,255,255,.22);
  font-size:14pt;font-weight:600;color:#8FD3FF;letter-spacing:-.005em}
.cover .body{padding:14mm 20mm 0;flex:1}
.cover .to{border-left:1.2mm solid var(--acc);padding:1mm 0 1mm 6mm}
.cover .to .k{font-size:8pt;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--mut)}
.cover .to .v{font-size:16pt;font-weight:700;color:var(--ink);line-height:1.3;margin-top:2mm;
  letter-spacing:-.01em}
.cover .tod{margin-top:3mm;font-size:9.5pt;line-height:1.5;color:var(--body)}
.cover .meta{display:flex;gap:14mm;margin-top:12mm;padding-top:7mm;border-top:1px solid var(--line)}
.cover .meta .k{font-size:8pt;font-weight:700;letter-spacing:.16em;text-transform:uppercase;
  color:var(--mut);margin-bottom:1.6mm}
.cover .meta .v{font-size:11pt;color:var(--ink);font-weight:600}

/* ── επιστολή ── */
.lh{display:flex;justify-content:space-between;gap:12mm;align-items:flex-start;
  padding-bottom:5mm;border-bottom:1px solid var(--line);margin-bottom:6mm}
.lh .k{font-size:8pt;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--mut)}
.lh .v{color:var(--ink);font-weight:600;margin-bottom:3mm}
.lh .v:last-child{margin-bottom:0}
.lh .rt{text-align:right;flex:none}
.sig{margin-top:8mm;padding-top:5mm;border-top:1px solid var(--line)}
.sig .nm{margin-top:11mm;font-weight:700;color:var(--ink);font-size:11pt}
.sig .co{color:var(--mut);font-size:9.5pt}

/* ── περιεχόμενα ── */
.toc{margin-top:2mm}
.toc a,.toc>div{display:flex;align-items:baseline;gap:4mm;padding:3.4mm 0;
  border-bottom:1px solid var(--hair)}
.toc .tn{flex:0 0 14mm;font-variant-numeric:tabular-nums;font-weight:700;color:var(--acc);font-size:9.5pt}
.toc .t{flex:1 1 auto;color:var(--ink);font-weight:600}
.toc .n{flex:none;font-variant-numeric:tabular-nums;color:var(--mut);font-size:9.5pt}
.toc .l1 .t{font-size:11.5pt}
.toc .l2{padding-left:8mm}
.toc .l2 .t{font-weight:400;color:var(--body);font-size:10pt}
.toc .l2 .tn{font-size:9pt;color:var(--faint)}

/* ── πίνακας ενοτήτων λογισμικού ── */
table.m{width:100%;border-collapse:collapse;font-size:9.5pt;table-layout:fixed}
table.m th{text-align:left;padding:0 0 2.6mm;border-bottom:1.4px solid var(--ink);
  font-size:8pt;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--ink)}
table.m th.n,table.m td.n{width:32mm;text-align:center}
table.m td{padding:2mm 2mm;border-bottom:1px solid var(--hair);word-wrap:break-word;vertical-align:top}
table.m tr.grp td{padding:5mm 2mm 2mm;border-bottom:1px solid var(--line);
  font-size:8pt;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--acc)}
table.m tr.grp:first-child td{padding-top:3.5mm}
table.m tr.off td{color:var(--faint)}
.dot{display:inline-block;width:3.2mm;height:3.2mm;border-radius:50%;background:var(--acc);
  box-shadow:0 0 0 1.2mm var(--acc-t)}
.ring{display:inline-block;width:3.2mm;height:3.2mm;border-radius:50%;
  box-shadow:inset 0 0 0 .5mm var(--line)}

/* ── πίνακες κόστους ── */
table.p{width:100%;border-collapse:collapse;font-size:10pt;margin:0 0 9mm;table-layout:fixed}
table.p th{text-align:left;padding:0 0 2.8mm;border-bottom:1.4px solid var(--ink);
  font-size:8.5pt;font-weight:700;letter-spacing:.13em;text-transform:uppercase;color:var(--ink)}
table.p th.n,table.p td.n{width:36mm;text-align:right;white-space:nowrap;
  font-variant-numeric:tabular-nums}
table.p td{padding:2.5mm 2mm;border-bottom:1px solid var(--hair);word-wrap:break-word;vertical-align:top}
table.p td.d{color:var(--ink)}
table.p td.n{font-weight:600;color:var(--ink)}
table.p tr.sub td{border-bottom:1px solid var(--hair)}
table.p tr.sub td.d{padding-left:9mm;position:relative;color:var(--body)}
table.p tr.sub td.d::before{content:"";position:absolute;left:2mm;top:4mm;width:2.4mm;height:2.4mm;
  border-radius:50%;background:var(--acc-t);box-shadow:inset 0 0 0 .7mm var(--acc)}
table.p tr.tot td{border-top:1.4px solid var(--ink);border-bottom:none;background:var(--acc-w);
  padding:3mm 2mm;font-weight:700;color:var(--ink);font-size:10.5pt}
table.p .q{display:block;font-size:8pt;color:var(--mut);margin-top:.8mm;font-weight:400;letter-spacing:.01em}
table.p td.nt{font-size:8pt;font-weight:700;letter-spacing:.08em;color:var(--acc-d);white-space:nowrap}
table.p th .cont{font-weight:400;letter-spacing:.1em;color:var(--mut);text-transform:none}
.note{font-size:9pt;color:var(--mut)}

/* ── συγκεντρωτικοί ── */
table.s{width:100%;border-collapse:collapse;font-size:10.5pt;table-layout:fixed;margin-bottom:5mm}
table.s th{text-align:left;padding:0 0 2.8mm;border-bottom:1.4px solid var(--ink);
  font-size:8.5pt;font-weight:700;letter-spacing:.13em;text-transform:uppercase;color:var(--ink)}
table.s th.n,table.s td.n{width:44mm;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
table.s td{padding:2.6mm 2mm;border-bottom:1px solid var(--hair)}
table.s td.l{color:var(--body)}
table.s td.n{font-weight:600;color:var(--ink)}
table.s tr.plain td{border-top:1px solid var(--line);font-weight:700;color:var(--ink)}
table.s tr.plain td.l{color:var(--ink)}
table.s tr.disc td{color:var(--red)}
table.s tr.disc td.n{color:var(--red);font-weight:700}
.grand{display:flex;align-items:center;justify-content:space-between;gap:8mm;
  background:linear-gradient(135deg,var(--navy),#0B3D6E);color:#fff;
  padding:5mm 7mm;border-radius:2mm;margin-top:2mm}
.grand .k{font-size:9pt;font-weight:700;letter-spacing:.16em;text-transform:uppercase;
  color:rgba(255,255,255,.78)}
.grand .v{font-size:18pt;font-weight:700;font-variant-numeric:tabular-nums;letter-spacing:-.015em}
.terms{margin-top:6mm;display:flex;gap:6mm;flex-wrap:wrap}
.terms .cell{flex:1 1 60mm;background:var(--tint);border-left:.9mm solid var(--acc);
  padding:3mm 4mm;border-radius:0 2mm 2mm 0}
.terms .k{font-size:8pt;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--mut)}
.terms .v{color:var(--ink);font-weight:600;margin-top:1mm}

/* ── τράπεζες ── */
.banks{display:flex;gap:6mm;margin-top:4mm}
.bank{flex:1 1 0;border:1px solid var(--line);border-radius:2.5mm;padding:4.5mm;background:var(--tint)}
.bank .nm{font-size:9pt;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  color:var(--acc-d);margin-bottom:3mm;padding-bottom:2.5mm;border-bottom:1px solid var(--line)}
.bank .k{font-size:8pt;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);margin-top:2.6mm}
.bank .v{color:var(--ink);font-weight:600;font-variant-numeric:tabular-nums;font-size:9.5pt;
  letter-spacing:.01em;word-break:normal;overflow-wrap:break-word}
.pay{display:flex;gap:5mm;margin:5mm 0 7mm}
.pay .step{flex:1 1 0;border:1px solid var(--line);border-radius:2.5mm;padding:4.5mm}
.pay .pc{font-size:20pt;font-weight:700;color:var(--acc);line-height:1;letter-spacing:-.02em}
.pay .pl{margin-top:2.5mm;color:var(--body)}

/* ── έντυπο αποδοχής ── */
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:4mm 8mm;margin-bottom:7mm}
.fgrid .fld{border-bottom:1px solid var(--line);padding-bottom:2mm;min-height:10mm}
.fgrid .fld.wide{grid-column:1 / -1}
.fgrid .k{font-size:8pt;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:var(--mut);margin-bottom:1.5mm}
.fgrid .v{color:var(--ink);font-weight:600;min-height:5mm}
.card{border:1px solid var(--line);border-radius:2.5mm;background:var(--tint);padding:4mm 6mm;margin-bottom:7mm}
.card .ttl{font-size:8pt;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:var(--acc-d);margin-bottom:3mm}
.card .row{display:flex;gap:4mm;padding:1.8mm 0;border-bottom:1px solid var(--line)}
.card .row:last-child{border-bottom:none}
.card .row .k{flex:0 0 34mm;color:var(--mut)}
.card .row .v{color:var(--ink);font-weight:600}
.box{border:1px solid var(--line);border-radius:2.5mm;margin-bottom:6mm;overflow:hidden}
.box .h{padding:3mm 5mm;background:var(--tint);border-bottom:1px solid var(--line);
  font-size:8pt;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--mut)}
.box .v{height:22mm}
.box.sg .v{height:29mm}

@page{size:A4;margin:12mm 14mm}
@media print{
  body{background:#fff;padding:0}
  .page{width:auto;min-height:262mm;margin:0;padding:0 0 16mm;box-shadow:none;
    break-after:page;page-break-after:always}
  .page.cover{padding:0}
  .cover .clogos{padding:0 0 10mm}
  .cover .hero{padding:18mm 14mm;margin:0 -14mm}
  .cover .body{padding:14mm 0 0}
  .page:last-child{break-after:auto;page-break-after:auto}
  .foot{left:0;right:0;bottom:2mm}
  table.m tr,table.p tr,table.s tr,.bank,.box,.grand{break-inside:avoid;page-break-inside:avoid}
  h1.sec,h2.sub,h3.sub3{break-after:avoid;page-break-after:avoid}
}
CSS;
    }

    /**
     * Το πλήρες έντυπο προσφοράς — δεκατρείς σελίδες, στη μορφή που στέλνουμε
     * χρόνια στους πελάτες («ΟΙΚΟΝ ΠΡΟΣΦΟΡΑ SOFT1 … CLOUD»). Επιστρέφει μόνο τις
     * σελίδες· το στυλ το δίνει η docCss().
     */
    public static function docHtml($cfg)
    {
        $res = self::calc($cfg);
        $c = $res['cfg'];
        $p = $c['p']; $o = $c['o']; $i = $c['sel']; $e = $c['ed'][$i];
        $t = $res['totals'][$i];
        $lines = self::bucketLines($res, $i);
        $bt = [];
        foreach (self::buckets() as $b => $bd) { $bt[$b] = array_sum(array_column($lines[$b], 'amount')); }
        $pre = array_sum($bt);
        $disc = (float) $o['discount'];
        $fin = $pre - $disc;
        $company = $o['client'] !== '' ? $o['client'] : '……………………………';
        $q = '«' . $company . '»';
        $prod = $e['soft1'] . ' - Cloud Edition';
        $full = $prod . ' + ' . $e['name'];
        $date = self::longDate($o['date']);
        self::$run = 'Οικονομική Πρόταση · ' . $company;
        self::$ref = $o['protocol'] !== '' ? 'Αρ. Πρωτοκόλλου ' . $o['protocol'] : 'CloudOn Ι.Κ.Ε.';
        /* Οι σελίδες συντίθενται πρώτα και αριθμούνται μετά: τα περιεχόμενα δεν μπορούν
           να δείχνουν σελίδες που δεν έχουν ακόμη προκύψει. */
        $P = [];
        $mark = [];
        /* Χώρος για πίνακες σε μια σελίδα, σε χιλιοστά. */
        $ROOM  = self::MM_PAGE - self::MM_HEAD;   // σκέτη σελίδα πινάκων
        $ROOM1 = $ROOM - 32;                      // …με την επικεφαλίδα 2.2 από πάνω
        $ROOMM = $ROOM - 96;                     // …με τα εισαγωγικά της 2.1 από πάνω

        /* ── 1. Εξώφυλλο ── */
        $P[] = self::mk(self::logos()
            . '<div class="hero"><div class="kick">Οικονομική Πρόταση</div>'
            . '<h1>Για την εγκατάσταση και λειτουργία του πληροφοριακού συστήματος</h1>'
            . '<div class="prod">' . self::e($prod) . '</div></div>'
            . '<div class="body"><div class="to"><div class="k">Προς</div>'
            . '<div class="v">' . self::e(mb_strtoupper($company, 'UTF-8')) . '</div>'
            . self::toDetails($o) . '</div>'
            . '<div class="meta">'
            . '<div><div class="k">Ημερομηνία</div><div class="v">' . self::e($date) . '</div></div>'
            . '<div><div class="k">Αρ. Πρωτοκόλλου</div><div class="v">'
            . self::e($o['protocol']) . '</div></div></div></div>', 'cover');

        /* ── 2. Συνοδευτική επιστολή ── */
        $P[] = self::mk('<div class="lh"><div>'
            . '<div class="k">Προς</div><div class="v">' . self::e($company) . '</div>'
            . '<div class="k">Υπόψη</div><div class="v">' . self::e($o['attn'] ?: '……………………') . '</div>'
            . '<div class="k">Αριθμός Πρωτοκόλλου</div><div class="v">' . self::e($o['protocol']) . '</div>'
            . '</div><div class="rt">'
            . '<div class="k">' . self::e($o['city']) . '</div>'
            . '<div class="v">' . self::e($date) . '</div></div></div>'
            . '<p>' . self::e($o['greeting'] ?: 'Αξιότιμοι κύριοι,') . '</p>'
            . '<p>Σε συνέχεια του ενδιαφέροντος που εκδηλώσατε για την εγκατάσταση του λογισμικού Soft1 '
            . 'και της πλατφόρμας PharmacyOne στην επιχείρηση ' . self::e($q) . ', σας παραθέτουμε την '
            . 'οικονομική μας πρόταση που αφορά σε όλο το εύρος των βασικών λειτουργιών και επιμέρους '
            . 'διαδικασιών που συζητήθηκαν και αναλύθηκαν στις ιδιαίτερες συναντήσεις μας.</p>'
            . '<p>Η πρόταση μας στηρίζεται στο ολοκληρωμένο και καινοτόμο για τα ελληνικά δεδομένα '
            . 'πληροφοριακό σύστημα Soft1, το οποίο έχει αναπτυχθεί από μια κορυφαία ομάδα εξειδικευμένων '
            . 'στελεχών με αξιοποίηση των πλέον σύγχρονων τεχνολογιών πληροφορικής, σε συνδυασμό με το '
            . '<b>PharmacyOne</b> — τη λύση της CloudOn για το σύγχρονο φαρμακείο, που καλύπτει τη '
            . 'συνταγογράφηση, τη διαχείριση αποθέματος και τη διασύνδεση με τα κανάλια λιανικής.</p>'
            . '<p>Το Soft1 είναι το μοναδικό ελληνικό λογισμικό που ενοποιεί σε ένα ολοκληρωμένο '
            . 'πληροφοριακό σύστημα λειτουργίες ERP, Μισθοδοσίας, Διαχείρισης Προσωπικού, CRM και '
            . 'Επιχειρηματικών Διαδικασιών. Με τεχνολογίες αιχμής και μεγάλο εύρος λειτουργιών, το '
            . 'λογισμικό Soft1 εξελίσσεται συνεχώς και προσφέρει ένα σύγχρονο μοντέλο μηχανογράφησης με '
            . 'ουσιαστικά οφέλη και πλεονεκτήματα για τη σύγχρονη επιχείρηση.</p>'
            . '<p>Η πολυετής παρουσία και υψηλή τεχνογνωσία της SoftOne Technologies στην αγορά business '
            . 'λογισμικού, η μεγάλη εγκατεστημένη βάση πελατών στην Ελλάδα και τη διεθνή αγορά, αλλά και '
            . 'το διευρυμένο δίκτυο συνεργατών αποτελούν τα εχέγγυα που διασφαλίζουν την επιτυχή '
            . 'εγκατάσταση του προτεινόμενου συστήματος στην επιχείρησή σας. Παραμένουμε στη διάθεσή σας '
            . 'για οποιαδήποτε συμπληρωματική πληροφορία ή διευκρίνιση.</p>'
            . '<div class="sig">Με τιμή,<div class="nm">' . self::e($o['seller']) . '</div>'
            . '<div class="co">CloudOn Ι.Κ.Ε.</div></div>');

        /* ── 3. Περιεχόμενα ── */
        $P[] = self::mk('%%TOC%%');

        /* ── 4. Εισαγωγή ── */
        $P[] = self::mk('<h1 class="sec">Εισαγωγή</h1>'
            . '<p>Η παρούσα οικονομική πρόταση αφορά στην προμήθεια, εγκατάσταση και θέση σε λειτουργία '
            . 'πληροφοριακού συστήματος <b>' . self::e($full) . '</b> στην “' . self::e($company) . '”.</p>'
            . '<p>Η διαμόρφωση της πρότασης αυτής προέκυψε με βάση τις απαιτήσεις της ' . self::e($q)
            . ' για τη λειτουργία του συστήματος, όπως παρουσιάσθηκαν και συζητήθηκαν αναλυτικά σε '
            . 'συναντήσεις στελεχών της εταιρίας με τα αρμόδια στελέχη της <b class="br">CloudOn</b>.</p>'
            . '<p>Στο πλαίσιο της πρότασης αυτής, η <b class="br">CloudOn</b> Ι.Κ.Ε. είναι σε θέση να '
            . 'αναλάβει τις ακόλουθες εργασίες:</p><ul>'
            . '<li>την εγκατάσταση του λογισμικού <b>' . self::e($full) . '</b></li>'
            . '<li>την παραμετροποίηση του συστήματος με βάση τις απαιτήσεις της ' . self::e($q) . '.</li></ul>'
            . '<p>Πιο συγκεκριμένα, η <b class="br">CloudOn</b> θα εγκαταστήσει εφαρμογές λογισμικού που θα '
            . 'ικανοποιούν τις επιθυμητές προδιαγραφές λειτουργίας, όπως περιγράφονται στη συνέχεια, '
            . 'ακολουθώντας κατάλληλη μεθοδολογία υλοποίησης και σύμφωνα με το χρονοδιάγραμμα εργασιών '
            . 'που παρουσιάζεται αναλυτικά σε επόμενη ενότητα της παρούσας πρότασης.</p>'
            . '<p>H <b class="br">CloudOn</b> εγγυάται την επιτυχή εγκατάσταση και λειτουργία του '
            . 'Λογισμικού, την υψηλή ποιότητα υπηρεσιών παραμετροποίησης λαμβάνοντας υπόψη:</p>'
            . '<ul class="tick"><li>Την μεγάλη τεχνογνωσία της SoftOne στην ανάπτυξη λύσεων λογισμικού,</li>'
            . '<li>Την διευρυμένη εμπειρία των στελεχών της, στην υλοποίηση σύνθετων έργων,</li>'
            . '<li>Την Ποιότητα και πληρότητα του λογισμικού Soft1</li></ul>'
            . '<p>Το λογισμικό <b>Soft1</b> αποτελεί μία από τις πλέον σύγχρονες λύσεις business '
            . 'λογισμικού, καθώς έχει σχεδιαστεί με βάση ιδιαίτερα υψηλές τεχνικές και λειτουργικές '
            . 'προδιαγραφές και με αξιοποίηση προηγμένων τεχνολογικών εργαλείων. Παρουσιάζει σημαντικά '
            . 'πλεονεκτήματα έναντι ανταγωνιστικών προϊόντων σε σχέση με την αρχιτεκτονική δόμησή του, '
            . 'τις τεχνολογίες που χρησιμοποιεί και την ενσωμάτωση σε μια ενιαία πλατφόρμα λειτουργιών '
            . 'όπως Χρηματοοικονομική Διαχείριση, Εμπορική Διαχείριση, Διαχείριση Εφοδιαστικής Αλυσίδας, '
            . 'Παραγωγή, CRM και Παροχή Υπηρεσιών.</p>'
            . '<p>Προτεραιότητα και επιδίωξη της <b>Soft1</b> είναι να εξελίσσει διαρκώς τα προϊόντα '
            . 'Soft1, έτσι ώστε να είναι πάντα σύγχρονα και να ξεχωρίζουν για την καινοτομία, την '
            . 'πληρότητα και την αποτελεσματικότητα τους. Βασικός και κυρίαρχος στόχος της εταιρίας είναι '
            . 'να προσφέρει ολοκληρωμένες λύσεις για τη μηχανογράφηση επιχειρήσεων που θα ικανοποιούν '
            . 'κάθε απαίτηση λειτουργίας, θα δημιουργούν ανταγωνιστικά πλεονεκτήματα και θα συνδράμουν '
            . 'ουσιαστικά στην ανάπτυξη των επιχειρήσεων στο σύγχρονο, σύνθετο επιχειρηματικό '
            . 'περιβάλλον.</p>');

        /* ── 5. Soft1 Open Enterprise Edition ── */
        $P[] = self::mk('<h1 class="sec"><span class="num">1</span>Soft1 Open Enterprise Edition</h1>'
            . '<p>Η νέα έκδοση <b>Soft1 Open Enterprise Edition</b> χαρακτηρίζει τη νέα γενιά λογισμικού '
            . 'Soft1, απευθύνεται σε κάθε σύγχρονη <b>«ανοικτή επιχείρηση»</b>, ανεξάρτητα από μέγεθος ή '
            . 'κλάδο δραστηριότητας και καλύπτει με τον καλύτερο τρόπο τις απαιτήσεις για:</p>'
            . '<ul class="tick"><li>ασφαλή και αξιόπιστη πρόσβαση σε δεδομένα της επιχείρησης, από παντού</li>'
            . '<li>αυτοματοποίηση διαδικασιών συνεργασίας με πελάτες και προμηθευτές</li>'
            . '<li>πλήρη διασύνδεση με άλλα συστήματα και εφαρμογές</li>'
            . '<li>εύκολη προσαρμογή της λειτουργικότητας για άμεση ανταπόκριση στις προκλήσεις της αγοράς</li></ul>'
            . '<p>Το λογισμικό “<b>Soft1 Open Enterprise Edition</b>” διατίθεται με εμπορικούς συνδυασμούς '
            . 'Soft1 που καλύπτουν τις μηχανογραφικές απαιτήσεις κάθε επιχείρησης και υποστηρίζουν κάθε '
            . 'εναλλακτικό μοντέλο λειτουργίας (on-premise ή στο cloud).</p>'
            . '<p>Η έκδοση Soft1 Open Enterprise περιλαμβάνει την λειτουργία των εφαρμογών <b class="br">Web '
            . '&amp; Mobile</b> &amp; <b>Soft1 eINVOICE Connector</b> εφαρμογές που διευρύνουν τις '
            . 'δυνατότητες του λογισμικού Soft1 και ικανοποιούν όλες τις απαιτήσεις για «επιχειρησιακή '
            . 'φορητότητα».</p>'
            . '<p>Με τις νέες <b>Web &amp; Mobile</b> εφαρμογές, τα στελέχη της επιχείρησης έχουν τη δυνατότητα:</p>'
            . '<ul class="tick"><li>να ανταποκριθούν πιο άμεσα σε ανάγκες πελατών τους</li>'
            . '<li>να εκμεταλλευθούν πληροφορίες για πιο γρήγορες και σωστές αποφάσεις</li>'
            . '<li>να διεκπεραιώσουν με πιο ευέλικτο τρόπο τις εργασίες τους.</li></ul>'
            . '<p>Η νέα τεχνολογική πλατφόρμα <b>Soft1 Mobility Platform</b> επιτρέπει τη δομημένη ανάπτυξη '
            . 'web &amp; mobile εφαρμογών, περιλαμβάνοντας κι ένα ενιαίο περιβάλλον διαχείρισης και ελέγχου '
            . 'λειτουργίας για ασφαλή διακίνηση δεδομένων, καθορισμό δικαιωμάτων πρόσβασης κλπ.</p>'
            . '<p>Με τις μοναδικές δυνατότητες του <b>Soft1 Cloud Mobilizer</b> και αξιοποιώντας τα '
            . '<b>Soft1 Web Services</b>, οι <b>Web &amp; Mobile</b> λύσεις της <b>SoftOne</b> μπορούν να '
            . 'λειτουργήσουν άμεσα, κυριολεκτικά με το πάτημα ενός κουμπιού! Χωρίς ανάγκη για πρόσθετο '
            . 'εξοπλισμό, χωρίς απαιτήσεις για συγχρονισμούς δεδομένων, χωρίς τεχνικές πολυπλοκότητες! '
            . '<b>Οικονομικά, αξιόπιστα και με απόλυτη ασφάλεια!</b></p>'
            . '<p>Η εξαιρετική λειτουργική πληρότητα της νέας γενιάς λογισμικού <b>Soft1 Open Enterprise '
            . 'Edition</b> είναι σε θέση να δημιουργήσει ανταγωνιστικά πλεονεκτήματα και να υποστηρίξει '
            . 'αποτελεσματικά κάθε επιχειρησιακό μοντέλο λειτουργίας, παρέχοντας τη δυνατότητα διαμόρφωσης '
            . 'μιας σύγχρονης μηχανογράφησης με ουσιαστικά οφέλη και πλεονεκτήματα για την επιχείρηση.</p>');

        /* ── 6-7. Προτεινόμενο λογισμικό + ο πίνακας των ενοτήτων ── */
        $tbl = self::soft1Table();
        $mk = function ($from, $to) use ($tbl, $i) {
            $h = '<table class="m"><thead><tr><th>Soft1 ERP</th><th class="n">Περιλαμβάνεται</th></tr>'
                . '</thead><tbody>';
            for ($x = $from; $x < $to && $x < count($tbl); $x++) {
                $r = $tbl[$x];
                if ($r[0] === 'g') {
                    $h .= '<tr class="grp"><td colspan="2">' . self::e($r[1]) . '</td></tr>';
                    continue;
                }
                $on = !empty($r[2][$i]);
                $lab = !empty($r[3]) ? $r[1] : self::e($r[1]);   // 4ο πεδίο = ήδη HTML
                /* Γεμάτη κουκκίδα = μέσα· άδειος δακτύλιος = εκτός. Το κενό κελί άφηνε
                   τον αναγνώστη να αναρωτιέται αν λείπει η πληροφορία ή η δυνατότητα. */
                $h .= '<tr' . ($on ? '' : ' class="off"') . '><td>' . $lab . '</td>'
                    . '<td class="n"><span class="' . ($on ? 'dot' : 'ring') . '"></span></td></tr>';
            }
            return $h . '</tbody></table>';
        };
        $head21 = '<h1 class="sec"><span class="num">2</span>Οικονομική Πρόταση</h1>'
            . '<h2 class="sub"><span class="num">2.1</span>Προτεινόμενο Λογισμικό</h2>'
            . '<p>Το λογισμικό που προτείνεται με την παρούσα πρόταση για εγκατάσταση και λειτουργία στην '
            . self::e($q) . ' είναι το <b>' . self::e($full) . '</b> που περιλαμβάνεται στην έκδοση '
            . '<b>Soft1 Open Enterprise Edition</b>.</p>'
            . '<p>Αναλυτικότερα, προτείνεται ο συνδυασμός <b class="br">' . self::e($prod) . '</b> που '
            . 'διατίθεται εναλλακτικά σύμφωνα με τους παρακάτω τρόπους διάθεσης:</p>'
            . '<ul><li>Ορισμένου Χρόνου / cloud - Συνδρομητικό Μοντέλο διάθεσης</li></ul>'
            . '<p>και περιλαμβάνει τη λειτουργικότητα των ενοτήτων Λογισμικού που περιγράφονται αναλυτικά '
            . 'παρακάτω:</p>';
        /* Κόβουμε στο όριο της σελίδας, ποτέ αμέσως μετά από επικεφαλίδα ομάδας. */
        $mark['2.1'] = count($P) + 1;
        $from = 0; $u = ($ROOM - $ROOMM) + self::MM_MTHEAD; $cut = [];
        foreach ($tbl as $x => $r) {
            $ru = $r[0] === 'g' ? self::MM_MGRP : self::MM_MROW;
            if ($u + $ru > $ROOM) { $cut[] = $x; $u = self::MM_MTHEAD; }
            $u += $ru;
        }
        $cut[] = count($tbl);
        foreach ($cut as $z => $to) {
            $P[] = self::mk(($z === 0 ? $head21 : '') . $mk($from, $to));
            $from = $to;
        }

        /* ── 7β. Τι περιλαμβάνει το πακέτο και τι προστίθεται επιπλέον ──
           Ο πελάτης πρέπει να ξεχωρίζει με μια ματιά τι έρχεται μέσα στην έκδοση από
           αυτά που ενεργοποιούμε πάνω της· αλλιώς τα βλέπει όλα ως «χρεώσεις». */
        $incHead = '<h2 class="sub"><span class="num">2.1.1</span>Η έκδοση '
            . self::e($e['name']) . ' — τι περιλαμβάνει</h2>';
        $incTbl = '<table class="m"><thead><tr><th>' . self::e($e['name'])
            . '</th><th class="n">Περιλαμβάνεται</th></tr></thead><tbody>'
            . '<tr class="grp"><td colspan="2">Λογιστική κατηγορία ' . self::e($e['cat'])
            . ' · ' . self::e($e['soft1']) . '</td></tr>';
        foreach (self::features() as $f) {
            $onF = !empty($f[1][$i]);
            $incTbl .= '<tr' . ($onF ? '' : ' class="off"') . '><td>' . self::e($f[0]) . '</td>'
                . '<td class="n"><span class="' . ($onF ? 'dot' : 'ring') . '"></span></td></tr>';
        }
        $incTbl .= '</tbody></table>';

        $mq = self::moduleQty($c);
        $exBody = ''; $exRows = 0;
        foreach (self::moduleGroups() as $g) {
            $rows = '';
            foreach ($g['items'] as $it) {
                if (empty($c['yn'][$it[0]])) { continue; }
                $rows .= '<tr><td>' . self::e($it[1]) . '</td><td class="n">'
                    . self::fmtQty(max(1, $mq[$it[0]] ?? 1)) . ' τεμ.</td></tr>';
                $exRows++;
            }
            if ($rows !== '') {
                $exBody .= '<tr class="grp"><td colspan="2">' . self::e($g['title']) . '</td></tr>' . $rows;
                $exRows++;
            }
        }
        $exHead = '<h3 class="sub3">Επιπλέον modules &amp; διασυνδέσεις που ενεργοποιούνται</h3>';
        $ex = $exBody === ''
            ? $exHead . '<p class="mut">Δεν ενεργοποιούνται επιπλέον modules — η προσφορά '
                . 'περιλαμβάνει μόνο τη λειτουργικότητα της έκδοσης.</p>'
            : $exHead . '<table class="m"><thead><tr><th>CloudOn / SoftOne modules</th>'
                . '<th class="n">Ποσότητα</th></tr></thead><tbody>' . $exBody . '</tbody></table>';
        $incMm = 24 + self::MM_MTHEAD + (count(self::features()) + 1) * self::MM_MROW;
        $exMm  = 26 + self::MM_MTHEAD + max($exRows, 1) * self::MM_MROW;
        if ($incMm + $exMm <= $ROOM) {
            $P[] = self::mk($incHead . $incTbl . $ex);
        } else {
            $P[] = self::mk($incHead . $incTbl);
            $P[] = self::mk($ex);
        }

        /* ── 8-10. Οικονομική προσφορά ── */
        $bk = self::buckets();
        /* Ο βασικός συνδυασμός πρώτος, ο extra user από κάτω ως περιλαμβανόμενος,
           και μετά όσα η έκδοση φέρνει μαζί της χωρίς χρέωση — όπως στο έντυπο. */
        $b1 = $lines[1];
        usort($b1, function ($a, $b) {
            $rank = function ($l) { return $l['k'] == 32 ? 0 : ($l['k'] == 31 ? 1 : 2); };
            return $rank($a) <=> $rank($b);
        });
        foreach ($b1 as $x => $l) { if ($l['k'] == 31) { $b1[$x]['sub'] = true; } }
        $inc = [];
        foreach (self::features() as $f) {
            if (!empty($f[1][$i])) { $inc[] = ['lab' => 'Soft1 ' . $f[0], 'sub' => true, 'note' => '']; }
        }
        if ($b1) { array_splice($b1, 2, 0, $inc); }

        $incRow = [['lab' => 'Νομοθετικές ενημερώσεις, Νέες εκδόσεις Λογισμικού, online ηλεκτρονική '
            . 'τεκμηρίωση', 'note' => 'ΠΕΡΙΛΑΜΒΑΝΟΝΤΑΙ']];
        $head22 = '<h2 class="sub"><span class="num">2.2</span>Οικονομική Προσφορά - Λογισμικό '
            . self::e($prod) . '</h2>'
            . '<p><b>Τρόπος Διάθεσης Λογισμικού:</b> Ορισμένου Χρόνου / Cloud - Συνδρομητικό Μοντέλο διάθεσης</p>';
        $mark['2.2'] = count($P) + 1;
        $chunks = [];
        foreach ([1, 2, 3, 4, 5] as $b) {
            /* Ο πρώτος κάδος μοιράζεται τη σελίδα με την επικεφαλίδα — παίρνει λιγότερο χώρο. */
            foreach (self::bucketChunks($bk[$b], $b === 1 ? $b1 : $lines[$b],
                $b === 1 ? $ROOM1 : $ROOM, $b === 5 ? $incRow : []) as $ch) { $chunks[] = $ch; }
        }
        $open = $head22; $u = $ROOM - $ROOM1; $held = 0;
        foreach ($chunks as $ch) {
            /* Ποτέ σελίδα με μόνο μια επικεφαλίδα: κόβουμε αφού μπει τουλάχιστον ένας πίνακας. */
            if ($held > 0 && $u + $ch['u'] > $ROOM) {
                $P[] = self::mk($open); $open = ''; $u = 0; $held = 0;
            }
            $open .= $ch['h'];
            $u += $ch['u'] + self::MM_GAP;
            $held++;
        }
        /* Η 2.3 είναι σύντομη — μπαίνει στο υπόλοιπο της τελευταίας σελίδας αν χωρά. */
        $h3 = 0;
        foreach ($lines[3] as $l) { $h3 += self::hrs($l['amount']); }
        $tail23 = '<h2 class="sub"><span class="num">2.3</span>Υπηρεσίες Υλοποίησης Έργου</h2>'
            . '<p>Το κόστος των υπηρεσιών υλοποίησης του έργου, όπως αναλύεται λεπτομερώς στην παράγραφο '
            . '<b>2.2</b> «Οικονομική Προσφορά - Υπηρεσίες Παραμετροποίησης», ανέρχεται σε <b>'
            . self::fmtEur($bt[3]) . '</b> και αντιστοιχεί σε <b>' . $h3
            . '</b> ώρες εργασίας.</p>'
            . '<p>Ο σχεδιασμός και η υλοποίηση του έργου θα βασιστεί στις λειτουργικές / τεχνικές '
            . 'δυνατότητες και προδιαγραφές των εφαρμογών Soft1.</p>'
            . '<p class="mut">Οι ώρες υπηρεσιών υπολογίζονται με χρέωση ' . self::fmtEur(self::HOUR_RATE)
            . ' ανά ώρα. Στις παραπάνω αξίες έχουν ήδη ενσωματωθεί οι εκπτώσεις: αδειών CloudOn '
            . self::fmtPct($p['L4']) . ', αδειών Soft1 ' . self::fmtPct($p['K8']) . ', υπηρεσιών '
            . self::fmtPct($p['J8']) . '.</p>';
        if ($u + 62 > $ROOM) { $P[] = self::mk($open); $open = ''; }   // 62mm = η ενότητα 2.3
        $mark['2.3'] = count($P) + 1;
        $P[] = self::mk($open . $tail23);

        /* ── 11. Συγκεντρωτικοί πίνακες ── */
        $sum = '<h2 class="sub"><span class="num">2.4</span>Συγκεντρωτικοί πίνακες</h2>'
            . '<h3 class="sub3"><span class="num">2.4.1</span>Λογισμικό &amp; Υπηρεσίες</h3>'
            . '<table class="s"><thead><tr><th>Περιγραφή</th><th class="n">Αξία</th></tr></thead><tbody>';
        foreach ([1, 3, 5, 2, 4] as $b) {
            $sum .= '<tr><td class="l">' . self::e($bk[$b][1]) . '</td><td class="n">'
                . self::fmtEur($bt[$b]) . '</td></tr>';
        }
        /* Πόσο θα κόστιζε χωρίς τις εκπτώσεις που δώσαμε — η αξία τους αλλιώς δεν φαίνεται
           πουθενά, αφού είναι ήδη ενσωματωμένη μέσα σε κάθε γραμμή. */
        $zc = $c;
        $zc['p']['L4'] = 0; $zc['p']['J8'] = 0; $zc['p']['K8'] = 0; $zc['rd'] = [];
        $zt = self::calc($zc);
        $gross = $zt['totals'][$i]['first'];
        $saved = $gross - $pre;
        $sum .= ($disc ? '<tr class="plain"><td class="l">Σύνολο</td><td class="n">'
                . self::fmtEur($pre) . '</td></tr>'
                . '<tr class="disc"><td class="l">Επιπλέον έκπτωση προσφοράς</td><td class="n">-'
                . self::fmtEur($disc) . '</td></tr>' : '')
            . '</tbody></table>'
            . '<div class="grand"><span class="k">Τελική Αξία Έργου</span>'
            . '<span class="v">' . self::fmtEur($fin) . '</span></div>'
            . ($saved > 0.005
                ? '<p class="mut" style="margin-top:4mm">Στις παραπάνω αξίες έχουν <b>ήδη ενσωματωθεί '
                    . 'εκπτώσεις συνολικής αξίας ' . self::fmtEur($saved) . '</b> — αδειών CloudOn '
                    . self::fmtPct($p['L4']) . ', υπηρεσιών ' . self::fmtPct($p['J8']) . ', αδειών Soft1 '
                    . self::fmtPct($p['K8']) . '. Χωρίς αυτές η αξία του έργου θα ανερχόταν σε '
                    . self::fmtEur($gross) . '.</p>'
                : '')
            . '<div class="terms">'
            . '<div class="cell"><div class="k">Αξίες</div><div class="v">Όλες οι παραπάνω αξίες '
            . 'επιβαρύνονται με ΦΠΑ ' . (float) $o['vat'] . '%.</div></div>'
            . '<div class="cell"><div class="k">Ισχύς προσφοράς</div><div class="v">Η προσφορά ισχύει '
            . 'για ' . (int) $o['validDays'] . ' ημέρες.</div></div></div>'
            . '<h3 class="sub3"><span class="num">2.4.2</span>Εφάπαξ και επαναλαμβανόμενο κόστος</h3>'
            . '<table class="s"><thead><tr><th>Περιγραφή</th><th class="n">Αξία</th></tr></thead><tbody>'
            . '<tr><td class="l">Εφάπαξ κόστος έναρξης (παραμετροποίηση &amp; διασυνδέσεις)</td>'
            . '<td class="n">' . self::fmtEur($bt[3] + $bt[4]) . '</td></tr>'
            . '<tr><td class="l">Ετήσιο επαναλαμβανόμενο κόστος (συνδρομές &amp; υποστήριξη)</td>'
            . '<td class="n">' . self::fmtEur($bt[1] + $bt[2] + $bt[5]) . '</td></tr>'
            . '<tr class="plain"><td class="l">Μηνιαίο κόστος ανά χρήστη</td><td class="n">'
            . self::fmtEur($t['monthlyPerUser']) . '</td></tr></tbody></table>';
        $mark['2.4'] = count($P) + 1;
        $P[] = self::mk($sum);

        /* Η ανάλυση του ετήσιου: ό,τι ξαναχρεώνεται κάθε χρόνο σε ΕΝΑΝ πίνακα — άδειες
           Soft1, άδειες CloudOn και ετήσιες υπηρεσίες (τηλεφωνική υποστήριξη, ενημερώσεις,
           courier, e-shop). Χωρίς αυτόν ο πελάτης έβλεπε μόνο ένα άθροισμα. */
        $annAll = array_merge($lines[1], $lines[2], $lines[5]);
        $annSum = '<h3 class="sub3"><span class="num">2.4.3</span>Ανάλυση ετήσιου επαναλαμβανόμενου κόστους</h3>'
            . '<p class="mut">Οι παρακάτω αξίες επαναλαμβάνονται κάθε έτος. Το εφάπαξ κόστος '
            . 'παραμετροποίησης και διασυνδέσεων (' . self::fmtEur($bt[3] + $bt[4]) . ') χρεώνεται '
            . 'μόνο στο πρώτο έτος.</p>'
            . '<table class="s"><thead><tr><th>Περιγραφή</th><th class="n">Αξία / έτος</th></tr></thead><tbody>';
        foreach ($annAll as $l) {
            $annSum .= '<tr><td class="l">' . self::e($l['lab'])
                . ($l['qty'] ? ' <span class="mut">· ' . self::e($l['qty']) . '</span>' : '')
                . '</td><td class="n">' . self::fmtEur($l['amount']) . '</td></tr>';
        }
        $annSum .= '</tbody></table>'
            . '<div class="grand"><span class="k">Ετήσιο επαναλαμβανόμενο κόστος</span>'
            . '<span class="v">' . self::fmtEur($bt[1] + $bt[2] + $bt[5]) . '</span></div>';
        $mark['2.4.3'] = count($P) + 1;
        $P[] = self::mk($annSum);

        /* ── 12. Τρόπος πληρωμής ── */
        $mark['2.5'] = count($P) + 1;
        /* Ο διακανονισμός όπως τον όρισε ο πωλητής — μία γραμμή ανά δόση. */
        $pl = self::planRows($o['plan'], $fin);
        $steps = '';
        foreach ($pl['rows'] as $st) {
            $steps .= '<div class="step"><div class="pc">' . self::e($st['lab']) . '</div>'
                . '<div class="pl">' . ($st['w'] !== '' ? self::e($st['w']) . ' — ' : '')
                . '<b>' . self::fmtEur($st['eur']) . '</b></div></div>';
        }
        if (abs($pl['rest']) > 0.01) {
            $steps .= '<div class="step"><div class="pc">—</div><div class="pl">Υπόλοιπο προς '
                . 'διακανονισμό — <b>' . self::fmtEur($pl['rest']) . '</b></div></div>';
        }
        /* Η συνδρομή δεν είναι μέρος του έργου: τιμολογείται χωριστά και επαναλαμβάνεται. */
        $annEur = $bt[1] + $bt[2] + $bt[5];
        $subDiv = ['Ετησίως προκαταβολικά' => [1, 'ανά έτος'], 'Ανά εξάμηνο' => [2, 'ανά εξάμηνο'],
            'Μηνιαία' => [12, 'ανά μήνα']];
        $sc = $subDiv[$o['subCycle']] ?? $subDiv['Ετησίως προκαταβολικά'];
        $P[] = self::mk('<h2 class="sub"><span class="num">2.5</span>Τρόπος Πληρωμής</h2>'
            . '<p>Ο παρακάτω διακανονισμός αφορά τη <b>συνολική αξία του πρώτου έτους</b> — '
            . 'το εφάπαξ κόστος παραμετροποίησης &amp; διασυνδέσεων μαζί με την πρώτη ετήσια '
            . 'συνδρομή του λογισμικού <b>' . self::e($prod) . '</b>:</p>'
            . '<table class="s"><tbody>'
            . '<tr><td class="l">Εφάπαξ κόστος έναρξης (παραμετροποίηση &amp; διασυνδέσεις)</td>'
            . '<td class="n">' . self::fmtEur($bt[3] + $bt[4]) . '</td></tr>'
            . '<tr><td class="l">Ετήσια συνδρομή &amp; υποστήριξη — 1ο έτος</td>'
            . '<td class="n">' . self::fmtEur($bt[1] + $bt[2] + $bt[5]) . '</td></tr>'
            . ($disc ? '<tr class="disc"><td class="l">Έκπτωση προσφοράς</td><td class="n">-'
                . self::fmtEur($disc) . '</td></tr>' : '')
            . '<tr class="plain"><td class="l">Συνολικό ποσό προς εξόφληση (1ο έτος, προ ΦΠΑ)</td>'
            . '<td class="n">' . self::fmtEur($fin) . '</td></tr>'
            . '</tbody></table>'
            . '<p>Η εξόφλησή του γίνεται ως εξής:</p>'
            . '<div class="pay">' . $steps . '</div>'
            . '<table class="s"><tbody>'
            . '<tr><td class="l">Τρόπος εξόφλησης</td><td class="n">' . self::e($o['payMethod']) . '</td></tr>'
            . '<tr><td class="l">Ετήσια συνδρομή &amp; υποστήριξη — <b>από το 2ο έτος</b></td><td class="n">'
            . self::fmtEur($annEur / $sc[0]) . ' ' . $sc[1] . '</td></tr>'
            . '</tbody></table>'
            . '<p class="mut">Η ετήσια συνδρομή τιμολογείται ' . mb_strtolower($o['subCycle'], 'UTF-8')
            . ', με πρώτη χρέωση την ημερομηνία έναρξης παραγωγικής λειτουργίας, και ανανεώνεται '
            . 'αυτόματα κάθε έτος. Οι εφάπαξ υπηρεσίες παραμετροποίησης και διασύνδεσης χρεώνονται '
            . 'μία μόνο φορά.</p>'
            . '<h3 class="sub3">Τραπεζικοί λογαριασμοί CLOUDON IKE</h3>'
            . '<div class="banks">'
            . '<div class="bank"><div class="nm">Eurobank</div>'
            . '<div class="k">Αριθμός λογαριασμού</div><div class="v">0026 0234 46 0200987302</div>'
            . '<div class="k">IBAN</div><div class="v">GR5402 6023 400004 60200 987302</div>'
            . '<div class="k">Δικαιούχος</div><div class="v">CLOUDON IKE</div></div>'
            . '<div class="bank"><div class="nm">Alpha Bank</div>'
            . '<div class="k">Αριθμός λογαριασμού</div><div class="v">209/00 200 2001 311</div>'
            . '<div class="k">IBAN</div><div class="v">GR69 0140 2090 2090 0200 2001 311</div>'
            . '<div class="k">Δικαιούχος</div><div class="v">CLOUDON IKE</div></div>'
            . '</div>'
            . '<p style="margin-top:7mm" class="note">Η εξόφληση των προϊόντων άλλων κατασκευαστών '
            . '(Βάση Δεδομένων) γίνεται με αξιόγραφο 30 ημερών από την ημερομηνία τιμολόγησης.</p>');

        /* ── 13. Έντυπο αποδοχής ── */
        $mark['3'] = count($P) + 1;
        $P[] = self::mk('<h1 class="sec"><span class="num">3</span>Παράρτημα B - Έντυπο Αποδοχής Προσφοράς</h1>'
            . '<p>Για την αποδοχή της παραπάνω προσφοράς, παρακαλείσθε να <b>επιστρέψετε υπογεγραμμένη '
            . 'και σφραγισμένη την παρούσα σελίδα.</b></p>'
            . '<div class="fgrid">'
            . '<div class="fld wide"><div class="k">Από</div><div class="v">' . self::e($company) . '</div></div>'
            . '<div class="fld"><div class="k">Υπεύθυνος</div><div class="v">' . self::e($o['attn']) . '</div></div>'
            . '<div class="fld"><div class="k">Τηλέφωνο</div><div class="v">' . self::e($o['cphone']) . '</div></div>'
            . '<div class="fld"><div class="k">Fax</div><div class="v"></div></div>'
            . '<div class="fld"><div class="k">Ημερομηνία</div><div class="v"></div></div>'
            . '</div>'
            . '<div class="card"><div class="ttl">Προς</div>'
            . '<div class="row"><span class="k">Επωνυμία</span><span class="v">CloudOn I.K.E</span></div>'
            . '<div class="row"><span class="k">Τηλέφωνο</span><span class="v">' . self::e($o['tel']) . '</span></div>'
            . '<div class="row"><span class="k">Fax</span><span class="v">' . self::e($o['fax']) . '</span></div>'
            . '<div class="row"><span class="k">Υπόψη</span><span class="v">' . self::e($o['acceptAttn']) . '</span></div>'
            . '<div class="row"><span class="k">Σχετικά με</span><span class="v">Αριθμός Πρωτοκόλλου: '
            . self::e($o['protocol']) . '</span></div></div>'
            . '<div class="box"><div class="h">Παρατηρήσεις</div><div class="v"></div></div>'
            . '<div class="box sg"><div class="h">Υπογραφή — Σφραγίδα Επιχείρησης</div><div class="v"></div></div>',
            );

        /* ── αρίθμηση & περιεχόμενα, τώρα που ξέρουμε πόσες σελίδες βγήκαν ── */
        self::$pages = count($P);
        $mark['ε'] = 4; $mark['1'] = 5; $mark['2'] = $mark['2.1'];
        $toc = [
            ['l1', '', 'Εισαγωγή', $mark['ε']],
            ['l1', '1', 'Soft1 Open Enterprise Edition', $mark['1']],
            ['l1', '2', 'Οικονομική Πρόταση', $mark['2']],
            ['l2', '2.1', 'Προτεινόμενο Λογισμικό', $mark['2.1']],
            ['l2', '2.2', 'Οικονομική Προσφορά — Λογισμικό ' . $prod, $mark['2.2']],
            ['l2', '2.3', 'Υπηρεσίες Υλοποίησης Έργου', $mark['2.3']],
            ['l2', '2.4', 'Συγκεντρωτικοί πίνακες', $mark['2.4']],
            ['l2', '2.5', 'Τρόπος Πληρωμής', $mark['2.5']],
            ['l1', '3', 'Παράρτημα B — Έντυπο Αποδοχής Προσφοράς', $mark['3']],
        ];
        $b3 = '<div class="eyebrow">Το έντυπο</div><h1 class="sec">Περιεχόμενα</h1><div class="toc">';
        foreach ($toc as $r) {
            $b3 .= '<div class="' . $r[0] . '"><span class="tn">' . self::e($r[1]) . '</span>'
                . '<span class="t">' . self::e($r[2]) . '</span>'
                . '<span class="n">' . $r[3] . '</span></div>';
        }
        $H = '';
        foreach ($P as $x => $pg) { $H .= self::pg($pg['b'], $x + 1, $pg['c']); }
        return str_replace('%%TOC%%', $b3 . '</div>', $H);
    }

}
