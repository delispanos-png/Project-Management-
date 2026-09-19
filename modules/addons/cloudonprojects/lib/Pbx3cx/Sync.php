<?php
/**
 * CloudOn Agent — ΦΑΣΗ 3: συγχρονισμός δομής PBX.
 *
 * Φέρνει extensions, ουρές και ring groups από το 3CX και τα αντιστοιχίζει σε
 * χειριστές του CloudOn. Η αντιστοίχιση γίνεται με το EMAIL, που είναι το μόνο
 * σταθερό κοινό στοιχείο: τα ονόματα διαφέρουν (3CX «Βασίλης Βάκρινος» vs
 * CloudOn «Vasilis Vakrinos») και τα DN αλλάζουν.
 *
 * ΚΑΝΟΝΑΣ: ό,τι έχει οριστεί χειροκίνητα ΔΕΝ το ξαναγράφει ποτέ ο συγχρονισμός.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Pbx3cxSync
{
    /** Τι τραβάμε και πώς λέγεται στον χάρτη μας. */
    /* Κάθε σύνολο έχει ΔΙΚΑ ΤΟΥ πεδία: το Users έχει FirstName/LastName, τα
       Queues/RingGroups έχουν Name. Κοινό $select δίνει HTTP 400 — το OData
       απορρίπτει άγνωστο πεδίο (επαληθεύτηκε στη δοκιμή). */
    private static $SETS = [
        ['Users', 'extension', 'Id,Number,FirstName,LastName,EmailAddress'],
        ['Queues', 'queue', 'Id,Number,Name'],
        ['RingGroups', 'ringgroup', 'Id,Number,Name'],
    ];

    /**
     * Εκτελεί τον συγχρονισμό. Επιστρέφει σύνοψη — ποτέ δεν πετάει για ένα
     * σύνολο που απέτυχε: τα υπόλοιπα πρέπει να περάσουν.
     */
    public static function run()
    {
        $now = date('Y-m-d H:i:s');
        $res = ['at' => $now, 'sets' => [], 'new' => 0, 'updated' => 0,
            'matched' => 0, 'gone' => 0, 'errors' => []];

        /* Ο κατάλογος email → χειριστής, μία φορά. */
        $byMail = [];
        foreach (Capsule::table('tbladmins')->where('disabled', 0)->get(['id', 'email']) as $a) {
            $em = mb_strtolower(trim((string) $a->email));
            if ($em !== '') { $byMail[$em] = (int) $a->id; }
        }

        $seen = [];
        foreach (self::$SETS as [$set, $type, $select]) {
            try {
                $rows = self::page($set, $select);
                $res['sets'][$set] = count($rows);
                foreach ($rows as $r) {
                    $dn = trim((string) ($r['Number'] ?? ''));
                    if ($dn === '') { continue; }
                    $seen[] = $dn;
                    $name = trim((string) ($r['Name'] ?? ''));
                    if ($name === '') {
                        $name = trim(((string) ($r['FirstName'] ?? '')) . ' ' . ((string) ($r['LastName'] ?? '')));
                    }
                    $mail = mb_strtolower(trim((string) ($r['EmailAddress'] ?? '')));
                    $cur = Capsule::table('mod_cpm_pbx_map')->where('dn', $dn)->first();

                    $data = ['dn_type' => $type, 'display_name' => mb_substr($name, 0, 120) ?: null,
                        'email' => $mail ?: null, 'active' => 1, 'synced_at' => $now];

                    /* Η χειροκίνητη αντιστοίχιση είναι ιερή. */
                    if (!$cur || $cur->matched_by !== 'manual') {
                        if ($mail !== '' && isset($byMail[$mail])) {
                            $data['admin_id'] = $byMail[$mail];
                            $data['matched_by'] = 'email';
                        } elseif (!$cur || $cur->matched_by === 'email') {
                            /* Έχασε το email του ή δεν ταιριάζει πια → καθάρισε,
                               αλλιώς θα κρατούσαμε λάθος αντιστοίχιση για πάντα. */
                            $data['admin_id'] = null;
                            $data['matched_by'] = 'none';
                        }
                    }
                    if ($cur) {
                        Capsule::table('mod_cpm_pbx_map')->where('id', $cur->id)->update($data);
                        $res['updated']++;
                    } else {
                        Capsule::table('mod_cpm_pbx_map')->insert($data + ['dn' => $dn]);
                        $res['new']++;
                    }
                    if (!empty($data['admin_id'])) { $res['matched']++; }
                }
            } catch (\Throwable $e) {
                $res['errors'][] = $set . ': ' . $e->getMessage();
                Pbx3cxClient::log('sync', 'error', $set . ' — ' . $e->getMessage());
            }
        }

        /* Ό,τι δεν είδαμε δεν το ΣΒΗΝΟΥΜΕ — το σημαδεύουμε ανενεργό. Ένα DN που
           καταργήθηκε μπορεί να έχει ιστορικό κλήσεων που πρέπει να διαβάζεται. */
        if ($seen && empty($res['errors'])) {
            $res['gone'] = Capsule::table('mod_cpm_pbx_map')->whereNotIn('dn', $seen)
                ->where('active', 1)->update(['active' => 0]);
        }

        Pbx3cxClient::setCfg('last_sync', json_encode($res, JSON_UNESCAPED_UNICODE));
        Pbx3cxClient::log('sync', $res['errors'] ? 'error' : 'ok',
            'Νέα: ' . $res['new'] . ' · ενημερώθηκαν: ' . $res['updated']
            . ' · αντιστοιχισμένα: ' . $res['matched'] . ' · ανενεργά: ' . $res['gone']);
        return $res;
    }

    /**
     * Σελιδοποίηση. ΜΕΤΡΗΘΗΚΕ: το XAPI απορρίπτει $top > 100 με HTTP 400.
     * Δεν είναι θεωρητικό όριο — το επιβεβαιώσαμε στο ίδιο μας το PBX.
     */
    const PAGE = 100;

    private static function page($set, $select)
    {
        $all = [];
        for ($skip = 0; $skip < 10000; $skip += self::PAGE) {
            $q = ['$top' => self::PAGE, '$select' => $select];
            if ($skip > 0) { $q['$skip'] = $skip; }
            $j = Pbx3cxClient::xapi($set, $q);
            $v = $j['value'] ?? [];
            $all = array_merge($all, $v);
            if (count($v) < self::PAGE) { break; }
        }
        return $all;
    }

    /**
     * ΖΩΝΤΑΝΕΣ ΚΛΗΣΕΙΣ — «ποιος μιλάει τώρα».
     *
     * Είναι το ΜΟΝΟ κομμάτι τηλεφωνικής δραστηριότητας που δίνει σήμερα το PBX
     * μετά τη μεταφορά σε νέο server: το ιστορικό της αναφοράς είναι κενό, αλλά
     * οι ενεργές κλήσεις διαβάζονται κανονικά. Τις δένουμε με τον χάρτη DN →
     * χειριστή ώστε να φαίνεται ΟΝΟΜΑ, όχι νούμερο.
     */
    public static function live()
    {
        $j = Pbx3cxClient::xapi('ActiveCalls');
        $rows = $j['value'] ?? [];
        if (!$rows) { return []; }

        /* Χάρτης DN → χειριστής, μία φορά. */
        $map = [];
        foreach (Capsule::table('mod_cpm_pbx_map')->whereNotNull('admin_id')->get() as $r) {
            $map[(string) $r->dn] = ['id' => (int) $r->admin_id, 'name' => Db::adminName((int) $r->admin_id)];
        }
        /* Ένα «0030…» και ένα «+30…» είναι ο ίδιος πελάτης — κανονικοποίηση. */
        $norm = function ($n) {
            $d = preg_replace('/\D+/', '', (string) $n);
            if ($d === '') { return ''; }
            if (strpos($d, '0030') === 0) { $d = substr($d, 4); }
            elseif (strpos($d, '30') === 0 && strlen($d) === 12) { $d = substr($d, 2); }
            return $d;
        };

        $out = [];
        foreach ($rows as $r) {
            $from = (string) ($r['Caller'] ?? '');
            $to = (string) ($r['Callee'] ?? '');
            $who = $map[$from] ?? $map[$to] ?? null;
            /* Ό,τι δεν είναι δικό μας extension είναι ο «άλλος» — ο πελάτης. */
            $other = isset($map[$from]) ? $to : $from;
            $started = !empty($r['EstablishedAt']) ? strtotime($r['EstablishedAt'])
                : (!empty($r['LastChangeStatus']) ? strtotime($r['LastChangeStatus']) : 0);
            $out[] = [
                'id' => (int) ($r['Id'] ?? 0),
                'status' => (string) ($r['Status'] ?? ''),
                'answered' => !empty($r['EstablishedAt']),
                'from' => $from, 'to' => $to,
                'other' => $other, 'otherE164' => $norm($other),
                'admin' => $who['id'] ?? 0, 'adminName' => $who['name'] ?? '',
                'since' => $started ? date('Y-m-d H:i:s', $started) : null,
                'seconds' => $started ? max(0, time() - $started) : 0,
            ];
        }
        return $out;
    }

    /**
     * Εισαγωγή ιστορικού από το CallHistoryView στον πίνακα κλήσεων.
     *
     * Το CallHistoryView είναι ΑΡΧΕΙΟ (2018 → 15/04/2025, μετά τη μεταφορά του
     * PBX σε νέο server). Το φέρνουμε για να υπάρχει ιστορικό και σύγκριση· τα
     * τρέχοντα θα έρχονται από το CDR.
     *
     * Διαβάζει από το ΤΕΛΟΣ (τα πιο πρόσφατα) προς τα πίσω.
     */
    public static function importHistory($max = 2000)
    {
        /* Το αρχείο έχει >100k γραμμές — το μέτρημα και το paging θέλουν χρόνο. */
        $j = Pbx3cxClient::xapi('CallHistoryView', ['$top' => 1, '$count' => 'true'], 120);
        $total = (int) ($j['@odata.count'] ?? 0);
        if ($total === 0) { return ['total' => 0, 'new' => 0, 'seen' => 0]; }

        $res = ['total' => $total, 'new' => 0, 'seen' => 0, 'from' => null, 'to' => null];
        $page = 100;
        /* ΠΡΟΣΟΧΗ: βαθύ $skip σε 123k γραμμές ρίχνει το PBX σε 504 (μετρήθηκε).
           Ταξινομούμε ΦΘΙΝΟΥΣΑ και παίρνουμε από την κορυφή — τα πιο πρόσφατα
           πρώτα, με ρηχό skip που το αντέχει. */
        for ($skip = 0; $skip < $max; $skip += $page) {
            $r = Pbx3cxClient::xapi('CallHistoryView',
                ['$top' => $page, '$skip' => $skip, '$orderby' => 'SegmentStartTime desc'], 120);
            if (empty($r['value'])) { break; }
            foreach ($r['value'] ?? [] as $v) {
                $res['seen']++;
                $out = self::fromSegment($v);
                if ($out) { $res['new'] += $out; }
                $st = substr((string) ($v['SegmentStartTime'] ?? ''), 0, 19);
                if ($st !== '') {
                    if ($res['from'] === null || $st < $res['from']) { $res['from'] = $st; }
                    if ($res['to'] === null || $st > $res['to']) { $res['to'] = $st; }
                }
            }
        }
        Pbx3cxClient::log('sync', 'ok', 'Ιστορικό: ' . $res['new'] . ' νέες από ' . $res['seen'] . ' segments');
        return $res;
    }

    /** Ένα segment του αρχείου → εγγραφή κλήσης (idempotent στο seg-<id>). */
    private static function fromSegment(array $v)
    {
        $seg = (int) ($v['SegmentId'] ?? 0);
        if (!$seg) { return 0; }
        $hid = 'seg-' . $seg;
        if (Capsule::table('mod_cpm_calls')->where('history_id', $hid)->exists()) { return 0; }

        $srcDn = trim((string) ($v['SrcDn'] ?? ''));
        $dstDn = trim((string) ($v['DstDn'] ?? ''));
        $srcNo = trim((string) ($v['SrcCallerNumber'] ?? ''));
        $dstNo = trim((string) ($v['DstCallerNumber'] ?? ''));
        $srcInt = !empty($v['SrcInternal']);
        $dstInt = !empty($v['DstInternal']);

        $dir = ($srcInt && $dstInt) ? 'internal' : ($dstInt ? 'in' : ($srcInt ? 'out' : 'internal'));
        $other = $dir === 'in' ? ($srcNo ?: $srcDn) : ($dir === 'out' ? ($dstNo ?: $dstDn) : '');
        $e164 = Pbx3cxCdr::e164($other);

        $map = Capsule::table('mod_cpm_pbx_map')->whereIn('dn', array_filter([$srcDn, $dstDn]))->get()->keyBy('dn');
        $adminId = null;
        if ($dir === 'in' && isset($map[$dstDn])) { $adminId = $map[$dstDn]->admin_id ? (int) $map[$dstDn]->admin_id : null; }
        elseif (isset($map[$srcDn])) { $adminId = $map[$srcDn]->admin_id ? (int) $map[$srcDn]->admin_id : null; }

        [$cid, $how] = $e164 !== '' ? Pbx3cxCdr::matchClient($e164) : [null, null];

        $start = substr((string) ($v['SegmentStartTime'] ?? ''), 0, 19);
        $end = substr((string) ($v['SegmentEndTime'] ?? ''), 0, 19);
        $talk = 0;
        if (!empty($v['CallTime'])) {
            try { $i = new \DateInterval((string) $v['CallTime']); $talk = $i->h * 3600 + $i->i * 60 + $i->s; }
            catch (\Throwable $e) { $talk = 0; }
        }
        Capsule::table('mod_cpm_calls')->insert([
            'history_id' => $hid, 'direction' => $dir,
            'started_at' => $start ? str_replace('T', ' ', $start) : date('Y-m-d H:i:s'),
            'ended_at' => $end ? str_replace('T', ' ', $end) : null,
            'answered_at' => !empty($v['CallAnswered']) && $start ? str_replace('T', ' ', $start) : null,
            'talk_seconds' => $talk, 'answered' => !empty($v['CallAnswered']) ? 1 : 0,
            'from_no' => mb_substr($srcNo, 0, 40) ?: null, 'to_no' => mb_substr($dstNo, 0, 40) ?: null,
            'from_dn' => mb_substr($srcDn, 0, 20) ?: null, 'to_dn' => mb_substr($dstDn, 0, 20) ?: null,
            'other_e164' => $e164 ?: null, 'admin_id' => $adminId,
            'clientid' => $cid, 'client_match' => $how,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return 1;
    }

    /** Ο χάρτης όπως τον βλέπει η οθόνη. */
    public static function map()
    {
        $out = [];
        foreach (Capsule::table('mod_cpm_pbx_map')->orderBy('dn_type')->orderBy('dn')->get() as $r) {
            $out[] = ['id' => (int) $r->id, 'dn' => $r->dn, 'type' => $r->dn_type,
                'name' => $r->display_name, 'email' => $r->email,
                'admin' => $r->admin_id ? (int) $r->admin_id : 0,
                'adminName' => $r->admin_id ? Db::adminName((int) $r->admin_id) : '',
                'by' => $r->matched_by, 'active' => (bool) $r->active];
        }
        return $out;
    }
}
