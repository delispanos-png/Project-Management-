<?php
/**
 * Εξαγωγή φωτογραφίας (headshot) από βιογραφικό PDF με face detection.
 * Render σελίδας (pdftoppm) + OpenCV Haar cascade (python) → τετράγωνο thumbnail.
 */

namespace WHMCS\Module\Addon\CloudonProjects;

class CvPhoto
{
    /** Επιστρέφει 'photo_<id>.jpg' αν βρέθηκε πρόσωπο, αλλιώς ''. */
    public static function extract($pdf, $destDir, $cvId)
    {
        if (!is_file($pdf)) {
            return '';
        }
        $name = 'photo_' . (int) $cvId . '.jpg';
        $out = $destDir . '/' . $name;
        @exec('/usr/bin/python3 /opt/cloudon-cv/facecrop.py ' . escapeshellarg($pdf) . ' ' . escapeshellarg($out) . ' 2>/dev/null', $o);
        $res = trim(implode('', $o));
        if ($res === 'OK' && is_file($out) && filesize($out) > 0) {
            return $name;
        }
        if (is_file($out)) { @unlink($out); }
        return '';
    }

    /**
     * Ingest ανεβασμένου CV κατευθείαν στο Storage (S3/local): αποθηκεύει το αρχείο,
     * εξάγει & αποθηκεύει τη φωτο (αν PDF). Δεν αφήνει μόνιμο τοπικό αρχείο (πέρα από temp).
     * $tmpPath = upload tmp_name. Επιστρέφει ['cv_storage_id','photo_storage_id','photo'].
     */
    public static function ingest($tmpPath, $origName, $mime, $cvId)
    {
        $out = ['cv_storage_id' => null, 'photo_storage_id' => null, 'photo' => '', 'cv_stored' => ''];
        if (!is_file($tmpPath)) { return $out; }
        $localDir = Storage::base() . '/attachments/cloudonprojects';
        // CV αρχείο → Storage· fallback τοπικά αν αποτύχει (π.χ. S3 down) — ο sweep cron μεταφέρει μετά
        try {
            $rec = Storage::store(['module' => 'cv', 'ref_type' => 'cv_file', 'ref_id' => (int) $cvId,
                'src' => $tmpPath, 'orig_name' => $origName, 'mime' => $mime]);
            $out['cv_storage_id'] = (int) $rec['id'];
        } catch (\Throwable $e) {
            if (!is_dir($localDir)) { @mkdir($localDir, 0750, true); }
            $ext = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($origName, PATHINFO_EXTENSION))) ?: 'pdf';
            $stored = uniqid('cv', true) . '.' . $ext;
            if (@copy($tmpPath, $localDir . '/' . $stored)) { $out['cv_stored'] = $stored; }
        }
        // Φωτογραφία (μόνο PDF)
        if ($mime === 'application/pdf') {
            $tmpDir = sys_get_temp_dir();
            $pn = self::extract($tmpPath, $tmpDir, $cvId);
            if ($pn !== '') {
                $pp = $tmpDir . '/' . $pn;
                if (is_file($pp)) {
                    try {
                        $prec = Storage::store(['module' => 'cv', 'ref_type' => 'cv_photo', 'ref_id' => (int) $cvId,
                            'src' => $pp, 'orig_name' => $pn, 'mime' => 'image/jpeg']);
                        $out['photo_storage_id'] = (int) $prec['id'];
                        $out['photo'] = $pn;
                    } catch (\Throwable $e) {
                        if (!is_dir($localDir)) { @mkdir($localDir, 0750, true); }
                        if (@copy($pp, $localDir . '/' . $pn)) { $out['photo'] = $pn; }   // τοπικά fallback
                    }
                    @unlink($pp);
                }
            }
        }
        return $out;
    }
}
