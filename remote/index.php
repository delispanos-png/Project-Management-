<?php
/**
 * CloudOn — Απομακρυσμένη υποστήριξη (δημόσια σελίδα λήψης).
 *
 * Δίνουμε στον πελάτη μία εύκολη διεύθυνση (my.cloudon.gr/remote) αντί για
 * download link. Επώνυμη, φιλική, με βήματα — χωρίς τεχνική ορολογία.
 * Γλώσσα: ?lang=el|en (default el).
 */

$lang = (($_GET['lang'] ?? '') === 'en') ? 'en' : 'el';
$DL     = '/remote/pack.php?f=client';                                   // ZIP (καθαρό κατέβασμα)
$DL_EXE = 'https://remote.cloudon.gr/download/CloudOn-Remote.exe';        // απευθείας .exe
$FIX    = '/remote/pack.php?f=fix';
$PHONE = '2107222560';          // για το tel: link (σκέτα ψηφία)
$PHONE_TXT = '210 7222560';      // όπως εμφανίζεται
$SUPPORT_URL = 'https://my.cloudon.gr/submitticket.php';

$T = [
  'el' => [
    'title' => 'Απομακρυσμένη υποστήριξη — CloudOn',
    'meta' => 'Κατέβασε το εργαλείο απομακρυσμένης υποστήριξης της CloudOn και σύνδεσε τον τεχνικό μας στον υπολογιστή σου με ασφάλεια.',
    'eyebrow' => 'Απομακρυσμένη υποστήριξη',
    'h1' => 'Ας το δούμε μαζί,',
    'h1b' => 'σαν να ήμασταν δίπλα σου.',
    'lead' => 'Κατέβασε το εργαλείο μας και ο τεχνικός θα συνδεθεί στον υπολογιστή σου για να λύσει το θέμα. Δεν χρειάζεται εγκατάσταση ούτε τεχνικές γνώσεις.',
    'dl' => 'Λήψη για Windows',
    'dl_alt' => 'ή κατέβασε απευθείας το .exe',
    'dl_sub' => '23 MB · Αρχείο ZIP · Δεν χρειάζεται εγκατάσταση',
    'steps_h' => 'Τρία απλά βήματα',
    's1t' => 'Κατέβασε & άνοιξε',
    's1p' => 'Πάτα το κουμπί λήψης. Κατεβαίνει ένα αρχείο <b>ZIP</b>: κάνε <b>διπλό κλικ</b> για να ανοίξει και μετά <b>διπλό κλικ στο πρόγραμμα</b> που είναι μέσα. Αν σε ρωτήσει ο υπολογιστής αν επιτρέπεις να τρέξει, πάτα «Ναι».',
    's2t' => 'Πες μας τους κωδικούς',
    's2p' => 'Θα δεις δύο αριθμούς: <b>Το ID σου</b> και έναν <b>Κωδικό</b>. Διάβασέ τους στον τεχνικό στο τηλέφωνο ή γράψ’ τους στο ticket.',
    's3t' => 'Έτοιμα!',
    's3p' => 'Ο τεχνικός συνδέεται και βλέπεις στην οθόνη σου ό,τι κάνει. Μπορείς να διακόψεις τη σύνδεση όποτε θέλεις.',
    'safe_h' => 'Είσαι απόλυτα ασφαλής',
    'safe1' => 'Κανείς δεν μπορεί να συνδεθεί χωρίς τον κωδικό που <b>εσύ</b> δίνεις.',
    'safe2' => 'Βλέπεις στην οθόνη σου ό,τι κάνει ο τεχνικός, σε πραγματικό χρόνο.',
    'safe3' => 'Μόλις κλείσεις το πρόγραμμα, η σύνδεση <b>τερματίζεται οριστικά</b>.',
    'safe4' => 'Η σύνδεση είναι κρυπτογραφημένη και περνά μόνο από δικούς μας servers.',
    'help_h' => 'Κάτι δεν πάει καλά;',
    'help_p' => 'Αν το πρόγραμμα δεν συνδέεται ή σου βγάζει σφάλμα, τρέξε αυτό το μικρό εργαλείο επιδιόρθωσης και δοκίμασε ξανά.',
    'help_btn' => 'Εργαλείο επιδιόρθωσης',
    'help_note' => 'Χρειάζεται δεξί κλικ → «Εκτέλεση ως διαχειριστής».',
    'contact_h' => 'Προτιμάς να μιλήσουμε;',
    'contact_p' => 'Είμαστε εδώ για σένα.',
    'call' => 'Τηλεφώνησέ μας',
    'ticket' => 'Άνοιξε αίτημα',
    'warn_h' => 'Τι θα δεις όταν το ανοίξεις',
    'warn_p' => 'Τα Windows ρωτούν πριν τρέξει <b>κάθε</b> πρόγραμμα που δεν ήρθε από το Microsoft Store — ακόμη κι αν είναι απολύτως ασφαλές. <b>Δεν σημαίνει ότι υπάρχει ιός.</b> Δες τι να πατήσεις:',
    'warn_w1t' => '«Τα Windows προστάτευσαν τον υπολογιστή σας»',
    'warn_w1p' => 'Πάτα <b>«Περισσότερες πληροφορίες»</b> και μετά εμφανίζεται το κουμπί <b>«Εκτέλεση οπωσδήποτε»</b>.',
    'warn_w2t' => '«Θέλετε να επιτρέψετε σε αυτήν την εφαρμογή…;»',
    'warn_w2p' => 'Πάτα <b>«Ναι»</b>. Είναι το κανονικό παράθυρο αδειών των Windows.',
    'warn_w3t' => '3. «Θέλετε να επιτρέψετε σε αυτήν την εφαρμογή…;»',
    'warn_w3p' => 'Πάτα <b>«Ναι»</b>. Είναι το κανονικό παράθυρο αδειών των Windows.',
    'warn_note' => 'Αν επιλέξεις να κατεβάσεις απευθείας το <b>.exe</b> αντί για το ZIP, ο browser μπορεί να ρωτήσει αν θες να κρατήσεις το αρχείο — πάτα <b>«Διατήρηση» / «Keep»</b>. Το αρχείο κατεβαίνει πάντα από <b>δικό μας server</b> μέσω ασφαλούς σύνδεσης.',
    'foot' => 'Χρειάζεσαι Windows. Για Mac ή κινητό, επικοινώνησε μαζί μας και θα σε καθοδηγήσουμε.',
  ],
  'en' => [
    'title' => 'Remote support — CloudOn',
    'meta' => 'Download the CloudOn remote support tool and let our technician connect to your computer securely.',
    'eyebrow' => 'Remote support',
    'h1' => 'Let’s look at it together,',
    'h1b' => 'as if we were next to you.',
    'lead' => 'Download our tool and a technician will connect to your computer to fix the issue. No installation and no technical knowledge required.',
    'dl' => 'Download for Windows',
    'dl_alt' => 'or download the .exe directly',
    'dl_sub' => '23 MB · ZIP file · No installation needed',
    'steps_h' => 'Three simple steps',
    's1t' => 'Download & open',
    's1p' => 'Click the download button. A <b>ZIP</b> file downloads: <b>double-click</b> to open it, then <b>double-click the program</b> inside. If your computer asks whether to allow it to run, click “Yes”.',
    's2t' => 'Tell us the codes',
    's2p' => 'You’ll see two numbers: <b>Your ID</b> and a <b>Password</b>. Read them to the technician on the phone, or write them in your ticket.',
    's3t' => 'That’s it!',
    's3p' => 'The technician connects and you see everything they do on your screen. You can end the session at any time.',
    'safe_h' => 'You’re completely safe',
    'safe1' => 'Nobody can connect without the password <b>you</b> provide.',
    'safe2' => 'You watch everything the technician does, in real time.',
    'safe3' => 'The moment you close the program, the connection <b>ends for good</b>.',
    'safe4' => 'The connection is encrypted and runs only through our own servers.',
    'help_h' => 'Something not working?',
    'help_p' => 'If the program won’t connect or shows an error, run this small repair tool and try again.',
    'help_btn' => 'Repair tool',
    'help_note' => 'Right-click → “Run as administrator”.',
    'contact_h' => 'Prefer to talk?',
    'contact_p' => 'We’re here for you.',
    'call' => 'Call us',
    'ticket' => 'Open a ticket',
    'warn_h' => 'What you’ll see when you open it',
    'warn_p' => 'Windows asks before running <b>any</b> program that did not come from the Microsoft Store — even perfectly safe ones. <b>It does not mean there is a virus.</b> Here is what to click:',
    'warn_w1t' => '“Windows protected your PC”',
    'warn_w1p' => 'Click <b>“More info”</b> and then <b>“Run anyway”</b>.',
    'warn_w2t' => '“Do you want to allow this app…?”',
    'warn_w2p' => 'Click <b>“Yes”</b>. This is the standard Windows permission dialog.',
    'warn_w3t' => 'Your browser says the file “isn’t downloaded securely”',
    'warn_w3p' => 'Click the arrow next to the file and choose <b>“Keep”</b> or <b>“Keep anyway”</b>.',
    'warn_note' => 'If you choose the direct <b>.exe</b> instead of the ZIP, your browser may ask whether to keep the file — click <b>“Keep”</b>. The file always downloads from <b>our own server</b> over a secure connection.',
    'foot' => 'Windows required. For Mac or mobile, contact us and we’ll guide you.',
  ],
];
$t = fn($k) => $T[$lang][$k] ?? $T['el'][$k] ?? $k;
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$other = $lang === 'en' ? 'el' : 'en';
?><!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $e($t('title')) ?></title>
<meta name="description" content="<?= $e($t('meta')) ?>">
<meta name="robots" content="index,follow">
<meta property="og:title" content="<?= $e($t('title')) ?>">
<meta property="og:description" content="<?= $e($t('meta')) ?>">
<meta name="theme-color" content="#0090dd">
<link rel="icon" href="/templates/horn/assets/img/favicon.ico">
<style>
*,*::before,*::after{box-sizing:border-box}
:root{
  --ink:#10233d;--txt:#41597a;--mut:#7d90ab;--line:#e2e9f2;--bg:#f5f8fc;--card:#fff;
  --brand:#0090dd;--brand-d:#0072ad;--ok:#16a26a;
}
html,body{margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  background:var(--bg);color:var(--txt);-webkit-font-smoothing:antialiased;line-height:1.6;font-size:16px}
a{color:var(--brand-d)}
.wrap{max-width:820px;margin:0 auto;padding:0 20px}

/* header */
.top{background:var(--card);border-bottom:1px solid var(--line)}
.top-in{display:flex;align-items:center;gap:14px;padding:14px 0}
.top img{height:38px;width:auto;display:block}
.lang{margin-left:auto;font-size:13px;font-weight:700;white-space:nowrap}
.lang a{text-decoration:none;padding:5px 9px;border-radius:8px;color:var(--mut)}
.lang a.on{background:#0090dd15;color:var(--brand-d)}

/* hero */
.hero{background:linear-gradient(165deg,#0d2340,#0a1a2e);color:#eaf3ff;padding:52px 0 60px;text-align:center;position:relative;overflow:hidden}
.hero::after{content:"";position:absolute;width:520px;height:520px;border-radius:50%;
  background:radial-gradient(circle,#0090dd55,transparent 70%);top:-240px;right:-160px;pointer-events:none}
.eyebrow{display:inline-block;font-size:12px;font-weight:800;letter-spacing:2.6px;text-transform:uppercase;
  color:#6fd0ff;background:#0090dd1f;border:1px solid #0090dd44;border-radius:999px;padding:5px 14px;margin-bottom:16px}
h1{margin:0 0 14px;font-size:clamp(27px,5.4vw,42px);line-height:1.15;letter-spacing:-.8px;color:#fff;font-weight:800}
h1 span{display:block;color:#7fd6ff}
.lead{max-width:540px;margin:0 auto 30px;color:#b9cde6;font-size:clamp(15px,2.3vw,17.5px)}

/* download */
.dl{display:inline-flex;align-items:center;gap:12px;background:linear-gradient(135deg,#22b4ff,#0086cf);
  color:#fff;text-decoration:none;font-weight:800;font-size:clamp(16px,2.6vw,19px);
  padding:17px 34px;border-radius:15px;box-shadow:0 16px 38px -10px rgba(0,144,221,.75);
  transition:transform .16s,box-shadow .16s;min-height:56px}
.dl:hover{transform:translateY(-2px);box-shadow:0 22px 46px -10px rgba(0,144,221,.85)}
.dl:active{transform:translateY(0)}
.dl svg{width:22px;height:22px;flex:none}
.dl-sub{margin-top:11px;font-size:13px;color:#8fa8c6}

/* sections */
.sec{padding:46px 0}
h2{font-size:clamp(21px,3.4vw,26px);color:var(--ink);margin:0 0 26px;text-align:center;letter-spacing:-.4px;font-weight:800}
.steps{display:grid;gap:16px}
.step{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px 24px;
  display:flex;gap:18px;align-items:flex-start;box-shadow:0 2px 10px rgba(16,35,61,.04)}
.step-n{flex:none;width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#22b4ff,#0086cf);
  color:#fff;font-weight:800;font-size:19px;display:flex;align-items:center;justify-content:center;
  box-shadow:0 6px 16px -4px rgba(0,144,221,.6)}
.step h3{margin:0 0 5px;font-size:17px;color:var(--ink);font-weight:800}
.step p{margin:0;font-size:15px}

/* safety */
.safe{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:26px 26px 22px;
  box-shadow:0 2px 10px rgba(16,35,61,.04)}
.safe h2{text-align:left;margin-bottom:16px;display:flex;align-items:center;gap:11px;font-size:20px}
.safe ul{list-style:none;margin:0;padding:0;display:grid;gap:12px}
.safe li{display:flex;gap:11px;align-items:flex-start;font-size:15px}
.safe li svg{width:20px;height:20px;flex:none;margin-top:2px;color:var(--ok)}

/* help + contact */
.help{background:#fff8ec;border:1px solid #f0d9ae;border-radius:16px;padding:22px 24px;margin-top:18px}
.help h3{margin:0 0 6px;font-size:17px;color:#8a5a12;font-weight:800}
.help p{margin:0 0 14px;font-size:15px;color:#7a5a24}
.btn2{display:inline-flex;align-items:center;gap:9px;background:#fff;border:1.5px solid #e0c79a;color:#8a5a12;text-decoration:none;font-weight:700;font-size:15px;
  padding:12px 20px;border-radius:11px;min-height:48px}
.btn2:hover{background:#fffdf8}
.help small{display:block;margin-top:9px;color:#96754a;font-size:13px}
.contact{text-align:center;padding:8px 0 0}
.contact-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:16px}
.cbtn{display:inline-flex;align-items:center;gap:9px;background:var(--card);border:1.5px solid var(--line);
  color:var(--ink);text-decoration:none;font-weight:700;font-size:15.5px;padding:13px 24px;border-radius:12px;min-height:50px}
.cbtn:hover{border-color:var(--brand);color:var(--brand-d)}
.cbtn.primary{background:var(--brand);border-color:var(--brand);color:#fff}
.cbtn.primary:hover{background:var(--brand-d);color:#fff}

/* προειδοποιήσεις Windows */
.warn{background:#fff;border:1px solid var(--line);border-left:5px solid #eba63c;border-radius:16px;
  padding:24px 26px;box-shadow:0 2px 10px rgba(16,35,61,.04)}
.warn h2{text-align:left;font-size:20px;margin:0 0 10px;display:flex;align-items:center;gap:10px}
.warn-lead{margin:0 0 18px;font-size:15px}
.warn-list{display:grid;gap:12px}
.warn-item{background:var(--bg);border-radius:12px;padding:14px 16px}
.warn-item > b{display:block;color:var(--ink);font-size:14.5px;margin-bottom:5px}
.warn-item span b{display:inline;color:var(--ink);font-weight:700}
.warn-item span{font-size:14.5px}
.warn-note{margin:18px 0 0;font-size:14px;color:var(--mut);border-top:1px solid var(--line);padding-top:14px}
@media(max-width:620px){.warn{padding:20px 18px}.warn h2{font-size:18px}}

footer{text-align:center;color:var(--mut);font-size:13.5px;padding:34px 20px 44px;border-top:1px solid var(--line);margin-top:38px;background:var(--card)}

@media(max-width:620px){
  .hero{padding:40px 0 46px}
  .sec{padding:34px 0}
  .step{padding:18px 18px;gap:14px}
  .step-n{width:38px;height:38px;font-size:17px}
  .dl{width:100%;justify-content:center;padding:17px 20px}
  .cbtn{flex:1 1 100%;justify-content:center}
}
</style>
</head>
<body>

<header class="top"><div class="wrap top-in">
  <a href="https://www.cloudon.gr"><img src="/templates/horn/assets/img/logo-cloudon.png" alt="CloudOn"></a>
  <span class="lang">
    <a href="?lang=el" class="<?= $lang === 'el' ? 'on' : '' ?>">ΕΛ</a><a href="?lang=en" class="<?= $lang === 'en' ? 'on' : '' ?>">EN</a>
  </span>
</div></header>

<section class="hero"><div class="wrap">
  <span class="eyebrow"><?= $e($t('eyebrow')) ?></span>
  <h1><?= $e($t('h1')) ?><span><?= $e($t('h1b')) ?></span></h1>
  <p class="lead"><?= $e($t('lead')) ?></p>
  <a class="dl" href="<?= $e($DL) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    <?= $e($t('dl')) ?>
  </a>
  <div class="dl-sub"><?= $e($t('dl_sub')) ?><br>
    <a href="<?= $e($DL_EXE) ?>" style="color:#7fd6ff;text-decoration:underline"><?= $e($t('dl_alt')) ?></a></div>
</div></section>

<section class="sec"><div class="wrap">
  <h2><?= $e($t('steps_h')) ?></h2>
  <div class="steps">
    <div class="step"><div class="step-n">1</div><div>
      <h3><?= $e($t('s1t')) ?></h3><p><?= $t('s1p') ?></p></div></div>
    <div class="step"><div class="step-n">2</div><div>
      <h3><?= $e($t('s2t')) ?></h3><p><?= $t('s2p') ?></p></div></div>
    <div class="step"><div class="step-n">3</div><div>
      <h3><?= $e($t('s3t')) ?></h3><p><?= $t('s3p') ?></p></div></div>
  </div>
</div></section>

<section class="sec" style="padding-bottom:0"><div class="wrap">
  <div class="warn">
    <h2>⚠️ <?= $e($t('warn_h')) ?></h2>
    <p class="warn-lead"><?= $t('warn_p') ?></p>
    <div class="warn-list">
      <?php foreach ([['warn_w1t','warn_w1p'], ['warn_w2t','warn_w2p']] as [$a, $b]): ?>
      <div class="warn-item"><b><?= $t($a) ?></b><span><?= $t($b) ?></span></div>
      <?php endforeach; ?>
    </div>
    <p class="warn-note"><?= $t('warn_note') ?></p>
  </div>
</div></section>

<section class="sec" style="padding-top:0"><div class="wrap">
  <div class="safe">
    <h2>🔒 <?= $e($t('safe_h')) ?></h2>
    <ul>
      <?php foreach (['safe1', 'safe2', 'safe3', 'safe4'] as $k): ?>
      <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= $t($k) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="help">
    <h3>🛠 <?= $e($t('help_h')) ?></h3>
    <p><?= $e($t('help_p')) ?></p>
    <a class="btn2" href="<?= $e($FIX) ?>">⬇ <?= $e($t('help_btn')) ?></a>
    <small><?= $e($t('help_note')) ?></small>
  </div>

  <div class="contact sec" style="padding-bottom:0">
    <h2 style="margin-bottom:6px"><?= $e($t('contact_h')) ?></h2>
    <p style="margin:0"><?= $e($t('contact_p')) ?></p>
    <div class="contact-btns">
      <a class="cbtn primary" href="tel:<?= $e($PHONE) ?>">📞 <?= $e($t('call')) ?> <?= $e($PHONE_TXT) ?></a>
      <a class="cbtn" href="<?= $e($SUPPORT_URL) ?>">✉ <?= $e($t('ticket')) ?></a>
    </div>
  </div>
</div></section>

<footer><?= $e($t('foot')) ?><br>© <?= date('Y') ?> CloudOn</footer>
</body>
</html>
