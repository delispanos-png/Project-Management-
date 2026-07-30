<?php
/**
 * CloudOn — σερβίρει τα εργαλεία υποστήριξης ως ZIP.
 *
 * Γιατί ZIP: οι browsers εμφανίζουν τρομακτικά μηνύματα σε .exe/.bat
 * («isn't commonly downloaded», «could harm your device — Keep/Delete»).
 * Το ZIP δεν είναι εκτελέσιμο, οπότε κατεβαίνει καθαρά.
 *
 * ΚΡΙΣΙΜΟ: το όνομα του .exe ΜΕΣΑ στο zip πρέπει να διατηρηθεί ακέραιο —
 * ο RustDesk client διαβάζει από αυτό τον server και το κλειδί
 * (CloudOn-Remote-host=…,key=…exe). Μετονομασία = χάνει τη σύνδεση.
 *
 * Το ZIP παράγεται από την πηγή και ανανεώνεται αυτόματα (cache 24h), ώστε
 * να μη μείνει πίσω αν ενημερωθεί ο client στο remote.cloudon.gr.
 */

$SOURCES = [
    'client' => [
        'url'      => 'https://remote.cloudon.gr/download/CloudOn-Remote.exe',
        'zip'      => 'CloudOn-Remote.zip',
        'fallback' => 'CloudOn-Remote.exe',
    ],
    'fix' => [
        'url'      => 'https://remote.cloudon.gr/download/fix-cloudon-remote.bat',
        'zip'      => 'CloudOn-Επιδιόρθωση.zip',
        'fallback' => 'fix-cloudon-remote.bat',
    ],
];

$what = $_GET['f'] ?? 'client';
if (!isset($SOURCES[$what])) {
    http_response_code(404);
    exit('Not found');
}
$src = $SOURCES[$what];

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$zipPath = $cacheDir . '/' . $what . '.zip';
$maxAge = 86400;   // 24 ώρες

/** Κατεβάζει την πηγή· επιστρέφει [περιεχόμενο, όνομα αρχείου] ή null. */
function fetchSource($url, $fallbackName)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($raw === false || $code !== 200) {
        return null;
    }
    $headers = substr($raw, 0, $hsize);
    $body = substr($raw, $hsize);
    // Κράτα το πραγματικό filename (περιέχει host/key για τον RustDesk)
    $name = $fallbackName;
    if (preg_match('/filename="([^"]+)"/i', $headers, $m)) {
        $name = $m[1];
    }
    // Καθάρισε επικίνδυνους χαρακτήρες διαδρομής, κράτα τα υπόλοιπα ως έχουν
    $name = str_replace(['/', '\\', "\0", "\r", "\n"], '', $name);
    return [$body, $name];
}

$needsBuild = !is_file($zipPath) || (time() - filemtime($zipPath)) > $maxAge;

if ($needsBuild) {
    $fetched = fetchSource($src['url'], $src['fallback']);
    if ($fetched === null) {
        // Αποτυχία πηγής: αν έχουμε παλιό zip, σέρβιρέ το· αλλιώς στείλε στην πηγή.
        if (!is_file($zipPath)) {
            header('Location: ' . $src['url'], true, 302);
            exit;
        }
    } else {
        [$body, $innerName] = $fetched;
        $tmp = $zipPath . '.tmp';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFromString($innerName, $body);
            // Μικρό readme μέσα στο zip — ο πελάτης ξέρει τι να κάνει
            $readme = "CloudOn — Απομακρυσμένη υποστήριξη\r\n"
                . "==================================\r\n\r\n"
                . "1. Κάνε διπλό κλικ στο αρχείο:\r\n   " . $innerName . "\r\n\r\n"
                . "2. Αν τα Windows εμφανίσουν «Τα Windows προστάτευσαν τον υπολογιστή σας»,\r\n"
                . "   πάτα «Περισσότερες πληροφορίες» και μετά «Εκτέλεση οπωσδήποτε».\r\n\r\n"
                . "3. Δώσε στον τεχνικό μας το ID και τον κωδικό που θα εμφανιστούν.\r\n\r\n"
                . "Τηλέφωνο: 210 7222560\r\n"
                . "Οδηγίες: https://my.cloudon.gr/remote\r\n";
            $zip->addFromString('ΔΙΑΒΑΣΕ ΜΕ.txt', $readme);
            $zip->close();
            @rename($tmp, $zipPath);
            @chmod($zipPath, 0644);
        } elseif (!is_file($zipPath)) {
            header('Location: ' . $src['url'], true, 302);
            exit;
        }
    }
}

if (!is_file($zipPath)) {
    header('Location: ' . $src['url'], true, 302);
    exit;
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $src['zip'] . '"');
header('Content-Length: ' . filesize($zipPath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
readfile($zipPath);
