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
.tile.me video.mirror{transform:scaleX(-1)}
.tile .nm{position:absolute;left:10px;bottom:8px;background:#0009;padding:3px 10px;border-radius:8px;font-size:12px;font-weight:700}
#bar{display:flex;gap:12px;justify-content:center;padding:14px;background:var(--card)}
#bar .rbtn{width:54px;height:54px}
#bar .leave{background:var(--bad)}
#topbar{display:flex;align-items:center;gap:10px;padding:10px 16px;font-size:13px;color:var(--mut)}
#topbar b{color:var(--ink)}
.toast{position:fixed;top:14px;left:50%;transform:translateX(-50%);background:var(--card);padding:10px 18px;border-radius:12px;font-size:13px;box-shadow:0 8px 24px rgba(0,0,0,.4);z-index:9}
.bgb.on{background:var(--brand);color:#fff}
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
  <div id="topbar"><b style="color:var(--brand)">●</b> <b>CloudOn <?= $isRemote ? 'Remote Υποστήριξη' : 'Meet' ?></b> · δωμάτιο <?= htmlspecialchars($room) ?> · <span id="cnt"></span></div>
  <div id="grid"></div>
  <div id="bar">
    <button class="rbtn" id="cMic" title="Μικρόφωνο"></button>
    <button class="rbtn" id="cCam" title="Κάμερα"></button>
    <button class="rbtn" id="cShare" title="Διαμοιρασμός οθόνης"></button>
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
const ICE = {iceServers: [{urls: 'stun:stun.l.google.com:19302'}, {urls: 'stun:stun1.l.google.com:19302'}]};
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

let stream = null, camTrack = null, micOn = true, camOn = true, sharing = false;
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
    segBusy = false;
  });
  return seg;
}
function procLoop() {
  procRAF = requestAnimationFrame(procLoop);
  if (bgMode === 'none' || segBusy || rawVideo.readyState < 2) return;
  segBusy = true;
  segInit().send({image: rawVideo});
}
async function makeEffectiveTrack() {
  const raw = rawStream ? rawStream.getVideoTracks()[0] : null;
  if (bgMode === 'none' || !raw) {
    cancelAnimationFrame(procRAF);
    return raw;
  }
  const st = raw.getSettings();
  procCanvas.width = st.width || 1280;
  procCanvas.height = st.height || 720;
  rawVideo.srcObject = new MediaStream([raw]);
  await rawVideo.play().catch(() => {});
  cancelAnimationFrame(procRAF);
  procLoop();
  return procCanvas.captureStream(25).getVideoTracks()[0];
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
async function api(a, data, qs) {
  const url = API + '?a=' + a + (MT ? '&mt=' + encodeURIComponent(MT) : '') + (qs || '');
  const r = await fetch(url, data ? {method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(Object.assign({room: ROOM}, data)), credentials: 'same-origin'} : {credentials: 'same-origin'});
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
         video: cam ? {deviceId: {exact: cam}, width: {ideal: 1280}} : {width: {ideal: 1280}}});
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
  t.innerHTML = `<video autoplay playsinline ${isMe ? 'muted class="mirror"' : ''}></video><div class="nm">${name}${isMe ? ' (εσύ)' : ''}</div>`;
  $('#grid').appendChild(t);
  return t.querySelector('video');
}
function updCnt() {
  $('#cnt').textContent = (Object.keys(pcs).length + 1) + ' συμμετέχοντες';
}
function newPc(peer, name) {
  const pc = new RTCPeerConnection(ICE);
  stream.getTracks().forEach(t => pc.addTrack(t, stream));
  pc.onicecandidate = e => { if (e.candidate) api('rtc_signal', {peer: me, to: peer, kind: 'ice', payload: JSON.stringify(e.candidate)}); };
  pc.ontrack = e => {
    let v = document.querySelector('#tile-' + peer + ' video');
    if (!v) v = addTile(peer, name, false);
    if (v.srcObject !== e.streams[0]) v.srcObject = e.streams[0];
    const w = document.getElementById('rWait'); if (w) w.remove();
  };
  pcs[peer] = {pc, name};
  updCnt();
  return pc;
}
async function callPeer(peer, name) {
  const pc = newPc(peer, name);
  const off = await pc.createOffer();
  await pc.setLocalDescription(off);
  api('rtc_signal', {peer: me, to: peer, kind: 'offer', payload: JSON.stringify({sdp: off, name: myNameVal()})});
}
function myNameVal() { return $('#myName') ? ($('#myName').value.trim() || 'Επισκέπτης') : ''; }
async function handleMsg(m) {
  if (m.kind === 'offer') {
    const d = JSON.parse(m.payload);
    const pc = pcs[m.from] ? pcs[m.from].pc : newPc(m.from, d.name || '…');
    if (d.name && pcs[m.from]) { pcs[m.from].name = d.name; const nm = document.querySelector('#tile-' + m.from + ' .nm'); if (nm) nm.textContent = d.name; }
    await pc.setRemoteDescription(d.sdp);
    const ans = await pc.createAnswer();
    await pc.setLocalDescription(ans);
    api('rtc_signal', {peer: me, to: m.from, kind: 'answer', payload: JSON.stringify(ans)});
  } else if (m.kind === 'answer' && pcs[m.from]) {
    await pcs[m.from].pc.setRemoteDescription(JSON.parse(m.payload));
  } else if (m.kind === 'ice' && pcs[m.from]) {
    try { await pcs[m.from].pc.addIceCandidate(JSON.parse(m.payload)); } catch (e) {}
  } else if (m.kind === 'bye') {
    dropPeer(m.from);
  }
}
function dropPeer(peer) {
  if (pcs[peer]) { try { pcs[peer].pc.close(); } catch (e) {} delete pcs[peer]; }
  const t = $('#tile-' + peer); if (t) t.remove();
  updCnt();
}
async function poll() {
  try {
    const r = await api('rtc_poll', null, '&room=' + ROOM + '&peer=' + me + '&after=' + lastMsg);
    for (const m of r.messages) { lastMsg = Math.max(lastMsg, m.id); await handleMsg(m); }
    const alive = new Set(r.roster.map(x => x.peer));
    Object.keys(pcs).forEach(p => { if (!alive.has(p)) dropPeer(p); });
  } catch (e) {}
}

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
  if (!r.peer) { toast('Σφάλμα σύνδεσης'); return; }
  me = r.peer;
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
  r.roster.forEach(p => callPeer(p.peer, p.name));   // ο νεοφερμένος καλεί τους υπάρχοντες
  pollT = setInterval(poll, 1200);
};

/* ─── In-call controls ─── */
$('#cMic').onclick = () => { micOn = !micOn; applyToggles(); };
$('#cCam').onclick = () => { camOn = !camOn; applyToggles(); };
$('#cShare').onclick = async () => {
  if (!sharing) {
    try {
      const ds = await navigator.mediaDevices.getDisplayMedia({video: true});
      const track = ds.getVideoTracks()[0];
      Object.values(pcs).forEach(({pc}) => {
        const sn = pc.getSenders().find(s => s.track && s.track.kind === 'video');
        if (sn) sn.replaceTrack(track);
      });
      const myV = document.querySelector('#tile-' + me + ' video');
      myV.srcObject = new MediaStream([track, ...stream.getAudioTracks()]);
      myV.classList.remove('mirror');
      sharing = true; $('#cShare').classList.add('off'); toast('Μοιράζεσαι την οθόνη σου 🖥');
      track.onended = stopShare;
    } catch (e) {}
  } else stopShare();
};
function stopShare() {
  if (!sharing) return;
  sharing = false; $('#cShare').classList.remove('off');
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
function leave() {
  clearInterval(pollT);
  Object.keys(pcs).forEach(p => api('rtc_signal', {peer: me, to: p, kind: 'bye', payload: ''}));
  api('rtc_leave', {peer: me});
  Object.keys(pcs).forEach(dropPeer);
  cancelAnimationFrame(procRAF);
  if (stream) stream.getTracks().forEach(t => t.stop());
  if (rawStream) rawStream.getTracks().forEach(t => t.stop());
  document.body.innerHTML = '<div style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px"><div style="font-size:56px">👋</div><h2>Το meeting ολοκληρώθηκε</h2><button class="btn btn-p" onclick="location.reload()">Επανασύνδεση</button></div>';
}
$('#cLeave').onclick = leave;
window.addEventListener('beforeunload', () => { if (me) { navigator.sendBeacon && api('rtc_leave', {peer: me}); } });
</script>
</body>
</html>
