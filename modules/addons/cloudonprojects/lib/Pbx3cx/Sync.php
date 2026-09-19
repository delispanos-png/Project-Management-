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
