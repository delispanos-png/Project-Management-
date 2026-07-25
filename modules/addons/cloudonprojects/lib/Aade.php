<?php

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

/**
 * Πελάτης για το web service Μητρώου ΑΑΔΕ/ΓΓΠΣ «RgWsPublic2».
 * Αντλεί στοιχεία επιχείρησης από ΑΦΜ (επωνυμία, διεύθυνση, ΔΟΥ, ΚΑΔ, κατάσταση).
 * Απαιτεί ειδικούς κωδικούς web service (TAXISnet). Τα creds είναι server-side μόνο.
 */
class Aade
{
    const ENDPOINT = 'https://www1.gsis.gr/wsaade/RgWsPublic2/RgWsPublic2';
    const WSDL     = 'https://www1.gsis.gr/wsaade/RgWsPublic2/RgWsPublic2?WSDL';
    const WSSE     = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    /** Έλεγχος εγκυρότητας ελληνικού ΑΦΜ (9 ψηφία + check digit modulo 11). */
    public static function validAfm(string $afm): bool
    {
        $afm = preg_replace('/\D+/', '', $afm);
        if (strlen($afm) !== 9 || $afm === '000000000') {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += ((int) $afm[$i]) << (8 - $i); // ψηφίο * 2^(8-i)
        }
        $mod = $sum % 11;
        if ($mod === 10) {
            $mod = 0;
        }
        return $mod === (int) $afm[8];
    }

    private static function setting(string $k, string $def = ''): string
    {
        $v = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', $k)->value('value');
        return $v === null || $v === '' ? $def : (string) $v;
    }

    public static function enabled(): bool
    {
        return self::setting('aade_enabled', 'off') === 'on'
            && self::setting('aade_user') !== ''
            && self::setting('aade_pass') !== '';
    }

    /**
     * Αναζήτηση ΑΦΜ. Επιστρέφει:
     *  ['ok'=>true, 'data'=>[...]] ή ['ok'=>false, 'error'=>'μήνυμα', 'code'=>'']
     */
    public static function lookup(string $afm): array
    {
        $afm = preg_replace('/\D+/', '', $afm);
        if (!self::validAfm($afm)) {
            return ['ok' => false, 'error' => 'Μη έγκυρο ΑΦΜ.', 'code' => 'AFM_INVALID'];
        }
        if (!self::enabled()) {
            return ['ok' => false, 'error' => 'Η υπηρεσία ΑΑΔΕ δεν είναι ρυθμισμένη.', 'code' => 'NOT_CONFIGURED'];
        }

        $user     = self::setting('aade_user');
        $pass     = self::setting('aade_pass');
        $calledBy = self::setting('aade_afm'); // προαιρετικό (ΑΦΜ καλούντος)

        $env = self::buildEnvelope($user, $pass, $calledBy, $afm);

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $env,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/soap+xml; charset=utf-8', // SOAP 1.2
                'Accept: application/soap+xml, text/xml',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['ok' => false, 'error' => 'Αποτυχία σύνδεσης με ΑΑΔΕ.', 'code' => 'CONN', 'debug' => $cerr];
        }
        return self::parse((string) $resp, (int) $http);
    }

    private static function buildEnvelope(string $user, string $pass, string $calledBy, string $afmFor): string
    {
        $u  = htmlspecialchars($user, ENT_XML1);
        $p  = htmlspecialchars($pass, ENT_XML1);
        $cb = htmlspecialchars($calledBy, ENT_XML1);
        $cf = htmlspecialchars($afmFor, ENT_XML1);
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope"'
            . ' xmlns:tns="http://rgwspublic2/RgWsPublic2Service" xmlns:ns1="http://rgwspublic2/RgWsPublic2">'
            . '<soap:Header>'
            . '<wsse:Security xmlns:wsse="' . self::WSSE . '" soap:mustUnderstand="true">'
            . '<wsse:UsernameToken>'
            . '<wsse:Username>' . $u . '</wsse:Username>'
            . '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">' . $p . '</wsse:Password>'
            . '</wsse:UsernameToken>'
            . '</wsse:Security>'
            . '</soap:Header>'
            . '<soap:Body>'
            . '<tns:rgWsPublic2AfmMethod>'
            . '<tns:INPUT_REC>'
            . '<ns1:afm_called_by>' . $cb . '</ns1:afm_called_by>'
            . '<ns1:afm_called_for>' . $cf . '</ns1:afm_called_for>'
            . '</tns:INPUT_REC>'
            . '</tns:rgWsPublic2AfmMethod>'
            . '</soap:Body>'
            . '</soap:Envelope>';
    }

    /** Πρώτη εμφάνιση ενός tag (case-insensitive, αγνοώντας namespaces). */
    private static function pick(\SimpleXMLElement $x, string $local): string
    {
        $nodes = $x->xpath('//*[local-name()="' . $local . '"]');
        if ($nodes && isset($nodes[0])) {
            return trim((string) $nodes[0]);
        }
        return '';
    }

    private static function parse(string $resp, int $http): array
    {
        // WS-Security / SOAP fault → φιλικό μήνυμα
        libxml_use_internal_errors(true);
        $x = simplexml_load_string($resp);
        if ($x === false) {
            return ['ok' => false, 'error' => 'Μη αναγνώσιμη απάντηση ΑΑΔΕ.', 'code' => 'PARSE', 'http' => $http];
        }

        // SOAP fault (π.χ. λάθος credentials)
        $faultString = self::pick($x, 'faultstring') ?: self::pick($x, 'Reason');
        if ($faultString !== '') {
            return ['ok' => false, 'error' => 'ΑΑΔΕ: ' . $faultString, 'code' => 'FAULT'];
        }

        // error_rec της υπηρεσίας (π.χ. ΑΦΜ ανύπαρκτο/εκτός μητρώου)
        $errCode = self::pick($x, 'error_code');
        $errDesc = self::pick($x, 'error_descr');
        if ($errCode !== '' || $errDesc !== '') {
            return ['ok' => false, 'error' => $errDesc !== '' ? $errDesc : ('Σφάλμα ΑΑΔΕ (' . $errCode . ')'), 'code' => $errCode ?: 'AADE_ERR'];
        }

        $onomasia = self::pick($x, 'onomasia');
        $commer   = self::pick($x, 'commer_title');
        $doy      = self::pick($x, 'doy');
        $doyDescr = self::pick($x, 'doy_descr');
        $addr     = self::pick($x, 'postal_address');
        $addrNo   = self::pick($x, 'postal_address_no');
        $zip      = self::pick($x, 'postal_zip_code');
        $area     = self::pick($x, 'postal_area_description');
        $iniFlag  = self::pick($x, 'i_ni_flag_descr');   // «ΦΠ» = Φυσικό, «ΜΗ ΦΠ» = Μη Φυσικό (εταιρεία/οργανισμός)
        $deact    = self::pick($x, 'deactivation_flag');  // 1 = ενεργός, 2 = ανενεργός
        $firmDesc = self::pick($x, 'firm_flag_descr');
        $legal    = self::pick($x, 'legal_status_descr'); // π.χ. ΑΕ, ΕΠΕ, ΙΚΕ, ΟΕ, ΑΤΟΜΙΚΗ
        $afmOut   = self::pick($x, 'afm');

        if ($onomasia === '' && $doy === '' && $afmOut === '') {
            return ['ok' => false, 'error' => 'Δεν βρέθηκαν στοιχεία για το ΑΦΜ.', 'code' => 'EMPTY', 'http' => $http];
        }

        // Κύρια δραστηριότητα (ΚΑΔ) — item με firm_act_kind = 1
        $kad = '';
        foreach ($x->xpath('//*[local-name()="item"]') as $it) {
            $kind = trim((string) ($it->xpath('.//*[local-name()="firm_act_kind"]')[0] ?? ''));
            $d    = trim((string) ($it->xpath('.//*[local-name()="firm_act_descr"]')[0] ?? ''));
            if ($d !== '') {
                if ($kind === '1') { $kad = $d; break; }
                if ($kad === '') { $kad = $d; }
            }
        }

        $street = trim($addr . ' ' . $addrNo);
        // «ΜΗ ΦΠ» = Μη Φυσικό Πρόσωπο (εταιρεία/οργανισμός)· «ΦΠ» = Φυσικό
        $isCompany = ($iniFlag !== '' && mb_strpos($iniFlag, 'ΜΗ') !== false);

        return [
            'ok'   => true,
            'data' => [
                'afm'        => $afmOut,
                'name'       => $onomasia,
                'title'      => $commer,
                'street'     => $street,
                'city'       => $area,
                'postcode'   => $zip,
                'doy'        => $doyDescr,
                'doy_code'   => $doy,
                'kad'        => $kad,
                'is_company' => $isCompany,
                'active'     => ($deact === '' || $deact === '1'),
                'firm_type'  => $firmDesc,
                'legal_form' => $legal,
            ],
        ];
    }
}
