<?php
/**
 * Αυτόματη αξιολόγηση νέων αιτήσεων (source=form) από τη δημόσια σελίδα apply.php.
 * Κάνει HTTP self-call στο cv_ai (επαναχρησιμοποιεί eval + ειδοποιήσεις high-interest).
 * Τρέξε κάθε 5': php modules/addons/cloudonprojects/crons/cv_autoeval.php
 */

define('WHMCS', true);
require __DIR__ . '/../../../../projectmanagement/boot.php';   // pm_mint_token + Capsule

use WHMCS\Database\Capsule;

$ids = Capsule::table('mod_cpm_cv')->where('source', 'form')->whereNull('ai_score')
    ->where('cv_mime', 'application/pdf')->orderBy('id')->limit(40)->pluck('id')->all();
if (!$ids) { echo "Καμία νέα αίτηση προς αξιολόγηση\n"; exit; }

$base = 'https://my.cloudon.gr';
$ua = 'CloudOn-CVAutoEval';
$jar = tempnam(sys_get_temp_dir(), 'cvj');
$tok = pm_mint_token(2, 1800);   // admin #2, 30' ttl

// εγκατάσταση session (token handoff, UA-bound)
$ch = curl_init($base . '/project/?t=' . $tok);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => $ua, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
curl_exec($ch); curl_close($ch);

$done = 0;
foreach ($ids as $id) {
    $ch = curl_init($base . '/projectmanagement/api.php?a=cv_ai');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => $ua, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['id' => (int) $id]), CURLOPT_TIMEOUT => 120]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 200) { $done++; }
    echo "eval #$id → HTTP $code\n";
}
@unlink($jar);
echo "Αξιολογήθηκαν $done / " . count($ids) . " νέες αιτήσεις\n";
