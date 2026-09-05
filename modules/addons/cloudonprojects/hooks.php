<?php
/**
 * CloudOn Project Manager — hooks.
 *   • TicketOpen              : auto-task στο project του τμήματος (αν auto_task=on)
 *   • TicketStatusChange      : δίγλωσσο email στον πελάτη σε ουσιαστική αλλαγή status
 *   • AdminAreaViewTicketPage : panel με link στο task (ή δημιουργία task)
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudonProjects\Db;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Db.php'; // do NOT require the main addon file (double-declare fatal)
require_once __DIR__ . '/lib/Notify.php';

/** Guard: hooks never fatal before activation. */
function cloudonprojects_ready()
{
    try {
        return Capsule::schema()->hasTable('mod_cpm_tasks');
    } catch (\Throwable $e) {
        return false;
    }
}

function cloudonprojects_setting($name, $default = '')
{
    $v = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', $name)->value('value');
    return $v !== null ? $v : $default;
}

/* ---- Auto-task από νέο ticket (1.11) ---- */
add_hook('TicketOpen', 1, function ($vars) {
    if (!cloudonprojects_ready() || cloudonprojects_setting('auto_task', 'on') !== 'on') {
        return;
    }
    $ticketId = (int) ($vars['ticketid'] ?? 0);
    if ($ticketId <= 0 || Db::taskForTicket($ticketId)) {
        return;
    }
    $t = Capsule::table('tbltickets')->where('id', $ticketId)->first(['did', 'tid', 'title', 'userid', 'urgency']);
    if (!$t) {
        return;
    }
    /* Η εργασία από ticket ανήκει στο DEPARTMENT του ticket, όχι σε έργο.
       Έργο υπάρχει μόνο όταν η δουλειά είναι μέρος παράδοσης σε πελάτη. */
    $prio = ['High' => 2, 'Medium' => 1, 'Low' => 0][$t->urgency ?? ''] ?? 0;
    $taskId = Db::saveTask(0, [
        'project_id' => null,
        'dept_id'    => (int) $t->did,
        'title'      => '[#' . $t->tid . '] ' . mb_substr($t->title, 0, 180),
        'descr'      => 'Αυτόματο task από ticket #' . $t->tid,
        'status_id'  => Db::firstStatusId(),
        'priority'   => $prio,
        'ticketid'   => $ticketId,
    ], null);
    Db::logActivity($taskId, null, 'auto', 'Δημιουργήθηκε αυτόματα από ticket #' . $t->tid);
});

/* ---- Ανάθεση ticket από διαχειριστή → ανάθεση και στο task ---- */
add_hook('TicketFlagged', 1, function ($vars) {
    if (!cloudonprojects_ready()) {
        return;
    }
    $ticketId = (int) ($vars['ticketid'] ?? 0);
    $toAdmin = (int) ($vars['adminid'] ?? 0); // σε ποιον χειριστή ανατέθηκε (0 = αφαίρεση)
    $task = \WHMCS\Module\Addon\CloudonProjects\Db::taskForTicket($ticketId);
    if (!$task || (int) $task->assignee === $toAdmin) {
        return;
    }
    $db = \WHMCS\Module\Addon\CloudonProjects\Db::class;
    $db::saveTask($task->id, ['assignee' => $toAdmin ?: null], null);
    $db::logActivity($task->id, (int) ($_SESSION['adminid'] ?? 0) ?: null, 'assign',
        $toAdmin ? 'Ανάθεση από το ticket: ' . $db::adminName($toAdmin) : 'Αφαίρεση ανάθεσης από το ticket');
    if ($toAdmin) {
        \WHMCS\Module\Addon\CloudonProjects\Notify::assigned($task->id, $toAdmin, (int) ($_SESSION['adminid'] ?? 0));
    }
});

/* ---- Αλλαγή προτεραιότητας ticket → προτεραιότητα task ---- */
add_hook('TicketPriorityChange', 1, function ($vars) {
    if (!cloudonprojects_ready()) {
        return;
    }
    $ticketId = (int) ($vars['ticketid'] ?? 0);
    $task = \WHMCS\Module\Addon\CloudonProjects\Db::taskForTicket($ticketId);
    if (!$task) {
        return;
    }
    // διάβασε την τρέχουσα urgency από το ticket (ανεξάρτητα από το σχήμα των $vars)
    $urg = \WHMCS\Database\Capsule::table('tbltickets')->where('id', $ticketId)->value('urgency');
    $prio = ['High' => 2, 'Medium' => 1, 'Low' => 0][$urg] ?? 0;
    if ((int) $task->priority === $prio) {
        return;
    }
    $db = \WHMCS\Module\Addon\CloudonProjects\Db::class;
    $db::saveTask($task->id, ['priority' => $prio], null);
    $db::logActivity($task->id, (int) ($_SESSION['adminid'] ?? 0) ?: null, 'edit',
        'Προτεραιότητα από το ticket: ' . (['Κανονική', 'Υψηλή', 'Κρίσιμη'][$prio] ?? $prio));
});

/* ---- Απόκρυψη WHMCS sidebar στη σελίδα του module ΑΠΟ ΤΟ HEAD (χωρίς flash) ---- */
add_hook('AdminAreaHeadOutput', 1, function ($vars) {
    if (($_GET['module'] ?? '') !== 'cloudonprojects') {
        return '';
    }
    // CSS στο <head>: το sidebar δεν ζωγραφίζεται ΠΟΤΕ — κανένα αναβόσβημα
    return '<style>
#sidebar,.sidebar-opener{display:none !important}
#contentarea{margin-left:0 !important;padding-left:12px !important}
</style>';
});

/* ---- Καμπανάκι ειδοποιήσεων στην πάνω μπάρα (όλοι οι admins) ---- */
add_hook('AdminAreaHeaderOutput', 1, function ($vars) {
    if (!cloudonprojects_ready()) {
        return '';
    }
    $aid = (int) ($_SESSION['adminid'] ?? 0);
    if (!$aid) {
        return '';
    }
    $db = \WHMCS\Module\Addon\CloudonProjects\Db::class;
    $unread = $db::unreadCount($aid);
    $items = $db::notificationsFor($aid, 12);
    $link = 'addonmodules.php?module=cloudonprojects';
    $typeIco = ['assign' => 'fa-user-check', 'comment' => 'fa-comment', 'done' => 'fa-check-circle',
                'due' => 'fa-bell', 'recurring' => 'fa-sync-alt', 'info' => 'fa-info-circle', 'action' => 'fa-bolt'];

    $h = '<style>
#cnpBellLi{position:relative}
#cnpBellDrop{display:none;position:absolute;top:100%;right:0;z-index:1050;width:390px;max-height:480px;overflow:auto;
  background:#fff;border:1px solid #dde3ec;border-radius:0 0 12px 12px;box-shadow:0 8px 28px rgba(16,42,67,.25);font-size:13px;text-align:left}
#cnpBellDrop .hd{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #eef2f7;font-weight:700;color:#243447}
#cnpBellDrop a.it{display:flex;gap:10px;padding:9px 14px;border-bottom:1px solid #f4f6f9;color:#44566c;text-decoration:none;align-items:baseline;line-height:1.4}
#cnpBellDrop a.it:hover{background:#f4f9fd}
#cnpBellDrop a.it.unread{background:#eef7fd;font-weight:600;color:#243447}
#cnpBellDrop .tm{color:#8291a9;font-size:11px;white-space:nowrap;margin-left:auto}
#cnpBellLi .badge-container .badge{background:#d92d3a}
</style>';
    // κρυφό template — το JS το κουμπώνει ως κανονικό εικονίδιο μέσα στο ul.right-nav
    $h .= '<div id="cnpBellTpl" style="display:none"><li class="bt" id="cnpBellLi">'
        . '<a href="#" id="cnpBellBtn" title="Ειδοποιήσεις">'
        . ($unread
            ? '<div class="badge-container"><i class="fas fa-bell always"></i><span class="badge">' . ($unread > 99 ? '99+' : $unread) . '</span></div>'
            : '<i class="fas fa-bell always"></i>')
        . '<span class="visible-sidebar">Ειδοποιήσεις</span></a>';
    $h .= '<div id="cnpBellDrop"><div class="hd">Ειδοποιήσεις'
        . '<a href="' . $link . '&do=readallnotif" style="font-size:11px;font-weight:400">όλα ως διαβασμένα</a></div>';
    if (!count($items)) {
        $h .= '<div style="padding:16px;color:#8291a9;text-align:center">Καμία ειδοποίηση ακόμη.</div>';
    }
    foreach ($items as $n) {
        $ico = $typeIco[$n->type] ?? 'fa-info-circle';
        $h .= '<a class="it' . ($n->is_read ? '' : ' unread') . '" href="' . $link . '&do=gonotif&id=' . (int) $n->id . '">'
            . '<i class="fas ' . $ico . '"></i><span>' . htmlspecialchars($n->title) . '</span>'
            . '<span class="tm">' . htmlspecialchars(date('d/m H:i', strtotime($n->created_at))) . '</span></a>';
    }
    $h .= '</div></li></div>';
    $h .= '<script>(function(){
function mount(){
  var tpl=document.getElementById("cnpBellTpl"),nav=document.querySelector("ul.right-nav");
  if(!tpl)return;
  if(nav){ nav.insertBefore(tpl.firstChild,nav.firstChild); tpl.remove(); }
  else{ // fallback: διακριτικό κουμπί κάτω δεξιά
    var li=tpl.firstChild; li.style.cssText="position:fixed;bottom:18px;right:18px;z-index:1049;list-style:none;background:#2f4050;border-radius:50%;width:44px;height:44px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(0,0,0,.3)";
    var dd=li.querySelector("#cnpBellDrop"); dd.style.top="auto"; dd.style.bottom="52px"; dd.style.borderRadius="12px";
    document.body.appendChild(li); tpl.remove();
  }
  var b=document.getElementById("cnpBellBtn"),d=document.getElementById("cnpBellDrop");
  if(!b)return;
  b.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();d.style.display=d.style.display==="block"?"none":"block";});
  document.addEventListener("click",function(e){if(d&&!d.contains(e.target))d.style.display="none";});
}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",mount);else mount();
})();</script>';
    return $h;
});

/* ---- Admin ticket view: link στο task / γρήγορη δημιουργία ---- */
add_hook('AdminAreaViewTicketPage', 1, function ($vars) {
    if (!cloudonprojects_ready()) {
        return '';
    }
    $ticketId = (int) ($vars['ticketid'] ?? 0);
    if ($ticketId <= 0) {
        return '';
    }
    $link = 'addonmodules.php?module=cloudonprojects';
    $task = Db::taskForTicket($ticketId);

    if ($task) {
        $st = Db::status($task->status_id);
        $proj = Db::project($task->project_id);
        $h = '<div class="alert alert-info" style="margin-top:10px"><i class="fas fa-tasks"></i> '
            . 'Project task: <a href="' . $link . '&tab=task&id=' . (int) $task->id . '"><b>'
            . htmlspecialchars($task->title) . '</b></a>'
            . ' <span class="label" style="background:' . htmlspecialchars($st->color ?? '#888') . '">'
            . htmlspecialchars($st->title ?? '?') . '</span>'
            . ($proj ? ' <small class="text-muted">(' . htmlspecialchars($proj->name) . ')</small>' : '')
            . ($task->assignee ? ' — ' . htmlspecialchars(Db::adminName($task->assignee)) : '');
        $chatTask = $task;
        if ($task->completed_at) {
            $h .= ' <span class="label label-success"><i class="fas fa-check"></i> Ολοκληρώθηκε '
                . htmlspecialchars(date('d/m H:i', strtotime($task->completed_at))) . '</span>';
        } else {
            // Ο χειριστής δηλώνει ολοκλήρωση → task done + ενημέρωση διαχειριστών (καμπανάκι+email)
            $h .= '<form method="post" action="' . $link . '" style="display:inline;margin-left:10px">'
                . '<input type="hidden" name="do" value="ticketworkdone"><input type="hidden" name="ticketid" value="' . $ticketId . '">'
                . '<button class="btn btn-xs btn-success" onclick="return confirm(\'Δήλωση ολοκλήρωσης εργασίας; Θα ενημερωθεί ο διαχειριστής.\')">'
                . '<i class="fas fa-check"></i> Η εργασία ολοκληρώθηκε</button></form>';
        }
        $h .= '</div>';

        // Εσωτερική συνομιλία (χειριστής ↔ ομάδα/διαχειριστής) — ΔΕΝ τη βλέπει ποτέ ο πελάτης
        $h .= '<div class="panel panel-default" style="margin-top:6px"><div class="panel-heading" style="padding:6px 12px">'
            . '<b><i class="fas fa-comments"></i> Εσωτερική συνομιλία</b> <small class="text-muted">— αόρατη στον πελάτη</small></div><div class="panel-body" style="padding:8px 12px">';
        $comments = Db::comments($chatTask->id);
        $recent = array_slice($comments->all(), -5);
        if (!count($recent)) {
            $h .= '<p class="text-muted" style="margin:0 0 8px;font-size:12px">Καμία εσωτερική κουβέντα ακόμη.</p>';
        }
        $me = (int) ($_SESSION['adminid'] ?? 0);
        foreach ($recent as $c) {
            $toTxt = '';
            if ($c->to_admin !== null) {
                $toTxt = ' <span class="label label-info" style="font-weight:400">προς: '
                    . ((int) $c->to_admin === -1 ? 'Διαχειριστές' : htmlspecialchars(Db::adminName($c->to_admin))) . '</span>';
            }
            $mine = ((int) $c->admin_id === $me);
            $h .= '<div style="margin-bottom:6px;padding:6px 10px;border-radius:10px;max-width:85%;'
                . ($mine ? 'background:#e8f4fd;margin-left:auto' : 'background:#f4f6f9') . '">'
                . '<b style="font-size:11px">' . htmlspecialchars(Db::adminName($c->admin_id)) . '</b>' . $toTxt
                . ' <small class="text-muted">' . htmlspecialchars(date('d/m H:i', strtotime($c->created_at))) . '</small>'
                . '<div style="font-size:12.5px">' . nl2br(htmlspecialchars($c->comment)) . '</div></div>';
        }
        if (count($comments) > 5) {
            $h .= '<small><a href="' . $link . '&tab=task&id=' . (int) $chatTask->id . '#comments">όλη η συζήτηση (' . count($comments) . ') →</a></small>';
        }
        $h .= '<form method="post" action="' . $link . '" style="margin-top:8px;display:flex;gap:6px">'
            . '<input type="hidden" name="do" value="ticketcomment"><input type="hidden" name="ticketid" value="' . $ticketId . '">'
            . '<input type="text" name="comment" class="form-control input-sm" placeholder="Μήνυμα προς την ομάδα…" required style="flex:1">'
            . '<select name="to_admin" class="form-control input-sm" style="width:auto"><option value="">— όλους —</option><option value="-1">📣 Διαχειριστές</option>';
        foreach (Db::admins() as $a) {
            if ((int) $a->id === $me) { continue; }
            $h .= '<option value="' . (int) $a->id . '">' . htmlspecialchars(trim($a->firstname . ' ' . $a->lastname)) . '</option>';
        }
        $h .= '</select><button class="btn btn-sm btn-primary"><i class="fas fa-paper-plane"></i></button></form>';
        $h .= '</div></div>';
        return $h;
    }
    return '<div class="alert alert-default" style="margin-top:10px"><i class="fas fa-tasks"></i> '
        . 'Δεν υπάρχει task για αυτό το ticket. '
        . '<a href="' . $link . '&tab=task&id=0&ticketid=' . $ticketId . '">Δημιουργία task →</a></div>';
});

/* 🎟 Όρια tickets ανά ομάδα πελατών: καταγραφή καναλιού + ειδοποίηση υπέρβασης */
if (!function_exists('cpm_quota_track')) {
    function cpm_quota_track($ticketId, $userId, $channel)
    {
        if (!$ticketId || !$userId || !cloudonprojects_ready()) {
            return;
        }
        try {
            $C = \WHMCS\Database\Capsule::class;
            $C::table('mod_cpm_ticket_usage')->updateOrInsert(['ticketid' => (int) $ticketId],
                ['userid' => (int) $userId, 'channel' => $channel, 'created_at' => date('Y-m-d H:i:s')]);
            $pkId = (int) $C::table('mod_cpm_client_package')->where('clientid', $userId)->value('package_id');
            $q = $pkId ? $C::table('mod_cpm_support_packages')->where('id', $pkId)->first() : null;
            if (!$q || !(int) $q->t_month) {
                return;   // χωρίς όριο για την ομάδα
            }
            $m0 = date('Y-m-01 00:00:00');
            $tot = $C::table('tbltickets')->where('userid', $userId)->where('date', '>=', $m0)->count();
            $chn = $C::table('mod_cpm_ticket_usage')->where('userid', $userId)
                ->where('channel', $channel)->where('created_at', '>=', $m0)->count();
            $chQ = $channel === 'phone' ? (int) $q->phone_month : (int) $q->email_month;
            $over = [];
            if ($tot > (int) $q->t_month) {
                $over[] = "σύνολο $tot/{$q->t_month}";
            }
            if ($chQ > 0 && $chn > $chQ) {
                $over[] = ($channel === 'phone' ? 'τηλεφωνικά' : 'email') . " $chn/$chQ";
            }
            if ($over) {
                $Db = \WHMCS\Module\Addon\CloudonProjects\Db::class;
                $cl = $C::table('tblclients')->where('id', $userId)->first(['companyname', 'firstname', 'lastname']);
                $nm = $cl->companyname ?: trim($cl->firstname . ' ' . $cl->lastname);
                foreach ($Db::fullAccessAdminIds() as $fa) {
                    $Db::pushNotification($fa, 'due', '🎟 Υπέρβαση ορίου tickets: ' . $nm . ' (' . implode(' · ', $over) . ')',
                        '/project/#/inbox');
                }
                logActivity('CPM: υπέρβαση ορίου tickets πελάτη #' . $userId . ' — ' . implode(', ', $over) . ' (ticket #' . $ticketId . ')');
            }
        } catch (\Throwable $e) {
        }
    }
}
add_hook('TicketOpen', 5, function ($vars) {
    cpm_quota_track($vars['ticketid'] ?? 0, $vars['userid'] ?? 0, 'email');
});
add_hook('TicketAdminOpen', 5, function ($vars) {
    cpm_quota_track($vars['ticketid'] ?? 0, $vars['userid'] ?? 0, 'phone');
});

/* ---- Ειδοποίηση πελάτη σε αλλαγή status (δίγλωσσο EL/EN) — έξυπνο σετ ---- */
add_hook('TicketStatusChange', 1, function ($vars) {
    if (cloudonprojects_setting('ticket_status_email', 'on') !== 'on') {
        return;
    }
    $ticketId = (int) ($vars['ticketid'] ?? 0);
    if ($ticketId <= 0) {
        return;
    }
    $tk = Capsule::table('tbltickets')->where('id', $ticketId)->first(['tid', 'title', 'userid', 'name', 'email', 'c', 'status']);
    if (!$tk) {
        return;
    }
    $status = (string) ($vars['status'] ?? $tk->status);

    $sysurl = rtrim((string) Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value'), '/');
    $link = $sysurl . '/viewticket.php?tid=' . rawurlencode((string) $tk->tid) . '&c=' . rawurlencode((string) $tk->c);
    // Έξυπνο σετ + rendering: single source στο Notify (Open/Answered/Customer-Reply → null)
    $mail = \WHMCS\Module\Addon\CloudonProjects\Notify::ticketStatusEmail($tk->name, $tk->tid, $tk->title, $link, $status);
    if (!$mail) {
        return;
    }
    $subject = $mail['subject'];
    $html = $mail['html'];

    // Παραλήπτης: email λογαριασμού (εγγεγραμμένος) ή του ticket (guest)
    $to = '';
    if ((int) $tk->userid > 0) {
        $to = (string) Capsule::table('tblclients')->where('id', (int) $tk->userid)->value('email');
    }
    if ($to === '') {
        $to = (string) $tk->email;
    }
    $to = filter_var($to, FILTER_VALIDATE_EMAIL);
    if (!$to) {
        return;
    }
    // MailType=mail → σωστό base64 MIME (όπως το WHMCS εσωτερικά) ώστε τα UTF-8 & το layout να ΜΗΝ «σπάνε»
    $from = (string) (Capsule::table('tblconfiguration')->where('setting', 'Email')->value('value') ?: 'noreply@cloudon.gr');
    $headers = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . "From: CloudOn Support <" . $from . ">\r\n"
        . "Reply-To: " . $from . "\r\n";
    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', chunk_split(base64_encode($html)), $headers);
});

/* MyCloudOn: στο viewquote, όταν το quote είναι δεμένο με προσφορά PM, δίνει
   (α) κουμπί branded PDF (offer-view.php) και (β) νήμα σχολίων/ερωτήσεων. */
add_hook('ClientAreaPageViewQuote', 1, function ($vars) {
    if (!cloudonprojects_ready()) {
        return [];
    }
    $qid = (int) ($vars['quoteid'] ?? $vars['id'] ?? 0);
    if (!$qid) {
        return [];
    }
    try {
        if (!Capsule::schema()->hasTable('mod_cpm_offers')) {
            return [];
        }
        $offer = Capsule::table('mod_cpm_offers')->where('quoteid', $qid)->first(['id']);
    } catch (\Throwable $e) {
        return [];
    }
    if (!$offer) {
        return [];
    }
    // Τα σχόλια (αν υπάρχει ο πίνακας), με τον πελάτη ως «Εσείς».
    $comments = [];
    try {
        if (Capsule::schema()->hasTable('mod_cpm_offer_comments')) {
            foreach (Capsule::table('mod_cpm_offer_comments')->where('quoteid', $qid)
                         ->orderBy('id')->get(['by_type', 'body', 'created_at']) as $c) {
                $comments[] = [
                    'mine' => $c->by_type === 'client',
                    'who' => $c->by_type === 'client' ? 'Εσείς' : 'Ομάδα CloudOn',
                    'body' => nl2br(htmlspecialchars((string) $c->body, ENT_QUOTES, 'UTF-8')),
                    'at' => date('d/m/Y H:i', strtotime((string) $c->created_at)),
                ];
            }
            // Οι απαντήσεις της ομάδας θεωρούνται διαβασμένες μόλις τις δει ο πελάτης.
            Capsule::table('mod_cpm_offer_comments')->where('quoteid', $qid)
                ->where('by_type', 'admin')->whereNull('read_by_client_at')
                ->update(['read_by_client_at' => date('Y-m-d H:i:s')]);
        }
    } catch (\Throwable $e) {
    }
    return [
        'cloudonOfferPdf' => 'offer-view.php?q=' . $qid,
        'cloudonOfferQuoteId' => $qid,
        'cloudonOfferComments' => $comments,
        'cloudonOfferCommentPost' => 'offer-comment.php',
    ];
});
