<?php
/**
 * Έλεγχος υγείας των δικών μας παρεμβάσεων στο WHMCS.
 *
 *   /opt/plesk/php/8.3/bin/php scripts/healthcheck.php
 *
 * Τρέξ' το ΠΡΙΝ και ΜΕΤΑ από κάθε αναβάθμιση και σύγκρινε τα δύο αποτελέσματα.
 * Δεν αλλάζει τίποτα — μόνο διαβάζει.
 *
 * Έξοδος: 0 αν όλα καλά, 1 αν υπάρχει έστω ένα ΣΦΑΛΜΑ.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
chdir($root);
require $root . '/init.php';
require_once ROOTDIR . '/includes/gatewayfunctions.php';

use WHMCS\Database\Capsule;

$fails = 0;
$warns = 0;

function ok($what, $detail = '')
{
    printf("  \033[32m✓\033[0m %-52s %s\n", $what, $detail);
}
function bad($what, $detail = '')
{
    global $fails;
    $fails++;
    printf("  \033[31m✗ ΣΦΑΛΜΑ\033[0m %-44s %s\n", $what, $detail);
}
function warn($what, $detail = '')
{
    global $warns;
    $warns++;
    printf("  \033[33m! ΠΡΟΣΟΧΗ\033[0m %-44s %s\n", $what, $detail);
}
function section($t)
{
    echo "\n\033[1m── " . $t . "\033[0m\n";
}

echo "\n\033[1mΈλεγχος υγείας CloudOn WHMCS\033[0m — " . date('Y-m-d H:i:s') . "\n";
echo "WHMCS " . (Capsule::table('tblconfiguration')->where('setting', 'Version')->value('value') ?: '?')
    . " · PHP " . PHP_VERSION . "\n";

/* ------------------------------------------------------------------ */
section('Αρχεία που πρέπει να υπάρχουν');

$files = [
    'includes/hooks/viva_reconcile.php'                    => 'Συμφωνία πληρωμών Viva',
    'includes/hooks/paypal_surcharge.php'                  => 'Χρέωση PayPal',
    'includes/hooks/cart_badge.php'                        => 'Μετρητής καλαθιού',
    'modules/gateways/vivapayments.php'                    => 'Gateway Viva',
    'modules/gateways/callback/vivapayments.php'           => 'Callback Viva',
    'modules/gateways/vivapayments/lib/Api.php'            => 'API Viva',
    'modules/gateways/vivapayments/lib/Settle.php'         => 'Εξόφληση Viva',
    'modules/gateways/vivapayments/crons/reconcile.php'    => 'Cron συμφωνίας',
    'lang/overrides/greek.php'                             => 'Ελληνικά κλειδιά',
    'lang/overrides/english.php'                           => 'Αγγλικά κλειδιά',
    'templates/horn/header.tpl'                            => 'Κεφαλίδα horn',
    'templates/horn/assets/layout/menu.tpl'                => 'Μενού horn',
    'templates/horn/css/custom.css'                        => 'Προσαρμογές CSS',
    'remote/index.php'                                     => 'Σελίδα απομακρυσμένης υποστήριξης',
    'projectmanagement/apply.php'                          => 'Δημόσια σελίδα καριέρας',
    'modules/addons/cloudonprojects/lib/JobViews.php'      => 'Επισκεψιμότητα αγγελιών',
];
foreach ($files as $f => $what) {
    is_file($root . '/' . $f) ? ok($what, $f) : bad($what . ' — ΛΕΙΠΕΙ', $f);
}

/* ------------------------------------------------------------------ */
section('Προσαρμογές μέσα στα πρότυπα (χάνονται σε ενημέρωση horn)');

$marks = [
    'templates/horn/header.tpl'             => ['cnp-cart-link',  'Μετρητής καλαθιού στην μπάρα'],
    'templates/horn/assets/layout/menu.tpl' => ['cnp_remote_nav', 'Απομακρυσμένη υποστήριξη στο μενού'],
    'templates/horn/css/custom.css'         => ['cnp-cart-badge', 'CSS μετρητή καλαθιού'],
];
foreach ($marks as $f => [$needle, $what]) {
    $p = $root . '/' . $f;
    if (!is_file($p)) {
        bad($what, "λείπει το $f");
    } elseif (strpos(file_get_contents($p), $needle) !== false) {
        ok($what);
    } else {
        bad($what . ' — ΧΑΘΗΚΕ', $f);
    }
}

$applyMarks = [
    "JobViews::hit"  => 'Καταγραφή προβολών στο apply.php',
    "isset(\$_GET['trk'])" => 'Beacon μέτρησης αγγελιών',
];
$applyPath = $root . '/projectmanagement/apply.php';
if (is_file($applyPath)) {
    $applySrc = file_get_contents($applyPath);
    foreach ($applyMarks as $needle => $what) {
        strpos($applySrc, $needle) !== false ? ok($what) : bad($what . ' — ΧΑΘΗΚΕ', 'projectmanagement/apply.php');
    }
}

/* ------------------------------------------------------------------ */
section('Κλειδιά γλώσσας');

foreach (['greek' => 'cnp_remote_nav', 'english' => 'cnp_remote_nav'] as $lang => $key) {
    $p = $root . "/lang/overrides/$lang.php";
    (is_file($p) && strpos(file_get_contents($p), $key) !== false)
        ? ok("Κλειδί $key ($lang)")
        : bad("Κλειδί $key ($lang) — ΛΕΙΠΕΙ");
}

/* ------------------------------------------------------------------ */
section('Τρόποι πληρωμής');

$gwExpected = ['vivapayments', 'banktransfer', 'paypal'];
foreach ($gwExpected as $gw) {
    $p = getGatewayVariables($gw);
    if (empty($p['type'])) {
        bad("Gateway $gw ανενεργό");
        continue;
    }
    ok("Gateway $gw ενεργό", $p['name'] ?? '');
}

$viva = getGatewayVariables('vivapayments');
foreach (['clientId' => 'Client ID', 'clientSecret' => 'Client Secret',
          'merchantId' => 'Merchant ID', 'apiKey' => 'API key', 'sourceCode' => 'Κωδικός πηγής'] as $k => $label) {
    empty(trim((string) ($viva[$k] ?? '')))
        ? bad("Viva: λείπει $label")
        : ok("Viva: $label", $k === 'sourceCode' ? $viva[$k] : '(ορισμένο)');
}

$bt = getGatewayVariables('banktransfer');
if (empty(trim((string) ($bt['instructions'] ?? '')))) {
    bad('Τραπεζική κατάθεση: ΚΕΝΕΣ οδηγίες — ο πελάτης δεν βλέπει IBAN');
} else {
    ok('Τραπεζική κατάθεση: οδηγίες', strlen($bt['instructions']) . ' χαρακτήρες');
}

/* ------------------------------------------------------------------ */
section('Πίνακες βάσης');

$tables = [
    'mod_viva_orders' => 'Παραγγελίες Viva',
    'mod_viva_tokens' => 'Tokens Viva',
    'mod_viva_log'    => 'Ιστορικό Viva',
    'mod_hetzner_instances' => 'Hetzner',
    'mod_cpm_tasks'   => 'Project Management',
    'mod_cpm_cv_jobs' => 'Αγγελίες θέσεων',
    'mod_cpm_cv_job_views' => 'Επισκεψιμότητα αγγελιών',
];
foreach ($tables as $t => $what) {
    Capsule::schema()->hasTable($t)
        ? ok($what, $t . ' (' . Capsule::table($t)->count() . ')')
        : bad($what . ' — ΛΕΙΠΕΙ ο πίνακας', $t);
}

/* ------------------------------------------------------------------ */
section('Ρυθμίσεις');

$settings = [
    'Template'                      => 'horn',
    'OrderFormTemplate'             => 'horn',
    'Language'                      => 'greek',
    'SequentialInvoiceNumbering'    => '1',
    'SequentialInvoiceNumberFormat' => '{YEAR}{NUMBER}',
    'TaxCustomInvoiceNumberFormat'  => 'PF{YEAR}{NUMBER}',
];
foreach ($settings as $s => $expected) {
    $v = Capsule::table('tblconfiguration')->where('setting', $s)->value('value');
    $v === $expected ? ok("$s = $expected") : bad("$s", "αναμενόταν '$expected', βρέθηκε '" . $v . "'");
}

// Οι μετρητές δεν έχουν «σωστή» τιμή — απλώς δεν πρέπει να πάνε πίσω.
foreach (['SequentialInvoiceNumberValue' => '2026', 'TaxNextCustomInvoiceNumber' => ''] as $s => $prefix) {
    $v = Capsule::table('tblconfiguration')->where('setting', $s)->value('value');
    $maxUsed = $prefix !== ''
        ? (int) substr((string) Capsule::table('tblinvoices')->where('invoicenum', 'like', $prefix . '%')->max('invoicenum'), 4)
        : (int) substr((string) Capsule::table('tblinvoices')->where('invoicenum', 'like', 'PF%')->max('invoicenum'), 6);
    if ((int) $v > $maxUsed) {
        ok("$s = $v", "μεγαλύτερο από το τελευταίο σε χρήση ($maxUsed)");
    } else {
        bad("$s = $v", "ΜΙΚΡΟΤΕΡΟ/ΙΣΟ με το τελευταίο σε χρήση ($maxUsed) — ΚΙΝΔΥΝΟΣ ΔΙΠΛΩΝ ΑΡΙΘΜΩΝ");
    }
}

/* ------------------------------------------------------------------ */
section('Σύνδεση με τη Viva');

if (!empty(trim((string) ($viva['clientId'] ?? '')))) {
    try {
        require_once ROOTDIR . '/modules/gateways/vivapayments.php';
        $api = CloudOn\Viva\Api::fromGatewayParams($viva);
        $tok = $api->accessToken();
        ok('OAuth2 προς Viva', 'token ' . strlen($tok) . ' χαρακτήρων');
        try {
            $api->webhookVerificationBody();
            ok('Κλειδί webhook Viva');
        } catch (Throwable $e) {
            warn('Κλειδί webhook Viva', mb_substr($e->getMessage(), 0, 60));
        }
    } catch (Throwable $e) {
        bad('OAuth2 προς Viva', mb_substr($e->getMessage(), 0, 90));
    }
} else {
    warn('Σύνδεση Viva', 'παραλείφθηκε — λείπουν credentials');
}

/* ------------------------------------------------------------------ */
section('Εκκρεμείς πληρωμές Viva');

if (Capsule::schema()->hasTable('mod_viva_orders')) {
    $stuck = Capsule::table('mod_viva_orders')->where('status', 'pending')
        ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-2 hours')))->count();
    $stuck === 0
        ? ok('Καμία κολλημένη παραγγελία')
        : warn("$stuck παραγγελίες εκκρεμείς πάνω από 2 ώρες", 'τρέξε το reconcile.php');
}

/* ------------------------------------------------------------------ */
section('Cron');

$cronLast = Capsule::table('tblconfiguration')->where('setting', 'lastCronInvocationTime')->value('value');
if ($cronLast) {
    $age = time() - strtotime($cronLast);
    $age < 1800
        ? ok('Cron έτρεξε πρόσφατα', round($age / 60) . ' λεπτά πριν (' . $cronLast . ')')
        : bad('Cron', 'τελευταία εκτέλεση ' . $cronLast . ' — πριν ' . round($age / 3600, 1) . ' ώρες');
} else {
    warn('Cron', 'δεν βρέθηκε lastCronInvocationTime');
}

$cronPhp = Capsule::table('tblconfiguration')->where('setting', 'CronPHPVersion')->value('value');
if ($cronPhp) {
    version_compare($cronPhp, '8.1', '>=')
        ? ok('Έκδοση PHP του cron', $cronPhp)
        : bad('Έκδοση PHP του cron', $cronPhp . ' — πολύ παλιά');
}

/* ------------------------------------------------------------------ */
echo "\n" . str_repeat('─', 72) . "\n";
if ($fails === 0 && $warns === 0) {
    echo "\033[32mΌλα εντάξει.\033[0m\n\n";
} else {
    printf("\033[%sm%d σφάλματα\033[0m, %d προειδοποιήσεις\n\n", $fails ? '31' : '32', $fails, $warns);
}

exit($fails > 0 ? 1 : 0);
