<?php
/* Εργαλείο ελέγχου: ΜΟΝΟ από γραμμή εντολών. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('403'); }
/* Ο φύλακας είναι deny-by-default: ένα endpoint που δεν δηλώθηκε ΟΥΤΕ σε cnp_action_cap
   ΟΥΤΕ σε cnp_open_actions επιστρέφει 403 — δηλαδή νεκρό κουμπί. Εδώ το βρίσκουμε
   πριν το βρει ο χρήστης. */
$root = '/var/www/vhosts/cloudon.gr/my.cloudon.gr';
$api = file_get_contents($root . '/projectmanagement/api.php');

preg_match_all("/^\s*case\s+'([a-z0-9_]+)'\s*:/mi", $api, $m);
$cases = array_unique($m[1]);

/* Ο χάρτης χτίζεται με $add('cap', ['a','b',…]) — όχι με «=>», γι' αυτό διαβάζουμε
   τις λίστες μέσα στις κλήσεις $add(). */
$i = strpos($api, 'function cnp_action_cap');
$j = strpos($api, 'return $map[$action]', $i);
$src = substr($api, $i, $j - $i);
preg_match_all("/\\\$add\\(\\s*'[^']*'\\s*,\\s*\\[(.*?)\\]\\s*\\)/s", $src, $m2);
$gated = [];
foreach ($m2[1] as $lst) {
    preg_match_all("/'([a-z0-9_]+)'/", $lst, $mm);
    foreach ($mm[1] as $a) { $gated[$a] = true; }
}

$i = strpos($api, 'function cnp_open_actions');
$j = strpos($api, '];', $i);
preg_match_all("/'([a-z0-9_]+)'/", substr($api, $i, $j - $i), $m3);
$open = array_flip($m3[1]);

/* Και οι επισκέπτες με δικό τους υπογεγραμμένο token (cnp_guest_actions + rtc_*). */
$i = strpos($api, 'function cnp_guest_actions');
$j = strpos($api, '];', $i);
preg_match_all("/'([a-z0-9_]+)'/", substr($api, $i, $j - $i), $m4);
$guest = array_flip($m4[1]);

$bad = 0;
foreach ($cases as $c) {
    if (isset($gated[$c]) || isset($open[$c]) || isset($guest[$c])) { continue; }
    echo "  ✗ case '$c' — δεν δηλώθηκε πουθενά, ο φύλακας θα το κόψει με 403\n";
    $bad++;
}
echo $bad ? '' : '  ✓ κάθε endpoint είναι δηλωμένο στο μοντέλο δικαιωμάτων ('
    . count($cases) . ' endpoints: ' . count(array_intersect_key($gated, array_flip($cases)))
    . ' με δικαίωμα, ' . count(array_intersect_key($open, array_flip($cases))) . " ανοιχτά)\n";

/* Και το αντίστροφο: δηλωμένο όνομα που δεν αντιστοιχεί σε endpoint (ξεχασμένο). */
$ghost = array_diff(array_merge(array_keys($gated), array_keys($open), array_keys($guest)), $cases);
if ($ghost) { echo '  ℹ δηλωμένα χωρίς endpoint (άκυρα): ' . implode(', ', $ghost) . "\n"; }
