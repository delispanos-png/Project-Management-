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
        'ANNUAL'       => ['Κανονική άδεια',                          true],
        'SICK'         => ['Ασθένεια',                                false],
        'MATERNITY'    => ['Άδεια μητρότητας',                        false],
        'MAT_SPECIAL'  => ['Ειδική άδεια προστασίας μητρότητας',      false],
        'PATERNITY'    => ['Άδεια πατρότητας',                        false],
        'PARENTAL'     => ['Γονική άδεια',                            false],
        'CHILDCARE'    => ['Άδεια φροντίδας παιδιού (μειωμένο ωράριο)', false],
        'SCHOOL'       => ['Σχολική άδεια (παρακολούθηση επίδοσης)',  false],
        'CHILD_SICK'   => ['Ασθένεια παιδιού / εξαρτώμενου μέλους',   false],
        'CHILD_SEV'    => ['Σοβαρά νοσήματα παιδιού',                 false],
        'CHILD_HOSP'   => ['Νοσηλεία παιδιού',                        false],
        'SINGLE_PAR'   => ['Πρόσθετη άδεια μονογονέα',                false],
        'CARER'        => ['Άδεια φροντιστή',                         false],
        'FORCE_MAJ'    => ['Απουσία λόγω ανωτέρας βίας',              false],
        'MARRIAGE'     => ['Άδεια γάμου / συμφώνου συμβίωσης',        false],
        'BEREAVEMENT'  => ['Άδεια θανάτου συγγενούς',                 false],
        'BEREAV_CHILD' => ['Άδεια πενθούντων γονέων',                 false],
        'IVF'          => ['Ιατρικώς υποβοηθούμενη αναπαραγωγή',      false],
        'PRENATAL'     => ['Προγεννητικές εξετάσεις',                 false],
        'GYN'          => ['Γυναικολογικός έλεγχος',                  false],
        'BLOOD'        => ['Αιμοδοσία',                               false],
        'EXAMS'        => ['Άδεια εξετάσεων (σπουδαστές)',            false],
        'ELECTION'     => ['Εκλογική άδεια',                          false],
        'MILITARY'     => ['Στρατιωτική υποχρέωση / έφεδροι',         false],
        'UNION'        => ['Συνδικαλιστική άδεια',                    false],
        'UNPAID'       => ['Άνευ αποδοχών',                           false],
        'OTHER'        => ['Άλλο',                                    false],
    ];

    /**
     * ΟΙ ΕΙΔΙΚΕΣ ΑΔΕΙΕΣ ΩΣ ΚΩΔΙΚΑΣ (22/10/2026) — Π.Δ. 62/2025 «Κώδικας Εργατικού Δικαίου»
     * (κωδικοποίηση ν.4808/2021, οδηγία 2019/1158), ΑΚ 657-658, ΕΓΣΣΕ. Πενθήμερο.
     *
     * group: για την ομαδοποίηση στις λίστες · unit: year (ανά ημερολογιακό έτος) | event
     * (ανά γεγονός) | child (ανά παιδί) · basis: work (εργάσιμες) | cal (ημερολογιακές)
     * | hours · pay: ποιος πληρώνει · doc: δικαιολογητικό · law: άρθρο · rule: ο κανόνας
     * όπως τον διαβάζει άνθρωπος. Το ΠΟΣΟ ανά εργαζόμενο το βγάζει το entitlement() από
     * τα οικογενειακά του στοιχεία· η μισθοδοσία έχει τον τελευταίο λόγο (υπερισχύει
     * ό,τι γραφτεί στο mod_cpm_leave_years για τον τύπο/έτος).
     */
    const META = [
        'ANNUAL'       => ['group' => 'Κανονική', 'unit' => 'year', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => '', 'law' => 'ΠΔ 62/2025 άρθ. 89',
                           'rule' => '20/21/22 ημέρες (πενθήμερο) ανάλογα με τα έτη· 25 με 10 έτη στην εταιρεία ή 12 προϋπηρεσία· 26 με 25 έτη. +6 για εργαζόμενο με αναπηρία.'],
        'SICK'         => ['group' => 'Υγεία', 'unit' => 'event', 'basis' => 'cal', 'pay' => 'Εργοδότης ½ τις 3 πρώτες · μετά e-ΕΦΚΑ + διαφορά', 'doc' => 'Ηλεκτρονική βεβαίωση ανικανότητας (ΗΔΙΚΑ)', 'law' => 'ΑΚ 657-658 · ΠΔ 62/2025 άρθ. 349',
                           'rule' => 'Χωρίς ετήσιο όριο ημερών. Ημέρες 1-3: μισές αποδοχές από τον εργοδότη. Από την 4η: επίδομα e-ΕΦΚΑ και ο εργοδότης συμπληρώνει έως ½ μήνα (κάτω από 1 έτος) ή 1 μήνα (1 έτος+).'],
        'MATERNITY'    => ['group' => 'Γονείς', 'unit' => 'event', 'basis' => 'cal', 'pay' => 'e-ΕΦΚΑ / ΔΥΠΑ', 'doc' => 'Βεβαίωση πιθανής ημερομηνίας τοκετού, ληξιαρχική πράξη γέννησης', 'law' => 'ΠΔ 62/2025 άρθ. 236', 'sex' => 'F',
                           'rule' => '17 εβδομάδες (119 ημερολογιακές): 8 πριν και 9 μετά τον τοκετό.'],
        'MAT_SPECIAL'  => ['group' => 'Γονείς', 'unit' => 'event', 'basis' => 'cal', 'pay' => 'ΔΥΠΑ', 'doc' => 'Αίτηση στη ΔΥΠΑ', 'law' => 'ΠΔ 62/2025 άρθ. 238', 'sex' => 'F',
                           'rule' => '9 μήνες αμέσως μετά τη μητρότητα· έως 7 μήνες μεταβιβάζονται στον πατέρα. Αντικαθιστά ισόχρονη άδεια φροντίδας παιδιού.'],
        'PATERNITY'    => ['group' => 'Γονείς', 'unit' => 'child', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Ληξιαρχική πράξη γέννησης', 'law' => 'ΠΔ 62/2025 άρθ. 230',
                           'rule' => '14 εργάσιμες ανά γέννηση/υιοθεσία: 2 πριν τον τοκετό + 12 μέσα σε 30 ημέρες, ή όλες μετά τη γέννηση.'],
        'PARENTAL'     => ['group' => 'Γονείς', 'unit' => 'child', 'basis' => 'work', 'pay' => 'ΔΥΠΑ οι 2 πρώτοι μήνες · μετά χωρίς αποδοχές', 'doc' => 'Αίτηση 1 μήνα πριν · δήλωση στο ΕΡΓΑΝΗ', 'law' => 'ΠΔ 62/2025 άρθ. 231',
                           'rule' => '4 μήνες ανά γονέα και παιδί έως 8 ετών, με 1 έτος υπηρεσίας. Ολόκληρη, τμηματικά ή ως μειωμένο ωράριο. Διπλάσια για μονογονέα.'],
        'CHILDCARE'    => ['group' => 'Γονείς', 'unit' => 'child', 'basis' => 'hours', 'pay' => 'Εργοδότης (μετρά ως εργασία)', 'doc' => 'Υπεύθυνες δηλώσεις γονέων', 'law' => 'ΠΔ 62/2025 άρθ. 239',
                           'rule' => '30 μήνες μετά τη μητρότητα/ειδική/γονική: 1 ώρα λιγότερη τη μέρα (ή 2 ώρες 12 μήνες + 1 ώρα 6 μήνες, ή συνεχόμενη άδεια με συμφωνία).'],
        'SCHOOL'       => ['group' => 'Γονείς', 'unit' => 'year', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Βεβαίωση σχολείου για την επίσκεψη', 'law' => 'ΠΔ 62/2025 άρθ. 240',
                           'rule' => 'Έως 4 εργάσιμες τον χρόνο ανά παιδί σχολικής ηλικίας (έως 18), σε ώρες ή ημέρες, με άδεια του εργοδότη.'],
        'CHILD_SICK'   => ['group' => 'Γονείς', 'unit' => 'year', 'basis' => 'work', 'pay' => 'Χωρίς αποδοχές', 'doc' => 'Ιατρική βεβαίωση', 'law' => 'ΠΔ 62/2025 άρθ. 244',
                           'rule' => '6 εργάσιμες τον χρόνο (8 με 2 παιδιά, 14 με 3+) για ασθένεια παιδιού έως 16 ή εξαρτώμενου μέλους (σύζυγος, γονείς, αδέλφια).'],
        'CHILD_SEV'    => ['group' => 'Γονείς', 'unit' => 'year', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Ιατρική βεβαίωση', 'law' => 'ΠΔ 62/2025 άρθ. 245 §1',
                           'rule' => '10 εργάσιμες τον χρόνο για παιδί με σοβαρό νόσημα (μεταγγίσεις, αιμοκάθαρση, καρκίνος, μεταμόσχευση, σπάνια νόσος ≥67%, νοητική αναπηρία, Down, αυτισμός). Πρώτα εξαντλούνται οι άλλες αμειβόμενες ειδικές.'],
        'CHILD_HOSP'   => ['group' => 'Γονείς', 'unit' => 'year', 'basis' => 'work', 'pay' => 'Χωρίς αποδοχές', 'doc' => 'Βεβαίωση νοσοκομείου', 'law' => 'ΠΔ 62/2025 άρθ. 245 §3',
                           'rule' => 'Όσο διαρκεί η νοσηλεία παιδιού (κάθε ηλικίας), έως 30 εργάσιμες τον χρόνο.'],
        'SINGLE_PAR'   => ['group' => 'Γονείς', 'unit' => 'year', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Ληξιαρχική πράξη θανάτου ή απόφαση επιμέλειας', 'law' => 'ΠΔ 62/2025 άρθ. 247',
                           'rule' => '6 εργάσιμες τον χρόνο (8 με 3+ παιδιά) για χήρο/α ή άγαμο/η γονέα με την επιμέλεια, επιπλέον όλων των άλλων.'],
        'CARER'        => ['group' => 'Οικογένεια', 'unit' => 'year', 'basis' => 'work', 'pay' => 'Χωρίς αποδοχές', 'doc' => 'Ιατρική βεβαίωση', 'law' => 'ΠΔ 62/2025 άρθ. 232',
                           'rule' => 'Έως 5 εργάσιμες τον χρόνο για φροντίδα συγγενή με σοβαρό ιατρικό λόγο, με 6 μήνες υπηρεσίας.'],
        'FORCE_MAJ'    => ['group' => 'Οικογένεια', 'unit' => 'year', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Βεβαίωση νοσοκομείου ή ιατρού', 'law' => 'ΠΔ 62/2025 άρθ. 233',
                           'rule' => 'Έως 2 φορές τον χρόνο, έως 1 εργάσιμη κάθε φορά, για ξαφνική ασθένεια ή ατύχημα παιδιού/εξαρτώμενου.'],
        'MARRIAGE'     => ['group' => 'Οικογένεια', 'unit' => 'event', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Πιστοποιητικό γάμου / συμφώνου', 'law' => 'ΠΔ 62/2025 άρθ. 241',
                           'rule' => '5 εργάσιμες για γάμο ή σύμφωνο συμβίωσης.'],
        'BEREAVEMENT'  => ['group' => 'Οικογένεια', 'unit' => 'event', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Ληξιαρχική πράξη θανάτου', 'law' => 'ΕΓΣΣΕ 2002-03 / 2010-12',
                           'rule' => '2 ημέρες για θάνατο συζύγου, παιδιού, γονέα, αδελφού ή συγγενή ίδιου βαθμού εξ αγχιστείας.'],
        'BEREAV_CHILD' => ['group' => 'Οικογένεια', 'unit' => 'event', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Ληξιαρχική πράξη θανάτου', 'law' => 'ΠΔ 62/2025 άρθ. 246',
                           'rule' => '20 ημέρες σε απώλεια παιδιού.'],
        'IVF'          => ['group' => 'Υγεία', 'unit' => 'year', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Βεβαίωση ιατρού / μονάδας ΙΥΑ', 'law' => 'ΠΔ 62/2025 άρθ. 237',
                           'rule' => '7 εργάσιμες για ιατρικώς υποβοηθούμενη αναπαραγωγή.'],
        'PRENATAL'     => ['group' => 'Υγεία', 'unit' => 'event', 'basis' => 'hours', 'pay' => 'Εργοδότης', 'doc' => 'Ενημέρωση εργοδότη · βεβαίωση εξέτασης', 'law' => 'ΠΔ 62/2025 άρθ. 242', 'sex' => 'F',
                           'rule' => 'Απουσία χωρίς περικοπή αποδοχών για προγεννητικές εξετάσεις όταν γίνονται σε ώρες εργασίας.'],
        'GYN'          => ['group' => 'Υγεία', 'unit' => 'year', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Βεβαίωση εξέτασης', 'law' => 'ΠΔ 62/2025 άρθ. 242', 'sex' => 'F',
                           'rule' => '1 ημέρα τον χρόνο για γυναικολογικό προληπτικό έλεγχο.'],
        'BLOOD'        => ['group' => 'Υγεία', 'unit' => 'event', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Βεβαίωση νοσοκομείου', 'law' => 'ν.4554/2018 άρθ. 68',
                           'rule' => 'Η ημέρα της αιμοδοσίας, με πλήρεις αποδοχές· ειδοποίηση 5 εργάσιμες πριν (εκτός έκτακτης ανάγκης).'],
        'EXAMS'        => ['group' => 'Άλλες', 'unit' => 'year', 'basis' => 'work', 'pay' => 'Κατά τη νομοθεσία (ελέγξτε με μισθοδοσία)', 'doc' => 'Πρόγραμμα / βεβαίωση εξετάσεων', 'law' => 'ΠΔ 62/2025 άρθ. 254',
                           'rule' => 'Έως 30 ημέρες τον χρόνο για εργαζόμενους μαθητές/σπουδαστές κάτω των 25.'],
        'ELECTION'     => ['group' => 'Άλλες', 'unit' => 'event', 'basis' => 'work', 'pay' => 'Εργοδότης', 'doc' => 'Υπεύθυνη δήλωση', 'law' => 'ΚΥΑ κάθε εκλογών',
                           'rule' => '1 ημέρα για 100-200 χλμ., 2 για 201-400, έως 3 για 400+ ή νησιά.'],
        'MILITARY'     => ['group' => 'Άλλες', 'unit' => 'event', 'basis' => 'cal', 'pay' => 'Κατά τη νομοθεσία', 'doc' => 'Φύλλο κατάταξης / πρόσκληση', 'law' => 'ΠΔ 62/2025 άρθ. 320-322',
                           'rule' => 'Προστασία θέσης κατά τη στράτευση ή μετακλήση εφέδρου.'],
        'UNION'        => ['group' => 'Άλλες', 'unit' => 'event', 'basis' => 'work', 'pay' => 'Χωρίς αποδοχές', 'doc' => 'Έγγραφη ειδοποίηση την προηγούμενη εβδομάδα', 'law' => 'ΠΔ 62/2025 άρθ. 383',
                           'rule' => 'Για συνδικαλιστικά στελέχη, ανάλογα με τη θέση (π.χ. 3 ημέρες/μήνα).'],
        'UNPAID'       => ['group' => 'Άλλες', 'unit' => 'event', 'basis' => 'work', 'pay' => 'Χωρίς αποδοχές', 'doc' => 'Έγγραφη συμφωνία · ανάρτηση στο ΕΡΓΑΝΗ', 'law' => 'ΠΔ 62/2025 άρθ. 255',
                           'rule' => 'Με έγγραφη συμφωνία, έως 1 έτος (ανανεώσιμο). Αναστέλλεται η σύμβαση.'],
        'OTHER'        => ['group' => 'Άλλες', 'unit' => 'event', 'basis' => 'work', 'pay' => '', 'doc' => '', 'law' => '', 'rule' => 'Ό,τι δεν ταιριάζει σε άλλον τύπο — γράψε σημείωση.'],
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

        /* Προϋπηρεσία ΠΡΙΝ από εμάς, σε μήνες. Χωρίς αυτή δεν βγαίνει το
           δικαίωμα: ο Βάκρινος και η Αλεφαντή προσλήφθηκαν την ΙΔΙΑ ημέρα και
           δικαιούνται 26 και 25 αντίστοιχα, ακριβώς επειδή διαφέρει αυτό. */
        if (!$s->hasColumn('mod_cpm_leave_staff', 'prior_months')) {
            $s->table('mod_cpm_leave_staff', function ($t) {
                $t->integer('prior_months')->default(0);
                $t->tinyInteger('week_days')->default(5);   // πενθήμερο / εξαήμερο
            });
        }

        /* ΟΙΚΟΓΕΝΕΙΑΚΑ ΣΤΟΙΧΕΙΑ (22/10/2026): από αυτά βγαίνει το δικαίωμα στις ειδικές
           άδειες (γονική, σχολική, ασθένεια παιδιού, μονογονέα…). Ευαίσθητα — τα βλέπουν
           μόνο ο ίδιος και όσοι έχουν «Άδειες προσωπικού». */
        if (!$s->hasColumn('mod_cpm_leave_staff', 'children')) {
            $s->table('mod_cpm_leave_staff', function ($t) {
                $t->char('sex', 1)->nullable();
                $t->text('children')->nullable();           // JSON [{born, name, disabled, serious}]
                $t->tinyInteger('single_parent')->default(0);
                $t->tinyInteger('disabled')->default(0);    // αναπηρία ≥50% → +6 στην κανονική
                $t->tinyInteger('student')->default(0);     // σπουδαστής <25 → άδεια εξετάσεων
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

        /* ΜΕΤΑΦΟΡΑ. Δεν είναι λογιστική λεπτομέρεια — είναι ο λόγος που δύο έτη
           του αρχείου δεν έβγαιναν. Ημέρες περασμένου έτους που λήφθηκαν μέσα
           σε τούτο προσθέτουν στο διαθέσιμο, αλλιώς το άθροισμα ξεπερνά το
           δικαίωμα και μοιάζει με λάθος ενώ είναι νόμιμο. */
        if (!$s->hasColumn('mod_cpm_leave_years', 'carried_in')) {
            $s->table('mod_cpm_leave_years', function ($t) {
                $t->decimal('carried_in', 5, 2)->default(0);
            });
        }

        /* ΤΑΚΤΟΠΟΙΗΜΕΝΟ ΕΤΟΣ. Το Excel είχε έτη σημειωμένα «ΟΚ» χωρίς καμία
           αναλυτική εγγραφή — η άδεια δόθηκε, απλώς δεν καταγράφηκε αναλυτικά.
           Χωρίς αυτή τη σημαία το εργαλείο τα διάβαζε ως «δεν πήρε τίποτα» και
           έβγαζε ότι χρωστάμε 82 ημέρες σε έναν άνθρωπο. Κλειστό έτος ΔΕΝ
           μετράει στο συνολικό υπόλοιπο ούτε στις προθεσμίες. */
        if (!$s->hasColumn('mod_cpm_leave_years', 'settled')) {
            $s->table('mod_cpm_leave_years', function ($t) {
                $t->tinyInteger('settled')->default(0);
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
            $carry = (float) ($y->carried_in ?? 0);
            $out[(int) $y->year] = [
                'settled'   => (bool) ($y->settled ?? 0),
                'entitled'  => (float) $y->entitled_days,
                'carried'   => $carry,
                /* Διαθέσιμο = δικαίωμα + μεταφορά. Αυτό είναι το νούμερο πάνω
                   στο οποίο κρίνεται αν κάποιος «ξέφυγε», όχι το δικαίωμα. */
                'available' => (float) $y->entitled_days + $carry,
                'taken'     => 0.0,
                'remaining' => (float) $y->entitled_days + $carry,
                'legacy'    => $y->legacy_taken === null ? null : (float) $y->legacy_taken,
                'mismatch'  => false,
                'note'      => (string) $y->note,
            ];
        }
        /* Χωριστά το τι ήρθε από το Excel: μόνο ΑΥΤΟ συγκρίνεται με το
           `legacy_taken`. Αλλιώς η πρώτη νέα άδεια που καταχωρείς σε ένα
           παλιό έτος ανάβει ψεύτικη «ασυμφωνία» — το παλιό φύλλο δεν ήξερε
           για αυτήν, και δεν είναι λάθος να μην την ξέρει. */
        $imported = Capsule::table('mod_cpm_leaves')->where('staff_id', (int) $staffId)
            ->where('type', $type)->whereIn('status', self::COUNTS)
            ->whereNotNull('source_id')
            ->select('year', Capsule::raw('SUM(days) AS d'))->groupBy('year')
            ->pluck('d', 'year')->all();

        $rows = Capsule::table('mod_cpm_leaves')->where('staff_id', (int) $staffId)
            ->where('type', $type)->whereIn('status', self::COUNTS)
            ->select('year', Capsule::raw('SUM(days) AS d'))->groupBy('year')->get();
        foreach ($rows as $r) {
            $y = (int) $r->year;
            if (!isset($out[$y])) {
                /* Άδεια σε έτος χωρίς δηλωμένο δικαίωμα: δεν την κρύβουμε. */
                $out[$y] = ['settled' => false, 'entitled' => 0.0, 'carried' => 0.0, 'available' => 0.0,
                    'taken' => 0.0, 'remaining' => 0.0,
                    'legacy' => null, 'mismatch' => false, 'note' => ''];
            }
            $out[$y]['taken'] = (float) $r->d;
            $out[$y]['remaining'] = round($out[$y]['available'] - (float) $r->d, 2);
        }
        foreach ($out as $y => &$b) {
            /* Ασυμφωνία = το φύλλο διαφωνεί με τις ΕΙΣΑΓΜΕΝΕΣ εγγραφές και δεν
               εξηγείται από μεταφορά. Ό,τι καταχωρήθηκε από το εργαλείο μετά
               τη μεταφορά δεν συμμετέχει στη σύγκριση. */
            $imp = (float) ($imported[$y] ?? 0);
            if ($b['legacy'] !== null && abs($b['legacy'] - $imp) > 0.01
                && abs(round($b['available'] - $imp, 2)) > 0.01) {
                $b['mismatch'] = true;
            }
        }
        unset($b);
        ksort($out);
        return $out;
    }

    /**
     * Πόσες ημέρες ΠΡΟΒΛΕΠΕΙ Ο ΝΟΜΟΣ για αυτόν τον εργαζόμενο, αυτό το έτος.
     *
     * Κλίμακα πενθημέρου: 1ο ημερολογιακό έτος 20 (αναλογικά), 2ο 21 (αναλογικά),
     * 3ο και μετά 22. Με 10 έτη στον ίδιο εργοδότη Ή 12 συνολικής προϋπηρεσίας
     * γίνονται 25, και με 25 έτη συνολικής προϋπηρεσίας 26.
     *
     * ΠΡΟΤΑΣΗ, ΟΧΙ ΚΑΝΟΝΑΣ. Η μισθοδοσία έχει τον τελευταίο λόγο και το
     * `entitled_days` του έτους υπερισχύει πάντα — εδώ απλώς σταματάμε να
     * ξαναβγάζουμε το ίδιο νούμερο με το χέρι κάθε Ιανουάριο.
     *
     * @return array ['days' => float, 'why' => string]
     */
    public static function suggestEntitlement($staff, $year)
    {
        $hire = $staff->hire_date ?? null;
        if (!$hire) {
            return ['days' => 0.0, 'why' => 'χωρίς ημερομηνία πρόσληψης δεν υπολογίζεται'];
        }
        $h = new \DateTime($hire);
        $hy = (int) $h->format('Y');
        $ordinal = $year - $hy + 1;                       // 1ο, 2ο, 3ο… ημερολογιακό έτος
        if ($ordinal < 1) {
            return ['days' => 0.0, 'why' => 'πριν από την πρόσληψη'];
        }

        /* Συνολική προϋπηρεσία στο ΤΕΛΟΣ του έτους: ό,τι είχε φέρει μαζί του,
           συν όσο έχει δουλέψει εδώ. */
        $hereMonths  = max(0, ((int) $year - $hy) * 12 + (12 - (int) $h->format('n') + 1));
        $totalMonths = (int) ($staff->prior_months ?? 0) + $hereMonths;
        $hereYears   = intdiv($hereMonths, 12);
        $totalYears  = intdiv($totalMonths, 12);

        /* Η βάση: πρώτα η προϋπηρεσία, μετά η κλίμακα των πρώτων ετών. */
        if ($totalYears >= 25) {
            $base = 26.0; $why = 'πάνω από 25 έτη συνολικής προϋπηρεσίας';
        } elseif ($hereYears >= 10 || $totalYears >= 12) {
            $base = 25.0;
            $why = $hereYears >= 10 ? '10 έτη στην εταιρεία' : '12 έτη συνολικής προϋπηρεσίας';
        } elseif ($ordinal >= 3) {
            $base = 22.0; $why = 'από το 3ο ημερολογιακό έτος';
        } elseif ($ordinal === 2) {
            $base = 21.0; $why = '2ο ημερολογιακό έτος';
        } else {
            $base = 20.0; $why = '1ο ημερολογιακό έτος';
        }

        /* Εργαζόμενος με αναπηρία: +6 εργάσιμες στην κανονική (ΠΔ 62/2025 άρθ. 89 §4). */
        if (!empty($staff->disabled)) { $base += 6.0; $why .= ' + 6 (αναπηρία)'; }

        /* ΤΟ ΕΤΟΣ ΠΡΟΣΛΗΨΗΣ ΕΙΝΑΙ ΠΑΝΤΑ ΑΝΑΛΟΓΙΚΟ — ακόμη κι όταν ο εργαζόμενος
           δικαιούται 25 ή 26 λόγω προϋπηρεσίας. Δεν παίρνει ολόκληρη ετήσια
           άδεια για τρεις μήνες δουλειάς. */
        if ($ordinal === 1) {
            $months = 12 - (int) $h->format('n') + 1;
            return ['days' => (float) round($base * $months / 12),
                'why' => $why . ', αναλογικά για ' . $months . ' μήνες'];
        }
        return ['days' => $base, 'why' => $why];
    }

    /**
     * Ως πότε πρέπει να χορηγηθεί η άδεια ενός έτους δικαιώματος.
     *
     * Μέχρι 31 Μαρτίου του επόμενου έτους. Μετά, η αξίωση γίνεται ΧΡΗΜΑΤΙΚΗ με
     * προσαύξηση 100% συν επίδομα αδείας — δηλαδή μια ξεχασμένη ημέρα κοστίζει
     * διπλά. Γι' αυτό το εργαλείο προειδοποιεί αντί να περιμένει να το θυμηθεί
     * κάποιος.
     */
    public static function carryDeadline($year)
    {
        return ($year + 1) . '-03-31';
    }

    /** Υπόλοιπα που χάνονται αν δεν ληφθούν — με πόσες μέρες περιθώριο. */
    public static function expiring($staffId, $today = null)
    {
        $today = $today ?: date('Y-m-d');
        $out = [];
        foreach (self::balance($staffId) as $y => $b) {
            if (!empty($b['settled']) || $b['remaining'] <= 0.01) { continue; }
            $dl = self::carryDeadline($y);
            if ($dl < $today) {
                $out[] = ['year' => $y, 'days' => $b['remaining'], 'deadline' => $dl, 'left' => -1];
            } else {
                $left = (int) ((strtotime($dl) - strtotime($today)) / 86400);
                if ($left <= 120) {
                    $out[] = ['year' => $y, 'days' => $b['remaining'], 'deadline' => $dl, 'left' => $left];
                }
            }
        }
        return $out;
    }

    /** Το συνολικό υπόλοιπο κανονικής άδειας — αυτό που ρωτάει ο άνθρωπος. */
    public static function remainingTotal($staffId)
    {
        $sum = 0.0;
        foreach (self::balance($staffId) as $b) {
            if (!empty($b['settled'])) { continue; }
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

    /* ------------------------------------------------------------------ */
    /* ΕΙΔΙΚΕΣ ΑΔΕΙΕΣ: ΔΙΚΑΙΩΜΑ ΑΠΟ ΤΑ ΟΙΚΟΓΕΝΕΙΑΚΑ ΣΤΟΙΧΕΙΑ (22/10/2026)    */
    /* ------------------------------------------------------------------ */

    /** Τα παιδιά του εργαζομένου: [{born, name, disabled, serious}] — ευαίσθητα δεδομένα. */
    public static function children($staff)
    {
        $k = json_decode((string) ($staff->children ?? ''), true);
        return is_array($k) ? array_values(array_filter($k, function ($c) { return !empty($c['born']); })) : [];
    }

    /** Ηλικία σε πλήρη έτη σε μια ημερομηνία. */
    private static function age($born, $at)
    {
        return (int) (new \DateTime($born))->diff(new \DateTime($at))->y;
    }

    /**
     * Τι δικαιούται ΑΥΤΟΣ ο εργαζόμενος σε ΑΥΤΟ το ημερολογιακό έτος, ανά ειδική άδεια.
     * Επιστρέφει code => [entitled (null = χωρίς ετήσιο όριο), eligible, why, label, …meta,
     * taken, remaining]. Τα ποσά των ετήσιων (unit=year) μετρούν· των «ανά γεγονός»
     * δείχνονται ως κανόνας. Ό,τι γράφτηκε χειροκίνητα στο mod_cpm_leave_years υπερισχύει.
     */
    public static function special($staff, $year)
    {
        $y1 = $year . '-01-01'; $y2 = $year . '-12-31';
        $kids = self::children($staff);
        $sex = (string) ($staff->sex ?? '');
        $alive = array_filter($kids, function ($c) use ($y2) { return $c['born'] <= $y2; });
        $under16 = count(array_filter($alive, function ($c) use ($y1) { return self::age($c['born'], $y1) < 16 || !empty($c['disabled']); }));
        $school = count(array_filter($alive, function ($c) use ($y1, $y2) {
            return self::age($c['born'], $y2) >= 4 && (self::age($c['born'], $y1) <= 18 || !empty($c['disabled']));
        }));
        $under8 = count(array_filter($alive, function ($c) use ($y1) { return self::age($c['born'], $y1) < 8; }));
        $bornNow = count(array_filter($alive, function ($c) use ($y1, $y2) { return $c['born'] >= $y1 && $c['born'] <= $y2; }));
        $serious = count(array_filter($alive, function ($c) { return !empty($c['serious']); }));
        $svcM = $staff->hire_date ? (int) round(((strtotime($y2) - strtotime($staff->hire_date)) / 86400) / 30.44) : 0;
        $single = !empty($staff->single_parent);
        $n = count($alive);

        $r = [];
        $set = function ($code, $days, $eligible, $why) use (&$r) { $r[$code] = ['entitled' => $days, 'eligible' => $eligible, 'why' => $why]; };
        $set('SICK', null, true, 'χωρίς ετήσιο όριο — μετράμε όσες πήρες');
        $set('MATERNITY', 119, $sex !== 'M', $sex === 'M' ? 'μόνο για τη μητέρα' : '119 ημερολογιακές ανά τοκετό');
        $set('MAT_SPECIAL', 274, $sex !== 'M', $sex === 'M' ? 'έως 7 μήνες με μεταβίβαση από τη μητέρα' : '9 μήνες μετά τη μητρότητα');
        $set('PATERNITY', $bornNow ? 14 * $bornNow : 14, $bornNow > 0, $bornNow ? $bornNow . ' γέννηση φέτος × 14' : '14 εργάσιμες σε κάθε γέννηση/υιοθεσία');
        $set('PARENTAL', null, $under8 > 0 && $svcM >= 12, $under8 ? ($svcM >= 12 ? $under8 . ' παιδί κάτω των 8 · 4 μήνες ανά παιδί' . ($single ? ' (διπλάσια — μονογονέας)' : '') : 'θέλει 1 έτος υπηρεσίας') : 'για παιδί κάτω των 8');
        $set('CHILDCARE', null, $n > 0, $n ? '30 μήνες μειωμένο ωράριο μετά τη μητρότητα/γονική' : 'για γονείς');
        $set('SCHOOL', 4.0 * $school, $school > 0, $school ? $school . ' παιδί σχολικής ηλικίας × 4' : 'κανένα παιδί σχολικής ηλικίας');
        $set('CHILD_SICK', (float) ($under16 >= 3 ? 14 : ($under16 == 2 ? 8 : 6)), true,
            $under16 ? $under16 . ' παιδί κάτω των 16 → ' . ($under16 >= 3 ? 14 : ($under16 == 2 ? 8 : 6)) : 'για εξαρτώμενα μέλη (σύζυγος, γονείς)');
        $set('CHILD_SEV', $serious ? 10.0 : 0.0, $serious > 0, $serious ? 'παιδί με σοβαρό νόσημα' : 'μόνο για παιδί με σοβαρό νόσημα');
        $set('CHILD_HOSP', $n ? 30.0 : 0.0, $n > 0, $n ? 'όσο η νοσηλεία, έως 30' : 'για γονείς');
        $set('SINGLE_PAR', $single ? ($n >= 3 ? 8.0 : 6.0) : 0.0, $single, $single ? 'μονογονέας' . ($n >= 3 ? ' με 3+ παιδιά' : '') : 'μόνο για μονογονέα');
        $set('CARER', 5.0, $svcM >= 6, $svcM >= 6 ? '5 εργάσιμες τον χρόνο' : 'θέλει 6 μήνες υπηρεσίας');
        $set('FORCE_MAJ', 2.0, true, '2 φορές × 1 ημέρα');
        $set('MARRIAGE', 5, true, '5 εργάσιμες ανά γάμο');
        $set('BEREAVEMENT', 2, true, '2 ημέρες ανά απώλεια');
        $set('BEREAV_CHILD', 20, true, '20 ημέρες');
        $set('IVF', 7.0, true, '7 εργάσιμες');
        $set('PRENATAL', null, $sex !== 'M', 'σε ώρες, όταν χρειάζεται');
        $set('GYN', $sex !== 'M' ? 1.0 : 0.0, $sex !== 'M', '1 ημέρα τον χρόνο');
        $set('BLOOD', 1, true, 'η ημέρα κάθε αιμοδοσίας');
        $set('EXAMS', !empty($staff->student) ? 30.0 : 0.0, !empty($staff->student), !empty($staff->student) ? 'σπουδαστής κάτω των 25' : 'μόνο για σπουδαστές κάτω των 25');
        $set('ELECTION', 3, true, '1-3 ημέρες ανά εκλογές, ανάλογα με την απόσταση');
        $set('MILITARY', null, true, 'όσο διαρκεί');
        $set('UNION', null, true, 'ανάλογα με τη θέση');
        $set('UNPAID', null, true, 'με έγγραφη συμφωνία');

        /* Χειροκίνητη τιμή του έτους (μισθοδοσία / ΣΣΕ) υπερισχύει. */
        $ovr = Capsule::table('mod_cpm_leave_years')->where('staff_id', (int) $staff->id)->where('year', (int) $year)
            ->where('type', '!=', 'ANNUAL')->pluck('entitled_days', 'type')->all();
        $taken = Capsule::table('mod_cpm_leaves')->where('staff_id', (int) $staff->id)->where('year', (int) $year)
            ->where('type', '!=', 'ANNUAL')->whereIn('status', self::COUNTS)
            ->select('type', Capsule::raw('SUM(days) AS d'))->groupBy('type')->pluck('d', 'type')->all();
        foreach ($r as $code => &$x) {
            $m = self::META[$code];
            if (isset($ovr[$code])) { $x['entitled'] = (float) $ovr[$code]; $x['eligible'] = true; $x['why'] = 'ορίστηκε από τη μισθοδοσία'; $x['override'] = true; }
            $x['taken'] = (float) ($taken[$code] ?? 0);
            /* Υπόλοιπο μόνο για ΕΤΗΣΙΑ όρια· στα «ανά γεγονός» το νούμερο είναι κανόνας, όχι κουμπαράς. */
            $x['remaining'] = ($m['unit'] === 'year' && $x['entitled'] !== null) ? round($x['entitled'] - $x['taken'], 2) : null;
            $x += ['code' => $code, 'label' => self::typeLabel($code)] + $m;
        }
        unset($x);
        return $r;
    }

    /** Ημέρες μιας περιόδου κατά τη βάση του τύπου: ημερολογιακές ή εργάσιμες. */
    public static function daysFor($type, $from, $to)
    {
        if ((self::META[$type]['basis'] ?? 'work') === 'cal') {
            return (float) ((new \DateTime($from))->diff(new \DateTime($to))->days + 1);
        }
        return (float) self::suggestDays($from, $to);
    }

    /* ------------------------------------------------------------------ */
    /* ΑΠΟΥΣΙΑ ΩΣ ΦΡΑΓΜΟΣ (22/10/2026)                                     */
    /* ------------------------------------------------------------------ */
    /* Εγκεκριμένη άδεια δεν είναι μόνο γραμμή στο ημερολόγιο: όσο λείπει κάποιος
       ΔΕΝ του ανατίθεται εργασία, δεν του πάει η μπάλα, δεν καλείται σε σύσκεψη.
       Ο φραγμός ζει ΕΔΩ (ένας κανόνας) και τον καλούν όλα τα κυκλώματα. Ενεργός μόνο
       όταν το ζητήσει το api (χειριστής) — όχι σε cron/hooks του WHMCS, όπου ένα
       exception θα έσπαγε π.χ. την ανάθεση ticket. */
    public static $guard = false;

    /** Η εγκεκριμένη άδεια του χειριστή που πέφτει στο [from, to] — ή null. */
    public static function awayDuring($adminId, $from, $to = null)
    {
        $adminId = (int) $adminId;
        if (!$adminId || !$from) { return null; }
        $from = substr((string) $from, 0, 10);
        $to = $to ? substr((string) $to, 0, 10) : $from;
        if ($to < $from) { $to = $from; }
        return Capsule::table('mod_cpm_leaves as l')
            ->join('mod_cpm_leave_staff as s', 's.id', '=', 'l.staff_id')
            ->where('s.admin_id', $adminId)
            ->whereIn('l.status', ['approved', 'taken'])
            ->whereNotNull('l.date_from')
            ->where('l.date_from', '<=', $to)
            ->where(function ($q) use ($from) { $q->where('l.date_to', '>=', $from)->orWhereNull('l.date_to'); })
            ->orderBy('l.date_from')
            ->first(['l.id', 'l.type', 'l.date_from', 'l.date_to']);
    }

    /** Τρέχουσες & μελλοντικές εγκεκριμένες άδειες, ανά χειριστή — για τις λίστες επιλογής. */
    public static function awayMap($today = null)
    {
        $today = $today ?: date('Y-m-d');
        $out = [];
        foreach (Capsule::table('mod_cpm_leaves as l')
                ->join('mod_cpm_leave_staff as s', 's.id', '=', 'l.staff_id')
                ->whereIn('l.status', ['approved', 'taken'])->whereNotNull('l.date_from')
                ->where(function ($q) use ($today) { $q->where('l.date_to', '>=', $today)->orWhereNull('l.date_to'); })
                ->orderBy('l.date_from')
                ->get(['s.admin_id', 'l.type', 'l.date_from', 'l.date_to']) as $r) {
            $out[(int) $r->admin_id][] = ['from' => (string) $r->date_from,
                'to' => (string) ($r->date_to ?: $r->date_from), 'type' => self::typeLabel($r->type)];
        }
        return $out;
    }

    public static function spanText($r)
    {
        $f = date('d/m/Y', strtotime($r->date_from));
        $t = $r->date_to && $r->date_to !== $r->date_from ? date('d/m/Y', strtotime($r->date_to)) : '';
        return $t ? $f . ' – ' . $t : $f;
    }

    /**
     * Φραγμός ανάθεσης εργασίας / μπάλας. Μπλοκάρει αν ο άνθρωπος λείπει ΣΗΜΕΡΑ, ή αν
     * η έναρξη ή η προθεσμία της εργασίας πέφτουν μέσα στην άδειά του. Μια εργασία με
     * προθεσμία ΜΕΤΑ την επιστροφή του επιτρέπεται — θα τη δουλέψει όταν γυρίσει.
     */
    public static function guardTask($adminId, $start = null, $due = null, $what = 'να του ανατεθεί εργασία')
    {
        if (!self::$guard || !(int) $adminId) { return; }
        foreach (array_filter([date('Y-m-d'), $start, $due]) as $d) {
            $r = self::awayDuring($adminId, $d);
            if ($r) { throw new LeaveBlocked((int) $adminId, $r, $what, $d); }
        }
    }

    /** Φραγμός για συμμετοχή σε κάτι με διάρκεια (σύσκεψη, ραντεβού). */
    public static function guardRange($adminId, $from, $to, $what = 'να κληθεί σε σύσκεψη')
    {
        if (!self::$guard || !(int) $adminId) { return; }
        $r = self::awayDuring($adminId, $from, $to);
        if ($r) { throw new LeaveBlocked((int) $adminId, $r, $what, substr((string) $from, 0, 10)); }
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

/** Ο χειριστής είναι σε εγκεκριμένη άδεια — το api το γυρίζει ως 409 με καθαρό μήνυμα. */
class LeaveBlocked extends \RuntimeException
{
    public $adminId;
    public $leave;
    public function __construct($adminId, $leave, $what, $date)
    {
        $this->adminId = (int) $adminId;
        $this->leave = $leave;
        parent::__construct(Db::adminName($adminId) . ' είναι σε άδεια (' . Leave::typeLabel($leave->type) . ', '
            . Leave::spanText($leave) . ') — δεν μπορεί ' . $what . ' σε αυτό το διάστημα.');
    }
}
