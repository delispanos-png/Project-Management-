<?php
/**
 * Πρωινή σύνοψη 08:00 — ΕΝΑ email ανά άνθρωπο (lib/Digest.php).
 *
 * Cron (/etc/cron.d/cloudonprojects):
 *   0 8 * * *  …/php crons/digest.php
 * Δοκιμή χωρίς αποστολή:  CPM_DRY=1 php crons/digest.php
 * Προεπισκόπηση ενός:     php crons/digest.php preview <admin_id>   (γράφει HTML στο stdout, δεν στέλνει)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('WHMCS', true);
require __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Db.php';
require_once __DIR__ . '/../lib/Notify.php';
require_once __DIR__ . '/../lib/Overrun.php';
require_once __DIR__ . '/../lib/Digest.php';

use WHMCS\Module\Addon\CloudonProjects\Digest;

$log = function ($m) { echo '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n"; };

if (($argv[1] ?? '') === 'preview') {
    $d = Digest::build((int) ($argv[2] ?? 0));
    echo $d ? "SUBJECT: {$d['subject']}\n\n{$d['html']}\n" : "(τίποτα για σύνοψη)\n";
    exit;
}
$dry = (bool) getenv('CPM_DRY');
$log('Πρωινή σύνοψη' . ($dry ? ' (dry)' : ''));
$n = Digest::run($dry, 0, $log);
$log('Τέλος: ' . $n . ' σύνοψη/εις');
