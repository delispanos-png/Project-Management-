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
}
