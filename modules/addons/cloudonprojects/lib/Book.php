<?php
/**
 * CloudOn — ο τηλεφωνικός κατάλογος της εταιρείας.
 *
 * Η ΑΡΧΗ: εδώ είναι η αλήθεια. Το 3CX διαβάζεται ΜΙΑ φορά για να γεμίσει ο
 * κατάλογος με ό,τι έχει ήδη καταχωρήσει η ομάδα· από εκεί και πέρα κάθε αλλαγή
 * γίνεται εδώ και ταξιδεύει προς το τηλεφωνικό κέντρο. Αμφίδρομος συγχρονισμός
 * δεν υπάρχει επίτηδες: θα κέρδιζε πάντα ο τελευταίος που πάτησε αποθήκευση,
 * και κανείς δεν θα ήξερε ποιος ούτε πότε.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Book
{
    /** Οι ετικέτες τηλεφώνου και πού αντιστοιχούν στο 3CX. */
    public static function phoneLabels()
    {
        return [
            'main'   => ['Κύριο',    'PhoneNumber'],
            'mobile' => ['Κινητό',   'Mobile2'],
            'work'   => ['Σταθερό',  'Business'],
            'work2'  => ['Σταθερό 2', 'Business2'],
            'home'   => ['Οικίας',   'Home'],
            'fax'    => ['Fax',      'BusinessFax'],
            'other'  => ['Άλλο',     'Other'],
        ];
    }

    public static function statuses()
    {
        /* ΜΟΝΟ «ζει ή όχι». Ο «Υποψήφιος» και ο «Προμηθευτής» έφυγαν από εδώ:
           δεν είναι καταστάσεις, είναι ΣΧΕΣΕΙΣ — βλ. rels(). Ανακατεμένα, μια
           φαρμακαποθήκη που απλώς συνεργαζόμαστε δεν χωρούσε πουθενά. */
        return ['active' => 'Ενεργός', 'inactive' => 'Ανενεργός'];
    }

    /**
     * Τι μας είναι η επαφή.
     *
     * Κάθε τιμή λέει: [ετικέτα, αν έχει δικά ΜΑΣ προϊόντα]. Ο προμηθευτής δεν
     * έχει — εμείς έχουμε δικά του — οπότε στην καρτέλα του δεν έχει νόημα να
     * ρωτάμε τι προϊόντα μας χρησιμοποιεί ούτε πώς δρομολογούνται οι κλήσεις
     * του στην υποστήριξή μας.
     */
    public static function rels()
    {
        return [
            'client'   => ['Πελάτης', true],
            'prospect' => ['Υποψήφιος πελάτης', true],
            'partner'  => ['Συνεργάτης', true],
            'supplier' => ['Προμηθευτής', false],
        ];
    }

    /** Δείχνουμε προϊόντα & δρομολόγηση σε αυτή τη σχέση; */
    public static function relHasProducts($rel)
    {
        $r = self::rels();
        return !isset($r[(string) $rel]) || $r[(string) $rel][1];
    }

    /**
     * Καθαρίζει κείμενο που ήρθε HTML-ξεφευγμένο.
     *
     * Το WHMCS αποθηκεύει τις επωνυμίες ξεφευγμένες: «Σ.ΛΕΩΝ &amp; ΣΙΑ Ε.Ε».
     * Αν το κρατήσουμε έτσι, η οθόνη το ξαναξεφεύγει και ο χρήστης διαβάζει
     * κυριολεκτικά «&amp;» — και το ίδιο ταξιδεύει και στις οθόνες των
     * τηλεφώνων μέσω του 3CX.
     */
    public static function plain($v)
    {
        $v = trim((string) $v);
        if ($v === '' || strpos($v, '&') === false) { return $v; }
        /* Δύο περάσματα: κάποια πεδία είναι διπλά ξεφευγμένα (&amp;amp;). */
        for ($i = 0; $i < 2 && strpos($v, '&') !== false; $i++) {
            $d = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($d === $v) { break; }
            $v = $d;
        }
        return trim($v);
    }

    /** Το όνομα που βλέπει άνθρωπος — και που στέλνεται στο τηλέφωνο. */
    public static function label(array $b)
    {
        $co = trim((string) ($b['company'] ?? ''));
        $per = trim(trim((string) ($b['first'] ?? '')) . ' ' . trim((string) ($b['last'] ?? '')));
        if ($co !== '' && $per !== '') { return $co . ' — ' . $per; }
        return $co !== '' ? $co : $per;
    }

    /* ── ΚΑΤΑΛΟΓΟΣ ↔ 3CX, ΔΙΠΛΗ ΚΑΤΕΥΘΥΝΣΗ (20/09/2026) ─────────────────────
       Ο δικός μας κατάλογος είναι ο πλήρης και ΕΜΕΙΣ γράφουμε στο κέντρο
       (push). Αλλά όποιος διορθώσει μια επαφή μέσα στο 3CX — από το app, τη
       συσκευή ή την κονσόλα — δεν πρέπει να χάσει τη δουλειά του.

       Το 3CX δεν λέει ΠΟΤΕ άλλαξε κάτι. Γι' αυτό συγκρίνουμε ό,τι ΒΛΕΠΟΥΜΕ εκεί
       με ό,τι ΘΑ ΣΤΕΛΝΑΜΕ εμείς τώρα (pbxBody). Επειδή η αποστολή γράφει πάντα
       ολόκληρη την επαφή, κάθε διαφορά σημαίνει ακριβώς «άλλαξε στο 3CX μετά την
       τελευταία αποστολή» — και περνά στην καρτέλα. Κανόνες:
       · Καρτέλα με δική της αλλαγή που δεν έχει σταλεί ακόμη: κερδίζει η καρτέλα.
       · Νέα επαφή στο 3CX → νέα καρτέλα, ή δένεται σε καρτέλα με το ίδιο τηλέφωνο.
       · Επαφή που σβήστηκε στο 3CX → η καρτέλα ΜΕΝΕΙ και ξαναστέλνεται. Η
         διαγραφή γίνεται από εδώ, όχι από το τηλέφωνο.
       · Αριθμός που ανήκει ήδη σε άλλη καρτέλα δεν μετακινείται σιωπηλά.
       Τρέχει κάθε 10' από το pulse. */

    /** Πεδία κειμένου του 3CX → στήλες της καρτέλας (και όρια μήκους). */
    const PBX_TEXT = ['FirstName' => ['first', 60], 'LastName' => ['last', 60],
                      'CompanyName' => ['company', 160], 'Email' => ['email', 120],
                      'Title' => ['title', 80], 'Department' => ['department', 80]];

    /** Όλες οι επαφές του κέντρου, ανά Id. ($skip/$orderby δίνουν 400 — σελίδες με Id gt.) */
    public static function pbxContacts()
    {
        $live = []; $last = 0;
        for ($i = 0; $i < 100; $i++) {
            $j = Pbx3cxClient::xapi('Contacts', ['$top' => 100, '$filter' => 'Id gt ' . $last], 30);
            $v = $j['value'] ?? [];
            foreach ($v as $c) {
                $pid = (int) ($c['Id'] ?? 0);
                if ($pid) { $live[$pid] = $c; $last = max($last, $pid); }
            }
            if (count($v) < 100) { break; }
        }
        return $live;
    }

    /** Τα τηλέφωνα μιας επαφής του 3CX, κανονικοποιημένα: e164 => [label, raw]. */
    public static function pbxPhones(array $c)
    {
        $out = [];
        foreach (self::phoneLabels() as $lab => $v) {
            $raw = trim((string) ($c[$v[1]] ?? ''));
            /* Το «Other» του 3CX κρατά συχνά τίτλο θέσης, όχι τηλέφωνο. */
            if ($raw === '' || !preg_match('/^[\d\s()+.\-]{6,}$/', $raw)) { continue; }
            $e = Pbx3cxCdr::e164($raw);
            if (strlen(preg_replace('/\D/', '', $e)) < 8 || isset($out[$e])) { continue; }
            $out[$e] = ['label' => $lab, 'raw' => mb_substr($raw, 0, 40)];
        }
        return $out;
    }

    /** Το επώνυμο χωρίς την εσωτερική σήμανση «[Support]» / «[Εταιρεία]». */
    public static function stripHint($v)
    {
        return trim(preg_replace('/\s*\[[^\]]*\]\s*$/u', '', (string) $v));
    }

    /**
     * Διαβάζει το κέντρο και φέρνει εδώ ό,τι άλλαξε εκεί.
     * @return array new, linked, updated, gone, skipped, phones, changes[]
     */
    public static function pullFromPbx()
    {
        $res = ['new' => 0, 'linked' => 0, 'updated' => 0, 'gone' => 0, 'skipped' => 0, 'phones' => 0, 'changes' => []];
        $live = self::pbxContacts();
        if (!$live) { return $res; }   // κενή απάντηση: δεν πειράζουμε τίποτα
        $now = date('Y-m-d H:i:s');
        $cards = [];
        foreach (Capsule::table('mod_cpm_book')->whereNotNull('pbx_id')->get() as $b) { $cards[(int) $b->pbx_id] = $b; }
        $toLabel = [];
        foreach (self::phoneLabels() as $k => $v) { $toLabel[$v[1]] = $k; }

        foreach ($live as $pid => $c) {
            $b = $cards[$pid] ?? null;
            if (!$b) {
                $r = self::adoptFromPbx($c, $now);
                if ($r === 'new') { $res['new']++; } elseif ($r === 'linked') { $res['linked']++; } else { $res['skipped']++; }
                if ($r === 'new' || $r === 'linked') { $res['changes'][] = self::label(['company' => $c['CompanyName'] ?? '', 'first' => $c['FirstName'] ?? '', 'last' => $c['LastName'] ?? '']) . ' (' . ($r === 'new' ? 'νέα από 3CX' : 'δέθηκε') . ')'; }
                continue;
            }
            /* Δική μας αλλαγή που δεν έχει φύγει ακόμη: θα σταλεί, δεν διαβάζουμε. */
            if ((int) $b->to_pbx === 1 && ($b->pbx_at === null || $b->updated_at > $b->pbx_at)) { $res['skipped']++; continue; }

            $phones = Capsule::table('mod_cpm_book_phones')->where('book_id', $b->id)->orderBy('sort')->get();
            /* Καρτέλα χωρίς τηλέφωνο δεν στέλνεται ποτέ — ούτε συγκρίνεται, αλλιώς
               θα «έβλεπε» τη διαφορά κάθε 10΄ για πάντα. */
            $exp = self::pbxBody($b, $phones);
            if (!$exp) { $res['skipped']++; continue; }
            $upd = []; $what = []; $touched = [];

            foreach (self::PBX_TEXT as $f => $m) {
                $lv = self::plain($c[$f] ?? ''); $ev = trim((string) ($exp[$f] ?? ''));
                if ($f === 'LastName') { $lv = self::stripHint($lv); $ev = self::stripHint($ev); }
                if ($lv === $ev) { continue; }
                $upd[$m[0]] = mb_substr($lv, 0, $m[1]) ?: null;
                $what[] = $m[0] . ' «' . $ev . '» → «' . $lv . '»';
            }

            /* Τηλέφωνα ως ΣΥΝΟΛΑ αριθμών, όχι ανά πεδίο: το «Κύριο» καθρεφτίζει το
               πρώτο τηλέφωνο όταν δεν υπάρχει κύριο, οπότε η σύγκριση ανά πεδίο
               θα έβλεπε φαντάσματα. Ένα έφυγε + ένα ήρθε = διόρθωση αριθμού. */
            $expNums = [];
            foreach ($toLabel as $f => $lab) { $v = trim((string) ($exp[$f] ?? '')); if ($v !== '') { $expNums[$v] = 1; } }
            $liveNums = self::pbxPhones($c);
            $cardNums = [];
            foreach ($phones as $p) { $cardNums[$p->e164] = $p; }
            $removed = []; $added = [];
            foreach ($cardNums as $e => $p) { if (isset($expNums[$e]) && !isset($liveNums[$e])) { $removed[$e] = $p; } }
            foreach ($liveNums as $e => $i) {
                if (isset($cardNums[$e])) { continue; }
                $owner = Capsule::table('mod_cpm_book_phones')->where('e164', $e)->where('book_id', '<>', $b->id)->value('book_id');
                if ($owner) { $what[] = $e . ' ανήκει ήδη στην καρτέλα #' . $owner . ' — δεν μεταφέρθηκε'; continue; }
                $added[$e] = $i;
            }
            if (count($removed) === 1 && count($added) === 1) {
                $p = reset($removed); $e = array_key_first($added);
                Capsule::table('mod_cpm_book_phones')->where('id', $p->id)
                    ->update(['e164' => $e, 'raw' => $added[$e]['raw']]);
                $touched[] = $p->e164; $touched[] = $e; $res['phones']++;
                $what[] = 'τηλέφωνο ' . $p->e164 . ' → ' . $e;
            } else {
                foreach ($removed as $e => $p) {
                    Capsule::table('mod_cpm_book_phones')->where('id', $p->id)->delete();
                    $touched[] = $e; $res['phones']++; $what[] = 'αφαιρέθηκε ' . $e;
                }
                $sort = 90;
                foreach ($added as $e => $i) {
                    Capsule::table('mod_cpm_book_phones')->insertOrIgnore(['book_id' => $b->id, 'e164' => $e,
                        'raw' => $i['raw'], 'label' => $i['label'], 'sort' => $sort++]);
                    $touched[] = $e; $res['phones']++; $what[] = 'νέο ' . $e;
                }
            }
            if (!$upd && !$touched) { continue; }

            /* pbx_at = updated_at: η καρτέλα ΔΕΝ είναι «προς αποστολή» — ό,τι
               μπήκε ήρθε από εκεί. updated_by κενό = το σύστημα, όχι άνθρωπος. */
            $upd += ['updated_at' => $now, 'updated_by' => null, 'pbx_at' => $now, 'pbx_error' => null];
            Capsule::table('mod_cpm_book')->where('id', $b->id)->update($upd);
            foreach (array_unique($touched) as $e) { self::reindexCalls($e); }
            $res['updated']++;
            $res['changes'][] = self::label((array) $b) . ': ' . implode(', ', $what);
        }

        /* Σβήστηκε στο 3CX: η καρτέλα μένει· χωρίς pbx_id θα ξανασταλεί. */
        foreach ($cards as $pid => $b) {
            if (isset($live[$pid])) { continue; }
            Capsule::table('mod_cpm_book')->where('id', $b->id)->update(['pbx_id' => null, 'pbx_at' => null]);
            $res['gone']++;
        }

        if ($res['new'] || $res['linked']) { self::linkClients(); }
        if ($res['new'] || $res['linked'] || $res['phones']) { self::refreshLastCall(); }
        if ($res['new'] || $res['linked'] || $res['updated'] || $res['gone']) {
            Pbx3cxClient::log('sync', 'ok', 'Κατάλογος ← 3CX: ' . $res['new'] . ' νέες, ' . $res['linked']
                . ' δέθηκαν, ' . $res['updated'] . ' ενημερώθηκαν, ' . $res['gone'] . ' έλειπαν εκεί'
                . ($res['changes'] ? ' — ' . mb_substr(implode(' · ', $res['changes']), 0, 700) : ''));
        }
        return $res;
    }

    /**
     * Επαφή που υπάρχει στο 3CX και όχι εδώ: νέα καρτέλα, ή δέσιμο σε καρτέλα
     * που έχει ήδη κάποιο από τα τηλέφωνά της (χωρίς να χαθεί τίποτα δικό της).
     * @return string new | linked | skipped
     */
    private static function adoptFromPbx(array $c, $now)
    {
        $pid = (int) ($c['Id'] ?? 0);
        $row = [];
        foreach (self::PBX_TEXT as $f => $m) {
            $v = self::plain($c[$f] ?? '');
            if ($f === 'LastName') { $v = self::stripHint($v); }
            $row[$m[0]] = mb_substr($v, 0, $m[1]) ?: null;
        }
        $phones = self::pbxPhones($c);
        if (!$pid || !$phones || self::label($row) === '') { return 'skipped'; }

        $ex = Capsule::table('mod_cpm_book_phones as p')->join('mod_cpm_book as b', 'b.id', '=', 'p.book_id')
            ->whereIn('p.e164', array_keys($phones))->orderBy('b.id')->select('b.*')->first();
        if ($ex) {
            if ($ex->pbx_id) { return 'skipped'; }   // διπλή επαφή στο 3CX — η καρτέλα δένεται ήδη αλλού
            $upd = [];
            foreach ($row as $k => $v) { if ($v !== null && trim((string) ($ex->$k ?? '')) === '') { $upd[$k] = $v; } }
            $have = Capsule::table('mod_cpm_book_phones')->where('book_id', $ex->id)->pluck('e164')->all();
            $sort = 90;
            foreach ($phones as $e => $i) {
                if (in_array($e, $have, true)) { continue; }
                Capsule::table('mod_cpm_book_phones')->insertOrIgnore(['book_id' => $ex->id, 'e164' => $e,
                    'raw' => $i['raw'], 'label' => $i['label'], 'sort' => $sort++]);
            }
            $upd += ['pbx_id' => $pid, 'pbx_at' => $now, 'pbx_error' => null, 'to_pbx' => 1, 'updated_at' => $now, 'updated_by' => null];
            Capsule::table('mod_cpm_book')->where('id', $ex->id)->update($upd);
            return 'linked';
        }

        $row += ['kind' => trim((string) $row['company']) !== '' ? 'company' : 'person',
                 'status' => 'active', 'to_pbx' => 1, 'pbx_id' => $pid, 'pbx_at' => $now,
                 'created_at' => $now, 'updated_at' => $now];
        $id = (int) Capsule::table('mod_cpm_book')->insertGetId($row);
        $sort = 0;
        foreach ($phones as $e => $i) {
            Capsule::table('mod_cpm_book_phones')->insertOrIgnore(['book_id' => $id, 'e164' => $e,
                'raw' => $i['raw'], 'label' => $i['label'], 'sort' => $sort++]);
            self::reindexCalls($e);
        }
        return 'new';
    }

    /** Το κουμπί «γέμισμα από 3CX» της οθόνης — πια το ίδιο με το pulse. */
    public static function importFromPbx()
    {
        $r = self::pullFromPbx();
        return ['new' => $r['new'] + $r['linked'], 'updated' => $r['updated'],
                'skipped' => $r['skipped'], 'phones' => $r['phones']];
    }

    /**
     * ΠΟΙΟΣ ΕΙΝΑΙ ΑΥΤΟ ΤΟ ΤΗΛΕΦΩΝΟ — η ΜΟΝΗ διαδρομή αναγνώρισης.
     *
     * Παλιότερα ψάχναμε σε τρία μέρη: πελάτες WHMCS, επαφές πελατών, κατάλογο
     * 3CX. Τρεις πηγές σημαίνει τρεις διαφορετικές απαντήσεις για τον ίδιο
     * αριθμό και καμία που να μπορείς να διορθώσεις. Τώρα ρωτάμε ΜΟΝΟ τον
     * κατάλογο· ο πελάτης WHMCS είναι ιδιότητα της καρτέλας, όχι ξεχωριστός
     * δρόμος. Ό,τι λείπει, μπαίνει στον κατάλογο — και διορθώνεται μια φορά,
     * για όλους.
     *
     * @return array|null ['id','name','clientid']
     */
    public static function resolve($e164)
    {
        $e = trim((string) $e164);
        if ($e === '') { return null; }
        static $memo = [];
        if (array_key_exists($e, $memo)) { return $memo[$e]; }
        $r = Capsule::table('mod_cpm_book_phones as p')
            ->join('mod_cpm_book as b', 'b.id', '=', 'p.book_id')
            ->where('p.e164', $e)
            ->select('b.id', 'b.company', 'b.first', 'b.last', 'b.clientid')
            ->orderBy('b.id')->first();
        $memo[$e] = $r ? ['id' => (int) $r->id,
            'name' => self::label(['company' => $r->company, 'first' => $r->first, 'last' => $r->last]),
            'clientid' => $r->clientid ? (int) $r->clientid : null] : null;
        return $memo[$e];
    }

    /** Πολλά τηλέφωνα μαζί — μία ερώτηση αντί για εκατό. */
    public static function resolveMany(array $nums)
    {
        $nums = array_values(array_unique(array_filter($nums)));
        if (!$nums) { return []; }
        $out = [];
        foreach (Capsule::table('mod_cpm_book_phones as p')
            ->join('mod_cpm_book as b', 'b.id', '=', 'p.book_id')
            ->whereIn('p.e164', $nums)
            ->select('p.e164', 'b.id', 'b.company', 'b.first', 'b.last', 'b.clientid')
            ->orderBy('b.id')->get() as $r) {
            if (isset($out[$r->e164])) { continue; }   // πρώτη καρτέλα κερδίζει, σταθερά
            $out[$r->e164] = ['id' => (int) $r->id,
                'name' => self::label(['company' => $r->company, 'first' => $r->first, 'last' => $r->last]),
                'clientid' => $r->clientid ? (int) $r->clientid : null];
        }
        return $out;
    }

    /**
     * Ξαναπερνά τις κλήσεις από τον κατάλογο.
     *
     * Τρέχει μετά από εισαγωγή ή αλλαγή καρτέλας: μια διόρθωση σε ένα τηλέφωνο
     * πρέπει να φανεί ΑΜΕΣΩΣ σε όλες τις παλιές κλήσεις, αλλιώς η αναφορά θα
     * έλεγε άλλα από την καρτέλα.
     */
    public static function reindexCalls($e164 = null)
    {
        $q = Capsule::table('mod_cpm_calls')->where('other_e164', '<>', '');
        if ($e164) { $q->where('other_e164', $e164); }
        $nums = $q->distinct()->pluck('other_e164')->all();
        $map = self::resolveMany($nums);
        $hit = 0;
        foreach ($nums as $n) {
            $m = $map[$n] ?? null;
            $upd = ['book_id' => $m['id'] ?? null,
                    'clientid' => $m['clientid'] ?? null,
                    'client_match' => $m ? ($m['clientid'] ? 'book' : 'bookname') : 'none'];
            $c = Capsule::table('mod_cpm_calls')->where('other_e164', $n)
                ->where('client_match', '<>', 'anon')->update($upd);
            if ($m) { $hit += $c; }
        }
        return $hit;
    }

    /**
     * Φέρνει τους πελάτες του WHMCS που έχουν τηλέφωνο και λείπουν από τον
     * κατάλογο.
     *
     * ΓΙΑΤΙ: από τη στιγμή που η αναγνώριση περνά ΜΟΝΟ από εδώ, ένας πελάτης
     * που δεν είναι στον κατάλογο είναι αόρατος — κι ας τον έχουμε στο WHMCS με
     * τηλέφωνο. Δεν στέλνονται αυτόματα στο τηλεφωνικό κέντρο: αυτό είναι ρητή
     * απόφαση, με το κουμπί αποστολής.
     */
    public static function importFromWhmcs()
    {
        $res = ['new' => 0, 'skipped' => 0];
        $now = date('Y-m-d H:i:s');
        $have = Capsule::table('mod_cpm_book')->whereNotNull('clientid')->pluck('clientid')->all();
        $have = array_flip(array_map('intval', $have));

        foreach (Capsule::table('tblclients')->where('phonenumber', '<>', '')
            ->get(['id', 'companyname', 'firstname', 'lastname', 'phonenumber', 'email',
                   'address1', 'city', 'postcode', 'country']) as $c) {
            $cid = (int) $c->id;
            if (isset($have[$cid])) { $res['skipped']++; continue; }
            $e = Pbx3cxCdr::e164($c->phonenumber);
            if (strlen(preg_replace('/\D/', '', $e)) < 9) { $res['skipped']++; continue; }
            /* Αν ο αριθμός υπάρχει ήδη σε άλλη καρτέλα, μην κάνεις δεύτερη —
               δέσε τον πελάτη σ' εκείνη. Μία καρτέλα ανά τηλέφωνο. */
            $ex = Capsule::table('mod_cpm_book_phones')->where('e164', $e)->value('book_id');
            if ($ex) {
                Capsule::table('mod_cpm_book')->where('id', $ex)->whereNull('clientid')
                    ->update(['clientid' => $cid, 'updated_at' => $now]);
                $res['skipped']++;
                continue;
            }
            $co = self::plain($c->companyname);
            $id = (int) Capsule::table('mod_cpm_book')->insertGetId([
                'kind' => $co !== '' ? 'company' : 'person',
                'company' => $co !== '' ? mb_substr($co, 0, 160) : null,
                'first' => mb_substr(self::plain($c->firstname), 0, 60) ?: null,
                'last' => mb_substr(self::plain($c->lastname), 0, 60) ?: null,
                'email' => mb_substr(self::plain($c->email), 0, 120) ?: null,
                'address' => mb_substr(self::plain($c->address1), 0, 200) ?: null,
                'city' => mb_substr(self::plain($c->city), 0, 80) ?: null,
                'postcode' => mb_substr(trim((string) $c->postcode), 0, 12) ?: null,
                'country' => mb_substr(trim((string) $c->country), 0, 40) ?: null,
                'status' => 'active', 'clientid' => $cid, 'to_pbx' => 1,
                'created_at' => $now, 'updated_at' => $now]);
            Capsule::table('mod_cpm_book_phones')->insertOrIgnore([
                'book_id' => $id, 'e164' => $e,
                'raw' => mb_substr((string) $c->phonenumber, 0, 40), 'label' => 'main', 'sort' => 0]);
            $res['new']++;
        }
        return $res;
    }

    /** Ποιες καρτέλες αντιστοιχούν σε πελάτη WHMCS — με βάση τα τηλέφωνα. */
    public static function linkClients()
    {
        $n = 0;
        foreach (Capsule::table('mod_cpm_book')->whereNull('clientid')->pluck('id') as $id) {
            foreach (Capsule::table('mod_cpm_book_phones')->where('book_id', $id)->pluck('e164') as $e) {
                [$cid, ] = Pbx3cxCdr::matchClient($e);
                if ($cid) {
                    Capsule::table('mod_cpm_book')->where('id', $id)->update(['clientid' => $cid]);
                    $n++;
                    break;
                }
            }
        }
        return $n;
    }

    /** Πότε μιλήσαμε τελευταία φορά με τον καθένα. */
    public static function refreshLastCall()
    {
        Capsule::statement(
            'UPDATE mod_cpm_book b
             LEFT JOIN (SELECT p.book_id, MAX(c.started_at) mx
                        FROM mod_cpm_book_phones p
                        JOIN mod_cpm_calls c ON c.other_e164 = p.e164
                        GROUP BY p.book_id) x ON x.book_id = b.id
             SET b.last_call_at = x.mx');
    }

    /**
     * Η επαφή όπως τη στέλνουμε στο 3CX — ΚΑΙ το μέτρο σύγκρισης για ό,τι
     * άλλαξε εκεί (βλ. pullFromPbx). null = δεν έχει τηλέφωνο, δεν στέλνεται.
     */
    public static function pbxBody($b, $phones)
    {
        $labels = self::phoneLabels();

        /* Το 3CX έχει ΣΥΓΚΕΚΡΙΜΕΝΑ πεδία τηλεφώνου. Στέλνουμε το καθένα στο
           δικό του· τα επιπλέον της ίδιας ετικέτας δεν χωράνε και μένουν μόνο
           εδώ — γι' αυτό ο κατάλογός μας είναι ο πλήρης. */
        /* Η AI ρεσεψιόν βλέπει από το 3CX ΜΟΝΟ το όνομα της επαφής ({{other_party_name}}).
           Γι' αυτό η «ουρά του πελάτη» ταξιδεύει μέσα στο επώνυμο, σε αγκύλες, π.χ.
           «Παπαδοπούλου [Support]»: καλύπτεται από υποστήριξη και τα προϊόντα του πάνε σε
           μία ουρά (ή έχει «πάντα σε»). Οι οδηγίες λένε στη ρεσεψιόν να μη διαβάζει
           ποτέ τις αγκύλες και να συνδέει απευθείας. Χωρίς κάλυψη → χωρίς αγκύλες. */
        $hint = self::routeHint($b);
        $last = trim((string) $b->last);
        /* Επαφή που είναι μόνο ΕΠΙΧΕΙΡΗΣΗ: η επωνυμία μπαίνει ως όνομα, αλλιώς η ρεσεψιόν
           δεν βλέπει τίποτα ({{other_party_name}} = μόνο όνομα) και δεν την αναγνωρίζει. */
        $isCompany = $last === '' && trim((string) $b->first) === '' && trim((string) $b->company) !== '';
        if ($isCompany) { $last = trim((string) $b->company); $hint = trim('Εταιρεία ' . $hint); }
        /* ΜΕΤΡΗΘΗΚΕ: το 3CX δέχεται έως 50 χαρακτήρες σε CompanyName (LENGTH_NOT_MORE_50_CHARS)
           — οι μακριές επωνυμίες κόβονται εδώ, ο δικός μας κατάλογος τις κρατά ολόκληρες. */
        $body = ['FirstName' => mb_substr((string) $b->first, 0, 50),
                 'LastName' => mb_substr($last . ($hint !== '' ? ' [' . $hint . ']' : ''), 0, 50),
                 'CompanyName' => mb_substr((string) $b->company, 0, 50), 'Email' => (string) $b->email,
                 'Title' => mb_substr((string) $b->title, 0, 50),
                 'Department' => mb_substr((string) ($b->department ?? ''), 0, 50)];
        foreach ($labels as $k => $v) { $body[$v[1]] = ''; }
        $used = [];
        foreach ($phones as $p) {
            $field = $labels[$p->label][1] ?? 'Other';
            if (isset($used[$field])) { continue; }
            $body[$field] = $p->e164;
            $used[$field] = 1;
        }
        if (!$used) { return null; }
        /* ΜΕΤΡΗΘΗΚΕ (20/09/2026): το 3CX απορρίπτει επαφή χωρίς PhoneNumber (το «Κύριο»)
           με CONTACTS_SPECIFY_PHONE_NUMBER — αυτό ήταν το «σκάσιμο» στην αποθήκευση για
           καρτέλες που είχαν μόνο κινητό. Το πρώτο τηλέφωνο μπαίνει και ως Κύριο. */
        if (empty($body['PhoneNumber'])) { $body['PhoneNumber'] = (string) $phones[0]->e164; }
        return $body;
    }

    /**
     * Στέλνει ΜΙΑ καρτέλα στο τηλεφωνικό κέντρο.
     *
     * Δεν πετάει: αν το PBX αρνηθεί, η καρτέλα μένει και ο λόγος γράφεται στο
     * pbx_error, ώστε να φαίνεται στην οθόνη ποιες δεν έφτασαν και γιατί.
     */
    public static function push($id)
    {
        $b = Capsule::table('mod_cpm_book')->where('id', (int) $id)->first();
        if (!$b) { return ['ok' => false, 'why' => 'δεν βρέθηκε']; }

        $phones = Capsule::table('mod_cpm_book_phones')->where('book_id', $b->id)
            ->orderBy('sort')->get();
        $body = self::pbxBody($b, $phones);
        if (!$body) { return ['ok' => false, 'why' => 'χωρίς τηλέφωνο']; }

        try {
            if ($b->pbx_id) {
                Pbx3cxClient::xwrite('PATCH', 'Contacts(' . (int) $b->pbx_id . ')', $body);
                $pid = (int) $b->pbx_id;
            } else {
                $r = Pbx3cxClient::xwrite('POST', 'Contacts', $body);
                $pid = (int) ($r['Id'] ?? 0);
            }
            Capsule::table('mod_cpm_book')->where('id', $b->id)->update([
                'pbx_id' => $pid ?: null, 'pbx_at' => date('Y-m-d H:i:s'), 'pbx_error' => null]);
            return ['ok' => true, 'pbx_id' => $pid];
        } catch (\Throwable $e) {
            Capsule::table('mod_cpm_book')->where('id', $b->id)
                ->update(['pbx_error' => mb_substr($e->getMessage(), 0, 200)]);
            return ['ok' => false, 'why' => $e->getMessage()];
        }
    }

    /** Η ουρά στην οποία πάει ο πελάτης χωρίς ερωτήσεις — ή '' αν δεν είναι μονοσήμαντο. */
    public static function routeHint($b)
    {
        if ((int) ($b->support_cover ?? 0) !== 1) { return ''; }
        if (!class_exists(__NAMESPACE__ . '\Route') || !class_exists(__NAMESPACE__ . '\Pbx3cxBlueprint')) { return ''; }
        $dn = trim((string) ($b->route_dn ?? ''));
        if ($dn === '') {
            $dns = [];
            foreach (Route::parseProducts($b->products ?? '') as $p) { $dns[Route::PRODUCTS[$p][1]] = true; }
            if (count($dns) !== 1) { return ''; }
            $dn = (string) array_key_first($dns);
        }
        return isset(Pbx3cxBlueprint::TOPICS[$dn]) ? Pbx3cxBlueprint::TOPICS[$dn]['name'] : '';
    }

    /** Αφαίρεση από το τηλεφωνικό κέντρο (η καρτέλα μένει). */
    public static function unpush($id)
    {
        $b = Capsule::table('mod_cpm_book')->where('id', (int) $id)->first();
        if (!$b || !$b->pbx_id) { return true; }
        try { Pbx3cxClient::xwrite('DELETE', 'Contacts(' . (int) $b->pbx_id . ')'); }
        catch (\Throwable $e) { /* μπορεί να έχει ήδη σβηστεί εκεί */ }
        Capsule::table('mod_cpm_book')->where('id', $b->id)
            ->update(['pbx_id' => null, 'pbx_at' => null]);
        return true;
    }

    /** Όσες καρτέλες δεν έχουν φτάσει ακόμη στο κέντρο, ή άλλαξαν από τότε. */
    public static function pushPending($max = 200)
    {
        $ids = Capsule::table('mod_cpm_book')->where('to_pbx', 1)
            ->where(function ($q) { $q->whereNull('pbx_at')->orWhereRaw('updated_at > pbx_at'); })
            ->limit($max)->pluck('id');
        $ok = 0; $bad = 0;
        foreach ($ids as $id) {
            $r = self::push($id);
            if (!empty($r['ok'])) { $ok++; } else { $bad++; }
        }
        return ['sent' => $ok, 'failed' => $bad];
    }
}
