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
    <meta name="viewport" content="width=device-width, initial-scale=1"><title>CloudOn Projects</title>
    <style>body{margin:0;height:100vh;display:flex;align-items:center;justify-content:center;background:#0e1626;
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}
    .b{text-align:center;color:#c9d5e8}.l{width:70px;height:70px;border-radius:20px;margin:0 auto 20px;
    background:linear-gradient(135deg,#00a8ff,#0072ad);color:#fff;font-size:38px;font-weight:800;
    display:flex;align-items:center;justify-content:center;box-shadow:0 12px 36px rgba(0,144,221,.5)}
    h1{font-size:20px;margin:0 0 8px;color:#fff}p{color:#7d8ba6;margin:0 0 22px}
    a{display:inline-block;background:linear-gradient(135deg,#00a8ff,#0090dd);color:#fff;text-decoration:none;
    padding:12px 26px;border-radius:12px;font-weight:700;box-shadow:0 8px 22px rgba(0,144,221,.4)}</style></head>
    <body><div class="b"><div class="l">P</div><h1>CloudOn Projects</h1>
    <p>Άνοιξε την εφαρμογή από το WHMCS admin (SSO).</p>
    <a href="<?= $loginUrl ?>">Είσοδος →</a></div></body></html><?php
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
