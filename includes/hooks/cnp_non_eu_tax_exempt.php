<?php
/**
 * Αυτόματη σήμανση Tax Exempt για νέους πελάτες ΕΚΤΟΣ ΕΕ.
 *
 * Καθεστώς ΦΠΑ (cloud/SaaS από ελληνική εταιρεία, κάτω από το όριο €10.000
 * διασυνοριακών EU-B2C):
 *   • Ελλάδα + ΕΕ            → 24% ελληνικός ΦΠΑ (home-country)
 *   • Εκτός ΕΕ (τρίτη χώρα)  → 0% ελληνικός ΦΠΑ
 *   • EU B2B με έγκυρο VAT   → 0% reverse charge (χειροκίνητα/έλεγχος VIES)
 *
 * Ο γενικός κανόνας «VAT 24%» χτυπά όλους· γι' αυτό ο νέος εκτός-ΕΕ πελάτης
 * μαρκάρεται Tax Exempt με την εγγραφή, ώστε κάθε ΝΕΟ τιμολόγιό του (και των
 * υπηρεσιών του) να βγαίνει χωρίς ΦΠΑ. Δεν αγγίζει Ελλάδα/ΕΕ.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function cnp_eu_country_codes()
{
    return ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE',
            'IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'];
}

add_hook('ClientAdd', 1, function ($vars) {
    $cid = (int) ($vars['userid'] ?? $vars['clientid'] ?? $vars['id'] ?? 0);
    if (!$cid) {
        return;
    }
    try {
        $country = strtoupper((string) Capsule::table('tblclients')->where('id', $cid)->value('country'));
        if ($country !== '' && !in_array($country, cnp_eu_country_codes(), true)) {
            Capsule::table('tblclients')->where('id', $cid)->update(['taxexempt' => 1]);
            if (function_exists('logActivity')) {
                logActivity('Auto Tax Exempt: νέος εκτός-ΕΕ πελάτης #' . $cid . ' (' . $country
                    . ') — χωρίς ελληνικό ΦΠΑ (τρίτη χώρα)');
            }
        }
    } catch (\Throwable $e) {
        if (function_exists('logActivity')) {
            logActivity('cnp_non_eu_tax_exempt hook σφάλμα: ' . $e->getMessage());
        }
    }
});
