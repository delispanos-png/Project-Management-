<?php
/**
 * CloudOn Careers — δημόσια σελίδα (premium). Isolated DB load (δεν πειράζει admin sessions).
 * Ροή: landing (hero → γιατί εμάς → ανοιχτές θέσεις με modal ανάλυσης) →
 *      «Εκδήλωση ενδιαφέροντος» → ΞΕΧΩΡΙΣΤΗ σελίδα φόρμας με assigned θέση (apply.php?job=ID).
 * Νέες αιτήσεις (source=form) αξιολογούνται από το cron cv_autoeval.php.
 */

$__cookies = $_COOKIE;
$_COOKIE = [];
@ini_set('session.use_cookies', '0');
@ini_set('session.use_only_cookies', '0');
define('WHMCS', true);
require __DIR__ . '/../init.php';
if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
$_COOKIE = $__cookies;

use WHMCS\Database\Capsule;
require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/CvPhoto.php';
use WHMCS\Module\Addon\CloudonProjects\CvPhoto;

$CVDIR = __DIR__ . '/../attachments/cloudonprojects';
$err = ''; $done = false; $doneJob = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hp = trim($_POST['website'] ?? '');
    $ts = (int) ($_POST['ts'] ?? 0);
    $elapsed = time() - $ts;
    if ($hp !== '' || $ts <= 0 || $elapsed < 3 || $elapsed > 7200) {
        $err = 'Η υποβολή απέτυχε. Δοκίμασε ξανά.';
    } else {
        $general = !empty($_POST['general']);
        $jobId = (int) ($_POST['job'] ?? 0);
        $job = $jobId ? Capsule::table('mod_cpm_cv_jobs')->where('id', $jobId)->where('active', 1)->first() : null;
        $name = mb_substr(trim($_POST['name'] ?? ''), 0, 150);
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone = mb_substr(trim($_POST['phone'] ?? ''), 0, 50);
        $letter = mb_substr(trim($_POST['letter'] ?? ''), 0, 6000);
        $f = $_FILES['cv'] ?? null;
        if (!$general && !$job) { $err = 'Επίλεξε έγκυρη θέση.'; }
        elseif ($name === '') { $err = 'Συμπλήρωσε το ονοματεπώνυμό σου.'; }
        elseif (!$email) { $err = 'Συμπλήρωσε έγκυρο email.'; }
        elseif (!$f || $f['error'] !== UPLOAD_ERR_OK) { $err = 'Ανέβασε το βιογραφικό σου (PDF).'; }
        elseif ($f['size'] > 15 * 1024 * 1024) { $err = 'Το αρχείο ξεπερνά τα 15MB.'; }
        else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['php', 'phtml', 'phar', 'cgi', 'sh', 'exe', 'htaccess', 'html', 'htm', 'svg'], true)) {
                $err = 'Μη επιτρεπτός τύπος αρχείου. Ανέβασε PDF ή Word.';
            } else {
                if (!is_dir($CVDIR)) { @mkdir($CVDIR, 0750, true); }
                $stored = uniqid('cv', true) . '.' . (preg_replace('/[^a-z0-9]/', '', $ext) ?: 'pdf');
                if (!move_uploaded_file($f['tmp_name'], $CVDIR . '/' . $stored)) {
                    $err = 'Σφάλμα αποθήκευσης. Δοκίμασε ξανά.';
                } else {
                    $mime = $ext === 'pdf' ? 'application/pdf' : (($ext === 'doc' || $ext === 'docx') ? 'application/msword' : ($f['type'] ?: 'application/octet-stream'));
                    $id = Capsule::table('mod_cpm_cv')->insertGetId([
                        'source' => 'form', 'name' => $name, 'email' => $email, 'phone' => $phone,
                        'job_id' => $job ? (int) $job->id : 0, 'job_title' => $job ? mb_substr($job->title, 0, 190) : 'Γενική αίτηση', 'letter' => $letter,
                        'cv_stored' => $stored, 'cv_name' => mb_substr($f['name'], 0, 190), 'cv_mime' => $mime, 'status' => 'new',
                        'applied_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    if ($mime === 'application/pdf') {
                        try { $ph = CvPhoto::extract($CVDIR . '/' . $stored, $CVDIR, $id); if ($ph !== '') { Capsule::table('mod_cpm_cv')->where('id', $id)->update(['photo' => $ph]); } } catch (\Throwable $e) {}
                    }
                    $done = true; $doneJob = $job ? $job->title : '';
                }
            }
        }
    }
}

$jobs = Capsule::table('mod_cpm_cv_jobs')->where('active', 1)->orderBy('title')->get();
$now = time();
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$SELF = 'https://my.cloudon.gr/project/apply.php';

/** Μορφοποίηση περιγραφής αγγελίας (headers/bullets/paragraphs) — ασφαλές HTML. */
$fmtDesc = function ($t) use ($e) {
    $t = trim((string) $t);
    if ($t === '') { return '<p class="mut">—</p>'; }
    $out = ''; $inUl = false;
    foreach (preg_split('/\r?\n/', $t) as $ln) {
        $s = trim($ln);
        if ($s === '' || preg_match('/^[-–—_=*]{2,}$/u', $s)) { if ($inUl) { $out .= '</ul>'; $inUl = false; } continue; }
        if (preg_match('/^[-•·]\s+(.*)/u', $s, $m)) { if (!$inUl) { $out .= '<ul>'; $inUl = true; } $out .= '<li>' . $e($m[1]) . '</li>'; continue; }
        if ($inUl) { $out .= '</ul>'; $inUl = false; }
        $letters = preg_replace('/[^\p{L}]/u', '', $s);
        $isHead = $letters !== '' && mb_strtoupper($letters, 'UTF-8') === $letters && mb_strlen($s) <= 64;
        $out .= $isHead ? '<h4>' . $e(rtrim($s, ':')) . '</h4>' : '<p>' . $e($s) . '</p>';
    }
    if ($inUl) { $out .= '</ul>'; }
    return $out;
};
$skillChips = function ($csv) use ($e) {
    $out = '';
    foreach (preg_split('/[,\n·]+/', (string) $csv) as $s) { $s = trim($s); if ($s !== '') { $out .= '<span class="chip">' . $e($s) . '</span>'; } }
    return $out;
};

// ── Ποια όψη; landing | form (ανά θέση/γενική) | thank-you ──
$postedJob = ($_SERVER['REQUEST_METHOD'] === 'POST' && !$done) ? (int) ($_POST['job'] ?? 0) : 0;
$reqJobId = $postedJob ?: (ctype_digit((string) ($_GET['job'] ?? '')) ? (int) $_GET['job'] : 0);
$formJob = null;
foreach ($jobs as $jj) { if ((int) $jj->id === $reqJobId) { $formJob = $jj; break; } }
$formGeneral = !$done && (($_GET['job'] ?? '') === 'general' || (!empty($_POST['general']) && $_SERVER['REQUEST_METHOD'] === 'POST'));
$mode = $done ? 'done' : (($formJob || $formGeneral) ? 'form' : 'landing');

// SEO
$titles = []; foreach ($jobs as $jj) { $titles[] = $jj->title; }
$metaDesc = count($jobs)
    ? 'Ανοιχτές θέσεις εργασίας στην CloudOn: ' . mb_substr(implode(', ', $titles), 0, 150) . '. Στείλε το βιογραφικό σου online.'
    : 'Καριέρα στην CloudOn — στείλε μας το βιογραφικό σου για μελλοντικές ευκαιρίες.';
$ld = [];
foreach ($jobs as $jj) {
    $ld[] = ['@context' => 'https://schema.org/', '@type' => 'JobPosting', 'title' => $jj->title,
        'description' => '<p>' . htmlspecialchars((string) ($jj->descr ?: $jj->title) . ($jj->skills ? ' Δεξιότητες: ' . $jj->skills : '')) . '</p>',
        'datePosted' => date('Y-m-d', strtotime((string) ($jj->created_at ?: 'now'))),
        'employmentType' => stripos((string) $jj->emptype, 'μερικ') !== false ? 'PART_TIME' : 'FULL_TIME',
        'hiringOrganization' => ['@type' => 'Organization', 'name' => 'CloudOn', 'sameAs' => 'https://cloudon.gr'],
        'directApply' => true,
        'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $jj->location ?: 'Αθήνα', 'addressCountry' => 'GR']]];
}
// Δεδομένα θέσεων για το modal ανάλυσης (client-side)
$jobsData = [];
foreach ($jobs as $jj) {
    $jobsData[(int) $jj->id] = ['title' => $jj->title, 'location' => $jj->location, 'emptype' => $jj->emptype,
        'skills' => $skillChips($jj->skills), 'html' => $fmtDesc($jj->descr)];
}
?><!DOCTYPE html><html lang="el"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Καριέρα &amp; Θέσεις Εργασίας — CloudOn</title>
<meta name="description" content="<?= $e($metaDesc) ?>">
<link rel="canonical" href="<?= $SELF ?>">
<meta name="robots" content="index,follow">
<meta property="og:type" content="website">
<meta property="og:title" content="Καριέρα στην CloudOn">
<meta property="og:description" content="<?= $e($metaDesc) ?>">
<meta property="og:url" content="<?= $SELF ?>">
<meta property="og:image" content="https://my.cloudon.gr/project/apply-assets/office.jpg">
<meta name="twitter:card" content="summary_large_image">
<?php foreach ($ld as $l): ?>
<script type="application/ld+json"><?= json_encode($l, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endforeach; ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%230090dd'/><text x='50' y='68' font-size='52' text-anchor='middle' fill='white' font-family='Arial' font-weight='bold'>C</text></svg>">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--brand:#0090dd;--brand2:#22b4ff;--brand-d:#0072ad;--ink:#0f2038;--txt:#43536b;--mut:#8093ac;--line:#e6ecf3;--bg:#f4f7fb;--card:#fff;--ok:#16a26a;--bad:#e2515f;--navy:#0b1b30}
html{scroll-behavior:smooth}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:var(--bg);color:var(--txt);line-height:1.6;-webkit-font-smoothing:antialiased}
img{max-width:100%;display:block}
.container{max-width:1080px;margin:0 auto;padding:0 20px}
.mut{color:var(--mut)}
.btn{display:inline-flex;align-items:center;gap:9px;background:linear-gradient(135deg,var(--brand2),var(--brand-d));color:#fff;border:0;border-radius:13px;padding:14px 26px;font-size:16px;font-weight:800;cursor:pointer;text-decoration:none;box-shadow:0 14px 32px -8px rgba(0,144,221,.55);transition:transform .16s,box-shadow .16s}
.btn:hover{transform:translateY(-2px);box-shadow:0 20px 44px -8px rgba(0,144,221,.65)}
.btn.ghost{background:rgba(255,255,255,.12);box-shadow:none;border:1px solid rgba(255,255,255,.35)}
.btn.o{background:#fff;color:var(--brand-d);border:1.5px solid var(--line);box-shadow:none}
.btn.o:hover{border-color:var(--brand);box-shadow:0 8px 20px -10px rgba(0,144,221,.4)}
.btn.sm{padding:10px 18px;font-size:14px;border-radius:11px}
.brand{display:inline-flex;background:#fff;border-radius:11px;padding:8px 14px;box-shadow:0 6px 20px -8px rgba(0,0,0,.4)}
.brand img{height:30px;display:block}
/* ── HERO ── */
.hero{position:relative;color:#fff;overflow:hidden;padding:26px 0 96px}
.hero::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(9,20,38,.78),rgba(9,20,38,.92)),url('apply-assets/office.jpg') center/cover;z-index:-2}
.hero::after{content:"";position:absolute;top:-140px;right:-120px;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle,#22b4ff55,transparent 70%);filter:blur(40px);z-index:-1}
.nav{display:flex;align-items:center;justify-content:space-between;padding:8px 0 0}
.nav .links{display:flex;gap:8px}
.hero-in{max-width:720px;margin-top:64px}
.eyebrow{display:inline-block;font-size:12px;font-weight:800;letter-spacing:3px;text-transform:uppercase;color:#7fe0ff;background:#22b4ff1a;border:1px solid #22b4ff44;border-radius:999px;padding:6px 15px;margin-bottom:18px}
.hero h1{font-size:clamp(32px,6vw,50px);line-height:1.06;letter-spacing:-1.2px;color:#fff;margin-bottom:16px;font-weight:800}
.hero h1 .g{background:linear-gradient(100deg,#7fe0ff,#fff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.hero p{font-size:clamp(16px,2.4vw,19px);color:#bcd3ea;max-width:560px;margin-bottom:26px}
.hero .cta{display:flex;gap:12px;flex-wrap:wrap}
.stats{display:flex;gap:30px;margin-top:44px;flex-wrap:wrap}
.stats .s b{display:block;font-size:26px;color:#fff;font-weight:800}
.stats .s span{font-size:12.5px;color:#8fb0d0}
/* ── SECTIONS ── */
.section{padding:64px 0}
.section h2{font-size:clamp(24px,4vw,34px);color:var(--ink);letter-spacing:-.6px;text-align:center;margin-bottom:8px;font-weight:800}
.section .sub{text-align:center;color:var(--mut);max-width:560px;margin:0 auto 40px;font-size:15px}
.values{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
.val{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:0 10px 30px -20px rgba(15,32,56,.4)}
.val .ic{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#e8f6ff,#d3ecff);display:flex;align-items:center;justify-content:center;margin-bottom:14px}
.val .ic svg{width:24px;height:24px;stroke:var(--brand-d)}
.val h3{font-size:17px;color:var(--ink);margin-bottom:6px}
.val p{font-size:13.5px;color:var(--txt)}
.band{position:relative;border-radius:22px;overflow:hidden;margin:8px 0 0;min-height:280px;display:flex;align-items:flex-end;color:#fff}
.band img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2}
.band::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(9,20,38,.85),rgba(9,20,38,.25));z-index:-1}
.band .txt{padding:30px 34px;max-width:520px}
.band .txt h3{font-size:24px;margin-bottom:8px}
.band .txt p{color:#cfe0f0;font-size:14.5px}
/* jobs */
.jobs{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:18px}
.job{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px 22px 20px;box-shadow:0 10px 30px -20px rgba(15,32,56,.4);display:flex;flex-direction:column;transition:transform .16s,box-shadow .16s,border-color .16s}
.job:hover{transform:translateY(-3px);box-shadow:0 22px 46px -22px rgba(15,32,56,.5);border-color:#bfe2f7}
.job h3{font-size:18px;color:var(--ink);margin-bottom:8px}
.job .meta{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.tag{font-size:11.5px;font-weight:700;color:var(--brand-d);background:#e8f6ff;border-radius:999px;padding:4px 11px}
.job p.d{font-size:13.5px;color:var(--txt);margin-bottom:14px;flex:1;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.chips{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px}
.chip{font-size:10.5px;font-weight:600;color:var(--txt);background:#f1f5fa;border:1px solid var(--line);border-radius:8px;padding:3px 9px}
.job .acts{display:flex;gap:8px;flex-wrap:wrap}
/* job description (modal + form ctx) */
.jobdesc h4{font-size:12.5px;letter-spacing:.6px;color:var(--brand-d);margin:18px 0 7px;font-weight:800;text-transform:uppercase}
.jobdesc h4:first-child{margin-top:0}
.jobdesc p{font-size:14px;color:var(--txt);margin:0 0 9px}
.jobdesc ul{margin:0 0 13px;padding-left:20px}
.jobdesc li{font-size:14px;color:var(--txt);margin:4px 0}
/* modal */
.modal{position:fixed;inset:0;background:rgba(9,20,38,.62);backdrop-filter:blur(3px);display:none;z-index:100;padding:16px;overflow-y:auto}
.modal.show{display:block}
.modal-box{background:var(--card);border-radius:22px;max-width:680px;width:100%;margin:5vh auto 40px;padding:34px 34px 30px;position:relative;box-shadow:0 30px 90px -20px rgba(0,0,0,.55)}
.modal-x{position:absolute;top:15px;right:15px;background:var(--bg);border:0;width:38px;height:38px;border-radius:50%;font-size:16px;cursor:pointer;color:var(--txt);line-height:1}
.modal-x:hover{background:#e6ecf3}
#jmTitle{font-size:24px;color:var(--ink);letter-spacing:-.4px;margin-bottom:10px;padding-right:34px}
#jmMeta{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
#jmSkills{display:flex;gap:6px;flex-wrap:wrap;margin:16px 0 22px}
.modal-foot{display:flex;gap:10px;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:20px;margin-top:22px}
/* form page */
.applyhead{background:var(--navy);color:#fff;padding:15px 0}
.applyhead .container{display:flex;align-items:center;justify-content:space-between;gap:12px}
.backlink{color:#bcd3ea;text-decoration:none;font-size:14px;font-weight:700}
.backlink:hover{color:#fff}
.applywrap{max-width:720px;margin:0 auto;padding:40px 20px 64px}
.jobctx{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:26px 28px;margin-bottom:18px;box-shadow:0 10px 30px -20px rgba(15,32,56,.4)}
.jobctx .assigned{display:inline-block;font-size:11px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:var(--brand-d);background:#e8f6ff;border-radius:999px;padding:5px 13px;margin-bottom:12px}
.jobctx h1{font-size:26px;color:var(--ink);letter-spacing:-.5px;margin-bottom:10px}
.jobctx .meta{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
.jobctx details{margin-top:6px;border-top:1px solid var(--line);padding-top:14px}
.jobctx summary{cursor:pointer;font-weight:700;color:var(--brand-d);font-size:14px;list-style:none;display:flex;align-items:center;gap:6px}
.jobctx summary::-webkit-details-marker{display:none}
.jobctx summary::before{content:"▸";transition:transform .15s}
.jobctx details[open] summary::before{transform:rotate(90deg)}
.jobctx .jobdesc{margin-top:14px}
/* form */
.card{background:var(--card);border:1px solid var(--line);border-radius:20px;box-shadow:0 20px 60px -30px rgba(15,32,56,.5);padding:30px}
label{display:block;font-size:12px;font-weight:700;color:var(--mut);text-transform:uppercase;letter-spacing:.4px;margin:15px 0 5px}
input,select,textarea{width:100%;font:inherit;font-size:15px;padding:12px 14px;border:1px solid var(--line);border-radius:12px;background:#fbfdff;color:var(--ink)}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(0,144,221,.14)}
.row{display:flex;gap:14px;flex-wrap:wrap}.row>div{flex:1;min-width:200px}
.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.card .btn{width:100%;justify-content:center;margin-top:22px}
.note{font-size:12px;color:var(--mut);margin-top:10px;text-align:center}
.alert{padding:12px 15px;border-radius:11px;margin-bottom:16px;font-size:14px;background:#fdecec;color:#b3323f;border:1px solid #f5c2c7}
.done{text-align:center;padding:20px 6px}
.done .ic{width:72px;height:72px;border-radius:50%;background:#16a26a1a;color:var(--ok);font-size:38px;display:flex;align-items:center;justify-content:center;margin:0 auto 18px}
.done h2{color:var(--ink);margin-bottom:8px}
.empty{text-align:center;color:var(--mut);padding:20px}
.foot{background:var(--navy);color:#8fb0d0;text-align:center;padding:30px 20px;font-size:13px}
.foot .brand{margin:0 auto 12px}.foot .brand img{height:24px}
.foot a{color:#bcd3ea}
@media(max-width:640px){.hero-in{margin-top:38px}.section{padding:44px 0}.modal-box{padding:26px 22px}}
</style></head>
<body>

<?php if ($mode === 'form'): // ── ΣΕΛΙΔΑ ΦΟΡΜΑΣ (assigned θέση) ── ?>
<header class="applyhead"><div class="container">
  <span class="brand"><img src="apply-assets/cloudon-logo.svg" alt="CloudOn"></span>
  <a class="backlink" href="apply.php">← Όλες οι θέσεις</a>
</div></header>
<div class="applywrap">
  <div class="jobctx">
    <span class="assigned">✓ Θέση που επέλεξες</span>
    <h1><?= $e($formJob ? $formJob->title : 'Γενική αίτηση') ?></h1>
    <?php if ($formJob): ?>
      <div class="meta">
        <?php if ($formJob->location): ?><span class="tag">📍 <?= $e($formJob->location) ?></span><?php endif; ?>
        <?php if ($formJob->emptype): ?><span class="tag"><?= $e($formJob->emptype) ?></span><?php endif; ?>
      </div>
      <?php if (trim((string) $formJob->skills) !== ''): ?><div class="chips" style="margin-bottom:4px"><?= $skillChips($formJob->skills) ?></div><?php endif; ?>
      <?php if (trim((string) $formJob->descr) !== ''): ?>
      <details><summary>Δες ολόκληρη την αγγελία</summary><div class="jobdesc"><?= $fmtDesc($formJob->descr) ?></div></details>
      <?php endif; ?>
    <?php else: ?>
      <p class="mut" style="font-size:14px">Δεν βρήκες τη θέση που ταιριάζει; Στείλε μας το βιογραφικό σου και θα σε έχουμε υπόψη για μελλοντικές ευκαιρίες.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="font-size:19px;color:var(--ink);margin-bottom:4px">Εκδήλωσε το ενδιαφέρον σου</h2>
    <p class="mut" style="font-size:13px;margin-bottom:6px">Συμπλήρωσε τα στοιχεία σου & ανέβασε το βιογραφικό. Λίγα λεπτά μόνο.</p>
    <?php if ($err): ?><div class="alert" style="margin-top:12px"><?= $e($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" autocomplete="on" action="apply.php?job=<?= $formJob ? (int) $formJob->id : 'general' ?>">
      <input type="hidden" name="ts" value="<?= $now ?>">
      <?php if ($formJob): ?><input type="hidden" name="job" value="<?= (int) $formJob->id ?>"><?php else: ?><input type="hidden" name="general" value="1"><?php endif; ?>
      <div class="hp"><label>Website</label><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <div class="row">
        <div><label>Ονοματεπώνυμο *</label><input type="text" name="name" required maxlength="150" value="<?= $e($_POST['name'] ?? '') ?>"></div>
        <div><label>Email *</label><input type="email" name="email" required maxlength="150" value="<?= $e($_POST['email'] ?? '') ?>"></div>
      </div>
      <label>Τηλέφωνο</label><input type="tel" name="phone" maxlength="50" value="<?= $e($_POST['phone'] ?? '') ?>">
      <label>Βιογραφικό (PDF προτιμότερο) *</label><input type="file" name="cv" accept=".pdf,.doc,.docx" required>
      <label>Λίγα λόγια για σένα (προαιρετικά)</label><textarea name="letter" rows="4" maxlength="6000" placeholder="Γιατί σε ενδιαφέρει η θέση, τι σε ξεχωρίζει…"><?= $e($_POST['letter'] ?? '') ?></textarea>
      <button class="btn" type="submit">Αποστολή αίτησης →</button>
      <div class="note">Με την υποβολή, συναινείς στην επεξεργασία των στοιχείων σου για σκοπούς πρόσληψης.</div>
    </form>
  </div>
</div>

<?php elseif ($done): // ── THANK YOU ── ?>
<header class="applyhead"><div class="container">
  <span class="brand"><img src="apply-assets/cloudon-logo.svg" alt="CloudOn"></span>
  <a class="backlink" href="apply.php">← Όλες οι θέσεις</a>
</div></header>
<div class="applywrap">
  <div class="card"><div class="done"><div class="ic">✓</div>
    <h2>Λάβαμε την αίτησή σου!</h2>
    <p>Σε ευχαριστούμε για το ενδιαφέρον σου<?= $doneJob ? ' για τη θέση «' . $e($doneJob) . '»' : '' ?>. Θα εξετάσουμε το βιογραφικό σου και θα επικοινωνήσουμε μαζί σου αν προχωρήσουμε.</p>
    <a class="btn sm" style="margin-top:18px" href="apply.php">Δες κι άλλες θέσεις</a></div></div>
</div>

<?php else: // ── LANDING ── ?>
<header class="hero">
  <div class="container">
    <nav class="nav">
      <span class="brand"><img src="apply-assets/cloudon-logo.svg" alt="CloudOn"></span>
      <div class="links"><a class="btn ghost sm" href="#jobs">Ανοιχτές θέσεις</a></div>
    </nav>
    <div class="hero-in">
      <span class="eyebrow">Καριέρα στην CloudOn</span>
      <h1>Χτίσε την καριέρα σου <span class="g">σε μια ομάδα που εξελίσσεται.</span></h1>
      <p>Δουλεύουμε με σύγχρονη τεχνολογία, πραγματικό αντίκτυπο και ανθρώπους που στηρίζουν ο ένας τον άλλον. Έλα να μεγαλώσουμε μαζί.</p>
      <div class="cta"><a class="btn" href="#jobs">Δες τις θέσεις →</a></div>
      <div class="stats">
        <div class="s"><b><?= count($jobs) ?></b><span>ανοιχτές θέσεις</span></div>
        <div class="s"><b>100%</b><span>cloud &amp; τεχνολογία</span></div>
        <div class="s"><b>∞</b><span>ευκαιρίες εξέλιξης</span></div>
      </div>
    </div>
  </div>
</header>

<section class="section">
  <div class="container">
    <h2>Γιατί να έρθεις σε εμάς</h2>
    <p class="sub">Δεν προσλαμβάνουμε απλώς — χτίζουμε μια ομάδα που θέλει να μένει.</p>
    <div class="values">
      <div class="val"><div class="ic"><svg fill="none" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg></div><h3>Ανάπτυξη</h3><p>Μαθαίνεις συνεχώς νέες τεχνολογίες με πραγματικά έργα και mentoring από έμπειρους συναδέλφους.</p></div>
      <div class="val"><div class="ic"><svg fill="none" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><h3>Ομάδα</h3><p>Συνεργατικό, ανθρώπινο περιβάλλον όπου η γνώμη σου μετράει και η βοήθεια είναι πάντα δίπλα σου.</p></div>
      <div class="val"><div class="ic"><svg fill="none" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg></div><h3>Τεχνολογία</h3><p>Cloud, δίκτυα, ανάπτυξη — δουλεύεις με σύγχρονα εργαλεία σε ένα περιβάλλον που καινοτομεί.</p></div>
      <div class="val"><div class="ic"><svg fill="none" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg></div><h3>Σταθερότητα</h3><p>Μια εταιρεία που μεγαλώνει σταθερά, με πραγματικούς πελάτες και μακροχρόνιες σχέσεις.</p></div>
    </div>
    <div class="band" style="margin-top:24px">
      <img src="apply-assets/collab.jpg" alt="Η ομάδα μας">
      <div class="txt"><h3>Ένα περιβάλλον που σε ανεβάζει</h3><p>Μοντέρνοι χώροι, ευέλικτο κλίμα και συνάδελφοι που γιορτάζουν μαζί κάθε επιτυχία.</p></div>
    </div>
  </div>
</section>

<section class="section" id="jobs" style="background:linear-gradient(180deg,#fff,#f4f7fb)">
  <div class="container">
    <h2>Ανοιχτές θέσεις</h2>
    <p class="sub">Διάβασε ολόκληρη την αγγελία και εκδήλωσε το ενδιαφέρον σου σε λίγα λεπτά.</p>
    <?php if (count($jobs)): ?>
      <div class="jobs">
        <?php foreach ($jobs as $j): ?>
          <div class="job">
            <h3><?= $e($j->title) ?></h3>
            <div class="meta">
              <?php if ($j->location): ?><span class="tag">📍 <?= $e($j->location) ?></span><?php endif; ?>
              <?php if ($j->emptype): ?><span class="tag"><?= $e($j->emptype) ?></span><?php endif; ?>
            </div>
            <?php if (trim((string) $j->descr) !== ''): ?><p class="d"><?= $e(mb_substr(strip_tags($j->descr), 0, 170)) ?>…</p><?php endif; ?>
            <?php if (trim((string) $j->skills) !== ''): ?><div class="chips"><?= $skillChips($j->skills) ?></div><?php endif; ?>
            <div class="acts">
              <button class="btn o sm" onclick="openJob(<?= (int) $j->id ?>)">Δες την αγγελία</button>
              <a class="btn sm" href="apply.php?job=<?= (int) $j->id ?>">Εκδήλωση ενδιαφέροντος →</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p style="text-align:center;margin-top:26px;font-size:14px;color:var(--mut)">Δεν βρίσκεις θέση που σου ταιριάζει; <a href="apply.php?job=general" style="color:var(--brand);font-weight:700">Στείλε γενική αίτηση →</a></p>
    <?php else: ?>
      <div class="empty">Δεν υπάρχουν ανοιχτές θέσεις αυτή τη στιγμή.<br><a class="btn sm" style="margin-top:14px" href="apply.php?job=general">Στείλε γενική αίτηση →</a></div>
    <?php endif; ?>
  </div>
</section>

<!-- MODAL: ανάλυση αγγελίας -->
<div class="modal" id="jobModal" onclick="if(event.target===this)closeJob()">
  <div class="modal-box">
    <button class="modal-x" onclick="closeJob()" aria-label="Κλείσιμο">✕</button>
    <h2 id="jmTitle"></h2>
    <div id="jmMeta"></div>
    <div id="jmDesc" class="jobdesc"></div>
    <div id="jmSkills"></div>
    <div class="modal-foot">
      <a id="jmApply" class="btn" href="#">Εκδήλωση ενδιαφέροντος →</a>
      <button class="btn o" onclick="closeJob()">Κλείσιμο</button>
    </div>
  </div>
</div>
<script>
const JOBS = <?= json_encode($jobsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
function openJob(id){
  const j = JOBS[id]; if(!j) return;
  document.getElementById('jmTitle').textContent = j.title;
  document.getElementById('jmMeta').innerHTML = (j.location?'<span class="tag">📍 '+esc(j.location)+'</span>':'') + (j.emptype?'<span class="tag">'+esc(j.emptype)+'</span>':'');
  document.getElementById('jmDesc').innerHTML = j.html || '<p class="mut">—</p>';
  document.getElementById('jmSkills').innerHTML = j.skills || '';
  document.getElementById('jmApply').href = 'apply.php?job=' + id;
  document.getElementById('jobModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeJob(){ document.getElementById('jobModal').classList.remove('show'); document.body.style.overflow=''; }
function esc(s){ return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
document.addEventListener('keydown', e => { if(e.key==='Escape') closeJob(); });
</script>

<footer class="foot">
  <span class="brand"><img src="apply-assets/cloudon-logo.svg" alt="CloudOn"></span><br>
  <a href="https://cloudon.gr">cloudon.gr</a> · Careers
</footer>
<?php endif; ?>

</body></html>
