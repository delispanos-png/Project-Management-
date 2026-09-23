<?php

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

/**
 * Η κατάσταση του χειριστή ταξιδεύει από το Project Manager στο τηλεφωνικό κέντρο.
 *
 * ΤΟ ΠΡΟΒΛΗΜΑ: δήλωνες «σε σύσκεψη» ή «λείπω» στο εργαλείο, και το τηλέφωνο
 * συνέχιζε να χτυπάει — γιατί το 3CX δεν το ήξερε. Έπρεπε να το πεις δύο φορές,
 * σε δύο προγράμματα, και όποιος ξεχνούσε το δεύτερο δεχόταν κλήσεις ενώ ήταν
 * αλλού. Αν δεν μπορείς στο ένα, δεν μπορείς και στο άλλο.
 *
 * ΜΙΑ ΚΑΤΕΥΘΥΝΣΗ, ΕΠΙΤΗΔΕΣ: PM → 3CX. Το ανάποδο (αλλάζεις προφίλ από το
 * τηλέφωνο και ενημερώνεται το εργαλείο) θέλει δημοσκόπηση του κέντρου κάθε
 * λίγα λεπτά και δεν ζητήθηκε — θα μπει ξεχωριστά αν χρειαστεί.
 *
 * ΔΕΝ ΜΠΛΟΚΑΡΕΙ ΠΟΤΕ: αν το κέντρο είναι κάτω ή ο χειριστής δεν έχει εσωτερικό,
 * η κατάσταση αλλάζει κανονικά στο εργαλείο και το αποτυχημένο συγχρονισμό τον
 * βλέπεις στο τεχνικό ημερολόγιο. Μια βλάβη στο τηλεφωνικό κέντρο δεν έχει
 * λόγο να σε εμποδίζει να δηλώσεις ότι λείπεις.
 */
class Pbx3cxPresence
{
    /**
     * Κατάσταση εργαλείου → προφίλ 3CX, ΕΝΑ ΠΡΟΣ ΕΝΑ.
     *
     * Οι πέντε επιλέξιμες καταστάσεις του εργαλείου είναι πλέον ακριβώς αυτές
     * που δείχνει ο client του 3CX. ΠΡΟΣΟΧΗ στη διπλή ονοματολογία: το XAPI
     * ονομάζει τα προφίλ αλλιώς από τον client — «Out of office» είναι το
     * «Do Not Disturb», και τα δύο «Custom» είναι το «Lunch» και το
     * «Business Trip». Γράφουμε ΠΑΝΤΑ το όνομα του XAPI.
     *
     * Το «Σε σύσκεψη» δεν επιλέγεται: το βάζει το ημερολόγιο. Το 3CX δεν έχει
     * αντίστοιχο, οπότε φεύγει ως «Μην ενοχλείτε» — για τον καλούντα σημαίνει
     * το ίδιο πράγμα.
     *
     * Το «Εκτός» ΔΕΝ στέλνεται καθόλου: σημαίνει «δεν είσαι στην εφαρμογή», όχι
     * «δεν είσαι στο τηλέφωνό σου».
     */
    const MAP = [
        'online'  => 'Available',      // Available
        'away'    => 'Away',           // Away
        'dnd'     => 'Out of office',  // Do Not Disturb
        'lunch'   => 'Custom 1',       // Lunch
        'trip'    => 'Custom 2',       // Business Trip
        'meeting' => 'Out of office',  // από το ημερολόγιο ή δηλωμένη με το χέρι
        'busy'    => 'Out of office',  // «Απασχολημένος» = μη με ενοχλείτε
        'offline' => 'Away',           // έκλεισε την εφαρμογή → Away στο κέντρο
    ];

    /** Πώς λέγεται το προφίλ στον client — για να το δείχνει η οθόνη ρυθμίσεων. */
    const CLIENT_NAME = [
        'Available'     => 'Available',
        'Away'          => 'Away',
        'Out of office' => 'Do Not Disturb',
        'Custom 1'      => 'Lunch',
        'Custom 2'      => 'Business Trip',
    ];

    /** Ανοιχτό εξ ορισμού: αυτός είναι ο λόγος που φτιάχτηκε. */
    public static function enabled()
    {
        return Pbx3cxClient::cfg('presence_sync', '1') === '1';
    }

    public static function setEnabled($on)
    {
        Pbx3cxClient::setCfg('presence_sync', $on ? '1' : '0');
    }

    /** Το εσωτερικό του χειριστή, ή '' αν δεν είναι δεμένος με κανένα. */
    public static function dnFor($adminId)
    {
        if (!$adminId) {
            return '';
        }
        try {
            return (string) Capsule::table('mod_cpm_pbx_map')
                ->where('admin_id', (int) $adminId)->where('dn_type', 'extension')
                ->value('dn');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Στέλνει την κατάσταση στο κέντρο.
     *
     * @param int    $adminId ο χειριστής
     * @param string $status  online|busy|meeting|away
     * @return array ['ok'=>bool, 'dn'=>string, 'profile'=>string, 'skip'=>string, 'error'=>string]
     */
    public static function push($adminId, $status)
    {
        $out = ['ok' => false, 'dn' => '', 'profile' => '', 'skip' => '', 'error' => ''];
        if (!self::enabled()) {
            $out['skip'] = 'off';
            return $out;
        }
        if (!isset(self::MAP[$status])) {
            $out['skip'] = 'unknown';
            return $out;
        }
        if (!Pbx3cxClient::configured()) {
            $out['skip'] = 'no-pbx';
            return $out;
        }
        $dn = self::dnFor($adminId);
        if ($dn === '') {
            /* Δεν είναι σφάλμα: όχι κάθε χειριστής έχει τηλέφωνο. */
            $out['skip'] = 'no-dn';
            return $out;
        }
        $out['dn'] = $dn;
        $out['profile'] = self::MAP[$status];
        try {
            /* Σύντομο timeout: η αλλαγή κατάστασης είναι χειρονομία ενός κλικ και
               δεν επιτρέπεται να κολλήσει την οθόνη περιμένοντας το κέντρο. */
            $r = Pbx3cxClient::xapi('Users', ['$filter' => "Number eq '" . $dn . "'",
                '$select' => 'Id,CurrentProfileName'], 6);
            $u = $r['value'][0] ?? null;
            if (!$u) {
                $out['error'] = 'το εσωτερικό ' . $dn . ' δεν βρέθηκε στο κέντρο';
                Pbx3cxClient::log('presence', 'error', $out['error']);
                return $out;
            }
            /* Ίδια τιμή → δεν γράφουμε. Κάθε εγγραφή γράφεται στο ημερολόγιο του
               3CX, και δεν θέλουμε να το γεμίζουμε με «Available → Available». */
            if ((string) ($u['CurrentProfileName'] ?? '') === $out['profile']) {
                $out['ok'] = true;
                $out['skip'] = 'same';
                Db::setPref((int) $adminId, 'pbx_pushed', $status);
                return $out;
            }
            Pbx3cxClient::xwrite('PATCH', 'Users(' . $u['Id'] . ')',
                ['CurrentProfileName' => $out['profile']], 6);
            $out['ok'] = true;
            Db::setPref((int) $adminId, 'pbx_pushed', $status);
            Pbx3cxClient::log('presence', 'ok', 'εσωτερικό ' . $dn . ' → ' . $out['profile']
                . ' (κατάσταση «' . $status . '» από το Project Manager)');
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            Pbx3cxClient::log('presence', 'error', 'εσωτερικό ' . $dn . ' → ' . $out['profile']
                . ': ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * Συγχρονισμός ΑΥΤΟΜΑΤΩΝ αλλαγών (21/9/2026): η κατάσταση που υπολογίζει το Project
     * Manager (σύσκεψη από ημερολόγιο, αδράνεια → Λείπω, κλείσιμο εφαρμογής → Εκτός) ΔΕΝ
     * περνούσε στο 3CX — μόνο η χειροκίνητη δήλωση. Εδώ στέλνεται μόνο όταν αλλάζει σε
     * σχέση με το τελευταίο που στείλαμε (pref pbx_pushed), ώστε να μη χτυπάμε το κέντρο
     * σε κάθε σφυγμό.
     */
    /** Τι από τα ΑΥΤΟΜΑΤΑ περνά στο τηλέφωνο: manual = τίποτα (μόνο ό,τι δηλώνει ο ίδιος),
     *  meeting = και η σύσκεψη από το ημερολόγιο (προεπιλογή), all = και Λείπω/Εκτός από αδράνεια. */
    public static function mode()
    {
        /* Προεπιλογή manual (απόφαση 21/9/2026): το τηλέφωνο παίρνει ΜΟΝΟ ό,τι δηλώνει ο χειριστής.
           Ο σφυγμός/αυτόματο είναι για να ξέρουμε ποιος είναι ενεργός στο Project Manager, όχι ενέργεια. */
        $m = Pbx3cxClient::cfg('presence_auto', 'manual');
        return in_array($m, ['manual', 'meeting', 'all'], true) ? $m : 'manual';
    }
    public static function setMode($m)
    {
        Pbx3cxClient::setCfg('presence_auto', in_array($m, ['manual', 'meeting', 'all'], true) ? $m : 'manual');
    }

    /**
     * @param array $pr το αποτέλεσμα της cnp_presence(): status, manual, meeting
     * Κανόνας (21/9/2026): το τηλέφωνο ΔΕΝ πρέπει να κλείνει επειδή κάποιος δουλεύει σε άλλο
     * πρόγραμμα. Το «Λείπω» από αδράνεια της εφαρμογής στέλνεται ΜΟΝΟ σε mode=all. Στα άλλα
     * modes, όταν λήξει μια σύσκεψη ή μια χρονική δήλωση, το τηλέφωνο επιστρέφει σε Available.
     */
    public static function sync($adminId, array $pr)
    {
        if (!self::enabled()) { return null; }
        $status = (string) ($pr['status'] ?? '');
        $isMeeting = $status === 'meeting' && !empty($pr['meeting']);
        $isManual = !empty($pr['manual']) && !$isMeeting;
        $mode = self::mode();
        $want = null;
        if ($isManual) { $want = $status; }
        elseif ($isMeeting) { $want = $mode === 'manual' ? null : 'meeting'; }
        elseif ($mode === 'all') { $want = $status; }
        else {
            /* αυτόματη κατάσταση (online/away/offline) σε mode manual/meeting: μόνο ΕΠΑΝΑΦΟΡΑ
               σε Available, αν το τελευταίο που στείλαμε ήταν κάτι άλλο (σύσκεψη που έληξε,
               δήλωση με λήξη, ή Λείπω από την παλιά συμπεριφορά). Ποτέ δεν κατεβάζουμε το τηλέφωνο. */
            $last = (string) Db::pref((int) $adminId, 'pbx_pushed', '');
            if ($last !== '' && $last !== 'online') { $want = 'online'; }
        }
        if ($want === null || !isset(self::MAP[$want])) { return null; }
        if ((string) Db::pref((int) $adminId, 'pbx_pushed', '') === $want) { return null; }
        return self::push($adminId, $want);
    }

    /** Ποιοι χειριστές έχουν εσωτερικό στο κέντρο — μόνο αυτοί έχουν νόημα στη σάρωση. */
    public static function mappedAdmins()
    {
        try {
            return array_map('intval', Capsule::table('mod_cpm_pbx_map')->where('dn_type', 'extension')
                ->join('tbladmins', 'tbladmins.id', '=', 'mod_cpm_pbx_map.admin_id')->where('tbladmins.disabled', 0)
                ->pluck('mod_cpm_pbx_map.admin_id')->all());
        } catch (\Throwable $e) { return []; }
    }
}
