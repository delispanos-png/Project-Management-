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
        return ['active' => 'Ενεργός', 'prospect' => 'Υποψήφιος',
                'supplier' => 'Προμηθευτής', 'inactive' => 'Ανενεργός'];
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

    /**
     * ΕΦΑΠΑΞ ΕΙΣΑΓΩΓΗ από τον κατάλογο του 3CX.
     *
     * Τρέχει μία φορά (ή ξανά για ό,τι προστέθηκε στο κέντρο εκτός CloudOn).
     * Δεν πατάει ΠΟΤΕ πάνω σε καρτέλα που έχει ήδη επεξεργαστεί άνθρωπος: αν
     * υπάρχει καρτέλα με αυτό το pbx_id, την προσπερνά.
     */
    public static function importFromPbx()
    {
        $res = ['new' => 0, 'skipped' => 0, 'phones' => 0];
        $labels = self::phoneLabels();
        $toLabel = [];
        foreach ($labels as $k => $v) { $toLabel[$v[1]] = $k; }

        $known = Capsule::table('mod_cpm_book')->whereNotNull('pbx_id')->pluck('id', 'pbx_id')->all();
        $now = date('Y-m-d H:i:s');

        for ($skip = 0; $skip < 5000; $skip += 100) {
            $j = Pbx3cxClient::xapi('Contacts', ['$top' => 100, '$skip' => $skip], 30);
            $v = $j['value'] ?? [];
            if (!$v) { break; }
            foreach ($v as $c) {
                $pid = (int) ($c['Id'] ?? 0);
                if (!$pid) { continue; }
                if (isset($known[$pid])) { $res['skipped']++; continue; }

                $row = [
                    'kind' => trim((string) ($c['CompanyName'] ?? '')) !== '' ? 'company' : 'person',
                    'company' => mb_substr(trim((string) ($c['CompanyName'] ?? '')), 0, 160) ?: null,
                    'first' => mb_substr(trim((string) ($c['FirstName'] ?? '')), 0, 60) ?: null,
                    'last' => mb_substr(trim((string) ($c['LastName'] ?? '')), 0, 60) ?: null,
                    'title' => mb_substr(trim((string) ($c['Title'] ?? '')), 0, 80) ?: null,
                    'email' => mb_substr(trim((string) ($c['Email'] ?? '')), 0, 120) ?: null,
                    'status' => 'active', 'to_pbx' => 1,
                    'pbx_id' => $pid, 'pbx_at' => $now,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                if (self::label($row) === '') { $res['skipped']++; continue; }

                $phones = [];
                foreach ($toLabel as $field => $lab) {
                    $raw = trim((string) ($c[$field] ?? ''));
                    /* ΠΡΟΣΟΧΗ: το «Other» του 3CX κρατά συχνά τίτλο θέσης, όχι
                       τηλέφωνο. Δεχόμαστε μόνο ό,τι μοιάζει με αριθμό. */
                    if ($raw === '' || !preg_match('/^[\d\s()+.\-]{6,}$/', $raw)) { continue; }
                    $e = Pbx3cxCdr::e164($raw);
                    if (strlen(preg_replace('/\D/', '', $e)) < 8) { continue; }
                    $phones[$e] = ['label' => $lab, 'raw' => mb_substr($raw, 0, 40)];
                }
                if (!$phones) { $res['skipped']++; continue; }

                $id = (int) Capsule::table('mod_cpm_book')->insertGetId($row);
                $sort = 0;
                foreach ($phones as $e => $p) {
                    Capsule::table('mod_cpm_book_phones')->insertOrIgnore([
                        'book_id' => $id, 'e164' => $e, 'raw' => $p['raw'],
                        'label' => $p['label'], 'sort' => $sort++]);
                    $res['phones']++;
                }
                $res['new']++;
                $known[$pid] = $id;
            }
            if (count($v) < 100) { break; }
        }

        self::linkClients();
        self::refreshLastCall();
        Pbx3cxClient::log('sync', 'ok', 'Κατάλογος: εισήχθησαν ' . $res['new']
            . ' επαφές από το 3CX (' . $res['phones'] . ' τηλέφωνα)');
        return $res;
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
        if ($last === '' && $hint !== '') { $last = trim((string) $b->company); }
        /* ΜΕΤΡΗΘΗΚΕ: το 3CX δέχεται έως 50 χαρακτήρες σε CompanyName (LENGTH_NOT_MORE_50_CHARS)
           — οι μακριές επωνυμίες κόβονται εδώ, ο δικός μας κατάλογος τις κρατά ολόκληρες. */
        $body = ['FirstName' => mb_substr((string) $b->first, 0, 50),
                 'LastName' => mb_substr($last . ($hint !== '' ? ' [' . $hint . ']' : ''), 0, 50),
                 'CompanyName' => mb_substr((string) $b->company, 0, 50), 'Email' => (string) $b->email,
                 'Title' => mb_substr((string) $b->title, 0, 50)];
        foreach ($labels as $k => $v) { $body[$v[1]] = ''; }
        $used = [];
        foreach ($phones as $p) {
            $field = $labels[$p->label][1] ?? 'Other';
            if (isset($used[$field])) { continue; }
            $body[$field] = $p->e164;
            $used[$field] = 1;
        }
        if (!$used) { return ['ok' => false, 'why' => 'χωρίς τηλέφωνο']; }
        /* ΜΕΤΡΗΘΗΚΕ (20/09/2026): το 3CX απορρίπτει επαφή χωρίς PhoneNumber (το «Κύριο»)
           με CONTACTS_SPECIFY_PHONE_NUMBER — αυτό ήταν το «σκάσιμο» στην αποθήκευση για
           καρτέλες που είχαν μόνο κινητό. Το πρώτο τηλέφωνο μπαίνει και ως Κύριο. */
        if (empty($body['PhoneNumber'])) { $body['PhoneNumber'] = (string) $phones[0]->e164; }

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
