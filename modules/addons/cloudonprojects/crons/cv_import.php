<?php
/**
 * CloudOn Project Manager — εισαγωγή αιτήσεων/βιογραφικών από το WordPress
 * (plugin wp-job-openings) στο κύκλωμα Προσλήψεων. Read-only ως προς το WP.
 * Idempotent: παραλείπει ήδη εισαγμένες (wp_id). Τρέξε:
 *   /opt/plesk/php/8.3/bin/php modules/addons/cloudonprojects/crons/cv_import.php
 */

define('WHMCS', true);
$WP = '/var/www/vhosts/cloudon.gr/httpdocs';
require __DIR__ . '/../../../../init.php';

use WHMCS\Database\Capsule;

$CVDIR = '/var/www/vhosts/cloudon.gr/my.cloudon.gr/attachments/cloudonprojects';
if (!is_dir($CVDIR)) { @mkdir($CVDIR, 0750, true); }

/* ── WP DB creds από wp-config ── */
$cfg = file_get_contents($WP . '/wp-config.php');
$wpc = function ($k) use ($cfg) { return preg_match("/'" . $k . "'\\s*,\\s*'([^']*)'/", $cfg, $m) ? $m[1] : ''; };
$dbn = $wpc('DB_NAME'); $dbu = $wpc('DB_USER'); $dbp = $wpc('DB_PASSWORD');
$hostRaw = $wpc('DB_HOST') ?: 'localhost'; $port = 3306;
if (strpos($hostRaw, ':') !== false) { [$host, $port] = explode(':', $hostRaw); } else { $host = $hostRaw; }
preg_match('/\$table_prefix\s*=\s*[\'"]([^\'"]+)/', $cfg, $m); $pfx = $m[1] ?? 'wp_';

$wp = @new mysqli($host, $dbu, $dbp, $dbn, (int) $port);
if ($wp->connect_errno) { fwrite(STDERR, "WP DB connect failed\n"); exit(1); }
$wp->set_charset('utf8mb4');

/* ── 1. Αγγελίες (job openings) — ένωση ανά τίτλο (όχι διπλότυπα) ── */
$titleMap = [];  // lower(title) => cloudon job id
foreach (Capsule::table('mod_cpm_cv_jobs')->get(['id', 'title']) as $j) { $titleMap[mb_strtolower(trim($j->title))] = (int) $j->id; }
$jobMap = [];    // wp_id => cloudon job id
$res = $wp->query("SELECT ID, post_title, post_status, post_content FROM {$pfx}posts WHERE post_type='awsm_job_openings'");
$nJobs = 0;
while ($j = $res->fetch_assoc()) {
    $wid = (int) $j['ID']; $title = trim($j['post_title']); $tk = mb_strtolower($title);
    if (isset($titleMap[$tk])) {
        $cid = $titleMap[$tk];
        if ($j['post_status'] === 'publish') { Capsule::table('mod_cpm_cv_jobs')->where('id', $cid)->update(['active' => 1]); }
    } else {
        $cid = Capsule::table('mod_cpm_cv_jobs')->insertGetId([
            'wp_id' => $wid, 'title' => mb_substr($title, 0, 190),
            'descr' => mb_substr(strip_tags($j['post_content']), 0, 6000),
            'active' => $j['post_status'] === 'publish' ? 1 : 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $titleMap[$tk] = $cid; $nJobs++;
    }
    $jobMap[$wid] = $cid;
}
echo "Jobs: +$nJobs (κατηγορίες: " . count($titleMap) . ")\n";

/* ── 2. Meta όλων των αιτήσεων (σε map) ── */
$meta = [];
$res = $wp->query("SELECT pm.post_id, pm.meta_key, pm.meta_value
    FROM {$pfx}postmeta pm JOIN {$pfx}posts p ON p.ID=pm.post_id
    WHERE p.post_type='awsm_job_application'");
while ($r = $res->fetch_assoc()) { $meta[(int) $r['post_id']][$r['meta_key']] = $r['meta_value']; }

/* ── 3. Paths των CV (attachments) ── */
$attIds = [];
foreach ($meta as $mm) { if (!empty($mm['awsm_attachment_id'])) { $attIds[] = (int) $mm['awsm_attachment_id']; } }
$attPath = [];
if ($attIds) {
    $in = implode(',', array_unique($attIds));
    $res = $wp->query("SELECT post_id, meta_value FROM {$pfx}postmeta WHERE meta_key='_wp_attached_file' AND post_id IN ($in)");
    while ($r = $res->fetch_assoc()) { $attPath[(int) $r['post_id']] = $r['meta_value']; }
}

/* ── 4. Αιτήσεις ── */
$existing = Capsule::table('mod_cpm_cv')->whereNotNull('wp_id')->pluck('wp_id')->all();
$existing = array_flip(array_map('intval', $existing));
$res = $wp->query("SELECT ID, post_date FROM {$pfx}posts WHERE post_type='awsm_job_application' AND post_status='publish' ORDER BY ID");
$nApp = 0; $nFile = 0; $nSkip = 0;
$uplBase = $WP . '/wp-content/uploads/';
while ($a = $res->fetch_assoc()) {
    $wid = (int) $a['ID'];
    if (isset($existing[$wid])) { $nSkip++; continue; }
    $mm = $meta[$wid] ?? [];
    $wpJob = (int) ($mm['awsm_job_id'] ?? 0);
    // CV αρχείο
    $stored = ''; $cvName = ''; $cvMime = '';
    $attId = (int) ($mm['awsm_attachment_id'] ?? 0);
    if ($attId && !empty($attPath[$attId])) {
        $src = $uplBase . $attPath[$attId];
        if (is_file($src)) {
            $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION)) ?: 'pdf';
            $stored = uniqid('cv', true) . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
            if (@copy($src, $CVDIR . '/' . $stored)) {
                $cvName = basename($attPath[$attId]);
                $cvMime = $ext === 'pdf' ? 'application/pdf' : ($ext === 'doc' || $ext === 'docx' ? 'application/msword' : 'application/octet-stream');
                $nFile++;
            } else { $stored = ''; }
        }
    }
    $letter = trim($mm['awsm_applicant_letter'] ?? '');
    if ($letter === '-') { $letter = ''; }
    Capsule::table('mod_cpm_cv')->insert([
        'source' => 'wp', 'wp_id' => $wid,
        'name' => mb_substr(trim($mm['awsm_applicant_name'] ?? ''), 0, 150) ?: 'Άγνωστος',
        'email' => mb_substr(trim($mm['awsm_applicant_email'] ?? ''), 0, 150),
        'phone' => mb_substr(trim($mm['awsm_applicant_phone'] ?? ''), 0, 50),
        'job_id' => $wpJob && isset($jobMap[$wpJob]) ? $jobMap[$wpJob] : null,
        'job_title' => mb_substr(trim($mm['awsm_apply_for'] ?? ''), 0, 190),
        'letter' => mb_substr($letter, 0, 8000),
        'cv_stored' => $stored, 'cv_name' => mb_substr($cvName, 0, 190), 'cv_mime' => $cvMime,
        'status' => 'new', 'applied_at' => $a['post_date'],
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $nApp++;
}
echo "Applications: +$nApp (CV αρχεία: $nFile, ήδη υπάρχουν: $nSkip)\n";
$wp->close();
echo "Done.\n";
