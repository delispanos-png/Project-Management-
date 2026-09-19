<?php
/**
 * CloudOn Agent — η ΔΟΜΗ του τηλεφωνικού κέντρου ως κώδικας.
 *
 * ΤΟ ΝΟΗΜΑ: μέχρι σήμερα το 3CX ρυθμιζόταν με το χέρι, από πολλούς, χωρίς
 * ίχνος. Εδώ η επιθυμητή δομή (τμήματα, ωράρια, ουρές, AI ρεσεψιόν) γράφεται
 * ΜΙΑ φορά, το panel δείχνει τι διαφέρει από το ζωντανό PBX, και η εφαρμογή
 * γίνεται βήμα-βήμα με καταγραφή. Αν κάποιος αλλάξει κάτι στο 3CX, το
 * «Έλεγχος» το δείχνει την επόμενη μέρα.
 *
 * Η ΔΡΟΜΟΛΟΓΗΣΗ των εισερχομένων (ποιο νούμερο πέφτει πού) είναι ΞΕΧΩΡΙΣΤΟ
 * βήμα, με risk='route': δεν εφαρμόζεται ποτέ μαζί με τα υπόλοιπα, μόνο ρητά.
 *
 * ΜΕΤΡΗΜΕΝΑ (19/09/2026): PATCH σε Groups/Users επιτρέπεται από τον ρόλο.
 * Οι κανόνες «BasedOnDID» δεν ταιριάζουν ποτέ (το Did έρχεται ως «Cloud2020On»)
 * — όλες οι κλήσεις πέφτουν στους «ForwardAll» κανόνες #12 (Sip1) και #13 (Cyprus).
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

class Pbx3cxBlueprint
{
    /* Σταθερά αναγνωριστικά του ΔΙΚΟΥ ΜΑΣ PBX — δεν είναι γενικός κώδικας. */
    const G_CLOUDON = 102;     // προεπιλεγμένο τμήμα — όλοι
    const G_EMERG   = 205;     // έκτακτη ανάγκη — 201, 202
    const AI_DN     = '902';   // AI ρεσεψιόν
    const AI_ID     = 218;
    const TICKET_DN = '900';   // «CloudOn, Support» — το voicemail του γίνεται αίτημα
    const TICKET_ID = 210;
    const TICKET_MAIL = 'support@cloudon.gr';
    const RULES_ALL = [12, 13];            // ForwardAll: Sip1.CloudOn.gr, Cyprus
    const SCRIPT_DN = '806';               // το παλιό call script — μένει ως εφεδρεία

    /** Παλιά τμήματα που καταργούνται. Ό,τι έχουν μέσα μεταφέρεται στο CloudOn. */
    const OLD_GROUPS = [110 => 'Reception two', 111 => 'Sales Department',
        114 => 'Technical Department', 116 => 'PharmacyOne Cyprus',
        181 => 'Reception one', 209 => 'Support'];

    const EMERG_MEMBERS = ['201', '202', '804'];

    /** Ο ιδιοκτήτης. Ρόλοι που δίνει το 3CX: users, receptionists, group_admins,
        managers, group_owners, system_admins, system_owners (ο ανώτατος). */
    const OWNER_DN   = '201';
    const OWNER_ROLE = 'system_owners';

    /** Μοντέλο φωνής (realtime). Διαθέσιμα στο PBX: gpt-realtime-2.1, -2, -1.5, -2.1-mini. */
    const AI_REALTIME = 'gpt-realtime-2.1';

    /** Ποιοι σηκώνουν το Support. Το «CloudOn» είναι όλοι οι υπόλοιποι. */
    const AGENTS_SUPPORT = ['305', '223', '221', '220', '212', '304'];
    const AGENTS_CLOUDON = ['201', '202', '203', '204'];

    /* ─────────────────────────── επιθυμητή δομή ─────────────────────────── */

    private static function hours(array $periods)
    {
        $out = [];
        foreach ($periods as $day => $span) {
            $out[] = ['DayOfWeek' => $day, 'Start' => $span[0] . ':00', 'Stop' => $span[1] . ':00'];
        }
        return ['Type' => 'SpecificHoursExcludingHolidays', 'IgnoreHolidays' => false, 'Periods' => $out];
    }

    public static function cloudonHours()
    {
        $w = ['09:00', '17:00'];
        return self::hours(['Monday' => $w, 'Tuesday' => $w, 'Wednesday' => $w, 'Thursday' => $w, 'Friday' => $w]);
    }

    public static function emergencyHours()
    {
        $w = ['17:01', '20:00'];
        return self::hours(['Monday' => $w, 'Tuesday' => $w, 'Wednesday' => $w, 'Thursday' => $w,
            'Friday' => $w, 'Saturday' => ['09:30', '14:00']]);
    }

    private static function dest($to, $number = '')
    {
        return ['To' => $to, 'Number' => (string) $number, 'External' => ''];
    }

    private static function route($to, $number = '')
    {
        return ['IsPromptEnabled' => false, 'Prompt' => '', 'Route' => self::dest($to, $number)];
    }

    /**
     * Οι ουρές όπως πρέπει να είναι. Κλειδί = αριθμός.
     *
     * ΔΥΟ ουρές (απόφαση 19/09/2026): «Support» και «CloudOn». Η AI ρεσεψιόν
     * στέλνει ΠΑΝΤΑ στο Support μέσα στο ωράριο· εκτός ωραρίου η ουρά η ίδια
     * προωθεί στο Emergency (OutOfOfficeRoute), και το Emergency εκτός του
     * δικού του ωραρίου στο κουτί αιτημάτων (voicemail 900). Οι παλιές ουρές
     * (800/801/802/803/807) μένουν ως έχουν — δεύτερο βήμα.
     */
    public static function queues()
    {
        $vmTicket = self::dest('VoiceMail', self::TICKET_DN);
        return [
            '810' => ['Name' => 'Support', 'PollingStrategy' => 'LongestWaiting', 'RingTimeout' => 20,
                'MasterTimeout' => 120, 'Agents' => self::AGENTS_SUPPORT, 'Managers' => ['201'],
                'ForwardNoAnswer' => $vmTicket, 'OutOfOfficeRoute' => self::route('Queue', '804'),
                'HolidaysRoute' => self::route('Queue', '804'), 'AnnounceQueuePosition' => true],
            '811' => ['Name' => 'CloudOn', 'PollingStrategy' => 'LongestWaiting', 'RingTimeout' => 20,
                'MasterTimeout' => 120, 'Agents' => self::AGENTS_CLOUDON, 'Managers' => ['201'],
                'ForwardNoAnswer' => $vmTicket, 'OutOfOfficeRoute' => self::route('Queue', '804'),
                'HolidaysRoute' => self::route('Queue', '804'), 'AnnounceQueuePosition' => true],
            '804' => ['Name' => 'Emergency', 'Agents' => ['201', '202'], 'Managers' => ['201'],
                'ForwardNoAnswer' => $vmTicket, 'OutOfOfficeRoute' => self::route('VoiceMail', self::TICKET_DN),
                'HolidaysRoute' => self::route('VoiceMail', self::TICKET_DN)],
        ];
    }

    /** Η AI ρεσεψιόν — τι λέει, πού στέλνει. */
    public static function agent()
    {
        $dir = function ($num, $name, $type, $descr) {
            return ['Number' => (string) $num, 'Name' => $name, 'Type' => $type, 'Description' => $descr, 'Tags' => []];
        };
        return [
            'DisplayName' => 'CloudOn, Receptionist',
            'AgentSettings' => [
                'Language' => 'Greek',
                'Voice' => 'marin',
                'MaxCallDuration' => 420,
                'AgentType' => 'receptionist',
                'FirstMessage' => 'Καλέσατε την CloudOn. Είμαι η ψηφιακή ρεσεψιόν. Πείτε μου σε τι μπορώ να βοηθήσω.',
                'SystemPrompt' => self::systemPrompt(),
                'RoutingDirectory' => [
                    $dir('810', 'Support', 'Queue', 'Τεχνική υποστήριξη: SoftOne, PharmacyOne, server, δίκτυο, internet, email, τηλεφωνία, πρόβλημα, σφάλμα, δεν δουλεύει, ticket'),
                    $dir('811', 'CloudOn', 'Queue', 'Γενικά: λογιστήριο, τιμολόγια, πληρωμές, πωλήσεις, προσφορά, νέος πελάτης, πληροφορίες, CarOn, οτιδήποτε μη τεχνικό'),
                    $dir('804', 'Emergency', 'Queue', 'Έκτακτη ανάγκη εκτός ωραρίου: η επιχείρηση σταμάτησε, δεν εκδίδονται αποδείξεις, δεν λειτουργεί καθόλου το σύστημα'),
                    $dir(self::TICKET_DN, 'CloudOn, Support', 'Extension', 'Καταχώρηση αιτήματος: επανάκληση ή μήνυμα που γίνεται ticket'),
                ],
                'HumanHandoff' => $dir('811', 'CloudOn', 'Queue', 'Άνθρωπος της CloudOn'),
                'AgentFallback' => ['Number' => self::TICKET_DN, 'Action' => 'transfer', 'Tags' => []],
                'CheckStatusBeforeTransfer' => true,
                'EnableNameMatching' => true,
                'SpamInstructions' => 'Τηλεπωλήσεις, αυτόματες κλήσεις, απάτες, ύποπτοι που ζητούν πληρωμές, κωδικούς ή απομακρυσμένη πρόσβαση.',
            ],
        ];
    }

    public static function systemPrompt()
    {
        return <<<'TXT'
# Ρόλος
- Είσαι η ψηφιακή ρεσεψιόν της CloudOn. Μιλάς ελληνικά. Αν ο καλών μιλήσει αγγλικά, συνέχισε στα αγγλικά.
- Σκοπός σου: να καταλάβεις γρήγορα τι χρειάζεται ο καλών και να τον συνδέσεις με άνθρωπο της ομάδας.
- Σύντομες προτάσεις, ευγενικός και επαγγελματικός τόνος. Μία ερώτηση κάθε φορά. Δεν δίνεις τεχνικές οδηγίες και δεν λύνεις προβλήματα εσύ.

# Φωνή και ύφος
- Μίλα όπως μια πραγματική, ευγενική ρεσεψιονίστ στο τηλέφωνο: ζεστά, ήρεμα, με φυσικό ρυθμό και μικρές παύσεις. Όχι μονότονα, όχι βιαστικά, όχι σαν εκφωνητής.
- Καθημερινά ελληνικά, απλές λέξεις. Πες «Μάλιστα», «Βεβαίως», «Μισό λεπτό» όπου ταιριάζει. Μη διαβάζεις λίστες επιλογών σαν μενού.
- Άφησε τον καλούντα να μιλήσει και να ολοκληρώσει. Μην τον διακόπτεις. Αν δεν κατάλαβες, ζήτα ευγενικά να το ξαναπεί.
- Προφέρε σωστά τα ονόματα προϊόντων: «Σοφτ-Ουάν» (SoftOne), «Φάρμασι-Ουάν» (PharmacyOne), «Κλάουντ-Ον» (CloudOn), «Καρ-Ον» (CarOn).

# Η εταιρεία
CloudOn: υπηρεσίες πληροφορικής σε Ελλάδα και Κύπρο. SoftOne ERP, PharmacyOne (λογισμικό φαρμακείου), cloud, servers, δίκτυα, τηλεφωνία VoIP, CarOn, e-commerce.
Ωράριο: Δευτέρα έως Παρασκευή 09:00 έως 17:00. Έκτακτη υποστήριξη: Δευτέρα έως Παρασκευή 17:01 έως 20:00 και Σάββατο 09:30 έως 14:00.

# Ροή κλήσης
1. Ρώτα σε τι μπορείς να βοηθήσεις. Αν δεν τα έχει πει, ζήτα όνομα και επιχείρηση. Μην ξαναρωτάς κάτι που ήδη είπε.
2. Πες «Σας συνδέω με την υποστήριξη» και σύνδεσέ τον.

# Πού συνδέεις
- Μέσα στο ωράριο, κάθε τεχνικό θέμα (SoftOne, PharmacyOne, server, δίκτυο, internet, email, τηλεφωνία, σφάλμα, δεν δουλεύει) → Support.
- Λογιστήριο, τιμολόγια, πληρωμές, πωλήσεις, προσφορά, νέος πελάτης, πληροφορίες, CarOn, οτιδήποτε μη τεχνικό → CloudOn.
- Ζητά συγκεκριμένο συνεργάτη με το όνομά του → σύνδεσέ τον απευθείας.
- Δεν καταλαβαίνεις μετά από δύο προσπάθειες → CloudOn.

# Όταν οι άνθρωποι του Support είναι απασχολημένοι ή δεν απαντούν
- Πες ότι όλοι οι συνεργάτες της υποστήριξης είναι απασχολημένοι αυτή τη στιγμή.
- Πρότεινε επανάκληση: πάρε όνομα, επιχείρηση, τηλέφωνο επιστροφής (επιβεβαίωσε αν είναι ο αριθμός από τον οποίο καλεί) και σύντομη περιγραφή του θέματος.
- Μετά σύνδεσέ τον στο «Καταχώρηση αιτήματος» και πες του να αφήσει το μήνυμά του μετά τον ήχο. Πες ότι θα τον καλέσουμε εμείς εντός του ωραρίου.

# Εκτός ωραρίου
- Δευτέρα έως Παρασκευή 17:01 έως 20:00 και Σάββατο 09:30 έως 14:00: κάθε τεχνικό θέμα → Emergency αντί για Support. Μη τεχνικά θέματα → Καταχώρηση αιτήματος.
- Τις υπόλοιπες ώρες (νύχτα, Κυριακή, αργίες) → Καταχώρηση αιτήματος. Πες ότι θα απαντήσουμε την επόμενη εργάσιμη ημέρα.

# Κανόνες
- Μην υπόσχεσαι χρόνους αποκατάστασης ή τιμές.
- Μη δίνεις τηλέφωνα ή προσωπικά στοιχεία συνεργατών.
- Τηλεπωλήσεις και spam: ευγενικά τερμάτισε την κλήση.
TXT;
    }

    /* ─────────────────────────── ανάγνωση ζωντανής κατάστασης ─────────────────────────── */

    private static function live()
    {
        $L = [];
        $g = Pbx3cxClient::xapi('Groups', ['$top' => 40, '$select' => 'Id,Name,IsDefault,Hours',
            '$expand' => 'Members($select=Id,Number,Type)']);
        foreach ($g['value'] ?? [] as $r) { $L['groups'][(int) $r['Id']] = $r; }
        $u = Pbx3cxClient::xapi('Users', ['$top' => 100, '$select' => 'Id,Number,DisplayName,PrimaryGroupId,EmailAddress,VMEnabled,VMEmailOptions,TranscriptionMode',
            '$expand' => 'Groups($select=GroupId;$expand=Rights($select=RoleName))']);
        foreach ($u['value'] ?? [] as $r) { $L['users'][(string) $r['Number']] = $r; }
        $q = Pbx3cxClient::xapi('Queues', ['$top' => 40, '$select' => 'Id,Number,Name,PollingStrategy,RingTimeout,MasterTimeout,ForwardNoAnswer,OutOfOfficeRoute,HolidaysRoute,AnnounceQueuePosition',
            '$expand' => 'Agents($select=Number),Managers($select=Number),Groups($select=GroupId)']);
        foreach ($q['value'] ?? [] as $r) { $L['queues'][(string) $r['Number']] = $r; }
        $rg = Pbx3cxClient::xapi('RingGroups', ['$top' => 20, '$select' => 'Id,Number', '$expand' => 'Groups($select=GroupId)']);
        foreach ($rg['value'] ?? [] as $r) { $L['rings'][(string) $r['Number']] = $r; }
        $ai = Pbx3cxClient::xapi('Users', ['$top' => 1, '$filter' => "Number eq '" . self::AI_DN . "'",
            '$select' => 'Id,Number,DisplayName,AgentSettings']);
        $L['agent'] = $ai['value'][0] ?? null;
        $L['ai_settings'] = Pbx3cxClient::xapi('AISettings');
        $ir = Pbx3cxClient::xapi('InboundRules', ['$top' => 40,
            '$select' => 'Id,Condition,OfficeHoursDestination,OutOfOfficeHoursDestination,HolidaysDestination',
            '$expand' => 'TrunkDN($select=Number,Name)']);
        foreach ($ir['value'] ?? [] as $r) { $L['rules'][(int) $r['Id']] = $r; }
        return $L;
    }

    private static function periodsText(array $hours)
    {
        $d = ['Monday' => 'Δε', 'Tuesday' => 'Τρ', 'Wednesday' => 'Τε', 'Thursday' => 'Πε', 'Friday' => 'Πα', 'Saturday' => 'Σα', 'Sunday' => 'Κυ'];
        $o = [];
        foreach ($hours['Periods'] ?? [] as $p) {
            $o[] = ($d[$p['DayOfWeek']] ?? $p['DayOfWeek']) . ' ' . substr($p['Start'], 0, 5) . '-' . substr($p['Stop'], 0, 5);
        }
        return $o ? implode(', ', $o) : 'χωρίς ωράριο';
    }

    private static function sameHours(array $a, array $b)
    {
        $n = function (array $h) {
            $p = [];
            foreach ($h['Periods'] ?? [] as $x) {
                /* «Sat 00:00-00:01» = ψευδο-κλειστό της παλιάς ρύθμισης — μετράει ως τίποτα. */
                if (substr($x['Start'], 0, 5) === '00:00' && substr($x['Stop'], 0, 5) <= '00:02') { continue; }
                $p[] = $x['DayOfWeek'] . substr($x['Start'], 0, 5) . substr($x['Stop'], 0, 5);
            }
            sort($p);
            return ($h['Type'] ?? '') . '|' . implode(',', $p);
        };
        return $n($a) === $n($b);
    }

    private static function memberNumbers(array $group)
    {
        return array_map(function ($m) { return (string) $m['Number']; }, $group['Members'] ?? []);
    }

    private static function groupIds(array $dn)
    {
        return array_map(function ($g) { return (int) $g['GroupId']; }, $dn['Groups'] ?? []);
    }

    /** Ο ρόλος ενός DN μέσα σε ένα τμήμα ('' αν δεν είναι μέλος). */
    private static function roleIn(array $dn, $gid)
    {
        foreach ($dn['Groups'] ?? [] as $g) {
            if ((int) $g['GroupId'] === (int) $gid) { return (string) ($g['Rights']['RoleName'] ?? ''); }
        }
        return '';
    }

    /* ─────────────────────────── τα βήματα ─────────────────────────── */

    /**
     * Κάθε βήμα: key, label, risk ('low' ή 'route'), check(L) → [state, detail],
     * apply(L). state: ok | change | error. Η σειρά ΕΧΕΙ σημασία (πρώτα τα μέλη,
     * μετά οι διαγραφές).
     */
    private static function steps()
    {
        $S = [];

        /* 1. Τμήμα CloudOn: ωράριο 9-17 + ΟΛΟΙ μέσα (άνθρωποι, ουρές, ring groups). */
        $S[] = ['key' => 'g_cloudon', 'label' => 'Τμήμα «CloudOn» — ωράριο 09:00-17:00, όλα τα εσωτερικά',
            'risk' => 'low',
            'check' => function ($L) {
                $g = $L['groups'][self::G_CLOUDON] ?? null;
                if (!$g) { return ['error', 'Δεν βρέθηκε το τμήμα #' . self::G_CLOUDON]; }
                $d = [];
                if (!self::sameHours($g['Hours'] ?? [], self::cloudonHours())) {
                    $d[] = 'ωράριο: ' . self::periodsText($g['Hours'] ?? []) . ' → Δε-Πα 09:00-17:00';
                }
                $miss = self::missingFromCloudon($L);
                if ($miss) { $d[] = 'λείπουν: ' . implode(', ', $miss); }
                return [$d ? 'change' : 'ok', $d ? implode(' · ', $d) : 'ωράριο σωστό · ' . count(self::memberNumbers($g)) . ' μέλη'];
            },
            'apply' => function ($L) {
                $g = $L['groups'][self::G_CLOUDON];
                if (!self::sameHours($g['Hours'] ?? [], self::cloudonHours())) {
                    Pbx3cxClient::xwrite('PATCH', 'Groups(' . self::G_CLOUDON . ')', ['Hours' => self::cloudonHours()]);
                }
                foreach (self::missingFromCloudon($L) as $num) { self::addToGroup($L, $num, self::G_CLOUDON); }
            }];

        /* 2. Τμήμα Emergency: 17:01-20:00 + Σάββατο 09:30-14:00, μέλη 201/202/804. */
        $S[] = ['key' => 'g_emerg', 'label' => 'Τμήμα «Emergency» — Δε-Πα 17:01-20:00, Σα 09:30-14:00, μόνο 201 & 202',
            'risk' => 'low',
            'check' => function ($L) {
                $g = $L['groups'][self::G_EMERG] ?? null;
                if (!$g) { return ['error', 'Δεν βρέθηκε το τμήμα #' . self::G_EMERG]; }
                $d = [];
                if ($g['Name'] !== 'Emergency') { $d[] = 'όνομα «' . $g['Name'] . '» → «Emergency»'; }
                if (!self::sameHours($g['Hours'] ?? [], self::emergencyHours())) {
                    $d[] = 'ωράριο: ' . self::periodsText($g['Hours'] ?? []) . ' → Δε-Πα 17:01-20:00, Σα 09:30-14:00';
                }
                $have = self::memberNumbers($g);
                $miss = array_diff(self::EMERG_MEMBERS, $have);
                $extra = array_diff($have, self::EMERG_MEMBERS);
                if ($miss) { $d[] = 'λείπουν: ' . implode(', ', $miss); }
                if ($extra) { $d[] = 'περισσεύουν: ' . implode(', ', $extra); }
                return [$d ? 'change' : 'ok', $d ? implode(' · ', $d) : 'σωστό'];
            },
            'apply' => function ($L) {
                $g = $L['groups'][self::G_EMERG];
                $body = [];
                if ($g['Name'] !== 'Emergency') { $body['Name'] = 'Emergency'; }
                if (!self::sameHours($g['Hours'] ?? [], self::emergencyHours())) { $body['Hours'] = self::emergencyHours(); }
                if ($body) { Pbx3cxClient::xwrite('PATCH', 'Groups(' . self::G_EMERG . ')', $body); }
                $have = self::memberNumbers($g);
                foreach (array_diff(self::EMERG_MEMBERS, $have) as $num) { self::addToGroup($L, $num, self::G_EMERG); }
                foreach (array_diff($have, self::EMERG_MEMBERS) as $num) { self::removeFromGroup($L, $num, self::G_EMERG); }
            }];

        /* 2β. Ο 201 είναι Ιδιοκτήτης (system_owners) σε ΟΛΑ τα τμήματα — ο ρόλος
           ζει στη σχέση χρήστη-τμήματος (UserGroup.Rights.RoleName), όχι στον χρήστη. */
        $S[] = ['key' => 'owner_201', 'label' => 'Ο 201 Ιδιοκτήτης (System Owner) σε CloudOn και Emergency',
            'risk' => 'low',
            'check' => function ($L) {
                $u = $L['users'][self::OWNER_DN] ?? null;
                if (!$u) { return ['error', 'Δεν βρέθηκε το ' . self::OWNER_DN]; }
                $d = [];
                foreach ([self::G_CLOUDON => 'CloudOn', self::G_EMERG => 'Emergency'] as $gid => $nm) {
                    $role = self::roleIn($u, $gid);
                    if ($role !== self::OWNER_ROLE) { $d[] = $nm . ': «' . ($role ?: 'εκτός') . '» → ' . self::OWNER_ROLE; }
                }
                return [$d ? 'change' : 'ok', $d ? implode(' · ', $d) : 'ιδιοκτήτης και στα δύο'];
            },
            'apply' => function ($L) {
                $u = $L['users'][self::OWNER_DN];
                $groups = [];
                foreach (self::groupIds($u) as $gid) {
                    $g = ['GroupId' => $gid];
                    if (in_array($gid, [self::G_CLOUDON, self::G_EMERG], true)) { $g['Rights'] = ['RoleName' => self::OWNER_ROLE]; }
                    $groups[] = $g;
                }
                Pbx3cxClient::xwrite('PATCH', 'Users(' . (int) $u['Id'] . ')', ['Groups' => $groups]);
            }];

        /* 3. Νέες ουρές + διορθώσεις υπαρχουσών. */
        foreach (self::queues() as $num => $want) {
            $S[] = ['key' => 'q_' . $num, 'label' => 'Ουρά ' . $num . ' «' . $want['Name'] . '»',
                'risk' => 'low',
                'check' => function ($L) use ($num, $want) {
                    $q = $L['queues'][$num] ?? null;
                    if (!$q) { return ['change', 'δεν υπάρχει — θα δημιουργηθεί με ' . count($want['Agents'] ?? []) . ' χειριστές']; }
                    $d = self::queueDiff($q, $want);
                    return [$d ? 'change' : 'ok', $d ? implode(' · ', $d) : 'σωστή · χειριστές: ' . implode(',', array_map(function ($a) { return $a['Number']; }, $q['Agents'] ?? []))];
                },
                'apply' => function ($L) use ($num, $want) {
                    $q = $L['queues'][$num] ?? null;
                    $body = self::queueBody($want, $q);
                    if (!$q) {
                        $body['Number'] = (string) $num;
                        $body['Groups'] = [['GroupId' => self::G_CLOUDON]];
                        Pbx3cxClient::xwrite('POST', 'Queues', $body);
                    } elseif ($body) {
                        Pbx3cxClient::xwrite('PATCH', 'Queues(' . (int) $q['Id'] . ')', $body);
                    }
                }];
        }

        /* 4. Το «κουτί αιτημάτων»: voicemail του 900 → email υποστήριξης, με απομαγνητοφώνηση. */
        $S[] = ['key' => 'dn_ticket', 'label' => 'Εσωτερικό 900 — το μήνυμα γίνεται αίτημα (email ' . self::TICKET_MAIL . ', απομαγνητοφώνηση)',
            'risk' => 'low',
            'check' => function ($L) {
                $u = $L['users'][self::TICKET_DN] ?? null;
                if (!$u) { return ['error', 'Δεν βρέθηκε το 900']; }
                $d = [];
                if (mb_strtolower((string) $u['EmailAddress']) !== self::TICKET_MAIL) { $d[] = 'email «' . $u['EmailAddress'] . '» → ' . self::TICKET_MAIL; }
                if (empty($u['VMEnabled'])) { $d[] = 'voicemail ανενεργό'; }
                if (($u['VMEmailOptions'] ?? '') !== 'Attachment') { $d[] = 'επιλογή email «' . $u['VMEmailOptions'] . '» → Attachment'; }
                if (($u['TranscriptionMode'] ?? '') !== 'Voicemail') { $d[] = 'απομαγνητοφώνηση «' . $u['TranscriptionMode'] . '» → Voicemail'; }
                if ((int) $u['PrimaryGroupId'] !== self::G_CLOUDON) { $d[] = 'κύριο τμήμα → CloudOn'; }
                return [$d ? 'change' : 'ok', $d ? implode(' · ', $d) : 'σωστό'];
            },
            'apply' => function ($L) {
                $u = $L['users'][self::TICKET_DN];
                $body = ['EmailAddress' => self::TICKET_MAIL, 'VMEnabled' => true, 'VMEmailOptions' => 'Attachment',
                    'TranscriptionMode' => 'Voicemail', 'SendEmailMissedCalls' => false];
                if (!in_array(self::G_CLOUDON, self::groupIds($u), true)) { self::addToGroup($L, self::TICKET_DN, self::G_CLOUDON); }
                $body['PrimaryGroupId'] = self::G_CLOUDON;
                Pbx3cxClient::xwrite('PATCH', 'Users(' . (int) $u['Id'] . ')', $body);
            }];

        /* 5. Η AI ρεσεψιόν. */
        $S[] = ['key' => 'ai', 'label' => 'AI ρεσεψιόν (902) — ελληνικά, ροή και προορισμοί',
            'risk' => 'low',
            'check' => function ($L) {
                $a = $L['agent'];
                if (!$a) { return ['error', 'Δεν βρέθηκε το 902']; }
                $d = self::agentDiff($a);
                return [$d ? 'change' : 'ok', $d ? implode(' · ', $d) : 'ίδιο με το σχέδιο'];
            },
            'apply' => function ($L) {
                $want = self::agent();
                $cur = $L['agent']['AgentSettings'] ?? [];
                /* Στέλνουμε ΟΛΟ το AgentSettings: ό,τι δεν ορίζουμε μένει όπως ήταν. */
                $as = array_merge($cur, $want['AgentSettings']);
                Pbx3cxClient::xwrite('PATCH', 'Users(' . self::AI_ID . ')', ['DisplayName' => $want['DisplayName'], 'AgentSettings' => $as]);
            }];

        /* 5β. Το μοντέλο φωνής ΟΛΩΝ των AI agents (ρύθμιση συστήματος). Το παλιό
           «gpt-realtime» ακούγεται μηχανικό· το 2.1 είναι το πιο φυσικό που δίνει το PBX. */
        $S[] = ['key' => 'ai_model', 'label' => 'Μοντέλο φωνής AI → ' . self::AI_REALTIME,
            'risk' => 'low',
            'check' => function ($L) {
                $cur = (string) ($L['ai_settings']['RealtimeModel'] ?? '');
                return [$cur === self::AI_REALTIME ? 'ok' : 'change', $cur === self::AI_REALTIME ? 'σωστό' : '«' . $cur . '» → ' . self::AI_REALTIME];
            },
            'apply' => function ($L) {
                Pbx3cxClient::xwrite('PATCH', 'AISettings', ['RealtimeModel' => self::AI_REALTIME]);
            }];

        /* 6. Καθάρισμα παλιών τμημάτων — ΜΟΝΟ αφού όλοι είναι στο CloudOn. */
        $S[] = ['key' => 'g_cleanup', 'label' => 'Κατάργηση παλιών τμημάτων (' . implode(', ', self::OLD_GROUPS) . ')',
            'risk' => 'low',
            'check' => function ($L) {
                $left = [];
                foreach (self::OLD_GROUPS as $id => $nm) { if (isset($L['groups'][$id])) { $left[] = $nm; } }
                if (!$left) { return ['ok', 'καταργήθηκαν']; }
                $miss = self::missingFromCloudon($L);
                return ['change', 'υπάρχουν ακόμη: ' . implode(', ', $left) . ($miss ? ' — πρώτα πρέπει να μπουν στο CloudOn: ' . implode(', ', $miss) : '')];
            },
            'apply' => function ($L) {
                if (self::missingFromCloudon($L)) { throw new \RuntimeException('Πρώτα το βήμα «Τμήμα CloudOn» — υπάρχουν μέλη εκτός'); }
                /* Κύριο τμήμα → CloudOn για όποιον το είχε σε τμήμα που φεύγει. */
                foreach ($L['users'] as $num => $u) {
                    if (isset(self::OLD_GROUPS[(int) $u['PrimaryGroupId']])) {
                        Pbx3cxClient::xwrite('PATCH', 'Users(' . (int) $u['Id'] . ')', ['PrimaryGroupId' => self::G_CLOUDON]);
                    }
                }
                foreach (self::OLD_GROUPS as $id => $nm) {
                    if (!isset($L['groups'][$id])) { continue; }
                    /* ΜΕΤΡΗΘΗΚΕ: DELETE σε τμήμα με μέλη → 400 GROUP_WITH_MEMBERS_CANNOT_BE_DELETED.
                       Πρώτα βγαίνουν ΟΛΑ τα μέλη (είναι ήδη στο CloudOn), μετά σβήνει. */
                    foreach (self::memberNumbers($L['groups'][$id]) as $num) {
                        try { self::removeFromGroup($L, $num, $id); }
                        catch (\Throwable $e) { Pbx3cxClient::log('blueprint', 'error', 'Μέλος ' . $num . ' του #' . $id . ': ' . $e->getMessage()); }
                    }
                    Pbx3cxClient::xwrite('DELETE', 'Groups(' . $id . ')');
                    Pbx3cxClient::log('blueprint', 'ok', 'Καταργήθηκε το τμήμα «' . $nm . '» (#' . $id . ')');
                    /* Μετά τη διαγραφή τα Groups κάθε DN έχουν αλλάξει — αν στείλουμε
                       τη σκαληνή λίστα, το επόμενο PATCH απαντά NOT_FOUND. Ξαναδιάβασε. */
                    $L = self::live();
                }
            }];

        /* 7. ΔΡΟΜΟΛΟΓΗΣΗ — ξεχωριστό, ρητό βήμα. Όλες οι εισερχόμενες → AI ρεσεψιόν. */
        $S[] = ['key' => 'route_ai', 'label' => 'Δρομολόγηση εισερχομένων → AI ρεσεψιόν (902) αντί για το script 806',
            'risk' => 'route',
            'check' => function ($L) {
                $d = []; $ok = [];
                foreach (self::RULES_ALL as $id) {
                    $r = $L['rules'][$id] ?? null;
                    if (!$r) { $d[] = 'κανόνας #' . $id . ' δεν βρέθηκε'; continue; }
                    $to = ($r['OfficeHoursDestination']['To'] ?? '') . ' ' . ($r['OfficeHoursDestination']['Number'] ?? '');
                    $trunk = $r['TrunkDN']['Name'] ?? ('#' . $id);
                    if (($r['OfficeHoursDestination']['Number'] ?? '') === self::AI_DN) { $ok[] = $trunk; }
                    else { $d[] = $trunk . ': ' . trim($to) . ' → Extension ' . self::AI_DN; }
                }
                return [$d ? 'change' : 'ok', $d ? implode(' · ', $d) : 'όλες οι γραμμές στην AI ρεσεψιόν (' . implode(', ', $ok) . ')'];
            },
            'apply' => function ($L) {
                $to = ['To' => 'Extension', 'Number' => self::AI_DN, 'External' => ''];
                foreach (self::RULES_ALL as $id) {
                    if (!isset($L['rules'][$id])) { continue; }
                    Pbx3cxClient::xwrite('PATCH', 'InboundRules(' . $id . ')', ['OfficeHoursDestination' => $to,
                        'OutOfOfficeHoursDestination' => $to, 'HolidaysDestination' => $to]);
                }
                Pbx3cxClient::log('blueprint', 'ok', 'Οι εισερχόμενες πηγαίνουν πλέον στην AI ρεσεψιόν (902)');
            }];

        /* 8. Επιστροφή στο παλιό script — για να υπάρχει κουμπί «πίσω». */
        $S[] = ['key' => 'route_script', 'label' => 'Επιστροφή δρομολόγησης στο script 806 (εφεδρεία)',
            'risk' => 'route',
            'check' => function ($L) {
                $on = 0;
                foreach (self::RULES_ALL as $id) {
                    if (($L['rules'][$id]['OfficeHoursDestination']['Number'] ?? '') === self::SCRIPT_DN) { $on++; }
                }
                return [$on === count(self::RULES_ALL) ? 'ok' : 'change', $on === count(self::RULES_ALL) ? 'ενεργό το script' : 'διαθέσιμο ως επιστροφή'];
            },
            'apply' => function ($L) {
                $to = ['To' => 'RoutePoint', 'Number' => self::SCRIPT_DN, 'External' => ''];
                foreach (self::RULES_ALL as $id) {
                    if (!isset($L['rules'][$id])) { continue; }
                    Pbx3cxClient::xwrite('PATCH', 'InboundRules(' . $id . ')', ['OfficeHoursDestination' => $to,
                        'OutOfOfficeHoursDestination' => $to, 'HolidaysDestination' => $to]);
                }
                Pbx3cxClient::log('blueprint', 'ok', 'Οι εισερχόμενες επέστρεψαν στο script 806');
            }];

        return $S;
    }

    /* ─────────────────────────── βοηθοί ─────────────────────────── */

    /** Ποιος ΔΕΝ είναι μέλος του CloudOn (άνθρωποι, ουρές, ring groups, AI). */
    private static function missingFromCloudon(array $L)
    {
        $miss = [];
        foreach (['users', 'queues', 'rings'] as $set) {
            foreach ($L[$set] ?? [] as $num => $r) {
                if (!in_array(self::G_CLOUDON, self::groupIds($r), true)) { $miss[] = (string) $num; }
            }
        }
        return $miss;
    }

    /** Πού ζει ένα DN (entity set + Id) — για PATCH στο ίδιο το DN. */
    private static function locate(array $L, $num)
    {
        $num = (string) $num;
        if (isset($L['users'][$num])) { return ['Users', (int) $L['users'][$num]['Id'], $L['users'][$num]]; }
        if (isset($L['queues'][$num])) { return ['Queues', (int) $L['queues'][$num]['Id'], $L['queues'][$num]]; }
        if (isset($L['rings'][$num])) { return ['RingGroups', (int) $L['rings'][$num]['Id'], $L['rings'][$num]]; }
        throw new \RuntimeException('Άγνωστο DN ' . $num);
    }

    private static function addToGroup(array $L, $num, $gid)
    {
        [$set, $id, $r] = self::locate($L, $num);
        $ids = self::groupIds($r);
        if (in_array((int) $gid, $ids, true)) { return; }
        $ids[] = (int) $gid;
        Pbx3cxClient::xwrite('PATCH', $set . '(' . $id . ')', ['Groups' => array_map(function ($g) { return ['GroupId' => $g]; }, array_values(array_unique($ids)))]);
    }

    private static function removeFromGroup(array $L, $num, $gid)
    {
        [$set, $id, $r] = self::locate($L, $num);
        $ids = array_values(array_filter(self::groupIds($r), function ($g) use ($gid) { return $g !== (int) $gid; }));
        if (!$ids) { $ids = [self::G_CLOUDON]; }        // κανείς δεν μένει χωρίς τμήμα
        Pbx3cxClient::xwrite('PATCH', $set . '(' . $id . ')', ['Groups' => array_map(function ($g) { return ['GroupId' => $g]; }, $ids)]);
    }

    private static function destText($d)
    {
        if (!$d || ($d['To'] ?? 'None') === 'None') { return '—'; }
        return $d['To'] . ' ' . ($d['Number'] ?? '');
    }

    private static function sameDest($a, $b)
    {
        return ($a['To'] ?? 'None') === ($b['To'] ?? 'None') && (string) ($a['Number'] ?? '') === (string) ($b['Number'] ?? '');
    }

    private static function queueDiff(array $q, array $want)
    {
        $d = [];
        if ($q['Name'] !== $want['Name']) { $d[] = 'όνομα «' . $q['Name'] . '» → «' . $want['Name'] . '»'; }
        foreach (['PollingStrategy', 'RingTimeout', 'MasterTimeout', 'AnnounceQueuePosition'] as $k) {
            if (array_key_exists($k, $want) && $q[$k] != $want[$k]) { $d[] = $k . ' ' . json_encode($q[$k]) . ' → ' . json_encode($want[$k]); }
        }
        if (isset($want['Agents'])) {
            $have = array_map(function ($a) { return (string) $a['Number']; }, $q['Agents'] ?? []);
            $m = array_diff($want['Agents'], $have); $x = array_diff($have, $want['Agents']);
            if ($m) { $d[] = 'λείπουν χειριστές ' . implode(',', $m); }
            if ($x) { $d[] = 'περισσεύουν χειριστές ' . implode(',', $x); }
        }
        if (isset($want['ForwardNoAnswer']) && !self::sameDest($q['ForwardNoAnswer'] ?? null, $want['ForwardNoAnswer'])) {
            $d[] = 'αναπάντητη: ' . self::destText($q['ForwardNoAnswer'] ?? null) . ' → ' . self::destText($want['ForwardNoAnswer']);
        }
        foreach (['OutOfOfficeRoute', 'HolidaysRoute'] as $k) {
            if (isset($want[$k]) && !self::sameDest($q[$k]['Route'] ?? null, $want[$k]['Route'])) {
                $d[] = ($k === 'OutOfOfficeRoute' ? 'εκτός ωραρίου' : 'αργίες') . ': ' . self::destText($q[$k]['Route'] ?? null) . ' → ' . self::destText($want[$k]['Route']);
            }
        }
        if (!in_array(self::G_CLOUDON, self::groupIds($q), true)) { $d[] = 'εκτός τμήματος CloudOn'; }
        return $d;
    }

    /** Το σώμα PATCH/POST μιας ουράς: μόνο ό,τι διαφέρει (ή όλα, αν είναι νέα). */
    private static function queueBody(array $want, $q)
    {
        $b = [];
        foreach (['Name', 'PollingStrategy', 'RingTimeout', 'MasterTimeout', 'AnnounceQueuePosition', 'ForwardNoAnswer', 'OutOfOfficeRoute', 'HolidaysRoute'] as $k) {
            if (!array_key_exists($k, $want)) { continue; }
            if (!$q) { $b[$k] = $want[$k]; continue; }
            $cur = $q[$k] ?? null;
            $same = in_array($k, ['ForwardNoAnswer'], true) ? self::sameDest($cur, $want[$k])
                : (in_array($k, ['OutOfOfficeRoute', 'HolidaysRoute'], true) ? self::sameDest($cur['Route'] ?? null, $want[$k]['Route']) : $cur == $want[$k]);
            if (!$same) { $b[$k] = $want[$k]; }
        }
        foreach (['Agents', 'Managers'] as $k) {
            if (!isset($want[$k])) { continue; }
            $have = $q ? array_map(function ($a) { return (string) $a['Number']; }, $q[$k] ?? []) : [];
            if (!$q || array_diff($want[$k], $have) || array_diff($have, $want[$k])) {
                $b[$k] = array_map(function ($n) { return ['Number' => (string) $n]; }, $want[$k]);
            }
        }
        if ($q && !in_array(self::G_CLOUDON, self::groupIds($q), true)) {
            $ids = self::groupIds($q); $ids[] = self::G_CLOUDON;
            $b['Groups'] = array_map(function ($g) { return ['GroupId' => $g]; }, array_values(array_unique($ids)));
        }
        return $b;
    }

    private static function agentDiff(array $a)
    {
        $want = self::agent();
        $cur = $a['AgentSettings'] ?? [];
        $d = [];
        if (($a['DisplayName'] ?? '') !== $want['DisplayName']) { $d[] = 'όνομα'; }
        foreach ($want['AgentSettings'] as $k => $v) {
            $c = $cur[$k] ?? null;
            if (in_array($k, ['RoutingDirectory'], true)) {
                $cn = array_map(function ($x) { return $x['Number'] . '|' . $x['Description']; }, $c ?: []);
                $wn = array_map(function ($x) { return $x['Number'] . '|' . $x['Description']; }, $v);
                sort($cn); sort($wn);
                if ($cn !== $wn) { $d[] = 'προορισμοί: ' . implode(',', array_map(function ($x) { return $x['Number']; }, $c ?: [])) . ' → ' . implode(',', array_map(function ($x) { return $x['Number']; }, $v)); }
            } elseif (in_array($k, ['HumanHandoff', 'AgentFallback'], true)) {
                if ((string) ($c['Number'] ?? '') !== (string) $v['Number'] || (string) ($c['Action'] ?? '') !== (string) ($v['Action'] ?? ($c['Action'] ?? ''))) { $d[] = $k . ' → ' . $v['Number']; }
            } elseif (is_bool($v)) {
                if ((bool) $c !== $v) { $d[] = $k; }
            } elseif (trim(str_replace("\r", '', (string) $c)) !== trim((string) $v)) {
                $d[] = $k === 'SystemPrompt' ? 'οδηγίες (prompt)' : ($k === 'FirstMessage' ? 'χαιρετισμός' : $k . ' «' . mb_substr((string) $c, 0, 20) . '» → «' . mb_substr((string) $v, 0, 20) . '»');
            }
        }
        return $d;
    }

    /* ─────────────────────────── δημόσιο API ─────────────────────────── */

    /** Τι διαφέρει από το σχέδιο. Δεν αλλάζει τίποτα. */
    public static function plan()
    {
        $L = self::live();
        $out = ['at' => date('Y-m-d H:i:s'), 'steps' => []];
        foreach (self::steps() as $s) {
            try { [$state, $detail] = $s['check']($L); }
            catch (\Throwable $e) { $state = 'error'; $detail = $e->getMessage(); }
            $out['steps'][] = ['key' => $s['key'], 'label' => $s['label'], 'risk' => $s['risk'],
                'state' => $state, 'detail' => $detail];
        }
        $out['pending'] = count(array_filter($out['steps'], function ($s) { return $s['state'] === 'change' && $s['risk'] === 'low'; }));
        return $out;
    }

    /**
     * Εφαρμογή. Χωρίς $keys: όλα τα ΧΑΜΗΛΟΥ ρίσκου βήματα που διαφέρουν. Τα
     * βήματα δρομολόγησης μόνο αν ζητηθούν ρητά με το key τους. Κάθε βήμα
     * ξαναδιαβάζει το PBX, ώστε το προηγούμενο να έχει μετρήσει.
     */
    public static function apply(array $keys = [], $who = '')
    {
        $res = ['at' => date('Y-m-d H:i:s'), 'done' => [], 'errors' => []];
        foreach (self::steps() as $s) {
            $explicit = in_array($s['key'], $keys, true);
            if ($s['risk'] === 'route' && !$explicit) { continue; }
            if ($keys && !$explicit) { continue; }
            $L = self::live();
            try { [$state] = $s['check']($L); } catch (\Throwable $e) { $state = 'error'; }
            if ($state === 'ok' && $s['risk'] !== 'route') { continue; }
            if ($state === 'ok' && $s['risk'] === 'route' && !$explicit) { continue; }
            try {
                $s['apply']($L);
                $res['done'][] = $s['key'];
                Pbx3cxClient::log('blueprint', 'ok', $s['label'] . ($who ? ' — από ' . $who : ''));
            } catch (\Throwable $e) {
                $res['errors'][] = $s['key'] . ': ' . $e->getMessage();
                Pbx3cxClient::log('blueprint', 'error', $s['label'] . ' — ' . $e->getMessage());
            }
        }
        return $res;
    }
}
