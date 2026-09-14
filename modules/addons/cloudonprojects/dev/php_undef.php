<?php
/* Εργαλείο ελέγχου: ΜΟΝΟ από γραμμή εντολών. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('403'); }
/* Καλείται συνάρτηση που δεν ορίζεται πουθενά; Τέτοιο λάθος ΔΕΝ το πιάνει το php -l —
   σκάει μόνο τη στιγμή που ο χρήστης πατήσει το κουμπί (το είχαμε ζήσει με το
   cnp_action_area() στις 4/9). */
$root = '/var/www/vhosts/cloudon.gr/my.cloudon.gr';
$files = array_merge(
    glob($root . '/projectmanagement/*.php'),
    glob($root . '/modules/addons/cloudonprojects/*.php'),
    glob($root . '/modules/addons/cloudonprojects/lib/*.php'),
    glob($root . '/modules/addons/cloudonprojects/crons/*.php'),
    glob($root . '/includes/hooks/*.php')
);
$defs = [];
$calls = [];
foreach ($files as $f) {
    $s = file_get_contents($f);
    preg_match_all('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $s, $m);
    foreach ($m[1] as $d) { $defs[strtolower($d)] = true; }
    /* Μόνο τα δικά μας προθέματα: οτιδήποτε άλλο είναι PHP/WHMCS και δεν το ελέγχουμε. */
    preg_match_all('/(?<![\$>:\w])((?:cnp|cpm|cloudon)_[A-Za-z0-9_]*)\s*\(/', $s, $m2);
    foreach ($m2[1] as $i => $c) { $calls[strtolower($c)][basename($f)] = true; }
}
$bad = 0;
foreach ($calls as $c => $where) {
    if (isset($defs[$c])) { continue; }
    if (function_exists($c)) { continue; }
    echo "  ✗ $c() καλείται σε " . implode(', ', array_keys($where)) . " αλλά δεν ορίζεται\n";
    $bad++;
}
echo $bad ? "" : '  ✓ κάθε δική μας συνάρτηση που καλείται, ορίζεται (' . count($calls) . " ονόματα)\n";
