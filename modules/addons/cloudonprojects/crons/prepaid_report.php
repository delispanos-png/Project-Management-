<?php
/**
 * Αναφορά προαγορασμένου χρόνου προς τους πελάτες.
 *
 * Η συχνότητα ορίζεται **ανά πελάτη** στο συμβόλαιό του (Προαγορασμένος χρόνος →
 * Συμβόλαιο → «Αναφορά προς τον πελάτη»): μηνιαία, εβδομαδιαία, και τα δύο, ή καμία.
 * Το cron τρέχει και τις δύο περιόδους· κάθε τρέξιμο στέλνει μόνο σε όποιον
 * την έχει ζητήσει, και μόνο αν υπάρχει κίνηση στην περίοδο.
 *
 * Πρόγραμμα:
 *   μηνιαία   — 1η του μήνα, 08:00:   0 8 1 * *  … prepaid_report.php monthly
 *   εβδομαδιαία — Παρασκευή 18:00:    0 18 * * 5 … prepaid_report.php weekly
 *
 * Δοκιμή χωρίς αποστολή:  CPM_DRY=1 php prepaid_report.php monthly
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

$whmcsRoot = dirname(__DIR__, 4);
require_once $whmcsRoot . '/init.php';
require_once dirname(__DIR__) . '/lib/Db.php';
require_once dirname(__DIR__) . '/lib/Cover.php';
require_once dirname(__DIR__) . '/lib/Report.php';
if (is_file(dirname(__DIR__, 2) . '/supportcontracts/lib/Db.php')) {
    require_once dirname(__DIR__, 2) . '/supportcontracts/lib/Db.php';
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudonProjects\Report;
use WHMCS\Module\Addon\CloudonProjects\Cover;

$freq = in_array($argv[1] ?? '', ['monthly', 'weekly'], true) ? $argv[1] : 'monthly';
$dry = (getenv('CPM_DRY') === '1');

try {
    if (!Capsule::schema()->hasTable('mod_supportcontracts_clients')) {
        exit(0);
    }
    if ($freq === 'monthly') {
        // Ο μήνας που μόλις έκλεισε — τρέχει την 1η, καλύπτει όλο τον προηγούμενο.
        $from = date('Y-m-01', strtotime('first day of last month'));
        $to = date('Y-m-t', strtotime('last day of last month'));
    } else {
        $from = date('Y-m-d', strtotime('-6 days'));
        $to = date('Y-m-d');
    }
    $adminUser = Capsule::table('tbladmins')->where('disabled', 0)->orderBy('id')->value('username');
    Report::ensureTemplate();

    $sent = 0;
    $skipped = 0;
    foreach (Report::dueClients($freq) as $uid) {
        $r = Report::build($uid, $from, $to);
        if ($r['empty']) {
            $skipped++;
            continue;   // καμία κίνηση: μην στέλνεις κενή αναφορά
        }
        if (!$r['to']) {
            $skipped++;
            echo "  ! ο πελάτης #$uid δεν έχει παραλήπτη\n";
            continue;
        }
        if ($dry) {
            echo '  [DRY] #' . $uid . ' ' . Report::clientName($uid) . ' → ' . implode(', ', $r['to'])
                . ' · υπόλοιπο ' . Cover::fmt(Cover::clientState($uid)['balance']) . "\n";
            $sent++;
            continue;
        }
        $res = Report::send($uid, $from, $to, $adminUser);
        if (!empty($res['ok'])) {
            $sent++;
        } else {
            echo '  ! αποτυχία για #' . $uid . ': ' . ($res['error'] ?? '') . "\n";
        }
    }
    echo ($dry ? '[DRY] ' : '') . "αναφορά $freq ($from – $to): στάλθηκαν $sent, παραλείφθηκαν $skipped\n";
} catch (\Throwable $e) {
    error_log('cloudonprojects prepaid_report: ' . $e->getMessage());
    echo 'σφάλμα: ' . $e->getMessage() . "\n";
    exit(1);
}
