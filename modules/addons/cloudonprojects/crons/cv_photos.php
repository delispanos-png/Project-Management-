<?php
/* ΜΟΝΟ ΑΠΟ ΓΡΑΜΜΗ ΕΝΤΟΛΩΝ (20/09/2026).
   Ο φάκελος βρίσκεται μέσα στο webroot, οπότε το script ήταν εκτελέσιμο από
   ΟΠΟΙΟΝΔΗΠΟΤΕ στο internet με ένα απλό GET — μετρήθηκε: και τα οκτώ cron
   απαντούσαν HTTP 200 και έτρεχαν κανονικά. Αυτό σημαίνει ότι ένας ξένος
   μπορούσε να χτυπά το τηλεφωνικό κέντρο με συγχρονισμούς, να στέλνει
   ειδοποιήσεις στην ομάδα και αναφορές σε πελάτες, όσες φορές ήθελε.
   Όλα τα cron τρέχουν από το /etc/cron.d με CLI php, άρα δεν χάνεται τίποτα. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Εξαγωγή φωτογραφιών (headshots) από τα βιογραφικά PDF → thumbnails.
 * Idempotent (μόνο όσα δεν έχουν ήδη photo). Τρέξε:
 *   /opt/plesk/php/8.3/bin/php modules/addons/cloudonprojects/crons/cv_photos.php [limit]
 */

define('WHMCS', true);
require __DIR__ . '/../../../../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudonProjects\CvPhoto;

require_once __DIR__ . '/../lib/CvPhoto.php';

$DIR = '/var/www/vhosts/cloudon.gr/my.cloudon.gr/attachments/cloudonprojects';
$limit = isset($argv[1]) ? (int) $argv[1] : 0;

$q = Capsule::table('mod_cpm_cv')->where('cv_stored', '<>', '')->where('cv_mime', 'application/pdf')->where('photo', '');
if ($limit > 0) { $q->limit($limit); }
$rows = $q->orderByDesc('id')->get(['id', 'cv_stored']);

$found = 0; $none = 0;
foreach ($rows as $r) {
    $pdf = $DIR . '/' . basename($r->cv_stored);
    $name = CvPhoto::extract($pdf, $DIR, $r->id);
    Capsule::table('mod_cpm_cv')->where('id', $r->id)->update(['photo' => $name]);
    if ($name !== '') { $found++; } else { $none++; }
}
echo 'Επεξεργάστηκαν: ' . count($rows) . " · με φωτογραφία: $found · χωρίς: $none\n";
