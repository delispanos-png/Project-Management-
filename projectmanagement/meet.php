<?php
/**
 * 🎥 CloudOn Meet — WebRTC τηλεδιάσκεψη (ομάδα + πελάτες-guests).
 * Pre-join με επιλογή κάμερας/μικροφώνου, mesh P2P, διαμοιρασμός οθόνης.
 * Auth: session πάνελ (ομάδα) Ή room token ?t= (guests).
 */
require __DIR__ . '/boot.php';

$room = preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['room'] ?? '');
$tok = $_GET['t'] ?? '';
$adminId = pm_admin_id();
$isGuest = $adminId <= 0;
if ($room === '' || ($isGuest && pm_verify_meet($tok) !== $room)) {
    http_response_code(403);
    echo '<meta charset="utf-8"><body style="font-family:sans-serif;text-align:center;padding:60px"><h2>🔒 Μη έγκυρος σύνδεσμος meeting</h2></body>';
    exit;
}
$isRemote = strpos($room, 'r') === 0;   // δωμάτια remote υποστήριξης (r…) vs meetings (m…)
/* Δωμάτιο δεμένο με γεγονός ημερολογίου: έχει ώρα λήξης. Μετά τη λήξη δεν ξανανοίγει. */
$win = pm_meet_window($room);
if ($win && $win['ended']) {
    $backE = $adminId > 0 ? '/project/#/calendar' : '';
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CloudOn Meet</title>'
        . '<body style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;text-align:center;padding:70px 20px;background:#0f172a;color:#e5eaf1">'
        . '<div style="font-size:56px">⏰</div><h2 style="margin:10px 0 6px">Το meeting ολοκληρώθηκε</h2>'
        . '<p style="color:#8595ac;max-width:520px;margin:0 auto 22px;line-height:1.5">«' . htmlspecialchars($win['title']) . '» είχε ώρα λήξης '
        . date('H:i', $win['endTs']) . ' (' . date('d/m', $win['endTs']) . '). Το δωμάτιο έκλεισε.<br>'
        . ($adminId > 0 ? 'Για συνέχεια, φτιάξε <b>νέο meeting</b> από το ημερολόγιο — θα έχει νέο σύνδεσμο.' : 'Αν χρειάζεται συνέχεια, ο διοργανωτής θα σας στείλει νέο σύνδεσμο.') . '</p>'
        . ($backE ? '<a href="' . $backE . '" style="display:inline-block;background:#0090dd;color:#fff;padding:11px 20px;border-radius:10px;text-decoration:none;font-weight:700">Επιστροφή στο ημερολόγιο</a>' : '')
        . '</body>';
    exit;
}
// Έξοδος/επιστροφή στην εφαρμογή — μόνο για την ομάδα (οι guests δεν έχουν πάνελ).
// Σε PWA/standalone δεν υπάρχει back του browser, άρα ΠΡΕΠΕΙ να υπάρχει ρητό κουμπί.
$backUrl = $isGuest ? '' : ($isRemote ? '/project/#/inbox' : '/project/#/calendar');
$backLabel = $isRemote ? 'Επιστροφή στα tickets' : 'Επιστροφή στο ημερολόγιο';
$myName = '';
$team = [];
if (!$isGuest) {
    require_once __DIR__ . '/../init.php';
    $a = \WHMCS\Database\Capsule::table('tbladmins')->where('id', $adminId)->first(['firstname', 'lastname']);
    $myName = trim(($a->firstname ?? '') . ' ' . ($a->lastname ?? ''));
    foreach (\WHMCS\Database\Capsule::table('tbladmins')->where('disabled', 0)
        ->where('id', '!=', $adminId)->get(['id', 'firstname', 'lastname']) as $t) {
        $team[] = ['id' => (int) $t->id, 'name' => trim($t->firstname . ' ' . $t->lastname)];
    }
}
/* ICE (STUN/TURN) από ρυθμίσεις του addon — ο TURN κουμπώνει χωρίς αλλαγή κώδικα.
   Χωρίς TURN, δουλεύει σε LAN/ευνοϊκά δίκτυα· με TURN, από παντού (σπίτι/κινητό). */
$cfgv = function ($k, $d = '') {
    $v = \WHMCS\Database\Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', $k)->value('value');
    return $v === null ? $d : trim((string) $v);
};
$iceServers = [];
$stun = $cfgv('ice_stun');
$stunList = $stun !== '' ? preg_split('/[\s,]+/', $stun) : ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'];
foreach ($stunList as $su) { if ($su) { $iceServers[] = ['urls' => $su]; } }
$turn = $cfgv('ice_turn');
if ($turn !== '') {
    $ts = ['urls' => array_values(array_filter(preg_split('/[\s,]+/', $turn)))];
    $tu = $cfgv('ice_turn_user');
    if ($tu !== '') { $ts['username'] = $tu; $ts['credential'] = $cfgv('ice_turn_pass'); }
    $iceServers[] = $ts;
}
$iceJson = json_encode(['iceServers' => $iceServers]);
?><!DOCTYPE html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CloudOn Meet</title>
<style>
:root{--bg:#0e1626;--card:#182136;--ink:#e8eef7;--mut:#8494ab;--brand:#0090dd;--ok:#2dbd6e;--bad:#e2515f;--line:#2a3650}
*{box-sizing:border-box;margin:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--ink);height:100vh;display:flex;flex-direction:column}
.btn{border:0;border-radius:12px;padding:11px 20px;font-size:14px;font-weight:700;cursor:pointer;background:var(--card);color:var(--ink)}
.btn-p{background:var(--brand);color:#fff}
.btn:hover{filter:brightness(1.15)}
.inp{background:var(--card);border:1px solid var(--line);border-radius:10px;color:var(--ink);padding:10px 13px;font-size:14px;width:100%}
/* pre-join */
#pre{flex:1;display:flex;align-items:center;justify-content:center;padding:20px}
.pre-box{width:min(920px,100%);display:flex;gap:26px;flex-wrap:wrap;align-items:center;justify-content:center}
.pre-vid{position:relative;width:min(520px,92vw);aspect-ratio:16/10;background:#000;border-radius:18px;overflow:hidden;box-shadow:0 16px 50px rgba(0,0,0,.5)}
.pre-vid video{width:100%;height:100%;object-fit:cover;transform:scaleX(-1)}
.pre-vid video.nomirror{transform:none}
.pre-vid .off{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:60px;background:#141c2e}
.pre-ctl{position:absolute;bottom:12px;left:0;right:0;display:flex;gap:10px;justify-content:center}
.rbtn{width:48px;height:48px;border-radius:50%;border:0;font-size:19px;cursor:pointer;background:#ffffff22;color:#fff;backdrop-filter:blur(6px)}
.rbtn.off{background:var(--bad)}
.pre-form{width:300px;display:flex;flex-direction:column;gap:11px}
.pre-form label{font-size:11px;color:var(--mut);text-transform:uppercase;letter-spacing:.6px;font-weight:700}
h1{font-size:21px;letter-spacing:-.3px}
h1 b{color:var(--brand)}
/* call */
#call{flex:1;display:none;flex-direction:column;min-height:0}
#grid{flex:1;min-height:0;display:grid;gap:10px;padding:12px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));grid-auto-rows:1fr}
.tile{position:relative;background:#000;border-radius:14px;overflow:hidden;min-height:0}
.tile video{width:100%;height:100%;object-fit:cover}
/* ΟΤΑΝ ΚΑΠΟΙΟΣ ΜΟΙΡΑΖΕΤΑΙ ΟΘΟΝΗ, Η ΟΘΟΝΗ ΕΙΝΑΙ ΤΟ ΘΕΜΑ.
   Το κοινόχρηστο βίντεο αντικαθιστά την κάμερα στο ΙΔΙΟ κομμάτι, οπότε εμφανιζόταν
   σε ένα κελί 300px — δηλαδή αδιάβαστο. Εδώ η οθόνη πιάνει όλο τον χώρο και τα
   πρόσωπα μαζεύονται σε μια λωρίδα από κάτω. Και `contain` αντί για `cover`:
   μια οθόνη δεν κόβεται στις άκρες, εκεί είναι τα μενού. */
#grid.presenting{grid-template-columns:1fr;grid-auto-rows:1fr}
/* ΔΥΟ+ ΟΘΟΝΕΣ ΤΑΥΤΟΧΡΟΝΑ (30/9/2026, όπως στο Discord): δίπλα-δίπλα· σε κινητό η μία κάτω από την άλλη. */
#grid.presenting.multi{grid-template-columns:repeat(2,1fr)}
@media (max-width:900px){ #grid.presenting.multi{grid-template-columns:1fr} }
#grid.presenting .tile.present video{object-fit:contain;background:#000}
#grid.presenting .tile:not(.present){display:none}
#strip{display:none;gap:8px;padding:0 12px 12px;overflow-x:auto;flex:none}
#grid.presenting ~ #strip{display:flex}
#strip .tile{width:168px;height:96px;flex:none}
@media (max-width:640px){ #strip .tile{width:118px;height:68px} }
.tile.me video.mirror{transform:scaleX(-1)}
.tile .nm{position:absolute;left:10px;bottom:8px;background:#0009;padding:3px 10px;border-radius:8px;font-size:12px;font-weight:700}
#bar{display:flex;gap:12px;justify-content:center;padding:14px;background:var(--card)}
#bar .rbtn{width:54px;height:54px}
#bar .leave{background:var(--bad)}
#topbar{display:flex;align-items:center;gap:10px;padding:10px 16px;font-size:13px;color:var(--mut)}
#topbar b{color:var(--ink)}
/* έξοδος/επιστροφή στην εφαρμογή */
.meet-back{display:inline-flex;align-items:center;gap:7px;background:var(--card);color:var(--ink);
  border:1px solid var(--line);border-radius:11px;padding:9px 14px;font-size:13px;font-weight:700;
  cursor:pointer;text-decoration:none;flex:none;min-height:42px}
.meet-back:hover{filter:brightness(1.2)}
.meet-back:active{transform:scale(.96)}
.meet-back svg{width:17px;height:17px}
#topbar .meet-back{padding:7px 12px;min-height:38px;font-size:12.5px}
@media (max-width:768px){
  #topbar{padding:8px 10px;gap:8px;flex-wrap:wrap}
  #topbar .meet-back span{display:none}   /* σε κινητό μόνο το βέλος */
  #bar{gap:8px;padding:12px 8px calc(12px + env(safe-area-inset-bottom,0px))}
  #bar .rbtn{width:48px;height:48px}
}
.toast{position:fixed;top:14px;left:50%;transform:translateX(-50%);background:var(--card);padding:10px 18px;border-radius:12px;font-size:13px;box-shadow:0 8px 24px rgba(0,0,0,.4);z-index:9}
.bgb.on{background:var(--brand);color:#fff}
/* ✋ Σηκωμένο χέρι: σήμα στο πλακίδιο + ουρά με σειρά, πάνω από το πλέγμα */
.tile .hand{position:absolute;top:8px;left:8px;background:#f5b400;color:#1a1200;font-weight:800;font-size:13px;
  padding:4px 10px;border-radius:999px;box-shadow:0 4px 14px rgba(0,0,0,.4);display:none;align-items:center;gap:4px;border:0}
.tile.raised .hand{display:inline-flex}
.tile.raised{box-shadow:0 0 0 3px #f5b400 inset}
button.hand{cursor:pointer}
#handQ{display:none;align-items:center;gap:8px;flex-wrap:wrap;margin:0 12px;padding:8px 12px;border-radius:12px;
  background:#f5b40022;border:1px solid #f5b40066;font-size:13px}
#handQ.on{display:flex}
#handQ b{color:#f5b400}
#cHand.on{background:#f5b400;color:#1a1200}
/* 💬 Chat δωματίου — πλαϊνό πάνελ */
#chatPane{position:fixed;right:12px;top:58px;bottom:96px;width:340px;max-width:calc(100vw - 24px);z-index:8;
  background:var(--card);border-radius:14px;box-shadow:0 12px 36px rgba(0,0,0,.45);display:none;flex-direction:column;overflow:hidden}
#chatPane.on{display:flex}
#chatPane .ch-h{display:flex;align-items:center;gap:8px;padding:11px 14px;border-bottom:1px solid var(--line);font-weight:800}
#chatPane .ch-h button{margin-left:auto;background:none;border:0;color:var(--mut);font-size:18px;cursor:pointer}
#chatList{flex:1;overflow-y:auto;padding:10px 12px;display:flex;flex-direction:column;gap:8px}
.cmsg{max-width:88%;align-self:flex-start;background:#ffffff12;border-radius:12px;padding:7px 10px;font-size:13.5px;line-height:1.4;word-wrap:break-word;white-space:pre-wrap}
.cmsg.mine{align-self:flex-end;background:var(--brand);color:#fff}
.cmsg .cm-h{font-size:11px;font-weight:700;opacity:.75;margin-bottom:2px;white-space:normal}
.cmsg a{color:inherit;text-decoration:underline}
.ch-empty{color:var(--mut);font-size:12.5px;text-align:center;margin:auto 0}
#chatForm{display:flex;gap:7px;padding:10px;border-top:1px solid var(--line)}
#chatIn{flex:1;resize:none;min-height:38px;max-height:120px;font-size:13.5px;padding:8px 11px;font-family:inherit}
/* Ανοιχτό chat σε υπολογιστή: τα πλακίδια κάνουν χώρο αντί να κρύβονται από κάτω. */
@media (min-width:900px){ body.chat-on #grid, body.chat-on #strip, body.chat-on #handQ{margin-right:352px} }
#chatForm button{border:0;border-radius:10px;background:var(--brand);color:#fff;font-weight:700;padding:0 14px;cursor:pointer}
#cChat{position:relative}
#cChat .bdg{position:absolute;top:-3px;right:-3px;min-width:19px;height:19px;border-radius:10px;background:var(--bad);color:#fff;
  font-size:11px;font-weight:800;display:none;align-items:center;justify-content:center;padding:0 5px}
#cChat.unread .bdg{display:flex}
#cChat.on{background:var(--brand)}
@media (max-width:640px){ #chatPane{left:8px;right:8px;width:auto;top:50px;bottom:84px} }
</style>
</head>
<body>
<div id="pre">
  <div class="pre-box">
    <div class="pre-vid">
      <video id="pv" autoplay muted playsinline></video>
      <div class="off" id="pvOff" style="display:none"></div>
      <div class="pre-ctl">
        <button class="rbtn" id="pMic" title="Μικρόφωνο"></button>
        <button class="rbtn" id="pCam" title="Κάμερα"></button>
      </div>
    </div>
    <div class="pre-form">
      <?php if ($backUrl): ?>
      <a class="meet-back" href="<?= htmlspecialchars($backUrl) ?>" style="align-self:flex-start">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        <span><?= htmlspecialchars($backLabel) ?></span></a>
      <?php endif; ?>
      <h1><b>CloudOn</b> <?= $isRemote ? 'Remote' : 'Meet' ?></h1>
      <?php if ($isRemote && $isGuest): ?>
      <div style="font-size:12.5px;color:var(--mut);line-height:1.6;background:var(--card);border-radius:10px;padding:10px 13px">
        🖥 Ο τεχνικός της CloudOn θα δει την οθόνη σας για να σας βοηθήσει.<br>
        Πατήστε «Έναρξη» και επιλέξτε ποια οθόνη θα μοιραστείτε.</div>
      <?php endif; ?>
      <div id="nameWrap" style="display:<?= $isGuest ? 'block' : 'none' ?>">
        <label>Το όνομά σας</label>
        <input class="inp" id="myName" placeholder="π.χ. Γιώργος Π." value="<?= htmlspecialchars($myName) ?>">
      </div>
      <div><label>🎤 Μικρόφωνο</label><select class="inp" id="selMic"></select></div>
      <div><label>📷 Κάμερα</label><select class="inp" id="selCam"></select></div>
      <div><label>Φόντο</label>
        <div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:4px">
          <button class="btn bgb on" data-bg="none" style="padding:8px 13px;font-size:12.5px">Κανονικό</button>
          <button class="btn bgb" data-bg="blur" style="padding:8px 13px;font-size:12.5px">✨ Θόλωμα</button>
          <button class="btn bgb" data-bg="brand" style="padding:8px 13px;font-size:12.5px">🏢 CloudOn</button>
          <label class="btn bgb" data-bg="image" style="padding:8px 13px;font-size:12.5px;cursor:pointer">🖼 Δική σου εικόνα<input type="file" id="bgFile" accept="image/*" style="display:none"></label>
        </div></div>
      <button class="btn btn-p" id="joinBtn" style="font-size:16px;padding:14px">Συμμετοχή στο meeting</button>
      <div style="font-size:11.5px;color:var(--mut)">Δωμάτιο: <?= htmlspecialchars($room) ?> · Η κλήση γίνεται απευθείας μεταξύ των συμμετεχόντων (P2P, κρυπτογραφημένη)</div>
    </div>
  </div>
</div>

<div id="call">
  <div id="topbar">
    <?php if ($backUrl): ?>
    <button class="meet-back" id="cExit" title="<?= htmlspecialchars($backLabel) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      <span><?= htmlspecialchars($backLabel) ?></span></button>
    <?php endif; ?>
    <b style="color:var(--brand)">●</b> <b>CloudOn <?= $isRemote ? 'Remote Υποστήριξη' : 'Meet' ?></b> · δωμάτιο <?= htmlspecialchars($room) ?> · <span id="cnt"></span><?= $win ? ' · <span id="endAt" title="Το meeting κλείνει αυτόματα στη λήξη">λήγει ' . date('H:i', $win['endTs']) . '</span>' : '' ?></div>
  <div id="endBanner" style="display:none;position:fixed;top:56px;left:50%;transform:translateX(-50%);z-index:9;background:#eba63c;color:#1a1200;font-weight:800;padding:10px 18px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.35);font-size:15px"></div>
  <div id="handQ" role="status" aria-live="polite"></div>
  <div id="chatPane" role="complementary" aria-label="Chat συνάντησης">
    <div class="ch-h">💬 Chat συνάντησης <button type="button" id="chatX" title="Κλείσιμο" aria-label="Κλείσιμο chat">×</button></div>
    <div id="chatList"><div class="ch-empty" id="chatEmpty">Γράψε κάτι στην ομάδα — το βλέπουν όλοι όσοι είναι στη συνάντηση, και όσοι μπουν αργότερα.</div></div>
    <form id="chatForm" autocomplete="off"><textarea class="inp" id="chatIn" rows="1" maxlength="2000" placeholder="Μήνυμα… (Enter στέλνει)"></textarea><button type="submit">Αποστολή</button></form>
  </div>
  <div id="grid"></div>
  <div id="strip"></div>
  <div id="bar">
    <button class="rbtn" id="cMic" title="Μικρόφωνο"></button>
    <button class="rbtn" id="cCam" title="Κάμερα"></button>
    <button class="rbtn" id="cShare" title="Διαμοιρασμός οθόνης"></button>
    <button class="rbtn" id="cHand" title="Σήκωσε χέρι για να πάρεις τον λόγο" aria-pressed="false">✋</button>
    <button class="rbtn" id="cChat" title="Chat συνάντησης" aria-pressed="false">💬<span class="bdg" id="chatBdg"></span></button>
    <button class="rbtn" id="cBg" title="Φόντο (κανονικό/θόλωμα/εικόνα)">✨</button>
    <button class="rbtn" id="cInv" title="Πρόσκληση συμμετέχοντα"></button>
    <button class="rbtn leave" id="cLeave" title="Αποχώρηση"></button>
  </div>
</div>

<div id="invModal" style="display:none;position:fixed;inset:0;background:#000a;z-index:20;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="width:min(430px,92vw);background:var(--card);border-radius:18px;padding:24px 22px;display:flex;flex-direction:column;gap:13px">
    <h2 style="font-size:17px">Πρόσκληση στο meeting</h2>
    <div>
      <label style="font-size:11px;color:var(--mut);text-transform:uppercase;letter-spacing:.6px;font-weight:700">Σύνδεσμος</label>
      <div style="display:flex;gap:7px;margin-top:5px">
        <input class="inp" id="invUrl" readonly>
        <button class="btn" id="invCopy" style="white-space:nowrap">📋 Αντιγραφή</button></div>
    </div>
    <?php if (!$isGuest): ?>
    <div>
      <label style="font-size:11px;color:var(--mut);text-transform:uppercase;letter-spacing:.6px;font-weight:700">Κάλεσε συνάδελφο (ειδοποίηση + email τώρα)</label>
      <div style="display:flex;gap:7px;margin-top:5px">
        <select class="inp" id="invAdm">
          <?php foreach ($team as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-p" id="invAdmGo" style="white-space:nowrap">Κάλεσε</button></div>
    </div>
    <div>
      <label style="font-size:11px;color:var(--mut);text-transform:uppercase;letter-spacing:.6px;font-weight:700">Ή στείλε πρόσκληση σε email</label>
      <div style="display:flex;gap:7px;margin-top:5px">
        <input class="inp" id="invEm" placeholder="onoma@example.gr">
        <button class="btn btn-p" id="invEmGo" style="white-space:nowrap">Αποστολή</button></div>
    </div>
    <?php endif; ?>
    <button class="btn" onclick="document.getElementById('invModal').style.display='none'">Κλείσιμο</button>
  </div>
</div>
<script src="mediapipe/selfie_segmentation.js"></script>
<script>
'use strict';
const ROOM = <?= json_encode($room) ?>;
const MT = <?= json_encode($isGuest ? $tok : '') ?>;
const IS_GUEST = <?= $isGuest ? 'true' : 'false' ?>;
const IS_REMOTE = <?= $isRemote ? 'true' : 'false' ?>;
const API = 'api.php';
const ICE = <?= $iceJson ?>;
const MEET_END = <?= $win ? (int) $win['endTs'] * 1000 : 0 ?>;      // ms, ώρα server
const MEET_TITLE = <?= json_encode($win ? $win['title'] : '') ?>;
const SERVER_NOW = <?= time() * 1000 ?>;
const $ = s => document.querySelector(s);

/* Σύγχρονα line icons (Feather-style) */
const ICO = {
  mic: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>',
  micOff: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>',
  cam: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>',
  camOff: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16 16v1a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2m5.66 0H14a2 2 0 0 1 2 2v3.34l1 1L23 7v10"/></svg>',
  share: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/><path d="M9 10l3-3 3 3M12 7v6" stroke-width="1.8"/></svg>',
  leave: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-3.33-2.67m-2.67-3.34a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91"/><line x1="23" y1="1" x2="1" y2="23"/></svg>',
  camBig: '<svg width="54" height="54" viewBox="0 0 24 24" fill="none" stroke="#5b6b85" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16 16v1a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2m5.66 0H14a2 2 0 0 1 2 2v3.34l1 1L23 7v10"/></svg>'
};

/* ΧΡΟΝΟΔΙΑΚΟΠΤΗΣ ΠΟΥ ΔΕΝ «ΚΟΙΜΑΤΑΙ» (30/9/2026).
   Όταν ο χρήστης άλλαζε παράθυρο, ο browser πάγωνε το requestAnimationFrame και
   έκοβε τα setInterval της σελίδας (έως 1/λεπτό μετά από 5΄). Αποτέλεσμα: με
   θόλωμα/φόντο η κάμερά του ΠΑΓΩΝΕ για όλους τους άλλους, και η σηματοδοσία
   καθυστερούσε. Οι timers ενός Worker δεν περιορίζονται έτσι — από εκεί
   χτυπάει το ρολόι και για την επεξεργασία εικόνας και για το poll. */
const ticker = (() => {
  const subs = {};
  let w = null;
  try {
    w = new Worker(URL.createObjectURL(new Blob([
      'const t={};onmessage=e=>{const d=e.data;if(d.on){clearInterval(t[d.id]);t[d.id]=setInterval(()=>postMessage(d.id),d.ms)}else{clearInterval(t[d.id]);delete t[d.id]}}'
    ], {type: 'text/javascript'})));
    w.onmessage = e => { const f = subs[e.data]; if (f) { f(); } };
  } catch (e) { w = null; }
  const fallback = {};
  return {
    every(id, ms, fn) {
      subs[id] = fn;
      if (w) { w.postMessage({id, ms, on: true}); } else { clearInterval(fallback[id]); fallback[id] = setInterval(fn, ms); }
    },
    stop(id) {
      delete subs[id];
      if (w) { w.postMessage({id, on: false}); } else { clearInterval(fallback[id]); }
    }
  };
})();

let stream = null, camTrack = null, micOn = true, camOn = true, sharing = false;
/* Ποιος μοιράζεται οθόνη τώρα (peer id) — καθορίζει τη διάταξη, όχι τις ροές. */
let presenter = null;            // ο πιο πρόσφατος (συμβατότητα)
const presenters = new Set();    // ΟΛΟΙ όσοι μοιράζονται τώρα
let rawStream = null, bgMode = 'none', bgImg = null, seg = null, segBusy = false, procRAF = 0;
const procCanvas = document.createElement('canvas');
const procCtx = procCanvas.getContext('2d');
const rawVideo = document.createElement('video');
rawVideo.muted = true; rawVideo.playsInline = true;

/* 🏢 Εταιρικό φόντο: «τοίχος» γραφείου CloudOn, ζωγραφισμένος δυναμικά */
let brandBg = null;
function makeBrandBg() {
  return new Promise(res => {
    if (brandBg) return res(brandBg);
    const c = document.createElement('canvas');
    c.width = 1280; c.height = 720;
    const x = c.getContext('2d');
    // τοίχος: απαλό gradient γκρι-μπλε γραφείου
    const g = x.createLinearGradient(0, 0, 0, 720);
    g.addColorStop(0, '#233650'); g.addColorStop(.72, '#16223a'); g.addColorStop(1, '#101a2d');
    x.fillStyle = g; x.fillRect(0, 0, 1280, 720);
    // φωτεινό accent (σαν κρυφός φωτισμός οροφής)
    const rg = x.createRadialGradient(950, 80, 40, 950, 80, 640);
    rg.addColorStop(0, 'rgba(0,144,221,.28)'); rg.addColorStop(1, 'rgba(0,144,221,0)');
    x.fillStyle = rg; x.fillRect(0, 0, 1280, 720);
    // λεπτή brand γραμμή + «σοβατεπί»
    x.fillStyle = 'rgba(0,144,221,.55)'; x.fillRect(0, 596, 1280, 4);
    x.fillStyle = '#0b1322'; x.fillRect(0, 600, 1280, 120);
    // διακριτικό διαγώνιο watermark
    x.save();
    x.globalAlpha = .045; x.fillStyle = '#ffffff';
    x.font = '700 34px Arial'; x.rotate(-0.26);
    for (let yy = 0; yy < 1100; yy += 110) {
      for (let xx = -300; xx < 1500; xx += 260) {
        x.fillText('CloudOn', xx + (yy % 220 ? 120 : 0), yy);
      }
    }
    x.restore();
    // λογότυπο σαν ταμπέλα στον τοίχο (πάνω δεξιά)
    const logo = new Image();
    logo.onload = () => {
      const lw = 240, lh = lw * logo.height / logo.width, lx = 1280 - lw - 80, ly = 105;
      x.save();
      x.globalAlpha = .1; x.fillStyle = '#fff';
      const r2 = 22, bx = lx - 34, by = ly - 30, bw = lw + 68, bh = lh + 60;
      x.beginPath(); x.roundRect(bx, by, bw, bh, r2); x.fill();   // «πινακίδα»
      x.globalAlpha = .92;
      x.drawImage(logo, lx, ly, lw, lh);
      x.restore();
      brandBg = c; res(c);
    };
    logo.onerror = () => { brandBg = c; res(c); };
    logo.src = '/assets/img/logo.png';
  });
}

function segInit() {
  if (seg) return seg;
  seg = new SelfieSegmentation({locateFile: f => 'mediapipe/' + f});
  seg.setOptions({modelSelection: 1});
  seg.onResults(res => {
    const w = procCanvas.width, h = procCanvas.height;
    procCtx.save();
    procCtx.clearRect(0, 0, w, h);
    // 1. πρόσωπο/σώμα (μάσκα)
    procCtx.drawImage(res.segmentationMask, 0, 0, w, h);
    procCtx.globalCompositeOperation = 'source-in';
    procCtx.drawImage(res.image, 0, 0, w, h);
    // 2. φόντο πίσω του
    procCtx.globalCompositeOperation = 'destination-over';
    const bgPic = bgMode === 'brand' ? brandBg : (bgMode === 'image' ? bgImg : null);
    if (bgPic) {
      const r = Math.max(w / bgPic.width, h / bgPic.height);
      procCtx.drawImage(bgPic, (w - bgPic.width * r) / 2, (h - bgPic.height * r) / 2, bgPic.width * r, bgPic.height * r);
    } else {
      procCtx.filter = 'blur(16px)';
      procCtx.drawImage(res.image, -12, -12, w + 24, h + 24);
      procCtx.filter = 'none';
    }
    procCtx.restore();
    segBusy = false; segAt = Date.now();
  });
  return seg;
}
/* ~20 καρέ/δευτ. από τον Worker: σταθερός ρυθμός και στο παρασκήνιο, και λιγότερο
   φορτίο από τα 60 του rAF (που «έτρωγε» το main thread και έκανε τα βίντεο των
   άλλων να κολλάνε σε όποιον είχε θόλωμα).
   ΦΥΛΑΚΑΣ: αν η κατάτμηση κολλήσει (>1΄΄ χωρίς αποτέλεσμα), στέλνουμε ΟΛΟ το
   καρέ θολό — η εικόνα κινείται, και το φόντο που διάλεξες να κρύψεις μένει κρυφό. */
let segAt = 0, segSent = 0;
function procLoop() {
  if (bgMode === 'none' || rawVideo.readyState < 2) return;
  const now = Date.now();
  if (segBusy && now - segSent > 1000) {
    const w = procCanvas.width, h = procCanvas.height;
    procCtx.save(); procCtx.filter = 'blur(22px)';
    procCtx.drawImage(rawVideo, -16, -16, w + 32, h + 32);
    procCtx.restore();
    if (now - segSent > 3000) { segBusy = false; }   // ξαναδοκίμασε την κατάτμηση
    return;
  }
  if (segBusy) return;
  segBusy = true; segSent = now;
  segInit().send({image: rawVideo}).catch(() => { segBusy = false; });
}
async function makeEffectiveTrack() {
  const raw = rawStream ? rawStream.getVideoTracks()[0] : null;
  if (bgMode === 'none' || !raw) {
    ticker.stop('proc');
    return raw;
  }
  const st = raw.getSettings();
  procCanvas.width = st.width || 1280;
  procCanvas.height = st.height || 720;
  rawVideo.srcObject = new MediaStream([raw]);
  await rawVideo.play().catch(() => {});
  ticker.every('proc', 50, procLoop);
  return procCanvas.captureStream(20).getVideoTracks()[0];
}
async function setBg(mode) {
  if (mode === 'brand') await makeBrandBg();
  bgMode = mode;
  document.querySelectorAll('.bgb').forEach(b => b.classList.toggle('on', b.dataset.bg === mode));
  const eff = await makeEffectiveTrack();
  if (!eff) return;
  camTrack = eff;
  const newStream = new MediaStream([eff, ...(rawStream ? rawStream.getAudioTracks() : [])]);
  stream = newStream;
  $('#pv').srcObject = stream;
  const mirrorOn = bgMode === 'none' || bgMode === 'blur';
  $('#pv').classList.toggle('nomirror', !mirrorOn);
  if (me) {   // εν κλήσει: replaceTrack παντού + δικό μου tile
    Object.values(pcs).forEach(({pc}) => {
      const sn = pc.getSenders().find(x => x.track && x.track.kind === 'video');
      if (sn && !sharing) sn.replaceTrack(eff);
    });
    const myV = document.querySelector('#tile-' + me + ' video');
    if (myV && !sharing) {
      myV.srcObject = stream;
      myV.classList.toggle('mirror', mirrorOn);
    }
  }
  applyToggles();
}
let me = null, lastMsg = 0, pollT = null;
const pcs = {};   // peer -> {pc, name, tile}

function toast(m) {
  const t = document.createElement('div'); t.className = 'toast'; t.textContent = m;
  document.body.appendChild(t); setTimeout(() => t.remove(), 3000);
}
let myKey = '';   // υπογραφή του peer μας (rtc_join) — απαιτείται σε signal/poll/leave
async function api(a, data, qs) {
  const url = API + '?a=' + a + (MT ? '&mt=' + encodeURIComponent(MT) : '') + (qs || '');
  const r = await fetch(url, data ? {method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(Object.assign({room: ROOM, k: myKey}, data)), credentials: 'same-origin'} : {credentials: 'same-origin'});
  return r.json();
}

/* ─── Pre-join: συσκευές + preview ─── */
async function getStream() {
  const mic = $('#selMic').value, cam = $('#selCam').value;
  if (rawStream) rawStream.getTracks().forEach(t => t.stop());
  try {
    rawStream = await navigator.mediaDevices.getUserMedia(IS_REMOTE
      ? {audio: mic ? {deviceId: {exact: mic}} : true}
      : {audio: mic ? {deviceId: {exact: mic}} : true,
         video: Object.assign(cam ? {deviceId: {exact: cam}} : {},
           {width: {ideal: 1280}, height: {ideal: 720}, frameRate: {ideal: 24, max: 30}})});
  } catch (e) {
    try { rawStream = await navigator.mediaDevices.getUserMedia({audio: true}); camOn = false; }
    catch (e2) { rawStream = new MediaStream(); camOn = false; micOn = false; toast('Χωρίς πρόσβαση σε κάμερα/μικρόφωνο'); }
  }
  await setBg(bgMode);   // χτίζει το τελικό stream (raw ή με φόντο)
}
function applyToggles() {
  if (!stream) return;
  stream.getAudioTracks().forEach(t => t.enabled = micOn);
  stream.getVideoTracks().forEach(t => t.enabled = camOn);
  if (rawStream) rawStream.getVideoTracks().forEach(t => t.enabled = camOn);
  $('#pMic').classList.toggle('off', !micOn); $('#cMic').classList.toggle('off', !micOn);
  $('#pCam').classList.toggle('off', !camOn); $('#cCam').classList.toggle('off', !camOn);
  $('#pMic').innerHTML = $('#cMic').innerHTML = micOn ? ICO.mic : ICO.micOff;
  $('#pCam').innerHTML = $('#cCam').innerHTML = camOn ? ICO.cam : ICO.camOff;
  $('#pvOff').style.display = camOn && camTrack ? 'none' : 'flex';
}
$('#pvOff').innerHTML = ICO.camBig;
$('#cShare').innerHTML = ICO.share;
$('#cLeave').innerHTML = ICO.leave;
ICO.userPlus = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>';
$('#cInv').innerHTML = ICO.userPlus;
$('#cBg').onclick = () => {
  const cycle = ['none', 'blur', 'brand'].concat(bgImg ? ['image'] : []);
  const next = cycle[(cycle.indexOf(bgMode) + 1) % cycle.length];
  setBg(next);
  toast({none: 'Κανονικό φόντο', blur: '✨ Θολό φόντο', brand: '🏢 Φόντο CloudOn', image: '🖼 Δικό σου φόντο'}[next]);
};
$('#cInv').onclick = async () => {
  const modal = $('#invModal');
  modal.style.display = 'flex';
  if (!$('#invUrl').value) {
    if (IS_GUEST) {
      $('#invUrl').value = location.href;
    } else {
      const r = await api('rtc_invite', {});   // επιστρέφει φρέσκο guest URL για το τρέχον δωμάτιο
      $('#invUrl').value = r.url || location.href;
    }
  }
};
$('#invCopy').onclick = () => {
  navigator.clipboard.writeText($('#invUrl').value).then(() => toast('Ο σύνδεσμος αντιγράφηκε 📋'));
};
const iag = $('#invAdmGo'); if (iag) iag.onclick = async () => {
  const r = await api('rtc_invite', {admin: +$('#invAdm').value});
  toast('🔔 Κλήθηκε: ' + (r.sent || ''));
};
const ieg = $('#invEmGo'); if (ieg) ieg.onclick = async () => {
  const em = $('#invEm').value.trim();
  if (!em) return;
  const r = await api('rtc_invite', {email: em});
  if (r.sent) { toast('✉ Η πρόσκληση στάλθηκε: ' + r.sent); $('#invEm').value = ''; }
  else toast('Μη έγκυρο email', true);
};
function remoteGuestUi() {
  if (!IS_REMOTE) return;
  // Remote mode = ΟΧΙ κάμερες/φόντα για κανέναν — μόνο μικρόφωνο + όνομα
  const camSel = $('#selCam'); if (camSel) camSel.closest('div').style.display = 'none';
  document.querySelectorAll('.bgb').forEach(b => b.closest('div').closest('div').style.display = 'none');
  $('#pCam').style.display = 'none';
  $('#pvOff').style.display = 'flex';
  if (IS_GUEST) {
    $('#joinBtn').textContent = '🖥 Έναρξη — μοιράσου την οθόνη σου';
    $('#pvOff').innerHTML = '<div style="text-align:center;color:var(--mut);font-size:14px;padding:20px">Η οθόνη σας θα εμφανιστεί εδώ<br>μόλις πατήσετε «Έναρξη»</div>';
  } else {
    $('#joinBtn').textContent = '👁 Σύνδεση για προβολή οθόνης πελάτη';
    $('#pvOff').innerHTML = '<div style="text-align:center;color:var(--mut);font-size:14px;padding:20px">🖥 Remote προβολή<br>Θα δεις την οθόνη του πελάτη μόλις μπει και τη μοιραστεί</div>';
  }
}
async function loadDevices() {
  await getStream();   // πρώτα άδεια → μετά ονόματα συσκευών
  const devs = await navigator.mediaDevices.enumerateDevices();
  const fill = (sel, kind, label) => {
    const el = $(sel); el.innerHTML = '';
    devs.filter(d => d.kind === kind).forEach((d, i) =>
      el.insertAdjacentHTML('beforeend', `<option value="${d.deviceId}">${d.label || label + ' ' + (i + 1)}</option>`));
    if (!el.options.length) el.innerHTML = `<option value="">— καμία —</option>`;
  };
  fill('#selMic', 'audioinput', 'Μικρόφωνο');
  fill('#selCam', 'videoinput', 'Κάμερα');
  remoteGuestUi();
}
$('#selMic').onchange = getStream;
$('#selCam').onchange = getStream;
document.querySelectorAll('.bgb').forEach(b => {
  if (b.dataset.bg !== 'image') b.onclick = () => setBg(b.dataset.bg);
});
$('#bgFile').onchange = e => {
  const f = e.target.files[0]; if (!f) return;
  const img = new Image();
  img.onload = () => { bgImg = img; setBg('image'); toast('🖼 Το φόντο σου μπήκε'); };
  img.src = URL.createObjectURL(f);
};
$('#pMic').onclick = () => { micOn = !micOn; applyToggles(); };
$('#pCam').onclick = () => { camOn = !camOn; applyToggles(); };
loadDevices();

/* ─── Κλήση: mesh WebRTC ─── */
function addTile(peer, name, isMe) {
  const t = document.createElement('div');
  t.className = 'tile' + (isMe ? ' me' : '');
  t.id = 'tile-' + peer;
  /* Το όνομα το δίνει ο ΕΠΙΣΚΕΠΤΗΣ (πελάτης) — ΠΟΤΕ ως HTML: αλλιώς ένα «όνομα» με κώδικα
     έτρεχε στη σελίδα του υπαλλήλου, στο ίδιο origin με το api.php (28/9/2026). */
  t.innerHTML = `<video autoplay playsinline ${isMe ? 'muted class="mirror"' : ''}></video><div class="nm"></div>`;
  t.querySelector('.nm').textContent = String(name == null ? '' : name) + (isMe ? ' (εσύ)' : '');
  /* Μπαίνει κάποιος ΕΝΩ τρέχει παρουσίαση: το πρόσωπό του πάει στη λωρίδα, όχι
     πάνω από την οθόνη που όλοι κοιτούν. */
  $(presenters.size && !presenters.has(peer) ? '#strip' : '#grid').appendChild(t);
  return t.querySelector('video');
}
function updCnt() {
  /* Μετράμε ΣΥΝΔΕΔΕΜΕΝΟΥΣ (όχι ημιτελείς προσκλήσεις): 1 = μόνος σου. */
  const live = Object.values(pcs).filter(x => /connected|completed/.test(x.pc.iceConnectionState)).length;
  $('#cnt').textContent = (live + 1) + ' συμμετέχοντες';
}
/* ΠΟΙΟΤΗΤΑ ΑΝΑΛΟΓΑ ΜΕ ΤΟ ΠΛΗΘΟΣ (30/9/2026).
   Η κλήση είναι mesh: ο καθένας ανεβάζει ΞΕΧΩΡΙΣΤΗ ροή σε κάθε άλλον. Με 1280px χωρίς
   όριο, πέντε άτομα = πέντε ροές HD ανά άνθρωπο — η σύνδεση γέμιζε και τα βίντεο
   «σταματούσαν και ξεκινούσαν». Τώρα κάθε ροή κάμερας έχει ταβάνι που πέφτει όσο
   μεγαλώνει η ομάδα. Η ΟΘΟΝΗ παίρνει προτεραιότητα: περισσότερο bitrate και
   «balanced», ώστε η κύλιση να μένει ομαλή χωρίς να θολώνει το κείμενο. */
function tuneSenders() {
  const n = Math.max(1, Object.keys(pcs).length);
  const cam = n <= 1 ? 1500000 : n <= 3 ? 800000 : n <= 5 ? 500000 : 300000;
  const scr = n <= 2 ? 2500000 : n <= 4 ? 1600000 : 1000000;
  Object.values(pcs).forEach(({pc}) => {
    pc.getSenders().forEach(sn => {
      if (!sn.track || sn.track.kind !== 'video') return;
      const p = sn.getParameters();
      if (!p.encodings || !p.encodings.length) { p.encodings = [{}]; }
      const e = p.encodings[0];
      if (sharing) {
        e.maxBitrate = scr; e.maxFramerate = 30; e.scaleResolutionDownBy = 1;
        p.degradationPreference = 'balanced';
      } else {
        e.maxBitrate = cam; e.maxFramerate = 24; e.scaleResolutionDownBy = n >= 4 ? 1.5 : 1;
        p.degradationPreference = 'balanced';
      }
      sn.setParameters(p).catch(() => {});
    });
  });
}
function newPc(peer, name) {
  const pc = new RTCPeerConnection(ICE);
  if (stream) stream.getTracks().forEach(t => pc.addTrack(t, stream));
  /* Αυτοΐαση: αν η P2P σύνδεση πέσει (αλλαγή δικτύου, sleep), σβήνεται και ο «μικρότερος»
     από τους δύο ξανακαλεί — δεν χρειάζεται reload (18/9/2026). */
  pc.oniceconnectionstatechange = () => {
    if (pc.iceConnectionState === 'failed') { dropPeer(peer); }
    else if (pc.iceConnectionState === 'disconnected') {
      setTimeout(() => { if (pcs[peer] && pcs[peer].pc === pc && ['disconnected', 'failed'].includes(pc.iceConnectionState)) { dropPeer(peer); } }, 8000);
    }
  };
  pc.onicecandidate = e => { if (e.candidate) api('rtc_signal', {peer: me, to: peer, kind: 'ice', payload: JSON.stringify(e.candidate)}); };
  pc.ontrack = e => {
    let v = document.querySelector('#tile-' + peer + ' video');
    if (!v) v = addTile(peer, name, false);
    if (v.srcObject !== e.streams[0]) v.srcObject = e.streams[0];
    const w = document.getElementById('rWait'); if (w) w.remove();
  };
  pc.onconnectionstatechange = () => { updCnt(); if (pc.connectionState === 'connected') { tuneSenders(); } };
  pcs[peer] = {pc, name, at: Date.now()};
  updCnt();
  return pc;
}
async function callPeer(peer, name) {
  const pc = newPc(peer, name);
  const off = await pc.createOffer();
  await pc.setLocalDescription(off);
  api('rtc_signal', {peer: me, to: peer, kind: 'offer', payload: JSON.stringify({sdp: off, name: myNameVal()})});
  /* Αν ήδη μοιράζομαι, πες το και στον καινούριο — αλλιώς θα έβλεπε την οθόνη μου
     σε μικρό πλακίδιο, σαν να ήταν πρόσωπο. */
  if (sharing) { api('rtc_signal', {peer: me, to: peer, kind: 'share', payload: '1'}); }
  if (hands[me]) { api('rtc_signal', {peer: me, to: peer, kind: 'hand', payload: String(hands[me])}); }
}
function myNameVal() { return $('#myName') ? ($('#myName').value.trim() || 'Επισκέπτης') : ''; }
async function handleMsg(m) {
  if (m.kind === 'offer') {
    const d = JSON.parse(m.payload);
    /* Glare (κληθήκαμε ταυτόχρονα): ο ΜΙΚΡΟΤΕΡΟΣ id κρατά τη δική του πρόσκληση, ο μεγαλύτερος
       υποχωρεί και απαντά. Έτσι δύο peers που ξανασυνδέονται δεν κολλάνε ποτέ σε αδιέξοδο. */
    if (pcs[m.from] && pcs[m.from].pc.signalingState !== 'stable') {
      if (me < m.from) { return; }
      dropPeer(m.from);
    } else if (pcs[m.from] && pcs[m.from].pc.remoteDescription) {
      dropPeer(m.from);   // νέα πρόσκληση από peer που ξανασυνδέθηκε: φρέσκια σύνδεση
    }
    const pc = pcs[m.from] ? pcs[m.from].pc : newPc(m.from, d.name || '…');
    if (d.name && pcs[m.from]) { pcs[m.from].name = d.name; const nm = document.querySelector('#tile-' + m.from + ' .nm'); if (nm) nm.textContent = d.name; }
    await pc.setRemoteDescription(d.sdp);
    const ans = await pc.createAnswer();
    await pc.setLocalDescription(ans);
    api('rtc_signal', {peer: me, to: m.from, kind: 'answer', payload: JSON.stringify(ans)});
    /* Ο ΝΕΟΣ μας καλεί — εμείς του λέμε ότι μοιραζόμαστε / έχουμε σηκωμένο χέρι.
       Πριν, όποιος έμπαινε ενώ έτρεχε παρουσίαση έβλεπε την οθόνη σαν πρόσωπο. */
    if (sharing) { api('rtc_signal', {peer: me, to: m.from, kind: 'share', payload: '1'}); }
    if (hands[me]) { api('rtc_signal', {peer: me, to: m.from, kind: 'hand', payload: String(hands[me])}); }
  } else if (m.kind === 'answer' && pcs[m.from]) {
    await pcs[m.from].pc.setRemoteDescription(JSON.parse(m.payload));
  } else if (m.kind === 'ice' && pcs[m.from]) {
    try { await pcs[m.from].pc.addIceCandidate(JSON.parse(m.payload)); } catch (e) {}
  } else if (m.kind === 'share') {
    /* Ο άλλος άρχισε ή σταμάτησε να μοιράζεται. Δεν πειράζουμε ροές — μόνο διάταξη. */
    setPresenter(m.from, m.payload === '1');
  } else if (m.kind === 'hand') {
    if (m.payload === 'down') { if (hands[me]) { setHand(false, true); } return; }   // σου το κατέβασε συνάδελφος
    const was = !!hands[m.from];
    if (m.payload === '0') { delete hands[m.from]; } else { hands[m.from] = +m.payload || Date.now(); }
    renderHands();
    if (!was && hands[m.from]) {
      const nm = (pcs[m.from] && pcs[m.from].name) || 'Κάποιος';
      toast('✋ ' + nm + ' σήκωσε χέρι'); beep(1);
    }
  } else if (m.kind === 'bye') {
    if (presenters.has(m.from)) { setPresenter(m.from, false); }
    dropPeer(m.from);
  }
}
function dropPeer(peer) {
  if (pcs[peer]) { try { pcs[peer].pc.close(); } catch (e) {} delete pcs[peer]; }
  const t = $('#tile-' + peer); if (t) t.remove();
  if (hands[peer]) { delete hands[peer]; renderHands(); }
  updCnt();
  tuneSenders();
}
let pollFails = 0, rejoining = false;
const seenAt = {};   // peer -> πότε πρωτοεμφανίστηκε στο roster χωρίς σύνδεση
function setStatus(txt) { const el = $('#cnt'); if (el) el.textContent = txt; }
async function poll() {
  if (!me || rejoining) return;
  let r;
  try {
    r = await api('rtc_poll', null, '&room=' + ROOM + '&peer=' + me + '&k=' + myKey + '&after=' + lastMsg + '&chat_after=' + lastChat + '&name=' + encodeURIComponent(myNameVal()));
    if (!r || !Array.isArray(r.messages)) { throw new Error(r && r.error ? r.error : 'bad'); }
  } catch (e) {
    /* Ο server δεν απαντά ή αρνείται: μετά από 4 συνεχόμενες αποτυχίες (~5΄΄) ξαναμπαίνουμε
       στο ΙΔΙΟ δωμάτιο αυτόματα, με νέο peer — όχι «κλείσε κι άνοιξε». */
    pollFails++;
    if (pollFails >= 2) setStatus('🔄 Πρόβλημα σύνδεσης… (' + pollFails + ')');
    if (pollFails >= 4) { await rejoin('Χάθηκε η επικοινωνία με τον server'); }
    return;
  }
  if (pollFails) { pollFails = 0; updCnt(); }
  if (r.restored) { toast('🔄 Επανασυνδέθηκες στο δωμάτιο'); }
  if (r.end) { applyEnd(r.end, false); }
  if (r.extendOk !== null && r.extendOk !== undefined) { extendOk = r.extendOk; extendWhy = r.extendWhy || ''; }
  for (const m of r.messages) { lastMsg = Math.max(lastMsg, m.id); try { await handleMsg(m); } catch (e) {} }
  /* Το πρώτο φόρτωμα είναι ΙΣΤΟΡΙΚΟ: φαίνεται, αλλά δεν μετρά ως αδιάβαστο ούτε βγάζει ειδοποίηση. */
  { const hist = !chatLoaded; chatLoaded = true; (r.chat || []).forEach(m => addChat(Object.assign(m, {fresh: !hist}))); }
  const alive = new Set(r.roster.map(x => x.peer));
  const now = Date.now();
  Object.keys(pcs).forEach(p => {
    if (!alive.has(p)) { dropPeer(p); delete seenAt[p]; return; }
    /* Πρόσκληση που δεν κατέληξε ποτέ σε σύνδεση (ο άλλος δεν την είδε): μετά από 20΄΄ πετιέται
       και ξανακαλούμε από την αρχή. */
    const x = pcs[p];
    if (now - x.at > 20000 && !/connected|completed/.test(x.pc.iceConnectionState) && x.pc.signalingState !== 'stable') { dropPeer(p); seenAt[p] = now; }
  });
  /* Κάποιος είναι στο δωμάτιο αλλά δεν έχουμε σύνδεση (έπεσε, ή ξαναμπήκε και η πρόσκλησή
     του χάθηκε): μετά από 4΄΄ τον καλούμε εμείς. Αν εκείνος νομίζει ότι είναι ακόμη
     συνδεδεμένος, θα δεχθεί τη νέα πρόσκληση και θα ανανεώσει τη σύνδεση. */
  r.roster.forEach(p => {
    if (p.peer === me || pcs[p.peer]) { delete seenAt[p.peer]; return; }
    if (!seenAt[p.peer]) { seenAt[p.peer] = now; return; }
    if (now - seenAt[p.peer] > 4000) { seenAt[p.peer] = now + 10000; callPeer(p.peer, p.name).catch(() => {}); }
  });
}
/* Ξαναμπαίνουμε στο ίδιο δωμάτιο: νέο peer id, καθαρές συνδέσεις, καλούμε όλους. */
async function rejoin(why) {
  if (rejoining) return;
  rejoining = true;
  setStatus('🔄 Επανασύνδεση…');
  if (why) toast(why + ' — επανασύνδεση…');
  Object.keys(pcs).forEach(dropPeer);
  const oldTile = me ? $('#tile-' + me) : null;
  const oldMe = me;
  if (oldMe) { api('rtc_leave', {peer: oldMe, k: myKey}).catch(() => {}); }   // να μη μείνει «φάντασμα» στη λίστα
  for (let i = 0; i < 20; i++) {
    try {
      const r = await api('rtc_join', {name: myNameVal()});
      if (r && r.ended) { endDone = true; rejoining = false; leaveCleanup(); showEnded(); return; }
      if (r && r.peer) {
        me = r.peer; myKey = r.key || ''; lastMsg = 0; pollFails = 0; myPeers.add(me);
        if (oldTile) { oldTile.id = 'tile-' + me; }
        r.roster.forEach(p => callPeer(p.peer, p.name).catch(() => {}));
        rejoining = false; updCnt(); toast('✅ Ξανά μέσα');
        return;
      }
    } catch (e) {}
    await new Promise(res => setTimeout(res, Math.min(15000, 2000 * (i + 1))));
  }
  rejoining = false;
  setStatus('⚠ Χωρίς σύνδεση — πάτα Επανασύνδεση');
}
/* Επιστροφή στο tab / επανασύνδεση δικτύου: άμεσο poll αντί να περιμένουμε τον κύκλο. */
document.addEventListener('visibilitychange', () => { if (!document.hidden && me) poll(); });
window.addEventListener('online', () => { if (me) poll(); });

$('#joinBtn').onclick = async () => {
  if (IS_GUEST && !myNameVal()) { toast('Γράψε το όνομά σου'); return; }
  if (IS_REMOTE && IS_GUEST) {
    let ds;
    try {
      ds = await navigator.mediaDevices.getDisplayMedia({video: true});
    } catch (e) {
      toast('Χρειάζεται να επιτρέψετε τον διαμοιρασμό οθόνης για να ξεκινήσει η υποστήριξη');
      return;
    }
    const sTrack = ds.getVideoTracks()[0];
    stream = new MediaStream([sTrack, ...(rawStream ? rawStream.getAudioTracks() : [])]);
    camTrack = sTrack;
    camOn = true;
    sTrack.onended = () => toast('Ο διαμοιρασμός οθόνης σταμάτησε — κλείστε τη σελίδα για τερματισμό');
  }
  const r = await api('rtc_join', {name: myNameVal()});
  if (r && r.ended) { showEnded(); return; }
  if (!r.peer) { toast(r && r.error ? r.error : 'Σφάλμα σύνδεσης'); return; }
  me = r.peer; myKey = r.key || ''; myPeers.add(me);
  $('#pre').style.display = 'none';
  $('#call').style.display = 'flex';
  const mv = addTile(me, r.name, true);
  mv.srcObject = stream;
  mv.classList.toggle('mirror', !(IS_REMOTE && IS_GUEST) && (bgMode === 'none' || bgMode === 'blur'));
  if (IS_REMOTE && !stream.getVideoTracks().length) {
    const t = document.getElementById('tile-' + me);
    t.style.cssText = 'position:fixed;right:14px;bottom:86px;width:180px;height:52px;z-index:5;background:var(--card);border-radius:12px';
    t.querySelector('.nm').textContent = '🎙 ' + r.name + ' (εσύ — μόνο ήχος)';
  }
  if (IS_REMOTE) {
    $('#cBg').style.display = 'none';    // φόντα άσχετα στο remote
    $('#cCam').style.display = 'none';   // κανείς δεν χρειάζεται κάμερα εδώ
    if (!IS_GUEST && !stream.getVideoTracks().length) {
      const w = document.createElement('div');
      w.id = 'rWait';
      w.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--mut);font-size:15px;text-align:center;pointer-events:none';
      w.innerHTML = '⏳ Περιμένουμε τον πελάτη να μπει και να μοιραστεί την οθόνη του…<br><small>Μόλις συνδεθεί, η οθόνη του θα εμφανιστεί εδώ</small>';
      $('#grid').style.position = 'relative';
      $('#grid').appendChild(w);
    }
  }
  updCnt();
  r.roster.forEach(p => callPeer(p.peer, p.name).catch(e => { console.warn('call failed', p, e); toast('Δεν έγινε σύνδεση με ' + p.name + ' — θα ξαναπροσπαθήσω'); }));
  ticker.every('poll', 1200, poll);
};

/* ═══ ΔΙΑΤΑΞΗ ΠΑΡΟΥΣΙΑΣΗΣ ═══
   Ποιος μοιράζεται τώρα. Όταν υπάρχει κάποιος, το πλακίδιό του πιάνει όλη την
   οθόνη και τα υπόλοιπα πάνε σε λωρίδα από κάτω. Αν μοιράζονται δύο, δείχνουμε
   τον πιο πρόσφατο — δύο «μεγάλες» οθόνες δεν χωράνε πουθενά. */
function setPresenter(peer, on) {
  /* Παλιά κλήση setPresenter(null) = κανείς. */
  if (peer === null) { presenters.clear(); }
  else if (on === false) { presenters.delete(peer); }
  else { presenters.add(peer); }
  [...presenters].forEach(p => { if (!document.getElementById('tile-' + p)) { presenters.delete(p); } });
  presenter = presenters.size ? [...presenters].pop() : null;
  const grid = $('#grid'), strip = $('#strip');
  document.querySelectorAll('.tile').forEach(t => t.classList.remove('present'));
  if (!presenters.size) {
    grid.classList.remove('presenting', 'multi');
    /* Τα πρόσωπα γυρίζουν στο πλέγμα. */
    [...strip.children].forEach(t => grid.appendChild(t));
    return;
  }
  grid.classList.add('presenting');
  grid.classList.toggle('multi', presenters.size > 1);
  presenters.forEach(p => {
    const big = document.getElementById('tile-' + p);
    big.classList.add('present');
    grid.appendChild(big);
  });
  document.querySelectorAll('#grid .tile').forEach(t => { if (!t.classList.contains('present')) { strip.appendChild(t); } });
}
window.setPresenter = setPresenter;   // για δοκιμές διάταξης χωρίς δεύτερο peer
function announceShare(on) {
  Object.keys(pcs).forEach(p => api('rtc_signal', {peer: me, to: p, kind: 'share', payload: on ? '1' : '0'}));
}

/* ═══ ✋ ΣΗΚΩΜΑ ΧΕΡΙΟΥ ═══
   Ζητάς τον λόγο χωρίς να διακόψεις. Όλοι βλέπουν το σήμα στο πλακίδιό σου και
   την ουρά με τη σειρά (ποιος το σήκωσε πρώτος). Το κατεβάζεις εσύ — ή ένας
   συνάδελφος από την ομάδα πατώντας το ✋ στο πλακίδιό σου, όταν σου δώσει τον λόγο. */
const hands = {};   // peer -> πότε σήκωσε (ms)
function nameOf(peer) {
  if (peer === me) { return 'Εσύ'; }
  const nm = document.querySelector('#tile-' + peer + ' .nm');
  return (pcs[peer] && pcs[peer].name) || (nm ? nm.textContent : '…');
}
function renderHands() {
  const order = Object.keys(hands).sort((a, b) => hands[a] - hands[b]);
  document.querySelectorAll('.tile').forEach(t => {
    const peer = t.id.replace('tile-', '');
    let b = t.querySelector('.hand');
    const idx = order.indexOf(peer);
    t.classList.toggle('raised', idx >= 0);
    if (idx < 0) { if (b) { b.remove(); } return; }
    if (!b) {
      const canLower = peer === me || !IS_GUEST;
      b = document.createElement(canLower ? 'button' : 'span');
      b.className = 'hand';
      if (canLower) {
        b.type = 'button';
        b.title = peer === me ? 'Κατέβασε το χέρι σου' : 'Κατέβασέ του το χέρι (πήρε τον λόγο)';
        b.onclick = () => { if (peer === me) { setHand(false); } else { api('rtc_signal', {peer: me, to: peer, kind: 'hand', payload: 'down'}); } };
      }
      t.appendChild(b);
    }
    b.textContent = '✋ ' + (idx + 1);
  });
  const q = $('#handQ');
  if (!order.length) { q.classList.remove('on'); q.innerHTML = ''; return; }
  q.classList.add('on');
  q.innerHTML = '<b>✋ Ζητούν τον λόγο:</b> ';
  order.forEach((p, i) => {
    const s = document.createElement('span');
    s.textContent = (i ? '· ' : '') + (i + 1) + '. ' + nameOf(p);
    q.appendChild(s);
  });
}
function setHand(on, byOther) {
  if (!me) return;
  if (on) { hands[me] = Date.now(); } else { delete hands[me]; }
  const hb = $('#cHand');
  hb.classList.toggle('on', !!on); hb.setAttribute('aria-pressed', on ? 'true' : 'false');
  hb.title = on ? 'Κατέβασε το χέρι σου' : 'Σήκωσε χέρι για να πάρεις τον λόγο';
  Object.keys(pcs).forEach(p => api('rtc_signal', {peer: me, to: p, kind: 'hand', payload: on ? String(hands[me]) : '0'}));
  renderHands();
  if (byOther) { toast('Σου έδωσαν τον λόγο — το χέρι σου κατέβηκε'); }
  else { toast(on ? '✋ Σήκωσες χέρι — θα σου δώσουν τον λόγο' : 'Κατέβασες το χέρι'); }
}
$('#cHand').onclick = () => setHand(!hands[me]);
if (IS_REMOTE) { $('#cHand').style.display = 'none'; }

/* ═══ 💬 CHAT ΣΥΝΑΝΤΗΣΗΣ ═══
   Κείμενο προς όλο το δωμάτιο, μέσα από το ίδιο poll. Πάντα ως ΚΕΙΜΕΝΟ (textContent):
   ο επισκέπτης γράφει ό,τι θέλει, και ένα «μήνυμα» με κώδικα δεν πρέπει να τρέξει
   στη σελίδα του υπαλλήλου. Οι σύνδεσμοι φτιάχνονται ως στοιχεία, όχι ως HTML. */
let lastChat = 0, chatUnread = 0, chatLoaded = false;
const myPeers = new Set();     // ο peer μου αλλάζει στην επανασύνδεση — τα δικά μου μένουν «δικά μου»
const chatSeen = new Set();
function chatOpen() { return $('#chatPane').classList.contains('on'); }
function chatBadge() {
  const b = $('#cChat');
  b.classList.toggle('unread', chatUnread > 0);
  $('#chatBdg').textContent = chatUnread > 9 ? '9+' : String(chatUnread);
}
function addChat(m) {
  if (chatSeen.has(m.id)) { return; }
  chatSeen.add(m.id); lastChat = Math.max(lastChat, m.id);
  const list = $('#chatList'), empty = $('#chatEmpty'); if (empty) { empty.remove(); }
  const mine = myPeers.has(m.peer);
  const el = document.createElement('div'); el.className = 'cmsg' + (mine ? ' mine' : '');
  const h = document.createElement('div'); h.className = 'cm-h'; h.textContent = (mine ? 'Εσύ' : m.name) + ' · ' + (m.at || '');
  el.appendChild(h);
  String(m.body || '').split(/(https?:\/\/[^\s<>"']+)/g).forEach((part, i) => {
    if (i % 2) { const a = document.createElement('a'); a.href = part; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.textContent = part; el.appendChild(a); }
    else if (part) { el.appendChild(document.createTextNode(part)); }
  });
  const atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
  list.appendChild(el);
  if (atBottom || mine) { list.scrollTop = list.scrollHeight; }
  if (!mine && !chatOpen() && m.fresh !== false) {
    chatUnread++; chatBadge();
    toast('💬 ' + m.name + ': ' + String(m.body).slice(0, 80));
  }
}
function toggleChat(on) {
  const pane = $('#chatPane'), want = on === undefined ? !chatOpen() : on;
  pane.classList.toggle('on', want);
  document.body.classList.toggle('chat-on', want);
  $('#cChat').classList.toggle('on', want); $('#cChat').setAttribute('aria-pressed', want ? 'true' : 'false');
  if (want) { chatUnread = 0; chatBadge(); const l = $('#chatList'); l.scrollTop = l.scrollHeight; setTimeout(() => $('#chatIn').focus(), 30); }
}
$('#cChat').onclick = () => toggleChat();
$('#chatX').onclick = () => toggleChat(false);
async function sendChat() {
  const inp = $('#chatIn'), body = inp.value.trim();
  if (!body || !me) { return; }
  inp.value = ''; inp.style.height = '';
  const r = await api('rtc_chat', {peer: me, body}).catch(() => null);
  if (!r || !r.ok) { inp.value = body; toast((r && r.error) || 'Δεν στάλθηκε — δοκίμασε ξανά'); return; }
  addChat({id: r.id, peer: me, name: myNameVal() || 'Εσύ', body, at: new Date().toTimeString().slice(0, 5)});
}
$('#chatForm').onsubmit = e => { e.preventDefault(); sendChat(); };
$('#chatIn').onkeydown = e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); } };
$('#chatIn').oninput = e => { const t = e.target; t.style.height = ''; t.style.height = Math.min(120, t.scrollHeight) + 'px'; };

/* ─── In-call controls ─── */
$('#cMic').onclick = () => { micOn = !micOn; applyToggles(); };
$('#cCam').onclick = () => { camOn = !camOn; applyToggles(); };
$('#cShare').onclick = async () => {
  if (!sharing) {
    try {
      const ds = await navigator.mediaDevices.getDisplayMedia({video: {frameRate: {ideal: 30, max: 30}}, audio: false});
      const track = ds.getVideoTracks()[0];
      try { track.contentHint = 'detail'; } catch (e) {}   // κείμενο ευανάγνωστο· η ομαλότητα από το «balanced»
      Object.values(pcs).forEach(({pc}) => {
        const sn = pc.getSenders().find(s => s.track && s.track.kind === 'video');
        if (sn) sn.replaceTrack(track);
      });
      const myV = document.querySelector('#tile-' + me + ' video');
      myV.srcObject = new MediaStream([track, ...stream.getAudioTracks()]);
      myV.classList.remove('mirror');
      sharing = true; $('#cShare').classList.add('off'); toast('Μοιράζεσαι την οθόνη σου 🖥');
      tuneSenders();
      announceShare(true); setPresenter(me, true);
      track.onended = stopShare;
    } catch (e) {}
  } else stopShare();
};
function stopShare() {
  if (!sharing) return;
  sharing = false; $('#cShare').classList.remove('off');
  setTimeout(tuneSenders, 300);
  announceShare(false); setPresenter(me, false);
  Object.values(pcs).forEach(({pc}) => {
    const sn = pc.getSenders().find(s => s.track && s.track.kind === 'video');
    if (sn && camTrack) sn.replaceTrack(camTrack);
  });
  const myV = document.querySelector('#tile-' + me + ' video');
  if (myV) {
    myV.srcObject = stream;
    myV.classList.toggle('mirror', bgMode === 'none' || bgMode === 'blur');
  }
  toast('Ο διαμοιρασμός σταμάτησε');
}
const BACK_URL = <?= json_encode($backUrl) ?>;
const BACK_LABEL = <?= json_encode($backLabel) ?>;
/** Τερματισμός σύνδεσης & απελευθέρωση κάμερας/μικροφώνου (χωρίς αλλαγή οθόνης). */
function leaveCleanup() {
  ticker.stop('poll');
  if (me) {
    Object.keys(pcs).forEach(p => api('rtc_signal', {peer: me, to: p, kind: 'bye', payload: ''}));
    api('rtc_leave', {peer: me, final: 1});   // final: πραγματική αποχώρηση → η κατάστασή σου αλλάζει αμέσως
  }
  Object.keys(pcs).forEach(dropPeer);
  ticker.stop('proc');
  if (stream) stream.getTracks().forEach(t => t.stop());
  if (rawStream) rawStream.getTracks().forEach(t => t.stop());
  me = null;   // μη στείλεις δεύτερο leave στο beforeunload
}
/* ─── Λήξη meeting: 5΄ πριν ειδοποιούνται ΟΛΟΙ, στη λήξη κλείνει η σύνδεση ─── */
const clockSkew = SERVER_NOW - Date.now();      // ώρα server, όχι ρολόι browser
const srvNow = () => Date.now() + clockSkew;
let endWarned = false, endDone = false, meetEnd = MEET_END, extendOk = null, extendWhy = '';
function beep(times) {
  try {
    const ac = new (window.AudioContext || window.webkitAudioContext)();
    for (let i = 0; i < (times || 2); i++) {
      const o = ac.createOscillator(), g = ac.createGain();
      o.type = 'sine'; o.frequency.value = 880; g.gain.value = 0.12;
      o.connect(g); g.connect(ac.destination);
      o.start(ac.currentTime + i * 0.35); o.stop(ac.currentTime + i * 0.35 + 0.22);
    }
  } catch (e) {}
}
function showEnded() {
  document.body.innerHTML = '<div style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;padding:20px;text-align:center">'
    + '<div style="font-size:56px">⏰</div><h2>Το meeting ολοκληρώθηκε</h2>'
    + '<div style="color:var(--mut);max-width:520px;line-height:1.5">' + (MEET_TITLE ? '«' + MEET_TITLE.replace(/</g, '&lt;') + '» — ' : '') + 'η ώρα λήξης πέρασε και το δωμάτιο έκλεισε.<br>'
    + (BACK_URL ? 'Για συνέχεια, φτιάξε <b>νέο meeting</b> από το ημερολόγιο — θα έχει νέο σύνδεσμο.' : 'Αν χρειάζεται συνέχεια, ο διοργανωτής θα σας στείλει νέο σύνδεσμο.') + '</div>'
    + (BACK_URL ? '<a class="btn btn-p" href="' + BACK_URL + '" style="text-decoration:none">Επιστροφή στο ημερολόγιο</a>' : '') + '</div>';
}
function endTick() {
  if (!meetEnd || endDone) return;
  const left = meetEnd - srvNow();
  const b = $('#endBanner');
  if (left <= 0) {
    endDone = true;
    if (me) { leaveCleanup(); }
    showEnded();
    return;
  }
  if (left <= 5 * 60000) {
    const m = Math.floor(left / 60000), sec = Math.floor(left % 60000 / 1000);
    if (b) {
      b.style.display = me ? 'flex' : 'none';
      const txt = '⏰ Μένουν ' + (m ? m + '΄ ' : '') + String(sec).padStart(2, '0') + '΄΄ — στη λήξη η σύνδεση κλείνει';
      /* Παράταση: ΜΟΝΟ για την ομάδα και ΜΟΝΟ αν χωράει (ο server ελέγχει τα ημερολόγια των συμμετεχόντων). */
      const ext = !IS_GUEST && extendOk === true ? '<button class="btn btn-p" id="extBtn" style="margin-left:12px;padding:5px 12px">+15΄ Παράταση</button>'
        : (!IS_GUEST && extendOk === false ? '<span style="margin-left:12px;font-weight:600;opacity:.85;font-size:12.5px" title="' + extendWhy.replace(/"/g, '&quot;') + '">χωρίς παράταση — ' + extendWhy.replace(/</g, '&lt;') + '</span>' : '');
      if (b.dataset.k !== txt + ext) { b.dataset.k = txt + ext; b.innerHTML = '<span>' + txt + '</span>' + ext; const eb = $('#extBtn'); if (eb) eb.onclick = extendMeeting; }
      b.style.alignItems = 'center';
      if (left <= 60000) { b.style.background = '#e2515f'; b.style.color = '#fff'; }
    }
    if (!endWarned && me) { endWarned = true; beep(3); toast('⏰ Μένουν 5 λεπτά — το meeting κλείνει στη λήξη'); }
  } else if (b) { b.style.display = 'none'; }
}
async function extendMeeting() {
  const eb = $('#extBtn'); if (eb) eb.disabled = true;
  const r = await api('meet_extend', {mins: 15}).catch(() => null);
  if (!r || !r.ok) { toast(r && r.error ? r.error : 'Δεν έγινε η παράταση'); if (eb) eb.disabled = false; return; }
  applyEnd(r.end, true);
}
function applyEnd(newEnd, mine) {
  if (!newEnd || newEnd === meetEnd) return;
  const later = newEnd > meetEnd;
  meetEnd = newEnd;
  if (later) { endWarned = false; extendOk = null; const b = $('#endBanner'); if (b) { b.style.display = 'none'; b.style.background = '#eba63c'; b.style.color = '#1a1200'; } }
  const ea = $('#endAt'); if (ea) ea.textContent = 'λήγει ' + new Date(newEnd).toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit', hour12: false});
  if (!mine) toast('⏰ Το meeting ' + (later ? 'παρατάθηκε' : 'άλλαξε') + ' — λήγει ' + new Date(newEnd).toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit', hour12: false}));
  else toast('✅ Παράταση έως ' + new Date(newEnd).toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit', hour12: false}));
}
setInterval(endTick, 1000);
function leave() {
  leaveCleanup();
  document.body.innerHTML = '<div style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:16px;padding:20px;text-align:center">'
    + '<div style="font-size:56px">👋</div><h2>Το meeting ολοκληρώθηκε</h2>'
    + '<div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center">'
    + (BACK_URL ? '<a class="btn btn-p" href="' + BACK_URL + '" style="text-decoration:none">' + BACK_LABEL + '</a>' : '')
    + '<button class="btn" onclick="location.reload()">Επανασύνδεση</button></div></div>';
}
$('#cLeave').onclick = leave;
{ const ex = $('#cExit'); if (ex) { ex.onclick = () => { leaveCleanup(); location.href = BACK_URL; }; } }
/* Κλείσιμο tab/reload: sendBeacon φτάνει ακόμη κι όταν η σελίδα πεθαίνει (το fetch κοβόταν) —
   έτσι δεν μένει «φάντασμά» μας στη λίστα για τους άλλους. */
window.addEventListener('pagehide', () => {
  if (!me) return;
  const url = API + '?a=rtc_leave' + (MT ? '&mt=' + encodeURIComponent(MT) : '');
  /* text/plain: τύπος που το sendBeacon στέλνει πάντα χωρίς αντιρρήσεις· ο server διαβάζει JSON από το σώμα. */
  try { navigator.sendBeacon(url, new Blob([JSON.stringify({room: ROOM, peer: me, k: myKey, final: 1})], {type: 'text/plain;charset=UTF-8'})); } catch (e) {}
});
</script>
</body>
</html>
