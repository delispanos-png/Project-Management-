<?php
/**
 * CloudOn Careers — δημόσια σελίδα υποβολής βιογραφικών.
 * Public (χωρίς login). Isolated WHMCS load (δεν πειράζει admin sessions).
 * Οι νέες αιτήσεις (source=form) αξιολογούνται από το cron cv_autoeval.php.
 */

/* ---- Isolated DB load (όπως boot.php step 1) ---- */
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
$msg = ''; $err = ''; $done = false;

/* ---- POST: υποβολή αίτησης ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // anti-spam: honeypot + time-trap
    $hp = trim($_POST['website'] ?? '');            // κρυφό πεδίο — πρέπει να είναι κενό
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
                        try {
                            $ph = CvPhoto::extract($CVDIR . '/' . $stored, $CVDIR, $id);
                            if ($ph !== '') { Capsule::table('mod_cpm_cv')->where('id', $id)->update(['photo' => $ph]); }
                        } catch (\Throwable $e) {
                        }
                    }
                    $done = true;
                }
            }
        }
    }
}

/* ---- Ενεργές θέσεις ---- */
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
?><!DOCTYPE html><html lang="el"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Καριέρα &amp; Θέσεις Εργασίας — CloudOn</title>
<meta name="description" content="<?= $e($metaDesc) ?>">
<link rel="canonical" href="<?= $SELF ?>">
<meta name="robots" content="index,follow">
<meta property="og:type" content="website">
<meta property="og:title" content="Καριέρα &amp; Θέσεις Εργασίας — CloudOn">
<meta property="og:description" content="<?= $e($metaDesc) ?>">
<meta property="og:url" content="<?= $SELF ?>">
<meta name="twitter:card" content="summary">
<?php foreach ($ld as $l): ?>
<script type="application/ld+json"><?= json_encode($l, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endforeach; ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%230090dd'/><text x='50' y='68' font-size='52' text-anchor='middle' fill='white' font-family='Arial' font-weight='bold'>C</text></svg>">
<style>
*{box-sizing:border-box}
:root{--brand:#0090dd;--brand-d:#0072ad;--ink:#12233d;--txt:#3e506a;--mut:#8091ad;--line:#e5eaf1;--bg:#eef3f9;--card:#fff;--ok:#16a26a;--bad:#e2515f}
html,body{margin:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--txt);line-height:1.5}
.hero{background:linear-gradient(135deg,#0b1b30,#123a63);color:#fff;padding:44px 20px 40px;text-align:center}
.hero .logo{width:60px;height:60px;border-radius:16px;margin:0 auto 16px;background:linear-gradient(135deg,#28c0ff,#0072ad);display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:800;box-shadow:0 12px 30px rgba(0,144,221,.5)}
.hero h1{margin:0 0 6px;font-size:28px;letter-spacing:-.5px}
.hero p{margin:0;color:#aecbe8}
.wrap{max-width:720px;margin:-22px auto 40px;padding:0 16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:0 12px 40px -18px rgba(15,32,56,.3);padding:26px 26px}
label{display:block;font-size:12px;font-weight:700;color:var(--mut);text-transform:uppercase;letter-spacing:.4px;margin:14px 0 5px}
input,select,textarea{width:100%;font:inherit;font-size:15px;padding:11px 13px;border:1px solid var(--line);border-radius:11px;background:#fbfdff;color:var(--ink)}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(0,144,221,.14)}
.row{display:flex;gap:14px;flex-wrap:wrap}.row>div{flex:1;min-width:200px}
.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.btn{margin-top:20px;width:100%;background:linear-gradient(135deg,#22b4ff,#0086cf);color:#fff;border:0;border-radius:13px;padding:14px;font-size:16px;font-weight:800;cursor:pointer;box-shadow:0 12px 30px rgba(0,144,221,.4)}
.btn:hover{filter:brightness(1.05)}
.job-desc{font-size:13px;color:var(--txt);background:#f4f8fc;border:1px solid var(--line);border-radius:11px;padding:12px 14px;margin-top:8px;white-space:pre-wrap;display:none}
.job-desc b{color:var(--ink)}
.note{font-size:12px;color:var(--mut);margin-top:8px}
.alert{padding:12px 15px;border-radius:11px;margin-bottom:16px;font-size:14px}
.alert.err{background:#fdecec;color:#b3323f;border:1px solid #f5c2c7}
.done{text-align:center;padding:26px 10px}
.done .ic{width:64px;height:64px;border-radius:50%;background:#16a26a1a;color:var(--ok);font-size:34px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
.done h2{color:var(--ink);margin:0 0 8px}
a.again{display:inline-block;margin-top:16px;color:var(--brand);font-weight:700;text-decoration:none}
.foot{text-align:center;color:var(--mut);font-size:12px;margin:22px 0}
</style></head>
<body>
<div class="hero"><div class="logo">C</div><h1>Γίνε μέλος της ομάδας μας</h1><p>Στείλε μας το βιογραφικό σου — θα το δούμε προσεκτικά.</p></div>
<div class="wrap">
<?php if ($done): ?>
  <div class="card"><div class="done"><div class="ic">✓</div>
    <h2>Λάβαμε την αίτησή σου!</h2>
    <p>Σε ευχαριστούμε για το ενδιαφέρον σου. Θα εξετάσουμε το βιογραφικό σου και θα επικοινωνήσουμε μαζί σου αν προχωρήσουμε.</p>
    <a class="again" href="apply.php">← Νέα αίτηση</a></div></div>
<?php elseif (!count($jobs)): ?>
  <div class="card"><div class="done"><div class="ic" style="background:#8291a91a;color:#8291a9">ℹ</div>
    <h2>Δεν υπάρχουν ανοιχτές θέσεις αυτή τη στιγμή</h2>
    <p>Ξαναδοκίμασε σύντομα ή στείλε μας το βιογραφικό σου για μελλοντικές ευκαιρίες.</p></div></div>
<?php else: ?>
  <div class="card">
    <?php if ($err): ?><div class="alert err"><?= $e($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" autocomplete="on">
      <input type="hidden" name="ts" value="<?= $now ?>">
      <div class="hp"><label>Website</label><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <label>Θέση που σε ενδιαφέρει *</label>
      <select name="job" id="job" required onchange="showDesc()">
        <option value="">— επίλεξε θέση —</option>
        <?php foreach ($jobs as $j): ?>
          <option value="<?= (int) $j->id ?>" data-desc="<?= $e(($j->descr ?: '') . ($j->skills ? "\n\nΔεξιότητες: " . $j->skills : '') . ($j->location ? "\nΤοποθεσία: " . $j->location : '') . ($j->emptype ? "\nΤύπος: " . $j->emptype : '')) ?>"><?= $e($j->title) ?><?= $j->location ? ' — ' . $e($j->location) : '' ?></option>
        <?php endforeach; ?>
      </select>
      <div class="job-desc" id="jobDesc"></div>
      <div class="row">
        <div><label>Ονοματεπώνυμο *</label><input type="text" name="name" required maxlength="150" value="<?= $e($_POST['name'] ?? '') ?>"></div>
        <div><label>Email *</label><input type="email" name="email" required maxlength="150" value="<?= $e($_POST['email'] ?? '') ?>"></div>
      </div>
      <label>Τηλέφωνο</label><input type="tel" name="phone" maxlength="50" value="<?= $e($_POST['phone'] ?? '') ?>">
      <label>Βιογραφικό (PDF προτιμότερο) *</label><input type="file" name="cv" accept=".pdf,.doc,.docx" required>
      <label>Λίγα λόγια για σένα (προαιρετικά)</label><textarea name="letter" rows="4" maxlength="6000" placeholder="Γιατί σε ενδιαφέρει η θέση, τι σε ξεχωρίζει…"><?= $e($_POST['letter'] ?? '') ?></textarea>
      <button class="btn" type="submit">Αποστολή αίτησης</button>
      <div class="note">Με την υποβολή, συναινείς στην επεξεργασία των στοιχείων σου για σκοπούς πρόσληψης.</div>
    </form>
  </div>
<?php endif; ?>
  <div class="foot">CloudOn · Careers</div>
</div>
<script>
function showDesc(){var s=document.getElementById('job'),o=s.options[s.selectedIndex],d=document.getElementById('jobDesc');var t=o?o.getAttribute('data-desc'):'';if(t&&t.trim()){d.textContent=t;d.style.display='block';}else{d.style.display='none';}}
</script>
</body></html>
