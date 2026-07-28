<?php
/**
 * Scaleway — nightly reconcile.
 * Εντοπίζει ορφανά instances (χρεώνονται χωρίς υπηρεσία) και υπηρεσίες χωρίς VM,
 * και τα καταγράφει στο ιστορικό του addon.
 *
 * Cron: 0 4 * * * php /path/modules/addons/scaleway/crons/reconcile.php
 */

require_once dirname(__DIR__, 4) . '/init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Scaleway\Db;
use WHMCS\Module\Addon\Scaleway\Sync;

require_once __DIR__ . '/../lib/Db.php';
require_once __DIR__ . '/../lib/Sync.php';

$settings = [];
foreach (Capsule::table('tbladdonmodules')->where('module', 'scaleway')->get() as $r) {
    $settings[$r->setting] = $r->value;
}
if (empty($settings['secret_key']) && !Db::projects(true)) {
    Db::log('Reconcile: δεν υπάρχουν credentials — παραλείφθηκε.', 'warn');
    exit(0);
}

$sync = new Sync($settings);
$res = $sync->reconcile();

Db::log(sprintf('Reconcile: %d instances · %d πιθανά ορφανά · %d υπηρεσίες χωρίς VM.',
    $res['fleet'], count($res['orphans']), count($res['missing'])));

foreach ($res['orphans'] as $o) {
    Db::log('Ορφανό instance: ' . $o['name'] . ' (' . $o['zone'] . ' / ' . $o['type'] . ') — υπηρεσία #' . $o['service_id'], 'warn');
}
foreach ($res['missing'] as $sid) {
    Db::log('Ενεργή υπηρεσία #' . $sid . ' χωρίς συνδεδεμένο instance.', 'warn');
}
echo "OK\n";
