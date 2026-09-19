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

    /* ΑΦΑΙΡΕΘΗΚΕ: importHistory() / fromSegment().
     *
     * Διάβαζαν το CallHistoryView με βαθύ $skip σε ~123.000 γραμμές. Στις
     * 19/09/2026 αυτά τα ερωτήματα έριξαν την PostgreSQL του PBX: το κέντρο
     * απάντησε 504, και λίγο μετά η υπηρεσία postgresql ήταν Stopped — τα
     * τηλέφωνα δούλευαν, αλλά κανείς δεν μπορούσε να συνδεθεί (HTTP 500 σε
     * κάθε έκδοση token, web login και API).
     *
     * Δεν τα χρειαζόμαστε: το αρχείο σταματά στις 15/04/2025 (μεταφορά σε νέο
     * server) και ζητήθηκαν δεδομένα ΜΟΝΟ της τρέχουσας χρονιάς. Το ιστορικό
     * έρχεται από το CDR. Μην ξαναγραφτεί paging πάνω στο CallHistoryView. */

    /**
     * Ο εταιρικός κατάλογος του 3CX → δικός μας πίνακας.
     *
     * ΤΟ ΠΡΟΒΛΗΜΑ ΠΟΥ ΛΥΝΕΙ: το WHMCS έχει τηλέφωνο για 210 πελάτες, οπότε 9
     * στις 10 κλήσεις έβγαιναν γυμνός αριθμός. Το τηλεφωνικό κέντρο όμως έχει
     * 476 επαφές που η ομάδα έχει ήδη καταχωρήσει. Τις διαβάζουμε ώστε να
     * φαίνεται ΟΝΟΜΑ μέχρι να περαστεί ο πελάτης κανονικά στο WHMCS.
     *
     * ΔΕΝ γίνονται πελάτες: δεν αγγίζουμε το clientid πουθενά. Είναι μόνο
     * ετικέτα εμφάνισης, και φαίνεται ρητά ότι προέρχεται από το 3CX.
     */
    public static function book()
    {
        /* ΠΡΟΣΟΧΗ: το πεδίο «Other» ΔΕΝ είναι πάντα τηλέφωνο — μετρήθηκε να
           κρατά τίτλο θέσης («Senior Technical Consultant»). Δεχόμαστε μόνο
           τιμές που μοιάζουν με αριθμό. */
        $fields = ['PhoneNumber', 'Mobile2', 'Business', 'Business2', 'Home', 'Other'];
        $now = date('Y-m-d H:i:s');
        $seen = [];
        $rows = 0;

        for ($skip = 0; $skip < 5000; $skip += 100) {
            $j = Pbx3cxClient::xapi('Contacts', ['$top' => 100, '$skip' => $skip], 30);
            $v = $j['value'] ?? [];
            if (!$v) { break; }
            foreach ($v as $c) {
                $cid = (int) ($c['Id'] ?? 0);
                $nm = trim(trim((string) ($c['FirstName'] ?? '')) . ' ' . trim((string) ($c['LastName'] ?? '')));
                $co = trim((string) ($c['CompanyName'] ?? ''));
                /* Δείχνουμε ό,τι αναγνωρίζει άνθρωπος: επωνυμία αν υπάρχει,
                   αλλιώς το όνομα. Αν υπάρχουν και τα δύο, μπαίνουν μαζί. */
                $label = $co !== '' && $nm !== '' ? $co . ' — ' . $nm : ($co !== '' ? $co : $nm);
                if ($label === '') { continue; }

                foreach ($fields as $f) {
                    $raw = trim((string) ($c[$f] ?? ''));
                    if ($raw === '' || !preg_match('/^[\d\s()+.\-]{6,}$/', $raw)) { continue; }
                    $e = Pbx3cxCdr::e164($raw);
                    if ($e === '' || strlen(preg_replace('/\D/', '', $e)) < 8) { continue; }
                    $key = $e . '|' . $cid;
                    if (isset($seen[$key])) { continue; }
                    $seen[$key] = 1;
                    Capsule::table('mod_cpm_pbx_book')->updateOrInsert(
                        ['e164' => $e, 'contact_id' => $cid],
                        ['name' => mb_substr($label, 0, 160), 'company' => mb_substr($co, 0, 160),
                         'field' => $f, 'synced_at' => $now]);
                    $rows++;
                }
            }
            if (count($v) < 100) { break; }
        }

        /* Ό,τι έφυγε από τον κατάλογο του 3CX φεύγει και από εδώ — αλλιώς θα
           δείχναμε για πάντα όνομα που κάποιος έσβησε επίτηδες. */
        $gone = Capsule::table('mod_cpm_pbx_book')->where('synced_at', '<', $now)->delete();

        Pbx3cxClient::log('sync', 'ok', 'Κατάλογος 3CX: ' . $rows . ' αριθμοί'
            . ($gone ? ' · έφυγαν ' . $gone : ''));
        return ['numbers' => $rows, 'removed' => (int) $gone];
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
