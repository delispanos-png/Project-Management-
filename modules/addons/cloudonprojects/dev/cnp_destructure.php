<?php
/* Εργαλείο ελέγχου: ΜΟΝΟ από γραμμή εντολών. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('403'); }
/* Τα views αποδομούν ονόματα από το window.CNP. Ένα όνομα που δεν εξάγεται γίνεται
   σιωπηλά undefined και σκάει μόνο τη στιγμή που ο χρήστης πατήσει το κουμπί. */
$dir = '/var/www/vhosts/cloudon.gr/my.cloudon.gr/projectmanagement';
$exp = array_map('trim', array_filter(explode("\n", shell_exec(
    '/opt/plesk/php/8.3/bin/php ' . escapeshellarg(__DIR__ . '/cnp_exports.php')))));
$bad = 0;
foreach (glob($dir . '/*.js') as $f) {
    $s = file_get_contents($f);
    if (!preg_match_all('/(?:const|let|var)\s*\{([^}]*)\}\s*=\s*window\.CNP\s*;/s', $s, $m)) { continue; }
    foreach ($m[1] as $body) {
        foreach (explode(',', $body) as $t) {
            $t = trim(preg_replace('/:.*$/', '', $t));
            if ($t === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $t)) { continue; }
            if (!in_array($t, $exp, true)) {
                echo '  ✗ ' . basename($f) . ": αποδομεί «$t» που ΔΕΝ υπάρχει στο window.CNP\n";
                $bad++;
            }
        }
    }
}
echo $bad ? '' : "  ✓ κάθε αποδομημένο όνομα υπάρχει στο window.CNP\n";
