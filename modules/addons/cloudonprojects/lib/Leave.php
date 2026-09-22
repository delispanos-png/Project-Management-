<?php

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

/**
 * Άδειες προσωπικού.
 *
 * ΤΟ ΠΡΟΒΛΗΜΑ: το μητρώο αδειών ζούσε σε ένα Excel με ένα φύλλο ανά εργαζόμενο,
 * χειροκίνητα αθροίσματα στο υποσέλιδο κάθε έτους και σχόλια τύπου «ΧΡΩΣΤΑΜΕ 3
 * ΗΜΕΡ» σε ελεύθερο κείμενο. Κανείς δεν μπορούσε να απαντήσει «πόσες μέρες μου
 * μένουν» χωρίς να ανοίξει το αρχείο, και δύο φύλλα είχαν ήδη αθροίσματα που
 * δεν έβγαιναν (Βάκρινος 2021: 28 εγγραφές έναντι 26 στο σύνολο).
 *
 * ΤΕΣΣΕΡΙΣ ΚΑΝΟΝΕΣ ΠΟΥ ΚΡΑΤΑΜΕ ΑΠΟ ΤΟ EXCEL — δεν είναι ιδιοτροπίες, είναι
 * εργατικό δίκαιο, και κάθε ένας τους σπάει αν τον αγνοήσεις:
 *
 * 1. ΕΤΟΣ ΔΙΚΑΙΩΜΑΤΟΣ != ΕΤΟΣ ΛΗΨΗΣ. Η άδεια του 2025 λαμβάνεται νόμιμα μέσα στο
 *    2026. Η εγγραφή κρέμεται από το `year` (δικαίωμα), ΟΧΙ από το `date_from`.
 *    Αν τις ομαδοποιήσεις κατά ημερολογιακό έτος, τα υπόλοιπα βγαίνουν λάθος.
 * 2. ΟΙ ΗΜΕΡΕΣ ΔΕΝ ΒΓΑΙΝΟΥΝ ΑΠΟ ΤΙΣ ΗΜΕΡΟΜΗΝΙΕΣ. Μεσολαβούν Σαββατοκύριακα και
 *    αργίες. Το `days` καταχωρείται, δεν υπολογίζεται — ο υπολογισμός μόνο
 *    ΠΡΟΤΕΙΝΕΙ (δες suggestDays()).
 * 3. ΜΟΝΟ Η ΚΑΝΟΝΙΚΗ ΑΔΕΙΑ ΑΦΑΙΡΕΙ ΥΠΟΛΟΙΠΟ. Ασθένεια, σχολική, γάμου, θανάτου,
 *    εκλογική: καταγράφονται για το ιστορικό και την ΕΡΓΑΝΗ, δεν χρεώνονται.
 * 4. Η ΕΡΓΑΝΗ ΕΙΝΑΙ ΑΛΛΗ ΗΜΕΡΟΜΗΝΙΑ. Στο αρχείο υπάρχουν δεκάδες άδειες που
 *    δηλώθηκαν σε διαφορετικές ημέρες από τις πραγματικές. Ξεχωριστό πεδίο, όχι
 *    σχόλιο — αλλιώς δεν μπορείς ποτέ να ελέγξεις τι δηλώθηκε και τι όχι.
 *
 * ΤΑ ΥΠΟΛΟΙΠΑ ΥΠΟΛΟΓΙΖΟΝΤΑΙ, ΔΕΝ ΑΠΟΘΗΚΕΥΟΝΤΑΙ. Το Excel κρατούσε άθροισμα σε
 * κελί και γι' αυτό ξέφευγε. Εδώ το μόνο που αποθηκεύεται είναι το δικαίωμα·
 * ό,τι έχει ληφθεί προκύπτει από τις εγγραφές. Η μόνη εξαίρεση είναι το
 * `legacy_taken`: για τα παλιά έτη που το φύλλο έλεγε άλλο νούμερο από το
 * άθροισμά του, το κρατάμε ΔΙΠΛΑ στο σωστό και το δείχνουμε ως ασυμφωνία, αντί
 * να διαλέξουμε σιωπηλά ποιο είναι το σωστό.
 */
class Leave
{
    /** Τύποι αδειών. Το δεύτερο πεδίο λέει αν χρεώνει το υπόλοιπο. */
    const TYPES = [
        'ANNUAL'      => ['Κανονική άδεια',      true],
        'SICK'        => ['Ασθένεια',            false],
        'SCHOOL'      => ['Σχολική άδεια',       false],
        'PARENTAL'    => ['Γονική άδεια',        false],
        'MARRIAGE'    => ['Άδεια γάμου',         false],
        'BEREAVEMENT' => ['Άδεια θανάτου',       false],
        'ELECTION'    => ['Εκλογική άδεια',      false],
        'UNPAID'      => ['Άνευ αποδοχών',       false],
        'OTHER'       => ['Άλλο',                false],
    ];

    /**
     * Η ΠΟΡΕΙΑ ΜΙΑΣ ΑΔΕΙΑΣ — αυτό είναι το κύκλωμα.
     *
     * Το `taken` ΔΕΝ είναι κατάσταση που πατάει κάποιος: μπαίνει μόνο του όταν
     * περάσει η ημερομηνία. Ο χειριστής δηλώνει πρόθεση, ο υπεύθυνος εγκρίνει,
     * και ο χρόνος κάνει το υπόλοιπο. Έτσι κανείς δεν χρειάζεται να θυμηθεί να
     * «κλείσει» μια άδεια που ήδη πέρασε.
     */
    const FLOW = [
        'requested' => ['Ζητήθηκε',     'wait'],
        'approved'  => ['Εγκρίθηκε',    'ok'],
        'taken'     => ['Ελήφθη',       'done'],
        'rejected'  => ['Απορρίφθηκε',  'no'],
        'cancelled' => ['Ακυρώθηκε',    'no'],
    ];

    /** Καταστάσεις που ΜΕΤΡΑΝΕ στο υπόλοιπο. Απορριφθείσα ή ακυρωμένη δεν χρεώνει. */
    const COUNTS = ['requested', 'approved', 'taken'];

    /** Σημαίες εκκρεμοτήτων — το ελεύθερο κείμενο του Excel γίνεται φιλτράρισμα. */
    const FLAGS = [
        'owed'       => 'Την χρωστάμε',
        'not_synced' => 'Δεν έχει περαστεί στο σύστημα',
        'comp'       => 'Αποζημιώθηκε',
    ];

    /* ------------------------------------------------------------------ */
    /* Σχήμα                                                              */
    /* ------------------------------------------------------------------ */

    public static function install()
    {
        $s = Capsule::schema();

        /* Ο εργαζόμενος. Γέφυρα με τον χειριστή του WHMCS: το μητρώο αδειών δεν
           φτιάχνει δεύτερη ταυτότητα ανθρώπου — δένει σε αυτήν που ήδη υπάρχει. */
        if (!$s->hasTable('mod_cpm_leave_staff')) {
            $s->create('mod_cpm_leave_staff', function ($t) {
                $t->increments('id');
                $t->integer('admin_id')->unsigned()->unique();
                $t->string('afm', 20)->nullable();
                $t->date('hire_date')->nullable();
                /* Αναλογία για το έτος πρόσληψης: 1,67 ή 2,08 ημέρες/μήνα. */
                $t->decimal('accrual_month', 4, 2)->nullable();
                $t->string('source_code', 12)->nullable();   // ALEF/VAKR… από το Excel
                $t->tinyInteger('active')->default(1);
                $t->text('notes')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        /* Το ΔΙΚΑΙΩΜΑ ανά έτος και τύπο. Δεν είναι σταθερό: εξαρτάται από
           προϋπηρεσία (25 → 26) και από αναλογία στο έτος πρόσληψης. */
        if (!$s->hasTable('mod_cpm_leave_years')) {
            $s->create('mod_cpm_leave_years', function ($t) {
                $t->increments('id');
                $t->integer('staff_id')->unsigned()->index();
                $t->smallInteger('year');
                $t->string('type', 12)->default('ANNUAL');
                $t->decimal('entitled_days', 5, 2)->default(0);
                /* Τι έλεγε το Excel ότι ελήφθη, όταν διαφωνεί με το άθροισμα των
                   εγγραφών. Κρατιέται για να ΦΑΙΝΕΤΑΙ η ασυμφωνία, όχι για να
                   χρησιμοποιηθεί σε υπολογισμό. */
                $t->decimal('legacy_taken', 5, 2)->nullable();
                $t->text('note')->nullable();
                $t->unique(['staff_id', 'year', 'type']);
            });
        }

        /* Η κάθε άδεια. */
        if (!$s->hasTable('mod_cpm_leaves')) {
            $s->create('mod_cpm_leaves', function ($t) {
                $t->increments('id');
                $t->integer('staff_id')->unsigned()->index();
                $t->smallInteger('year')->index();           // ΕΤΟΣ ΔΙΚΑΙΩΜΑΤΟΣ
                $t->string('type', 12)->default('ANNUAL');
                $t->decimal('days', 5, 2)->default(0);
                $t->date('date_from')->nullable()->index();
                $t->date('date_to')->nullable();
                /* Το αρχικό κείμενο του Excel («5/8-7/8/2026»). Ό,τι δεν μπόρεσε
                   να διαβαστεί ως ημερομηνία σώζεται εδώ αντί να χαθεί. */
                $t->string('raw_period', 80)->nullable();
                $t->string('status', 12)->default('requested');
                /* ΕΡΓΑΝΗ: κείμενο, γιατί στο αρχείο υπάρχουν και περίοδοι
                   («23/3-26/3/2026»), όχι μόνο μεμονωμένες ημερομηνίες. */
                $t->string('ergani', 40)->nullable();
                $t->string('flag', 12)->nullable()->index();  // owed | not_synced | comp
                $t->text('note')->nullable();
                $t->string('source_id', 40)->nullable()->index();  // ALEF-ANN-2026-001
                $t->integer('event_id')->unsigned()->nullable();   // αντίγραφο στο ημερολόγιο
                $t->integer('created_by')->unsigned()->nullable();
                $t->integer('approved_by')->unsigned()->nullable();
                $t->dateTime('approved_at')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->dateTime('updated_at')->nullable();
            });
        }
    }

    /* ------------------------------------------------------------------ */
    /* Ερωτήματα                                                          */
    /* ------------------------------------------------------------------ */

    public static function typeLabel($code)
    {
        return self::TYPES[$code][0] ?? $code;
    }

    public static function deducts($code)
    {
        return (bool) (self::TYPES[$code][1] ?? false);
    }

    public static function flowLabel($code)
    {
        return self::FLOW[$code][0] ?? $code;
    }

    /** Ο εργαζόμενος ενός χειριστή, ή null αν δεν είναι στο μητρώο. */
    public static function staffFor($adminId)
    {
        if (!$adminId) { return null; }
        return Capsule::table('mod_cpm_leave_staff')->where('admin_id', (int) $adminId)->first();
    }

    /**
     * Υπόλοιπο ανά έτος δικαιώματος για έναν εργαζόμενο.
     *
     * Επιστρέφει έτος => [entitled, taken, remaining, legacy, mismatch].
     * Το `mismatch` δεν είναι σφάλμα προς διόρθωση — είναι ερώτηση προς άνθρωπο.
     */
    public static function balance($staffId, $type = 'ANNUAL')
    {
        $out = [];
        foreach (Capsule::table('mod_cpm_leave_years')->where('staff_id', (int) $staffId)
                ->where('type', $type)->orderBy('year')->get() as $y) {
            $out[(int) $y->year] = [
                'entitled'  => (float) $y->entitled_days,
                'taken'     => 0.0,
                'remaining' => (float) $y->entitled_days,
                'legacy'    => $y->legacy_taken === null ? null : (float) $y->legacy_taken,
                'mismatch'  => false,
                'note'      => (string) $y->note,
            ];
        }
        $rows = Capsule::table('mod_cpm_leaves')->where('staff_id', (int) $staffId)
            ->where('type', $type)->whereIn('status', self::COUNTS)
            ->select('year', Capsule::raw('SUM(days) AS d'))->groupBy('year')->get();
        foreach ($rows as $r) {
            $y = (int) $r->year;
            if (!isset($out[$y])) {
                /* Άδεια σε έτος χωρίς δηλωμένο δικαίωμα: δεν την κρύβουμε. */
                $out[$y] = ['entitled' => 0.0, 'taken' => 0.0, 'remaining' => 0.0,
                    'legacy' => null, 'mismatch' => false, 'note' => ''];
            }
            $out[$y]['taken'] = (float) $r->d;
            $out[$y]['remaining'] = round($out[$y]['entitled'] - (float) $r->d, 2);
        }
        foreach ($out as $y => &$b) {
            if ($b['legacy'] !== null && abs($b['legacy'] - $b['taken']) > 0.01) {
                $b['mismatch'] = true;
            }
        }
        unset($b);
        ksort($out);
        return $out;
    }

    /** Το συνολικό υπόλοιπο κανονικής άδειας — αυτό που ρωτάει ο άνθρωπος. */
    public static function remainingTotal($staffId)
    {
        $sum = 0.0;
        foreach (self::balance($staffId) as $b) {
            $sum += $b['remaining'];
        }
        return round($sum, 2);
    }

    /**
     * Πρόταση ημερών για μια περίοδο: εργάσιμες Δευτέρα–Παρασκευή.
     *
     * ΠΡΟΤΑΣΗ, ΟΧΙ ΑΠΟΦΑΣΗ. Δεν ξέρουμε τις αργίες, και το Excel έχει εγγραφές
     * όπου οι χρεωμένες ημέρες διαφέρουν από τις εργάσιμες της περιόδου. Ο
     * χειριστής βλέπει τον αριθμό και τον διορθώνει αν χρειάζεται.
     */
    public static function suggestDays($from, $to)
    {
        if (!$from) { return 0; }
        try {
            $a = new \DateTime($from);
            $b = new \DateTime($to ?: $from);
        } catch (\Throwable $e) {
            return 0;
        }
        if ($b < $a) { return 0; }
        $n = 0;
        for ($d = clone $a; $d <= $b; $d->modify('+1 day')) {
            if ((int) $d->format('N') <= 5) { $n++; }
        }
        return $n;
    }

    /** Ποιοι λείπουν μια δεδομένη ημέρα — για τη ζώνη διαθεσιμότητας. */
    public static function awayOn($date)
    {
        $out = [];
        foreach (Capsule::table('mod_cpm_leaves as l')
                ->join('mod_cpm_leave_staff as s', 's.id', '=', 'l.staff_id')
                ->whereIn('l.status', ['approved', 'taken'])
                ->whereNotNull('l.date_from')
                ->where('l.date_from', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->where('l.date_to', '>=', $date)->orWhereNull('l.date_to');
                })
                ->get(['s.admin_id', 'l.type']) as $r) {
            $out[(int) $r->admin_id] = self::typeLabel($r->type);
        }
        return $out;
    }
}
