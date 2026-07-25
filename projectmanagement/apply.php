<?php
/**
 * CloudOn Careers — δημόσια σελίδα (premium). Isolated DB load (δεν πειράζει admin sessions).
 * Ροή: hero → γιατί εμάς → ανοιχτές θέσεις → φόρμα αίτησης. Νέες αιτήσεις (source=form)
 * αξιολογούνται από το cron cv_autoeval.php.
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
        $jobId = (int) ($_POST['job'] ?? 0);
        $job = Capsule::table('mod_cpm_cv_jobs')->where('id', $jobId)->where('active', 1)->first();
        $name = mb_substr(trim($_POST['name'] ?? ''), 0, 150);
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone = mb_substr(trim($_POST['phone'] ?? ''), 0, 50);
        $letter = mb_substr(trim($_POST['letter'] ?? ''), 0, 6000);
        $f = $_FILES['cv'] ?? null;
        if (!$job) { $err = 'Επίλεξε έγκυρη θέση.'; }
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
                        'job_id' => (int) $job->id, 'job_title' => mb_substr($job->title, 0, 190), 'letter' => $letter,
                        'cv_stored' => $stored, 'cv_name' => mb_substr($f['name'], 0, 190), 'cv_mime' => $mime, 'status' => 'new',
                        'applied_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    if ($mime === 'application/pdf') {
                        try { $ph = CvPhoto::extract($CVDIR . '/' . $stored, $CVDIR, $id); if ($ph !== '') { Capsule::table('mod_cpm_cv')->where('id', $id)->update(['photo' => $ph]); } } catch (\Throwable $e) {}
                    }
                    $done = true; $doneJob = $job->title;
                }
            }
        }
    }
}

$jobs = Capsule::table('mod_cpm_cv_jobs')->where('active', 1)->orderBy('title')->get();
$now = time();
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$SELF = 'https://my.cloudon.gr/project/apply.php';
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
$chips = function ($csv) use ($e) {
    $out = '';
    foreach (preg_split('/[,\n·]+/', (string) $csv) as $s) { $s = trim($s); if ($s !== '') { $out .= '<span class="chip">' . $e($s) . '</span>'; } }
    return $out;
};
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
.btn{display:inline-flex;align-items:center;gap:9px;background:linear-gradient(135deg,var(--brand2),var(--brand-d));color:#fff;border:0;border-radius:13px;padding:14px 26px;font-size:16px;font-weight:800;cursor:pointer;text-decoration:none;box-shadow:0 14px 32px -8px rgba(0,144,221,.55);transition:transform .16s,box-shadow .16s}
.btn:hover{transform:translateY(-2px);box-shadow:0 20px 44px -8px rgba(0,144,221,.65)}
.btn.ghost{background:rgba(255,255,255,.12);box-shadow:none;border:1px solid rgba(255,255,255,.35)}
.btn.sm{padding:10px 18px;font-size:14px;border-radius:11px}
/* ── HERO ── */
.hero{position:relative;color:#fff;overflow:hidden;padding:26px 0 96px}
.hero::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(9,20,38,.78),rgba(9,20,38,.92)),url('apply-assets/office.jpg') center/cover;z-index:-2}
.hero::after{content:"";position:absolute;top:-140px;right:-120px;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle,#22b4ff55,transparent 70%);filter:blur(40px);z-index:-1}
.nav{display:flex;align-items:center;justify-content:space-between;padding:8px 0 0}
.brand{display:inline-flex;background:#fff;border-radius:11px;padding:8px 14px;box-shadow:0 6px 20px -8px rgba(0,0,0,.4)}
.brand img{height:30px;display:block}
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
/* values */
.values{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
.val{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:0 10px 30px -20px rgba(15,32,56,.4)}
.val .ic{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#e8f6ff,#d3ecff);display:flex;align-items:center;justify-content:center;margin-bottom:14px}
.val .ic svg{width:24px;height:24px;stroke:var(--brand-d)}
.val h3{font-size:17px;color:var(--ink);margin-bottom:6px}
.val p{font-size:13.5px;color:var(--txt)}
/* culture band */
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
/* form */
.formwrap{max-width:660px;margin:0 auto}
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
@media(max-width:640px){.hero-in{margin-top:38px}.section{padding:44px 0}}
</style></head>
<body>

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
      <div class="cta">
        <a class="btn" href="#jobs">Δες τις θέσεις →</a>
        <a class="btn ghost" href="#apply">Στείλε βιογραφικό</a>
      </div>
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
    <p class="sub">Βρες τη θέση που σου ταιριάζει και κάνε αίτηση σε λίγα λεπτά.</p>
    <?php if (count($jobs)): ?>
      <div class="jobs">
        <?php foreach ($jobs as $j): ?>
          <div class="job">
            <h3><?= $e($j->title) ?></h3>
            <div class="meta">
              <?php if ($j->location): ?><span class="tag">📍 <?= $e($j->location) ?></span><?php endif; ?>
              <?php if ($j->emptype): ?><span class="tag"><?= $e($j->emptype) ?></span><?php endif; ?>
            </div>
            <?php if (trim((string) $j->descr) !== ''): ?><p class="d"><?= $e(mb_substr(strip_tags($j->descr), 0, 180)) ?></p><?php endif; ?>
            <?php if (trim((string) $j->skills) !== ''): ?><div class="chips"><?= $chips($j->skills) ?></div><?php endif; ?>
            <button class="btn sm" style="align-self:flex-start" onclick="pick(<?= (int) $j->id ?>,'<?= $e(addslashes($j->title)) ?>')">Κάνε αίτηση →</button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty">Δεν υπάρχουν ανοιχτές θέσεις αυτή τη στιγμή — στείλε μας το βιογραφικό σου παρακάτω για μελλοντικές ευκαιρίες.</div>
    <?php endif; ?>
  </div>
</section>

<section class="section" id="apply">
  <div class="container formwrap">
    <?php if ($done): ?>
      <div class="card"><div class="done"><div class="ic">✓</div>
        <h2>Λάβαμε την αίτησή σου!</h2>
        <p>Σε ευχαριστούμε για το ενδιαφέρον σου<?= $doneJob ? ' για τη θέση «' . $e($doneJob) . '»' : '' ?>. Θα εξετάσουμε το βιογραφικό σου και θα επικοινωνήσουμε μαζί σου αν προχωρήσουμε.</p>
        <a class="btn sm" style="margin-top:18px" href="apply.php">Νέα αίτηση</a></div></div>
    <?php else: ?>
      <h2 style="margin-bottom:8px">Στείλε το βιογραφικό σου</h2>
      <p class="sub">Λίγα λεπτά — και είσαι στη λίστα μας.</p>
      <div class="card">
        <?php if ($err): ?><div class="alert"><?= $e($err) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" autocomplete="on">
          <input type="hidden" name="ts" value="<?= $now ?>">
          <div class="hp"><label>Website</label><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
          <label>Θέση που σε ενδιαφέρει *</label>
          <select name="job" id="job" required>
            <option value="">— επίλεξε θέση —</option>
            <?php foreach ($jobs as $j): ?><option value="<?= (int) $j->id ?>"><?= $e($j->title) ?><?= $j->location ? ' — ' . $e($j->location) : '' ?></option><?php endforeach; ?>
          </select>
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
    <?php endif; ?>
  </div>
</section>

<footer class="foot">
  <span class="brand"><img src="apply-assets/cloudon-logo.svg" alt="CloudOn"></span><br>
  <a href="https://cloudon.gr">cloudon.gr</a> · Careers
</footer>

<script>
function pick(id,title){var s=document.getElementById('job');if(s){s.value=id;}document.getElementById('apply').scrollIntoView({behavior:'smooth'});}
</script>
</body></html>
