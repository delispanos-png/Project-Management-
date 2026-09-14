<?php
/* Εργαλείο ελέγχου: ΜΟΝΟ από γραμμή εντολών. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('403'); }
/* Τυπώνει ό,τι εξάγεται στο window.CNP — με ισοστάθμιση αγκίστρων, όχι αφελές regex,
   γιατί το αντικείμενο window.CNP = {…} έχει ένθετα αγκύλια και το regex έκοβε στη μέση. */
$dir = '/var/www/vhosts/cloudon.gr/my.cloudon.gr/projectmanagement';
$src = '';
foreach (glob($dir . '/*.js') as $f) { $src .= file_get_contents($f) . "\n"; }

preg_match_all('/window\.CNP\.([A-Za-z_][A-Za-z0-9_]*)\s*=/', $src, $m);
$out = $m[1];

$i = strpos($src, 'window.CNP = {');
if ($i !== false) {
    $i = strpos($src, '{', $i);
    $d = 0;
    for ($j = $i; $j < strlen($src); $j++) {
        if ($src[$j] === '{') { $d++; } elseif ($src[$j] === '}') { $d--; if (!$d) { break; } }
    }
    $body = substr($src, $i + 1, $j - $i - 1);
/* Το οριοθετικό μπαίνει σε lookahead: αλλιώς το regex «καταναλώνει» το κόμμα
   και χάνει κάθε ΔΕΥΤΕΡΟ όνομα της λίστας (έτσι «εξαφανιζόταν» το cnpConfirm). */
    preg_match_all('/(?:^|[,{])\s*([A-Za-z_][A-Za-z0-9_]*)\s*(?=[,:}])/', $body, $m2);
    $out = array_merge($out, $m2[1]);
}
echo implode("\n", array_unique($out)), "\n";
