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

    /** Οι λειτουργικές ομάδες Soft1 — σταθερό κείμενο της πρότασης. */
    public static function soft1Modules()
    {
        return [
            ['Operations & CRM', ['Ημερολόγιο – Διαχείριση Συνάντησης', 'CRM – Sales & Marketing',
                'Contacts & Φυσικά Πρόσωπα', 'Έργα', 'Υπηρεσίες & Τεχνικοί']],
            ['Stock Management', ['Διαχείριση Αποθήκης (απεριόριστοι αποθηκευτικοί χώροι)',
                'Υποκαταστήματα', 'Εναλλακτικά – Αντίστοιχα Είδη', 'Θέσεις Αποθήκευσης – Ράφια',
                'Serial Numbers – Services']],
            ['Εμπορική Δραστηριότητα', ['Πωλήσεις, Διαχείριση Λιανικής, Διαχείριση Πελατών, Χρεωστών',
                'Παρακολούθηση Πωλητών, Εισπρακτόρων', 'Μέσα, Γεωγραφικά Σημεία',
                'Συγκεντρωτικές Καρτέλες & Ισοζύγια – Όμιλοι Εταιρειών']],
            ['Χρηματοοικονομική Διαχείριση – Δαπάνες', [
                'Χρημ. Συναλλαγές: Εισπράξεις – Πληρωμές, Αξιόγραφα, Ειδικές Συναλλαγές & Χρεοπιστώσεις',
                'Χρηματικοί & Τραπεζικοί Λογαριασμοί – Ταμεία & Εμβάσματα, Συμψηφισμοί',
                'Αντιστοιχήσεις – Open Item, Διαχείριση Πιστωτικών Καρτών', 'Διακανονισμοί & Δόσεις']],
            ['Reporting Tools', ['Ελεύθερα Πεδία και Αθροιστές',
                'Διαχείριση Επισυναπτόμενων Ηλεκτρονικών Αρχείων', 'Report Generator – Basic',
                'Report Designer – Advanced', 'Merging']],
            ['Customization Tools', ['Σχεδιασμός Οθονών', 'Remote Systems', 'ALERT Systems']],
            ['Web & Mobile apps', ['Soft1 Web Report (5 users)', 'Soft1 My Customer (50 customers)',
                'Soft1 Quick View & Soft1 My Portal']],
        ];
    }

    /** Οι πέντε «κάδοι» της προσφοράς — έτσι ομαδοποιούνται οι γραμμές στο έγγραφο. */
    public static function buckets()
    {
        return [
            1 => ['Cloud Licensing — Λογισμικό Soft1', 'Αξία Ετήσιας Συνδρομής Λογισμικού', 'Αξία / έτος'],
            2 => ['Cloud Licensing — Άδειες CloudOn / Συνταγογράφηση', 'Ετήσιο Κόστος Αδειών CloudOn', 'Αξία αδείας € / έτος'],
            3 => ['Υπηρεσίες Παραμετροποίησης', 'Συνολική Αξία Υπηρεσιών Παραμετροποίησης', 'Αξία (εφάπαξ)'],
            4 => ['Υπηρεσίες διασύνδεσης / courier / marketplaces', 'Αξία Υπηρεσιών διασύνδεσης', 'Αξία (εφάπαξ)'],
            5 => ['Υπηρεσίες Νέων Εκδόσεων / Τηλ. Υποστήριξης — ετήσιες χρεώσεις', 'Αξία Ετήσιων Υπηρεσιών', 'Αξία / έτος'],
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
            $out[$rw[1]][] = ['lab' => $rw[2], 'qty' => $qty, 'amount' => $v];
        }
        foreach (self::oneoffRows() as $ri => $rw) {
            $v = $res['oneoff'][$ri][$i];
            if (abs($v) < 0.005) { continue; }
            $qty = $rw[4] ? call_user_func($rw[4], $p, $r, $e, $i, $yn)
                : ($rw[2] ? '× ' . self::fmtHrs($v / self::HOUR_RATE) . ' ώρες · τιμή ' . self::fmtEur(self::HOUR_RATE) : '');
            $out[$rw[1]][] = ['lab' => $rw[3], 'qty' => $qty, 'amount' => $v];
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

    private static function bucketTable($bk, $lines)
    {
        if (!$lines) { return ''; }
        $total = array_sum(array_column($lines, 'amount'));
        $h = '<h5>' . self::e($bk[0]) . '</h5>'
            . '<table class="doc"><thead><tr><th>Περιγραφή</th><th class="n">' . self::e($bk[2]) . '</th></tr></thead><tbody>';
        foreach ($lines as $l) {
            $h .= '<tr><td>' . self::e($l['lab'])
                . ($l['qty'] ? '<span class="qty">' . self::e($l['qty']) . '</span>' : '')
                . '</td><td class="n">' . self::fmtEur($l['amount']) . '</td></tr>';
        }
        return $h . '<tr class="sum"><td>' . self::e($bk[1]) . '</td><td class="n">'
            . self::fmtEur($total) . '</td></tr></tbody></table>';
    }

    /**
     * Το πλήρες έγγραφο προσφοράς, κατά το πρότυπο «ΔΕΙΓΜΑ ΠΡΟΣΦΟΡΑ SOFT1 … CLOUD».
     * Επιστρέφει μόνο τις σελίδες — το στυλ το βάζει η οθόνη ή το email.
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
        $vat = $fin * ((float) $o['vat'] / 100);
        $company = $o['client'] !== '' ? $o['client'] : '……………………………';
        $prod = $e['soft1'] . ' — Cloud Edition + ' . $e['name'];
        $H = '';

        /* Εξώφυλλο */
        $H .= '<div class="page cover">'
            . '<div class="kicker">Οικονομική Πρόταση</div>'
            . '<div class="ctitle">Για την εγκατάσταση και λειτουργία του πληροφοριακού συστήματος</div>'
            . '<div class="cprod">' . self::e($prod) . '</div>'
            . '<div class="cclient">' . self::e($company) . '</div>'
            . '<div class="cmeta">' . self::e($o['city']) . ', <b>' . self::e(self::longDate($o['date'])) . '</b><br>'
            . 'Αρ. Πρωτ/λου: <b>' . self::e($o['protocol']) . '</b></div></div>';

        /* Συνοδευτική επιστολή */
        $H .= '<div class="page"><div class="letterhead">'
            . '<div><span class="lab">Προς:</span> <b>' . self::e($company) . '</b><br>'
            . ($o['address'] ? self::e($o['address']) . '<br>' : '')
            . ($o['afm'] ? '<span class="lab">ΑΦΜ:</span> ' . self::e($o['afm'])
                . ($o['doy'] ? ' &nbsp;<span class="lab">Δ.Ο.Υ.:</span> ' . self::e($o['doy']) : '') . '<br>' : '')
            . '<span class="lab">Υπόψη:</span> ' . self::e($o['attn'] ?: '…') . '</div>'
            . '<div><span class="lab">Αριθμός Πρωτοκόλλου:</span> ' . self::e($o['protocol']) . '<br>'
            . self::e($o['city']) . ', ' . self::e(self::longDate($o['date'])) . '</div></div>'
            . '<p>Αξιότιμε/η κύριε/κυρία' . ($o['attn'] ? ' ' . self::e($o['attn']) : '') . ',</p>'
            . '<p>Σε συνέχεια του ενδιαφέροντος που εκδηλώσατε για την εγκατάσταση του λογισμικού Soft1 και της '
            . 'πλατφόρμας PharmacyOne στην επιχείρηση «' . self::e($company) . '», σας παραθέτουμε την οικονομική '
            . 'μας πρόταση, που αφορά σε όλο το εύρος των βασικών λειτουργιών και επιμέρους διαδικασιών που '
            . 'συζητήθηκαν και αναλύθηκαν στις ιδιαίτερες συναντήσεις μας.</p>'
            . '<p>Η πρότασή μας στηρίζεται στο ολοκληρωμένο πληροφοριακό σύστημα Soft1, σε συνδυασμό με το '
            . 'PharmacyOne — τη λύση της CloudOn για το σύγχρονο φαρμακείο, που καλύπτει τη συνταγογράφηση, '
            . 'τη διαχείριση αποθέματος και τη διασύνδεση με τα κανάλια λιανικής.</p>'
            . '<p>Παραμένουμε στη διάθεσή σας για οποιαδήποτε συμπληρωματική πληροφορία ή διευκρίνιση.</p>'
            . '<div class="sig">Με τιμή,<b>' . self::e($o['seller']) . '</b>CloudOn Ι.Κ.Ε.</div></div>';

        /* Προτεινόμενο λογισμικό */
        $feats = [];
        foreach (self::features() as $f) { if (!empty($f[1][$i])) { $feats[] = $f[0]; } }
        $mods = self::activeModules($c);
        $H .= '<div class="page"><h3>1 Οικονομική Πρόταση</h3>'
            . '<h4>1.1 Προτεινόμενο Λογισμικό</h4>'
            . '<p>Το λογισμικό που προτείνεται για εγκατάσταση και λειτουργία στην «' . self::e($company)
            . '» είναι το <b>' . self::e($prod) . '</b>, με συνδρομητικό μοντέλο διάθεσης ορισμένου χρόνου (cloud). '
            . 'Η εγκατάσταση αφορά <b>' . (int) $p['I4'] . '</b> ' . self::plural($p['I4'], 'χρήστη', 'χρήστες') . ', <b>'
            . (int) $p['J4'] . '</b> ' . self::plural($p['J4'], 'υποκατάστημα', 'υποκαταστήματα') . ' και <b>'
            . (int) $p['K4'] . '</b> ' . self::plural($p['K4'], 'εταιρεία', 'εταιρείες') . '.</p>'
            . '<h5>Ενότητες λογισμικού που περιλαμβάνονται στην έκδοση</h5><ul class="ticks">';
        foreach ($feats as $f) { $H .= '<li>' . self::e($f) . '</li>'; }
        $H .= '</ul>';
        if ($mods) {
            $H .= '<h5>Πρόσθετα modules PharmacyOne / CloudOn</h5><ul class="ticks">';
            foreach ($mods as $m) { $H .= '<li>' . self::e($m) . '</li>'; }
            $H .= '</ul>';
        }
        $H .= '<h5>Βασικές λειτουργικές ομάδες Soft1</h5>'
            . '<table class="doc"><thead><tr><th>Ομάδα</th><th>Περιλαμβάνει</th></tr></thead><tbody>';
        foreach (self::soft1Modules() as $g) {
            $H .= '<tr class="inc"><td><b>' . self::e($g[0]) . '</b></td><td>'
                . implode(' · ', array_map([self::class, 'e'], $g[1])) . '</td></tr>';
        }
        $H .= '</tbody></table></div>';

        /* Οικονομική πρόταση */
        $H .= '<div class="page"><h4>1.2 Οικονομική Πρόταση</h4>'
            . '<p>Τρόπος διάθεσης λογισμικού: <b>ορισμένου χρόνου / cloud — συνδρομητικό μοντέλο</b>.</p>';
        foreach (self::buckets() as $b => $bd) { $H .= self::bucketTable($bd, $lines[$b]); }
        $H .= '<p class="docnote">Οι ώρες υπηρεσιών υπολογίζονται με χρέωση ' . self::fmtEur(self::HOUR_RATE)
            . ' ανά ώρα. Στις παραπάνω αξίες έχουν ήδη ενσωματωθεί οι εκπτώσεις: αδειών CloudOn '
            . self::fmtPct($p['L4']) . ', αδειών Soft1 ' . self::fmtPct($p['K8']) . ', υπηρεσιών '
            . self::fmtPct($p['J8']) . '.</p></div>';

        /* Συγκεντρωτικοί πίνακες */
        $H .= '<div class="page"><h4>1.3 Υπηρεσίες Υλοποίησης Έργου</h4>'
            . '<p>Το κόστος των υπηρεσιών υλοποίησης του έργου ανέρχεται σε <b>' . self::fmtEur($bt[3])
            . '</b> και αντιστοιχεί σε <b>' . self::fmtHrs($bt[3] / self::HOUR_RATE) . '</b> ώρες εργασίας.</p>'
            . '<h4>1.4 Συγκεντρωτικοί Πίνακες</h4>'
            . '<h5>1.4.1 Λογισμικό &amp; Υπηρεσίες</h5>'
            . '<table class="doc"><thead><tr><th>Περιγραφή</th><th class="n">Αξία</th></tr></thead><tbody>';
        foreach (self::buckets() as $b => $bd) {
            $H .= '<tr><td>' . self::e($bd[1]) . '</td><td class="n">' . self::fmtEur($bt[$b]) . '</td></tr>';
        }
        $H .= '<tr class="sum"><td>Τελική Αξία Έργου προ έκπτωσης</td><td class="n">' . self::fmtEur($pre) . '</td></tr>'
            . ($disc ? '<tr><td>Έκπτωση επί της αξίας λογισμικού και υπηρεσιών</td><td class="n">−'
                . self::fmtEur($disc) . '</td></tr>' : '')
            . '<tr class="grand"><td>Τελική Αξία Έργου</td><td class="n">' . self::fmtEur($fin) . '</td></tr>'
            . '<tr><td>ΦΠΑ ' . (float) $o['vat'] . '%</td><td class="n">' . self::fmtEur($vat) . '</td></tr>'
            . '<tr class="sum"><td>Σύνολο με ΦΠΑ</td><td class="n">' . self::fmtEur($fin + $vat) . '</td></tr>'
            . '</tbody></table>'
            . '<h5>1.4.2 Κατανομή σε εφάπαξ και επαναλαμβανόμενο κόστος</h5>'
            . '<table class="doc"><thead><tr><th>Περιγραφή</th><th class="n">Αξία</th></tr></thead><tbody>'
            . '<tr><td>Εφάπαξ κόστος έναρξης (παραμετροποίηση &amp; διασυνδέσεις)</td><td class="n">'
            . self::fmtEur($bt[3] + $bt[4]) . '</td></tr>'
            . '<tr><td>Ετήσιο επαναλαμβανόμενο κόστος (συνδρομές &amp; υποστήριξη)</td><td class="n">'
            . self::fmtEur($bt[1] + $bt[2] + $bt[5]) . '</td></tr>'
            . '<tr class="sum"><td>Σύνολο πρώτου έτους προ έκπτωσης</td><td class="n">' . self::fmtEur($pre) . '</td></tr>'
            . '<tr><td>Μηνιαίο κόστος ανά χρήστη</td><td class="n">' . self::fmtEur($t['monthlyPerUser']) . '</td></tr>'
            . '</tbody></table>'
            . '<div class="terms"><p><b>Αξίες:</b> όλες οι παραπάνω αξίες επιβαρύνονται με ΦΠΑ '
            . (float) $o['vat'] . '%.<br><b>Ισχύς προσφοράς:</b> η προσφορά ισχύει για '
            . (int) $o['validDays'] . ' ημέρες.</p></div></div>';

        /* Τρόπος πληρωμής */
        $rest = 100 - (float) $o['prepay'];
        $H .= '<div class="page"><h4>1.5 Τρόπος Πληρωμής</h4>'
            . '<p>Η εξόφληση της αξίας των εφαρμογών λογισμικού και του κόστους των υπηρεσιών υλοποίησης γίνεται ως εξής:</p>'
            . '<ul><li><b>' . (float) $o['prepay'] . '%</b> της συνολικής αξίας του έργου με την ανάθεση.</li>'
            . '<li><b>' . $rest . '%</b> του συνολικού ποσού με την παράδοση του έργου.</li></ul>'
            . '<h5>Τραπεζικοί λογαριασμοί — CloudOn Ι.Κ.Ε.</h5><div class="banks">'
            . '<div class="bank"><b>EUROBANK</b>IBAN: GR54 0260 2340 0004 6020 0987 302<br>Δικαιούχος: CLOUDON IKE</div>'
            . '<div class="bank"><b>ALPHA BANK</b>IBAN: GR69 0140 2090 2090 0200 2001 311<br>Δικαιούχος: CLOUDON IKE</div>'
            . '</div>'
            . '<p class="docnote">Η εξόφληση προϊόντων άλλων κατασκευαστών (βάσεις δεδομένων) γίνεται με '
            . 'αξιόγραφο 30 ημερών από την ημερομηνία τιμολόγησης.</p></div>';

        /* Έντυπο αποδοχής */
        $H .= '<div class="page"><h3>2 Παράρτημα Α — Έντυπο Αποδοχής Προσφοράς</h3>'
            . '<p>Για την αποδοχή της παραπάνω προσφοράς, παρακαλείσθε να επιστρέψετε υπογεγραμμένη και '
            . 'σφραγισμένη την παρούσα σελίδα.</p><div class="acc">'
            . '<div><div class="row">Από: ' . self::e($company) . '</div>'
            . '<div class="row">ΑΦΜ / Δ.Ο.Υ.: ' . self::e($o['afm']) . ($o['doy'] ? ' — ' . self::e($o['doy']) : '') . '</div>'
            . '<div class="row">Υπεύθυνος: ' . self::e($o['attn']) . '</div>'
            . '<div class="row">Τηλέφωνο: ' . self::e($o['cphone']) . '</div>'
            . '<div class="row">Email: ' . self::e($o['cemail']) . '</div>'
            . '<div class="row">Ημερομηνία:</div></div>'
            . '<div><div class="row">Προς: CloudOn Ι.Κ.Ε.</div>'
            . '<div class="row">Τηλέφωνο: ' . self::e($o['tel']) . '</div>'
            . '<div class="row">Fax: ' . self::e($o['fax']) . '</div>'
            . '<div class="row">Υπόψη: ' . self::e($o['acceptAttn']) . '</div>'
            . '<div class="row">Αρ. Πρωτοκόλλου: ' . self::e($o['protocol']) . '</div></div></div>'
            . '<div class="remarks"><span>Παρατηρήσεις</span></div>'
            . '<div class="signbox"><span>Υπογραφή — Σφραγίδα Επιχείρησης</span></div></div>';

        return $H;
    }

}
