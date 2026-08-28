<?php
/**
 * CloudOn Project Manager
 * ------------------------------------------------------------------
 * Project management inside WHMCS (GoodDay replacement):
 * projects per client/department, Kanban board with drag & drop,
 * tasks with assignee/priority/due date, comments, activity audit,
 * auto-task from new tickets. Roadmap: Φάσεις 1-6.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudonProjects\Db;
use WHMCS\Module\Addon\CloudonProjects\Time;
use WHMCS\Module\Addon\CloudonProjects\Notify;

require_once __DIR__ . '/lib/Db.php';
require_once __DIR__ . '/lib/Time.php';
require_once __DIR__ . '/lib/Notify.php';
require_once __DIR__ . '/lib/Storage.php';

function cloudonprojects_config()
{
    return [
        'name'        => 'CloudOn Project Manager',
        'description' => 'Projects, Kanban board, tasks, αναθέσεις & ιστορικό — όλα μέσα στο WHMCS.',
        'version'     => '1.0',
        'author'      => 'Cloud On',
        'language'    => 'greek',
        'fields'      => [
            'auto_task' => [
                'FriendlyName' => 'Auto-task από νέα tickets',
                'Type' => 'yesno', 'Default' => 'on',
                'Description' => 'Κάθε νέο ticket δημιουργεί task στο project του τμήματός του (το project ορίζει το τμήμα του στην επεξεργασία project).',
            ],
            'notify_email' => [
                'FriendlyName' => 'Ειδοποιήσεις email σε χειριστές',
                'Type' => 'yesno', 'Default' => 'on',
                'Description' => 'Email σε ανάθεση task, νέο σχόλιο, προγραμματισμένες εργασίες + ημερήσιο digest εκπρόθεσμων.',
            ],
            'team_roles' => [
                'FriendlyName' => 'Ρόλοι μελών ομάδων',
                'Type' => 'text', 'Size' => '80',
                'Default' => 'Διαχειριστής έργου,Senior Τεχνικός,Τεχνικός,Υποστήριξη,Πωλήσεις,Λογιστήριο,Developer',
                'Description' => 'Οι διαθέσιμοι ρόλοι στο dropdown των Ομάδων (χωρισμένοι με κόμμα).',
            ],
            'full_access_roles' => [
                'FriendlyName' => 'Ρόλοι με πλήρη πρόσβαση',
                'Type' => 'text', 'Size' => '20', 'Default' => '1',
                'Description' => 'IDs ρόλων admin (tbladminroles, με κόμμα) που βλέπουν ΤΑ ΠΑΝΤΑ. Όλοι οι υπόλοιποι agents βλέπουν μόνο projects όπου είναι μέλη + ό,τι τους έχει ανατεθεί.',
            ],
            'cost_per_hour' => [
                'FriendlyName' => 'Κόστος ώρας εργασίας (€)',
                'Type' => 'text', 'Size' => '10', 'Default' => '0',
                'Description' => 'Εσωτερικό κόστος ανά ώρα εργασίας — για τον υπολογισμό κερδοφορίας ανά πελάτη/project.',
            ],
            'request_form' => [
                'FriendlyName' => 'Δημόσια φόρμα αιτημάτων',
                'Type' => 'yesno', 'Default' => '',
                'Description' => 'Ενεργοποιεί τη φόρμα index.php?m=cloudonprojects&action=request (χωρίς login) — κάθε υποβολή γίνεται lead στις Πωλήσεις.',
            ],
            'sales_target' => [
                'FriendlyName' => 'Μηνιαίος στόχος πωλήσεων (€)',
                'Type' => 'text', 'Size' => '10', 'Default' => '0',
                'Description' => 'Εμφανίζεται στο tab Πωλήσεις με πρόοδο από τις κερδισμένες προσφορές του μήνα. 0 = χωρίς στόχο.',
            ],
        ],
    ];
}

function cloudonprojects_activate()
{
    try {
        Db::install();
        return ['status' => 'success', 'description' => 'CloudOn Project Manager: πίνακες + προεπιλεγμένες στήλες board δημιουργήθηκαν.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Σφάλμα: ' . $e->getMessage()];
    }
}

function cloudonprojects_deactivate()
{
    return ['status' => 'success', 'description' => 'Απενεργοποιήθηκε (τα δεδομένα διατηρήθηκαν).'];
}

function cloudonprojects_upgrade($vars)
{
    Db::install();
}

/* ------------------------------------------------------------------ */
/* Helpers                                                            */
/* ------------------------------------------------------------------ */

function cpm_e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Πεδίο επιλογής πελάτη με live αναζήτηση (αντικαθιστά τα σκέτα Client ID). */
function cpm_client_input($name, $value = 0, $width = '220px')
{
    static $n = 0;
    $n++;
    $uid = 'cnpCli' . $n;
    $value = (int) $value;
    $label = $value ? cpm_client_label($value) . ' (#' . $value . ')' : '';
    return '<input type="hidden" name="' . cpm_e($name) . '" id="' . $uid . 'v" value="' . $value . '">'
        . '<input type="text" class="form-control" style="width:' . cpm_e($width) . ';display:inline-block" id="' . $uid . '" list="' . $uid . 'l"'
        . ' placeholder="Αναζήτηση πελάτη (όνομα/email/ID)…" value="' . cpm_e($label) . '" autocomplete="off">'
        . '<datalist id="' . $uid . 'l"></datalist>'
        . '<script>cnpClientAuto("' . $uid . '");</script>';
}

function cpm_admin_id()
{
    return (int) ($_SESSION['adminid'] ?? 0);
}

/** Ο τρέχων admin έχει πλήρη πρόσβαση; (αλλιώς: μόνο μέλος-projects + αναθέσεις του) */
function cpm_is_full()
{
    static $v = null;
    if ($v === null) {
        $v = Db::isFullAccess(cpm_admin_id());
    }
    return $v;
}

function cpm_prio_badge($p)
{
    return [0 => '<span class="label label-default">Κανονική</span>',
            1 => '<span class="label label-warning">Υψηλή</span>',
            2 => '<span class="label label-danger">Κρίσιμη</span>'][$p] ?? '';
}

function cpm_client_label($clientid)
{
    if (!$clientid) { return ''; }
    $c = Capsule::table('tblclients')->where('id', (int) $clientid)->first(['firstname', 'lastname', 'companyname']);
    return $c ? ($c->companyname ?: trim($c->firstname . ' ' . $c->lastname)) : ('#' . $clientid);
}

/* ------------------------------------------------------------------ */
/* Admin output                                                       */
/* ------------------------------------------------------------------ */

function cloudonprojects_output($vars)
{
    $link    = $vars['modulelink'];
    $adminId = (int) ($_SESSION['adminid'] ?? 0);
    $tab     = $_REQUEST['tab'] ?? 'board';
    $do      = $_REQUEST['do'] ?? '';

    /* ---- Launcher για το αυτόνομο web app (SSO handoff) ----
       Το παλιό admin UI έχει καταργηθεί: κάθε είσοδος χωρίς do= (AJAX/hook posts)
       ανακατευθύνεται αυτόματα στο νέο πανελ. Escape hatch: &legacy=1 */
    if ((($do === '' && !isset($_GET['legacy'])) || isset($_GET['pmlaunch'])) && $adminId > 0) {
        $secret = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'pm_secret')->value('value');
        if (!$secret) {
            $secret = bin2hex(random_bytes(24));
            Capsule::table('tbladdonmodules')->insert(['module' => 'cloudonprojects', 'setting' => 'pm_secret', 'value' => $secret]);
        }
        $payload = $adminId . '.' . (time() + 90);
        $tok = rtrim(strtr(base64_encode($payload . '.' . hash_hmac('sha256', $payload, $secret)), '+/', '-_'), '=');
        $deep = ($tab === 'task' && !empty($_GET['id'])) ? '#/task/' . (int) $_GET['id'] : '';
        header('Location: /project/?t=' . $tok . $deep);
        exit;
    }

    /* ---- AJAX: move task (drag & drop) — return JSON, no HTML ---- */
    if ($do === 'movetask' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $t = Db::task((int) ($_POST['taskid'] ?? 0));
        $ok = $t && Db::canSeeTask($adminId, $t)
            && Db::moveTask((int) $t->id, (int) ($_POST['statusid'] ?? 0), $adminId);
        // agent (όχι διαχειριστής) πήγε task σε «Ολοκληρώθηκε» → ενημέρωση διαχειριστών
        if ($ok && !Db::isFullAccess($adminId)) {
            $st = Db::status((int) ($_POST['statusid'] ?? 0));
            if ($st && $st->is_done) {
                Notify::workDone($adminId, $t->title, 'addonmodules.php?module=cloudonprojects&tab=task&id=' . (int) $t->id);
            }
        }
        if ($ok) {
            $stN = Db::status((int) ($_POST['statusid'] ?? 0));
            Notify::watchers((int) $t->id, $adminId, $t->title . ' → ' . ($stN->title ?? '?'), null);
        }
        echo json_encode(['ok' => (bool) $ok]);
        exit;
    }
    /* ---- Ειδοποιήσεις: άνοιγμα + όλα ως διαβασμένα ---- */
    if ($do === 'gonotif') {
        $n = Db::notification((int) ($_REQUEST['id'] ?? 0));
        if ($n && (int) $n->admin_id === $adminId) {
            Db::markNotifRead($adminId, $n->id);
            $to = $n->url ?: ($link . '&tab=mine');
            if (strpos($to, '://') !== false || strpos($to, '//') === 0) {
                $to = $link . '&tab=mine'; // μόνο σχετικά admin URLs
            }
            header('Location: ' . $to);
            return;
        }
        header('Location: ' . $link);
        return;
    }
    if ($do === 'readallnotif') {
        Db::markNotifRead($adminId);
        header('Location: ' . $link . '&tab=mine');
        return;
    }

    /* ---- AJAX: αναζήτηση πελάτη για autocomplete ---- */
    if ($do === 'clientsearch') {
        header('Content-Type: application/json');
        $q = trim($_REQUEST['q'] ?? '');
        $out = [];
        if (mb_strlen($q) >= 2) {
            $query = Capsule::table('tblclients')->limit(12);
            if (ctype_digit($q)) {
                $query->where('id', (int) $q);
            } else {
                $like = '%' . $q . '%';
                $query->where(function ($w) use ($like) {
                    $w->where('firstname', 'like', $like)->orWhere('lastname', 'like', $like)
                      ->orWhere('companyname', 'like', $like)->orWhere('email', 'like', $like);
                });
            }
            foreach ($query->get(['id', 'firstname', 'lastname', 'companyname']) as $c) {
                $out[] = ['id' => (int) $c->id,
                          'label' => ($c->companyname ?: trim($c->firstname . ' ' . $c->lastname)) . ' (#' . $c->id . ')'];
            }
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($do === 'movelead' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $ld = Db::lead((int) ($_POST['leadid'] ?? 0));
        $allowed = $ld && (Db::isFullAccess($adminId) || (int) $ld->assignee === $adminId || (int) $ld->created_by === $adminId);
        $ok = $allowed && Db::moveLead((int) $ld->id, (string) ($_POST['stage'] ?? ''), $adminId);
        echo json_encode(['ok' => (bool) $ok]);
        exit;
    }
    if ($do === 'moveoffer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $oid = (int) ($_POST['offerid'] ?? 0);
        $stage = (string) ($_POST['stage'] ?? '');
        $o = $oid ? Db::offer($oid) : null;
        if ($o && !Db::isFullAccess($adminId) && (int) $o->assignee !== $adminId && (int) $o->created_by !== $adminId) {
            echo json_encode(['ok' => false]);
            exit;
        }
        // αν είναι δεμένη με quote, το στάδιο αλλάζει ΚΑΙ στο quote ώστε να μη γυρίσει πίσω στο sync
        $ok = Db::moveOffer($oid, $stage, $adminId);
        if ($ok && $o && $o->quoteid) {
            $qs = ['draft' => 'Draft', 'sent' => 'Delivered', 'accepted' => 'Accepted', 'lost' => 'Lost'][$stage] ?? null;
            if ($qs) {
                Capsule::table('tblquotes')->where('id', (int) $o->quoteid)->update(['stage' => $qs]);
            }
        }
        echo json_encode(['ok' => (bool) $ok]);
        exit;
    }

    /* ---- POST handlers (redirect after) ---- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($do === 'saveproject') {
            if (!Db::isFullAccess($adminId)) {
                header('Location: ' . $link . '&tab=projects');
                return;
            }
            $pid = (int) ($_POST['id'] ?? 0);
            $pid = Db::saveProject($pid, [
                'name'           => mb_substr(trim($_POST['name'] ?? ''), 0, 120) ?: 'Χωρίς όνομα',
                'clientid'       => (int) ($_POST['clientid'] ?? 0) ?: null,
                'deptid'         => (int) ($_POST['deptid'] ?? 0) ?: null,
                'color'          => preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#0097e4',
                'descr'          => mb_substr(trim($_POST['descr'] ?? ''), 0, 60000),
                'client_visible' => isset($_POST['client_visible']) ? 1 : 0,
                'parent_id'      => ((int) ($_POST['parent_id'] ?? 0) && (int) $_POST['parent_id'] !== $pid) ? (int) $_POST['parent_id'] : null,
                'pstatus'        => array_key_exists($_POST['pstatus'] ?? '', Db::projectStatuses()) ? $_POST['pstatus'] : null,
                'health'         => array_key_exists($_POST['health'] ?? '', Db::healthColors()) ? $_POST['health'] : null,
            ]);
            Db::saveProjectMembers($pid, (array) ($_POST['members'] ?? []));
            Db::saveProjectTeams($pid, (array) ($_POST['teams'] ?? []));
            header('Location: ' . $link . '&tab=projects&saved=1');
            return;
        }
        if (in_array($do, ['saveteam', 'delteam', 'addteammember', 'delteammember'], true)) {
            if (!Db::isFullAccess($adminId)) {
                header('Location: ' . $link . '&tab=teams');
                return;
            }
            if ($do === 'saveteam') {
                $tid = (int) ($_POST['id'] ?? 0);
                Db::saveTeam($tid, [
                    'name'  => mb_substr(trim($_POST['name'] ?? ''), 0, 80) ?: 'Χωρίς όνομα',
                    'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#0097e4',
                    'descr' => mb_substr(trim($_POST['descr'] ?? ''), 0, 500),
                ]);
            } elseif ($do === 'delteam') {
                Db::deleteTeam((int) ($_POST['id'] ?? 0));
            } elseif ($do === 'addteammember') {
                $tid = (int) ($_POST['team_id'] ?? 0);
                $aid = (int) ($_POST['admin_id'] ?? 0);
                if ($tid && $aid && Db::team($tid)) {
                    Db::addTeamMember($tid, $aid,
                        mb_substr(trim($_POST['role_title'] ?? ''), 0, 60) ?: null,
                        isset($_POST['is_leader']) ? 1 : 0);
                }
            } elseif ($do === 'delteammember') {
                Db::removeTeamMember((int) ($_POST['team_id'] ?? 0), (int) ($_POST['admin_id'] ?? 0));
            }
            header('Location: ' . $link . '&tab=teams');
            return;
        }
        if ($do === 'archiveproject') {
            if (!Db::isFullAccess($adminId)) {
                header('Location: ' . $link . '&tab=projects');
                return;
            }
            $pid = (int) ($_POST['id'] ?? 0);
            $p = Db::project($pid);
            if ($p) {
                Db::saveProject($pid, ['status' => $p->status === 'archived' ? 'active' : 'archived']);
            }
            header('Location: ' . $link . '&tab=projects');
            return;
        }
        if ($do === 'savetask') {
            $tid = (int) ($_POST['id'] ?? 0);
            if (($tid && !Db::canSeeTask($adminId, Db::task($tid)))
                || (!$tid && !Db::canSeeProject($adminId, (int) ($_POST['project_id'] ?? 0)))) {
                header('Location: ' . $link . '&tab=board');
                return;
            }
            $estMin = (int) round(((float) str_replace(',', '.', $_POST['est_h'] ?? 0)) * 60) + (int) ($_POST['est_m'] ?? 0);
            $data = [
                'project_id' => (int) ($_POST['project_id'] ?? 0),
                'title'      => mb_substr(trim($_POST['title'] ?? ''), 0, 200) ?: 'Χωρίς τίτλο',
                'descr'      => mb_substr(trim($_POST['descr'] ?? ''), 0, 60000),
                'status_id'  => (int) ($_POST['status_id'] ?? Db::firstStatusId()),
                'priority'   => min(2, max(0, (int) ($_POST['priority'] ?? 0))),
                'assignee'   => (int) ($_POST['assignee'] ?? 0) ?: null,
                'ticketid'   => (int) ($_POST['ticketid'] ?? 0) ?: null,
                'due_date'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['due_date'] ?? '') ? $_POST['due_date'] : null,
                'action_user'      => (int) ($_POST['action_user'] ?? 0) ?: null,
                'schedule_date'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['schedule_date'] ?? '') ? $_POST['schedule_date'] : null,
                'type_id'          => (int) ($_POST['type_id'] ?? 0) ?: null,
                'estimate_minutes' => $estMin > 0 ? $estMin : null,
            ];
            $old = $tid ? Db::task($tid) : null;
            // Χαρακτηρισμός (ανάθεση + προτεραιότητα) ΜΟΝΟ από διαχειριστές με πλήρη πρόσβαση.
            if (!Db::isFullAccess($adminId)) {
                if ($old) {
                    $data['assignee'] = $old->assignee;   // κρατά ό,τι όρισε ο διαχειριστής
                    $data['priority'] = (int) $old->priority;
                } else {
                    $data['assignee'] = $adminId;         // νέο δικό του task: αυτο-ανάθεση
                }
            }
            $newId = Db::saveTask($tid, $data, $adminId);
            if (!$tid) {
                Db::logActivity($newId, $adminId, 'create', 'Δημιουργία task');
                if ($data['assignee']) {
                    Notify::assigned($newId, $data['assignee'], $adminId);
                }
            } else {
                if ($old && (int) $old->assignee !== (int) $data['assignee']) {
                    Db::logActivity($newId, $adminId, 'assign', 'Ανάθεση: ' . Db::adminName($data['assignee']));
                    Notify::assigned($newId, $data['assignee'], $adminId);
                }
                if ($old && (int) $old->status_id !== (int) $data['status_id']) {
                    Db::moveTask($newId, $data['status_id'], $adminId);
                    if (!Db::isFullAccess($adminId)) {
                        $stNew = Db::status($data['status_id']);
                        if ($stNew && $stNew->is_done) {
                            Notify::workDone($adminId, $data['title'], 'addonmodules.php?module=cloudonprojects&tab=task&id=' . $newId);
                        }
                    }
                }
                Db::logActivity($newId, $adminId, 'edit', 'Επεξεργασία στοιχείων');
            }
            // «η μπάλα» άλλαξε → καμπανάκι στον νέο παραλήπτη
            if ($data['action_user'] && (int) ($old->action_user ?? 0) !== (int) $data['action_user']
                && (int) $data['action_user'] !== $adminId) {
                Db::pushNotification($data['action_user'], 'action',
                    '⚡ Απαιτείται ενέργειά σου: ' . $data['title'],
                    'addonmodules.php?module=cloudonprojects&tab=task&id=' . $newId);
                Db::logActivity($newId, $adminId, 'action', 'Η μπάλα στον/στην ' . Db::adminName($data['action_user']));
            }
            // custom fields του project (3.5)
            foreach (Db::fieldsForProject($data['project_id']) as $cf) {
                if (isset($_POST['cf'][$cf->id])) {
                    Db::saveFieldValue($newId, $cf->id, mb_substr(trim($_POST['cf'][$cf->id]), 0, 2000));
                }
            }
            // έλεγχος υποχρεωτικών ανά τύπο (GoodDay-style)
            $warn = '';
            if ($data['type_id'] && ($tt = Db::taskType($data['type_id']))) {
                $miss = [];
                if ($tt->req_assignee && !$data['assignee']) { $miss[] = 'Ανάθεση'; }
                if ($tt->req_due && !$data['due_date']) { $miss[] = 'Λήξη'; }
                if ($tt->req_estimate && !$data['estimate_minutes']) { $miss[] = 'Εκτίμηση χρόνου'; }
                if ($miss) {
                    $warn = '&warn=' . urlencode('Ο τύπος «' . $tt->name . '» χρειάζεται: ' . implode(', ', $miss));
                }
            }
            header('Location: ' . $link . '&tab=task&id=' . $newId . '&saved=1' . $warn);
            return;
        }
        if ($do === 'scheduletask') {
            // γρήγορος προγραμματισμός ημέρας: σήμερα / αύριο / καθαρισμός
            $tid = (int) ($_POST['taskid'] ?? 0);
            $t = Db::task($tid);
            if ($t && Db::canSeeTask($adminId, $t)) {
                $when = $_POST['when'] ?? '';
                $date = $when === 'today' ? date('Y-m-d') : ($when === 'tomorrow' ? date('Y-m-d', strtotime('+1 day')) : null);
                Db::saveTask($tid, ['schedule_date' => $date], $adminId);
            }
            header('Location: ' . ($_POST['ref'] ?? $link . '&tab=mine'));
            return;
        }
        if ($do === 'additem') {
            $tid = (int) ($_POST['taskid'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            if ($tid && $title !== '' && Db::task($tid)) {
                Db::addCheckItem($tid, $title);
            }
            header('Location: ' . $link . '&tab=task&id=' . $tid . '#checklist');
            return;
        }
        if ($do === 'toggleitem') {
            $it = Db::toggleCheckItem((int) ($_POST['itemid'] ?? 0));
            header('Location: ' . $link . '&tab=task&id=' . (int) ($it->task_id ?? 0) . '#checklist');
            return;
        }
        if ($do === 'delitem') {
            $tid = (int) ($_POST['taskid'] ?? 0);
            Db::deleteCheckItem((int) ($_POST['itemid'] ?? 0));
            header('Location: ' . $link . '&tab=task&id=' . $tid . '#checklist');
            return;
        }
        if (in_array($do, ['saverec', 'delrec', 'savefield', 'delfield'], true) && !Db::isFullAccess($adminId)) {
            header('Location: ' . $link . '&tab=projects');
            return;
        }
        if ($do === 'saverec') {
            $rid = (int) ($_POST['id'] ?? 0);
            $freq = in_array($_POST['freq'] ?? '', ['daily', 'weekly', 'monthly', 'yearly'], true) ? $_POST['freq'] : 'monthly';
            Db::saveRecurring($rid, [
                'project_id' => (int) ($_POST['project_id'] ?? 0),
                'title'      => mb_substr(trim($_POST['title'] ?? ''), 0, 200) ?: 'Χωρίς τίτλο',
                'descr'      => mb_substr(trim($_POST['descr'] ?? ''), 0, 60000),
                'priority'   => min(2, max(0, (int) ($_POST['priority'] ?? 0))),
                'assignee'   => (int) ($_POST['assignee'] ?? 0) ?: null,
                'freq'       => $freq,
                'every'      => max(1, (int) ($_POST['every'] ?? 1)),
                'next_run'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['next_run'] ?? '') ? $_POST['next_run'] : date('Y-m-d'),
                'due_days'   => max(0, (int) ($_POST['due_days'] ?? 0)),
                'active'     => isset($_POST['active']) ? 1 : 0,
            ]);
            header('Location: ' . $link . '&tab=projects&saved=1#recurring');
            return;
        }
        if ($do === 'delrec') {
            Db::deleteRecurring((int) ($_POST['id'] ?? 0));
            header('Location: ' . $link . '&tab=projects#recurring');
            return;
        }
        if ($do === 'savefield') {
            $pid = (int) ($_POST['project_id'] ?? 0);
            $type = in_array($_POST['type'] ?? '', ['text', 'select', 'date'], true) ? $_POST['type'] : 'text';
            if ($pid && trim($_POST['label'] ?? '') !== '') {
                Db::saveField(0, [
                    'project_id' => $pid,
                    'label'      => mb_substr(trim($_POST['label']), 0, 60),
                    'type'       => $type,
                    'options'    => $type === 'select' ? mb_substr(trim($_POST['options'] ?? ''), 0, 2000) : null,
                    'sort'       => (int) ($_POST['sort'] ?? 0),
                ]);
            }
            header('Location: ' . $link . '&tab=projects&edit=' . $pid . '#fields');
            return;
        }
        if ($do === 'delfield') {
            $pid = (int) ($_POST['project_id'] ?? 0);
            Db::deleteField((int) ($_POST['id'] ?? 0));
            header('Location: ' . $link . '&tab=projects&edit=' . $pid . '#fields');
            return;
        }
        if ($do === 'addcomment') {
            $tid = (int) ($_POST['taskid'] ?? 0);
            $c = trim($_POST['comment'] ?? '');
            if ($tid && $c !== '') {
                $to = ($_POST['to_admin'] ?? '') !== '' ? (int) $_POST['to_admin'] : null; // -1=διαχειριστές
                Db::addComment($tid, $adminId, mb_substr($c, 0, 60000), $to);
                Notify::commented($tid, $adminId, $c);          // assignee
                if ($to !== null) {
                    Notify::commentTo($tid, $adminId, $c, $to); // στοχευμένο «προς»
                }
                Notify::watchers($tid, $adminId, 'Σχόλιο στο: ' . (Db::task($tid)->title ?? ''), null);
            }
            header('Location: ' . $link . '&tab=task&id=' . $tid . '#comments');
            return;
        }
        if ($do === 'togglewatch') {
            $tid = (int) ($_POST['taskid'] ?? 0);
            if ($tid && Db::task($tid)) {
                Db::toggleWatcher($tid, $adminId);
            }
            header('Location: ' . ($_POST['ref'] ?? $link . '&tab=task&id=' . $tid));
            return;
        }
        if ($do === 'addreminder') {
            $tid = (int) ($_POST['taskid'] ?? 0) ?: null;
            $at = preg_match('/^\d{4}-\d{2}-\d{2}(T| )\d{2}:\d{2}/', $_POST['remind_at'] ?? '')
                ? str_replace('T', ' ', substr($_POST['remind_at'], 0, 16)) . ':00' : null;
            if ($at) {
                Db::addReminder($adminId, $at, trim($_POST['note'] ?? ''), $tid);
            }
            header('Location: ' . ($_POST['ref'] ?? $link . '&tab=task&id=' . (int) $tid));
            return;
        }
        if ($do === 'requestupdate') {
            $tid = (int) ($_POST['taskid'] ?? 0);
            if ($tid && Db::task($tid)) {
                Notify::requestUpdate($tid, $adminId);
                Db::logActivity($tid, $adminId, 'edit', 'Ζητήθηκε ενημέρωση κατάστασης');
            }
            header('Location: ' . ($_POST['ref'] ?? $link . '&tab=task&id=' . $tid));
            return;
        }
        if ($do === 'ticketcomment') {
            // Εσωτερική συνομιλία στο ticket: γράφει στο linked task (το δημιουργεί αν λείπει)
            $ticketId = (int) ($_POST['ticketid'] ?? 0);
            $c = trim($_POST['comment'] ?? '');
            $tk = Capsule::table('tbltickets')->where('id', $ticketId)->first(['tid', 'title', 'did']);
            if ($tk && $c !== '') {
                $task = Db::taskForTicket($ticketId);
                if (!$task) {
                    // Εργασία από ticket: ανήκει στο department του, χωρίς έργο.
                    if (true) {
                        $newTid = Db::saveTask(0, [
                            'project_id' => null,
                            'dept_id'    => (int) $tk->did,
                            'title'      => '[#' . $tk->tid . '] ' . mb_substr($tk->title, 0, 180),
                            'status_id'  => Db::firstStatusId(),
                            'ticketid'   => $ticketId,
                        ], $adminId);
                        $task = Db::task($newTid);
                    }
                }
                if ($task) {
                    $to = ($_POST['to_admin'] ?? '') !== '' ? (int) $_POST['to_admin'] : null;
                    Db::addComment($task->id, $adminId, mb_substr($c, 0, 60000), $to);
                    Notify::commented($task->id, $adminId, $c);
                    if ($to !== null) {
                        Notify::commentTo($task->id, $adminId, $c, $to);
                    }
                    Notify::watchers($task->id, $adminId, 'Σχόλιο στο: ' . $task->title, null);
                }
            }
            header('Location: supporttickets.php?action=view&id=' . $ticketId);
            return;
        }
        if ($do === 'addexpense') {
            if (Db::isFullAccess($adminId)) {
                $pid = (int) ($_POST['project_id'] ?? 0);
                $amt = (float) str_replace(',', '.', $_POST['amount'] ?? 0);
                if ($pid && $amt > 0 && Db::project($pid)) {
                    Db::addExpense($pid, trim($_POST['descr'] ?? '') ?: 'Έξοδο',
                        $amt, preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['spent_at'] ?? '') ? $_POST['spent_at'] : date('Y-m-d'), $adminId);
                }
            }
            header('Location: ' . $link . '&tab=profit');
            return;
        }
        if ($do === 'delexpense') {
            if (Db::isFullAccess($adminId)) {
                Db::deleteExpense((int) ($_POST['id'] ?? 0));
            }
            header('Location: ' . $link . '&tab=profit');
            return;
        }
        if ($do === 'starttimer') {
            $tid = (int) ($_POST['taskid'] ?? 0);
            if ($tid && Db::task($tid)) {
                $r = Db::startTimer($tid, $adminId);
                foreach ($r['stopped'] as $sid) {
                    Time::push($sid); // ο auto-stopped timer γίνεται κανονική (μη χρεώσιμη) εγγραφή
                }
                Db::logActivity($tid, $adminId, 'timer', 'Έναρξη χρονομέτρησης');
            }
            header('Location: ' . $link . '&tab=task&id=' . $tid . '#time');
            return;
        }
        if ($do === 'stoptimer') {
            $lid = (int) ($_POST['logid'] ?? 0);
            $l = $lid ? Db::timelog($lid) : null;
            $tid = $l ? (int) $l->task_id : 0;
            if ($l && $l->running && (int) $l->admin_id === $adminId) {
                $e = Db::stopTimer($lid);
                if ($e) {
                    Db::updateTimelog($lid, [
                        'billable' => (($_POST['billable'] ?? '0') === '1') ? 1 : 0,
                        'note'     => mb_substr(trim($_POST['note'] ?? ''), 0, 255),
                    ]);
                    Time::push($lid);
                    Db::logActivity($tid, $adminId, 'time', 'Χρονομέτρηση: ' . Time::fmt(Db::timelog($lid)->minutes)
                        . ((($_POST['billable'] ?? '0') === '1') ? ' (χρεώσιμο)' : ' (χωρίς χρέωση)'));
                }
            }
            header('Location: ' . $link . '&tab=task&id=' . $tid . '#time');
            return;
        }
        if ($do === 'addtime') {
            $tid = (int) ($_POST['taskid'] ?? 0);
            $mins = (int) round(((float) ($_POST['hours'] ?? 0)) * 60) + (int) ($_POST['minutes'] ?? 0);
            if ($tid && $mins > 0 && Db::task($tid)) {
                $billable = (($_POST['billable'] ?? '0') === '1');
                $eid = Db::addTime($tid, $adminId, $mins, $billable, trim($_POST['note'] ?? ''));
                Time::push($eid);
                Db::logActivity($tid, $adminId, 'time', 'Καταχώρηση χρόνου: ' . Time::fmt($mins)
                    . ($billable ? ' (χρεώσιμο)' : ' (χωρίς χρέωση)'));
            }
            header('Location: ' . $link . '&tab=task&id=' . $tid . '#time');
            return;
        }
        if ($do === 'deltime') {
            $lid = (int) ($_POST['logid'] ?? 0);
            $l = $lid ? Db::timelog($lid) : null;
            $tid = $l ? (int) $l->task_id : 0;
            if ($l) {
                Time::reverse($lid); // επιστροφή χρέωσης + σβήσιμο SC worklog αν είχε περαστεί
                Db::deleteTimelog($lid);
                Db::logActivity($tid, $adminId, 'time', 'Διαγραφή καταχώρησης χρόνου (' . Time::fmt($l->minutes) . ')');
            }
            header('Location: ' . $link . '&tab=task&id=' . $tid . '#time');
            return;
        }
        if ($do === 'ticketworkdone') {
            // Δήλωση ολοκλήρωσης από τη σελίδα του ticket → task done + ενημέρωση διαχειριστών
            $ticketId = (int) ($_POST['ticketid'] ?? 0);
            $tk = Capsule::table('tbltickets')->where('id', $ticketId)->first(['tid', 'title']);
            if ($tk) {
                $task = Db::taskForTicket($ticketId);
                if ($task && !$task->completed_at) {
                    $doneId = (int) Capsule::table('mod_cpm_statuses')->where('is_done', 1)->orderBy('sort')->value('id');
                    if ($doneId && Db::canSeeTask($adminId, $task)) {
                        Db::moveTask($task->id, $doneId, $adminId);
                    }
                }
                Notify::workDone($adminId, '[#' . $tk->tid . '] ' . $tk->title,
                    'supporttickets.php?action=view&id=' . $ticketId);
                if (function_exists('logActivity')) {
                    logActivity('CPM: δήλωση ολοκλήρωσης εργασίας για ticket #' . $tk->tid . ' από admin #' . $adminId);
                }
            }
            header('Location: supporttickets.php?action=view&id=' . $ticketId);
            return;
        }
        if ($do === 'quicktask') {
            $pid = (int) ($_POST['project_id'] ?? 0);
            $sid = (int) ($_POST['status_id'] ?? 0);
            $title = mb_substr(trim($_POST['title'] ?? ''), 0, 200);
            if ($pid && $title !== '' && Db::project($pid) && Db::canSeeProject($adminId, $pid)) {
                $tid = Db::saveTask(0, [
                    'project_id' => $pid, 'title' => $title,
                    'status_id'  => Db::status($sid) ? $sid : Db::firstStatusId(),
                ], $adminId);
                Db::logActivity($tid, $adminId, 'create', 'Γρήγορη δημιουργία από board');
            }
            header('Location: ' . $link . '&tab=board&project=' . $pid);
            return;
        }
        if ($do === 'saveinteraction') {
            $leadId = (int) ($_POST['lead_id'] ?? 0) ?: null;
            $clientId = (int) ($_POST['clientid'] ?? 0) ?: null;
            $summary = mb_substr(trim($_POST['summary'] ?? ''), 0, 255);
            if (($leadId || $clientId) && $summary !== '') {
                // αν το lead έχει δεθεί με πελάτη, κράτα και τα δύο (φαίνεται στο timeline του πελάτη)
                if ($leadId && !$clientId) {
                    $clientId = (int) (Db::lead($leadId)->clientid ?? 0) ?: null;
                }
                Db::addInteraction([
                    'lead_id'       => $leadId,
                    'clientid'      => $clientId,
                    'kind'          => array_key_exists($_POST['kind'] ?? '', Db::interactionKinds()) ? $_POST['kind'] : 'note',
                    'summary'       => $summary,
                    'detail'        => mb_substr(trim($_POST['detail'] ?? ''), 0, 60000) ?: null,
                    'admin_id'      => $adminId,
                    'happened_at'   => preg_match('/^\d{4}-\d{2}-\d{2}(T| )\d{2}:\d{2}/', $_POST['happened_at'] ?? '')
                        ? str_replace('T', ' ', substr($_POST['happened_at'], 0, 16)) . ':00' : date('Y-m-d H:i:s'),
                    'followup_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['followup_date'] ?? '') ? $_POST['followup_date'] : null,
                    'followup_note' => mb_substr(trim($_POST['followup_note'] ?? ''), 0, 200) ?: null,
                ]);
            }
            header('Location: ' . ($_POST['ref'] ?? $link . '&tab=sales&view=log'));
            return;
        }
        if ($do === 'donefollowup') {
            $iid = (int) ($_POST['id'] ?? 0);
            Capsule::table('mod_cpm_interactions')->where('id', $iid)->update(['followup_done' => 1]);
            header('Location: ' . ($_POST['ref'] ?? $link . '&tab=sales&view=log'));
            return;
        }
        if ($do === 'delinteraction') {
            Db::deleteInteraction((int) ($_POST['id'] ?? 0));
            header('Location: ' . ($_POST['ref'] ?? $link . '&tab=sales&view=log'));
            return;
        }
        if ($do === 'saveptarget' || $do === 'delptarget') {
            if (Db::isFullAccess($adminId)) {
                if ($do === 'saveptarget') {
                    $pid = (int) ($_POST['product_id'] ?? 0);
                    if ($pid && Capsule::table('tblproducts')->where('id', $pid)->exists()) {
                        Db::saveProductTarget($pid, (int) ($_POST['target_units'] ?? 0),
                            (float) str_replace(',', '.', $_POST['target_value'] ?? 0));
                    }
                } else {
                    Db::deleteProductTarget((int) ($_POST['id'] ?? 0));
                }
            }
            header('Location: ' . $link . '&tab=sales&view=targets');
            return;
        }
        if ($do === 'savelead') {
            $lid = (int) ($_POST['id'] ?? 0);
            $stage = array_key_exists($_POST['stage'] ?? '', Db::leadStages()) ? $_POST['stage'] : 'target';
            $data = [
                'company'     => mb_substr(trim($_POST['company'] ?? ''), 0, 120) ?: null,
                'contact'     => mb_substr(trim($_POST['contact'] ?? ''), 0, 120) ?: null,
                'email'       => mb_substr(trim($_POST['email'] ?? ''), 0, 120) ?: null,
                'phone'       => mb_substr(trim($_POST['phone'] ?? ''), 0, 40) ?: null,
                'source'      => mb_substr(trim($_POST['source'] ?? ''), 0, 60) ?: null,
                'stage'       => $stage,
                'assignee'    => (int) ($_POST['assignee'] ?? 0) ?: null,
                'next_action' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['next_action'] ?? '') ? $_POST['next_action'] : null,
                'next_note'   => mb_substr(trim($_POST['next_note'] ?? ''), 0, 200) ?: null,
                'descr'       => mb_substr(trim($_POST['descr'] ?? ''), 0, 60000),
                'closed_at'   => Db::leadStages()[$stage][2] ? date('Y-m-d H:i:s') : null,
            ];
            if (!$lid) {
                $data['created_by'] = $adminId;
            }
            if (empty($data['company']) && empty($data['contact'])) {
                $data['company'] = 'Χωρίς όνομα';
            }
            $lid = Db::saveLead($lid, $data);
            header('Location: ' . $link . '&tab=lead&id=' . $lid . '&saved=1');
            return;
        }
        if ($do === 'deletelead') {
            $lid = (int) ($_POST['id'] ?? 0);
            $l = Db::lead($lid);
            if ($l) {
                Db::deleteLead($lid);
                if (function_exists('logActivity')) {
                    logActivity('CPM: διαγραφή lead #' . $lid . ' («' . trim(($l->company ?: '') . ' ' . ($l->contact ?: '')) . '») από admin #' . $adminId);
                }
            }
            header('Location: ' . $link . '&tab=sales');
            return;
        }
        if ($do === 'convertlead') {
            // Μετατροπή lead → WHMCS client (localAPI AddClient)
            $lid = (int) ($_POST['id'] ?? 0);
            $l = Db::lead($lid);
            if ($l && !$l->clientid) {
                $names = preg_split('/\s+/', trim((string) $l->contact), 2);
                $r = localAPI('AddClient', [
                    'firstname'   => $names[0] ?: ($l->company ?: 'Νέος'),
                    'lastname'    => $names[1] ?? '-',
                    'companyname' => (string) $l->company,
                    'email'       => (string) $l->email,
                    'phonenumber' => (string) $l->phone,
                    'password2'   => bin2hex(random_bytes(10)),
                    'country'     => 'GR',
                    'noemail'     => true, // ΧΩΡΙΣ welcome email — ο χειριστής αποφασίζει πότε
                    'skipvalidation' => true,
                ], 'pdelis');
                if (($r['result'] ?? '') === 'success' && !empty($r['clientid'])) {
                    Db::saveLead($lid, ['clientid' => (int) $r['clientid']]);
                    Db::moveLead($lid, 'won', $adminId);
                    // πέρασε τον πελάτη και στις προσφορές του lead
                    Capsule::table('mod_cpm_offers')->where('lead_id', $lid)
                        ->whereNull('clientid')->update(['clientid' => (int) $r['clientid']]);
                    header('Location: ' . $link . '&tab=lead&id=' . $lid . '&converted=1');
                    return;
                }
                header('Location: ' . $link . '&tab=lead&id=' . $lid . '&cerr=' . urlencode($r['message'] ?? 'AddClient failed'));
                return;
            }
            header('Location: ' . $link . '&tab=lead&id=' . $lid);
            return;
        }
        if ($do === 'leadclient') {
            // Σύνδεση lead με ΥΠΑΡΧΟΝΤΑ πελάτη WHMCS
            $lid = (int) ($_POST['id'] ?? 0);
            $cid = (int) ($_POST['clientid'] ?? 0);
            if ($lid && $cid && Capsule::table('tblclients')->where('id', $cid)->exists()) {
                Db::saveLead($lid, ['clientid' => $cid]);
                Db::moveLead($lid, 'won', $adminId);
                Capsule::table('mod_cpm_offers')->where('lead_id', $lid)
                    ->whereNull('clientid')->update(['clientid' => $cid]);
            }
            header('Location: ' . $link . '&tab=lead&id=' . $lid);
            return;
        }
        if ($do === 'leadoffer') {
            // Νέα προσφορά δεμένη με το lead
            $lid = (int) ($_POST['id'] ?? 0);
            $l = Db::lead($lid);
            if ($l) {
                $oid = Db::saveOffer(0, [
                    'title'      => 'Προσφορά — ' . ($l->company ?: $l->contact),
                    'clientid'   => $l->clientid ?: null,
                    'lead_id'    => $lid,
                    'stage'      => 'draft',
                    'assignee'   => $l->assignee,
                    'created_by' => $adminId,
                ]);
                if ($l->stage === 'target' || $l->stage === 'contacted' || $l->stage === 'interested') {
                    Db::moveLead($lid, 'offer', $adminId);
                }
                header('Location: ' . $link . '&tab=offer&id=' . $oid . '&saved=1');
                return;
            }
            header('Location: ' . $link . '&tab=sales');
            return;
        }
        if ($do === 'saveoffer') {
            $oid = (int) ($_POST['id'] ?? 0);
            $stage = array_key_exists($_POST['stage'] ?? '', Db::offerStages()) ? $_POST['stage'] : 'new';
            $data = [
                'title'          => mb_substr(trim($_POST['title'] ?? ''), 0, 200) ?: 'Χωρίς τίτλο',
                'clientid'       => (int) ($_POST['clientid'] ?? 0) ?: null,
                'amount'         => ($_POST['amount'] ?? '') !== '' ? round((float) str_replace(',', '.', $_POST['amount']), 2) : null,
                'stage'          => $stage,
                'assignee'       => (int) ($_POST['assignee'] ?? 0) ?: null,
                'expected_close' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['expected_close'] ?? '') ? $_POST['expected_close'] : null,
                'descr'          => mb_substr(trim($_POST['descr'] ?? ''), 0, 60000),
                'closed_at'      => Db::offerStages()[$stage][2] ? date('Y-m-d H:i:s') : null,
            ];
            if (!$oid) {
                $data['created_by'] = $adminId;
            }
            $oid = Db::saveOffer($oid, $data);
            header('Location: ' . $link . '&tab=offer&id=' . $oid . '&saved=1');
            return;
        }
        if ($do === 'deleteoffer') {
            $oid = (int) ($_POST['id'] ?? 0);
            $o = Db::offer($oid);
            if ($o) {
                Db::deleteOffer($oid);
                if (function_exists('logActivity')) {
                    logActivity('CPM: διαγραφή προσφοράς #' . $oid . ' («' . $o->title . '») από admin #' . $adminId);
                }
            }
            header('Location: ' . $link . '&tab=offers');
            return;
        }
        if ($do === 'createquote') {
            $oid = (int) ($_POST['id'] ?? 0);
            $o = Db::offer($oid);
            if ($o && !$o->quoteid) {
                $item = ['desc' => $o->title, 'qty' => 1, 'up' => (float) ($o->amount ?? 0), 'discount' => 0, 'taxable' => true];
                $r = localAPI('CreateQuote', [
                    'subject'    => $o->title,
                    'stage'      => 'Draft',
                    'validuntil' => $o->expected_close ?: date('Y-m-d', strtotime('+30 days')),
                    'userid'     => (int) $o->clientid,
                    'lineitems'  => base64_encode(serialize([$item])),
                ], 'pdelis');
                if (($r['result'] ?? '') === 'success' && !empty($r['quoteid'])) {
                    Db::saveOffer($oid, ['quoteid' => (int) $r['quoteid'], 'stage' => 'draft']);
                    header('Location: quotes.php?action=manage&id=' . (int) $r['quoteid']);
                    return;
                }
                header('Location: ' . $link . '&tab=offer&id=' . $oid . '&qerr=' . urlencode($r['message'] ?? 'CreateQuote failed'));
                return;
            }
            header('Location: ' . $link . '&tab=offer&id=' . $oid);
            return;
        }
        if ($do === 'linkquote') {
            $oid = (int) ($_POST['id'] ?? 0);
            $qid = (int) ($_POST['quoteid'] ?? 0);
            if ($oid && $qid && Capsule::table('tblquotes')->where('id', $qid)->exists()) {
                Db::saveOffer($oid, ['quoteid' => $qid]);
            }
            header('Location: ' . $link . '&tab=offer&id=' . $oid);
            return;
        }
        if ($do === 'unlinkquote') {
            $oid = (int) ($_POST['id'] ?? 0);
            Db::saveOffer($oid, ['quoteid' => null]);
            header('Location: ' . $link . '&tab=offer&id=' . $oid);
            return;
        }
        if ($do === 'deletetask') {
            $tid = (int) ($_POST['taskid'] ?? 0);
            $t = Db::task($tid);
            if ($t && Db::canSeeTask($adminId, $t)) {
                Db::deleteTask($tid);
                if (function_exists('logActivity')) {
                    logActivity('CPM: διαγραφή task #' . $tid . ' («' . $t->title . '») από admin #' . $adminId);
                }
            }
            header('Location: ' . $link . '&tab=board' . ($t ? '&project=' . (int) $t->project_id : ''));
            return;
        }
    }

    echo cpm_styles($link);

    /* ---- App shell: σκούρο sidebar + top bar ---- */
    $groups = [
        'Εργασία'  => [
            'board'    => ['fa-columns', 'Board'],
            'mine'     => ['fa-user-check', 'Η δουλειά μου'],
            'list'     => ['fa-list-ul', 'Λίστα tasks'],
            'calendar' => ['fa-calendar-alt', 'Ημερολόγιο'],
            'time'     => ['fa-clock', 'Χρόνος'],
        ],
        'Πωλήσεις' => [
            'sales'    => ['fa-bullseye', 'Funnel & CRM'],
            'offers'   => ['fa-file-signature', 'Προσφορές'],
        ],
        'Διοίκηση' => [
            'client'   => ['fa-user', 'Πελάτης 360°'],
            'profit'   => ['fa-coins', 'Κερδοφορία'],
            'teams'    => ['fa-sitemap', 'Ομάδες'],
            'projects' => ['fa-folder-open', 'Projects'],
        ],
    ];
    if (!cpm_is_full()) {
        unset($groups['Διοίκηση']['client'], $groups['Διοίκηση']['profit']);
    }
    $activeTab = $tab;
    if ($tab === 'task' || $tab === 'drill') { $activeTab = 'board'; }
    if ($tab === 'offer') { $activeTab = 'offers'; }
    if ($tab === 'lead') { $activeTab = 'sales'; }
    $activeLabel = 'Board';
    foreach ($groups as $g) {
        if (isset($g[$activeTab])) { $activeLabel = $g[$activeTab][1]; }
    }

    echo '<div class="cnpapp"><aside class="cnp-side">';
    echo '<div class="cnp-brand"><span class="cnp-brand-ico"><i class="fas fa-layer-group"></i></span><span class="cnp-brand-t">Project<b>Manager</b><small>CloudOn</small></span></div>';
    foreach ($groups as $gLabel => $items) {
        if (!count($items)) { continue; }
        echo '<div class="cnp-side-g">' . cpm_e($gLabel) . '</div>';
        foreach ($items as $k => $m) {
            echo '<a class="cnp-side-a' . ($activeTab === $k ? ' on' : '') . '" href="' . $link . '&tab=' . $k . '">'
                . '<i class="fas ' . $m[0] . '"></i><span>' . $m[1] . '</span></a>';
        }
    }
    echo '<div class="cnp-side-g">Web App</div>';
    echo '<a class="cnp-side-a" href="' . $link . '&pmlaunch=1" target="_blank" title="Άνοιγμα του αυτόνομου app">'
        . '<i class="fas fa-rocket"></i><span>Άνοιγμα App ↗</span></a>';
    echo '</aside><main class="cnp-main">';

    // top bar: τίτλος + ημερομηνία + γρήγορες ενέργειες
    $days = ['Κυριακή', 'Δευτέρα', 'Τρίτη', 'Τετάρτη', 'Πέμπτη', 'Παρασκευή', 'Σάββατο'];
    $months = [1 => 'Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μαΐ', 'Ιουν', 'Ιουλ', 'Αυγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ'];
    echo '<header class="cnp-top"><div><h2>' . cpm_e($activeLabel) . '</h2>'
        . '<small>' . $days[(int) date('w')] . ' ' . (int) date('j') . ' ' . $months[(int) date('n')] . ' ' . date('Y')
        . ' · ' . cpm_e(Db::adminName($adminId)) . '</small></div>';
    echo '<div class="cnp-top-acts">'
        . '<a class="btn btn-default btn-sm" href="' . $link . '&tab=lead&id=0"><i class="fas fa-bullseye"></i> Νέο lead</a> '
        . '<a class="btn btn-primary btn-sm" href="' . $link . '&tab=task&id=0"><i class="fas fa-plus"></i> Νέο task</a>'
        . '</div></header>';

    try {
    switch ($tab) {
        case 'projects': cpm_tab_projects($link, $adminId); break;
        case 'list':     cpm_tab_list($link); break;
        case 'mine':     cpm_tab_list($link, $adminId); break;
        case 'time':     cpm_tab_time($link); break;
        case 'calendar': cpm_tab_calendar($link); break;
        case 'offers':   cpm_tab_offers($link); break;
        case 'client':   cpm_tab_client($link); break;
        case 'sales':    cpm_tab_sales($link); break;
        case 'teams':    cpm_tab_teams($link, $adminId); break;
        case 'profit':   cpm_tab_profit($link); break;
        case 'drill':    cpm_tab_drill($link, $adminId); break;
        case 'lead':     cpm_tab_lead($link, $adminId); break;
        case 'offer':    cpm_tab_offer($link, $adminId); break;
        case 'task':     cpm_tab_task($link, $adminId); break;
        default:         cpm_tab_board($link, $adminId); break;
    }
    } catch (\Throwable $e) {
        // ποτέ λευκή σελίδα: εμφάνισε το σφάλμα καθαρά
        echo '<div class="alert alert-danger"><b><i class="fas fa-bug"></i> Σφάλμα στο κύκλωμα «' . cpm_e($tab) . '»:</b> '
            . cpm_e($e->getMessage()) . ' <small class="text-muted">(' . cpm_e(basename($e->getFile()) . ':' . $e->getLine()) . ')</small></div>';
        if (function_exists('logActivity')) {
            logActivity('CPM ERROR [' . $tab . ']: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        }
    }
    echo '</main></div>'; // .cnp-main .cpm
}

/* ------------------------------------------------------------------ */
/* Styles + board JS                                                  */
/* ------------------------------------------------------------------ */

function cpm_styles($link = '')
{
    return '<script>
var CPM_LINK = ' . json_encode($link) . ';
/* ── PJAX: ομαλή πλοήγηση χωρίς full reload ── */
(function(){
  if (window.cnpPjaxInit) return; window.cnpPjaxInit = 1;
  var busy = false;
  function bar(on){
    var b = document.getElementById("cnpBar");
    if (on) {
      if (!b) { b = document.createElement("div"); b.id = "cnpBar"; document.body.appendChild(b); }
      b.style.width = "70%";
    } else if (b) { b.style.width = "100%"; setTimeout(function(){ b.remove(); }, 250); }
  }
  function runScripts(root){
    root.querySelectorAll("script").forEach(function(s){
      var n = document.createElement("script"); n.textContent = s.textContent; s.replaceWith(n);
    });
  }
  function swap(url, push){
    if (busy) return; busy = true; bar(true);
    var main = document.querySelector(".cnp-main");
    if (main) main.classList.add("cnp-loading");
    fetch(url, {credentials: "same-origin"}).then(function(r){ return r.text(); }).then(function(html){
      var doc = new DOMParser().parseFromString(html, "text/html");
      var nm = doc.querySelector(".cnp-main"), ns = doc.querySelector(".cnp-side");
      var cm = document.querySelector(".cnp-main"), cs = document.querySelector(".cnp-side");
      if (!nm || !cm) { window.location.href = url; return; }
      cm.innerHTML = nm.innerHTML;
      if (ns && cs) cs.innerHTML = ns.innerHTML;
      runScripts(cm);
      if (push) history.pushState({cnp: 1}, "", url);
      cm.classList.remove("cnp-loading");
      cm.classList.remove("cnp-enter"); void cm.offsetWidth; cm.classList.add("cnp-enter");
      window.scrollTo(0, 0); bar(false); busy = false;
    }).catch(function(){ window.location.href = url; });
  }
  document.addEventListener("click", function(e){
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
    var a = e.target.closest ? e.target.closest("a") : null;
    if (!a || !a.href || a.getAttribute("target")) return;
    if (!a.closest(".cnpapp")) return;                       // μόνο μέσα στην εφαρμογή
    if (a.href.indexOf("module=cloudonprojects") === -1) return; // εξωτερικά (tickets κ.λπ.) → κανονικά
    if (a.href.indexOf("do=") !== -1) return;                // action links → full load
    if (a.getAttribute("href").charAt(0) === "#") return;
    e.preventDefault(); swap(a.href, true);
  });
  window.addEventListener("popstate", function(){
    if (location.href.indexOf("module=cloudonprojects") !== -1) swap(location.href, false);
  });
})();
function cnpClientAuto(id){
  var inp=document.getElementById(id),dl=document.getElementById(id+"l"),hid=document.getElementById(id+"v"),t;
  if(!inp)return;
  inp.addEventListener("input",function(){
    var m=inp.value.match(/\(#(\d+)\)\s*$/);
    hid.value=m?m[1]:(/^\d+$/.test(inp.value.trim())?inp.value.trim():"0");
    if(inp.value.trim()==="")hid.value="0";
    clearTimeout(t);var q=inp.value.trim();
    if(q.length<2||m)return;
    t=setTimeout(function(){
      fetch(CPM_LINK+"&do=clientsearch&q="+encodeURIComponent(q)).then(function(r){return r.json();}).then(function(list){
        dl.innerHTML="";list.forEach(function(o){var op=document.createElement("option");op.value=o.label;dl.appendChild(op);});
      });
    },250);
  });
}
</script><style>
/* ═══════════════ CloudOn Project Manager — UI v3 (app shell) ═══════════════ */
.cnpapp{--ink:#15243a;--txt:#41536e;--mut:#8595ad;--line:#e6ebf2;--canvas:#f4f6fa;--card:#fff;
  --brand:#0090dd;--brand-d:#0072ad;--ok:#16a26a;--warn:#eba63c;--bad:#e2515f;--violet:#7b5cd6;
  --side:#152238;--side2:#1c2c47;--r:14px;
  --sh:0 1px 2px rgba(16,34,58,.05),0 10px 26px -16px rgba(16,34,58,.18);
  display:flex;align-items:stretch;margin:0 -5px;border-radius:18px;overflow:hidden;
  box-shadow:0 4px 30px -12px rgba(16,34,58,.25);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  font-size:13.5px;color:var(--txt);min-height:calc(100vh - 190px)}
.cnpapp b,.cnpapp strong{color:var(--ink)}
.cnpapp h2,.cnpapp h3,.cnpapp h4{color:var(--ink);font-weight:800;letter-spacing:-.3px}
.cnpapp a{color:var(--brand-d)}
.cnpapp ::selection{background:#cfe9fa}

/* ── sidebar ── */
.cnp-side{width:216px;flex:0 0 216px;background:linear-gradient(180deg,var(--side) 0%,#101b30 100%);
  padding:18px 12px 20px;display:flex;flex-direction:column;gap:1px}
.cnp-brand{display:flex;align-items:center;gap:10px;padding:2px 8px 16px;border-bottom:1px solid rgba(255,255,255,.07);margin-bottom:12px}
.cnp-brand-ico{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#00a6ff,#0072ad);
  display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;box-shadow:0 4px 10px rgba(0,144,221,.4)}
.cnp-brand-t{color:#e8eef7;font-size:14px;font-weight:500;line-height:1.1}
.cnp-brand-t b{color:#fff;font-weight:800}
.cnp-brand-t small{display:block;color:#5f7194;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;margin-top:2px}
.cnp-side-g{color:#5f7194;font-size:10px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;padding:14px 10px 5px}
.cnp-side-a{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:10px;color:#aebbd1;
  font-size:13px;font-weight:600;text-decoration:none;transition:all .13s}
.cnp-side-a i{width:17px;text-align:center;font-size:13px;opacity:.75}
.cnp-side-a:hover{background:rgba(255,255,255,.06);color:#fff;text-decoration:none}
.cnp-side-a.on{background:linear-gradient(90deg,#0090dd 0%,#0072ad 100%);color:#fff;box-shadow:0 4px 12px rgba(0,144,221,.35)}
.cnp-side-a.on i{opacity:1}

/* ── main + top bar ── */
.cnp-main{flex:1;min-width:0;background:var(--canvas);padding:18px 22px 28px;transition:opacity .14s}
.cnp-main.cnp-loading{opacity:.45;pointer-events:none}
.cnp-main.cnp-enter{animation:cnpEnter .2s ease}
@keyframes cnpEnter{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}
#cnpBar{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#00a6ff,#0090dd);
  z-index:2000;transition:width .3s ease;border-radius:0 3px 3px 0;box-shadow:0 0 8px rgba(0,144,221,.6)}
.cnp-top{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:18px;flex-wrap:wrap}
.cnp-top h2{margin:0;font-size:21px}
.cnp-top small{color:var(--mut);font-weight:600}
.cnp-top-acts{display:flex;gap:8px;align-items:center}

/* ── κάρτες (panels) ── */
.cnpapp .panel{border:none;border-radius:var(--r);box-shadow:var(--sh);margin-bottom:16px;background:var(--card)}
.cnpapp .panel-heading{background:transparent;border-bottom:1px solid var(--line);color:var(--ink);
  font-weight:700;padding:13px 18px;border-radius:var(--r) var(--r) 0 0}
.cnpapp .panel-body{padding:15px 18px}
.cnpapp .panel-info{box-shadow:var(--sh),inset 0 3px 0 var(--brand)}
.cnpapp .panel-success{box-shadow:var(--sh),inset 0 3px 0 var(--ok)}
.cnpapp .panel-warning{box-shadow:var(--sh),inset 0 3px 0 var(--warn)}
.cnpapp .panel-danger{box-shadow:var(--sh),inset 0 3px 0 var(--bad)}

/* ── πίνακες ── */
.cnpapp .table{margin-bottom:0}
.cnpapp .table>thead>tr>th{border-bottom:1px solid var(--line);border-top:none;font-size:10.5px;
  text-transform:uppercase;letter-spacing:.8px;color:var(--mut);font-weight:700;padding:10px 14px;background:transparent}
.cnpapp .table>tbody>tr>td{border-top:1px solid #f1f4f9;padding:10px 14px;vertical-align:middle}
.cnpapp .table-bordered,.cnpapp .table-bordered>tbody>tr>td,.cnpapp .table-bordered>thead>tr>th{border-left:none;border-right:none}
.cnpapp .table-bordered{border:none;border-radius:var(--r);box-shadow:var(--sh);background:var(--card);overflow:hidden}
.cnpapp .table-striped>tbody>tr:nth-of-type(odd){background:#fafcfe}
.cnpapp .table>tbody>tr:hover{background:#f1f7fd}

/* ── κουμπιά ── */
.cnpapp .btn{border-radius:10px;font-weight:600;box-shadow:none;transition:all .13s;border-width:1px}
.cnpapp .btn-primary{background:var(--brand);border-color:var(--brand)}
.cnpapp .btn-primary:hover{background:var(--brand-d);border-color:var(--brand-d);transform:translateY(-1px);box-shadow:0 4px 10px rgba(0,144,221,.3)}
.cnpapp .btn-default{background:#fff;border-color:#d6deea;color:var(--txt)}
.cnpapp .btn-default:hover{border-color:var(--brand);color:var(--brand-d);background:#f5fbff}
.cnpapp .btn-success{background:var(--ok);border-color:var(--ok)}
.cnpapp .btn-warning{background:var(--warn);border-color:var(--warn);color:#fff}
.cnpapp .btn-danger{background:var(--bad);border-color:var(--bad)}
.cnpapp .btn-info{background:#e5f4fd;border-color:#bfe3f7;color:var(--brand-d)}
.cnpapp .btn-link{box-shadow:none}

/* ── φόρμες ── */
.cnpapp .form-control{border-radius:10px;border:1px solid #d6deea;box-shadow:none;color:var(--ink);background:#fff;
  transition:border-color .13s,box-shadow .13s}
.cnpapp .form-control:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(0,144,221,.13)}
.cnpapp select.form-control{cursor:pointer}
.cnpapp .control-label{color:var(--mut);font-weight:600;font-size:12px}
.cnpapp textarea.form-control{border-radius:12px}

/* ── badges / alerts / progress ── */
.cnpapp .label{border-radius:999px;padding:3.5px 11px;font-weight:600;font-size:10.5px;letter-spacing:.2px}
.cnpapp .label-success{background:#e0f5eb;color:#0d7a4e}
.cnpapp .label-warning{background:#fcf1dc;color:#96660f}
.cnpapp .label-danger{background:#fce7ea;color:#af2837}
.cnpapp .label-info{background:#e1f1fc;color:#04649c}
.cnpapp .label-default{background:#eaeff6;color:#57677f}
.cnpapp .label-primary{background:var(--brand)}
.cnpapp .alert{border:none;border-radius:12px;box-shadow:var(--sh)}
.cnpapp .alert-info{background:#e8f4fd;color:#0b5e8c}
.cnpapp .alert-success{background:#e2f6ec;color:#0d7a4e}
.cnpapp .alert-warning{background:#fcf3df;color:#8d6410}
.cnpapp .alert-danger{background:#fce9ec;color:#a02532}
.cnpapp .progress{border-radius:999px;background:#e7ecf4;box-shadow:none;overflow:hidden}
.cnpapp .progress-bar{border-radius:999px;box-shadow:none}

/* ── KPI stat cards ── */
.cnp-stat{background:var(--card);border:none;border-radius:var(--r);text-align:left;padding:15px 16px 13px 21px;
  margin-bottom:14px;box-shadow:var(--sh);position:relative;overflow:hidden;display:block;color:var(--txt);text-decoration:none}
.cnp-stat::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:#c9d4e3;border-radius:0 4px 4px 0}
.cnp-stat b{font-size:25px;display:block;line-height:1.12;color:var(--ink);font-variant-numeric:tabular-nums;letter-spacing:-.6px}
.cnp-stat small{color:var(--mut);font-size:11.5px;font-weight:600}
.cnp-stat.ok::before{background:var(--ok)}
.cnp-stat.warn::before{background:var(--warn)}
.cnp-stat.bad::before{background:var(--bad)}
.cnp-stat.info::before{background:var(--brand)}
a.cnp-stat{cursor:pointer;transition:box-shadow .14s,transform .14s}
a.cnp-stat:hover{text-decoration:none;color:var(--txt);transform:translateY(-2px);box-shadow:0 10px 24px -12px rgba(16,34,58,.3)}
a.cnp-stat::after{content:"→";position:absolute;right:14px;top:12px;color:#c9d4e3;font-weight:700;transition:all .14s}
a.cnp-stat:hover::after{color:var(--brand);transform:translateX(3px)}
.cnp-target{background:var(--card);border:none;border-radius:var(--r);padding:14px 18px;margin-bottom:14px;box-shadow:var(--sh)}
.cnp-target .bar{height:10px;border-radius:999px;background:#e7ecf4;overflow:hidden;margin-top:8px}
.cnp-target .bar>span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--brand),var(--ok))}

/* ── kanban ── */
.cnp-board{display:flex;gap:13px;align-items:stretch;overflow-x:auto;padding-bottom:12px;min-height:calc(100vh - 560px)}
.cnp-board::-webkit-scrollbar{height:9px}
.cnp-board::-webkit-scrollbar-thumb{background:#cbd6e4;border-radius:99px}
.cnp-col{background:#e8edf4;border:none;border-radius:var(--r);flex:1 1 272px;min-width:256px;display:flex;flex-direction:column}
.cnp-col-h{padding:12px 15px 9px;font-weight:700;font-size:13px;color:var(--ink);border-bottom:3px solid;
  display:flex;justify-content:space-between;align-items:center;background:transparent;border-radius:var(--r) var(--r) 0 0}
.cnp-col-n{background:#fff;color:var(--mut);border-radius:999px;padding:1px 9px;font-size:11px;font-weight:700;box-shadow:0 1px 2px rgba(16,34,58,.12)}
.cnp-cards{padding:10px;min-height:50px;display:flex;flex-direction:column;gap:9px;flex:1}
.cnp-card{background:#fff;border:1px solid transparent;border-radius:12px;padding:11px 13px;cursor:grab;
  box-shadow:0 1px 3px rgba(16,34,58,.1);display:block;color:var(--ink);text-decoration:none;
  transition:box-shadow .14s,transform .14s,border-color .14s}
.cnp-card:hover{border-color:var(--brand);text-decoration:none;color:var(--ink);box-shadow:0 7px 18px rgba(16,34,58,.17);transform:translateY(-2px)}
.cnp-card.dragging{opacity:.5;transform:rotate(1.5deg) scale(1.02)}
.cnp-card-t{font-weight:600;font-size:13px;margin-bottom:7px;line-height:1.4;color:var(--ink)}
.cnp-card-m{display:flex;gap:7px;align-items:center;flex-wrap:wrap;font-size:11px;color:var(--mut)}
.cnp-dot{width:9px;height:9px;border-radius:50%;display:inline-block;flex:0 0 auto}
.cnp-ava{background:linear-gradient(135deg,#00a6ff,#0072ad);color:#fff;border-radius:50%;width:22px;height:22px;
  display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;box-shadow:0 1px 3px rgba(0,114,173,.4)}
.cnp-col.dragover{outline:2px dashed var(--brand);outline-offset:-3px;background:#dfedfa}
.cnp-quick{display:flex;gap:5px;margin:2px 10px 12px}
.cnp-quick input{flex:1;border:1.5px dashed #c1cddd;border-radius:10px;background:transparent;padding:6px 11px;font-size:12px;color:var(--txt);transition:all .13s}
.cnp-quick input::placeholder{color:#9aa8bd}
.cnp-quick input:focus{outline:none;border-color:var(--brand);background:#fff;border-style:solid;box-shadow:0 0 0 3px rgba(0,144,221,.12)}
.cnp-quick button,.cnp-quick a{border:none;background:transparent;color:var(--brand);cursor:pointer;padding:0 6px}

/* ── διάφορα ── */
.cnp-due-over{color:var(--bad);font-weight:700}
.cnp-card--over{box-shadow:inset 3px 0 0 var(--bad),0 1px 3px rgba(16,34,58,.1)}
.cnp-ball{background:#fdf3d7;color:#8a6d1a;border-radius:999px;padding:2px 8px;font-size:10.5px;font-weight:700}
.cnp-ball--me{background:#fdeaea;color:#c0392b;animation:cnpPulse 2s infinite}
@keyframes cnpPulse{0%,100%{opacity:1}50%{opacity:.55}}

/* ── ημερολόγιο ── */
.cnp-cal{width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed;background:var(--card);
  border-radius:var(--r);overflow:hidden;box-shadow:var(--sh)}
.cnp-cal th{background:#f6f8fb;border-bottom:1px solid var(--line);padding:9px;font-size:10.5px;text-align:center;
  text-transform:uppercase;letter-spacing:.8px;color:var(--mut)}
.cnp-cal td{border-bottom:1px solid #f1f4f9;border-right:1px solid #f1f4f9;vertical-align:top;height:100px;padding:6px;font-size:11px}
.cnp-cal td:last-child{border-right:none}
.cnp-cal td.other{background:#fafbfd;color:#c1cddd}
.cnp-cal td.today{background:#e9f5fd;box-shadow:inset 0 2px 0 var(--brand)}
.cnp-cal .d{font-weight:700;font-size:12px;margin-bottom:4px;color:var(--ink)}
.cnp-cal a.ev{display:block;color:var(--ink);text-decoration:none;padding:2px 6px;border-radius:6px;margin-bottom:3px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:#eef2f8;border-left:3px solid;transition:background .12s}
.cnp-cal a.ev:hover{background:#dfeaf6}
.cnp-cal a.ev.done{opacity:.4;text-decoration:line-through}
.cnp-cal a.ev.over{background:#fce9ec}

/* ── responsive ── */
@media (max-width:1150px){
  .cnp-side{width:60px;flex:0 0 60px;padding:14px 8px}
  .cnp-brand-t,.cnp-side-g,.cnp-side-a span{display:none}
  .cnp-side-a{justify-content:center;padding:11px 0}
  .cnp-brand{justify-content:center;padding-bottom:12px}
}
</style>';
}

function cpm_board_js($link)
{
    return '<script>
(function(){
  var dragId=null;
  document.querySelectorAll(".cnp-card[draggable]").forEach(function(c){
    c.addEventListener("dragstart",function(e){dragId=c.getAttribute("data-task");c.classList.add("dragging");});
    c.addEventListener("dragend",function(){c.classList.remove("dragging");});
  });
  document.querySelectorAll(".cnp-col").forEach(function(col){
    col.addEventListener("dragover",function(e){e.preventDefault();col.classList.add("dragover");});
    col.addEventListener("dragleave",function(){col.classList.remove("dragover");});
    col.addEventListener("drop",function(e){
      e.preventDefault();col.classList.remove("dragover");
      if(!dragId)return;
      var sid=col.getAttribute("data-status");
      var card=document.querySelector(".cnp-card[data-task=\'"+dragId+"\']");
      var fd=new FormData();fd.append("do","movetask");fd.append("taskid",dragId);fd.append("statusid",sid);
      fetch("' . $link . '",{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(j){
        if(j.ok&&card){ col.querySelector(".cnp-cards").appendChild(card);
          col.querySelectorAll(".cnp-col-n").forEach(function(){});
          document.querySelectorAll(".cnp-col").forEach(function(c2){
            c2.querySelector(".cnp-col-n").textContent=c2.querySelectorAll(".cnp-card").length;});
        }
      });
      dragId=null;
    });
  });
})();
</script>';
}

/* ------------------------------------------------------------------ */
/* Board KPIs — tickets/SLA/παραγωγικότητα ημέρας                     */
/* ------------------------------------------------------------------ */

function cpm_board_kpis($link, $adminId)
{
    $today = date('Y-m-d 00:00:00');
    $full = cpm_is_full();

    /* ---- βασικά νούμερα tickets ---- */
    $openQ = Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled']);
    $closedQ = Capsule::table('tbltickets')->where('status', 'Closed')->where('lastreply', '>=', $today);
    if (!$full) {
        $openQ->where('flag', $adminId);
        $closedQ->where('flag', $adminId);
    }
    $open = $openQ->count();
    $closedToday = $closedQ->count();

    // καθυστερημένα ως προς SLA (supportcontracts: προθεσμία πέρασε χωρίς πρώτη απάντηση)
    $slaOver = null;
    try {
        if (Capsule::schema()->hasTable('mod_supportcontracts_tickets')) {
            $q = Capsule::table('mod_supportcontracts_tickets as st')
                ->join('tbltickets as t', 't.id', '=', 'st.ticketid')
                ->whereNotIn('t.status', ['Closed', 'Cancelled'])
                ->whereNotNull('st.sla_due')->where('st.sla_due', '<', date('Y-m-d H:i:s'))
                ->whereNull('st.first_response_at');
            if (!$full) {
                $q->where('t.flag', $adminId);
            }
            $slaOver = $q->count();
        }
    } catch (\Throwable $e) {
    }

    /* ---- παραγωγικότητα σήμερα ανά agent ---- */
    $admins = [];
    foreach (Db::admins() as $a) {
        $admins[(int) $a->id] = ['name' => trim($a->firstname . ' ' . $a->lastname),
                                 'open' => 0, 'replies' => 0, 'done' => 0, 'mins' => 0];
    }
    // ανοιχτά ανά agent (flag)
    foreach (Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])
        ->selectRaw('flag, COUNT(*) n')->groupBy('flag')->get() as $r) {
        if (isset($admins[(int) $r->flag])) {
            $admins[(int) $r->flag]['open'] = (int) $r->n;
        }
    }
    $unassigned = (int) Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])
        ->where(function ($w) { $w->where('flag', 0)->orWhereNull('flag'); })->count();
    // απαντήσεις σήμερα (tblticketreplies.admin = ονοματεπώνυμο)
    $nameToId = [];
    foreach ($admins as $id => $a) {
        $nameToId[$a['name']] = $id;
    }
    foreach (Capsule::table('tblticketreplies')->where('date', '>=', $today)->where('admin', '!=', '')
        ->selectRaw('admin, COUNT(*) n')->groupBy('admin')->get() as $r) {
        if (isset($nameToId[$r->admin])) {
            $admins[$nameToId[$r->admin]]['replies'] = (int) $r->n;
        }
    }
    // tasks που ολοκληρώθηκαν σήμερα
    foreach (Capsule::table('mod_cpm_tasks')->where('completed_at', '>=', $today)->whereNotNull('assignee')
        ->selectRaw('assignee, COUNT(*) n')->groupBy('assignee')->get() as $r) {
        if (isset($admins[(int) $r->assignee])) {
            $admins[(int) $r->assignee]['done'] = (int) $r->n;
        }
    }
    // χρόνος που καταχωρήθηκε σήμερα
    foreach (Capsule::table('mod_cpm_timelogs')->where('running', 0)->where('created_at', '>=', $today)
        ->selectRaw('admin_id, SUM(minutes) m')->groupBy('admin_id')->get() as $r) {
        if (isset($admins[(int) $r->admin_id])) {
            $admins[(int) $r->admin_id]['mins'] = (int) $r->m;
        }
    }
    // score: απάντηση=1, task=2, 30΄ εργασίας=1
    $totalActions = 0;
    foreach ($admins as $id => &$a) {
        $a['score'] = $a['replies'] + $a['done'] * 2 + (int) floor($a['mins'] / 30);
        $totalActions += $a['replies'] + $a['done'];
    }
    unset($a);
    // κράτα μόνο agents με δραστηριότητα ή ανοιχτά
    $active = array_filter($admins, function ($a) {
        return $a['open'] || $a['replies'] || $a['done'] || $a['mins'];
    });
    uasort($active, function ($x, $y) {
        return $y['score'] <=> $x['score'];
    });
    $topId = null;
    foreach ($active as $id => $a) {
        if ($a['score'] > 0) {
            $topId = $id;
        }
        break;
    }

    /* ---- render (κλικ → drill-down λίστα) ---- */
    echo '<div class="row" style="margin-bottom:2px">';
    $cards = [
        ['Ανοιχτά tickets' . ($full ? '' : ' (δικά σου)'), (string) $open, $open ? 'info' : 'ok', $link . '&tab=drill&k=open'],
        ['Εκτός SLA (χωρίς απάντηση)', $slaOver === null ? '—' : (string) $slaOver, $slaOver ? 'bad' : 'ok', $link . '&tab=drill&k=sla'],
        ['Έκλεισαν σήμερα', (string) $closedToday, $closedToday ? 'ok' : 'info', $link . '&tab=drill&k=closed'],
        ['Ενέργειες σήμερα (απαντήσεις+tasks)', (string) $totalActions, $totalActions ? 'ok' : 'warn', $link . '&tab=drill&k=actions'],
    ];
    foreach ($cards as $c) {
        echo '<div class="col-sm-3"><a class="cnp-stat ' . $c[2] . '" href="' . $c[3] . '"><b>' . cpm_e($c[1]) . '</b><small>' . cpm_e($c[0]) . '</small></a></div>';
    }
    echo '</div>';

    /* ---- δεύτερη σειρά KPIs (ιδέες: αναμονή απάντησης, ηλικία, χρεώσιμα, SLA 7ημ) ---- */
    if ($full) {
        // περιμένουν απάντησή μας: τελευταία απάντηση από πελάτη (ή καμία απάντηση)
        $waiting = 0;
        foreach (Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])->get(['id']) as $tk) {
            $lastAdmin = Capsule::table('tblticketreplies')->where('tid', $tk->id)->orderBy('id', 'desc')->value('admin');
            if ($lastAdmin === null || $lastAdmin === '') {
                $waiting++;
            }
        }
        // ξεχασμένα: ανοιχτά πάνω από 7 ημέρες
        $staleN = Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])
            ->where('date', '<', date('Y-m-d H:i:s', strtotime('-7 days')))->count();
        // χρεώσιμος χρόνος εβδομάδας (SC worklog: tickets + tasks μαζί)
        $billTxt = '—';
        try {
            if (Capsule::schema()->hasTable('mod_supportcontracts_worklog')) {
                $monday = date('Y-m-d 00:00:00', strtotime('monday this week'));
                $bill = (int) Capsule::table('mod_supportcontracts_worklog')
                    ->where('billable', 1)->where('created_at', '>=', $monday)->sum('charged_minutes');
                $billTxt = Time::fmt($bill);
            }
        } catch (\Throwable $e) {
        }
        // SLA επίδοση 7 ημερών (% εντός προθεσμίας από όσα απαντήθηκαν)
        $slaTxt = '—';
        $slaCls = 'info';
        try {
            if (Capsule::schema()->hasTable('mod_supportcontracts_tickets')) {
                $wk = Capsule::table('mod_supportcontracts_tickets')
                    ->whereNotNull('sla_met')->where('first_response_at', '>=', date('Y-m-d', strtotime('-7 days')));
                $tot = (clone $wk)->count();
                if ($tot > 0) {
                    $met = (clone $wk)->where('sla_met', 1)->count();
                    $pct = (int) round($met / $tot * 100);
                    $slaTxt = $pct . '%';
                    $slaCls = $pct >= 90 ? 'ok' : ($pct >= 70 ? 'warn' : 'bad');
                }
            }
        } catch (\Throwable $e) {
        }
        echo '<div class="row" style="margin-bottom:2px">';
        $monday = date('Y-m-d', strtotime('monday this week'));
        foreach ([
            ['Περιμένουν απάντησή μας', (string) $waiting, $waiting ? 'warn' : 'ok', $link . '&tab=drill&k=waiting'],
            ['Ανοιχτά πάνω από 7 ημέρες', (string) $staleN, $staleN ? 'bad' : 'ok', $link . '&tab=drill&k=stale'],
            ['Χρεώσιμα εβδομάδας', $billTxt, 'info', $link . '&tab=time&from=' . $monday . '&to=' . date('Y-m-d')],
            ['SLA επίδοση 7ημ.', $slaTxt, $slaCls, $link . '&tab=drill&k=sla7'],
        ] as $c) {
            echo '<div class="col-sm-3"><a class="cnp-stat ' . $c[2] . '" href="' . $c[3] . '"><b>' . cpm_e($c[1]) . '</b><small>' . cpm_e($c[0]) . '</small></a></div>';
        }
        echo '</div>';
    }

    if ($full && (count($active) || $unassigned)) {
        echo '<div class="panel panel-default" style="margin-bottom:14px"><div class="panel-heading" style="padding:6px 12px"><b><i class="fas fa-users"></i> Ανά agent σήμερα</b>'
            . ($unassigned ? ' <span class="label label-warning" style="margin-left:8px">' . $unassigned . ' tickets χωρίς ανάθεση</span>' : '')
            . '</div><table class="table table-condensed" style="margin:0;font-size:12px"><thead><tr>'
            . '<th>Agent</th><th>Ομάδα</th><th>Ανοιχτά tickets</th><th>Απαντήσεις σήμερα</th><th>Tasks ολοκληρώθηκαν</th><th>Χρόνος σήμερα</th><th>Score</th></tr></thead><tbody>';
        if (!count($active)) {
            echo '<tr><td colspan="7" class="text-center text-muted">Καμία δραστηριότητα agent σήμερα — ανάθεσε τα tickets (flag) για να μετρούν ανά agent.</td></tr>';
        }
        $teamMap = Db::adminTeamMap();
        foreach ($active as $id => $a) {
            $isTop = ($id === $topId);
            echo '<tr' . ($isTop ? ' class="success"' : '') . '><td>' . ($isTop ? '🏆 ' : '') . '<b>' . cpm_e($a['name']) . '</b>'
                . ($isTop ? ' <small class="text-success">πιο παραγωγικός σήμερα</small>' : '') . '</td>'
                . '<td><small>' . cpm_e($teamMap[$id] ?? '—') . '</small></td>'
                . '<td>' . $a['open'] . '</td><td>' . $a['replies'] . '</td><td>' . $a['done'] . '</td>'
                . '<td>' . ($a['mins'] ? cpm_e(Time::fmt($a['mins'])) : '—') . '</td>'
                . '<td><b>' . $a['score'] . '</b></td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

/* ------------------------------------------------------------------ */
/* KPI drill-down — λίστες αποτελεσμάτων πίσω από κάθε κάρτα          */
/* ------------------------------------------------------------------ */

function cpm_tab_drill($link, $adminId)
{
    $k = $_GET['k'] ?? 'open';
    $full = cpm_is_full();
    $today = date('Y-m-d 00:00:00');
    $titles = [
        'open'    => 'Ανοιχτά tickets',
        'sla'     => 'Εκτός SLA — χωρίς πρώτη απάντηση',
        'closed'  => 'Έκλεισαν σήμερα',
        'actions' => 'Ενέργειες σήμερα',
        'waiting' => 'Περιμένουν απάντησή μας',
        'stale'   => 'Ανοιχτά πάνω από 7 ημέρες',
        'sla7'    => 'SLA επίδοση — τελευταίες 7 ημέρες',
    ];
    echo '<a href="' . $link . '&tab=board" class="btn btn-default btn-xs" style="margin-bottom:12px">&larr; Board</a>';
    echo '<h4 style="margin:0 0 14px"><b>' . cpm_e($titles[$k] ?? 'Αποτελέσματα') . '</b></h4>';

    // helper: πίνακας tickets
    $ticketTable = function ($rows, $extraHead = '', $extraCell = null) {
        echo '<div class="panel panel-default"><table class="table table-condensed" style="margin:0"><thead><tr>'
            . '<th>Ticket</th><th>Πελάτης</th><th>Κατάσταση</th><th>Προτερ/τα</th><th>Ηλικία</th><th>Τελ. κίνηση</th>' . $extraHead . '</tr></thead><tbody>';
        if (!count($rows)) {
            echo '<tr><td colspan="9" class="text-center text-muted" style="padding:22px">Τίποτα εδώ 🎉</td></tr>';
        }
        foreach ($rows as $r) {
            $age = (int) floor((time() - strtotime($r->date)) / 86400);
            echo '<tr><td><a href="supporttickets.php?action=view&id=' . (int) $r->id . '"><b>#' . cpm_e($r->tid) . '</b> '
                . cpm_e(mb_substr($r->title, 0, 65)) . '</a></td>'
                . '<td>' . cpm_e($r->userid ? cpm_client_label($r->userid) : '—') . '</td>'
                . '<td><span class="label label-info">' . cpm_e($r->status) . '</span></td>'
                . '<td>' . cpm_e($r->urgency ?: '—') . '</td>'
                . '<td' . ($age >= 7 ? ' class="cnp-due-over"' : '') . '>' . $age . ' ημ.</td>'
                . '<td>' . cpm_e(date('d/m H:i', strtotime($r->lastreply))) . '</td>'
                . ($extraCell ? $extraCell($r) : '') . '</tr>';
        }
        echo '</tbody></table></div>';
    };

    switch ($k) {
        case 'open':
        case 'stale':
        case 'closed':
            $q = Capsule::table('tbltickets');
            if ($k === 'open') {
                $q->whereNotIn('status', ['Closed', 'Cancelled'])->orderBy('lastreply', 'desc');
            } elseif ($k === 'stale') {
                $q->whereNotIn('status', ['Closed', 'Cancelled'])
                  ->where('date', '<', date('Y-m-d H:i:s', strtotime('-7 days')))->orderBy('date');
            } else {
                $q->where('status', 'Closed')->where('lastreply', '>=', $today)->orderBy('lastreply', 'desc');
            }
            if (!$full) {
                $q->where('flag', $adminId);
            }
            $ticketTable($q->limit(200)->get(['id', 'tid', 'title', 'status', 'urgency', 'date', 'lastreply', 'userid']));
            break;

        case 'sla':
            $q = Capsule::table('mod_supportcontracts_tickets as st')
                ->join('tbltickets as t', 't.id', '=', 'st.ticketid')
                ->whereNotIn('t.status', ['Closed', 'Cancelled'])
                ->whereNotNull('st.sla_due')->where('st.sla_due', '<', date('Y-m-d H:i:s'))
                ->whereNull('st.first_response_at')
                ->select('t.id', 't.tid', 't.title', 't.status', 't.urgency', 't.date', 't.lastreply', 't.userid', 'st.sla_due')
                ->orderBy('st.sla_due');
            if (!$full) {
                $q->where('t.flag', $adminId);
            }
            $ticketTable($q->limit(200)->get(), '<th>Προθεσμία SLA</th>', function ($r) {
                return '<td class="cnp-due-over">' . cpm_e(date('d/m H:i', strtotime($r->sla_due))) . ' ⚠</td>';
            });
            break;

        case 'waiting':
            $rows = [];
            $q = Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled']);
            if (!$full) {
                $q->where('flag', $adminId);
            }
            foreach ($q->orderBy('lastreply')->get(['id', 'tid', 'title', 'status', 'urgency', 'date', 'lastreply', 'userid']) as $tk) {
                $lastAdmin = Capsule::table('tblticketreplies')->where('tid', $tk->id)->orderBy('id', 'desc')->value('admin');
                if ($lastAdmin === null || $lastAdmin === '') {
                    $rows[] = $tk;
                }
            }
            $ticketTable($rows);
            break;

        case 'actions':
            echo '<div class="panel panel-default"><div class="panel-heading"><b>Απαντήσεις σε tickets σήμερα</b></div>'
                . '<table class="table table-condensed" style="margin:0"><thead><tr><th>Ώρα</th><th>Agent</th><th>Ticket</th></tr></thead><tbody>';
            $reps = Capsule::table('tblticketreplies as r')->join('tbltickets as t', 't.id', '=', 'r.tid')
                ->where('r.date', '>=', $today)->where('r.admin', '!=', '')
                ->select('r.date', 'r.admin', 't.id as ticket_id', 't.tid', 't.title')->orderBy('r.date', 'desc')->get();
            if (!count($reps)) {
                echo '<tr><td colspan="3" class="text-center text-muted" style="padding:22px">Καμία απάντηση ακόμη σήμερα.</td></tr>';
            }
            foreach ($reps as $r) {
                echo '<tr><td>' . cpm_e(date('H:i', strtotime($r->date))) . '</td><td><b>' . cpm_e($r->admin) . '</b></td>'
                    . '<td><a href="supporttickets.php?action=view&id=' . (int) $r->ticket_id . '">#' . cpm_e($r->tid) . ' ' . cpm_e(mb_substr($r->title, 0, 60)) . '</a></td></tr>';
            }
            echo '</tbody></table></div>';
            echo '<div class="panel panel-default"><div class="panel-heading"><b>Tasks που ολοκληρώθηκαν σήμερα</b></div>'
                . '<table class="table table-condensed" style="margin:0"><thead><tr><th>Ώρα</th><th>Agent</th><th>Task</th></tr></thead><tbody>';
            $done = Capsule::table('mod_cpm_tasks')->where('completed_at', '>=', $today)->orderBy('completed_at', 'desc')->get();
            if (!count($done)) {
                echo '<tr><td colspan="3" class="text-center text-muted" style="padding:22px">Κανένα ακόμη.</td></tr>';
            }
            foreach ($done as $d) {
                echo '<tr><td>' . cpm_e(date('H:i', strtotime($d->completed_at))) . '</td><td><b>' . cpm_e(Db::adminName($d->assignee)) . '</b></td>'
                    . '<td><a href="' . $link . '&tab=task&id=' . (int) $d->id . '">' . cpm_e($d->title) . '</a></td></tr>';
            }
            echo '</tbody></table></div>';
            break;

        case 'sla7':
            $rows = Capsule::table('mod_supportcontracts_tickets as st')
                ->join('tbltickets as t', 't.id', '=', 'st.ticketid')
                ->whereNotNull('st.sla_met')->where('st.first_response_at', '>=', date('Y-m-d', strtotime('-7 days')))
                ->select('t.id', 't.tid', 't.title', 't.userid', 'st.sla_due', 'st.first_response_at', 'st.sla_met')
                ->orderBy('st.first_response_at', 'desc')->get();
            echo '<div class="panel panel-default"><table class="table table-condensed" style="margin:0"><thead><tr>'
                . '<th>Ticket</th><th>Πελάτης</th><th>Προθεσμία</th><th>Πρώτη απάντηση</th><th>Αποτέλεσμα</th></tr></thead><tbody>';
            if (!count($rows)) {
                echo '<tr><td colspan="5" class="text-center text-muted" style="padding:22px">Καμία μέτρηση SLA στις 7 ημέρες.</td></tr>';
            }
            foreach ($rows as $r) {
                echo '<tr><td><a href="supporttickets.php?action=view&id=' . (int) $r->id . '"><b>#' . cpm_e($r->tid) . '</b> ' . cpm_e(mb_substr($r->title, 0, 55)) . '</a></td>'
                    . '<td>' . cpm_e(cpm_client_label($r->userid)) . '</td>'
                    . '<td>' . cpm_e(date('d/m H:i', strtotime($r->sla_due))) . '</td>'
                    . '<td>' . cpm_e(date('d/m H:i', strtotime($r->first_response_at))) . '</td>'
                    . '<td>' . ($r->sla_met ? '<span class="label label-success">Εντός SLA ✓</span>' : '<span class="label label-danger">Εκτός SLA</span>') . '</td></tr>';
            }
            echo '</tbody></table></div>';
            break;

        default:
            echo '<div class="alert alert-info">Άγνωστη προβολή.</div>';
    }
}

/* ------------------------------------------------------------------ */
/* Workload — φόρτος ανά agent (full μόνο)                            */
/* ------------------------------------------------------------------ */

function cpm_workload_panel($link)
{
    $today = date('Y-m-d');
    $week = date('Y-m-d', strtotime('+7 days'));
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $rows = Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $doneIds)
        ->whereNotNull('assignee')->get(['assignee', 'estimate_minutes', 'schedule_date', 'due_date', 'action_user']);
    $balls = Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $doneIds)
        ->whereNotNull('action_user')->selectRaw('action_user, COUNT(*) n')->groupBy('action_user')->pluck('n', 'action_user')->all();
    $wl = [];
    foreach ($rows as $r) {
        $aid = (int) $r->assignee;
        if (!isset($wl[$aid])) {
            $wl[$aid] = ['open' => 0, 'est' => 0, 'today' => 0, 'week' => 0, 'over' => 0];
        }
        $wl[$aid]['open']++;
        $wl[$aid]['est'] += (int) $r->estimate_minutes;
        if ($r->schedule_date && $r->schedule_date <= $today) { $wl[$aid]['today']++; }
        elseif ($r->schedule_date && $r->schedule_date <= $week) { $wl[$aid]['week']++; }
        if ($r->due_date && $r->due_date < $today) { $wl[$aid]['over']++; }
    }
    if (!count($wl) && !count($balls)) {
        return;
    }
    echo '<div class="panel panel-default" style="margin-bottom:14px"><div class="panel-heading" style="padding:6px 12px"><b><i class="fas fa-weight-hanging"></i> Φόρτος ομάδας (ανοιχτά tasks)</b></div>'
        . '<table class="table table-condensed" style="margin:0;font-size:12px"><thead><tr>'
        . '<th>Agent</th><th>Ανοιχτά</th><th>Εκτιμ. υπόλοιπο</th><th>Στο πλάνο σήμερα</th><th>Επόμενες 7 ημ.</th><th>Εκπρόθεσμα</th><th>⚡ Μπάλες</th></tr></thead><tbody>';
    // ταξινόμηση κατά εκτιμώμενο φόρτο
    uasort($wl, function ($a, $b) { return $b['est'] <=> $a['est']; });
    foreach ($wl as $aid => $w) {
        $estTxt = $w['est'] ? Time::fmt($w['est']) : '—';
        echo '<tr><td><b>' . cpm_e(Db::adminName($aid)) . '</b></td>'
            . '<td>' . $w['open'] . '</td>'
            . '<td>' . ($w['est'] >= 480 ? '<b class="text-danger">' . $estTxt . '</b>' : cpm_e($estTxt)) . '</td>'
            . '<td>' . $w['today'] . '</td><td>' . $w['week'] . '</td>'
            . '<td>' . ($w['over'] ? '<b class="text-danger">' . $w['over'] . '</b>' : '0') . '</td>'
            . '<td>' . (isset($balls[$aid]) ? '⚡' . (int) $balls[$aid] : '—') . '</td></tr>';
        unset($balls[$aid]);
    }
    foreach ($balls as $aid => $n) {
        echo '<tr><td><b>' . cpm_e(Db::adminName($aid)) . '</b></td><td>0</td><td>—</td><td>0</td><td>0</td><td>0</td><td>⚡' . (int) $n . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

/* ------------------------------------------------------------------ */
/* Board (1.6)                                                        */
/* ------------------------------------------------------------------ */

function cpm_tab_board($link, $adminId)
{
    // κεντρικό KPI dashboard: ΜΟΝΟ διαχειριστές — οι agents έχουν το δικό τους στο «Η δουλειά μου»
    if (cpm_is_full()) {
        cpm_board_kpis($link, $adminId);
        cpm_workload_panel($link);
    }
    $projects = Db::projectsFor($adminId);
    if (!count($projects)) {
        echo cpm_is_full()
            ? '<div class="alert alert-info">Δεν υπάρχουν projects ακόμη. <a href="' . $link . '&tab=projects">Δημιούργησε το πρώτο →</a></div>'
            : '<div class="alert alert-info">Δεν είσαι μέλος σε κανένα project ακόμη — δες το tab «Η δουλειά μου» για tasks που σου έχουν ανατεθεί.</div>';
        return;
    }
    $pid = (int) ($_REQUEST['project'] ?? 0);
    if (!$pid || !Db::project($pid) || !Db::canSeeProject($adminId, $pid)) {
        $pid = (int) $projects[0]->id;
    }
    $proj = Db::project($pid);

    // project picker + quick task
    echo '<form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom:14px">';
    echo '<input type="hidden" name="module" value="cloudonprojects"><input type="hidden" name="tab" value="board"> ';
    echo '<select name="project" class="form-control" onchange="this.form.submit()">';
    $kidsOf = [];
    $rootsP = [];
    foreach ($projects as $p) {
        if ($p->parent_id) { $kidsOf[(int) $p->parent_id][] = $p; } else { $rootsP[] = $p; }
    }
    $optLabel = function ($p) { return cpm_e($p->name) . ($p->clientid ? ' — ' . cpm_e(cpm_client_label($p->clientid)) : ''); };
    foreach ($rootsP as $p) {
        echo '<option value="' . (int) $p->id . '"' . ($p->id == $pid ? ' selected' : '') . '>' . $optLabel($p) . '</option>';
        foreach ($kidsOf[(int) $p->id] ?? [] as $kid) {
            echo '<option value="' . (int) $kid->id . '"' . ($kid->id == $pid ? ' selected' : '') . '>&nbsp;&nbsp;↳ ' . $optLabel($kid) . '</option>';
        }
    }
    // παιδιά με μη-ορατό γονιό
    foreach ($kidsOf as $parId => $kids) {
        $vis = false;
        foreach ($rootsP as $rp) { if ((int) $rp->id === $parId) { $vis = true; break; } }
        if (!$vis) {
            foreach ($kids as $kid) {
                echo '<option value="' . (int) $kid->id . '"' . ($kid->id == $pid ? ' selected' : '') . '>' . $optLabel($kid) . '</option>';
            }
        }
    }
    echo '</select> ';
    echo '<a class="btn btn-primary" href="' . $link . '&tab=task&id=0&project=' . $pid . '"><i class="fas fa-plus"></i> Νέο task</a>';
    if ($proj->clientid) {
        echo ' <a class="btn btn-default" href="clientssummary.php?userid=' . (int) $proj->clientid . '" target="_blank">Καρτέλα πελάτη</a>';
    }
    echo '</form>';

    $board = Db::board($pid);
    $taskMins = Db::minutesByTask($pid);
    $checkProg = Db::checklistProgress($pid);
    $typesById = [];
    foreach (Db::taskTypes() as $ty) {
        $typesById[(int) $ty->id] = $ty;
    }
    $today = date('Y-m-d');
    // μετρητής εκπρόθεσμων (3.1)
    $overdueN = 0;
    foreach ($board as $cards0) {
        foreach ($cards0 as $t0) {
            if ($t0->due_date && $t0->due_date < $today && !$t0->completed_at) {
                $overdueN++;
            }
        }
    }
    if ($overdueN) {
        echo '<div class="alert alert-danger" style="padding:6px 12px;display:inline-block"><i class="fas fa-exclamation-triangle"></i> '
            . '<b>' . $overdueN . '</b> εκπρόθεσμ' . ($overdueN === 1 ? 'ο task' : 'α tasks') . ' σε αυτό το project</div>';
    }
    echo '<div class="cnp-board">';
    foreach (Db::statuses() as $st) {
        $cards = $board[(int) $st->id] ?? [];
        echo '<div class="cnp-col" data-status="' . (int) $st->id . '">';
        echo '<div class="cnp-col-h" style="border-color:' . cpm_e($st->color) . '">' . cpm_e($st->title)
            . ' <span class="cnp-col-n">' . count($cards) . '</span></div>';
        echo '<div class="cnp-cards">';
        foreach ($cards as $t) {
            $prioC = [0 => '#8291a9', 1 => '#e0a020', 2 => '#d92d3a'][$t->priority] ?? '#8291a9';
            $ini = '';
            if ($t->assignee) {
                $nm = Db::adminName($t->assignee);
                $parts = preg_split('/\s+/', $nm);
                $ini = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
            }
            $dueHtml = '';
            $over = false;
            if ($t->due_date) {
                $over = ($t->due_date < $today && !$t->completed_at);
                $dueHtml = '<span class="' . ($over ? 'cnp-due-over' : '') . '"><i class="far fa-calendar"></i> ' . cpm_e(date('d/m', strtotime($t->due_date))) . '</span>';
            }
            echo '<a class="cnp-card' . ($over ? ' cnp-card--over' : '') . '" draggable="true" data-task="' . (int) $t->id . '" href="' . $link . '&tab=task&id=' . (int) $t->id . '">';
            $tyIco = '';
            if (!empty($t->type_id) && isset($typesById[(int) $t->type_id])) {
                $ty = $typesById[(int) $t->type_id];
                $tyIco = '<i class="fas ' . cpm_e($ty->icon) . '" style="color:' . cpm_e($ty->color) . '" title="' . cpm_e($ty->name) . '"></i> ';
            }
            echo '<div class="cnp-card-t">' . $tyIco . cpm_e($t->title) . '</div>';
            echo '<div class="cnp-card-m"><span class="cnp-dot" style="background:' . $prioC . '" title="Προτεραιότητα"></span>';
            if ($ini) { echo '<span class="cnp-ava" title="' . cpm_e(Db::adminName($t->assignee)) . '">' . cpm_e($ini) . '</span>'; }
            if ($t->ticketid) {
                $tid = Capsule::table('tbltickets')->where('id', $t->ticketid)->value('tid');
                echo '<span title="Συνδεδεμένο ticket"><i class="fas fa-life-ring"></i> #' . cpm_e($tid ?: $t->ticketid) . '</span>';
            }
            echo $dueHtml;
            if (!empty($taskMins[(int) $t->id])) {
                echo '<span title="Καταχωρημένος χρόνος"><i class="far fa-clock"></i> ' . cpm_e(Time::fmt($taskMins[(int) $t->id])) . '</span>';
            }
            if (!empty($checkProg[(int) $t->id])) {
                [$d, $n] = $checkProg[(int) $t->id];
                echo '<span title="Checklist" class="' . ($d >= $n ? 'text-success' : '') . '"><i class="far fa-check-square"></i> ' . $d . '/' . $n . '</span>';
            }
            if (!empty($t->estimate_minutes)) {
                echo '<span title="Εκτίμηση χρόνου">~' . cpm_e(Time::fmt($t->estimate_minutes)) . '</span>';
            }
            if (!empty($t->action_user)) {
                $an = Db::adminName($t->action_user);
                $ap = preg_split('/\s+/', $an);
                echo '<span class="cnp-ball' . ((int) $t->action_user === $adminId ? ' cnp-ball--me' : '') . '" title="Απαιτεί ενέργεια: ' . cpm_e($an) . '">⚡'
                    . cpm_e(mb_strtoupper(mb_substr($ap[0] ?? '', 0, 1) . mb_substr($ap[1] ?? '', 0, 1))) . '</span>';
            }
            echo '</div></a>';
        }
        echo '</div>';
        // γρήγορη προσθήκη: γράψε τίτλο και Enter — πλήρης φόρμα με το ⧉
        echo '<form class="cnp-quick" method="post" action="' . $link . '">'
            . '<input type="hidden" name="do" value="quicktask"><input type="hidden" name="project_id" value="' . $pid . '"><input type="hidden" name="status_id" value="' . (int) $st->id . '">'
            . '<input type="text" name="title" placeholder="+ Νέο task…" autocomplete="off">'
            . '<a href="' . $link . '&tab=task&id=0&project=' . $pid . '&status=' . (int) $st->id . '" title="Πλήρης φόρμα" style="align-self:center;color:#8291a9"><i class="fas fa-external-link-alt"></i></a>'
            . '</form>';
        echo '</div>';
    }
    echo '</div>';
    echo cpm_board_js($link);
}

/* ------------------------------------------------------------------ */
/* Projects tab (1.3)                                                 */
/* ------------------------------------------------------------------ */

function cpm_tab_projects($link, $adminId)
{
    // περιορισμένος agent: μόνο λίστα των projects όπου είναι μέλος
    if (!cpm_is_full()) {
        echo '<p class="text-muted">Τα projects στα οποία είσαι μέλος:</p>';
        echo '<table class="table table-striped table-bordered"><thead><tr><th></th><th>Project</th><th>Πελάτης</th></tr></thead><tbody>';
        $mine = Db::projectsFor($adminId);
        if (!count($mine)) {
            echo '<tr><td colspan="3" class="text-center text-muted">Κανένα ακόμη — ζήτησε από διαχειριστή να σε προσθέσει ως μέλος.</td></tr>';
        }
        foreach ($mine as $p) {
            echo '<tr><td><span class="cnp-dot" style="background:' . cpm_e($p->color) . ';width:12px;height:12px"></span></td>'
                . '<td><a href="' . $link . '&tab=board&project=' . (int) $p->id . '"><b>' . cpm_e($p->name) . '</b></a></td>'
                . '<td>' . cpm_e(cpm_client_label($p->clientid) ?: '—') . '</td></tr>';
        }
        echo '</tbody></table>';
        return;
    }

    $editId = (int) ($_REQUEST['edit'] ?? 0);
    $edit = $editId ? Db::project($editId) : null;

    if (isset($_GET['saved'])) {
        echo '<div class="alert alert-success">Αποθηκεύτηκε.</div>';
    }

    // form (create / edit)
    echo '<div class="panel panel-default"><div class="panel-heading"><b>' . ($edit ? 'Επεξεργασία project' : 'Νέο project') . '</b></div><div class="panel-body">';
    echo '<form method="post" action="' . $link . '&tab=projects" class="form-inline">';
    echo '<input type="hidden" name="do" value="saveproject"><input type="hidden" name="id" value="' . (int) $editId . '">';
    echo '<input type="text" name="name" class="form-control" style="width:220px" placeholder="Όνομα project" required value="' . cpm_e($edit->name ?? '') . '"> ';
    echo cpm_client_input('clientid', $edit->clientid ?? 0, '200px') . ' ';
    echo '<select name="deptid" class="form-control" title="Τμήμα (για auto-tasks από tickets)"><option value="">— τμήμα —</option>';
    foreach (Capsule::table('tblticketdepartments')->orderBy('order')->get(['id', 'name']) as $d) {
        echo '<option value="' . (int) $d->id . '"' . (($edit->deptid ?? 0) == $d->id ? ' selected' : '') . '>' . cpm_e($d->name) . '</option>';
    }
    echo '</select> ';
    echo '<input type="color" name="color" class="form-control" style="width:52px;padding:2px" value="' . cpm_e($edit->color ?? '#0097e4') . '"> ';
    // ιεραρχία + κατάσταση + υγεία (portfolio)
    echo '<select name="parent_id" class="form-control" title="Γονικό project (ιεραρχία)"><option value="">— κορυφαίο —</option>';
    foreach (Db::projects(true) as $pp) {
        if ($pp->id == $editId || $pp->parent_id) { continue; } // 1 επίπεδο βάθους
        echo '<option value="' . (int) $pp->id . '"' . (($edit->parent_id ?? 0) == $pp->id ? ' selected' : '') . '>↳ ' . cpm_e($pp->name) . '</option>';
    }
    echo '</select> ';
    echo '<select name="pstatus" class="form-control" title="Κατάσταση project"><option value="">— κατάσταση —</option>';
    foreach (Db::projectStatuses() as $k => $v) {
        echo '<option value="' . $k . '"' . (($edit->pstatus ?? '') === $k ? ' selected' : '') . '>' . $v . '</option>';
    }
    echo '</select> ';
    echo '<select name="health" class="form-control" title="Υγεία project"><option value="">— υγεία —</option>';
    foreach (['green' => '🟢 Καλά', 'yellow' => '🟡 Προσοχή', 'red' => '🔴 Πρόβλημα'] as $k => $v) {
        echo '<option value="' . $k . '"' . (($edit->health ?? '') === $k ? ' selected' : '') . '>' . $v . '</option>';
    }
    echo '</select> ';
    echo '<input type="text" name="descr" class="form-control" style="width:260px" placeholder="Περιγραφή (προαιρετικά)" value="' . cpm_e($edit->descr ?? '') . '"> ';
    echo '<label style="font-weight:normal;margin-right:6px" title="Ο πελάτης βλέπει την πρόοδο του project στο portal του"><input type="checkbox" name="client_visible" value="1"' . ((int) ($edit->client_visible ?? 1) ? ' checked' : '') . '> ορατό στον πελάτη</label> ';
    // μέλη: ποιοι agents βλέπουν το project (οι full-access βλέπουν πάντα όλα)
    $curMembers = $editId ? Db::projectMembers($editId) : [];
    echo '<span style="display:inline-block;border-left:1px solid #e2e8f0;padding-left:10px;margin-left:4px"><i class="fas fa-user-shield text-muted" title="Μέλη — ποιοι agents έχουν πρόσβαση"></i> ';
    foreach (Db::admins() as $a) {
        if (Db::isFullAccess($a->id)) {
            continue; // οι full-access δεν χρειάζονται membership
        }
        echo '<label style="font-weight:normal;margin-right:8px"><input type="checkbox" name="members[]" value="' . (int) $a->id . '"'
            . (in_array((int) $a->id, $curMembers, true) ? ' checked' : '') . '> ' . cpm_e(trim($a->firstname . ' ' . $a->lastname)) . '</label>';
    }
    // ή ολόκληρες ομάδες (όλα τα μέλη της ομάδας αποκτούν πρόσβαση)
    $curTeams = $editId ? Db::projectTeams($editId) : [];
    $allTeams = Db::teams();
    if (count($allTeams)) {
        echo ' <i class="fas fa-sitemap text-muted" title="Ομάδες με πρόσβαση"></i> ';
        foreach ($allTeams as $tm) {
            echo '<label style="font-weight:normal;margin-right:8px"><input type="checkbox" name="teams[]" value="' . (int) $tm->id . '"'
                . (in_array((int) $tm->id, $curTeams, true) ? ' checked' : '') . '> <span class="cnp-dot" style="background:' . cpm_e($tm->color) . '"></span> ' . cpm_e($tm->name) . '</label>';
        }
    }
    echo '</span> ';
    echo '<button class="btn btn-primary">' . ($edit ? 'Αποθήκευση' : 'Δημιουργία') . '</button>';
    if ($edit) { echo ' <a class="btn btn-default" href="' . $link . '&tab=projects">Ακύρωση</a>'; }
    echo '</form>';

    // custom fields του project (3.5)
    if ($edit) {
        echo '<hr><div id="fields"><b><i class="fas fa-sliders-h"></i> Custom πεδία tasks (μόνο για αυτό το project)</b>';
        $flds = Db::fieldsForProject($editId);
        if (count($flds)) {
            echo '<table class="table table-condensed" style="margin:8px 0;max-width:640px"><thead><tr><th>Πεδίο</th><th>Τύπος</th><th>Επιλογές</th><th></th></tr></thead><tbody>';
            foreach ($flds as $cf) {
                echo '<tr><td>' . cpm_e($cf->label) . '</td><td>' . cpm_e($cf->type) . '</td>'
                    . '<td><small>' . cpm_e(str_replace("\n", ' · ', (string) $cf->options)) . '</small></td>'
                    . '<td><form method="post" action="' . $link . '&tab=projects" style="display:inline" onsubmit="return confirm(\'Διαγραφή πεδίου και των τιμών του;\')">'
                    . '<input type="hidden" name="do" value="delfield"><input type="hidden" name="id" value="' . (int) $cf->id . '"><input type="hidden" name="project_id" value="' . $editId . '">'
                    . '<button class="btn btn-xs btn-link text-danger"><i class="fas fa-times"></i></button></form></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<form method="post" action="' . $link . '&tab=projects" class="form-inline" style="margin-top:6px">'
            . '<input type="hidden" name="do" value="savefield"><input type="hidden" name="project_id" value="' . $editId . '">'
            . '<input type="text" name="label" class="form-control input-sm" placeholder="Όνομα πεδίου" required> '
            . '<select name="type" class="form-control input-sm" onchange="document.getElementById(\'cnpFldOpts\').style.display=this.value===\'select\'?\'\':\'none\'">'
            . '<option value="text">Κείμενο</option><option value="select">Επιλογή</option><option value="date">Ημερομηνία</option></select> '
            . '<input type="text" name="options" id="cnpFldOpts" class="form-control input-sm" style="display:none;width:220px" placeholder="επιλογές χωρισμένες με |"> '
            . '<button class="btn btn-sm btn-default"><i class="fas fa-plus"></i> Προσθήκη πεδίου</button>'
            . '<script>document.querySelector(\'#fields form\').addEventListener("submit",function(){var o=this.elements.options;if(o&&o.value){o.value=o.value.split("|").map(function(s){return s.trim();}).filter(Boolean).join("\\n");}});</script>'
            . '</form></div>';
    }
    echo '</div></div>';

    // portfolio λίστα: δέντρο (γονικά → παιδιά) + κατάσταση/υγεία/πρόοδος
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all();
    $all = Db::projects(true);
    $children = [];
    $roots = [];
    foreach ($all as $p) {
        if ($p->parent_id) {
            $children[(int) $p->parent_id][] = $p;
        } else {
            $roots[] = $p;
        }
    }
    // ορφανά παιδιά (γονιός διαγράφηκε) → ως κορυφαία
    foreach ($children as $parId => $kids) {
        $found = false;
        foreach ($all as $p) { if ((int) $p->id === $parId) { $found = true; break; } }
        if (!$found) { foreach ($kids as $k) { $roots[] = $k; } unset($children[$parId]); }
    }
    $pStatuses = Db::projectStatuses();
    $hColors = Db::healthColors();
    $renderRow = function ($p, $depth) use ($link, $doneIds, $pStatuses, $hColors) {
        $open = Capsule::table('mod_cpm_tasks')->where('project_id', $p->id)->whereNotIn('status_id', $doneIds ?: [0])->count();
        [$d, $tot, $pct] = Db::projectProgress($p->id);
        $dept = $p->deptid ? Capsule::table('tblticketdepartments')->where('id', $p->deptid)->value('name') : '—';
        echo '<tr' . ($p->status === 'archived' ? ' class="text-muted"' : '') . '>';
        echo '<td>' . ($p->health ? '<span class="cnp-dot" style="background:' . ($hColors[$p->health] ?? '#8291a9') . ';width:12px;height:12px" title="Υγεία"></span>' : '<span class="cnp-dot" style="background:' . cpm_e($p->color) . ';width:12px;height:12px"></span>') . '</td>';
        echo '<td style="padding-left:' . (8 + $depth * 26) . 'px">' . ($depth ? '<i class="fas fa-level-up-alt fa-rotate-90 text-muted" style="margin-right:5px"></i>' : '')
            . '<a href="' . $link . '&tab=board&project=' . (int) $p->id . '"><b>' . cpm_e($p->name) . '</b></a></td>';
        echo '<td>' . cpm_e(cpm_client_label($p->clientid) ?: '—') . '</td>';
        echo '<td>' . cpm_e($dept ?: '—') . '</td>';
        echo '<td>' . ($p->pstatus ? '<span class="label label-info">' . cpm_e($pStatuses[$p->pstatus] ?? $p->pstatus) . '</span>' : '—') . '</td>';
        echo '<td>' . $open . '</td>';
        echo '<td style="min-width:110px"><div class="progress" style="height:8px;margin:0 0 2px"><div class="progress-bar progress-bar-success" style="width:' . $pct . '%"></div></div><small class="text-muted">' . $d . '/' . $tot . ' (' . $pct . '%)</small></td>';
        $delta = Db::snapshotDelta($p->id, 7);
        if ($delta === null) {
            echo '<td class="text-muted">—</td>';
        } else {
            [$was, $now] = $delta;
            $diff = $now - $was;
            echo '<td>' . ($diff > 0 ? '<b class="text-danger">▲ +' . $diff . '</b>'
                : ($diff < 0 ? '<b class="text-success">▼ ' . $diff . '</b>' : '<span class="text-muted">=</span>'))
                . ' <small class="text-muted">ανοιχτά vs 7ημ</small></td>';
        }
        echo '<td>' . ($p->status === 'archived' ? '<span class="label label-default">Αρχείο</span>' : '<span class="label label-success">Ενεργό</span>') . '</td>';
        echo '<td style="white-space:nowrap"><a class="btn btn-xs btn-default" href="' . $link . '&tab=projects&edit=' . (int) $p->id . '">Επεξεργασία</a> ';
        echo '<form method="post" action="' . $link . '&tab=projects" style="display:inline"><input type="hidden" name="do" value="archiveproject"><input type="hidden" name="id" value="' . (int) $p->id . '">';
        echo '<button class="btn btn-xs btn-default">' . ($p->status === 'archived' ? 'Επαναφορά' : 'Αρχειοθέτηση') . '</button></form></td></tr>';
    };
    echo '<table class="table table-striped table-bordered"><thead><tr><th></th><th>Project</th><th>Πελάτης</th><th>Τμήμα</th><th>Κατάσταση</th><th>Ανοιχτά</th><th>Πρόοδος</th><th>Τάση 7ημ</th><th></th><th></th></tr></thead><tbody>';
    foreach ($roots as $p) {
        $renderRow($p, 0);
        foreach ($children[(int) $p->id] ?? [] as $kid) {
            $renderRow($kid, 1);
        }
    }
    echo '</tbody></table>';

    cpm_recurring_section($link);
}

/* ------------------------------------------------------------------ */
/* Επαναλαμβανόμενα tasks — συντηρήσεις (3.3)                         */
/* ------------------------------------------------------------------ */

function cpm_recurring_section($link)
{
    $freqL = ['daily' => 'ημέρες', 'weekly' => 'εβδομάδες', 'monthly' => 'μήνες', 'yearly' => 'έτη'];
    $editId = (int) ($_GET['editrec'] ?? 0);
    $er = $editId ? Db::recurring($editId) : null;

    echo '<div class="panel panel-default" id="recurring" style="margin-top:20px"><div class="panel-heading"><b><i class="fas fa-sync-alt"></i> Επαναλαμβανόμενα tasks (συντηρήσεις)</b></div><div class="panel-body">';

    // form
    echo '<form method="post" action="' . $link . '&tab=projects" class="form-inline" style="margin-bottom:12px">'
        . '<input type="hidden" name="do" value="saverec"><input type="hidden" name="id" value="' . $editId . '">'
        . '<input type="text" name="title" class="form-control input-sm" style="width:200px" placeholder="Τίτλος εργασίας" required value="' . cpm_e($er->title ?? '') . '"> ';
    echo '<select name="project_id" class="form-control input-sm">';
    foreach (Db::projects() as $p) {
        echo '<option value="' . (int) $p->id . '"' . (($er->project_id ?? 0) == $p->id ? ' selected' : '') . '>' . cpm_e($p->name) . '</option>';
    }
    echo '</select> κάθε <input type="number" name="every" min="1" class="form-control input-sm" style="width:55px" value="' . (int) ($er->every ?? 1) . '"> ';
    echo '<select name="freq" class="form-control input-sm">';
    foreach ($freqL as $k => $v) {
        echo '<option value="' . $k . '"' . (($er->freq ?? 'monthly') === $k ? ' selected' : '') . '>' . $v . '</option>';
    }
    echo '</select> από <input type="date" name="next_run" class="form-control input-sm" value="' . cpm_e($er->next_run ?? date('Y-m-d')) . '" title="Επόμενη εκτέλεση"> ';
    echo '<select name="assignee" class="form-control input-sm"><option value="">— χειριστής —</option>';
    foreach (Db::admins() as $a) {
        echo '<option value="' . (int) $a->id . '"' . (($er->assignee ?? 0) == $a->id ? ' selected' : '') . '>' . cpm_e(trim($a->firstname . ' ' . $a->lastname)) . '</option>';
    }
    echo '</select> ';
    echo '<select name="priority" class="form-control input-sm">';
    foreach ([0 => 'Κανονική', 1 => 'Υψηλή', 2 => 'Κρίσιμη'] as $k => $v) {
        echo '<option value="' . $k . '"' . (((int) ($er->priority ?? 0)) === $k ? ' selected' : '') . '>' . $v . '</option>';
    }
    echo '</select> ';
    echo 'λήξη+<input type="number" name="due_days" min="0" class="form-control input-sm" style="width:55px" value="' . (int) ($er->due_days ?? 3) . '" title="Λήξη = ημέρα δημιουργίας + Ν ημέρες (0=χωρίς)">ημ. ';
    echo '<label style="font-weight:normal"><input type="checkbox" name="active" value="1"' . ((int) ($er->active ?? 1) ? ' checked' : '') . '> ενεργό</label> ';
    echo '<button class="btn btn-sm btn-primary">' . ($er ? 'Αποθήκευση' : 'Προσθήκη') . '</button>';
    if ($er) { echo ' <a class="btn btn-sm btn-default" href="' . $link . '&tab=projects#recurring">Ακύρωση</a>'; }
    echo '</form>';

    // list
    $recs = Db::recurringAll();
    if (count($recs)) {
        echo '<table class="table table-striped table-condensed" style="font-size:12px"><thead><tr>'
            . '<th>Εργασία</th><th>Project</th><th>Συχνότητα</th><th>Επόμενη</th><th>Χειριστής</th><th>Τελευταία</th><th>Κατάσταση</th><th></th></tr></thead><tbody>';
        foreach ($recs as $r) {
            echo '<tr' . ($r->active ? '' : ' class="text-muted"') . '>'
                . '<td><b>' . cpm_e($r->title) . '</b></td>'
                . '<td><span class="cnp-dot" style="background:' . cpm_e($r->project_color ?: '#8595ac') . '"></span> ' . cpm_e($r->project_name ?: 'Χωρίς έργο') . '</td>'
                . '<td>κάθε ' . (int) $r->every . ' ' . cpm_e($freqL[$r->freq] ?? $r->freq) . '</td>'
                . '<td><b>' . cpm_e(date('d/m/Y', strtotime($r->next_run))) . '</b></td>'
                . '<td>' . cpm_e(Db::adminName($r->assignee)) . '</td>'
                . '<td>' . ($r->last_run ? cpm_e(date('d/m/Y', strtotime($r->last_run))) : '—') . '</td>'
                . '<td>' . ($r->active ? '<span class="label label-success">Ενεργό</span>' : '<span class="label label-default">Ανενεργό</span>') . '</td>'
                . '<td><a class="btn btn-xs btn-default" href="' . $link . '&tab=projects&editrec=' . (int) $r->id . '#recurring">Επεξεργασία</a> '
                . '<form method="post" action="' . $link . '&tab=projects" style="display:inline" onsubmit="return confirm(\'Διαγραφή προγράμματος; (τα ήδη δημιουργημένα tasks μένουν)\')">'
                . '<input type="hidden" name="do" value="delrec"><input type="hidden" name="id" value="' . (int) $r->id . '">'
                . '<button class="btn btn-xs btn-link text-danger"><i class="fas fa-times"></i></button></form></td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="text-muted">Κανένα επαναλαμβανόμενο πρόγραμμα. Ιδανικό για περιοδικές συντηρήσεις (backups, updates, έλεγχοι).</p>';
    }
    echo '<small class="text-muted">Το ημερήσιο cron δημιουργεί αυτόματα το task όταν φτάσει η «Επόμενη» ημερομηνία και ειδοποιεί τον χειριστή.</small>';
    echo '</div></div>';
}

/* ------------------------------------------------------------------ */
/* List (1.7) + Mine (1.8)                                            */
/* ------------------------------------------------------------------ */

/* ------------------------------------------------------------------ */
/* Προσωπικό dashboard χειριστή («Η δουλειά μου»)                     */
/* ------------------------------------------------------------------ */

function cpm_my_dashboard($link, $aid)
{
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    // tickets ανατεθειμένα σε εμένα (flag)
    $myTickets = Capsule::table('tbltickets')->where('flag', $aid)
        ->whereNotIn('status', ['Closed', 'Cancelled'])
        ->get(['id', 'tid', 'title', 'status', 'urgency', 'date', 'lastreply']);
    // SLA deadlines (supportcontracts)
    $slaMap = [];
    try {
        if (Capsule::schema()->hasTable('mod_supportcontracts_tickets') && count($myTickets)) {
            foreach (Capsule::table('mod_supportcontracts_tickets')
                ->whereIn('ticketid', $myTickets->pluck('id')->all())->get(['ticketid', 'sla_due', 'first_response_at']) as $s) {
                $slaMap[(int) $s->ticketid] = $s;
            }
        }
    } catch (\Throwable $e) {
    }
    $nearDeadline = 0; // εκτός SLA ή λήγει στο επόμενο 24ωρο (χωρίς πρώτη απάντηση)
    foreach ($myTickets as $tk) {
        $s = $slaMap[(int) $tk->id] ?? null;
        if ($s && $s->sla_due && !$s->first_response_at
            && strtotime($s->sla_due) < strtotime('+24 hours')) {
            $nearDeadline++;
        }
    }
    // tasks μου
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $myTasksQ = Capsule::table('mod_cpm_tasks')->where('assignee', $aid)->whereNotIn('status_id', $doneIds);
    $myTasksOpen = (clone $myTasksQ)->count();
    $tasksDue = (clone $myTasksQ)->whereNotNull('due_date')->where('due_date', '<=', $today)->count();
    // χρόνος σήμερα
    $minsToday = (int) Capsule::table('mod_cpm_timelogs')->where('admin_id', $aid)
        ->where('running', 0)->where('created_at', '>=', $today . ' 00:00:00')->sum('minutes');

    echo '<div class="row" style="margin-bottom:2px">';
    foreach ([
        ['Tickets μου (ανοιχτά)', (string) count($myTickets), count($myTickets) ? 'info' : 'ok', 'supporttickets.php?view=flagged'],
        ['Κοντά σε SLA deadline', (string) $nearDeadline, $nearDeadline ? 'bad' : 'ok', $link . '&tab=drill&k=sla'],
        ['Tasks μου (ανοιχτά)', (string) $myTasksOpen, $myTasksOpen ? 'info' : 'ok', $link . '&tab=mine'],
        ['Λήγουν σήμερα / εκπρόθεσμα', (string) $tasksDue, $tasksDue ? 'warn' : 'ok', $link . '&tab=mine&open=1&submitted=1'],
    ] as $c) {
        echo '<div class="col-sm-3"><a class="cnp-stat ' . $c[2] . '" href="' . $c[3] . '"><b>' . cpm_e($c[1]) . '</b><small>' . cpm_e($c[0]) . '</small></a></div>';
    }
    echo '</div>';

    // ⚡ η μπάλα σε εμένα + πλάνο ημέρας
    $actionOnMe = (int) Capsule::table('mod_cpm_tasks')->where('action_user', $aid)
        ->whereNotIn('status_id', $doneIds)->count();
    $planned = Capsule::table('mod_cpm_tasks as t')
        ->leftJoin('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
        ->select('t.*', 'p.name as pname', 'p.color as pcolor')
        ->whereNotIn('t.status_id', $doneIds)
        ->whereNotNull('t.schedule_date')->where('t.schedule_date', '<=', $today)
        ->where(function ($w) use ($aid) {
            $w->where('t.assignee', $aid)->orWhere('t.action_user', $aid);
        })->orderByRaw('t.priority DESC')->orderBy('t.due_date')->get();
    echo '<div class="row" style="margin-bottom:2px">';
    foreach ([
        ['⚡ Απαιτούν ενέργειά μου', (string) $actionOnMe, $actionOnMe ? 'warn' : 'ok'],
        ['Στο πλάνο μου σήμερα', (string) count($planned), count($planned) ? 'info' : 'ok'],
    ] as $c) {
        echo '<div class="col-sm-3"><div class="cnp-stat ' . $c[2] . '"><b>' . cpm_e($c[1]) . '</b><small>' . cpm_e($c[0]) . '</small></div></div>';
    }
    echo '<div class="col-sm-6"><div class="cnp-stat"><b>' . cpm_e(Time::fmt($minsToday)) . '</b><small>Καταχωρημένος χρόνος σήμερα</small></div></div>';
    echo '</div>';

    // Το πλάνο μου σήμερα (schedule_date — GoodDay-style)
    if (count($planned)) {
        echo '<div class="panel panel-info"><div class="panel-heading" style="padding:6px 12px"><b><i class="fas fa-calendar-day"></i> Το πλάνο μου σήμερα</b></div><div class="panel-body" style="padding:8px 12px">';
        foreach ($planned as $pt) {
            $prioC = [0 => '#8291a9', 1 => '#e0a020', 2 => '#d92d3a'][$pt->priority] ?? '#8291a9';
            echo '<div style="display:flex;gap:8px;align-items:baseline;padding:4px 0;border-bottom:1px dashed #eef2f7">'
                . '<span class="cnp-dot" style="background:' . $prioC . '"></span>'
                . '<a href="' . $link . '&tab=task&id=' . (int) $pt->id . '" style="flex:1"><b>' . cpm_e($pt->title) . '</b></a>'
                . '<small class="text-muted">' . cpm_e($pt->pname ?: 'Χωρίς έργο') . '</small>'
                . ((int) $pt->action_user === $aid ? '<span class="cnp-ball cnp-ball--me">⚡ εσύ</span>' : '')
                . ($pt->schedule_date < $today ? '<small class="cnp-due-over">από ' . cpm_e(date('d/m', strtotime($pt->schedule_date))) . '</small>' : '')
                . '</div>';
        }
        echo '</div></div>';
    }

    // Τα tickets μου — με SLA προθεσμία, ταξινομημένα κατά επείγον
    if (count($myTickets)) {
        $rows = [];
        foreach ($myTickets as $tk) {
            $s = $slaMap[(int) $tk->id] ?? null;
            $due = ($s && $s->sla_due && !$s->first_response_at) ? $s->sla_due : null;
            $rows[] = ['tk' => $tk, 'due' => $due];
        }
        usort($rows, function ($a, $b) {
            if ($a['due'] && $b['due']) { return strcmp($a['due'], $b['due']); }
            if ($a['due']) { return -1; }
            if ($b['due']) { return 1; }
            return strcmp($b['tk']->lastreply, $a['tk']->lastreply);
        });
        echo '<div class="panel panel-default"><div class="panel-heading" style="padding:6px 12px"><b><i class="fas fa-life-ring"></i> Τα tickets μου</b></div>'
            . '<table class="table table-condensed" style="margin:0;font-size:12px"><thead><tr>'
            . '<th>Ticket</th><th>Κατάσταση</th><th>Προτερ/τα</th><th>SLA προθεσμία</th><th>Ηλικία</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $tk = $r['tk'];
            $overSla = $r['due'] && strtotime($r['due']) < time();
            $age = (int) floor((time() - strtotime($tk->date)) / 86400);
            echo '<tr' . ($overSla ? ' class="danger"' : '') . '>'
                . '<td><a href="supporttickets.php?action=view&id=' . (int) $tk->id . '"><b>#' . cpm_e($tk->tid) . '</b> ' . cpm_e(mb_substr($tk->title, 0, 60)) . '</a></td>'
                . '<td>' . cpm_e($tk->status) . '</td>'
                . '<td>' . cpm_e($tk->urgency ?: '—') . '</td>'
                . '<td>' . ($r['due'] ? '<b class="' . ($overSla ? 'cnp-due-over' : '') . '">' . cpm_e(date('d/m H:i', strtotime($r['due']))) . '</b>' . ($overSla ? ' ⚠' : '') : '—') . '</td>'
                . '<td>' . $age . ' ημ.</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    // follow-ups μου σήμερα (leads + πελάτες)
    $follows = [];
    try {
        foreach (Capsule::table('mod_cpm_leads')->where('assignee', $aid)
            ->whereNotIn('stage', ['won', 'lost'])->whereNotNull('next_action')
            ->where('next_action', '<=', $today)->get() as $ld) {
            $follows[] = ['t' => ($ld->company ?: $ld->contact) . ($ld->next_note ? ' — ' . $ld->next_note : ''),
                          'url' => $link . '&tab=lead&id=' . (int) $ld->id];
        }
        foreach (Capsule::table('mod_cpm_interactions')->where('admin_id', $aid)
            ->whereNull('lead_id')->whereNotNull('clientid')->where('followup_done', 0)
            ->whereNotNull('followup_date')->where('followup_date', '<=', $today)->get() as $fi) {
            $follows[] = ['t' => cpm_client_label($fi->clientid) . ' — ' . ($fi->followup_note ?: $fi->summary),
                          'url' => cpm_is_full() ? $link . '&tab=client&client=' . (int) $fi->clientid : ''];
        }
    } catch (\Throwable $e) {
    }
    if (count($follows)) {
        echo '<div class="panel panel-warning"><div class="panel-heading" style="padding:6px 12px"><b><i class="far fa-bell"></i> Follow-ups για σήμερα (' . count($follows) . ')</b></div><div class="panel-body" style="padding:8px 12px">';
        foreach ($follows as $f) {
            echo '<div style="padding:3px 0"><i class="fas fa-phone-alt text-muted"></i> '
                . ($f['url'] ? '<a href="' . $f['url'] . '">' . cpm_e($f['t']) . '</a>' : cpm_e($f['t'])) . '</div>';
        }
        echo '</div></div>';
    }
}

function cpm_tab_list($link, $mineAdminId = 0)
{
    if ($mineAdminId) {
        cpm_my_dashboard($link, $mineAdminId);
        echo '<h4 style="margin:16px 0 8px"><b><i class="fas fa-tasks"></i> Τα tasks μου</b></h4>';
    }
    $f = [
        'project_id' => (int) ($_GET['fp'] ?? 0),
        'status_id'  => (int) ($_GET['fs'] ?? 0),
        'assignee'   => $mineAdminId ?: (int) ($_GET['fa'] ?? 0),
        'priority'   => $_GET['fr'] ?? '',
        'q'          => trim($_GET['q'] ?? ''),
        'open_only'  => isset($_GET['open']) ? 1 : (($_GET['submitted'] ?? '') ? 0 : 1),
    ];
    if (!cpm_is_full()) {
        $f['restrict_admin'] = cpm_admin_id();
    }
    $tab = $mineAdminId ? 'mine' : 'list';

    echo '<form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom:12px">';
    echo '<input type="hidden" name="module" value="cloudonprojects"><input type="hidden" name="tab" value="' . $tab . '"><input type="hidden" name="submitted" value="1"> ';
    echo '<select name="fp" class="form-control"><option value="">— όλα τα projects —</option>';
    foreach (Db::projectsFor(cpm_admin_id()) as $p) {
        echo '<option value="' . (int) $p->id . '"' . ($f['project_id'] == $p->id ? ' selected' : '') . '>' . cpm_e($p->name) . '</option>';
    }
    echo '</select> <select name="fs" class="form-control"><option value="">— status —</option>';
    foreach (Db::statuses() as $s) {
        echo '<option value="' . (int) $s->id . '"' . ($f['status_id'] == $s->id ? ' selected' : '') . '>' . cpm_e($s->title) . '</option>';
    }
    echo '</select> ';
    if (!$mineAdminId) {
        echo '<select name="fa" class="form-control"><option value="">— χειριστής —</option>';
        foreach (Db::admins() as $a) {
            echo '<option value="' . (int) $a->id . '"' . ($f['assignee'] == $a->id ? ' selected' : '') . '>' . cpm_e(trim($a->firstname . ' ' . $a->lastname)) . '</option>';
        }
        echo '</select> ';
    }
    echo '<select name="fr" class="form-control"><option value="">— προτερ/τα —</option>';
    foreach ([0 => 'Κανονική', 1 => 'Υψηλή', 2 => 'Κρίσιμη'] as $k => $v) {
        echo '<option value="' . $k . '"' . ($f['priority'] !== '' && (int) $f['priority'] === $k ? ' selected' : '') . '>' . $v . '</option>';
    }
    echo '</select> ';
    echo '<input type="text" name="q" class="form-control" placeholder="αναζήτηση…" value="' . cpm_e($f['q']) . '"> ';
    echo '<label style="font-weight:normal"><input type="checkbox" name="open" value="1"' . ($f['open_only'] ? ' checked' : '') . '> μόνο ανοιχτά</label> ';
    echo '<button class="btn btn-default">Φίλτρο</button></form>';

    $rows = Db::tasksFiltered($f);
    $mins = Db::minutesForTasks(array_map(function ($r) { return (int) $r->id; }, $rows->all()));
    $today = date('Y-m-d');
    echo '<table class="table table-striped table-bordered table-condensed"><thead><tr><th>Task</th><th>Project</th><th>Status</th><th>Προτερ/τα</th><th>Χειριστής</th><th>Λήξη</th><th>Χρόνος</th><th>Ticket</th>'
        . ($mineAdminId ? '<th>Πλάνο</th>' : '') . '</tr></thead><tbody>';
    if (!count($rows)) {
        echo '<tr><td colspan="' . ($mineAdminId ? 9 : 8) . '" class="text-center text-muted">Κανένα task.</td></tr>';
    }
    foreach ($rows as $t) {
        $st = Db::status($t->status_id);
        $over = ($t->due_date && $t->due_date < $today && !$t->completed_at);
        echo '<tr>';
        echo '<td><a href="' . $link . '&tab=task&id=' . (int) $t->id . '"><b>' . cpm_e($t->title) . '</b></a></td>';
        echo '<td><span class="cnp-dot" style="background:' . cpm_e($t->project_color ?: '#8595ac') . '"></span> ' . cpm_e($t->project_name) . '</td>';
        echo '<td><span class="label" style="background:' . cpm_e($st->color ?? '#888') . '">' . cpm_e($st->title ?? '?') . '</span></td>';
        echo '<td>' . cpm_prio_badge($t->priority) . '</td>';
        echo '<td>' . cpm_e(Db::adminName($t->assignee)) . '</td>';
        echo '<td' . ($over ? ' class="cnp-due-over"' : '') . '>' . ($t->due_date ? cpm_e(date('d/m/Y', strtotime($t->due_date))) : '—') . '</td>';
        echo '<td>' . (!empty($mins[(int) $t->id]) ? '<i class="far fa-clock"></i> ' . cpm_e(Time::fmt($mins[(int) $t->id])) : '—') . '</td>';
        $tid = $t->ticketid ? Capsule::table('tbltickets')->where('id', $t->ticketid)->value('tid') : null;
        echo '<td>' . ($t->ticketid ? '<a href="supporttickets.php?action=view&id=' . (int) $t->ticketid . '">#' . cpm_e($tid ?: $t->ticketid) . '</a>' : '—') . '</td>';
        if ($mineAdminId) {
            $ref = 'addonmodules.php?module=cloudonprojects&tab=mine';
            echo '<td style="white-space:nowrap">';
            if ($t->schedule_date) {
                echo '<small><i class="fas fa-calendar-day"></i> ' . cpm_e(date('d/m', strtotime($t->schedule_date))) . '</small> ';
            }
            foreach ([['today', 'Σήμερα'], ['tomorrow', 'Αύριο']] as $btn) {
                echo '<form method="post" action="' . $link . '" style="display:inline"><input type="hidden" name="do" value="scheduletask">'
                    . '<input type="hidden" name="taskid" value="' . (int) $t->id . '"><input type="hidden" name="when" value="' . $btn[0] . '"><input type="hidden" name="ref" value="' . $ref . '">'
                    . '<button class="btn btn-xs btn-default" style="padding:0 5px;font-size:10px">' . $btn[1] . '</button></form> ';
            }
            echo '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
}

/* ------------------------------------------------------------------ */
/* Task page (1.5 / 1.9 / 1.10)                                       */
/* ------------------------------------------------------------------ */

function cpm_tab_task($link, $adminId)
{
    $id = (int) ($_REQUEST['id'] ?? 0);
    $t = $id ? Db::task($id) : null;
    if ($id && !$t) {
        echo '<div class="alert alert-warning">Το task δεν βρέθηκε.</div>';
        return;
    }
    if ($t && !Db::canSeeTask($adminId, $t)) {
        echo '<div class="alert alert-warning"><i class="fas fa-lock"></i> Δεν έχεις πρόσβαση σε αυτό το task.</div>';
        return;
    }
    if (!$t && !Db::canSeeProject($adminId, (int) ($_REQUEST['project'] ?? 0))) {
        echo '<div class="alert alert-warning"><i class="fas fa-lock"></i> Δεν έχεις πρόσβαση σε αυτό το project.</div>';
        return;
    }
    if (isset($_GET['saved'])) {
        echo '<div class="alert alert-success">Αποθηκεύτηκε.</div>';
    }
    if (isset($_GET['warn'])) {
        echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> ' . cpm_e($_GET['warn']) . '</div>';
    }
    $backPid = (int) ($t->project_id ?? ($_REQUEST['project'] ?? 0));
    echo '<a href="' . $link . '&tab=board&project=' . $backPid . '" class="btn btn-default btn-xs" style="margin-bottom:12px">&larr; Board</a>';

    echo '<div class="row"><div class="col-md-7">';
    echo '<div class="panel panel-default"><div class="panel-heading"><b>' . ($t ? 'Task #' . $id : 'Νέο task') . '</b></div><div class="panel-body">';
    echo '<form method="post" action="' . $link . '" class="form-horizontal"><input type="hidden" name="do" value="savetask"><input type="hidden" name="id" value="' . $id . '">';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Τίτλος</label><div class="col-sm-9"><input type="text" name="title" class="form-control" required value="' . cpm_e($t->title ?? '') . '"></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Project</label><div class="col-sm-9"><select name="project_id" class="form-control">';
    $taskProjects = Db::projectsFor($adminId);
    // αν το task είναι σε project που δεν είναι μέλος (το βλέπει ως assignee), κράτα το project στη λίστα
    if ($t && !$taskProjects->firstWhere('id', $t->project_id) && ($tp = Db::project($t->project_id))) {
        $taskProjects->push($tp);
    }
    foreach ($taskProjects as $p) {
        $sel = ((int) ($t->project_id ?? ($_REQUEST['project'] ?? 0)) === (int) $p->id) ? ' selected' : '';
        echo '<option value="' . (int) $p->id . '"' . $sel . '>' . cpm_e($p->name) . '</option>';
    }
    echo '</select></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Status</label><div class="col-sm-9"><select name="status_id" class="form-control">';
    foreach (Db::statuses() as $s) {
        $sel = ((int) ($t->status_id ?? ($_REQUEST['status'] ?? Db::firstStatusId())) === (int) $s->id) ? ' selected' : '';
        echo '<option value="' . (int) $s->id . '"' . $sel . '>' . cpm_e($s->title) . '</option>';
    }
    echo '</select></div></div>';
    $lockChar = !cpm_is_full() && $t; // χαρακτηρισμός: μόνο από διαχειριστή
    echo '<div class="form-group"><label class="col-sm-3 control-label">Προτεραιότητα</label><div class="col-sm-9"><select name="priority" class="form-control"' . ($lockChar ? ' disabled' : '') . '>';
    foreach ([0 => 'Κανονική', 1 => 'Υψηλή', 2 => 'Κρίσιμη'] as $k => $v) {
        echo '<option value="' . $k . '"' . (((int) ($t->priority ?? 0)) === $k ? ' selected' : '') . '>' . $v . '</option>';
    }
    echo '</select>' . ($lockChar ? '<small class="text-muted"><i class="fas fa-lock"></i> Ορίζεται από τον διαχειριστή που αναθέτει</small>' : '') . '</div></div>';
    if ($lockChar) {
        echo '<div class="form-group"><label class="col-sm-3 control-label">Ανάθεση σε</label><div class="col-sm-9">'
            . '<p class="form-control-static">' . cpm_e(Db::adminName($t->assignee)) . ' <small class="text-muted"><i class="fas fa-lock"></i> από τον διαχειριστή</small></p></div></div>';
    } else {
        echo '<div class="form-group"><label class="col-sm-3 control-label">Ανάθεση σε</label><div class="col-sm-9"><select name="assignee" class="form-control"><option value="">— κανείς —</option>';
        foreach (Db::admins() as $a) {
            echo '<option value="' . (int) $a->id . '"' . (((int) ($t->assignee ?? 0)) === (int) $a->id ? ' selected' : '') . '>' . cpm_e(trim($a->firstname . ' ' . $a->lastname)) . '</option>';
        }
        echo '</select></div></div>';
    }
    // τύπος task (GoodDay-style) + εκτίμηση
    echo '<div class="form-group"><label class="col-sm-3 control-label">Τύπος</label><div class="col-sm-9"><select name="type_id" class="form-control">';
    echo '<option value="">— γενικό —</option>';
    foreach (Db::taskTypes() as $ty) {
        $req = [];
        if ($ty->req_assignee) { $req[] = 'ανάθεση'; }
        if ($ty->req_due) { $req[] = 'λήξη'; }
        if ($ty->req_estimate) { $req[] = 'εκτίμηση'; }
        echo '<option value="' . (int) $ty->id . '"' . (((int) ($t->type_id ?? 0)) === (int) $ty->id ? ' selected' : '') . '>'
            . cpm_e($ty->name) . ($req ? ' (απαιτεί: ' . implode(', ', $req) . ')' : '') . '</option>';
    }
    echo '</select></div></div>';
    $eh = $t && $t->estimate_minutes ? intdiv($t->estimate_minutes, 60) : '';
    $em = $t && $t->estimate_minutes ? $t->estimate_minutes % 60 : '';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Εκτίμηση χρόνου</label><div class="col-sm-9">'
        . '<input type="number" step="0.5" min="0" name="est_h" class="form-control" style="width:90px;display:inline-block" placeholder="ώρες" value="' . cpm_e($eh) . '"> '
        . '<input type="number" min="0" max="59" name="est_m" class="form-control" style="width:90px;display:inline-block" placeholder="λεπτά" value="' . cpm_e($em) . '"></div></div>';
    // «η μπάλα» — ποιος πρέπει να κινηθεί τώρα
    echo '<div class="form-group"><label class="col-sm-3 control-label">⚡ Απαιτεί ενέργεια από</label><div class="col-sm-9"><select name="action_user" class="form-control">';
    echo '<option value="">— κανέναν —</option>';
    foreach (Db::admins() as $a) {
        echo '<option value="' . (int) $a->id . '"' . (((int) ($t->action_user ?? 0)) === (int) $a->id ? ' selected' : '') . '>' . cpm_e(trim($a->firstname . ' ' . $a->lastname)) . '</option>';
    }
    echo '</select><small class="text-muted">Σε ποιον είναι «η μπάλα» — θα ειδοποιηθεί με καμπανάκι.</small></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Λήξη</label><div class="col-sm-9"><input type="date" name="due_date" class="form-control" value="' . cpm_e($t->due_date ?? '') . '"></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Προγραμματισμένο για</label><div class="col-sm-9"><input type="date" name="schedule_date" class="form-control" value="' . cpm_e($t->schedule_date ?? '') . '">'
        . '<small class="text-muted">Πότε σκοπεύεις να το δουλέψεις (≠ λήξη) — φαίνεται στο «Η δουλειά μου».</small></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Ticket ID</label><div class="col-sm-9"><input type="number" name="ticketid" class="form-control" value="' . cpm_e($t->ticketid ?? ((int) ($_REQUEST['ticketid'] ?? 0) ?: '')) . '" placeholder="εσωτερικό id ticket (προαιρετικό)"></div></div>';
    // custom fields του project (3.5)
    $cfProject = (int) ($t->project_id ?? ($_REQUEST['project'] ?? 0));
    if ($cfProject) {
        $cfVals = $t ? Db::fieldValues($t->id) : [];
        foreach (Db::fieldsForProject($cfProject) as $cf) {
            $v = $cfVals[(int) $cf->id] ?? '';
            echo '<div class="form-group"><label class="col-sm-3 control-label">' . cpm_e($cf->label) . '</label><div class="col-sm-9">';
            if ($cf->type === 'select') {
                echo '<select name="cf[' . (int) $cf->id . ']" class="form-control"><option value="">—</option>';
                foreach (array_filter(array_map('trim', explode("\n", (string) $cf->options))) as $opt) {
                    echo '<option value="' . cpm_e($opt) . '"' . ($v === $opt ? ' selected' : '') . '>' . cpm_e($opt) . '</option>';
                }
                echo '</select>';
            } elseif ($cf->type === 'date') {
                echo '<input type="date" name="cf[' . (int) $cf->id . ']" class="form-control" value="' . cpm_e($v) . '">';
            } else {
                echo '<input type="text" name="cf[' . (int) $cf->id . ']" class="form-control" value="' . cpm_e($v) . '">';
            }
            echo '</div></div>';
        }
    }
    echo '<div class="form-group"><label class="col-sm-3 control-label">Περιγραφή / Ανάλυση</label><div class="col-sm-9"><textarea name="descr" class="form-control" rows="5">' . cpm_e($t->descr ?? '') . '</textarea></div></div>';
    echo '<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><button class="btn btn-primary">Αποθήκευση</button>';
    if ($t) {
        echo ' <button form="cnpDelForm" class="btn btn-danger" onclick="return confirm(\'Οριστική διαγραφή του task (μαζί με σχόλια/ιστορικό);\')">Διαγραφή</button>';
    }
    echo '</div></div></form>';
    if ($t) {
        echo '<form id="cnpDelForm" method="post" action="' . $link . '"><input type="hidden" name="do" value="deletetask"><input type="hidden" name="taskid" value="' . $id . '"></form>';
    }
    echo '</div></div>';

    // checklist (3.2)
    if ($t) {
        $items = Db::checklist($t->id);
        $doneN = 0;
        foreach ($items as $it) { $doneN += (int) $it->done; }
        echo '<div class="panel panel-default" id="checklist"><div class="panel-heading"><b><i class="far fa-check-square"></i> Checklist</b>'
            . (count($items) ? ' <span class="label label-' . ($doneN >= count($items) ? 'success' : 'default') . '">' . $doneN . '/' . count($items) . '</span>' : '')
            . '</div><div class="panel-body">';
        if (count($items)) {
            $pct = (int) round($doneN / count($items) * 100);
            echo '<div class="progress" style="height:8px;margin-bottom:10px"><div class="progress-bar progress-bar-success" style="width:' . $pct . '%"></div></div>';
        }
        foreach ($items as $it) {
            echo '<div style="display:flex;align-items:center;gap:8px;padding:3px 0">';
            echo '<form method="post" action="' . $link . '" style="margin:0"><input type="hidden" name="do" value="toggleitem"><input type="hidden" name="itemid" value="' . (int) $it->id . '">'
                . '<button class="btn btn-xs ' . ($it->done ? 'btn-success' : 'btn-default') . '" title="Εναλλαγή"><i class="fas fa-check"></i></button></form>';
            echo '<span style="flex:1;' . ($it->done ? 'text-decoration:line-through;color:#8291a9' : '') . '">' . cpm_e($it->title) . '</span>';
            echo '<form method="post" action="' . $link . '" style="margin:0"><input type="hidden" name="do" value="delitem"><input type="hidden" name="itemid" value="' . (int) $it->id . '"><input type="hidden" name="taskid" value="' . (int) $t->id . '">'
                . '<button class="btn btn-xs btn-link text-danger" style="padding:0 4px"><i class="fas fa-times"></i></button></form>';
            echo '</div>';
        }
        echo '<form method="post" action="' . $link . '" class="form-inline" style="margin-top:8px">'
            . '<input type="hidden" name="do" value="additem"><input type="hidden" name="taskid" value="' . (int) $t->id . '">'
            . '<input type="text" name="title" class="form-control input-sm" style="width:70%" placeholder="Νέο βήμα…" required> '
            . '<button class="btn btn-sm btn-default"><i class="fas fa-plus"></i></button></form>';
        echo '</div></div>';
    }

    // comments (1.9) + «προς» (στοχευμένη εσωτερική επικοινωνία)
    if ($t) {
        echo '<div class="panel panel-default" id="comments"><div class="panel-heading"><b>Σχόλια / Συνομιλία</b></div><div class="panel-body">';
        foreach (Db::comments($id) as $c) {
            $toTxt = '';
            if ($c->to_admin !== null) {
                $toTxt = ' <span class="label label-info" style="font-weight:400">προς: '
                    . ((int) $c->to_admin === -1 ? 'Διαχειριστές' : cpm_e(Db::adminName($c->to_admin))) . '</span>';
            }
            echo '<div style="border-bottom:1px solid #eee;padding:8px 0"><b>' . cpm_e(Db::adminName($c->admin_id)) . '</b>' . $toTxt . ' '
                . '<small class="text-muted">' . cpm_e($c->created_at) . '</small><br>' . nl2br(cpm_e($c->comment)) . '</div>';
        }
        echo '<form method="post" action="' . $link . '" style="margin-top:12px"><input type="hidden" name="do" value="addcomment"><input type="hidden" name="taskid" value="' . $id . '">';
        echo '<textarea name="comment" class="form-control" rows="3" placeholder="Σχόλιο / μήνυμα προς την ομάδα…" required></textarea>';
        echo '<div style="margin-top:8px;display:flex;gap:8px;align-items:center">'
            . '<select name="to_admin" class="form-control input-sm" style="width:auto"><option value="">— απλό σχόλιο —</option>'
            . '<option value="-1">📣 Προς διαχειριστές</option>';
        foreach (Db::admins() as $a) {
            if ((int) $a->id === $adminId) { continue; }
            echo '<option value="' . (int) $a->id . '">Προς ' . cpm_e(trim($a->firstname . ' ' . $a->lastname)) . '</option>';
        }
        echo '</select><button class="btn btn-primary btn-sm">Αποστολή</button>'
            . '<small class="text-muted">Με «προς» → καμπανάκι + email στον παραλήπτη.</small></div></form>';
        echo '</div></div>';
    }
    echo '</div><div class="col-md-5">';

    if ($t) {
        // Ενέργειες: παρακολούθηση / υπενθύμιση / ζήτα ενημέρωση
        $watching = in_array($adminId, Db::watcherIds($id), true);
        $refT = $link . '&tab=task&id=' . $id;
        echo '<div class="panel panel-default"><div class="panel-body" style="padding:10px 14px">';
        echo '<form method="post" action="' . $link . '" style="display:inline"><input type="hidden" name="do" value="togglewatch"><input type="hidden" name="taskid" value="' . $id . '"><input type="hidden" name="ref" value="' . cpm_e($refT) . '">'
            . '<button class="btn btn-sm ' . ($watching ? 'btn-info' : 'btn-default') . '"><i class="far fa-eye"></i> '
            . ($watching ? 'Παρακολουθείς ✓' : 'Παρακολούθηση') . '</button></form> ';
        if (cpm_is_full() && $t->assignee && (int) $t->assignee !== $adminId) {
            echo '<form method="post" action="' . $link . '" style="display:inline"><input type="hidden" name="do" value="requestupdate"><input type="hidden" name="taskid" value="' . $id . '"><input type="hidden" name="ref" value="' . cpm_e($refT) . '">'
                . '<button class="btn btn-sm btn-warning" title="Ping στον χειριστή: πού είμαστε;"><i class="fas fa-question-circle"></i> Ζήτα ενημέρωση</button></form> ';
        }
        $nWatch = count(Db::watcherIds($id));
        if ($nWatch) {
            echo '<small class="text-muted" style="margin-left:6px"><i class="far fa-eye"></i> ' . $nWatch . '</small>';
        }
        // προσωπική υπενθύμιση
        echo '<form method="post" action="' . $link . '" class="form-inline" style="margin-top:9px;border-top:1px solid #eef2f7;padding-top:9px">'
            . '<input type="hidden" name="do" value="addreminder"><input type="hidden" name="taskid" value="' . $id . '"><input type="hidden" name="ref" value="' . cpm_e($refT) . '">'
            . '<i class="far fa-bell text-muted"></i> <input type="datetime-local" name="remind_at" class="form-control input-sm" value="' . date('Y-m-d\T09:00', strtotime('+1 day')) . '"> '
            . '<input type="text" name="note" class="form-control input-sm" style="width:170px" placeholder="υπενθύμισέ μου…"> '
            . '<button class="btn btn-sm btn-default">Ορισμός</button></form>';
        foreach (Db::remindersForTask($id, $adminId) as $rm) {
            echo '<div style="font-size:11px;color:#8291a9;margin-top:4px"><i class="far fa-bell"></i> εκκρεμεί: '
                . cpm_e(date('d/m H:i', strtotime($rm->remind_at))) . ($rm->note ? ' — ' . cpm_e($rm->note) : '') . '</div>';
        }
        echo '</div></div>';

        // linked ticket panel
        if ($t->ticketid) {
            $tk = Capsule::table('tbltickets')->where('id', $t->ticketid)->first(['tid', 'title', 'status']);
            if ($tk) {
                echo '<div class="panel panel-info"><div class="panel-heading"><b>Συνδεδεμένο ticket</b></div><div class="panel-body">'
                    . '<a href="supporttickets.php?action=view&id=' . (int) $t->ticketid . '"><b>#' . cpm_e($tk->tid) . '</b> ' . cpm_e($tk->title) . '</a>'
                    . ' <span class="label label-default">' . cpm_e($tk->status) . '</span></div></div>';
            }
        }
        // time tracking (2.1 / 2.2)
        cpm_time_panel($link, $adminId, $t);

        // activity (1.10)
        echo '<div class="panel panel-default"><div class="panel-heading"><b>Ιστορικό ενεργειών</b></div><div class="panel-body" style="max-height:420px;overflow:auto">';
        $labels = ['create' => 'Δημιουργία', 'status' => 'Αλλαγή status', 'assign' => 'Ανάθεση', 'edit' => 'Επεξεργασία', 'comment' => 'Σχόλιο', 'auto' => 'Αυτόματο'];
        foreach (Db::activity($id) as $a) {
            echo '<div style="border-bottom:1px solid #f0f0f0;padding:6px 0;font-size:12px">'
                . '<b>' . cpm_e($labels[$a->action] ?? $a->action) . '</b> — ' . cpm_e($a->detail)
                . '<br><span class="text-muted">' . cpm_e(Db::adminName($a->admin_id)) . ' · ' . cpm_e($a->created_at) . '</span></div>';
        }
        echo '</div></div>';
    }
    echo '</div></div>';
}

/* ------------------------------------------------------------------ */
/* Ημερολόγιο (3.6)                                                   */
/* ------------------------------------------------------------------ */

function cpm_tab_calendar($link)
{
    $ym = preg_match('/^\d{4}-\d{2}$/', $_GET['ym'] ?? '') ? $_GET['ym'] : date('Y-m');
    $firstTs = strtotime($ym . '-01');
    $prev = date('Y-m', strtotime($ym . '-01 -1 month'));
    $next = date('Y-m', strtotime($ym . '-01 +1 month'));
    $today = date('Y-m-d');
    $monthNames = [1 => 'Ιανουάριος', 'Φεβρουάριος', 'Μάρτιος', 'Απρίλιος', 'Μάιος', 'Ιούνιος',
        'Ιούλιος', 'Αύγουστος', 'Σεπτέμβριος', 'Οκτώβριος', 'Νοέμβριος', 'Δεκέμβριος'];

    echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">'
        . '<a class="btn btn-default btn-sm" href="' . $link . '&tab=calendar&ym=' . $prev . '">&larr;</a>'
        . '<b style="font-size:17px">' . $monthNames[(int) date('n', $firstTs)] . ' ' . date('Y', $firstTs) . '</b>'
        . '<a class="btn btn-default btn-sm" href="' . $link . '&tab=calendar&ym=' . $next . '">&rarr;</a>'
        . ($ym !== date('Y-m') ? '<a class="btn btn-link btn-sm" href="' . $link . '&tab=calendar">Σήμερα</a>' : '')
        . '</div>';

    // tasks του μήνα ανά ημέρα (μόνο όσα βλέπει ο admin)
    $byDay = [];
    $aid = cpm_admin_id();
    foreach (Db::tasksForMonth($ym) as $t) {
        if (!cpm_is_full() && (int) $t->assignee !== $aid && !Db::canSeeProject($aid, $t->project_id)) {
            continue;
        }
        $byDay[$t->due_date][] = $t;
    }

    $daysInMonth = (int) date('t', $firstTs);
    $startDow = (int) date('N', $firstTs); // 1=Δευτέρα
    echo '<table class="cnp-cal"><thead><tr>';
    foreach (['Δευ', 'Τρί', 'Τετ', 'Πέμ', 'Παρ', 'Σάβ', 'Κυρ'] as $d) {
        echo '<th>' . $d . '</th>';
    }
    echo '</tr></thead><tbody><tr>';
    for ($i = 1; $i < $startDow; $i++) {
        echo '<td class="other"></td>';
    }
    $col = $startDow - 1;
    for ($day = 1; $day <= $daysInMonth; $day++) {
        if ($col === 7) {
            echo '</tr><tr>';
            $col = 0;
        }
        $date = $ym . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
        echo '<td class="' . ($date === $today ? 'today' : '') . '"><div class="d">' . $day . '</div>';
        foreach ($byDay[$date] ?? [] as $t) {
            $cls = 'ev';
            if ($t->completed_at) { $cls .= ' done'; } elseif ($date < $today) { $cls .= ' over'; }
            echo '<a class="' . $cls . '" style="border-color:' . cpm_e($t->project_color ?: '#8595ac') . '" '
                . 'href="' . $link . '&tab=task&id=' . (int) $t->id . '" '
                . 'title="' . cpm_e($t->title . ' — ' . ($t->project_name ?: 'Χωρίς έργο') . ($t->assignee ? ' · ' . Db::adminName($t->assignee) : '')) . '">'
                . ((int) $t->priority === 2 ? '<b style="color:#d92d3a">!</b> ' : ((int) $t->priority === 1 ? '<b style="color:#e0a020">!</b> ' : ''))
                . cpm_e($t->title) . '</a>';
        }
        echo '</td>';
        $col++;
    }
    while ($col < 7) {
        echo '<td class="other"></td>';
        $col++;
    }
    echo '</tr></tbody></table>';
    echo '<p class="text-muted" style="margin-top:8px;font-size:12px">Εμφανίζονται tasks με ημερομηνία λήξης · χρώμα = project · κόκκινο φόντο = εκπρόθεσμο · αχνό = ολοκληρωμένο.</p>';
}

/* ------------------------------------------------------------------ */
/* Client area — πρόοδος projects πελάτη (5.2)                        */
/* ------------------------------------------------------------------ */

function cloudonprojects_clientarea($vars)
{
    // Δημόσια φόρμα αιτημάτων (χωρίς login) — κάθε υποβολή = lead στο CRM
    if (($_REQUEST['action'] ?? '') === 'request') {
        $enabled = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'request_form')->value('value') === 'on';
        if (!$enabled) {
            return ['pagetitle' => 'Αίτημα', 'templatefile' => 'requestform', 'vars' => ['cnpFormOff' => true]];
        }
        $sent = false;
        $err = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // anti-spam: honeypot + ελάχιστος χρόνος συμπλήρωσης 3"
            $hp = trim($_POST['website'] ?? '');
            $ts = (int) ($_POST['fts'] ?? 0);
            $name = mb_substr(trim($_POST['contact'] ?? ''), 0, 120);
            $email = mb_substr(trim($_POST['email'] ?? ''), 0, 120);
            $msg = mb_substr(trim($_POST['message'] ?? ''), 0, 5000);
            if ($hp !== '' || (time() - $ts) < 3) {
                $sent = true; // σιωπηλή απόρριψη bot
            } elseif ($name === '' || $msg === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $err = 'Συμπλήρωσε όνομα, έγκυρο email και το αίτημά σου.';
            } else {
                $lid = Db::saveLead(0, [
                    'company'     => mb_substr(trim($_POST['company'] ?? ''), 0, 120) ?: null,
                    'contact'     => $name,
                    'email'       => $email,
                    'phone'       => mb_substr(trim($_POST['phone'] ?? ''), 0, 40) ?: null,
                    'source'      => 'Φόρμα site',
                    'stage'       => 'target',
                    'next_action' => date('Y-m-d'),
                    'next_note'   => 'Νέο αίτημα από τη φόρμα — επικοινώνησε',
                    'descr'       => $msg,
                ]);
                foreach (Db::fullAccessAdminIds() as $mgr) {
                    Db::pushNotification($mgr, 'info', '📝 Νέο αίτημα από φόρμα: ' . ($name ?: $email),
                        'addonmodules.php?module=cloudonprojects&tab=lead&id=' . $lid);
                }
                if (function_exists('logActivity')) {
                    logActivity('CPM: νέο lead #' . $lid . ' από δημόσια φόρμα (' . $email . ')');
                }
                $sent = true;
            }
        }
        return [
            'pagetitle'    => 'Αίτημα / Ενδιαφέρον',
            'templatefile' => 'requestform',
            'vars'         => ['cnpSent' => $sent, 'cnpErr' => $err, 'cnpTs' => time()],
        ];
    }

    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        return ['pagetitle' => 'Τα Projects μου', 'templatefile' => 'clientarea', 'vars' => ['cnpNoAccess' => true]];
    }
    $statuses = [];
    foreach (Db::statuses() as $s) {
        $statuses[(int) $s->id] = $s;
    }
    $doneIds = [];
    foreach ($statuses as $sid => $s) {
        if ($s->is_done) {
            $doneIds[] = $sid;
        }
    }
    $projects = [];
    $rows = Capsule::table('mod_cpm_projects')->where('clientid', $uid)
        ->where('status', 'active')->where('client_visible', 1)->orderBy('name')->get();
    foreach ($rows as $p) {
        $tasks = Capsule::table('mod_cpm_tasks')->where('project_id', $p->id)
            ->orderByRaw('completed_at IS NULL DESC')->orderBy('due_date')->orderBy('id', 'desc')->get();
        $total = count($tasks);
        $done = 0;
        $open = [];
        $recent = [];
        foreach ($tasks as $t) {
            $isDone = in_array((int) $t->status_id, $doneIds, true);
            if ($isDone) {
                $done++;
                if (count($recent) < 8 && $t->completed_at) {
                    $recent[] = ['title' => $t->title, 'date' => date('d/m/Y', strtotime($t->completed_at))];
                }
            } elseif (count($open) < 15) {
                $st = $statuses[(int) $t->status_id] ?? null;
                $open[] = [
                    'title'  => $t->title,
                    'status' => $st->title ?? '—',
                    'color'  => $st->color ?? '#8291a9',
                    'due'    => $t->due_date ? date('d/m/Y', strtotime($t->due_date)) : '',
                ];
            }
        }
        $projects[] = [
            'name'    => $p->name,
            'descr'   => $p->descr,
            'color'   => $p->color,
            'total'   => $total,
            'done'    => $done,
            'pct'     => $total ? (int) round($done / $total * 100) : 0,
            'open'    => $open,
            'recent'  => $recent,
        ];
    }
    // Portal v2: tickets + προαγορασμένες ώρες + πρόσφατη δραστηριότητα
    $tickets = [];
    foreach (Capsule::table('tbltickets')->where('userid', $uid)
        ->orderBy('lastreply', 'desc')->limit(10)->get(['id', 'tid', 'title', 'status', 'lastreply', 'c']) as $tk) {
        $tickets[] = ['tid' => $tk->tid, 'c' => $tk->c, 'title' => $tk->title, 'status' => $tk->status,
            'open' => !in_array($tk->status, ['Closed', 'Cancelled'], true),
            'last' => date('d/m/Y', strtotime($tk->lastreply))];
    }
    $scBalance = null;
    try {
        if (Capsule::schema()->hasTable('mod_supportcontracts_clients')) {
            $bal = Capsule::table('mod_supportcontracts_clients')->where('userid', $uid)->value('balance_minutes');
            if ($bal !== null) {
                $h = intdiv((int) $bal, 60);
                $m = ((int) $bal) % 60;
                $scBalance = ['txt' => ($h ? $h . 'ω ' : '') . ($m || !$h ? $m . '΄' : ''), 'low' => (int) $bal <= 0];
            }
        }
    } catch (\Throwable $e) {
    }
    return [
        'pagetitle'    => 'Τα Projects μου',
        'breadcrumb'   => ['index.php?m=cloudonprojects' => 'Τα Projects μου'],
        'templatefile' => 'clientarea',
        'requirelogin' => true,
        'vars'         => ['cnpProjects' => $projects, 'cnpTickets' => $tickets, 'cnpSc' => $scBalance],
    ];
}

/* ------------------------------------------------------------------ */
/* Κερδοφορία — έσοδα vs κόστος ωρών + έξοδα (full μόνο)              */
/* ------------------------------------------------------------------ */

function cpm_tab_profit($link)
{
    if (!cpm_is_full()) {
        echo '<div class="alert alert-warning"><i class="fas fa-lock"></i> Μόνο για διαχειριστές.</div>';
        return;
    }
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-01-01');
    $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
    $costH = (float) str_replace(',', '.', (string) (Capsule::table('tbladdonmodules')
        ->where('module', 'cloudonprojects')->where('setting', 'cost_per_hour')->value('value') ?: 0));

    echo '<form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom:14px">'
        . '<input type="hidden" name="module" value="cloudonprojects"><input type="hidden" name="tab" value="profit"> '
        . 'Από <input type="date" name="from" class="form-control" value="' . cpm_e($from) . '"> '
        . 'έως <input type="date" name="to" class="form-control" value="' . cpm_e($to) . '"> '
        . '<button class="btn btn-default">Προβολή</button> '
        . '<span class="text-muted" style="margin-left:10px">Κόστος ώρας: <b>' . cpm_fmt_eur($costH) . '</b>'
        . ($costH <= 0 ? ' — <a href="configaddonmods.php">όρισέ το στις ρυθμίσεις</a> για σωστό κόστος εργασίας' : '') . '</span></form>';

    /* ---- κόστος ωρών ανά πελάτη (timelogs μέσω project ή sc_userid) ---- */
    $mins = [];
    foreach (Capsule::table('mod_cpm_timelogs as l')
        ->join('mod_cpm_tasks as t', 't.id', '=', 'l.task_id')
        ->leftJoin('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
        ->where('l.running', 0)->whereBetween('l.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
        ->selectRaw('COALESCE(p.clientid, l.sc_userid, 0) as cid, SUM(l.minutes) m')->groupBy('cid')->get() as $r) {
        $mins[(int) $r->cid] = (int) $r->m;
    }
    /* ---- έξοδα ανά πελάτη (μέσω project) ---- */
    $exp = [];
    foreach (Db::expenses($from, $to) as $e) {
        $exp[(int) ($e->clientid ?: 0)] = ($exp[(int) ($e->clientid ?: 0)] ?? 0) + (float) $e->amount;
    }
    /* ---- έσοδα ανά πελάτη (πληρωμές) — μόνο για πελάτες που έχουμε δουλέψει/ξοδέψει ---- */
    $cids = array_unique(array_merge(array_keys($mins), array_keys($exp)));
    $cids = array_values(array_filter($cids));
    $rev = [];
    if ($cids) {
        foreach (Capsule::table('tblaccounts')->whereIn('userid', $cids)
            ->whereBetween('date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('userid, SUM(amountin) - SUM(amountout) as v')->groupBy('userid')->get() as $r) {
            $rev[(int) $r->userid] = (float) $r->v;
        }
    }

    $rows = [];
    foreach ($cids as $cid) {
        $laborCost = round(($mins[$cid] ?? 0) / 60 * $costH, 2);
        $expense = round($exp[$cid] ?? 0, 2);
        $revenue = round($rev[$cid] ?? 0, 2);
        $profit = $revenue - $laborCost - $expense;
        $rows[] = ['cid' => $cid, 'mins' => $mins[$cid] ?? 0, 'labor' => $laborCost,
                   'exp' => $expense, 'rev' => $revenue, 'profit' => $profit];
    }
    usort($rows, function ($a, $b) { return $a['profit'] <=> $b['profit']; }); // χειρότεροι πρώτα

    $totR = $totL = $totE = 0;
    foreach ($rows as $r) { $totR += $r['rev']; $totL += $r['labor']; $totE += $r['exp']; }
    echo '<div class="row">';
    foreach ([['Έσοδα περιόδου (πελάτες με έργο)', cpm_fmt_eur($totR), 'ok'],
              ['Κόστος εργασίας', cpm_fmt_eur($totL), 'warn'],
              ['Έξοδα projects', cpm_fmt_eur($totE), 'warn'],
              ['Καθαρό', cpm_fmt_eur($totR - $totL - $totE), ($totR - $totL - $totE) >= 0 ? 'ok' : 'bad']] as $c) {
        echo '<div class="col-sm-3"><div class="cnp-stat ' . $c[2] . '"><b>' . $c[1] . '</b><small>' . $c[0] . '</small></div></div>';
    }
    echo '</div>';

    echo '<div class="panel panel-default"><div class="panel-heading"><b><i class="fas fa-coins"></i> Ανά πελάτη</b> <small class="text-muted">(οι ζημιογόνοι πρώτα)</small></div>'
        . '<table class="table table-striped table-condensed" style="margin:0;font-size:12px"><thead><tr>'
        . '<th>Πελάτης</th><th>Χρόνος</th><th>Κόστος εργασίας</th><th>Έξοδα</th><th>Έσοδα</th><th>Κέρδος</th><th>Περιθώριο</th></tr></thead><tbody>';
    if (!count($rows)) {
        echo '<tr><td colspan="7" class="text-center text-muted">Καμία εργασία/έξοδο στην περίοδο.</td></tr>';
    }
    foreach ($rows as $r) {
        $margin = $r['rev'] > 0 ? (int) round($r['profit'] / $r['rev'] * 100) : null;
        echo '<tr><td><a href="' . $link . '&tab=client&client=' . $r['cid'] . '"><b>' . cpm_e(cpm_client_label($r['cid'])) . '</b></a></td>'
            . '<td>' . cpm_e(Time::fmt($r['mins'])) . '</td>'
            . '<td>' . cpm_fmt_eur($r['labor']) . '</td><td>' . cpm_fmt_eur($r['exp']) . '</td>'
            . '<td>' . cpm_fmt_eur($r['rev']) . '</td>'
            . '<td class="' . ($r['profit'] >= 0 ? 'text-success' : 'text-danger') . '"><b>' . cpm_fmt_eur($r['profit']) . '</b></td>'
            . '<td>' . ($margin === null ? '—' : $margin . '%') . '</td></tr>';
    }
    echo '</tbody></table></div>';

    /* ---- έξοδα projects (CRUD) ---- */
    echo '<div class="panel panel-default"><div class="panel-heading"><b><i class="fas fa-receipt"></i> Έξοδα projects</b></div><div class="panel-body">';
    echo '<form method="post" action="' . $link . '" class="form-inline" style="margin-bottom:10px">'
        . '<input type="hidden" name="do" value="addexpense">'
        . '<select name="project_id" class="form-control input-sm">';
    foreach (Db::projects(true) as $p) {
        echo '<option value="' . (int) $p->id . '">' . cpm_e($p->name) . '</option>';
    }
    echo '</select> <input type="text" name="descr" class="form-control input-sm" style="width:220px" placeholder="περιγραφή (π.χ. licenses, hosting)" required> '
        . '<input type="number" step="0.01" min="0.01" name="amount" class="form-control input-sm" style="width:100px" placeholder="ποσό €" required> '
        . '<input type="date" name="spent_at" class="form-control input-sm" value="' . date('Y-m-d') . '"> '
        . '<button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Καταχώρηση</button></form>';
    $exRows = Db::expenses($from, $to);
    if (count($exRows)) {
        echo '<table class="table table-condensed" style="margin:0;font-size:12px"><thead><tr><th>Ημ/νία</th><th>Project</th><th>Πελάτης</th><th>Περιγραφή</th><th>Ποσό</th><th></th></tr></thead><tbody>';
        foreach ($exRows as $e) {
            echo '<tr><td>' . cpm_e(date('d/m/Y', strtotime($e->spent_at))) . '</td>'
                . '<td>' . cpm_e($e->project_name) . '</td>'
                . '<td>' . cpm_e($e->clientid ? cpm_client_label($e->clientid) : '—') . '</td>'
                . '<td>' . cpm_e($e->descr) . '</td><td><b>' . cpm_fmt_eur($e->amount) . '</b></td>'
                . '<td><form method="post" action="' . $link . '" style="display:inline" onsubmit="return confirm(\'Διαγραφή εξόδου;\')">'
                . '<input type="hidden" name="do" value="delexpense"><input type="hidden" name="id" value="' . (int) $e->id . '">'
                . '<button class="btn btn-xs btn-link text-danger"><i class="fas fa-times"></i></button></form></td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="text-muted" style="margin:0">Κανένα έξοδο στην περίοδο.</p>';
    }
    echo '</div></div>';
}

/* ------------------------------------------------------------------ */
/* Ομάδες — οργανόγραμμα                                              */
/* ------------------------------------------------------------------ */

function cpm_tab_teams($link, $adminId)
{
    $full = cpm_is_full();
    $teams = Db::teams();

    // διαθέσιμοι ρόλοι: από τις ρυθμίσεις + όσοι ήδη χρησιμοποιούνται
    $rolesCfg = (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', 'team_roles')->value('value')
        ?: 'Διαχειριστής έργου,Senior Τεχνικός,Τεχνικός,Υποστήριξη,Πωλήσεις,Λογιστήριο,Developer');
    $roleOptions = array_values(array_filter(array_map('trim', explode(',', $rolesCfg))));
    foreach (Capsule::table('mod_cpm_team_members')->whereNotNull('role_title')
        ->distinct()->pluck('role_title')->all() as $used) {
        if ($used !== '' && !in_array($used, $roleOptions, true)) {
            $roleOptions[] = $used; // παλιοί ρόλοι παραμένουν επιλέξιμοι
        }
    }

    if ($full) {
        // νέα ομάδα
        echo '<form method="post" action="' . $link . '" class="form-inline" style="margin-bottom:16px">'
            . '<input type="hidden" name="do" value="saveteam"><input type="hidden" name="id" value="0">'
            . '<input type="text" name="name" class="form-control input-sm" placeholder="Όνομα ομάδας (π.χ. Τεχνικό τμήμα)" required> '
            . '<input type="color" name="color" class="form-control input-sm" style="width:46px;padding:2px" value="#0097e4"> '
            . '<input type="text" name="descr" class="form-control input-sm" style="width:240px" placeholder="Περιγραφή (προαιρετικά)"> '
            . '<button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Νέα ομάδα</button></form>';
    }

    if (!count($teams)) {
        echo '<div class="alert alert-info">Δεν υπάρχουν ομάδες ακόμη.'
            . ($full ? ' Φτιάξε την πρώτη (π.χ. «Τεχνικό τμήμα», «Πωλήσεις») και πρόσθεσε μέλη.' : '') . '</div>';
        return;
    }

    // ανάθεση μελών: ποιοι admins δεν ανήκουν πουθενά
    $assigned = [];
    echo '<div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">';
    foreach ($teams as $tm) {
        $members = Db::teamMembers($tm->id);
        // projects της ομάδας
        $projNames = Capsule::table('mod_cpm_project_teams as pt')
            ->join('mod_cpm_projects as p', 'p.id', '=', 'pt.project_id')
            ->where('pt.team_id', $tm->id)->pluck('p.name')->all();
        echo '<div style="flex:1 1 300px;min-width:290px;max-width:420px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden">';
        echo '<div style="padding:11px 14px;border-top:4px solid ' . cpm_e($tm->color) . ';display:flex;justify-content:space-between;align-items:center">'
            . '<b style="font-size:14.5px">' . cpm_e($tm->name) . '</b>'
            . '<span class="cnp-col-n">' . count($members) . '</span></div>';
        if ($tm->descr) {
            echo '<div style="padding:0 14px 6px;color:#8291a9;font-size:12px">' . cpm_e($tm->descr) . '</div>';
        }
        /* Δικαιώματα & departments ορίζονται στην εφαρμογή PM. Εδώ φαίνονται
           μόνο — αλλιώς μια ομάδα φτιαγμένη από αυτή τη σελίδα δείχνει πλήρης
           ενώ δεν δίνει τίποτα και δεν εξυπηρετεί κανένα department. */
        $areaL = ['support' => 'Υποστήριξη', 'projects' => 'Έργα', 'sales' => 'Πωλήσεις',
            'hr' => 'Προσλήψεις', 'admin' => 'Διοίκηση'];
        $tAreas = array_filter(array_map('trim', explode(',', (string) ($tm->areas ?? ''))));
        $tDepts = Capsule::schema()->hasTable('mod_cpm_team_depts')
            ? Capsule::table('mod_cpm_team_depts as td')
                ->join('tblticketdepartments as d', 'd.id', '=', 'td.dept_id')
                ->where('td.team_id', $tm->id)->pluck('d.name')->all()
            : [];
        echo '<div style="padding:0 14px 8px;font-size:11.5px;line-height:1.9">'
            . '<span style="color:#8291a9">Εξυπηρετεί:</span> '
            . ($tDepts ? cpm_e(implode(' · ', $tDepts))
                : '<span style="color:#e0a800">κανένα department</span>') . '<br>'
            . '<span style="color:#8291a9">Πρόσβαση:</span> '
            . ($tAreas ? cpm_e(implode(' · ', array_map(function ($a) use ($areaL) {
                return $areaL[$a] ?? $a;
            }, $tAreas)))
                : '<span style="color:#e0a800">κανένα κύκλωμα</span>')
            . '</div>';
        echo '<div style="padding:4px 14px 10px">';
        if (!count($members)) {
            echo '<p class="text-muted" style="font-size:12px">Χωρίς μέλη.</p>';
        }
        foreach ($members as $m) {
            $assigned[(int) $m->admin_id] = true;
            $nm = Db::adminName($m->admin_id);
            $parts = preg_split('/\s+/', $nm);
            $ini = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
            echo '<div style="display:flex;align-items:center;gap:9px;padding:5px 0;border-bottom:1px dashed #eef2f7">'
                . '<span class="cnp-ava" style="background:' . cpm_e($tm->color) . '">' . cpm_e($ini) . '</span>'
                . '<span style="flex:1"><b>' . ($m->is_leader ? '👑 ' : '') . cpm_e($nm) . '</b>'
                . ($m->role_title ? '<br><small class="text-muted">' . cpm_e($m->role_title) . '</small>'
                    : ($m->is_leader ? '<br><small class="text-muted">Αρχηγός ομάδας</small>' : '')) . '</span>';
            if ($full) {
                echo '<form method="post" action="' . $link . '" style="margin:0" onsubmit="return confirm(\'Αφαίρεση από την ομάδα;\')">'
                    . '<input type="hidden" name="do" value="delteammember"><input type="hidden" name="team_id" value="' . (int) $tm->id . '"><input type="hidden" name="admin_id" value="' . (int) $m->admin_id . '">'
                    . '<button class="btn btn-xs btn-link text-danger" style="padding:0 4px"><i class="fas fa-times"></i></button></form>';
            }
            echo '</div>';
        }
        if (count($projNames)) {
            echo '<div style="margin-top:8px;font-size:11px;color:#6b7c96"><i class="fas fa-folder-open"></i> '
                . cpm_e(implode(' · ', array_slice($projNames, 0, 5)))
                . (count($projNames) > 5 ? ' +' . (count($projNames) - 5) : '') . '</div>';
        }
        if ($full) {
            echo '<form method="post" action="' . $link . '" class="form-inline" style="margin-top:10px;border-top:1px solid #eef2f7;padding-top:9px">'
                . '<input type="hidden" name="do" value="addteammember"><input type="hidden" name="team_id" value="' . (int) $tm->id . '">'
                . '<select name="admin_id" class="form-control input-sm" style="max-width:130px">';
            foreach (Db::admins() as $a) {
                echo '<option value="' . (int) $a->id . '">' . cpm_e(trim($a->firstname . ' ' . $a->lastname)) . '</option>';
            }
            echo '</select> <select name="role_title" class="form-control input-sm" style="max-width:150px"><option value="">— ρόλος —</option>';
            foreach ($roleOptions as $ro) {
                echo '<option value="' . cpm_e($ro) . '">' . cpm_e($ro) . '</option>';
            }
            echo '</select> '
                . '<label style="font-weight:normal;font-size:11px" title="Αρχηγός ομάδας"><input type="checkbox" name="is_leader" value="1"> 👑</label> '
                . '<button class="btn btn-xs btn-default"><i class="fas fa-plus"></i></button></form>';
            echo '<form method="post" action="' . $link . '" style="margin-top:6px;text-align:right" onsubmit="return confirm(\'Διαγραφή ομάδας «' . cpm_e($tm->name) . '»; (τα μέλη δεν διαγράφονται)\')">'
                . '<input type="hidden" name="do" value="delteam"><input type="hidden" name="id" value="' . (int) $tm->id . '">'
                . '<button class="btn btn-xs btn-link text-danger">Διαγραφή ομάδας</button></form>';
        }
        echo '</div></div>';
    }

    // εκτός ομάδας
    $solo = [];
    foreach (Db::admins() as $a) {
        if (!isset($assigned[(int) $a->id])) {
            $solo[] = trim($a->firstname . ' ' . $a->lastname) . (Db::isFullAccess($a->id) ? ' (διαχειριστής)' : '');
        }
    }
    if (count($solo)) {
        echo '<div style="flex:1 1 260px;min-width:250px;max-width:340px;background:#fafbfc;border:1px dashed #cbd5e1;border-radius:12px;padding:12px 14px">'
            . '<b style="color:#8291a9"><i class="far fa-user"></i> Χωρίς ομάδα</b><div style="margin-top:6px;font-size:13px">';
        foreach ($solo as $s) {
            echo '<div style="padding:3px 0">' . cpm_e($s) . '</div>';
        }
        echo '</div></div>';
    }
    echo '</div>';
    echo '<p class="text-muted" style="margin-top:14px;font-size:12px;line-height:1.8"><i class="fas fa-info-circle"></i> '
        . '<b>Department</b> = πού απευθύνεται το αίτημα (τα ticket departments). '
        . '<b>Ομάδα</b> = ειδικότητα ανθρώπων· ένα department το εξυπηρετούν πολλές ομάδες '
        . 'και μια ομάδα εξυπηρετεί πολλά departments.<br>'
        . '<i class="fas fa-exclamation-triangle"></i> Εδώ ορίζονται μόνο <b>όνομα, χρώμα και μέλη</b>. '
        . 'Τα <b>departments που εξυπηρετεί</b> και η <b>πρόσβαση σε κυκλώματα</b> ορίζονται στην εφαρμογή '
        . '<a href="' . $link . '&pmlaunch=1" target="_blank">Project Manager → Ομάδες</a> — '
        . 'ομάδα χωρίς αυτά δεν δίνει δικαιώματα σε κανέναν.<br>'
        . 'Στην επεξεργασία ενός project μπορείς να δώσεις πρόσβαση σε ολόκληρη ομάδα αντί για μεμονωμένα μέλη.</p>';
}

/* ------------------------------------------------------------------ */
/* Πωλήσεις — λίστα στόχων + leads funnel + στόχος μήνα (CRM)         */
/* ------------------------------------------------------------------ */

function cpm_tab_sales($link)
{
    $view = in_array($_GET['view'] ?? 'funnel', ['funnel', 'contacts', 'log', 'targets'], true) ? $_GET['view'] : 'funnel';
    $subs = ['funnel' => ['fa-filter', 'Funnel'], 'contacts' => ['fa-address-book', 'Επαφές'], 'log' => ['fa-history', 'Επικοινωνίες']];
    if (cpm_is_full()) {
        $subs['targets'] = ['fa-crosshairs', 'Στόχοι προϊόντων'];
    }
    echo '<div style="margin-bottom:14px">';
    foreach ($subs as $k => $m) {
        echo '<a class="btn btn-sm ' . ($view === $k ? 'btn-primary' : 'btn-default') . '" style="margin-right:6px" href="'
            . $link . '&tab=sales&view=' . $k . '"><i class="fas ' . $m[0] . '"></i> ' . $m[1] . '</a>';
    }
    echo '</div>';
    if ($view === 'contacts') {
        cpm_sales_contacts($link);
        return;
    }
    if ($view === 'log') {
        cpm_sales_log($link);
        return;
    }
    if ($view === 'targets') {
        cpm_sales_targets($link);
        return;
    }

    $leads = Db::leads();
    if (!cpm_is_full()) {
        $aid = cpm_admin_id();
        $leads = $leads->filter(function ($l) use ($aid) {
            return (int) $l->assignee === $aid || (int) $l->created_by === $aid;
        })->values();
    }
    $stages = Db::leadStages();
    $today = date('Y-m-d');

    // στόχος πωλήσεων μήνα
    $target = (float) str_replace(',', '.', (string) (Capsule::table('tbladdonmodules')
        ->where('module', 'cloudonprojects')->where('setting', 'sales_target')->value('value') ?: 0));
    $won = Db::wonValueForMonth(date('Y-m'));
    $monthNames = [1 => 'Ιανουαρίου', 'Φεβρουαρίου', 'Μαρτίου', 'Απριλίου', 'Μαΐου', 'Ιουνίου',
        'Ιουλίου', 'Αυγούστου', 'Σεπτεμβρίου', 'Οκτωβρίου', 'Νοεμβρίου', 'Δεκεμβρίου'];

    echo '<div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-bottom:4px">';
    echo '<div class="cnp-target" style="flex:1;min-width:320px;margin-bottom:10px">'
        . '<div style="display:flex;justify-content:space-between;align-items:baseline">'
        . '<b><i class="fas fa-bullseye" style="color:#0097e4"></i> Πωλήσεις ' . $monthNames[(int) date('n')] . '</b>'
        . '<span><b style="font-size:17px">' . cpm_fmt_eur($won) . '</b>'
        . ($target > 0 ? ' <small class="text-muted">/ στόχος ' . cpm_fmt_eur($target) . '</small>' : '') . '</span></div>';
    if ($target > 0) {
        $pct = min(100, (int) round($won / $target * 100));
        echo '<div class="bar"><span style="width:' . $pct . '%"></span></div>'
            . '<small class="text-muted">' . $pct . '% του στόχου — από κερδισμένες προσφορές του μήνα</small>';
    } else {
        echo '<small class="text-muted">Όρισε μηνιαίο στόχο € στις ρυθμίσεις του module για να βλέπεις πρόοδο.</small>';
    }
    echo '</div>';
    echo '<a class="btn btn-primary" style="margin-top:8px" href="' . $link . '&tab=lead&id=0"><i class="fas fa-plus"></i> Νέος στόχος / lead</a>';
    echo '</div>';

    // ειδοποίηση για follow-ups που καίνε
    $followDue = 0;
    foreach ($leads as $l) {
        if (!$stages[$l->stage][2] && $l->next_action && $l->next_action <= $today) {
            $followDue++;
        }
    }
    if ($followDue) {
        echo '<div class="alert alert-warning" style="padding:6px 12px;display:inline-block"><i class="fas fa-phone"></i> '
            . '<b>' . $followDue . '</b> επικοινων' . ($followDue === 1 ? 'ία' : 'ίες') . ' για σήμερα ή εκπρόθεσμ' . ($followDue === 1 ? 'η' : 'ες') . '</div>';
    }

    // funnel kanban
    $byStage = [];
    foreach ($leads as $l) {
        $byStage[$l->stage][] = $l;
    }
    echo '<div class="cnp-board">';
    foreach ($stages as $key => $meta) {
        $cards = $byStage[$key] ?? [];
        echo '<div class="cnp-col cnp-lcol" data-stage="' . cpm_e($key) . '">';
        echo '<div class="cnp-col-h" style="border-color:' . $meta[1] . '">' . cpm_e($meta[0])
            . ' <span class="cnp-col-n">' . count($cards) . '</span></div>';
        echo '<div class="cnp-cards">';
        foreach ($cards as $l) {
            $overF = (!$meta[2] && $l->next_action && $l->next_action <= $today);
            echo '<a class="cnp-card' . ($overF ? ' cnp-card--over' : '') . '" draggable="true" data-lead="' . (int) $l->id . '" href="' . $link . '&tab=lead&id=' . (int) $l->id . '">';
            echo '<div class="cnp-card-t">' . cpm_e($l->company ?: $l->contact ?: '—') . '</div>';
            echo '<div class="cnp-card-m">';
            if ($l->company && $l->contact) { echo '<span><i class="far fa-user"></i> ' . cpm_e(mb_substr($l->contact, 0, 20)) . '</span>'; }
            if ($l->phone) { echo '<span><i class="fas fa-phone-alt"></i> ' . cpm_e($l->phone) . '</span>'; }
            if ($l->source) { echo '<span title="Πηγή"><i class="fas fa-tag"></i> ' . cpm_e($l->source) . '</span>'; }
            if ($l->assignee) {
                $nm = Db::adminName($l->assignee);
                $parts = preg_split('/\s+/', $nm);
                echo '<span class="cnp-ava" title="' . cpm_e($nm) . '">' . cpm_e(mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1))) . '</span>';
            }
            if ($l->next_action && !$meta[2]) {
                echo '<span class="' . ($overF ? 'cnp-due-over' : '') . '" title="' . cpm_e($l->next_note ?: 'Επόμενη ενέργεια') . '">'
                    . '<i class="far fa-bell"></i> ' . cpm_e(date('d/m', strtotime($l->next_action))) . '</span>';
            }
            if ($l->clientid) { echo '<span title="Πελάτης WHMCS #' . (int) $l->clientid . '" class="text-success"><i class="fas fa-user-check"></i></span>'; }
            echo '</div></a>';
        }
        echo '</div></div>';
    }
    echo '</div>';

    echo '<script>
(function(){
  var dragId=null;
  document.querySelectorAll(".cnp-card[data-lead]").forEach(function(c){
    c.addEventListener("dragstart",function(){dragId=c.getAttribute("data-lead");c.classList.add("dragging");});
    c.addEventListener("dragend",function(){c.classList.remove("dragging");});
  });
  document.querySelectorAll(".cnp-lcol").forEach(function(col){
    col.addEventListener("dragover",function(e){e.preventDefault();col.classList.add("dragover");});
    col.addEventListener("dragleave",function(){col.classList.remove("dragover");});
    col.addEventListener("drop",function(e){
      e.preventDefault();col.classList.remove("dragover");
      if(!dragId)return;
      var card=document.querySelector(".cnp-card[data-lead=\'"+dragId+"\']");
      var fd=new FormData();fd.append("do","movelead");fd.append("leadid",dragId);fd.append("stage",col.getAttribute("data-stage"));
      fetch(CPM_LINK,{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(j){
        if(j.ok&&card){ col.querySelector(".cnp-cards").appendChild(card);
          document.querySelectorAll(".cnp-lcol").forEach(function(c2){
            c2.querySelector(".cnp-col-n").textContent=c2.querySelectorAll(".cnp-card").length;});
        }
      });
      dragId=null;
    });
  });
})();
</script>';
}

/** Φόρμα καταγραφής επικοινωνίας (lead ή πελάτης). */
function cpm_interaction_form($link, $ref, $leadId = 0, $clientId = 0, $withPickers = false)
{
    $h = '<form method="post" action="' . $link . '" class="form-inline" style="margin-bottom:10px">'
        . '<input type="hidden" name="do" value="saveinteraction"><input type="hidden" name="ref" value="' . cpm_e($ref) . '">';
    if ($withPickers) {
        $h .= '<select name="lead_id" class="form-control input-sm" style="max-width:170px"><option value="">— lead —</option>';
        foreach (Db::leads() as $l) {
            if (in_array($l->stage, ['won', 'lost'], true)) { continue; }
            $h .= '<option value="' . (int) $l->id . '">' . cpm_e(mb_substr($l->company ?: $l->contact, 0, 30)) . '</option>';
        }
        $h .= '</select> ή ' . cpm_client_input('clientid', 0, '170px') . ' ';
    } else {
        $h .= '<input type="hidden" name="lead_id" value="' . (int) $leadId . '"><input type="hidden" name="clientid" value="' . (int) $clientId . '">';
    }
    $h .= '<select name="kind" class="form-control input-sm">';
    foreach (Db::interactionKinds() as $k => $m) {
        $h .= '<option value="' . $k . '">' . $m[0] . '</option>';
    }
    $h .= '</select> '
        . '<input type="text" name="summary" class="form-control input-sm" style="width:230px" placeholder="τι ειπώθηκε / τι έγινε" required> '
        . '<input type="datetime-local" name="happened_at" class="form-control input-sm" value="' . date('Y-m-d\TH:i') . '"> '
        . '<span style="white-space:nowrap">follow-up: <input type="date" name="followup_date" class="form-control input-sm"> '
        . '<input type="text" name="followup_note" class="form-control input-sm" style="width:150px" placeholder="τι θα γίνει"></span> '
        . '<button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Καταγραφή</button></form>';
    return $h;
}

/** Πίνακας επικοινωνιών. */
function cpm_interaction_rows($link, $ref, $rows, $showTarget = false)
{
    $kinds = Db::interactionKinds();
    $today = date('Y-m-d');
    $h = '<table class="table table-condensed" style="margin:0;font-size:12px"><tbody>';
    if (!count($rows)) {
        $h .= '<tr><td class="text-muted text-center">Καμία επικοινωνία ακόμη.</td></tr>';
    }
    foreach ($rows as $i) {
        $k = $kinds[$i->kind] ?? $kinds['note'];
        $h .= '<tr><td style="white-space:nowrap">' . cpm_e(date('d/m/y H:i', strtotime($i->happened_at))) . '</td>'
            . '<td style="white-space:nowrap;color:' . $k[2] . '"><i class="' . $k[1] . '"></i> ' . $k[0] . '</td>';
        if ($showTarget) {
            $target = '';
            if ($i->lead_id) {
                $target = '<a href="' . $link . '&tab=lead&id=' . (int) $i->lead_id . '">' . cpm_e($i->lead_company ?: $i->lead_contact ?: ('lead #' . $i->lead_id)) . '</a>';
            } elseif ($i->clientid) {
                $target = '<a href="' . $link . '&tab=client&client=' . (int) $i->clientid . '">' . cpm_e(cpm_client_label($i->clientid)) . '</a>';
            }
            $h .= '<td>' . $target . '</td>';
        }
        $h .= '<td><b>' . cpm_e($i->summary) . '</b>' . ($i->detail ? '<br><span class="text-muted">' . nl2br(cpm_e(mb_substr($i->detail, 0, 300))) . '</span>' : '') . '</td>'
            . '<td>' . cpm_e(Db::adminName($i->admin_id)) . '</td><td style="white-space:nowrap">';
        if ($i->followup_date) {
            if ($i->followup_done) {
                $h .= '<span class="text-muted"><i class="fas fa-check"></i> έγινε</span>';
            } else {
                $over = $i->followup_date <= $today;
                $h .= '<span class="' . ($over ? 'cnp-due-over' : '') . '" title="' . cpm_e($i->followup_note ?: '') . '"><i class="far fa-bell"></i> '
                    . cpm_e(date('d/m', strtotime($i->followup_date))) . '</span> '
                    . '<form method="post" action="' . $link . '" style="display:inline"><input type="hidden" name="do" value="donefollowup"><input type="hidden" name="id" value="' . (int) $i->id . '"><input type="hidden" name="ref" value="' . cpm_e($ref) . '">'
                    . '<button class="btn btn-xs btn-link" title="Ολοκληρώθηκε" style="padding:0 3px"><i class="fas fa-check"></i></button></form>';
            }
        }
        $h .= '</td><td><form method="post" action="' . $link . '" style="display:inline" onsubmit="return confirm(\'Διαγραφή επικοινωνίας;\')">'
            . '<input type="hidden" name="do" value="delinteraction"><input type="hidden" name="id" value="' . (int) $i->id . '"><input type="hidden" name="ref" value="' . cpm_e($ref) . '">'
            . '<button class="btn btn-xs btn-link text-danger" style="padding:0 3px"><i class="fas fa-times"></i></button></form></td></tr>';
    }
    return $h . '</tbody></table>';
}

/** Ενιαία λίστα επαφών: leads + πελάτες με CRM δραστηριότητα. */
function cpm_sales_contacts($link)
{
    $q = trim($_GET['q'] ?? '');
    echo '<form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom:12px">'
        . '<input type="hidden" name="module" value="cloudonprojects"><input type="hidden" name="tab" value="sales"><input type="hidden" name="view" value="contacts"> '
        . '<input type="text" name="q" class="form-control" style="width:260px" placeholder="Αναζήτηση σε leads & πελάτες…" value="' . cpm_e($q) . '"> '
        . '<button class="btn btn-default">Αναζήτηση</button></form>';

    $last = Db::lastContactMap();
    $stages = Db::leadStages();
    $aid = cpm_admin_id();
    $rows = [];
    // leads
    foreach (Db::leads() as $l) {
        if (!cpm_is_full() && (int) $l->assignee !== $aid && (int) $l->created_by !== $aid) {
            continue;
        }
        $name = $l->company ?: $l->contact ?: '—';
        if ($q !== '' && mb_stripos($name . ' ' . $l->contact . ' ' . $l->email . ' ' . $l->phone, $q) === false) {
            continue;
        }
        $meta = $stages[$l->stage] ?? $stages['target'];
        $rows[] = [
            'name'  => $name, 'sub' => $l->contact && $l->company ? $l->contact : '',
            'badge' => '<span class="label" style="background:' . $meta[1] . '">' . cpm_e($meta[0]) . '</span>',
            'phone' => $l->phone, 'email' => $l->email,
            'last'  => $last['lead:' . $l->id] ?? '',
            'next'  => (!$meta[2] && $l->next_action) ? $l->next_action : '',
            'who'   => Db::adminName($l->assignee),
            'href'  => $link . '&tab=lead&id=' . (int) $l->id,
        ];
    }
    // πελάτες: με CRM επικοινωνίες πάντα· με αναζήτηση, ψάχνει όλους (περιορισμένος: μόνο δικές του επαφές)
    if (cpm_is_full()) {
        $clientIds = [];
        foreach (array_keys($last) as $k) {
            if (strpos($k, 'client:') === 0) {
                $clientIds[] = (int) substr($k, 7);
            }
        }
    } else {
        $clientIds = array_map('intval', Capsule::table('mod_cpm_interactions')
            ->where('admin_id', $aid)->whereNotNull('clientid')->distinct()->pluck('clientid')->all());
    }
    $cq = Capsule::table('tblclients');
    if (!cpm_is_full()) {
        $cq->whereIn('id', $clientIds ?: [0]); // περιορισμένος: ποτέ global αναζήτηση πελατών
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $cq->where(function ($w) use ($like) {
            $w->where('firstname', 'like', $like)->orWhere('lastname', 'like', $like)
              ->orWhere('companyname', 'like', $like)->orWhere('email', 'like', $like);
        })->limit(30);
    } elseif ($clientIds) {
        $cq->whereIn('id', $clientIds);
    } else {
        $cq->whereRaw('1=0');
    }
    foreach ($cq->get(['id', 'firstname', 'lastname', 'companyname', 'email', 'phonenumber']) as $c) {
        $rows[] = [
            'name'  => $c->companyname ?: trim($c->firstname . ' ' . $c->lastname), 'sub' => '',
            'badge' => '<span class="label label-success">Πελάτης</span>',
            'phone' => $c->phonenumber, 'email' => $c->email,
            'last'  => $last['client:' . $c->id] ?? '', 'next' => '',
            'who'   => '', 'href' => $link . '&tab=client&client=' . (int) $c->id,
        ];
    }
    usort($rows, function ($a, $b) {
        return strcmp($b['last'], $a['last']);
    });

    $today = date('Y-m-d');
    echo '<table class="table table-striped table-condensed" style="font-size:13px"><thead><tr>'
        . '<th>Επαφή</th><th>Κατάσταση</th><th>Τηλέφωνο</th><th>Email</th><th>Τελ. επαφή</th><th>Follow-up</th><th>Χειριστής</th></tr></thead><tbody>';
    if (!count($rows)) {
        echo '<tr><td colspan="7" class="text-center text-muted">Τίποτα — πρόσθεσε leads στο Funnel ή κατέγραψε επικοινωνίες.</td></tr>';
    }
    foreach ($rows as $r) {
        echo '<tr><td><a href="' . $r['href'] . '"><b>' . cpm_e($r['name']) . '</b></a>'
            . ($r['sub'] ? ' <small class="text-muted">' . cpm_e($r['sub']) . '</small>' : '') . '</td>'
            . '<td>' . $r['badge'] . '</td>'
            . '<td>' . cpm_e($r['phone'] ?: '—') . '</td><td>' . cpm_e($r['email'] ?: '—') . '</td>'
            . '<td>' . ($r['last'] ? cpm_e(date('d/m/y', strtotime($r['last']))) : '<span class="text-muted">ποτέ</span>') . '</td>'
            . '<td>' . ($r['next'] ? '<span class="' . ($r['next'] <= $today ? 'cnp-due-over' : '') . '"><i class="far fa-bell"></i> ' . cpm_e(date('d/m', strtotime($r['next']))) . '</span>' : '—') . '</td>'
            . '<td>' . cpm_e($r['who']) . '</td></tr>';
    }
    echo '</tbody></table>';
}

/** Ημερολόγιο επικοινωνιών (log) + γρήγορη καταγραφή. */
function cpm_sales_log($link)
{
    $ref = $link . '&tab=sales&view=log';
    echo '<div class="panel panel-default"><div class="panel-heading"><b><i class="fas fa-plus"></i> Γρήγορη καταγραφή</b></div><div class="panel-body">'
        . cpm_interaction_form($link, $ref, 0, 0, true)
        . '<small class="text-muted">Διάλεξε lead ή πελάτη. Αν βάλεις follow-up σε lead, ενημερώνεται και η «Επόμενη ενέργεια» του.</small></div></div>';

    // εκκρεμή follow-ups πελατών
    $pend = Db::pendingClientFollowups();
    if (!cpm_is_full()) {
        $aid = cpm_admin_id();
        $pend = $pend->filter(function ($p) use ($aid) { return (int) $p->admin_id === $aid; })->values();
    }
    if (count($pend)) {
        echo '<div class="panel panel-warning"><div class="panel-heading"><b><i class="far fa-bell"></i> Εκκρεμή follow-ups πελατών (' . count($pend) . ')</b></div>';
        echo cpm_interaction_rows($link, $ref, Capsule::table('mod_cpm_interactions as i')
            ->leftJoin('mod_cpm_leads as l', 'l.id', '=', 'i.lead_id')
            ->select('i.*', 'l.company as lead_company', 'l.contact as lead_contact')
            ->whereIn('i.id', array_map(function ($p) { return (int) $p->id; }, $pend->all()))
            ->orderBy('i.followup_date')->get(), true);
        echo '</div>';
    }

    $recent = Db::recentInteractions(60);
    if (!cpm_is_full()) {
        $aid = cpm_admin_id();
        $recent = $recent->filter(function ($i) use ($aid) { return (int) $i->admin_id === $aid; })->values();
    }
    echo '<div class="panel panel-default"><div class="panel-heading"><b>Πρόσφατες επικοινωνίες</b></div>'
        . cpm_interaction_rows($link, $ref, $recent, true) . '</div>';
}

/** Στόχοι πωλήσεων ανά προϊόν — στόχος vs πραγματικές νέες πωλήσεις μήνα. */
function cpm_sales_targets($link)
{
    if (!cpm_is_full()) {
        echo '<div class="alert alert-warning"><i class="fas fa-lock"></i> Μόνο για διαχειριστές.</div>';
        return;
    }
    $ym = preg_match('/^\d{4}-\d{2}$/', $_GET['ym'] ?? '') ? $_GET['ym'] : date('Y-m');
    $prev = date('Y-m', strtotime($ym . '-01 -1 month'));
    $next = date('Y-m', strtotime($ym . '-01 +1 month'));
    $monthNames = [1 => 'Ιανουάριος', 'Φεβρουάριος', 'Μάρτιος', 'Απρίλιος', 'Μάιος', 'Ιούνιος',
        'Ιούλιος', 'Αύγουστος', 'Σεπτέμβριος', 'Οκτώβριος', 'Νοέμβριος', 'Δεκέμβριος'];

    // επιλογή μήνα
    echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">'
        . '<a class="btn btn-default btn-sm" href="' . $link . '&tab=sales&view=targets&ym=' . $prev . '">&larr;</a>'
        . '<b style="font-size:16px">' . $monthNames[(int) date('n', strtotime($ym . '-01'))] . ' ' . substr($ym, 0, 4) . '</b>'
        . ($ym < date('Y-m') ? '<a class="btn btn-default btn-sm" href="' . $link . '&tab=sales&view=targets&ym=' . $next . '">&rarr;</a>' : '')
        . ($ym !== date('Y-m') ? '<a class="btn btn-link btn-sm" href="' . $link . '&tab=sales&view=targets">τρέχων μήνας</a>' : '')
        . '</div>';

    // φόρμα: νέος στόχος / ενημέρωση
    echo '<div class="panel panel-default"><div class="panel-heading"><b><i class="fas fa-crosshairs"></i> Ορισμός στόχου προϊόντος (μηνιαίος)</b></div><div class="panel-body">';
    echo '<form method="post" action="' . $link . '" class="form-inline">'
        . '<input type="hidden" name="do" value="saveptarget">'
        . '<select name="product_id" class="form-control input-sm" style="max-width:320px">';
    $groups = [];
    foreach (Capsule::table('tblproducts')->where('hidden', 0)->orderBy('name')->get(['id', 'name', 'gid']) as $p) {
        $groups[(int) $p->gid][] = $p;
    }
    $gNames = Capsule::table('tblproductgroups')->pluck('name', 'id')->all();
    foreach ($groups as $gid => $prods) {
        echo '<optgroup label="' . cpm_e($gNames[$gid] ?? ('Ομάδα #' . $gid)) . '">';
        foreach ($prods as $p) {
            echo '<option value="' . (int) $p->id . '">' . cpm_e($p->name) . '</option>';
        }
        echo '</optgroup>';
    }
    echo '</select> '
        . 'Στόχος: <input type="number" min="0" name="target_units" class="form-control input-sm" style="width:85px" placeholder="τεμ./μήνα"> '
        . '<input type="text" name="target_value" class="form-control input-sm" style="width:100px" placeholder="€/μήνα (προαιρ.)"> '
        . '<button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Αποθήκευση</button> '
        . '<small class="text-muted">Ο στόχος ισχύει για κάθε μήνα — αν το προϊόν έχει ήδη στόχο, ενημερώνεται.</small></form></div></div>';

    // πίνακας: στόχος vs πραγματικότητα
    $targets = Db::productTargets();
    $sales = Db::productSalesForMonth($ym);
    $totT = $totA = 0;
    $totTV = $totAV = 0.0;
    echo '<div class="panel panel-default"><div class="panel-heading"><b>Πρόοδος στόχων — ' . cpm_e($monthNames[(int) date('n', strtotime($ym . '-01'))]) . '</b></div>'
        . '<table class="table table-striped table-condensed" style="margin:0"><thead><tr>'
        . '<th>Προϊόν</th><th>Στόχος τεμ.</th><th>Πωλήσεις</th><th style="min-width:170px">Πρόοδος</th>'
        . '<th>Στόχος €</th><th>Αξία πωλήσεων</th><th></th></tr></thead><tbody>';
    if (!count($targets)) {
        echo '<tr><td colspan="7" class="text-center text-muted" style="padding:22px">Δεν έχεις ορίσει στόχους ακόμη — διάλεξε προϊόν από πάνω.</td></tr>';
    }
    foreach ($targets as $t) {
        [$units, $value] = $sales[(int) $t->product_id] ?? [0, 0.0];
        $pct = $t->target_units > 0 ? min(100, (int) round($units / $t->target_units * 100)) : 0;
        $realPct = $t->target_units > 0 ? (int) round($units / $t->target_units * 100) : null;
        $barCls = $realPct === null ? 'progress-bar-info' : ($realPct >= 100 ? 'progress-bar-success' : ($realPct >= 60 ? 'progress-bar-info' : 'progress-bar-warning'));
        $totT += (int) $t->target_units;
        $totA += $units;
        $totTV += (float) $t->target_value;
        $totAV += $value;
        echo '<tr><td><b>' . cpm_e($t->product_name) . '</b></td>'
            . '<td>' . (int) $t->target_units . '</td>'
            . '<td><b>' . $units . '</b></td>'
            . '<td><div class="progress" style="height:9px;margin:0 0 2px"><div class="progress-bar ' . $barCls . '" style="width:' . $pct . '%"></div></div>'
            . '<small class="' . ($realPct !== null && $realPct >= 100 ? 'text-success' : 'text-muted') . '">'
            . ($realPct === null ? '—' : $realPct . '%' . ($realPct >= 100 ? ' 🎉' : '')) . '</small></td>'
            . '<td>' . ((float) $t->target_value > 0 ? cpm_fmt_eur($t->target_value) : '—') . '</td>'
            . '<td>' . cpm_fmt_eur($value) . '</td>'
            . '<td><form method="post" action="' . $link . '" style="display:inline" onsubmit="return confirm(\'Διαγραφή στόχου;\')">'
            . '<input type="hidden" name="do" value="delptarget"><input type="hidden" name="id" value="' . (int) $t->id . '">'
            . '<button class="btn btn-xs btn-link text-danger"><i class="fas fa-times"></i></button></form></td></tr>';
    }
    if (count($targets)) {
        $totPct = $totT > 0 ? (int) round($totA / $totT * 100) : null;
        echo '<tr style="font-weight:700;background:#f6f8fb"><td>Σύνολο</td><td>' . $totT . '</td><td>' . $totA . '</td>'
            . '<td>' . ($totPct === null ? '—' : $totPct . '%') . '</td>'
            . '<td>' . ($totTV > 0 ? cpm_fmt_eur($totTV) : '—') . '</td><td>' . cpm_fmt_eur($totAV) . '</td><td></td></tr>';
    }
    echo '</tbody></table></div>';

    // πωλήσεις μήνα ΧΩΡΙΣ στόχο (ό,τι πουλήθηκε εκτός λίστας — για να μη χάνεις εικόνα)
    $targeted = [];
    foreach ($targets as $t) {
        $targeted[(int) $t->product_id] = true;
    }
    $other = [];
    foreach ($sales as $pid => $sv) {
        if (!isset($targeted[$pid])) {
            $other[$pid] = $sv;
        }
    }
    if (count($other)) {
        $pNames = Capsule::table('tblproducts')->whereIn('id', array_keys($other))->pluck('name', 'id')->all();
        echo '<div class="panel panel-default"><div class="panel-heading"><b>Πωλήσεις μήνα χωρίς στόχο</b></div>'
            . '<table class="table table-condensed" style="margin:0;font-size:12px"><tbody>';
        foreach ($other as $pid => $sv) {
            echo '<tr><td>' . cpm_e($pNames[$pid] ?? ('Προϊόν #' . $pid)) . '</td><td><b>' . $sv[0] . '</b> τεμ.</td><td>' . cpm_fmt_eur($sv[1]) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

function cpm_tab_lead($link, $adminId)
{
    $id = (int) ($_REQUEST['id'] ?? 0);
    $l = $id ? Db::lead($id) : null;
    if ($id && !$l) {
        echo '<div class="alert alert-warning">Το lead δεν βρέθηκε.</div>';
        return;
    }
    if ($l && !cpm_is_full() && (int) $l->assignee !== $adminId && (int) $l->created_by !== $adminId) {
        echo '<div class="alert alert-warning"><i class="fas fa-lock"></i> Δεν έχεις πρόσβαση σε αυτό το lead.</div>';
        return;
    }
    if (isset($_GET['saved'])) { echo '<div class="alert alert-success">Αποθηκεύτηκε.</div>'; }
    if (isset($_GET['converted'])) { echo '<div class="alert alert-success"><i class="fas fa-user-check"></i> Δημιουργήθηκε πελάτης WHMCS και το lead μαρκαρίστηκε «Έγινε πελάτης».</div>'; }
    if (isset($_GET['cerr'])) { echo '<div class="alert alert-danger">Σφάλμα δημιουργίας πελάτη: ' . cpm_e($_GET['cerr']) . '</div>'; }
    echo '<a href="' . $link . '&tab=sales" class="btn btn-default btn-xs" style="margin-bottom:12px">&larr; Πωλήσεις</a>';

    echo '<div class="row"><div class="col-md-7">';
    echo '<div class="panel panel-default"><div class="panel-heading"><b>' . ($l ? 'Lead #' . $id : 'Νέος στόχος / lead') . '</b></div><div class="panel-body">';
    echo '<form method="post" action="' . $link . '" class="form-horizontal"><input type="hidden" name="do" value="savelead"><input type="hidden" name="id" value="' . $id . '">';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Επωνυμία</label><div class="col-sm-9"><input type="text" name="company" class="form-control" value="' . cpm_e($l->company ?? '') . '"></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Πρόσωπο επαφής</label><div class="col-sm-9"><input type="text" name="contact" class="form-control" value="' . cpm_e($l->contact ?? '') . '"></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Email</label><div class="col-sm-9"><input type="email" name="email" class="form-control" value="' . cpm_e($l->email ?? '') . '"></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Τηλέφωνο</label><div class="col-sm-9"><input type="text" name="phone" class="form-control" value="' . cpm_e($l->phone ?? '') . '"></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Πηγή</label><div class="col-sm-9"><input type="text" name="source" class="form-control" list="cnpSrcL" value="' . cpm_e($l->source ?? '') . '" placeholder="σύσταση, site, κλήση, έκθεση…">'
        . '<datalist id="cnpSrcL"><option value="Σύσταση"><option value="Site"><option value="Κλήση"><option value="Email"><option value="LinkedIn"><option value="Έκθεση"></datalist></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Στάδιο</label><div class="col-sm-9"><select name="stage" class="form-control">';
    foreach (Db::leadStages() as $k => $meta) {
        echo '<option value="' . $k . '"' . (($l->stage ?? 'target') === $k ? ' selected' : '') . '>' . cpm_e($meta[0]) . '</option>';
    }
    echo '</select></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Χειριστής</label><div class="col-sm-9"><select name="assignee" class="form-control"><option value="">— κανείς —</option>';
    foreach (Db::admins() as $a) {
        echo '<option value="' . (int) $a->id . '"' . (((int) ($l->assignee ?? 0)) === (int) $a->id ? ' selected' : '') . '>' . cpm_e(trim($a->firstname . ' ' . $a->lastname)) . '</option>';
    }
    echo '</select></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Επόμενη ενέργεια</label><div class="col-sm-9" style="display:flex;gap:8px">'
        . '<input type="date" name="next_action" class="form-control" style="width:170px" value="' . cpm_e($l->next_action ?? '') . '">'
        . '<input type="text" name="next_note" class="form-control" placeholder="τι θα γίνει (π.χ. τηλέφωνο για demo)" value="' . cpm_e($l->next_note ?? '') . '"></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Σημειώσεις</label><div class="col-sm-9"><textarea name="descr" class="form-control" rows="4">' . cpm_e($l->descr ?? '') . '</textarea></div></div>';
    echo '<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><button class="btn btn-primary">Αποθήκευση</button>';
    if ($l) {
        echo ' <button form="cnpLDelForm" class="btn btn-danger" onclick="return confirm(\'Διαγραφή lead; (οι προσφορές του μένουν, ξεδένονται)\')">Διαγραφή</button>';
    }
    echo '</div></div></form>';
    if ($l) {
        echo '<form id="cnpLDelForm" method="post" action="' . $link . '"><input type="hidden" name="do" value="deletelead"><input type="hidden" name="id" value="' . $id . '"></form>';
    }
    echo '</div></div></div>';

    echo '<div class="col-md-5">';
    if ($l) {
        // μετατροπή σε πελάτη (CRM κύκλος)
        echo '<div class="panel panel-' . ($l->clientid ? 'success' : 'info') . '"><div class="panel-heading"><b><i class="fas fa-user-plus"></i> Πελάτης WHMCS</b></div><div class="panel-body">';
        if ($l->clientid) {
            echo '<p><i class="fas fa-check-circle text-success"></i> Συνδεδεμένο με τον πελάτη '
                . '<a href="clientssummary.php?userid=' . (int) $l->clientid . '" target="_blank"><b>' . cpm_e(cpm_client_label($l->clientid)) . '</b> (#' . (int) $l->clientid . ')</a></p>';
        } else {
            echo '<form method="post" action="' . $link . '" style="margin-bottom:10px"><input type="hidden" name="do" value="convertlead"><input type="hidden" name="id" value="' . $id . '">'
                . '<button class="btn btn-sm btn-success"' . ($l->email ? '' : ' disabled title="Χρειάζεται email"') . ' onclick="return confirm(\'Δημιουργία νέου πελάτη WHMCS από αυτό το lead; (χωρίς welcome email)\')">'
                . '<i class="fas fa-user-plus"></i> Μετατροπή σε νέο πελάτη</button>'
                . ($l->email ? '' : ' <small class="text-muted">συμπλήρωσε email πρώτα</small>') . '</form>';
            echo '<form method="post" action="' . $link . '" class="form-inline"><input type="hidden" name="do" value="leadclient"><input type="hidden" name="id" value="' . $id . '">'
                . cpm_client_input('clientid', 0, '170px') . ' '
                . '<button class="btn btn-sm btn-default">Σύνδεση υπάρχοντος</button></form>';
        }
        echo '</div></div>';

        // επικοινωνίες του lead
        $refL = $link . '&tab=lead&id=' . $id;
        echo '<div class="panel panel-default"><div class="panel-heading"><b><i class="fas fa-history"></i> Επικοινωνίες</b></div><div class="panel-body" style="padding-bottom:4px">'
            . cpm_interaction_form($link, $refL, $id, 0) . '</div>'
            . cpm_interaction_rows($link, $refL, Db::interactionsForLead($id)) . '</div>';

        // προσφορές του lead
        $offers = Capsule::table('mod_cpm_offers as o')
            ->leftJoin('tblquotes as q', 'q.id', '=', 'o.quoteid')
            ->where('o.lead_id', $id)
            ->select('o.*', 'q.total as quote_total')->orderBy('o.id', 'desc')->get();
        echo '<div class="panel panel-default"><div class="panel-heading"><b><i class="fas fa-file-signature"></i> Προσφορές</b></div><div class="panel-body">';
        echo '<form method="post" action="' . $link . '" style="margin-bottom:10px"><input type="hidden" name="do" value="leadoffer"><input type="hidden" name="id" value="' . $id . '">'
            . '<button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Νέα προσφορά για το lead</button></form>';
        if (count($offers)) {
            echo '<table class="table table-condensed" style="margin:0;font-size:12px"><tbody>';
            foreach ($offers as $o) {
                $meta = Db::offerStages()[$o->stage] ?? Db::offerStages()['new'];
                echo '<tr><td><a href="' . $link . '&tab=offer&id=' . (int) $o->id . '">' . cpm_e($o->title) . '</a></td>'
                    . '<td><span class="label" style="background:' . $meta[1] . '">' . cpm_e($meta[0]) . '</span></td>'
                    . '<td>' . cpm_fmt_eur(cpm_offer_value($o)) . '</td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="text-muted" style="margin:0">Καμία προσφορά ακόμη.</p>';
        }
        echo '</div></div>';
    } else {
        echo '<div class="panel panel-default"><div class="panel-body text-muted" style="font-size:12px">'
            . '<b>Ροή:</b> Στόχος (δεν έχει γίνει επαφή) → Έγινε επαφή → Ενδιαφέρεται → Σε προσφορά → Έγινε πελάτης / Δεν προχώρησε.'
            . '<br>Με το «Επόμενη ενέργεια» δεν ξεχνιέται κανένα follow-up — φαίνεται στην κάρτα και στο πρωινό email.</div></div>';
    }
    echo '</div></div>';
}

/* ------------------------------------------------------------------ */
/* Πελάτης — ενιαίο timeline (5.1)                                    */
/* ------------------------------------------------------------------ */

function cpm_tab_client($link)
{
    if (!cpm_is_full()) {
        echo '<div class="alert alert-warning"><i class="fas fa-lock"></i> Η καθολική προβολή πελατών είναι διαθέσιμη μόνο σε διαχειριστές με πλήρη πρόσβαση.</div>';
        return;
    }
    $q = trim($_GET['q'] ?? '');
    $uid = (int) ($_GET['client'] ?? 0);

    // αναζήτηση: αριθμός = client id, αλλιώς όνομα/επωνυμία/email
    $matches = [];
    if (!$uid && $q !== '') {
        if (ctype_digit($q)) {
            $uid = (int) $q;
        } else {
            $like = '%' . $q . '%';
            $matches = Capsule::table('tblclients')
                ->where('firstname', 'like', $like)->orWhere('lastname', 'like', $like)
                ->orWhere('companyname', 'like', $like)->orWhere('email', 'like', $like)
                ->limit(15)->get(['id', 'firstname', 'lastname', 'companyname', 'email']);
            if (count($matches) === 1) {
                $uid = (int) $matches[0]->id;
                $matches = [];
            }
        }
    }

    echo '<form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom:14px">'
        . '<input type="hidden" name="module" value="cloudonprojects"><input type="hidden" name="tab" value="client"> '
        . '<input type="text" name="q" class="form-control" style="width:260px" placeholder="Client ID, όνομα, επωνυμία ή email…" value="' . cpm_e($q) . '"> '
        . '<button class="btn btn-default">Αναζήτηση</button></form>';

    if (count($matches)) {
        echo '<div class="panel panel-default"><table class="table table-condensed" style="margin:0"><tbody>';
        foreach ($matches as $m) {
            $label = $m->companyname ?: trim($m->firstname . ' ' . $m->lastname);
            echo '<tr><td><a href="' . $link . '&tab=client&client=' . (int) $m->id . '"><b>' . cpm_e($label) . '</b></a> '
                . '<small class="text-muted">#' . (int) $m->id . ' · ' . cpm_e($m->email) . '</small></td></tr>';
        }
        echo '</tbody></table></div>';
        return;
    }
    if (!$uid) {
        echo '<p class="text-muted">Αναζήτησε πελάτη για να δεις το ενιαίο ιστορικό του: tasks, χρόνο εργασίας, tickets, κινήσεις προαγοράς, προσφορές και πληρωμές.</p>';
        return;
    }
    $client = Capsule::table('tblclients')->where('id', $uid)->first(['id', 'firstname', 'lastname', 'companyname', 'email', 'status']);
    if (!$client) {
        echo '<div class="alert alert-warning">Δεν βρέθηκε πελάτης #' . $uid . '.</div>';
        return;
    }

    // summary
    $openTasks = Capsule::table('mod_cpm_tasks as t')->leftJoin('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
        ->where('p.clientid', $uid)
        ->whereNotIn('t.status_id', Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0])->count();
    $openTickets = Capsule::table('tbltickets')->where('userid', $uid)
        ->whereNotIn('status', ['Closed', 'Cancelled'])->count();
    $activeSvc = Capsule::table('tblhosting')->where('userid', $uid)->where('domainstatus', 'Active')->count();
    $offerStats = Db::offerStats($uid);
    $scBal = null;
    try {
        if (Capsule::schema()->hasTable('mod_supportcontracts_clients')) {
            $scBal = Capsule::table('mod_supportcontracts_clients')->where('userid', $uid)->value('balance_minutes');
        }
    } catch (\Throwable $e) {
    }

    echo '<h4 style="margin-top:0"><b>' . cpm_e($client->companyname ?: trim($client->firstname . ' ' . $client->lastname)) . '</b>'
        . ' <small>#' . $uid . ' · ' . cpm_e($client->email) . ' · <a href="clientssummary.php?userid=' . $uid . '" target="_blank">καρτέλα</a>'
        . ' · <a href="' . $link . '&tab=offers&client=' . $uid . '">προσφορές</a></small></h4>';

    echo '<div class="row" style="margin-bottom:4px">';
    $cards = [
        ['Ενεργές υπηρεσίες', (string) $activeSvc, 'default'],
        ['Ανοιχτά tasks', (string) $openTasks, $openTasks ? 'info' : 'default'],
        ['Ανοιχτά tickets', (string) $openTickets, $openTickets ? 'warning' : 'default'],
    ];
    $cards[] = $scBal !== null
        ? ['Υπόλοιπο προαγοράς', Time::fmt((int) $scBal), $scBal > 0 ? 'success' : 'danger']
        : ['Προσφορές (win rate)', $offerStats['win_rate'] === null ? '—' : $offerStats['win_rate'] . '%', 'default'];
    foreach ($cards as $c) {
        echo '<div class="col-sm-3"><div class="panel panel-' . $c[2] . '"><div class="panel-body" style="text-align:center;padding:10px">'
            . '<div style="font-size:20px;font-weight:700">' . $c[1] . '</div><small class="text-muted">' . $c[0] . '</small></div></div></div>';
    }
    echo '</div>';

    // περίοδος
    $months = in_array((int) ($_GET['months'] ?? 6), [3, 6, 12, 120], true) ? (int) $_GET['months'] : 6;
    echo '<div style="margin-bottom:10px">Περίοδος: ';
    foreach ([3 => '3 μήνες', 6 => '6 μήνες', 12 => '12 μήνες', 120 => 'όλα'] as $m => $lbl) {
        echo '<a class="btn btn-xs ' . ($months === $m ? 'btn-primary' : 'btn-default') . '" style="margin-right:4px" href="'
            . $link . '&tab=client&client=' . $uid . '&months=' . $m . '">' . $lbl . '</a>';
    }
    echo '</div>';

    // γρήγορη καταγραφή επικοινωνίας για τον πελάτη
    echo '<div class="panel panel-default"><div class="panel-body" style="padding-bottom:2px">'
        . cpm_interaction_form($link, $link . '&tab=client&client=' . $uid, 0, $uid) . '</div></div>';

    $since = date('Y-m-d', strtotime('-' . $months . ' months'));
    $events = Db::clientTimeline($uid, $since);

    $typeMeta = [
        'task'       => ['fas fa-plus-square', '#0097e4', 'Task'],
        'task_done'  => ['fas fa-check-circle', '#1f9d57', 'Ολοκλήρωση'],
        'time'       => ['far fa-clock', '#8291a9', 'Χρόνος'],
        'time_bill'  => ['far fa-clock', '#e0a020', 'Χρόνος (χρεώσιμος)'],
        'ticket'     => ['fas fa-life-ring', '#7b5cd6', 'Ticket'],
        'sc_plus'    => ['fas fa-battery-full', '#1f9d57', 'Προαγορά'],
        'sc_minus'   => ['fas fa-battery-quarter', '#d92d3a', 'Ανάλωση'],
        'offer'      => ['fas fa-file-signature', '#0097e4', 'Προσφορά'],
        'offer_won'  => ['fas fa-trophy', '#1f9d57', 'Κερδισμένη'],
        'offer_lost' => ['fas fa-times-circle', '#d92d3a', 'Χαμένη'],
        'payment'    => ['fas fa-euro-sign', '#1f9d57', 'Πληρωμή'],
        'contact'    => ['fas fa-comments', '#0097e4', 'Επικοινωνία'],
    ];
    echo '<div class="panel panel-default"><div class="panel-heading"><b>Ιστορικό (' . count($events) . ')</b></div><div class="panel-body" style="max-height:640px;overflow:auto">';
    if (!count($events)) {
        echo '<p class="text-muted">Καμία δραστηριότητα στην περίοδο.</p>';
    }
    $lastDay = '';
    foreach ($events as $e) {
        $day = date('d/m/Y', strtotime($e['ts']));
        if ($day !== $lastDay) {
            echo '<div style="font-weight:700;color:#44566c;border-bottom:1px solid #e2e8f0;margin:12px 0 6px;padding-bottom:2px">' . $day . '</div>';
            $lastDay = $day;
        }
        $m = $typeMeta[$e['type']] ?? ['far fa-circle', '#8291a9', $e['type']];
        $href = '';
        if (strpos($e['link'], 'task:') === 0) {
            $href = $link . '&tab=task&id=' . (int) substr($e['link'], 5);
        } elseif (strpos($e['link'], 'offer:') === 0) {
            $href = $link . '&tab=offer&id=' . (int) substr($e['link'], 6);
        } elseif (strpos($e['link'], 'ticket:') === 0) {
            $href = 'supporttickets.php?action=view&id=' . (int) substr($e['link'], 7);
        } elseif (strpos($e['link'], 'invoice:') === 0) {
            $href = 'invoices.php?action=edit&id=' . (int) substr($e['link'], 8);
        }
        echo '<div style="display:flex;gap:10px;padding:4px 0;font-size:13px;align-items:baseline">'
            . '<span style="color:' . $m[1] . ';width:18px;text-align:center"><i class="' . $m[0] . '"></i></span>'
            . '<span style="color:#8291a9;min-width:42px">' . date('H:i', strtotime($e['ts'])) . '</span>'
            . '<span style="flex:1">' . ($href ? '<a href="' . $href . '" style="color:inherit">' : '')
            . cpm_e($e['title']) . ($href ? '</a>' : '')
            . ($e['meta'] !== '' ? ' <small class="text-muted">— ' . cpm_e($e['meta']) . '</small>' : '') . '</span>'
            . '</div>';
    }
    echo '</div></div>';
}

/* ------------------------------------------------------------------ */
/* Προσφορές — kanban (4.1) + ιστορικό πελάτη (4.3)                   */
/* ------------------------------------------------------------------ */

function cpm_offer_value($o)
{
    return $o->quoteid && $o->quote_total !== null ? (float) $o->quote_total : (float) ($o->amount ?? 0);
}

function cpm_fmt_eur($v)
{
    return number_format((float) $v, 2, ',', '.') . ' €';
}

function cpm_tab_offers($link)
{
    $client = (int) ($_GET['client'] ?? 0);
    $offers = Db::offers($client);
    if (!cpm_is_full()) {
        $aid = cpm_admin_id();
        $offers = $offers->filter(function ($o) use ($aid) {
            return (int) $o->assignee === $aid || (int) $o->created_by === $aid;
        })->values();
    }
    $stats = Db::offerStats($client);

    echo '<form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom:12px">'
        . '<input type="hidden" name="module" value="cloudonprojects"><input type="hidden" name="tab" value="offers"> '
        . '<input type="number" name="client" class="form-control input-sm" style="width:120px" placeholder="Client ID" value="' . ($client ?: '') . '"> '
        . '<button class="btn btn-sm btn-default">Φίλτρο πελάτη</button>'
        . ($client ? ' <a class="btn btn-sm btn-link" href="' . $link . '&tab=offers">όλες</a> <b>' . cpm_e(cpm_client_label($client)) . '</b>' : '')
        . ' <a class="btn btn-sm btn-primary" style="margin-left:12px" href="' . $link . '&tab=offer&id=0' . ($client ? '&clientid=' . $client : '') . '"><i class="fas fa-plus"></i> Νέα προσφορά</a>'
        . '</form>';

    // στατιστικά (4.3)
    echo '<div class="row" style="margin-bottom:4px">';
    foreach ([['Ανοιχτές', $stats['open'] . ' <small>(' . cpm_fmt_eur($stats['open_value']) . ')</small>', 'info'],
              ['Κερδισμένες', $stats['won'] . ' <small>(' . cpm_fmt_eur($stats['won_value']) . ')</small>', 'success'],
              ['Χαμένες', (string) $stats['lost'], 'danger'],
              ['Ποσοστό επιτυχίας', $stats['win_rate'] === null ? '—' : $stats['win_rate'] . '%', 'default']] as $c) {
        echo '<div class="col-sm-3"><div class="panel panel-' . $c[2] . '"><div class="panel-body" style="text-align:center;padding:10px">'
            . '<div style="font-size:20px;font-weight:700">' . $c[1] . '</div><small class="text-muted">' . $c[0] . '</small></div></div></div>';
    }
    echo '</div>';

    // kanban
    $byStage = [];
    foreach ($offers as $o) {
        $byStage[$o->stage][] = $o;
    }
    $today = date('Y-m-d');
    echo '<div class="cnp-board">';
    foreach (Db::offerStages() as $key => $meta) {
        $cards = $byStage[$key] ?? [];
        $sum = 0;
        foreach ($cards as $o) { $sum += cpm_offer_value($o); }
        echo '<div class="cnp-col cnp-ocol" data-stage="' . cpm_e($key) . '">';
        echo '<div class="cnp-col-h" style="border-color:' . $meta[1] . '">' . cpm_e($meta[0])
            . ' <span class="cnp-col-n">' . count($cards) . '</span></div>';
        if ($sum > 0) {
            echo '<div style="padding:4px 12px;font-size:11px;color:#6b7c96;border-bottom:1px solid #e9edf3"><b>' . cpm_fmt_eur($sum) . '</b></div>';
        }
        echo '<div class="cnp-cards">';
        foreach ($cards as $o) {
            $val = cpm_offer_value($o);
            $stale = (!$meta[2] && $o->expected_close && $o->expected_close < $today);
            echo '<a class="cnp-card' . ($stale ? ' cnp-card--over' : '') . '" draggable="true" data-offer="' . (int) $o->id . '" href="' . $link . '&tab=offer&id=' . (int) $o->id . '">';
            echo '<div class="cnp-card-t">' . cpm_e($o->title) . '</div>';
            echo '<div class="cnp-card-m">';
            if ($o->clientid) { echo '<span><i class="far fa-user"></i> ' . cpm_e(mb_substr(cpm_client_label($o->clientid), 0, 22)) . '</span>'; }
            if ($val > 0) { echo '<span><b>' . cpm_fmt_eur($val) . '</b></span>'; }
            if ($o->quoteid) { echo '<span title="Δεμένη με WHMCS Quote #' . (int) $o->quoteid . '"><i class="fas fa-file-invoice"></i> Q' . (int) $o->quoteid . '</span>'; }
            if ($o->expected_close) { echo '<span class="' . ($stale ? 'cnp-due-over' : '') . '"><i class="far fa-calendar"></i> ' . cpm_e(date('d/m', strtotime($o->expected_close))) . '</span>'; }
            echo '</div></a>';
        }
        echo '</div></div>';
    }
    echo '</div>';

    // drag&drop για offers
    echo '<script>
(function(){
  var dragId=null;
  document.querySelectorAll(".cnp-card[data-offer]").forEach(function(c){
    c.addEventListener("dragstart",function(){dragId=c.getAttribute("data-offer");c.classList.add("dragging");});
    c.addEventListener("dragend",function(){c.classList.remove("dragging");});
  });
  document.querySelectorAll(".cnp-ocol").forEach(function(col){
    col.addEventListener("dragover",function(e){e.preventDefault();col.classList.add("dragover");});
    col.addEventListener("dragleave",function(){col.classList.remove("dragover");});
    col.addEventListener("drop",function(e){
      e.preventDefault();col.classList.remove("dragover");
      if(!dragId)return;
      var card=document.querySelector(".cnp-card[data-offer=\'"+dragId+"\']");
      var fd=new FormData();fd.append("do","moveoffer");fd.append("offerid",dragId);fd.append("stage",col.getAttribute("data-stage"));
      fetch("' . $link . '",{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(j){
        if(j.ok&&card){ col.querySelector(".cnp-cards").appendChild(card);
          document.querySelectorAll(".cnp-ocol").forEach(function(c2){
            c2.querySelector(".cnp-col-n").textContent=c2.querySelectorAll(".cnp-card").length;});
        }
      });
      dragId=null;
    });
  });
})();
</script>';
}

function cpm_tab_offer($link, $adminId)
{
    $id = (int) ($_REQUEST['id'] ?? 0);
    $o = null;
    if ($id) {
        Db::syncOfferStages();
        $o = Db::offer($id);
        if (!$o) {
            echo '<div class="alert alert-warning">Η προσφορά δεν βρέθηκε.</div>';
            return;
        }
        if (!cpm_is_full() && (int) $o->assignee !== $adminId && (int) $o->created_by !== $adminId) {
            echo '<div class="alert alert-warning"><i class="fas fa-lock"></i> Δεν έχεις πρόσβαση σε αυτή την προσφορά.</div>';
            return;
        }
    }
    if (isset($_GET['saved'])) { echo '<div class="alert alert-success">Αποθηκεύτηκε.</div>'; }
    if (isset($_GET['qerr'])) { echo '<div class="alert alert-danger">Σφάλμα δημιουργίας Quote: ' . cpm_e($_GET['qerr']) . '</div>'; }
    echo '<a href="' . $link . '&tab=offers" class="btn btn-default btn-xs" style="margin-bottom:12px">&larr; Προσφορές</a>';

    echo '<div class="row"><div class="col-md-7">';
    echo '<div class="panel panel-default"><div class="panel-heading"><b>' . ($o ? 'Προσφορά #' . $id : 'Νέα προσφορά') . '</b></div><div class="panel-body">';
    echo '<form method="post" action="' . $link . '" class="form-horizontal"><input type="hidden" name="do" value="saveoffer"><input type="hidden" name="id" value="' . $id . '">';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Τίτλος</label><div class="col-sm-9"><input type="text" name="title" class="form-control" required value="' . cpm_e($o->title ?? '') . '"></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Πελάτης</label><div class="col-sm-9">'
        . cpm_client_input('clientid', (int) ($o->clientid ?? ($_REQUEST['clientid'] ?? 0)), '100%')
        . (($o->clientid ?? 0) ? '<small class="text-muted"><a href="clientssummary.php?userid=' . (int) $o->clientid . '" target="_blank">καρτέλα πελάτη</a></small>' : '')
        . '</div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Ποσό (χωρίς quote)</label><div class="col-sm-9"><input type="text" name="amount" class="form-control" value="' . cpm_e($o->amount ?? '') . '" placeholder="π.χ. 1500.00">'
        . (($o->quoteid ?? 0) ? '<small class="text-muted">Υπερισχύει το σύνολο του δεμένου Quote.</small>' : '') . '</div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Στάδιο</label><div class="col-sm-9"><select name="stage" class="form-control">';
    foreach (Db::offerStages() as $k => $meta) {
        echo '<option value="' . $k . '"' . (($o->stage ?? 'new') === $k ? ' selected' : '') . '>' . cpm_e($meta[0]) . '</option>';
    }
    echo '</select></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Χειριστής</label><div class="col-sm-9"><select name="assignee" class="form-control"><option value="">— κανείς —</option>';
    foreach (Db::admins() as $a) {
        echo '<option value="' . (int) $a->id . '"' . (((int) ($o->assignee ?? 0)) === (int) $a->id ? ' selected' : '') . '>' . cpm_e(trim($a->firstname . ' ' . $a->lastname)) . '</option>';
    }
    echo '</select></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Αναμενόμενο κλείσιμο</label><div class="col-sm-9"><input type="date" name="expected_close" class="form-control" value="' . cpm_e($o->expected_close ?? '') . '"></div></div>';
    echo '<div class="form-group"><label class="col-sm-3 control-label">Σημειώσεις</label><div class="col-sm-9"><textarea name="descr" class="form-control" rows="4">' . cpm_e($o->descr ?? '') . '</textarea></div></div>';
    echo '<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><button class="btn btn-primary">Αποθήκευση</button>';
    if ($o) {
        echo ' <button form="cnpODelForm" class="btn btn-danger" onclick="return confirm(\'Οριστική διαγραφή προσφοράς; (το WHMCS Quote δεν διαγράφεται)\')">Διαγραφή</button>';
    }
    echo '</div></div></form>';
    if ($o) {
        echo '<form id="cnpODelForm" method="post" action="' . $link . '"><input type="hidden" name="do" value="deleteoffer"><input type="hidden" name="id" value="' . $id . '"></form>';
    }
    echo '</div></div>';
    echo '</div><div class="col-md-5">';

    if ($o) {
        if (!empty($o->lead_id)) {
            $ld = Db::lead($o->lead_id);
            if ($ld) {
                echo '<div class="alert alert-info" style="padding:8px 12px"><i class="fas fa-bullseye"></i> Από lead: '
                    . '<a href="' . $link . '&tab=lead&id=' . (int) $ld->id . '"><b>' . cpm_e($ld->company ?: $ld->contact) . '</b></a>'
                    . ' <span class="label label-default">' . cpm_e(Db::leadStages()[$ld->stage][0] ?? $ld->stage) . '</span></div>';
            }
        }
        // WHMCS Quote (4.2)
        echo '<div class="panel panel-info"><div class="panel-heading"><b><i class="fas fa-file-invoice"></i> WHMCS Quote</b></div><div class="panel-body">';
        if ($o->quoteid) {
            $q = Capsule::table('tblquotes')->where('id', (int) $o->quoteid)->first(['stage', 'total', 'validuntil', 'datesent']);
            if ($q) {
                echo '<p>Δεμένη με το Quote <a href="quotes.php?action=manage&id=' . (int) $o->quoteid . '"><b>#' . (int) $o->quoteid . '</b></a>'
                    . ' <span class="label label-default">' . cpm_e($q->stage ?: '—') . '</span></p>'
                    . '<p>Σύνολο: <b>' . cpm_fmt_eur($q->total) . '</b>'
                    . ($q->validuntil && $q->validuntil !== '0000-00-00' ? ' · Ισχύει έως: <b>' . cpm_e(date('d/m/Y', strtotime($q->validuntil))) . '</b>' : '') . '</p>'
                    . '<p class="text-muted" style="font-size:11px">Το στάδιο της προσφοράς συγχρονίζεται αυτόματα από το Quote (Accepted→Αποδεκτή κ.λπ.). Αποστολή/PDF/αποδοχή γίνονται από το Quote.</p>';
            } else {
                echo '<p class="text-danger">Το Quote #' . (int) $o->quoteid . ' δεν υπάρχει πια.</p>';
            }
            echo '<form method="post" action="' . $link . '" style="display:inline"><input type="hidden" name="do" value="unlinkquote"><input type="hidden" name="id" value="' . $id . '">'
                . '<button class="btn btn-xs btn-default">Αποσύνδεση</button></form>';
        } else {
            echo '<form method="post" action="' . $link . '" style="margin-bottom:10px"><input type="hidden" name="do" value="createquote"><input type="hidden" name="id" value="' . $id . '">'
                . '<button class="btn btn-sm btn-primary"' . ($o->clientid ? '' : ' disabled title="Χρειάζεται Client ID"') . '><i class="fas fa-plus"></i> Δημιουργία Quote από την προσφορά</button>'
                . ($o->clientid ? '' : ' <small class="text-muted">ορίστε πρώτα πελάτη</small>') . '</form>';
            echo '<form method="post" action="' . $link . '" class="form-inline"><input type="hidden" name="do" value="linkquote"><input type="hidden" name="id" value="' . $id . '">'
                . '<input type="number" name="quoteid" class="form-control input-sm" style="width:110px" placeholder="Quote ID"> '
                . '<button class="btn btn-sm btn-default">Σύνδεση υπάρχοντος</button></form>';
        }
        echo '</div></div>';

        // ιστορικό πελάτη (4.3)
        if ($o->clientid) {
            $hist = Db::offers($o->clientid);
            $stats = Db::offerStats($o->clientid);
            echo '<div class="panel panel-default"><div class="panel-heading"><b>Ιστορικό προσφορών — ' . cpm_e(cpm_client_label($o->clientid)) . '</b></div>'
                . '<div class="panel-body" style="padding-bottom:0"><p style="font-size:12px">Σύνολο: <b>' . $stats['total'] . '</b>'
                . ' · Κερδισμένες: <b class="text-success">' . $stats['won'] . '</b> (' . cpm_fmt_eur($stats['won_value']) . ')'
                . ' · Χαμένες: <b class="text-danger">' . $stats['lost'] . '</b>'
                . ($stats['win_rate'] !== null ? ' · Επιτυχία: <b>' . $stats['win_rate'] . '%</b>' : '') . '</p></div>'
                . '<table class="table table-condensed" style="margin:0;font-size:12px"><tbody>';
            foreach ($hist as $h) {
                $meta = Db::offerStages()[$h->stage] ?? Db::offerStages()['new'];
                echo '<tr' . ($h->id == $id ? ' class="active"' : '') . '><td><a href="' . $link . '&tab=offer&id=' . (int) $h->id . '">' . cpm_e($h->title) . '</a></td>'
                    . '<td><span class="label" style="background:' . $meta[1] . '">' . cpm_e($meta[0]) . '</span></td>'
                    . '<td>' . cpm_fmt_eur(cpm_offer_value($h)) . '</td>'
                    . '<td><small>' . cpm_e(date('d/m/y', strtotime($h->created_at))) . '</small></td></tr>';
            }
            echo '</tbody></table></div>';
        }
    }
    echo '</div></div>';
}

/* ------------------------------------------------------------------ */
/* Χρόνος εργασίας — panel στο task (2.1/2.2)                         */
/* ------------------------------------------------------------------ */

function cpm_time_panel($link, $adminId, $t)
{
    $total = Db::taskMinutes($t->id);
    $uid = Time::clientForTask($t);
    $scOn = Time::scReady() && $uid;

    echo '<div class="panel panel-default" id="time"><div class="panel-heading"><b><i class="far fa-clock"></i> Χρόνος εργασίας</b>'
        . ($total ? ' <span class="label label-primary" style="font-size:12px">' . cpm_e(Time::fmt($total)) . '</span>' : '')
        . ($t->estimate_minutes ? ' <small class="text-muted">/ εκτίμηση ' . cpm_e(Time::fmt($t->estimate_minutes)) . '</small>' : '')
        . '</div><div class="panel-body">';
    // estimate vs πραγματικός (GoodDay-style)
    if ($t->estimate_minutes) {
        $pct = (int) round($total / $t->estimate_minutes * 100);
        $barCls = $pct <= 80 ? 'progress-bar-success' : ($pct <= 100 ? 'progress-bar-warning' : 'progress-bar-danger');
        echo '<div class="progress" style="height:9px;margin-bottom:4px"><div class="progress-bar ' . $barCls . '" style="width:' . min(100, $pct) . '%"></div></div>'
            . '<small class="text-muted">' . $pct . '% της εκτίμησης'
            . ($pct > 100 ? ' — <b class="text-danger">υπέρβαση ' . cpm_e(Time::fmt($total - $t->estimate_minutes)) . '</b>' : '') . '</small>';
    }

    if ($scOn) {
        echo '<p class="text-muted" style="font-size:11px;margin-top:0"><i class="fas fa-link"></i> Πελάτης: <b>'
            . cpm_e(cpm_client_label($uid)) . '</b> — οι καταχωρήσεις περνούν στο Συμβόλαιο Υποστήριξης'
            . ' (τα χρεώσιμα αφαιρούν προαγορά).</p>';
    } else {
        echo '<p class="text-muted" style="font-size:11px;margin-top:0">Εσωτερική εργασία — ο χρόνος μένει μόνο στο project.</p>';
    }

    // timer
    $running = Db::runningTimer($adminId);
    if ($running && (int) $running->task_id === (int) $t->id) {
        $secs = time() - strtotime($running->started_at);
        echo '<div class="alert alert-success" style="margin-bottom:10px"><i class="fas fa-stopwatch"></i> Τρέχει: '
            . '<b id="cnpElapsed" data-secs="' . $secs . '">…</b>'
            . '<form method="post" action="' . $link . '" style="margin-top:8px">'
            . '<input type="hidden" name="do" value="stoptimer"><input type="hidden" name="logid" value="' . (int) $running->id . '">'
            . '<label style="margin-right:8px;font-weight:normal"><input type="radio" name="billable" value="1"' . ($scOn ? ' checked' : '') . '> Χρεώσιμο</label>'
            . '<label style="margin-right:8px;font-weight:normal"><input type="radio" name="billable" value="0"' . ($scOn ? '' : ' checked') . '> Χωρίς χρέωση</label>'
            . '<input type="text" name="note" class="form-control input-sm" style="margin:6px 0" placeholder="σημείωση (προαιρετικά)">'
            . '<button class="btn btn-sm btn-danger"><i class="fas fa-stop"></i> Stop & καταχώρηση</button>'
            . '</form></div>'
            . '<script>(function(){var e=document.getElementById("cnpElapsed");if(!e)return;var s=parseInt(e.getAttribute("data-secs"),10);'
            . 'function f(){var h=Math.floor(s/3600),m=Math.floor(s%3600/60),c=s%60;'
            . 'e.textContent=(h?h+":":"")+("0"+m).slice(-2)+":"+("0"+c).slice(-2);s++;}f();setInterval(f,1000);})();</script>';
    } else {
        if ($running) {
            $ot = Db::task($running->task_id);
            echo '<div class="alert alert-warning" style="padding:6px 10px;font-size:12px">Τρέχει ήδη timer στο '
                . '<a href="' . $link . '&tab=task&id=' . (int) $running->task_id . '">' . cpm_e($ot->title ?? ('#' . $running->task_id)) . '</a>'
                . ' — αν ξεκινήσεις εδώ, εκείνος θα σταματήσει αυτόματα (χωρίς χρέωση).</div>';
        }
        echo '<form method="post" action="' . $link . '" style="margin-bottom:10px">'
            . '<input type="hidden" name="do" value="starttimer"><input type="hidden" name="taskid" value="' . (int) $t->id . '">'
            . '<button class="btn btn-sm btn-success"><i class="fas fa-play"></i> Start timer</button></form>';
    }

    // manual entry
    echo '<form method="post" action="' . $link . '" class="form-inline" style="border-top:1px solid #eee;padding-top:10px">'
        . '<input type="hidden" name="do" value="addtime"><input type="hidden" name="taskid" value="' . (int) $t->id . '">'
        . '<input type="number" step="0.25" min="0" name="hours" class="form-control input-sm" style="width:65px" placeholder="ώρες"> '
        . '<input type="number" min="0" name="minutes" class="form-control input-sm" style="width:65px" placeholder="λεπτά"> '
        . '<label style="font-weight:normal;margin:0 4px"><input type="radio" name="billable" value="1"' . ($scOn ? ' checked' : '') . '> Χρεώσιμο</label>'
        . '<label style="font-weight:normal;margin:0 4px"><input type="radio" name="billable" value="0"' . ($scOn ? '' : ' checked') . '> Χωρίς</label> '
        . '<input type="text" name="note" class="form-control input-sm" style="width:130px" placeholder="σημείωση"> '
        . '<button class="btn btn-sm btn-primary">Καταχώρηση</button></form>';

    // entries
    $logs = Db::timelogsForTask($t->id);
    if (count($logs)) {
        echo '<table class="table table-condensed" style="margin:12px 0 0;font-size:12px"><thead><tr>'
            . '<th>Πότε</th><th>Ποιος</th><th>Χρόνος</th><th>Χρέωση</th><th>Σημείωση</th><th></th></tr></thead><tbody>';
        foreach ($logs as $l) {
            if ($l->running) {
                echo '<tr class="text-success"><td>' . cpm_e(date('d/m H:i', strtotime($l->started_at))) . '</td>'
                    . '<td>' . cpm_e(Db::adminName($l->admin_id)) . '</td><td colspan="3"><i>σε εξέλιξη…</i></td><td></td></tr>';
                continue;
            }
            echo '<tr><td>' . cpm_e(date('d/m H:i', strtotime($l->created_at))) . '</td>'
                . '<td>' . cpm_e(Db::adminName($l->admin_id)) . '</td>'
                . '<td><b>' . cpm_e(Time::fmt($l->minutes)) . '</b></td>'
                . '<td>' . ((int) $l->billable
                    ? '<span class="label label-warning">' . cpm_e(Time::fmt($l->charged_minutes ?: $l->minutes)) . '</span>'
                    : '<span class="label label-default">—</span>') . '</td>'
                . '<td>' . cpm_e($l->note ?: '') . '</td>'
                . '<td><form method="post" action="' . $link . '" style="display:inline" onsubmit="return confirm(\'Διαγραφή καταχώρησης; Αν είχε χρεωθεί, η χρέωση θα επιστραφεί στον πελάτη.\')">'
                . '<input type="hidden" name="do" value="deltime"><input type="hidden" name="logid" value="' . (int) $l->id . '">'
                . '<button class="btn btn-xs btn-link text-danger" style="padding:0"><i class="fas fa-times"></i></button></form></td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div></div>';
}

/* ------------------------------------------------------------------ */
/* Tab «Χρόνος» — σύνολα ανά project/πελάτη/χειριστή (2.4)            */
/* ------------------------------------------------------------------ */

function cpm_tab_time($link)
{
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
    $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
    $fp   = (int) ($_GET['fp'] ?? 0);
    $fa   = cpm_is_full() ? (int) ($_GET['fa'] ?? 0) : cpm_admin_id(); // περιορισμένος: μόνο ο δικός του χρόνος

    echo '<form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom:14px">'
        . '<input type="hidden" name="module" value="cloudonprojects"><input type="hidden" name="tab" value="time"> '
        . 'Από <input type="date" name="from" class="form-control" value="' . cpm_e($from) . '"> '
        . 'έως <input type="date" name="to" class="form-control" value="' . cpm_e($to) . '"> ';
    echo '<select name="fp" class="form-control"><option value="">— όλα τα projects —</option>';
    foreach (Db::projectsFor(cpm_admin_id(), true) as $p) {
        echo '<option value="' . (int) $p->id . '"' . ($fp == $p->id ? ' selected' : '') . '>' . cpm_e($p->name) . '</option>';
    }
    if (cpm_is_full()) {
        echo '</select> <select name="fa" class="form-control"><option value="">— χειριστής —</option>';
        foreach (Db::admins() as $a) {
            echo '<option value="' . (int) $a->id . '"' . ($fa == $a->id ? ' selected' : '') . '>' . cpm_e(trim($a->firstname . ' ' . $a->lastname)) . '</option>';
        }
        echo '</select>';
    } else {
        echo '</select> <span class="label label-default">μόνο ο χρόνος σου</span>';
    }
    echo ' <button class="btn btn-default">Προβολή</button></form>';

    $rows = Db::timeReport($from, $to, ['project_id' => $fp, 'admin_id' => $fa]);

    // aggregate
    $tot = ['w' => 0, 'b' => 0, 'nb' => 0, 'c' => 0];
    $byProject = $byClient = $byAdmin = [];
    foreach ($rows as $r) {
        $m = (int) $r->minutes;
        $tot['w'] += $m;
        $tot[(int) $r->billable ? 'b' : 'nb'] += $m;
        $tot['c'] += (int) $r->charged_minutes;
        foreach ([['byProject', $r->project_name ?: 'Χωρίς έργο'], ['byClient', $r->clientid ? cpm_client_label($r->clientid) : '— εσωτερικά —'], ['byAdmin', Db::adminName($r->admin_id)]] as $g) {
            [$grp, $key] = $g;
            if (!isset(${$grp}[$key])) { ${$grp}[$key] = ['w' => 0, 'b' => 0, 'c' => 0]; }
            ${$grp}[$key]['w'] += $m;
            if ((int) $r->billable) { ${$grp}[$key]['b'] += $m; }
            ${$grp}[$key]['c'] += (int) $r->charged_minutes;
        }
    }

    echo '<div class="row" style="margin-bottom:6px">';
    foreach ([['Σύνολο εργασίας', $tot['w'], 'primary'], ['Χρεώσιμα', $tot['b'], 'warning'],
              ['Χωρίς χρέωση', $tot['nb'], 'default'], ['Χρεώθηκαν (προαγορά)', $tot['c'], 'danger']] as $c) {
        echo '<div class="col-sm-3"><div class="panel panel-' . $c[2] . '"><div class="panel-body" style="text-align:center">'
            . '<div style="font-size:22px;font-weight:700">' . cpm_e(Time::fmt($c[1])) . '</div>'
            . '<small class="text-muted">' . $c[0] . '</small></div></div></div>';
    }
    echo '</div>';

    $groupTable = function ($title, $data) {
        arsort($data);
        $h = '<div class="col-md-4"><div class="panel panel-default"><div class="panel-heading"><b>' . $title . '</b></div>'
            . '<table class="table table-condensed" style="margin:0;font-size:12px"><thead><tr><th></th><th>Σύνολο</th><th>Χρεώσιμα</th><th>Χρέωση</th></tr></thead><tbody>';
        if (!count($data)) { $h .= '<tr><td colspan="4" class="text-muted text-center">—</td></tr>'; }
        foreach ($data as $k => $v) {
            $h .= '<tr><td>' . cpm_e($k) . '</td><td><b>' . cpm_e(Time::fmt($v['w'])) . '</b></td>'
                . '<td>' . cpm_e(Time::fmt($v['b'])) . '</td><td>' . cpm_e(Time::fmt($v['c'])) . '</td></tr>';
        }
        return $h . '</tbody></table></div></div>';
    };
    // sort by worked minutes
    foreach ([&$byProject, &$byClient, &$byAdmin] as &$d) {
        uasort($d, function ($a, $b) { return $b['w'] <=> $a['w']; });
    }
    unset($d);
    echo '<div class="row">' . $groupTable('Ανά project', $byProject)
        . $groupTable('Ανά πελάτη', $byClient)
        . $groupTable('Ανά χειριστή', $byAdmin) . '</div>';

    // entries
    echo '<div class="panel panel-default"><div class="panel-heading"><b>Καταχωρήσεις (' . count($rows) . ')</b></div>'
        . '<table class="table table-striped table-condensed" style="margin:0;font-size:12px"><thead><tr>'
        . '<th>Πότε</th><th>Task</th><th>Project</th><th>Πελάτης</th><th>Χειριστής</th><th>Χρόνος</th><th>Χρέωση</th><th>Σημείωση</th></tr></thead><tbody>';
    if (!count($rows)) {
        echo '<tr><td colspan="8" class="text-center text-muted">Καμία καταχώρηση στην περίοδο.</td></tr>';
    }
    foreach ($rows as $r) {
        echo '<tr><td>' . cpm_e(date('d/m/Y H:i', strtotime($r->created_at))) . '</td>'
            . '<td><a href="' . $link . '&tab=task&id=' . (int) $r->task_id . '">' . cpm_e($r->task_title) . '</a></td>'
            . '<td><span class="cnp-dot" style="background:' . cpm_e($r->project_color ?: '#8595ac') . '"></span> ' . cpm_e($r->project_name ?: 'Χωρίς έργο') . '</td>'
            . '<td>' . cpm_e($r->clientid ? cpm_client_label($r->clientid) : '—') . '</td>'
            . '<td>' . cpm_e(Db::adminName($r->admin_id)) . '</td>'
            . '<td><b>' . cpm_e(Time::fmt($r->minutes)) . '</b></td>'
            . '<td>' . ((int) $r->billable ? '<span class="label label-warning">' . cpm_e(Time::fmt($r->charged_minutes)) . '</span>' : '—') . '</td>'
            . '<td>' . cpm_e($r->note ?: '') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
