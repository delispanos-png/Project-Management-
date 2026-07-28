<?php
/**
 * Scaleway — WHMCS addon module.
 *
 * Διαχείριση credentials/projects, συγχρονισμός τιμών από τον κατάλογο
 * Scaleway, import ήδη πουλημένων υπηρεσιών και εποπτεία στόλου.
 * Συνοδεύει το provisioning module "scaleway".
 *
 * @package WHMCS\Module\Addon\Scaleway
 * @author  Cloudon
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Scaleway\Db;
use WHMCS\Module\Addon\Scaleway\Sync;
use WHMCS\Module\Server\Scaleway\Api;
use WHMCS\Module\Server\Scaleway\ApiException;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Db.php';
require_once __DIR__ . '/lib/Sync.php';

function scaleway_config()
{
    $zones = [];
    foreach (Api::ZONES as $z => $label) {
        $zones[$z] = $label;
    }

    return [
        'name'        => 'Scaleway Cloud',
        'description' => 'Credentials/projects, συγχρονισμός τιμών, import υπηρεσιών και εποπτεία στόλου για το provisioning module «scaleway».',
        'author'      => 'Cloudon',
        'language'    => 'greek',
        'version'     => '1.0',
        'fields'      => [
            'brand_name' => [
                'FriendlyName' => 'Ονομασία brand',
                'Type'         => 'text',
                'Size'         => '30',
                'Default'      => 'Cloud Server',
                'Description'  => 'Τι βλέπει ο πελάτης. Η λέξη «Scaleway» δεν εμφανίζεται ποτέ.',
            ],
            'secret_key' => [
                'FriendlyName' => 'Secret Key (καθολικό)',
                'Type'         => 'password',
                'Size'         => '45',
                'Description'  => 'Scaleway API secret key. Για πολλαπλά projects χρησιμοποίησε την καρτέλα «Projects».',
            ],
            'project_id' => [
                'FriendlyName' => 'Project ID (καθολικό)',
                'Type'         => 'text',
                'Size'         => '45',
                'Description'  => 'Το UUID του Scaleway project.',
            ],
            'default_zone' => [
                'FriendlyName' => 'Προεπιλεγμένη zone',
                'Type'         => 'dropdown',
                'Options'      => $zones,
                'Default'      => 'fr-par-1',
            ],
            'markup' => [
                'FriendlyName' => '➤ ΤΟ ΚΕΡΔΟΣ ΣΟΥ: περιθώριο %',
                'Type'         => 'text',
                'Size'         => '6',
                'Default'      => '30',
                'Description'  => 'Πόσο % πάνω από το κόστος του παρόχου χρεώνεις. Εφαρμόζεται στο <b>σύνολο</b> (instance + IPv4 + δίσκος). Π.χ. κόστος 10 € + 30% → τιμή πώλησης 13 €.',
            ],
            'rounding' => [
                'FriendlyName' => 'Στρογγυλοποίηση',
                'Type'         => 'dropdown',
                'Options'      => [
                    'none'  => 'Καμία (2 δεκαδικά)',
                    'half'  => 'Στο πλησιέστερο 0,50',
                    'int'   => 'Στο επόμενο ακέραιο',
                    'up99'  => 'X,99',
                ],
                'Default'      => 'none',
            ],
            'cycle' => [
                'FriendlyName' => 'Κύκλος χρέωσης',
                'Type'         => 'dropdown',
                'Options'      => [
                    'monthly' => 'Μηνιαίος', 'quarterly' => 'Τριμηνιαίος',
                    'semiannually' => 'Εξαμηνιαίος', 'annually' => 'Ετήσιος',
                ],
                'Default'      => 'monthly',
            ],
            'currency' => [
                'FriendlyName' => 'Currency ID',
                'Type'         => 'text',
                'Size'         => '4',
                'Default'      => '1',
                'Description'  => 'Το ID νομίσματος του WHMCS στο οποίο γράφονται οι τιμές.',
            ],
            'ipv4_monthly' => [
                'FriendlyName' => 'ΚΟΣΤΟΣ παρόχου: IPv4 / μήνα (€)',
                'Type'         => 'text',
                'Size'         => '8',
                'Default'      => '2.92',
                'Description'  => 'Τι <b>σου χρεώνει η Scaleway</b> (ΟΧΙ τι χρεώνεις εσύ). Flexible IP ≈ 0,004 €/ώρα × 730 = <b>2,92 €</b>. Το API δεν το επιστρέφει, γι\' αυτό δηλώνεται εδώ.',
            ],
            'block_gb_monthly' => [
                'FriendlyName' => 'ΚΟΣΤΟΣ παρόχου: block storage / GB / μήνα (€)',
                'Type'         => 'text',
                'Size'         => '8',
                'Default'      => '0.086',
                'Description'  => 'Τι <b>σου χρεώνει η Scaleway</b> ανά GB. Τρέχουσα τιμή SSD block storage ≈ <b>0,086 €</b>. Χρεώνεται μόνο ο δίσκος πέρα από τον περιλαμβανόμενο του τύπου.',
            ],
            'scan_all_zones' => [
                'FriendlyName' => 'Σάρωση όλων των zones',
                'Type'         => 'yesno',
                'Description'  => 'Ο στόλος/import σαρώνει όλες τις zones (πιο αργό, πιο πλήρες).',
            ],
        ],
    ];
}

function scaleway_activate()
{
    try {
        Db::install();
        return ['status' => 'success', 'description' => 'Το module Scaleway ενεργοποιήθηκε.'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Αποτυχία δημιουργίας πινάκων: ' . $e->getMessage()];
    }
}

function scaleway_deactivate()
{
    return ['status' => 'success', 'description' => 'Απενεργοποιήθηκε. Τα δεδομένα διατηρήθηκαν.'];
}

function scaleway_output($vars)
{
    $sync = new Sync($vars);
    $link = $vars['modulelink'];
    $tab = $_GET['tab'] ?? 'projects';

    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $msg = scaleway_handlePost($sync, $_POST);
    }

    $tabs = [
        'projects' => '🔑 Projects',
        'pricing'  => '💶 Τιμές',
        'import'   => '📥 Import',
        'fleet'    => '🖥 Στόλος',
        'logs'     => '📜 Ιστορικό',
    ];

    echo '<ul class="nav nav-tabs">';
    foreach ($tabs as $k => $label) {
        $active = $k === $tab ? ' class="active"' : '';
        echo '<li' . $active . '><a href="' . $link . '&tab=' . $k . '">' . $label . '</a></li>';
    }
    echo '</ul><div style="padding:16px 4px">';

    if ($msg) {
        echo $msg;
    }

    switch ($tab) {
        case 'pricing': scaleway_tabPricing($sync, $link); break;
        case 'import':  scaleway_tabImport($sync, $link);  break;
        case 'fleet':   scaleway_tabFleet($sync, $link);   break;
        case 'logs':    scaleway_tabLogs();                break;
        default:        scaleway_tabProjects($sync, $link);
    }
    echo '</div>';
}

/* ─────────────────────────── POST router ─────────────────────────── */

function scaleway_handlePost(Sync $sync, array $post)
{
    $ok = function ($t) { return '<div class="alert alert-success">' . $t . '</div>'; };
    $err = function ($t) { return '<div class="alert alert-danger">' . $t . '</div>'; };

    try {
        switch ($post['scw_do'] ?? '') {

            case 'project_save':
                $id = (int) ($post['id'] ?? 0);
                if (trim((string) ($post['name'] ?? '')) === '') {
                    return $err('Δώσε ονομασία project.');
                }
                if (!$id && trim((string) ($post['secret_key'] ?? '')) === '') {
                    return $err('Δώσε secret key.');
                }
                Db::saveProject($post, $id);
                Db::log('Αποθήκευση project «' . $post['name'] . '».');
                return $ok('Το project αποθηκεύτηκε.');

            case 'project_delete':
                $e = Db::deleteProject((int) ($post['id'] ?? 0));
                return $e ? $err($e) : $ok('Το project διαγράφηκε.');

            case 'project_test':
                $row = Db::project((int) ($post['id'] ?? 0));
                if (!$row) {
                    return $err('Το project δεν βρέθηκε.');
                }
                $api = new Api(Db::projectSecret($row), $row->project_id, $row->zone);
                $res = $api->testConnection();
                return $res['success']
                    ? $ok('Σύνδεση OK — ο κατάλογος της zone ' . $row->zone . ' απαντά.')
                    : $err('Αποτυχία: ' . $res['error']);

            case 'test_global':
                $api = new Api(Api::normalizeSecret($sync->setting('secret_key')),
                    $sync->setting('project_id'), $sync->setting('default_zone', 'fr-par-1'));
                $res = $api->testConnection();
                return $res['success']
                    ? $ok('Σύνδεση OK με τις καθολικές ρυθμίσεις.')
                    : $err('Αποτυχία: ' . $res['error']);

            case 'map_save':
                $pid = (int) ($post['whmcs_pid'] ?? 0);
                if (!$pid) {
                    return $err('Επίλεξε προϊόν.');
                }
                Db::saveMapping($pid, $post);
                return $ok('Η αντιστοίχιση αποθηκεύτηκε.');

            case 'map_delete':
                Db::deleteMapping((int) ($post['whmcs_pid'] ?? 0));
                return $ok('Η αντιστοίχιση διαγράφηκε.');

            case 'price_sync':
                $rows = $sync->run(true);
                return $ok('Ενημερώθηκαν ' . count($rows) . ' προϊόντα.');

            case 'link':
                $e = $sync->linkService(
                    (int) ($post['service_id'] ?? 0),
                    (string) ($post['server_id'] ?? ''),
                    (int) ($post['project_id'] ?? 0),
                    (string) ($post['zone'] ?? '')
                );
                return $e ? $err($e) : $ok('Η υπηρεσία συνδέθηκε.');
        }
    } catch (ApiException $e) {
        return $err('Σφάλμα Scaleway: ' . $e->getMessage());
    } catch (\Throwable $e) {
        return $err('Σφάλμα: ' . $e->getMessage());
    }
    return '';
}

/* ─────────────────────────── Tab: Projects ─────────────────────────── */

function scaleway_tabProjects(Sync $sync, $link)
{
    $projects = Db::projects();
    ?>
    <p>Κάθε <strong>project</strong> είναι ένα ξεχωριστό Scaleway project με δικά του credentials.
       Ένα ορίζεται ως <em>πρωτεύον</em> και εκεί δημιουργούνται τα νέα VM (εκτός αν το προϊόν έχει override).</p>

    <form method="post" style="margin-bottom:14px">
        <input type="hidden" name="scw_do" value="test_global">
        <button class="btn btn-default">🔌 Έλεγχος καθολικών ρυθμίσεων</button>
    </form>

    <table class="datatable" width="100%">
        <thead><tr><th>Ονομασία</th><th>Project ID</th><th>Zone</th><th>Πρωτεύον</th><th>Ενεργό</th><th>Υπηρεσίες</th><th></th></tr></thead>
        <tbody>
        <?php if (!count($projects)): ?>
            <tr><td colspan="7">Δεν έχει καταχωρηθεί project — χρησιμοποιούνται οι καθολικές ρυθμίσεις.</td></tr>
        <?php endif; ?>
        <?php foreach ($projects as $p): ?>
            <?php $used = Capsule::table(Db::T_INSTANCES)->where('project_id', $p->id)->count(); ?>
            <tr>
                <td><?= htmlspecialchars($p->name) ?></td>
                <td><code><?= htmlspecialchars($p->project_id) ?></code></td>
                <td><?= htmlspecialchars($p->zone) ?></td>
                <td><?= $p->is_primary ? '★' : '' ?></td>
                <td><?= $p->enabled ? 'ναι' : 'όχι' ?></td>
                <td><?= (int) $used ?></td>
                <td style="white-space:nowrap">
                    <form method="post" style="display:inline">
                        <input type="hidden" name="scw_do" value="project_test">
                        <input type="hidden" name="id" value="<?= (int) $p->id ?>">
                        <button class="btn btn-xs btn-default">Test</button>
                    </form>
                    <form method="post" style="display:inline" onsubmit="return confirm('Διαγραφή project;')">
                        <input type="hidden" name="scw_do" value="project_delete">
                        <input type="hidden" name="id" value="<?= (int) $p->id ?>">
                        <button class="btn btn-xs btn-danger">Διαγραφή</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3 style="margin-top:22px">Προσθήκη / ενημέρωση project</h3>
    <form method="post" class="form-horizontal">
        <input type="hidden" name="scw_do" value="project_save">
        <table class="form">
            <tr><td width="180">ID (κενό = νέο)</td><td><input type="text" name="id" size="5"></td></tr>
            <tr><td>Ονομασία</td><td><input type="text" name="name" size="30" required></td></tr>
            <tr><td>Secret Key</td><td><input type="password" name="secret_key" size="45" autocomplete="new-password">
                <br><small>Κενό σε ενημέρωση = διατήρηση του υπάρχοντος.</small></td></tr>
            <tr><td>Project ID (UUID)</td><td><input type="text" name="project_id" size="45"></td></tr>
            <tr><td>Zone</td><td><select name="zone">
                <?php foreach (Api::ZONES as $z => $lbl): ?>
                    <option value="<?= $z ?>"><?= htmlspecialchars($lbl) ?> (<?= $z ?>)</option>
                <?php endforeach; ?>
            </select></td></tr>
            <tr><td>Πρωτεύον</td><td><input type="checkbox" name="is_primary" value="1"></td></tr>
            <tr><td>Ενεργό</td><td><input type="checkbox" name="enabled" value="1" checked></td></tr>
        </table>
        <button class="btn btn-primary">Αποθήκευση</button>
    </form>
    <?php
}

/* ─────────────────────────── Tab: Τιμές ─────────────────────────── */

function scaleway_tabPricing(Sync $sync, $link)
{
    $zone = $_GET['zone'] ?? $sync->setting('default_zone', 'fr-par-1');
    $cat = $sync->catalogue($zone);
    $products = Capsule::table('tblproducts')->where('servertype', 'scaleway')->orderBy('name')->get();
    $rows = $sync->run(false);
    ?>
    <p>Το κόστος υπολογίζεται από την ωριαία τιμή του τύπου × <?= Sync::HOURS_PER_MONTH ?> ώρες,
       συν IPv4 και τυχόν επιπλέον block storage. Η τιμή πώλησης προκύπτει με το περιθώριο κέρδους.</p>

    <form method="post" style="margin-bottom:14px">
        <input type="hidden" name="scw_do" value="price_sync">
        <button class="btn btn-primary">💶 Συγχρονισμός τιμών τώρα</button>
        <span style="margin-left:10px">Περιθώριο: <strong><?= htmlspecialchars($sync->defaultMarkup()) ?>%</strong></span>
    </form>

    <table class="datatable" width="100%">
        <thead><tr><th>Προϊόν</th><th>Τύπος</th><th>Κόστος/μήνα</th><th>Τιμή πώλησης</th><th>Κατάσταση</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="5">Δεν υπάρχουν αντιστοιχίσεις.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td>#<?= (int) $r['pid'] ?> — <?= htmlspecialchars($r['name']) ?></td>
                <td><code><?= htmlspecialchars($r['type']) ?></code></td>
                <td><?= $r['cost'] === null ? '—' : number_format($r['cost'], 2) . ' €' ?></td>
                <td><?= $r['price'] === null ? '—' : '<strong>' . number_format($r['price'], 2) . ' €</strong>' ?></td>
                <td><?= htmlspecialchars($r['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3 style="margin-top:22px">Αντιστοίχιση προϊόντος</h3>
    <form method="post">
        <input type="hidden" name="scw_do" value="map_save">
        <table class="form">
            <tr><td width="180">Προϊόν WHMCS</td><td>
                <select name="whmcs_pid">
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int) $p->id ?>">#<?= (int) $p->id ?> — <?= htmlspecialchars($p->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!count($products)): ?>
                    <br><small>Δεν υπάρχει προϊόν με module «scaleway».</small>
                <?php endif; ?>
            </td></tr>
            <tr><td>Τύπος instance</td><td>
                <select name="commercial_type">
                    <?php foreach ($cat['types'] as $name => $t): ?>
                        <?php
                        $ram = isset($t['ram']) ? round($t['ram'] / 1073741824) : 0;
                        $hourly = (float) ($t['hourly_price'] ?? 0);
                        ?>
                        <option value="<?= htmlspecialchars($name) ?>">
                            <?= strtoupper($name) ?> — <?= (int) ($t['ncpus'] ?? 0) ?> vCPU / <?= $ram ?> GB
                            (<?= number_format($hourly * Sync::HOURS_PER_MONTH, 2) ?> €/μήνα)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$cat['types']): ?><br><small>Δεν φορτώθηκε κατάλογος — έλεγξε τα credentials.</small><?php endif; ?>
            </td></tr>
            <tr><td>Zone</td><td><select name="zone">
                <?php foreach (Api::ZONES as $z => $lbl): ?>
                    <option value="<?= $z ?>" <?= $z === $zone ? 'selected' : '' ?>><?= $z ?></option>
                <?php endforeach; ?>
            </select></td></tr>
            <tr><td>Περιλαμβάνει IPv4</td><td><input type="checkbox" name="include_ipv4" value="1" checked></td></tr>
            <tr><td>Δίσκος (GB)</td><td><input type="text" name="disk_gb" size="6" value="0">
                <small>0 = προεπιλογή τύπου</small></td></tr>
            <tr><td>Περιθώριο % (override)</td><td><input type="text" name="markup" size="6" placeholder="κενό = προεπιλογή"></td></tr>
        </table>
        <button class="btn btn-primary">Αποθήκευση αντιστοίχισης</button>
    </form>
    <?php
}

/* ─────────────────────────── Tab: Import ─────────────────────────── */

function scaleway_tabImport(Sync $sync, $link)
{
    $cands = $sync->importCandidates();
    ?>
    <p>Υπηρεσίες WHMCS με module «scaleway» που δεν είναι ακόμη συνδεδεμένες με instance.
       Όπου βρεθεί instance με όνομα <code>whmcs-&lt;serviceid&gt;</code> ή ίδια IP, προτείνεται αυτόματα.</p>
    <table class="datatable" width="100%">
        <thead><tr><th>Υπηρεσία</th><th>Domain</th><th>Κατάσταση</th><th>IP</th><th>Πρόταση</th><th></th></tr></thead>
        <tbody>
        <?php if (!$cands): ?><tr><td colspan="6">Καμία εκκρεμότητα — όλα συνδεδεμένα.</td></tr><?php endif; ?>
        <?php foreach ($cands as $c): ?>
            <tr>
                <td>#<?= (int) $c['service_id'] ?></td>
                <td><?= htmlspecialchars((string) $c['domain']) ?></td>
                <td><?= htmlspecialchars((string) $c['status']) ?></td>
                <td><?= htmlspecialchars((string) $c['ip']) ?></td>
                <td>
                    <?php if ($c['match']): ?>
                        <code><?= htmlspecialchars($c['match']['name']) ?></code>
                        <small>(<?= htmlspecialchars($c['match']['zone']) ?> · <?= htmlspecialchars($c['match']['type']) ?>)</small>
                    <?php else: ?>
                        <span class="text-muted">δεν βρέθηκε</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($c['match']): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="scw_do" value="link">
                        <input type="hidden" name="service_id" value="<?= (int) $c['service_id'] ?>">
                        <input type="hidden" name="server_id" value="<?= htmlspecialchars($c['match']['id']) ?>">
                        <input type="hidden" name="project_id" value="<?= (int) $c['match']['project_id'] ?>">
                        <input type="hidden" name="zone" value="<?= htmlspecialchars($c['match']['zone']) ?>">
                        <button class="btn btn-xs btn-primary">Σύνδεση</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/* ─────────────────────────── Tab: Στόλος ─────────────────────────── */

function scaleway_tabFleet(Sync $sync, $link)
{
    $fleet = $sync->fleet();
    $rec = $sync->reconcile();
    ?>
    <p>Σύνολο instances: <strong><?= count($fleet) ?></strong>
       · Πιθανά ορφανά: <strong><?= count($rec['orphans']) ?></strong>
       · Υπηρεσίες χωρίς VM: <strong><?= count($rec['missing']) ?></strong></p>
    <table class="datatable" width="100%">
        <thead><tr><th>Project</th><th>Zone</th><th>Όνομα</th><th>Τύπος</th><th>Κατάσταση</th><th>IP</th><th>Υπηρεσία</th></tr></thead>
        <tbody>
        <?php if (!$fleet): ?><tr><td colspan="7">Δεν βρέθηκαν instances.</td></tr><?php endif; ?>
        <?php foreach ($fleet as $f): ?>
            <tr>
                <td><?= htmlspecialchars($f['project']) ?></td>
                <td><?= htmlspecialchars($f['zone']) ?></td>
                <td><code><?= htmlspecialchars($f['name']) ?></code></td>
                <td><?= htmlspecialchars($f['type']) ?></td>
                <td><?= htmlspecialchars($f['state']) ?></td>
                <td><?= htmlspecialchars((string) $f['ip']) ?></td>
                <td><?= $f['service_id'] ? ('#' . $f['service_id']) : '<span class="text-muted">—</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/* ─────────────────────────── Tab: Ιστορικό ─────────────────────────── */

function scaleway_tabLogs()
{
    ?>
    <table class="datatable" width="100%">
        <thead><tr><th width="150">Ώρα</th><th width="80">Επίπεδο</th><th>Μήνυμα</th></tr></thead>
        <tbody>
        <?php $logs = Db::logs(200); ?>
        <?php if (!count($logs)): ?><tr><td colspan="3">Κενό ιστορικό.</td></tr><?php endif; ?>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= htmlspecialchars($l->ts) ?></td>
                <td><?= htmlspecialchars($l->level) ?></td>
                <td><?= htmlspecialchars($l->message) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
