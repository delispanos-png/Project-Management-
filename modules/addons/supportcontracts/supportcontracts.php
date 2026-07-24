<?php
/**
 * Support Contracts & SLA
 * ------------------------------------------------------------------
 * Manages, per client:
 *   • a PREPAID SUPPORT TIME bank (balance in hours/minutes) with a full
 *     consumption ledger the client can inspect;
 *   • a PRIORITY flag that boosts the client in the ticket triage;
 *   • a custom SLA (first-response time + business days/hours/timezone),
 *     with the SLA clock counting only inside business hours.
 *
 * Billable flow: a ticket is marked "billable" on the admin ticket screen;
 * the operator then logs the handling time, which is auto-deducted from the
 * client's prepaid balance and recorded in the ledger.
 *
 * @package WHMCS\Module\Addon\SupportContracts
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\SupportContracts\Db;
use WHMCS\Module\Addon\SupportContracts\Sla;

require_once __DIR__ . '/lib/Db.php';
require_once __DIR__ . '/lib/Sla.php';
require_once __DIR__ . '/lib/Format.php';

function supportcontracts_config()
{
    return [
        'name'        => 'Support Contracts & SLA',
        'description' => 'Προαγορασμένος χρόνος υποστήριξης (τράπεζα χρόνου), προτεραιότητα πελάτη & custom SLA ανά πελάτη.',
        'version'     => '1.0',
        'author'      => 'Cloud On',
        'language'    => 'greek',
        'fields'      => [
            'default_response_value' => [
                'FriendlyName' => 'Default response time',
                'Type' => 'text', 'Size' => '5', 'Default' => '8',
                'Description' => 'ΠΡΟΕΠΙΛΟΓΗ για νέα συμβόλαια (η γενική μας διαθεσιμότητα). '
                    . '<b>Κάθε πελάτης ρυθμίζεται ΞΕΧΩΡΙΣΤΑ</b> στο συμβόλαιό του (μέρες/ώρες/ζώνη/SLA) — π.χ. default Δευ-Παρ 9-5, ή 7 ημέρες για ειδικές περιπτώσεις.',
            ],
            'default_response_unit' => [
                'FriendlyName' => 'Default response unit',
                'Type' => 'dropdown', 'Options' => 'hours,days', 'Default' => 'hours',
            ],
            'default_biz_days' => [
                'FriendlyName' => 'Default business days',
                'Type' => 'text', 'Size' => '14', 'Default' => '1,2,3,4,5',
                'Description' => 'ISO ημέρες (1=Δευ … 7=Κυρ), χωρισμένες με κόμμα.',
            ],
            'default_biz_start' => [
                'FriendlyName' => 'Default business start', 'Type' => 'text', 'Size' => '5', 'Default' => '09:00',
            ],
            'default_biz_end' => [
                'FriendlyName' => 'Default business end', 'Type' => 'text', 'Size' => '5', 'Default' => '17:00',
            ],
            'default_tz' => [
                'FriendlyName' => 'Default timezone', 'Type' => 'text', 'Size' => '24', 'Default' => 'Europe/Athens',
            ],
            'low_balance_hours' => [
                'FriendlyName' => 'Low-balance warning (hours)',
                'Type' => 'text', 'Size' => '5', 'Default' => '2',
                'Description' => 'Κάτω από αυτό το υπόλοιπο, το home δείχνει προειδοποίηση.',
            ],
            'client_ledger' => [
                'FriendlyName' => 'Client can view usage',
                'Type' => 'yesno', 'Default' => 'on',
                'Description' => 'Επιτρέπει στον πελάτη να βλέπει αναλυτικά την κατανάλωση του προαγορασμένου χρόνου.',
            ],
            'prepaid_hours_map' => [
                'FriendlyName' => 'Προϊόντα προαγοράς (JSON)',
                'Type' => 'textarea', 'Rows' => '3', 'Cols' => '50', 'Default' => '{}',
                'Description' => 'Product ID → ώρες που προστίθενται ΑΥΤΟΜΑΤΑ στο υπόλοιπο του πελάτη μόλις πληρωθεί το τιμολόγιο. '
                    . 'π.χ. <code>{"270":10,"271":20,"272":50}</code>. Δημιούργησε προϊόντα «Προαγορά Xω» και βάλ\' τα εδώ.',
            ],
            'weekly_report' => [
                'FriendlyName' => 'Εβδομαδιαίο email πελάτη',
                'Type' => 'yesno', 'Default' => '',
                'Description' => 'Στέλνει αυτόματα στον πελάτη, στο τέλος κάθε εβδομάδας, την κίνηση του προαγορασμένου χρόνου '
                    . '(αγορές/αναλώσεις ανά ticket + υπόλοιπο). Απαιτεί το cron <code>crons/weekly_report.php</code>.',
            ],
            'charge_step_minutes' => [
                'FriendlyName' => 'Βήμα χρέωσης (λεπτά)',
                'Type' => 'text', 'Size' => '5', 'Default' => '15',
                'Description' => 'Ο χρόνος χρέωσης στρογγυλοποιείται ΠΡΟΣ ΤΑ ΠΑΝΩ στο πλησιέστερο πολλαπλάσιο. '
                    . 'π.χ. 15 = ανά τέταρτο, 30 = ανά μισάωρο, 60 = ανά ώρα.',
            ],
            'min_charge_hours' => [
                'FriendlyName' => 'Ελάχιστη χρέωση (ώρες)',
                'Type' => 'text', 'Size' => '5', 'Default' => '0',
                'Description' => 'Ελάχιστος χρόνος ανά καταχώρηση (0 = μόνο το βήμα χρέωσης). π.χ. 1 = τουλάχιστον 1 ώρα.',
            ],
        ],
    ];
}

function supportcontracts_activate()
{
    try {
        Db::install();
        supportcontracts_ensure_email_template();
        return ['status' => 'success', 'description' => 'Οι πίνακες δημιουργήθηκαν.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Σφάλμα: ' . $e->getMessage()];
    }
}

function supportcontracts_deactivate()
{
    return ['status' => 'success', 'description' => 'Απενεργοποιήθηκε (τα δεδομένα διατηρήθηκαν).'];
}

function supportcontracts_upgrade($vars)
{
    Db::install();
}

/* ------------------------------------------------------------------ */
/* Helpers                                                            */
/* ------------------------------------------------------------------ */

function supportcontracts_client_name($userid)
{
    $c = Capsule::table('tblclients')->where('id', (int) $userid)->first(['firstname', 'lastname', 'companyname']);
    if (!$c) {
        return "#{$userid}";
    }
    $name = trim($c->firstname . ' ' . $c->lastname);
    return $c->companyname ? "{$c->companyname} ({$name})" : $name;
}

/** Validate + store an uploaded contract file; returns the stored filename or null. */
function supportcontracts_handle_upload($uid)
{
    if (empty($_FILES['contract_file']['name']) || !is_uploaded_file($_FILES['contract_file']['tmp_name'] ?? '')) {
        return null;
    }
    $f = $_FILES['contract_file'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx', 'odt', 'jpg', 'jpeg', 'png', 'txt', 'xls', 'xlsx'];
    if (!in_array($ext, $allowed, true) || (int) $f['size'] <= 0 || (int) $f['size'] > 20 * 1024 * 1024) {
        return null;
    }
    $dir = supportcontracts_storage_dir();
    $safe = 'contract_' . (int) $uid . '_' . date('Ymd_His') . '.' . $ext;
    if (!$dir || !@move_uploaded_file($f['tmp_name'], $dir . '/' . $safe)) {
        return null;
    }
    $old = Db::contract($uid);
    if ($old && $old->contract_file && is_file($dir . '/' . $old->contract_file)) {
        @unlink($dir . '/' . $old->contract_file);
    }
    return $safe;
}

/** Storage dir for attached contracts — outside the web root (WHMCS attachments dir). */
function supportcontracts_storage_dir()
{
    $base = '';
    if (!empty($GLOBALS['attachments_dir'])) {
        $base = $GLOBALS['attachments_dir'];
    } elseif (defined('ROOTDIR')) {
        $base = ROOTDIR . '/attachments';
    } else {
        $base = __DIR__ . '/storage';
    }
    $dir = rtrim($base, '/\\') . '/supportcontracts';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return is_dir($dir) ? $dir : '';
}

/* ------------------------------------------------------------------ */
/* Admin area                                                         */
/* ------------------------------------------------------------------ */

function supportcontracts_output($vars)
{
    $link    = $vars['modulelink'];
    $cfg     = $vars;
    $action  = $_REQUEST['scaction'] ?? 'list';
    $adminId = $_SESSION['adminid'] ?? null;

    // ---- Download the attached contract (admin-only; streams before any output) ----
    if ($action === 'download') {
        $du = (int) ($_REQUEST['userid'] ?? 0);
        $dc = $du ? Db::contract($du) : null;
        $dir = supportcontracts_storage_dir();
        if ($dc && $dc->contract_file && $dir) {
            $path = $dir . '/' . basename($dc->contract_file);
            if (is_file($path)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($dc->contract_file) . '"');
                header('Content-Length: ' . filesize($path));
                readfile($path);
                exit;
            }
        }
        echo '<div class="alert alert-warning">Το αρχείο δεν βρέθηκε.</div>';
        return;
    }

    // ---- POST handlers (redirect back afterwards) ----
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = (int) ($_POST['userid'] ?? 0);

        if ($action === 'save' && $uid > 0) {
            $wasNew = !Db::contract($uid);
            Db::saveContract($uid, [
                'enabled'            => isset($_POST['enabled']) ? 1 : 0,
                'priority'           => (int) ($_POST['priority'] ?? 0),
                'sla_response_value' => max(0, (int) ($_POST['sla_response_value'] ?? 0)),
                'sla_response_unit'  => in_array($_POST['sla_response_unit'] ?? 'hours', ['hours', 'days'], true) ? $_POST['sla_response_unit'] : 'hours',
                'biz_days'           => preg_replace('/[^0-9,]/', '', $_POST['biz_days'] ?? '1,2,3,4,5'),
                'biz_start'          => substr(preg_replace('/[^0-9:]/', '', $_POST['biz_start'] ?? '09:00'), 0, 5),
                'biz_end'            => substr(preg_replace('/[^0-9:]/', '', $_POST['biz_end'] ?? '17:00'), 0, 5),
                'biz_tz'             => substr(trim($_POST['biz_tz'] ?? 'Europe/Athens'), 0, 40),
                'label'              => substr(trim($_POST['label'] ?? ''), 0, 100),
                'report_email'       => substr(trim($_POST['report_email'] ?? ''), 0, 255),
            ]);
            // Initial-balance seeding (migration from the old system, NO payment)
            $initMins = (int) round(((float) ($_POST['init_hours'] ?? 0)) * 60) + (int) ($_POST['init_minutes'] ?? 0);
            if ($wasNew && $initMins > 0) {
                Db::applyMovement($uid, $initMins, 'adjust', 'Αρχικοποίηση υπολοίπου (μεταφορά από παλιό σύστημα)', null, $adminId);
            }
            header('Location: ' . $link . '&scaction=edit&userid=' . $uid . '&saved=1');
            return;
        }

        if ($action === 'topup' && $uid > 0) {
            $mins = (int) round(((float) ($_POST['hours'] ?? 0)) * 60) + (int) ($_POST['minutes'] ?? 0);
            if ($mins !== 0) {
                Db::applyMovement($uid, abs($mins), 'topup', trim($_POST['note'] ?? 'Top-up'), null, $adminId);
            }
            header('Location: ' . $link . '&scaction=edit&userid=' . $uid . '&topped=1');
            return;
        }

        if ($action === 'adjust' && $uid > 0) {
            $mins = (int) round(((float) ($_POST['hours'] ?? 0)) * 60) + (int) ($_POST['minutes'] ?? 0);
            $sign = ($_POST['direction'] ?? 'add') === 'sub' ? -1 : 1;
            if ($mins !== 0) {
                Db::applyMovement($uid, $sign * abs($mins), 'adjust', trim($_POST['note'] ?? 'Adjustment'), null, $adminId);
            }
            header('Location: ' . $link . '&scaction=edit&userid=' . $uid . '&adjusted=1');
            return;
        }

        if ($action === 'delete' && $uid > 0) {
            $c = Db::contract($uid);
            if ($c) {
                $dir = supportcontracts_storage_dir();
                if ($c->contract_file && $dir && is_file($dir . '/' . $c->contract_file)) {
                    @unlink($dir . '/' . $c->contract_file);
                }
                Capsule::table('mod_supportcontracts_ledger')->where('userid', $uid)->delete();
                Capsule::table('mod_supportcontracts_worklog')->where('userid', $uid)->delete();
                Capsule::table('mod_supportcontracts_clients')->where('userid', $uid)->delete();
                if (function_exists('logActivity')) {
                    logActivity('Support Contracts: οριστική διαγραφή συμβολαίου + ιστορικού για client #' . $uid . ' (admin #' . (int) $adminId . ')');
                }
            }
            header('Location: ' . $link . '&deleted=1');
            return;
        }

        if ($action === 'savemeta' && $uid > 0) {
            $data = [
                'notes'        => mb_substr(trim($_POST['notes'] ?? ''), 0, 65000),
                'ticket_notes' => mb_substr(trim($_POST['ticket_notes'] ?? ''), 0, 65000),
                'covered'      => mb_substr(trim($_POST['covered'] ?? ''), 0, 65000),
            ];
            $uploaded = supportcontracts_handle_upload($uid);
            if ($uploaded) {
                $data['contract_file'] = $uploaded;
            }
            if (Db::contract($uid)) {
                Db::saveContract($uid, $data);
            }
            header('Location: ' . $link . '&scaction=edit&userid=' . $uid . '&metasaved=1');
            return;
        }

        if ($action === 'logtime') {
            $tid = (int) ($_POST['ticketid'] ?? 0);
            $mins = (int) round(((float) ($_POST['hours'] ?? 0)) * 60) + (int) ($_POST['minutes'] ?? 0);
            // charge = round worked time UP to the billing step, then apply the minimum
            $step = max(1, (int) ($cfg['charge_step_minutes'] ?? 15));
            $minCharge = (int) round(((float) ($cfg['min_charge_hours'] ?? 0)) * 60);
            $backTid = (int) ($_POST['whmcs_tid'] ?? 0);
            $isBillable = (($_POST['billable_mode'] ?? '1') === '1');
            if ($tid > 0) {
                $trow = Capsule::table('tbltickets')->where('id', $tid)->first(['userid']);
                $tuid = $trow ? (int) $trow->userid : 0;
                $charge = ($isBillable && $mins > 0) ? max($minCharge, (int) ceil($mins / $step) * $step) : 0;
                $st = Db::ticket($tid);
                Db::saveTicket($tid, [
                    'userid'         => $tuid,
                    'billable'       => $isBillable ? 1 : (int) ($st->billable ?? 0),
                    'minutes_logged' => (int) ($st->minutes_logged ?? 0) + $charge,
                ]);
                if ($mins > 0 && $tuid > 0) {
                    $note = 'Ticket #' . $backTid . ' – ' . ($isBillable ? 'χρόνος διεκπεραίωσης' : 'εργασία χωρίς χρέωση');
                    // worklog: every entry (billable + non-billable) with timestamps for reporting
                    Db::addWork($tuid, $tid, $mins, $charge, $isBillable, $note, $adminId);
                    if ($isBillable && $charge > 0 && Db::contract($tuid)) {
                        $ln = $note . ($charge > $mins ? ' (εργασία ' . supportcontracts_fmt_minutes($mins) . ', χρέωση ' . supportcontracts_fmt_minutes($charge) . ')' : '');
                        Db::applyMovement($tuid, -abs($charge), 'usage', $ln, $tid, $adminId);
                    }
                }
            }
            // return to the admin ticket view
            header('Location: ' . (defined('WHMCS') ? 'supporttickets.php?action=view&id=' . $tid : $link));
            return;
        }
    }

    echo '<div class="row"><div class="col-md-12">';

    if ($action === 'edit') {
        echo supportcontracts_render_edit($link, (int) ($_REQUEST['userid'] ?? 0), $vars);
    } elseif ($action === 'ledger') {
        echo supportcontracts_render_ledger($link, (int) ($_REQUEST['userid'] ?? 0));
    } else {
        echo supportcontracts_render_list($link, $vars);
    }

    echo '</div></div>';
}

function supportcontracts_render_list($link, $vars)
{
    $rows = Db::allContracts();
    $lowThresh = (int) (((float) ($vars['low_balance_hours'] ?? 2)) * 60);

    // total purchased / used per client (one grouped query)
    $agg = [];
    foreach (Capsule::table('mod_supportcontracts_ledger')
        ->selectRaw('userid, SUM(CASE WHEN minutes>0 THEN minutes ELSE 0 END) AS added, SUM(CASE WHEN minutes<0 THEN -minutes ELSE 0 END) AS used')
        ->groupBy('userid')->get() as $a) {
        $agg[(int) $a->userid] = ['added' => (int) $a->added, 'used' => (int) $a->used];
    }

    $h  = isset($_GET['deleted']) ? '<div class="alert alert-success">Το συμβόλαιο και το ιστορικό του διαγράφηκαν οριστικά.</div>' : '';
    $h .= '<div style="margin-bottom:14px"><a href="' . $link . '&scaction=edit" class="btn btn-primary"><i class="fas fa-plus"></i> Νέο συμβόλαιο / Ανάθεση πελάτη</a></div>';

    // pending billable worklist
    $pending = Db::pendingBillable(50);
    if (count($pending)) {
        $h .= '<div class="panel panel-warning"><div class="panel-heading"><b>Χρεώσιμα tickets χωρίς καταχωρημένο χρόνο</b></div><div class="panel-body" style="padding:0"><table class="table table-condensed" style="margin:0"><thead><tr><th>Ticket</th><th>Θέμα</th><th>Πελάτης</th><th>Κατάσταση</th><th></th></tr></thead><tbody>';
        foreach ($pending as $p) {
            $h .= '<tr><td>#' . (int) $p->tid . '</td><td>' . htmlspecialchars($p->title) . '</td><td>' . htmlspecialchars(supportcontracts_client_name($p->userid)) . '</td><td>' . htmlspecialchars($p->status) . '</td><td><a class="btn btn-xs btn-default" href="supporttickets.php?action=view&id=' . (int) $p->ticketid . '">Άνοιγμα</a></td></tr>';
        }
        $h .= '</tbody></table></div></div>';
    }

    $h .= '<table class="table table-striped table-bordered"><thead><tr>'
        . '<th>Πελάτης</th><th>Κατάσταση</th><th>Προτεραιότητα</th><th>Διαθέσιμο υπόλοιπο</th><th>Αγορασμένες</th><th>Αναλωμένες</th><th>SLA απόκρισης</th><th>Ωράριο</th><th></th></tr></thead><tbody>';

    if (!count($rows)) {
        $h .= '<tr><td colspan="9" class="text-center text-muted">Κανένα συμβόλαιο ακόμη.</td></tr>';
    }
    foreach ($rows as $r) {
        $prio = [0 => 'Κανονική', 1 => '<span class="label label-warning">Υψηλή</span>', 2 => '<span class="label label-danger">Κρίσιμη</span>'][$r->priority] ?? 'Κανονική';
        $bal = supportcontracts_fmt_minutes($r->balance_minutes);
        $balCls = $r->balance_minutes <= 0 ? 'text-danger' : ($r->balance_minutes < $lowThresh ? 'text-warning' : 'text-success');
        $h .= '<tr>'
            . '<td>' . htmlspecialchars(supportcontracts_client_name($r->userid)) . '</td>'
            . '<td>' . ($r->enabled ? '<span class="label label-success">Ενεργό</span>' : '<span class="label label-default">Ανενεργό</span>') . '</td>'
            . '<td>' . $prio . '</td>'
            . '<td class="' . $balCls . '"><b>' . $bal . '</b></td>'
            . '<td class="text-muted">' . supportcontracts_fmt_minutes($agg[(int) $r->userid]['added'] ?? 0) . '</td>'
            . '<td class="text-muted">' . supportcontracts_fmt_minutes($agg[(int) $r->userid]['used'] ?? 0) . '</td>'
            . '<td>' . htmlspecialchars(Sla::humanResponse($r)) . '</td>'
            . '<td><small>' . htmlspecialchars(Sla::humanHours($r)) . '</small></td>'
            . '<td><a class="btn btn-xs btn-default" href="' . $link . '&scaction=edit&userid=' . (int) $r->userid . '">Επεξεργασία</a> '
            . '<a class="btn btn-xs btn-default" href="' . $link . '&scaction=ledger&userid=' . (int) $r->userid . '">Ιστορικό</a> '
            . '<form method="post" action="' . $link . '&scaction=delete" style="display:inline" '
            . 'onsubmit="return confirm(\'Οριστική διαγραφή του συμβολαίου ΚΑΙ όλου του ιστορικού (κινήσεις, εργασίες, σύμβαση) για τον πελάτη; ΔΕΝ αναιρείται!\')">'
            . '<input type="hidden" name="userid" value="' . (int) $r->userid . '">'
            . '<button class="btn btn-xs btn-danger">Διαγραφή</button></form></td>'
            . '</tr>';
    }
    $h .= '</tbody></table>';
    return $h;
}

/** Client custom-field id that holds the ΑΦΜ (VAT ID / Tax). */
function supportcontracts_vat_field_id()
{
    static $id = false;
    if ($id === false) {
        $id = Capsule::table('tblcustomfields')->where('type', 'client')
            ->where(function ($w) {
                $w->where('fieldname', 'like', '%VAT%')
                  ->orWhere('fieldname', 'like', '%ΑΦΜ%')
                  ->orWhere('fieldname', 'like', '%Tax ID%');
            })
            ->orderBy('id')->value('id');
    }
    return $id;
}

/** Search clients by ΑΦΜ (VAT custom field / tax_id), client id, name/company, or email. */
function supportcontracts_search_clients($q)
{
    $q = trim((string) $q);
    if ($q === '') {
        return [];
    }
    $vatField = supportcontracts_vat_field_id();
    $isNumeric = ctype_digit($q);
    $esc = str_replace(['%', '_'], ['\%', '\_'], $q);
    // partial ΑΦΜ match only for ΑΦΜ-length queries (≥5 chars); short queries = exact
    $partial = strlen($q) >= 5;
    $ids = [];
    if ($isNumeric) {
        $ids[] = (int) $q; // client id (exact)
    }
    if ($vatField) {
        $vq = Capsule::table('tblcustomfieldsvalues')->where('fieldid', $vatField);
        $partial ? $vq->where('value', 'like', '%' . $esc . '%') : $vq->where('value', $q);
        foreach ($vq->limit(30)->pluck('relid') as $r) {
            $ids[] = (int) $r;
        }
    }
    $tq = Capsule::table('tblclients');
    $partial ? $tq->where('tax_id', 'like', '%' . $esc . '%') : $tq->where('tax_id', $q);
    foreach ($tq->limit(30)->pluck('id') as $r) {
        $ids[] = (int) $r;
    }

    if ($isNumeric) {
        // numeric query → treat as ID/ΑΦΜ only (no fuzzy name matching noise)
        if (!$ids) {
            return [];
        }
        $rows = Capsule::table('tblclients')->whereIn('id', array_unique($ids))
            ->orderBy('companyname')->limit(30)
            ->get(['id', 'firstname', 'lastname', 'companyname', 'email']);
    } else {
        $like = '%' . $esc . '%';
        $rows = Capsule::table('tblclients')
            ->where(function ($w) use ($like, $ids) {
                $w->where('companyname', 'like', $like)->orWhere('firstname', 'like', $like)
                  ->orWhere('lastname', 'like', $like)->orWhere('email', 'like', $like);
                if ($ids) {
                    $w->orWhereIn('id', array_unique($ids));
                }
            })
            ->orderBy('companyname')->limit(30)
            ->get(['id', 'firstname', 'lastname', 'companyname', 'email']);
    }
    foreach ($rows as $r) {
        $afm = $vatField
            ? Capsule::table('tblcustomfieldsvalues')->where('fieldid', $vatField)->where('relid', $r->id)->value('value')
            : null;
        $r->afm = $afm ?: Capsule::table('tblclients')->where('id', $r->id)->value('tax_id');
    }
    return $rows;
}

function supportcontracts_render_edit($link, $userid, $vars)
{
    $c = $userid ? Db::contract($userid) : null;
    // defaults for a new contract
    $d = [
        'enabled' => 1, 'priority' => 0,
        'sla_response_value' => $vars['default_response_value'] ?? 8,
        'sla_response_unit'  => $vars['default_response_unit'] ?? 'hours',
        'biz_days'  => $vars['default_biz_days'] ?? '1,2,3,4,5',
        'biz_start' => $vars['default_biz_start'] ?? '09:00',
        'biz_end'   => $vars['default_biz_end'] ?? '17:00',
        'biz_tz'    => $vars['default_tz'] ?? 'Europe/Athens',
        'label'     => '', 'balance_minutes' => 0,
        'report_email' => '', 'notes' => '', 'ticket_notes' => '', 'covered' => '', 'contract_file' => '',
    ];
    if ($c) {
        foreach ($d as $k => $v) {
            $d[$k] = $c->$k ?? $v;
        }
    }
    $flash = '';
    if (isset($_GET['saved']))   { $flash = '<div class="alert alert-success">Αποθηκεύτηκε.</div>'; }
    if (isset($_GET['topped']))  { $flash = '<div class="alert alert-success">Το υπόλοιπο ενημερώθηκε.</div>'; }
    if (isset($_GET['adjusted'])){ $flash = '<div class="alert alert-success">Η προσαρμογή καταχωρήθηκε.</div>'; }
    if (isset($_GET['metasaved'])){ $flash = '<div class="alert alert-success">Η σύμβαση &amp; οι σημειώσεις αποθηκεύτηκαν.</div>'; }

    $uidField = $userid
        ? '<input type="hidden" name="userid" value="' . (int) $userid . '"><p class="form-control-static"><b>' . htmlspecialchars(supportcontracts_client_name($userid)) . '</b> (ID ' . (int) $userid . ')</p>'
        : '<input type="number" name="userid" class="form-control" placeholder="Client ID" required>';

    $sel = fn($a, $b) => $a == $b ? ' selected' : '';
    $chk = fn($x) => $x ? ' checked' : '';

    // ---- client search (by ΑΦΜ / name / email / ID) — shown when no client picked yet ----
    $search = '';
    if (!$userid) {
        $q = trim($_REQUEST['q'] ?? '');
        $search .= '<div class="panel panel-default"><div class="panel-body">';
        $search .= '<form method="get" action="addonmodules.php" class="form-inline">'
            . '<input type="hidden" name="module" value="supportcontracts"><input type="hidden" name="scaction" value="edit">'
            . '<div class="input-group" style="width:65%"><span class="input-group-addon"><i class="fas fa-search"></i></span>'
            . '<input type="text" name="q" class="form-control" placeholder="Αναζήτηση πελάτη: ΑΦΜ / όνομα / email / ID" value="' . htmlspecialchars($q) . '" autofocus></div> '
            . '<button class="btn btn-primary">Αναζήτηση</button></form>';
        if ($q !== '') {
            $res = supportcontracts_search_clients($q);
            if (!count($res)) {
                $search .= '<div class="text-muted" style="margin-top:10px">Κανένας πελάτης για «' . htmlspecialchars($q) . '».</div>';
            } else {
                $search .= '<table class="table table-condensed table-hover" style="margin-top:12px;margin-bottom:0"><thead><tr>'
                    . '<th>ID</th><th>Πελάτης</th><th>ΑΦΜ</th><th>Email</th><th></th></tr></thead><tbody>';
                foreach ($res as $r) {
                    $nm = trim($r->firstname . ' ' . $r->lastname);
                    if ($r->companyname) {
                        $nm = $r->companyname . ($nm ? ' (' . $nm . ')' : '');
                    }
                    $search .= '<tr><td>#' . (int) $r->id . '</td><td>' . htmlspecialchars($nm) . '</td>'
                        . '<td>' . htmlspecialchars($r->afm ?: '—') . '</td><td>' . htmlspecialchars($r->email) . '</td>'
                        . '<td><a class="btn btn-xs btn-success" href="' . $link . '&scaction=edit&userid=' . (int) $r->id . '">Επιλογή &raquo;</a></td></tr>';
                }
                $search .= '</tbody></table>';
            }
        }
        $search .= '</div></div>';
    }

    $h  = '<a href="' . $link . '" class="btn btn-default btn-xs" style="margin-bottom:12px">&larr; Πίσω στη λίστα</a>' . $flash . $search;
    $h .= '<div class="row"><div class="col-md-7"><div class="panel panel-default"><div class="panel-heading"><b>Συμβόλαιο & SLA</b></div><div class="panel-body">';
    $h .= '<form method="post" action="' . $link . '&scaction=save" class="form-horizontal">';
    $h .= '<div class="form-group"><label class="col-sm-4 control-label">Πελάτης</label><div class="col-sm-8">' . $uidField . '</div></div>';
    $h .= '<div class="form-group"><label class="col-sm-4 control-label">Ενεργό</label><div class="col-sm-8"><input type="checkbox" name="enabled" value="1"' . $chk($d['enabled']) . '></div></div>';
    $h .= '<div class="form-group"><label class="col-sm-4 control-label">Προτεραιότητα</label><div class="col-sm-8"><select name="priority" class="form-control"><option value="0"' . $sel(0, $d['priority']) . '>Κανονική</option><option value="1"' . $sel(1, $d['priority']) . '>Υψηλή</option><option value="2"' . $sel(2, $d['priority']) . '>Κρίσιμη</option></select><small class="text-muted">Επηρεάζει τη σειρά προτεραιότητας στα tickets.</small></div></div>';
    $h .= '<div class="form-group"><label class="col-sm-4 control-label">Χρόνος απόκρισης</label><div class="col-sm-8"><div class="row">'
        . '<div class="col-xs-6"><input type="number" name="sla_response_value" class="form-control" value="' . (int) $d['sla_response_value'] . '"></div>'
        . '<div class="col-xs-6"><select name="sla_response_unit" class="form-control"><option value="hours"' . $sel('hours', $d['sla_response_unit']) . '>ώρες</option><option value="days"' . $sel('days', $d['sla_response_unit']) . '>ημέρες</option></select></div>'
        . '</div><small class="text-muted">Εντός εργάσιμων ωρών.</small></div></div>';
    $h .= '<div class="form-group"><label class="col-sm-4 control-label">Εργάσιμες μέρες</label><div class="col-sm-8"><input type="text" name="biz_days" class="form-control" value="' . htmlspecialchars($d['biz_days']) . '"><small class="text-muted">1=Δευ … 7=Κυρ (π.χ. 1,2,3,4,5)</small></div></div>';
    $h .= '<div class="form-group"><label class="col-sm-4 control-label">Ωράριο</label><div class="col-sm-8"><div class="input-group"><input type="text" name="biz_start" class="form-control" value="' . htmlspecialchars($d['biz_start']) . '"><span class="input-group-addon">έως</span><input type="text" name="biz_end" class="form-control" value="' . htmlspecialchars($d['biz_end']) . '"></div></div></div>';
    $h .= '<div class="form-group"><label class="col-sm-4 control-label">Ζώνη ώρας</label><div class="col-sm-8"><input type="text" name="biz_tz" class="form-control" value="' . htmlspecialchars($d['biz_tz']) . '"></div></div>';
    $h .= '<div class="form-group"><label class="col-sm-4 control-label">Ετικέτα</label><div class="col-sm-8"><input type="text" name="label" class="form-control" value="' . htmlspecialchars($d['label']) . '" placeholder="π.χ. Ετήσιο συμβόλαιο 20ω"></div></div>';
    $h .= '<div class="form-group"><label class="col-sm-4 control-label">Email αναφορών</label><div class="col-sm-8"><input type="text" name="report_email" class="form-control" value="' . htmlspecialchars($d['report_email']) . '" placeholder="κενό = email λογαριασμού"><small class="text-muted">Παραλήπτες εβδομαδιαίας αναφοράς (χωρισμένα με κόμμα). Κενό = το email του λογαριασμού.</small></div></div>';
    if (!$c) {
        $h .= '<div class="form-group"><label class="col-sm-4 control-label">Αρχικό υπόλοιπο</label><div class="col-sm-8">'
            . '<div class="input-group"><input type="number" step="0.25" name="init_hours" class="form-control" placeholder="ώρες"><span class="input-group-addon">ω</span>'
            . '<input type="number" name="init_minutes" class="form-control" placeholder="λεπτά"><span class="input-group-addon">′</span></div>'
            . '<small class="text-muted">Για πελάτες που ήδη έχουν προαγορασμένες ώρες από παλιό σύστημα — <b>χωρίς πληρωμή</b>. Καταγράφεται ως «Αρχικοποίηση».</small></div></div>';
    }
    $h .= '<div class="form-group"><div class="col-sm-offset-4 col-sm-8"><button class="btn btn-primary">Αποθήκευση</button></div></div>';
    $h .= '</form></div></div></div>';

    // right column: balance + movements (only for an existing contract)
    $h .= '<div class="col-md-5">';
    if ($c) {
        $h .= '<div class="panel panel-info"><div class="panel-heading"><b>Υπόλοιπο χρόνου</b></div><div class="panel-body text-center"><div style="font-size:30px;font-weight:800">' . supportcontracts_fmt_minutes($d['balance_minutes']) . '</div>';
        $h .= '<a href="' . $link . '&scaction=ledger&userid=' . (int) $userid . '" class="btn btn-xs btn-default">Αναλυτικό ιστορικό</a></div></div>';
        // top-up
        $h .= '<div class="panel panel-default"><div class="panel-heading"><b>Προσθήκη χρόνου (top-up)</b></div><div class="panel-body">';
        $h .= '<form method="post" action="' . $link . '&scaction=topup"><input type="hidden" name="userid" value="' . (int) $userid . '">';
        $h .= '<div class="input-group" style="margin-bottom:8px"><input type="number" step="0.25" name="hours" class="form-control" placeholder="ώρες"><span class="input-group-addon">ω</span><input type="number" name="minutes" class="form-control" placeholder="λεπτά"><span class="input-group-addon">′</span></div>';
        $h .= '<input type="text" name="note" class="form-control" placeholder="Σημείωση (π.χ. Τιμολόγιο #123)" style="margin-bottom:8px">';
        $h .= '<button class="btn btn-success btn-block">Προσθήκη</button></form></div></div>';
        // manual adjust
        $h .= '<div class="panel panel-default"><div class="panel-heading"><b>Χειροκίνητη προσαρμογή</b></div><div class="panel-body">';
        $h .= '<form method="post" action="' . $link . '&scaction=adjust"><input type="hidden" name="userid" value="' . (int) $userid . '">';
        $h .= '<div class="input-group" style="margin-bottom:8px"><span class="input-group-btn"><select name="direction" class="form-control" style="height:34px"><option value="add">+</option><option value="sub">−</option></select></span><input type="number" step="0.25" name="hours" class="form-control" placeholder="ώρες"><span class="input-group-addon">ω</span><input type="number" name="minutes" class="form-control" placeholder="λεπτά"><span class="input-group-addon">′</span></div>';
        $h .= '<input type="text" name="note" class="form-control" placeholder="Αιτιολογία" style="margin-bottom:8px">';
        $h .= '<button class="btn btn-default btn-block">Καταχώρηση</button></form></div></div>';
        // contract file + notes + ticket-handler notes
        $fileRow = !empty($d['contract_file'])
            ? '<p style="margin:4px 0 8px"><i class="fas fa-paperclip"></i> <a href="' . $link . '&scaction=download&userid=' . (int) $userid . '" target="_blank">' . htmlspecialchars($d['contract_file']) . '</a></p>'
            : '';
        $h .= '<div class="panel panel-default"><div class="panel-heading"><b>Σύμβαση &amp; Σημειώσεις</b></div><div class="panel-body">';
        $h .= '<form method="post" enctype="multipart/form-data" action="' . $link . '&scaction=savemeta"><input type="hidden" name="userid" value="' . (int) $userid . '">';
        $h .= '<label>Συνημμένη σύμβαση</label>' . $fileRow . '<input type="file" name="contract_file" class="form-control" style="margin-bottom:12px">';
        $h .= '<label>Σημειώσεις πελάτη</label><textarea name="notes" class="form-control" rows="4" placeholder="Συμφωνίες, ιδιαιτερότητες, παρατηρήσεις…" style="margin-bottom:12px">' . htmlspecialchars((string) $d['notes']) . '</textarea>';
        $h .= '<label>Σημειώσεις για διαχειριστές ticket</label><textarea name="ticket_notes" class="form-control" rows="4" placeholder="Λεπτομέρειες που θα βλέπουν οι διαχειριστές στα tickets του πελάτη…" style="margin-bottom:12px">' . htmlspecialchars((string) $d['ticket_notes']) . '</textarea>';
        $h .= '<small class="text-muted" style="display:block;margin-bottom:12px">Οι «Σημειώσεις για διαχειριστές ticket» εμφανίζονται στην καρτέλα του ticket.</small>';
        $h .= '<label>Καλυπτόμενα προϊόντα/υπηρεσίες</label><textarea name="covered" class="form-control" rows="3" placeholder="Τι καλύπτει το συμβόλαιο — για να μην υπάρχουν παρεξηγήσεις…" style="margin-bottom:6px">' . htmlspecialchars((string) $d['covered']) . '</textarea>';
        $svcList = '';
        foreach (Capsule::table('tblhosting as h')->join('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->where('h.userid', (int) $userid)->whereIn('h.domainstatus', ['Active', 'Suspended'])
            ->limit(40)->pluck('p.name') as $nm) {
            $svcList .= htmlspecialchars($nm) . ' · ';
        }
        if ($svcList) {
            $h .= '<small class="text-muted" style="display:block;margin-bottom:12px"><b>Ενεργές υπηρεσίες πελάτη:</b> ' . rtrim($svcList, ' · ') . '</small>';
        }
        $h .= '<button class="btn btn-primary btn-block">Αποθήκευση σύμβασης &amp; σημειώσεων</button></form></div></div>';
    } else {
        $h .= '<div class="alert alert-info">Αποθήκευσε πρώτα το συμβόλαιο για να διαχειριστείς το υπόλοιπο χρόνου, τη σύμβαση και τις σημειώσεις.</div>';
    }
    $h .= '</div></div>';
    return $h;
}

function supportcontracts_render_ledger($link, $userid)
{
    $rows = Db::ledger($userid, 500);
    $h  = '<a href="' . $link . '&scaction=edit&userid=' . (int) $userid . '" class="btn btn-default btn-xs" style="margin-bottom:12px">&larr; Πίσω</a>';
    $h .= '<h4>Αναλυτικό ιστορικό — ' . htmlspecialchars(supportcontracts_client_name($userid)) . '</h4>';
    $h .= '<table class="table table-striped table-bordered"><thead><tr><th>Ημ/νία</th><th>Τύπος</th><th>Ticket</th><th class="text-right">Χρόνος</th><th class="text-right">Υπόλοιπο</th><th>Σημείωση</th></tr></thead><tbody>';
    if (!count($rows)) {
        $h .= '<tr><td colspan="6" class="text-center text-muted">Καμία κίνηση.</td></tr>';
    }
    $typeLabel = ['topup' => '<span class="label label-success">Προσθήκη</span>', 'usage' => '<span class="label label-danger">Χρήση</span>', 'adjust' => '<span class="label label-default">Προσαρμογή</span>'];
    foreach ($rows as $r) {
        $h .= '<tr><td>' . htmlspecialchars($r->created_at) . '</td><td>' . ($typeLabel[$r->type] ?? $r->type) . '</td>'
            . '<td>' . ($r->ticketid ? '#' . (int) $r->ticketid : '—') . '</td>'
            . '<td class="text-right ' . ($r->minutes < 0 ? 'text-danger' : 'text-success') . '">' . ($r->minutes > 0 ? '+' : '') . supportcontracts_fmt_minutes($r->minutes) . '</td>'
            . '<td class="text-right"><b>' . supportcontracts_fmt_minutes($r->balance_after) . '</b></td>'
            . '<td>' . htmlspecialchars((string) $r->note) . '</td></tr>';
    }
    $h .= '</tbody></table>';
    return $h;
}

/* ------------------------------------------------------------------ */
/* Client area — detailed prepaid-time consumption                    */
/* ------------------------------------------------------------------ */

function supportcontracts_clientarea($vars)
{
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        return ['pagetitle' => 'Συμβόλαιο Υποστήριξης', 'templatefile' => 'clientledger', 'vars' => ['scNoAccess' => true]];
    }
    $c = Db::contract($uid);
    $ledger = [];
    foreach (Db::ledger($uid, 300) as $r) {
        $ledger[] = [
            'date'    => $r->created_at,
            'type'    => $r->type,
            'ticketid'=> $r->ticketid,
            'minutes' => supportcontracts_fmt_minutes($r->minutes),
            'positive'=> $r->minutes >= 0,
            'balance' => supportcontracts_fmt_minutes($r->balance_after),
            'note'    => $r->note,
        ];
    }
    return [
        'pagetitle'    => 'Προαγορασμένος Χρόνος Υποστήριξης',
        'breadcrumb'   => ['index.php?m=supportcontracts' => 'Συμβόλαιο Υποστήριξης'],
        'templatefile' => 'clientledger',
        'vars'         => [
            'scHasContract' => (bool) $c,
            'scBalance'     => $c ? supportcontracts_fmt_minutes($c->balance_minutes) : '0′',
            'scBalanceLow'  => $c ? ($c->balance_minutes <= 0) : false,
            'scResponse'    => $c ? Sla::humanResponse($c) : '',
            'scHours'       => $c ? Sla::humanHours($c) : '',
            'scLedger'      => $ledger,
        ],
    ];
}
