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
    private static function map()
    {
        static $m = null;
        if ($m === null) {
            $m = [];
            foreach (Capsule::table('mod_cpm_pbx_map')->get() as $r) {
                $m[(string) $r->dn] = ['admin' => (int) $r->admin_id, 'name' => (string) $r->display_name];
            }
        }
        return $m;
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
            'other_e164'   => substr(Pbx3cxCdr::e164($other), 0, 24),
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
                $legs[$s[0] . '|' . $h][] = $r;
            }
        }

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

            [$cid, $how] = Pbx3cxCdr::matchClient($row['other_e164']);
            $row['clientid']     = $cid;
            $row['client_match'] = $how;

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
