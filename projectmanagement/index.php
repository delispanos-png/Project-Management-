<?php
/**
 * CloudOn Projects — standalone web app (SPA shell).
 * Auth: δικό του session μέσω signed token handoff από το WHMCS admin.
 */

require_once __DIR__ . '/boot.php';

// Ποτέ cache του HTML shell → ο browser παίρνει πάντα το τρέχον ?v= των scripts (τέλος τα «δεν βλέπω αλλαγές»)
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Αποσύνδεση: καθάρισε το app session και γύρνα στην οθόνη εισόδου
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'] ?: '/', $p['domain'] ?? '', !empty($p['secure']), !empty($p['httponly']));
    }
    @session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$adminId = pm_admin_id();
if ($adminId <= 0) {
    // δεν υπάρχει έγκυρο app session → οδηγίες εισόδου μέσω WHMCS admin
    $loginUrl = '/cloudonadminpanel/addonmodules.php?module=cloudonprojects&pmlaunch=1';
    ?><!DOCTYPE html><html lang="el"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><title>CloudOn Projects — Έξυπνη διαχείριση έργων</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%230090dd'/><text x='50' y='68' font-size='52' text-anchor='middle' fill='white' font-family='Arial' font-weight='bold'>P</text></svg>">
    <style>
    *{box-sizing:border-box}
    :root{--bg:#0a1220;--bg2:#0e1a2e;--ink:#eaf2ff;--mut:#8091ad;--cyan:#22b8ff;--blue:#0090dd}
    html,body{margin:0;height:100%}
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden;
      background:radial-gradient(1200px 700px at 50% -10%,#12233d 0%,var(--bg2) 42%,var(--bg) 100%);
      font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
      color:var(--ink);-webkit-font-smoothing:antialiased;position:relative}
    /* ── aurora orbs ── */
    .orb{position:fixed;border-radius:50%;filter:blur(70px);opacity:.5;z-index:0;pointer-events:none;will-change:transform}
    .orb.a{width:520px;height:520px;background:radial-gradient(circle,#0090dd,transparent 70%);top:-160px;left:-120px;animation:drift1 22s ease-in-out infinite}
    .orb.b{width:460px;height:460px;background:radial-gradient(circle,#00d4ff,transparent 70%);bottom:-180px;right:-120px;animation:drift2 26s ease-in-out infinite}
    .orb.c{width:360px;height:360px;background:radial-gradient(circle,#5b8cff,transparent 70%);top:40%;left:60%;opacity:.35;animation:drift3 30s ease-in-out infinite}
    @keyframes drift1{0%,100%{transform:translate(0,0)}50%{transform:translate(80px,60px)}}
    @keyframes drift2{0%,100%{transform:translate(0,0)}50%{transform:translate(-70px,-50px)}}
    @keyframes drift3{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(-60px,40px) scale(1.12)}}
    /* subtle grid */
    body::before{content:"";position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.05;
      background-image:linear-gradient(#7fb8ff 1px,transparent 1px),linear-gradient(90deg,#7fb8ff 1px,transparent 1px);
      background-size:46px 46px;mask-image:radial-gradient(circle at 50% 45%,#000 0%,transparent 70%)}
    /* ── content ── */
    .wrap{position:relative;z-index:2;width:100%;max-width:620px;padding:32px 26px;text-align:center}
    .logo{width:88px;height:88px;border-radius:26px;margin:0 auto 26px;display:flex;align-items:center;justify-content:center;
      background:linear-gradient(135deg,#28c0ff,#0072ad);color:#fff;font-size:46px;font-weight:800;
      box-shadow:0 20px 50px rgba(0,144,221,.55),inset 0 2px 6px rgba(255,255,255,.35);
      animation:pop .7s cubic-bezier(.2,.8,.2,1) both,float 5s ease-in-out 1s infinite}
    .eyebrow{display:inline-block;font-size:12px;font-weight:800;letter-spacing:3.5px;color:var(--cyan);
      text-transform:uppercase;margin-bottom:14px;padding:5px 13px;border:1px solid #22b8ff40;border-radius:999px;background:#22b8ff12}
    h1{margin:0 0 14px;font-size:clamp(30px,6vw,46px);font-weight:800;letter-spacing:-1.2px;line-height:1.05;
      background:linear-gradient(100deg,#fff 25%,#7fe0ff 50%,#fff 75%);background-size:200% auto;
      -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
      animation:shine 6s linear infinite}
    .tag{color:#a9bad6;font-size:clamp(15px,2.4vw,18px);line-height:1.55;margin:0 auto 30px;max-width:460px}
    .feats{display:flex;flex-wrap:wrap;gap:11px;justify-content:center;margin:0 auto 34px;max-width:540px}
    .feat{display:flex;align-items:center;gap:9px;padding:11px 15px;border-radius:13px;font-size:13.5px;font-weight:600;
      color:#d3e2f7;background:rgba(255,255,255,.045);border:1px solid rgba(140,180,255,.14);backdrop-filter:blur(6px)}
    .feat svg{width:19px;height:19px;stroke:var(--cyan);flex:none}
    .cta{display:inline-flex;align-items:center;gap:10px;text-decoration:none;color:#fff;font-weight:800;font-size:16px;
      padding:15px 34px;border-radius:15px;background:linear-gradient(135deg,#22b4ff,#0086cf);position:relative;overflow:hidden;
      box-shadow:0 14px 34px rgba(0,150,221,.5);transition:transform .18s ease,box-shadow .18s ease}
    .cta:hover{transform:translateY(-2px);box-shadow:0 20px 46px rgba(0,150,221,.62)}
    .cta:active{transform:translateY(0)}
    .cta::after{content:"";position:absolute;top:0;left:-120%;width:60%;height:100%;
      background:linear-gradient(100deg,transparent,rgba(255,255,255,.4),transparent);transform:skewX(-18deg);animation:sweep 3.6s ease-in-out 1.4s infinite}
    .cta svg{width:18px;height:18px;transition:transform .18s ease}.cta:hover svg{transform:translateX(4px)}
    .note{margin-top:20px;color:var(--mut);font-size:12.5px;display:flex;align-items:center;gap:7px;justify-content:center}
    .note svg{width:14px;height:14px;stroke:var(--mut)}
    /* entrance stagger */
    .rv{opacity:0;transform:translateY(18px);animation:rise .7s cubic-bezier(.2,.8,.2,1) forwards}
    .d1{animation-delay:.15s}.d2{animation-delay:.28s}.d3{animation-delay:.4s}.d4{animation-delay:.52s}.d5{animation-delay:.66s}.d6{animation-delay:.8s}
    @keyframes rise{to{opacity:1;transform:none}}
    @keyframes pop{from{opacity:0;transform:scale(.6) rotate(-8deg)}to{opacity:1;transform:scale(1) rotate(0)}}
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
    @keyframes shine{to{background-position:200% center}}
    @keyframes sweep{0%{left:-120%}55%,100%{left:130%}}
    @media (prefers-reduced-motion:reduce){*{animation:none!important}.rv{opacity:1;transform:none}}
    </style></head>
    <body>
    <div class="orb a"></div><div class="orb b"></div><div class="orb c"></div>
    <div class="wrap">
      <div class="logo">P</div>
      <div class="eyebrow rv d1">Έξυπνη διαχείριση έργων</div>
      <h1 class="rv d2">CloudOn Projects</h1>
      <p class="tag rv d3">Έργα, tickets, πωλήσεις και χρόνος — όλα σε μία έξυπνη πλατφόρμα που κρατά την ομάδα σου συντονισμένη.</p>
      <div class="feats rv d4">
        <div class="feat"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="5" height="16" rx="1"/><rect x="10" y="4" width="5" height="10" rx="1"/><rect x="17" y="4" width="4" height="13" rx="1"/></svg> Projects & Kanban</div>
        <div class="feat"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 8.4V6a2 2 0 0 0-2-2H5.4a2 2 0 0 0-2 2v2.4a2 2 0 0 1 0 7.2V18a2 2 0 0 0 2 2h13.2a2 2 0 0 0 2-2v-2.4a2 2 0 0 1 0-7.2Z"/><path d="M9 4v16"/></svg> Tickets & SLA</div>
        <div class="feat"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg> CRM & Πωλήσεις</div>
        <div class="feat"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/></svg> Χρόνος & Χρεώσεις</div>
      </div>
      <a class="cta rv d5" href="<?= $loginUrl ?>">Είσοδος στην εφαρμογή
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
      <div class="note rv d6"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg> Ασφαλής σύνδεση μέσω WHMCS admin (SSO)</div>
    </div>
    </body></html><?php
    exit;
}

// καθάρισε το ?t= από το URL μετά το handoff
if (isset($_GET['t'])) {
    header('Location: /project/');
    exit;
}

$v = '1.0.' . max(@filemtime(__DIR__ . '/app.js'), @filemtime(__DIR__ . '/app.css'), @filemtime(__DIR__ . '/views2.js'), @filemtime(__DIR__ . '/views3.js'), @filemtime(__DIR__ . '/views4.js'), @filemtime(__DIR__ . '/views5.js'));
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>CloudOn Projects</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%230090dd'/><text x='50' y='68' font-size='52' text-anchor='middle' fill='white' font-family='Arial' font-weight='bold'>P</text></svg>">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#131e33">
<link rel="apple-touch-icon" href="icon.svg">
<link rel="stylesheet" href="app.css?v=<?= htmlspecialchars($v) ?>">
</head>
<body>
<div id="app" aria-live="polite">
  <div class="boot"><div class="boot-logo">P</div><div class="boot-txt">CloudOn Projects</div><div class="boot-bar"><span></span></div></div>
</div>
<script>if("serviceWorker" in navigator)navigator.serviceWorker.register("/project/sw.js").catch(()=>{});</script>
<script type="module" src="app.js?v=<?= htmlspecialchars($v) ?>"></script>
<script type="module" src="views2.js?v=<?= htmlspecialchars($v) ?>"></script>
<script type="module" src="views3.js?v=<?= htmlspecialchars($v) ?>"></script>
<script type="module" src="views4.js?v=<?= htmlspecialchars($v) ?>"></script>
<script type="module" src="views5.js?v=<?= htmlspecialchars($v) ?>"></script>
</body>
</html>
