<?php
/**
 * Support Contracts & SLA — hooks.
 *   • AdminAreaViewTicketPage : SLA/balance panel + billable toggle + log-time form
 *   • TicketOpen              : compute & store the SLA first-response deadline
 *   • TicketAdminReply        : stamp first response + SLA met/breached
 *   • ClientAreaPage(home)    : expose prepaid balance + SLA to the dashboard
 *
 * @package WHMCS\Module\Addon\SupportContracts
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\SupportContracts\Db;
use WHMCS\Module\Addon\SupportContracts\Sla;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Db.php';
require_once __DIR__ . '/lib/Sla.php';
require_once __DIR__ . '/lib/Format.php'; // supportcontracts_fmt_minutes() — do NOT require the main addon file (double-declare)

/** Small guard so hooks never fatal before the module is activated. */
function supportcontracts_ready()
{
    try {
        return Capsule::schema()->hasTable('mod_supportcontracts_clients');
    } catch (\Throwable $e) {
        return false;
    }
}

/* ---- Admin ticket view: SLA + prepaid balance + billable/log-time ---- */
add_hook('AdminAreaViewTicketPage', 1, function ($vars) {
    if (!supportcontracts_ready()) {
        return '';
    }
    $tid = (int) ($vars['ticketid'] ?? 0);
    if ($tid <= 0) {
        return '';
    }
    $ticket = Capsule::table('tbltickets')->where('id', $tid)->first(['userid', 'tid', 'date']);
    if (!$ticket) {
        return '';
    }
    $uid = (int) $ticket->userid;
    $c   = $uid ? Db::contract($uid) : null;
    $st  = Db::ticket($tid);
    $addonLink = 'addonmodules.php?module=supportcontracts';

    if (!$c) {
        return '<div class="alert alert-default" style="margin-top:10px"><i class="fas fa-file-contract"></i> '
            . 'Ο πελάτης δεν έχει συμβόλαιο υποστήριξης. '
            . '<a href="' . $addonLink . '&scaction=edit' . ($uid ? '&userid=' . $uid : '') . '">Δημιουργία</a></div>';
    }

    // SLA due (compute on the fly if not stored)
    $due = $st && $st->sla_due ? $st->sla_due : null;
    if (!$due) {
        $d = Sla::dueDate($ticket->date, $c);
        $due = $d ? $d->format('Y-m-d H:i') : null;
    }
    $prio = [0 => 'Κανονική', 1 => 'Υψηλή', 2 => 'Κρίσιμη'][$c->priority] ?? 'Κανονική';
    $prioCls = [0 => 'default', 1 => 'warning', 2 => 'danger'][$c->priority] ?? 'default';
    $balCls  = $c->balance_minutes <= 0 ? 'danger' : ($c->balance_minutes < 120 ? 'warning' : 'success');

    $breached = $due && strtotime($due) < time() && (!$st || !$st->first_response_at);

    $h  = '<div class="panel panel-' . ($breached ? 'danger' : 'info') . '" style="margin-top:12px"><div class="panel-heading"><b><i class="fas fa-headset"></i> Συμβόλαιο Υποστήριξης & SLA</b></div><div class="panel-body">';
    $h .= '<div class="row">';
    $h .= '<div class="col-sm-3"><small class="text-muted">Υπόλοιπο χρόνου</small><br><span class="label label-' . $balCls . '" style="font-size:14px">' . supportcontracts_fmt_minutes($c->balance_minutes) . '</span></div>';
    $h .= '<div class="col-sm-3"><small class="text-muted">Προτεραιότητα</small><br><span class="label label-' . $prioCls . '">' . $prio . '</span></div>';
    $h .= '<div class="col-sm-3"><small class="text-muted">SLA απόκρισης</small><br>' . htmlspecialchars(Sla::humanResponse($c)) . '</div>';
    $h .= '<div class="col-sm-3"><small class="text-muted">Προθεσμία απόκρισης</small><br>' . ($due ? '<b class="' . ($breached ? 'text-danger' : '') . '">' . htmlspecialchars($due) . '</b>' . ($breached ? ' <span class="label label-danger">Εκπρόθεσμο</span>' : '') : '—') . '</div>';
    $h .= '</div>';

    // details for ticket handlers (from the contract)
    if (!empty($c->ticket_notes)) {
        $h .= '<div class="alert alert-warning" style="margin:12px 0"><b><i class="fas fa-info-circle"></i> Σημειώσεις για διαχειριστές:</b><br>'
            . nl2br(htmlspecialchars($c->ticket_notes)) . '</div>';
    } else {
        $h .= '<hr style="margin:12px 0">';
    }
    if (!empty($c->covered)) {
        $h .= '<div class="alert alert-info" style="margin:0 0 12px"><b><i class="fas fa-check-circle"></i> Τι καλύπτει το συμβόλαιο:</b> '
            . nl2br(htmlspecialchars($c->covered)) . '</div>';
    }

    // billing step + minimum charge from module config
    $chargeStep = (int) (Capsule::table('tbladdonmodules')->where('module', 'supportcontracts')
        ->where('setting', 'charge_step_minutes')->value('value') ?: 15);
    $minChargeH = (float) (Capsule::table('tbladdonmodules')->where('module', 'supportcontracts')
        ->where('setting', 'min_charge_hours')->value('value') ?: 0);
    $chargeInfo = 'Χρέωση ανά ' . $chargeStep . '΄ (στρογγυλοποίηση προς τα πάνω)'
        . ($minChargeH > 0 ? ', ελάχ. ' . rtrim(rtrim(number_format($minChargeH, 2), '0'), '.') . 'ω' : '');

    // billable + log-time form
    $logged = $st ? (int) $st->minutes_logged : 0;
    $billable = $st ? (int) $st->billable : 0;
    $h .= '<form method="post" action="' . $addonLink . '&scaction=logtime" class="form-inline">';
    $h .= '<input type="hidden" name="ticketid" value="' . $tid . '"><input type="hidden" name="whmcs_tid" value="' . (int) $ticket->tid . '">';
    $h .= '<label style="margin-right:8px"><input type="radio" name="billable_mode" value="1" checked> <b>Χρεώσιμο</b></label>';
    $h .= '<label style="margin-right:12px"><input type="radio" name="billable_mode" value="0"> Χωρίς χρέωση</label> ';
    $h .= 'Χρόνος διεκπεραίωσης: <input type="number" step="0.25" name="hours" class="form-control input-sm" style="width:70px" placeholder="ώρες"> ';
    $h .= '<input type="number" name="minutes" class="form-control input-sm" style="width:70px" placeholder="λεπτά"> ';
    $h .= '<button class="btn btn-sm btn-primary">Καταχώρηση & αφαίρεση</button>';
    if ($logged > 0) {
        $h .= ' <span class="text-muted" style="margin-left:8px">Καταχωρημένα: <b>' . supportcontracts_fmt_minutes($logged) . '</b></span>';
    }
    $h .= '<br><small class="text-muted">Με την καταχώρηση, το ticket σημαίνεται ως χρεώσιμο και ο χρόνος αφαιρείται αυτόματα από το υπόλοιπο του πελάτη. '
        . '<b>' . htmlspecialchars($chargeInfo) . '.</b></small>';
    $h .= '</form>';
    $h .= '</div></div>';
    return $h;
});

/* ---- Ticket open: store the SLA first-response deadline ---- */
add_hook('TicketOpen', 1, function ($vars) {
    if (!supportcontracts_ready()) {
        return;
    }
    $tid = (int) ($vars['ticketid'] ?? 0);
    if ($tid <= 0) {
        return;
    }
    $t = Capsule::table('tbltickets')->where('id', $tid)->first(['userid', 'date']);
    if (!$t || !$t->userid) {
        return;
    }
    $c = Db::contract((int) $t->userid);
    if (!$c || !$c->enabled) {
        return;
    }
    $due = Sla::dueDate($t->date, $c);
    Db::saveTicket($tid, [
        'userid'  => (int) $t->userid,
        'sla_due' => $due ? $due->format('Y-m-d H:i:s') : null,
    ]);
});

/* ---- First staff reply: stamp response time + SLA met/breached ---- */
add_hook('TicketAdminReply', 1, function ($vars) {
    if (!supportcontracts_ready()) {
        return;
    }
    $tid = (int) ($vars['ticketid'] ?? 0);
    if ($tid <= 0) {
        return;
    }
    $st = Db::ticket($tid);
    if ($st && $st->first_response_at) {
        return; // already stamped
    }
    $due = $st ? $st->sla_due : null;
    $met = $due ? (time() <= strtotime($due) ? 1 : 0) : null;
    Db::saveTicket($tid, [
        'userid'            => (int) ($vars['userid'] ?? ($st->userid ?? 0)),
        'first_response_at' => date('Y-m-d H:i:s'),
        'sla_met'           => $met,
    ]);
});

/* ---- Client dashboard: expose prepaid balance + SLA ---- */
add_hook('ClientAreaPage', 1, function ($vars) {
    if (($vars['templatefile'] ?? '') !== 'clientareahome' || !supportcontracts_ready()) {
        return [];
    }
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        return [];
    }
    $c = Db::contract($uid);
    if (!$c || !$c->enabled) {
        return [];
    }
    return [
        'scHomeContract'  => true,
        'scHomeBalance'   => supportcontracts_fmt_minutes($c->balance_minutes),
        'scHomeMinutes'   => (int) $c->balance_minutes,
        'scHomeLow'       => $c->balance_minutes <= 0 ? 'empty' : ($c->balance_minutes < 120 ? 'low' : 'ok'),
        'scHomePriority'  => (int) $c->priority,
        'scHomeResponse'  => Sla::humanResponse($c),
        'scHomeHours'     => Sla::humanHours($c),
    ];
});

/* ---- Auto top-up prepaid hours when a "προαγορά" product invoice is paid ---- */
add_hook('InvoicePaid', 1, function ($vars) {
    if (!supportcontracts_ready()) {
        return;
    }
    $invoiceid = (int) ($vars['invoiceid'] ?? 0);
    if ($invoiceid <= 0) {
        return;
    }
    $mapJson = Capsule::table('tbladdonmodules')->where('module', 'supportcontracts')
        ->where('setting', 'prepaid_hours_map')->value('value');
    $map = json_decode((string) $mapJson, true);
    if (!is_array($map) || !$map) {
        return; // no prepaid products configured
    }
    $hoursByProduct = [];
    foreach ($map as $pid => $hrs) {
        $hoursByProduct[(int) $pid] = (float) $hrs;
    }
    $defaults = supportcontracts_defaults();

    // Each product/service line on the paid invoice.
    $items = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceid)->get(['id', 'type', 'relid']);
    foreach ($items as $it) {
        if (strtolower((string) $it->type) !== 'hosting') {
            continue; // only product/service order lines
        }
        $svc = Capsule::table('tblhosting')->where('id', (int) $it->relid)->first(['userid', 'packageid']);
        if (!$svc || !isset($hoursByProduct[(int) $svc->packageid])) {
            continue;
        }
        $mins = (int) round($hoursByProduct[(int) $svc->packageid] * 60);
        if ($mins <= 0) {
            continue;
        }
        $ref = 'inv:' . $invoiceid . ':item:' . (int) $it->id;
        if (Db::refExists($ref)) {
            continue; // already credited — idempotent
        }
        $uid = (int) $svc->userid;
        Db::ensureContract($uid, $defaults); // auto-create a contract if the client has none
        Db::applyMovement($uid, $mins, 'topup', 'Αγορά προαγοράς ωρών – Τιμολόγιο #' . $invoiceid, null, null, $ref);
    }
});
