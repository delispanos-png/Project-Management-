<?php
/**
 * Viva.com — χειροκίνητος έλεγχος συμφωνίας πληρωμών.
 *
 *   /opt/plesk/php/8.3/bin/php modules/gateways/vivapayments/crons/reconcile.php
 *
 * Το ίδιο τρέχει και αυτόματα σε κάθε cron του WHMCS (includes/hooks/viva_reconcile.php).
 * Χρήσιμο εδώ όταν θέλεις άμεσο αποτέλεσμα ή μεγαλύτερο παράθυρο ημερών.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../../../../init.php';
require_once ROOTDIR . '/includes/gatewayfunctions.php';
require_once ROOTDIR . '/includes/invoicefunctions.php';
require_once __DIR__ . '/../lib/Settle.php';

use CloudOn\Viva\Settle;

$params = getGatewayVariables('vivapayments');
if (empty($params['type'])) {
    exit("Το module Viva δεν είναι ενεργοποιημένο.\n");
}

$minutes = isset($argv[1]) ? (int) $argv[1] : 3;
$days    = isset($argv[2]) ? (int) $argv[2] : 7;

$res = Settle::reconcile($params, $minutes, $days);

echo "Έλεγχος συμφωνίας Viva (παραγγελίες παλαιότερες των {$minutes}' , έως {$days} ημέρες πίσω):\n";
foreach ($res as $k => $v) {
    echo '  ' . str_pad($k, 14) . $v . "\n";
}
