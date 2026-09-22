<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Διασταύρωση με το φύλλο «ΣΥΝΟΛΟ ΑΔΕΙΕΣ» του Excel.
 * Αν αυτό δεν βγει καθαρό, η μεταφορά ΔΕΝ είναι σωστή — όσο ωραία κι αν φαίνεται.
 */
define('WHMCS', true);
require '/var/www/vhosts/cloudon.gr/my.cloudon.gr/init.php';
require_once dirname(__DIR__, 2) . '/lib/Db.php';
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudonProjects\Leave;

/* κωδικός => [έτος => [δικαιούμενες, ληφθείσες, υπόλοιπο]] — όπως στο φύλλο */
$exp = [
  'EYST'=>[2023=>[25,25,0],2024=>[25,25,0],2025=>[25,25,0],2026=>[25,13,12]],
  'VAKR'=>[2023=>[26,26,0],2024=>[26,26,0],2025=>[26,26,0],2026=>[26,12,14]],
  'ALEF'=>[2023=>[25,25,0],2024=>[25,25,0],2025=>[25,25,0],2026=>[25,13,12]],
  'KONS'=>[2023=>[22,22,0],2024=>[22,22,0],2025=>[22,22,0],2026=>[22,12,10]],
  'DIMI'=>[2023=>[25,25,0],2024=>[25,25,0],2025=>[25,25,0],2026=>[25,12,13]],
  'MITR'=>[2023=>[21,21,0],2024=>[22,22,0],2025=>[22,22,0],2026=>[22, 9,13]],
  'KONT'=>[2024=>[18,18,0],2025=>[21,21,0],2026=>[22, 9,13]],
  'KARO'=>[2025=>[ 7, 7,0],2026=>[20, 6,14]],
  'LION'=>[2026=>[20, 9,11]],
];
$bad = 0; $ok = 0;
foreach ($exp as $code => $years) {
    $s = Capsule::table('mod_cpm_leave_staff')->where('source_code', $code)->first();
    if (!$s) { echo "✘ $code: δεν βρέθηκε\n"; $bad++; continue; }
    $bal = Leave::balance($s->id);
    foreach ($years as $y => [$e, $t, $r]) {
        $g = $bal[$y] ?? null;
        if (!$g) { printf("✘ %-5s %d: λείπει το έτος\n", $code, $y); $bad++; continue; }
        $d = [];
        if (abs($g['entitled'] - $e) > 0.01)  { $d[] = "δικαιούμενες {$g['entitled']} αντί $e"; }
        if (abs($g['taken'] - $t) > 0.01)     { $d[] = "ληφθείσες {$g['taken']} αντί $t"; }
        if (abs($g['remaining'] - $r) > 0.01) { $d[] = "υπόλοιπο {$g['remaining']} αντί $r"; }
        if ($d) { printf("✘ %-5s %d: %s\n", $code, $y, implode(' · ', $d)); $bad++; }
        else { $ok++; }
    }
}
echo "\nΣυμφωνούν: $ok · Διαφέρουν: $bad\n";
