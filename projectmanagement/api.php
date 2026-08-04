<?php
/**
 * CloudOn Project Manager — standalone SPA JSON API.
 * Auth: ενεργή WHMCS admin session (ίδιο cookie/origin) — κανένα δεύτερο login.
 * Επαναχρησιμοποιεί ΟΛΗ τη λογική του module (lib/Db.php, Time, Notify, δικαιώματα).
 */

require_once __DIR__ . '/boot.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudonProjects\Db;
use WHMCS\Module\Addon\CloudonProjects\Time;
use WHMCS\Module\Addon\CloudonProjects\Notify;
use WHMCS\Module\Addon\CloudonProjects\Storage;

require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/Db.php';
require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/Time.php';
require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/Notify.php';
require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/CvPhoto.php';
require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/Storage.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function fail($msg, $code = 400)
{
    http_response_code($code);
    out(['error' => $msg]);
}

$action = $_GET['a'] ?? '';
$adminId = pm_admin_id();
$MEET_ROOM = null;   // guests του CloudOn Meet: έγκυρο room token αντί για login
if ($adminId <= 0) {
    if (strpos($action, 'rtc_') === 0 && ($MEET_ROOM = pm_verify_meet($_REQUEST['mt'] ?? ''))) {
        // ok — signaling ως guest, περιορισμένος στο δωμάτιο του token
    } elseif ($action === 'event_rsvp_public') {
        // ok — δημόσιο RSVP πελάτη με δικό του signed token
    } else {
        fail('auth', 401);
    }
}
$FULL = $adminId > 0 ? Db::isFullAccess($adminId) : false;
$in = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testIn = getenv("CNP_TEST_INPUT");
    $in = json_decode($testIn && PHP_SAPI === "cli" ? file_get_contents($testIn) : file_get_contents("php://input"), true) ?: [];
}

/* ---------- helpers ---------- */
function initials($name)
{
    $p = preg_split('/\s+/', trim((string) $name));
    return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[1] ?? '', 0, 1));
}
/** Κανονικοποίηση ελληνικού κειμένου για ταίριασμα: πεζά, χωρίς τόνους, λέξεις ≥3. */
/**
 * Χονδρική ανίχνευση γλώσσας κειμένου, ως βοήθημα προς το AI.
 *
 * Δεν είναι αυθεντία: τα greeklish («kalimera, thelo voithia») μετρώνται ως
 * λατινικά ενώ είναι ελληνικά. Γι' αυτό το αποτέλεσμα δίνεται στο μοντέλο ως
 * ΥΠΟΔΕΙΞΗ με ρητή άδεια να την αγνοήσει — εκείνο κρίνει καλύτερα από κανόνα.
 *
 * @return string|null 'ελληνικά', 'αγγλικά' ή null όταν δεν υπάρχει επαρκές δείγμα
 */
/**
 * Αλυσίδα πιστώσεων ενός πελάτη: πού κατέληξε κάθε υπερπληρωμή.
 *
 * ΓΙΑΤΙ ΧΡΕΙΑΖΕΤΑΙ: όταν ο πελάτης πληρώσει παραπάνω, το WHMCS κρατά το
 * πλεόνασμα ως πίστωση και το εφαρμόζει σε ΕΠΟΜΕΝΑ παραστατικά. Έτσι μια
 * είσπραξη μπορεί τελικά να εξοφλεί άλλο τιμολόγιο από εκείνο στο οποίο
 * είναι καταχωρημένη — και χωρίς αυτή την αντιστοίχιση το λογιστήριο δεν
 * μπορεί να κάνει ματς.
 *
 * Η κατανάλωση θεωρείται FIFO: η παλαιότερη πίστωση ξοδεύεται πρώτη.
 *
 * @return array [invoiceId πηγής => [['invoice'=>id, 'num'=>'2026…', 'amount'=>float], …]]
 */
function cnp_credit_chain($clientId)
{
    static $cache = [];
    if (isset($cache[$clientId])) {
        return $cache[$clientId];
    }

    $queue = [];      // [invoiceId πηγής, υπόλοιπο]
    $out   = [];

    foreach (Capsule::table('tblcredit')->where('clientid', (int) $clientId)->orderBy('id')->get() as $r) {
        $amt = (float) $r->amount;

        if ($amt > 0) {                       // υπερπληρωμή → μπαίνει στην ουρά
            $src = (int) $r->relid;
            if (!$src && preg_match('/Invoice #(\d+)/', (string) $r->description, $m)) { $src = (int) $m[1]; }
            $queue[] = [$src, $amt];
            continue;
        }

        // Αρνητικό: καταναλώνει πίστωση για κάποιο παραστατικό
        $dst = (int) $r->relid;
        if (!$dst && preg_match('/Invoice #(\d+)/', (string) $r->description, $m)) { $dst = (int) $m[1]; }
        $need = -$amt;

        while ($need > 0.001 && $queue) {
            [$src, $left] = $queue[0];
            $take = min($left, $need);
            $need -= $take;
            $queue[0][1] -= $take;
            if ($queue[0][1] <= 0.001) { array_shift($queue); }

            if ($src && $dst) {
                $out[$src][] = ['invoice' => $dst, 'amount' => round($take, 2)];
            }
        }
    }

    // Συμπλήρωσε τους αριθμούς παραστατικών
    foreach ($out as $src => $list) {
        foreach ($list as $i => $x) {
            $out[$src][$i]['num'] = (string) (Capsule::table('tblinvoices')->where('id', $x['invoice'])->value('invoicenum') ?: ('#' . $x['invoice']));
        }
    }

    return $cache[$clientId] = $out;
}

function cnp_lang_hint($text)
{
    $letters = preg_replace('/[^\p{L}]+/u', '', (string) $text);
    if ($letters === '') {
        return null;
    }
    $greek = preg_match_all('/\p{Greek}/u', $letters);
    $latin = preg_match_all('/\p{Latin}/u', $letters);
    $total = $greek + $latin;
    if ($total < 12) {
        return null;   // πολύ μικρό δείγμα για ασφαλή κρίση
    }
    if ($greek / $total > 0.30) {
        return 'ελληνικά';
    }
    if ($latin / $total > 0.85) {
        return 'αγγλικά (ή greeklish — κρίνε το από το νόημα)';
    }
    return null;
}

function cnp_words($text, $max = 40)
{
    $t = mb_strtolower((string) $text, 'UTF-8');
    $t = strtr($t, ['ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω',
        'ϊ' => 'ι', 'ϋ' => 'υ', 'ΐ' => 'ι', 'ΰ' => 'υ', 'ς' => 'σ']);
    preg_match_all('/[a-zα-ω0-9]{3,}/u', $t, $m);
    $stop = ['και', 'για', 'την', 'τον', 'της', 'του', 'των', 'στο', 'στη', 'στον', 'στην', 'που', 'απο', 'από',
        'δεν', 'εχω', 'εχει', 'ενα', 'μια', 'εναν', 'ειναι', 'οτι', 'αλλα', 'μου', 'σας', 'μας', 'the', 'and',
        'for', 'with', 'have', 'has', 'this', 'that', 'not', 'you', 'προβλημα', 'θεμα', 'παρακαλω', 'καλημερα',
        'καλησπερα', 'ευχαριστω', 'ευχαριστουμε'];
    $out = [];
    foreach ($m[0] as $w) {
        if (!in_array($w, $stop, true)) {
            $out[$w] = true;
        }
        if (count($out) >= $max) {
            break;
        }
    }
    return array_keys($out);
}

/**
 * Καθαρισμός rich-text HTML (ασφαλή tags μόνο· χωρίς 4-byte για utf8mb3 DB).
 * $max: όριο χαρακτήρων — τα μεγάλα πεδία (descr/solution) κρατούν τη χωρητικότητά τους (60k).
 */
function cnp_clean_html($html, $max = 12000)
{
    $html = (string) $html;
    $html = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $html);          // 4-byte emoji → out
    // script/style/noscript ΜΑΖΙ με το περιεχόμενό τους — το strip_tags μόνο του αφαιρεί τα tags
    // και άφηνε τον κώδικα ως ορατό κείμενο (φαινόταν σε εισαγωγές από Confluence/WordPress).
    $html = preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1\s*>#is', ' ', $html);
    $html = preg_replace('#<(script|style|noscript)\b[^>]*/?>#i', ' ', $html);
    $html = strip_tags($html, '<b><strong><i><em><u><s><ul><ol><li><a><br><p><div><span><h3><h4><blockquote><code><pre><img><figure><figcaption><table><thead><tbody><tr><th><td>');
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);   // on* handlers
    // href/src: ALLOWLIST σχημάτων (blacklist «javascript:» άφηνε unquoted τιμές & data: URLs)
    $html = preg_replace_callback('/\b(href|src)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', function ($m) {
        $raw = trim($m[2], "\"'");
        $u = preg_replace('/[\x00-\x20]/', '', html_entity_decode($raw, ENT_QUOTES, 'UTF-8'));
        $safe = $u === '' || $u[0] === '#' || $u[0] === '/'
            || preg_match('#^(https?:|mailto:|tel:)#i', $u)
            || !preg_match('/^[a-z0-9.+-]*:/i', $u);      // σχετικό path χωρίς scheme
        return $m[1] . '="' . ($safe ? htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') : '#') . '"';
    }, $html);
    $html = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);           // inline styles → out
    // target=_blank + rel για ασφάλεια σε συνδέσμους
    $html = preg_replace('/<a\s+(?![^>]*\btarget=)/i', '<a target="_blank" rel="noopener noreferrer" ', $html);
    return mb_substr(trim($html), 0, (int) $max);
}

/**
 * Ασφαλές fetch URL που δίνει ο χρήστης (εισαγωγή γνώσης από τεκμηρίωση).
 * 🔒 SSRF: μόνο http/https, ΟΧΙ ιδιωτικά/loopback IP, όριο μεγέθους & redirects.
 */
function cnp_safe_fetch($url, $maxBytes = 3000000)
{
    $p = parse_url((string) $url);
    if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) {
        return ['ok' => false, 'error' => 'Μη έγκυρο URL (μόνο http/https)'];
    }
    $ips = @gethostbynamel($p['host']);
    if (!$ips) {
        $ips = filter_var($p['host'], FILTER_VALIDATE_IP) ? [$p['host']] : [];
    }
    if (!$ips) {
        return ['ok' => false, 'error' => 'Το domain δεν αναλύεται'];
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['ok' => false, 'error' => 'Δεν επιτρέπονται εσωτερικές διευθύνσεις'];
        }
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; CloudOnProjects/1.0)',
        CURLOPT_BUFFERSIZE => 65536, CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => function ($r, $dl) use ($maxBytes) { return $dl > $maxBytes ? 1 : 0; },
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $eff = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    if ($body === false || $code >= 400) {
        return ['ok' => false, 'error' => 'Η σελίδα δεν κατέβηκε (HTTP ' . $code . ')'];
    }
    return ['ok' => true, 'body' => $body, 'url' => $eff ?: $url, 'code' => $code];
}

/**
 * Ο cursor της επόμενης σελίδας ενός Confluence api/v2 response, ως «&cursor=…».
 * ΔΕΝ χρησιμοποιούμε αυτούσιο το _links.next: έρχεται με πρόθεμα «/wiki/» (Atlassian Cloud)
 * που δεν ισχύει σε custom domain → η σελιδοποίηση σταματούσε στην 1η σελίδα.
 */
function cnp_cursor_of($json)
{
    $n = $json['_links']['next'] ?? '';
    return ($n && preg_match('/[?&]cursor=([^&]+)/', $n, $m)) ? '&cursor=' . $m[1] : '';
}

/** Το root ενός URL (https://host) — για να βρούμε το wp-json. */
function cnp_site_root($url)
{
    $p = parse_url($url);
    return $p['scheme'] . '://' . $p['host'] . (!empty($p['port']) ? ':' . $p['port'] : '');
}

/**
 * Μετατρέπει σχετικά src/href σε απόλυτα (με βάση το URL του άρθρου) και πετά το
 * srcset/sizes — αλλιώς οι εικόνες σπάνε όταν το περιεχόμενο εμφανίζεται στο δικό μας domain.
 */
function cnp_absolutize_html($html, $base)
{
    $p = parse_url($base);
    if (empty($p['host'])) { return $html; }
    $root = ($p['scheme'] ?? 'https') . '://' . $p['host'];
    $dir = $root . preg_replace('#/[^/]*$#', '/', $p['path'] ?? '/');
    $html = preg_replace('/\s(?:srcset|sizes|data-lazy-srcset)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
    return preg_replace_callback('/\s(src|href)\s*=\s*"([^"]*)"/i', function ($m) use ($root, $dir) {
        $u = trim($m[2]);
        if ($u === '' || preg_match('#^(https?:|mailto:|tel:|data:|\#)#i', $u)) { return $m[0]; }
        if (strpos($u, '//') === 0) { return ' ' . $m[1] . '="https:' . $u . '"'; }
        if ($u[0] === '/') { return ' ' . $m[1] . '="' . $root . $u . '"'; }
        return ' ' . $m[1] . '="' . $dir . $u . '"';
    }, $html);
}

/** Κύριο περιεχόμενο από HTML σελίδα (χωρίς μενού/υποσέλιδα) + τίτλος. */
function cnp_html_article($html)
{
    $title = '';
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
    }
    if ($title === '' && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
    }
    $html = preg_replace('#<(script|style|nav|header|footer|form|noscript|svg)\b[^>]*>.*?</\1>#is', ' ', $html);
    $body = '';
    foreach (['#<article\b[^>]*>(.*?)</article>#is', '#<main\b[^>]*>(.*?)</main>#is',
        '#<div[^>]*class="[^"]*(?:entry-content|betterdocs-content|post-content)[^"]*"[^>]*>(.*?)</div>\s*</div>#is'] as $rx) {
        if (preg_match($rx, $html, $m)) { $body = $m[1]; break; }
    }
    if ($body === '' && preg_match('#<body\b[^>]*>(.*?)</body>#is', $html, $m)) { $body = $m[1]; }
    return ['title' => mb_substr($title, 0, 200), 'html' => $body];
}

/** Κατηγορίες tickets (area/cause) — cached ανά request. */
function cnp_ticket_cats()
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = ['area' => [], 'cause' => []];
    foreach (Capsule::table('mod_cpm_ticket_cats')->orderBy('sort')->orderBy('id')->get() as $r) {
        $c[$r->kind === 'cause' ? 'cause' : 'area'][] = ['id' => (int) $r->id, 'name' => $r->name, 'color' => $r->color];
    }
    return $c;
}

/** 🖥 Guacamole SSO: φτιάχνει token+URL που μπαίνει ΚΑΤΕΥΘΕΙΑΝ στη σύνδεση (json-auth). */
function cnp_guac_launch($protocol, $host, $port, $user, $pass, $extra = [])
{
    $secret = (string) Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', 'guac_secret')->value('value');
    $base = rtrim((string) Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', 'guac_url')->value('value'), '/');
    if ($secret === '' || $base === '') {
        return null;
    }
    $key = hex2bin($secret);
    $conn = 'remote';
    $params = array_merge(['hostname' => $host, 'port' => (string) $port,
        'username' => $user, 'password' => $pass], $extra);
    $payload = ['username' => 'tech-' . (int) ($_SESSION['pm_admin'] ?? 0) . '-' . substr(md5(microtime()), 0, 6),
        'expires' => (string) ((time() + 3600) * 1000),
        'connections' => [$conn => ['protocol' => $protocol, 'parameters' => $params]]];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $sig = hash_hmac('sha256', $json, $key, true);
    $blob = base64_encode(openssl_encrypt($sig . $json, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, str_repeat("\0", 16)));
    $ch = curl_init($base . '/api/tokens');
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => http_build_query(['data' => $blob])];
    $gip = (string) Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', 'guac_ip')->value('value');
    if ($gip !== '' && preg_match('#^https?://([^/:]+)#', $base, $mh)) {
        $opts[CURLOPT_RESOLVE] = [$mh[1] . ':443:' . $gip, $mh[1] . ':80:' . $gip];
    }
    curl_setopt_array($ch, $opts);
    $resp = json_decode((string) curl_exec($ch), true);
    curl_close($ch);
    if (empty($resp['authToken'])) {
        return null;
    }
    // client identifier: base64( conn + NUL + 'c' + NUL + 'json' )
    $clientId = base64_encode($conn . "\0c\0json");
    return $base . '/#/client/' . $clientId . '?token=' . rawurlencode($resp['authToken']);
}

/** Ποιος επιτρέπεται να απαντά σε ΠΕΛΑΤΕΣ: διαχειριστές + επικεφαλής ομάδων. */
function cnp_can_reply_clients($adminId, $isFull)
{
    if ($isFull) {
        return true;
    }
    try {
        return Capsule::table('mod_cpm_team_members')->where('admin_id', (int) $adminId)
            ->where('is_leader', 1)->exists();
    } catch (\Throwable $e) {
        return false;
    }
}

/** Ειδικότητες/πρόσβαση χειριστή: full → όλα· αλλιώς pref 'areas' (comma) ή default. */
function cnp_admin_areas($adminId, $isFull)
{
    if ($isFull) {
        return ['sales', 'support', 'projects', 'admin', 'hr'];
    }
    $raw = Db::pref($adminId, 'areas', '');
    $a = array_values(array_filter(array_map('trim', explode(',', $raw))));
    // χωρίς ανάθεση → default (backward-compat): όλα εκτός Διοίκησης
    return $a ?: ['sales', 'support', 'projects'];
}

/**
 * Βαθμολογία lead (0-100) με ανάλυση παραγόντων.
 * $l = record lead, $intCount = πλήθος επικοινωνιών, $lastInt = τελευταία επικοινωνία (datetime|null).
 * Επιστρέφει ['score'=>int, 'temp'=>'hot|warm|cold', 'factors'=>[['label','pts','on']...]].
 */
function cnp_lead_score($l, $intCount, $lastInt)
{
    $stageW = ['target' => 5, 'contacted' => 20, 'interested' => 42, 'offer' => 68, 'won' => 100, 'lost' => 0];
    $factors = [];
    $add = function ($label, $pts, $on) use (&$factors) { $factors[] = ['label' => $label, 'pts' => $pts, 'on' => (bool) $on]; };

    $sw = $stageW[$l->stage] ?? 5;
    $add('Στάδιο funnel', $sw, true);
    $score = $sw;

    $hasEmail = trim($l->email ?? '') !== '';
    $hasPhone = trim($l->phone ?? '') !== '';
    $add('Email επαφής', 8, $hasEmail); if ($hasEmail) { $score += 8; }
    $add('Τηλέφωνο επαφής', 8, $hasPhone); if ($hasPhone) { $score += 8; }

    $hasVal = (float) ($l->value ?? 0) > 0;
    $add('Εκτιμώμενη αξία', 10, $hasVal); if ($hasVal) { $score += 10; }

    // πρόσφατη δραστηριότητα
    $recPts = 0; $recLbl = 'Καμία πρόσφατη επαφή';
    if ($lastInt) {
        $days = (time() - strtotime($lastInt)) / 86400;
        if ($days <= 7) { $recPts = 16; $recLbl = 'Επαφή < 7 ημερών'; }
        elseif ($days <= 30) { $recPts = 9; $recLbl = 'Επαφή < 30 ημερών'; }
        else { $recPts = 0; $recLbl = 'Χωρίς πρόσφατη επαφή (>30ημ)'; }
    }
    $add($recLbl, 16, $recPts > 0); $score += $recPts;

    // όγκος επικοινωνίας
    $engPts = min(14, (int) $intCount * 3);
    $add('Εμπλοκή (' . (int) $intCount . ' επικοινωνίες)', 14, $engPts > 0); $score += $engPts;

    // προγραμματισμένη επόμενη ενέργεια
    $hasNext = !empty($l->next_action);
    $add('Προγραμματισμένη ενέργεια', 8, $hasNext); if ($hasNext) { $score += 8; }

    // ποιότητα πηγής
    $src = mb_strtolower(trim($l->source ?? ''));
    $goodSrc = $src !== '' && (strpos($src, 'referr') !== false || strpos($src, 'σύστασ') !== false || strpos($src, 'πελάτ') !== false || strpos($src, 'existing') !== false);
    $add('Ποιοτική πηγή (σύσταση)', 6, $goodSrc); if ($goodSrc) { $score += 6; }

    $score = max(0, min(100, (int) round($score)));
    $temp = $score >= 70 ? 'hot' : ($score >= 40 ? 'warm' : 'cold');
    return ['score' => $score, 'temp' => $temp, 'factors' => $factors];
}

/* ── Θυρίδα κωδικών: κλειδί + AES-256-GCM κρυπτογράφηση ── */
function cnp_vault_key()
{
    static $k = null;
    if ($k !== null) { return $k; }
    $v = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', 'pm_vault_key')->value('value');
    if (!$v) {
        $v = base64_encode(random_bytes(32));
        Capsule::table('tbladdonmodules')->insert(['module' => 'cloudonprojects', 'setting' => 'pm_vault_key', 'value' => $v]);
    }
    $k = base64_decode($v);
    return $k;
}
function cnp_vault_enc($plain)
{
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt((string) $plain, 'aes-256-gcm', cnp_vault_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ct);
}
function cnp_vault_dec($blob)
{
    $raw = base64_decode((string) $blob);
    if (strlen($raw) < 28) { return ''; }
    $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $ct = substr($raw, 28);
    $p = openssl_decrypt($ct, 'aes-256-gcm', cnp_vault_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $p === false ? '' : $p;
}
/** Τύποι εξοπλισμού για τη θυρίδα κωδικών. */
function cnp_vault_kinds()
{
    return [
        // εξοπλισμός
        'server' => 'Server', 'vm' => 'VM', 'router' => 'Router', 'switch' => 'Switch',
        'firewall' => 'Firewall', 'nas' => 'NAS/Storage', 'pbx' => '3CX/PBX', 'pc' => 'PC/Σταθμός',
        'printer' => 'Εκτυπωτής', 'wifi' => 'WiFi/Δίκτυο',
        // λογισμικό / εφαρμογές / λογαριασμοί
        'softone' => 'SoftOne', 'erp' => 'ERP / Λογιστικό', 'email' => 'Email', 'website' => 'Website/CMS',
        'app' => 'Εφαρμογή', 'saas' => 'Cloud / SaaS', 'microsoft' => 'Microsoft 365', 'db' => 'Βάση δεδομένων',
        'vpn' => 'VPN', 'portal' => 'Πύλη / Portal', 'hosting' => 'Hosting / cPanel', 'domain' => 'Domain / DNS',
        'account' => 'Λογαριασμός', 'other' => 'Άλλο'];
}

/** Στάδια αξιολόγησης υποψηφίου (Προσλήψεις). */
function cnp_cv_statuses()
{
    return ['new' => 'Νέες', 'review' => 'Υπό αξιολόγηση', 'shortlist' => 'Shortlist',
        'interview' => 'Συνέντευξη', 'rejected' => 'Απορρίφθηκε', 'hired' => 'Προσλήφθηκε'];
}

/** Διαθέσιμα μοντέλα AI για αξιολόγηση CV (key => label). Το 1ο = προεπιλογή (οικονομικό). */
function cnp_cv_models()
{
    return [
        'claude-haiku-4-5-20251001' => 'Οικονομικό — Haiku (γρήγορο, μαζική αξιολόγηση)',
        'claude-sonnet-5' => 'Ισορροπημένο — Sonnet',
        'claude-opus-5' => 'Αυστηρό — Opus (πιο ενδελεχές)',
    ];
}
function cnp_cv_default_model()
{
    $m = trim(Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', 'cv_ai_model')->value('value') ?: '');
    return array_key_exists($m, cnp_cv_models()) ? $m : 'claude-haiku-4-5-20251001';
}
/** Preset cover εικόνες αγγελιών (stem => label). Αρχεία: apply-assets/jobs/<stem>.jpg */
function cnp_cv_job_presets()
{
    return ['office' => 'Γραφείο / Γενικό', 'dev' => 'Ανάπτυξη λογισμικού', 'backend' => 'Backend / Υποδομές',
        'support' => 'Υποστήριξη / IT', 'pm' => 'Διαχείριση / Meetings', 'marketing' => 'Marketing', 'design' => 'Design / Creative'];
}
/** Αυτόματη επιλογή cover βάσει τίτλου/κειμένου θέσης. */
function cnp_cv_job_image_auto($text)
{
    $s = mb_strtolower((string) $text, 'UTF-8');
    $has = fn($needles) => (bool) preg_match('/(' . implode('|', $needles) . ')/u', $s);
    if ($has(['back[\s-]?end', 'devops', 'infrastr', 'server', 'database', 'βάσ[ηε]', 'υποδομ', 'sysadmin', 'network', 'δίκτυ'])) { return 'backend'; }
    if ($has(['market', 'seo', 'social media', 'advertis', 'μάρκετ', 'διαφήμ', 'προώθησ'])) { return 'marketing'; }
    if ($has(['design', ' ux', ' ui', 'graphic', 'creativ', 'σχεδ', 'γραφίστ'])) { return 'design'; }
    if ($has(['develop', 'software', 'engineer', 'program', 'front[\s-]?end', 'full[\s-]?stack', 'coder', 'προγραμμ', 'μηχανικ'])) { return 'dev'; }
    if ($has(['project', 'account', 'manager', 'coordinat', 'διαχειρ', 'υπεύθυν', 'συντον'])) { return 'pm'; }
    if ($has(['help[\s-]?desk', 'support', 'technician', 'τεχνικ', 'υποστήρ', 'service desk'])) { return 'support'; }
    return 'office';
}
/** Φάκελος για ανεβασμένες (custom) φωτογραφίες θέσεων. */
function cnp_cv_job_custom_dir()
{
    return __DIR__ . '/apply-assets/jobs/custom';
}
/** Είναι έγκυρο key ανεβασμένης εικόνας (custom/job-xxxx) που υπάρχει στον δίσκο; */
function cnp_cv_job_image_is_custom($img)
{
    $img = trim((string) $img);
    if (!preg_match('#^custom/job-[a-z0-9\-]{6,60}$#', $img)) { return false; }
    return is_file(__DIR__ . '/apply-assets/jobs/' . $img . '.jpg');
}
/** Τελικό cover stem για θέση (stored αν έγκυρο, αλλιώς auto). */
function cnp_cv_job_image($job)
{
    $img = trim((string) ($job->image ?? ''));
    if ($img !== '' && array_key_exists($img, cnp_cv_job_presets())) { return $img; }
    if (cnp_cv_job_image_is_custom($img)) { return $img; }
    return cnp_cv_job_image_auto(($job->title ?? '') . ' ' . ($job->title_en ?? ''));
}
/** Λίστα ανεβασμένων εικόνων (πιο πρόσφατες πρώτα). */
function cnp_cv_job_custom_list($limit = 24)
{
    $dir = cnp_cv_job_custom_dir();
    if (!is_dir($dir)) { return []; }
    $files = glob($dir . '/job-*.jpg') ?: [];
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $out = [];
    foreach (array_slice($files, 0, $limit) as $f) {
        $out[] = 'custom/' . basename($f, '.jpg');
    }
    return $out;
}
/** Ids διαχειριστών HR (full ή με ειδικότητα hr) — για ειδοποιήσεις. */
function cnp_hr_admin_ids()
{
    $ids = [];
    foreach (Db::admins() as $a) {
        if (Db::isFullAccess($a->id) || in_array('hr', cnp_admin_areas($a->id, false))) { $ids[] = (int) $a->id; }
    }
    return array_values(array_unique($ids));
}

/** Κλήση Anthropic messages API. Επιστρέφει ['ok'=>bool,'text'=>...,'error'=>...]. */
function cnp_anthropic($key, $model, $content, $maxTokens = 1500)
{
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'max_tokens' => $maxTokens, 'messages' => [['role' => 'user', 'content' => $content]]])]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = json_decode((string) $resp, true);
    $txt = '';
    foreach ($j['content'] ?? [] as $blk) { if (($blk['type'] ?? '') === 'text' && !empty($blk['text'])) { $txt = $blk['text']; break; } }
    if ($code !== 200 || $txt === '') { return ['ok' => false, 'error' => 'AI: ' . ($j['error']['message'] ?? ('HTTP ' . $code))]; }
    return ['ok' => true, 'text' => $txt];
}
/** Εξαγωγή JSON object από απάντηση AI (αγνοεί markdown fences κ.λπ.). */
function cnp_json_extract($txt)
{
    if (preg_match('/\{.*\}/s', $txt, $m)) { $txt = $m[0]; }
    $d = json_decode($txt, true);
    return is_array($d) ? $d : null;
}
/** PDF document block για το CV ενός υποψηφίου (ή null). */
function cnp_cv_pdf_block($r)
{
    if ($r->cv_mime !== 'application/pdf') { return null; }
    // migrated στο S3 → κατέβασε προσωρινά
    if (!empty($r->cv_storage_id)) {
        $tmp = Storage::toTempFile((int) $r->cv_storage_id);
        if ($tmp && is_file($tmp) && filesize($tmp) < 8 * 1024 * 1024) {
            $data = base64_encode(file_get_contents($tmp));
            if ((int) $r->cv_storage_id && Storage::record((int) $r->cv_storage_id)['driver'] === 's3') { @unlink($tmp); }
            return ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $data]];
        }
    }
    $path = $r->cv_stored ? realpath(__DIR__ . '/../attachments/cloudonprojects/' . basename($r->cv_stored)) : false;
    if ($path && is_file($path) && filesize($path) < 8 * 1024 * 1024) {
        return ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode(file_get_contents($path))]];
    }
    return null;
}

/**
 * Αξιολόγηση CV με AI co-pilot. Επιστρέφει ['ok'=>bool,'ai'=>...,'score'=>...,'model'=>...,'error'=>...].
 * $notify=true → ειδοποιεί υπεύθυνους HR όταν high-interest (μία φορά ανά υποψήφιο).
 */
function cnp_cv_evaluate($cvId, $model, $notify)
{
    $r = Capsule::table('mod_cpm_cv')->where('id', (int) $cvId)->first();
    if (!$r) { return ['ok' => false, 'error' => 'notfound']; }
    $key = trim(Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', 'ai_api_key')->value('value') ?: '');
    if ($key === '') { return ['ok' => false, 'error' => 'Δεν έχει οριστεί κλειδί AI (Ρυθμίσεις → AI)']; }
    if (!array_key_exists($model, cnp_cv_models())) { $model = cnp_cv_default_model(); }
    $jobDesc = '';
    if ($r->job_id) {
        $jb = Capsule::table('mod_cpm_cv_jobs')->where('id', $r->job_id)->first();
        if ($jb) { $jobDesc = trim((string) $jb->descr . ($jb->skills ? "\nΑΠΑΙΤΟΥΜΕΝΕΣ ΔΕΞΙΟΤΗΤΕΣ: " . $jb->skills : '')); }
    }
    $content = [];
    $path = $r->cv_stored ? realpath(__DIR__ . '/../attachments/cloudonprojects/' . basename($r->cv_stored)) : false;
    $isPdf = $path && $r->cv_mime === 'application/pdf' && is_file($path) && filesize($path) < 8 * 1024 * 1024;
    if ($isPdf) {
        $content[] = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode(file_get_contents($path))]];
    }
    $instr = "Είσαι έμπειρος recruiter/HR. Αξιολόγησε αντικειμενικά το βιογραφικό του υποψηφίου \"{$r->name}\" για τη θέση \"{$r->job_title}\".\n"
        . ($jobDesc !== '' ? "ΠΕΡΙΓΡΑΦΗ ΘΕΣΗΣ:\n" . mb_substr(strip_tags($jobDesc), 0, 3000) . "\n\n" : '')
        . ($r->letter ? "ΣΥΝΟΔΕΥΤΙΚΗ ΕΠΙΣΤΟΛΗ:\n" . mb_substr($r->letter, 0, 1500) . "\n\n" : '')
        . (!$isPdf ? "(Το αρχείο CV δεν είναι σε αναγνώσιμη μορφή — αξιολόγησε με βάση θέση/όνομα/επιστολή, με χαμηλότερη βεβαιότητα.)\n\n" : '')
        . "Εκτίμησε ΕΠΙΣΗΣ αν το βιογραφικό φαίνεται γραμμένο από άνθρωπο ή παραχθέν από AI (γενικόλογη/τυποποιημένη διατύπωση, υπερβολική «τελειότητα», μοτίβα AI).\n"
        . "Απάντησε ΜΟΝΟ με έγκυρο JSON (χωρίς markdown, στα ελληνικά) με ΑΚΡΙΒΩΣ αυτό το σχήμα:\n"
        . '{"score":0-100,"fit":0-100,"summary":"2-3 προτάσεις","strengths":["..."],"concerns":["..."],"category":"π.χ. Support/Developer/Sales/Marketing/Admin/Άλλο","seniority":"junior|mid|senior","yearsExp":αριθμός,"skills":["..."],"decision":"shortlist|interview|reject|maybe","interviewQuestions":["3 στοχευμένες ερωτήσεις"],"aiGenerated":{"verdict":"human|ai|mixed","confidence":0-100,"reason":"σύντομη αιτιολόγηση"}}';
    $content[] = ['type' => 'text', 'text' => $instr];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'max_tokens' => 1600, 'messages' => [['role' => 'user', 'content' => $content]]])]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = json_decode((string) $resp, true);
    $txt = '';
    foreach ($j['content'] ?? [] as $blk) { if (($blk['type'] ?? '') === 'text' && !empty($blk['text'])) { $txt = $blk['text']; break; } }
    if ($code !== 200 || $txt === '') { return ['ok' => false, 'error' => 'AI: ' . ($j['error']['message'] ?? ('HTTP ' . $code)) . (($code === 200 && $txt === '') ? ' (κενό κείμενο)' : '')]; }
    if (preg_match('/\{.*\}/s', $txt, $mm)) { $txt = $mm[0]; }
    $ai = json_decode($txt, true);
    if (!is_array($ai)) { return ['ok' => false, 'error' => 'AI: μη έγκυρη απάντηση']; }
    $score = isset($ai['score']) ? max(0, min(100, (int) $ai['score'])) : null;
    $upd = ['ai_score' => $score, 'ai_json' => json_encode($ai, JSON_UNESCAPED_UNICODE), 'ai_model' => $model, 'updated_at' => date('Y-m-d H:i:s')];
    $hot = ($score !== null && $score >= 75) || in_array($ai['decision'] ?? '', ['shortlist', 'interview'], true);
    if ($notify && $hot && !$r->notified) {
        $upd['notified'] = 1;
        if ($r->status === 'new') { $upd['status'] = 'review'; }
        $msg = '⭐ Ενδιαφέρον βιογραφικό: ' . $r->name . ' — ' . $r->job_title . ' (score ' . ($score ?? '?') . ') — κλείσε συνέντευξη';
        foreach (cnp_hr_admin_ids() as $aid) { Db::pushNotification($aid, 'due', $msg, '/project/#/recruit'); }
    }
    Capsule::table('mod_cpm_cv')->where('id', $cvId)->update($upd);
    return ['ok' => true, 'ai' => $ai, 'score' => $score, 'model' => $model, 'notified' => !empty($upd['notified'])];
}

/** Έλεγχος πρόσβασης σε κανάλι chat: team=όλοι, dN-M=οι δύο, gN=μέλη ομάδας. */
function cnp_chat_access($ch, $adminId)
{
    if ($ch === 'team') {
        return true;
    }
    if (preg_match('/^d(\d+)-(\d+)$/', $ch, $m)) {
        return (int) $m[1] === $adminId || (int) $m[2] === $adminId;
    }
    if (preg_match('/^g(\d+)$/', $ch, $m)) {
        return Capsule::table('mod_cpm_chat_groups')->where('id', (int) $m[1])
            ->where('members', 'like', '%,' . $adminId . ',%')->exists();
    }
    return false;
}

/** Σκορ ομοιότητας δύο συνόλων λέξεων. */
function cnp_overlap(array $a, array $b)
{
    if (!$a || !$b) {
        return 0;
    }
    $n = count(array_intersect($a, $b));
    return $n >= 2 ? $n : ($n === 1 && count($a) <= 4 ? 1 : 0);
}

function taskDto($t, $minsMap = null, $checkMap = null)
{
    return [
        'id' => (int) $t->id, 'title' => $t->title, 'status' => (int) $t->status_id,
        'prio' => (int) $t->priority, 'assignee' => $t->assignee ? (int) $t->assignee : null,
        'ball' => $t->action_user ? (int) $t->action_user : null,
        'due' => $t->due_date, 'sched' => $t->schedule_date, 'start' => $t->start_date ?? null,
        'type' => $t->type_id ? (int) $t->type_id : null,
        'est' => $t->estimate_minutes ? (int) $t->estimate_minutes : null,
        'ticket' => $t->ticketid ? (int) $t->ticketid : null,
        'done' => (bool) $t->completed_at,
        'mins' => $minsMap !== null ? (int) ($minsMap[(int) $t->id] ?? 0) : null,
        'check' => $checkMap !== null ? ($checkMap[(int) $t->id] ?? null) : null,
    ];
}
function clientLabel($cid)
{
    if (!$cid) {
        return null;
    }
    $c = Capsule::table('tblclients')->where('id', (int) $cid)->first(['firstname', 'lastname', 'companyname']);
    return $c ? ($c->companyname ?: trim($c->firstname . ' ' . $c->lastname)) : ('#' . $cid);
}

/* ───────── Γενικά αρχεία (Storage layer: local/S3) ───────── */
/** Ειδικότητα που απαιτείται ανά module ('' = οποιοσδήποτε authenticated, null = άγνωστο→deny). */
function cnp_file_area($module)
{
    $map = ['cv' => 'hr', 'task' => 'projects', 'project' => 'projects', 'ticket' => 'support',
        'lead' => 'sales', 'sales' => 'sales', 'chat' => '', 'general' => '', 'library' => ''];
    return array_key_exists($module, $map) ? $map[$module] : null;
}
/**
 * Η βιβλιοθήκη είναι ΙΔΙΩΤΙΚΗ ανά χειριστή — τα συνημμένα της ακολουθούν τον ίδιο κανόνα.
 * Επιστρέφει true αν ο admin δικαιούται πρόσβαση στα αρχεία του συγκεκριμένου τεκμηρίου.
 */
function cnp_lib_can($adminId, $refId, $needOwner = false)
{
    $row = Capsule::table('mod_cpm_library')->where('id', (int) $refId)->first();
    if (!$row) { return false; }
    if ((int) $row->admin_id === (int) $adminId) { return true; }
    return !$needOwner && !empty($row->shared);   // κοινόχρηστο → μόνο ανάγνωση
}
function cnp_file_authz($adminId, $FULL, $module)
{
    $area = cnp_file_area($module);
    if ($area === null) { return false; }
    if ($area === '') { return $adminId > 0; }
    return in_array($area, cnp_admin_areas($adminId, $FULL), true);
}
/** Blacklist επικίνδυνων επεκτάσεων (executables/scripts). */
function cnp_file_ext_ok($name)
{
    $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
    $bad = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'cgi', 'sh', 'bash', 'exe', 'bat', 'cmd',
        'com', 'msi', 'htaccess', 'htm', 'html', 'svg', 'js', 'mjs', 'jsp', 'asp', 'aspx', 'pl', 'py', 'rb', 'so'];
    return $ext !== '' && !in_array($ext, $bad, true);
}
/** Εγγραφή μητρώου → payload για frontend (url = proxy/redirect endpoint). */
function cnp_file_row($rec)
{
    $rec = (array) $rec;
    return ['id' => (int) $rec['id'], 'name' => $rec['orig_name'], 'mime' => $rec['mime'], 'size' => (int) $rec['size'],
        'kind' => $rec['kind'], 'driver' => $rec['driver'], 'createdAt' => $rec['created_at'],
        'url' => 'api.php?a=file_get&id=' . (int) $rec['id']];
}

switch ($action) {

/* ================= BOOT ================= */
case 'boot':
    $admins = [];
    foreach (Db::admins() as $a) {
        $nm = trim($a->firstname . ' ' . $a->lastname);
        $admins[] = ['id' => (int) $a->id, 'name' => $nm, 'ini' => initials($nm), 'full' => Db::isFullAccess($a->id)];
    }
    $projects = [];
    foreach (Db::projectsFor($adminId) as $p) {
        $projects[] = ['id' => (int) $p->id, 'name' => $p->name, 'color' => $p->color,
            'client' => $p->clientid ? (int) $p->clientid : null, 'clientName' => clientLabel($p->clientid),
            'parent' => $p->parent_id ? (int) $p->parent_id : null,
            'health' => $p->health, 'pstatus' => $p->pstatus];
    }
    $statuses = [];
    foreach (Db::statuses() as $s) {
        $statuses[] = ['id' => (int) $s->id, 'title' => $s->title, 'color' => $s->color, 'done' => (bool) $s->is_done];
    }
    $types = [];
    foreach (Db::taskTypes() as $ty) {
        $types[] = ['id' => (int) $ty->id, 'name' => $ty->name, 'icon' => $ty->icon, 'color' => $ty->color,
            'req' => ['assignee' => (bool) $ty->req_assignee, 'due' => (bool) $ty->req_due, 'est' => (bool) $ty->req_estimate]];
    }
    out(['me' => ['id' => $adminId, 'name' => Db::adminName($adminId), 'ini' => initials(Db::adminName($adminId)), 'full' => $FULL,
            'canReply' => cnp_can_reply_clients($adminId, $FULL), 'areas' => cnp_admin_areas($adminId, $FULL),
            'lang' => Db::pref($adminId, 'lang', 'el') === 'en' ? 'en' : 'el'],
        'projects' => $projects, 'statuses' => $statuses, 'types' => $types, 'admins' => $admins,
        'costPerHour' => $FULL ? (float) str_replace(',', '.', (string) (Capsule::table('tbladdonmodules')
            ->where('module', 'cloudonprojects')->where('setting', 'cost_per_hour')->value('value') ?: 0)) : 0,
        'meetLink' => Db::pref($adminId, 'meet_link', ''),
        'rustdeskDl' => (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'rustdesk_dl')->value('value') ?: ''),
        'unread' => Db::unreadCount($adminId)]);

/* ================= BOARD ================= */
case 'board':
    $pid = (int) ($_GET['project'] ?? 0);
    if (!$pid || !Db::canSeeProject($adminId, $pid)) {
        fail('project', 403);
    }
    $mins = Db::minutesByTask($pid);
    $check = Db::checklistProgress($pid);
    $allIds = Capsule::table('mod_cpm_tasks')->where('project_id', $pid)->pluck('id')->all();
    $blockedM = Db::blockedMap($allIds);
    $cols = [];
    $board = Db::board($pid);
    foreach (Db::statuses() as $s) {
        $cards = [];
        foreach ($board[(int) $s->id] ?? [] as $t) {
            $dto = taskDto($t, $mins, $check);
            $dto['blocked'] = isset($blockedM[(int) $t->id]) ? count($blockedM[(int) $t->id]) : 0;
            $cards[] = $dto;
        }
        $cols[] = ['status' => (int) $s->id, 'tasks' => $cards];
    }
    out(['columns' => $cols]);

/* ================= TASK (drawer) ================= */
case 'task':
    $t = Db::task((int) ($_GET['id'] ?? 0));
    if (!$t || !Db::canSeeTask($adminId, $t)) {
        fail('task', 404);
    }
    $comments = [];
    foreach (Db::comments($t->id) as $c) {
        $comments[] = ['id' => (int) $c->id, 'by' => Db::adminName($c->admin_id), 'byId' => (int) $c->admin_id,
            'to' => $c->to_admin !== null ? (int) $c->to_admin : null, 'body' => $c->comment, 'at' => $c->created_at];
    }
    $logs = [];
    foreach (Db::timelogsForTask($t->id) as $l) {
        $logs[] = ['id' => (int) $l->id, 'by' => Db::adminName($l->admin_id), 'mins' => (int) $l->minutes,
            'billable' => (bool) $l->billable, 'charged' => (int) $l->charged_minutes,
            'note' => $l->note, 'running' => (bool) $l->running, 'at' => $l->running ? $l->started_at : $l->created_at];
    }
    $check = [];
    foreach (Db::checklist($t->id) as $it) {
        $check[] = ['id' => (int) $it->id, 'title' => $it->title, 'done' => (bool) $it->done];
    }
    $acts = [];
    foreach (Db::activity($t->id, 30) as $a) {
        $acts[] = ['action' => $a->action, 'detail' => $a->detail, 'by' => Db::adminName($a->admin_id), 'at' => $a->created_at];
    }
    $ticket = null;
    if ($t->ticketid) {
        $tk = Capsule::table('tbltickets')->where('id', $t->ticketid)->first(['tid', 'title', 'status']);
        if ($tk) {
            $ticket = ['id' => (int) $t->ticketid, 'tid' => $tk->tid, 'title' => $tk->title, 'status' => $tk->status];
        }
    }
    $proj = Db::project($t->project_id);
    $running = Db::runningTimer($adminId);
    $deps = [];
    foreach (Db::depsOf($t->id) as $dp) {
        $deps[] = ['depId' => (int) $dp->dep_id, 'id' => (int) $dp->id, 'title' => $dp->title,
            'done' => (bool) $dp->completed_at];
    }
    out(['task' => taskDto($t), 'descr' => $t->descr, 'deps' => $deps,
        'project' => ['id' => (int) $proj->id, 'name' => $proj->name, 'color' => $proj->color],
        'comments' => $comments, 'timelogs' => $logs, 'total' => Db::taskMinutes($t->id),
        'check' => $check, 'activity' => $acts, 'ticket' => $ticket,
        'watching' => in_array($adminId, Db::watcherIds($t->id), true),
        'watchers' => count(Db::watcherIds($t->id)),
        'timerHere' => $running && (int) $running->task_id === (int) $t->id
            ? ['id' => (int) $running->id, 'since' => $running->started_at] : null,
        'timerElsewhere' => $running && (int) $running->task_id !== (int) $t->id ? (int) $running->task_id : null,
        'scClient' => Time::scReady() ? clientLabel(Time::clientForTask($t)) : null]);

/* ================= MY DAY ================= */
case 'myday':
    $today = date('Y-m-d');
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    // tickets μου + SLA
    $myTickets = [];
    $slaMap = [];
    $rows = Capsule::table('tbltickets')->where('flag', $adminId)
        ->whereNotIn('status', ['Closed', 'Cancelled'])->get(['id', 'tid', 'title', 'status', 'urgency', 'date', 'lastreply']);
    try {
        if (count($rows) && Capsule::schema()->hasTable('mod_supportcontracts_tickets')) {
            foreach (Capsule::table('mod_supportcontracts_tickets')->whereIn('ticketid', $rows->pluck('id')->all())
                ->get(['ticketid', 'sla_due', 'first_response_at']) as $s) {
                $slaMap[(int) $s->ticketid] = $s;
            }
        }
    } catch (\Throwable $e) {
    }
    foreach ($rows as $tk) {
        $s = $slaMap[(int) $tk->id] ?? null;
        $due = ($s && $s->sla_due && !$s->first_response_at) ? $s->sla_due : null;
        $myTickets[] = ['id' => (int) $tk->id, 'tid' => $tk->tid, 'title' => $tk->title, 'status' => $tk->status,
            'urgency' => $tk->urgency, 'slaDue' => $due, 'over' => $due && strtotime($due) < time(),
            'age' => (int) floor((time() - strtotime($tk->date)) / 86400)];
    }
    usort($myTickets, function ($a, $b) {
        if ($a['slaDue'] && $b['slaDue']) { return strcmp($a['slaDue'], $b['slaDue']); }
        return ($a['slaDue'] ? -1 : ($b['slaDue'] ? 1 : 0));
    });
    // πλάνο + μπάλες + tasks μου
    $plan = [];
    foreach (Capsule::table('mod_cpm_tasks as t')->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
        ->select('t.*', 'p.name as pname', 'p.color as pcolor')
        ->whereNotIn('t.status_id', $doneIds)->whereNotNull('t.schedule_date')->where('t.schedule_date', '<=', $today)
        ->where(function ($w) use ($adminId) { $w->where('t.assignee', $adminId)->orWhere('t.action_user', $adminId); })
        ->orderByRaw('t.priority DESC')->get() as $t) {
        $plan[] = taskDto($t) + ['pname' => $t->pname, 'pcolor' => $t->pcolor];
    }
    $balls = [];
    foreach (Capsule::table('mod_cpm_tasks as t')->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
        ->select('t.*', 'p.name as pname', 'p.color as pcolor')
        ->where('t.action_user', $adminId)->whereNotIn('t.status_id', $doneIds)->get() as $t) {
        $balls[] = taskDto($t) + ['pname' => $t->pname, 'pcolor' => $t->pcolor];
    }
    $myOpen = (int) Capsule::table('mod_cpm_tasks')->where('assignee', $adminId)->whereNotIn('status_id', $doneIds)->count();
    $dueToday = (int) Capsule::table('mod_cpm_tasks')->where('assignee', $adminId)->whereNotIn('status_id', $doneIds)
        ->whereNotNull('due_date')->where('due_date', '<=', $today)->count();
    $minsToday = (int) Capsule::table('mod_cpm_timelogs')->where('admin_id', $adminId)->where('running', 0)
        ->where('created_at', '>=', $today . ' 00:00:00')->sum('minutes');
    // follow-ups
    $follows = [];
    foreach (Capsule::table('mod_cpm_leads')->where('assignee', $adminId)->whereNotIn('stage', ['won', 'lost'])
        ->whereNotNull('next_action')->where('next_action', '<=', $today)->get() as $ld) {
        $follows[] = ['lead' => (int) $ld->id, 'who' => $ld->company ?: $ld->contact, 'phone' => $ld->phone, 'note' => $ld->next_note];
    }
    // notifications
    $notifs = [];
    foreach (Db::notificationsFor($adminId, 15) as $n) {
        $notifs[] = ['id' => (int) $n->id, 'type' => $n->type, 'title' => $n->title, 'url' => $n->url,
            'read' => (bool) $n->is_read, 'at' => $n->created_at];
    }
    /* ── 🧭 Προσωπικός coach: συμβουλές/προειδοποιήσεις ανά χειριστή ── */
    $coach = [];
    $overdue = (int) Capsule::table('mod_cpm_tasks')->where('assignee', $adminId)->whereNotIn('status_id', $doneIds)
        ->whereNotNull('due_date')->where('due_date', '<', $today)->count();
    $dueTod = (int) Capsule::table('mod_cpm_tasks')->where('assignee', $adminId)->whereNotIn('status_id', $doneIds)
        ->where('due_date', $today)->count();
    $wip = (int) Capsule::table('mod_cpm_tasks')->where('assignee', $adminId)->where('status_id', 2)->count();
    $stale = (int) Capsule::table('mod_cpm_tasks')->where('assignee', $adminId)->whereIn('status_id', [2, 3])
        ->where('updated_at', '<', date('Y-m-d', strtotime('-7 days')) . ' 23:59:59')->count();
    $noDue = (int) Capsule::table('mod_cpm_tasks')->where('assignee', $adminId)->whereNotIn('status_id', $doneIds)
        ->whereNull('due_date')->count();
    $run = Capsule::table('mod_cpm_timelogs')->where('admin_id', $adminId)->where('running', 1)
        ->orderBy('started_at')->first(['started_at']);
    $slaOver = count(array_filter($myTickets, function ($t) { return $t['over']; }));
    $awaiting = count(array_filter($myTickets, function ($t) {
        return in_array($t['status'], ['Open', 'Customer-Reply', 'In Progress'], true);
    }));
    $oldTk = count(array_filter($myTickets, function ($t) { return $t['age'] >= 5; }));
    // κανόνες (κρισιμότητα: bad → warn → tip → ok)
    if ($slaOver) {
        $coach[] = ['lvl' => 'bad', 'icon' => '⏰', 'text' => "$slaOver ticket" . ($slaOver > 1 ? 's' : '') . " έχ" . ($slaOver > 1 ? 'ουν' : 'ει') . " ξεπεράσει το SLA — απάντησε άμεσα, προηγούνται όλων."];
    }
    if ($overdue) {
        $coach[] = ['lvl' => 'bad', 'icon' => '🔴', 'text' => "Έχεις $overdue εκπρόθεσμ" . ($overdue > 1 ? 'ες εργασίες' : 'η εργασία') . '. Ξεκίνα από ' . ($overdue > 1 ? 'αυτές ή επαναπρογραμμάτισέ τες' : 'αυτήν ή επαναπρογραμμάτισέ την') . ' ρεαλιστικά.'];
    }
    if ($run && $run->started_at) {
        $h = floor((time() - strtotime($run->started_at)) / 3600);
        if ($h >= 3) {
            $coach[] = ['lvl' => 'warn', 'icon' => '⏱', 'text' => "Χρονόμετρο τρέχει εδώ και ~{$h}ω — αν τελείωσες, σταμάτησέ το για σωστή χρέωση."];
        }
    }
    if ($awaiting) {
        $coach[] = ['lvl' => 'warn', 'icon' => '💬', 'text' => "$awaiting ticket" . ($awaiting > 1 ? 's περιμένουν' : ' περιμένει') . " απάντησή σου. Μια σύντομη ενημέρωση τώρα κρατά τον πελάτη ήσυχο."];
    }
    if ($stale) {
        $coach[] = ['lvl' => 'warn', 'icon' => '🐌', 'text' => "$stale εργασί" . ($stale > 1 ? 'ες' : 'α') . " σε εξέλιξη χωρίς κίνηση >7 ημέρες. Δώσ' τους ώθηση ή γύρνα την μπάλα σε κάποιον."];
    }
    if ($dueTod) {
        $coach[] = ['lvl' => 'tip', 'icon' => '📌', 'text' => "$dueTod εργασί" . ($dueTod > 1 ? 'ες λήγουν' : 'α λήγει') . " σήμερα — κλείσ' " . ($dueTod > 1 ? 'τες' : 'την') . ' πριν το τέλος της ημέρας.'];
    }
    if ($wip > 5) {
        $coach[] = ['lvl' => 'tip', 'icon' => '🎯', 'text' => "$wip εργασίες ταυτόχρονα «σε εξέλιξη». Ολοκλήρωσε 1-2 πριν ξεκινήσεις νέες — λιγότερα ανοιχτά = ταχύτερη ροή."];
    }
    if ($noDue >= 3) {
        $coach[] = ['lvl' => 'tip', 'icon' => '🗓', 'text' => "$noDue εργασίες σου χωρίς προθεσμία. Βάλε ημερομηνία-στόχο για να μη χαθούν."];
    }
    if ($oldTk && !$slaOver) {
        $coach[] = ['lvl' => 'tip', 'icon' => '📨', 'text' => "$oldTk ticket" . ($oldTk > 1 ? 's ανοιχτά' : ' ανοιχτό') . " πάνω από 5 ημέρες. Δώσε ένα update ή κλείσ' το αν λύθηκε."];
    }
    if (!$coach) {
        $coach[] = ['lvl' => 'ok', 'icon' => '👏', 'text' => 'Όλα υπό έλεγχο — καμία εκκρεμότητα εκτός χρονοδιαγράμματος. Συνέχισε έτσι!'];
    } elseif (!$overdue && !$slaOver) {
        $coach[] = ['lvl' => 'ok', 'icon' => '✅', 'text' => 'Κανένα εκπρόθεσμο ούτε παραβίαση SLA — καλή εικόνα, μείνε συνεπής.'];
    }
    $coach = array_slice($coach, 0, 6);
    out(['tickets' => $myTickets, 'plan' => $plan, 'balls' => $balls, 'follows' => $follows, 'coach' => $coach,
        'notifs' => $notifs, 'stats' => ['tickets' => count($myTickets),
            'nearSla' => count(array_filter($myTickets, function ($t) { return $t['slaDue'] && strtotime($t['slaDue']) < strtotime('+24 hours'); })),
            'tasks' => $myOpen, 'dueToday' => $dueToday, 'minsToday' => $minsToday]]);

/* ================= CRM ================= */
case 'crm':
    $stages = [];
    foreach (Db::leadStages() as $k => $m) {
        $stages[] = ['key' => $k, 'title' => $m[0], 'color' => $m[1], 'closed' => (bool) $m[2], 'won' => (bool) $m[3]];
    }
    $leads = [];
    foreach (Db::leads() as $l) {
        if (!$FULL && (int) $l->assignee !== $adminId && (int) $l->created_by !== $adminId) {
            continue;
        }
        $leads[] = ['id' => (int) $l->id, 'company' => $l->company, 'contact' => $l->contact,
            'email' => $l->email, 'phone' => $l->phone, 'source' => $l->source, 'stage' => $l->stage,
            'value' => $l->value !== null ? (float) $l->value : null, 'lostReason' => $l->lost_reason,
            'assignee' => $l->assignee ? (int) $l->assignee : null, 'client' => $l->clientid ? (int) $l->clientid : null,
            'next' => $l->next_action, 'nextNote' => $l->next_note, 'descr' => $l->descr,
            'created' => substr((string) $l->created_at, 0, 10)];
    }
    $target = (float) str_replace(',', '.', (string) (Capsule::table('tbladdonmodules')
        ->where('module', 'cloudonprojects')->where('setting', 'sales_target')->value('value') ?: 0));
    out(['stages' => $stages, 'leads' => $leads,
        'won' => Db::wonValueForMonth(date('Y-m')), 'target' => $target]);

case 'crm_overview':
    $stagesMeta = Db::leadStages();
    $all = [];
    foreach (Db::leads() as $l) {
        if (!$FULL && (int) $l->assignee !== $adminId && (int) $l->created_by !== $adminId) {
            continue;
        }
        $all[] = $l;
    }
    $today0 = date('Y-m-d');
    $mStart = date('Y-m-01 00:00:00');
    $pipe = [];
    foreach ($stagesMeta as $k => $m) {
        $pipe[$k] = ['key' => $k, 'title' => $m[0], 'color' => $m[1], 'closed' => (bool) $m[2],
            'won' => (bool) $m[3], 'count' => 0, 'value' => 0.0];
    }
    $overdue = [];
    $rotting = [];
    $wonAll = 0;
    $lostAll = 0;
    $wonMonth = 0;
    $lostMonth = 0;
    $bySource = [];
    $byAssignee = [];
    $lostReasons = [];
    foreach ($all as $l) {
        $st7 = $l->stage;
        if (isset($pipe[$st7])) {
            $pipe[$st7]['count']++;
            $pipe[$st7]['value'] += (float) ($l->value ?? 0);
        }
        $isClosed = !empty($stagesMeta[$st7][2]);
        $lead7 = ['id' => (int) $l->id, 'name' => $l->company ?: $l->contact ?: ('#' . $l->id),
            'stage' => $st7, 'next' => $l->next_action, 'assignee' => $l->assignee ? (int) $l->assignee : null,
            'value' => $l->value !== null ? (float) $l->value : null];
        if (!$isClosed) {
            $bySource[$l->source ?: '—'] = ($bySource[$l->source ?: '—'] ?? 0) + 1;
            $key7 = $l->assignee ?: 0;
            $byAssignee[$key7] = ($byAssignee[$key7] ?? 0) + 1;
            if ($l->next_action && $l->next_action < $today0) {
                $overdue[] = $lead7;
            } elseif (!$l->next_action) {
                $rotting[] = $lead7;
            }
        }
        if ($st7 === 'won') {
            $wonAll++;
            if (($l->closed_at ?? '') >= $mStart) {
                $wonMonth++;
            }
        }
        if ($st7 === 'lost') {
            $lostAll++;
            if (($l->closed_at ?? '') >= $mStart) {
                $lostMonth++;
            }
            if ($l->lost_reason) {
                $lostReasons[] = ['name' => $lead7['name'], 'reason' => $l->lost_reason,
                    'at' => substr((string) $l->closed_at, 0, 10)];
            }
        }
    }
    arsort($bySource);
    arsort($byAssignee);
    $target9 = (float) str_replace(',', '.', (string) (Capsule::table('tbladdonmodules')
        ->where('module', 'cloudonprojects')->where('setting', 'sales_target')->value('value') ?: 0));
    out(['pipe' => array_values($pipe),
        'openCount' => array_sum(array_map(function ($p) { return $p['closed'] ? 0 : $p['count']; }, $pipe)),
        'openValue' => array_sum(array_map(function ($p) { return $p['closed'] ? 0 : $p['value']; }, $pipe)),
        'wonMonth' => $wonMonth, 'lostMonth' => $lostMonth,
        'winRate' => ($wonAll + $lostAll) > 0 ? round($wonAll / ($wonAll + $lostAll) * 100) : null,
        'wonValueMonth' => Db::wonValueForMonth(date('Y-m')), 'target' => $target9,
        'overdue' => array_slice($overdue, 0, 25), 'rotting' => array_slice($rotting, 0, 25),
        'bySource' => $bySource, 'byAssignee' => $byAssignee,
        'lostReasons' => array_slice(array_reverse($lostReasons), 0, 10)]);

/* ================= KPI (διοίκηση) ================= */
case 'kpi':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $today = date('Y-m-d 00:00:00');
    $open = Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])->count();
    $closedToday = Capsule::table('tbltickets')->where('status', 'Closed')->where('lastreply', '>=', $today)->count();
    $stale = Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])
        ->where('date', '<', date('Y-m-d H:i:s', strtotime('-7 days')))->count();
    $slaOver = 0;
    try {
        $slaOver = Capsule::table('mod_supportcontracts_tickets as st')->join('tbltickets as t', 't.id', '=', 'st.ticketid')
            ->whereNotIn('t.status', ['Closed', 'Cancelled'])->whereNotNull('st.sla_due')
            ->where('st.sla_due', '<', date('Y-m-d H:i:s'))->whereNull('st.first_response_at')->count();
    } catch (\Throwable $e) {
    }
    $waiting = 0;
    foreach (Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])->get(['id']) as $tk) {
        $la = Capsule::table('tblticketreplies')->where('tid', $tk->id)->orderBy('id', 'desc')->value('admin');
        if ($la === null || $la === '') {
            $waiting++;
        }
    }
    // ανά agent
    $agents = [];
    foreach (Db::admins() as $a) {
        $agents[(int) $a->id] = ['id' => (int) $a->id, 'name' => trim($a->firstname . ' ' . $a->lastname),
            'ini' => initials($a->firstname . ' ' . $a->lastname), 'open' => 0, 'replies' => 0, 'done' => 0, 'mins' => 0];
    }
    foreach (Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])
        ->selectRaw('flag, COUNT(*) n')->groupBy('flag')->get() as $r) {
        if (isset($agents[(int) $r->flag])) {
            $agents[(int) $r->flag]['open'] = (int) $r->n;
        }
    }
    $nameToId = [];
    foreach ($agents as $id => $a) {
        $nameToId[$a['name']] = $id;
    }
    foreach (Capsule::table('tblticketreplies')->where('date', '>=', $today)->where('admin', '!=', '')
        ->selectRaw('admin, COUNT(*) n')->groupBy('admin')->get() as $r) {
        if (isset($nameToId[$r->admin])) {
            $agents[$nameToId[$r->admin]]['replies'] = (int) $r->n;
        }
    }
    foreach (Capsule::table('mod_cpm_tasks')->where('completed_at', '>=', $today)->whereNotNull('assignee')
        ->selectRaw('assignee, COUNT(*) n')->groupBy('assignee')->get() as $r) {
        if (isset($agents[(int) $r->assignee])) {
            $agents[(int) $r->assignee]['done'] = (int) $r->n;
        }
    }
    foreach (Capsule::table('mod_cpm_timelogs')->where('running', 0)->where('created_at', '>=', $today)
        ->selectRaw('admin_id, SUM(minutes) m')->groupBy('admin_id')->get() as $r) {
        if (isset($agents[(int) $r->admin_id])) {
            $agents[(int) $r->admin_id]['mins'] = (int) $r->m;
        }
    }
    $teamMap = Db::adminTeamMap();
    $list = [];
    foreach ($agents as $id => $a) {
        $a['score'] = $a['replies'] + $a['done'] * 2 + (int) floor($a['mins'] / 30);
        $a['team'] = $teamMap[$id] ?? null;
        if ($a['open'] || $a['replies'] || $a['done'] || $a['mins']) {
            $list[] = $a;
        }
    }
    usort($list, function ($x, $y) { return $y['score'] <=> $x['score']; });
    $unassigned = (int) Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])
        ->where(function ($w) { $w->where('flag', 0)->orWhereNull('flag'); })->count();
    // workload
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $wl = [];
    foreach (Capsule::table('mod_cpm_tasks')->whereNotIn('status_id', $doneIds)->whereNotNull('assignee')
        ->get(['assignee', 'estimate_minutes', 'schedule_date', 'due_date']) as $r) {
        $k = (int) $r->assignee;
        if (!isset($wl[$k])) {
            $wl[$k] = ['id' => $k, 'name' => Db::adminName($k), 'open' => 0, 'est' => 0, 'today' => 0, 'over' => 0];
        }
        $wl[$k]['open']++;
        $wl[$k]['est'] += (int) $r->estimate_minutes;
        if ($r->schedule_date && $r->schedule_date <= date('Y-m-d')) {
            $wl[$k]['today']++;
        }
        if ($r->due_date && $r->due_date < date('Y-m-d')) {
            $wl[$k]['over']++;
        }
    }
    // κερδοφορία μήνα (σύνοψη)
    $costH = (float) str_replace(',', '.', (string) (Capsule::table('tbladdonmodules')
        ->where('module', 'cloudonprojects')->where('setting', 'cost_per_hour')->value('value') ?: 0));
    $mFrom = date('Y-m-01');
    $mins = (int) Capsule::table('mod_cpm_timelogs')->where('running', 0)
        ->where('created_at', '>=', $mFrom . ' 00:00:00')->sum('minutes');
    $exp = (float) Capsule::table('mod_cpm_expenses')->where('spent_at', '>=', $mFrom)->sum('amount');
    out(['cards' => ['open' => $open, 'slaOver' => $slaOver, 'closedToday' => $closedToday,
            'waiting' => $waiting, 'stale' => $stale, 'unassigned' => $unassigned],
        'agents' => $list, 'workload' => array_values($wl),
        'month' => ['won' => Db::wonValueForMonth(date('Y-m')), 'laborCost' => round($mins / 60 * $costH, 2),
            'expenses' => $exp, 'minutes' => $mins]]);

/* ================= NOTIFICATIONS ================= */
case 'notifs':
    $ns = [];
    foreach (Db::notificationsFor($adminId, 15) as $n) {
        $ns[] = ['id' => (int) $n->id, 'type' => $n->type, 'title' => $n->title, 'url' => $n->url,
            'read' => (bool) $n->is_read, 'at' => $n->created_at];
    }
    out(['unread' => Db::unreadCount($adminId), 'items' => $ns]);

case 'notif_read':
    Db::markNotifRead($adminId, (int) ($in['id'] ?? 0));
    out(['ok' => true, 'unread' => Db::unreadCount($adminId)]);

/* ================= ACTIONS ================= */
case 'move_task':
    $t = Db::task((int) ($in['task'] ?? 0));
    if (!$t || !Db::canSeeTask($adminId, $t)) {
        fail('task', 403);
    }
    $stChk = Db::status((int) ($in['status'] ?? 0));
    if ($stChk && $stChk->is_done) {
        $bm = Db::blockedMap([$t->id]);
        if (!empty($bm[(int) $t->id])) {
            fail('Μπλοκάρεται από: ' . implode(', ', array_slice($bm[(int) $t->id], 0, 3)));
        }
    }
    $ok = Db::moveTask($t->id, (int) ($in['status'] ?? 0), $adminId);
    if ($ok && !$FULL) {
        $st = Db::status((int) $in['status']);
        if ($st && $st->is_done) {
            Notify::workDone($adminId, $t->title, 'addonmodules.php?module=cloudonprojects&tab=task&id=' . $t->id);
        }
    }
    if ($ok) {
        $stN = Db::status((int) $in['status']);
        Notify::watchers($t->id, $adminId, $t->title . ' → ' . ($stN->title ?? '?'), null);
    }
    out(['ok' => (bool) $ok]);

case 'quick_task':
    $pid = (int) ($in['project'] ?? 0);
    $title = mb_substr(trim($in['title'] ?? ''), 0, 200);
    if (!$pid || $title === '' || !Db::canSeeProject($adminId, $pid)) {
        fail('input');
    }
    $sid = (int) ($in['status'] ?? 0);
    $tid = Db::saveTask(0, ['project_id' => $pid, 'title' => $title,
        'status_id' => Db::status($sid) ? $sid : Db::firstStatusId()], $adminId);
    Db::logActivity($tid, $adminId, 'create', 'Γρήγορη δημιουργία (web app)');
    out(['ok' => true, 'id' => $tid]);

case 'save_task':
    $tid = (int) ($in['task'] ?? 0);
    $t = Db::task($tid);
    if (!$t || !Db::canSeeTask($adminId, $t)) {
        fail('task', 403);
    }
    $data = [];
    if (array_key_exists('title', $in)) {
        $data['title'] = mb_substr(trim((string) $in['title']), 0, 200);
    }
    if (array_key_exists('descr', $in)) {
        $data['descr'] = cnp_clean_html($in['descr'], 60000);   // rich-text πεδίο → allowlist tags
    }
    foreach (['due_date' => 'due', 'schedule_date' => 'sched'] as $col => $k) {
        if (array_key_exists($k, $in)) {
            $data[$col] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in[$k]) ? $in[$k] : null;
        }
    }
    if (array_key_exists('type', $in)) {
        $data['type_id'] = (int) $in['type'] ?: null;
    }
    if (array_key_exists('est', $in)) {
        $data['estimate_minutes'] = (int) $in['est'] ?: null;
    }
    if (array_key_exists('ball', $in)) {
        $newBall = (int) $in['ball'] ?: null;
        $data['action_user'] = $newBall;
        if ($newBall && $newBall !== (int) $t->action_user && $newBall !== $adminId) {
            Db::pushNotification($newBall, 'action', '⚡ Απαιτείται ενέργειά σου: ' . $t->title,
                'addonmodules.php?module=cloudonprojects&tab=task&id=' . $tid);
        }
    }
    // χαρακτηρισμός: assignee/priority μόνο από διαχειριστές
    if ($FULL) {
        if (array_key_exists('assignee', $in)) {
            $newA = (int) $in['assignee'] ?: null;
            if ($newA && $newA !== (int) $t->assignee) {
                Notify::assigned($tid, $newA, $adminId);
            }
            $data['assignee'] = $newA;
        }
        if (array_key_exists('prio', $in)) {
            $data['priority'] = min(2, max(0, (int) $in['prio']));
        }
    }
    Db::saveTask($tid, $data, $adminId);
    Db::logActivity($tid, $adminId, 'edit', 'Επεξεργασία (web app)');
    out(['ok' => true]);

case 'comment':
    $tid = (int) ($in['task'] ?? 0);
    $t = Db::task($tid);
    $body = trim($in['body'] ?? '');
    if (!$t || !Db::canSeeTask($adminId, $t) || $body === '') {
        fail('input');
    }
    $to = ($in['to'] ?? '') !== '' && $in['to'] !== null ? (int) $in['to'] : null;
    Db::addComment($tid, $adminId, mb_substr($body, 0, 60000), $to);
    Notify::commented($tid, $adminId, $body);
    if ($to !== null) {
        Notify::commentTo($tid, $adminId, $body, $to);
    }
    Notify::watchers($tid, $adminId, 'Σχόλιο στο: ' . $t->title, null);
    out(['ok' => true]);

case 'timer_start':
    $tid = (int) ($in['task'] ?? 0);
    $t = Db::task($tid);
    if (!$t || !Db::canSeeTask($adminId, $t)) {
        fail('task', 403);
    }
    $r = Db::startTimer($tid, $adminId);
    foreach ($r['stopped'] as $sid) {
        Time::push($sid);
    }
    out(['ok' => true, 'id' => $r['id']]);

case 'timer_stop':
    $running = Db::runningTimer($adminId);
    if (!$running) {
        fail('no timer');
    }
    $e = Db::stopTimer($running->id);
    if ($e) {
        Db::updateTimelog($running->id, ['billable' => !empty($in['billable']) ? 1 : 0,
            'note' => mb_substr(trim($in['note'] ?? ''), 0, 255)]);
        Time::push($running->id);
    }
    out(['ok' => true, 'mins' => $e ? (int) Db::timelog($running->id)->minutes : 0]);

case 'time_add':
    $tid = (int) ($in['task'] ?? 0);
    $mins = (int) ($in['mins'] ?? 0);
    $t = Db::task($tid);
    if (!$t || !Db::canSeeTask($adminId, $t) || $mins <= 0) {
        fail('input');
    }
    $eid = Db::addTime($tid, $adminId, $mins, !empty($in['billable']), trim($in['note'] ?? ''));
    Time::push($eid);
    out(['ok' => true]);

case 'check_add':
    $tid = (int) ($in['task'] ?? 0);
    $t = Db::task($tid);
    $title = trim($in['title'] ?? '');
    if (!$t || !Db::canSeeTask($adminId, $t) || $title === '') {
        fail('input');
    }
    $id = Db::addCheckItem($tid, $title);
    out(['ok' => true, 'id' => $id]);

case 'check_toggle':
    $it = Db::toggleCheckItem((int) ($in['id'] ?? 0));
    out(['ok' => (bool) $it]);

case 'watch':
    $tid = (int) ($in['task'] ?? 0);
    if (!Db::task($tid)) {
        fail('task');
    }
    $on = Db::toggleWatcher($tid, $adminId);
    out(['ok' => true, 'watching' => $on]);

case 'remind':
    $at = preg_match('/^\d{4}-\d{2}-\d{2}(T| )\d{2}:\d{2}/', $in['at'] ?? '')
        ? str_replace('T', ' ', substr($in['at'], 0, 16)) . ':00' : null;
    if (!$at) {
        fail('input');
    }
    Db::addReminder($adminId, $at, trim($in['note'] ?? ''), (int) ($in['task'] ?? 0) ?: null);
    out(['ok' => true]);

case 'request_update':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    out(['ok' => Notify::requestUpdate((int) ($in['task'] ?? 0), $adminId)]);

/* ---- CRM actions ---- */
case 'move_lead':
    $l = Db::lead((int) ($in['lead'] ?? 0));
    if (!$l || (!$FULL && (int) $l->assignee !== $adminId && (int) $l->created_by !== $adminId)) {
        fail('lead', 403);
    }
    $okMv = Db::moveLead($l->id, (string) ($in['stage'] ?? ''), $adminId);
    if ($okMv && ($in['stage'] ?? '') === 'lost' && trim($in['reason'] ?? '') !== '') {
        Capsule::table('mod_cpm_leads')->where('id', $l->id)
            ->update(['lost_reason' => mb_substr(trim($in['reason']), 0, 190)]);
    }
    out(['ok' => $okMv]);

case 'save_lead':
    $lid = (int) ($in['lead'] ?? 0);
    if ($lid) {
        $l = Db::lead($lid);
        if (!$l || (!$FULL && (int) $l->assignee !== $adminId && (int) $l->created_by !== $adminId)) {
            fail('lead', 403);
        }
    }
    $stage = array_key_exists($in['stage'] ?? '', Db::leadStages()) ? $in['stage'] : 'target';
    $data = [
        'company' => mb_substr(trim($in['company'] ?? ''), 0, 120) ?: null,
        'contact' => mb_substr(trim($in['contact'] ?? ''), 0, 120) ?: null,
        'email' => mb_substr(trim($in['email'] ?? ''), 0, 120) ?: null,
        'phone' => mb_substr(trim($in['phone'] ?? ''), 0, 40) ?: null,
        'source' => mb_substr(trim($in['source'] ?? ''), 0, 60) ?: null,
        'stage' => $stage,
        'assignee' => (int) ($in['assignee'] ?? 0) ?: null,
        'next_action' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['next'] ?? '') ? $in['next'] : null,
        'next_note' => mb_substr(trim($in['nextNote'] ?? ''), 0, 200) ?: null,
        'descr' => cnp_clean_html($in['descr'] ?? '', 60000),   // rich-text
        'value' => ($in['value'] ?? '') !== '' && ($in['value'] ?? '') !== null
            ? round((float) str_replace(',', '.', (string) $in['value']), 2) : null,
        'lost_reason' => mb_substr(trim($in['lostReason'] ?? ''), 0, 190) ?: null,
        'closed_at' => Db::leadStages()[$stage][2] ? date('Y-m-d H:i:s') : null,
    ];
    if (!$lid) {
        $data['created_by'] = $adminId;
    }
    if (empty($data['company']) && empty($data['contact'])) {
        $data['company'] = 'Χωρίς όνομα';
    }
    out(['ok' => true, 'id' => Db::saveLead($lid, $data)]);

case 'interaction':
    $leadId = (int) ($in['lead'] ?? 0) ?: null;
    $clientId = (int) ($in['client'] ?? 0) ?: null;
    $summary = mb_substr(trim($in['summary'] ?? ''), 0, 255);
    if ((!$leadId && !$clientId) || $summary === '') {
        fail('input');
    }
    if ($leadId && !$clientId) {
        $clientId = (int) (Db::lead($leadId)->clientid ?? 0) ?: null;
    }
    Db::addInteraction(['lead_id' => $leadId, 'clientid' => $clientId,
        'kind' => array_key_exists($in['kind'] ?? '', Db::interactionKinds()) ? $in['kind'] : 'note',
        'summary' => $summary, 'detail' => null, 'admin_id' => $adminId,
        'happened_at' => date('Y-m-d H:i:s'),
        'followup_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['followup'] ?? '') ? $in['followup'] : null,
        'followup_note' => mb_substr(trim($in['followupNote'] ?? ''), 0, 200) ?: null]);
    out(['ok' => true]);

/* ================= ΛΙΣΤΑ / ΗΜΕΡΟΛΟΓΙΟ / ΧΡΟΝΟΣ ================= */
case 'list':
    $f = ['project_id' => (int) ($_GET['fp'] ?? 0), 'status_id' => (int) ($_GET['fs'] ?? 0),
          'assignee' => (int) ($_GET['fa'] ?? 0),
          'priority' => ($_GET['fr'] ?? '') !== '' ? (int) $_GET['fr'] : '',
          'q' => trim($_GET['q'] ?? ''), 'open_only' => (int) ($_GET['open'] ?? 1)];
    if (!$FULL) {
        $f['restrict_admin'] = $adminId;
    }
    $rows = Db::tasksFiltered($f);
    $mins = Db::minutesForTasks(array_map(function ($r) { return (int) $r->id; }, $rows->all()));
    $list = [];
    foreach ($rows as $t) {
        $d = taskDto($t);
        $d['project'] = (int) $t->project_id;      // το χρειάζεται το φίλτρο/chips ανά project
        $d['pname'] = $t->project_name;
        $d['pcolor'] = $t->project_color;
        $d['mins'] = (int) ($mins[(int) $t->id] ?? 0);
        $list[] = $d;
    }
    out(['tasks' => $list]);

case 'calendar':
    $ym = preg_match('/^\d{4}-\d{2}$/', $_GET['ym'] ?? '') ? $_GET['ym'] : date('Y-m');
    $items = [];
    foreach (Db::tasksForMonth($ym) as $t) {
        if (!$FULL && (int) $t->assignee !== $adminId && !Db::canSeeProject($adminId, $t->project_id)) {
            continue;
        }
        $items[] = ['id' => (int) $t->id, 'title' => $t->title, 'due' => $t->due_date,
            'prio' => (int) $t->priority, 'done' => (bool) $t->completed_at,
            'color' => $t->project_color, 'pname' => $t->project_name];
    }
    $evs = [];
    $mStart = $ym . '-01 00:00:00';
    $mEnd = date('Y-m-t 23:59:59', strtotime($ym . '-01'));
    foreach (Capsule::table('mod_cpm_events')
        ->where('start_dt', '<=', $mEnd)->where('end_dt', '>=', $mStart)
        ->orderBy('start_dt')->get() as $e) {
        $att = array_filter(array_map('intval', explode(',', $e->attendees)));
        $rs9 = [];
        foreach (Capsule::table('mod_cpm_event_rsvp')->where('event_id', $e->id)->get() as $r9) {
            $rs9[$r9->kind . $r9->ref] = $r9->status;
        }
        $evs[] = ['id' => (int) $e->id, 'kind' => $e->kind, 'title' => $e->title, 'rsvp' => $rs9,
            'start' => $e->start_dt, 'end' => $e->end_dt, 'allDay' => (bool) $e->all_day,
            'attendees' => array_values($att),
            'client' => $e->clientid ? (int) $e->clientid : null,
            'clientName' => $e->clientid ? clientLabel($e->clientid) : null,
            'location' => $e->location, 'notes' => $e->notes,
            'by' => (int) $e->created_by, 'canEdit' => $FULL || (int) $e->created_by === $adminId];
    }
    out(['ym' => $ym, 'items' => $items, 'events' => $evs]);

case 'event_save':                      // ομαδικό ημερολόγιο: meeting/ραντεβού/άδεια
    $eid = (int) ($in['id'] ?? 0);
    $kind = in_array($in['kind'] ?? '', ['meeting', 'appointment', 'leave', 'other'], true) ? $in['kind'] : 'meeting';
    $title = mb_substr(trim($in['title'] ?? ''), 0, 255);
    $startD = $in['start'] ?? '';
    $endD = $in['end'] ?? '';
    if ($title === '' || !strtotime($startD) || !strtotime($endD)) {
        fail('Τίτλος και ημερομηνίες είναι υποχρεωτικά');
    }
    if (strtotime($endD) < strtotime($startD)) {
        fail('Η λήξη είναι πριν την έναρξη');
    }
    $att = array_filter(array_map('intval', (array) ($in['attendees'] ?? [])));
    if (!$att) {
        $att = [$adminId];
    }
    $data = ['kind' => $kind, 'title' => $title,
        'start_dt' => date('Y-m-d H:i:s', strtotime($startD)),
        'end_dt' => date('Y-m-d H:i:s', strtotime($endD)),
        'all_day' => !empty($in['allDay']) ? 1 : 0,
        'attendees' => ',' . implode(',', $att) . ',',
        'clientid' => (int) ($in['client'] ?? 0) ?: null,
        'location' => mb_substr(trim($in['location'] ?? ''), 0, 190) ?: null,
        'notes' => cnp_clean_html($in['notes'] ?? '') ?: null];
    if ($eid) {
        $ev = Capsule::table('mod_cpm_events')->where('id', $eid)->first();
        if (!$ev || (!$FULL && (int) $ev->created_by !== $adminId)) {
            fail('event', 403);
        }
        Capsule::table('mod_cpm_events')->where('id', $eid)->update($data);
    } else {
        $eid = Capsule::table('mod_cpm_events')->insertGetId($data
            + ['created_by' => $adminId, 'created_at' => date('Y-m-d H:i:s')]);
        // ειδοποίησε τους συμμετέχοντες: καμπανάκι + EMAIL πρόσκλησης (τα email από το προφίλ τους)
        $kindL = ['meeting' => 'Meeting', 'appointment' => 'Ραντεβού', 'leave' => 'Άδεια', 'other' => 'Συμβάν'][$kind];
        $ts0 = strtotime($startD);
        $ts1 = strtotime($endD);
        $whenT = !empty($in['allDay'])
            ? date('d/m/Y', $ts0) . ($ts1 - $ts0 > 86400 ? ' – ' . date('d/m/Y', $ts1) : '')
            : date('d/m/Y H:i', $ts0) . ' – ' . date('H:i', $ts1);
        $gcalT = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            . '&text=' . rawurlencode($title)
            . '&dates=' . gmdate('Ymd\THis\Z', $ts0) . '/' . gmdate('Ymd\THis\Z', $ts1)
            . ($data['location'] ? '&location=' . rawurlencode($data['location']) : '');
        $isLinkT = $data['location'] && preg_match('#^https?://#', $data['location']);
        $bodyT = '<p><strong>' . htmlspecialchars($title) . '</strong> — σε προσκάλεσε ο/η ' . htmlspecialchars(Db::adminName($adminId)) . '</p>'
            . '<p style="background:#eef7fd;border-left:4px solid #0090dd;padding:10px 14px;">🗓 <strong>' . $whenT . '</strong>'
            . ($data['location'] ? '<br />' . ($isLinkT
                ? '🎥 Σύνδεσμος συμμετοχής: <a href="' . htmlspecialchars($data['location']) . '">' . htmlspecialchars($data['location']) . '</a>'
                : '📍 ' . htmlspecialchars($data['location'])) : '') . '</p>'
            . ($data['notes'] ? '<p>' . nl2br(htmlspecialchars($data['notes'])) . '</p>' : '')
            . '<p><a href="' . htmlspecialchars($gcalT) . '">➕ Προσθήκη στο ημερολόγιο</a> · '
            . '<a href="https://my.cloudon.gr/projectmanagement/#/calendar">Άνοιγμα στο πάνελ (RSVP)</a></p>';
        foreach ($att as $a) {
            if ($a !== $adminId) {
                Db::pushNotification($a, 'info', "📅 $kindL: $title — " . date('d/m H:i', $ts0), '/projectmanagement/#/calendar');
                if ($kind !== 'leave') {
                    \WHMCS\Module\Addon\CloudonProjects\Notify::send($a, "📅 $kindL: $title — " . date('d/m/Y H:i', $ts0), $bodyT);
                }
            }
        }
        // επιπλέον εξωτερικοί προσκεκλημένοι (σκέτα emails)
        foreach (array_filter(array_map('trim', explode(',', (string) ($in['extraEmails'] ?? '')))) as $xm) {
            if (filter_var($xm, FILTER_VALIDATE_EMAIL)) {
                \WHMCS\Module\Addon\CloudonProjects\Notify::sendTo($xm, "📅 Πρόσκληση: $title — " . date('d/m/Y H:i', $ts0), $bodyT);
            }
        }
        // 📧 πρόσκληση στον πελάτη (email με link + Add to Calendar)
        if (!empty($in['inviteClient']) && $data['clientid'] && in_array($kind, ['meeting', 'appointment'], true)) {
            $ts0 = strtotime($startD);
            $ts1 = strtotime($endD);
            $gcal = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
                . '&text=' . rawurlencode($title)
                . '&dates=' . gmdate('Ymd\THis\Z', $ts0) . '/' . gmdate('Ymd\THis\Z', $ts1)
                . ($data['location'] ? '&location=' . rawurlencode($data['location']) : '')
                . '&details=' . rawurlencode('Πρόσκληση από CloudOn' . ($data['notes'] ? ' — ' . mb_substr($data['notes'], 0, 300) : ''));
            $when = !empty($in['allDay'])
                ? date('d/m/Y', $ts0) . ($ts1 - $ts0 > 86400 ? ' – ' . date('d/m/Y', $ts1) : '')
                : date('d/m/Y H:i', $ts0) . ' – ' . date('H:i', $ts1);
            $isLink = $data['location'] && preg_match('#^https?://#', $data['location']);
            $msg = '<p>Αγαπητέ/ή πελάτη,</p>'
                . '<p>Σας προσκαλούμε σε <strong>' . ($kind === 'meeting' ? 'συνάντηση' : 'ραντεβού') . '</strong>: '
                . '<strong>' . htmlspecialchars($title) . '</strong></p>'
                . '<p style="background:#eef7fd;border-left:4px solid #0090dd;padding:10px 14px;">'
                . '🗓 <strong>' . $when . '</strong>'
                . ($data['location'] ? '<br />' . ($isLink
                    ? '🎥 Σύνδεσμος συμμετοχής: <a href="' . htmlspecialchars($data['location']) . '">' . htmlspecialchars($data['location']) . '</a>'
                    : '📍 Τοποθεσία: ' . htmlspecialchars($data['location'])) : '') . '</p>'
                . '<p><a href="' . htmlspecialchars($gcal) . '">➕ Προσθήκη στο ημερολόγιό σας (Google Calendar)</a></p>'
                . (function () use ($eid, $data) {
                    $base9 = 'https://my.cloudon.gr/projectmanagement/api.php?a=event_rsvp_public&t='
                        . pm_mint_rsvp($eid, $data['clientid']);
                    return '<table cellpadding="0" cellspacing="0" style="margin:14px 0"><tr>'
                        . '<td style="background:#2dbd6e;border-radius:10px;"><a href="' . $base9 . '&r=accept" '
                        . 'style="display:inline-block;padding:11px 22px;color:#ffffff;text-decoration:none;font-weight:bold;">✔ Επιβεβαιώνω τη συμμετοχή μου</a></td>'
                        . '<td style="width:12px;"></td>'
                        . '<td style="background:#e2515f;border-radius:10px;"><a href="' . $base9 . '&r=decline" '
                        . 'style="display:inline-block;padding:11px 22px;color:#ffffff;text-decoration:none;font-weight:bold;">✖ Δεν με εξυπηρετεί</a></td>'
                        . '</tr></table>';
                })()
                . '<p>Αν η ώρα δεν σας εξυπηρετεί, απαντήστε σε αυτό το email για εναλλακτική.</p>'
                . '<p>Με εκτίμηση,<br />Η ομάδα της CloudOn</p>';
            $er = localAPI('SendEmail', ['customtype' => 'general', 'id' => $data['clientid'],
                'customsubject' => '🗓 Πρόσκληση: ' . $title . ' — ' . date('d/m/Y', $ts0),
                'custommessage' => $msg]);
            if (($er['result'] ?? '') === 'success' && function_exists('logActivity')) {
                logActivity('CPM: πρόσκληση meeting «' . $title . '» στον πελάτη #' . $data['clientid'] . " (event #$eid)");
            }
        }
    }
    out(['ok' => true, 'id' => $eid]);

case 'event_del':
    $ev = Capsule::table('mod_cpm_events')->where('id', (int) ($in['id'] ?? 0))->first();
    if (!$ev || (!$FULL && (int) $ev->created_by !== $adminId)) {
        fail('event', 403);
    }
    Capsule::table('mod_cpm_events')->where('id', $ev->id)->delete();
    out(['ok' => true]);

case 'time':
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
    $fa = $FULL ? (int) ($_GET['fa'] ?? 0) : $adminId;
    $rows = Db::timeReport($from, $to, ['project_id' => (int) ($_GET['fp'] ?? 0), 'admin_id' => $fa]);
    $entries = [];
    $tot = ['w' => 0, 'b' => 0, 'nb' => 0, 'c' => 0];
    $agg = ['project' => [], 'client' => [], 'admin' => []];
    foreach ($rows as $r) {
        $m = (int) $r->minutes;
        $tot['w'] += $m;
        $tot[(int) $r->billable ? 'b' : 'nb'] += $m;
        $tot['c'] += (int) $r->charged_minutes;
        foreach ([['project', $r->project_name], ['client', $r->clientid ? clientLabel($r->clientid) : '— εσωτερικά —'],
                  ['admin', Db::adminName($r->admin_id)]] as $g) {
            [$grp, $key] = $g;
            if (!isset($agg[$grp][$key])) {
                $agg[$grp][$key] = ['w' => 0, 'b' => 0, 'c' => 0];
            }
            $agg[$grp][$key]['w'] += $m;
            if ((int) $r->billable) {
                $agg[$grp][$key]['b'] += $m;
            }
            $agg[$grp][$key]['c'] += (int) $r->charged_minutes;
        }
        $entries[] = ['at' => $r->created_at, 'task' => (int) $r->task_id, 'title' => $r->task_title,
            'pname' => $r->project_name, 'pcolor' => $r->project_color,
            'client' => $r->clientid ? clientLabel($r->clientid) : null,
            'by' => Db::adminName($r->admin_id), 'mins' => $m,
            'billable' => (bool) $r->billable, 'charged' => (int) $r->charged_minutes, 'note' => $r->note];
    }
    foreach ($agg as &$grp) {
        uasort($grp, function ($a, $b) { return $b['w'] <=> $a['w']; });
    }
    unset($grp);
    out(['from' => $from, 'to' => $to, 'entries' => $entries, 'totals' => $tot, 'agg' => $agg]);

/* ================= ΠΡΟΣΦΟΡΕΣ ================= */
case 'offers':
    $stages = [];
    foreach (Db::offerStages() as $k => $m) {
        $stages[] = ['key' => $k, 'title' => $m[0], 'color' => $m[1], 'closed' => (bool) $m[2], 'won' => (bool) $m[3]];
    }
    $offers = [];
    foreach (Db::offers((int) ($_GET['client'] ?? 0)) as $o) {
        if (!$FULL && (int) $o->assignee !== $adminId && (int) $o->created_by !== $adminId) {
            continue;
        }
        $offers[] = ['id' => (int) $o->id, 'title' => $o->title, 'stage' => $o->stage,
            'client' => $o->clientid ? (int) $o->clientid : null, 'clientName' => clientLabel($o->clientid),
            'value' => $o->quoteid && $o->quote_total !== null ? (float) $o->quote_total : (float) ($o->amount ?? 0),
            'amount' => $o->amount !== null ? (float) $o->amount : null,
            'quote' => $o->quoteid ? (int) $o->quoteid : null, 'quoteStage' => $o->quote_stage,
            'assignee' => $o->assignee ? (int) $o->assignee : null,
            'expected' => $o->expected_close, 'descr' => $o->descr, 'lead' => $o->lead_id ? (int) $o->lead_id : null];
    }
    out(['stages' => $stages, 'offers' => $offers]);

case 'move_offer':
    $o = Db::offer((int) ($in['offer'] ?? 0));
    if (!$o || (!$FULL && (int) $o->assignee !== $adminId && (int) $o->created_by !== $adminId)) {
        fail('offer', 403);
    }
    $stage = (string) ($in['stage'] ?? '');
    $ok = Db::moveOffer($o->id, $stage, $adminId);
    if ($ok && $o->quoteid) {
        $qs = ['draft' => 'Draft', 'sent' => 'Delivered', 'accepted' => 'Accepted', 'lost' => 'Lost'][$stage] ?? null;
        if ($qs) {
            Capsule::table('tblquotes')->where('id', (int) $o->quoteid)->update(['stage' => $qs]);
        }
    }
    out(['ok' => (bool) $ok]);

case 'save_offer':
    $oid = (int) ($in['offer'] ?? 0);
    if ($oid) {
        $o = Db::offer($oid);
        if (!$o || (!$FULL && (int) $o->assignee !== $adminId && (int) $o->created_by !== $adminId)) {
            fail('offer', 403);
        }
    }
    $stage = array_key_exists($in['stage'] ?? '', Db::offerStages()) ? $in['stage'] : 'new';
    $data = ['title' => mb_substr(trim($in['title'] ?? ''), 0, 200) ?: 'Χωρίς τίτλο',
        'clientid' => (int) ($in['client'] ?? 0) ?: null,
        'amount' => ($in['amount'] ?? '') !== '' && $in['amount'] !== null ? round((float) $in['amount'], 2) : null,
        'stage' => $stage, 'assignee' => (int) ($in['assignee'] ?? 0) ?: null,
        'expected_close' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['expected'] ?? '') ? $in['expected'] : null,
        'descr' => cnp_clean_html($in['descr'] ?? '', 60000),   // rich-text
        'closed_at' => Db::offerStages()[$stage][2] ? date('Y-m-d H:i:s') : null];
    if (!$oid) {
        $data['created_by'] = $adminId;
    }
    out(['ok' => true, 'id' => Db::saveOffer($oid, $data)]);

case 'create_quote':
    $o = Db::offer((int) ($in['offer'] ?? 0));
    if (!$o || $o->quoteid || !$o->clientid) {
        fail('offer');
    }
    $r = localAPI('CreateQuote', ['subject' => $o->title, 'stage' => 'Draft',
        'validuntil' => $o->expected_close ?: date('Y-m-d', strtotime('+30 days')),
        'userid' => (int) $o->clientid,
        'lineitems' => base64_encode(serialize([['desc' => $o->title, 'qty' => 1,
            'up' => (float) ($o->amount ?? 0), 'discount' => 0, 'taxable' => true]]))], 'pdelis');
    if (($r['result'] ?? '') === 'success' && !empty($r['quoteid'])) {
        Db::saveOffer($o->id, ['quoteid' => (int) $r['quoteid'], 'stage' => 'draft']);
        out(['ok' => true, 'quote' => (int) $r['quoteid']]);
    }
    fail($r['message'] ?? 'CreateQuote failed');

/* ================= CRM: ΕΠΑΦΕΣ / ΕΠΙΚΟΙΝΩΝΙΕΣ ================= */
case 'contacts':
    $q = trim($_GET['q'] ?? '');
    $last = Db::lastContactMap();
    $stages = Db::leadStages();
    $rows = [];
    foreach (Db::leads() as $l) {
        if (!$FULL && (int) $l->assignee !== $adminId && (int) $l->created_by !== $adminId) {
            continue;
        }
        $name = $l->company ?: $l->contact ?: '—';
        if ($q !== '' && mb_stripos($name . ' ' . $l->contact . ' ' . $l->email . ' ' . $l->phone, $q) === false) {
            continue;
        }
        $m = $stages[$l->stage] ?? $stages['target'];
        $rows[] = ['kind' => 'lead', 'id' => (int) $l->id, 'name' => $name,
            'sub' => $l->contact && $l->company ? $l->contact : null,
            'badge' => $m[0], 'color' => $m[1], 'phone' => $l->phone, 'email' => $l->email,
            'last' => $last['lead:' . $l->id] ?? null,
            'next' => !$m[2] ? $l->next_action : null, 'who' => $l->assignee ? Db::adminName($l->assignee) : null];
    }
    if ($FULL) {
        $cids = [];
        foreach (array_keys($last) as $k) {
            if (strpos($k, 'client:') === 0) {
                $cids[] = (int) substr($k, 7);
            }
        }
        $cq = Capsule::table('tblclients');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $cq->where(function ($w) use ($like) {
                $w->where('firstname', 'like', $like)->orWhere('lastname', 'like', $like)
                  ->orWhere('companyname', 'like', $like)->orWhere('email', 'like', $like);
            })->limit(30);
        } elseif ($cids) {
            $cq->whereIn('id', $cids);
        } else {
            $cq->whereRaw('1=0');
        }
        foreach ($cq->get(['id', 'firstname', 'lastname', 'companyname', 'email', 'phonenumber']) as $c) {
            $rows[] = ['kind' => 'client', 'id' => (int) $c->id,
                'name' => $c->companyname ?: trim($c->firstname . ' ' . $c->lastname), 'sub' => null,
                'badge' => 'Πελάτης', 'color' => '#16a26a', 'phone' => $c->phonenumber, 'email' => $c->email,
                'last' => $last['client:' . $c->id] ?? null, 'next' => null, 'who' => null];
        }
    }
    usort($rows, function ($a, $b) { return strcmp((string) $b['last'], (string) $a['last']); });
    out(['rows' => $rows]);

case 'comms':
    $recent = [];
    foreach (Db::recentInteractions(60) as $i) {
        if (!$FULL && (int) $i->admin_id !== $adminId) {
            continue;
        }
        $recent[] = ['id' => (int) $i->id, 'kind' => $i->kind, 'summary' => $i->summary,
            'by' => Db::adminName($i->admin_id), 'at' => $i->happened_at,
            'lead' => $i->lead_id ? (int) $i->lead_id : null,
            'who' => $i->lead_id ? ($i->lead_company ?: $i->lead_contact) : clientLabel($i->clientid),
            'followup' => $i->followup_date, 'followupNote' => $i->followup_note,
            'followupDone' => (bool) $i->followup_done];
    }
    out(['recent' => $recent]);

case 'followup_done':
    $i = Db::interaction((int) ($in['id'] ?? 0));
    if (!$i || (!$FULL && (int) $i->admin_id !== $adminId)) {
        fail('interaction', 403);
    }
    Capsule::table('mod_cpm_interactions')->where('id', $i->id)->update(['followup_done' => 1]);
    out(['ok' => true]);

/* ================= ΣΤΟΧΟΙ ΠΡΟΪΟΝΤΩΝ ================= */
case 'targets':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $ym = preg_match('/^\d{4}-\d{2}$/', $_GET['ym'] ?? '') ? $_GET['ym'] : date('Y-m');
    $from = $ym . '-01';
    $to = date('Y-m-t', strtotime($from));
    // client → πωλητής (από το lead που έκλεισε: assignee)
    $lead2seller = [];
    foreach (Capsule::table('mod_cpm_leads')->where('clientid', '>', 0)->where('assignee', '>', 0)
        ->orderBy('id')->get(['clientid', 'assignee']) as $l) {
        $lead2seller[(int) $l->clientid] = (int) $l->assignee;   // τελευταίο lead κερδίζει
    }
    // πωλήσεις μήνα ανά προϊόν + πωλητή
    $agg = [];   // pid => sellerId(0=χωρίς) => [units, value]
    foreach (Capsule::table('tblhosting')->whereBetween('regdate', [$from, $to])
        ->whereNotIn('domainstatus', ['Cancelled', 'Fraud'])->get(['packageid', 'userid', 'amount']) as $r) {
        $pid = (int) $r->packageid;
        $seller = $lead2seller[(int) $r->userid] ?? 0;
        $agg[$pid][$seller] = $agg[$pid][$seller] ?? [0, 0.0];
        $agg[$pid][$seller][0]++;
        $agg[$pid][$seller][1] += (float) $r->amount;
    }
    // στόχοι ανά προϊόν + πωλητή
    $tg = [];   // pid => adminId => [tUnits, tValue, id]
    foreach (Capsule::table('mod_cpm_product_targets')->get() as $t) {
        $tg[(int) $t->product_id][(int) $t->admin_id] = [(int) $t->target_units, (float) $t->target_value, (int) $t->id];
    }
    $pids = array_values(array_unique(array_merge(array_keys($agg), array_keys($tg))));
    $pnames = $pids ? Capsule::table('tblproducts')->whereIn('id', $pids)->pluck('name', 'id')->all() : [];
    $cards = [];
    foreach ($pids as $pid) {
        $ovT = $tg[$pid][0] ?? [0, 0.0, 0];               // εταιρικός στόχος (admin 0)
        $ou = 0; $ov = 0.0;
        foreach (($agg[$pid] ?? []) as $uv) { $ou += $uv[0]; $ov += $uv[1]; }
        $people = [];
        $set = array_unique(array_merge(array_keys($tg[$pid] ?? []), array_keys($agg[$pid] ?? [])));
        foreach ($set as $a) {
            if ($a === 0) { continue; }
            $t = $tg[$pid][$a] ?? [0, 0.0, 0];
            $s = $agg[$pid][$a] ?? [0, 0.0];
            $people[] = ['admin' => $a, 'name' => Db::adminName($a),
                'tUnits' => $t[0], 'tValue' => $t[1], 'tid' => $t[2], 'units' => $s[0], 'value' => $s[1]];
        }
        usort($people, function ($x, $y) { return $y['value'] <=> $x['value']; });
        $un = $agg[$pid][0] ?? [0, 0.0];
        $cards[] = ['product' => $pid, 'name' => $pnames[$pid] ?? ('#' . $pid),
            'tUnits' => $ovT[0], 'tValue' => $ovT[1], 'tid' => $ovT[2], 'units' => $ou, 'value' => $ov,
            'people' => $people, 'unattrUnits' => $un[0], 'unattrValue' => $un[1]];
    }
    usort($cards, function ($x, $y) { return $y['value'] <=> $x['value']; });
    $sellers = [];
    foreach (Db::admins() as $a) {
        $nm = trim($a->firstname . ' ' . $a->lastname);
        if (preg_match('/\b(bot|test|debug|cnptest|system)\b/i', $nm)) { continue; }
        $sellers[] = ['id' => (int) $a->id, 'name' => $nm];
    }
    $products = [];
    $gNames = Capsule::table('tblproductgroups')->pluck('name', 'id')->all();
    foreach (Capsule::table('tblproducts')->where('hidden', 0)->orderBy('name')->get(['id', 'name', 'gid']) as $p) {
        $products[] = ['id' => (int) $p->id, 'name' => $p->name, 'group' => $gNames[(int) $p->gid] ?? ''];
    }
    out(['ym' => $ym, 'cards' => $cards, 'sellers' => $sellers, 'products' => $products]);

case 'save_ptarget':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $pid = (int) ($in['product'] ?? 0);
    if (!$pid || !Capsule::table('tblproducts')->where('id', $pid)->exists()) {
        fail('product');
    }
    $adm = (int) ($in['admin'] ?? 0);   // 0 = εταιρικός στόχος, >0 = ανά πωλητή
    $u = max(0, (int) ($in['units'] ?? 0));
    $v = round((float) ($in['value'] ?? 0), 2);
    if ($u === 0 && $v == 0.0) {   // κενό = διαγραφή αυτού του στόχου
        Capsule::table('mod_cpm_product_targets')->where('product_id', $pid)->where('admin_id', $adm)->delete();
        out(['ok' => true, 'deleted' => true]);
    }
    Capsule::table('mod_cpm_product_targets')->updateOrInsert(
        ['product_id' => $pid, 'admin_id' => $adm],
        ['target_units' => $u, 'target_value' => $v, 'created_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true]);

case 'del_ptarget':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    Db::deleteProductTarget((int) ($in['id'] ?? 0));
    out(['ok' => true]);

/* ================= ΠΕΛΑΤΗΣ 360 ================= */
case 'client360':
    // Ανοιχτό και στους χειριστές — αλλά τα οικονομικά (ποσά) μόνο σε διαχειριστές
    $q = trim($_GET['q'] ?? '');
    $cid = (int) ($_GET['id'] ?? 0);
    if (!$cid && $q !== '') {
        if (ctype_digit($q)) {
            $cid = (int) $q;
        } else {
            $like = '%' . $q . '%';
            $matches = [];
            foreach (Capsule::table('tblclients')->where(function ($w) use ($like) {
                $w->where('firstname', 'like', $like)->orWhere('lastname', 'like', $like)
                  ->orWhere('companyname', 'like', $like)->orWhere('email', 'like', $like);
            })->limit(12)->get(['id', 'firstname', 'lastname', 'companyname', 'email']) as $m) {
                $matches[] = ['id' => (int) $m->id, 'name' => $m->companyname ?: trim($m->firstname . ' ' . $m->lastname), 'email' => $m->email];
            }
            if (count($matches) === 1) {
                $cid = $matches[0]['id'];
            } else {
                out(['matches' => $matches]);
            }
        }
    }
    if (!$cid) {
        out(['matches' => []]);
    }
    $cl = Capsule::table('tblclients')->where('id', $cid)->first(['id', 'firstname', 'lastname', 'companyname', 'email']);
    if (!$cl) {
        fail('client', 404);
    }
    $months = in_array((int) ($_GET['months'] ?? 6), [3, 6, 12, 120], true) ? (int) $_GET['months'] : 6;
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $scBal = null;
    try {
        if (Capsule::schema()->hasTable('mod_supportcontracts_clients')) {
            $scBal = Capsule::table('mod_supportcontracts_clients')->where('userid', $cid)->value('balance_minutes');
        }
    } catch (\Throwable $e) {
    }
    // 📦 Υπηρεσίες/προγράμματα από εμάς (ενεργά + σε αναστολή)
    $svcs = [];
    foreach (Capsule::table('tblhosting as h')
        ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
        ->where('h.userid', $cid)->whereIn('h.domainstatus', ['Active', 'Suspended'])
        ->orderBy('h.nextduedate')
        ->get(['h.id', 'h.domain', 'h.domainstatus', 'h.nextduedate', 'h.billingcycle',
            'h.amount', 'h.dedicatedip', 'p.name as pname']) as $sv) {
        $svcs[] = ['id' => (int) $sv->id, 'product' => $sv->pname, 'domain' => $sv->domain,
            'ip' => $sv->dedicatedip, 'status' => $sv->domainstatus,
            'due' => $sv->nextduedate > '0000-00-00' ? $sv->nextduedate : null,
            'cycle' => $sv->billingcycle,
            'amount' => $FULL ? (float) $sv->amount : null];
    }
    // 🛡️ SLA / συμβόλαιο υποστήριξης
    $slaInfo = null;
    try {
        if (Capsule::schema()->hasTable('mod_supportcontracts_clients')) {
            $sc = Capsule::table('mod_supportcontracts_clients')->where('userid', $cid)->first();
            if ($sc) {
                $met = null;
                $tot9 = Capsule::table('mod_supportcontracts_tickets as st')
                    ->join('tbltickets as t', 't.id', '=', 'st.ticketid')
                    ->where('t.userid', $cid)->whereNotNull('st.first_response_at')
                    ->where('t.date', '>=', date('Y-m-d', strtotime('-90 days')));
                $cnt9 = (clone $tot9)->count();
                if ($cnt9 > 0) {
                    $met = round((clone $tot9)->where('st.sla_met', 1)->count() / $cnt9 * 100);
                }
                $slaInfo = ['enabled' => (bool) $sc->enabled, 'priority' => $sc->priority,
                    'label' => $sc->label,
                    'response' => trim($sc->sla_response_value . ' ' . $sc->sla_response_unit),
                    'bizHours' => trim(($sc->biz_start ?? '') . '–' . ($sc->biz_end ?? '')),
                    'balance' => (int) $sc->balance_minutes, 'met90' => $met];
            }
        }
    } catch (\Throwable $e) {
    }
    // 💶 Ανοιχτό υπόλοιπο: διαχειριστές=ποσό, χειριστές=ΝΑΙ/ΟΧΙ
    $owedAmt = (float) Capsule::table('tblinvoices')->where('userid', $cid)->where('status', 'Unpaid')->sum('total');
    $owedCnt = (int) Capsule::table('tblinvoices')->where('userid', $cid)->where('status', 'Unpaid')->count();
    // 👥 πρόσωπα επαφής + τηλέφωνο
    $phone9 = Capsule::table('tblclients')->where('id', $cid)->value('phonenumber');
    $people9 = [];
    try {
        foreach (Capsule::table('mod_cpm_people')->where('clientid', $cid)->get() as $pp) {
            $people9[] = ['name' => $pp->name, 'title' => $pp->title, 'phone' => $pp->phone, 'email' => $pp->email];
        }
    } catch (\Throwable $e) {
    }
    $myPk = (int) Capsule::table('mod_cpm_client_package')->where('clientid', $cid)->value('package_id');
    $allPk = [];
    foreach (Capsule::table('mod_cpm_support_packages')->orderBy('sort')->get(['id', 'name']) as $pk) {
        $allPk[] = ['id' => (int) $pk->id, 'name' => $pk->name];
    }
    out(['client' => ['id' => $cid, 'name' => $cl->companyname ?: trim($cl->firstname . ' ' . $cl->lastname),
            'email' => $cl->email, 'phone' => $phone9],
        'package' => $myPk ?: null, 'packages' => $FULL ? $allPk : [],
        'remote' => (function () use ($cid) {
            try {
                $q = Capsule::table('mod_cpm_remote_sessions')->where('clientid', $cid)->whereNotNull('ended_at');
                return ['sessions90' => (clone $q)->where('started_at', '>=', date('Y-m-d', strtotime('-90 days')))->count(),
                    'mins90' => (int) (clone $q)->where('started_at', '>=', date('Y-m-d', strtotime('-90 days')))->sum('minutes'),
                    'monthMins' => (int) (clone $q)->where('started_at', '>=', date('Y-m-01'))->sum('minutes')];
            } catch (\Throwable $e) {
                return null;
            }
        })(),
        'services' => $svcs,
        'sla' => $slaInfo,
        'owed' => ['flag' => $owedCnt > 0,
            'amount' => $FULL ? round($owedAmt, 2) : null,
            'count' => $FULL ? $owedCnt : null],
        'people' => $people9,
        'full' => $FULL,
        'summary' => [
            'services' => Capsule::table('tblhosting')->where('userid', $cid)->where('domainstatus', 'Active')->count(),
            'openTasks' => Capsule::table('mod_cpm_tasks as t')->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
                ->where('p.clientid', $cid)->whereNotIn('t.status_id', $doneIds)->count(),
            'openTickets' => Capsule::table('tbltickets')->where('userid', $cid)->whereNotIn('status', ['Closed', 'Cancelled'])->count(),
            'scBalance' => $scBal !== null ? (int) $scBal : null,
        ],
        'months' => $months,
        'timeline' => Db::clientTimeline($cid, date('Y-m-d', strtotime('-' . $months . ' months')))]);

/* ================= ΚΕΡΔΟΦΟΡΙΑ ================= */
case 'profit':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-01-01');
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
    $costH = (float) str_replace(',', '.', (string) (Capsule::table('tbladdonmodules')
        ->where('module', 'cloudonprojects')->where('setting', 'cost_per_hour')->value('value') ?: 0));
    $mins = [];
    foreach (Capsule::table('mod_cpm_timelogs as l')
        ->join('mod_cpm_tasks as t', 't.id', '=', 'l.task_id')
        ->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
        ->where('l.running', 0)->whereBetween('l.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
        ->selectRaw('COALESCE(p.clientid, l.sc_userid, 0) as cid, SUM(l.minutes) m')->groupBy('cid')->get() as $r) {
        $mins[(int) $r->cid] = (int) $r->m;
    }
    $exp = [];
    $expList = [];
    foreach (Db::expenses($from, $to) as $e) {
        $exp[(int) ($e->clientid ?: 0)] = ($exp[(int) ($e->clientid ?: 0)] ?? 0) + (float) $e->amount;
        $expList[] = ['id' => (int) $e->id, 'at' => $e->spent_at, 'project' => $e->project_name,
            'client' => $e->clientid ? clientLabel($e->clientid) : null,
            'descr' => $e->descr, 'amount' => (float) $e->amount];
    }
    $cids = array_values(array_filter(array_unique(array_merge(array_keys($mins), array_keys($exp)))));
    $rev = [];
    if ($cids) {
        foreach (Capsule::table('tblaccounts')->whereIn('userid', $cids)
            ->whereBetween('date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('userid, SUM(amountin) - SUM(amountout) as v')->groupBy('userid')->get() as $r) {
            $rev[(int) $r->userid] = (float) $r->v;
        }
    }
    $clients = [];
    foreach ($cids as $cid2) {
        $labor = round(($mins[$cid2] ?? 0) / 60 * $costH, 2);
        $e2 = round($exp[$cid2] ?? 0, 2);
        $r2 = round($rev[$cid2] ?? 0, 2);
        $clients[] = ['id' => $cid2, 'name' => clientLabel($cid2) ?: '—', 'mins' => $mins[$cid2] ?? 0,
            'labor' => $labor, 'exp' => $e2, 'rev' => $r2, 'profit' => round($r2 - $labor - $e2, 2)];
    }
    usort($clients, function ($a, $b) { return $a['profit'] <=> $b['profit']; });
    $projList = [];
    foreach (Db::projects(true) as $p) {
        $projList[] = ['id' => (int) $p->id, 'name' => $p->name];
    }
    out(['from' => $from, 'to' => $to, 'costH' => $costH, 'clients' => $clients,
        'expenses' => $expList, 'projects' => $projList]);

case 'add_expense':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $pid = (int) ($in['project'] ?? 0);
    $amt = (float) ($in['amount'] ?? 0);
    if (!$pid || $amt <= 0 || !Db::project($pid)) {
        fail('input');
    }
    Db::addExpense($pid, trim($in['descr'] ?? '') ?: 'Έξοδο', $amt,
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['at'] ?? '') ? $in['at'] : date('Y-m-d'), $adminId);
    out(['ok' => true]);

case 'del_expense':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    Db::deleteExpense((int) ($in['id'] ?? 0));
    out(['ok' => true]);

/* ================= ΟΜΑΔΕΣ ================= */
case 'teams':
    $teams = [];
    $assigned = [];
    foreach (Db::teams() as $tm) {
        $members = [];
        foreach (Db::teamMembers($tm->id) as $m) {
            $assigned[(int) $m->admin_id] = 1;
            $members[] = ['id' => (int) $m->admin_id, 'name' => Db::adminName($m->admin_id),
                'ini' => initials(Db::adminName($m->admin_id)),
                'role' => $m->role_title, 'leader' => (bool) $m->is_leader];
        }
        $projNames = Capsule::table('mod_cpm_project_teams as pt')
            ->join('mod_cpm_projects as p', 'p.id', '=', 'pt.project_id')
            ->where('pt.team_id', $tm->id)->pluck('p.name')->all();
        $teams[] = ['id' => (int) $tm->id, 'name' => $tm->name, 'color' => $tm->color,
            'descr' => $tm->descr, 'members' => $members, 'projects' => $projNames];
    }
    $solo = [];
    foreach (Db::admins() as $a) {
        if (!isset($assigned[(int) $a->id])) {
            $solo[] = ['name' => trim($a->firstname . ' ' . $a->lastname), 'full' => Db::isFullAccess($a->id)];
        }
    }
    $rolesCfg = (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', 'team_roles')->value('value')
        ?: 'Διαχειριστής έργου,Senior Τεχνικός,Τεχνικός,Υποστήριξη,Πωλήσεις,Λογιστήριο,Developer');
    out(['teams' => $teams, 'solo' => $solo,
        'roles' => array_values(array_filter(array_map('trim', explode(',', $rolesCfg)))), 'canManage' => $FULL]);

case 'save_team':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $tid = Db::saveTeam((int) ($in['id'] ?? 0), [
        'name' => mb_substr(trim($in['name'] ?? ''), 0, 80) ?: 'Χωρίς όνομα',
        'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $in['color'] ?? '') ? $in['color'] : '#0090dd',
        'descr' => mb_substr(trim($in['descr'] ?? ''), 0, 500)]);
    out(['ok' => true, 'id' => $tid]);

case 'del_team':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    Db::deleteTeam((int) ($in['id'] ?? 0));
    out(['ok' => true]);

case 'team_member_add':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $tid = (int) ($in['team'] ?? 0);
    $aid2 = (int) ($in['admin'] ?? 0);
    if (!$tid || !$aid2 || !Db::team($tid)) {
        fail('input');
    }
    Db::addTeamMember($tid, $aid2, mb_substr(trim($in['role'] ?? ''), 0, 60) ?: null, !empty($in['leader']) ? 1 : 0);
    out(['ok' => true]);

case 'team_member_del':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    Db::removeTeamMember((int) ($in['team'] ?? 0), (int) ($in['admin'] ?? 0));
    out(['ok' => true]);

/* ================= PROJECTS PORTFOLIO ================= */
case 'portfolio':
    $projs = [];
    $depts = [];
    foreach (Capsule::table('tblticketdepartments')->orderBy('order')->get(['id', 'name']) as $dp) {
        $depts[] = ['id' => (int) $dp->id, 'name' => $dp->name];
    }
    $src = $FULL ? Db::projects(true) : Db::projectsFor($adminId, true);
    $todoBy = [];
    try {
        foreach (Capsule::table('mod_cpm_project_todos')->groupBy('project_id')
            ->get(['project_id', Capsule::raw('COUNT(*) t'), Capsule::raw('SUM(done_at IS NOT NULL) d')]) as $r8) {
            $todoBy[(int) $r8->project_id] = [(int) $r8->d, (int) $r8->t];
        }
    } catch (\Throwable $e) {
    }
    $spentBy = [];
    try {
        foreach (Capsule::table('mod_cpm_timelogs as tl')
            ->join('mod_cpm_tasks as t', 't.id', '=', 'tl.task_id')
            ->where('tl.running', 0)
            ->groupBy('t.project_id')
            ->get([Capsule::raw('t.project_id as pid'), Capsule::raw('SUM(tl.minutes) as m')]) as $r9) {
            $spentBy[(int) $r9->pid] = (int) $r9->m;
        }
    } catch (\Throwable $e) {
    }
    foreach ($src as $p) {
        [$done, $total, $pct] = Db::projectProgress($p->id);
        $delta = Db::snapshotDelta($p->id, 7);
        $projs[] = ['id' => (int) $p->id, 'name' => $p->name, 'color' => $p->color,
            'kind' => $p->kind ?? 'dept',
            'budget' => $p->budget !== null ? (float) $p->budget : null,
            'estHours' => $p->est_hours !== null ? (float) $p->est_hours : null,
            'start' => $p->start_date, 'due' => $p->due_date,
            'offerId' => $p->offer_id ? (int) $p->offer_id : null,
            'spentMins' => $spentBy[(int) $p->id] ?? 0,
            'todos' => $todoBy[(int) $p->id] ?? null,
            'client' => $p->clientid ? (int) $p->clientid : null, 'clientName' => clientLabel($p->clientid),
            'dept' => $p->deptid ? (int) $p->deptid : null, 'parent' => $p->parent_id ? (int) $p->parent_id : null,
            'pstatus' => $p->pstatus, 'health' => $p->health, 'archived' => $p->status === 'archived',
            'visible' => (bool) $p->client_visible, 'done' => $done, 'total' => $total, 'pct' => $pct,
            'trend' => $delta ? $delta[1] - $delta[0] : null,
            'members' => $FULL ? Db::projectMembers($p->id) : [],
            'teams' => $FULL ? Db::projectTeams($p->id) : []];
    }
    $teamsL = [];
    foreach (Db::teams() as $tm) {
        $teamsL[] = ['id' => (int) $tm->id, 'name' => $tm->name, 'color' => $tm->color];
    }
    out(['projects' => $projs, 'depts' => $depts, 'teams' => $teamsL, 'canManage' => $FULL]);

case 'save_project':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $pid = (int) ($in['id'] ?? 0);
    $data = ['name' => mb_substr(trim($in['name'] ?? ''), 0, 120) ?: 'Χωρίς όνομα',
        'clientid' => (int) ($in['client'] ?? 0) ?: null,
        'deptid' => (int) ($in['dept'] ?? 0) ?: null,
        'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $in['color'] ?? '') ? $in['color'] : '#0090dd',
        'descr' => cnp_clean_html($in['descr'] ?? '', 60000),   // rich-text
        'client_visible' => !empty($in['visible']) ? 1 : 0,
        'parent_id' => ((int) ($in['parent'] ?? 0) && (int) $in['parent'] !== $pid) ? (int) $in['parent'] : null,
        'pstatus' => array_key_exists($in['pstatus'] ?? '', Db::projectStatuses()) ? $in['pstatus'] : null,
        'health' => array_key_exists($in['health'] ?? '', Db::healthColors()) ? $in['health'] : null,
        'kind' => in_array($in['kind'] ?? '', ['dept', 'client'], true) ? $in['kind'] : 'dept',
        'budget' => ($in['budget'] ?? '') !== '' && $in['budget'] !== null
            ? round((float) str_replace(',', '.', (string) $in['budget']), 2) : null,
        'est_hours' => ($in['estHours'] ?? '') !== '' && $in['estHours'] !== null
            ? round((float) str_replace(',', '.', (string) $in['estHours']), 1) : null,
        'start_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['start'] ?? '') ? $in['start'] : null,
        'due_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['due'] ?? '') ? $in['due'] : null];
    $pid = Db::saveProject($pid, $data);
    if (array_key_exists('members', $in)) {
        Db::saveProjectMembers($pid, (array) $in['members']);
    }
    if (array_key_exists('teams', $in)) {
        Db::saveProjectTeams($pid, (array) $in['teams']);
    }
    out(['ok' => true, 'id' => $pid]);

case 'project_from_offer':             // κερδισμένη προσφορά → έργο πελάτη
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $of9 = Capsule::table('mod_cpm_offers')->where('id', (int) ($in['offer'] ?? 0))->first();
    if (!$of9) {
        fail('offer', 404);
    }
    $ex9 = Capsule::table('mod_cpm_projects')->where('offer_id', $of9->id)->first(['id', 'name']);
    if ($ex9) {
        out(['ok' => true, 'id' => (int) $ex9->id, 'existing' => true]);
    }
    $amount9 = $of9->quoteid
        ? (float) (Capsule::table('tblquotes')->where('id', $of9->quoteid)->value('total') ?: 0)
        : (float) ($of9->amount ?: 0);
    $pid9 = Db::saveProject(0, [
        'name' => mb_substr('Έργο: ' . $of9->title, 0, 120),
        'kind' => 'client',
        'clientid' => $of9->clientid ?: null,
        'budget' => $amount9 ?: null,
        'offer_id' => (int) $of9->id,
        'pstatus' => 'new', 'health' => 'green',
        'color' => '#7b5cd6', 'client_visible' => 0,
        'descr' => "Δημιουργήθηκε από προσφορά #{$of9->id}" . ($of9->quoteid ? " / Quote #{$of9->quoteid}" : ''),
    ]);
    Db::saveTask(0, ['project_id' => $pid9, 'title' => 'Kick-off: πλάνο & ανάλυση έργου',
        'status_id' => Db::firstStatusId()], $adminId);
    if (function_exists('logActivity')) {
        logActivity('CPM: έργο #' . $pid9 . ' από προσφορά #' . $of9->id . ' (admin #' . $adminId . ')');
    }
    out(['ok' => true, 'id' => $pid9]);

case 'ptodos':                         // 📋 παραδοτέα/TODO έργου
    $pid7 = (int) ($_GET['project'] ?? 0);
    if (!$pid7 || !Db::canSeeProject($adminId, $pid7)) {
        fail('project', 403);
    }
    out(['todos' => array_map(function ($t) {
        return ['id' => (int) $t->id, 'title' => $t->title,
            'done' => (bool) $t->done_at,
            'doneBy' => $t->done_by ? Db::adminName($t->done_by) : null,
            'doneAt' => $t->done_at ? substr($t->done_at, 0, 10) : null];
    }, Capsule::table('mod_cpm_project_todos')->where('project_id', $pid7)
        ->orderBy('sort')->orderBy('id')->get()->all())]);

case 'ptodo_add':
    $pid7 = (int) ($in['project'] ?? 0);
    $t7 = mb_substr(trim($in['title'] ?? ''), 0, 255);
    if (!$pid7 || $t7 === '' || !Db::canSeeProject($adminId, $pid7)) {
        fail('input', 403);
    }
    $id7 = Capsule::table('mod_cpm_project_todos')->insertGetId(['project_id' => $pid7,
        'title' => $t7, 'created_by' => $adminId, 'created_at' => date('Y-m-d H:i:s'),
        'sort' => (int) Capsule::table('mod_cpm_project_todos')->where('project_id', $pid7)->max('sort') + 1]);
    out(['ok' => true, 'id' => $id7]);

case 'ptodo_toggle':
    $td = Capsule::table('mod_cpm_project_todos')->where('id', (int) ($in['id'] ?? 0))->first();
    if (!$td || !Db::canSeeProject($adminId, $td->project_id)) {
        fail('todo', 403);
    }
    Capsule::table('mod_cpm_project_todos')->where('id', $td->id)->update($td->done_at
        ? ['done_at' => null, 'done_by' => null]
        : ['done_at' => date('Y-m-d H:i:s'), 'done_by' => $adminId]);
    out(['ok' => true, 'done' => !$td->done_at]);

case 'ptodo_del':
    $td = Capsule::table('mod_cpm_project_todos')->where('id', (int) ($in['id'] ?? 0))->first();
    if (!$td || !Db::canSeeProject($adminId, $td->project_id)) {
        fail('todo', 403);
    }
    Capsule::table('mod_cpm_project_todos')->where('id', $td->id)->delete();
    out(['ok' => true]);

case 'archive_project':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $p = Db::project((int) ($in['id'] ?? 0));
    if ($p) {
        Db::saveProject($p->id, ['status' => $p->status === 'archived' ? 'active' : 'archived']);
    }
    out(['ok' => (bool) $p]);

case 'recurring':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $recs = [];
    foreach (Db::recurringAll() as $r) {
        $recs[] = ['id' => (int) $r->id, 'title' => $r->title, 'project' => (int) $r->project_id,
            'pname' => $r->project_name, 'pcolor' => $r->project_color,
            'freq' => $r->freq, 'every' => (int) $r->every, 'next' => $r->next_run,
            'dueDays' => (int) $r->due_days, 'assignee' => $r->assignee ? (int) $r->assignee : null,
            'prio' => (int) $r->priority, 'active' => (bool) $r->active, 'last' => $r->last_run];
    }
    out(['recurring' => $recs]);

case 'save_recurring':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $freq = in_array($in['freq'] ?? '', ['daily', 'weekly', 'monthly', 'yearly'], true) ? $in['freq'] : 'monthly';
    Db::saveRecurring((int) ($in['id'] ?? 0), [
        'project_id' => (int) ($in['project'] ?? 0),
        'title' => mb_substr(trim($in['title'] ?? ''), 0, 200) ?: 'Χωρίς τίτλο',
        'descr' => cnp_clean_html($in['descr'] ?? '', 60000),   // rich-text
        'priority' => min(2, max(0, (int) ($in['prio'] ?? 0))),
        'assignee' => (int) ($in['assignee'] ?? 0) ?: null,
        'freq' => $freq, 'every' => max(1, (int) ($in['every'] ?? 1)),
        'next_run' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['next'] ?? '') ? $in['next'] : date('Y-m-d'),
        'due_days' => max(0, (int) ($in['dueDays'] ?? 0)),
        'active' => !empty($in['active']) ? 1 : 0]);
    out(['ok' => true]);

case 'del_recurring':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    Db::deleteRecurring((int) ($in['id'] ?? 0));
    out(['ok' => true]);

/* ================= TICKET INBOX ================= */
case 'tickets':
    $view = $_GET['view'] ?? 'open';
    $q = trim($_GET['q'] ?? '');
    $fArea = (int) ($_GET['area'] ?? 0);
    $fCause = (int) ($_GET['cause'] ?? 0);
    $tq = Capsule::table('tbltickets');
    if (($fArea || $fCause) && $view !== 'mine' && $view !== 'unassigned') {
        $tq->orderBy('lastreply', 'desc');   // φίλτρο κατηγορίας: όλα τα statuses
    } elseif ($view === 'closed') {
        $tq->where('status', 'Closed')->orderBy('lastreply', 'desc');
    } else {
        $tq->whereNotIn('status', ['Closed', 'Cancelled'])->orderBy('lastreply', 'desc');
        if ($view === 'mine') {
            $tq->where('flag', $adminId);
        } elseif ($view === 'unassigned') {
            $tq->where(function ($w) { $w->where('flag', 0)->orWhereNull('flag'); });
        }
    }
    if (!$FULL && $view !== 'unassigned') {
        $tq->where('flag', $adminId); // agents: δικά τους (+ αδέσποτα στο unassigned)
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $tq->where(function ($w) use ($like) {
            $w->where('title', 'like', $like)->orWhere('tid', 'like', $like)->orWhere('name', 'like', $like);
        });
    }
    if ($fArea || $fCause) {
        $tq->whereIn('tbltickets.id', function ($sub) use ($fArea, $fCause) {
            $sub->select('ticketid')->from('mod_cpm_ticket_class');
            if ($fArea) { $sub->where('area_id', $fArea); }
            if ($fCause) { $sub->where('cause_id', $fCause); }
        });
    }
    $rows = $tq->limit(100)->get(['id', 'tid', 'did', 'userid', 'name', 'title', 'status', 'urgency',
        'date', 'lastreply', 'flag', 'adminunread', 'clientunread']);
    $classMap = [];
    if (count($rows)) {
        foreach (Capsule::table('mod_cpm_ticket_class')->whereIn('ticketid', $rows->pluck('id')->all())->get() as $cl) {
            $classMap[(int) $cl->ticketid] = $cl;
        }
    }
    // SLA map
    $slaMap = [];
    try {
        if (count($rows) && Capsule::schema()->hasTable('mod_supportcontracts_tickets')) {
            foreach (Capsule::table('mod_supportcontracts_tickets')->whereIn('ticketid', $rows->pluck('id')->all())
                ->get(['ticketid', 'sla_due', 'first_response_at']) as $s) {
                $slaMap[(int) $s->ticketid] = $s;
            }
        }
    } catch (\Throwable $e) {
    }
    // «περιμένει απάντηση»: τελευταίο reply όχι από admin
    $list = [];
    foreach ($rows as $tk) {
        $lastAdmin = Capsule::table('tblticketreplies')->where('tid', $tk->id)->orderBy('id', 'desc')->value('admin');
        $waiting = ($lastAdmin === null || $lastAdmin === '');
        if ($view === 'waiting' && !$waiting) {
            continue;
        }
        $s = $slaMap[(int) $tk->id] ?? null;
        $list[] = ['id' => (int) $tk->id, 'tid' => $tk->tid, 'title' => $tk->title,
            'client' => $tk->userid ? clientLabel($tk->userid) : $tk->name,
            'status' => $tk->status, 'urgency' => $tk->urgency,
            'last' => $tk->lastreply, 'age' => (int) floor((time() - strtotime($tk->date)) / 86400),
            'flag' => (int) $tk->flag ?: null, 'unread' => (bool) $tk->adminunread,
            'waiting' => $waiting,
            'slaDue' => ($s && $s->sla_due && !$s->first_response_at) ? $s->sla_due : null,
            'area' => isset($classMap[(int) $tk->id]) ? (int) $classMap[(int) $tk->id]->area_id ?: null : null,
            'cause' => isset($classMap[(int) $tk->id]) ? (int) $classMap[(int) $tk->id]->cause_id ?: null : null];
    }
    out(['tickets' => $list, 'cats' => cnp_ticket_cats()]);

case 'ticket':
    $tid = (int) ($_GET['id'] ?? 0);
    $tk = Capsule::table('tbltickets')->where('id', $tid)->first();
    if (!$tk) {
        fail('ticket', 404);
    }
    if (!$FULL && (int) $tk->flag !== $adminId && (int) $tk->flag !== 0) {
        fail('ticket', 403);
    }
    $attList = function ($str, $rid) {
        $out = [];
        foreach (array_values(array_filter(explode('|', (string) $str))) as $i => $f) {
            $out[] = ['i' => $i, 'rid' => $rid,
                'name' => preg_replace('/^\\d+_/', '', $f)];
        }
        return $out;
    };
    $conv = [[
        'by' => $tk->name ?: clientLabel($tk->userid), 'admin' => false,
        'at' => $tk->date, 'body' => $tk->message, 'att' => $attList($tk->attachment ?? '', 0),
    ]];
    foreach (Capsule::table('tblticketreplies')->where('tid', $tid)->orderBy('id')->get() as $r) {
        $conv[] = ['by' => $r->admin ?: ($r->name ?: clientLabel($r->userid)),
            'admin' => $r->admin !== '' && $r->admin !== null, 'at' => $r->date, 'body' => $r->message,
            'att' => $attList($r->attachment ?? '', (int) $r->id)];
    }
    // εσωτερική συνομιλία (linked task comments)
    $task = Db::taskForTicket($tid);
    $notes = [];
    if ($task) {
        foreach (Db::comments($task->id) as $c) {
            $notes[] = ['by' => Db::adminName($c->admin_id), 'byId' => (int) $c->admin_id,
                'to' => $c->to_admin !== null ? (int) $c->to_admin : null,
                'at' => $c->created_at, 'body' => $c->comment];
        }
    }
    $sla = null;
    try {
        if (Capsule::schema()->hasTable('mod_supportcontracts_tickets')) {
            $s = Capsule::table('mod_supportcontracts_tickets')->where('ticketid', $tid)->first(['sla_due', 'first_response_at']);
            if ($s && $s->sla_due && !$s->first_response_at) {
                $sla = $s->sla_due;
            }
        }
    } catch (\Throwable $e) {
    }
    // 💡 αυτόματες προτάσεις: KB λύσεις + παρόμοια tickets που λύθηκαν
    $suggest = ['kb' => [], 'similar' => []];
    try {
        $tw0 = cnp_words($tk->title . ' ' . mb_substr($tk->message, 0, 600));
        if ($tw0) {
            foreach (Capsule::table('mod_cpm_kb')->get() as $k9) {
                $sc = cnp_overlap($tw0, cnp_words($k9->title . ' ' . $k9->keywords . ' ' . $k9->tags));
                if ($sc > 0) {
                    $suggest['kb'][] = ['id' => (int) $k9->id, 'title' => $k9->title,
                        'solution' => $k9->solution, 'score' => $sc];
                }
            }
            usort($suggest['kb'], function ($a, $b) { return $b['score'] <=> $a['score']; });
            $suggest['kb'] = array_slice($suggest['kb'], 0, 3);
            foreach (Capsule::table('tbltickets')->where('id', '!=', $tid)
                ->where('status', 'Closed')->orderBy('lastreply', 'desc')
                ->limit(300)->get(['id', 'tid', 'title', 'userid', 'name', 'lastreply']) as $t9) {
                $sc = cnp_overlap($tw0, cnp_words($t9->title));
                if ($sc > 0) {
                    $suggest['similar'][] = ['id' => (int) $t9->id, 'tid' => $t9->tid, 'title' => $t9->title,
                        'client' => $t9->userid ? clientLabel($t9->userid) : $t9->name,
                        'last' => substr($t9->lastreply, 0, 10), 'score' => $sc];
                }
            }
            usort($suggest['similar'], function ($a, $b) { return $b['score'] <=> $a['score']; });
            $suggest['similar'] = array_slice($suggest['similar'], 0, 3);
        }
    } catch (\Throwable $e) {
    }
    // 🎟 χρήση/όριο tickets της ομάδας του πελάτη (τρέχων μήνας)
    $tq9 = null;
    if ($tk->userid) {
        try {
            $pk9 = (int) Capsule::table('mod_cpm_client_package')->where('clientid', $tk->userid)->value('package_id');
            $qr9 = $pk9 ? Capsule::table('mod_cpm_support_packages')->where('id', $pk9)->first() : null;
            if ($qr9 && (int) $qr9->t_month > 0) {
                $m0 = date('Y-m-01 00:00:00');
                $used9 = Capsule::table('tbltickets')->where('userid', $tk->userid)->where('date', '>=', $m0)->count();
                $ph9 = Capsule::table('mod_cpm_ticket_usage')->where('userid', $tk->userid)
                    ->where('channel', 'phone')->where('created_at', '>=', $m0)->count();
                $em9 = Capsule::table('mod_cpm_ticket_usage')->where('userid', $tk->userid)
                    ->where('channel', 'email')->where('created_at', '>=', $m0)->count();
                $tq9 = ['used' => $used9, 'quota' => (int) $qr9->t_month, 'package' => $qr9->name,
                    'email' => ['u' => $em9, 'q' => (int) $qr9->email_month],
                    'phone' => ['u' => $ph9, 'q' => (int) $qr9->phone_month],
                    'over' => $used9 > (int) $qr9->t_month];
            }
        } catch (\Throwable $e) {
        }
    }
    $statuses = Capsule::table('tblticketstatuses')->orderBy('sortorder')->pluck('title')->all();

    /* ── Συμφραζόμενα πελάτη ──────────────────────────────────────────────
       Χωρίς αυτά ο τεχνικός βλέπει μόνο επωνυμία και κείμενο: δεν ξέρει σε
       ποια από τις υπηρεσίες του πελάτη αναφέρεται, ούτε πώς να τον βρει.
       Τα μαζεύουμε εδώ ώστε να μη χρειάζεται να ανοίξει το WHMCS παράλληλα. */
    $ctx = null;
    if ((int) $tk->userid) {
        $cl = Capsule::table('tblclients')->where('id', (int) $tk->userid)->first();
        if ($cl) {
            // Το πεδίο service κρατάει «S<id>» για υπηρεσία, «D<id>» για domain.
            $relId = 0;
            $relKind = '';
            if (preg_match('/^([SD])(\d+)$/', (string) $tk->service, $m9)) {
                $relKind = $m9[1];
                $relId = (int) $m9[2];
            }

            $svc = [];
            foreach (Capsule::table('tblhosting')->where('userid', $cl->id)
                         ->orderByRaw("FIELD(domainstatus,'Active','Suspended','Pending','Terminated','Cancelled','Fraud')")
                         ->orderBy('id', 'desc')->get() as $h9) {
                $svc[] = [
                    'id'      => (int) $h9->id,
                    'name'    => (string) (Capsule::table('tblproducts')->where('id', $h9->packageid)->value('name') ?: '—'),
                    'domain'  => (string) $h9->domain,
                    'status'  => (string) $h9->domainstatus,
                    'nextdue' => $h9->nextduedate && $h9->nextduedate !== '0000-00-00' ? $h9->nextduedate : null,
                    'ip'      => (string) $h9->dedicatedip,
                    'related' => ($relKind === 'S' && (int) $h9->id === $relId),
                ];
            }

            $dom = [];
            foreach (Capsule::table('tbldomains')->where('userid', $cl->id)
                         ->orderBy('id', 'desc')->limit(20)->get() as $d9) {
                $dom[] = ['id' => (int) $d9->id, 'domain' => (string) $d9->domain,
                    'status' => (string) $d9->status, 'expiry' => $d9->expirydate,
                    'related' => ($relKind === 'D' && (int) $d9->id === $relId)];
            }

            $ctx = [
                'id'       => (int) $cl->id,
                'name'     => trim($cl->firstname . ' ' . $cl->lastname),
                'company'  => (string) $cl->companyname,
                'email'    => (string) $cl->email,
                'phone'    => (string) $cl->phonenumber,
                'country'  => (string) $cl->country,
                'city'     => (string) $cl->city,
                'status'   => (string) $cl->status,
                'since'    => substr((string) $cl->datecreated, 0, 10),
                'ip'       => (string) ($tk->ipaddress ?? ''),
                'services' => $svc,
                'domains'  => $dom,
                // Ο επικοινωνών μπορεί να είναι υπο-επαφή, όχι ο κάτοχος.
                'contact'  => (int) $tk->contactid
                    ? (function ($cid) {
                        $c9 = Capsule::table('tblcontacts')->where('id', $cid)->first();
                        return $c9 ? ['name' => trim($c9->firstname . ' ' . $c9->lastname), 'email' => $c9->email] : null;
                    })((int) $tk->contactid)
                    : null,
                'openTickets' => (int) Capsule::table('tbltickets')->where('userid', $cl->id)
                    ->whereNotIn('status', ['Closed'])->count(),
                'unpaid' => (float) Capsule::table('tblinvoices')->where('userid', $cl->id)
                    ->where('status', 'Unpaid')->sum('total'),
            ];
        }
    } elseif ($tk->email || $tk->name) {
        // Ticket από μη εγγεγραμμένο — ό,τι ξέρουμε είναι στο ίδιο το ticket.
        $ctx = ['id' => null, 'name' => (string) $tk->name, 'email' => (string) $tk->email,
            'ip' => (string) ($tk->ipaddress ?? ''), 'guest' => true,
            'services' => [], 'domains' => []];
    }

    out(['ctx' => $ctx,
        'ticket' => ['id' => $tid, 'tid' => $tk->tid, 'title' => $tk->title,
            'client' => $tk->userid ? clientLabel($tk->userid) : $tk->name, 'clientId' => (int) $tk->userid ?: null,
            'email' => $tk->email, 'status' => $tk->status, 'urgency' => $tk->urgency,
            'flag' => (int) $tk->flag ?: null, 'dept' => (int) $tk->did, 'slaDue' => $sla],
        'suggest' => $suggest, 'quota' => $tq9,
        'class' => (function () use ($tid) {
            $cl = Capsule::table('mod_cpm_ticket_class')->where('ticketid', $tid)->first();
            return ['area' => $cl && $cl->area_id ? (int) $cl->area_id : null,
                'cause' => $cl && $cl->cause_id ? (int) $cl->cause_id : null,
                'note' => $cl->note ?? null,
                'by' => $cl && $cl->classified_by ? Db::adminName($cl->classified_by) : null];
        })(),
        'cats' => cnp_ticket_cats(),
        'conv' => $conv, 'notes' => $notes,
        'task' => $task ? (int) $task->id : null,
        'statuses' => $statuses]);

case 'ticket_reply':
    if (!empty($_FILES)) {          // multipart (με συνημμένα) → πεδία από $_POST
        $in = $_POST;
    }
    $tid = (int) ($in['ticket'] ?? 0);
    $msg = trim($in['body'] ?? '');
    $tk = Capsule::table('tbltickets')->where('id', $tid)->first(['flag', 'tid']);
    if (!$tk || $msg === '') {
        fail('input');
    }
    if (!$FULL && (int) $tk->flag !== $adminId && (int) $tk->flag !== 0) {
        fail('ticket', 403);
    }
    if (!cnp_can_reply_clients($adminId, $FULL)) {
        fail('Μόνο ο επικεφαλής ομάδας ή διαχειριστής απαντά στον πελάτη — χρησιμοποίησε εσωτερική σημείωση', 403);
    }
    // συνημμένα: έλεγχος ΠΡΙΝ σταλεί η απάντηση, αποθήκευση WHMCS-style στο attachmentsnew
    $attNames = [];
    $attDir = __DIR__ . '/../attachmentsnew';
    if (!empty($_FILES['files'])) {
        $ff = $_FILES['files'];
        $names = is_array($ff['name']) ? $ff['name'] : [$ff['name']];
        foreach ($names as $k => $nm) {
            $err = is_array($ff['error']) ? $ff['error'][$k] : $ff['error'];
            $sz = is_array($ff['size']) ? $ff['size'][$k] : $ff['size'];
            if ($err !== UPLOAD_ERR_OK) {
                fail('upload');
            }
            if ($sz > 20 * 1024 * 1024) {
                fail('Μέγιστο 20MB ανά αρχείο');
            }
            $ext = strtolower(pathinfo($nm, PATHINFO_EXTENSION));
            if (in_array($ext, ['php', 'phtml', 'phar', 'cgi', 'sh', 'exe', 'htaccess'], true)) {
                fail('Μη επιτρεπτός τύπος αρχείου');
            }
        }
        foreach ($names as $k => $nm) {
            $tmp = is_array($ff['tmp_name']) ? $ff['tmp_name'][$k] : $ff['tmp_name'];
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $nm) ?: 'file';
            $stored = random_int(100000, 999999) . '_' . mb_substr($safe, 0, 160);
            if (!move_uploaded_file($tmp, $attDir . '/' . $stored)) {
                fail('write');
            }
            $attNames[] = $stored;
        }
    }
    $uname = Capsule::table('tbladmins')->where('id', $adminId)->value('username');
    $r = localAPI('AddTicketReply', ['ticketid' => $tid, 'message' => $msg, 'adminusername' => $uname]
        + (!empty($in['status']) ? ['status' => $in['status']] : []), $uname);
    if (($r['result'] ?? '') !== 'success') {
        foreach ($attNames as $s) {     // μην αφήσεις ορφανά στο δίσκο
            @unlink($attDir . '/' . $s);
        }
        fail($r['message'] ?? 'reply failed');
    }
    if ($attNames) {
        $rid = (int) Capsule::table('tblticketreplies')->where('tid', $tid)->max('id');
        if ($rid) {
            Capsule::table('tblticketreplies')->where('id', $rid)->update(['attachment' => implode('|', $attNames)]);
        }
    }
    out(['ok' => true]);

case 'profile':                        // το προφίλ ΜΟΥ (κάθε χρήστης)
    $me8 = Capsule::table('tbladmins')->where('id', $adminId)
        ->first(['username', 'firstname', 'lastname', 'email', 'roleid', 'created_at']);
    $role8 = Capsule::table('tbladminroles')->where('id', (int) $me8->roleid)->value('name');
    $teams8 = Capsule::table('mod_cpm_team_members as tm')
        ->join('mod_cpm_teams as t', 't.id', '=', 'tm.team_id')
        ->where('tm.admin_id', $adminId)
        ->get(['t.name', 't.color', 'tm.role_title', 'tm.is_leader'])->all();
    $projs8 = Db::visibleProjectIds($adminId);
    $projList8 = Capsule::table('mod_cpm_projects')->where('status', 'active')
        ->when($projs8 !== null, function ($q) use ($projs8) { $q->whereIn('id', $projs8 ?: [0]); })
        ->orderBy('name')->get(['id', 'name', 'color'])->all();
    out(['profile' => [
        'username' => $me8->username, 'first' => $me8->firstname, 'last' => $me8->lastname,
        'email' => $me8->email, 'role' => $role8 ?: ('#' . $me8->roleid), 'full' => $FULL,
        'since' => substr((string) $me8->created_at, 0, 10)],
        'teams' => array_map(function ($t) { return ['name' => $t->name, 'color' => $t->color,
            'role' => $t->role_title, 'leader' => (bool) $t->is_leader]; }, $teams8),
        'projects' => array_map(function ($p) { return ['id' => (int) $p->id, 'name' => $p->name,
            'color' => $p->color]; }, $projList8),
        'allProjects' => $projs8 === null,
        'prefs' => ['notify_email' => Db::pref($adminId, 'notify_email', 'on'),
            'digest' => Db::pref($adminId, 'digest', 'on'),
            'lang' => Db::pref($adminId, 'lang', 'el') === 'en' ? 'en' : 'el',
            'meet_link' => Db::pref($adminId, 'meet_link', '')]]);

case 'profile_save':                   // στοιχεία μου (self μόνο)
    $email8 = trim($in['email'] ?? '');
    if ($email8 !== '' && !filter_var($email8, FILTER_VALIDATE_EMAIL)) {
        fail('Άκυρο email');
    }
    Capsule::table('tbladmins')->where('id', $adminId)->update([
        'firstname' => mb_substr(trim($in['first'] ?? ''), 0, 60),
        'lastname' => mb_substr(trim($in['last'] ?? ''), 0, 60),
        'email' => $email8, 'updated_at' => date('Y-m-d H:i:s')]);
    if (array_key_exists('meetLink', $in)) {
        Db::setPref($adminId, 'meet_link', mb_substr(trim((string) $in['meetLink']), 0, 190));
    }
    out(['ok' => true]);

case 'profile_pass':                   // αλλαγή δικού μου κωδικού με επιβεβαίωση τρέχοντος
    $curHash = Capsule::table('tbladmins')->where('id', $adminId)->value('password');
    if (!password_verify((string) ($in['current'] ?? ''), (string) $curHash)) {
        fail('Λάθος τρέχων κωδικός');
    }
    $np = (string) ($in['new'] ?? '');
    if (strlen($np) < 8) {
        fail('Ο νέος κωδικός θέλει τουλάχιστον 8 χαρακτήρες');
    }
    if ($np !== ($in['confirm'] ?? '')) {
        fail('Οι δύο κωδικοί δεν ταιριάζουν');
    }
    Capsule::table('tbladmins')->where('id', $adminId)
        ->update(['password' => password_hash($np, PASSWORD_DEFAULT),
            'passwordhash' => password_hash($np, PASSWORD_DEFAULT), 'updated_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true]);

case 'profile_pref':                   // προσωπικές προτιμήσεις ειδοποιήσεων
    $key8 = (string) ($in['key'] ?? '');
    if ($key8 === 'lang') {                       // γλώσσα διεπαφής ανά χρήστη (el|en)
        Db::setPref($adminId, 'lang', ($in['value'] ?? '') === 'en' ? 'en' : 'el');
        out(['ok' => true]);
    }
    if (!in_array($key8, ['notify_email', 'digest'], true)) {
        fail('pref');
    }
    Db::setPref($adminId, $key8, ($in['value'] ?? '') === 'on' ? 'on' : 'off');
    out(['ok' => true]);

case 'triage':                          // 🎯 Πλάνο ημέρας — πρόταση tickets (managers)
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $now = time();
    $slaBy = [];
    try {
        foreach (Capsule::table('mod_supportcontracts_tickets')->whereNotNull('sla_due')->get() as $st9) {
            $slaBy[(int) $st9->ticketid] = $st9;
        }
    } catch (\Throwable $e) {
    }
    $prioBy = [];
    try {
        foreach (Capsule::table('mod_supportcontracts_clients')->where('enabled', 1)->get(['userid', 'priority']) as $pc) {
            $prioBy[(int) $pc->userid] = $pc->priority;
        }
    } catch (\Throwable $e) {
    }
    $rows9 = Capsule::table('tbltickets')
        ->whereNotIn('status', ['Closed', 'Cancelled'])
        ->get(['id', 'tid', 'title', 'userid', 'name', 'urgency', 'status', 'date', 'lastreply', 'flag']);
    $ids9 = array_map(function ($r) { return (int) $r->id; }, $rows9->all());
    // ποιος μίλησε τελευταίος; (admin κενό = πελάτης)
    $lastAdmin = [];
    if ($ids9) {
        foreach (Capsule::table('tblticketreplies')->whereIn('tid', $ids9)
            ->orderBy('id')->get(['tid', 'admin']) as $r9) {
            $lastAdmin[(int) $r9->tid] = trim((string) $r9->admin) !== '';
        }
    }
    $list9 = [];
    foreach ($rows9 as $t9) {
        $why = [];
        // 1) κρισιμότητα ticket
        $u = ['High' => 30, 'Medium' => 15, 'Low' => 5][$t9->urgency] ?? 10;
        $why['urgency'] = $u;
        // 2) περιμένει απάντησή μας + πόσο
        $waiting = !array_key_exists((int) $t9->id, $lastAdmin) ? true : !$lastAdmin[(int) $t9->id];
        $wh = max(0, ($now - strtotime($t9->lastreply)) / 3600);
        $w = $waiting ? min(30, round($wh * 1.5, 1)) : 0;
        $why['wait'] = $w;
        // 3) SLA πελάτη
        $sv = 0;
        $slaDue = null;
        if (isset($slaBy[(int) $t9->id])) {
            $st9 = $slaBy[(int) $t9->id];
            $slaDue = $st9->sla_due;
            if (!$st9->first_response_at) {
                $left = (strtotime($st9->sla_due) - $now) / 3600;
                $sv = $left < 0 ? 40 : ($left < 2 ? 30 : ($left < 8 ? 15 : 5));
            }
        }
        $why['sla'] = $sv;
        // 4) προτεραιότητα συμβολαίου πελάτη
        $cp = ['High' => 15, 'Medium' => 7][$prioBy[(int) $t9->userid] ?? ''] ?? 0;
        $why['contract'] = $cp;
        // 5) παλαιότητα
        $ageD = max(0, ($now - strtotime($t9->date)) / 86400);
        $why['age'] = min(10, round($ageD / 2, 1));
        $score = array_sum($why);
        $list9[] = ['id' => (int) $t9->id, 'tid' => $t9->tid, 'title' => $t9->title,
            'client' => $t9->userid ? clientLabel($t9->userid) : $t9->name,
            'urgency' => $t9->urgency, 'status' => $t9->status,
            'flag' => $t9->flag ? (int) $t9->flag : null,
            'waiting' => $waiting, 'waitH' => round($wh, 1),
            'slaDue' => $slaDue, 'contractPrio' => $prioBy[(int) $t9->userid] ?? null,
            'ageDays' => round($ageD, 1),
            'score' => round($score, 1), 'why' => $why];
    }
    usort($list9, function ($a, $b) { return $b['score'] <=> $a['score']; });
    // 💡 πρόταση ανάθεσης: ποιος έχει λύσει παρόμοια (top 20, μόνο ανανάθετα)
    $nameToId = [];
    foreach (Capsule::table('tbladmins')->where('disabled', 0)->get(['id', 'firstname', 'lastname']) as $a9) {
        $nameToId[trim($a9->firstname . ' ' . $a9->lastname)] = (int) $a9->id;
    }
    $closed9 = Capsule::table('tbltickets')->where('status', 'Closed')
        ->orderBy('lastreply', 'desc')->limit(300)->get(['id', 'title'])->all();
    foreach (array_slice($list9, 0, 20) as $k9 => $t9) {
        if ($t9['flag']) {
            continue;
        }
        $tw9 = cnp_words($t9['title']);
        $solvers = [];
        foreach ($closed9 as $c9) {
            if (cnp_overlap($tw9, cnp_words($c9->title)) > 0) {
                $adm = Capsule::table('tblticketreplies')->where('tid', $c9->id)
                    ->where('admin', '!=', '')->orderBy('id', 'desc')->value('admin');
                if ($adm && isset($nameToId[trim($adm)])) {
                    $solvers[trim($adm)] = ($solvers[trim($adm)] ?? 0) + 1;
                }
            }
        }
        if ($solvers) {
            arsort($solvers);
            $top9 = array_key_first($solvers);
            $list9[$k9]['suggestAssignee'] = ['id' => $nameToId[$top9], 'name' => $top9, 'solved' => $solvers[$top9]];
        }
    }
    $sum9 = ['open' => count($list9),
        'waiting' => count(array_filter($list9, function ($x) { return $x['waiting']; })),
        'slaRisk' => count(array_filter($list9, function ($x) { return $x['why']['sla'] >= 15; })),
        'high' => count(array_filter($list9, function ($x) { return $x['urgency'] === 'High'; })),
        'unassigned' => count(array_filter($list9, function ($x) { return !$x['flag']; }))];
    out(['plan' => array_slice($list9, 0, 20), 'summary' => $sum9]);

/* ============ 📚 ΒΑΣΗ ΓΝΩΣΗΣ ============ */
case 'ksearch':                         // «το έχω ξαναλύσει;» — ψάξιμο σε KB + ιστορικό tickets
    $q9 = trim($_GET['q'] ?? '');
    if (mb_strlen($q9) < 3) {
        fail('q');
    }
    $qw = cnp_words($q9);
    if (!$qw) {
        fail('q');
    }
    // KB — ταίριασμα λέξεων + fallback σε υποσυμβολοσειρά (ώστε το search «να πιάνει τα πάντα»)
    $qRaw = mb_strtolower($q9, 'UTF-8');
    $kbHits = [];
    foreach (Capsule::table('mod_cpm_kb')->get() as $k9) {
        $hay = $k9->title . ' ' . $k9->keywords . ' ' . $k9->tags . ' ' . $k9->solution;
        $kw = cnp_words($k9->title . ' ' . $k9->keywords . ' ' . $k9->tags . ' ' . mb_substr($k9->solution, 0, 2000));
        $sc = cnp_overlap($qw, $kw);
        if ($sc === 0 && mb_strpos(mb_strtolower($hay, 'UTF-8'), $qRaw) !== false) {
            $sc = 1;                                   // δεν έπιασε λέξη, αλλά υπάρχει αυτούσιο το κείμενο
        }
        if ($sc > 0) {
            $kbHits[] = ['id' => (int) $k9->id, 'title' => $k9->title, 'tags' => $k9->tags,
                'keywords' => $k9->keywords, 'solution' => $k9->solution, 'uses' => (int) $k9->uses,
                'areaId' => (int) $k9->area_id,
                'relAreas' => array_values(array_filter(array_map('intval', explode(',', (string) $k9->rel_areas)))),
                'by' => $k9->created_by ? Db::adminName($k9->created_by) : null,
                'at' => substr((string) $k9->created_at, 0, 10), 'score' => $sc];
        }
    }
    usort($kbHits, function ($a, $b) { return $b['score'] <=> $a['score']; });
    // ιστορικό tickets: πρόφιλτρο SQL με LIKE στις 2 πιο σπάνιες λέξεις, μετά scoring
    $tHits = [];
    $cand = Capsule::table('tbltickets');
    $cand->where(function ($qq) use ($qw) {
        foreach (array_slice($qw, 0, 6) as $w) {
            $qq->orWhere('title', 'like', '%' . $w . '%')
               ->orWhere('message', 'like', '%' . $w . '%')
               ->orWhereExists(function ($sub) use ($w) {
                   $sub->selectRaw('1')->from('tblticketreplies')
                       ->whereColumn('tblticketreplies.tid', 'tbltickets.id')
                       ->where('tblticketreplies.message', 'like', '%' . $w . '%');
               });
        }
    });
    foreach ($cand->orderBy('lastreply', 'desc')->limit(200)->get(['id', 'tid', 'title', 'userid', 'name', 'status', 'lastreply', 'message']) as $t9) {
        $tw = cnp_words($t9->title . ' ' . mb_substr($t9->message, 0, 600));
        $sc = cnp_overlap($qw, $tw);
        // bonus: λύθηκε (Closed) → πιο χρήσιμο ως προηγούμενο
        if ($sc > 0) {
            $tHits[] = ['id' => (int) $t9->id, 'tid' => $t9->tid, 'title' => $t9->title,
                'client' => $t9->userid ? clientLabel($t9->userid) : $t9->name,
                'status' => $t9->status, 'last' => substr($t9->lastreply, 0, 10),
                'score' => $sc + ($t9->status === 'Closed' ? 1 : 0)];
        }
    }
    usort($tHits, function ($a, $b) { return $b['score'] <=> $a['score']; });
    out(['kb' => array_slice($kbHits, 0, 10), 'tickets' => array_slice($tHits, 0, 15)]);

case 'kb_draft':                        // AI προσχέδιο KB από κλεισμένο ticket
    $tid3 = (int) ($in['ticket'] ?? 0);
    $tk3 = Capsule::table('tbltickets')->where('id', $tid3)->first();
    if (!$tk3) {
        fail('ticket', 404);
    }
    $key = (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', 'ai_api_key')->value('value') ?: '');
    if ($key === '') {   // χωρίς AI: βασικό προσχέδιο
        out(['ok' => true, 'draft' => ['title' => $tk3->title, 'keywords' => implode(' ', array_slice(cnp_words($tk3->title), 0, 8)), 'solution' => '']]);
    }
    $convTxt = "ΠΕΛΑΤΗΣ: " . mb_substr($tk3->message, 0, 2500) . "\n";
    foreach (Capsule::table('tblticketreplies')->where('tid', $tid3)->orderBy('id')->limit(20)->get() as $r) {
        $who = ($r->admin !== '' && $r->admin !== null) ? 'ΟΜΑΔΑ' : 'ΠΕΛΑΤΗΣ';
        $convTxt .= $who . ': ' . mb_substr($r->message, 0, 1500) . "\n";
    }
    $prompt = "Από την παρακάτω συνομιλία ticket υποστήριξης, φτιάξε εγγραφή για βάση γνώσης στα ελληνικά. Απάντησε ΜΟΝΟ με JSON χωρίς markdown: {\"title\": \"σύντομος τίτλος προβλήματος\", \"keywords\": \"λέξεις-κλειδιά χωρισμένες με κόμμα\", \"solution\": \"η λύση βήμα-βήμα όπως εφαρμόστηκε\"}. Αν η συνομιλία δεν περιέχει σαφή λύση, βάλε στο solution ό,τι έγινε.\n\nΘΕΜΑ: {$tk3->title}\n\nΣΥΝΟΜΙΛΙΑ:\n$convTxt";
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => json_encode(['model' => 'claude-haiku-4-5-20251001', 'max_tokens' => 1200,
            'messages' => [['role' => 'user', 'content' => $prompt]]]),
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $j = json_decode((string) $resp, true);
    $txt = $j['content'][0]['text'] ?? '';
    $draft = json_decode(trim(preg_replace('/^```json|```$/m', '', trim($txt))), true);
    if (!is_array($draft) || empty($draft['title'])) {
        $draft = ['title' => $tk3->title, 'keywords' => implode(' ', array_slice(cnp_words($tk3->title), 0, 8)), 'solution' => $txt];
    }
    out(['ok' => true, 'draft' => ['title' => mb_substr($draft['title'], 0, 255),
        'keywords' => mb_substr($draft['keywords'] ?? '', 0, 500),
        'solution' => mb_substr($draft['solution'] ?? '', 0, 60000)]]);

case 'mynext':                          // ▶ «Τι δουλεύω τώρα» — προσωπικό top-3 (όλοι)
    $now = time();
    $slaBy = [];
    try {
        foreach (Capsule::table('mod_supportcontracts_tickets')->whereNotNull('sla_due')->get() as $st9) {
            $slaBy[(int) $st9->ticketid] = $st9;
        }
    } catch (\Throwable $e) {
    }
    $rows9 = Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])
        ->where(function ($q9) use ($adminId) { $q9->where('flag', $adminId)->orWhere('flag', 0); })
        ->get(['id', 'tid', 'title', 'userid', 'name', 'urgency', 'date', 'lastreply', 'flag']);
    $ids9 = array_map(function ($r) { return (int) $r->id; }, $rows9->all());
    $lastAdmin = [];
    if ($ids9) {
        foreach (Capsule::table('tblticketreplies')->whereIn('tid', $ids9)->orderBy('id')->get(['tid', 'admin']) as $r9) {
            $lastAdmin[(int) $r9->tid] = trim((string) $r9->admin) !== '';
        }
    }
    $list9 = [];
    foreach ($rows9 as $t9) {
        $u = ['High' => 30, 'Medium' => 15, 'Low' => 5][$t9->urgency] ?? 10;
        $waiting = !($lastAdmin[(int) $t9->id] ?? false);
        $w = $waiting ? min(30, round(max(0, ($now - strtotime($t9->lastreply)) / 3600) * 1.5, 1)) : 0;
        $sv = 0;
        if (isset($slaBy[(int) $t9->id]) && !$slaBy[(int) $t9->id]->first_response_at) {
            $left = (strtotime($slaBy[(int) $t9->id]->sla_due) - $now) / 3600;
            $sv = $left < 0 ? 40 : ($left < 2 ? 30 : ($left < 8 ? 15 : 5));
        }
        $score = $u + $w + $sv + ((int) $t9->flag === $adminId ? 5 : 0);   // δικά μου ελαφρώς μπροστά
        $list9[] = ['id' => (int) $t9->id, 'tid' => $t9->tid, 'title' => $t9->title,
            'client' => $t9->userid ? clientLabel($t9->userid) : $t9->name,
            'mine' => (int) $t9->flag === $adminId, 'waiting' => $waiting,
            'score' => round($score, 1)];
    }
    usort($list9, function ($a, $b) { return $b['score'] <=> $a['score']; });
    out(['next' => array_slice($list9, 0, 3)]);

case 'recurrent':                       // 🔁 επαναλαμβανόμενα προβλήματα 90 ημερών (full)
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $since = date('Y-m-d', strtotime('-90 days'));
    $sigs = [];
    foreach (Capsule::table('tbltickets')->where('date', '>=', $since)
        ->get(['id', 'tid', 'title', 'userid', 'name', 'status', 'date']) as $t9) {
        $sigs[] = ['t' => $t9, 'w' => cnp_words($t9->title, 12)];
    }
    $used = [];
    $clusters = [];
    foreach ($sigs as $i => $a) {
        if (isset($used[$i]) || !$a['w']) {
            continue;
        }
        $grp = [$a];
        $used[$i] = true;
        foreach ($sigs as $j => $b) {
            if ($j <= $i || isset($used[$j])) {
                continue;
            }
            if (cnp_overlap($a['w'], $b['w']) >= 2) {
                $grp[] = $b;
                $used[$j] = true;
            }
        }
        if (count($grp) >= 3) {   // «επαναλαμβανόμενο» = 3+ φορές στο τρίμηνο
            $cl = [];
            foreach ($grp as $g) {
                $cl[] = ['id' => (int) $g['t']->id, 'tid' => $g['t']->tid, 'title' => $g['t']->title,
                    'client' => $g['t']->userid ? clientLabel($g['t']->userid) : $g['t']->name,
                    'date' => substr($g['t']->date, 0, 10), 'status' => $g['t']->status];
            }
            $clusters[] = ['label' => $grp[0]['t']->title, 'count' => count($grp),
                'clients' => array_values(array_unique(array_map(function ($x) { return $x['client']; }, $cl))),
                'tickets' => $cl];
        }
    }
    usort($clusters, function ($a, $b) { return $b['count'] <=> $a['count']; });
    out(['clusters' => array_slice($clusters, 0, 10)]);

case 'client_health':                   // ❤️ υγεία πελατών — ποιοι «καίγονται» (full)
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $since = date('Y-m-d', strtotime('-90 days'));
    $stats = [];
    foreach (Capsule::table('tbltickets')->where('date', '>=', $since)->where('userid', '>', 0)
        ->get(['userid', 'status']) as $t9) {
        $stats[(int) $t9->userid]['tickets'] = ($stats[(int) $t9->userid]['tickets'] ?? 0) + 1;
        if (!in_array($t9->status, ['Closed', 'Cancelled'], true)) {
            $stats[(int) $t9->userid]['open'] = ($stats[(int) $t9->userid]['open'] ?? 0) + 1;
        }
    }
    try {
        foreach (Capsule::table('mod_supportcontracts_tickets as st')
            ->join('tbltickets as t', 't.id', '=', 'st.ticketid')
            ->where('t.date', '>=', $since)->where('st.sla_met', 0)->whereNotNull('st.first_response_at')
            ->get(['t.userid']) as $b9) {
            $stats[(int) $b9->userid]['breach'] = ($stats[(int) $b9->userid]['breach'] ?? 0) + 1;
        }
    } catch (\Throwable $e) {
    }
    foreach (Capsule::table('tblinvoices')->where('status', 'Unpaid')
        ->groupBy('userid')->get(['userid', Capsule::raw('SUM(total) s'), Capsule::raw('COUNT(*) c')]) as $u9) {
        $stats[(int) $u9->userid]['owed'] = (float) $u9->s;
        $stats[(int) $u9->userid]['owedCnt'] = (int) $u9->c;
    }
    $outH = [];
    foreach ($stats as $cid9 => $x) {
        $score = 100
            - min(30, ($x['tickets'] ?? 0) * 2)          // πολλά tickets = τριβή
            - min(25, ($x['breach'] ?? 0) * 8)           // σπασμένα SLA
            - min(25, ($x['owed'] ?? 0) > 0 ? 10 + min(15, ($x['owedCnt'] ?? 0) * 5) : 0)
            - min(20, ($x['open'] ?? 0) * 5);            // ανοιχτές εκκρεμότητες τώρα
        $outH[] = ['client' => $cid9, 'name' => clientLabel($cid9), 'score' => max(0, (int) $score),
            'tickets90' => $x['tickets'] ?? 0, 'open' => $x['open'] ?? 0,
            'slaBreaches' => $x['breach'] ?? 0, 'owed' => round($x['owed'] ?? 0, 2)];
    }
    usort($outH, function ($a, $b) { return $a['score'] <=> $b['score']; });
    out(['clients' => array_slice($outH, 0, 15)]);

case 'kb_list':
    /* ΧΩΡΙΣ το πλήρες `solution`: με 1.000+ άρθρα το payload έφτανε 22MB και ο browser
       κόλλαγε. Στέλνουμε μόνο μετα-δεδομένα + απόσπασμα· το πλήρες κείμενο έρχεται με
       kb_get όταν ανοίξει το άρθρο. */
    $items9 = array_map(function ($k9) {
        // κενό ΠΡΙΝ το strip_tags — αλλιώς «</td><td>» κολλάει τις λέξεις («ΚατηγορίαΕρώτηση»)
        $plain9 = preg_replace('/<[^>]+>/', ' ', (string) $k9->solution);
        $plain9 = trim(preg_replace('/\s+/u', ' ',
            html_entity_decode($plain9, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return ['id' => (int) $k9->id, 'title' => $k9->title, 'keywords' => $k9->keywords,
            'tags' => $k9->tags, 'excerpt' => mb_substr($plain9, 0, 400), 'uses' => (int) $k9->uses,
            'areaId' => (int) $k9->area_id,
            'relAreas' => array_values(array_filter(array_map('intval', explode(',', (string) $k9->rel_areas)))),
            'by' => $k9->created_by ? Db::adminName($k9->created_by) : null,
            'byId' => (int) $k9->created_by, 'src' => (string) $k9->source_url,
            'at' => substr((string) $k9->created_at, 0, 10)];
    }, Capsule::table('mod_cpm_kb')
        ->select('id', 'title', 'keywords', 'tags', 'area_id', 'rel_areas', 'uses', 'created_by', 'created_at',
            'source_url', Capsule::raw('LEFT(solution, 1200) as solution'))
        ->orderBy('uses', 'desc')->orderBy('id', 'desc')->get()->all());
    // προϊόντα = η ενιαία ταξινομία περιοχών (Ρυθμίσεις → Κατηγορίες tickets)
    $prods9 = [];
    foreach (cnp_ticket_cats()['area'] as $a9) {
        $n9 = 0;
        foreach ($items9 as $it9) {
            if ($it9['areaId'] === $a9['id'] || in_array($a9['id'], $it9['relAreas'], true)) {
                $n9++;
            }
        }
        $prods9[] = $a9 + ['count' => $n9];
    }
    $unfiled9 = 0;
    foreach ($items9 as $it9) {
        if (!$it9['areaId']) {
            $unfiled9++;
        }
    }
    out(['items' => $items9, 'products' => $prods9, 'unfiled' => $unfiled9]);

case 'kb_save':
    $kid = (int) ($in['id'] ?? 0);
    $valid9 = array_column(cnp_ticket_cats()['area'], 'id');
    $area9 = (int) ($in['areaId'] ?? 0);
    $rel9 = array_values(array_unique(array_filter(array_map('intval', (array) ($in['relAreas'] ?? [])),
        function ($x) use ($valid9, $area9) { return in_array($x, $valid9, true) && $x !== $area9; })));
    $data9 = ['title' => mb_substr(trim($in['title'] ?? ''), 0, 255),
        'keywords' => mb_substr(trim($in['keywords'] ?? ''), 0, 500),
        'tags' => mb_substr(trim($in['tags'] ?? ''), 0, 190),
        'area_id' => in_array($area9, $valid9, true) ? $area9 : 0,
        'rel_areas' => implode(',', array_slice($rel9, 0, 12)),
        'solution' => cnp_clean_html($in['solution'] ?? '', 60000),   // rich-text
        'updated_at' => date('Y-m-d H:i:s')];
    if ($data9['title'] === '' || $data9['solution'] === '') {
        fail('Τίτλος και λύση είναι υποχρεωτικά');
    }
    if ($kid) {
        Capsule::table('mod_cpm_kb')->where('id', $kid)->update($data9);
    } else {
        $kid = Capsule::table('mod_cpm_kb')->insertGetId($data9
            + ['created_by' => $adminId, 'created_at' => date('Y-m-d H:i:s')]);
    }
    out(['ok' => true, 'id' => $kid]);

case 'kb_get':                           // πλήρες κείμενο ΕΝΟΣ άρθρου (on demand)
    $kg = Capsule::table('mod_cpm_kb')->where('id', (int) ($_GET['id'] ?? $in['id'] ?? 0))->first();
    if (!$kg) { fail('notfound', 404); }
    out(['ok' => true, 'id' => (int) $kg->id, 'title' => $kg->title,
        'solution' => (string) $kg->solution, 'src' => (string) $kg->source_url]);

case 'kb_bulk':                          // μαζικές ενέργειες σε άρθρα γνώσης
    $idsB = array_values(array_filter(array_map('intval', (array) ($in['ids'] ?? []))));
    if (!$idsB) { fail('Δεν διάλεξες άρθρα'); }
    $opB = (string) ($in['op'] ?? '');
    if ($opB === 'delete') {
        if (!$FULL) { fail('Μόνο διαχειριστής μπορεί να διαγράψει', 403); }
        $n = Capsule::table('mod_cpm_kb')->whereIn('id', $idsB)->delete();
        out(['ok' => true, 'op' => 'delete', 'n' => $n]);
    }
    if ($opB === 'area') {
        $validB = array_column(cnp_ticket_cats()['area'], 'id');
        $aB = (int) ($in['areaId'] ?? 0);
        if ($aB && !in_array($aB, $validB, true)) { fail('Άγνωστο προϊόν'); }
        $n = Capsule::table('mod_cpm_kb')->whereIn('id', $idsB)
            ->update(['area_id' => $aB, 'updated_at' => date('Y-m-d H:i:s')]);
        out(['ok' => true, 'op' => 'area', 'n' => $n]);
    }
    if ($opB === 'tags') {
        $n = Capsule::table('mod_cpm_kb')->whereIn('id', $idsB)
            ->update(['tags' => mb_substr(trim($in['tags'] ?? ''), 0, 190), 'updated_at' => date('Y-m-d H:i:s')]);
        out(['ok' => true, 'op' => 'tags', 'n' => $n]);
    }
    fail('Άγνωστη ενέργεια');

case 'kb_import_probe':                  // 🌐 ανάλυση URL τεκμηρίωσης πριν την εισαγωγή
    $urlI = trim($in['url'] ?? '');
    $rootI = null;
    if ($urlI === '') { fail('Δώσε URL'); }
    if (!preg_match('#^https?://#i', $urlI)) { $urlI = 'https://' . $urlI; }
    $rootI = cnp_site_root($urlI);
    // 1) WordPress REST — πιάνει BetterDocs/σελίδες/άρθρα με μία κλήση
    $found = [];
    foreach (['docs', 'pages', 'posts'] as $ptype) {
        $probe = cnp_safe_fetch($rootI . '/wp-json/wp/v2/' . $ptype . '?per_page=100&_fields=id,title,link,doc_category,categories', 4000000);
        if (empty($probe['ok'])) { continue; }
        $arr = json_decode($probe['body'], true);
        if (!is_array($arr) || !$arr || isset($arr['code'])) { continue; }
        foreach ($arr as $it) {
            if (empty($it['id'])) { continue; }
            $found[] = ['id' => (int) $it['id'], 'type' => $ptype,
                'title' => html_entity_decode(strip_tags($it['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'link' => (string) ($it['link'] ?? ''),
                'cat' => (int) (($it['doc_category'][0] ?? $it['categories'][0] ?? 0))];
        }
        if ($found) { break; }
    }
    if ($found) {
        // ονόματα κατηγοριών (ό,τι ταξινομία βρέθηκε)
        $cats = [];
        foreach (['doc_category', 'categories'] as $tax) {
            $cp = cnp_safe_fetch($rootI . '/wp-json/wp/v2/' . $tax . '?per_page=100&_fields=id,name,count');
            if (empty($cp['ok'])) { continue; }
            $ca = json_decode($cp['body'], true);
            if (!is_array($ca) || isset($ca['code'])) { continue; }
            foreach ($ca as $c) { $cats[(int) $c['id']] = html_entity_decode($c['name'], ENT_QUOTES, 'UTF-8'); }
            if ($cats) { break; }
        }
        // ήδη εισαγμένα (για να μη διπλογράψουμε)
        $have = [];
        foreach (Capsule::table('mod_cpm_kb')->whereIn('source_url', array_column($found, 'link'))
            ->pluck('source_url') as $u) { $have[$u] = true; }
        foreach ($found as &$f) {
            $f['catName'] = $cats[$f['cat']] ?? '';
            $f['exists'] = isset($have[$f['link']]);
        }
        unset($f);
        out(['ok' => true, 'mode' => 'wp', 'site' => $rootI, 'total' => count($found),
            'cats' => $cats, 'items' => $found]);
    }
    // 2) Confluence / Atlassian wiki (π.χ. wiki.soft1.eu) — api/v2
    $spaceKey = null; $pageId = null;
    if (preg_match('#/(?:space|spaces|display)/([A-Za-z0-9_~-]{2,40})#', $urlI, $mk)) { $spaceKey = $mk[1]; }
    if (preg_match('#/(?:pages/)?(\d{5,})#', $urlI, $mp)) { $pageId = $mp[1]; }
    if ($spaceKey || $pageId) {
        $conf = [];
        // 2α. αν το URL δείχνει σε σελίδα → ΟΛΟ το δέντρο από κάτω (τα άμεσα παιδιά είναι
        //     συνήθως ευρετήρια· η ουσία βρίσκεται βαθύτερα).
        if ($pageId) {
            $base = '/api/v2/pages/' . $pageId . '/descendants?limit=250&depth=10';
            $cur = '';
            for ($guard = 0; $guard < 8; $guard++) {
                $cr = cnp_safe_fetch($rootI . $base . $cur);
                if (empty($cr['ok'])) { break; }
                $cj = json_decode($cr['body'], true);
                foreach ($cj['results'] ?? [] as $c) {
                    if (($c['type'] ?? 'page') !== 'page') { continue; }
                    $conf[] = ['id' => (string) $c['id'], 'type' => 'confluence', 'title' => (string) $c['title'],
                        'link' => $rootI . '/pages/' . $c['id'], 'cat' => 0];
                }
                $cur = cnp_cursor_of($cj);
                if ($cur === '') { break; }
            }
        }
        // 2β. αλλιώς (ή αν δεν είχε παιδιά) → όλο το space, με σελιδοποίηση
        if (!$conf && $spaceKey) {
            $sr = cnp_safe_fetch($rootI . '/api/v2/spaces?keys=' . rawurlencode($spaceKey));
            $sj = !empty($sr['ok']) ? json_decode($sr['body'], true) : null;
            $sid = $sj['results'][0]['id'] ?? null;
            if ($sid) {
                $base = '/api/v2/spaces/' . $sid . '/pages?limit=250';
                $cur = '';
                for ($guard = 0; $guard < 8; $guard++) {
                    $pr = cnp_safe_fetch($rootI . $base . $cur);
                    if (empty($pr['ok'])) { break; }
                    $pj = json_decode($pr['body'], true);
                    foreach ($pj['results'] ?? [] as $c) {
                        $conf[] = ['id' => (string) $c['id'], 'type' => 'confluence', 'title' => (string) $c['title'],
                            'link' => $rootI . '/pages/' . $c['id'], 'cat' => 0];
                    }
                    $cur = cnp_cursor_of($pj);
                    if ($cur === '') { break; }
                }
            }
        }
        if ($conf) {
            $have = [];
            foreach (Capsule::table('mod_cpm_kb')->whereIn('source_url', array_column($conf, 'link'))
                ->pluck('source_url') as $u) { $have[$u] = true; }
            foreach ($conf as &$c2) { $c2['catName'] = $spaceKey ?: 'Wiki'; $c2['exists'] = isset($have[$c2['link']]); }
            unset($c2);
            out(['ok' => true, 'mode' => 'confluence', 'site' => $rootI, 'total' => count($conf),
                'cats' => [], 'items' => $conf,
                'note' => 'Οι σελίδες-ευρετήρια (χωρίς δικό τους κείμενο) παραλείπονται αυτόματα κατά την εισαγωγή.']);
        }
    }

    // 3) απλή σελίδα
    $pg = cnp_safe_fetch($urlI);
    if (empty($pg['ok'])) { fail($pg['error']); }
    $art = cnp_html_article($pg['body']);
    if (trim(strip_tags($art['html'])) === '') { fail('Δεν βρέθηκε περιεχόμενο σε αυτή τη σελίδα'); }
    out(['ok' => true, 'mode' => 'page', 'site' => $rootI,
        'items' => [['id' => 0, 'title' => $art['title'] ?: $urlI, 'link' => $pg['url'], 'cat' => 0,
            'catName' => '', 'exists' => Capsule::table('mod_cpm_kb')->where('source_url', $pg['url'])->exists()]]]);

case 'kb_import_commit':                 // εισαγωγή επιλεγμένων άρθρων στην τράπεζα
    $items = (array) ($in['items'] ?? []);
    if (!$items) { fail('Δεν διάλεξες άρθρα'); }
    if (count($items) > 120) { fail('Πολλά άρθρα μαζί — διάλεξε έως 120'); }
    $validI = array_column(cnp_ticket_cats()['area'], 'id');
    $areaI = in_array((int) ($in['areaId'] ?? 0), $validI, true) ? (int) $in['areaId'] : 0;
    $tagI = mb_substr(trim($in['tags'] ?? ''), 0, 190);
    $ok = 0; $skip = 0; $errs = [];
    foreach ($items as $it) {
        $link = trim($it['link'] ?? '');
        if ($link === '') { continue; }
        $exists = Capsule::table('mod_cpm_kb')->where('source_url', $link)->first();
        if ($exists && empty($in['overwrite'])) { $skip++; continue; }
        $html = ''; $title = trim($it['title'] ?? '');
        if (($it['type'] ?? '') === 'confluence' && !empty($it['id'])) {   // Confluence api/v2
            $r = cnp_safe_fetch(cnp_site_root($link) . '/api/v2/pages/'
                . preg_replace('/\D/', '', $it['id']) . '?body-format=export_view');
            if (!empty($r['ok'])) {
                $j = json_decode($r['body'], true);
                $html = (string) ($j['body']['export_view']['value'] ?? '');
                if ($title === '') { $title = (string) ($j['title'] ?? ''); }
            }
            if (trim(strip_tags(cnp_clean_html($html, 60000))) === '') { $skip++; continue; }  // σελίδα-ευρετήριο
        } elseif (!empty($it['id']) && !empty($it['type'])) {     // WP REST → καθαρό content
            $r = cnp_safe_fetch(cnp_site_root($link) . '/wp-json/wp/v2/' . preg_replace('/[^a-z]/', '', $it['type'])
                . '/' . (int) $it['id'] . '?_fields=title,content,link');
            if (!empty($r['ok'])) {
                $j = json_decode($r['body'], true);
                $html = (string) ($j['content']['rendered'] ?? '');
                if ($title === '') { $title = html_entity_decode(strip_tags($j['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8'); }
            }
        }
        if ($html === '') {                                        // fallback: κατέβασε τη σελίδα
            $r = cnp_safe_fetch($link);
            if (empty($r['ok'])) { $errs[] = $title ?: $link; continue; }
            $a = cnp_html_article($r['body']);
            $html = $a['html'];
            if ($title === '') { $title = $a['title']; }
        }
        $clean = cnp_clean_html(cnp_absolutize_html($html, $link), 60000);
        if ($title === '' || trim(strip_tags($clean)) === '') { $errs[] = $title ?: $link; continue; }
        // (1) κενό ΑΝΤΙ για κάθε tag — αλλιώς τα κελιά πινάκων κολλάνε σε μία λέξη
        //     («publishκατηγοριαερωτησηαπαντησηναι»).
        // (2) html_entity_decode ΠΡΙΝ την εξαγωγή: το Confluence κωδικοποιεί τα ελληνικά ως
        //     &alpha;&omicron;… και οι λέξεις γέμιζαν «alpha, omicron, chi».
        $plain = html_entity_decode(preg_replace('/<[^>]+>/', ' ', $clean), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $kw = implode(', ', array_slice(cnp_words($title . ' ' . mb_substr($plain, 0, 1800)), 0, 18));
        // 4-byte (emoji) εκτός — DB utf8mb3, αλλιώς αποθηκεύεται ως ????
        $title = trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $title));
        $row = ['title' => mb_substr($title, 0, 255), 'keywords' => mb_substr($kw, 0, 500),
            'solution' => $clean, 'tags' => $tagI, 'area_id' => $areaI, 'rel_areas' => '',
            'source_url' => mb_substr($link, 0, 500), 'updated_at' => date('Y-m-d H:i:s')];
        if ($exists) {
            Capsule::table('mod_cpm_kb')->where('id', $exists->id)->update($row);
        } else {
            Capsule::table('mod_cpm_kb')->insert($row + ['created_by' => $adminId, 'created_at' => date('Y-m-d H:i:s')]);
        }
        $ok++;
    }
    out(['ok' => true, 'imported' => $ok, 'skipped' => $skip, 'failed' => count($errs),
        'failedTitles' => array_slice($errs, 0, 10)]);

case 'kb_del':
    if (!$FULL) {
        fail('perm', 403);
    }
    Capsule::table('mod_cpm_kb')->where('id', (int) ($in['id'] ?? 0))->delete();
    out(['ok' => true]);

case 'kb_use':                          // μέτρηση χρησιμότητας (κλικ σε πρόταση)
    Capsule::table('mod_cpm_kb')->where('id', (int) ($in['id'] ?? 0))->increment('uses');
    out(['ok' => true]);

/* ============ 🖥 REMOTE ΣΥΝΕΔΡΙΕΣ (χρονομέτρηση + χρέωση) ============ */
case 'rdp_file':                        // ⬇ έτοιμο .rdp αρχείο (ανοίγει το mstsc)
    $ip9 = trim($_GET['ip'] ?? '');
    if (!filter_var($ip9, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.\-]{3,190}$/', $ip9)) {
        fail('ip');
    }
    $label9 = preg_replace('/[^a-zA-Z0-9.\-]/', '_', $_GET['label'] ?? $ip9);
    $rdp = "full address:s:$ip9\r\n"
        . "prompt for credentials:i:1\r\n"
        . "screen mode id:i:2\r\n"
        . "desktopwidth:i:1920\r\ndesktopheight:i:1080\r\n"
        . "session bpp:i:32\r\n"
        . "compression:i:1\r\n"
        . "audiomode:i:0\r\n"
        . "redirectclipboard:i:1\r\n"
        . "redirectprinters:i:0\r\n"
        . "autoreconnection enabled:i:1\r\n"
        . "authentication level:i:2\r\n"
        . "negotiate security layer:i:1\r\n";
    header('Content-Type: application/x-rdp');
    header('Content-Disposition: attachment; filename="CloudOn-' . $label9 . '.rdp"');
    header('Content-Length: ' . strlen($rdp));
    echo $rdp;
    exit;

case 'remote_active':                   // η τρέχουσα ανοιχτή συνεδρία μου
    $rs = Capsule::table('mod_cpm_remote_sessions')->where('admin_id', $adminId)
        ->whereNull('ended_at')->orderBy('id', 'desc')->first();
    out(['session' => $rs ? ['id' => (int) $rs->id, 'client' => (int) $rs->clientid,
        'clientName' => clientLabel($rs->clientid), 'tool' => $rs->tool,
        'note' => $rs->note, 'meetUrl' => $rs->meet_url,
        'started' => $rs->started_at,
        'secs' => time() - strtotime($rs->started_at)] : null]);

case 'remote_start':
    $cid8 = (int) ($in['client'] ?? 0);
    if (!$cid8 || !Capsule::table('tblclients')->where('id', $cid8)->exists()) {
        fail('Διάλεξε πελάτη');
    }
    if (Capsule::table('mod_cpm_remote_sessions')->where('admin_id', $adminId)->whereNull('ended_at')->exists()) {
        fail('Έχεις ήδη ανοιχτή remote συνεδρία — κλείσε την πρώτα');
    }
    $peer8 = preg_replace('/[^0-9]/', '', $in['peer'] ?? '');   // RustDesk ID (9ψήφιο)
    if ($peer8 === '') {
        fail('Δώσε το RustDesk ID που διαβάζει ο πελάτης');
    }
    $rid8 = Capsule::table('mod_cpm_remote_sessions')->insertGetId(['admin_id' => $adminId,
        'clientid' => $cid8, 'ticketid' => (int) ($in['ticket'] ?? 0) ?: null,
        'tool' => 'rustdesk', 'note' => mb_substr(trim(($in['note'] ?? '') . ' [ID ' . $peer8 . ']'), 0, 255),
        'meet_url' => null, 'started_at' => date('Y-m-d H:i:s')]);
    // 📇 ΘΥΜΗΣΟΥ το RustDesk ID του πελάτη (address book) — ώστε να μη ξαναρωτάμε
    Capsule::table('mod_cpm_client_remote')->updateOrInsert(['clientid' => $cid8],
        ['rustdesk_id' => $peer8, 'updated_by' => $adminId, 'updated_at' => date('Y-m-d H:i:s')]);
    // deep link για το RustDesk του χειριστή
    $gatewayUrl = 'rustdesk://connection/new/' . $peer8;
    out(['ok' => true, 'id' => $rid8, 'gatewayUrl' => $gatewayUrl]);

case 'remote_peer':                     // αποθηκευμένο RustDesk ID ενός πελάτη
    $cid8 = (int) ($_GET['client'] ?? $in['client'] ?? 0);
    $rp = Capsule::table('mod_cpm_client_remote')->where('clientid', $cid8)->first();
    out(['rustdesk_id' => $rp->rustdesk_id ?? '', 'label' => $rp->label ?? '',
        'updated' => $rp->updated_at ?? null]);

case 'remote_save_peer':                // αποθήκευση/επεξεργασία ID χωρίς σύνδεση
    $cid8 = (int) ($in['client'] ?? 0);
    if (!$cid8 || !Capsule::table('tblclients')->where('id', $cid8)->exists()) { fail('Διάλεξε πελάτη'); }
    $peer8 = preg_replace('/[^0-9]/', '', $in['peer'] ?? '');
    if ($peer8 === '') {
        Capsule::table('mod_cpm_client_remote')->where('clientid', $cid8)->delete();  // κενό = διαγραφή
        out(['ok' => true, 'deleted' => true]);
    }
    Capsule::table('mod_cpm_client_remote')->updateOrInsert(['clientid' => $cid8],
        ['rustdesk_id' => $peer8, 'label' => mb_substr(trim($in['label'] ?? ''), 0, 120),
         'updated_by' => $adminId, 'updated_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true, 'rustdesk_id' => $peer8]);

case 'remote_book':                     // 📇 όλες οι αποθηκευμένες συνδέσεις (address book) + ιστορικό/στατιστικά
    $rows = Capsule::table('mod_cpm_client_remote as r')
        ->join('tblclients as c', 'c.id', '=', 'r.clientid')
        ->orderBy('r.updated_at', 'desc')
        ->get(['r.clientid', 'r.rustdesk_id', 'r.label', 'r.updated_at',
               'c.firstname', 'c.lastname', 'c.companyname']);
    // σύνοψη ολοκληρωμένων συνεδριών ανά πελάτη (πλήθος / τελευταία / συνολικά λεπτά)
    $sAgg = [];
    foreach (Capsule::table('mod_cpm_remote_sessions')->whereNotNull('ended_at')
        ->groupBy('clientid')
        ->get([Capsule::raw('clientid'), Capsule::raw('COUNT(*) as n'),
               Capsule::raw('MAX(started_at) as last_at'), Capsule::raw('SUM(minutes) as mins')]) as $g) {
        $sAgg[(int) $g->clientid] = ['n' => (int) $g->n, 'lastAt' => $g->last_at, 'mins' => (int) $g->mins];
    }
    $book = [];
    foreach ($rows as $r) {
        $a = $sAgg[(int) $r->clientid] ?? ['n' => 0, 'lastAt' => null, 'mins' => 0];
        $book[] = ['clientid' => (int) $r->clientid, 'rustdesk_id' => $r->rustdesk_id,
            'label' => $r->label, 'updated' => $r->updated_at,
            'name' => $r->companyname ?: trim($r->firstname . ' ' . $r->lastname),
            'sessions' => $a['n'], 'lastAt' => $a['lastAt'], 'totalMins' => $a['mins']];
    }
    // στατιστικά 30 ημερών (όλης της ομάδας — ο restricted βλέπει μόνο τα δικά του)
    $since30 = date('Y-m-d H:i:s', strtotime('-30 days'));
    $qStat = Capsule::table('mod_cpm_remote_sessions')->whereNotNull('ended_at')->where('started_at', '>=', $since30);
    if (!$FULL) {
        $qStat->where('admin_id', $adminId);
    }
    $st30 = $qStat->first([Capsule::raw('COUNT(*) as n'), Capsule::raw('SUM(minutes) as mins'),
        Capsule::raw('SUM(CASE WHEN billable=1 THEN minutes ELSE 0 END) as bmins')]);
    // πρόσφατες συνεδρίες
    $qRec = Capsule::table('mod_cpm_remote_sessions as s')->leftJoin('tblclients as c', 'c.id', '=', 's.clientid')
        ->whereNotNull('s.ended_at')->orderBy('s.started_at', 'desc')->limit(8);
    if (!$FULL) {
        $qRec->where('s.admin_id', $adminId);
    }
    $recent = [];
    foreach ($qRec->get(['s.id', 's.clientid', 's.admin_id', 's.ticketid', 's.started_at', 's.minutes',
        's.billable', 's.note', 'c.firstname', 'c.lastname', 'c.companyname']) as $s) {
        $recent[] = ['id' => (int) $s->id, 'clientid' => (int) $s->clientid,
            'name' => $s->companyname ?: trim($s->firstname . ' ' . $s->lastname) ?: ('#' . (int) $s->clientid),
            'by' => Db::adminName((int) $s->admin_id), 'ticket' => (int) $s->ticketid,
            'startedAt' => $s->started_at, 'minutes' => (int) $s->minutes,
            'billable' => (bool) $s->billable, 'note' => $s->note];
    }
    out(['book' => $book, 'recent' => $recent,
        'stats' => ['saved' => count($book), 'n30' => (int) ($st30->n ?? 0),
            'mins30' => (int) ($st30->mins ?? 0), 'bmins30' => (int) ($st30->bmins ?? 0)],
        'dl' => (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'rustdesk_dl')->value('value') ?: '')]);

case 'remote_send_client':              // στείλε το πρόγραμμα υποστήριξης στον πελάτη
    $cid8 = (int) ($in['client'] ?? 0);
    $to8 = filter_var(trim($in['email'] ?? ''), FILTER_VALIDATE_EMAIL)
        ? trim($in['email'])
        : (string) Capsule::table('tblclients')->where('id', $cid8)->value('email');
    if (!filter_var($to8, FILTER_VALIDATE_EMAIL)) {
        fail('Άκυρο email');
    }
    $dl = (string) Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', 'rustdesk_dl')->value('value');
    $html = '<p>Γεια σας,</p><p>Για να σας βοηθήσουμε απομακρυσμένα, κατεβάστε και τρέξτε το μικρό μας πρόγραμμα:</p>'
        . '<p style="text-align:center;margin:18px 0"><a href="' . htmlspecialchars($dl)
        . '" style="background:#0090dd;color:#fff;padding:12px 26px;border-radius:10px;text-decoration:none;font-weight:bold;">⬇ Κατέβασμα CloudOn Remote</a></p>'
        . '<p style="background:#eef7fd;border-left:4px solid #0090dd;padding:10px 14px;">'
        . '<b>Οδηγίες (30 δευτερόλεπτα):</b><br>1. Κατεβάστε και ανοίξτε το αρχείο (δεν χρειάζεται εγκατάσταση).<br>'
        . '2. Θα δείτε ένα <b>ID (9 ψηφία)</b> και έναν <b>κωδικό</b>.<br>'
        . '3. Διαβάστε μας το ID και τον κωδικό στο τηλέφωνο — και είμαστε μαζί σας!</p>'
        . '<p>Το πρόγραμμα είναι ασφαλές και συνδέεται μόνο στους δικούς μας servers.</p>'
        . '<p>Με εκτίμηση,<br>Η ομάδα υποστήριξης της CloudOn</p>';
    \WHMCS\Module\Addon\CloudonProjects\Notify::sendTo($to8, 'CloudOn — Πρόγραμμα απομακρυσμένης υποστήριξης', $html);
    if (function_exists('logActivity')) {
        logActivity('CPM: RustDesk client στάλθηκε στον πελάτη #' . $cid8 . ' (' . $to8 . ')');
    }
    out(['ok' => true, 'sent' => $to8]);

case 'remote_stop':
    $rs = Capsule::table('mod_cpm_remote_sessions')->where('id', (int) ($in['id'] ?? 0))
        ->where('admin_id', $adminId)->whereNull('ended_at')->first();
    if (!$rs) {
        fail('session', 404);
    }
    $mins8 = max(1, (int) round((time() - strtotime($rs->started_at)) / 60));
    $bill8 = !empty($in['billable']);
    $note8 = mb_substr(trim($in['note'] ?? ($rs->note ?: '')), 0, 200);
    $charged8 = 0;
    $wl8 = null;
    try {
        require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/Time.php';
        $charged8 = \WHMCS\Module\Addon\CloudonProjects\Time::chargeFor($mins8, $bill8);
        $scDb = '\\WHMCS\\Module\\Addon\\SupportContracts\\Db';
        if (!class_exists($scDb)) {
            require_once __DIR__ . '/../modules/addons/supportcontracts/lib/Db.php';
        }
        $wl8 = $scDb::addWork($rs->clientid, $rs->ticketid, $mins8, $charged8, $bill8,
            '🖥 Remote συνεδρία (RustDesk)' . ($note8 ? ': ' . $note8 : ''),
            $adminId);
        if ($bill8 && $charged8 > 0) {
            $scDb::applyMovement($rs->clientid, -$charged8, 'work',
                'Remote συνεδρία' . ($note8 ? ': ' . $note8 : ''), $rs->ticketid, $adminId, 'cpm-remote-' . $rs->id);
        }
    } catch (\Throwable $e) {
        // χωρίς supportcontracts: μόνο καταγραφή διάρκειας
    }
    Capsule::table('mod_cpm_remote_sessions')->where('id', $rs->id)->update([
        'ended_at' => date('Y-m-d H:i:s'), 'minutes' => $mins8, 'billable' => $bill8 ? 1 : 0,
        'note' => $note8 ?: null, 'sc_worklog_id' => $wl8 ?: null]);
    if (function_exists('logActivity')) {
        logActivity("CPM: remote συνεδρία #{$rs->id} πελάτη #{$rs->clientid} — {$mins8}' "
            . ($bill8 ? "(χρέωση {$charged8}')" : '(χωρίς χρέωση)') . " από admin #$adminId");
    }
    out(['ok' => true, 'minutes' => $mins8, 'charged' => $charged8]);

case 'tquotas':                         // 🎟 πακέτα υποστήριξης με όρια tickets (full)
    if (!$FULL) {
        fail('perm', 403);
    }
    $rows7 = [];
    foreach (Capsule::table('mod_cpm_support_packages')->orderBy('sort')->orderBy('id')->get() as $pk) {
        $rows7[] = ['id' => (int) $pk->id, 'name' => $pk->name,
            'clients' => (int) Capsule::table('mod_cpm_client_package')->where('package_id', $pk->id)->count(),
            't' => (int) $pk->t_month, 'email' => (int) $pk->email_month, 'phone' => (int) $pk->phone_month];
    }
    out(['packages' => $rows7]);

case 'tquota_save':                     // δημιουργία/μετονομασία/όρια πακέτου
    if (!$FULL) {
        fail('perm', 403);
    }
    $pid7 = (int) ($in['id'] ?? 0);
    $nm7 = mb_substr(trim($in['name'] ?? ''), 0, 80);
    if ($nm7 === '') {
        fail('Δώσε όνομα πακέτου');
    }
    $data7 = ['name' => $nm7, 't_month' => max(0, (int) ($in['t'] ?? 0)),
        'email_month' => max(0, (int) ($in['email'] ?? 0)), 'phone_month' => max(0, (int) ($in['phone'] ?? 0))];
    if ($pid7) {
        Capsule::table('mod_cpm_support_packages')->where('id', $pid7)->update($data7);
    } else {
        $pid7 = Capsule::table('mod_cpm_support_packages')->insertGetId($data7
            + ['sort' => (int) Capsule::table('mod_cpm_support_packages')->max('sort') + 1]);
    }
    out(['ok' => true, 'id' => $pid7]);

case 'tquota_del':
    if (!$FULL) {
        fail('perm', 403);
    }
    Capsule::table('mod_cpm_support_packages')->where('id', (int) ($in['id'] ?? 0))->delete();
    Capsule::table('mod_cpm_client_package')->where('package_id', (int) ($in['id'] ?? 0))->delete();
    out(['ok' => true]);

case 'client_package_set':              // ανάθεση πελάτη σε πακέτο (full)
    if (!$FULL) {
        fail('perm', 403);
    }
    $cid7 = (int) ($in['client'] ?? 0);
    $pk7 = (int) ($in['package'] ?? 0);
    if (!$cid7) {
        fail('client');
    }
    if ($pk7) {
        Capsule::table('mod_cpm_client_package')->updateOrInsert(['clientid' => $cid7], ['package_id' => $pk7]);
    } else {
        Capsule::table('mod_cpm_client_package')->where('clientid', $cid7)->delete();
    }
    out(['ok' => true]);

case 'users':                          // διαχείριση χρηστών (μόνο διαχειριστές)
    if (!$FULL) {
        fail('perm', 403);
    }
    $fullRoles = array_filter(array_map('intval', explode(',',
        (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'full_access_roles')->value('value') ?: '1'))));
    out(['users' => array_map(function ($a) use ($fullRoles) {
            $isFullU = in_array((int) $a->roleid, $fullRoles, true);
            return ['id' => (int) $a->id, 'username' => $a->username,
                'name' => trim($a->firstname . ' ' . $a->lastname), 'email' => $a->email,
                'roleid' => (int) $a->roleid, 'disabled' => (bool) $a->disabled,
                'full' => $isFullU, 'areas' => cnp_admin_areas((int) $a->id, $isFullU)];
        }, Capsule::table('tbladmins')->orderBy('disabled')->orderBy('username')->get()->all()),
        'roles' => array_map(function ($r) use ($fullRoles) {
            return ['id' => (int) $r->id, 'name' => $r->name,
                'full' => in_array((int) $r->id, $fullRoles, true)];
        }, Capsule::table('tbladminroles')->orderBy('id')->get()->all())]);

case 'user_areas_save':                 // ειδικότητες/πρόσβαση χειριστή (full μόνο)
    if (!$FULL) {
        fail('perm', 403);
    }
    $uid7 = (int) ($in['id'] ?? 0);
    if (!$uid7 || !Capsule::table('tbladmins')->where('id', $uid7)->exists()) { fail('user'); }
    $valid = ['sales', 'support', 'projects', 'admin', 'hr'];
    $areas7 = array_values(array_intersect($valid, (array) ($in['areas'] ?? [])));
    Db::setPref($uid7, 'areas', implode(',', $areas7));
    out(['ok' => true, 'areas' => $areas7]);

case 'user_save':
    if (!$FULL) {
        fail('perm', 403);
    }
    $uid = (int) ($in['id'] ?? 0);
    $roleid = (int) ($in['roleid'] ?? 0);
    if (!Capsule::table('tbladminroles')->where('id', $roleid)->exists()) {
        fail('Άκυρος ρόλος');
    }
    $email = trim($in['email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail('Άκυρο email');
    }
    $row = ['firstname' => mb_substr(trim($in['first'] ?? ''), 0, 60),
        'lastname' => mb_substr(trim($in['last'] ?? ''), 0, 60),
        'email' => $email, 'roleid' => $roleid];
    if ($uid) {
        if (!Capsule::table('tbladmins')->where('id', $uid)->exists()) {
            fail('user', 404);
        }
        Capsule::table('tbladmins')->where('id', $uid)->update($row + ['updated_at' => date('Y-m-d H:i:s')]);
        out(['ok' => true, 'id' => $uid]);
    }
    $uname = preg_replace('/[^A-Za-z0-9._-]/', '', trim($in['username'] ?? ''));
    if (strlen($uname) < 3) {
        fail('Username τουλάχιστον 3 χαρακτήρες (λατινικά/αριθμοί)');
    }
    if (Capsule::table('tbladmins')->whereRaw('LOWER(username) = ?', [strtolower($uname)])->exists()) {
        fail('Το username υπάρχει ήδη');
    }
    $plain = substr(strtr(base64_encode(random_bytes(24)), '+/', 'Kx'), 0, 14);
    $depts = ',' . implode(',', Capsule::table('tblticketdepartments')->pluck('id')->all()) . ',';
    $uuid = sprintf('%08x-%04x-4%03x-%04x-%012x', random_int(0, 0xffffffff), random_int(0, 0xffff),
        random_int(0, 0xfff), random_int(0x8000, 0xbfff), random_int(0, 0xffffffffffff));
    $uid = Capsule::table('tbladmins')->insertGetId($row + ['username' => $uname,
        'password' => password_hash($plain, PASSWORD_DEFAULT),
        'passwordhash' => password_hash($plain, PASSWORD_DEFAULT),   // WHMCS 9 ελέγχει ΑΥΤΟ στο login
        'uuid' => $uuid, 'authmodule' => '', 'authdata' => '', 'signature' => '', 'notes' => 'Δημιουργήθηκε από CloudOn Projects',
        'template' => 'blend', 'language' => '', 'disabled' => 0, 'loginattempts' => 0,
        'supportdepts' => $depts, 'ticketnotifications' => '', 'homewidgets' => '',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true, 'id' => $uid, 'password' => $plain]);

case 'user_pass':                      // reset κωδικού → νέος, εμφανίζεται μία φορά
    if (!$FULL) {
        fail('perm', 403);
    }
    $uid = (int) ($in['id'] ?? 0);
    if (!Capsule::table('tbladmins')->where('id', $uid)->exists()) {
        fail('user', 404);
    }
    $plain = substr(strtr(base64_encode(random_bytes(24)), '+/', 'Kx'), 0, 14);
    Capsule::table('tbladmins')->where('id', $uid)
        ->update(['password' => password_hash($plain, PASSWORD_DEFAULT),
            'passwordhash' => password_hash($plain, PASSWORD_DEFAULT), 'loginattempts' => 0,
            'updated_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true, 'password' => $plain]);

case 'user_toggle':
    if (!$FULL) {
        fail('perm', 403);
    }
    $uid = (int) ($in['id'] ?? 0);
    if ($uid === $adminId) {
        fail('Δεν μπορείς να απενεργοποιήσεις τον εαυτό σου');
    }
    $u = Capsule::table('tbladmins')->where('id', $uid)->first(['disabled']);
    if (!$u) {
        fail('user', 404);
    }
    Capsule::table('tbladmins')->where('id', $uid)->update(['disabled' => $u->disabled ? 0 : 1,
        'updated_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true, 'disabled' => !$u->disabled]);

case 'user_del':
    if (!$FULL) {
        fail('perm', 403);
    }
    $uid = (int) ($in['id'] ?? 0);
    if ($uid === $adminId) {
        fail('Δεν μπορείς να διαγράψεις τον εαυτό σου');
    }
    $u = Capsule::table('tbladmins')->where('id', $uid)->first(['roleid', 'username']);
    if (!$u) {
        fail('user', 404);
    }
    // μην μείνει το σύστημα χωρίς κανέναν ενεργό διαχειριστή
    $fullRoles = array_filter(array_map('intval', explode(',',
        (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'full_access_roles')->value('value') ?: '1'))));
    if (in_array((int) $u->roleid, $fullRoles, true)
        && Capsule::table('tbladmins')->whereIn('roleid', $fullRoles)->where('disabled', 0)->where('id', '!=', $uid)->count() === 0) {
        fail('Είναι ο τελευταίος ενεργός διαχειριστής');
    }
    Capsule::table('tbladmins')->where('id', $uid)->delete();
    // καθάρισμα συμμετοχών στο app (ιστορικό tasks/χρόνου μένει άθικτο)
    Capsule::table('mod_cpm_project_members')->where('admin_id', $uid)->delete();
    Capsule::table('mod_cpm_team_members')->where('admin_id', $uid)->delete();
    Capsule::table('mod_cpm_watchers')->where('admin_id', $uid)->delete();
    Capsule::table('mod_cpm_notifications')->where('admin_id', $uid)->delete();
    out(['ok' => true]);

case 'ticket_att':                     // ασφαλές κατέβασμα συνημμένου ticket
    $tid = (int) ($_GET['ticket'] ?? 0);
    $rid = (int) ($_GET['rid'] ?? 0);
    $idx = (int) ($_GET['i'] ?? 0);
    $tk = Capsule::table('tbltickets')->where('id', $tid)->first(['flag', 'attachment']);
    if (!$tk) {
        fail('ticket', 404);
    }
    if (!$FULL && (int) $tk->flag !== $adminId && (int) $tk->flag !== 0) {
        fail('ticket', 403);
    }
    if ($rid) {
        $row = Capsule::table('tblticketreplies')->where('id', $rid)->where('tid', $tid)->first(['attachment']);
        $attStr = $row->attachment ?? '';
    } else {
        $attStr = $tk->attachment ?? '';
    }
    $parts = array_values(array_filter(explode('|', (string) $attStr)));
    $stored = $parts[$idx] ?? null;
    $path = $stored ? realpath(__DIR__ . '/../attachmentsnew/' . $stored) : false;
    if (!$stored || !$path || strpos($path, realpath(__DIR__ . '/../attachmentsnew') . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
        fail('file', 404);
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . preg_replace('/^\\d+_/', '', $stored) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;

case 'ticket_update':
    $tid = (int) ($in['ticket'] ?? 0);
    $tk = Capsule::table('tbltickets')->where('id', $tid)->first(['flag']);
    if (!$tk) {
        fail('ticket', 404);
    }
    if (!$FULL && (int) $tk->flag !== $adminId && (int) $tk->flag !== 0) {
        fail('ticket', 403);
    }
    $upd = ['ticketid' => $tid];
    if (array_key_exists('status', $in) && $in['status'] !== '') {
        $upd['status'] = $in['status'];
    }
    if ($FULL && array_key_exists('flag', $in)) {
        $upd['flag'] = (int) $in['flag']; // ανάθεση: μόνο διαχειριστές
    }
    if ($FULL && array_key_exists('urgency', $in) && in_array($in['urgency'], ['Low', 'Medium', 'High'], true)) {
        $upd['priority'] = $in['urgency'];
    }
    $uname = Capsule::table('tbladmins')->where('id', $adminId)->value('username');
    $r = localAPI('UpdateTicket', $upd, $uname);
    out(['ok' => ($r['result'] ?? '') === 'success', 'msg' => $r['message'] ?? null]);

case 'ticket_note':
    $tid = (int) ($in['ticket'] ?? 0);
    $msg = trim($in['body'] ?? '');
    $tk = Capsule::table('tbltickets')->where('id', $tid)->first(['tid', 'title', 'did']);
    if (!$tk || $msg === '') {
        fail('input');
    }
    $task = Db::taskForTicket($tid);
    if (!$task) {
        $proj = Db::projectForDept($tk->did) ?: (Db::projects()[0] ?? null);
        if ($proj) {
            $ntid = Db::saveTask(0, ['project_id' => (int) $proj->id,
                'title' => '[#' . $tk->tid . '] ' . mb_substr($tk->title, 0, 180),
                'status_id' => Db::firstStatusId(), 'ticketid' => $tid], $adminId);
            $task = Db::task($ntid);
        }
    }
    if (!$task) {
        fail('no project');
    }
    $to = ($in['to'] ?? '') !== '' && $in['to'] !== null ? (int) $in['to'] : null;
    Db::addComment($task->id, $adminId, mb_substr($msg, 0, 60000), $to);
    Notify::commented($task->id, $adminId, $msg);
    if ($to !== null) {
        Notify::commentTo($task->id, $adminId, $msg, $to);
    }
    Notify::watchers($task->id, $adminId, 'Σχόλιο στο: ' . $task->title, null);
    out(['ok' => true]);

/* ================= UNIFIED SEARCH (⌘K) ================= */
case 'search':
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 2) {
        out(['tasks' => [], 'tickets' => [], 'leads' => [], 'clients' => []]);
    }
    $like = '%' . $q . '%';
    $tasks = [];
    $tqq = Capsule::table('mod_cpm_tasks as t')->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
        ->where('t.title', 'like', $like)->orderBy('t.id', 'desc')->limit(6)
        ->select('t.id', 't.title', 't.assignee', 't.project_id', 'p.name as pname', 'p.color as pcolor');
    foreach ($tqq->get() as $t) {
        if (!$FULL && (int) $t->assignee !== $adminId && !Db::canSeeProject($adminId, $t->project_id)) {
            continue;
        }
        $tasks[] = ['id' => (int) $t->id, 'title' => $t->title, 'pname' => $t->pname, 'pcolor' => $t->pcolor];
    }
    $tickets = [];
    $tkq = Capsule::table('tbltickets')->where(function ($w) use ($like) {
        $w->where('title', 'like', $like)->orWhere('tid', 'like', $like)->orWhere('name', 'like', $like);
    })->orderBy('lastreply', 'desc')->limit(6);
    if (!$FULL) {
        $tkq->where('flag', $adminId);
    }
    foreach ($tkq->get(['id', 'tid', 'title', 'status']) as $t) {
        $tickets[] = ['id' => (int) $t->id, 'tid' => $t->tid, 'title' => $t->title, 'status' => $t->status];
    }
    $leads = [];
    foreach (Capsule::table('mod_cpm_leads')->where(function ($w) use ($like) {
        $w->where('company', 'like', $like)->orWhere('contact', 'like', $like)->orWhere('email', 'like', $like);
    })->limit(5)->get() as $l) {
        if (!$FULL && (int) $l->assignee !== $adminId && (int) $l->created_by !== $adminId) {
            continue;
        }
        $leads[] = ['id' => (int) $l->id, 'name' => $l->company ?: $l->contact, 'stage' => $l->stage];
    }
    $clients = [];
    if ($FULL) {
        foreach (Capsule::table('tblclients')->where(function ($w) use ($like) {
            $w->where('firstname', 'like', $like)->orWhere('lastname', 'like', $like)
              ->orWhere('companyname', 'like', $like)->orWhere('email', 'like', $like);
        })->limit(5)->get(['id', 'firstname', 'lastname', 'companyname']) as $c) {
            $clients[] = ['id' => (int) $c->id, 'name' => $c->companyname ?: trim($c->firstname . ' ' . $c->lastname)];
        }
    }
    out(['tasks' => $tasks, 'tickets' => $tickets, 'leads' => $leads, 'clients' => $clients]);

/* ================= ΡΥΘΜΙΣΕΙΣ (in-app) ================= */
case 'settings_get':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $keys = ['auto_task', 'notify_email', 'request_form', 'sales_target', 'cost_per_hour', 'team_roles', 'full_access_roles', 'ai_api_key', 'cv_ai_model',
        'storage_driver', 's3_endpoint', 's3_region', 's3_bucket', 's3_key', 's3_secret', 's3_prefix'];
    $vals = [];
    foreach ($keys as $k) {
        $vals[$k] = (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', $k)->value('value') ?? '');
    }
    $vals['s3_secret_set'] = $vals['s3_secret'] !== '' ? '1' : '';   // δεν εκθέτουμε το secret
    $vals['s3_secret'] = '';
    /* Το AI key είναι μυστικό όπως κάθε άλλο: δεν φεύγει ποτέ προς τον browser.
       Στέλνουμε μόνο ένδειξη ότι υπάρχει και τα 4 τελευταία ψηφία, ώστε να
       αναγνωρίζεται ποιο κλειδί είναι χωρίς να αποκαλύπτεται. */
    $vals['ai_api_key_set']  = $vals['ai_api_key'] !== '' ? '1' : '';
    $vals['ai_api_key_tail'] = $vals['ai_api_key'] !== '' ? mb_substr($vals['ai_api_key'], -4) : '';
    $vals['ai_api_key'] = '';
    $sts = [];
    foreach (Db::statuses() as $s) {
        $cnt = Capsule::table('mod_cpm_tasks')->where('status_id', $s->id)->count();
        $sts[] = ['id' => (int) $s->id, 'title' => $s->title, 'color' => $s->color,
            'done' => (bool) $s->is_done, 'sort' => (int) $s->sort, 'tasks' => $cnt];
    }
    $types = [];
    foreach (Db::taskTypes() as $ty) {
        $types[] = ['id' => (int) $ty->id, 'name' => $ty->name, 'icon' => $ty->icon, 'color' => $ty->color,
            'reqA' => (bool) $ty->req_assignee, 'reqD' => (bool) $ty->req_due, 'reqE' => (bool) $ty->req_estimate];
    }
    out(['settings' => $vals, 'statuses' => $sts, 'types' => $types]);

case 'settings_save':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $allowed = ['auto_task', 'notify_email', 'request_form', 'sales_target', 'cost_per_hour', 'team_roles', 'full_access_roles', 'ai_api_key', 'cv_ai_model',
        'storage_driver', 's3_endpoint', 's3_region', 's3_bucket', 's3_key', 's3_secret', 's3_prefix'];
    foreach ((array) ($in['settings'] ?? []) as $k => $v) {
        if (!in_array($k, $allowed, true)) {
            continue;
        }
        // Κενό σε πεδίο μυστικού σημαίνει «μην αλλάξεις», όχι «σβήσε».
        if (in_array($k, ['s3_secret', 'ai_api_key'], true) && trim((string) $v) === '') { continue; }
        $v = mb_substr(trim((string) $v), 0, 500);
        $ex = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', $k);
        if ($ex->exists()) {
            $ex->update(['value' => $v]);
        } else {
            Capsule::table('tbladdonmodules')->insert(['module' => 'cloudonprojects', 'setting' => $k, 'value' => $v]);
        }
    }
    out(['ok' => true]);

case 'storage_test':                      // health check σύνδεσης S3 (+ auto CORS)
    if (!$FULL) { fail('forbidden', 403); }
    $stt = Storage::s3Test();
    if (!empty($stt['ok'])) {
        $cors = Storage::applyCors(['https://my.cloudon.gr']);
        $stt['msg'] .= ' · ' . $cors['msg'];
        if (empty($cors['ok'])) { $stt['ok'] = false; }
    }
    out($stt);

/* ── Γενικό API αρχείων (όλα τα modules, μέσω Storage) ── */
case 'file_presign_put':                  // direct-to-S3 upload (μεγάλα/βίντεο)
    $module = (string) ($in['module'] ?? '');
    if (!cnp_file_authz($adminId, $FULL, $module)) { fail('forbidden', 403); }
    if (!Storage::isS3()) { out(['mode' => 'multipart']); }   // local driver → ο client πέφτει σε file_upload
    $name = (string) ($in['filename'] ?? 'file');
    if (!cnp_file_ext_ok($name)) { fail('Μη επιτρεπτός τύπος αρχείου', 400); }
    $size = (int) ($in['size'] ?? 0);
    if ($size > 5 * 1024 * 1024 * 1024) { fail('Πολύ μεγάλο αρχείο (όριο 5GB)', 400); }
    $mime = mb_substr((string) ($in['mime'] ?? 'application/octet-stream'), 0, 120);
    $key = Storage::newKey($module ?: 'general', pathinfo($name, PATHINFO_EXTENSION), 's3');
    out(['mode' => 'direct', 'uploadUrl' => Storage::presignPutKey($key, $mime, 900), 'key' => $key,
        'headers' => ['Content-Type' => $mime]]);

case 'file_confirm':                      // καταχώρηση μετά από direct-to-S3 upload
    $module = (string) ($in['module'] ?? '');
    if (!cnp_file_authz($adminId, $FULL, $module)) { fail('forbidden', 403); }
    if ($module === 'library' && !cnp_lib_can($adminId, (int) ($in['ref_id'] ?? 0), true)) { fail('forbidden', 403); }
    $key = (string) ($in['key'] ?? '');
    $prefix = trim(Storage::config('s3_prefix', ''), '/');
    $rel = ($prefix !== '' && strpos($key, $prefix . '/') === 0) ? substr($key, strlen($prefix) + 1) : $key;
    if (!preg_match('#^' . preg_quote($module ?: 'general', '#') . '/\d{4}/\d{2}/[a-f0-9]{32}#', $rel)) { fail('Μη έγκυρο key', 400); }
    if (!Storage::exists($key, 's3')) { fail('Το αρχείο δεν βρέθηκε στο storage', 404); }
    $mime = mb_substr((string) ($in['mime'] ?? 'application/octet-stream'), 0, 120);
    $size = (int) ($in['size'] ?? 0);
    try { $h = Storage::s3()->headObject(['Bucket' => Storage::bucket(), 'Key' => $key]); $size = (int) $h['ContentLength']; if (!empty($h['ContentType'])) { $mime = $h['ContentType']; } } catch (\Throwable $e) {}
    $rec = Storage::registerExternal(['module' => $module, 'ref_type' => (string) ($in['ref_type'] ?? ''), 'ref_id' => (int) ($in['ref_id'] ?? 0),
        'storage_key' => $key, 'orig_name' => (string) ($in['orig_name'] ?? 'file'), 'mime' => $mime, 'size' => $size, 'uploaded_by' => $adminId]);
    out(['ok' => true, 'file' => cnp_file_row($rec)]);

case 'file_upload':                       // server-side upload (μικρά ή local driver) — multipart
    if (!empty($_FILES)) { $in = $_POST; }
    $module = (string) ($in['module'] ?? '');
    if (!cnp_file_authz($adminId, $FULL, $module)) { fail('forbidden', 403); }
    if ($module === 'library' && !cnp_lib_can($adminId, (int) ($in['ref_id'] ?? 0), true)) { fail('forbidden', 403); }
    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) { fail('Σφάλμα ανεβάσματος', 400); }
    if (!cnp_file_ext_ok($f['name'])) { fail('Μη επιτρεπτός τύπος αρχείου', 400); }
    if ($f['size'] > 50 * 1024 * 1024) { fail('Πολύ μεγάλο για server upload — απαιτείται S3 (direct)', 400); }
    $rec = Storage::store(['module' => $module, 'ref_type' => (string) ($in['ref_type'] ?? ''), 'ref_id' => (int) ($in['ref_id'] ?? 0),
        'src' => $f['tmp_name'], 'orig_name' => $f['name'], 'mime' => $f['type'] ?: 'application/octet-stream', 'uploaded_by' => $adminId]);
    out(['ok' => true, 'file' => cnp_file_row($rec)]);

case 'file_list':                         // λίστα αρχείων ανά οντότητα
    $module = (string) ($_GET['module'] ?? '');
    if (!cnp_file_authz($adminId, $FULL, $module)) { fail('forbidden', 403); }
    if ($module === 'library' && !cnp_lib_can($adminId, (int) ($_GET['ref_id'] ?? 0))) { fail('forbidden', 403); }
    $q = Capsule::table('mod_cpm_storage')->where('module', $module);
    if (isset($_GET['ref_type'])) { $q->where('ref_type', (string) $_GET['ref_type']); }
    if (isset($_GET['ref_id'])) { $q->where('ref_id', (int) $_GET['ref_id']); }
    out(['files' => array_map('cnp_file_row', $q->orderByDesc('id')->limit(500)->get()->all())]);

case 'file_get':                          // προβολή/λήψη (s3→302 presigned, local→proxy stream)
    $rec = Storage::record((int) ($_GET['id'] ?? 0));
    if (!$rec) { fail('file', 404); }
    if (!cnp_file_authz($adminId, $FULL, $rec['module'])) { fail('file', 403); }
    if ($rec['module'] === 'library' && !cnp_lib_can($adminId, (int) $rec['ref_id'])) { fail('file', 403); }
    $dl = !empty($_GET['dl']);
    if ($rec['driver'] === 's3') {
        header('Location: ' . Storage::presign($rec['id'], 300, $dl), true, 302);
        exit;
    }
    $stream = Storage::openRead($rec['id']);
    if (!$stream) { fail('file', 404); }
    $mime = $rec['mime'] ?: 'application/octet-stream';
    $previewable = ($mime === 'application/pdf' || strpos($mime, 'image/') === 0 || strpos($mime, 'video/') === 0 || strpos($mime, 'audio/') === 0);
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . (($dl || !$previewable) ? 'attachment' : 'inline') . '; filename="' . rawurlencode($rec['orig_name'] ?: 'file') . '"');
    if ($rec['size']) { header('Content-Length: ' . (int) $rec['size']); }
    fpassthru($stream); fclose($stream); exit;

case 'file_delete':
    $rec = Storage::record((int) ($in['id'] ?? 0));
    if (!$rec) { fail('file', 404); }
    if (!cnp_file_authz($adminId, $FULL, $rec['module'])) { fail('forbidden', 403); }
    if ($rec['module'] === 'library' && !cnp_lib_can($adminId, (int) $rec['ref_id'], true)) { fail('forbidden', 403); }
    Storage::delete($rec['id']);
    out(['ok' => true]);

case 'status_save':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $sid = (int) ($in['id'] ?? 0);
    $data = ['title' => mb_substr(trim($in['title'] ?? ''), 0, 60) ?: 'Στήλη',
        'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $in['color'] ?? '') ? $in['color'] : '#8595ac',
        'is_done' => !empty($in['done']) ? 1 : 0];
    if ($sid) {
        Capsule::table('mod_cpm_statuses')->where('id', $sid)->update($data);
    } else {
        $data['sort'] = 1 + (int) Capsule::table('mod_cpm_statuses')->max('sort');
        $sid = Capsule::table('mod_cpm_statuses')->insertGetId($data);
    }
    out(['ok' => true, 'id' => $sid]);

case 'status_del':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $sid = (int) ($in['id'] ?? 0);
    if (Capsule::table('mod_cpm_tasks')->where('status_id', $sid)->exists()) {
        fail('Η στήλη έχει tasks — μετακίνησέ τα πρώτα');
    }
    if (Capsule::table('mod_cpm_statuses')->count() <= 2) {
        fail('Χρειάζονται τουλάχιστον 2 στήλες');
    }
    Capsule::table('mod_cpm_statuses')->where('id', $sid)->delete();
    out(['ok' => true]);

case 'type_save':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $tid2 = (int) ($in['id'] ?? 0);
    $data = ['name' => mb_substr(trim($in['name'] ?? ''), 0, 60) ?: 'Τύπος',
        'icon' => preg_match('/^fa-[a-z0-9-]+$/', $in['icon'] ?? '') ? $in['icon'] : 'fa-tasks',
        'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $in['color'] ?? '') ? $in['color'] : '#8595ac',
        'req_assignee' => !empty($in['reqA']) ? 1 : 0, 'req_due' => !empty($in['reqD']) ? 1 : 0,
        'req_estimate' => !empty($in['reqE']) ? 1 : 0];
    if ($tid2) {
        Capsule::table('mod_cpm_task_types')->where('id', $tid2)->update($data);
    } else {
        $data['sort'] = 1 + (int) Capsule::table('mod_cpm_task_types')->max('sort');
        $tid2 = Capsule::table('mod_cpm_task_types')->insertGetId($data);
    }
    out(['ok' => true, 'id' => $tid2]);

case 'type_del':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $tid2 = (int) ($in['id'] ?? 0);
    Capsule::table('mod_cpm_tasks')->where('type_id', $tid2)->update(['type_id' => null]);
    Capsule::table('mod_cpm_task_types')->where('id', $tid2)->delete();
    out(['ok' => true]);

/* ================= CANNED RESPONSES ================= */
case 'canned':
    $rows = [];
    foreach (Capsule::table('mod_cpm_canned')->orderBy('sort')->orderBy('id')->get() as $cn) {
        $rows[] = ['id' => (int) $cn->id, 'title' => $cn->title, 'body' => $cn->body];
    }
    out(['canned' => $rows]);

case 'canned_save':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $cid2 = (int) ($in['id'] ?? 0);
    $data = ['title' => mb_substr(trim($in['title'] ?? ''), 0, 80) ?: 'Απάντηση',
        'body' => mb_substr(trim($in['body'] ?? ''), 0, 20000)];
    if ($cid2) {
        Capsule::table('mod_cpm_canned')->where('id', $cid2)->update($data);
    } else {
        $cid2 = Capsule::table('mod_cpm_canned')->insertGetId($data + ['sort' => 0]);
    }
    out(['ok' => true, 'id' => $cid2]);

case 'canned_del':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    Capsule::table('mod_cpm_canned')->where('id', (int) ($in['id'] ?? 0))->delete();
    out(['ok' => true]);

/* ================= AUTOMATIONS ================= */
case 'autos':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $rows = [];
    foreach (Capsule::table('mod_cpm_automations')->orderBy('id')->get() as $a) {
        $rows[] = ['id' => (int) $a->id, 'name' => $a->name, 'trigger' => $a->trigger,
            'tvalue' => $a->tvalue, 'action' => $a->action, 'avalue' => $a->avalue,
            'active' => (bool) $a->active];
    }
    // λίστες για τα dropdowns
    $stagesL = [];
    foreach (Db::leadStages() as $k => $m) {
        $stagesL[] = ['key' => $k, 'title' => $m[0]];
    }
    out(['autos' => $rows,
        'ticketStatuses' => Capsule::table('tblticketstatuses')->orderBy('sortorder')->pluck('title')->all(),
        'leadStages' => $stagesL]);

case 'auto_save':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $aid3 = (int) ($in['id'] ?? 0);
    $data = ['name' => mb_substr(trim($in['name'] ?? ''), 0, 120) ?: 'Κανόνας',
        'trigger' => in_array($in['trigger'] ?? '', ['task_status', 'ticket_status', 'lead_stage', 'sla_breach'], true) ? $in['trigger'] : 'task_status',
        'tvalue' => mb_substr(trim((string) ($in['tvalue'] ?? '')), 0, 60) ?: null,
        'action' => in_array($in['action'] ?? '', ['assign_task', 'ball', 'set_prio', 'notify', 'assign_ticket', 'escalate'], true) ? $in['action'] : 'notify',
        'avalue' => mb_substr(trim((string) ($in['avalue'] ?? '')), 0, 60) ?: null,
        'active' => !empty($in['active']) ? 1 : 0];
    if ($aid3) {
        Capsule::table('mod_cpm_automations')->where('id', $aid3)->update($data);
    } else {
        $data['created_at'] = date('Y-m-d H:i:s');
        $aid3 = Capsule::table('mod_cpm_automations')->insertGetId($data);
    }
    out(['ok' => true, 'id' => $aid3]);

case 'auto_del':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    Capsule::table('mod_cpm_auto_log')->where('auto_id', (int) ($in['id'] ?? 0))->delete();
    Capsule::table('mod_cpm_automations')->where('id', (int) ($in['id'] ?? 0))->delete();
    out(['ok' => true]);

/* ================= AI (Claude) ================= */
case 'ai_proofread':                     // ✨ ορθογραφικός/συντακτικός έλεγχος κειμένου (κάθε editor)
    $keyP = (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', 'ai_api_key')->value('value') ?: '');
    if ($keyP === '') {
        fail('Δεν έχει οριστεί AI API key — βάλ\' το στις Ρυθμίσεις');
    }
    $srcP = cnp_clean_html($in['html'] ?? '', 20000);
    if (trim(strip_tags($srcP)) === '') {
        fail('Δεν υπάρχει κείμενο για έλεγχο');
    }
    $modeP = ($in['mode'] ?? 'fix') === 'polish' ? 'polish' : 'fix';
    $rulesP = $modeP === 'polish'
        ? "Διόρθωσε ορθογραφία, τονισμό, στίξη και σύνταξη ΚΑΙ βελτίωσε τη ροή/σαφήνεια, κρατώντας το ίδιο νόημα και ύφος."
        : "Διόρθωσε ΜΟΝΟ ορθογραφία, τονισμό, στίξη και προφανή συντακτικά λάθη. ΜΗΝ αλλάξεις ύφος, λεξιλόγιο ή δομή.";
    $promptP = "Είσαι επιμελητής ελληνικών (και αγγλικών) κειμένων για επαγγελματικό εργαλείο υποστήριξης.\n"
        . $rulesP . "\n\nΚΑΝΟΝΕΣ:\n"
        . "- Το κείμενο είναι HTML. Διατήρησε ΑΚΡΙΒΩΣ τα ίδια tags (<b>, <ul>, <li>, <p>, <br>, <a href>…). Μην προσθέσεις/αφαιρέσεις tags.\n"
        . "- Μην μεταφράσεις. Κράτα τη γλώσσα του πρωτοτύπου.\n"
        . "- Μην πειράξεις ονόματα, IP, domain, κωδικούς, εντολές, αριθμούς ticket.\n"
        . "- Αν το κείμενο είναι ήδη σωστό, επίστρεψε το ίδιο και κενή λίστα αλλαγών.\n\n"
        . "Απάντησε ΜΟΝΟ με JSON:\n"
        . '{"html":"<το διορθωμένο HTML>","changes":[{"from":"λάθος","to":"σωστό","why":"σύντομη αιτία"}],"summary":"μία φράση"}'
        . "\n\nΚΕΙΜΕΝΟ:\n" . $srcP;
    $resP = cnp_anthropic($keyP, 'claude-haiku-4-5-20251001', $promptP, 4000);
    if (empty($resP['ok'])) {
        fail($resP['error']);
    }
    $jP = cnp_json_extract($resP['text']);
    if (!$jP || !isset($jP['html'])) {
        fail('Η απάντηση του AI δεν διαβάστηκε');
    }
    $changesP = [];
    foreach (array_slice((array) ($jP['changes'] ?? []), 0, 40) as $ch) {
        $changesP[] = ['from' => mb_substr((string) ($ch['from'] ?? ''), 0, 120),
            'to' => mb_substr((string) ($ch['to'] ?? ''), 0, 120),
            'why' => mb_substr((string) ($ch['why'] ?? ''), 0, 120)];
    }
    out(['ok' => true, 'html' => cnp_clean_html($jP['html'], 60000),
        'changes' => $changesP, 'summary' => mb_substr((string) ($jP['summary'] ?? ''), 0, 200),
        'clean' => count($changesP) === 0]);

case 'ai_suggest':
case 'ai_summary':
    $tid3 = (int) ($in['ticket'] ?? 0);
    $tk3 = Capsule::table('tbltickets')->where('id', $tid3)->first();
    if (!$tk3) {
        fail('ticket', 404);
    }
    $key = (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', 'ai_api_key')->value('value') ?: '');
    if ($key === '') {
        fail('Δεν έχει οριστεί AI API key — βάλ\' το στις Ρυθμίσεις');
    }
    // στήσε τη συνομιλία
    $convTxt = "ΠΕΛΑΤΗΣ (" . ($tk3->name ?: 'πελάτης') . "): " . mb_substr($tk3->message, 0, 3000) . "\n";
    foreach (Capsule::table('tblticketreplies')->where('tid', $tid3)->orderBy('id')->limit(20)->get() as $r) {
        $who = ($r->admin !== '' && $r->admin !== null) ? 'ΟΜΑΔΑ (' . $r->admin . ')' : 'ΠΕΛΑΤΗΣ';
        $convTxt .= $who . ': ' . mb_substr($r->message, 0, 2000) . "\n";
    }
    // 🧠 RAG: τροφοδότησε το AI με τη δική μας γνώση (τράπεζα λύσεων + παρόμοια λυμένα tickets)
    $ragTxt = '';
    if ($action === 'ai_suggest') {
        try {
            $tw3 = cnp_words($tk3->title . ' ' . mb_substr($tk3->message, 0, 600));
            $kbCtx = [];
            foreach (Capsule::table('mod_cpm_kb')->get() as $k9) {
                $sc = cnp_overlap($tw3, cnp_words($k9->title . ' ' . $k9->keywords . ' ' . $k9->tags));
                if ($sc > 0) {
                    $kbCtx[] = [$sc, "ΛΥΣΗ ΑΠΟ ΤΗ ΒΑΣΗ ΓΝΩΣΗΣ «{$k9->title}»:\n" . mb_substr($k9->solution, 0, 800)];
                }
            }
            usort($kbCtx, function ($a, $b) { return $b[0] <=> $a[0]; });
            foreach (array_slice($kbCtx, 0, 2) as $x) {
                $ragTxt .= $x[1] . "\n\n";
            }
            foreach (Capsule::table('tbltickets')->where('id', '!=', $tid3)->where('status', 'Closed')
                ->orderBy('lastreply', 'desc')->limit(300)->get(['id', 'title']) as $t9) {
                if (cnp_overlap($tw3, cnp_words($t9->title)) > 0) {
                    $fix = Capsule::table('tblticketreplies')->where('tid', $t9->id)
                        ->where('admin', '!=', '')->orderBy('id', 'desc')->value('message');
                    if ($fix) {
                        $ragTxt .= "ΕΤΣΙ ΛΥΣΑΜΕ ΠΑΡΟΜΟΙΟ ΠΕΡΙΣΤΑΤΙΚΟ («{$t9->title}»):\n" . mb_substr($fix, 0, 700) . "\n\n";
                    }
                    if (mb_strlen($ragTxt) > 3500) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
        }
    }
    /* Τελευταίο μήνυμα ΠΕΛΑΤΗ — αυτό ορίζει τη γλώσσα της απάντησης.
       Η συνομιλία μπορεί να αλλάξει γλώσσα στην πορεία, οπότε μετράει το
       τελευταίο που έγραψε ο ίδιος, όχι το αρχικό. */
    $lastCust = (string) $tk3->message;
    foreach (Capsule::table('tblticketreplies')->where('tid', $tid3)->orderBy('id')->get() as $r9) {
        if ($r9->admin === '' || $r9->admin === null) {
            $lastCust = (string) $r9->message;
        }
    }
    $langHint = cnp_lang_hint($lastCust);

    $prompt = $action === 'ai_suggest'
        ? "Είσαι ο βοηθός υποστήριξης της CloudOn (ελληνική εταιρεία IT/hosting). Με βάση τη συνομιλία του ticket"
            . ($ragTxt !== '' ? " ΚΑΙ τις παρακάτω λύσεις από το ιστορικό μας (χρησιμοποίησέ τες αν ταιριάζουν — έτσι δουλεύουμε εμείς)" : '')
            . ", γράψε ΜΙΑ επαγγελματική, φιλική απάντηση προς τον πελάτη, έτοιμη για αποστολή."
            . " Χωρίς placeholders, χωρίς υπογραφή. Συνοπτική και επί της ουσίας.\n\n"
            . "ΓΛΩΣΣΑ — ΚΡΙΣΙΜΟ: γράψε ΑΠΟΚΛΕΙΣΤΙΚΑ στη γλώσσα που χρησιμοποίησε ο πελάτης"
            . " στο τελευταίο του μήνυμα. Αν έγραψε αγγλικά, απάντησε αγγλικά. Αν έγραψε"
            . " ελληνικά, απάντησε ελληνικά. Αν έγραψε ελληνικά με λατινικούς χαρακτήρες"
            . " (greeklish), απάντησε σε κανονικά ελληνικά. Μην μεταφράσεις και μη γράψεις"
            . " σε δύο γλώσσες."
            . ($langHint ? " (Αυτόματη ανίχνευση: {$langHint} — αγνόησέ την αν διαφωνείς με το κείμενο.)" : '')
            . "\n\n"
            . ($ragTxt !== '' ? "ΓΝΩΣΗ ΑΠΟ ΤΟ ΙΣΤΟΡΙΚΟ ΜΑΣ:\n$ragTxt\n" : '')
            . "ΘΕΜΑ: {$tk3->title}\n\nΣΥΝΟΜΙΛΙΑ:\n$convTxt\n"
            . "ΤΕΛΕΥΤΑΙΟ ΜΗΝΥΜΑ ΠΕΛΑΤΗ (η γλώσσα του καθορίζει τη γλώσσα σου):\n"
            . mb_substr($lastCust, 0, 1500) . "\n\nΑΠΑΝΤΗΣΗ:"
        // Η σύνοψη είναι για την ΟΜΑΔΑ, όχι για τον πελάτη — μένει στα ελληνικά.
        : "Σύνοψε το παρακάτω ticket υποστήριξης στα ελληνικά σε 3-4 bullet points: τι ζητά ο πελάτης, τι έχει γίνει, τι εκκρεμεί.\n\nΘΕΜΑ: {$tk3->title}\n\nΣΥΝΟΜΙΛΙΑ:\n$convTxt";
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'claude-haiku-4-5-20251001', 'max_tokens' => 1024,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode((string) $resp, true);
    if ($code !== 200 || empty($j['content'][0]['text'])) {
        fail('AI: ' . ($j['error']['message'] ?? ('HTTP ' . $code)));
    }
    out(['ok' => true, 'text' => $j['content'][0]['text']]);

/* ================= ΠΡΟΣΩΠΑ & CRM ΠΕΔΙΑ (Κ5) ================= */
case 'people':
    $rows = [];
    foreach (Db::peopleFor((int) ($_GET['lead'] ?? 0), (int) ($_GET['client'] ?? 0)) as $p) {
        $rows[] = ['id' => (int) $p->id, 'name' => $p->name, 'email' => $p->email,
            'phone' => $p->phone, 'title' => $p->title, 'notes' => $p->notes];
    }
    out(['people' => $rows]);

case 'person_save':
    $pid5 = (int) ($in['id'] ?? 0);
    $data = ['name' => mb_substr(trim($in['name'] ?? ''), 0, 120) ?: 'Χωρίς όνομα',
        'email' => mb_substr(trim($in['email'] ?? ''), 0, 120) ?: null,
        'phone' => mb_substr(trim($in['phone'] ?? ''), 0, 40) ?: null,
        'title' => mb_substr(trim($in['title'] ?? ''), 0, 80) ?: null,
        'notes' => cnp_clean_html($in['notes'] ?? '') ?: null];
    if (!$pid5) {
        $data['lead_id'] = (int) ($in['lead'] ?? 0) ?: null;
        $data['clientid'] = (int) ($in['client'] ?? 0) ?: null;
        if (!$data['lead_id'] && !$data['clientid']) {
            fail('χρειάζεται lead ή client');
        }
    }
    out(['ok' => true, 'id' => Db::savePerson($pid5, $data)]);

case 'person_del':
    Db::delPerson((int) ($in['id'] ?? 0));
    out(['ok' => true]);

case 'lead_fields':
    $flds = [];
    foreach (Db::leadFields() as $f5) {
        $flds[] = ['id' => (int) $f5->id, 'label' => $f5->label, 'type' => $f5->type,
            'options' => $f5->options ? array_values(array_filter(array_map('trim', explode("\n", $f5->options)))) : []];
    }
    $vals = ($_GET['lead'] ?? 0) ? Db::leadValues((int) $_GET['lead']) : [];
    out(['fields' => $flds, 'values' => $vals]);

case 'lead_field_save':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    $fid5 = (int) ($in['id'] ?? 0);
    $type = in_array($in['type'] ?? '', ['text', 'select', 'date'], true) ? $in['type'] : 'text';
    $data = ['label' => mb_substr(trim($in['label'] ?? ''), 0, 60) ?: 'Πεδίο', 'type' => $type,
        'options' => $type === 'select' ? implode("\n", array_filter(array_map('trim', explode('|', $in['options'] ?? '')))) : null];
    if ($fid5) {
        Capsule::table('mod_cpm_lead_fields')->where('id', $fid5)->update($data);
    } else {
        $data['sort'] = 0;
        $fid5 = Capsule::table('mod_cpm_lead_fields')->insertGetId($data);
    }
    out(['ok' => true, 'id' => $fid5]);

case 'lead_field_del':
    if (!$FULL) {
        fail('forbidden', 403);
    }
    Capsule::table('mod_cpm_lead_values')->where('field_id', (int) ($in['id'] ?? 0))->delete();
    Capsule::table('mod_cpm_lead_fields')->where('id', (int) ($in['id'] ?? 0))->delete();
    out(['ok' => true]);

case 'lead_value_save':
    $lid5 = (int) ($in['lead'] ?? 0);
    $l5 = Db::lead($lid5);
    if (!$l5 || (!$FULL && (int) $l5->assignee !== $adminId && (int) $l5->created_by !== $adminId)) {
        fail('lead', 403);
    }
    Db::saveLeadValue($lid5, (int) ($in['field'] ?? 0), mb_substr((string) ($in['value'] ?? ''), 0, 2000));
    out(['ok' => true]);

/* ================= GANTT / ΔΙΑΘΕΣΙΜΟΤΗΤΑ ΟΜΑΔΑΣ ================= */
case 'gantt':
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-7 days'));
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d', strtotime('+35 days'));
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $rows = Capsule::table('mod_cpm_tasks as t')
        ->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
        ->select('t.*', 'p.name as pname', 'p.color as pcolor')
        ->whereNotIn('t.status_id', $doneIds)
        ->where(function ($w) {
            $w->whereNotNull('t.start_date')->orWhereNotNull('t.due_date')->orWhereNotNull('t.schedule_date');
        })->get();
    $tasks = [];
    $load = [];      // ανά χειριστή ανά ημέρα (λεπτά)
    $projIds = [];
    foreach ($rows as $t) {
        if (!$FULL && (int) $t->assignee !== $adminId && !Db::canSeeProject($adminId, $t->project_id)) {
            continue;
        }
        $start = $t->start_date ?: ($t->schedule_date ?: $t->due_date);
        $end = $t->due_date ?: $start;
        if ($end < $start) {
            $end = $start;
        }
        if ($end < $from || $start > $to) {
            continue;
        }
        $est = (int) $t->estimate_minutes;
        $days = [];
        for ($d = $start; $d <= $end; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            if ((int) date('N', strtotime($d)) <= 5) {
                $days[] = $d;
            }
        }
        $perDay = $est > 0 && count($days) ? $est / count($days) : ($est === 0 ? 60 : 0);
        $aKey = (int) $t->assignee;
        foreach ($days as $d) {
            if ($d >= $from && $d <= $to && $aKey) {
                $load[$aKey][$d] = ($load[$aKey][$d] ?? 0) + $perDay;
            }
        }
        $projIds[(int) $t->project_id] = 1;
        $tasks[] = ['id' => (int) $t->id, 'title' => $t->title, 'project' => (int) $t->project_id, '_bid' => (int) $t->id,
            'assignee' => $aKey ?: null, 'start' => $start, 'end' => $end,
            'color' => $t->pcolor, 'pname' => $t->pname, 'prio' => (int) $t->priority,
            'est' => $est ?: null, 'ticket' => $t->ticketid ? (int) $t->ticketid : null];
    }
    // projects δέντρο (όσα έχουν tasks + οι γονείς τους)
    $projects = [];
    $all = $FULL ? Db::projects(true) : Db::projectsFor($adminId, true);
    $byId = [];
    foreach ($all as $p) {
        $byId[(int) $p->id] = $p;
    }
    foreach (array_keys($projIds) as $pidG) {
        if (isset($byId[$pidG]) && $byId[$pidG]->parent_id) {
            $projIds[(int) $byId[$pidG]->parent_id] = 1; // φέρε και τον γονιό
        }
    }
    foreach ($all as $p) {
        if (!isset($projIds[(int) $p->id])) {
            continue;
        }
        $projects[] = ['id' => (int) $p->id, 'name' => $p->name, 'color' => $p->color,
            'parent' => $p->parent_id ? (int) $p->parent_id : null,
            'client' => clientLabel($p->clientid)];
    }
    $gBlocked = Db::blockedMap(array_column($tasks, '_bid'));
    foreach ($tasks as &$gt) {
        $gt['blocked'] = isset($gBlocked[$gt['_bid']]) ? count($gBlocked[$gt['_bid']]) : 0;
        unset($gt['_bid']);
    }
    unset($gt);
    // 🌴 άδειες: ανά admin ποιες μέρες λείπει (για τη ζώνη διαθεσιμότητας)
    $leaves = [];
    try {
        foreach (Capsule::table('mod_cpm_events')->where('kind', 'leave')
            ->where('start_dt', '<=', $to . ' 23:59:59')->where('end_dt', '>=', $from . ' 00:00:00')->get() as $lv) {
            foreach (array_filter(array_map('intval', explode(',', $lv->attendees))) as $a) {
                $d0 = max(strtotime(substr($lv->start_dt, 0, 10)), strtotime($from));
                $d1 = min(strtotime(substr($lv->end_dt, 0, 10)), strtotime($to));
                for ($d = $d0; $d <= $d1; $d += 86400) {
                    $leaves[$a][date('Y-m-d', $d)] = true;
                }
            }
        }
    } catch (\Throwable $e) {
    }
    out(['from' => $from, 'to' => $to, 'projects' => $projects, 'tasks' => $tasks, 'load' => $load, 'leaves' => $leaves]);

case 'gantt_move':
    $t6 = Db::task((int) ($in['task'] ?? 0));
    if (!$t6 || !Db::canSeeTask($adminId, $t6)) {
        fail('task', 403);
    }
    $st6 = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['start'] ?? '') ? $in['start'] : null;
    $en6 = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['end'] ?? '') ? $in['end'] : null;
    if (!$st6 || !$en6 || $en6 < $st6) {
        fail('dates');
    }
    Db::saveTask($t6->id, ['start_date' => $st6, 'due_date' => $en6], $adminId);
    Db::logActivity($t6->id, $adminId, 'edit', 'Gantt: ' . $st6 . ' → ' . $en6);
    out(['ok' => true]);

/* ================= ΕΞΑΡΤΗΣΕΙΣ + ΑΡΧΕΙΑ ================= */
case 'dep_add':
    $t7 = Db::task((int) ($in['task'] ?? 0));
    if (!$t7 || !Db::canSeeTask($adminId, $t7)) {
        fail('task', 403);
    }
    $ok7 = Db::addDep($t7->id, (int) ($in['on'] ?? 0));
    out(['ok' => (bool) $ok7]);

case 'dep_del':
    Db::delDep((int) ($in['id'] ?? 0));
    out(['ok' => true]);

/* Τα task attachments ενοποιήθηκαν στο γενικό Storage layer (file_* endpoints, module=task).
   Τα παλιά cases files/file_upload/file_get/file_del (mod_cpm_files, local) αφαιρέθηκαν. */

/* ---- realtime version (ελαφρύ polling — Κ6) ---- */
/* ============ 🎥 CLOUDON MEET (WebRTC signaling) ============ */
case 'rtc_join':
    $room = preg_replace('/[^a-zA-Z0-9\-]/', '', $in['room'] ?? '');
    if ($room === '' || ($adminId <= 0 && $MEET_ROOM !== $room)) {
        fail('room', 403);
    }
    $peer = substr(bin2hex(random_bytes(8)), 0, 12);
    $name = $adminId > 0 ? Db::adminName($adminId) : (mb_substr(trim($in['name'] ?? ''), 0, 60) ?: 'Επισκέπτης');
    // καθάρισμα: πεθαμένοι peers + παλιά μηνύματα
    Capsule::table('mod_cpm_rtc_peers')->where('last_seen', '<', date('Y-m-d H:i:s', time() - 40))->delete();
    Capsule::table('mod_cpm_rtc_msgs')->where('created_at', '<', date('Y-m-d H:i:s', time() - 600))->delete();
    Capsule::table('mod_cpm_rtc_peers')->insert(['room' => $room, 'peer' => $peer, 'name' => $name,
        'admin_id' => $adminId > 0 ? $adminId : null, 'last_seen' => date('Y-m-d H:i:s')]);
    $roster = [];
    foreach (Capsule::table('mod_cpm_rtc_peers')->where('room', $room)->where('peer', '!=', $peer)->get() as $p9) {
        $roster[] = ['peer' => $p9->peer, 'name' => $p9->name];
    }
    out(['peer' => $peer, 'name' => $name, 'roster' => $roster]);

case 'rtc_signal':
    $room = preg_replace('/[^a-zA-Z0-9\-]/', '', $in['room'] ?? '');
    if ($room === '' || ($adminId <= 0 && $MEET_ROOM !== $room)) {
        fail('room', 403);
    }
    $kind = in_array($in['kind'] ?? '', ['offer', 'answer', 'ice', 'bye'], true) ? $in['kind'] : null;
    if (!$kind || empty($in['peer']) || empty($in['to'])) {
        fail('input');
    }
    Capsule::table('mod_cpm_rtc_msgs')->insert(['room' => $room,
        'to_peer' => preg_replace('/[^a-f0-9]/', '', $in['to']),
        'from_peer' => preg_replace('/[^a-f0-9]/', '', $in['peer']),
        'kind' => $kind, 'payload' => mb_substr((string) ($in['payload'] ?? ''), 0, 200000),
        'created_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true]);

case 'rtc_poll':
    $room = preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['room'] ?? '');
    if ($room === '' || ($adminId <= 0 && $MEET_ROOM !== $room)) {
        fail('room', 403);
    }
    $peer = preg_replace('/[^a-f0-9]/', '', $_GET['peer'] ?? '');
    Capsule::table('mod_cpm_rtc_peers')->where('room', $room)->where('peer', $peer)
        ->update(['last_seen' => date('Y-m-d H:i:s')]);
    $after = (int) ($_GET['after'] ?? 0);
    $msgs = [];
    foreach (Capsule::table('mod_cpm_rtc_msgs')->where('room', $room)->where('to_peer', $peer)
        ->where('id', '>', $after)->orderBy('id')->limit(100)->get() as $m9) {
        $msgs[] = ['id' => (int) $m9->id, 'from' => $m9->from_peer, 'kind' => $m9->kind, 'payload' => $m9->payload];
    }
    $roster = [];
    foreach (Capsule::table('mod_cpm_rtc_peers')->where('room', $room)
        ->where('last_seen', '>', date('Y-m-d H:i:s', time() - 30))->get() as $p9) {
        $roster[] = ['peer' => $p9->peer, 'name' => $p9->name];
    }
    out(['messages' => $msgs, 'roster' => $roster]);

case 'rtc_leave':
    $room = preg_replace('/[^a-zA-Z0-9\-]/', '', $in['room'] ?? '');
    $peer = preg_replace('/[^a-f0-9]/', '', $in['peer'] ?? '');
    Capsule::table('mod_cpm_rtc_peers')->where('room', $room)->where('peer', $peer)->delete();
    out(['ok' => true]);

case 'rtc_invite':                      // πρόσκληση ΚΑΤΑ ΤΗ ΔΙΑΡΚΕΙΑ του meeting (μόνο ομάδα)
    if ($adminId <= 0) {
        fail('perm', 403);
    }
    $room = preg_replace('/[^a-zA-Z0-9\-]/', '', $in['room'] ?? '');
    if ($room === '') {
        fail('room');
    }
    $url9 = 'https://my.cloudon.gr/projectmanagement/meet.php?room=' . $room . '&t=' . pm_mint_meet($room);
    $byName = Db::adminName($adminId);
    $body9 = '<p><strong>' . htmlspecialchars($byName) . '</strong> σας προσκαλεί σε meeting <strong>που είναι σε εξέλιξη τώρα</strong>.</p>'
        . '<p style="background:#eef7fd;border-left:4px solid #0090dd;padding:10px 14px;">'
        . '🎥 <a href="' . htmlspecialchars($url9) . '"><strong>Συμμετοχή στο meeting</strong></a></p>'
        . '<p>Ανοίγει απευθείας στον browser — χωρίς εγκατάσταση ή λογαριασμό.</p>';
    if (!empty($in['admin'])) {
        $to9 = (int) $in['admin'];
        Db::pushNotification($to9, 'action', '🎥 ' . $byName . ' σε καλεί ΤΩΡΑ σε meeting!', $url9);
        \WHMCS\Module\Addon\CloudonProjects\Notify::send($to9, '🎥 Σε καλούν σε meeting ΤΩΡΑ', $body9);
        out(['ok' => true, 'sent' => Db::adminName($to9)]);
    }
    if (!empty($in['email']) && filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
        \WHMCS\Module\Addon\CloudonProjects\Notify::sendTo(trim($in['email']), '🎥 Πρόσκληση σε meeting σε εξέλιξη — CloudOn', $body9);
        out(['ok' => true, 'sent' => trim($in['email'])]);
    }
    out(['ok' => true, 'url' => $url9]);

case 'meet_room':                       // δημιουργία δωματίου + guest URL
    $room = 'm' . substr(bin2hex(random_bytes(6)), 0, 10);
    $base = 'https://my.cloudon.gr/projectmanagement/meet.php';
    out(['room' => $room,
        'url' => $base . '?room=' . $room . '&t=' . pm_mint_meet($room)]);

/* ============ ✔ RSVP MEETINGS ============ */
case 'event_rsvp':                      // απάντηση μέλους ομάδας
    $ev = Capsule::table('mod_cpm_events')->where('id', (int) ($in['id'] ?? 0))->first();
    if (!$ev) {
        fail('event', 404);
    }
    $att9 = array_filter(array_map('intval', explode(',', $ev->attendees)));
    if (!in_array($adminId, $att9, true)) {
        fail('Δεν είσαι στους συμμετέχοντες', 403);
    }
    $st9 = ($in['status'] ?? '') === 'declined' ? 'declined' : 'accepted';
    Capsule::table('mod_cpm_event_rsvp')->updateOrInsert(
        ['event_id' => $ev->id, 'kind' => 'admin', 'ref' => $adminId],
        ['status' => $st9, 'responded_at' => date('Y-m-d H:i:s')]);
    if ((int) $ev->created_by !== $adminId) {
        Db::pushNotification($ev->created_by, 'info',
            ($st9 === 'accepted' ? '✅ ' : '❌ ') . Db::adminName($adminId)
            . ($st9 === 'accepted' ? ' αποδέχθηκε' : ' δεν μπορεί') . ': ' . $ev->title,
            '/projectmanagement/#/calendar');
    }
    out(['ok' => true, 'status' => $st9]);

case 'event_rsvp_public':               // απάντηση πελάτη από το email (χωρίς login)
    $v9 = pm_verify_rsvp($_GET['t'] ?? '');
    if (!$v9) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:sans-serif;text-align:center;padding:60px 20px"><h2>⏰ Ο σύνδεσμος έληξε</h2><p>Επικοινωνήστε μαζί μας για νέο.</p></body>';
        exit;
    }
    [$evId9, $cid9] = $v9;
    $ev = Capsule::table('mod_cpm_events')->where('id', $evId9)->first();
    $st9 = ($_GET['r'] ?? '') === 'decline' ? 'declined' : 'accepted';
    if ($ev && (int) $ev->clientid === $cid9) {
        Capsule::table('mod_cpm_event_rsvp')->updateOrInsert(
            ['event_id' => $evId9, 'kind' => 'client', 'ref' => $cid9],
            ['status' => $st9, 'responded_at' => date('Y-m-d H:i:s')]);
        Db::pushNotification($ev->created_by, 'info',
            ($st9 === 'accepted' ? '✅ Ο πελάτης αποδέχθηκε' : '❌ Ο πελάτης ΔΕΝ μπορεί') . ': ' . $ev->title,
            '/projectmanagement/#/calendar');
        if (function_exists('logActivity')) {
            logActivity('CPM: RSVP πελάτη #' . $cid9 . ' → ' . $st9 . ' (event #' . $evId9 . ')');
        }
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:sans-serif;text-align:center;padding:60px 20px;background:#f4f6fa">'
        . '<div style="max-width:440px;margin:0 auto;background:#fff;border-radius:16px;padding:36px 28px;box-shadow:0 8px 30px rgba(16,42,67,.12)">'
        . ($st9 === 'accepted'
            ? '<div style="font-size:52px">✅</div><h2 style="color:#152238">Ευχαριστούμε!</h2><p style="color:#44566c">Η συμμετοχή σας επιβεβαιώθηκε' . ($ev ? ' για <b>' . htmlspecialchars($ev->title) . '</b><br>🗓 ' . date('d/m/Y H:i', strtotime($ev->start_dt)) : '') . '.</p>'
            : '<div style="font-size:52px">📅</div><h2 style="color:#152238">Καταγράφηκε</h2><p style="color:#44566c">Λάβαμε ότι δεν σας εξυπηρετεί η ώρα — θα επικοινωνήσουμε για εναλλακτική.</p>')
        . '<p style="color:#8291a9;font-size:13px">CloudOn — Innovative e-business solutions</p></div></body>';
    exit;

/* ============ 💬 ΕΣΩΤΕΡΙΚΟ CHAT ============ */
case 'chat_group_save':                 // δημιουργία ομάδας συνομιλίας
    $gname = mb_substr(trim($in['name'] ?? ''), 0, 80);
    $gmem = array_values(array_unique(array_merge([$adminId],
        array_filter(array_map('intval', (array) ($in['members'] ?? []))))));
    if ($gname === '' || count($gmem) < 2) {
        fail('Όνομα και τουλάχιστον ένα ακόμη μέλος');
    }
    $gid = Capsule::table('mod_cpm_chat_groups')->insertGetId(['name' => $gname,
        'members' => ',' . implode(',', $gmem) . ',',
        'created_by' => $adminId, 'created_at' => date('Y-m-d H:i:s')]);
    foreach ($gmem as $gm) {
        if ($gm !== $adminId) {
            Db::pushNotification($gm, 'info', '💬 Προστέθηκες στην ομάδα «' . $gname . '»', '/projectmanagement/#/chat');
        }
    }
    out(['ok' => true, 'id' => $gid]);

case 'chat_group_del':                  // διαγραφή/αποχώρηση
    $gr = Capsule::table('mod_cpm_chat_groups')->where('id', (int) ($in['id'] ?? 0))->first();
    if (!$gr) {
        fail('group', 404);
    }
    if ($FULL || (int) $gr->created_by === $adminId) {
        Capsule::table('mod_cpm_chat_groups')->where('id', $gr->id)->delete();
        Capsule::table('mod_cpm_chat')->where('channel', 'g' . $gr->id)->delete();
        out(['ok' => true, 'deleted' => true]);
    }
    // απλό μέλος: αποχωρεί
    $mem = array_values(array_diff(array_filter(array_map('intval', explode(',', $gr->members))), [$adminId]));
    Capsule::table('mod_cpm_chat_groups')->where('id', $gr->id)
        ->update(['members' => ',' . implode(',', $mem) . ',']);
    out(['ok' => true, 'left' => true]);

case 'chat_channels':
    Db::setPref($adminId, 'last_seen', (string) time());
    $now6 = time();
    $reads = [];
    foreach (Capsule::table('mod_cpm_chat_reads')->where('admin_id', $adminId)->get() as $r6) {
        $reads[$r6->channel] = (int) $r6->last_id;
    }
    $chans = [['id' => 'team', 'name' => '# Ομάδα', 'kind' => 'team',
        'unread' => Capsule::table('mod_cpm_chat')->where('channel', 'team')
            ->where('id', '>', $reads['team'] ?? 0)->where('admin_id', '!=', $adminId)->count()]];
    foreach (Capsule::table('mod_cpm_chat_groups')
        ->where('members', 'like', '%,' . $adminId . ',%')->orderBy('name')->get() as $g6) {
        $ch = 'g' . $g6->id;
        $chans[] = ['id' => $ch, 'name' => $g6->name, 'kind' => 'group',
            'groupId' => (int) $g6->id, 'mine' => (int) $g6->created_by === $adminId,
            'members' => count(array_filter(explode(',', $g6->members))),
            'unread' => Capsule::table('mod_cpm_chat')->where('channel', $ch)
                ->where('id', '>', $reads[$ch] ?? 0)->where('admin_id', '!=', $adminId)->count()];
    }
    foreach (Db::admins() as $a6) {
        if ((int) $a6->id === $adminId) {
            continue;
        }
        $ch = 'd' . min($adminId, (int) $a6->id) . '-' . max($adminId, (int) $a6->id);
        $seen = (int) Db::pref($a6->id, 'last_seen', '0');
        $manual = Db::pref($a6->id, 'chat_status', 'online');
        $status = $manual === 'offline' ? 'offline' : (($now6 - $seen) < 90 ? 'online' : 'away');
        $chans[] = ['id' => $ch, 'name' => trim($a6->firstname . ' ' . $a6->lastname),
            'kind' => 'dm', 'admin' => (int) $a6->id, 'status' => $status,
            'reason' => $manual === 'offline' ? Db::pref($a6->id, 'chat_reason', '') : '',
            'unread' => Capsule::table('mod_cpm_chat')->where('channel', $ch)
                ->where('id', '>', $reads[$ch] ?? 0)->where('admin_id', '!=', $adminId)->count()];
    }
    out(['channels' => $chans, 'myStatus' => Db::pref($adminId, 'chat_status', 'online'),
        'myReason' => Db::pref($adminId, 'chat_reason', '')]);

case 'chat_msgs':
    $ch = preg_replace('/[^a-z0-9\-]/', '', $_GET['channel'] ?? 'team');
    if (!cnp_chat_access($ch, $adminId)) {
        fail('channel', 403);
    }
    Db::setPref($adminId, 'last_seen', (string) time());
    $after = (int) ($_GET['after'] ?? 0);
    $q6 = Capsule::table('mod_cpm_chat')->where('channel', $ch);
    $msgs = $after > 0
        ? $q6->where('id', '>', $after)->orderBy('id')->limit(200)->get()
        : $q6->orderBy('id', 'desc')->limit(60)->get()->reverse()->values();
    $outM = [];
    $maxId = $after;
    foreach ($msgs as $m9) {
        $maxId = max($maxId, (int) $m9->id);
        $outM[] = ['id' => (int) $m9->id, 'by' => (int) $m9->admin_id,
            'body' => $m9->body, 'at' => $m9->created_at,
            'file' => $m9->filename ? ['name' => $m9->filename, 'size' => (int) $m9->size, 'id' => (int) $m9->id,
                'mime' => $m9->mime, 'kind' => Storage::kindFromMime($m9->mime),
                'url' => 'api.php?a=chat_file&id=' . (int) $m9->id] : null];
    }
    // mark read
    if ($maxId > 0) {
        if (Capsule::table('mod_cpm_chat_reads')->where('admin_id', $adminId)->where('channel', $ch)->exists()) {
            Capsule::table('mod_cpm_chat_reads')->where('admin_id', $adminId)->where('channel', $ch)
                ->where('last_id', '<', $maxId)->update(['last_id' => $maxId]);
        } else {
            Capsule::table('mod_cpm_chat_reads')->insert(['admin_id' => $adminId, 'channel' => $ch, 'last_id' => $maxId]);
        }
    }
    out(['messages' => $outM]);

case 'chat_send':
    if (!empty($_FILES)) {
        $in = $_POST;   // multipart με αρχείο
    }
    $ch = preg_replace('/[^a-z0-9\-]/', '', $in['channel'] ?? 'team');
    if (!cnp_chat_access($ch, $adminId)) {
        fail('channel', 403);
    }
    $body = mb_substr(trim($in['body'] ?? ''), 0, 4000);
    $fn = null;
    $sz = null;
    $storageId = null;
    $fmime = null;
    if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['file']['size'] > 50 * 1024 * 1024) {
            fail('Μέγιστο 50MB (για μεγαλύτερα βίντεο χρησιμοποίησε τα Συνημμένα σε task/CV)');
        }
        if (!cnp_file_ext_ok($_FILES['file']['name'])) {
            fail('Μη επιτρεπτός τύπος αρχείου');
        }
        $fn = mb_substr($_FILES['file']['name'], 0, 190);
        $sz = (int) $_FILES['file']['size'];
        $fmime = $_FILES['file']['type'] ?: 'application/octet-stream';
        $rec = Storage::store(['module' => 'chat', 'ref_type' => $ch, 'ref_id' => 0, 'src' => $_FILES['file']['tmp_name'],
            'orig_name' => $fn, 'mime' => $fmime, 'uploaded_by' => $adminId]);
        $storageId = (int) $rec['id'];
    }
    if ($body === '' && !$fn) {
        fail('empty');
    }
    $mid = Capsule::table('mod_cpm_chat')->insertGetId(['channel' => $ch, 'admin_id' => $adminId,
        'body' => $body ?: null, 'filename' => $fn, 'storage_id' => $storageId, 'mime' => $fmime, 'size' => $sz,
        'created_at' => date('Y-m-d H:i:s')]);
    Db::setPref($adminId, 'last_seen', (string) time());
    // καμπανάκι στους παραλήπτες (DM: ο άλλος, ομάδα: όλα τα μέλη) — εκτός offline
    $recips = [];
    if (preg_match('/^d(\d+)-(\d+)$/', $ch, $m6)) {
        $recips = [(int) $m6[1] === $adminId ? (int) $m6[2] : (int) $m6[1]];
    } elseif (preg_match('/^g(\d+)$/', $ch, $m6)) {
        $gr = Capsule::table('mod_cpm_chat_groups')->where('id', (int) $m6[1])->first();
        $recips = array_diff(array_filter(array_map('intval', explode(',', $gr->members ?? ''))), [$adminId]);
        $gname = $gr->name ?? '';
    }
    foreach ($recips as $other) {
        if (Db::pref($other, 'chat_status', 'online') !== 'offline') {
            Db::pushNotification($other, 'comment', '💬 ' . (isset($gname) ? "[$gname] " : '') . Db::adminName($adminId) . ': '
                . mb_substr($body ?: ('📎 ' . $fn), 0, 80), '/projectmanagement/#/chat');
        }
    }
    out(['ok' => true, 'id' => $mid]);

case 'chat_file':                       // κατέβασμα/προβολή συνημμένου chat (auth: μέλος καναλιού)
    $m9 = Capsule::table('mod_cpm_chat')->where('id', (int) ($_GET['id'] ?? 0))->first();
    if (!$m9 || (!$m9->stored && !$m9->storage_id)) {
        fail('file', 404);
    }
    if (!cnp_chat_access($m9->channel, $adminId)) {
        fail('file', 403);
    }
    if ($m9->storage_id) {                                  // νέο: μέσω Storage (S3/local) — κρατά channel auth
        $srec = Storage::record((int) $m9->storage_id);
        if (!$srec) { fail('file', 404); }
        $dl = !empty($_GET['dl']);
        if ($srec['driver'] === 's3') { header('Location: ' . Storage::presign($srec['id'], 300, $dl), true, 302); exit; }
        $stream = Storage::openRead($srec['id']);
        if (!$stream) { fail('file', 404); }
        $mime = $srec['mime'] ?: 'application/octet-stream';
        $preview = ($mime === 'application/pdf' || strpos($mime, 'image/') === 0 || strpos($mime, 'video/') === 0 || strpos($mime, 'audio/') === 0);
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . (($dl || !$preview) ? 'attachment' : 'inline') . '; filename="' . rawurlencode($srec['orig_name'] ?: 'file') . '"');
        if ($srec['size']) { header('Content-Length: ' . (int) $srec['size']); }
        fpassthru($stream); fclose($stream); exit;
    }
    $path = realpath(__DIR__ . '/../attachments/cloudonprojects/' . $m9->stored);
    if (!$path || strpos($path, realpath(__DIR__ . '/../attachments/cloudonprojects') . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
        fail('file', 404);
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $m9->filename . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;

case 'chat_status':                     // Εμφάνιση online/offline (χειροκίνητο) + λόγος
    $off = ($in['status'] ?? '') === 'offline';
    // η σύνδεση DB είναι utf8mb3 — αφαίρεσε 4-byte chars (emoji) για να μη γίνουν «????»
    $reason = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', trim($in['reason'] ?? ''));
    Db::setPref($adminId, 'chat_status', $off ? 'offline' : 'online');
    Db::setPref($adminId, 'chat_reason', $off ? mb_substr(trim($reason), 0, 80) : '');
    out(['ok' => true]);

/* ============ 🏷 ΚΑΤΗΓΟΡΙΟΠΟΙΗΣΗ TICKETS (root-cause) ============ */
case 'ticket_classify':                 // ο διαχειριστής/επικεφαλής ταξινομεί
    if (!cnp_can_reply_clients($adminId, $FULL)) {
        fail('Μόνο διαχειριστής ή επικεφαλής ταξινομεί tickets', 403);
    }
    $tid7 = (int) ($in['ticket'] ?? 0);
    if (!$tid7 || !Capsule::table('tbltickets')->where('id', $tid7)->exists()) {
        fail('ticket', 404);
    }
    $area7 = (int) ($in['area'] ?? 0) ?: null;
    $cause7 = (int) ($in['cause'] ?? 0) ?: null;
    $note7 = cnp_clean_html($in['note'] ?? '');
    Capsule::table('mod_cpm_ticket_class')->updateOrInsert(['ticketid' => $tid7],
        ['area_id' => $area7, 'cause_id' => $cause7,
         'note' => $note7 !== '' ? $note7 : null,
         'classified_by' => $adminId, 'classified_at' => date('Y-m-d H:i:s')]);
    if (function_exists('logActivity')) {
        logActivity('CPM: ticket #' . $tid7 . ' ταξινομήθηκε (area=' . ($area7 ?: '-') . ' cause=' . ($cause7 ?: '-') . ') admin #' . $adminId);
    }
    out(['ok' => true]);

case 'classify_suggest':               // ✨ AI προτείνει area+cause από το περιεχόμενο
    if (!cnp_can_reply_clients($adminId, $FULL)) {
        fail('perm', 403);
    }
    $tid7 = (int) ($in['ticket'] ?? 0);
    $tk7 = Capsule::table('tbltickets')->where('id', $tid7)->first();
    if (!$tk7) {
        fail('ticket', 404);
    }
    $key7 = (string) (Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
        ->where('setting', 'ai_api_key')->value('value') ?: '');
    if ($key7 === '') {
        fail('Δεν έχει οριστεί AI API key στις Ρυθμίσεις');
    }
    $cats7 = cnp_ticket_cats();
    $areaList = implode(', ', array_map(function ($a) { return $a['id'] . '=' . $a['name']; }, $cats7['area']));
    $causeList = implode(', ', array_map(function ($c) { return $c['id'] . '=' . $c['name']; }, $cats7['cause']));
    $conv7 = mb_substr($tk7->title . "\n" . $tk7->message, 0, 2500);
    foreach (Capsule::table('tblticketreplies')->where('tid', $tid7)->orderBy('id')->limit(8)->get() as $r) {
        $conv7 .= "\n" . mb_substr($r->message, 0, 800);
    }
    $prompt7 = "Είσαι ταξινομητής tickets IT εταιρείας. Διάλεξε την ΚΑΛΥΤΕΡΗ Περιοχή/Προϊόν και Ρίζα-προβλήματος από τις λίστες. "
        . "Απάντησε ΜΟΝΟ με JSON χωρίς markdown: {\"area_id\": <id ή 0>, \"cause_id\": <id ή 0>}.\n\n"
        . "ΠΕΡΙΟΧΕΣ: $areaList\nΡΙΖΕΣ: $causeList\n\nTICKET:\n$conv7";
    $ch7 = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch7, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $key7, 'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => json_encode(['model' => 'claude-haiku-4-5-20251001', 'max_tokens' => 100,
            'messages' => [['role' => 'user', 'content' => $prompt7]]])]);
    $j7 = json_decode((string) curl_exec($ch7), true);
    curl_close($ch7);
    $txt7 = $j7['content'][0]['text'] ?? '';
    $sug7 = json_decode(trim(preg_replace('/^```json|```$/m', '', trim($txt7))), true);
    out(['ok' => true, 'area' => (int) ($sug7['area_id'] ?? 0) ?: null, 'cause' => (int) ($sug7['cause_id'] ?? 0) ?: null]);

case 'kb_match':                        // 📚 άρθρα γνώσης που ταιριάζουν σε area/cause
    $terms = trim($_GET['q'] ?? '');
    if ($terms === '') {
        out(['items' => []]);
    }
    $qw = cnp_words($terms);
    $hits = [];
    foreach (Capsule::table('mod_cpm_kb')->get() as $k) {
        $sc = cnp_overlap($qw, cnp_words($k->title . ' ' . $k->keywords . ' ' . $k->tags));
        if ($sc > 0) {
            $hits[] = ['id' => (int) $k->id, 'title' => $k->title, 'solution' => $k->solution, 'score' => $sc];
        }
    }
    usort($hits, function ($a, $b) { return $b['score'] <=> $a['score']; });
    out(['items' => array_slice($hits, 0, 4)]);

case 'tcats':                           // λίστα κατηγοριών (+ πλήθος για διαχείριση)
    if (!$FULL) {
        fail('perm', 403);
    }
    $counts = [];
    foreach (Capsule::table('mod_cpm_ticket_class')
        ->select(Capsule::raw('area_id, cause_id'))->get() as $cl) {
        if ($cl->area_id) { $counts['a' . $cl->area_id] = ($counts['a' . $cl->area_id] ?? 0) + 1; }
        if ($cl->cause_id) { $counts['c' . $cl->cause_id] = ($counts['c' . $cl->cause_id] ?? 0) + 1; }
    }
    $out7 = ['area' => [], 'cause' => []];
    foreach (Capsule::table('mod_cpm_ticket_cats')->orderBy('sort')->orderBy('id')->get() as $r) {
        $k = $r->kind === 'cause' ? 'cause' : 'area';
        $out7[$k][] = ['id' => (int) $r->id, 'name' => $r->name, 'color' => $r->color,
            'used' => (int) ($counts[($k === 'cause' ? 'c' : 'a') . $r->id] ?? 0)];
    }
    out($out7);

case 'tcat_save':
    if (!$FULL) {
        fail('perm', 403);
    }
    $kind7 = ($in['kind'] ?? '') === 'cause' ? 'cause' : 'area';
    $nm7 = mb_substr(trim($in['name'] ?? ''), 0, 80);
    if ($nm7 === '') {
        fail('Δώσε όνομα');
    }
    $col7 = preg_match('/^#[0-9a-fA-F]{6}$/', $in['color'] ?? '') ? $in['color'] : '#0090dd';
    $cid7 = (int) ($in['id'] ?? 0);
    if ($cid7) {
        Capsule::table('mod_cpm_ticket_cats')->where('id', $cid7)->update(['name' => $nm7, 'color' => $col7]);
    } else {
        $cid7 = Capsule::table('mod_cpm_ticket_cats')->insertGetId(['kind' => $kind7, 'name' => $nm7,
            'color' => $col7, 'sort' => (int) Capsule::table('mod_cpm_ticket_cats')->where('kind', $kind7)->max('sort') + 1]);
    }
    out(['ok' => true, 'id' => $cid7]);

case 'tcat_reorder':                     // νέα σειρά περιοχών/ριζών (drag ή ↑↓)
    if (!$FULL) { fail('forbidden', 403); }
    $kindR = ($in['kind'] ?? '') === 'cause' ? 'cause' : 'area';
    $idsR = (array) ($in['ids'] ?? []);
    if (!$idsR) { fail('Δεν δόθηκε σειρά'); }
    foreach ($idsR as $i => $cid) {
        Capsule::table('mod_cpm_ticket_cats')->where('id', (int) $cid)->where('kind', $kindR)
            ->update(['sort' => $i + 1]);
    }
    out(['ok' => true]);

case 'tcat_del':
    if (!$FULL) {
        fail('perm', 403);
    }
    $cid7 = (int) ($in['id'] ?? 0);
    Capsule::table('mod_cpm_ticket_cats')->where('id', $cid7)->delete();
    Capsule::table('mod_cpm_ticket_class')->where('area_id', $cid7)->update(['area_id' => null]);
    Capsule::table('mod_cpm_ticket_class')->where('cause_id', $cid7)->update(['cause_id' => null]);
    out(['ok' => true]);

case 'rootcause':                       // 🔬 ανάλυση ριζών (full)
    if (!$FULL) {
        fail('perm', 403);
    }
    $since7 = date('Y-m-d', strtotime('-' . (in_array((int) ($_GET['days'] ?? 90), [30, 90, 180, 365], true) ? (int) $_GET['days'] : 90) . ' days'));
    $cats7 = cnp_ticket_cats();
    $areaN = []; foreach ($cats7['area'] as $a) { $areaN[$a['id']] = $a; }
    $causeN = []; foreach ($cats7['cause'] as $c) { $causeN[$c['id']] = $c; }
    // ταξινομημένα tickets στο διάστημα
    $daysN = in_array((int) ($_GET['days'] ?? 90), [30, 90, 180, 365], true) ? (int) $_GET['days'] : 90;
    $prevSince = date('Y-m-d', strtotime('-' . ($daysN * 2) . ' days'));
    $rows7 = Capsule::table('mod_cpm_ticket_class as cl')
        ->join('tbltickets as t', 't.id', '=', 'cl.ticketid')
        ->where('t.date', '>=', $prevSince)
        ->get(['cl.ticketid', 'cl.area_id', 'cl.cause_id', 't.userid', 't.status', 't.date']);
    // διαχώρισε τρέχουσα vs προηγούμενη περίοδο + μηνιαία buckets
    $prevCause = []; $monthly = [];
    foreach ($rows7 as $r) {
        $inCur = $r->date >= $since7;
        if (!$inCur && $r->cause_id) { $prevCause[$r->cause_id] = ($prevCause[$r->cause_id] ?? 0) + 1; }
        if ($inCur && $r->cause_id) {
            $ym = substr($r->date, 0, 7);
            $monthly[$ym][$r->cause_id] = ($monthly[$ym][$r->cause_id] ?? 0) + 1;
        }
    }
    $rows7 = $rows7->filter(function ($r) use ($since7) { return $r->date >= $since7; });
    // χρόνος ανά ticket (μέσω linked task timelogs)
    $timeByTicket = [];
    try {
        foreach (Capsule::table('mod_cpm_timelogs as tl')
            ->join('mod_cpm_tasks as tk', 'tk.id', '=', 'tl.task_id')
            ->whereNotNull('tk.ticketid')->where('tl.running', 0)
            ->groupBy('tk.ticketid')->get([Capsule::raw('tk.ticketid as tid'), Capsule::raw('SUM(tl.minutes) m')]) as $r) {
            $timeByTicket[(int) $r->tid] = (int) $r->m;
        }
    } catch (\Throwable $e) {
    }
    $byCause = []; $byArea = []; $matrix = []; $total7 = 0; $classified7 = 0;
    foreach ($rows7 as $r) {
        $total7++;
        if ($r->cause_id) {
            $classified7++;
            $byCause[$r->cause_id]['n'] = ($byCause[$r->cause_id]['n'] ?? 0) + 1;
            $byCause[$r->cause_id]['min'] = ($byCause[$r->cause_id]['min'] ?? 0) + ($timeByTicket[(int) $r->ticketid] ?? 0);
        }
        if ($r->area_id) {
            $byArea[$r->area_id]['n'] = ($byArea[$r->area_id]['n'] ?? 0) + 1;
        }
        if ($r->area_id && $r->cause_id) {
            $matrix[$r->area_id][$r->cause_id] = ($matrix[$r->area_id][$r->cause_id] ?? 0) + 1;
        }
    }
    $topCauses = [];
    foreach ($byCause as $cid => $d) {
        $topCauses[] = ['id' => $cid, 'name' => $causeN[$cid]['name'] ?? '?', 'color' => $causeN[$cid]['color'] ?? '#888',
            'count' => $d['n'], 'minutes' => $d['min'] ?? 0,
            'delta' => $d['n'] - ($prevCause[$cid] ?? 0)];
    }
    usort($topCauses, function ($a, $b) { return $b['count'] <=> $a['count']; });
    $topAreas = [];
    foreach ($byArea as $aid => $d) {
        $topAreas[] = ['id' => $aid, 'name' => $areaN[$aid]['name'] ?? '?', 'color' => $areaN[$aid]['color'] ?? '#888', 'count' => $d['n']];
    }
    usort($topAreas, function ($a, $b) { return $b['count'] <=> $a['count']; });
    // total tickets στο διάστημα (για ποσοστό ταξινόμησης)
    $allTk = (int) Capsule::table('tbltickets')->where('date', '>=', $since7)->count();
    // μηνιαία σειρά (τελευταίοι μήνες) για τις top-5 ρίζες
    ksort($monthly);
    $months = array_keys($monthly);
    $top5 = array_slice(array_map(function ($x) { return $x['id']; }, $topCauses), 0, 5);
    $series = [];
    foreach ($months as $ym) {
        $row = ['ym' => $ym];
        foreach ($top5 as $cid) { $row[$cid] = $monthly[$ym][$cid] ?? 0; }
        $series[] = $row;
    }
    out(['topCauses' => $topCauses, 'topAreas' => $topAreas,
        'matrix' => $matrix, 'areas' => $cats7['area'], 'causes' => $cats7['cause'],
        'classified' => $classified7, 'totalClassified' => $total7, 'allTickets' => $allTk,
        'series' => $series, 'top5' => $top5]);

case 'standup':                         // 🏃 Standup dashboard — απασχόληση περιόδου + on-time
    $period = in_array($_GET['p'] ?? 'week', ['week', 'month'], true) ? $_GET['p'] : 'week';
    if ($period === 'month') {
        $ps = date('Y-m-01'); $pe = date('Y-m-t');
    } else {
        $ps = date('Y-m-d', strtotime('monday this week')); $pe = date('Y-m-d', strtotime('sunday this week'));
    }
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $bugType = (int) Capsule::table('mod_cpm_task_types')->where('name', 'like', '%Bug%')->value('id');
    $vis = $FULL ? null : Db::visibleProjectIds($adminId);
    $tScope = function ($q) use ($FULL, $adminId, $vis) {
        if (!$FULL) {
            $q->where(function ($w) use ($adminId, $vis) {
                $w->where('t.assignee', $adminId);
                if ($vis) { $w->orWhereIn('t.project_id', $vis ?: [0]); }
            });
        }
        return $q;
    };
    // στατιστικά περιόδου + λίστες drill-down (κλικ στην κάρτα → τα στοιχεία)
    $drill = [];
    $newProjRows = Capsule::table('mod_cpm_projects')->where('created_at', '>=', $ps . ' 00:00:00')
        ->when($vis !== null, function ($q) use ($vis) { $q->whereIn('id', $vis ?: [0]); })
        ->orderByDesc('created_at')->get(['id', 'name', 'clientid', 'kind']);
    $newProj = count($newProjRows);
    $drill['newProjects'] = $newProjRows->map(function ($p) {
        return ['type' => 'project', 'id' => (int) $p->id, 'title' => $p->name,
            'sub' => $p->kind === 'client' ? clientLabel($p->clientid) : 'Τμήμα'];
    })->all();
    $bugRows = $tScope(Capsule::table('mod_cpm_tasks as t')->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id'))
        ->where('t.type_id', $bugType)->whereNotIn('t.status_id', $doneIds)
        ->get(['t.id', 't.title', 'p.name as pname']);
    $bugsOpen = count($bugRows);
    $drill['bugs'] = $bugRows->map(function ($t) {
        return ['type' => 'task', 'id' => (int) $t->id, 'title' => $t->title, 'sub' => $t->pname];
    })->all();
    $dueRows = $tScope(Capsule::table('mod_cpm_tasks as t')->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id'))
        ->whereBetween('t.due_date', [$ps, $pe])->whereNotIn('t.status_id', $doneIds)
        ->orderBy('t.due_date')->get(['t.id', 't.title', 't.due_date', 'p.name as pname']);
    $dueThis = count($dueRows);
    $drill['deadlines'] = $dueRows->map(function ($t) {
        return ['type' => 'task', 'id' => (int) $t->id, 'title' => $t->title, 'sub' => $t->pname . ' · λήγει ' . $t->due_date];
    })->all();
    $doneRows = $tScope(Capsule::table('mod_cpm_tasks as t')->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id'))
        ->whereBetween('t.completed_at', [$ps . ' 00:00:00', $pe . ' 23:59:59'])
        ->orderByDesc('t.completed_at')->get(['t.id', 't.title', 't.completed_at', 'p.name as pname']);
    $doneThis = count($doneRows);
    $drill['completed'] = $doneRows->map(function ($t) {
        return ['type' => 'task', 'id' => (int) $t->id, 'title' => $t->title, 'sub' => $t->pname . ' · ' . substr($t->completed_at, 0, 10)];
    })->all();
    // roster ανά μέλος
    $admins = $FULL ? Db::admins() : Capsule::table('tbladmins')->where('id', $adminId)->get();
    $teamIds = [];
    try {
        $teamIds = array_map('intval', Capsule::table('mod_cpm_team_members')->pluck('admin_id')->all());
    } catch (\Throwable $e) {
    }
    $roster = [];
    $minsBy = [];
    try {
        foreach (Capsule::table('mod_cpm_timelogs')->where('running', 0)
            ->whereBetween('created_at', [$ps . ' 00:00:00', $pe . ' 23:59:59'])
            ->groupBy('admin_id')->get(['admin_id', Capsule::raw('SUM(minutes) m')]) as $r) {
            $minsBy[(int) $r->admin_id] = (int) $r->m;
        }
    } catch (\Throwable $e) {
    }
    foreach ($admins as $a) {
        $aid = (int) $a->id;
        $nm = Db::adminName($aid);
        // αγνόησε bots/test/system accounts
        if (preg_match('/\b(bot|test|debug|cnptest|system)\b/i', $nm)) { continue; }
        $open = Capsule::table('mod_cpm_tasks')->where('assignee', $aid)->whereNotIn('status_id', $doneIds)->count();
        $overdue = Capsule::table('mod_cpm_tasks')->where('assignee', $aid)->whereNotIn('status_id', $doneIds)
            ->whereNotNull('due_date')->where('due_date', '<', date('Y-m-d'))->count();
        $dueP = Capsule::table('mod_cpm_tasks')->where('assignee', $aid)->whereNotIn('status_id', $doneIds)
            ->whereBetween('due_date', [$ps, $pe])->count();
        // on-time: ολοκληρωμένα με deadline μέσα στην περίοδο
        $cl = Capsule::table('mod_cpm_tasks')->where('assignee', $aid)->whereNotNull('due_date')
            ->whereBetween('completed_at', [$ps . ' 00:00:00', $pe . ' 23:59:59'])
            ->get(['due_date', 'completed_at']);
        $onT = 0; $late = 0;
        foreach ($cl as $t) { if (substr($t->completed_at, 0, 10) <= $t->due_date) { $onT++; } else { $late++; } }
        $tkOpen = Capsule::table('tbltickets')->where('flag', $aid)->whereNotIn('status', ['Closed', 'Cancelled'])->count();
        $mins = $minsBy[$aid] ?? 0;
        $activity = $open + $overdue + $dueP + $onT + $late + $tkOpen + $mins;
        // κράτα: ο ίδιος πάντα, μέλη ομάδας (για να φαίνεται και το «τίποτα») ή όποιον έχει δραστηριότητα
        if ($aid !== $adminId && !in_array($aid, $teamIds, true) && $activity === 0) { continue; }
        $roster[] = ['id' => $aid, 'name' => $nm,
            'open' => $open, 'overdue' => $overdue, 'dueP' => $dueP,
            'onTime' => $onT, 'late' => $late,
            'score' => ($onT + $late) ? round($onT / ($onT + $late) * 100) : null,
            'tickets' => $tkOpen, 'mins' => $mins];
    }
    // ταξινόμηση: εκπρόθεσμα ↓, μετά ανοιχτά ↓ (πιεσμένοι πρώτοι)
    usort($roster, function ($a, $b) {
        return ($b['overdue'] <=> $a['overdue']) ?: ($b['open'] <=> $a['open']) ?: ($b['mins'] <=> $a['mins']);
    });
    // εξέλιξη projects (ανοιχτά)
    $projs = [];
    foreach (($FULL ? Db::projects(false) : Db::projectsFor($adminId, false)) as $p) {
        if (in_array($p->pstatus, ['done'], true)) { continue; }
        [$pd, $pt, $ppct] = Db::projectProgress($p->id);
        $days = $p->due_date ? (int) floor((strtotime($p->due_date) - time()) / 86400) : null;
        $projs[] = ['id' => (int) $p->id, 'name' => $p->name, 'kind' => $p->kind ?? 'dept',
            'client' => clientLabel($p->clientid), 'health' => $p->health, 'pstatus' => $p->pstatus,
            'done' => $pd, 'total' => $pt, 'pct' => $ppct,
            'due' => $p->due_date ?: null, 'daysLeft' => $days];
    }
    usort($projs, function ($a, $b) {
        $ad = $a['daysLeft'] === null ? 9999 : $a['daysLeft']; $bd = $b['daysLeft'] === null ? 9999 : $b['daysLeft'];
        return $ad <=> $bd;
    });
    // tickets εξέλιξη
    $tkOpenRows = Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled'])
        ->when(!$FULL, function ($q) use ($adminId) { $q->where('flag', $adminId); })
        ->orderByDesc('lastreply')->get(['id', 'tid', 'title', 'status']);
    $tkOpen = count($tkOpenRows);
    $drill['ticketsOpen'] = $tkOpenRows->map(function ($t) {
        return ['type' => 'ticket', 'id' => (int) $t->id, 'title' => '#' . $t->tid . ' ' . $t->title, 'sub' => $t->status];
    })->all();
    $tkClosedRows = Capsule::table('tbltickets')->where('status', 'Closed')
        ->whereBetween('lastreply', [$ps . ' 00:00:00', $pe . ' 23:59:59'])
        ->when(!$FULL, function ($q) use ($adminId) { $q->where('flag', $adminId); })
        ->orderByDesc('lastreply')->get(['id', 'tid', 'title', 'lastreply']);
    $tkClosedP = count($tkClosedRows);
    $drill['ticketsClosed'] = $tkClosedRows->map(function ($t) {
        return ['type' => 'ticket', 'id' => (int) $t->id, 'title' => '#' . $t->tid . ' ' . $t->title, 'sub' => 'έκλεισε ' . substr($t->lastreply, 0, 10)];
    })->all();
    out(['period' => $period, 'from' => $ps, 'to' => $pe, 'full' => $FULL,
        'stats' => ['newProjects' => $newProj, 'bugs' => $bugsOpen, 'deadlines' => $dueThis, 'completed' => $doneThis,
            'ticketsOpen' => $tkOpen, 'ticketsClosed' => $tkClosedP],
        'drill' => $drill, 'roster' => $roster, 'projects' => array_slice($projs, 0, 20)]);

/* ═══════════ 🔗 Δημόσιο link project για πελάτη (χωρίς credentials) ═══════════ */
case 'share_info':
case 'share_save':
case 'share_revoke':
case 'share_reply':
    $pid = (int) ($_GET['project'] ?? $_POST['project'] ?? ($in['project'] ?? 0));
    $proj = $pid ? Capsule::table('mod_cpm_projects')->where('id', $pid)->first() : null;
    if (!$proj) { fail('project', 404); }
    // δικαίωμα: full ή ορατότητα έργου
    $canShare = $FULL || in_array($pid, Db::visibleProjectIds($adminId), true);
    if (!$canShare) { fail('forbidden', 403); }
    $mkUrl = function ($tok) use ($pid) {
        $host = $_SERVER['HTTP_HOST'] ?? 'my.cloudon.gr';
        return 'https://' . $host . '/project/share.php?p=' . $pid . '&t=' . $tok;
    };
    $row = Capsule::table('mod_cpm_project_shares')->where('project_id', $pid)->first();

    if ($action === 'share_save') {
        $exp = trim($in['expires_at'] ?? '');
        $exp = preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp) ? $exp . ' 23:59:59' : null;
        $canC = !empty($in['can_comment']) ? 1 : 0;
        $rotate = !empty($in['rotate']);
        if ($row && !$rotate) {
            Capsule::table('mod_cpm_project_shares')->where('id', $row->id)
                ->update(['expires_at' => $exp, 'can_comment' => $canC, 'revoked' => 0]);
            $tok = $row->token;
        } else {
            $tok = bin2hex(random_bytes(20));
            if ($row) {
                Capsule::table('mod_cpm_project_shares')->where('id', $row->id)
                    ->update(['token' => $tok, 'expires_at' => $exp, 'can_comment' => $canC, 'revoked' => 0]);
            } else {
                Capsule::table('mod_cpm_project_shares')->insert(['project_id' => $pid, 'token' => $tok,
                    'expires_at' => $exp, 'can_comment' => $canC, 'revoked' => 0, 'views' => 0,
                    'created_by' => $adminId, 'created_at' => date('Y-m-d H:i:s')]);
            }
        }
        logActivity("CNP: project share link " . ($rotate ? 'rotated' : 'saved') . " #$pid by admin $adminId");
        out(['url' => $mkUrl($tok), 'token' => $tok, 'expires_at' => $exp, 'can_comment' => $canC, 'revoked' => 0]);
    }

    if ($action === 'share_revoke') {
        if ($row) { Capsule::table('mod_cpm_project_shares')->where('id', $row->id)->update(['revoked' => 1]); }
        out(['ok' => true]);
    }

    if ($action === 'share_reply') {                 // απάντηση ομάδας στο thread του πελάτη
        $body = trim($in['body'] ?? '');
        if ($body === '') { fail('empty', 422); }
        Capsule::table('mod_cpm_share_comments')->insert(['project_id' => $pid, 'author' => Db::adminName($adminId),
            'body' => mb_substr($body, 0, 2000), 'from_team' => 1, 'admin_id' => $adminId,
            'created_at' => date('Y-m-d H:i:s')]);
        out(['ok' => true]);
    }

    // share_info
    $comments = Capsule::table('mod_cpm_share_comments')->where('project_id', $pid)
        ->orderBy('created_at')->get()->map(function ($c) {
            return ['author' => $c->author, 'body' => $c->body, 'team' => (int) $c->from_team, 'at' => $c->created_at];
        })->all();
    out(['exists' => (bool) $row,
        'url' => $row ? $mkUrl($row->token) : null,
        'expires_at' => $row && $row->expires_at ? substr($row->expires_at, 0, 10) : null,
        'can_comment' => $row ? (int) $row->can_comment : 0,
        'revoked' => $row ? (int) $row->revoked : 0,
        'views' => $row ? (int) $row->views : 0,
        'last_view' => $row ? $row->last_view : null,
        'comments' => $comments]);

case 'agenda':                          // 🗒 Ανοιχτά projects & tickets — αναλυτικά για meeting
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $today0 = date('Y-m-d');
    // ---- Ανοιχτά projects (αναλυτικά) ----
    $projects = [];
    foreach (($FULL ? Db::projects(false) : Db::projectsFor($adminId, false)) as $p) {
        if (in_array($p->pstatus, ['done'], true)) { continue; }
        [$pd, $pt, $ppct] = Db::projectProgress($p->id);
        $lastUpd = Capsule::table('mod_cpm_tasks')->where('project_id', $p->id)->max('updated_at');
        $nextT = Capsule::table('mod_cpm_tasks as t')->where('t.project_id', $p->id)
            ->whereNotIn('t.status_id', $doneIds)
            ->orderByRaw('t.priority DESC')->orderByRaw('t.due_date IS NULL, t.due_date ASC')
            ->first(['t.title', 't.assignee', 't.due_date']);
        $todos = Capsule::table('mod_cpm_project_todos')->where('project_id', $p->id)
            ->whereNull('done_at')->orderBy('sort')->orderBy('id')->limit(5)->pluck('title')->all();
        $todoTotal = Capsule::table('mod_cpm_project_todos')->where('project_id', $p->id)->count();
        $todoDone = Capsule::table('mod_cpm_project_todos')->where('project_id', $p->id)->whereNotNull('done_at')->count();
        $openTasks = Capsule::table('mod_cpm_tasks')->where('project_id', $p->id)->whereNotIn('status_id', $doneIds)->count();
        $ownerIds = Capsule::table('mod_cpm_tasks')->where('project_id', $p->id)->whereNotIn('status_id', $doneIds)
            ->whereNotNull('assignee')->distinct()->pluck('assignee')->all();
        $owners = array_values(array_filter(array_map(function ($id) { return $id ? Db::adminName((int) $id) : null; }, $ownerIds)));
        $spentMins = (int) Capsule::table('mod_cpm_timelogs as tl')->join('mod_cpm_tasks as t', 't.id', '=', 'tl.task_id')
            ->where('t.project_id', $p->id)->where('tl.running', 0)->sum('tl.minutes');
        $days = $p->due_date ? (int) floor((strtotime($p->due_date) - time()) / 86400) : null;
        $staleD = $lastUpd ? (int) floor((time() - strtotime($lastUpd)) / 86400) : null;
        $projects[] = ['id' => (int) $p->id, 'name' => $p->name, 'kind' => $p->kind ?? 'dept',
            'client' => $p->clientid ? clientLabel($p->clientid) : null, 'clientid' => (int) $p->clientid,
            'health' => $p->health, 'pstatus' => $p->pstatus,
            'done' => $pd, 'total' => $pt, 'pct' => $ppct, 'openTasks' => $openTasks,
            'todoDone' => $todoDone, 'todoTotal' => $todoTotal, 'pendingTodos' => $todos,
            'due' => $p->due_date ?: null, 'daysLeft' => $days,
            'lastUpdate' => $lastUpd ? substr($lastUpd, 0, 10) : null, 'staleDays' => $staleD,
            'owners' => $owners, 'spentMins' => $spentMins, 'budget' => $p->budget ? (float) $p->budget : null,
            'next' => $nextT ? ['title' => $nextT->title, 'who' => $nextT->assignee ? Db::adminName((int) $nextT->assignee) : null,
                'due' => $nextT->due_date ?: null] : null];
    }
    usort($projects, function ($a, $b) {
        $ad = $a['daysLeft'] === null ? 9999 : $a['daysLeft']; $bd = $b['daysLeft'] === null ? 9999 : $b['daysLeft'];
        return $ad <=> $bd;
    });
    // ---- Ανοιχτά tickets (αναλυτικά) ----
    $cats = cnp_ticket_cats();
    $areaN = []; foreach ($cats['area'] as $a) { $areaN[$a['id']] = $a; }
    $causeN = []; foreach ($cats['cause'] as $c) { $causeN[$c['id']] = $c; }
    $depN = [];
    foreach (Capsule::table('tblticketdepartments')->get(['id', 'name']) as $dp) { $depN[(int) $dp->id] = $dp->name; }
    $tickets = [];
    $tq = Capsule::table('tbltickets')->whereNotIn('status', ['Closed', 'Cancelled']);
    if (!$FULL) { $tq->where('flag', $adminId); }
    foreach ($tq->orderByRaw("FIELD(urgency,'High','Medium','Low')")->orderBy('lastreply')->get() as $tk) {
        $lastRep = Capsule::table('tblticketreplies')->where('tid', $tk->id)->orderByDesc('id')->first(['admin', 'date']);
        $waitUs = !$lastRep ? true : (trim((string) $lastRep->admin) === '');   // πελάτης απάντησε τελευταίος → περιμένει εμάς
        $cl = Capsule::table('mod_cpm_ticket_class')->where('ticketid', $tk->id)->first(['area_id', 'cause_id', 'note']);
        $cid = $tk->userid ? clientLabel($tk->userid) : ($tk->name ?: 'Guest');
        $tickets[] = ['id' => (int) $tk->id, 'tid' => $tk->tid, 'title' => $tk->title,
            'client' => $cid, 'dept' => $depN[(int) $tk->did] ?? '—', 'status' => $tk->status,
            'urgency' => $tk->urgency, 'assignee' => $tk->flag ? Db::adminName((int) $tk->flag) : null,
            'age' => (int) floor((time() - strtotime($tk->date)) / 86400),
            'idle' => (int) floor((time() - strtotime($tk->lastreply ?: $tk->date)) / 86400),
            'waitUs' => $waitUs,
            'area' => $cl && $cl->area_id && isset($areaN[$cl->area_id]) ? $areaN[$cl->area_id] : null,
            'cause' => $cl && $cl->cause_id && isset($causeN[$cl->cause_id]) ? $causeN[$cl->cause_id] : null,
            'note' => $cl->note ?? null];
    }
    out(['projects' => $projects, 'tickets' => $tickets,
        'counts' => ['projects' => count($projects), 'tickets' => count($tickets),
            'waitUs' => count(array_filter($tickets, function ($t) { return $t['waitUs']; }))]]);

/* ═══════ WHMCS: διαχείριση Τμημάτων & Ticket Statuses (full μόνο) ═══════ */
case 'wh_ticket_manage':
    if (!$FULL) { fail('forbidden', 403); }
    $depsW = [];
    foreach (Capsule::table('tblticketdepartments')->orderBy('order')->get() as $dp) {
        $depsW[] = ['id' => (int) $dp->id, 'name' => $dp->name, 'email' => $dp->email,
            'hidden' => (int) $dp->hidden,
            'tickets' => (int) Capsule::table('tbltickets')->where('did', $dp->id)->count()];
    }
    $statW = [];
    $coreW = ['Open', 'Answered', 'Customer-Reply', 'Closed'];
    foreach (Capsule::table('tblticketstatuses')->orderBy('sortorder')->get() as $s) {
        $statW[] = ['id' => (int) $s->id, 'title' => $s->title, 'color' => $s->color ?: '#888888',
            'core' => in_array($s->title, $coreW, true) ? 1 : 0,
            'used' => (int) Capsule::table('tbltickets')->where('status', $s->title)->count()];
    }
    out(['depts' => $depsW, 'statuses' => $statW]);

case 'wh_dept_save':
    if (!$FULL) { fail('forbidden', 403); }
    $nm = mb_substr(trim($in['name'] ?? ''), 0, 100);
    if ($nm === '') { fail('Δώσε όνομα τμήματος'); }
    $em = filter_var(trim($in['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($in['email']) : '';
    $did = (int) ($in['id'] ?? 0);
    if ($did) {
        Capsule::table('tblticketdepartments')->where('id', $did)->update(['name' => $nm, 'email' => $em]);
    } else {
        $ord = (int) Capsule::table('tblticketdepartments')->max('order') + 1;
        Capsule::table('tblticketdepartments')->insert(['name' => $nm, 'email' => $em, 'order' => $ord,
            'clientsonly' => '', 'piperepliesonly' => '', 'noautoresponder' => '', 'hidden' => '',
            'host' => '', 'port' => '', 'login' => '', 'password' => '']);
    }
    out(['ok' => true]);

case 'wh_dept_del':
    if (!$FULL) { fail('forbidden', 403); }
    $did = (int) ($in['id'] ?? 0);
    if (Capsule::table('tbltickets')->where('did', $did)->exists()) {
        fail('Το τμήμα έχει tickets — μετακίνησέ τα πρώτα ή κρύψ\' το', 409);
    }
    Capsule::table('tblticketdepartments')->where('id', $did)->delete();
    out(['ok' => true]);

case 'wh_tstatus_save':
    if (!$FULL) { fail('forbidden', 403); }
    $ti = mb_substr(trim($in['title'] ?? ''), 0, 60);
    if ($ti === '') { fail('Δώσε τίτλο status'); }
    $col = preg_match('/^#[0-9a-fA-F]{6}$/', $in['color'] ?? '') ? $in['color'] : '#888888';
    $sid = (int) ($in['id'] ?? 0);
    if ($sid) {
        $old = Capsule::table('tblticketstatuses')->where('id', $sid)->value('title');
        // αν αλλάζει ο τίτλος, μετονόμασε και στα tickets ώστε να μη «χαθούν»
        if ($old && $old !== $ti) {
            Capsule::table('tbltickets')->where('status', $old)->update(['status' => $ti]);
        }
        Capsule::table('tblticketstatuses')->where('id', $sid)->update(['title' => $ti, 'color' => $col]);
    } else {
        $ord = (int) Capsule::table('tblticketstatuses')->max('sortorder') + 1;
        Capsule::table('tblticketstatuses')->insert(['title' => $ti, 'color' => $col, 'sortorder' => $ord,
            'showactive' => 1, 'showawaiting' => 0, 'autoclose' => 0]);
    }
    out(['ok' => true]);

case 'wh_tstatus_del':
    if (!$FULL) { fail('forbidden', 403); }
    $sid = (int) ($in['id'] ?? 0);
    $st = Capsule::table('tblticketstatuses')->where('id', $sid)->first();
    if (!$st) { fail('status'); }
    if (in_array($st->title, ['Open', 'Answered', 'Customer-Reply', 'Closed'], true)) {
        fail('Βασικό status του WHMCS — δεν διαγράφεται', 409);
    }
    if (Capsule::table('tbltickets')->where('status', $st->title)->exists()) {
        fail('Υπάρχουν tickets με αυτό το status — άλλαξέ τα πρώτα', 409);
    }
    Capsule::table('tblticketstatuses')->where('id', $sid)->delete();
    out(['ok' => true]);

/* ═══════ CRM Tasks / Δραστηριότητες ανά lead ═══════ */
case 'lead_tasks':
    $lid = (int) ($_GET['lead'] ?? $in['lead'] ?? 0);
    $rows = Capsule::table('mod_cpm_lead_tasks')->where('lead_id', $lid)
        ->orderBy('done')->orderByRaw('due_date IS NULL, due_date ASC')->orderByDesc('id')->get();
    $out = [];
    foreach ($rows as $t) {
        $out[] = ['id' => (int) $t->id, 'title' => $t->title, 'kind' => $t->kind,
            'due' => $t->due_date, 'assignee' => $t->assignee ? (int) $t->assignee : null,
            'who' => $t->assignee ? Db::adminName((int) $t->assignee) : null,
            'done' => (bool) $t->done];
    }
    out(['tasks' => $out]);

case 'lead_task_save':
    $lid = (int) ($in['lead'] ?? 0);
    $title = mb_substr(trim($in['title'] ?? ''), 0, 200);
    if (!$lid || $title === '') { fail('input'); }
    $data = ['title' => $title,
        'kind' => in_array($in['kind'] ?? '', ['call', 'email', 'meeting', 'todo'], true) ? $in['kind'] : 'todo',
        'due_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['due'] ?? '') ? $in['due'] : null,
        'assignee' => (int) ($in['assignee'] ?? 0) ?: null];
    $tid = (int) ($in['id'] ?? 0);
    if ($tid) {
        Capsule::table('mod_cpm_lead_tasks')->where('id', $tid)->update($data);
    } else {
        $data['lead_id'] = $lid; $data['created_by'] = $adminId; $data['created_at'] = date('Y-m-d H:i:s');
        Capsule::table('mod_cpm_lead_tasks')->insert($data);
    }
    out(['ok' => true]);

case 'lead_task_toggle':
    $tid = (int) ($in['id'] ?? 0);
    $t = Capsule::table('mod_cpm_lead_tasks')->where('id', $tid)->first();
    if (!$t) { fail('task'); }
    $nd = $t->done ? 0 : 1;
    Capsule::table('mod_cpm_lead_tasks')->where('id', $tid)
        ->update(['done' => $nd, 'done_at' => $nd ? date('Y-m-d H:i:s') : null]);
    out(['ok' => true, 'done' => (bool) $nd]);

case 'lead_task_del':
    Capsule::table('mod_cpm_lead_tasks')->where('id', (int) ($in['id'] ?? 0))->delete();
    out(['ok' => true]);

/* ============ 🔐 ΘΥΡΙΔΑ ΚΩΔΙΚΩΝ (ανά χειριστή· admin βλέπει όλα) ============ */
case 'vault_list':
    $kinds = cnp_vault_kinds();
    $mine = !empty($_GET['mine']) || !$FULL;         // restricted: δικά του + κοινά
    $q = Capsule::table('mod_cpm_vault');
    if ($mine) {
        // δικά μου Ή κοινά (ομάδας)
        $q->where(function ($w) use ($adminId) { $w->where('admin_id', $adminId)->orWhere('shared', 1); });
    }
    $rows = $q->orderBy('descr')->get();
    $cids = array_values(array_filter($rows->pluck('client_id')->all()));
    $cmap = [];
    if ($cids) {
        foreach (Capsule::table('tblclients')->whereIn('id', $cids)->get(['id', 'companyname', 'firstname', 'lastname']) as $c) {
            $cmap[(int) $c->id] = $c->companyname ?: trim($c->firstname . ' ' . $c->lastname);
        }
    }
    $items = [];
    foreach ($rows as $r) {
        $items[] = ['id' => (int) $r->id, 'descr' => $r->descr, 'kind' => $r->kind, 'kindLbl' => $kinds[$r->kind] ?? $r->kind,
            'username' => $r->username, 'ips' => $r->ips, 'url' => $r->url, 'location' => $r->location,
            'clientId' => $r->client_id ? (int) $r->client_id : null,
            'clientName' => $r->client_id ? ($cmap[(int) $r->client_id] ?? ('#' . $r->client_id)) : null,
            'purpose' => $r->purpose, 'shared' => (bool) $r->shared, 'owner' => (int) $r->admin_id,
            'ownerName' => Db::adminName($r->admin_id), 'canEdit' => ((int) $r->admin_id === $adminId || $FULL),
            'updated' => $r->updated_at];
    }
    out(['items' => $items, 'kinds' => $kinds, 'full' => $FULL, 'me' => $adminId, 'mine' => $mine]);

case 'vault_save':
    $descr = mb_substr(trim($in['descr'] ?? ''), 0, 150);
    if ($descr === '') { fail('Δώσε περιγραφή'); }
    // ο τύπος είναι ελεύθερο κείμενο (προτάσεις + custom, π.χ. «SoftOne», «Microsoft 365»)
    $kind = mb_substr(trim($in['kind'] ?? ''), 0, 40) ?: 'Άλλο';
    $data = ['descr' => $descr, 'kind' => $kind, 'username' => mb_substr(trim($in['username'] ?? ''), 0, 150),
        'ips' => mb_substr(trim($in['ips'] ?? ''), 0, 300), 'url' => mb_substr(trim($in['url'] ?? ''), 0, 300),
        'location' => mb_substr(trim($in['location'] ?? ''), 0, 150),
        'client_id' => (int) ($in['client'] ?? 0) ?: null, 'purpose' => mb_substr(trim($in['purpose'] ?? ''), 0, 200),
        'shared' => !empty($in['shared']) ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')];
    $pass = (string) ($in['password'] ?? '');
    $id = (int) ($in['id'] ?? 0);
    if ($id) {
        $own = (int) Capsule::table('mod_cpm_vault')->where('id', $id)->value('admin_id');
        if (!$own) { fail('notfound', 404); }
        if ($own !== $adminId && !$FULL) { fail('forbidden', 403); }
        if ($pass !== '') { $data['password_enc'] = cnp_vault_enc($pass); }  // αλλαγή κωδικού μόνο αν δόθηκε νέος
        Capsule::table('mod_cpm_vault')->where('id', $id)->update($data);
    } else {
        $data['admin_id'] = $adminId; $data['password_enc'] = cnp_vault_enc($pass); $data['created_at'] = date('Y-m-d H:i:s');
        $id = Capsule::table('mod_cpm_vault')->insertGetId($data);
    }
    out(['ok' => true, 'id' => $id]);

case 'vault_reveal':                     // αποκάλυψη κωδικού on-demand (owner, full, ή κοινό)
    $id = (int) ($in['id'] ?? $_GET['id'] ?? 0);
    $r = Capsule::table('mod_cpm_vault')->where('id', $id)->first();
    if (!$r) { fail('notfound', 404); }
    if ((int) $r->admin_id !== $adminId && !$FULL && !$r->shared) { fail('forbidden', 403); }
    out(['password' => cnp_vault_dec($r->password_enc)]);

case 'vault_del':
    $id = (int) ($in['id'] ?? 0);
    $r = Capsule::table('mod_cpm_vault')->where('id', $id)->first();
    if ($r && ((int) $r->admin_id === $adminId || $FULL)) { Capsule::table('mod_cpm_vault')->where('id', $id)->delete(); }
    out(['ok' => true]);

/* ============ 📚 ΒΙΒΛΙΟΘΗΚΗ ΧΕΙΡΙΣΤΗ (ιδιωτική· έγγραφα/σημειώσεις/links) ============ */
case 'lib_list':
    $q = trim($_GET['q'] ?? '');
    $cat = trim($_GET['cat'] ?? '');
    $scope = ($_GET['scope'] ?? 'mine') === 'shared' ? 'shared' : 'mine';
    $qry = Capsule::table('mod_cpm_library');
    if ($scope === 'shared') { $qry->where('shared', 1); }
    else { $qry->where('admin_id', $adminId); }
    if ($cat !== '') { $qry->where('category', $cat); }
    if (mb_strlen($q) >= 1) {
        $like = '%' . $q . '%';
        $qry->where(function ($w) use ($like) {
            $w->where('title', 'like', $like)->orWhere('body', 'like', $like)->orWhere('tags', 'like', $like)
              ->orWhere('category', 'like', $like)->orWhere('url', 'like', $like)->orWhere('filename', 'like', $like);
        });
    }
    $rows = $qry->orderByDesc('pinned')->orderByDesc('updated_at')->get();
    $items = []; $today = date('Y-m-d');
    foreach ($rows as $r) {
        $expDays = $r->expires_at ? (int) floor((strtotime($r->expires_at) - strtotime($today)) / 86400) : null;
        $items[] = ['id' => (int) $r->id, 'kind' => $r->kind, 'title' => $r->title, 'category' => $r->category,
            'tags' => $r->tags, 'body' => $r->kind === 'note' ? $r->body : '', 'url' => $r->url,
            'filename' => $r->filename, 'size' => (int) $r->size, 'pinned' => (bool) $r->pinned,
            'expires' => $r->expires_at, 'expDays' => $expDays, 'shared' => (bool) $r->shared,
            'owner' => (int) $r->admin_id, 'ownerName' => Db::adminName($r->admin_id), 'canEdit' => ((int) $r->admin_id === $adminId),
            'updated' => $r->updated_at];
    }
    $catQ = Capsule::table('mod_cpm_library');
    if ($scope === 'shared') { $catQ->where('shared', 1); } else { $catQ->where('admin_id', $adminId); }
    $cats = $catQ->where('category', '<>', '')->distinct()->orderBy('category')->pluck('category')->all();
    // πλήθος shared (για badge στο toggle)
    $sharedN = (int) Capsule::table('mod_cpm_library')->where('shared', 1)->count();
    out(['items' => $items, 'cats' => $cats, 'scope' => $scope, 'sharedN' => $sharedN]);

case 'lib_save':
    $title = mb_substr(trim($in['title'] ?? ''), 0, 200);
    if ($title === '') { fail('Δώσε τίτλο'); }
    $id = (int) ($in['id'] ?? 0);
    $existing = $id ? Capsule::table('mod_cpm_library')->where('id', $id)->first() : null;
    if ($id && (!$existing || (int) $existing->admin_id !== $adminId)) { fail('forbidden', 403); }
    $kind = $existing ? $existing->kind : (in_array($in['kind'] ?? '', ['note', 'link'], true) ? $in['kind'] : 'note');
    $data = ['title' => $title, 'category' => mb_substr(trim($in['category'] ?? ''), 0, 80),
        'tags' => mb_substr(trim($in['tags'] ?? ''), 0, 200),
        'expires_at' => ($in['expires'] ?? '') ?: null, 'shared' => !empty($in['shared']) ? 1 : 0,
        'exp_notified' => 0, 'updated_at' => date('Y-m-d H:i:s')];
    if ($kind === 'note') { $data['body'] = cnp_clean_html($in['body'] ?? ''); }
    if ($kind === 'link') { $data['url'] = mb_substr(trim($in['url'] ?? ''), 0, 500); }
    if ($id) {
        Capsule::table('mod_cpm_library')->where('id', $id)->update($data);
    } else {
        $data['kind'] = $kind; $data['admin_id'] = $adminId; $data['created_at'] = date('Y-m-d H:i:s');
        $id = Capsule::table('mod_cpm_library')->insertGetId($data);
    }
    out(['ok' => true, 'id' => $id]);

case 'lib_upload':                       // multipart
    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) { fail('upload'); }
    if ($f['size'] > 25 * 1024 * 1024) { fail('Μέγιστο 25MB'); }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['php', 'phtml', 'phar', 'cgi', 'sh', 'exe', 'htaccess'], true)) { fail('Μη επιτρεπτός τύπος'); }
    $dir = __DIR__ . '/../attachments/cloudonprojects';
    if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
    $stored = uniqid('lib', true) . '.dat';
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) { fail('write'); }
    Capsule::table('mod_cpm_library')->insert(['admin_id' => $adminId, 'kind' => 'file',
        'title' => mb_substr(($_POST['title'] ?? '') ?: $f['name'], 0, 200), 'category' => mb_substr(trim($_POST['category'] ?? ''), 0, 80),
        'tags' => mb_substr(trim($_POST['tags'] ?? ''), 0, 200), 'filename' => mb_substr($f['name'], 0, 200),
        'stored' => $stored, 'size' => (int) $f['size'], 'expires_at' => ($_POST['expires'] ?? '') ?: null,
        'shared' => !empty($_POST['shared']) ? 1 : 0, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true]);

case 'manual_img':                       // εικόνες οδηγού (μόνο για συνδεδεμένους — protected)
    $f = preg_replace('/[^a-z0-9_]/i', '', $_GET['f'] ?? '');
    $path = __DIR__ . '/manual-src/manual_' . $f . '.png';
    if ($f === '' || !is_file($path)) { fail('img', 404); }
    header('Content-Type: image/png');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=86400');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;

case 'lib_get':
    $r = Capsule::table('mod_cpm_library')->where('id', (int) ($_GET['id'] ?? 0))->first();
    if (!$r || $r->kind !== 'file' || ((int) $r->admin_id !== $adminId && !$r->shared)) { fail('file', 404); }
    $path = realpath(__DIR__ . '/../attachments/cloudonprojects/' . basename($r->stored));
    if (!$path || !is_file($path)) { fail('file', 404); }
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . rawurlencode($r->filename) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;

case 'lib_pin':
    $r = Capsule::table('mod_cpm_library')->where('id', (int) ($in['id'] ?? 0))->where('admin_id', $adminId)->first();
    if ($r) { Capsule::table('mod_cpm_library')->where('id', $r->id)->update(['pinned' => $r->pinned ? 0 : 1]); }
    out(['ok' => true]);

case 'lib_del':
    $r = Capsule::table('mod_cpm_library')->where('id', (int) ($in['id'] ?? 0))->where('admin_id', $adminId)->first();
    if ($r) {
        if ($r->stored) { @unlink(__DIR__ . '/../attachments/cloudonprojects/' . basename($r->stored)); }
        Capsule::table('mod_cpm_library')->where('id', $r->id)->delete();
    }
    out(['ok' => true]);

/* ============ ✅ ΠΛΑΝΟ ΧΕΙΡΙΣΤΗ ανά project («πού έμεινα») ============ */
case 'todos_list':
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $myOpenTasks = Capsule::table('mod_cpm_tasks as t')->join('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
        ->where('t.assignee', $adminId)->whereNotIn('t.status_id', $doneIds)
        ->select('t.id', 't.title', 't.project_id', 'p.name as pname', 'p.color as pcolor')->get();
    $todoRows = Capsule::table('mod_cpm_todos')->where('admin_id', $adminId)->orderBy('done')->orderBy('sort')->orderBy('id')->get();
    $noteRows = Capsule::table('mod_cpm_worknote')->where('admin_id', $adminId)->get()->keyBy('project_id');
    $proj = [];
    $ensure = function ($pid, $pname, $pcolor) use (&$proj, $noteRows) {
        if (!isset($proj[$pid])) {
            $proj[$pid] = ['id' => $pid, 'name' => $pname, 'color' => $pcolor, 'tasks' => [], 'todos' => [],
                'note' => isset($noteRows[$pid]) ? $noteRows[$pid]->note : ''];
        }
    };
    foreach ($myOpenTasks as $t) {
        $ensure((int) $t->project_id, $t->pname, $t->pcolor);
        $proj[(int) $t->project_id]['tasks'][] = ['id' => (int) $t->id, 'title' => $t->title];
    }
    $needNames = [];
    foreach ($todoRows as $t) { if ($t->project_id && !isset($proj[(int) $t->project_id])) { $needNames[(int) $t->project_id] = 1; } }
    foreach ($noteRows as $pid => $n) { if ($pid && !isset($proj[$pid])) { $needNames[$pid] = 1; } }
    if ($needNames) {
        foreach (Capsule::table('mod_cpm_projects')->whereIn('id', array_keys($needNames))->get(['id', 'name', 'color']) as $p) {
            $ensure((int) $p->id, $p->name, $p->color);
        }
    }
    foreach ($todoRows as $t) {
        $pid = (int) $t->project_id;
        if (!isset($proj[$pid])) { $ensure($pid, $pid ? '—' : 'Γενικά', '#8291a9'); }
        $proj[$pid]['todos'][] = ['id' => (int) $t->id, 'text' => $t->text, 'done' => (bool) $t->done,
            'remind' => $t->remind_at, 'overdue' => $t->remind_at && !$t->done && strtotime($t->remind_at) < time()];
    }
    // «Γενικά» πάντα διαθέσιμο για ελεύθερες σημειώσεις
    if (!isset($proj[0]) && !$todoRows->count()) { $ensure(0, 'Γενικά', '#8291a9'); }
    $groups = array_values($proj);
    usort($groups, function ($a, $b) {
        if ($a['id'] === 0) { return 1; }
        if ($b['id'] === 0) { return -1; }
        $ao = count($a['tasks']); $bo = count($b['tasks']);
        if ($ao !== $bo) { return $bo <=> $ao; }
        return strcmp($a['name'], $b['name']);
    });
    out(['groups' => $groups]);

case 'todo_add':
    $text = mb_substr(trim($in['text'] ?? ''), 0, 300);
    if ($text === '') { fail('empty'); }
    $remind = ($in['remind'] ?? '') ? date('Y-m-d H:i:s', strtotime($in['remind'])) : null;
    $sort = (int) Capsule::table('mod_cpm_todos')->where('admin_id', $adminId)->where('project_id', (int) ($in['project'] ?? 0))->max('sort') + 1;
    $id = Capsule::table('mod_cpm_todos')->insertGetId(['admin_id' => $adminId, 'project_id' => (int) ($in['project'] ?? 0),
        'text' => $text, 'sort' => $sort, 'remind_at' => $remind, 'remind_sent' => 0, 'created_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true, 'id' => $id]);

case 'todo_update':                      // επεξεργασία κειμένου/υπενθύμισης
    $r = Capsule::table('mod_cpm_todos')->where('id', (int) ($in['id'] ?? 0))->where('admin_id', $adminId)->first();
    if (!$r) { fail('notfound', 404); }
    $upd = [];
    if (isset($in['text']) && trim($in['text']) !== '') { $upd['text'] = mb_substr(trim($in['text']), 0, 300); }
    if (array_key_exists('remind', $in)) {
        $upd['remind_at'] = $in['remind'] ? date('Y-m-d H:i:s', strtotime($in['remind'])) : null;
        $upd['remind_sent'] = 0;
    }
    if ($upd) { Capsule::table('mod_cpm_todos')->where('id', $r->id)->update($upd); }
    out(['ok' => true]);

case 'todo_reorder':                     // νέα σειρά (drag) — λίστα ids
    $ids = $in['ids'] ?? [];
    foreach ($ids as $i => $tid) {
        Capsule::table('mod_cpm_todos')->where('id', (int) $tid)->where('admin_id', $adminId)->update(['sort' => $i + 1]);
    }
    out(['ok' => true]);

case 'my_todos':                         // «Η μέρα μου»: τα ανοιχτά μου todos (flat)
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $rows = Capsule::table('mod_cpm_todos')->where('admin_id', $adminId)->where('done', 0)->orderBy('project_id')->orderBy('sort')->orderBy('id')->get();
    $pnames = [];
    $pids = array_values(array_filter(array_unique($rows->pluck('project_id')->all())));
    if ($pids) {
        foreach (Capsule::table('mod_cpm_projects')->whereIn('id', $pids)->get(['id', 'name', 'color']) as $p) {
            $pnames[(int) $p->id] = ['name' => $p->name, 'color' => $p->color];
        }
    }
    $todos = [];
    foreach ($rows as $t) {
        $pid = (int) $t->project_id;
        $todos[] = ['id' => (int) $t->id, 'text' => $t->text, 'project' => $pid,
            'pname' => $pid ? ($pnames[$pid]['name'] ?? '—') : 'Γενικά', 'pcolor' => $pid ? ($pnames[$pid]['color'] ?? '#8291a9') : '#8291a9',
            'remind' => $t->remind_at, 'overdue' => $t->remind_at && strtotime($t->remind_at) < time()];
    }
    out(['todos' => $todos]);

case 'todo_toggle':
    $r = Capsule::table('mod_cpm_todos')->where('id', (int) ($in['id'] ?? 0))->where('admin_id', $adminId)->first();
    if ($r) { Capsule::table('mod_cpm_todos')->where('id', $r->id)->update(['done' => $r->done ? 0 : 1, 'done_at' => $r->done ? null : date('Y-m-d H:i:s')]); }
    out(['ok' => true]);

case 'todo_del':
    Capsule::table('mod_cpm_todos')->where('id', (int) ($in['id'] ?? 0))->where('admin_id', $adminId)->delete();
    out(['ok' => true]);

case 'todo_clear_done':
    Capsule::table('mod_cpm_todos')->where('admin_id', $adminId)->where('project_id', (int) ($in['project'] ?? 0))->where('done', 1)->delete();
    out(['ok' => true]);

case 'todo_seed':                        // αυτο-δημιουργία από τα ανοιχτά μου tasks του project
    $pid = (int) ($in['project'] ?? 0);
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $existing = array_map('mb_strtolower', Capsule::table('mod_cpm_todos')->where('admin_id', $adminId)->where('project_id', $pid)->pluck('text')->all());
    $added = 0;
    foreach (Capsule::table('mod_cpm_tasks')->where('project_id', $pid)->where('assignee', $adminId)->whereNotIn('status_id', $doneIds)->get(['title']) as $t) {
        if (in_array(mb_strtolower($t->title), $existing, true)) { continue; }
        Capsule::table('mod_cpm_todos')->insert(['admin_id' => $adminId, 'project_id' => $pid, 'text' => mb_substr($t->title, 0, 300), 'created_at' => date('Y-m-d H:i:s')]);
        $added++;
    }
    out(['ok' => true, 'added' => $added]);

case 'worknote_save':
    $pid = (int) ($in['project'] ?? 0);
    $note = cnp_clean_html($in['note'] ?? '');
    $ex = Capsule::table('mod_cpm_worknote')->where('admin_id', $adminId)->where('project_id', $pid)->first();
    if ($ex) { Capsule::table('mod_cpm_worknote')->where('id', $ex->id)->update(['note' => $note, 'updated_at' => date('Y-m-d H:i:s')]); }
    else { Capsule::table('mod_cpm_worknote')->insert(['admin_id' => $adminId, 'project_id' => $pid, 'note' => $note, 'updated_at' => date('Y-m-d H:i:s')]); }
    out(['ok' => true]);

/* ============ 🧑‍💼 ΠΡΟΣΛΗΨΕΙΣ / ΒΙΟΓΡΑΦΙΚΑ (ειδικότητα hr) ============ */
case 'cv_jobs':
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $jobs = [];
    foreach (Capsule::table('mod_cpm_cv_jobs')->orderByDesc('active')->orderBy('title')->get() as $jb) {
        $jobs[] = ['id' => (int) $jb->id, 'title' => $jb->title, 'active' => (bool) $jb->active,
            'descr' => $jb->descr, 'skills' => $jb->skills, 'location' => $jb->location, 'emptype' => $jb->emptype,
            'titleEn' => $jb->title_en, 'descrEn' => $jb->descr_en, 'skillsEn' => $jb->skills_en, 'emptypeEn' => $jb->emptype_en,
            'image' => (string) ($jb->image ?? ''), 'imageResolved' => cnp_cv_job_image($jb),
            'sections' => $jb->descr_json ? json_decode($jb->descr_json, true) : null,
            'count' => (int) Capsule::table('mod_cpm_cv')->where('job_id', $jb->id)->count()];
    }
    out(['jobs' => $jobs, 'statuses' => cnp_cv_statuses(), 'models' => cnp_cv_models(), 'defaultModel' => cnp_cv_default_model(),
        'imagePresets' => cnp_cv_job_presets(), 'imageBase' => 'apply-assets/jobs/',
        'customImages' => cnp_cv_job_custom_list(),
        'applyUrl' => 'https://my.cloudon.gr/project/apply.php']);

case 'fin_audit_csv':                    // Οι έλεγχοι σε CSV για το λογιστήριο
    if (!$FULL) { fail('forbidden', 403); }
    $sc = (string) ($_GET['section'] ?? '');
    $titles = ['mismatch' => 'asymfonia-vivlion', 'overpaid' => 'yperpliromena',
               'zombie' => 'zombi-syndromes', 'debt' => 'ofeiles'];
    if (!isset($titles[$sc])) { fail('Άγνωστη ενότητα', 404); }

    // Ξαναχρησιμοποιούμε τον ίδιο υπολογισμό μέσω εσωτερικής κλήσης.
    $_GET['section'] = $sc;
    ob_start();
    $keepOut = true;
    // Δεν μπορούμε να καλέσουμε το case· επαναλαμβάνουμε μόνο ό,τι χρειάζεται.
    ob_end_clean();

    $grossBy = []; $paidBy = []; $adjBy = []; $unpaidBy = [];
    foreach (Capsule::table('tblinvoices')->whereNotIn('status', ['Cancelled', 'Draft'])
                 ->selectRaw('userid, SUM(subtotal+tax+tax2) g')->groupBy('userid')->get() as $r) { $grossBy[(int) $r->userid] = (float) $r->g; }
    foreach (Capsule::table('tblaccounts')->where('gateway', '!=', '')->whereNotNull('gateway')
                 ->selectRaw('userid, SUM(amountin) p')->groupBy('userid')->get() as $r) { $paidBy[(int) $r->userid] = (float) $r->p; }
    foreach (Capsule::table('tblaccounts')->where('type', 'invoice_billing_adjustment_credit')
                 ->selectRaw('userid, SUM(amountin) a')->groupBy('userid')->get() as $r) { $adjBy[(int) $r->userid] = (float) $r->a; }
    foreach (Capsule::table('tblinvoices')->where('status', 'Unpaid')
                 ->selectRaw('userid, SUM(total) u')->groupBy('userid')->get() as $r) { $unpaidBy[(int) $r->userid] = (float) $r->u; }

    $nm = function ($id) {
        $x = Capsule::table('tblclients')->where('id', $id)->first();
        return $x ? trim(($x->companyname ?: ($x->firstname . ' ' . $x->lastname))) : '#' . $id;
    };
    $n3 = function ($v) { return number_format((float) $v, 2, ',', ''); };

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $titles[$sc] . '-' . date('Y-m-d') . '.csv"');
    $f3 = fopen('php://output', 'w');
    fwrite($f3, "\xEF\xBB\xBF");

    if ($sc === 'mismatch') {
        fputcsv($f3, ['ID πελάτη', 'Πελάτης', 'Υπολογισμός', 'Ανεξόφλητα WHMCS', 'Διαφορά'], ';');
        foreach ($grossBy as $cid => $g) {
            $mine = $g - ($paidBy[$cid] ?? 0) - ($adjBy[$cid] ?? 0);
            $wh = $unpaidBy[$cid] ?? 0;
            if (abs($mine - $wh) > 1) { fputcsv($f3, [$cid, $nm($cid), $n3($mine), $n3($wh), $n3($mine - $wh)], ';'); }
        }
    } elseif ($sc === 'overpaid') {
        fputcsv($f3, ['Παραστατικό', 'ID πελάτη', 'Πελάτης', 'Αξία', 'Εισπράχθηκαν', 'Πλήθος πληρωμών', 'Διαφορά'], ';');
        foreach (Capsule::select('SELECT invoiceid, COUNT(*) c, SUM(amountin) s FROM tblaccounts
                  WHERE gateway <> "" AND gateway IS NOT NULL AND amountin > 0 AND invoiceid > 0
                  GROUP BY invoiceid HAVING c > 1') as $r) {
            $i = Capsule::table('tblinvoices')->where('id', $r->invoiceid)->first();
            if (!$i) { continue; }
            $g = (float) ($i->subtotal + $i->tax + $i->tax2);
            if ((float) $r->s > $g + 0.01) {
                fputcsv($f3, [$i->invoicenum ?: '#' . $i->id, $i->userid, $nm((int) $i->userid),
                    $n3($g), $n3($r->s), $r->c, $n3((float) $r->s - $g)], ';');
            }
        }
    } elseif ($sc === 'zombie') {
        fputcsv($f3, ['Υπηρεσία', 'Domain', 'ID πελάτη', 'Πελάτης', 'Κατάσταση', 'Ποσό', 'Κύκλος', 'Συνδρομή',
            'Ημ. ακύρωσης', 'Πηγή ημερομηνίας', 'Τελευταία πληρωμή', 'Ποσό', 'Πληρωμή μετά την ακύρωση'], ';');
        foreach (Capsule::table('tblhosting')->whereNotNull('subscriptionid')->where('subscriptionid', '!=', '')
                     ->whereIn('domainstatus', ['Cancelled', 'Terminated', 'Fraud'])->get() as $h) {
            $last = Capsule::table('tblaccounts')->where('userid', $h->userid)->where('gateway', '!=', '')
                ->whereNotNull('gateway')->orderBy('date', 'desc')->first();
            $term = (string) ($h->termination_date ?? '');
            if ($term === '' || strpos($term, '0000') === 0) { $term = ''; }
            $cr = Capsule::table('tblcancelrequests')->where('relid', $h->id)->orderBy('id', 'desc')->first();
            $cancel = $term ?: ($cr ? substr((string) $cr->date, 0, 10) : '');
            $src = $term ? 'τερματισμός' : ($cr ? 'αίτημα πελάτη' : 'τελευταία μεταβολή');
            if (!$cancel && $h->lastupdate && strpos((string) $h->lastupdate, '0000') !== 0) {
                $cancel = substr((string) $h->lastupdate, 0, 10);
            }
            $lp = $last ? substr($last->date, 0, 10) : '';
            fputcsv($f3, [$h->id, $h->domain, $h->userid, $nm((int) $h->userid), $h->domainstatus,
                $n3($h->amount), $h->billingcycle, $h->subscriptionid,
                $cancel, $src, $lp, $last ? $n3($last->amountin) : '',
                ($cancel && $lp && $lp > $cancel) ? 'ΝΑΙ' : ''], ';');
        }
    } else {
        fputcsv($f3, ['ID πελάτη', 'Πελάτης', 'Οφειλή', 'Ανεξόφλητα WHMCS'], ';');
        $d3 = [];
        foreach (Capsule::table('tblclients')->where('status', 'Active')->pluck('id') as $cid) {
            $cid = (int) $cid;
            $b = ($grossBy[$cid] ?? 0) - ($paidBy[$cid] ?? 0) - ($adjBy[$cid] ?? 0);
            if ($b > 1) { $d3[] = [$cid, $nm($cid), $b, $unpaidBy[$cid] ?? 0]; }
        }
        usort($d3, function ($a, $b) { return $b[2] <=> $a[2]; });
        foreach ($d3 as $r) { fputcsv($f3, [$r[0], $r[1], $n3($r[2]), $n3($r[3])], ';'); }
    }
    fclose($f3);
    exit;

case 'fin_audit':                        // Οικονομικοί έλεγχοι — τέσσερα σημεία κινδύνου
    if (!$FULL) { fail('forbidden', 403); }
    $sec = (string) ($in['section'] ?? $_GET['section'] ?? '');

    /* Όλα με συγκεντρωτικά ερωτήματα, όχι βρόχους ανά πελάτη — αλλιώς η σελίδα
       αργεί με μερικές χιλιάδες παραστατικά. */
    $grossBy = []; $paidBy = []; $adjBy = []; $unpaidBy = [];
    foreach (Capsule::table('tblinvoices')->whereNotIn('status', ['Cancelled', 'Draft'])
                 ->selectRaw('userid, SUM(subtotal+tax+tax2) g')->groupBy('userid')->get() as $r) {
        $grossBy[(int) $r->userid] = (float) $r->g;
    }
    foreach (Capsule::table('tblaccounts')->where('gateway', '!=', '')->whereNotNull('gateway')
                 ->selectRaw('userid, SUM(amountin) p')->groupBy('userid')->get() as $r) {
        $paidBy[(int) $r->userid] = (float) $r->p;
    }
    foreach (Capsule::table('tblaccounts')->where('type', 'invoice_billing_adjustment_credit')
                 ->selectRaw('userid, SUM(amountin) a')->groupBy('userid')->get() as $r) {
        $adjBy[(int) $r->userid] = (float) $r->a;
    }
    foreach (Capsule::table('tblinvoices')->where('status', 'Unpaid')
                 ->selectRaw('userid, SUM(total) u')->groupBy('userid')->get() as $r) {
        $unpaidBy[(int) $r->userid] = (float) $r->u;
    }

    $nameOf = function ($id) {
        static $c = [];
        if (!isset($c[$id])) {
            $x = Capsule::table('tblclients')->where('id', $id)->first();
            $c[$id] = $x ? trim(($x->companyname ?: ($x->firstname . ' ' . $x->lastname))) : '#' . $id;
        }
        return $c[$id];
    };

    // ── 1. Ασυμφωνία βιβλίων: ο δικός μας υπολογισμός vs τα ανεξόφλητα του WHMCS
    $mismatch = [];
    foreach ($grossBy as $cid => $g) {
        $mine  = $g - ($paidBy[$cid] ?? 0) - ($adjBy[$cid] ?? 0);
        $whmcs = $unpaidBy[$cid] ?? 0;
        if (abs($mine - $whmcs) > 1) {
            $mismatch[] = ['client' => $cid, 'name' => $nameOf($cid),
                'mine' => round($mine, 2), 'whmcs' => round($whmcs, 2), 'diff' => round($mine - $whmcs, 2)];
        }
    }
    usort($mismatch, function ($a, $b) { return abs($b['diff']) <=> abs($a['diff']); });

    // ── 2. Υπερπληρωμένα παραστατικά
    $overpaid = [];
    foreach (Capsule::select('SELECT invoiceid, COUNT(*) c, SUM(amountin) s FROM tblaccounts
              WHERE gateway <> "" AND gateway IS NOT NULL AND amountin > 0 AND invoiceid > 0
              GROUP BY invoiceid HAVING c > 1') as $r) {
        $i = Capsule::table('tblinvoices')->where('id', $r->invoiceid)->first();
        if (!$i) { continue; }
        $g = (float) ($i->subtotal + $i->tax + $i->tax2);
        if ((float) $r->s > $g + 0.01) {
            $overpaid[] = ['invoice' => (int) $i->id, 'num' => (string) ($i->invoicenum ?: '#' . $i->id),
                'client' => (int) $i->userid, 'name' => $nameOf((int) $i->userid),
                'value' => round($g, 2), 'paid' => round((float) $r->s, 2),
                'n' => (int) $r->c, 'over' => round((float) $r->s - $g, 2)];
        }
    }
    usort($overpaid, function ($a, $b) { return $b['over'] <=> $a['over']; });

    // ── 3. Ζόμπι συνδρομές: ενεργή συνδρομή σε νεκρή υπηρεσία
    $zombie = [];
    foreach (Capsule::table('tblhosting')->whereNotNull('subscriptionid')->where('subscriptionid', '!=', '')
                 ->whereIn('domainstatus', ['Cancelled', 'Terminated', 'Fraud'])->get() as $h) {
        $last = Capsule::table('tblaccounts')->where('userid', $h->userid)->where('gateway', '!=', '')
            ->whereNotNull('gateway')->orderBy('date', 'desc')->first();
        /* Ημερομηνία ακύρωσης: πρώτα το termination_date, αλλιώς το αίτημα του
           πελάτη, αλλιώς η τελευταία μεταβολή της εγγραφής. Χωρίς αυτήν δεν
           φαίνεται αν η πληρωμή ήρθε ΜΕΤΑ την ακύρωση — που είναι το κρίσιμο. */
        $term = (string) ($h->termination_date ?? '');
        if ($term === '' || strpos($term, '0000') === 0) { $term = ''; }
        $cr = Capsule::table('tblcancelrequests')->where('relid', $h->id)->orderBy('id', 'desc')->first();
        $cancel = $term ?: ($cr ? substr((string) $cr->date, 0, 10) : '');
        $src = $term ? 'τερματισμός' : ($cr ? 'αίτημα πελάτη' : ($h->lastupdate ? 'τελευταία μεταβολή' : ''));
        if (!$cancel && $h->lastupdate && strpos((string) $h->lastupdate, '0000') !== 0) {
            $cancel = substr((string) $h->lastupdate, 0, 10);
        }
        $lp = $last ? substr($last->date, 0, 10) : null;

        $zombie[] = ['service' => (int) $h->id, 'client' => (int) $h->userid, 'name' => $nameOf((int) $h->userid),
            'domain' => (string) $h->domain, 'status' => (string) $h->domainstatus,
            'amount' => (float) $h->amount, 'cycle' => (string) $h->billingcycle,
            'sub' => (string) $h->subscriptionid,
            'cancel' => $cancel ?: null,
            'cancelSrc' => $src,
            'lastPay' => $lp,
            'lastAmt' => $last ? (float) $last->amountin : null,
            // Πληρωμή μετά την ακύρωση = εισπράττεις για υπηρεσία που δεν παρέχεις
            'afterCancel' => ($cancel && $lp && $lp > $cancel)];
    }
    usort($zombie, function ($a, $b) { return strcmp((string) $b['lastPay'], (string) $a['lastPay']); });

    // ── 4. Πραγματικές οφειλές ενεργών πελατών
    $debt = [];
    $activeIds = Capsule::table('tblclients')->where('status', 'Active')->pluck('id')->all();
    foreach ($activeIds as $cid) {
        $cid = (int) $cid;
        $bal = ($grossBy[$cid] ?? 0) - ($paidBy[$cid] ?? 0) - ($adjBy[$cid] ?? 0);
        if ($bal > 1) {
            $debt[] = ['client' => $cid, 'name' => $nameOf($cid), 'balance' => round($bal, 2),
                'unpaid' => round($unpaidBy[$cid] ?? 0, 2)];
        }
    }
    usort($debt, function ($a, $b) { return $b['balance'] <=> $a['balance']; });

    $sum = [
        'mismatch' => ['n' => count($mismatch), 'sum' => round(array_sum(array_map('abs', array_column($mismatch, 'diff'))), 2)],
        'overpaid' => ['n' => count($overpaid), 'sum' => round(array_sum(array_column($overpaid, 'over')), 2)],
        'zombie'   => ['n' => count($zombie),   'sum' => round(array_sum(array_column($zombie, 'amount')), 2)],
        'debt'     => ['n' => count($debt),     'sum' => round(array_sum(array_column($debt, 'balance')), 2)],
    ];

    $data = ['summary' => $sum];
    if ($sec === 'mismatch') { $data['rows'] = array_slice($mismatch, 0, 200); }
    elseif ($sec === 'overpaid') { $data['rows'] = array_slice($overpaid, 0, 200); }
    elseif ($sec === 'zombie')   { $data['rows'] = $zombie; }
    elseif ($sec === 'debt')     { $data['rows'] = array_slice($debt, 0, 200); }
    out($data);

case 'pay_statement_csv':                // Η καρτέλα σε CSV για το λογιστήριο
    if (!$FULL) { fail('forbidden', 403); }
    $cid2 = (int) ($_GET['client'] ?? 0);
    $cl2 = $cid2 ? Capsule::table('tblclients')->where('id', $cid2)->first() : null;
    if (!$cl2) { fail('Ο πελάτης δεν βρέθηκε', 404); }

    $ev2 = [];
    foreach (Capsule::table('tblinvoices')->where('userid', $cid2)->whereNotIn('status', ['Draft'])->get() as $i) {
        $gr = (float) ($i->subtotal + $i->tax + $i->tax2);
        if ($i->status === 'Cancelled' || $gr <= 0) { continue; }
        $ev2[] = [substr($i->date, 0, 10), 'Παραστατικό ' . ($i->invoicenum ?: '#' . $i->id), $gr, 0.0, $i->status, ''];
    }
    foreach (Capsule::table('tblaccounts')->where('userid', $cid2)
                 ->where('gateway', '!=', '')->whereNotNull('gateway')->get() as $a) {
        if ((float) $a->amountin <= 0) { continue; }
        $iv = $a->invoiceid ? Capsule::table('tblinvoices')->where('id', $a->invoiceid)->first() : null;
        $ev2[] = [substr($a->date, 0, 10),
            'Πληρωμή ' . $a->gateway . ($iv ? ' — παρ. ' . ($iv->invoicenum ?: '#' . $iv->id) : ''),
            0.0, (float) $a->amountin, '', (string) $a->transid];
    }
    usort($ev2, function ($x, $y) { $c = strcmp($x[0], $y[0]); return $c !== 0 ? $c : ($y[2] <=> $x[2]); });

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kartela-' . $cid2 . '-' . date('Y-m-d') . '.csv"');
    $f2 = fopen('php://output', 'w');
    fwrite($f2, "\xEF\xBB\xBF");
    $n2 = function ($v) { return $v ? number_format((float) $v, 2, ',', '') : ''; };

    fputcsv($f2, ['Καρτέλα πελάτη', trim(($cl2->companyname ?: ($cl2->firstname . ' ' . $cl2->lastname)))], ';');
    fputcsv($f2, ['Υπεύθυνος', trim($cl2->firstname . ' ' . $cl2->lastname), 'Email', $cl2->email], ';');
    fputcsv($f2, [], ';');
    fputcsv($f2, ['Ημερομηνία', 'Κίνηση', 'Χρέωση', 'Πίστωση', 'Υπόλοιπο', 'Κατάσταση', 'Transaction ID'], ';');

    $b2 = 0.0; $d2 = 0.0; $c2 = 0.0;
    foreach ($ev2 as $e) {
        $b2 += $e[2] - $e[3];
        $d2 += $e[2];
        $c2 += $e[3];
        fputcsv($f2, [$e[0], $e[1], $n2($e[2]), $n2($e[3]), number_format($b2, 2, ',', ''), $e[4], $e[5]], ';');
    }
    fputcsv($f2, [], ';');
    fputcsv($f2, ['ΣΥΝΟΛΑ', '', number_format($d2, 2, ',', ''), number_format($c2, 2, ',', ''),
        number_format($b2, 2, ',', '')], ';');
    fputcsv($f2, [$b2 > 0.005 ? 'ΟΦΕΙΛΗ ΠΕΛΑΤΗ' : ($b2 < -0.005 ? 'ΠΡΟΠΛΗΡΩΜΗ' : 'ΜΗΔΕΝΙΚΟ ΥΠΟΛΟΙΠΟ'), '', '', '',
        number_format(abs($b2), 2, ',', '')], ';');
    fclose($f2);
    exit;

case 'pay_statement':                    // Καρτέλα πελάτη: χρέωση / πίστωση / υπόλοιπο
    /* Η μόνη μορφή που διαβάζεται λογιστικά. ΧΡΕΩΣΗ = αξία παραστατικού,
       ΠΙΣΤΩΣΗ = πραγματικά χρήματα που μπήκαν. Οι εσωτερικές πιστώσεις ΔΕΝ
       μετριούνται — δεν είναι νέα χρήματα, απλώς μετακινούν υπάρχοντα, και
       αν τις μετρούσαμε θα διπλομετρούσαμε το ίδιο ποσό. */
    if (!$FULL) { fail('forbidden', 403); }
    $cid = (int) ($in['client'] ?? $_GET['client'] ?? 0);
    if (!$cid) { fail('Δώσε πελάτη'); }

    $cl = Capsule::table('tblclients')->where('id', $cid)->first();
    if (!$cl) { fail('Ο πελάτης δεν βρέθηκε', 404); }

    $ev = [];

    foreach (Capsule::table('tblinvoices')->where('userid', $cid)
                 ->whereNotIn('status', ['Draft'])->get() as $i) {
        $gross = (float) ($i->subtotal + $i->tax + $i->tax2);
        if ($i->status === 'Cancelled' || $gross <= 0) { continue; }
        $ev[] = [
            'date'   => $i->date,
            'sort'   => substr($i->date, 0, 10) . ' 00:00:00',
            'kind'   => 'invoice',
            'label'  => 'Παραστατικό ' . ($i->invoicenum ?: '#' . $i->id),
            'ref'    => (int) $i->id,
            'num'    => (string) ($i->invoicenum ?: ''),
            'debit'  => $gross,
            'credit' => 0.0,
            'note'   => $i->status,
        ];
    }

    foreach (Capsule::table('tblaccounts')->where('userid', $cid)
                 ->where('gateway', '!=', '')->whereNotNull('gateway')->get() as $a) {
        if ((float) $a->amountin <= 0) { continue; }
        $inv = $a->invoiceid ? Capsule::table('tblinvoices')->where('id', $a->invoiceid)->first() : null;
        $ev[] = [
            'date'   => $a->date,
            'sort'   => $a->date,
            'kind'   => 'payment',
            'label'  => 'Πληρωμή ' . $a->gateway . ($inv ? ' — παρ. ' . ($inv->invoicenum ?: '#' . $inv->id) : ''),
            'ref'    => (int) $a->invoiceid,
            'num'    => (string) ($inv->invoicenum ?? ''),
            'debit'  => 0.0,
            'credit' => (float) $a->amountin,
            'note'   => (string) $a->transid,
        ];
    }

    usort($ev, function ($x, $y) {
        $c = strcmp($x['sort'], $y['sort']);
        // Ίδια μέρα: πρώτα η χρέωση, μετά η πίστωση — έτσι διαβάζεται σωστά.
        return $c !== 0 ? $c : ($y['debit'] <=> $x['debit']);
    });

    $bal = 0.0; $td = 0.0; $tc = 0.0;
    foreach ($ev as $k => $e) {
        $bal += $e['debit'] - $e['credit'];
        $td += $e['debit'];
        $tc += $e['credit'];
        $ev[$k]['balance'] = round($bal, 2);
    }

    out([
        'client'  => ['id' => $cid, 'name' => trim(($cl->companyname ?: ($cl->firstname . ' ' . $cl->lastname))),
                      'person' => trim($cl->firstname . ' ' . $cl->lastname), 'email' => $cl->email],
        'rows'    => $ev,
        'debit'   => round($td, 2),
        'credit'  => round($tc, 2),
        'balance' => round($bal, 2),
    ]);

case 'pay_trace_export':                 // Εξαγωγή συμφωνίας σε CSV για το λογιστήριο
    if (!$FULL) { fail('forbidden', 403); }
    $qx  = trim((string) ($_GET['q'] ?? ''));
    $all = !empty($_GET['all']);
    $from = trim((string) ($_GET['from'] ?? ''));
    $to   = trim((string) ($_GET['to'] ?? ''));

    // Μόνο πραγματικές εισπράξεις μέσω gateway — όχι εσωτερικές πιστώσεις.
    $qb = Capsule::table('tblaccounts')->where('gateway', '!=', '')->whereNotNull('gateway');
    if ($from !== '') { $qb->where('date', '>=', $from . ' 00:00:00'); }
    if ($to !== '')   { $qb->where('date', '<=', $to . ' 23:59:59'); }
    if (!$all && $qx !== '') {
        $ids = [];
        foreach (Capsule::table('tblgatewaylog')->where('data', 'like', '%' . $qx . '%')->limit(500)->get() as $g) {
            if (preg_match('/(?:^|\n)\s*txn_id\s*=>\s*(\S+)/', (string) $g->data, $m)) { $ids[] = trim($m[1]); }
        }
        $qb->where(function ($w) use ($qx, $ids) {
            $w->where('transid', 'like', '%' . $qx . '%')->orWhere('description', 'like', '%' . $qx . '%');
            if ($ids) { $w->orWhereIn('transid', $ids); }
        });
    }

    $rowsX = $qb->orderBy('date')->get();

    // Μαζική ανάγνωση των IPN: το email του πληρωτή δεν υπάρχει πουθενά αλλού.
    $payerOf = [];
    $typeOf  = [];
    foreach ($rowsX as $a) {
        if (!$a->transid) { continue; }
        $g = Capsule::table('tblgatewaylog')->where('data', 'like', '%' . $a->transid . '%')->first();
        if (!$g) { continue; }
        if (preg_match('/(?:^|\n)\s*payer_email\s*=>\s*([^\n]*)/', (string) $g->data, $m)) { $payerOf[$a->transid] = trim($m[1]); }
        if (preg_match('/(?:^|\n)\s*txn_type\s*=>\s*([^\n]*)/', (string) $g->data, $m))    { $typeOf[$a->transid]  = trim($m[1]); }
    }

    $name = 'symfonia-pliromon-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');

    $fh = fopen('php://output', 'w');
    fwrite($fh, "\xEF\xBB\xBF");            // BOM — αλλιώς το Excel δείχνει κινέζικα στα ελληνικά

    $num = function ($v) { return number_format((float) $v, 2, ',', ''); };   // ελληνικό δεκαδικό
    $types = ['subscr_payment' => 'Συνδρομή', 'web_accept' => 'Χειροκίνητη', 'express_checkout' => 'Express'];

    fputcsv($fh, ['Ημερομηνία', 'Ώρα', 'Ποσό', 'Προμήθεια', 'Καθαρό', 'Τρόπος πληρωμής', 'Τύπος',
        'Πληρωτής (email)', 'Transaction ID', 'ID πελάτη', 'Επωνυμία', 'Υπεύθυνος', 'Email πελάτη',
        'Παραστατικό', 'Ημ. παραστατικού', 'Ποσό παραστατικού', 'Πίστωση στο παραστατικό',
        'Υπόλοιπο', 'Κατάσταση', 'Υπερπληρωμή προς', 'Ποσό υπερπληρωμής'], ';');

    $tot = 0; $totFee = 0;
    foreach ($rowsX as $a) {
        $cl  = Capsule::table('tblclients')->where('id', $a->userid)->first();
        $inv = $a->invoiceid ? Capsule::table('tblinvoices')->where('id', $a->invoiceid)->first() : null;
        $tt  = $typeOf[$a->transid] ?? '';
        $onward = ($a->userid && $a->invoiceid)
            ? (cnp_credit_chain((int) $a->userid)[(int) $a->invoiceid] ?? []) : [];
        $tot += (float) $a->amountin;
        $totFee += (float) $a->fees;

        fputcsv($fh, [
            substr($a->date, 0, 10), substr($a->date, 11, 5),
            $num($a->amountin), $num($a->fees), $num((float) $a->amountin - (float) $a->fees),
            $a->gateway,
            $types[$tt] ?? $tt,
            $payerOf[$a->transid] ?? '',
            $a->transid,
            $a->userid,
            $cl->companyname ?? '',
            $cl ? trim($cl->firstname . ' ' . $cl->lastname) : '',
            $cl->email ?? '',
            $inv->invoicenum ?? '',
            $inv ? substr($inv->date, 0, 10) : '',
            $inv ? $num($inv->subtotal + $inv->tax + $inv->tax2) : '',
            $inv ? $num($inv->credit) : '',
            $inv ? $num($inv->total) : '',
            $inv->status ?? '',
            implode(' + ', array_map(function ($x) { return $x['num']; }, $onward)),
            implode(' + ', array_map(function ($x) use ($num) { return $num($x['amount']); }, $onward)),
        ], ';');
    }

    fputcsv($fh, [], ';');
    fputcsv($fh, ['ΣΥΝΟΛΟ', '', $num($tot), $num($totFee), $num($tot - $totFee)], ';');
    fclose($fh);
    exit;

case 'pay_trace':                        // Συμφωνία πληρωμών: πού πήγε κάθε είσπραξη
    /* ΓΙΑΤΙ ΥΠΑΡΧΕΙ: ένας λογαριασμός PayPal μπορεί να πληρώνει ΠΟΛΛΟΥΣ πελάτες
       WHMCS, και ένας πελάτης να πληρώνεται από πολλά πρόσωπα. Η αντιστοίχιση
       είναι πολλά-προς-πολλά και χειροκίνητα παίρνει ώρες. */
    if (!$FULL) { fail('forbidden', 403); }
    $q = trim((string) ($in['q'] ?? $_GET['q'] ?? ''));
    if (mb_strlen($q) < 3) { fail('Δώσε email πληρωτή, transaction ID, ποσό ή όνομα (3+ χαρακτήρες)'); }

    $rows = [];
    $seen = [];

    // 1) Άμεση αναζήτηση στις κινήσεις (transid, περιγραφή)
    $direct = Capsule::table('tblaccounts')
        ->where(function ($w) use ($q) {
            $w->where('transid', 'like', '%' . $q . '%')->orWhere('description', 'like', '%' . $q . '%');
        })->orderBy('date', 'desc')->limit(200)->get();

    // 2) Αναζήτηση στα logs των gateways — εκεί ζει το email του πληρωτή
    $viaLog = [];
    foreach (Capsule::table('tblgatewaylog')->where('data', 'like', '%' . $q . '%')
                 ->orderBy('date', 'desc')->limit(300)->get() as $g) {
        if (preg_match('/(?:^|\n)\s*txn_id\s*=>\s*(\S+)/', (string) $g->data, $m)) {
            $viaLog[trim($m[1])] = $g;
        }
    }
    if ($viaLog) {
        foreach (Capsule::table('tblaccounts')->whereIn('transid', array_keys($viaLog))->get() as $a) {
            $direct->push($a);
        }
    }

    // 3) Αν μοιάζει με ποσό, ψάξε και με ποσό
    if (preg_match('/^\d+([.,]\d{1,2})?$/', $q)) {
        $amt = (float) str_replace(',', '.', $q);
        foreach (Capsule::table('tblaccounts')->whereBetween('amountin', [$amt - 0.02, $amt + 0.02])
                     ->orderBy('date', 'desc')->limit(100)->get() as $a) {
            $direct->push($a);
        }
    }

    foreach ($direct as $a) {
        if (isset($seen[$a->id])) { continue; }
        $seen[$a->id] = true;

        $cl  = Capsule::table('tblclients')->where('id', $a->userid)->first();
        $inv = $a->invoiceid ? Capsule::table('tblinvoices')->where('id', $a->invoiceid)->first() : null;

        // Στοιχεία πληρωτή από το IPN — δείχνουν ΠΟΙΟΣ πλήρωσε στ' αλήθεια
        $payer = null; $ptype = null;
        if ($a->transid && isset($viaLog[$a->transid])) {
            $g = $viaLog[$a->transid];
        } else {
            $g = $a->transid
                ? Capsule::table('tblgatewaylog')->where('data', 'like', '%' . $a->transid . '%')->first()
                : null;
        }
        if ($g) {
            foreach (['payer_email', 'txn_type'] as $k) {
                if (preg_match('/(?:^|\n)\s*' . $k . '\s*=>\s*([^\n]*)/', (string) $g->data, $m)) {
                    if ($k === 'payer_email') { $payer = trim($m[1]); } else { $ptype = trim($m[1]); }
                }
            }
        }

        $rows[] = [
            'id'       => (int) $a->id,
            'date'     => $a->date,
            'amount'   => (float) $a->amountin,
            'fees'     => (float) $a->fees,
            'gateway'  => (string) $a->gateway,
            'transid'  => (string) $a->transid,
            'kind'     => (string) $a->type,
            'payer'    => $payer,
            'ptype'    => $ptype,
            'clientId' => (int) $a->userid ?: null,
            'client'   => $cl ? trim(($cl->companyname ?: ($cl->firstname . ' ' . $cl->lastname))) : null,
            'person'   => $cl ? trim($cl->firstname . ' ' . $cl->lastname) : null,
            'email'    => $cl->email ?? null,
            'invoiceId' => (int) $a->invoiceid ?: null,
            'invoice'  => $inv->invoicenum ?? null,
            // ΠΡΟΣΟΧΗ: το tblinvoices.total είναι το ΥΠΟΛΟΙΠΟ μετά την πίστωση,
            // όχι το ποσό του παραστατικού. Το πραγματικό ποσό είναι subtotal+ΦΠΑ.
            'invTotal' => $inv ? (float) ($inv->subtotal + $inv->tax + $inv->tax2) : null,
            'invDue'   => $inv ? (float) $inv->total : null,
            'invCredit' => $inv ? (float) $inv->credit : null,
            'invStatus' => $inv->status ?? null,
            // Αν αυτή η είσπραξη δημιούργησε πίστωση, πού πήγε τελικά
            'onward'   => ($a->userid && $a->invoiceid)
                ? (cnp_credit_chain((int) $a->userid)[(int) $a->invoiceid] ?? [])
                : [],
        ];
    }

    usort($rows, function ($x, $y) { return strcmp($y['date'], $x['date']); });
    $rows = array_slice($rows, 0, 150);

    // Σύνοψη ανά πελάτη — η απάντηση στο «πού πήγαν τα λεφτά»
    $byClient = [];
    foreach ($rows as $r) {
        $k = $r['clientId'] ?: 0;
        if (!isset($byClient[$k])) {
            $byClient[$k] = ['id' => $r['clientId'], 'client' => $r['client'] ?: '(χωρίς πελάτη)', 'n' => 0, 'sum' => 0.0];
        }
        $byClient[$k]['n']++;
        $byClient[$k]['sum'] += $r['amount'];
    }
    usort($byClient, function ($x, $y) { return $y['sum'] <=> $x['sum']; });

    out(['q' => $q, 'rows' => $rows, 'byClient' => array_values($byClient),
         'total' => array_sum(array_column($rows, 'amount'))]);

case 'cv_job_views':                     // επισκεψιμότητα αγγελιών (ανεξάρτητα από αιτήσεις)
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    require_once __DIR__ . '/../modules/addons/cloudonprojects/lib/JobViews.php';
    $vDays = max(1, min(365, (int) ($in['days'] ?? 30)));
    $vStats = WHMCS\Module\Addon\CloudonProjects\JobViews::stats($vDays);
    $vRows = [];
    foreach (Capsule::table('mod_cpm_cv_jobs')->orderByDesc('active')->orderBy('title')->get() as $jb) {
        $s = $vStats[(int) $jb->id] ?? ['views' => 0, 'forms' => 0, 'uniques' => 0, 'last_at' => null];
        // Αιτήσεις της περιόδου, για να συγκρίνεται με τις προβολές της ίδιας περιόδου.
        $apps = (int) Capsule::table('mod_cpm_cv')->where('job_id', $jb->id)
            ->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime('-' . $vDays . ' days')))->count();
        $vRows[] = [
            'id'       => (int) $jb->id,
            'title'    => $jb->title,
            'active'   => (bool) $jb->active,
            'views'    => $s['views'],
            'uniques'  => $s['uniques'],
            'forms'    => $s['forms'],
            'apps'     => $apps,
            'appsAll'  => (int) Capsule::table('mod_cpm_cv')->where('job_id', $jb->id)->count(),
            'lastAt'   => $s['last_at'],
            'series'   => array_values(WHMCS\Module\Addon\CloudonProjects\JobViews::daily((int) $jb->id, min(30, $vDays))),
        ];
    }
    $vPage = $vStats[0] ?? ['views' => 0, 'uniques' => 0];
    out([
        'days'      => $vDays,
        'rows'      => $vRows,
        'page'      => ['views' => $vPage['views'], 'uniques' => $vPage['uniques'],
                        'series' => array_values(WHMCS\Module\Addon\CloudonProjects\JobViews::daily(0, min(30, $vDays)))],
        'breakdown' => WHMCS\Module\Addon\CloudonProjects\JobViews::breakdown($vDays),
    ]);

case 'cv_job_save':                      // δημιουργία/επεξεργασία αγγελίας
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $title = mb_substr(trim($in['title'] ?? ''), 0, 190);
    if ($title === '') { fail('Δώσε τίτλο θέσης'); }
    $sections = (isset($in['sections']) && is_array($in['sections'])) ? $in['sections'] : null;
    $data = ['title' => $title, 'descr' => mb_substr(trim($in['descr'] ?? ''), 0, 12000),
        'skills' => mb_substr(trim($in['skills'] ?? ''), 0, 3000), 'location' => mb_substr(trim($in['location'] ?? ''), 0, 120),
        'emptype' => mb_substr(trim($in['emptype'] ?? ''), 0, 40), 'active' => !empty($in['active']) ? 1 : 0,
        'title_en' => mb_substr(trim($in['titleEn'] ?? ''), 0, 190), 'descr_en' => mb_substr(trim($in['descrEn'] ?? ''), 0, 12000),
        'skills_en' => mb_substr(trim($in['skillsEn'] ?? ''), 0, 3000), 'emptype_en' => mb_substr(trim($in['emptypeEn'] ?? ''), 0, 40),
        'image' => (array_key_exists($in['image'] ?? '', cnp_cv_job_presets())
            || cnp_cv_job_image_is_custom($in['image'] ?? '')) ? $in['image'] : '',
        'descr_json' => $sections ? mb_substr(json_encode($sections, JSON_UNESCAPED_UNICODE), 0, 30000) : null];
    $id = (int) ($in['id'] ?? 0);
    if ($id) { Capsule::table('mod_cpm_cv_jobs')->where('id', $id)->update($data); }
    else { $data['created_at'] = date('Y-m-d H:i:s'); $id = Capsule::table('mod_cpm_cv_jobs')->insertGetId($data); }
    out(['ok' => true, 'id' => $id]);

case 'cv_job_image_upload':              // ανέβασμα δικής μας φωτογραφίας θέσης
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $f = $_FILES['file'] ?? null;
    if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) { fail('Δεν ανέβηκε αρχείο.'); }
    if ($f['size'] > 8 * 1024 * 1024) { fail('Το αρχείο ξεπερνά τα 8 MB.'); }
    $info = @getimagesize($f['tmp_name']);
    if (!$info) { fail('Το αρχείο δεν είναι έγκυρη εικόνα.'); }
    $allowed = [IMAGETYPE_JPEG => 1, IMAGETYPE_PNG => 1, IMAGETYPE_WEBP => 1];
    if (!isset($allowed[$info[2]])) { fail('Επιτρέπονται JPG, PNG ή WebP.'); }

    $src = null;
    if ($info[2] === IMAGETYPE_JPEG) { $src = @imagecreatefromjpeg($f['tmp_name']); }
    elseif ($info[2] === IMAGETYPE_PNG) { $src = @imagecreatefrompng($f['tmp_name']); }
    elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) { $src = @imagecreatefromwebp($f['tmp_name']); }
    if (!$src) { fail('Δεν ήταν δυνατή η επεξεργασία της εικόνας.'); }

    // Cover 1600×900 (16:9 — πρακτικά ίδια αναλογία με τις έτοιμες 900×520)
    // center crop χωρίς παραμόρφωση.
    $tw = 1600; $th = 900;
    $sw = imagesx($src); $sh = imagesy($src);
    $scale = max($tw / $sw, $th / $sh);
    $nw = (int) ceil($sw * $scale); $nh = (int) ceil($sh * $scale);
    $dst = imagecreatetruecolor($tw, $th);
    imagecopyresampled($dst, $src, (int) (($tw - $nw) / 2), (int) (($th - $nh) / 2), 0, 0, $nw, $nh, $sw, $sh);
    imagedestroy($src);

    $dir = cnp_cv_job_custom_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { imagedestroy($dst); fail('Ο φάκελος εικόνων δεν είναι εγγράψιμος.'); }
    $stem = 'job-' . date('Ymd') . '-' . bin2hex(random_bytes(4));
    $path = $dir . '/' . $stem . '.jpg';
    $okw = imagejpeg($dst, $path, 82);
    imagedestroy($dst);
    if (!$okw) { fail('Αποτυχία αποθήκευσης.'); }
    @chmod($path, 0644);
    out(['ok' => true, 'image' => 'custom/' . $stem]);

case 'cv_job_image_delete':              // διαγραφή ανεβασμένης φωτογραφίας
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $img = (string) ($in['image'] ?? '');
    if (!cnp_cv_job_image_is_custom($img)) { fail('Μη έγκυρη εικόνα.'); }
    // Μην τη σβήσεις αν τη χρησιμοποιεί θέση.
    $used = (int) Capsule::table('mod_cpm_cv_jobs')->where('image', $img)->count();
    if ($used > 0) { fail('Χρησιμοποιείται από ' . $used . ' θέση/θέσεις.'); }
    @unlink(__DIR__ . '/apply-assets/jobs/' . $img . '.jpg');
    out(['ok' => true]);

case 'cv_job_del':                       // διαγραφή (ή αρχειοθέτηση αν έχει υποψηφίους)
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $id = (int) ($in['id'] ?? 0);
    $cnt = (int) Capsule::table('mod_cpm_cv')->where('job_id', $id)->count();
    if ($cnt > 0) {
        Capsule::table('mod_cpm_cv_jobs')->where('id', $id)->update(['active' => 0]);
        out(['ok' => true, 'archived' => true, 'msg' => 'Έχει ' . $cnt . ' υποψηφίους — απενεργοποιήθηκε αντί διαγραφής']);
    }
    Capsule::table('mod_cpm_cv_jobs')->where('id', $id)->delete();
    out(['ok' => true, 'deleted' => true]);

case 'cv_job_draft':                     // ✨ AI σύνταξη δομημένης ΔΙΓΛΩΣΣΗΣ αγγελίας
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $title = mb_substr(trim($in['title'] ?? ''), 0, 190);
    if ($title === '') { fail('Δώσε πρώτα τίτλο θέσης'); }
    $key = trim(Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', 'ai_api_key')->value('value') ?: '');
    if ($key === '') { fail('Δεν έχει οριστεί κλειδί AI (Ρυθμίσεις → AI)'); }
    $skills = mb_substr(trim($in['skills'] ?? ''), 0, 2000);
    $loc = mb_substr(trim($in['location'] ?? ''), 0, 120);
    $emp = mb_substr(trim($in['emptype'] ?? ''), 0, 40);
    $hint = mb_substr(trim($in['hint'] ?? ''), 0, 1500);
    $mode = ($in['mode'] ?? '') === 'translate' ? 'translate' : 'draft';   // translate: μεταφράζει υπάρχον EL→EN
    if ($mode === 'translate') {
        $src = mb_substr(trim($in['descr'] ?? ''), 0, 12000);
        if ($src === '') { fail('Δεν υπάρχει ελληνικό κείμενο για μετάφραση'); }
        $prompt = "Μετάφρασε την παρακάτω αγγελία εργασίας από τα Ελληνικά στα Αγγλικά, διατηρώντας ΑΚΡΙΒΩΣ την ίδια δομή "
            . "(εισαγωγή, αρμοδιότητες, προσόντα, παροχές) σε επαγγελματικό ύφος.\n\nΤίτλος: {$title}\n\nΚείμενο:\n{$src}\n\n"
            . "Δώσε επίσης τον τίτλο & τα skills στα Αγγλικά. Απάντησε ΜΟΝΟ με JSON: "
            . "{\"en\":{\"title\":\"...\",\"intro\":\"...\",\"responsibilities\":[\"..\"],\"requirements\":[\"..\"],\"benefits\":[\"..\"],\"skills\":\"a, b, c\"}}";
    } else {
        $prompt = "Είσαι HR copywriter της CloudOn (πάροχος cloud/IT υπηρεσιών, έδρα Αθήνα). "
            . "Σύνταξε ελκυστική, ΑΝΑΛΥΤΙΚΗ, επαγγελματική αγγελία εργασίας για την παρακάτω θέση, ΚΑΙ στα Ελληνικά ΚΑΙ στα Αγγλικά.\n\n"
            . "Τίτλος: {$title}\n" . ($loc ? "Τοποθεσία: {$loc}\n" : '') . ($emp ? "Τύπος: {$emp}\n" : '')
            . ($skills ? "Δεξιότητες/απαιτήσεις: {$skills}\n" : '') . ($hint ? "Επιπλέον οδηγίες: {$hint}\n" : '')
            . "\nΚάθε γλώσσα να έχει: intro (2-4 προτάσεις για τη θέση & την ομάδα), responsibilities (5-8 αρμοδιότητες), "
            . "requirements (5-8 προσόντα/δεξιότητες), benefits (4-6 παροχές/ευκαιρίες εξέλιξης). Αναλυτικά & συγκεκριμένα, ΟΧΙ γενικόλογα. "
            . "Πρότεινε και 6-12 skills (comma-separated). Καθαρό κείμενο, ΟΧΙ markdown/αστεράκια.\n\n"
            . "Απάντησε ΜΟΝΟ με έγκυρο JSON: {\"el\":{\"intro\":\"..\",\"responsibilities\":[\"..\"],\"requirements\":[\"..\"],\"benefits\":[\"..\"],\"skills\":\"..\"},"
            . "\"en\":{\"title\":\"english title\",\"intro\":\"..\",\"responsibilities\":[\"..\"],\"requirements\":[\"..\"],\"benefits\":[\"..\"],\"skills\":\"..\"}}";
    }
    $res = cnp_anthropic($key, cnp_cv_default_model(), [['type' => 'text', 'text' => $prompt]], 4000);
    if (!$res['ok']) { fail($res['error']); }
    $d = cnp_json_extract($res['text']);
    if (!$d || (empty($d['el']) && empty($d['en']))) { fail('Η AI επέστρεψε μη έγκυρη απάντηση — δοκίμασε ξανά'); }
    out(['ok' => true, 'sections' => $d]);

case 'cv_list':
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $job = (int) ($_GET['job'] ?? 0); $status = $_GET['status'] ?? ''; $sq = trim($_GET['q'] ?? '');
    $dupsOnly = !empty($_GET['dups']);
    // emails που εμφανίζονται >1 φορά (διπλότυπα, χωρίς διαγραφή)
    $dupEmails = [];
    foreach (Capsule::table('mod_cpm_cv')->whereRaw("TRIM(email) <> ''")
        ->groupByRaw('LOWER(TRIM(email))')->havingRaw('COUNT(*) > 1')
        ->selectRaw('LOWER(TRIM(email)) e, COUNT(*) c')->get() as $de) { $dupEmails[$de->e] = (int) $de->c; }
    $applyBase = function ($q) use ($job, $sq, $dupsOnly, $dupEmails) {
        if ($job) { $q->where('job_id', $job); }
        if (mb_strlen($sq) >= 2) {
            $like = '%' . $sq . '%';
            $q->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('job_title', 'like', $like);
            });
        }
        if ($dupsOnly) { $q->whereIn(Capsule::raw('LOWER(TRIM(email))'), array_keys($dupEmails) ?: ['']); }
        return $q;
    };
    // πλήθη ανά στάδιο (με φίλτρο θέσης/αναζήτησης)
    $counts = [];
    foreach ($applyBase(Capsule::table('mod_cpm_cv'))->groupBy('status')->selectRaw('status, COUNT(*) c')->get() as $c) { $counts[$c->status] = (int) $c->c; }
    $totalAll = array_sum($counts);
    // σελιδοποίηση
    $per = in_array((int) ($_GET['per'] ?? 0), [25, 50, 100, 200], true) ? (int) $_GET['per'] : 50;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $lq = $applyBase(Capsule::table('mod_cpm_cv'));
    if ($status !== '') { $lq->where('status', $status); }
    $filtered = (clone $lq)->count();
    $pages = max(1, (int) ceil($filtered / $per));
    if ($page > $pages) { $page = $pages; }
    $rows = $lq->orderByDesc('applied_at')->orderByDesc('id')->offset(($page - 1) * $per)->limit($per)->get();
    $items = [];
    foreach ($rows as $r) {
        $ai = $r->ai_json ? json_decode($r->ai_json, true) : null;
        $items[] = ['id' => (int) $r->id, 'name' => $r->name, 'email' => $r->email, 'phone' => $r->phone,
            'jobTitle' => $r->job_title, 'status' => $r->status, 'rating' => (int) $r->rating,
            'aiScore' => $r->ai_score !== null ? (int) $r->ai_score : null,
            'fit' => $ai['fit'] ?? null, 'category' => $ai['category'] ?? null, 'seniority' => $ai['seniority'] ?? null,
            'decision' => $ai['decision'] ?? null, 'aiGen' => isset($ai['aiGenerated']['verdict']) ? $ai['aiGenerated']['verdict'] : null,
            'hasCv' => ($r->cv_stored !== '' || !empty($r->cv_storage_id)), 'photo' => ($r->photo !== '' || !empty($r->photo_storage_id)),
            'dup' => (trim($r->email) !== '' && isset($dupEmails[mb_strtolower(trim($r->email))])) ? $dupEmails[mb_strtolower(trim($r->email))] : 0,
            'assignee' => $r->assignee ? (int) $r->assignee : null, 'source' => $r->source, 'appliedAt' => $r->applied_at];
    }
    out(['items' => $items, 'counts' => $counts, 'totalAll' => $totalAll, 'filtered' => $filtered,
        'dupTotal' => array_sum($dupEmails), 'page' => $page, 'per' => $per, 'pages' => $pages, 'statuses' => cnp_cv_statuses()]);

case 'cv_get':
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $r = Capsule::table('mod_cpm_cv')->where('id', (int) ($_GET['id'] ?? 0))->first();
    if (!$r) { fail('notfound', 404); }
    $ai = $r->ai_json ? json_decode($r->ai_json, true) : null;
    out(['id' => (int) $r->id, 'name' => $r->name, 'email' => $r->email, 'phone' => $r->phone,
        'jobTitle' => $r->job_title, 'jobId' => $r->job_id ? (int) $r->job_id : null, 'letter' => $r->letter,
        'status' => $r->status, 'rating' => (int) $r->rating, 'notes' => $r->notes,
        'assignee' => $r->assignee ? (int) $r->assignee : null, 'aiScore' => $r->ai_score !== null ? (int) $r->ai_score : null, 'ai' => $ai, 'aiModel' => $r->ai_model,
        'interview' => $r->interview_json ? json_decode($r->interview_json, true) : null,
        'interviewEval' => $r->interview_eval ? json_decode($r->interview_eval, true) : null,
        'interviewAt' => $r->interview_at,
        'others' => (function ($r) {
            $out = [];
            if (trim($r->email) === '') { return $out; }
            foreach (Capsule::table('mod_cpm_cv')->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($r->email))])
                ->where('id', '<>', $r->id)->orderByDesc('applied_at')->limit(20)->get() as $o) {
                $out[] = ['id' => (int) $o->id, 'name' => $o->name, 'jobTitle' => $o->job_title, 'status' => $o->status,
                    'aiScore' => $o->ai_score !== null ? (int) $o->ai_score : null, 'appliedAt' => $o->applied_at];
            }
            return $out;
        })($r),
        'comms' => (function ($cid) {
            $out = [];
            foreach (Capsule::table('mod_cpm_cv_comms')->where('cv_id', $cid)->orderByDesc('id')->limit(30)->get() as $cm) {
                $out[] = ['kind' => $cm->kind, 'subject' => $cm->subject, 'body' => $cm->body, 'by' => Db::adminName($cm->by), 'at' => $cm->created_at];
            }
            return $out;
        })((int) $r->id),
        'hasCv' => ($r->cv_stored !== '' || !empty($r->cv_storage_id)), 'photo' => ($r->photo !== '' || !empty($r->photo_storage_id)), 'cvName' => $r->cv_name, 'cvMime' => $r->cv_mime, 'source' => $r->source, 'appliedAt' => $r->applied_at]);

case 'cv_photo':                         // headshot thumbnail (auth + hr)
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('img', 403); }
    $r = Capsule::table('mod_cpm_cv')->where('id', (int) ($_GET['id'] ?? 0))->first();
    if (!$r || !$r->photo) { fail('img', 404); }
    if (!empty($r->photo_storage_id)) {                     // migrated → S3/local via Storage
        $sr = Storage::record((int) $r->photo_storage_id);
        if ($sr && $sr['driver'] === 's3') { header('Location: ' . Storage::presign($sr['id'], 300), true, 302); exit; }
        if ($sr) { $st = Storage::openRead($sr['id']); if ($st) { header('Content-Type: image/jpeg'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=86400'); fpassthru($st); fclose($st); exit; } }
    }
    $path = realpath(__DIR__ . '/../attachments/cloudonprojects/' . basename($r->photo));
    if (!$path || !is_file($path)) { fail('img', 404); }
    header('Content-Type: image/jpeg');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=86400');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;

case 'cv_file':                          // προβολή/λήψη CV (auth + hr)
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('file', 403); }
    $r = Capsule::table('mod_cpm_cv')->where('id', (int) ($_GET['id'] ?? 0))->first();
    if (!$r || (!$r->cv_stored && empty($r->cv_storage_id))) { fail('file', 404); }
    $mime = $r->cv_mime ?: 'application/octet-stream';
    $previewable = ($mime === 'application/pdf' || strpos($mime, 'image/') === 0);   // μόνο PDF/εικόνα inline
    $disp = (!empty($_GET['dl']) || !$previewable) ? 'attachment' : 'inline';
    if (!empty($r->cv_storage_id)) {                        // migrated → S3/local via Storage
        $sr = Storage::record((int) $r->cv_storage_id);
        if ($sr && $sr['driver'] === 's3') { header('Location: ' . Storage::presign($sr['id'], 300, $disp === 'attachment'), true, 302); exit; }
        if ($sr) { $stm = Storage::openRead($sr['id']); if ($stm) {
            header('Content-Type: ' . $mime); header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: ' . $disp . '; filename="' . rawurlencode($r->cv_name ?: 'cv.pdf') . '"');
            if ($sr['size']) { header('Content-Length: ' . (int) $sr['size']); }
            fpassthru($stm); fclose($stm); exit;
        } }
    }
    $path = realpath(__DIR__ . '/../attachments/cloudonprojects/' . basename($r->cv_stored));
    if (!$path || !is_file($path)) { fail('file', 404); }
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . $disp . '; filename="' . rawurlencode($r->cv_name ?: 'cv.pdf') . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;

case 'cv_update':                        // status / rating / notes / assignee
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $r = Capsule::table('mod_cpm_cv')->where('id', (int) ($in['id'] ?? 0))->first();
    if (!$r) { fail('notfound', 404); }
    $upd = ['updated_at' => date('Y-m-d H:i:s')];
    if (isset($in['status']) && array_key_exists($in['status'], cnp_cv_statuses())) { $upd['status'] = $in['status']; }
    if (isset($in['rating'])) { $upd['rating'] = max(0, min(5, (int) $in['rating'])); }
    if (array_key_exists('notes', $in)) { $upd['notes'] = cnp_clean_html($in['notes']); }
    if (array_key_exists('assignee', $in)) { $upd['assignee'] = (int) $in['assignee'] ?: null; }
    Capsule::table('mod_cpm_cv')->where('id', $r->id)->update($upd);
    out(['ok' => true]);

case 'cv_add':                           // χειροκίνητη προσθήκη υποψηφίου (CV από άλλες πηγές) — multipart
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $name = mb_substr(trim($_POST['name'] ?? ''), 0, 150);
    if ($name === '') { fail('Δώσε ονοματεπώνυμο'); }
    $cvName = ''; $cvMime = ''; $tmpUpload = null;
    $f = $_FILES['file'] ?? null;
    if ($f && $f['error'] === UPLOAD_ERR_OK) {
        if ($f['size'] > 15 * 1024 * 1024) { fail('Μέγιστο 15MB'); }
        if (!is_uploaded_file($f['tmp_name'])) { fail('upload'); }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!cnp_file_ext_ok($f['name'])) { fail('Μη επιτρεπτός τύπος'); }
        $tmpUpload = $f['tmp_name'];
        $cvName = mb_substr($f['name'], 0, 190);
        $cvMime = $ext === 'pdf' ? 'application/pdf' : (($ext === 'doc' || $ext === 'docx') ? 'application/msword' : ($f['type'] ?: 'application/octet-stream'));
    }
    $jobId = (int) ($_POST['job'] ?? 0) ?: null;
    $jobTitle = mb_substr(trim($_POST['job_title'] ?? ''), 0, 190);
    if ($jobId && $jobTitle === '') { $jobTitle = (string) Capsule::table('mod_cpm_cv_jobs')->where('id', $jobId)->value('title'); }
    $id = Capsule::table('mod_cpm_cv')->insertGetId([
        'source' => 'manual', 'name' => $name,
        'email' => mb_substr(trim($_POST['email'] ?? ''), 0, 150), 'phone' => mb_substr(trim($_POST['phone'] ?? ''), 0, 50),
        'job_id' => $jobId, 'job_title' => $jobTitle, 'letter' => mb_substr(trim($_POST['letter'] ?? ''), 0, 8000),
        'cv_stored' => '', 'cv_name' => $cvName, 'cv_mime' => $cvMime, 'status' => 'new',
        'applied_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    // CV + φωτο κατευθείαν στο Storage (S3/local)
    if ($tmpUpload) {
        $ing = CvPhoto::ingest($tmpUpload, $cvName, $cvMime, $id);
        Capsule::table('mod_cpm_cv')->where('id', $id)->update(array_filter([
            'cv_storage_id' => $ing['cv_storage_id'], 'photo_storage_id' => $ing['photo_storage_id'],
            'photo' => $ing['photo'] ?: null, 'cv_stored' => $ing['cv_stored'] ?: null,
        ], fn($v) => $v !== null));
    }
    $ev = cnp_cv_evaluate($id, cnp_cv_default_model(), true);
    out(['ok' => true, 'id' => $id, 'evaluated' => !empty($ev['ok'])]);

case 'cv_ai':                            // co-pilot: αξιολόγηση/ταξινόμηση (με επιλογή μοντέλου)
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $model = $in['model'] ?? cnp_cv_default_model();
    $res = cnp_cv_evaluate((int) ($in['id'] ?? 0), $model, true);
    if (empty($res['ok'])) { fail($res['error'] === 'notfound' ? 'notfound' : $res['error'], $res['error'] === 'notfound' ? 404 : 400); }
    out(['ok' => true, 'ai' => $res['ai'], 'score' => $res['score'], 'model' => $res['model']]);

case 'cv_email':                         // αποστολή email σε υποψήφιο (καταγράφεται)
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $r = Capsule::table('mod_cpm_cv')->where('id', (int) ($in['id'] ?? 0))->first();
    if (!$r) { fail('notfound', 404); }
    $to = filter_var(trim($r->email), FILTER_VALIDATE_EMAIL);
    if (!$to) { fail('Ο υποψήφιος δεν έχει έγκυρο email'); }
    $subject = mb_substr(trim($in['subject'] ?? ''), 0, 190);
    $body = cnp_clean_html($in['body'] ?? '');
    if ($subject === '' || trim(strip_tags($body)) === '') { fail('Συμπλήρωσε θέμα & κείμενο'); }
    $adminEmail = filter_var(Capsule::table('tbladmins')->where('id', $adminId)->value('email'), FILTER_VALIDATE_EMAIL);
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#243447;line-height:1.55;max-width:640px">' . nl2br($body) . '</div>';
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: CloudOn <noreply@cloudon.gr>\r\n";
    if ($adminEmail) { $headers .= 'Reply-To: ' . $adminEmail . "\r\n"; }
    $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
    Capsule::table('mod_cpm_cv_comms')->insert(['cv_id' => $r->id, 'kind' => 'email', 'subject' => $subject, 'body' => $body, 'by' => $adminId, 'created_at' => date('Y-m-d H:i:s')]);
    if ($r->status === 'new') { Capsule::table('mod_cpm_cv')->where('id', $r->id)->update(['status' => 'review']); }
    out(['ok' => true, 'sent' => (bool) $sent]);

case 'cv_schedule':                      // προγραμματισμός συνέντευξης + ειδοποίηση/υπενθύμιση
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $r = Capsule::table('mod_cpm_cv')->where('id', (int) ($in['id'] ?? 0))->first();
    if (!$r) { fail('notfound', 404); }
    $when = trim($in['when'] ?? '');
    $at = $when ? date('Y-m-d H:i:s', strtotime($when)) : null;
    $upd = ['interview_at' => $at, 'updated_at' => date('Y-m-d H:i:s')];
    if ($at && !in_array($r->status, ['hired', 'rejected'], true)) { $upd['status'] = 'interview'; }
    Capsule::table('mod_cpm_cv')->where('id', $r->id)->update($upd);
    if ($at) {
        Capsule::table('mod_cpm_cv_comms')->insert(['cv_id' => $r->id, 'kind' => 'interview', 'subject' => 'Προγραμματισμός συνέντευξης',
            'body' => 'Συνέντευξη ορίστηκε: ' . date('d/m/Y H:i', strtotime($at)), 'by' => $adminId, 'created_at' => date('Y-m-d H:i:s')]);
        try { Db::addReminder((int) ($r->assignee ?: $adminId), date('Y-m-d H:i:s', strtotime($at . ' -1 hour')), 'Συνέντευξη: ' . $r->name . ' (' . $r->job_title . ')', null); } catch (\Throwable $e) {
        }
        foreach (cnp_hr_admin_ids() as $aid) { Db::pushNotification($aid, 'due', '📅 Συνέντευξη: ' . $r->name . ' — ' . date('d/m H:i', strtotime($at)), '/project/#/recruit'); }
    }
    out(['ok' => true, 'interviewAt' => $at]);

case 'cv_interview_kit':                 // παραγωγή οδηγού ερωτήσεων συνέντευξης
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $r = Capsule::table('mod_cpm_cv')->where('id', (int) ($in['id'] ?? 0))->first();
    if (!$r) { fail('notfound', 404); }
    $iv = $r->interview_json ? json_decode($r->interview_json, true) : null;
    if ($iv && !empty($iv['questions']) && empty($in['regen'])) { out(['ok' => true, 'kit' => $iv]); }
    $key = trim(Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', 'ai_api_key')->value('value') ?: '');
    if ($key === '') { fail('Δεν έχει οριστεί κλειδί AI'); }
    $model = array_key_exists($in['model'] ?? '', cnp_cv_models()) ? $in['model'] : cnp_cv_default_model();
    $ai = $r->ai_json ? json_decode($r->ai_json, true) : null;
    $skills = ($ai && !empty($ai['skills'])) ? implode(', ', array_slice($ai['skills'], 0, 15)) : '';
    $content = [];
    $blk = cnp_cv_pdf_block($r); if ($blk) { $content[] = $blk; }
    $content[] = ['type' => 'text', 'text' =>
        "Είσαι έμπειρος recruiter. Ετοίμασε στοχευμένο ΟΔΗΓΟ ΣΥΝΕΝΤΕΥΞΗΣ για τον υποψήφιο \"{$r->name}\" για τη θέση \"{$r->job_title}\".\n"
        . ($skills !== '' ? "Δηλωμένες δεξιότητες: $skills\n" : '')
        . "Δημιούργησε 10-14 ερωτήσεις σε 4 κατηγορίες:\n"
        . "• Γνώσεις — ερωτήσεις που ΕΠΑΛΗΘΕΥΟΥΝ σε βάθος όσα δηλώνει ότι γνωρίζει (τεχνικές/πρακτικές, όχι ναι/όχι).\n"
        . "• Χαρακτήρας — behavioral, soft skills, ομαδικότητα, διαχείριση πίεσης/σύγκρουσης.\n"
        . "• Εμπειρία — πραγματικά παραδείγματα από προηγούμενη δουλειά (STAR).\n"
        . "• Κίνητρα — γιατί εδώ, στόχοι, καταλληλότητα.\n"
        . 'Απάντησε ΜΟΝΟ με JSON (ελληνικά): {"questions":[{"id":"q1","category":"Γνώσεις|Χαρακτήρας|Εμπειρία|Κίνητρα","q":"...","purpose":"τι αξιολογεί σε λίγες λέξεις"}]}'];
    $resp = cnp_anthropic($key, $model, $content, 3500);
    if (empty($resp['ok'])) { fail($resp['error']); }
    $kit = cnp_json_extract($resp['text']);
    if (!$kit || empty($kit['questions'])) { fail('AI: μη έγκυρη απάντηση (δοκίμασε ξανά)'); }
    // δώσε ids αν λείπουν, κράτα τυχόν υπάρχουσες απαντήσεις
    foreach ($kit['questions'] as $i => &$qq) { if (empty($qq['id'])) { $qq['id'] = 'q' . ($i + 1); } } unset($qq);
    $kit['answers'] = $iv['answers'] ?? [];
    $kit['generated_at'] = date('Y-m-d H:i:s'); $kit['model'] = $model;
    Capsule::table('mod_cpm_cv')->where('id', $r->id)->update(['interview_json' => json_encode($kit, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true, 'kit' => $kit]);

case 'cv_interview_save':                // αποθήκευση καταγεγραμμένων απαντήσεων
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $r = Capsule::table('mod_cpm_cv')->where('id', (int) ($in['id'] ?? 0))->first();
    if (!$r) { fail('notfound', 404); }
    $iv = $r->interview_json ? json_decode($r->interview_json, true) : ['questions' => []];
    $ans = [];
    foreach ((array) ($in['answers'] ?? []) as $qid => $a) {
        $qid = preg_replace('/[^a-z0-9_]/i', '', (string) $qid);
        $ans[$qid] = ['text' => mb_substr(trim(strip_tags((string) ($a['text'] ?? ''))), 0, 4000), 'rating' => max(0, min(5, (int) ($a['rating'] ?? 0)))];
    }
    $iv['answers'] = $ans;
    $iv['interviewer'] = $adminId;
    if (isset($in['when'])) { $iv['when'] = trim($in['when']); }
    if (array_key_exists('notes', $in)) { $iv['notes'] = mb_substr(trim(strip_tags((string) $in['notes'])), 0, 4000); }
    Capsule::table('mod_cpm_cv')->where('id', $r->id)->update(['interview_json' => json_encode($iv, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true]);

case 'cv_interview_eval':                // AI αξιολόγηση της συνέντευξης
    if (!in_array('hr', cnp_admin_areas($adminId, $FULL))) { fail('forbidden', 403); }
    $r = Capsule::table('mod_cpm_cv')->where('id', (int) ($in['id'] ?? 0))->first();
    if (!$r) { fail('notfound', 404); }
    $iv = $r->interview_json ? json_decode($r->interview_json, true) : null;
    if (!$iv || empty($iv['questions'])) { fail('Δημιούργησε & κατέγραψε πρώτα τη συνέντευξη'); }
    $key = trim(Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')->where('setting', 'ai_api_key')->value('value') ?: '');
    if ($key === '') { fail('Δεν έχει οριστεί κλειδί AI'); }
    $model = array_key_exists($in['model'] ?? '', cnp_cv_models()) ? $in['model'] : cnp_cv_default_model();
    $ansMap = $iv['answers'] ?? [];
    $trans = '';
    foreach ($iv['questions'] as $qq) {
        $a = $ansMap[$qq['id']] ?? null;
        $trans .= '[' . ($qq['category'] ?? '') . "] " . ($qq['q'] ?? '') . "\n";
        $trans .= 'ΑΠΑΝΤΗΣΗ: ' . (($a && trim($a['text']) !== '') ? $a['text'] : '(δεν καταγράφηκε)') . (($a && !empty($a['rating'])) ? ' [βαθμ. συνεντευκτή: ' . $a['rating'] . '/5]' : '') . "\n\n";
    }
    $txt = "Αξιολόγησε τη ΣΥΝΕΝΤΕΥΞΗ του υποψηφίου \"{$r->name}\" για τη θέση \"{$r->job_title}\". Ακολουθούν οι ερωτήσεις και οι απαντήσεις όπως τις κατέγραψε ο συνεντευκτής.\n\n"
        . "ΣΥΝΕΝΤΕΥΞΗ:\n" . mb_substr($trans, 0, 12000) . "\n";
    if (!empty($iv['notes'])) { $txt .= "ΣΗΜΕΙΩΣΕΙΣ ΣΥΝΕΝΤΕΥΚΤΗ: " . mb_substr($iv['notes'], 0, 1500) . "\n"; }
    $txt .= "\nΑξιολόγησε: (α) χαρακτήρα & soft skills, (β) αν ΕΠΑΛΗΘΕΥΤΗΚΑΝ οι γνώσεις που δήλωνε (verified/partial/not/unclear), (γ) red flags, (δ) συνολική σύσταση.\n"
        . 'Απάντησε ΜΟΝΟ με JSON (ελληνικά): {"score":0-100,"character":"εκτίμηση χαρακτήρα 2-3 προτάσεις","knowledgeVerified":"verified|partial|not|unclear","knowledgeNote":"τι επαληθεύτηκε/όχι","strengths":["..."],"redFlags":["..."],"recommendation":"proceed|hold|reject","summary":"συνολικό συμπέρασμα"}';
    $resp = cnp_anthropic($key, $model, [['type' => 'text', 'text' => $txt]], 1500);
    if (empty($resp['ok'])) { fail($resp['error']); }
    $ev = cnp_json_extract($resp['text']);
    if (!$ev) { fail('AI: μη έγκυρη απάντηση'); }
    $ev['model'] = $model; $ev['at'] = date('Y-m-d H:i:s');
    Capsule::table('mod_cpm_cv')->where('id', $r->id)->update(['interview_eval' => json_encode($ev, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')]);
    out(['ok' => true, 'eval' => $ev]);

case 'topstats':                         // πάνω μενού: live σφυγμός + κατάσταση διαθεσιμότητας
    $today = date('Y-m-d');
    $doneIds = Capsule::table('mod_cpm_statuses')->where('is_done', 1)->pluck('id')->all() ?: [0];
    $tkIds = Capsule::table('tbltickets')->where('flag', $adminId)->whereNotIn('status', ['Closed', 'Cancelled'])->pluck('id')->all();
    $tickets = count($tkIds);
    $sla = 0;
    try {
        if ($tickets && Capsule::schema()->hasTable('mod_supportcontracts_tickets')) {
            $sla = (int) Capsule::table('mod_supportcontracts_tickets')->whereIn('ticketid', $tkIds)
                ->whereNotNull('sla_due')->whereNull('first_response_at')->where('sla_due', '<', date('Y-m-d H:i:s'))->count();
        }
    } catch (\Throwable $e) {
    }
    $todayN = (int) Capsule::table('mod_cpm_tasks')->where('assignee', $adminId)->whereNotIn('status_id', $doneIds)
        ->whereNotNull('due_date')->where('due_date', '<=', $today)->count();
    $ball = (int) Capsule::table('mod_cpm_tasks')->where('action_user', $adminId)->whereNotIn('status_id', $doneIds)->count();
    out(['tickets' => $tickets, 'sla' => $sla, 'today' => $todayN, 'ball' => $ball,
        'status' => Db::pref($adminId, 'chat_status', 'online'), 'reason' => Db::pref($adminId, 'chat_reason', '')]);

case 'lead_products':                    // γραμμές προϊόντων ενός deal
    $lid = (int) ($_GET['lead'] ?? $in['lead'] ?? 0);
    $rows = Capsule::table('mod_cpm_lead_products')->where('lead_id', $lid)->orderBy('id')->get();
    $items = []; $total = 0.0;
    foreach ($rows as $r) {
        $lt = round((float) $r->qty * (float) $r->unit_price, 2);
        $total += $lt;
        $items[] = ['id' => (int) $r->id, 'product_id' => $r->product_id ? (int) $r->product_id : null,
            'name' => $r->name, 'qty' => (float) $r->qty, 'price' => (float) $r->unit_price, 'total' => $lt];
    }
    // κατάλογος προϊόντων για επιλογή (name → default τιμή)
    $catalog = [];
    foreach (Capsule::table('tblproducts')->where('hidden', 0)->orderBy('name')->get(['id', 'name']) as $p) {
        $catalog[] = ['id' => (int) $p->id, 'name' => $p->name];
    }
    out(['items' => $items, 'total' => round($total, 2), 'catalog' => $catalog]);

case 'lead_product_save':
    $lid = (int) ($in['lead'] ?? 0);
    $name = mb_substr(trim($in['name'] ?? ''), 0, 150);
    if (!$lid || $name === '') { fail('input'); }
    $data = ['name' => $name, 'product_id' => (int) ($in['product_id'] ?? 0) ?: null,
        'qty' => max(0, round((float) ($in['qty'] ?? 1), 2)), 'unit_price' => round((float) ($in['price'] ?? 0), 2)];
    $iid = (int) ($in['id'] ?? 0);
    if ($iid) {
        Capsule::table('mod_cpm_lead_products')->where('id', $iid)->update($data);
    } else {
        $data['lead_id'] = $lid; $data['created_at'] = date('Y-m-d H:i:s');
        Capsule::table('mod_cpm_lead_products')->insert($data);
    }
    // αυτόματη ενημέρωση αξίας deal = σύνολο γραμμών
    $sum = 0.0;
    foreach (Capsule::table('mod_cpm_lead_products')->where('lead_id', $lid)->get(['qty', 'unit_price']) as $r) {
        $sum += (float) $r->qty * (float) $r->unit_price;
    }
    Capsule::table('mod_cpm_leads')->where('id', $lid)->update(['value' => round($sum, 2)]);
    out(['ok' => true, 'total' => round($sum, 2)]);

case 'lead_product_del':
    $iid = (int) ($in['id'] ?? 0);
    $lid = (int) Capsule::table('mod_cpm_lead_products')->where('id', $iid)->value('lead_id');
    Capsule::table('mod_cpm_lead_products')->where('id', $iid)->delete();
    if ($lid) {
        $sum = 0.0;
        foreach (Capsule::table('mod_cpm_lead_products')->where('lead_id', $lid)->get(['qty', 'unit_price']) as $r) {
            $sum += (float) $r->qty * (float) $r->unit_price;
        }
        Capsule::table('mod_cpm_leads')->where('id', $lid)->update(['value' => round($sum, 2)]);
    }
    out(['ok' => true]);

case 'campaigns':                        // λίστα καμπανιών + στατιστικά απόδοσης
    $chLbl = ['email' => 'Email', 'phone' => 'Τηλεφωνική', 'event' => 'Εκδήλωση', 'social' => 'Social', 'ads' => 'Διαφήμιση', 'other' => 'Άλλο'];
    $cps = Capsule::table('mod_cpm_campaigns')->orderByRaw("FIELD(status,'active','draft','done')")->orderBy('id', 'desc')->get();
    $list = [];
    foreach ($cps as $c) {
        $mids = Capsule::table('mod_cpm_campaign_leads')->where('campaign_id', $c->id)->pluck('lead_id')->all();
        $members = count($mids); $won = 0; $wonVal = 0.0; $openN = 0;
        if ($members) {
            foreach (Capsule::table('mod_cpm_leads')->whereIn('id', $mids)->get(['stage', 'value']) as $l) {
                if ($l->stage === 'won') { $won++; $wonVal += (float) $l->value; }
                elseif ($l->stage !== 'lost') { $openN++; }
            }
        }
        $list[] = ['id' => (int) $c->id, 'name' => $c->name, 'channel' => $c->channel,
            'channelLbl' => $chLbl[$c->channel] ?? $c->channel, 'status' => $c->status,
            'budget' => (float) $c->budget, 'goal' => $c->goal, 'start' => $c->start_date, 'end' => $c->end_date,
            'members' => $members, 'won' => $won, 'open' => $openN, 'wonValue' => round($wonVal, 2),
            'conv' => $members > 0 ? round($won / $members * 100) : null,
            'roi' => ($c->budget > 0) ? round(($wonVal - (float) $c->budget) / (float) $c->budget * 100) : null];
    }
    out(['campaigns' => $list, 'channels' => $chLbl]);

case 'campaign_save':
    $name = mb_substr(trim($in['name'] ?? ''), 0, 150);
    if ($name === '') { fail('input'); }
    $data = ['name' => $name, 'channel' => $in['channel'] ?? 'email', 'status' => $in['status'] ?? 'draft',
        'budget' => round((float) ($in['budget'] ?? 0), 2), 'goal' => mb_substr(trim($in['goal'] ?? ''), 0, 190),
        'start_date' => ($in['start'] ?? '') ?: null, 'end_date' => ($in['end'] ?? '') ?: null,
        'notes' => cnp_clean_html($in['notes'] ?? '')];
    $cid = (int) ($in['id'] ?? 0);
    if ($cid) {
        Capsule::table('mod_cpm_campaigns')->where('id', $cid)->update($data);
    } else {
        $data['created_by'] = $adminId; $data['created_at'] = date('Y-m-d H:i:s');
        $cid = Capsule::table('mod_cpm_campaigns')->insertGetId($data);
    }
    out(['ok' => true, 'id' => $cid]);

case 'campaign_del':
    $cid = (int) ($in['id'] ?? 0);
    Capsule::table('mod_cpm_campaign_leads')->where('campaign_id', $cid)->delete();
    Capsule::table('mod_cpm_campaigns')->where('id', $cid)->delete();
    out(['ok' => true]);

case 'campaign_detail':                  // πεδία + μέλη + υποψήφια leads προς προσθήκη
    $cid = (int) ($_GET['id'] ?? $in['id'] ?? 0);
    $c = Capsule::table('mod_cpm_campaigns')->where('id', $cid)->first();
    if (!$c) { fail('notfound'); }
    $sMeta = Db::leadStages();
    $mids = Capsule::table('mod_cpm_campaign_leads')->where('campaign_id', $cid)->pluck('lead_id')->all();
    $members = [];
    if ($mids) {
        foreach (Capsule::table('mod_cpm_leads')->whereIn('id', $mids)->orderBy('company')->get() as $l) {
            $members[] = ['id' => (int) $l->id, 'company' => $l->company, 'contact' => $l->contact,
                'stage' => $l->stage, 'stageLbl' => $sMeta[$l->stage][0] ?? $l->stage,
                'stageCol' => $sMeta[$l->stage][1] ?? '#8291a9', 'value' => (float) $l->value];
        }
    }
    // υποψήφια: leads που δεν είναι ήδη μέλη (max 200 πιο πρόσφατα)
    $cand = [];
    $cq = Capsule::table('mod_cpm_leads')->orderBy('id', 'desc');
    if ($mids) { $cq->whereNotIn('id', $mids); }
    foreach ($cq->limit(200)->get(['id', 'company', 'contact', 'stage']) as $l) {
        $cand[] = ['id' => (int) $l->id, 'company' => $l->company, 'contact' => $l->contact,
            'stageLbl' => $sMeta[$l->stage][0] ?? $l->stage];
    }
    out(['id' => (int) $c->id, 'name' => $c->name, 'channel' => $c->channel, 'status' => $c->status,
        'budget' => (float) $c->budget, 'goal' => $c->goal, 'start' => $c->start_date, 'end' => $c->end_date,
        'notes' => $c->notes, 'members' => $members, 'candidates' => $cand]);

case 'campaign_add_lead':
    $cid = (int) ($in['campaign'] ?? 0); $lid = (int) ($in['lead'] ?? 0);
    if (!$cid || !$lid) { fail('input'); }
    $exists = Capsule::table('mod_cpm_campaign_leads')->where('campaign_id', $cid)->where('lead_id', $lid)->exists();
    if (!$exists) {
        Capsule::table('mod_cpm_campaign_leads')->insert(['campaign_id' => $cid, 'lead_id' => $lid, 'added_at' => date('Y-m-d H:i:s')]);
    }
    out(['ok' => true]);

case 'campaign_remove_lead':
    Capsule::table('mod_cpm_campaign_leads')->where('campaign_id', (int) ($in['campaign'] ?? 0))->where('lead_id', (int) ($in['lead'] ?? 0))->delete();
    out(['ok' => true]);

case 'crm_reports':                      // αναλυτικά reports πωλήσεων (διοίκηση)
    if (!$FULL) { fail('forbidden', 403); }
    $sMeta = Db::leadStages();
    $all = Capsule::table('mod_cpm_leads')->get(['stage', 'value', 'source', 'assignee', 'created_at', 'closed_at']);
    // funnel ανά στάδιο
    $funnel = [];
    foreach ($sMeta as $k => $m) { $funnel[$k] = ['key' => $k, 'label' => $m[0], 'color' => $m[1], 'count' => 0, 'value' => 0.0]; }
    $wonN = 0; $lostN = 0; $wonVal = 0.0; $pipeline = 0.0; $openN = 0;
    $closeDays = []; $srcAgg = []; $selAgg = [];
    foreach ($all as $l) {
        $st = $l->stage; $v = (float) $l->value;
        if (isset($funnel[$st])) { $funnel[$st]['count']++; $funnel[$st]['value'] += $v; }
        $isWon = ($st === 'won'); $isLost = ($st === 'lost');
        if ($isWon) { $wonN++; $wonVal += $v;
            if ($l->closed_at && $l->created_at) { $d = (strtotime($l->closed_at) - strtotime($l->created_at)) / 86400; if ($d >= 0) { $closeDays[] = $d; } }
        } elseif ($isLost) { $lostN++; }
        else { $openN++; $pipeline += $v; }
        // ανά πηγή
        $src = trim($l->source ?? '') ?: '—';
        if (!isset($srcAgg[$src])) { $srcAgg[$src] = ['source' => $src, 'leads' => 0, 'won' => 0, 'value' => 0.0]; }
        $srcAgg[$src]['leads']++; if ($isWon) { $srcAgg[$src]['won']++; $srcAgg[$src]['value'] += $v; }
        // ανά πωλητή
        $aid = (int) ($l->assignee ?? 0);
        if ($aid) {
            if (!isset($selAgg[$aid])) { $selAgg[$aid] = ['admin' => $aid, 'leads' => 0, 'won' => 0, 'value' => 0.0]; }
            $selAgg[$aid]['leads']++; if ($isWon) { $selAgg[$aid]['won']++; $selAgg[$aid]['value'] += $v; }
        }
    }
    foreach ($srcAgg as &$s) { $s['conv'] = $s['leads'] > 0 ? round($s['won'] / $s['leads'] * 100) : 0; $s['value'] = round($s['value'], 2); } unset($s);
    foreach ($selAgg as &$s) { $s['name'] = Db::adminName($s['admin']); $s['conv'] = $s['leads'] > 0 ? round($s['won'] / $s['leads'] * 100) : 0; $s['value'] = round($s['value'], 2); } unset($s);
    usort($srcAgg, fn($a, $b) => $b['value'] <=> $a['value']);
    $selAgg = array_values($selAgg); usort($selAgg, fn($a, $b) => $b['value'] <=> $a['value']);
    // τάση 6 μηνών (won ανά μήνα κλεισίματος)
    $months = [];
    for ($i = 5; $i >= 0; $i--) { $ym = date('Y-m', strtotime("first day of -$i month")); $months[$ym] = ['ym' => $ym, 'won' => 0, 'value' => 0.0]; }
    foreach ($all as $l) {
        if ($l->stage === 'won' && $l->closed_at) { $ym = substr($l->closed_at, 0, 7); if (isset($months[$ym])) { $months[$ym]['won']++; $months[$ym]['value'] += (float) $l->value; } }
    }
    foreach ($months as &$m) { $m['value'] = round($m['value'], 2); } unset($m);
    out([
        'funnel' => array_values($funnel),
        'winRate' => ($wonN + $lostN) > 0 ? round($wonN / ($wonN + $lostN) * 100) : null,
        'won' => $wonN, 'lost' => $lostN, 'open' => $openN,
        'wonValue' => round($wonVal, 2), 'pipeline' => round($pipeline, 2),
        'avgCloseDays' => count($closeDays) ? round(array_sum($closeDays) / count($closeDays), 1) : null,
        'bySource' => array_values($srcAgg), 'bySeller' => $selAgg, 'byMonth' => array_values($months),
    ]);

case 'lead_score':                       // βαθμολογία ενός lead + ανάλυση παραγόντων
    $lid = (int) ($_GET['lead'] ?? $in['lead'] ?? 0);
    $l = Capsule::table('mod_cpm_leads')->where('id', $lid)->first();
    if (!$l) { fail('notfound'); }
    $intCount = (int) Capsule::table('mod_cpm_interactions')->where('lead_id', $lid)->count();
    $lastInt = Capsule::table('mod_cpm_interactions')->where('lead_id', $lid)->max('happened_at');
    out(cnp_lead_score($l, $intCount, $lastInt));

case 'hot_leads':                        // κατάταξη ανοιχτών leads κατά score (θερμά πρώτα)
    $open = Capsule::table('mod_cpm_leads')->whereNotIn('stage', ['won', 'lost'])->get();
    // aggregate επικοινωνιών ανά lead (μία query)
    $cntBy = []; $lastBy = [];
    foreach (Capsule::table('mod_cpm_interactions')->whereNotNull('lead_id')->where('lead_id', '>', 0)
        ->groupBy('lead_id')->get([Capsule::raw('lead_id'), Capsule::raw('COUNT(*) as c'), Capsule::raw('MAX(happened_at) as last')]) as $r) {
        $cntBy[(int) $r->lead_id] = (int) $r->c; $lastBy[(int) $r->lead_id] = $r->last;
    }
    $sMeta = Db::leadStages();
    $rows = [];
    foreach ($open as $l) {
        $sc = cnp_lead_score($l, $cntBy[$l->id] ?? 0, $lastBy[$l->id] ?? null);
        $rows[] = ['id' => (int) $l->id, 'company' => $l->company, 'contact' => $l->contact,
            'stage' => $l->stage, 'stageLbl' => $sMeta[$l->stage][0] ?? $l->stage,
            'value' => (float) $l->value, 'assignee' => $l->assignee ? (int) $l->assignee : null,
            'score' => $sc['score'], 'temp' => $sc['temp']];
    }
    usort($rows, fn($a, $b) => $b['score'] <=> $a['score']);
    out(['leads' => array_slice($rows, 0, 20), 'total' => count($rows),
        'hot' => count(array_filter($rows, fn($r) => $r['temp'] === 'hot')),
        'warm' => count(array_filter($rows, fn($r) => $r['temp'] === 'warm')),
        'cold' => count(array_filter($rows, fn($r) => $r['temp'] === 'cold'))]);

case 'leads_export':                     // εξαγωγή όλων των leads σε CSV
    if (!$FULL) { fail('forbidden', 403); }
    $cols = ['id', 'company', 'contact', 'email', 'phone', 'source', 'stage', 'value', 'next_action', 'descr'];
    $cell = fn($v) => '"' . str_replace('"', '""', (string) $v) . '"';
    $lines = [implode(',', array_map($cell, $cols))];
    foreach (Capsule::table('mod_cpm_leads')->orderBy('id')->get() as $l) {
        $row = [];
        foreach ($cols as $c) { $row[] = $cell($l->$c ?? ''); }
        $lines[] = implode(',', $row);
    }
    out(['csv' => implode("\r\n", $lines), 'count' => count($lines) - 1, 'filename' => 'leads-' . date('Ymd') . '.csv']);

case 'leads_import_preview':             // ανάλυση CSV + εντοπισμός διπλότυπων (χωρίς αποθήκευση)
    if (!$FULL) { fail('forbidden', 403); }
    $txt = trim((string) ($in['csv'] ?? ''));
    if ($txt === '') { fail('empty'); }
    // δείκτες υπαρχόντων leads
    $byEmail = []; $byPhone = []; $byComp = [];
    $ph = fn($p) => preg_replace('/\D+/', '', (string) $p);
    foreach (Capsule::table('mod_cpm_leads')->get(['id', 'company', 'email', 'phone']) as $x) {
        if (trim($x->email ?? '') !== '') { $byEmail[mb_strtolower(trim($x->email))] = ['id' => (int) $x->id, 'company' => $x->company]; }
        $p = $ph($x->phone); if (strlen($p) >= 8) { $byPhone[$p] = ['id' => (int) $x->id, 'company' => $x->company]; }
        if (trim($x->company ?? '') !== '') { $byComp[mb_strtolower(trim($x->company))] = ['id' => (int) $x->id, 'company' => $x->company]; }
    }
    $stageKeys = array_keys(Db::leadStages());
    $rawLines = preg_split('/\r\n|\r|\n/', $txt);
    $fields = ['company', 'contact', 'email', 'phone', 'source', 'stage', 'value', 'next_action', 'descr'];
    $aliases = ['εταιρεία' => 'company', 'εταιρια' => 'company', 'επωνυμία' => 'company', 'επαφή' => 'contact', 'όνομα' => 'contact', 'name' => 'contact',
        'τηλέφωνο' => 'phone', 'τηλ' => 'phone', 'πηγή' => 'source', 'στάδιο' => 'stage', 'αξία' => 'value', 'σημειώσεις' => 'descr', 'notes' => 'descr'];
    // κεφαλίδα;
    $first = str_getcsv($rawLines[0]);
    $map = null; $start = 0;
    $lc = array_map(fn($h) => mb_strtolower(trim($h)), $first);
    if (count(array_intersect($lc, array_merge($fields, array_keys($aliases), ['id']))) >= 2) {
        $map = [];
        foreach ($lc as $i => $h) { $key = in_array($h, $fields) ? $h : ($aliases[$h] ?? null); if ($key) { $map[$key] = $i; } }
        $start = 1;
    }
    $preview = []; $newN = 0; $dupN = 0;
    for ($i = $start; $i < count($rawLines); $i++) {
        if (trim($rawLines[$i]) === '') { continue; }
        $c = str_getcsv($rawLines[$i]);
        $get = function ($key, $ord) use ($c, $map) { $idx = $map ? ($map[$key] ?? null) : $ord; return $idx !== null && isset($c[$idx]) ? trim($c[$idx]) : ''; };
        $rec = ['company' => $get('company', 1), 'contact' => $get('contact', 2), 'email' => $get('email', 3),
            'phone' => $get('phone', 4), 'source' => $get('source', 5), 'stage' => $get('stage', 6),
            'value' => $get('value', 7), 'next_action' => $get('next_action', 8), 'descr' => $get('descr', 9)];
        if ($rec['company'] === '' && $rec['contact'] === '' && $rec['email'] === '') { continue; }
        if (!in_array($rec['stage'], $stageKeys)) { $rec['stage'] = 'target'; }
        $rec['value'] = (float) preg_replace('/[^\d.]/', '', $rec['value']);
        // dup;
        $dup = null; $by = '';
        $em = mb_strtolower($rec['email']); $pp = $ph($rec['phone']); $cm = mb_strtolower($rec['company']);
        if ($em !== '' && isset($byEmail[$em])) { $dup = $byEmail[$em]; $by = 'email'; }
        elseif (strlen($pp) >= 8 && isset($byPhone[$pp])) { $dup = $byPhone[$pp]; $by = 'τηλέφωνο'; }
        elseif ($cm !== '' && isset($byComp[$cm])) { $dup = $byComp[$cm]; $by = 'εταιρεία'; }
        if ($dup) { $dupN++; } else { $newN++; }
        $preview[] = ['rec' => $rec, 'dup' => $dup ? ['id' => $dup['id'], 'company' => $dup['company'], 'by' => $by] : null];
    }
    out(['rows' => $preview, 'newN' => $newN, 'dupN' => $dupN, 'total' => count($preview)]);

case 'leads_import_commit':              // εκτέλεση εισαγωγής με αποφάσεις ανά γραμμή
    if (!$FULL) { fail('forbidden', 403); }
    $rows = $in['rows'] ?? [];
    $stageKeys = array_keys(Db::leadStages());
    $ins = 0; $upd = 0; $skip = 0;
    foreach ($rows as $r) {
        $act = $r['action'] ?? 'new';
        if ($act === 'skip') { $skip++; continue; }
        $rec = $r['rec'] ?? [];
        $stage = in_array($rec['stage'] ?? '', $stageKeys) ? $rec['stage'] : 'target';
        $data = [
            'company' => mb_substr(trim($rec['company'] ?? ''), 0, 120), 'contact' => mb_substr(trim($rec['contact'] ?? ''), 0, 120),
            'email' => mb_substr(trim($rec['email'] ?? ''), 0, 120), 'phone' => mb_substr(trim($rec['phone'] ?? ''), 0, 40),
            'source' => mb_substr(trim($rec['source'] ?? ''), 0, 60), 'stage' => $stage, 'value' => (float) ($rec['value'] ?? 0),
            'next_action' => ($rec['next_action'] ?? '') ?: null, 'descr' => mb_substr(trim($rec['descr'] ?? ''), 0, 2000),
        ];
        if ($act === 'update' && !empty($r['dup']['id'])) {
            Capsule::table('mod_cpm_leads')->where('id', (int) $r['dup']['id'])->update($data); $upd++;
        } else {
            $data['created_by'] = $adminId; $data['created_at'] = date('Y-m-d H:i:s');
            Capsule::table('mod_cpm_leads')->insert($data); $ins++;
        }
    }
    out(['ok' => true, 'inserted' => $ins, 'updated' => $upd, 'skipped' => $skip]);

case 'leads_dupes':                      // εντοπισμός διπλότυπων μεταξύ υπαρχόντων leads
    if (!$FULL) { fail('forbidden', 403); }
    $ph = fn($p) => preg_replace('/\D+/', '', (string) $p);
    $sMeta = Db::leadStages();
    $groups = [];  // sig => [lead ids]
    $info = [];
    foreach (Capsule::table('mod_cpm_leads')->orderBy('id')->get() as $l) {
        $info[$l->id] = $l;
        $sigs = [];
        if (trim($l->email ?? '') !== '') { $sigs[] = 'e:' . mb_strtolower(trim($l->email)); }
        $p = $ph($l->phone); if (strlen($p) >= 8) { $sigs[] = 'p:' . $p; }
        if (trim($l->company ?? '') !== '') { $sigs[] = 'c:' . mb_strtolower(trim($l->company)); }
        foreach ($sigs as $s) { $groups[$s][] = (int) $l->id; }
    }
    $clusters = []; $seen = [];
    foreach ($groups as $sig => $ids) {
        $ids = array_values(array_unique($ids));
        if (count($ids) < 2) { continue; }
        sort($ids); $ckey = implode(',', $ids);
        if (isset($seen[$ckey])) { continue; } $seen[$ckey] = 1;
        $byLbl = ['e' => 'email', 'p' => 'τηλέφωνο', 'c' => 'εταιρεία'][$sig[0]] ?? '';
        $ls = [];
        foreach ($ids as $id) { $x = $info[$id];
            $ls[] = ['id' => (int) $id, 'company' => $x->company, 'contact' => $x->contact, 'email' => $x->email,
                'phone' => $x->phone, 'stage' => $x->stage, 'stageLbl' => $sMeta[$x->stage][0] ?? $x->stage, 'value' => (float) $x->value];
        }
        $clusters[] = ['by' => $byLbl, 'leads' => $ls];
    }
    out(['clusters' => $clusters, 'count' => count($clusters)]);

case 'lead_merge':                       // συγχώνευση: μετακίνηση σχέσεων drop→keep, διαγραφή drop
    if (!$FULL) { fail('forbidden', 403); }
    $keep = (int) ($in['keep'] ?? 0); $drop = (int) ($in['drop'] ?? 0);
    if (!$keep || !$drop || $keep === $drop) { fail('input'); }
    Capsule::table('mod_cpm_interactions')->where('lead_id', $drop)->update(['lead_id' => $keep]);
    Capsule::table('mod_cpm_lead_tasks')->where('lead_id', $drop)->update(['lead_id' => $keep]);
    Capsule::table('mod_cpm_lead_products')->where('lead_id', $drop)->update(['lead_id' => $keep]);
    // campaign_leads: απόφυγε διπλότυπο (campaign,lead)
    $keepCamps = Capsule::table('mod_cpm_campaign_leads')->where('lead_id', $keep)->pluck('campaign_id')->all();
    Capsule::table('mod_cpm_campaign_leads')->where('lead_id', $drop)->whereIn('campaign_id', $keepCamps ?: [0])->delete();
    Capsule::table('mod_cpm_campaign_leads')->where('lead_id', $drop)->update(['lead_id' => $keep]);
    // αν το keep δεν έχει αξία αλλά το drop έχει → κράτα τη μεγαλύτερη
    $kv = (float) Capsule::table('mod_cpm_leads')->where('id', $keep)->value('value');
    $dv = (float) Capsule::table('mod_cpm_leads')->where('id', $drop)->value('value');
    if ($dv > $kv) { Capsule::table('mod_cpm_leads')->where('id', $keep)->update(['value' => $dv]); }
    Capsule::table('mod_cpm_leads')->where('id', $drop)->delete();
    out(['ok' => true]);

case 'lead_timeline':                    // ενιαίο ιστορικό lead (επικοινωνίες + tasks)
    $lid = (int) ($_GET['lead'] ?? 0);
    $ev = [];
    foreach (Capsule::table('mod_cpm_interactions')->where('lead_id', $lid)->get() as $i) {
        $ev[] = ['type' => 'interaction', 'kind' => $i->kind, 'text' => $i->summary,
            'by' => $i->admin_id ? Db::adminName((int) $i->admin_id) : null,
            'at' => $i->happened_at ?: $i->created_at,
            'fup' => $i->followup_date && !$i->followup_done ? $i->followup_date : null];
    }
    foreach (Capsule::table('mod_cpm_lead_tasks')->where('lead_id', $lid)->get() as $t) {
        $ev[] = ['type' => 'task', 'kind' => $t->kind, 'text' => $t->title,
            'by' => $t->assignee ? Db::adminName((int) $t->assignee) : null,
            'at' => $t->done ? ($t->done_at ?: $t->created_at) : $t->created_at,
            'done' => (bool) $t->done, 'due' => $t->due_date];
    }
    usort($ev, function ($a, $b) { return strcmp($b['at'] ?? '', $a['at'] ?? ''); });
    out(['events' => array_slice($ev, 0, 60)]);

case 'my_crm_tasks':                     // ανοιχτές CRM εργασίες μου (ή όλων αν full)
    $today = date('Y-m-d');
    $q = Capsule::table('mod_cpm_lead_tasks as t')->join('mod_cpm_leads as l', 'l.id', '=', 't.lead_id')
        ->where('t.done', 0);
    if (!$FULL) { $q->where('t.assignee', $adminId); }
    $rows = $q->orderByRaw('t.due_date IS NULL, t.due_date ASC')->limit(50)
        ->get(['t.id', 't.title', 't.kind', 't.due_date', 't.assignee', 't.lead_id',
               'l.company', 'l.contact']);
    $out = [];
    foreach ($rows as $t) {
        $out[] = ['id' => (int) $t->id, 'title' => $t->title, 'kind' => $t->kind, 'due' => $t->due_date,
            'overdue' => $t->due_date && $t->due_date < $today,
            'lead' => (int) $t->lead_id, 'leadName' => $t->company ?: $t->contact ?: ('lead #' . $t->lead_id),
            'who' => $t->assignee ? Db::adminName((int) $t->assignee) : null];
    }
    out(['tasks' => $out]);

case 'version':
    $a6 = (string) Capsule::table('mod_cpm_tasks')->max('updated_at');
    $b6 = (string) Capsule::table('mod_cpm_tasks')->count();
    $c6 = (string) Capsule::table('tbltickets')->max('lastreply');
    $d6 = (string) Capsule::table('mod_cpm_notifications')->where('admin_id', $adminId)->max('id');
    $e6 = (string) Capsule::table('mod_cpm_comments')->max('id');
    $f6 = (string) Capsule::table('mod_cpm_leads')->max('updated_at');
    Db::setPref($adminId, 'last_seen', (string) time());
    $reads6 = [];
    foreach (Capsule::table('mod_cpm_chat_reads')->where('admin_id', $adminId)->get() as $r6) {
        $reads6[$r6->channel] = (int) $r6->last_id;
    }
    $chatUnread = 0;
    foreach (Capsule::table('mod_cpm_chat')->where('admin_id', '!=', $adminId)
        ->groupBy('channel')->get(['channel', Capsule::raw('MAX(id) m')]) as $g6) {
        if (!cnp_chat_access($g6->channel, $adminId)) {
            continue;
        }
        if ((int) $g6->m > ($reads6[$g6->channel] ?? 0)) {
            $chatUnread += Capsule::table('mod_cpm_chat')->where('channel', $g6->channel)
                ->where('admin_id', '!=', $adminId)->where('id', '>', $reads6[$g6->channel] ?? 0)->count();
        }
    }
    $g6chat = (string) Capsule::table('mod_cpm_chat')->max('id');
    out(['v' => md5($a6 . '|' . $b6 . '|' . $c6 . '|' . $d6 . '|' . $e6 . '|' . $f6 . '|' . $g6chat),
        'unread' => Db::unreadCount($adminId), 'chatUnread' => $chatUnread]);

/* ---- αναζήτηση πελάτη (autocomplete) ---- */
case 'client_search':
    $q = trim($_GET['q'] ?? '');
    $res = [];
    if (mb_strlen($q) >= 2) {
        $cq = Capsule::table('tblclients')->limit(12);
        if (ctype_digit($q)) {
            $cq->where('id', (int) $q);
        } else {
            $like = '%' . $q . '%';
            $cq->where(function ($w) use ($like) {
                $w->where('firstname', 'like', $like)->orWhere('lastname', 'like', $like)
                  ->orWhere('companyname', 'like', $like)->orWhere('email', 'like', $like);
            });
        }
        foreach ($cq->get(['id', 'firstname', 'lastname', 'companyname']) as $c) {
            $res[] = ['id' => (int) $c->id, 'label' => ($c->companyname ?: trim($c->firstname . ' ' . $c->lastname)) . ' (#' . $c->id . ')'];
        }
    }
    out(['results' => $res]);

default:
    fail('unknown action', 404);
}
