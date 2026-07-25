<?php
/**
 * Αποδιπλασιασμός υποψηφίων: κρατά την ΠΙΟ ΠΡΟΣΦΑΤΗ καταχώρηση ανά email,
 * μεταφέροντας αξιολόγηση/status/σημειώσεις από τις παλιότερες ώστε να μη χαθεί δουλειά.
 * Dry-run:  php cv_dedup.php
 * Εκτέλεση: php cv_dedup.php go
 */

define('WHMCS', true);
require __DIR__ . '/../../../../init.php';

use WHMCS\Database\Capsule;

$GO = (($argv[1] ?? '') === 'go');
$DIR = '/var/www/vhosts/cloudon.gr/my.cloudon.gr/attachments/cloudonprojects';
$statusRank = ['rejected' => 0, 'new' => 1, 'review' => 2, 'shortlist' => 3, 'interview' => 4, 'hired' => 5];

$rows = Capsule::table('mod_cpm_cv')->whereRaw("TRIM(email) <> ''")->orderBy('id')->get();
$groups = [];
foreach ($rows as $r) { $groups[mb_strtolower(trim($r->email))][] = $r; }

$dupGroups = 0; $removed = 0;
foreach ($groups as $email => $g) {
    if (count($g) < 2) { continue; }
    $dupGroups++;
    // πιο πρόσφατη = μεγαλύτερο applied_at, μετά id
    usort($g, function ($a, $b) {
        $c = strcmp((string) $b->applied_at, (string) $a->applied_at);
        return $c !== 0 ? $c : ($b->id <=> $a->id);
    });
    $keep = $g[0];
    $olders = array_slice($g, 1);
    // carry-over στο keep ό,τι του λείπει, από τις παλιότερες
    $upd = [];
    foreach ($g as $x) {
        if ($x->id === $keep->id) { continue; }
        if ($keep->ai_score === null && $x->ai_score !== null && !isset($upd['ai_score'])) {
            $upd['ai_score'] = $x->ai_score; $upd['ai_json'] = $x->ai_json; $upd['ai_model'] = $x->ai_model;
        }
        if (($keep->photo === '' || $keep->photo === null) && $x->photo && !isset($upd['photo'])) { $upd['photo'] = $x->photo; }
        if (empty($keep->interview_json) && !empty($x->interview_json) && !isset($upd['interview_json'])) { $upd['interview_json'] = $x->interview_json; $upd['interview_eval'] = $x->interview_eval; }
        // πιο προχωρημένο status
        if (($statusRank[$x->status] ?? 1) > ($statusRank[$keep->status] ?? 1) && ($statusRank[$x->status] ?? 1) > ($statusRank[$upd['status'] ?? $keep->status] ?? 1)) { $upd['status'] = $x->status; }
        if ((int) $x->rating > (int) ($upd['rating'] ?? $keep->rating)) { $upd['rating'] = (int) $x->rating; }
    }
    echo ($GO ? '[GO] ' : '[dry] ') . $email . ' — κρατώ #' . $keep->id . ' (' . substr((string) $keep->applied_at, 0, 10) . '), σβήνω: '
        . implode(',', array_map(fn($x) => '#' . $x->id, $olders)) . ($upd ? ' [carry: ' . implode(',', array_keys($upd)) . ']' : '') . "\n";
    if ($GO) {
        if ($upd) { $upd['updated_at'] = date('Y-m-d H:i:s'); Capsule::table('mod_cpm_cv')->where('id', $keep->id)->update($upd); }
        foreach ($olders as $x) {
            // μη σβήσεις αρχείο/φωτο που μεταφέρθηκε στο keep
            $keepStored = Capsule::table('mod_cpm_cv')->where('id', $keep->id)->first();
            if ($x->cv_stored && $x->cv_stored !== $keepStored->cv_stored) { @unlink($DIR . '/' . basename($x->cv_stored)); }
            if ($x->photo && $x->photo !== $keepStored->photo) { @unlink($DIR . '/' . basename($x->photo)); }
            Capsule::table('mod_cpm_cv_comms')->where('cv_id', $x->id)->delete();
            Capsule::table('mod_cpm_cv')->where('id', $x->id)->delete();
            $removed++;
        }
    } else {
        $removed += count($olders);
    }
}
echo "\n" . ($GO ? 'ΕΓΙΝΕ' : 'DRY-RUN') . ": $dupGroups διπλότυπες ομάδες (ίδιο email), " . ($GO ? 'αφαιρέθηκαν' : 'θα αφαιρεθούν') . " $removed καταχωρήσεις.\n";
