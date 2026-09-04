<?php
/**
 * MyCloudOn — προβολή/λήψη της branded προσφοράς (PDF) από τον πελάτη.
 *
 * Ασφάλεια: ΜΟΝΟ ο συνδεδεμένος πελάτης-ιδιοκτήτης του quote. Το PDF παράγεται
 * pixel-perfect μέσω Chromium (ίδιο μονοπάτι με την αποστολή). Αν το quote δεν
 * είναι δεμένο με προσφορά PM, γίνεται fallback στο native WHMCS PDF.
 *
 * Χρήση: offer-view.php?q=<quoteid>[&dl=1]
 */

use WHMCS\Authentication\CurrentUser;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

define('CLIENTAREA', true);
require __DIR__ . '/init.php';

$ca = new ClientArea();
$ca->initPage();

// 1) Ταυτοποίηση συνδεδεμένου πελάτη.
$currentUser = new CurrentUser();
$client = $currentUser->client();
$clientId = $client ? (int) $client->id : 0;
if (!$clientId) {
    header('Location: clientarea.php');
    exit;
}

// 2) Το quote + έλεγχος ιδιοκτησίας (αυστηρός).
$qid = (int) ($_GET['q'] ?? 0);
$quote = $qid ? Capsule::table('tblquotes')->where('id', $qid)->first(['id', 'userid', 'subject']) : null;
if (!$quote || (int) $quote->userid !== $clientId) {
    http_response_code(403);
    exit('Δεν έχετε πρόσβαση σε αυτή την προσφορά.');
}

// 3) Η προσφορά PM (αλλιώς → native WHMCS PDF).
$offer = Capsule::table('mod_cpm_offers')->where('quoteid', $qid)->first();
if (!$offer) {
    header('Location: dl.php?type=q&id=' . $qid);
    exit;
}

// 4) Το branded έγγραφο μέσω του τύπου προσφοράς.
$L = __DIR__ . '/modules/addons/cloudonprojects/lib/';
require_once $L . 'Pharmacy.php';
require_once $L . 'offers/OfferType.php';
require_once $L . 'offers/PharmacyOneType.php';
require_once $L . 'offers/PlainType.php';
require_once $L . 'offers/OfferTypes.php';

$type = \WHMCS\Module\Addon\CloudonProjects\Offers\OfferTypes::get((string) $offer->kind);
$cfg = json_decode((string) $offer->config, true) ?: [];
if ((string) $offer->kind === 'plain' || !$cfg) {
    $cfg = array_merge(['title' => $offer->title, 'amount' => $offer->amount,
        'descr' => $offer->descr], is_array($cfg) ? $cfg : []);
}
$docHtml = '<!doctype html><html lang="el"><head><meta charset="utf-8">'
    . '<base href="https://my.cloudon.gr/">'
    . '<style>' . $type->docCss() . '</style></head><body>'
    . $type->docHtml($cfg) . '</body></html>';

// 5) PDF μέσω Chromium (ίδιο setup με την αποστολή).
$tmp = sys_get_temp_dir();
$stamp = 'ov_' . bin2hex(random_bytes(6));
$htmlF = $tmp . '/' . $stamp . '.html';
$pdfF = $tmp . '/' . $stamp . '.pdf';
file_put_contents($htmlF, $docHtml);
$cmd = 'PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers HOME=' . escapeshellarg($tmp) . ' '
    . escapeshellarg('/opt/plesk/node/20/bin/node') . ' '
    . escapeshellarg($L . 'pdf-render.js') . ' '
    . escapeshellarg($htmlF) . ' ' . escapeshellarg($pdfF) . ' 2>&1';
$out = []; $rc = 1;
exec($cmd, $out, $rc);
@unlink($htmlF);
if ($rc !== 0 || !is_file($pdfF) || filesize($pdfF) < 500) {
    @unlink($pdfF);
    http_response_code(500);
    exit('Δεν ήταν δυνατή η δημιουργία του PDF. Δοκιμάστε ξανά.');
}

// 6) Streaming (inline προβολή ή λήψη).
$fname = 'Prosfora-' . $qid . '.pdf';
$disp = !empty($_GET['dl']) ? 'attachment' : 'inline';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disp . '; filename="' . $fname . '"');
header('Content-Length: ' . filesize($pdfF));
header('Cache-Control: private, max-age=0, no-store');
readfile($pdfF);
@unlink($pdfF);
