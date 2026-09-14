<?php
/* Εργαλείο ελέγχου: ΜΟΝΟ από γραμμή εντολών. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('403'); }
/* Κάθε api('x') του frontend πρέπει να έχει «case 'x':» στο api.php. Αλλιώς το κουμπί
   είναι νεκρό και ο χρήστης βλέπει σφάλμα μόνο όταν το πατήσει. */
$root = '/var/www/vhosts/cloudon.gr/my.cloudon.gr';
$api = file_get_contents($root . '/projectmanagement/api.php');
preg_match_all("/^\s*case\s+'([a-z0-9_]+)'\s*:/mi", $api, $m);
$have = array_flip($m[1]);
$used = [];
foreach (glob($root . '/projectmanagement/*.js') as $f) {
    $s = file_get_contents($f);
    preg_match_all("/\bapi\(\s*'([a-z0-9_]+)'/i", $s, $m2);
    foreach ($m2[1] as $c) { $used[$c][basename($f)] = true; }
}
$bad = 0;
foreach ($used as $c => $where) {
    if (isset($have[$c])) { continue; }
    echo "  ✗ api('$c') από " . implode(', ', array_keys($where)) . " — δεν υπάρχει case στο api.php\n";
    $bad++;
}
echo $bad ? '' : '  ✓ κάθε κλήση api() έχει endpoint (' . count($used) . ' σε χρήση, ' . count($have) . " δηλωμένα)\n";
/* Και το αντίστροφο, πληροφοριακά: endpoints που δεν καλεί κανείς. */
$orphan = array_diff(array_keys($have), array_keys($used));
echo '  ℹ ' . count($orphan) . " endpoints δεν καλούνται από το SPA (cron/webhook/παλιά): "
    . implode(', ', array_slice($orphan, 0, 12)) . (count($orphan) > 12 ? ' …' : '') . "\n";
