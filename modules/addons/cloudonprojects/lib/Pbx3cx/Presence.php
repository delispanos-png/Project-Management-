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
     * Πώς μεταφράζεται η κατάσταση του εργαλείου σε προφίλ του 3CX.
     *
     * Το 3CX έχει τέσσερα σταθερά προφίλ (Available, Away, Out of office, και δύο
     * «Custom» που ο καθένας ονομάζει όπως θέλει — άρα αναξιόπιστα για κανόνα).
     * «Απασχολημένος» και «σε σύσκεψη» πέφτουν και τα δύο σε Away: το κέντρο δεν
     * έχει ξεχωριστό «σε σύσκεψη», και η σημασία για τον καλούντα είναι η ίδια —
     * μην περιμένεις απάντηση τώρα.
     */
    const MAP = [
        'online'  => 'Available',
        'busy'    => 'Away',
        'meeting' => 'Away',
        'away'    => 'Out of office',
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
                return $out;
            }
            Pbx3cxClient::xwrite('PATCH', 'Users(' . $u['Id'] . ')',
                ['CurrentProfileName' => $out['profile']], 6);
            $out['ok'] = true;
            Pbx3cxClient::log('presence', 'ok', 'εσωτερικό ' . $dn . ' → ' . $out['profile']
                . ' (κατάσταση «' . $status . '» από το Project Manager)');
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            Pbx3cxClient::log('presence', 'error', 'εσωτερικό ' . $dn . ' → ' . $out['profile']
                . ': ' . $e->getMessage());
        }
        return $out;
    }
}
