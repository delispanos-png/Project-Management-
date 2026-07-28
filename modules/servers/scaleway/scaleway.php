<?php
/**
 * Scaleway — WHMCS provisioning module.
 *
 * White-label cloud server provisioning & full lifecycle management πάνω στο
 * Scaleway Instance API. Ο πελάτης δεν βλέπει ποτέ τη λέξη "Scaleway": όλα τα
 * client-facing κείμενα χρησιμοποιούν το ρυθμιζόμενο brand name.
 *
 * Συνοδεύεται από το addon module "scaleway" (projects/credentials, sync,
 * import ήδη πουλημένων υπηρεσιών, κόστη).
 *
 * @package WHMCS\Module\Server\Scaleway
 * @author  Cloudon
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\Scaleway\Api;
use WHMCS\Module\Server\Scaleway\ApiException;
use WHMCS\Module\Server\Scaleway\Helper;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Api.php';
require_once __DIR__ . '/lib/Helper.php';

function scaleway_MetaData()
{
    return [
        'DisplayName'                       => 'Cloud Servers (Scaleway-backed)',
        'APIVersion'                        => '1.1',
        // Τα credentials έρχονται από το addon (ή από WHMCS server override).
        'RequiresServer'                    => false,
        'DefaultNonSSLPort'                 => '',
        'DefaultSSLPort'                    => '',
        'ServiceSingleSignOnLabel'          => 'Open Control Panel',
        'AdminSingleSignOnLabel'            => '',
        'ListAccountsUniqueIdentifierField' => 'domain',
    ];
}

/**
 * Δυναμικός κατάλογος για τα dropdown του προϊόντος.
 * Χρησιμοποιεί τα καθολικά credentials του addon (η οθόνη προϊόντος δεν έχει
 * context υπηρεσίας).
 */
function scaleway_catalogue()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $types = ['' => '— όρισε credentials στο addon —'];
    $images = ['' => 'ubuntu_jammy'];
    $zones = Api::ZONES;
    $sshKeys = ['' => 'Καμία (πρόσβαση με κωδικό)'];
    $projects = ['' => '— πρωτεύον project —'];

    $c = Helper::globalCredentials();
    if ($c['secret'] !== '') {
        try {
            $api = new Api($c['secret'], $c['project'], $c['zone']);

            $avail = $api->serverAvailability();
            $types = [];
            foreach ($api->serverTypes() as $name => $t) {
                $ram = isset($t['ram']) ? round($t['ram'] / 1073741824) : 0;
                $cpu = (int) ($t['ncpus'] ?? 0);
                $label = strtoupper($name) . ' — ' . $cpu . ' vCPU / ' . $ram . ' GB RAM';
                if (!empty($t['hourly_price'])) {
                    $label .= ' / ' . number_format((float) $t['hourly_price'] * 730, 2) . '€ μήνα';
                }
                $state = $avail[$name]['availability'] ?? '';
                if ($state === 'shortage') {
                    $label .= ' (εξαντλημένο)';
                }
                $types[$name] = $label;
            }
            if (!$types) {
                $types = ['' => '— δεν βρέθηκαν τύποι —'];
            }

            $mk = $api->marketplaceImages();
            if ($mk) {
                $images = $mk;
            }

            foreach ($api->sshKeys() as $k) {
                if (!empty($k['id'])) {
                    $sshKeys[$k['id']] = ($k['name'] ?? $k['id']);
                }
            }
        } catch (ApiException $e) {
            // Άφησε placeholders· ο admin βλέπει το σφάλμα στο TestConnection.
        }
    }

    // Multi-project override list
    try {
        foreach (Capsule::table(Helper::T_PROJECTS)->orderBy('name')->get() as $p) {
            $projects[(string) $p->id] = $p->name . ($p->is_primary ? ' (πρωτεύον)' : '');
        }
    } catch (\Exception $e) {
        // ο πίνακας δημιουργείται από το addon
    }

    return $cache = compact('types', 'images', 'zones', 'sshKeys', 'projects');
}

function scaleway_ConfigOptions()
{
    $cat = scaleway_catalogue();

    return [
        'Instance Type' => [
            'Type'        => 'dropdown',
            'Options'     => $cat['types'],
            'Description' => 'Scaleway commercial type (εσωτερικό — δεν το βλέπει ο πελάτης).',
        ],
        'Operating System' => [
            'Type'        => 'dropdown',
            'Options'     => $cat['images'],
            'Description' => 'Προεπιλεγμένο image κατά το provisioning.',
        ],
        'Zone' => [
            'Type'        => 'dropdown',
            'Options'     => $cat['zones'],
            'Description' => 'Τοποθεσία datacenter.',
        ],
        'Public IPv4' => [
            'Type'        => 'yesno',
            'Description' => 'Ανάθεση δημόσιας IPv4 (προσθέτει κόστος flexible IP).',
        ],
        'Boot volume GB' => [
            'Type'        => 'text',
            'Size'        => '6',
            'Description' => 'Μέγεθος δίσκου σε GB. Κενό/0 = προεπιλογή του τύπου.',
        ],
        'SSH Key' => [
            'Type'        => 'dropdown',
            'Options'     => $cat['sshKeys'],
            'Description' => 'Προαιρετικό SSH key που ενσωματώνεται στη δημιουργία.',
        ],
        'Client may rebuild OS' => [
            'Type'        => 'yesno',
            'Description' => 'Εμφάνιση του control επανεγκατάστασης στο client area.',
        ],
        'Client may resize' => [
            'Type'        => 'yesno',
            'Description' => 'Επιτρέπει στον πελάτη αίτημα αναβάθμισης από το client area.',
        ],
        'Brand name' => [
            'Type'        => 'text',
            'Size'        => '25',
            'Description' => 'Εμφανιζόμενη ονομασία στον πελάτη (π.χ. CloudOn VPS).',
        ],
        'Scaleway project' => [
            'Type'        => 'dropdown',
            'Options'     => $cat['projects'],
            'Description' => 'Override: σε ποιο project να δημιουργούνται τα VM αυτού του προϊόντος.',
        ],
    ];
}

function scaleway_TestConnection(array $params)
{
    try {
        $res = Helper::api($params)->testConnection();
        return $res['success']
            ? ['success' => true, 'error' => '']
            : ['success' => false, 'error' => $res['error']];
    } catch (\Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Provision (ή υιοθέτηση) ενός Scaleway instance.
 *
 * Idempotent: αν το service είναι ήδη συνδεδεμένο, ή υπάρχει instance με το
 * ντετερμινιστικό μας όνομα, το υιοθετούμε αντί να φτιάξουμε διπλότυπο.
 */
function scaleway_CreateAccount(array $params)
{
    try {
        $serviceId = (int) ($params['serviceid'] ?? 0);

        // Multi-project: κάρφωσε το service στο project-στόχο ΠΡΙΝ από κάθε κλήση.
        $targetPid = Helper::targetProjectForCreate($params);
        $pinnedPid = Helper::instanceProjectId($serviceId);
        $zone = Helper::zoneFor($params);

        // Η zone μπορεί να επιλέγεται από τον πελάτη (configurable option).
        $zoneChoice = Helper::optionValue($params, ['zone', 'location', 'region', 'τοποθεσ']);
        if ($zoneChoice) {
            $zone = Helper::mapZone($zoneChoice, $zone);
        }
        if (!$pinnedPid && $targetPid) {
            Helper::recordInstance($serviceId, $targetPid, null, $zone);
            $pinnedPid = $targetPid;
        } else {
            Helper::recordInstance($serviceId, $pinnedPid ?: $targetPid, null, $zone);
        }

        $api = Helper::api($params)->forZone($zone);
        $name = Helper::remoteServerName($params);

        // 1. Ήδη συνδεδεμένο;
        $existingId = Helper::serverId($params);
        if ($existingId) {
            $srv = $api->getServer($existingId);
            if ($srv) {
                scaleway_persistFromServer($params, $srv);
                if (($srv['state'] ?? '') !== 'running') {
                    try { $api->powerOn($existingId); } catch (ApiException $e) { /* ήδη ξεκινά */ }
                }
                Helper::resetStock($params);
                return 'success';
            }
        }

        // 2. Υπάρχει instance με το όνομά μας; Υιοθέτησέ το.
        $byName = $api->findServerByName($name);
        if ($byName) {
            Helper::saveServerId($params, $byName['id']);
            Helper::recordInstance($serviceId, $pinnedPid ?: $targetPid, $byName['id'], $zone);
            scaleway_persistFromServer($params, $byName);
            Helper::resetStock($params);
            return 'success';
        }

        // 3. Νέα δημιουργία.
        $image = $params['configoption2'] ?: 'ubuntu_jammy';
        $osChoice = Helper::optionValue($params, ['operating system', 'os', 'template', 'λειτουργικ']);
        if ($osChoice) {
            if (stripos($osChoice, 'windows') !== false) {
                return 'Τα Windows δεν είναι διαθέσιμα σε αυτή την πλατφόρμα. Επίλεξε Linux.';
            }
            $image = Helper::mapImage($osChoice, $api, $image);
        }

        $commercialType = $params['configoption1'];
        if ($commercialType === '') {
            return 'Δεν έχει οριστεί Instance Type στο προϊόν.';
        }

        $data = [
            'name'            => $name,
            'commercial_type' => $commercialType,
            'image'           => $image,
            'enable_ipv6'     => true,
            'dynamic_ip_required' => false,
            'tags'            => [
                'whmcs_service=' . $serviceId,
                'whmcs_client=' . (int) ($params['userid'] ?? 0),
            ],
        ];

        // Δημόσια IPv4: το Scaleway τη δίνει με routed IP κατά τη δημιουργία.
        $wantIpv4 = ($params['configoption4'] !== 'off');
        if ($wantIpv4) {
            $data['routed_ip_enabled'] = true;
        }

        // Boot volume: αν έχει οριστεί μέγεθος, φτιάξε ρητό volume.
        $diskGb = (int) preg_replace('/\D/', '', (string) ($params['configoption5'] ?? ''));
        $diskChoice = Helper::optionValue($params, ['disk', 'storage', 'δίσκ', 'αποθηκευ']);
        if ($diskChoice) {
            $diskGb = max($diskGb, (int) preg_replace('/\D/', '', (string) $diskChoice));
        }
        if ($diskGb > 0) {
            $data['volumes'] = ['0' => [
                'size'        => $diskGb * 1000000000,
                'volume_type' => scaleway_volumeTypeFor($api, $commercialType),
            ]];
        }

        // SSH key (IAM) — προαιρετικό, συμπληρωματικό του κωδικού.
        $sshKeyId = $params['configoption6'] ?? '';

        $server = $api->createServer($data);
        if (empty($server['id'])) {
            return 'Το Scaleway δεν επέστρεψε instance.';
        }
        $serverId = $server['id'];
        Helper::saveServerId($params, $serverId);
        Helper::recordInstance($serviceId, $pinnedPid ?: $targetPid, $serverId, $zone);

        // Root password: το Scaleway δεν δίνει κωδικό → τον ορίζουμε με cloud-init.
        $rootPassword = Helper::generatePassword();
        try {
            $api->setCloudInit($serverId, Helper::rootPasswordCloudInit($rootPassword));
        } catch (ApiException $e) {
            if (function_exists('logModuleCall')) {
                logModuleCall('scaleway', 'cloud-init', $serverId, $e->getMessage());
            }
        }

        // Το Scaleway δημιουργεί το instance ΣΒΗΣΤΟ → εκκίνηση.
        try {
            $api->powerOn($serverId);
        } catch (ApiException $e) {
            return 'Το instance δημιουργήθηκε αλλά απέτυχε η εκκίνηση: ' . $e->getMessage();
        }

        // Extra IPs
        $extraIps = [];
        $extraCount = Helper::extraIpsCount($params);
        for ($i = 0; $i < $extraCount; $i++) {
            try {
                $ip = $api->createIp($serverId, 'routed_ipv4', ['whmcs_service=' . $serviceId]);
                if (!empty($ip['address'])) {
                    $extraIps[] = $ip['address'];
                }
            } catch (ApiException $e) {
                if (function_exists('logModuleCall')) {
                    logModuleCall('scaleway', 'ExtraIP', $extraCount, $e->getMessage());
                }
            }
        }

        // Ξαναδιάβασε το instance για να πάρεις την τελική IP.
        $fresh = $api->getServer($serverId) ?: $server;
        Helper::saveDelivery($params, $fresh, $rootPassword, $extraIps);
        Helper::saveCustomField($params, Helper::FIELD_ZONE, $zone);

        Helper::resetStock($params);
        return 'success';
    } catch (ApiException $e) {
        return 'Σφάλμα Scaleway: ' . $e->getMessage();
    } catch (\Throwable $e) {
        return 'Σφάλμα: ' . $e->getMessage();
    }
}

/** Ο κατάλληλος τύπος volume για έναν commercial type. */
function scaleway_volumeTypeFor(Api $api, $commercialType)
{
    try {
        $types = $api->serverTypes();
        $t = $types[$commercialType] ?? null;
        if ($t) {
            // Οι τύποι με local storage δέχονται l_ssd· οι υπόλοιποι block storage.
            $local = (int) ($t['volumes_constraint']['min_size'] ?? 0);
            if ($local > 0 && !empty($t['per_volume_constraint']['l_ssd'])) {
                return 'l_ssd';
            }
        }
    } catch (ApiException $e) {
        // fall through
    }
    return 'b_ssd';
}

/** Αντιγραφή IP/κωδικού από ένα Scaleway server object στο WHMCS. */
function scaleway_persistFromServer(array $params, array $server, $rootPassword = null)
{
    Helper::saveIpAndPassword($params, Helper::primaryIp($server), $rootPassword);
}

/** Αναστολή => σβήσιμο (σταματά η χρέωση compute, τα δεδομένα μένουν). */
function scaleway_SuspendAccount(array $params)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return 'Δεν υπάρχει συνδεδεμένο instance.';
        }
        Helper::api($params)->powerOff($id);
        return 'success';
    } catch (ApiException $e) {
        return 'Σφάλμα Scaleway: ' . $e->getMessage();
    }
}

/** Άρση αναστολής => εκκίνηση. */
function scaleway_UnsuspendAccount(array $params)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return 'Δεν υπάρχει συνδεδεμένο instance.';
        }
        Helper::api($params)->powerOn($id);
        return 'success';
    } catch (ApiException $e) {
        return 'Σφάλμα Scaleway: ' . $e->getMessage();
    }
}

/** Τερματισμός => οριστική διαγραφή instance + volumes + IPs. */
function scaleway_TerminateAccount(array $params)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return 'success'; // δεν υπάρχει τίποτα να διαγραφεί
        }
        $api = Helper::api($params);
        Helper::deleteResources($api, $id, (int) $params['serviceid'], $api->zone());
        return 'success';
    } catch (ApiException $e) {
        return 'Σφάλμα Scaleway: ' . $e->getMessage();
    }
}

/**
 * Αλλαγή πακέτου => resize commercial type.
 * Το Scaleway απαιτεί το instance να είναι ΣΒΗΣΤΟ κατά την αλλαγή.
 */
function scaleway_ChangePackage(array $params)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return 'Δεν υπάρχει συνδεδεμένο instance.';
        }
        $api = Helper::api($params);
        $target = $params['configoption1'];
        $srv = $api->getServer($id);
        if (!$srv) {
            return 'Το συνδεδεμένο instance δεν βρέθηκε.';
        }
        if (($srv['commercial_type'] ?? '') === $target) {
            return 'success';
        }

        $wasRunning = ($srv['state'] ?? '') === 'running';
        if ($wasRunning) {
            try { $api->powerOff($id); } catch (ApiException $e) { /* ίσως ήδη σβήνει */ }
            // περίμενε να σταματήσει (το Scaleway δεν έχει action-wait API)
            for ($i = 0; $i < 30; $i++) {
                sleep(2);
                $s = $api->getServer($id);
                if (($s['state'] ?? '') === 'stopped') {
                    break;
                }
            }
        }
        $api->changeCommercialType($id, $target);
        if ($wasRunning) {
            try { $api->powerOn($id); } catch (ApiException $e) { /* μη κρίσιμο */ }
        }
        return 'success';
    } catch (ApiException $e) {
        return 'Σφάλμα Scaleway: ' . $e->getMessage();
    }
}

/* ─────────────────────────── Admin area ─────────────────────────── */

function scaleway_AdminCustomButtonArray(array $params)
{
    return [
        'Power On'  => 'adminPowerOn',
        'Power Off' => 'adminPowerOff',
        'Reboot'    => 'adminReboot',
        'Sync Info' => 'adminSync',
    ];
}

function scaleway_adminPowerOn(array $params)  { return scaleway_simpleAction($params, 'powerOn'); }
function scaleway_adminPowerOff(array $params) { return scaleway_simpleAction($params, 'powerOff'); }
function scaleway_adminReboot(array $params)   { return scaleway_simpleAction($params, 'reboot'); }

function scaleway_adminSync(array $params)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return 'Δεν υπάρχει συνδεδεμένο instance.';
        }
        $srv = Helper::api($params)->getServer($id);
        if ($srv) {
            scaleway_persistFromServer($params, $srv);
        }
        return 'success';
    } catch (ApiException $e) {
        return $e->getMessage();
    }
}

function scaleway_simpleAction(array $params, $method)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return 'Δεν υπάρχει συνδεδεμένο instance.';
        }
        Helper::api($params)->{$method}($id);
        return 'success';
    } catch (ApiException $e) {
        return $e->getMessage();
    }
}

/** Admin service tab: ζωντανή κατάσταση χωρίς διαρροή στον πελάτη. */
function scaleway_AdminServicesTabFields(array $params)
{
    try {
        $id = Helper::serverId($params);
        if (!$id) {
            return ['Scaleway Link' => 'Δεν έχει συνδεθεί ακόμη'];
        }
        $api = Helper::api($params);
        $srv = $api->getServer($id);
        if (!$srv) {
            return ['Scaleway Link' => 'Το instance ' . $id . ' δεν βρέθηκε στη zone ' . $api->zone()];
        }
        return [
            'Scaleway Instance ID' => (string) $srv['id'],
            'Τύπος'  => strtoupper($srv['commercial_type'] ?? '?'),
            'Κατάσταση' => $srv['state'] ?? '?',
            'IPv4'   => Helper::primaryIp($srv) ?: '—',
            'OS'     => $srv['image']['name'] ?? '?',
            'Zone'   => $srv['zone'] ?? $api->zone(),
        ];
    } catch (ApiException $e) {
        return ['Scaleway Link' => 'Σφάλμα: ' . $e->getMessage()];
    }
}

/** SSO: κρατάμε τον πελάτη στο δικό μας white-label panel. */
function scaleway_ServiceSingleSignOn(array $params)
{
    return [
        'success'    => true,
        'redirectTo' => 'clientarea.php?action=productdetails&id=' . (int) $params['serviceid'],
    ];
}

require_once __DIR__ . '/clientarea.php';
