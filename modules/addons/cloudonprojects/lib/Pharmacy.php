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
    const SETUP_RATE = [15, 25, 25, 30];         // ανά έκδοση, ώρα παραμετροποίησης
    const MYDATA_SETUP = [200, 200, 250, 250];   // ανά έκδοση, στήσιμο myData

    /* ══════════════ προεπιλογές ══════════════ */

    public static function defaultParams()
    {
        return ['I4' => 4, 'J4' => 1, 'K4' => 1, 'L4' => 0.2,
            'I6' => 0, 'J6' => 0, 'K6' => 0, 'L6' => 0,
            'I8' => 3, 'J8' => 0.2, 'K8' => 0.2, 'L8' => 0,
            'I10' => 0, 'J10' => 0, 'K10' => 0, 'L10' => 0, 'I12' => 0];
    }

    /** Τα πεδία της φόρμας, με τη σειρά που εμφανίζονται. */
    public static function paramDefs()
    {
        return [
            ['I4', 'Αριθμός users', 'num'],
            ['J4', 'Υποκαταστήματα', 'num'],
            ['K4', 'Εταιρείες', 'num'],
            ['L4', 'Έκπτωση επί των αδειών CloudOn', 'pct'],
            ['I6', 'Αριθμός Price Checker', 'num'],
            ['J6', 'Courier Module', 'num'],
            ['K6', 'Αρ. POS Connector', 'num'],
            ['L6', 'Διασυνδέσεις POS', 'num'],
            ['I8', 'GB', 'num'],
            ['J8', 'Έκπτωση επί των υπηρεσιών', 'pct'],
            ['K8', 'Έκπτωση επί των αδειών Soft1', 'pct'],
            ['L8', 'Αριθμός RWA', 'num'],
            ['I10', 'Αρ. Η/Υ', 'num'],
            ['J10', 'E-Shop', 'num'],
            ['K10', 'Αρ. εκτυπωτών', 'num'],
            ['L10', 'Αρ. Picking & Packing modules', 'num'],
            ['I12', 'Cash Guard', 'num'],
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

    public static function moduleGroups()
    {
        return [
            ['title' => 'SoftOne', 'items' => [
                ['J16', 'Ομάδες Εταιρειών'], ['J17', 'Sql Connector'], ['J18', 'ECOS']]],
            ['title' => 'CloudOn Modules', 'items' => [
                ['L16', 'Logi Scoup'], ['L17', 'Data Box'], ['L18', 'IQVIA'],
                ['L19', 'RWA module'], ['L20', 'Price Checker'], ['L21', 'Διασύνδεση με E-shop'],
                ['L22', 'Picking and Packing module'], ['L23', 'E-Label'], ['L24', 'Courier Module']]],
            ['title' => 'CloudOn Marketplace', 'items' => [
                ['N16', 'Skroutz'], ['N17', 'einvoice Skroutz'], ['N18', 'Shopflix'],
                ['N19', 'Wolt'], ['N20', 'E-Food'], ['N21', 'Cash Guard'], ['N22', 'Skroutz FBS']]],
        ];
    }

    public static function defaultRates()
    {
        return ['S3' => 200, 'S4' => 200, 'S5' => 200, 'S6' => 200, 'S7' => 475, 'S8' => 50,
            'S9' => 1200, 'S10' => 120, 'S11' => 250, 'S12' => 250, 'S13' => 2500, 'S14' => 50,
            'S15' => 550, 'S16' => 250, 'S17' => 120, 'S19' => 100, 'S20' => 250, 'S21' => 350,
            'S22' => 550, 'S23' => 550, 'S24' => 550, 'S25' => 550, 'S26' => 30, 'S27' => 50,
            'S28' => 30, 'S29' => 250, 'S30' => 150, 'S31' => 60, 'S32' => 500, 'S33' => 25,
            'S34' => 120, 'S35' => 850,
            'T13' => 0.2, 'T15' => 0.3, 'T22' => 0.3, 'T23' => 0.3, 'T24' => 0.3, 'T25' => 0.3,
            'T35' => 0.3];
    }

    /** Ο τιμοκατάλογος: [κελί τιμής, περιγραφή, κελί ετήσιας αναπροσαρμογής]. */
    public static function rateRows()
    {
        return [
            ['S3', 'PharmacyOne B User', null], ['S4', 'PharmacyOne G User', null],
            ['S5', 'PharmacyOne Plus B User', null], ['S6', 'PharmacyOne Plus G User', null],
            ['S7', 'Αξία Ομάδας Εταιρειών', null], ['S8', 'Κόστος ανά GB', null],
            ['S9', 'Βασικό Π.Σ. S1', null], ['S10', 'My Data Express', null],
            ['S11', 'My Data Business', null], ['S12', 'Sql Connector', null],
            ['S13', 'Διασύνδεση με E-shop', 'T13'], ['S14', 'Price Checker', null],
            ['S15', 'Courier Module', 'T15'], ['S16', 'Picking and Packing module', null],
            ['S17', 'DataBox', null], ['S19', 'Logi Scoup', null], ['S20', 'RWA module', null],
            ['S21', 'E-Label', null], ['S22', 'Skroutz', 'T22'], ['S23', 'Shopflix', 'T23'],
            ['S24', 'Wolt', 'T24'], ['S25', 'E-Food', 'T25'], ['S26', 'Printer', null],
            ['S27', 'Αρ. POS Connector', null], ['S28', 'PharmacyOne Conf Per User', null],
            ['S29', 'einvoice Skroutz', null], ['S30', 'IQVIA', null],
            ['S31', 'Παραμετροποίηση POS', null], ['S32', 'Παραμετροποίηση ECOS', null],
            ['S33', 'Pc', null], ['S34', 'Cash Guard', null], ['S35', 'Skroutz FBS', 'T35'],
        ];
    }

    public static function defaultEditions()
    {
        return [
            ['key' => 'B',  'name' => 'PharmacyOne Β',       'soft1' => 'Soft1 Express',      'cat' => 'Β', 'price' => 700,  'extraUser' => 120],
            ['key' => 'C',  'name' => 'PharmacyOne ΒΓ',      'soft1' => 'Soft1 Express Plus', 'cat' => 'Γ', 'price' => 800,  'extraUser' => 120],
            ['key' => 'D',  'name' => 'PharmacyOne Plus Β',  'soft1' => 'Soft1 Business',     'cat' => 'Β', 'price' => 1200, 'extraUser' => 165],
            ['key' => 'E',  'name' => 'PharmacyOne Plus ΒΓ', 'soft1' => 'Soft1 Business',     'cat' => 'Γ', 'price' => 1400, 'extraUser' => 165],
        ];
    }

    /** Λειτουργικότητα ανά έκδοση (A5:E24 του φύλλου). */
    public static function features()
    {
        return [
            ['Χρώμα & Μέγεθος', [1, 1, 1, 1]],
            ['Αγορές, Διαχείριση Προμηθευτών, Πιστωτών', [1, 1, 1, 1]],
            ['Γεν. Λογιστική', [0, 1, 0, 1]],
            ['Έσοδα – Έξοδα', [1, 0, 1, 0]],
            ['Χρημ. Συναλλαγές', [1, 1, 1, 1]],
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

    private static function marketplaces($yn, $r)
    {
        return self::y($yn, 'N16') * $r['S22'] + self::y($yn, 'N18') * $r['S23']
             + self::y($yn, 'N19') * $r['S24'] + self::y($yn, 'N20') * $r['S25'];
    }

    private static function fmtPct($v) { return round($v * 1000) / 10 . '%'; }
    private static function fmtEur($v)
    {
        return number_format((float) $v, 2, ',', '.') . ' €';
    }
    private static function fmtHrs($v) { return number_format($v, 1, ',', '.'); }

    /**
     * Ετήσιες γραμμές (κόστος αδειών & ετήσιων υπηρεσιών).
     * `b` = κάδος προσφοράς. `$i` = δείκτης έκδοσης 0..3.
     */
    private static function annualRows()
    {
        return [
            [29, 5, 'Soft1 Υπηρεσίες Ετήσιας Τηλεφωνικής Υποστήριξης',
                function ($p, $r, $e, $i, $yn) { return '× ' . ($p['I4'] * 2) . ' ώρες · έκπτ. ' . self::fmtPct($p['J8']); },
                function ($p, $r, $e, $i, $yn) { return (100 * $p['K4'] * $p['J4']) + ($p['I4'] * 2 * 20) * (1 - $p['J8']); }],
            [30, 2, 'CloudOn Module Συνταγογράφησης (PharmacyOne)',
                function ($p, $r) { return '× ' . $p['I4'] . ' τεμ. · τιμή ' . self::fmtEur($r['S3']) . ' · έκπτ. ' . self::fmtPct($p['L4']); },
                function ($p, $r) { return ($r['S3'] * $p['I4']) * (1 - $p['L4']); }],
            [31, 1, 'Soft1 Cloud extra user',
                function ($p, $r, $e) { return '× ' . max($p['I4'] - 1, 0) . ' τεμ. · τιμή ' . self::fmtEur($e['extraUser']) . ' · έκπτ. ' . self::fmtPct($p['K8']); },
                function ($p, $r, $e) { return $e['extraUser'] * ($p['I4'] - 1) * (1 - $p['K8']); }],
            [32, 1, 'Soft1 — βασικός συνδυασμός (περιλαμβάνει 1 χρήστη)',
                function ($p, $r, $e) { return '× ' . $p['K4'] . ' εταιρεία(ες) · τιμή ' . self::fmtEur($e['price']) . ' · έκπτ. ' . self::fmtPct($p['K8']); },
                function ($p, $r, $e) { return $e['price'] * $p['K4'] * (1 - $p['K8']); }],
            [33, 1, 'Soft1 OpEn myData Live',
                function ($p, $r, $e, $i) { return '× ' . $p['K4'] . ' τεμ. · τιμή ' . self::fmtEur($i < 2 ? $r['S10'] : $r['S11']); },
                function ($p, $r, $e, $i) { return ($i < 2 ? $r['S10'] : $r['S11']) * $p['K4']; }],
            [34, 1, 'Soft1 1GB Extra Cloud Disk',
                function ($p, $r) { return '× ' . $p['I8'] . ' GB · τιμή ' . self::fmtEur($r['S8']); },
                function ($p, $r) { return $r['S8'] * $p['I8']; }],
            [35, 1, 'Soft1 Ομάδες Εταιρειών',
                function ($p, $r) { return '× 1 τεμ. · τιμή ' . self::fmtEur($r['S7']) . ' · έκπτ. ' . self::fmtPct($p['K8']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'J16') * $r['S7'] * (1 - $p['K8']); }],
            [36, 1, 'Soft1 Sql Connector',
                function ($p, $r) { return '× ' . $p['K4'] . ' τεμ. · τιμή ' . self::fmtEur($r['S12']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'J17') * $r['S12'] * $p['K4'] * (1 - $p['K8']); }],
            [37, 5, 'Ετήσια υπηρεσία υποστήριξης διασύνδεσης με E-shop',
                function ($p, $r) { return '× ' . $p['J10'] . ' · ' . self::fmtPct($r['T13']) . ' επί ' . self::fmtEur($r['S13']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L21') * $r['S13'] * $p['J10'] * (1 - $p['J8']) * $r['T13']; }],
            /* Το B38 του φύλλου χρησιμοποιεί (1-L4), τα C/D/E (1-K8). Διατηρείται. */
            [38, 2, 'CloudOn Price Checker — ετήσια άδεια',
                function ($p, $r) { return '× ' . $p['I6'] . ' τεμ. · τιμή ' . self::fmtEur($r['S14']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L20') * $r['S14'] * $p['I6'] * (1 - ($i === 0 ? $p['L4'] : $p['K8'])); }],
            [39, 5, 'Ετήσια παροχή υπηρεσιών για Courier Module',
                function ($p, $r) { return '× ' . $p['J6'] . ' · ' . self::fmtPct($r['T15']) . ' επί ' . self::fmtEur($r['S15']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L24') * $r['S15'] * $p['J6'] * (1 - $p['J8']) * $r['T15']; }],
            [40, 5, 'Ετήσιο κόστος ενημέρωσης δεδομένων (OTC & παραφάρμακα)',
                function ($p, $r) { return 'Data Box / Logi Scoup · έκπτ. ' . self::fmtPct($p['L4']); },
                function ($p, $r, $e, $i, $yn) { return (self::y($yn, 'L17') * $r['S17'] + self::y($yn, 'L16') * $r['S19']) * (1 - $p['L4']); }],
            [41, 2, 'CloudOn RWA Module — ετήσια άδεια',
                function ($p, $r) { return '× ' . $p['L8'] . ' τεμ. · τιμή ' . self::fmtEur($r['S20']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L19') * $r['S20'] * $p['L8'] * (1 - $p['L4']); }],
            [42, 2, 'CloudOn E-Label — ετήσια άδεια',
                function ($p, $r) { return '× ' . ($p['K4'] * $p['J4']) . ' τεμ. · τιμή ' . self::fmtEur($r['S21']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L23') * $r['S21'] * $p['K4'] * $p['J4'] * (1 - $p['L4']); }],
            [43, 5, 'Ετήσια υπηρεσία υποστήριξης Marketplaces',
                function ($p, $r, $e, $i, $yn) { return self::fmtPct($r['T22']) . ' επί ' . self::fmtEur(self::marketplaces($yn, $r)); },
                function ($p, $r, $e, $i, $yn) { return self::marketplaces($yn, $r) * $r['T22'] * (1 - $p['L4']); }],
            [44, 1, 'Soft1 POS Connector',
                function ($p, $r) { return '× ' . $p['K6'] . ' τεμ. · τιμή ' . self::fmtEur($r['S27']); },
                function ($p, $r) { return $r['S27'] * $p['K6']; }],
            [45, 2, 'CloudOn einvoice Skroutz — ετήσια άδεια',
                function ($p, $r) { return '× ' . $p['K4'] . ' τεμ. · τιμή ' . self::fmtEur($r['S29']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'N17') * $r['S29'] * $p['K4'] * (1 - $p['L4']); }],
            [46, 2, 'CloudOn IQVIA — ετήσια άδεια',
                function ($p, $r) { return '× ' . $p['K4'] . ' τεμ. · τιμή ' . self::fmtEur($r['S30']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L18') * $r['S30'] * $p['K4'] * (1 - $p['L4']); }],
            [47, 1, 'CloudOn Picking / Packing module',
                function ($p, $r) { return '× ' . $p['L10'] . ' τεμ. · τιμή ' . self::fmtEur($r['S16']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L22') * $r['S16'] * $p['L10'] * (1 - $p['L4']); }],
            [48, 2, 'CloudOn Cash Guard — ετήσια άδεια',
                function ($p, $r) { return '× ' . $p['I12'] . ' τεμ. · τιμή ' . self::fmtEur($r['S34']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'N21') * $r['S34'] * $p['I12'] * (1 - $p['L4']); }],
            [49, 5, 'Ετήσια υπηρεσία υποστήριξης Skroutz FBS',
                function ($p, $r) { return self::fmtPct($r['T35']) . ' επί ' . self::fmtEur($r['S35']); },
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'N22') * $r['S35'] * $p['K4'] * $r['T35'] * (1 - $p['L4']); }],
        ];
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
                function ($p, $r) { return ($p['I4'] * $r['S28']) * (1 - $p['J8']) + 60 * (1 - $p['L4']); }],
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
            [37, 4, 0, 'CloudOn modules Marketplaces — διαχείριση παραγγελιών',
                function ($p, $r) { return 'Skroutz / Shopflix / Wolt / E-Food'; },
                function ($p, $r, $e, $i, $yn) { return self::marketplaces($yn, $r) * (1 - $p['L4']); }],
            [38, 3, 0, 'Τεχνική υποστήριξη & παραμετροποίηση εξοπλισμού',
                function ($p, $r) { return $p['I10'] . ' Η/Υ · ' . $p['K10'] . ' εκτυπωτές'; },
                function ($p, $r) { return (($r['S33'] * $p['I10']) + ($r['S26'] * $p['K10'])) * (1 - $p['J8']); }],
            [39, 3, 0, 'Παραμετροποίηση Price Checker', null,
                function ($p, $r, $e, $i, $yn) { return self::y($yn, 'L20') * $r['S14'] * 2 * (1 - $p['L4']); }],
            [40, 3, 0, 'Παραμετροποίηση POS',
                function ($p, $r) { return '× ' . $p['L6'] . ' διασυνδέσεις · τιμή ' . self::fmtEur($r['S31']); },
                function ($p, $r) { return $p['L6'] * $r['S31']; }],
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
        $out['yn'] = [];
        foreach (self::defaultModules() as $k => $v) {
            $out['yn'][$k] = !empty($cfg['yn'][$k]) ? 1 : 0;
        }
        $out['r'] = [];
        foreach (self::defaultRates() as $k => $v) {
            $out['r'][$k] = isset($cfg['r'][$k]) && is_numeric($cfg['r'][$k]) ? (float) $cfg['r'][$k] : $v;
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
            'tel' => (string) ($o['tel'] ?? '+30 210 72 22 560'),
            'fax' => (string) ($o['fax'] ?? '+30 210 63 91 532'),
            'client' => (string) ($o['client'] ?? ''),
        ];
        return $out;
    }

    /**
     * @return array{annual:array, oneoff:array, totals:array}
     *   annual/oneoff: πίνακας [γραμμή][έκδοση] με ποσά
     */
    public static function calc($cfg)
    {
        $c = self::normalize($cfg);
        $p = $c['p']; $r = $c['r']; $yn = $c['yn'];
        $annual = [];
        foreach (self::annualRows() as $rw) {
            $line = [];
            foreach ($c['ed'] as $i => $e) { $line[] = (float) call_user_func($rw[4], $p, $r, $e, $i, $yn); }
            $annual[] = $line;
        }
        $oneoff = [];
        foreach (self::oneoffRows() as $rw) {
            $line = [];
            foreach ($c['ed'] as $i => $e) { $line[] = (float) call_user_func($rw[5], $p, $r, $e, $i, $yn); }
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
        $c = $res['cfg']; $p = $c['p']; $r = $c['r']; $yn = $c['yn']; $e = $c['ed'][$i];
        $out = [1 => [], 2 => [], 3 => [], 4 => [], 5 => []];
        foreach (self::annualRows() as $ri => $rw) {
            $v = $res['annual'][$ri][$i];
            if (abs($v) < 0.005) { continue; }
            $qty = $rw[3] ? call_user_func($rw[3], $p, $r, $e, $i, $yn) : '';
            $out[$rw[1]][] = ['k' => $rw[0], 'lab' => $rw[2], 'qty' => $qty, 'amount' => $v];
        }
        foreach (self::oneoffRows() as $ri => $rw) {
            $v = $res['oneoff'][$ri][$i];
            if (abs($v) < 0.005) { continue; }
            $qty = $rw[4] ? call_user_func($rw[4], $p, $r, $e, $i, $yn)
                : ($rw[2] ? '× ' . self::fmtHrs($v / self::HOUR_RATE) . ' ώρες · τιμή ' . self::fmtEur(self::HOUR_RATE) : '');
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
                return ['src' => self::LOGO_DIR . $f, 'alt' => 'Entersoft One', 'tag' => '', 'w' => '56mm'];
            }
        }
        return ['src' => self::LOGO_DIR . 'softone.png', 'alt' => 'SoftOne',
            'tag' => 'more than software', 'w' => '44mm'];
    }

    private static function head($both = false)
    {
        $h = '<div class="lg"><img class="c" src="' . self::LOGO_CLOUDON . '" alt="CloudOn">';
        if ($both) {
            $lg = self::partnerLogo();
            $h .= '<div class="s"><img src="' . $lg['src'] . '" alt="' . self::e($lg['alt'])
                . '" style="width:' . $lg['w'] . '">'
                . ($lg['tag'] ? '<span>' . self::e($lg['tag']) . '</span>' : '') . '</div>';
        }
        return $h . '</div>';
    }

    /** Μία σελίδα Α4 με τον αριθμό της κάτω δεξιά, όπως στο έντυπο. */
    private static function pg($body, $n, $cls = '', $both = false)
    {
        return '<div class="page' . ($cls ? ' ' . $cls : '') . '">' . self::head($both)
            . $body . '<div class="pnum">' . $n . '</div></div>';
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
        foreach ($lines as $l) { $total += $l['amount']; }
        $h = '<table class="p"><thead><tr><th>' . $bk[0] . '</th><th class="n">'
            . self::e($bk[2]) . '</th></tr></thead><tbody>';
        $k = 0;
        foreach ($rows as $l) {
            $sub = !empty($l['sub']);
            $h .= '<tr class="' . ($k++ % 2 ? 'alt' : '') . ($sub ? ' sub' : '') . '">'
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
*{box-sizing:border-box}
body{margin:0;padding:16px;background:#eef1f5;color:#1a1a1a;
  font:11pt/1.45 Calibri,Carlito,"Segoe UI",system-ui,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.page{position:relative;width:210mm;min-height:297mm;margin:0 auto 16px;padding:16mm 18mm 16mm;
  background:#fff;box-shadow:0 1px 8px rgba(15,32,56,.2)}
.pnum{position:absolute;right:18mm;bottom:10mm;font-size:10pt;color:#333}
.lg{display:flex;align-items:flex-start;justify-content:space-between;gap:10mm;margin-bottom:9mm}
.lg img.c{width:46mm;height:auto}
.lg .s{text-align:right}
.lg .s img{width:44mm;height:auto;display:block}
.lg .s span{display:block;font-size:8pt;letter-spacing:.2em;color:#5a6672;margin-top:1.5mm}
h1.sec{color:#2E74B5;font-size:16pt;font-weight:700;margin:0 0 5mm}
h2.sub{color:#2E74B5;font-size:12.5pt;font-weight:700;margin:7mm 0 3mm}
h2.sub:first-of-type{margin-top:0}
h3.sub3{color:#2E74B5;font-size:11.5pt;font-weight:700;margin:5mm 0 2.5mm}
p{margin:0 0 3.4mm;text-align:justify}
ul{margin:0 0 3.4mm;padding-left:7mm}
li{margin-bottom:1.4mm}
ul.tick{list-style:none;padding-left:3mm}
ul.tick>li::before{content:"\2713";color:#2E74B5;font-weight:700;display:inline-block;width:6mm;margin-left:-6mm}
.mut{color:#5a6672}
b.br{color:#2E74B5}
/* ── εξώφυλλο ── */
.cover .cbox{display:flex;gap:5mm;align-items:stretch;margin-top:4mm}
.cover .bl{flex:1 1 58%;background:#1F86D0;color:#fff;padding:9mm 7mm;text-align:center}
.cover .bl .t{font-size:15pt;font-weight:700;line-height:1.35}
.cover .bl .cl{margin-top:7mm;font-size:14pt;font-weight:700}
.cover .br2{flex:1 1 38%;background:#EE1C25;color:#fff;padding:9mm 6mm;
  display:flex;flex-direction:column;justify-content:center;text-align:center;font-size:11pt;line-height:1.6}
/* ── επιστολή ── */
.lh{font-size:11pt;line-height:1.9;margin-bottom:6mm}
.lh .r{text-align:right;margin-top:5mm}
.sig{margin-top:8mm}
.sig .nm{margin-top:12mm;font-weight:600}
/* ── περιεχόμενα ── */
.toc{margin-top:3mm}
.toc>div{display:flex;align-items:baseline;gap:2mm;margin-bottom:3mm;font-size:11pt}
.toc .d{flex:1 1 auto;border-bottom:1.6px dotted #9aa4ad;transform:translateY(-3px)}
.toc .n{font-variant-numeric:tabular-nums;white-space:nowrap}
.toc .l1{font-weight:700;color:#2E74B5}
.toc .l2{padding-left:9mm;font-size:10pt;color:#3a4753}
.toc .l2 .t{text-transform:uppercase;font-size:9pt;letter-spacing:.02em}
/* ── πίνακας λογισμικού ── */
table.m{width:100%;border-collapse:collapse;font-size:10pt;table-layout:fixed}
table.m th{background:#D9D9D9;color:#2E74B5;font-weight:700;font-size:10.5pt;text-align:left;padding:2.4mm 3mm}
table.m th.n,table.m td.n{width:40mm;text-align:center}
table.m td{padding:2.2mm 3mm;border-bottom:2px solid #fff;word-wrap:break-word}
table.m tr.alt td{background:#F2F2F2}
table.m tr.grp td{background:#D9D9D9;color:#2E74B5;font-weight:700}
.dot{display:inline-block;width:2.8mm;height:2.8mm;border-radius:50%;background:#2E9BD6}
/* ── πίνακες κόστους ── */
table.p{width:100%;border-collapse:collapse;font-size:10.5pt;margin:0 0 7mm;table-layout:fixed}
table.p th{background:#DEEBF7;color:#2E74B5;font-weight:700;text-align:left;padding:2.6mm 3mm}
table.p th.n,table.p td.n{width:37mm;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
table.p td{padding:2.4mm 3mm;border-bottom:2px solid #fff;word-wrap:break-word}
table.p tr.alt td{background:#F2F2F2}
table.p tr.sub td.d{padding-left:9mm;position:relative}
table.p tr.sub td.d::before{content:"\2713";color:#2E74B5;font-weight:700;position:absolute;left:3mm}
table.p tr.tot td{background:#BDD7EE;color:#1F4E79;font-weight:700}
table.p .q{display:block;font-size:8.5pt;color:#5a6672;margin-top:.6mm}
table.p td.nt{font-size:8.5pt;font-weight:700;letter-spacing:-.01em}
.note{font-size:9.5pt;color:#5a6672;margin-top:-4mm}
/* ── συγκεντρωτικός ── */
table.s{width:100%;border-collapse:collapse;font-size:11pt;table-layout:fixed}
table.s td{background:#DEEBF7;padding:3.2mm;border-bottom:2.5px solid #fff}
table.s td.l{font-style:italic;font-weight:700;color:#1F4E79}
table.s td.n{width:44mm;text-align:right;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap}
table.s tr.plain td.l{font-style:normal;font-weight:700;color:#1a1a1a}
table.s tr.disc td{color:#C00000;font-style:italic;font-weight:700}
table.s tr.head td{height:18mm;text-align:center}
table.s tr.head img{height:13mm}
.terms{margin-top:6mm;font-size:10.5pt}
/* ── τράπεζες ── */
table.b{width:100%;border-collapse:collapse;font-size:10.5pt;margin-top:3mm}
table.b td{border-bottom:1px solid #cfd7de;padding:3mm;vertical-align:top;line-height:1.75}
table.b td.k{width:42mm;font-weight:700;color:#2E74B5;font-size:11.5pt}
table.b b{color:#1a1a1a}
table.b .on{color:#2E74B5;font-weight:700}
/* ── έντυπο αποδοχής ── */
table.f{width:100%;border-collapse:collapse;font-size:11pt;margin-bottom:7mm;table-layout:fixed}
table.f td{border:1px solid #b9c2cb;padding:2.8mm 3mm;height:10mm}
table.f td.k{width:34mm}
table.f td.on{color:#2E74B5;font-weight:700}
.box{border:1px solid #b9c2cb;margin-bottom:7mm}
.box .h{text-align:center;padding:2.8mm;border-bottom:1px solid #b9c2cb}
.box .v{height:28mm}
.box.sg .v{height:44mm}
@page{size:A4;margin:14mm 16mm}
@media print{
  body{background:#fff;padding:0}
  .page{width:auto;min-height:245mm;margin:0;padding:0;box-shadow:none;
    break-after:page;page-break-after:always}
  .page:last-child{break-after:auto;page-break-after:auto}
  .pnum{right:0;bottom:0}
  table.m tr,table.p tr,table.s tr,table.f tr{break-inside:avoid;page-break-inside:avoid}
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
        $H = '';
        $n = 0;

        /* ── 1. Εξώφυλλο ── */
        $H .= self::pg('<div class="cbox">'
            . '<div class="bl"><div class="t">Οικονομική Πρόταση για την Εγκατάσταση και '
            . 'Λειτουργία του Πληροφοριακού Συστήματος<br>' . self::e($prod) . '</div>'
            . '<div class="cl">“ ' . self::e(mb_strtoupper($company, 'UTF-8')) . ' ”</div></div>'
            . '<div class="br2">' . self::e($date) . '<br>Αρ. Πρωτ/λου: ' . self::e($o['protocol']) . '</div>'
            . '</div>', ++$n, 'cover', true);

        /* ── 2. Συνοδευτική επιστολή ── */
        $H .= self::pg('<div class="lh">'
            . 'Προς: “ ' . self::e($company) . ' ”<br>'
            . 'Υπόψη: ' . self::e($o['attn'] ?: '……………………') . '<br>'
            . 'Αριθμός Πρωτοκόλλου: ' . self::e($o['protocol'])
            . '<div class="r">' . self::e($o['city']) . ', ' . self::e($date) . '</div></div>'
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
            . '<div class="sig">Με τιμή,<div class="nm">' . self::e($o['seller']) . '</div></div>', ++$n);

        /* ── 3. Περιεχόμενα ── */
        $toc = [
            ['l1', 'Εισαγωγή', 4], ['l1', '1&nbsp;&nbsp;&nbsp;Soft1 Open Enterprise Edition', 5],
            ['l1', '2&nbsp;&nbsp;&nbsp;Οικονομική Πρόταση', 6],
            ['l2', '2.1&nbsp;&nbsp;&nbsp;Προτεινόμενο Λογισμικό', 6],
            ['l2', '2.2&nbsp;&nbsp;&nbsp;Οικονομική Προσφορά - Λογισμικό ' . self::e($prod), 8],
            ['l2', '2.3&nbsp;&nbsp;&nbsp;Υπηρεσίες Υλοποίησης Έργου', 10],
            ['l2', '2.4&nbsp;&nbsp;&nbsp;Συγκεντρωτικοί πίνακες', 11],
            ['l2', '2.5&nbsp;&nbsp;&nbsp;Τρόπος Πληρωμής', 12],
            ['l1', '3&nbsp;&nbsp;&nbsp;Παράρτημα B - Έντυπο Αποδοχής Προσφοράς', 13],
        ];
        $b3 = '<h1 class="sec">ΠΕΡΙΕΧΟΜΕΝΑ</h1><div class="toc">';
        foreach ($toc as $r) {
            $b3 .= '<div class="' . $r[0] . '"><span class="t">' . $r[1] . '</span>'
                . '<span class="d"></span><span class="n">' . $r[2] . '</span></div>';
        }
        $H .= self::pg($b3 . '</div>', ++$n);

        /* ── 4. Εισαγωγή ── */
        $H .= self::pg('<h1 class="sec">Εισαγωγή</h1>'
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
            . 'περιβάλλον.</p>', ++$n);

        /* ── 5. Soft1 Open Enterprise Edition ── */
        $H .= self::pg('<h1 class="sec">1&nbsp;&nbsp;&nbsp;Soft1 Open Enterprise Edition</h1>'
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
            . 'μιας σύγχρονης μηχανογράφησης με ουσιαστικά οφέλη και πλεονεκτήματα για την επιχείρηση.</p>',
            ++$n);

        /* ── 6-7. Προτεινόμενο λογισμικό + ο πίνακας των ενοτήτων ── */
        $tbl = self::soft1Table();
        $split = 18;                     // ίδιο σημείο κοπής με το έντυπο
        $mk = function ($from, $to) use ($tbl, $i) {
            $h = '<table class="m"><thead><tr><th>Soft1 ERP</th><th class="n">Modules included</th></tr>'
                . '</thead><tbody>';
            $k = 0;
            for ($x = $from; $x < $to && $x < count($tbl); $x++) {
                $r = $tbl[$x];
                if ($r[0] === 'g') {
                    $h .= '<tr class="grp"><td colspan="2">' . self::e($r[1]) . '</td></tr>';
                    $k = 0;
                    continue;
                }
                $on = !empty($r[2][$i]);
                $lab = !empty($r[3]) ? $r[1] : self::e($r[1]);   // 4ο πεδίο = ήδη HTML
                $h .= '<tr class="' . ($k++ % 2 ? 'alt' : '') . '"><td>' . $lab . '</td>'
                    . '<td class="n">' . ($on ? '<span class="dot"></span>' : '') . '</td></tr>';
            }
            return $h . '</tbody></table>';
        };
        $H .= self::pg('<h1 class="sec">2&nbsp;&nbsp;&nbsp;Οικονομική Πρόταση</h1>'
            . '<h2 class="sub">2.1&nbsp;&nbsp;&nbsp;Προτεινόμενο Λογισμικό</h2>'
            . '<p>Το λογισμικό που προτείνεται με την παρούσα πρόταση για εγκατάσταση και λειτουργία στην '
            . self::e($q) . ' είναι το <b>' . self::e($full) . '</b> που περιλαμβάνεται στην έκδοση '
            . '<b>Soft1 Open Enterprise Edition</b>.</p>'
            . '<p>Αναλυτικότερα, προτείνεται ο συνδυασμός <b class="br">' . self::e($prod) . '</b> που '
            . 'διατίθεται εναλλακτικά σύμφωνα με τους παρακάτω τρόπους διάθεσης:</p>'
            . '<ul><li>Ορισμένου Χρόνου / cloud - Συνδρομητικό Μοντέλο διάθεσης</li></ul>'
            . '<p>και περιλαμβάνει τη λειτουργικότητα των ενοτήτων Λογισμικού που περιγράφονται αναλυτικά '
            . 'παρακάτω:</p>' . $mk(0, $split), ++$n);
        $H .= self::pg($mk($split, count($tbl)), ++$n);

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

        $H .= self::pg('<h2 class="sub">2.2&nbsp;&nbsp;&nbsp;Οικονομική Προσφορά - Λογισμικό '
            . self::e($prod) . '</h2>'
            . '<p><b>Τρόπος Διάθεσης Λογισμικού:</b> Ορισμένου Χρόνου / Cloud - Συνδρομητικό Μοντέλο διάθεσης</p>'
            . self::bucketTable($bk[1], $b1)
            . self::bucketTable($bk[2], $lines[2]), ++$n);

        $H .= self::pg(self::bucketTable($bk[3], $lines[3])
            . self::bucketTable($bk[4], $lines[4]), ++$n);

        $incRow = [['lab' => 'Νομοθετικές ενημερώσεις, Νέες εκδόσεις Λογισμικού, online ηλεκτρονική '
            . 'τεκμηρίωση', 'note' => 'ΠΕΡΙΛΑΜΒΑΝΟΝΤΑΙ']];
        $H .= self::pg(self::bucketTable($bk[5], $lines[5], $incRow)
            . '<h2 class="sub">2.3&nbsp;&nbsp;&nbsp;Υπηρεσίες Υλοποίησης Έργου</h2>'
            . '<p>Το κόστος των υπηρεσιών υλοποίησης του έργου, όπως αναλύεται λεπτομερώς στην παράγραφο '
            . '<b>2.2</b> «Οικονομική Προσφορά - Υπηρεσίες Παραμετροποίησης», ανέρχεται σε <b>'
            . self::fmtEur($bt[3]) . '</b> και αντιστοιχεί σε <b>' . self::fmtHrs($bt[3] / self::HOUR_RATE)
            . '</b> ώρες εργασίας.</p>'
            . '<p>Ο σχεδιασμός και η υλοποίηση του έργου θα βασιστεί στις λειτουργικές / τεχνικές '
            . 'δυνατότητες και προδιαγραφές των εφαρμογών Soft1.</p>'
            . '<p class="mut">Οι ώρες υπηρεσιών υπολογίζονται με χρέωση ' . self::fmtEur(self::HOUR_RATE)
            . ' ανά ώρα. Στις παραπάνω αξίες έχουν ήδη ενσωματωθεί οι εκπτώσεις: αδειών CloudOn '
            . self::fmtPct($p['L4']) . ', αδειών Soft1 ' . self::fmtPct($p['K8']) . ', υπηρεσιών '
            . self::fmtPct($p['J8']) . '.</p>', ++$n);

        /* ── 11. Συγκεντρωτικοί πίνακες ── */
        $sum = '<h2 class="sub">2.4&nbsp;&nbsp;&nbsp;Συγκεντρωτικοί πίνακες</h2>'
            . '<h3 class="sub3">2.4.1&nbsp;&nbsp;&nbsp;Λογισμικό &amp; Υπηρεσίες</h3>'
            . '<table class="s"><tbody><tr class="head"><td></td>'
            . '<td class="n"><img src="' . self::LOGO_CLOUDON . '" alt=""></td></tr>';
        foreach ([1, 3, 5, 2, 4] as $b) {
            $sum .= '<tr><td class="l">' . self::e($bk[$b][1]) . '</td><td class="n">'
                . self::fmtEur($bt[$b]) . '</td></tr>';
        }
        $sum .= '<tr class="plain"><td class="l">Τελική Αξία Έργου Προ-έκπτωσης</td><td class="n">'
            . self::fmtEur($pre) . '</td></tr>'
            . ($disc ? '<tr class="disc"><td class="l">Έκπτωση επί της Αξίας του Λογισμικού και των '
                . 'Υπηρεσιών</td><td class="n">-' . self::fmtEur($disc) . '</td></tr>' : '')
            . '<tr class="plain"><td class="l">Τελική Αξία Έργου</td><td class="n">'
            . self::fmtEur($fin) . '</td></tr></tbody></table>'
            . '<div class="terms"><b>Αξίες:</b> Όλες οι παραπάνω αξίες επιβαρύνονται με ΦΠΑ '
            . (float) $o['vat'] . '%.<br>'
            . '<b>Ισχύς προσφοράς:</b> Η προσφορά ισχύει για ' . (int) $o['validDays'] . ' ημέρες.</div>'
            . '<h3 class="sub3">2.4.2&nbsp;&nbsp;&nbsp;Εφάπαξ και επαναλαμβανόμενο κόστος</h3>'
            . '<table class="s"><tbody>'
            . '<tr><td class="l">Εφάπαξ κόστος έναρξης (παραμετροποίηση &amp; διασυνδέσεις)</td>'
            . '<td class="n">' . self::fmtEur($bt[3] + $bt[4]) . '</td></tr>'
            . '<tr><td class="l">Ετήσιο επαναλαμβανόμενο κόστος (συνδρομές &amp; υποστήριξη)</td>'
            . '<td class="n">' . self::fmtEur($bt[1] + $bt[2] + $bt[5]) . '</td></tr>'
            . '<tr class="plain"><td class="l">Μηνιαίο κόστος ανά χρήστη</td><td class="n">'
            . self::fmtEur($t['monthlyPerUser']) . '</td></tr></tbody></table>';
        $H .= self::pg($sum, ++$n);

        /* ── 12. Τρόπος πληρωμής ── */
        $rest = 100 - (float) $o['prepay'];
        $H .= self::pg('<h2 class="sub">2.5&nbsp;&nbsp;&nbsp;Τρόπος Πληρωμής</h2>'
            . '<p>Η εξόφληση της αξίας των εφαρμογών λογισμικού <b>' . self::e($prod) . '</b> και του '
            . 'κόστους των υπηρεσιών υλοποίησης γίνεται ως εξής:</p>'
            . '<ul><li>' . (float) $o['prepay'] . ' % της συνολικής αξίας του έργου μετρητοίς με την '
            . 'ανάθεση του έργου.</li>'
            . '<li>' . $rest . ' % του συνολικού ποσού με την παράδοση του έργου.</li></ul>'
            . '<p><b>Τραπεζικοί λογαριασμοί CLOUDON IKE</b></p>'
            . '<table class="b"><tbody>'
            . '<tr><td class="k">EUROBANK</td><td>'
            . 'Αριθμός λογαριασμού: 0026 0234 46 0200987302<br>'
            . 'IBAN: GR5402 6023 400004 60200 987302<br>'
            . 'ΔΙΚΑΙΟΥΧΟΣ: <span class="on">CLOUDON IKE</span></td></tr>'
            . '<tr><td class="k">ALPHA BANK</td><td>'
            . 'Αριθμός λογαριασμού: 209/00 200 2001 311<br>'
            . 'IBAN: GR69 0140 2090 2090 0200 2001 311<br>'
            . 'ΔΙΚΑΙΟΥΧΟΣ: <span class="on">CLOUDON IKE</span></td></tr>'
            . '</tbody></table>'
            . '<p style="margin-top:6mm">Η εξόφληση των προϊόντων άλλων κατασκευαστών (Βάση Δεδομένων) '
            . 'γίνεται με αξιόγραφο 30 ημερών από την ημερομηνία τιμολόγησης.</p>', ++$n);

        /* ── 13. Έντυπο αποδοχής ── */
        $H .= self::pg('<h1 class="sec">3&nbsp;&nbsp;&nbsp;Παράρτημα B - Έντυπο Αποδοχής Προσφοράς</h1>'
            . '<p>Για την αποδοχή της παραπάνω προσφοράς, παρακαλείσθε να <b>επιστρέψετε υπογεγραμμένη '
            . 'και σφραγισμένη την παρούσα σελίδα.</b></p>'
            . '<table class="f"><tbody>'
            . '<tr><td class="k">Από:</td><td>' . self::e($company) . '</td></tr>'
            . '<tr><td class="k">Υπεύθυνος:</td><td>' . self::e($o['attn']) . '</td></tr>'
            . '<tr><td class="k">Τηλέφωνο:</td><td>' . self::e($o['cphone']) . '</td></tr>'
            . '<tr><td class="k">Fax:</td><td></td></tr>'
            . '<tr><td class="k">Ημερομηνία:</td><td></td></tr>'
            . '</tbody></table>'
            . '<table class="f"><tbody>'
            . '<tr><td class="k">Προς:</td><td class="on">CloudOn I.K.E</td></tr>'
            . '<tr><td class="k">Τηλέφωνο:</td><td>' . self::e($o['tel']) . '</td></tr>'
            . '<tr><td class="k">Fax:</td><td>' . self::e($o['fax']) . '</td></tr>'
            . '<tr><td class="k">Υπόψη:</td><td>' . self::e($o['acceptAttn']) . '</td></tr>'
            . '</tbody></table>'
            . '<table class="f"><tbody>'
            . '<tr><td class="k">Σχετικά με:</td><td>Αριθμός Πρωτοκόλλου: ' . self::e($o['protocol'])
            . '</td></tr></tbody></table>'
            . '<div class="box"><div class="h">ΠΑΡΑΤΗΡΗΣΕΙΣ</div><div class="v"></div></div>'
            . '<div class="box sg"><div class="h">Υπογραφή - Σφραγίδα Επιχείρησης</div><div class="v"></div></div>',
            ++$n);

        return $H;
    }

}
