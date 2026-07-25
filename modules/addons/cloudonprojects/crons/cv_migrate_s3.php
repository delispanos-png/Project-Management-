<?php
/**
 * Migration υπαρχόντων βιογραφικών (local attachments/cloudonprojects) → S3 (Storage layer).
 * Ανεβάζει CV + φωτο στο S3, καταγράφει στο μητρώο, θέτει cv_storage_id/photo_storage_id.
 * Το serving (cv_file/cv_photo/pdf_block) προτιμά αυτόματα το S3 μετά.
 *
 * Χρήση (Plesk PHP — έχει curl):
 *   /opt/plesk/php/8.3/bin/php crons/cv_migrate_s3.php           # dry-run (μόνο αναφορά)
 *   /opt/plesk/php/8.3/bin/php crons/cv_migrate_s3.php go        # μεταφορά στο S3
 *   /opt/plesk/php/8.3/bin/php crons/cv_migrate_s3.php go 300    # με όριο batch
 *   /opt/plesk/php/8.3/bin/php crons/cv_migrate_s3.php purge     # ΔΙΑΓΡΑΦΗ τοπικών (μόνο όσα επιβεβαιωμένα στο S3)
 */

define('WHMCS', true);
require __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Storage.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudonProjects\Storage;

$mode = $argv[1] ?? 'dry';                 // dry | go | purge
$limit = (int) ($argv[2] ?? 400);
$DIR = realpath(__DIR__ . '/../../../../attachments/cloudonprojects');
$lp = fn($name) => $DIR . '/' . basename((string) $name);

if ($mode === 'purge') {
    // Διαγραφή τοπικών αρχείων που είναι ΕΠΙΒΕΒΑΙΩΜΕΝΑ στο S3
    $freed = 0; $n = 0;
    foreach (Capsule::table('mod_cpm_cv')->where(function ($q) { $q->whereNotNull('cv_storage_id')->orWhereNotNull('photo_storage_id'); })->get() as $r) {
        foreach ([['cv_storage_id', 'cv_stored'], ['photo_storage_id', 'photo']] as [$sidCol, $fileCol]) {
            $sid = (int) ($r->$sidCol ?? 0); $fname = (string) ($r->$fileCol ?? '');
            if (!$sid || $fname === '') { continue; }
            $rec = Storage::record($sid);
            if (!$rec || !Storage::exists($rec['storage_key'], $rec['driver'], $rec['bucket'])) { continue; }  // ασφάλεια: μόνο αν υπάρχει στο storage
            $p = $lp($fname);
            if (is_file($p)) { $sz = filesize($p); if (@unlink($p)) { $freed += $sz; $n++; } }
        }
    }
    echo "Purge: διαγράφηκαν $n τοπικά αρχεία, ελευθερώθηκαν " . round($freed / 1048576, 1) . " MB\n";
    exit;
}

if ($mode === 'go' && Storage::driver() !== 's3') {
    echo "⚠ Ο ενεργός driver ΔΕΝ είναι s3 — ρύθμισε πρώτα το S3 (Ρυθμίσεις → Αρχεία & Storage).\n";
    exit;
}

$rows = Capsule::table('mod_cpm_cv')
    ->where(function ($q) { $q->whereNull('cv_storage_id')->where('cv_stored', '!=', ''); })
    ->orWhere(function ($q) { $q->whereNull('photo_storage_id')->where('photo', '!=', '')->whereNotNull('photo'); })
    ->orderBy('id')->limit($limit)->get();

$cvN = 0; $phN = 0; $bytes = 0; $miss = 0;
foreach ($rows as $r) {
    // CV αρχείο
    if (empty($r->cv_storage_id) && !empty($r->cv_stored)) {
        $p = $lp($r->cv_stored);
        if (is_file($p)) {
            if ($mode === 'go') {
                try {
                    $rec = Storage::store(['module' => 'cv', 'ref_type' => 'cv_file', 'ref_id' => (int) $r->id,
                        'src' => $p, 'orig_name' => $r->cv_name ?: 'cv.pdf', 'mime' => $r->cv_mime ?: 'application/octet-stream']);
                    Capsule::table('mod_cpm_cv')->where('id', $r->id)->update(['cv_storage_id' => (int) $rec['id']]);
                } catch (\Throwable $e) { echo "  ! CV #{$r->id}: " . $e->getMessage() . "\n"; continue; }
            }
            $cvN++; $bytes += filesize($p);
        } else { $miss++; }
    }
    // Φωτογραφία
    if (empty($r->photo_storage_id) && !empty($r->photo)) {
        $p = $lp($r->photo);
        if (is_file($p)) {
            if ($mode === 'go') {
                try {
                    $rec = Storage::store(['module' => 'cv', 'ref_type' => 'cv_photo', 'ref_id' => (int) $r->id,
                        'src' => $p, 'orig_name' => basename($r->photo), 'mime' => 'image/jpeg']);
                    Capsule::table('mod_cpm_cv')->where('id', $r->id)->update(['photo_storage_id' => (int) $rec['id']]);
                } catch (\Throwable $e) { echo "  ! photo #{$r->id}: " . $e->getMessage() . "\n"; continue; }
            }
            $phN++; $bytes += filesize($p);
        }
    }
}
$remaining = (int) Capsule::table('mod_cpm_cv')->whereNull('cv_storage_id')->where('cv_stored', '!=', '')->count();
echo ($mode === 'go' ? "Μεταφέρθηκαν" : "[dry] Προς μεταφορά") . ": $cvN CV + $phN φωτο (" . round($bytes / 1048576, 1) . " MB)"
    . ($miss ? ", $miss λείπουν τοπικά" : '') . ". Απομένουν CV χωρίς migration: $remaining\n";
