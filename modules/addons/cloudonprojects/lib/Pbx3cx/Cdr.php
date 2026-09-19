<?php
/**
 * CloudOn Agent — ΦΑΣΗ 5: υποδοχή CDR από το 3CX.
 *
 * Το τηλεφωνικό κέντρο στέλνει μία γραμμή CSV ανά κλήση που τελειώνει
 * (Active Socket). Εδώ μετατρέπεται σε εγγραφή κλήσης, ταυτίζεται ο χειριστής
 * και ο πελάτης, και αποθηκεύεται ΜΙΑ φορά — όσες φορές κι αν ξανάρθει.
 *
 * ΓΙΑΤΙ ΕΤΣΙ: μετά τη μεταφορά του PBX σε νέο server (04/2025), οι αναφορές
 * του XAPI επιστρέφουν κενό. Το CDR είναι ο μόνος δρόμος για τρέχοντα δεδομένα.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Pbx3cxCdr
{
    /**
     * Η σειρά των πεδίων ΟΠΩΣ είναι ρυθμισμένη στο PBX (CDRSettings.EnabledFields).
     * Αν αλλάξει εκεί, αλλάζει κι εδώ — γι' αυτό η σειρά ελέγχεται με
     * `checkLayout()` και όχι με ευχή.
     */
    public static $FIELDS = [
        'historyid', 'callid', 'duration', 'time-start', 'time-answered', 'time-end',
        'reason-terminated', 'from-no', 'to-no', 'from-dn', 'to-dn', 'dial-no',
        'reason-changed', 'final-number', 'final-dn', 'bill-code', 'bill-rate',
        'bill-cost', 'bill-name', 'chain',
    ];

    /** Επαληθεύει ότι η διάταξη στο PBX είναι αυτή που περιμένουμε. */
    public static function checkLayout()
    {
        $j = Pbx3cxClient::xapi('CDRSettings');
        $live = array_map(function ($f) { return $f['Name']; }, $j['EnabledFields'] ?? []);
        return ['ok' => $live === self::$FIELDS, 'pbx' => $live, 'expected' => self::$FIELDS];
    }

    /** «00:04:32» ή «PT4M32S» ή «272» → δευτερόλεπτα. */
    private static function secs($v)
    {
        $v = trim((string) $v);
        if ($v === '') { return 0; }
        if (preg_match('/^(\d+):(\d{2}):(\d{2})/', $v, $m)) {
            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
        }
        if (preg_match('/^P/i', $v)) {
            $i = new \DateInterval($v);
            return $i->h * 3600 + $i->i * 60 + $i->s;
        }
        return (int) $v;
    }

    private static function dt($v)
    {
        $v = trim((string) $v);
        if ($v === '' || strpos($v, '0001-01-01') === 0) { return null; }
        $ts = strtotime($v);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    /** Κανονικοποίηση σε ελληνικό E.164 — η ίδια λογική με την ταύτιση πελάτη. */
    public static function e164($n)
    {
        $d = preg_replace('/\D+/', '', (string) $n);
        if ($d === '') { return ''; }

        /* Διεθνής κλήση: το 00 είναι το πρόθεμα εξόδου, ό,τι ακολουθεί είναι
           ολόκληρος ο ξένος αριθμός με τον κωδικό χώρας του.
           ΜΗΝ του κολλήσεις +30: το 0035799687016 (Κύπρος) γινόταν
           +305799687016, δηλαδή ανύπαρκτος ελληνικός αριθμός. */
        if (strpos($d, '00') === 0) {
            $d = substr($d, 2);
            if (strpos($d, '30') === 0 && strlen($d) === 12) { $d = substr($d, 2); }
            else { return strlen($d) >= 8 ? '+' . $d : $d; }
        } elseif (strpos($d, '30') === 0 && strlen($d) === 12) {
            $d = substr($d, 2);
        }

        /* Ελληνικός: 10 ψηφία, ξεκινά με 2 (σταθερό) ή 6 (κινητό). */
        if (strlen($d) === 10 && ($d[0] === '2' || $d[0] === '6')) { return '+30' . $d; }
        /* Μεγαλύτερος χωρίς 00 — έχει ήδη κωδικό χώρας. */
        if (strlen($d) > 10) { return '+' . $d; }
        /* Εσωτερικό ή σύντομος κωδικός — άφησέ τον όπως είναι. */
        return $d;
    }

    /**
     * Δέχεται μία ωμή γραμμή CDR και την αποθηκεύει.
     * Επιστρέφει ['ok'=>bool, 'id'=>int, 'new'=>bool, 'why'=>string].
     */
    public static function ingest($line)
    {
        $line = trim((string) $line);
        if ($line === '') { return ['ok' => false, 'why' => 'κενή γραμμή']; }
        $cols = str_getcsv($line);
        if (count($cols) < 6) { return ['ok' => false, 'why' => 'πολύ λίγα πεδία (' . count($cols) . ')']; }

        $f = [];
        foreach (self::$FIELDS as $i => $name) { $f[$name] = $cols[$i] ?? ''; }

        $hid = trim($f['historyid']);
        if ($hid === '') { return ['ok' => false, 'why' => 'χωρίς historyid']; }

        $fromDn = trim($f['from-dn']);
        $toDn = trim($f['to-dn']);
        $fromNo = trim($f['from-no']);
        $toNo = trim($f['to-no']);

        /* ── Κατεύθυνση ───────────────────────────────────────────────────────
           Κρίνεται από το αν το DN είναι ΔΙΚΟ ΜΑΣ, όχι από το αν έχει χειριστή.
           Η ρεσεψιόν και το θυροτηλέφωνο είναι δικά μας extensions χωρίς
           άνθρωπο· μια αναπάντητη προς τη ρεσεψιόν είναι εισερχόμενη, όχι
           εσωτερική (βρέθηκε στη δοκιμή). */
        $known = Capsule::table('mod_cpm_pbx_map')
            ->whereIn('dn', array_filter([$fromDn, $toDn]))->get()->keyBy('dn');
        $fromOurs = $fromDn !== '' && isset($known[$fromDn]);
        $toOurs = $toDn !== '' && isset($known[$toDn]);

        if ($fromOurs && $toOurs) { $dir = 'internal'; }
        elseif ($toOurs) { $dir = 'in'; }
        elseif ($fromOurs) { $dir = 'out'; }
        else { $dir = 'internal'; }          // δεν αναγνωρίζουμε κανένα άκρο

        /* Ο χειριστής: όποιος «κράτησε» την κλήση. Στην εισερχόμενη είναι ο
           προορισμός, στην εξερχόμενη η πηγή. */
        $adminId = null;
        if ($dir === 'in' && $toOurs) { $adminId = $known[$toDn]->admin_id ? (int) $known[$toDn]->admin_id : null; }
        elseif ($dir === 'out' && $fromOurs) { $adminId = $known[$fromDn]->admin_id ? (int) $known[$fromDn]->admin_id : null; }
        elseif ($dir === 'internal' && $fromOurs) { $adminId = $known[$fromDn]->admin_id ? (int) $known[$fromDn]->admin_id : null; }

        $other = $dir === 'in' ? $fromNo : ($dir === 'out' ? $toNo : '');
        $e164 = self::e164($other);

        /* Ταύτιση πελάτη — ίδια σειρά βεβαιότητας με τον σχεδιασμό. */
        [$clientId, $how] = $e164 !== '' ? self::matchClient($e164) : [null, null];

        $answered = self::dt($f['time-answered']);
        $started = self::dt($f['time-start']);
        $ended = self::dt($f['time-end']);
        $talk = self::secs($f['duration']);
        $ring = ($started && $answered) ? max(0, strtotime($answered) - strtotime($started)) : 0;

        $row = [
            'call_id' => trim($f['callid']) ?: null,
            'direction' => $dir,
            'started_at' => $started ?: date('Y-m-d H:i:s'),
            'answered_at' => $answered,
            'ended_at' => $ended,
            'ring_seconds' => $ring,
            'talk_seconds' => $talk,
            'answered' => $answered ? 1 : 0,
            'reason' => mb_substr(trim($f['reason-terminated']), 0, 40) ?: null,
            'from_no' => mb_substr($fromNo, 0, 40) ?: null,
            'to_no' => mb_substr($toNo, 0, 40) ?: null,
            'from_dn' => mb_substr($fromDn, 0, 20) ?: null,
            'to_dn' => mb_substr($toDn, 0, 20) ?: null,
            'final_dn' => mb_substr(trim($f['final-dn']), 0, 20) ?: null,
            'other_e164' => $e164 ?: null,
            'admin_id' => $adminId,
            'clientid' => $clientId,
            'client_match' => $how,
            'pbx_cost' => is_numeric(trim($f['bill-cost'])) ? (float) $f['bill-cost'] : null,
            'raw' => mb_substr($line, 0, 4000),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $exists = Capsule::table('mod_cpm_calls')->where('history_id', $hid)->first();
        if ($exists) {
            /* Ξαναήρθε — ενημερώνουμε, δεν διπλασιάζουμε. */
            Capsule::table('mod_cpm_calls')->where('id', $exists->id)->update($row);
            return ['ok' => true, 'id' => (int) $exists->id, 'new' => false];
        }
        $id = Capsule::table('mod_cpm_calls')->insertGetId($row + ['history_id' => $hid]);
        return ['ok' => true, 'id' => (int) $id, 'new' => true];
    }

    /** τηλέφωνο → πελάτης, με σειρά βεβαιότητας. */
    public static function matchClient($e164)
    {
        $d10 = substr(preg_replace('/\D+/', '', $e164), -10);
        if ($d10 === '') { return [null, null]; }

        /* 1) Επαφές πελατών — ο πίνακας που ήδη υπάρχει. */
        $c = Capsule::table('mod_cpm_client_contacts')->where('kind', 'phone')
            ->whereRaw("REPLACE(REPLACE(REPLACE(value,' ',''),'-',''),'+','') LIKE ?", ['%' . $d10])
            ->value('clientid');
        if ($c) { return [(int) $c, 'contact']; }

        /* 2) Το τηλέφωνο του πελάτη στο WHMCS — 47 διαφορετικές μορφές, γι' αυτό
              συγκρίνουμε μόνο τα 10 τελευταία ψηφία. */
        $c = Capsule::table('tblclients')
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phonenumber,' ',''),'-',''),'.',''),'+','') LIKE ?", ['%' . $d10])
            ->value('id');
        if ($c) { return [(int) $c, 'whmcs']; }

        /* 3) Δευτερεύουσες επαφές πελατών — ο υπεύθυνος που καλεί από το δικό
              του τηλέφωνο, όχι από το κεντρικό της εταιρείας. */
        $c = Capsule::table('tblcontacts')->where('phonenumber', '<>', '')
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phonenumber,' ',''),'-',''),'.',''),'+','') LIKE ?", ['%' . $d10])
            ->value('userid');
        if ($c) { return [(int) $c, 'contact']; }

        return [null, 'none'];
    }
}
