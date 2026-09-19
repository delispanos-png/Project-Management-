<?php
/**
 * CloudOn Agent — άντληση κλήσεων από τις αναφορές του 3CX.
 *
 * ΓΙΑΤΙ ΥΠΑΡΧΕΙ: η αρχική σχεδίαση περίμενε το CDR να μας στέλνει τις κλήσεις σε
 * ένα socket. Αυτό θέλει μόνιμη υπηρεσία και ανοιχτή θύρα. Εδώ ρωτάμε εμείς το
 * PBX — δύο αναφορές που απαντούν σε δέκατα του δευτερολέπτου:
 *
 *   ReportInboundCalls/Pbx.GetInboundCalls(periodFrom,periodTo,trunkDns,callsType)
 *   ReportOutboundCalls/Pbx.GetOutboundCalls(...)
 *
 * ΜΗΝ δοκιμάσεις ξανά το GetCallLogData: απαντά 200 αλλά ΠΑΝΤΑ 0 γραμμές, με
 * κάθε συνδυασμό παραμέτρων (δοκιμάστηκαν sourceType/destinationType/callsType
 * 0..255, φίλτρα '' και null). Ούτε το CallHistoryView — σαρώνει ~123.000
 * γραμμές και στις 19/09/2026 έριξε την PostgreSQL του PBX.
 *
 * ΤΑ ΣΚΕΛΗ: μία κλήση έρχεται σπασμένη σε πολλές γραμμές — Call Script (806),
 * ουρά υποδοχής (801), και τέλος ο χειριστής. Στις 18/09 ήταν 100 γραμμές για
 * 33 πραγματικές κλήσεις. Τις ενώνουμε με το CallHistoryId και κρατάμε ΜΙΑ
 * εγγραφή ανά κλήση, με χειριστή αυτόν που πραγματικά μίλησε.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Pbx3cxReport
{
    /** Μέγιστο σελίδας — το 3CX κόβει στο 100. */
    const PAGE = 100;

    /** ISO-8601 διάρκεια («PT7M8.687011S») → δευτερόλεπτα. */
    public static function dur($v)
    {
        if (!is_string($v) || $v === '') { return 0; }
        if (!preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:([\d.]+)S)?$/', $v, $m)) { return 0; }
        return (int) round(((int) ($m[1] ?? 0)) * 86400 + ((int) ($m[2] ?? 0)) * 3600
             + ((int) ($m[3] ?? 0)) * 60 + ((float) ($m[4] ?? 0)));
    }

    /** «2026-09-18T14:35:57.657075Z» → τοπική ώρα για τη βάση μας. */
    private static function dt($v)
    {
        if (!is_string($v) || $v === '') { return null; }
        try { return (new \DateTime($v))->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i:s'); } catch (\Throwable $e) { return null; }
    }

    /** Μία αναφορά, με σελιδοποίηση. Κρατάμε ΣΤΕΝΟ διάστημα — μέρα-μέρα. */
    private static function pull($entity, $fn, $from, $to)
    {
        $out = [];
        for ($skip = 0; $skip < 5000; $skip += self::PAGE) {
            $j = Pbx3cxClient::xapi(
                $entity . '/Pbx.' . $fn . "(periodFrom=$from,periodTo=$to,trunkDns='',callsType=0)",
                ['$top' => self::PAGE, '$skip' => $skip], 40);
            $v = $j['value'] ?? [];
            if (!$v) { break; }
            $out = array_merge($out, $v);
            if (count($v) < self::PAGE) { break; }
        }
        return $out;
    }

    /** Ο χάρτης DN → admin, μία φορά ανά τρέξιμο. */
    private static function map($fresh = false)
    {
        static $m = null;
        if ($m === null || $fresh) {
            $m = [];
            foreach (Capsule::table('mod_cpm_pbx_map')->get() as $r) {
                $m[(string) $r->dn] = ['admin' => (int) $r->admin_id, 'name' => (string) $r->display_name];
            }
        }
        return $m;
    }

    /**
     * DN που μιλάει στο ιστορικό αλλά δεν υπάρχει πια στο PBX.
     *
     * ΓΙΑΤΙ: το 224 έκανε 184 κλήσεις και μετά το extension διαγράφηκε. Ο
     * συγχρονισμός δομής φέρνει μόνο ό,τι ΥΠΑΡΧΕΙ, οπότε αυτές οι κλήσεις
     * έμεναν για πάντα «απαντημένες χωρίς χειριστή» — χρόνος δουλειάς που δεν
     * πιστωνόταν σε κανέναν, χωρίς καν να φαίνεται ότι λείπει.
     *
     * Το ΟΝΟΜΑ το δίνει η ίδια η αναφορά: το SourceDisplayName γράφει
     * «Σιουμπάλας, Θωμάς (224)». Μπαίνει λοιπόν στον χάρτη με το όνομά του και
     * μένει ένα κλικ, να δείξει κάποιος ποιος συνάδελφος είναι. Την αντιστοίχιση
     * ΔΕΝ την κάνουμε από το όνομα — δύο άνθρωποι μπορεί να λέγονται ίδια.
     *
     * @param array $dns  [dn => όνομα όπως το γράφει το PBX]
     */
    private static function noteOrphanDns(array $dns)
    {
        $map = self::map();
        $added = 0;
        foreach ($dns as $dn => $name) {
            $dn = trim((string) $dn);
            /* 3ψήφια εσωτερικά και μόνο. Τα 4-5ψήφια είναι trunks (10004), τα
               8xx/9xx είναι ουρές και σενάρια του ίδιου του 3CX — δεν είναι
               άνθρωποι και δεν έχει νόημα να ζητάμε να τους βρει κάποιος
               χειριστή. Με τον πρώτο κανόνα μπήκε στον χάρτη το ίδιο το 806. */
            if ($dn === '' || isset($map[$dn]) || !preg_match('/^\d{3}$/', $dn)
                || $dn[0] === '8' || $dn[0] === '9') { continue; }
            /* «Σιουμπάλας, Θωμάς (224)» → «Σιουμπάλας, Θωμάς» */
            $nm = trim(preg_replace('/\s*\(\d+\)\s*$/u', '', (string) $name));
            Capsule::table('mod_cpm_pbx_map')->insert([
                'dn' => $dn, 'dn_type' => 'extension',
                'display_name' => ($nm !== '' ? $nm : 'DN ' . $dn) . ' — δεν υπάρχει πια στο PBX',
                'admin_id' => null, 'matched_by' => 'none', 'active' => 0,
                'synced_at' => date('Y-m-d H:i:s')]);
            $added++;
        }
        if ($added) {
            self::map(true);
            Pbx3cxClient::log('sync', 'ok', $added . ' DN από παλιές κλήσεις δεν υπάρχουν πια '
                . 'στο PBX — μπήκαν στον χάρτη για χειροκίνητη αντιστοίχιση');
        }
        return $added;
    }

    /** Το DN ενός σκέλους. ΠΡΟΣΟΧΗ στα ονόματα — διαφέρουν ανά αναφορά. */
    private static function legDn(array $l, $dir)
    {
        /* Εισερχόμενη: το DestinationDn είναι ΣΥΧΝΑ ΚΕΝΟ (μετρήθηκε) — το DN
           βρίσκεται στο DestinationCallerId. Εξερχόμενη: το DestinationDn είναι
           το trunk (10004), ο συνάδελφος είναι στο SourceDn. */
        return $dir === 'out'
            ? trim((string) ($l['SourceDn'] ?: ($l['SourceCallerId'] ?? '')))
            : trim((string) ($l['DestinationCallerId'] ?: ($l['DestinationDn'] ?? '')));
    }

    /** Ο έξω συνομιλητής. Στις εξερχόμενες το πεδίο λέγεται CalleeId, όχι CallerId. */
    private static function other(array $l, $dir)
    {
        return $dir === 'out'
            ? trim((string) ($l['DestinationCalleeId'] ?: ($l['DestinationDisplayName'] ?? '')))
            : trim((string) ($l['SourceCallerId'] ?? ''));
    }

    /**
     * Τα σκέλη μιας κλήσης → μία εγγραφή.
     *
     * ΤΟ ΚΡΙΣΙΜΟ: το Call Script (806) σηκώνει το τηλέφωνο και καταγράφεται ως
     * «Answered». Αν το μετρούσαμε, ΚΑΘΕ αναπάντητη θα φαινόταν απαντημένη και
     * η οθόνη θα έλεγε ψέματα. Απαντημένη = μίλησε ΑΝΘΡΩΠΟΣ της ομάδας, δηλαδή
     * σκέλος με DN αντιστοιχισμένο σε συνάδελφο.
     *
     * Χρόνος ομιλίας = το ΑΘΡΟΙΣΜΑ των σκελών με συναδέλφους (σε μεταβίβαση
     * μίλησαν δύο· και οι δύο χρόνοι είναι χρόνος της ομάδας). Η κλήση χρεώνεται
     * σε αυτόν που μίλησε περισσότερο.
     *
     * Αναμονή = ό,τι μεσολάβησε πριν απαντήσει άνθρωπος — μενού, ουρά, κουδούνισμα.
     */
    private static function fold(array $legs, $dir)
    {
        usort($legs, function ($a, $b) { return strcmp((string) $a['StartTime'], (string) $b['StartTime']); });
        $first = $legs[0];
        $last  = $legs[count($legs) - 1];
        $map   = self::map();

        $talk = 0; $adminId = 0; $agentDn = ''; $best = -1; $wait = 0; $seenAgent = false;
        $cost = 0.0; $aiSummary = ''; $rec = '';

        foreach ($legs as $l) {
            $dn = self::legDn($l, $dir);
            $t  = self::dur($l['TalkingDuration'] ?? '');
            $r  = self::dur($l['RingingDuration'] ?? '');
            $isAgent = $dn !== '' && isset($map[$dn]) && $map[$dn]['admin'] > 0;
            $ok = $isAgent && (string) ($l['Status'] ?? '') === 'Answered' && $t > 0;

            if ($ok) {
                $seenAgent = true;
                $talk += $t;
                if ($t > $best) { $best = $t; $adminId = $map[$dn]['admin']; $agentDn = $dn; }
            } elseif (!$seenAgent) {
                /* Πριν απαντήσει άνθρωπος, ΚΑΙ το κουδούνισμα ΚΑΙ ο χρόνος στο
                   μενού/ουρά είναι αναμονή για τον πελάτη. */
                $wait += $r + $t;
            }
            $cost += (float) ($l['CallCost'] ?? 0);
            if ($aiSummary === '') { $aiSummary = trim((string) ($l['Summary'] ?? '')); }
            if ($rec === '') { $rec = trim((string) ($l['RecordingUrl'] ?? '')); }
        }

        /* Εξερχόμενη από DN που δεν έχει αντιστοιχιστεί ακόμη με συνάδελφο: μην
           τη χάσουμε — κρατάμε χρόνο και DN, χωρίς χειριστή. */
        if (!$seenAgent && $dir === 'out') {
            foreach ($legs as $l) { $talk = max($talk, self::dur($l['TalkingDuration'] ?? '')); }
            $agentDn = self::legDn($first, $dir);
            /* Ακόμη κι αν δεν απάντησαν, ΞΕΡΟΥΜΕ ποιος κάλεσε — μην τον χάσεις. */
            if ($agentDn !== '' && isset($map[$agentDn]) && $map[$agentDn]['admin'] > 0) {
                $adminId = $map[$agentDn]['admin'];
            }
            if ($talk > 0) { $seenAgent = true; }
        }

        $other = self::other($first, $dir);
        /* Ο καλών έκρυψε τον αριθμό του. Είναι κανονική κλήση — αν το αφήσουμε
           κενό, στην οθόνη βγαίνει «—» σαν να λείπει δεδομένο. */
        $anon = strcasecmp($other, 'anonymous') === 0 || $other === '';
        $raw = array_filter([
            'legs'  => count($legs),
            'ai'    => $aiSummary !== '' ? mb_substr($aiSummary, 0, 400) : null,
            'rec'   => $rec !== '' ? $rec : null,
            'trunk' => (string) ($first['TrunkName'] ?? '') ?: null,
        ]);

        return [
            'call_id'      => substr((string) ($first['CdrId'] ?? ''), 0, 40),
            'direction'    => $dir,
            'started_at'   => self::dt($first['StartTime'] ?? ''),
            'ring_seconds' => $wait,
            'talk_seconds' => $talk,
            'answered'     => $seenAgent ? 1 : 0,
            'reason'       => $seenAgent ? 'Answered' : substr((string) ($last['Status'] ?? 'Unanswered'), 0, 40),
            'from_no'      => substr($dir === 'out' ? $agentDn : $other, 0, 40),
            'to_no'        => substr($dir === 'out' ? $other : $agentDn, 0, 40),
            'final_dn'     => substr($agentDn, 0, 20),
            'other_e164'   => $anon ? '' : substr(Pbx3cxCdr::e164($other), 0, 24),
            'client_match' => $anon ? 'anon' : null,
            'admin_id'     => $adminId ?: null,
            'pbx_cost'     => round($cost, 4),
            'raw'          => $raw ? json_encode($raw, JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    /**
     * Άντληση μιας ημέρας. Επιστρέφει [νέες, ενημερωμένες, σκέλη].
     *
     * Ό,τι έγραψε ο συνάδελφος μετά την κλήση (περίληψη, χρέωση, αιτιολογία)
     * ΔΕΝ πειράζεται ποτέ σε επανάληψη — γράφουμε μόνο τα πεδία του PBX.
     */
    public static function day($date)
    {
        $from = $date . 'T00:00:00Z';
        $to   = $date . 'T23:59:59Z';
        $legs = [];
        foreach ([['in', 'ReportInboundCalls', 'GetInboundCalls'],
                  ['out', 'ReportOutboundCalls', 'GetOutboundCalls']] as $s) {
            foreach (self::pull($s[1], $s[2], $from, $to) as $r) {
                $h = (string) ($r['CallHistoryId'] ?? '');
                if ($h === '') { continue; }
                /* ΔΕΝ ΕΙΝΑΙ ΤΗΛΕΦΩΝΑ: το 3CX περνάει από τις ίδιες αναφορές και
                   τις συνεδρίες LiveChat και τα WebMeeting. Έμπαιναν ως κλήσεις
                   με κενό αριθμό και μηδέν ομιλία, δηλαδή σαν αναπάντητες. */
                if (stripos((string) ($r['TrunkName'] ?? ''), 'WebMeeting') !== false) { continue; }
                if (strcasecmp(trim((string) ($r['SourceCallerId'] ?? '')), 'LiveChat') === 0) { continue; }
                $legs[$s[0] . '|' . $h][] = $r;
            }
        }

        /* Πρώτα τα άγνωστα DN: αν τα καταχωρήσουμε τώρα, οι κλήσεις αυτού του
           τρεξίματος θα βρουν αμέσως τη γραμμή τους στον χάρτη. */
        /* ΜΟΝΟ από εξερχόμενες. Ένα μενού ή μια ουρά ΔΕΝ σηκώνει ποτέ το
           τηλέφωνο για να καλέσει έξω — άρα όποιο DN κάλεσε, ήταν άνθρωπος.
           Και το όνομά του το γράφει η ίδια η αναφορά. */
        $allDns = [];
        foreach ($legs as $key => $group) {
            if (strpos($key, 'out|') !== 0) { continue; }
            foreach ($group as $l) {
                $dn0 = self::legDn($l, 'out');
                if ($dn0 !== '' && !isset($allDns[$dn0])) {
                    $allDns[$dn0] = (string) ($l['SourceDisplayName'] ?? '');
                }
            }
        }
        self::noteOrphanDns($allDns);

        $seen = 0; $built = [];
        foreach ($legs as $key => $group) {
            $seen += count($group);
            [$dir, $hist] = explode('|', $key, 2);
            $row = self::fold($group, $dir);
            if ($row['started_at'] === null) { continue; }
            $built[] = ['hist' => $hist, 'row' => $row, 'legs' => count($group)];
        }

        /* ΤΟ ΠΡΟΟΙΜΙΟ ΤΗΣ ΚΛΗΣΗΣ
           Το 3CX καταγράφει το αυτόματο μενού με ΔΙΚΟ ΤΟΥ CallHistoryId. Έτσι μία
           κλήση που απαντήθηκε κανονικά άφηνε ΔΥΟ εγγραφές: το μενού (μηδέν
           ομιλία, κανένας χειριστής — δηλαδή «αναπάντητη») και την πραγματική.
           Μετρήθηκε: 459 τέτοια φαντάσματα στη χρονιά, το 15% όσων εμφανίζονταν
           ως χαμένες. Τα ενώνουμε: ο χρόνος στο μενού γίνεται αναμονή της
           πραγματικής κλήσης.
           ΠΡΟΣΟΧΗ: κρατάμε όσα ΔΕΝ έχουν συνέχεια — αυτά είναι αληθινές
           εγκαταλείψεις, «το έκλεισε πριν του απαντήσουμε», και μετράνε. */
        $drop = [];
        foreach ($built as $i => $b) {
            $isPrologue = $b['legs'] === 1 && empty($b['row']['admin_id'])
                && (int) $b['row']['talk_seconds'] === 0 && $b['row']['final_dn'] === '';
            if (!$isPrologue || $b['row']['other_e164'] === '') { continue; }
            foreach ($built as $k => $o) {
                if ($k === $i || $o['row']['other_e164'] !== $b['row']['other_e164']) { continue; }
                if (isset($drop[$k])) { continue; }
                $gap = abs(strtotime($o['row']['started_at']) - strtotime($b['row']['started_at']));
                if ($gap > 180) { continue; }
                $built[$k]['row']['ring_seconds'] += (int) $b['row']['ring_seconds'];
                $drop[$i] = true;
                break;
            }
        }

        $new = 0; $upd = 0;
        foreach ($built as $i => $b) {
            $hist = $b['hist']; $row = $b['row']; $dir = $row['direction'];
            if (isset($drop[$i])) {
                /* Μπορεί να γράφτηκε σε παλιότερο συγχρονισμό, πριν φτιαχτεί ο
                   κανόνας — φύγε από τη μέση, αλλιώς μένει για πάντα ψεύτικη. */
                Capsule::table('mod_cpm_calls')->where('history_id', $hist)
                    ->whereNull('logged_at')->delete();
                continue;
            }

            /* Η απόκρυψη αριθμού έχει ήδη σημειωθεί στο fold() — μην τη σβήσεις
               με «none», γιατί «δεν ξέρουμε ποιος» και «δεν θέλησε να πει ποιος»
               είναι διαφορετικά πράγματα και φαίνονται διαφορετικά στην οθόνη. */
            if (($row['client_match'] ?? null) === 'anon') {
                $row['clientid'] = null;
            } else {
                [$cid, $how] = Pbx3cxCdr::matchClient($row['other_e164']);
                $row['clientid']     = $cid;
                $row['client_match'] = $how;
            }

            $exists = Capsule::table('mod_cpm_calls')->where('history_id', $hist)->first();
            /* Μια κλήση μπορεί να εμφανιστεί ΚΑΙ στις δύο αναφορές — εισερχόμενη
               που προωθήθηκε σε εξωτερικό αριθμό. Κρατάμε την πρώτη εκδοχή
               (εισερχόμενη, γιατί τη διαβάζουμε πρώτη) ώστε η κατεύθυνση να μην
               αλλάζει σε κάθε συγχρονισμό και να μη μετρηθεί η κλήση δύο φορές. */
            if ($exists && (string) $exists->direction !== $dir) { continue; }
            if ($exists) {
                Capsule::table('mod_cpm_calls')->where('history_id', $hist)->update($row);
                $upd++;
            } else {
                $row['history_id'] = $hist;
                $row['created_at'] = date('Y-m-d H:i:s');
                Capsule::table('mod_cpm_calls')->insert($row);
                $new++;
                /* Σκιώδης δρομολόγηση: τι ΘΑ αποφασίζαμε για αυτή την κλήση. */
                if (class_exists(__NAMESPACE__ . '\Route')) { Route::shadow($row, $hist); }
            }
        }
        return ['new' => $new, 'updated' => $upd, 'legs' => $seen,
            'calls' => count($built) - count($drop), 'merged' => count($drop)];
    }

    /** Πολλές ημέρες, από την παλαιότερη προς τη νεότερη. */
    public static function range($fromDate, $toDate)
    {
        $t0 = microtime(true);
        $res = ['days' => 0, 'new' => 0, 'updated' => 0, 'calls' => 0, 'merged' => 0];
        $d = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        while ($d <= $end) {
            $r = self::day($d->format('Y-m-d'));
            $res['days']++; $res['new'] += $r['new'];
            $res['updated'] += $r['updated']; $res['calls'] += $r['calls'];
            $res['merged'] += $r['merged'];
            $d->modify('+1 day');
        }
        $res['secs'] = round(microtime(true) - $t0, 1);
        Pbx3cxClient::log('sync', 'ok', 'Κλήσεις ' . $fromDate . '→' . $toDate . ': '
            . $res['new'] . ' νέες, ' . $res['updated'] . ' ενημερώθηκαν (' . $res['secs'] . '΄΄)');
        return $res;
    }
}
