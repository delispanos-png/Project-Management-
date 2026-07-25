<?php
/**
 * CloudOn Careers — δημόσια σελίδα (premium, δίγλωσση EL/EN). Isolated DB load.
 * Ροή: landing (hero → values → θέσεις + modal ανάλυσης) → «Εκδήλωση ενδιαφέροντος»
 *      → ΞΕΧΩΡΙΣΤΗ σελίδα φόρμας με assigned θέση (apply.php?job=ID).
 * Γλώσσα: ?lang=el|en (default el), διατηρείται σε links/φόρμα. Νέες αιτήσεις (source=form)
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

// ── Γλώσσα ──
$lang = (($_REQUEST['lang'] ?? '') === 'en') ? 'en' : 'el';
$T = [
  'el' => [
    'title' => 'Καριέρα & Θέσεις Εργασίας — CloudOn', 'og_title' => 'Καριέρα στην CloudOn',
    'meta_jobs' => 'Ανοιχτές θέσεις εργασίας στην CloudOn: ', 'meta_tail' => '. Στείλε το βιογραφικό σου online.',
    'meta_none' => 'Καριέρα στην CloudOn — στείλε μας το βιογραφικό σου για μελλοντικές ευκαιρίες.',
    'nav_jobs' => 'Ανοιχτές θέσεις', 'eyebrow' => 'Καριέρα στην CloudOn',
    'h1a' => 'Χτίσε την καριέρα σου ', 'h1b' => 'σε μια ομάδα που εξελίσσεται.',
    'hero_p' => 'Δουλεύουμε με σύγχρονη τεχνολογία, πραγματικό αντίκτυπο και ανθρώπους που στηρίζουν ο ένας τον άλλον. Έλα να μεγαλώσουμε μαζί.',
    'see_jobs' => 'Δες τις θέσεις →', 'stat_jobs' => 'ανοιχτές θέσεις', 'stat_tech' => 'cloud & τεχνολογία', 'stat_grow' => 'ευκαιρίες εξέλιξης',
    'why_h' => 'Γιατί να έρθεις σε εμάς', 'why_sub' => 'Δεν προσλαμβάνουμε απλώς — χτίζουμε μια ομάδα που θέλει να μένει.',
    'v1t' => 'Ανάπτυξη', 'v1p' => 'Μαθαίνεις συνεχώς νέες τεχνολογίες με πραγματικά έργα και mentoring από έμπειρους συναδέλφους.',
    'v2t' => 'Ομάδα', 'v2p' => 'Συνεργατικό, ανθρώπινο περιβάλλον όπου η γνώμη σου μετράει και η βοήθεια είναι πάντα δίπλα σου.',
    'v3t' => 'Τεχνολογία', 'v3p' => 'Cloud, δίκτυα, ανάπτυξη — δουλεύεις με σύγχρονα εργαλεία σε ένα περιβάλλον που καινοτομεί.',
    'v4t' => 'Σταθερότητα', 'v4p' => 'Μια εταιρεία που μεγαλώνει σταθερά, με πραγματικούς πελάτες και μακροχρόνιες σχέσεις.',
    'band_h' => 'Ένα περιβάλλον που σε ανεβάζει', 'band_p' => 'Μοντέρνοι χώροι, ευέλικτο κλίμα και συνάδελφοι που γιορτάζουν μαζί κάθε επιτυχία.',
    'jobs_h' => 'Ανοιχτές θέσεις', 'jobs_sub' => 'Διάβασε ολόκληρη την αγγελία και εκδήλωσε το ενδιαφέρον σου σε λίγα λεπτά.',
    'view_posting' => 'Δες την αγγελία', 'apply_cta' => 'Εκδήλωση ενδιαφέροντος →', 'close' => 'Κλείσιμο',
    'no_fit' => 'Δεν βρίσκεις θέση που σου ταιριάζει; ', 'general_link' => 'Στείλε γενική αίτηση →',
    'no_jobs' => 'Δεν υπάρχουν ανοιχτές θέσεις αυτή τη στιγμή.',
    'back_all' => '← Όλες οι θέσεις', 'assigned' => '✓ Θέση που επέλεξες', 'general_title' => 'Γενική αίτηση',
    'general_desc' => 'Δεν βρήκες τη θέση που ταιριάζει; Στείλε μας το βιογραφικό σου και θα σε έχουμε υπόψη για μελλοντικές ευκαιρίες.',
    'see_full' => 'Δες ολόκληρη την αγγελία', 'form_h' => 'Εκδήλωσε το ενδιαφέρον σου',
    'form_sub' => 'Συμπλήρωσε τα στοιχεία σου & ανέβασε το βιογραφικό. Λίγα λεπτά μόνο.',
    'l_name' => 'Ονοματεπώνυμο', 'l_email' => 'Email', 'l_phone' => 'Τηλέφωνο',
    'l_cv' => 'Βιογραφικό (PDF προτιμότερο)', 'l_letter' => 'Λίγα λόγια για σένα (προαιρετικά)',
    'letter_ph' => 'Γιατί σε ενδιαφέρει η θέση, τι σε ξεχωρίζει…', 'submit' => 'Αποστολή αίτησης →',
    'consent' => 'Με την υποβολή, συναινείς στην επεξεργασία των στοιχείων σου για σκοπούς πρόσληψης.',
    'ty_h' => 'Λάβαμε την αίτησή σου!', 'ty_thanks' => 'Σε ευχαριστούμε για το ενδιαφέρον σου', 'ty_for' => ' για τη θέση «',
    'ty_tail' => '». Θα εξετάσουμε το βιογραφικό σου και θα επικοινωνήσουμε μαζί σου αν προχωρήσουμε.',
    'ty_tail2' => '. Θα εξετάσουμε το βιογραφικό σου και θα επικοινωνήσουμε μαζί σου αν προχωρήσουμε.',
    'ty_more' => 'Δες κι άλλες θέσεις',
    'e_fail' => 'Η υποβολή απέτυχε. Δοκίμασε ξανά.', 'e_job' => 'Επίλεξε έγκυρη θέση.', 'e_name' => 'Συμπλήρωσε το ονοματεπώνυμό σου.',
    'e_email' => 'Συμπλήρωσε έγκυρο email.', 'e_cv' => 'Ανέβασε το βιογραφικό σου (PDF).', 'e_size' => 'Το αρχείο ξεπερνά τα 15MB.',
    'e_type' => 'Μη επιτρεπτός τύπος αρχείου. Ανέβασε PDF ή Word.', 'e_save' => 'Σφάλμα αποθήκευσης. Δοκίμασε ξανά.',
  ],
  'en' => [
    'title' => 'Careers & Job Openings — CloudOn', 'og_title' => 'Careers at CloudOn',
    'meta_jobs' => 'Open positions at CloudOn: ', 'meta_tail' => '. Send us your CV online.',
    'meta_none' => 'Careers at CloudOn — send us your CV for future opportunities.',
    'nav_jobs' => 'Open positions', 'eyebrow' => 'Careers at CloudOn',
    'h1a' => 'Build your career ', 'h1b' => 'in a team that keeps growing.',
    'hero_p' => 'We work with modern technology, real impact and people who support one another. Come grow with us.',
    'see_jobs' => 'See the positions →', 'stat_jobs' => 'open positions', 'stat_tech' => 'cloud & technology', 'stat_grow' => 'growth opportunities',
    'why_h' => 'Why join us', 'why_sub' => "We don't just hire — we build a team that wants to stay.",
    'v1t' => 'Growth', 'v1p' => 'You keep learning new technologies through real projects and mentoring from experienced colleagues.',
    'v2t' => 'Team', 'v2p' => 'A collaborative, human environment where your voice matters and help is always right beside you.',
    'v3t' => 'Technology', 'v3p' => 'Cloud, networks, development — you work with modern tools in an environment that innovates.',
    'v4t' => 'Stability', 'v4p' => 'A company that grows steadily, with real clients and long-term relationships.',
    'band_h' => 'An environment that lifts you up', 'band_p' => 'Modern spaces, a flexible vibe and colleagues who celebrate every win together.',
    'jobs_h' => 'Open positions', 'jobs_sub' => 'Read the full posting and express your interest in just a few minutes.',
    'view_posting' => 'View posting', 'apply_cta' => 'Apply now →', 'close' => 'Close',
    'no_fit' => "Can't find a position that fits? ", 'general_link' => 'Send a general application →',
    'no_jobs' => 'There are no open positions at the moment.',
    'back_all' => '← All positions', 'assigned' => '✓ Position you selected', 'general_title' => 'General application',
    'general_desc' => "Didn't find the right position? Send us your CV and we'll keep you in mind for future opportunities.",
    'see_full' => 'View the full posting', 'form_h' => 'Express your interest',
    'form_sub' => 'Fill in your details & upload your CV. Just a few minutes.',
    'l_name' => 'Full name', 'l_email' => 'Email', 'l_phone' => 'Phone',
    'l_cv' => 'CV (PDF preferred)', 'l_letter' => 'A few words about you (optional)',
    'letter_ph' => 'Why the position interests you, what sets you apart…', 'submit' => 'Submit application →',
    'consent' => 'By submitting, you consent to the processing of your details for recruitment purposes.',
    'ty_h' => 'We received your application!', 'ty_thanks' => 'Thank you for your interest', 'ty_for' => ' in the «',
    'ty_tail' => '» position. We will review your CV and get in touch if we move forward.',
    'ty_tail2' => '. We will review your CV and get in touch if we move forward.',
    'ty_more' => 'See more positions',
    'e_fail' => 'Submission failed. Please try again.', 'e_job' => 'Select a valid position.', 'e_name' => 'Enter your full name.',
    'e_email' => 'Enter a valid email.', 'e_cv' => 'Upload your CV (PDF).', 'e_size' => 'The file exceeds 15MB.',
    'e_type' => 'File type not allowed. Upload PDF or Word.', 'e_save' => 'Save error. Please try again.',
  ],
];
$t = fn($k) => $T[$lang][$k] ?? $T['el'][$k] ?? $k;

$CVDIR = __DIR__ . '/../attachments/cloudonprojects';
$err = ''; $done = false; $doneJob = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hp = trim($_POST['website'] ?? '');
    $ts = (int) ($_POST['ts'] ?? 0);
    $elapsed = time() - $ts;
    if ($hp !== '' || $ts <= 0 || $elapsed < 3 || $elapsed > 7200) {
        $err = $t('e_fail');
    } else {
        $general = !empty($_POST['general']);
        $jobId = (int) ($_POST['job'] ?? 0);
        $job = $jobId ? Capsule::table('mod_cpm_cv_jobs')->where('id', $jobId)->where('active', 1)->first() : null;
        $name = mb_substr(trim($_POST['name'] ?? ''), 0, 150);
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone = mb_substr(trim($_POST['phone'] ?? ''), 0, 50);
        $letter = mb_substr(trim($_POST['letter'] ?? ''), 0, 6000);
        $f = $_FILES['cv'] ?? null;
        if (!$general && !$job) { $err = $t('e_job'); }
        elseif ($name === '') { $err = $t('e_name'); }
        elseif (!$email) { $err = $t('e_email'); }
        elseif (!$f || $f['error'] !== UPLOAD_ERR_OK) { $err = $t('e_cv'); }
        elseif ($f['size'] > 15 * 1024 * 1024) { $err = $t('e_size'); }
        else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['php', 'phtml', 'phar', 'cgi', 'sh', 'exe', 'htaccess', 'html', 'htm', 'svg'], true)) {
                $err = $t('e_type');
            } else {
                if (!is_dir($CVDIR)) { @mkdir($CVDIR, 0750, true); }
                $stored = uniqid('cv', true) . '.' . (preg_replace('/[^a-z0-9]/', '', $ext) ?: 'pdf');
                if (!move_uploaded_file($f['tmp_name'], $CVDIR . '/' . $stored)) {
                    $err = $t('e_save');
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
$qlang = '&lang=' . $lang;   // για links με υπάρχον query
$plang = '?lang=' . $lang;   // για base links

/** Μορφοποίηση περιγραφής αγγελίας (headers/bullets/paragraphs) — ασφαλές HTML. */
$fmtDesc = function ($txt) use ($e) {
    $txt = trim((string) $txt);
    if ($txt === '') { return '<p class="mut">—</p>'; }
    $out = ''; $inUl = false;
    foreach (preg_split('/\r?\n/', $txt) as $ln) {
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
/** Τοπικοποιημένη τιμή πεδίου θέσης (EN έκδοση αν lang=en & υπάρχει, αλλιώς fallback). */
$jl = function ($job, $field) use ($lang) {
    if ($lang === 'en') {
        $map = ['title' => 'title_en', 'descr' => 'descr_en', 'skills' => 'skills_en', 'emptype' => 'emptype_en'];
        if (isset($map[$field]) && trim((string) ($job->{$map[$field]} ?? '')) !== '') { return $job->{$map[$field]}; }
    }
    return $job->$field ?? '';
};

// ── Ποια όψη; landing | form | thank-you ──
$postedJob = ($_SERVER['REQUEST_METHOD'] === 'POST' && !$done) ? (int) ($_POST['job'] ?? 0) : 0;
$reqJobId = $postedJob ?: (ctype_digit((string) ($_GET['job'] ?? '')) ? (int) $_GET['job'] : 0);
$formJob = null;
foreach ($jobs as $jj) { if ((int) $jj->id === $reqJobId) { $formJob = $jj; break; } }
$formGeneral = !$done && (($_GET['job'] ?? '') === 'general' || (!empty($_POST['general']) && $_SERVER['REQUEST_METHOD'] === 'POST'));
$mode = $done ? 'done' : (($formJob || $formGeneral) ? 'form' : 'landing');

// base URL για εναλλαγή γλώσσας (διατηρεί context)
$curBase = 'apply.php';
if ($mode === 'form') { $curBase .= '?job=' . ($formJob ? (int) $formJob->id : 'general'); }
$switch = fn($lg) => $curBase . (strpos($curBase, '?') !== false ? '&' : '?') . 'lang=' . $lg;

// SEO
$titles = []; foreach ($jobs as $jj) { $titles[] = $jl($jj, 'title'); }
$metaDesc = count($jobs) ? $t('meta_jobs') . mb_substr(implode(', ', $titles), 0, 150) . $t('meta_tail') : $t('meta_none');
$ld = [];
foreach ($jobs as $jj) {
    $ld[] = ['@context' => 'https://schema.org/', '@type' => 'JobPosting', 'title' => $jl($jj, 'title'),
        'description' => '<p>' . htmlspecialchars((string) ($jl($jj, 'descr') ?: $jl($jj, 'title')) . ($jl($jj, 'skills') ? ' Skills: ' . $jl($jj, 'skills') : '')) . '</p>',
        'datePosted' => date('Y-m-d', strtotime((string) ($jj->created_at ?: 'now'))),
        'employmentType' => stripos((string) $jj->emptype, 'μερικ') !== false ? 'PART_TIME' : 'FULL_TIME',
        'hiringOrganization' => ['@type' => 'Organization', 'name' => 'CloudOn', 'sameAs' => 'https://cloudon.gr'],
        'directApply' => true,
        'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $jj->location ?: 'Αθήνα', 'addressCountry' => 'GR']]];
}
$jobsData = [];
foreach ($jobs as $jj) {
    $jobsData[(int) $jj->id] = ['title' => $jl($jj, 'title'), 'location' => $jj->location, 'emptype' => $jl($jj, 'emptype'),
        'skills' => $skillChips($jl($jj, 'skills')), 'html' => $fmtDesc($jl($jj, 'descr'))];
}
?><!DOCTYPE html><html lang="<?= $lang ?>"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($t('title')) ?></title>
<meta name="description" content="<?= $e($metaDesc) ?>">
<link rel="canonical" href="<?= $SELF . $plang ?>">
<link rel="alternate" hreflang="el" href="<?= $SELF ?>?lang=el">
<link rel="alternate" hreflang="en" href="<?= $SELF ?>?lang=en">
<link rel="alternate" hreflang="x-default" href="<?= $SELF ?>">
<meta name="robots" content="index,follow">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= $e($t('og_title')) ?>">
<meta property="og:description" content="<?= $e($metaDesc) ?>">
<meta property="og:url" content="<?= $SELF . $plang ?>">
<meta property="og:locale" content="<?= $lang === 'en' ? 'en_US' : 'el_GR' ?>">
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
.brand{display:inline-flex;align-items:center}
.brand img{height:72px;width:auto;display:block}
.langsw{display:inline-flex;gap:2px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.28);border-radius:10px;padding:3px;font-size:12.5px;font-weight:800}
.langsw a{color:#cfe0f0;text-decoration:none;padding:5px 10px;border-radius:7px;line-height:1}
.langsw a.on{background:#fff;color:var(--brand-d)}
/* ── HERO ── */
.hero{position:relative;color:#fff;overflow:hidden;padding:26px 0 96px}
.hero::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(9,20,38,.78),rgba(9,20,38,.92)),url('apply-assets/office.jpg') center/cover;z-index:-2}
.hero::after{content:"";position:absolute;top:-140px;right:-120px;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle,#22b4ff55,transparent 70%);filter:blur(40px);z-index:-1}
.nav{display:flex;align-items:center;justify-content:space-between;padding:8px 0 0;gap:10px}
.nav .links{display:flex;gap:10px;align-items:center}
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
/* job description */
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
.applyhead .r{display:flex;align-items:center;gap:12px}
.applyhead .brand img{height:44px}
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
/* ── SITE FOOTER (ίδιο με cloudon.gr) ── */
.sfoot{background:#f7f9fc;border-top:1px solid var(--line);padding:56px 0 0;margin-top:40px;color:#43536b}
.sfoot-logo{display:block;width:max-content;margin:0 auto 18px}
.sfoot-logo img{height:74px;width:auto;display:block}
.sfoot-tag{display:flex;align-items:center;justify-content:center;gap:16px;font-size:12px;font-weight:700;letter-spacing:2px;color:#5a6b83;margin-bottom:44px}
.sfoot-tag span{height:1px;width:70px;background:var(--line)}
.sfoot-cols{display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:34px}
.sfoot-sub h4{font-size:15px;color:var(--ink);font-weight:800;margin-bottom:12px;line-height:1.32}
.sfoot-sub p{font-size:13.5px;margin-bottom:14px}
.sfoot-sub form input[type=email]{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-size:14px;margin-bottom:10px;background:#fff}
.sfoot-chk{display:flex;gap:8px;align-items:flex-start;font-size:12px;color:var(--mut);margin:0 0 12px;text-transform:none;letter-spacing:0;font-weight:400}
.sfoot-chk input{width:auto;margin-top:2px}
.sfoot-chk a{color:var(--brand)}
.sfoot-sub form button{background:var(--brand);color:#fff;border:0;border-radius:10px;padding:11px 26px;font-size:13px;font-weight:800;letter-spacing:1px;cursor:pointer}
.sfoot-sub form button:hover{background:var(--brand-d)}
.sfoot-contact{margin-top:20px;font-size:13.5px}
.sfoot-contact a{color:var(--ink);font-weight:700;text-decoration:none}
.sfoot-contact a:hover{color:var(--brand)}
.sfoot-contact .sep{color:var(--mut);margin:0 5px}
.sfoot-contact .addr{margin-top:6px;color:var(--mut);font-size:13px}
.sfoot-nav{display:flex;flex-direction:column}
.sfoot-nav a{font-size:13px;font-weight:700;color:#43536b;text-decoration:none;letter-spacing:.4px;padding:9px 0;border-bottom:1px solid var(--line)}
.sfoot-nav a:hover{color:var(--brand)}
.sfoot-bottom{text-align:center;font-size:12.5px;color:var(--mut);padding:22px 20px;margin-top:46px;border-top:1px solid var(--line)}
@media(max-width:860px){.sfoot-cols{grid-template-columns:1fr 1fr}}
@media(max-width:560px){.sfoot-cols{grid-template-columns:1fr;gap:26px}}
@media(max-width:640px){.hero-in{margin-top:38px}.section{padding:44px 0}.modal-box{padding:26px 22px}}
</style></head>
<body>
<?php
$langSwitch = '<span class="langsw"><a href="' . $e($switch('el')) . '"' . ($lang === 'el' ? ' class="on"' : '') . '>EL</a><a href="' . $e($switch('en')) . '"' . ($lang === 'en' ? ' class="on"' : '') . '>EN</a></span>';
?>

<?php if ($mode === 'form'): // ── ΣΕΛΙΔΑ ΦΟΡΜΑΣ ── ?>
<header class="applyhead"><div class="container">
  <a class="brand" href="https://cloudon.gr" title="CloudOn — cloudon.gr"><img src="apply-assets/cloudon-logo-white.png" alt="CloudOn"></a>
  <div class="r"><?= $langSwitch ?><a class="backlink" href="apply.php<?= $plang ?>"><?= $e($t('back_all')) ?></a></div>
</div></header>
<div class="applywrap">
  <div class="jobctx">
    <span class="assigned"><?= $e($t('assigned')) ?></span>
    <h1><?= $e($formJob ? $jl($formJob, 'title') : $t('general_title')) ?></h1>
    <?php if ($formJob): ?>
      <div class="meta">
        <?php if ($formJob->location): ?><span class="tag">📍 <?= $e($formJob->location) ?></span><?php endif; ?>
        <?php if ($jl($formJob, 'emptype')): ?><span class="tag"><?= $e($jl($formJob, 'emptype')) ?></span><?php endif; ?>
      </div>
      <?php if (trim((string) $jl($formJob, 'skills')) !== ''): ?><div class="chips" style="margin-bottom:4px"><?= $skillChips($jl($formJob, 'skills')) ?></div><?php endif; ?>
      <?php if (trim((string) $jl($formJob, 'descr')) !== ''): ?>
      <details><summary><?= $e($t('see_full')) ?></summary><div class="jobdesc"><?= $fmtDesc($jl($formJob, 'descr')) ?></div></details>
      <?php endif; ?>
    <?php else: ?>
      <p class="mut" style="font-size:14px"><?= $e($t('general_desc')) ?></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="font-size:19px;color:var(--ink);margin-bottom:4px"><?= $e($t('form_h')) ?></h2>
    <p class="mut" style="font-size:13px;margin-bottom:6px"><?= $e($t('form_sub')) ?></p>
    <?php if ($err): ?><div class="alert" style="margin-top:12px"><?= $e($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" autocomplete="on" action="apply.php?job=<?= $formJob ? (int) $formJob->id : 'general' ?><?= $qlang ?>">
      <input type="hidden" name="ts" value="<?= $now ?>">
      <input type="hidden" name="lang" value="<?= $lang ?>">
      <?php if ($formJob): ?><input type="hidden" name="job" value="<?= (int) $formJob->id ?>"><?php else: ?><input type="hidden" name="general" value="1"><?php endif; ?>
      <div class="hp"><label>Website</label><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <div class="row">
        <div><label><?= $e($t('l_name')) ?> *</label><input type="text" name="name" required maxlength="150" value="<?= $e($_POST['name'] ?? '') ?>"></div>
        <div><label><?= $e($t('l_email')) ?> *</label><input type="email" name="email" required maxlength="150" value="<?= $e($_POST['email'] ?? '') ?>"></div>
      </div>
      <label><?= $e($t('l_phone')) ?></label><input type="tel" name="phone" maxlength="50" value="<?= $e($_POST['phone'] ?? '') ?>">
      <label><?= $e($t('l_cv')) ?> *</label><input type="file" name="cv" accept=".pdf,.doc,.docx" required>
      <label><?= $e($t('l_letter')) ?></label><textarea name="letter" rows="4" maxlength="6000" placeholder="<?= $e($t('letter_ph')) ?>"><?= $e($_POST['letter'] ?? '') ?></textarea>
      <button class="btn" type="submit"><?= $e($t('submit')) ?></button>
      <div class="note"><?= $e($t('consent')) ?></div>
    </form>
  </div>
</div>

<?php elseif ($done): // ── THANK YOU ── ?>
<header class="applyhead"><div class="container">
  <a class="brand" href="https://cloudon.gr" title="CloudOn — cloudon.gr"><img src="apply-assets/cloudon-logo-white.png" alt="CloudOn"></a>
  <div class="r"><?= $langSwitch ?><a class="backlink" href="apply.php<?= $plang ?>"><?= $e($t('back_all')) ?></a></div>
</div></header>
<div class="applywrap">
  <div class="card"><div class="done"><div class="ic">✓</div>
    <h2><?= $e($t('ty_h')) ?></h2>
    <p><?= $e($t('ty_thanks')) . ($doneJob ? $e($t('ty_for')) . $e($doneJob) . $e($t('ty_tail')) : $e($t('ty_tail2'))) ?></p>
    <a class="btn sm" style="margin-top:18px" href="apply.php<?= $plang ?>"><?= $e($t('ty_more')) ?></a></div></div>
</div>

<?php else: // ── LANDING ── ?>
<header class="hero">
  <div class="container">
    <nav class="nav">
      <a class="brand" href="https://cloudon.gr" title="CloudOn — cloudon.gr"><img src="apply-assets/cloudon-logo-white.png" alt="CloudOn"></a>
      <div class="links"><?= $langSwitch ?><a class="btn ghost sm" href="#jobs"><?= $e($t('nav_jobs')) ?></a></div>
    </nav>
    <div class="hero-in">
      <span class="eyebrow"><?= $e($t('eyebrow')) ?></span>
      <h1><?= $e($t('h1a')) ?><span class="g"><?= $e($t('h1b')) ?></span></h1>
      <p><?= $e($t('hero_p')) ?></p>
      <div class="cta"><a class="btn" href="#jobs"><?= $e($t('see_jobs')) ?></a></div>
      <div class="stats">
        <div class="s"><b><?= count($jobs) ?></b><span><?= $e($t('stat_jobs')) ?></span></div>
        <div class="s"><b>100%</b><span><?= $e($t('stat_tech')) ?></span></div>
        <div class="s"><b>∞</b><span><?= $e($t('stat_grow')) ?></span></div>
      </div>
    </div>
  </div>
</header>

<section class="section">
  <div class="container">
    <h2><?= $e($t('why_h')) ?></h2>
    <p class="sub"><?= $e($t('why_sub')) ?></p>
    <div class="values">
      <div class="val"><div class="ic"><svg fill="none" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg></div><h3><?= $e($t('v1t')) ?></h3><p><?= $e($t('v1p')) ?></p></div>
      <div class="val"><div class="ic"><svg fill="none" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><h3><?= $e($t('v2t')) ?></h3><p><?= $e($t('v2p')) ?></p></div>
      <div class="val"><div class="ic"><svg fill="none" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg></div><h3><?= $e($t('v3t')) ?></h3><p><?= $e($t('v3p')) ?></p></div>
      <div class="val"><div class="ic"><svg fill="none" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg></div><h3><?= $e($t('v4t')) ?></h3><p><?= $e($t('v4p')) ?></p></div>
    </div>
    <div class="band" style="margin-top:24px">
      <img src="apply-assets/collab.jpg" alt="CloudOn team">
      <div class="txt"><h3><?= $e($t('band_h')) ?></h3><p><?= $e($t('band_p')) ?></p></div>
    </div>
  </div>
</section>

<section class="section" id="jobs" style="background:linear-gradient(180deg,#fff,#f4f7fb)">
  <div class="container">
    <h2><?= $e($t('jobs_h')) ?></h2>
    <p class="sub"><?= $e($t('jobs_sub')) ?></p>
    <?php if (count($jobs)): ?>
      <div class="jobs">
        <?php foreach ($jobs as $j): ?>
          <div class="job">
            <h3><?= $e($jl($j, 'title')) ?></h3>
            <div class="meta">
              <?php if ($j->location): ?><span class="tag">📍 <?= $e($j->location) ?></span><?php endif; ?>
              <?php if ($jl($j, 'emptype')): ?><span class="tag"><?= $e($jl($j, 'emptype')) ?></span><?php endif; ?>
            </div>
            <?php if (trim((string) $jl($j, 'descr')) !== ''): ?><p class="d"><?= $e(mb_substr(strip_tags($jl($j, 'descr')), 0, 170)) ?>…</p><?php endif; ?>
            <?php if (trim((string) $jl($j, 'skills')) !== ''): ?><div class="chips"><?= $skillChips($jl($j, 'skills')) ?></div><?php endif; ?>
            <div class="acts">
              <button class="btn o sm" onclick="openJob(<?= (int) $j->id ?>)"><?= $e($t('view_posting')) ?></button>
              <a class="btn sm" href="apply.php?job=<?= (int) $j->id ?><?= $qlang ?>"><?= $e($t('apply_cta')) ?></a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p style="text-align:center;margin-top:26px;font-size:14px;color:var(--mut)"><?= $e($t('no_fit')) ?><a href="apply.php?job=general<?= $qlang ?>" style="color:var(--brand);font-weight:700"><?= $e($t('general_link')) ?></a></p>
    <?php else: ?>
      <div class="empty"><?= $e($t('no_jobs')) ?><br><a class="btn sm" style="margin-top:14px" href="apply.php?job=general<?= $qlang ?>"><?= $e($t('general_link')) ?></a></div>
    <?php endif; ?>
  </div>
</section>

<div class="modal" id="jobModal" onclick="if(event.target===this)closeJob()">
  <div class="modal-box">
    <button class="modal-x" onclick="closeJob()" aria-label="<?= $e($t('close')) ?>">✕</button>
    <h2 id="jmTitle"></h2>
    <div id="jmMeta"></div>
    <div id="jmDesc" class="jobdesc"></div>
    <div id="jmSkills"></div>
    <div class="modal-foot">
      <a id="jmApply" class="btn" href="#"><?= $e($t('apply_cta')) ?></a>
      <button class="btn o" onclick="closeJob()"><?= $e($t('close')) ?></button>
    </div>
  </div>
</div>
<script>
const JOBS = <?= json_encode($jobsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const LANG = '<?= $lang ?>';
function openJob(id){
  const j = JOBS[id]; if(!j) return;
  document.getElementById('jmTitle').textContent = j.title;
  document.getElementById('jmMeta').innerHTML = (j.location?'<span class="tag">📍 '+esc(j.location)+'</span>':'') + (j.emptype?'<span class="tag">'+esc(j.emptype)+'</span>':'');
  document.getElementById('jmDesc').innerHTML = j.html || '<p class="mut">—</p>';
  document.getElementById('jmSkills').innerHTML = j.skills || '';
  document.getElementById('jmApply').href = 'apply.php?job=' + id + '&lang=' + LANG;
  document.getElementById('jobModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeJob(){ document.getElementById('jobModal').classList.remove('show'); document.body.style.overflow=''; }
function esc(s){ return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
document.addEventListener('keydown', e => { if(e.key==='Escape') closeJob(); });
</script>

<?php endif; ?>

<footer class="sfoot">
  <div class="container">
    <a class="sfoot-logo" href="https://cloudon.gr" title="CloudOn — cloudon.gr"><img src="apply-assets/cloudon-logo-white.png" alt="CloudOn"></a>
    <div class="sfoot-tag"><span></span>CLOUD, WEB &amp; ERP SOLUTIONS<span></span></div>
    <div class="sfoot-cols">
      <div class="sfoot-sub">
        <h4>SUBSCRIBE FOR CLOUD, WEB AND ERP UPDATES</h4>
        <p>Get practical updates on cloud infrastructure, websites, and business automation.</p>
        <form action="https://cloudon.gr/" method="get" target="_blank">
          <input type="email" name="email" placeholder="E-mail" aria-label="E-mail">
          <label class="sfoot-chk"><input type="checkbox"> <span>I have read and agree with <a href="https://cloudon.gr/personal-data/" target="_blank" rel="noopener">Privacy Policy</a></span></label>
          <button type="submit">SUBSCRIBE</button>
        </form>
        <div class="sfoot-contact">
          <a href="tel:+302107222560">210 7222560</a><span class="sep">•</span><a href="mailto:info@cloudon.gr">info@cloudon.gr</a>
          <div class="addr">13 Peloponnisou Str., 15341 Agia Paraskevi</div>
        </div>
      </div>
      <nav class="sfoot-nav">
        <a href="https://cloudon.gr/about-us/">ABOUT US</a>
        <a href="https://cloudon.gr/contact/">CONTACT</a>
        <a href="https://cloudon.gr/web-design/">WEB DESIGN</a>
        <a href="https://cloudon.gr/e-commerce/">E-COMMERCE</a>
      </nav>
      <nav class="sfoot-nav">
        <a href="https://cloudon.gr/cloud-services/">CLOUD SERVICES</a>
        <a href="https://cloudon.gr/erp-softone/">ERP</a>
        <a href="https://cloudon.gr/backup-restore-solutions/">BACKUP &amp; RESTORE</a>
        <a href="https://cloudon.gr/voip-services-and-cloud-pbx/">VOIP SERVICES</a>
      </nav>
      <nav class="sfoot-nav">
        <a href="https://cloudon.gr/contact/">REQUEST QUOTE</a>
        <a href="https://cloudon.gr/personal-data/">PRIVACY POLICY</a>
        <a href="https://cloudon.gr/terms-conditions/">TERMS &amp; CONDITIONS</a>
      </nav>
    </div>
  </div>
  <div class="sfoot-bottom">© <?= date('Y') ?> CloudOn. All rights reserved.</div>
</footer>

</body></html>
