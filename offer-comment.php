<?php
/**
 * MyCloudOn — ο πελάτης υποβάλλει σχόλιο/ερώτηση πάνω στην προσφορά του.
 * Ασφάλεια: συνδεδεμένος πελάτης-ιδιοκτήτης του quote, POST, same-origin.
 */

use WHMCS\Authentication\CurrentUser;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

define('CLIENTAREA', true);
require __DIR__ . '/init.php';

$ca = new ClientArea();
$ca->initPage();

$client = (new CurrentUser())->client();
$clientId = $client ? (int) $client->id : 0;
if (!$clientId) {
    header('Location: clientarea.php');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: clientarea.php');
    exit;
}
// CSRF άμυνα σε βάθος: ίδια προέλευση (μαζί με SameSite session cookie).
$host = 'https://my.cloudon.gr';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (($origin !== '' && strpos($origin, $host) !== 0)
    || ($origin === '' && $referer !== '' && strpos($referer, $host) !== 0)) {
    http_response_code(403);
    exit('bad origin');
}

$qid = (int) ($_POST['q'] ?? 0);
$quote = $qid ? Capsule::table('tblquotes')->where('id', $qid)->first(['id', 'userid', 'subject']) : null;
if (!$quote || (int) $quote->userid !== $clientId) {
    http_response_code(403);
    exit('Δεν έχετε πρόσβαση.');
}
$offer = Capsule::table('mod_cpm_offers')->where('quoteid', $qid)->first(['id', 'assignee', 'created_by', 'title']);
if (!$offer) {
    header('Location: viewquote.php?id=' . $qid);
    exit;
}

// utf8mb3: αφαίρεση 4-byte (emoji) ώστε να μη γίνουν «????».
$body = trim((string) ($_POST['body'] ?? ''));
$body = mb_substr(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $body), 0, 4000);
if ($body !== '') {
    Capsule::table('mod_cpm_offer_comments')->insert([
        'offer_id' => (int) $offer->id, 'quoteid' => $qid, 'by_type' => 'client',
        'by_id' => $clientId, 'body' => $body, 'created_at' => date('Y-m-d H:i:s'),
        'read_by_client_at' => date('Y-m-d H:i:s'),
    ]);
    // Ειδοποίηση ομάδας (υπεύθυνος + δημιουργός).
    require_once __DIR__ . '/modules/addons/cloudonprojects/lib/Db.php';
    $title = 'Νέα ερώτηση πελάτη — προσφορά «' . mb_substr((string) $offer->title, 0, 60) . '»';
    foreach (array_unique(array_filter([(int) $offer->assignee, (int) $offer->created_by])) as $aid) {
        try {
            \WHMCS\Module\Addon\CloudonProjects\Db::pushNotification($aid, 'comment', $title, '/project/#/offers');
        } catch (\Throwable $e) {
        }
    }
}
header('Location: viewquote.php?id=' . $qid . '#cloudon-comments');
exit;
