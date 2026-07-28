<?php
/**
 * Scaleway — client area control panel (white-label).
 *
 * Ο πελάτης δεν βλέπει ποτέ τη λέξη "Scaleway". Λειτουργίες που υποστηρίζονται
 * αξιόπιστα από το Instance API:
 *   • power on / soft stop / reboot
 *   • reverse DNS στη δημόσια IP
 *   • snapshots (δημιουργία / διαγραφή)
 *   • resize (αλλαγή τύπου, με σβήσιμο-άναμμα)
 *   • επανεγκατάσταση OS (recreate διατηρώντας τη δημόσια IP) με νέο κωδικό root
 *
 * Δεν υλοποιούνται (δεν τα προσφέρει το API): web console, rescue mode,
 * reset κωδικού χωρίς επανεγκατάσταση.
 *
 * @package WHMCS\Module\Server\Scaleway
 */

use WHMCS\Module\Server\Scaleway\Api;
use WHMCS\Module\Server\Scaleway\ApiException;
use WHMCS\Module\Server\Scaleway\Helper;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function scaleway_ClientAreaCustomButtonArray(array $params)
{
    return [
        'Εκκίνηση'            => 'clientBoot',
        'Τερματισμός (ήπιος)' => 'clientShutdown',
        'Επανεκκίνηση'        => 'clientReboot',
    ];
}

function scaleway_clientBoot(array $params)     { return scaleway_simpleAction($params, 'powerOn'); }
function scaleway_clientShutdown(array $params) { return scaleway_simpleAction($params, 'stopInPlace'); }
function scaleway_clientReboot(array $params)   { return scaleway_simpleAction($params, 'reboot'); }

/** Κύριος renderer + router ενεργειών. */
function scaleway_ClientArea(array $params)
{
    $id = Helper::serverId($params);
    $brand = Helper::brand($params);

    try {
        $api = Helper::api($params);
    } catch (\Throwable $e) {
        return scaleway_caError($brand, 'Η υπηρεσία δεν είναι προσωρινά διαθέσιμη.');
    }

    // AJAX (JSON) — ελαφρύ polling κατάστασης
    if (isset($_GET['scwajax'])) {
        scaleway_caAjax($api, $id, $params);   // πάντα κάνει exit
    }

    $notice = '';
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['scwaction'])) {
        list($notice, $error) = scaleway_caHandlePost($api, $id, $params);
        $id = Helper::serverId($params);   // μπορεί να άλλαξε (rebuild)
    }

    if (!$id) {
        return scaleway_caError($brand, 'Ο διακομιστής προετοιμάζεται. Δοκίμασε ξανά σε λίγο.');
    }

    try {
        $srv = $api->getServer($id);
    } catch (ApiException $e) {
        return scaleway_caError($brand, 'Δεν ήταν δυνατή η ανάκτηση της κατάστασης.');
    }
    if (!$srv) {
        return scaleway_caError($brand, 'Ο διακομιστής δεν βρέθηκε.');
    }

    $ipv4 = Helper::primaryIp($srv) ?: '';
    $ipv6 = '';
    foreach ($srv['public_ips'] ?? [] as $ip) {
        if (($ip['family'] ?? '') === 'inet6' && !empty($ip['address'])) {
            $ipv6 = $ip['address'];
            break;
        }
    }

    // Reverse DNS + id της κύριας IP (για το rDNS form)
    $ptr = '';
    $primaryIpId = '';
    foreach ($srv['public_ips'] ?? [] as $ip) {
        if (($ip['address'] ?? '') === $ipv4) {
            $ptr = $ip['reverse'] ?? '';
            $primaryIpId = $ip['id'] ?? '';
            break;
        }
    }

    // Snapshots αυτής της υπηρεσίας
    $snapshots = [];
    try {
        foreach ($api->listSnapshots() as $sn) {
            if (in_array('whmcs_service=' . (int) $params['serviceid'], $sn['tags'] ?? [], true)) {
                $snapshots[] = [
                    'id'      => $sn['id'],
                    'name'    => $sn['name'] ?? '',
                    'size'    => isset($sn['size']) ? round($sn['size'] / 1000000000, 1) : null,
                    'state'   => $sn['state'] ?? '',
                    'created' => substr((string) ($sn['creation_date'] ?? ''), 0, 16),
                ];
            }
        }
    } catch (ApiException $e) {
        // μη κρίσιμο
    }

    $allowRebuild = ($params['configoption7'] === 'on');
    $allowResize = ($params['configoption8'] === 'on');

    $images = [];
    if ($allowRebuild) {
        try {
            $images = $api->marketplaceImages();
        } catch (ApiException $e) {
            $images = [];
        }
    }

    $resizeOptions = $allowResize ? scaleway_resizeTargets($api, $srv) : [];

    $ram = isset($srv['commercial_type']) ? null : null;
    $typeInfo = [];
    try {
        $types = $api->serverTypes();
        $t = $types[$srv['commercial_type'] ?? ''] ?? null;
        if ($t) {
            $typeInfo = [
                'cores'  => (int) ($t['ncpus'] ?? 0),
                'memory' => isset($t['ram']) ? round($t['ram'] / 1073741824) : 0,
            ];
        }
    } catch (ApiException $e) {
        // μη κρίσιμο
    }

    $diskGb = 0;
    foreach ($srv['volumes'] ?? [] as $v) {
        $diskGb += isset($v['size']) ? round($v['size'] / 1000000000) : 0;
    }

    return [
        'templatefile' => 'overview',
        'vars' => [
            'brand'         => $brand,
            'notice'        => $notice,
            'error'         => $error,
            'serviceId'     => (int) $params['serviceid'],
            'status'        => $srv['state'] ?? 'unknown',
            'serverName'    => $params['domain'] ?: ('Server #' . $params['serviceid']),
            'typeLabel'     => strtoupper($srv['commercial_type'] ?? ''),
            'cores'         => $typeInfo['cores'] ?? '',
            'memory'        => $typeInfo['memory'] ?? '',
            'disk'          => $diskGb ?: '',
            'location'      => Api::ZONES[$srv['zone'] ?? ''] ?? ($srv['zone'] ?? ''),
            'os'            => $srv['image']['name'] ?? '',
            'ipv4'          => $ipv4,
            'ipv6'          => $ipv6,
            'ptr'           => $ptr,
            'primaryIpId'   => $primaryIpId,
            'snapshots'     => $snapshots,
            'images'        => $images,
            'allowRebuild'  => $allowRebuild,
            'allowResize'   => $allowResize,
            'resizeOptions' => $resizeOptions,
        ],
    ];
}

/** Τύποι στους οποίους επιτρέπεται resize (ίδια αρχιτεκτονική, διαθέσιμοι). */
function scaleway_resizeTargets(Api $api, array $srv)
{
    $out = [];
    try {
        $types = $api->serverTypes();
        $avail = $api->serverAvailability();
        $cur = $types[$srv['commercial_type'] ?? ''] ?? null;
        $curArch = $cur['arch'] ?? null;
        foreach ($types as $name => $t) {
            if ($curArch && ($t['arch'] ?? null) !== $curArch) {
                continue;
            }
            if (($avail[$name]['availability'] ?? '') === 'shortage') {
                continue;
            }
            $ram = isset($t['ram']) ? round($t['ram'] / 1073741824) : 0;
            $out[$name] = strtoupper($name) . ' — ' . (int) ($t['ncpus'] ?? 0) . ' vCPU / ' . $ram . ' GB';
        }
    } catch (ApiException $e) {
        // μη κρίσιμο
    }
    return $out;
}

/** @return array [notice, error] */
function scaleway_caHandlePost(Api $api, $id, array $params)
{
    $notice = '';
    $error = '';
    $serviceId = (int) $params['serviceid'];

    try {
        switch ($_POST['scwaction']) {

            case 'power':
                if (!$id) { break; }
                $op = $_POST['op'] ?? '';
                if ($op === 'on')       { $api->powerOn($id);     $notice = 'Ο διακομιστής ξεκινά…'; }
                elseif ($op === 'off')  { $api->stopInPlace($id); $notice = 'Ο διακομιστής τερματίζεται…'; }
                elseif ($op === 'reboot') { $api->reboot($id);    $notice = 'Επανεκκίνηση σε εξέλιξη…'; }
                elseif ($op === 'hardoff') { $api->powerOff($id); $notice = 'Αναγκαστικός τερματισμός…'; }
                break;

            case 'rdns':
                if ($params['configoption4'] === 'off') { break; }
                $ipId = preg_replace('/[^0-9a-f\-]/i', '', (string) ($_POST['ip_id'] ?? ''));
                $ptr = trim((string) ($_POST['ptr'] ?? ''));
                if ($ipId === '') { $error = 'Δεν βρέθηκε η IP.'; break; }
                if ($ptr !== '' && !preg_match('/^[a-z0-9.\-]+$/i', $ptr)) {
                    $error = 'Μη έγκυρο όνομα (επιτρέπονται γράμματα, αριθμοί, τελείες, παύλες).';
                    break;
                }
                $api->setIpReverse($ipId, $ptr === '' ? null : $ptr);
                $notice = $ptr === '' ? 'Το reverse DNS καθαρίστηκε.' : 'Το reverse DNS ενημερώθηκε.';
                break;

            case 'snapshot':
                if (!$id) { break; }
                $srv = $api->getServer($id);
                $bootVol = null;
                foreach ($srv['volumes'] ?? [] as $v) {
                    if (!empty($v['id'])) { $bootVol = $v['id']; break; }
                }
                if (!$bootVol) { $error = 'Δεν βρέθηκε δίσκος.'; break; }
                $name = trim((string) ($_POST['name'] ?? '')) ?: ('snap-' . date('Ymd-Hi'));
                $api->createSnapshot($bootVol, mb_substr($name, 0, 60), ['whmcs_service=' . $serviceId]);
                $notice = 'Το στιγμιότυπο δημιουργείται…';
                break;

            case 'snapshot_delete':
                $snapId = preg_replace('/[^0-9a-f\-]/i', '', (string) ($_POST['snapshot_id'] ?? ''));
                if ($snapId === '') { break; }
                if (!scaleway_ownsSnapshot($api, $params, $snapId)) {
                    $error = 'Μη έγκυρο στιγμιότυπο.'; break;
                }
                $api->deleteSnapshot($snapId);
                $notice = 'Το στιγμιότυπο διαγράφηκε.';
                break;

            case 'resize':
                if ($params['configoption8'] !== 'on' || !$id) { break; }
                $target = trim((string) ($_POST['type'] ?? ''));
                $allowed = scaleway_resizeTargets($api, $api->getServer($id) ?: []);
                if ($target === '' || !isset($allowed[$target])) {
                    $error = 'Μη έγκυρη επιλογή.'; break;
                }
                $srv = $api->getServer($id);
                $wasRunning = ($srv['state'] ?? '') === 'running';
                if ($wasRunning) {
                    $api->powerOff($id);
                    for ($i = 0; $i < 30; $i++) {
                        sleep(2);
                        $s = $api->getServer($id);
                        if (($s['state'] ?? '') === 'stopped') { break; }
                    }
                }
                $api->changeCommercialType($id, $target);
                if ($wasRunning) {
                    try { $api->powerOn($id); } catch (ApiException $e) { /* μη κρίσιμο */ }
                }
                $notice = 'Η αναβάθμιση ολοκληρώθηκε.';
                break;

            case 'rebuild':
                if ($params['configoption7'] !== 'on' || !$id) { break; }
                $image = trim((string) ($_POST['image'] ?? ''));
                if ($image === '') { $error = 'Επίλεξε λειτουργικό σύστημα.'; break; }
                if (stripos($image, 'windows') !== false) {
                    $error = 'Τα Windows δεν είναι διαθέσιμα.'; break;
                }
                $res = scaleway_rebuild($api, $id, $image, $params);
                if ($res['ok']) {
                    $notice = 'Η επανεγκατάσταση ξεκίνησε. Ο νέος κωδικός root στάλθηκε στα στοιχεία της υπηρεσίας.';
                } else {
                    $error = $res['error'];
                }
                break;
        }
    } catch (ApiException $e) {
        $error = $e->getMessage();
    }

    return [$notice, $error];
}

/**
 * Επανεγκατάσταση OS.
 *
 * Το Scaleway δεν έχει endpoint "rebuild": ο μόνος αξιόπιστος τρόπος είναι
 * αναδημιουργία του instance. Κρατάμε τη δημόσια IP (την αποσυνδέουμε πριν τον
 * τερματισμό ώστε να μη διαγραφεί) και την επανασυνδέουμε στο νέο instance.
 */
function scaleway_rebuild(Api $api, $id, $image, array $params)
{
    $serviceId = (int) $params['serviceid'];
    $srv = $api->getServer($id);
    if (!$srv) {
        return ['ok' => false, 'error' => 'Ο διακομιστής δεν βρέθηκε.'];
    }

    $commercialType = $srv['commercial_type'] ?? $params['configoption1'];
    $name = $srv['name'] ?? Helper::remoteServerName($params);

    // 1. Κράτα τη δημόσια IPv4 (αποσύνδεση ώστε να επιβιώσει του terminate)
    $keepIpId = '';
    foreach ($srv['public_ips'] ?? [] as $ip) {
        if (($ip['family'] ?? 'inet') === 'inet' && !empty($ip['id'])) {
            $keepIpId = $ip['id'];
            break;
        }
    }
    if ($keepIpId) {
        try { $api->detachIp($keepIpId); } catch (ApiException $e) { $keepIpId = ''; }
    }

    // 2. Τερματισμός του παλιού instance (μαζί με τα volumes του)
    try {
        $api->terminate($id);
    } catch (ApiException $e) {
        try { $api->powerOff($id); } catch (ApiException $e2) { /* ignore */ }
        for ($i = 0; $i < 30; $i++) {
            sleep(2);
            $s = $api->getServer($id);
            if (!$s || ($s['state'] ?? '') === 'stopped') { break; }
        }
        try { $api->terminate($id); } catch (ApiException $e3) {
            return ['ok' => false, 'error' => 'Αποτυχία τερματισμού: ' . $e3->getMessage()];
        }
    }
    // περίμενε να εξαφανιστεί
    for ($i = 0; $i < 30; $i++) {
        sleep(2);
        if (!$api->getServer($id)) { break; }
    }

    // 3. Νέο instance ίδιου τύπου/ονόματος
    $data = [
        'name'            => $name,
        'commercial_type' => $commercialType,
        'image'           => $image,
        'enable_ipv6'     => true,
        'routed_ip_enabled' => true,
        'tags'            => ['whmcs_service=' . $serviceId, 'whmcs_client=' . (int) ($params['userid'] ?? 0)],
    ];
    $diskGb = (int) preg_replace('/\D/', '', (string) ($params['configoption5'] ?? ''));
    if ($diskGb > 0) {
        $data['volumes'] = ['0' => [
            'size' => $diskGb * 1000000000,
            'volume_type' => scaleway_volumeTypeFor($api, $commercialType),
        ]];
    }

    try {
        $new = $api->createServer($data);
    } catch (ApiException $e) {
        return ['ok' => false, 'error' => 'Αποτυχία δημιουργίας: ' . $e->getMessage()];
    }
    if (empty($new['id'])) {
        return ['ok' => false, 'error' => 'Το Scaleway δεν επέστρεψε instance.'];
    }
    $newId = $new['id'];

    Helper::saveServerId($params, $newId);
    Helper::recordInstance($serviceId, Helper::instanceProjectId($serviceId), $newId, $api->zone());

    // 4. Νέος κωδικός root μέσω cloud-init
    $rootPassword = Helper::generatePassword();
    try {
        $api->setCloudInit($newId, Helper::rootPasswordCloudInit($rootPassword));
    } catch (ApiException $e) {
        // μη κρίσιμο — ο πελάτης μπορεί να μπει με SSH key
    }

    // 5. Επανασύνδεση της παλιάς IP και εκκίνηση
    if ($keepIpId) {
        try { $api->attachIp($keepIpId, $newId); } catch (ApiException $e) { /* θα πάρει νέα */ }
    }
    try { $api->powerOn($newId); } catch (ApiException $e) { /* ίσως ήδη ξεκινά */ }

    $fresh = $api->getServer($newId) ?: $new;
    Helper::saveDelivery($params, $fresh, $rootPassword);

    return ['ok' => true, 'error' => ''];
}

/** Επιβεβαίωση ότι ένα snapshot ανήκει σε αυτή την υπηρεσία (anti-IDOR). */
function scaleway_ownsSnapshot(Api $api, array $params, $snapshotId)
{
    try {
        foreach ($api->listSnapshots() as $sn) {
            if (($sn['id'] ?? '') === $snapshotId) {
                return in_array('whmcs_service=' . (int) $params['serviceid'], $sn['tags'] ?? [], true);
            }
        }
    } catch (ApiException $e) {
        return false;
    }
    return false;
}

/** Ελαφρύ JSON endpoint για polling κατάστασης. */
function scaleway_caAjax(Api $api, $id, array $params)
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    $out = ['ok' => false];
    try {
        if ($id) {
            $srv = $api->getServer($id);
            if ($srv) {
                $out = [
                    'ok'     => true,
                    'status' => $srv['state'] ?? 'unknown',
                    'ipv4'   => Helper::primaryIp($srv) ?: '',
                    'type'   => strtoupper($srv['commercial_type'] ?? ''),
                ];
            }
        }
    } catch (ApiException $e) {
        $out = ['ok' => false, 'error' => 'unavailable'];
    }
    echo json_encode($out);
    exit;
}

function scaleway_caError($brand, $message)
{
    return [
        'templatefile' => 'overview',
        'vars' => [
            'brand'   => $brand,
            'fatal'   => $message,
            'notice'  => '',
            'error'   => '',
            'status'  => 'unknown',
            'snapshots' => [],
            'images'  => [],
            'resizeOptions' => [],
            'allowRebuild' => false,
            'allowResize'  => false,
        ],
    ];
}
