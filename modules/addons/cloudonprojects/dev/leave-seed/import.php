<?php
/* ΜΟΝΟ ΑΠΟ ΓΡΑΜΜΗ ΕΝΤΟΛΩΝ. Ο φάκελος είναι μέσα στο webroot. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/**
 * Μεταφορά του μητρώου αδειών από το Excel στο Project Manager.
 *
 * ΕΚΤΕΛΕΙΤΑΙ ΞΑΝΑ ΑΚΙΝΔΥΝΑ: κάθε άδεια φέρει το `source_id` του αρχείου
 * (π.χ. ALEF-ANN-2026-001) και ενημερώνεται αντί να διπλογραφεί.
 *
 * ΔΕΝ ΑΓΓΙΖΕΙ άδειες που καταχωρήθηκαν μέσα από το εργαλείο (source_id κενό).
 *
 *   php import.php            → δοκιμή, δεν γράφει τίποτα
 *   php import.php --write    → γράφει
 */

define('WHMCS', true);
require '/var/www/vhosts/cloudon.gr/my.cloudon.gr/init.php';
require_once dirname(__DIR__, 2) . '/lib/Db.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudonProjects\Leave;

$write = in_array('--write', $argv, true);

/* Το Excel είναι ελληνικά, οι χειριστές του WHMCS λατινικά — η αντιστοίχιση
   γίνεται ΜΙΑ ΦΟΡΑ εδώ, ρητά, αντί για μαντεψιές με μεταγραφή. */
const MAP = [
    'ALEF' => 21,  // Dora Alefanti
    'VAKR' => 10,  // Vasilis Vakrinos
    'DIMI' => 20,  // Thimios Dimitropoulos
    'EYST' => 3,   // Emmanuela Efstratiades
    'KONS' => 22,  // nikos konstantakopoulos
    'MITR' => 9,   // John Mitrousis
    'KONT' => 23,  // marios kontos
    'KARO' => 27,  // Kleon Karolos
    'LION' => 28,  // Dionisis Liontos
];

Leave::install();

$lines = file(__DIR__ . '/seed.txt', FILE_IGNORE_NEW_LINES);
$staffId = null;
$code = '';
$n = ['staff' => 0, 'years' => 0, 'leaves' => 0, 'updated' => 0, 'skipped' => 0];
$now = date('Y-m-d H:i:s');

foreach ($lines as $ln) {
    if ($ln === '' || $ln[0] === '#') { continue; }
    $f = explode('|', $ln);

    if ($f[0] === 'E') {
        [, $code, $afm, $lastName, $hire, $accrual] = array_pad($f, 6, '');
        $notes = $f[6] ?? '';
        $adminId = MAP[$code] ?? 0;
        if (!$adminId) {
            fwrite(STDERR, "ΠΡΟΣΟΧΗ: ο κωδικός $code δεν αντιστοιχεί σε χειριστή — παραλείπεται\n");
            $staffId = null;
            continue;
        }
        $row = [
            'admin_id'      => $adminId,
            'afm'           => $afm ?: null,
            'hire_date'     => $hire ?: null,
            'accrual_month' => $accrual !== '' ? (float) $accrual : null,
            'source_code'   => $code,
            'active'        => 1,
            'notes'         => $notes ?: null,
        ];
        $ex = Capsule::table('mod_cpm_leave_staff')->where('admin_id', $adminId)->first();
        if ($ex) {
            $staffId = (int) $ex->id;
            if ($write) { Capsule::table('mod_cpm_leave_staff')->where('id', $staffId)->update($row); }
        } else {
            $row['created_at'] = $now;
            $staffId = $write ? (int) Capsule::table('mod_cpm_leave_staff')->insertGetId($row) : -1;
            $n['staff']++;
        }
        echo sprintf("· %-5s %-22s → χειριστής #%d\n", $code, $lastName, $adminId);
        continue;
    }

    if ($staffId === null) { continue; }

    if ($f[0] === 'Y') {
        [, $year, $type, $entitled, $legacy] = array_pad($f, 5, '');
        $note = $f[5] ?? '';
        $row = [
            'staff_id'      => $staffId,
            'year'          => (int) $year,
            'type'          => $type,
            'entitled_days' => (float) $entitled,
            'legacy_taken'  => $legacy !== '' ? (float) $legacy : null,
            'note'          => $note ?: null,
        ];
        if ($write) {
            $ex = Capsule::table('mod_cpm_leave_years')->where('staff_id', $staffId)
                ->where('year', (int) $year)->where('type', $type)->first();
            if ($ex) { Capsule::table('mod_cpm_leave_years')->where('id', $ex->id)->update($row); }
            else { Capsule::table('mod_cpm_leave_years')->insert($row); }
        }
        $n['years']++;
        continue;
    }

    if ($f[0] === 'L') {
        [, $sid, $year, $type, $days, $from, $to, $raw, $st] = array_pad($f, 9, '');
        $ergani = $f[9] ?? '';
        $note   = $f[10] ?? '';
        $flag   = $f[11] ?? '';
        $row = [
            'staff_id'   => $staffId,
            'year'       => (int) $year,
            'type'       => $type,
            'days'       => (float) $days,
            'date_from'  => $from ?: null,
            'date_to'    => $to ?: ($from ?: null),
            'raw_period' => $raw ?: null,
            /* Όλα όσα έρχονται από το Excel έχουν ήδη συμβεί. Το κύκλωμα
               αιτημάτων αφορά από εδώ και μπρος. */
            'status'     => 'taken',
            'ergani'     => $ergani ?: null,
            'flag'       => $flag ?: null,
            'note'       => $note ?: null,
            'source_id'  => $sid,
            'updated_at' => $now,
        ];
        if ($write) {
            $ex = Capsule::table('mod_cpm_leaves')->where('source_id', $sid)->first();
            if ($ex) {
                Capsule::table('mod_cpm_leaves')->where('id', $ex->id)->update($row);
                $n['updated']++;
            } else {
                $row['created_at'] = $now;
                Capsule::table('mod_cpm_leaves')->insert($row);
                $n['leaves']++;
            }
        } else {
            $n['leaves']++;
        }
    }
}

echo "\n" . ($write ? 'ΓΡΑΦΤΗΚΑΝ' : 'ΔΟΚΙΜΗ — δεν γράφτηκε τίποτα') . ":\n";
echo "  εργαζόμενοι νέοι : {$n['staff']}\n";
echo "  έτη δικαιώματος  : {$n['years']}\n";
echo "  άδειες νέες      : {$n['leaves']}\n";
echo "  άδειες ενημέρωση : {$n['updated']}\n";
if (!$write) { echo "\nΓια να γραφτούν: php " . basename(__FILE__) . " --write\n"; }
