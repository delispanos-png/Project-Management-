<?php
/**
 * 🔗 CloudOn — Δημόσια σελίδα προόδου έργου για τον πελάτη.
 * Χωρίς credentials· πρόσβαση με signed-by-DB token, χρονικά περιορισμένη.
 * Read-only: πρόοδος, παραδοτέα, ενημερώσεις. Προαιρετικά: μηνύματα πελάτη.
 */
require __DIR__ . '/../init.php';

use WHMCS\Database\Capsule;

function share_fail($msg)
{
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<body style="font-family:system-ui,Segoe UI,sans-serif;background:#f4f7fb;margin:0;display:flex;'
        . 'min-height:100vh;align-items:center;justify-content:center;text-align:center;color:#334">'
        . '<div style="max-width:420px;padding:40px"><div style="font-size:52px">🔒</div>'
        . '<h2 style="color:#0072ad;margin:14px 0 8px">Ο σύνδεσμος δεν είναι διαθέσιμος</h2>'
        . '<p style="color:#667;line-height:1.6">' . htmlspecialchars($msg) . '</p>'
        . '<p style="margin-top:24px;font-size:13px;color:#99a">CloudOn · Project Portal</p></div></body>';
    exit;
}

$pid = (int) ($_GET['p'] ?? 0);
$tok = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? '');
if (!$pid || !$tok) {
    share_fail('Λείπουν στοιχεία του συνδέσμου.');
}

$share = Capsule::table('mod_cpm_project_shares')->where('project_id', $pid)->where('token', $tok)->first();
if (!$share || $share->revoked) {
    share_fail('Ο σύνδεσμος έχει ανακληθεί ή δεν ισχύει.');
}
if ($share->expires_at && strtotime($share->expires_at) < time()) {
    share_fail('Ο σύνδεσμος έχει λήξει.');
}
$proj = Capsule::table('mod_cpm_projects')->where('id', $pid)->first();
if (!$proj || $proj->status === 'archived') {
    share_fail('Το έργο δεν είναι πλέον διαθέσιμο.');
}

/* ---- POST: μήνυμα πελάτη ---- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($share->can_comment)) {
    $body = trim($_POST['body'] ?? '');
    $author = trim($_POST['author'] ?? '');
    if ($body !== '') {
        Capsule::table('mod_cpm_share_comments')->insert([
            'project_id' => $pid, 'author' => mb_substr($author ?: 'Πελάτης', 0, 90),
            'body' => mb_substr($body, 0, 2000), 'from_team' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        // ειδοποίηση ομάδας
        try {
            $mgrs = Capsule::table('mod_cpm_project_members')->where('project_id', $pid)->pluck('admin_id')->all();
            if (!$mgrs) {
                $mgrs = Capsule::table('tbladmins')->where('disabled', 0)->where('roleid', 1)->pluck('id')->all();
            }
            foreach (array_unique($mgrs) as $mid) {
                Capsule::table('mod_cpm_notifications')->insert([
                    'admin_id' => (int) $mid, 'type' => 'client_comment',
                    'title' => '💬 Μήνυμα πελάτη: ' . mb_substr($proj->name, 0, 40),
                    'url' => '#/projects/' . $pid, 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
        }
    }
    header('Location: share.php?p=' . $pid . '&t=' . $tok . '#msg');
    exit;
}

/* ---- view tracking (μία φορά ανά συνεδρία επισκέπτη) ---- */
$ck = 'cnp_sv_' . $pid;
if (!isset($_COOKIE[$ck])) {
    Capsule::table('mod_cpm_project_shares')->where('id', $share->id)
        ->update(['views' => $share->views + 1, 'last_view' => date('Y-m-d H:i:s')]);
    setcookie($ck, '1', time() + 6 * 3600, '/');
}

/* ---- δεδομένα έργου (client-safe) ---- */
$doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
$total = Capsule::table('mod_cpm_tasks')->where('project_id', $pid)->count();
$done = Capsule::table('mod_cpm_tasks')->where('project_id', $pid)->whereIn('status_id', $doneIds)->count();
$pct = $total ? (int) round($done / $total * 100) : 0;

$todos = Capsule::table('mod_cpm_project_todos')->where('project_id', $pid)
    ->orderBy('sort')->orderBy('id')->get();
$tDone = 0;
foreach ($todos as $t) {
    if ($t->done_at) {
        $tDone++;
    }
}
// αν υπάρχουν παραδοτέα, η πρόοδος βασίζεται σε αυτά (πιο κατανοητό για πελάτη)
if (count($todos)) {
    $pct = (int) round($tDone / count($todos) * 100);
}

$updates = Capsule::table('mod_cpm_tasks')->where('project_id', $pid)
    ->whereIn('status_id', $doneIds)->whereNotNull('completed_at')
    ->orderByDesc('completed_at')->limit(12)->get(['title', 'completed_at']);

$clientName = '';
if ($proj->clientid) {
    $c = Capsule::table('tblclients')->where('id', $proj->clientid)->first(['firstname', 'lastname', 'companyname']);
    $clientName = $c ? ($c->companyname ?: trim($c->firstname . ' ' . $c->lastname)) : '';
}

$comments = !empty($share->can_comment)
    ? Capsule::table('mod_cpm_share_comments')->where('project_id', $pid)->orderBy('created_at')->get()
    : [];

$isDone = $pct >= 100 && ($total || count($todos));
$statusLabel = $isDone ? 'Ολοκληρώθηκε' : ($pct > 0 ? 'Σε εξέλιξη' : 'Σε προετοιμασία');
$statusColor = $isDone ? '#16a26a' : ($pct > 0 ? '#0090dd' : '#eba63c');

$grDate = function ($d) {
    if (!$d) {
        return '';
    }
    $m = ['', 'Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μαΐ', 'Ιουν', 'Ιουλ', 'Αυγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ'];
    $ts = strtotime($d);
    return (int) date('j', $ts) . ' ' . $m[(int) date('n', $ts)] . ' ' . date('Y', $ts);
};
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$circ = 2 * M_PI * 52;
$off = $circ * (1 - $pct / 100);
?><!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $e($proj->name) ?> · Πρόοδος έργου — CloudOn</title>
<style>
  :root{--brand:#0090dd;--brand-d:#0072ad;--ink:#1a2433;--mut:#6b7a90;--line:#e6ecf3;--bg:#f4f7fb;--card:#fff;--ok:#16a26a}
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--ink);line-height:1.55}
  .wrap{max-width:760px;margin:0 auto;padding:0 18px 60px}
  header.top{background:linear-gradient(120deg,var(--brand-d),var(--brand));color:#fff;padding:22px 0 90px}
  .top .wrap{display:flex;align-items:center;gap:12px}
  .logo{width:40px;height:40px;border-radius:11px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:20px;backdrop-filter:blur(4px)}
  .brand-t{font-weight:800;font-size:17px;letter-spacing:.2px}.brand-t small{display:block;font-weight:500;font-size:11px;opacity:.85}
  .hero{background:var(--card);border-radius:18px;box-shadow:0 12px 32px rgba(20,50,90,.10);margin-top:-70px;padding:26px 24px;position:relative}
  .hero h1{margin:0 0 4px;font-size:23px;line-height:1.25;text-wrap:balance}
  .cli{color:var(--mut);font-size:13.5px}
  .pill{display:inline-block;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700;color:#fff;margin-top:10px}
  .ringrow{display:flex;align-items:center;gap:24px;margin-top:22px;flex-wrap:wrap}
  .ring{position:relative;width:128px;height:128px;flex:none}
  .ring svg{transform:rotate(-90deg)}
  .ring .val{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
  .ring .val b{font-size:30px;line-height:1}.ring .val span{font-size:11px;color:var(--mut)}
  .meta{flex:1;min-width:180px;display:flex;flex-direction:column;gap:10px}
  .meta .m{display:flex;justify-content:space-between;font-size:13.5px;border-bottom:1px dashed var(--line);padding-bottom:8px}
  .meta .m span{color:var(--mut)}.meta .m b{font-weight:700}
  .sec{background:var(--card);border-radius:16px;box-shadow:0 6px 18px rgba(20,50,90,.06);margin-top:16px;padding:20px 22px}
  .sec h2{margin:0 0 14px;font-size:15px;display:flex;align-items:center;gap:8px}
  .sec h2 .n{margin-left:auto;font-size:12px;font-weight:600;color:var(--mut)}
  .dl{list-style:none;margin:0;padding:0}
  .dl li{display:flex;align-items:flex-start;gap:11px;padding:9px 0;border-bottom:1px solid var(--line);font-size:14px}
  .dl li:last-child{border-bottom:0}
  .chk{width:20px;height:20px;border-radius:6px;flex:none;display:flex;align-items:center;justify-content:center;font-size:12px;margin-top:1px}
  .chk.on{background:var(--ok);color:#fff}.chk.off{border:2px solid var(--line);color:transparent}
  .dl li.d span.tt{color:var(--mut)}
  .dl li .dt{margin-left:auto;font-size:11.5px;color:var(--mut);white-space:nowrap;padding-left:8px}
  .tl{position:relative;padding-left:22px}
  .tl:before{content:"";position:absolute;left:6px;top:6px;bottom:6px;width:2px;background:var(--line)}
  .tl .ev{position:relative;padding:0 0 15px 4px}
  .tl .ev:before{content:"";position:absolute;left:-20px;top:5px;width:11px;height:11px;border-radius:50%;background:var(--brand);border:2px solid #fff;box-shadow:0 0 0 2px var(--line)}
  .tl .ev .t{font-weight:600;font-size:13.5px}.tl .ev .d{font-size:11.5px;color:var(--mut)}
  .empty{color:var(--mut);font-size:13.5px;text-align:center;padding:14px}
  .msg{border:1px solid var(--line);border-radius:12px;padding:12px 14px;margin-bottom:10px;font-size:13.5px}
  .msg.team{background:#eef7ff;border-color:#d3e9fb}
  .msg .h{font-weight:700;font-size:12px;margin-bottom:3px}.msg .h span{color:var(--mut);font-weight:500;margin-left:6px}
  form.cm{display:flex;flex-direction:column;gap:9px;margin-top:6px}
  form.cm input,form.cm textarea{font:inherit;padding:10px 12px;border:1px solid var(--line);border-radius:10px;width:100%;background:var(--bg)}
  form.cm textarea{min-height:78px;resize:vertical}
  form.cm button{align-self:flex-end;background:var(--brand);color:#fff;border:0;padding:10px 20px;border-radius:10px;font-weight:700;cursor:pointer;font-size:14px}
  footer{text-align:center;color:var(--mut);font-size:12px;margin-top:30px}
  @media(max-width:520px){.hero,.sec{padding:18px 16px}.ring{width:108px;height:108px}}
</style>
</head>
<body>
<header class="top"><div class="wrap"><div class="logo">C</div>
  <div class="brand-t">CloudOn<small>Project Portal</small></div></div></header>
<div class="wrap">
  <section class="hero">
    <h1><?= $e($proj->name) ?></h1>
    <?php if ($clientName): ?><div class="cli">για <?= $e($clientName) ?></div><?php endif; ?>
    <span class="pill" style="background:<?= $statusColor ?>"><?= $statusLabel ?></span>
    <div class="ringrow">
      <div class="ring">
        <svg width="128" height="128" viewBox="0 0 128 128">
          <circle cx="64" cy="64" r="52" fill="none" stroke="#e6ecf3" stroke-width="12"/>
          <circle cx="64" cy="64" r="52" fill="none" stroke="<?= $statusColor ?>" stroke-width="12"
            stroke-linecap="round" stroke-dasharray="<?= $circ ?>" stroke-dashoffset="<?= $off ?>"/>
        </svg>
        <div class="val"><b><?= $pct ?>%</b><span>ολοκλήρωση</span></div>
      </div>
      <div class="meta">
        <?php if ($proj->start_date): ?><div class="m"><span>Έναρξη</span><b><?= $e($grDate($proj->start_date)) ?></b></div><?php endif; ?>
        <?php if ($proj->due_date): ?><div class="m"><span>Παράδοση</span><b><?= $e($grDate($proj->due_date)) ?></b></div><?php endif; ?>
        <?php if (count($todos)): ?><div class="m"><span>Παραδοτέα</span><b><?= $tDone ?> / <?= count($todos) ?></b></div>
        <?php elseif ($total): ?><div class="m"><span>Εργασίες</span><b><?= $done ?> / <?= $total ?></b></div><?php endif; ?>
      </div>
    </div>
    <?php if (trim((string) $proj->descr)): ?>
      <p style="color:var(--mut);font-size:14px;margin:18px 0 0"><?= nl2br($e($proj->descr)) ?></p>
    <?php endif; ?>
  </section>

  <?php if (count($todos)): ?>
  <section class="sec"><h2>📦 Παραδοτέα<span class="n"><?= $tDone ?>/<?= count($todos) ?></span></h2>
    <ul class="dl">
      <?php foreach ($todos as $t): $d = (bool) $t->done_at; ?>
        <li class="<?= $d ? 'd' : '' ?>"><span class="chk <?= $d ? 'on' : 'off' ?>"><?= $d ? '✓' : '' ?></span>
          <span class="tt"><?= $e($t->title) ?></span>
          <?php if ($d): ?><span class="dt"><?= $e($grDate($t->done_at)) ?></span><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="sec"><h2>🕑 Πρόσφατες ενημερώσεις</h2>
    <?php if (count($updates)): ?>
      <div class="tl"><?php foreach ($updates as $u): ?>
        <div class="ev"><div class="t"><?= $e($u->title) ?></div><div class="d"><?= $e($grDate($u->completed_at)) ?></div></div>
      <?php endforeach; ?></div>
    <?php else: ?><div class="empty">Δεν υπάρχουν ακόμη καταχωρημένες ενημερώσεις.</div><?php endif; ?>
  </section>

  <?php if (!empty($share->can_comment)): ?>
  <section class="sec" id="msg"><h2>💬 Επικοινωνία</h2>
    <?php foreach ($comments as $c): ?>
      <div class="msg <?= $c->from_team ? 'team' : '' ?>">
        <div class="h"><?= $c->from_team ? '🛟 ' : '' ?><?= $e($c->author) ?><span><?= $e($grDate($c->created_at)) ?></span></div>
        <?= nl2br($e($c->body)) ?></div>
    <?php endforeach; ?>
    <form class="cm" method="post" action="share.php?p=<?= $pid ?>&t=<?= $e($tok) ?>">
      <input type="text" name="author" maxlength="90" placeholder="Το όνομά σας (προαιρετικό)">
      <textarea name="body" maxlength="2000" placeholder="Γράψτε ένα μήνυμα στην ομάδα…" required></textarea>
      <button type="submit">Αποστολή</button>
    </form>
  </section>
  <?php endif; ?>

  <footer>Ενημερώθηκε <?= $e($grDate(date('Y-m-d'))) ?> · CloudOn Project Portal<br>
    <span style="opacity:.7">Αυτή η σελίδα ενημερώνεται αυτόματα με την πρόοδο του έργου.</span></footer>
</div>
</body>
</html>
