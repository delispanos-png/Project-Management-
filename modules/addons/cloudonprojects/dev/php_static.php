<?php
/* Εργαλείο ελέγχου: ΜΟΝΟ από γραμμή εντολών. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('403'); }
/* Db::foo(), Catalog::foo(), Pharmacy::foo()… Ένα όνομα που δεν υπάρχει περνά το php -l
   και σκάει μόνο στον χρήστη. Εδώ ελέγχουμε ΟΛΕΣ τις στατικές κλήσεις των κλάσεών μας. */
$root = '/var/www/vhosts/cloudon.gr/my.cloudon.gr';
$libs = glob($root . '/modules/addons/cloudonprojects/lib/*.php');
$members = [];               // κλάση => [μέθοδοι + σταθερές + στατικές ιδιότητες]
foreach ($libs as $f) {
    $cls = basename($f, '.php');
    $s = file_get_contents($f);
    preg_match_all('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $s, $m);
    preg_match_all('/const\s+([A-Z_][A-Z0-9_]*)/', $s, $m2);
    preg_match_all('/static\s+\$([A-Za-z_][A-Za-z0-9_]*)/', $s, $m3);
    $members[$cls] = array_map('strtolower', array_merge($m[1], $m2[1], $m3[1]));
}
$files = array_merge(
    glob($root . '/projectmanagement/*.php'), $libs,
    glob($root . '/modules/addons/cloudonprojects/*.php'),
    glob($root . '/modules/addons/cloudonprojects/crons/*.php'),
    glob($root . '/includes/hooks/*.php')
);
$bad = 0; $n = 0;
foreach ($files as $f) {
    $s = file_get_contents($f);
    if (!preg_match_all('/\b([A-Z][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)/', $s, $m, PREG_SET_ORDER)) { continue; }
    foreach ($m as $x) {
        if (!isset($members[$x[1]])) { continue; }          // ξένη κλάση — δεν μας αφορά
        $n++;
        if (in_array(strtolower($x[2]), $members[$x[1]], true)) { continue; }
        if (in_array(strtolower($x[2]), ['class'], true)) { continue; }
        echo '  ✗ ' . basename($f) . ": {$x[1]}::{$x[2]} δεν υπάρχει στην κλάση\n";
        $bad++;
    }
}
echo $bad ? "" : "  ✓ κάθε στατική κλήση δικής μας κλάσης υπάρχει ($n κλήσεις)\n";
