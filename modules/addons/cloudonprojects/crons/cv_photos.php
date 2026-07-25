<?php
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
