<?php
/**
 * CloudOn Careers — δημόσια σελίδα (premium, δίγλωσση EL/EN). Isolated DB load.
 * Ροή: landing (hero → values → θέσεις + modal ανάλυσης) → «Εκδήλωση ενδιαφέροντος»
 *      → ΞΕΧΩΡΙΣΤΗ σελίδα φόρμας με assigned θέση (apply.php?job=ID).
 * Γλώσσα: ?lang=el|en (default el), διατηρείται σε links/φόρμα. Νέες αιτήσεις (source=form)
 * αξιολογούνται από το cron cv_autoeval.php.
 *
 * ΕΜΦΑΝΙΣΗ: ίδια με το νέο cloudon.gr (tokens, κεφαλίδα, κάρτες, υποσέλιδο).
 * Η λογική πάνω από το HTML είναι αυτούσια από το apply.php. Όσο το αρχείο
 * ΔΕΝ λέγεται apply.php (π.χ. apply-v2.php για προεπισκόπηση), δεν μετράει
 * προβολές, έχει noindex και τα links μένουν στο ίδιο αρχείο.
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
require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/Storage.php';
require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/CvPhoto.php';
require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/JobViews.php';
use WHMCS\Module\Addon\CloudonProjects\CvPhoto;
use WHMCS\Module\Addon\CloudonProjects\JobViews;

$ME      = basename(__FILE__);           // apply.php στο live, apply-v2.php στην προεπισκόπηση
$IS_LIVE = ($ME === 'apply.php');
/* Βάση των links προς το site (το νέο cloudon.gr βγήκε live 8/11/2026). */
$SITE    = 'https://cloudon.gr';

// ── Καταγραφή προβολής αγγελίας (beacon από το modal) ──
// Απαντά αμέσως 204 και δεν συνεχίζει να χτίζει τη σελίδα.
if (isset($_GET['trk'])) {
    if ($IS_LIVE) JobViews::hit((int) $_GET['trk'], ($_GET['k'] ?? '') === 'form' ? 'form' : 'view');
    http_response_code(204);
    exit;
}

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
            } elseif (!is_uploaded_file($f['tmp_name'])) {
                $err = $t('e_save');
            } else {
                $mime = $ext === 'pdf' ? 'application/pdf' : (($ext === 'doc' || $ext === 'docx') ? 'application/msword' : ($f['type'] ?: 'application/octet-stream'));
                $id = Capsule::table('mod_cpm_cv')->insertGetId([
                    'source' => 'form', 'name' => $name, 'email' => $email, 'phone' => $phone,
                    'job_id' => $job ? (int) $job->id : 0, 'job_title' => $job ? mb_substr($job->title, 0, 190) : 'Γενική αίτηση', 'letter' => $letter,
                    'cv_stored' => '', 'cv_name' => mb_substr($f['name'], 0, 190), 'cv_mime' => $mime, 'status' => 'new',
                    'applied_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                ]);
                // CV + φωτο κατευθείαν στο Storage (S3/local) — χωρίς μόνιμο τοπικό αρχείο
                try {
                    $ing = CvPhoto::ingest($f['tmp_name'], mb_substr($f['name'], 0, 190), $mime, $id);
                    $upd = array_filter(['cv_storage_id' => $ing['cv_storage_id'], 'photo_storage_id' => $ing['photo_storage_id'], 'photo' => $ing['photo'] ?: null, 'cv_stored' => $ing['cv_stored'] ?: null], fn($v) => $v !== null);
                    if ($upd) { Capsule::table('mod_cpm_cv')->where('id', $id)->update($upd); }
                } catch (\Throwable $e) {}
                $done = true; $doneJob = $job ? $job->title : '';
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
    // ΠΡΟΣΟΧΗ: ο τροποποιητής /u είναι ΑΠΑΡΑΙΤΗΤΟΣ. Χωρίς αυτόν η PHP κόβει σε bytes
    // και το «·» (C2 B7) ταιριάζει με το δεύτερο byte του ελληνικού «η» (CE B7),
    // σπάζοντας κάθε λέξη που το περιέχει (π.χ. «Ελληνική» → «νική»).
    foreach (preg_split('/[,\n·]+/u', (string) $csv) as $s) { $s = trim($s); if ($s !== '') { $out .= '<span class="chip">' . $e($s) . '</span>'; } }
    return $out;
};
/** Cover εικόνα θέσης (stored preset ή auto βάσει ρόλου) → πλήρες σχετικό path. */
$jobImg = function ($job) {
    $presets = ['office', 'dev', 'backend', 'support', 'pm', 'marketing', 'design'];
    $img = trim((string) ($job->image ?? ''));
    // Ανεβασμένη (custom) φωτογραφία — δεκτή εφόσον υπάρχει στον δίσκο.
    if (preg_match('#^custom/job-[a-z0-9\-]{6,60}$#', $img)
        && is_file(__DIR__ . '/apply-assets/jobs/' . $img . '.jpg')) {
        return 'apply-assets/jobs/' . $img . '.jpg';
    }
    if ($img === '' || !in_array($img, $presets, true)) {
        $s = mb_strtolower(($job->title ?? '') . ' ' . ($job->title_en ?? ''), 'UTF-8');
        $has = fn($n) => (bool) preg_match('/(' . implode('|', $n) . ')/u', $s);
        $img = $has(['back[\s-]?end', 'devops', 'infrastr', 'server', 'database', 'βάσ[ηε]', 'υποδομ', 'sysadmin', 'network', 'δίκτυ']) ? 'backend'
            : ($has(['market', 'seo', 'social media', 'advertis', 'μάρκετ', 'διαφήμ', 'προώθησ']) ? 'marketing'
            : ($has(['design', ' ux', ' ui', 'graphic', 'creativ', 'σχεδ', 'γραφίστ']) ? 'design'
            : ($has(['develop', 'software', 'engineer', 'program', 'front[\s-]?end', 'full[\s-]?stack', 'coder', 'προγραμμ', 'μηχανικ']) ? 'dev'
            : ($has(['project', 'account', 'manager', 'coordinat', 'διαχειρ', 'υπεύθυν', 'συντον']) ? 'pm'
            : ($has(['help[\s-]?desk', 'support', 'technician', 'τεχνικ', 'υποστήρ', 'service desk']) ? 'support' : 'office')))));
    }
    return 'apply-assets/jobs/' . $img . '.jpg';
};
/** Τοπικοποιημένη τιμή πεδίου θέσης (EN έκδοση αν lang=en & υπάρχει, αλλιώς fallback). */
$jl = function ($job, $field) use ($lang) {
    if ($lang === 'en') {
        $map = ['title' => 'title_en', 'descr' => 'descr_en', 'skills' => 'skills_en', 'emptype' => 'emptype_en'];
        if (isset($map[$field]) && trim((string) ($job->{$map[$field]} ?? '')) !== '') { return $job->{$map[$field]}; }
        if ($field === 'location') {   // δεν υπάρχει location_en: οι συνηθισμένες πόλεις
            $loc = trim((string) ($job->location ?? ''));
            return strtr($loc, ['Αθήνα' => 'Athens', 'Λευκωσία' => 'Nicosia', 'Λεμεσός' => 'Limassol', 'Θεσσαλονίκη' => 'Thessaloniki', 'Απομακρυσμένα' => 'Remote', 'Υβριδικά' => 'Hybrid']);
        }
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

// Μέτρηση: η σελίδα καριέρας συνολικά (job_id 0) και το άνοιγμα φόρμας ανά θέση.
// Το άνοιγμα της αναλυτικής αγγελίας μετριέται από το beacon του modal.
if (!$IS_LIVE) {
    // προεπισκόπηση: καμία μέτρηση
} elseif ($mode === 'landing') {
    JobViews::hit(0, 'view');
} elseif ($mode === 'form' && $formJob) {
    JobViews::hit((int) $formJob->id, 'form');
}

// base URL για εναλλαγή γλώσσας (διατηρεί context)
$curBase = $ME;
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
    $jobsData[(int) $jj->id] = ['title' => $jl($jj, 'title'), 'location' => $jl($jj, 'location'), 'emptype' => $jl($jj, 'emptype'),
        'skills' => $skillChips($jl($jj, 'skills')), 'html' => $fmtDesc($jl($jj, 'descr')), 'image' => $jobImg($jj)];
}
?><!DOCTYPE html><html lang="<?= $lang ?>"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($t('title')) ?></title>
<meta name="description" content="<?= $e($metaDesc) ?>">
<link rel="canonical" href="<?= $SELF . $plang ?>">
<link rel="alternate" hreflang="el" href="<?= $SELF ?>?lang=el">
<link rel="alternate" hreflang="en" href="<?= $SELF ?>?lang=en">
<link rel="alternate" hreflang="x-default" href="<?= $SELF ?>">
<meta name="robots" content="<?= $IS_LIVE ? 'index,follow' : 'noindex,nofollow' ?>">
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
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%232E9BFF'/><text x='50' y='68' font-size='52' text-anchor='middle' fill='white' font-family='Arial' font-weight='bold'>C</text></svg>">
<style>
/* Tokens: ίδια με το cloudon.gr (wp-content/themes/cloudon/assets/css/tokens.css) */
:root{--c-bg:#fff;--c-surface:#fff;--c-surface-2:#F2F6FC;--c-line:#E2E9F3;--c-ink:#0B1220;--c-ink-2:#4C5C78;--c-ink-3:#5E6D86;
  --c-link:#1668C7;--c-brand:#2E9BFF;--c-brand-2:#12B5AC;--c-brand-2-ink:#0A7A74;--g-brand:linear-gradient(135deg,#2E9BFF,#12B5AC);
  --c-ok:#16a26a;
  --f-sans:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",sans-serif;
  --f-mono:"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,monospace;
  --t-h1:clamp(2rem,1.2rem + 2.6vw,3.2rem);--t-h2:clamp(1.55rem,1.1rem + 1.6vw,2.3rem);--t-h3:clamp(1.2rem,1rem + .7vw,1.5rem);
  --t-small:.875rem;--t-micro:.75rem;
  --s-1:.25rem;--s-2:.5rem;--s-3:.75rem;--s-4:1rem;--s-5:1.5rem;--s-6:2rem;--s-7:3rem;--s-8:4rem;
  --r-sm:10px;--r-md:16px;--r-lg:24px;--sh-1:0 1px 2px rgba(11,18,32,.06);--sh-2:0 10px 28px rgba(11,18,32,.08);--sh-3:0 26px 70px rgba(11,18,32,.12);
  --gutter:clamp(1rem,4vw,2.5rem)}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--f-sans);font-size:1.0625rem;line-height:1.6;background:var(--c-bg);color:var(--c-ink);-webkit-font-smoothing:antialiased}
img{max-width:100%;display:block}
a{color:var(--c-link);text-decoration:none}
.wrap{max-width:1240px;margin:0 auto;padding-inline:var(--gutter)}
.mut{color:var(--c-ink-2)}
.micro{font-family:var(--f-mono);font-size:var(--t-micro);font-weight:700;letter-spacing:.08em;color:var(--c-ink-3)}
h1,h2,h3{color:var(--c-ink);letter-spacing:-.02em;line-height:1.15}
em{font-style:normal;background:var(--g-brand);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.skip{position:absolute;left:-9999px}.skip:focus{left:var(--s-4);top:var(--s-4);z-index:200;background:#fff;padding:var(--s-2) var(--s-4);border-radius:var(--r-sm)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.5em;min-height:46px;padding:.7rem 1.5rem;border-radius:999px;border:0;font:inherit;font-weight:700;font-size:1rem;cursor:pointer;text-decoration:none;transition:transform .16s,box-shadow .16s}
.btn-primary{background:var(--g-brand);color:#0B1220;box-shadow:0 10px 26px rgba(46,155,255,.28)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(46,155,255,.36)}
.btn-ghost{background:var(--c-surface);color:var(--c-ink);border:1px solid var(--c-line)}
.btn-ghost:hover{border-color:var(--c-brand)}
.btn-sm{min-height:44px;padding:.5rem 1.1rem;font-size:.92rem}
:focus-visible{outline:2px solid var(--c-link);outline-offset:3px}
/* κεφαλίδα — όπως στο site */
.site-head{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.94);backdrop-filter:blur(10px);border-bottom:1px solid var(--c-line)}
.head-row{display:flex;align-items:center;gap:var(--s-5);min-height:76px}
.brand-lockup{display:flex;flex-direction:column;gap:3px}
.brand img{height:42px;width:auto}
.tagline{font-size:.5rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--c-ink-3)}
.nav{margin-left:auto;display:flex;gap:var(--s-5);align-items:center}
.nav a{color:var(--c-ink-2);font-size:var(--t-small);font-weight:600}
.nav a:hover,.nav a[aria-current]{color:var(--c-ink)}
.langsw{display:inline-flex;gap:2px;border:1px solid var(--c-line);border-radius:999px;padding:3px;font-size:.75rem;font-weight:800}
.langsw a{color:var(--c-ink-2);padding:.45rem .7rem;border-radius:999px;line-height:1}
.langsw a.on{background:var(--c-ink);color:#fff}
@media(max-width:900px){.nav{display:none}.head-row{gap:var(--s-3)}.head-row .langsw{margin-left:auto}.tagline{display:none}.brand img{height:34px}}
/* ήρωας — ανοιχτόχρωμος, με πλέγμα, όπως οι σελίδες του site */
.hero-wrap{position:relative;overflow:hidden;border-bottom:1px solid var(--c-line);background:
  radial-gradient(60% 80% at 85% 10%,rgba(46,155,255,.10),transparent 60%),
  radial-gradient(50% 70% at 10% 90%,rgba(18,181,172,.08),transparent 60%),#fff}
.hero-wrap::before{content:"";position:absolute;inset:0;background:
  linear-gradient(var(--c-line) 1px,transparent 1px) 0 0/48px 48px,
  linear-gradient(90deg,var(--c-line) 1px,transparent 1px) 0 0/48px 48px;opacity:.55;
  -webkit-mask-image:linear-gradient(180deg,#000,transparent 85%);mask-image:linear-gradient(180deg,#000,transparent 85%)}
.hero-wrap>.wrap{position:relative}
.hero-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:var(--s-7);align-items:center;padding-block:clamp(3rem,6vw,5.5rem) var(--s-6)}
.hero-title{font-size:var(--t-h1);margin:var(--s-3) 0 var(--s-4)}
.hero-sub{font-size:clamp(1.05rem,1rem + .3vw,1.2rem);color:var(--c-ink-2);max-width:56ch}
.hero-cta{display:flex;gap:var(--s-3);flex-wrap:wrap;margin-top:var(--s-6)}
.hero-photo{border-radius:var(--r-lg);overflow:hidden;border:1px solid var(--c-line);box-shadow:var(--sh-3);aspect-ratio:4/3}
.hero-photo img{width:100%;height:100%;object-fit:cover}
.kpi{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border:1px solid var(--c-line);border-radius:var(--r-lg);background:var(--c-surface);overflow:hidden;margin-bottom:clamp(2rem,4vw,3rem)}
.kpi div{padding:var(--s-5)}.kpi div+div{border-left:1px solid var(--c-line)}
.kpi b{display:block;font-size:1.5rem;color:var(--c-ink)}.kpi span{font-size:var(--t-small);color:var(--c-ink-2)}
@media(max-width:860px){.hero-grid{grid-template-columns:1fr}.hero-photo{display:none}}
@media(max-width:520px){.kpi{grid-template-columns:1fr}.kpi div+div{border-left:0;border-top:1px solid var(--c-line)}}
/* ενότητες */
.section{padding-block:clamp(3rem,6vw,5rem)}
.band-soft{background:var(--c-surface-2)}
.sec-head{max-width:720px;margin-bottom:var(--s-6);padding-top:var(--s-4);border-top:1px solid var(--c-line)}
.sec-head .n{display:block;font-family:var(--f-mono);font-size:var(--t-micro);letter-spacing:.08em;color:var(--c-brand-2-ink);margin-bottom:var(--s-2)}
.sec-head h2{font-size:var(--t-h2);margin-bottom:var(--s-2)}
.sec-head p{color:var(--c-ink-2)}
.grid{display:grid;gap:var(--s-5)}
.card{background:var(--c-surface);border:1px solid var(--c-line);border-radius:var(--r-lg);padding:var(--s-6);box-shadow:var(--sh-1)}
.ico-box{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:#E6F2FF;color:#1668C7;margin-bottom:var(--s-4)}
.ico-box svg{width:22px;height:22px}
.card h3{font-size:1.15rem;margin-bottom:var(--s-2)}
.card p{color:var(--c-ink-2);font-size:.97rem}
.photo-band{position:relative;border-radius:var(--r-lg);overflow:hidden;margin-top:var(--s-6);min-height:280px;display:flex;align-items:flex-end}
.photo-band img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.photo-band::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(11,18,32,.82),rgba(11,18,32,.2))}
.photo-band .txt{position:relative;z-index:1;padding:var(--s-6);max-width:520px}
.photo-band h3{color:#fff;font-size:var(--t-h3);margin-bottom:var(--s-2)}.photo-band p{color:#D7E1F0}
/* θέσεις */
.jobs{display:grid;gap:var(--s-5);grid-template-columns:repeat(auto-fill,minmax(min(320px,100%),1fr))}
.job{display:flex;flex-direction:column;background:var(--c-surface);border:1px solid var(--c-line);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--sh-1);transition:transform .2s,box-shadow .2s,border-color .2s}
.job:hover{transform:translateY(-3px);box-shadow:var(--sh-2);border-color:#A9D3FF}
.job-cover{aspect-ratio:16/9;background:#eef2f7 no-repeat center 30%/cover}
.job-body{padding:var(--s-5);display:flex;flex-direction:column;gap:var(--s-3);flex:1}
.job h3{font-size:1.2rem}
.meta{display:flex;gap:var(--s-2);flex-wrap:wrap}
.tag{font-family:var(--f-mono);font-size:var(--t-micro);padding:3px var(--s-2);border-radius:var(--r-sm);border:1px solid var(--c-line);color:var(--c-ink-2);white-space:nowrap}
.job p.d{font-size:.95rem;color:var(--c-ink-2);display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.chips{display:flex;gap:6px;flex-wrap:wrap}
.chip{font-size:.78rem;font-weight:600;color:var(--c-ink-2);background:var(--c-surface-2);border-radius:999px;padding:3px 10px}
.acts{display:flex;gap:var(--s-2);flex-wrap:wrap;margin-top:auto;padding-top:var(--s-2)}
.no-fit{margin-top:var(--s-6);padding:var(--s-5);border:1px dashed #C9D6E6;border-radius:var(--r-md);display:flex;gap:var(--s-3);align-items:center;justify-content:space-between;flex-wrap:wrap;color:var(--c-ink-2);background:#fff}
/* περιγραφή αγγελίας */
.jobdesc h4{font-family:var(--f-mono);font-size:var(--t-micro);letter-spacing:.08em;color:var(--c-brand-2-ink);margin:var(--s-5) 0 var(--s-2);text-transform:uppercase}
.jobdesc h4:first-child{margin-top:0}
.jobdesc p{font-size:.97rem;color:var(--c-ink-2);margin:0 0 var(--s-2)}
.jobdesc ul{list-style:none;margin:0 0 var(--s-3);padding:0;display:grid;gap:var(--s-2)}
.jobdesc li{position:relative;padding-left:1.6rem;font-size:.97rem;color:var(--c-ink-2)}
.jobdesc li::before{content:"";position:absolute;left:2px;top:.5em;width:9px;height:5px;border-left:2px solid var(--c-brand-2);border-bottom:2px solid var(--c-brand-2);transform:rotate(-45deg)}
/* παράθυρο αγγελίας */
.modal{position:fixed;inset:0;background:rgba(11,18,32,.55);backdrop-filter:blur(4px);display:none;z-index:100;padding:var(--s-4);overflow-y:auto}
.modal.show{display:block}
.modal-box{background:var(--c-surface);border-radius:var(--r-lg);max-width:720px;margin:4vh auto var(--s-7);overflow:hidden;box-shadow:var(--sh-3);position:relative}
.modal-cover{aspect-ratio:21/9;background:#eef2f7 no-repeat center 30%/cover}
.modal-in{padding:var(--s-6)}
.modal-x{position:absolute;top:14px;right:14px;width:44px;height:44px;border-radius:50%;border:0;background:rgba(255,255,255,.94);box-shadow:var(--sh-2);font-size:1rem;cursor:pointer;color:var(--c-ink)}
#jmTitle{font-size:var(--t-h3);margin-bottom:var(--s-3)}
#jmMeta{display:flex;gap:var(--s-2);flex-wrap:wrap;margin-bottom:var(--s-5)}
#jmSkills{margin-top:var(--s-4)}
.modal-foot{display:flex;gap:var(--s-3);flex-wrap:wrap;border-top:1px solid var(--c-line);padding-top:var(--s-5);margin-top:var(--s-5)}
/* σελίδα φόρμας */
.apply-grid{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:var(--s-6);align-items:start;padding-block:var(--s-4) var(--s-7)}
.jobctx{position:sticky;top:100px;background:var(--c-surface);border:1px solid var(--c-line);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--sh-1)}
.jobctx-cover{aspect-ratio:16/9;background:#eef2f7 no-repeat center 30%/cover}
.jobctx-in{padding:var(--s-5)}
.assigned{display:inline-block;font-family:var(--f-mono);font-size:var(--t-micro);font-weight:700;letter-spacing:.06em;color:var(--c-brand-2-ink);background:#E3F6F4;border-radius:var(--r-sm);padding:3px var(--s-2);margin-bottom:var(--s-3)}
.jobctx h1{font-size:var(--t-h3);margin-bottom:var(--s-3)}
.jobctx details{margin-top:var(--s-4);border-top:1px solid var(--c-line);padding-top:var(--s-3)}
.jobctx summary{cursor:pointer;font-weight:700;color:var(--c-link);list-style:none;min-height:44px;display:flex;align-items:center;gap:var(--s-2)}
.jobctx summary::-webkit-details-marker{display:none}
.jobctx summary::before{content:"+";display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:var(--c-surface-2);transition:transform .2s}
.jobctx details[open] summary::before{transform:rotate(45deg)}
.jobctx .jobdesc{margin-top:var(--s-3)}
.back{display:inline-flex;align-items:center;min-height:44px;margin-top:var(--s-4);font-family:var(--f-mono);font-size:var(--t-micro);font-weight:700;letter-spacing:.06em}
.form-card{background:var(--c-surface);border:1px solid var(--c-line);border-top:3px solid var(--c-brand);border-radius:var(--r-lg);padding:var(--s-6);box-shadow:var(--sh-2)}
.form-card h2{font-size:var(--t-h3);margin-bottom:var(--s-2)}
.f-row{display:grid;grid-template-columns:1fr 1fr;gap:0 var(--s-4)}
.f-field{margin-top:var(--s-4)}
.f-field label{display:block;font-size:var(--t-small);font-weight:700;color:var(--c-ink-2);margin-bottom:var(--s-2)}
.f-field input,.f-field textarea{width:100%;font:inherit;font-size:1rem;padding:.75rem 1rem;border:1px solid #CDD8E6;border-radius:var(--r-md);background:#fff;color:var(--c-ink)}
.f-field input:focus,.f-field textarea:focus{outline:none;border-color:var(--c-brand);box-shadow:0 0 0 3px rgba(46,155,255,.18)}
.f-field input[type=file]{padding:.6rem;background:var(--c-surface-2);border-style:dashed}
.f-field textarea{min-height:130px;resize:vertical}
.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.form-card .btn{width:100%;margin-top:var(--s-5)}
.note{font-size:var(--t-small);color:var(--c-ink-3);margin-top:var(--s-3);text-align:center}
.note a{text-decoration:underline;text-underline-offset:3px}
.alert{padding:var(--s-3) var(--s-4);border-radius:var(--r-md);margin-top:var(--s-4);font-size:.95rem;background:#FDECEE;color:#A42A36;border:1px solid #F5C2C7}
.done{max-width:640px;margin:var(--s-8) auto;text-align:center}
.done .ic{width:76px;height:76px;border-radius:50%;background:#E3F5EC;color:var(--c-ok);font-size:2.2rem;display:grid;place-items:center;margin:0 auto var(--s-5)}
.done h1{font-size:var(--t-h2);margin-bottom:var(--s-3)}.done p{color:var(--c-ink-2)}
.empty{text-align:center;color:var(--c-ink-2);padding:var(--s-6)}
@media(max-width:860px){.apply-grid{grid-template-columns:1fr}.jobctx{position:static}}
@media(max-width:560px){.f-row{grid-template-columns:1fr}}
/* υποσέλιδο — όπως στο site */
.site-foot{border-top:1px solid var(--c-line)}
.foot-cta{background:var(--c-surface-2);border-bottom:1px solid var(--c-line)}
.foot-cta-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.3fr);gap:var(--s-6);padding-block:var(--s-7);align-items:start}
.foot-cta h2{font-size:var(--t-h2);margin-bottom:var(--s-2)}.foot-cta p{color:var(--c-ink-2);margin-bottom:var(--s-4)}
.foot-contact{list-style:none;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:var(--s-5)}
.foot-contact li{display:grid;gap:4px;align-content:start}
.foot-tel{font-size:1.06rem;font-weight:700;color:var(--c-ink)}
.foot-contact .mut{font-size:var(--t-small)}
.foot-main{padding-block:var(--s-7) var(--s-5)}
.foot-grid{display:grid;gap:var(--s-6);grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr))}
.foot-grid ul{list-style:none}.foot-grid li{min-height:34px;display:flex;align-items:center}
.foot-grid a{color:var(--c-ink-2);font-size:var(--t-small)}.foot-grid a:hover{color:var(--c-ink)}
.foot-legal{margin-top:var(--s-6);padding-top:var(--s-4);border-top:1px solid var(--c-line);font-size:var(--t-small);color:var(--c-ink-3)}
@media(max-width:860px){.foot-cta-row{grid-template-columns:1fr}}
@media (prefers-reduced-motion:reduce){*{transition:none!important;scroll-behavior:auto!important}}
</style></head>
<body>
<a class="skip" href="#main"><?= $lang === 'en' ? 'Skip to content' : 'Μετάβαση στο περιεχόμενο' ?></a>
<?php
$langSwitch = '<span class="langsw"><a href="' . $e($switch('el')) . '"' . ($lang === 'el' ? ' class="on" aria-current="true"' : '') . ' hreflang="el">EL</a><a href="' . $e($switch('en')) . '"' . ($lang === 'en' ? ' class="on" aria-current="true"' : '') . ' hreflang="en">EN</a></span>';
$NAV = $lang === 'en'
  ? [['Products', '/#products'], ['Clients', '/clients/'], ['Company', '/about-us/'], ['Careers', ''], ['Support', '/submit-ticket/'], ['Contact', '/contact/']]
  : [['Προϊόντα', '/#products'], ['Πελάτες', '/clients/'], ['Εταιρεία', '/about-us/'], ['Καριέρα', ''], ['Υποστήριξη', '/submit-ticket/'], ['Επικοινωνία', '/contact/']];
$eyebrow = $lang === 'en' ? 'CAREERS AT CLOUDON' : 'ΚΑΡΙΕΡΑ ΣΤΗΝ CLOUDON';
?>
<header class="site-head">
  <div class="wrap head-row">
    <div class="brand-lockup">
      <a class="brand" href="<?= $e($SITE) ?>/" title="CloudOn"><img src="apply-assets/cloudon-logo-crop.png" alt="CloudOn" width="164" height="42"></a>
      <p class="tagline">Innovative e-business solutions</p>
    </div>
    <nav class="nav" aria-label="<?= $lang === 'en' ? 'Main' : 'Κύριο μενού' ?>">
      <?php foreach ($NAV as [$lbl, $href]): ?>
        <?php if ($href === ''): ?><a href="<?= $e($ME . $plang) ?>" aria-current="page"><?= $e($lbl) ?></a>
        <?php else: ?><a href="<?= $e($SITE . $href) ?>"><?= $e($lbl) ?></a><?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <?= $langSwitch ?>
  </div>
</header>
<main id="main">

<?php if ($mode === 'form'): // ── ΣΕΛΙΔΑ ΦΟΡΜΑΣ ── ?>
<div class="wrap">
  <a class="back" href="<?= $e($ME . $plang) ?>"><?= $e($t('back_all')) ?></a>
  <div class="apply-grid">
    <aside class="jobctx">
      <?php if ($formJob): ?><div class="jobctx-cover" style="background-image:url('<?= $e($jobImg($formJob)) ?>')"></div><?php endif; ?>
      <div class="jobctx-in">
        <span class="assigned"><?= $e($t('assigned')) ?></span>
        <h1><?= $e($formJob ? $jl($formJob, 'title') : $t('general_title')) ?></h1>
        <?php if ($formJob): ?>
          <div class="meta">
            <?php if ($formJob->location): ?><span class="tag"><?= $e($jl($formJob, 'location')) ?></span><?php endif; ?>
            <?php if ($jl($formJob, 'emptype')): ?><span class="tag"><?= $e($jl($formJob, 'emptype')) ?></span><?php endif; ?>
          </div>
          <?php if (trim((string) $jl($formJob, 'skills')) !== ''): ?><div class="chips" style="margin-top:var(--s-3)"><?= $skillChips($jl($formJob, 'skills')) ?></div><?php endif; ?>
          <?php if (trim((string) $jl($formJob, 'descr')) !== ''): ?>
          <details><summary><?= $e($t('see_full')) ?></summary><div class="jobdesc"><?= $fmtDesc($jl($formJob, 'descr')) ?></div></details>
          <?php endif; ?>
        <?php else: ?>
          <p class="mut"><?= $e($t('general_desc')) ?></p>
        <?php endif; ?>
      </div>
    </aside>

    <div class="form-card">
      <h2><?= $e($t('form_h')) ?></h2>
      <p class="mut"><?= $e($t('form_sub')) ?></p>
      <?php if ($err): ?><div class="alert" role="alert"><?= $e($err) ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" autocomplete="on" action="<?= $e($ME) ?>?job=<?= $formJob ? (int) $formJob->id : 'general' ?><?= $qlang ?>">
        <input type="hidden" name="ts" value="<?= $now ?>">
        <input type="hidden" name="lang" value="<?= $lang ?>">
        <?php if ($formJob): ?><input type="hidden" name="job" value="<?= (int) $formJob->id ?>"><?php else: ?><input type="hidden" name="general" value="1"><?php endif; ?>
        <div class="hp" aria-hidden="true"><label for="f-web">Website</label><input id="f-web" type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="f-row">
          <div class="f-field"><label for="f-name"><?= $e($t('l_name')) ?> *</label><input id="f-name" type="text" name="name" required maxlength="150" autocomplete="name" value="<?= $e($_POST['name'] ?? '') ?>"></div>
          <div class="f-field"><label for="f-email"><?= $e($t('l_email')) ?> *</label><input id="f-email" type="email" name="email" required maxlength="150" autocomplete="email" value="<?= $e($_POST['email'] ?? '') ?>"></div>
        </div>
        <div class="f-field"><label for="f-phone"><?= $e($t('l_phone')) ?></label><input id="f-phone" type="tel" name="phone" maxlength="50" autocomplete="tel" value="<?= $e($_POST['phone'] ?? '') ?>"></div>
        <div class="f-field"><label for="f-cv"><?= $e($t('l_cv')) ?> *</label><input id="f-cv" type="file" name="cv" accept=".pdf,.doc,.docx" required></div>
        <div class="f-field"><label for="f-letter"><?= $e($t('l_letter')) ?></label><textarea id="f-letter" name="letter" rows="4" maxlength="6000" placeholder="<?= $e($t('letter_ph')) ?>"><?= $e($_POST['letter'] ?? '') ?></textarea></div>
        <button class="btn btn-primary" type="submit"><?= $e($t('submit')) ?></button>
        <p class="note"><?= $e($t('consent')) ?> <a href="<?= $e($SITE) ?>/personal-data/"><?= $lang === 'en' ? 'Privacy' : 'Προσωπικά δεδομένα' ?></a></p>
      </form>
    </div>
  </div>
</div>

<?php elseif ($done): // ── THANK YOU ── ?>
<div class="wrap">
  <div class="done"><div class="ic" aria-hidden="true">✓</div>
    <h1><?= $e($t('ty_h')) ?></h1>
    <p><?= $e($t('ty_thanks')) . ($doneJob ? $e($t('ty_for')) . $e($doneJob) . $e($t('ty_tail')) : $e($t('ty_tail2'))) ?></p>
    <p style="margin-top:var(--s-5)"><a class="btn btn-ghost" href="<?= $e($ME . $plang) ?>"><?= $e($t('ty_more')) ?></a></p>
  </div>
</div>

<?php else: // ── LANDING ── ?>
<section class="hero-wrap">
  <div class="wrap">
    <div class="hero-grid">
      <div>
        <p class="micro" style="color:var(--c-brand-2-ink)"><?= $e($eyebrow) ?></p>
        <h1 class="hero-title"><?= $e($t('h1a')) ?><em><?= $e($t('h1b')) ?></em></h1>
        <p class="hero-sub"><?= $e($t('hero_p')) ?></p>
        <p class="hero-cta">
          <a class="btn btn-primary" href="#jobs"><?= $e($t('see_jobs')) ?></a>
          <a class="btn btn-ghost" href="<?= $e($ME) ?>?job=general<?= $qlang ?>"><?= $e(rtrim($t('general_link'), ' →')) ?></a>
        </p>
      </div>
      <div class="hero-photo"><img src="apply-assets/office.jpg" alt="" loading="eager"></div>
    </div>
    <div class="kpi">
      <div><b><?= count($jobs) ?></b><span><?= $e($t('stat_jobs')) ?></span></div>
      <div><b>100%</b><span><?= $e($t('stat_tech')) ?></span></div>
      <div><b>∞</b><span><?= $e($t('stat_grow')) ?></span></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="sec-head"><span class="n">01 — <?= $lang === 'en' ? 'WHY CLOUDON' : 'ΓΙΑΤΙ CLOUDON' ?></span><h2><?= $e($t('why_h')) ?></h2><p><?= $e($t('why_sub')) ?></p></div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr))">
      <div class="card"><div class="ico-box"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg></div><h3><?= $e($t('v1t')) ?></h3><p><?= $e($t('v1p')) ?></p></div>
      <div class="card"><div class="ico-box"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><h3><?= $e($t('v2t')) ?></h3><p><?= $e($t('v2p')) ?></p></div>
      <div class="card"><div class="ico-box"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg></div><h3><?= $e($t('v3t')) ?></h3><p><?= $e($t('v3p')) ?></p></div>
      <div class="card"><div class="ico-box"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg></div><h3><?= $e($t('v4t')) ?></h3><p><?= $e($t('v4p')) ?></p></div>
    </div>
    <div class="photo-band">
      <img src="apply-assets/collab.jpg" alt="" loading="lazy">
      <div class="txt"><h3><?= $e($t('band_h')) ?></h3><p><?= $e($t('band_p')) ?></p></div>
    </div>
  </div>
</section>

<section class="section band-soft" id="jobs">
  <div class="wrap">
    <div class="sec-head"><span class="n">02 — <?= $lang === 'en' ? 'OPEN POSITIONS' : 'ΑΝΟΙΧΤΕΣ ΘΕΣΕΙΣ' ?></span><h2><?= $e($t('jobs_h')) ?></h2><p><?= $e($t('jobs_sub')) ?></p></div>
    <?php if (count($jobs)): ?>
      <div class="jobs">
        <?php foreach ($jobs as $j): ?>
          <article class="job">
            <div class="job-cover" style="background-image:url('<?= $e($jobImg($j)) ?>')"></div>
            <div class="job-body">
              <h3><?= $e($jl($j, 'title')) ?></h3>
              <div class="meta">
                <?php if ($j->location): ?><span class="tag"><?= $e($jl($j, 'location')) ?></span><?php endif; ?>
                <?php if ($jl($j, 'emptype')): ?><span class="tag"><?= $e($jl($j, 'emptype')) ?></span><?php endif; ?>
              </div>
              <?php if (trim((string) $jl($j, 'descr')) !== ''): ?><p class="d"><?= $e(mb_substr(strip_tags($jl($j, 'descr')), 0, 170)) ?>…</p><?php endif; ?>
              <?php if (trim((string) $jl($j, 'skills')) !== ''): ?><div class="chips"><?= $skillChips($jl($j, 'skills')) ?></div><?php endif; ?>
              <div class="acts">
                <button class="btn btn-ghost btn-sm" type="button" onclick="openJob(<?= (int) $j->id ?>)"><?= $e($t('view_posting')) ?></button>
                <a class="btn btn-primary btn-sm" href="<?= $e($ME) ?>?job=<?= (int) $j->id ?><?= $qlang ?>"><?= $e($t('apply_cta')) ?></a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="no-fit"><span><?= $e($t('no_fit')) ?></span><a class="btn btn-ghost btn-sm" href="<?= $e($ME) ?>?job=general<?= $qlang ?>"><?= $e($t('general_link')) ?></a></div>
    <?php else: ?>
      <div class="empty"><?= $e($t('no_jobs')) ?><br><a class="btn btn-primary btn-sm" style="margin-top:var(--s-4)" href="<?= $e($ME) ?>?job=general<?= $qlang ?>"><?= $e($t('general_link')) ?></a></div>
    <?php endif; ?>
  </div>
</section>

<div class="modal" id="jobModal" onclick="if(event.target===this)closeJob()" role="dialog" aria-modal="true" aria-labelledby="jmTitle">
  <div class="modal-box">
    <button class="modal-x" type="button" onclick="closeJob()" aria-label="<?= $e($t('close')) ?>">✕</button>
    <div id="jmImg" class="modal-cover"></div>
    <div class="modal-in">
      <h2 id="jmTitle"></h2>
      <div id="jmMeta"></div>
      <div id="jmDesc" class="jobdesc"></div>
      <div id="jmSkills"></div>
      <div class="modal-foot">
        <a id="jmApply" class="btn btn-primary" href="#"><?= $e($t('apply_cta')) ?></a>
        <button class="btn btn-ghost" type="button" onclick="closeJob()"><?= $e($t('close')) ?></button>
      </div>
    </div>
  </div>
</div>
<script>
const JOBS = <?= json_encode($jobsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const LANG = '<?= $lang ?>';
const ME = <?= json_encode($ME) ?>;
let lastFocus = null;
function openJob(id){
  const j = JOBS[id]; if(!j) return;
  lastFocus = document.activeElement;
  document.getElementById('jmImg').style.backgroundImage = j.image ? "url('" + j.image + "')" : '';
  document.getElementById('jmTitle').textContent = j.title;
  document.getElementById('jmMeta').innerHTML = (j.location?'<span class="tag">'+esc(j.location)+'</span>':'') + (j.emptype?'<span class="tag">'+esc(j.emptype)+'</span>':'');
  document.getElementById('jmDesc').innerHTML = j.html || '<p class="mut">—</p>';
  document.getElementById('jmSkills').innerHTML = j.skills ? '<div class="chips">' + j.skills + '</div>' : '';
  document.getElementById('jmApply').href = ME + '?job=' + id + '&lang=' + LANG;
  document.getElementById('jobModal').classList.add('show');
  document.body.style.overflow = 'hidden';
  document.querySelector('.modal-x').focus();
  trackJob(id);
}
// Μετράει ότι κάποιος διάβασε την αγγελία, ακόμη κι αν δεν κάνει αίτηση.
// Μία φορά ανά θέση σε κάθε επίσκεψη — τα ξανα-ανοίγματα δεν φουσκώνουν το νούμερο.
const TRACKED = new Set();
function trackJob(id){
  if (TRACKED.has(id)) return;
  TRACKED.add(id);
  const u = ME + '?trk=' + encodeURIComponent(id);
  if (navigator.sendBeacon) { navigator.sendBeacon(u); }
  else { fetch(u, {method:'GET', keepalive:true}).catch(()=>{}); }
}
function closeJob(){ document.getElementById('jobModal').classList.remove('show'); document.body.style.overflow=''; if (lastFocus) lastFocus.focus(); }
function esc(s){ return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
document.addEventListener('keydown', e => { if(e.key==='Escape') closeJob(); });
</script>

<?php endif; ?>
</main>

<footer class="site-foot">
  <section class="foot-cta">
    <div class="wrap foot-cta-row">
      <div>
        <h2><?= $lang === 'en' ? 'Questions about a position?' : 'Ερωτήσεις για μια θέση;' ?></h2>
        <p><?= $lang === 'en' ? 'Write to us — a person from the team will answer.' : 'Γράψε μας — θα σου απαντήσει άνθρωπος της ομάδας.' ?></p>
        <a class="btn btn-primary" href="mailto:info@cloudon.gr?subject=<?= rawurlencode($lang === 'en' ? 'Careers' : 'Καριέρα') ?>">info@cloudon.gr</a>
      </div>
      <ul class="foot-contact">
        <li><span class="micro"><?= $lang === 'en' ? 'GREECE' : 'ΕΛΛΑΔΑ' ?></span><a class="foot-tel" href="tel:+302107222560">210 7222560</a><span class="mut"><?= $lang === 'en' ? '13 Peloponnisou, 15341 Agia Paraskevi' : 'Πελοποννήσου 13, 15341 Αγία Παρασκευή' ?></span></li>
        <li><span class="micro"><?= $lang === 'en' ? 'CYPRUS' : 'ΚΥΠΡΟΣ' ?></span><a class="foot-tel" href="tel:+35722056009">+357 22 056009</a></li>
        <li><span class="micro">EMAIL</span><a class="foot-tel" href="mailto:info@cloudon.gr">info@cloudon.gr</a></li>
      </ul>
    </div>
  </section>
  <div class="wrap foot-main">
    <div class="foot-grid">
      <div class="brand-lockup"><a class="brand" href="<?= $e($SITE) ?>/"><img src="apply-assets/cloudon-logo-crop.png" alt="CloudOn" width="164" height="42" loading="lazy"></a><p class="tagline">Innovative e-business solutions</p></div>
      <div><p class="micro" style="margin-bottom:var(--s-3)"><?= $lang === 'en' ? 'PRODUCTS' : 'ΠΡΟΪΟΝΤΑ' ?></p><ul>
        <?php foreach ([['PharmacyOne','pharmacyone'],['RxVision','rxvision'],['BoxVisio','boxvisio'],['Nous','nous'],['Cloud Services','cloud-services'],['Cloud PBX','cloud-pbx'],['E-Commerce','ecommerce'],['Findora','findora'],['CarOn','caron']] as [$n, $s]): ?>
          <li><a href="<?= $e($SITE . '/' . $s . '/') ?>"><?= $e($n) ?></a></li>
        <?php endforeach; ?>
      </ul></div>
      <div><p class="micro" style="margin-bottom:var(--s-3)"><?= $lang === 'en' ? 'SERVICES' : 'ΥΠΗΡΕΣΙΕΣ' ?></p><ul>
        <li><a href="<?= $e($SITE) ?>/erp/">SoftOne ERP</a></li>
        <li><a href="<?= $e($SITE) ?>/backup-restore-solutions/">Backup &amp; Restore</a></li>
        <li><a href="<?= $e($SITE) ?>/web-design/"><?= $lang === 'en' ? 'Web design' : 'Κατασκευή ιστοσελίδων' ?></a></li>
      </ul></div>
      <div><p class="micro" style="margin-bottom:var(--s-3)"><?= $lang === 'en' ? 'COMPANY' : 'ΕΤΑΙΡΕΙΑ' ?></p><ul>
        <li><a href="<?= $e($SITE) ?>/about-us/"><?= $lang === 'en' ? 'About us' : 'Ποιοι είμαστε' ?></a></li>
        <li><a href="<?= $e($SITE) ?>/clients/"><?= $lang === 'en' ? 'Clients' : 'Πελατολόγιο' ?></a></li>
        <li><a href="<?= $e($ME . $plang) ?>"><?= $lang === 'en' ? 'Careers' : 'Καριέρα' ?></a></li>
        <li><a href="<?= $e($SITE) ?>/personal-data/"><?= $lang === 'en' ? 'Personal data' : 'Προσωπικά δεδομένα' ?></a></li>
        <li><a href="<?= $e($SITE) ?>/terms-conditions/"><?= $lang === 'en' ? 'Terms of use' : 'Όροι χρήσης' ?></a></li>
      </ul></div>
    </div>
    <p class="foot-legal">© <?= date('Y') ?> CloudOn · Innovative e-business solutions</p>
  </div>
</footer>

</body></html>
