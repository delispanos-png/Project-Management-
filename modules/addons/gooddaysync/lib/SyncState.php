<?php
/**
 * Sync engine — orchestrates WHMCS ↔ GoodDay, using localAPI for all WHMCS
 * operations (never the remote WHMCS API). Faithful to app.py §4 and §5.
 *
 * Safety:
 *   • Every GoodDay write goes through GoodDayClient, which honours DRY_RUN.
 *   • Loop-prevention: replies that originated from GoodDay (!public / mirror)
 *     are never pushed back; a same-process static guard suppresses the reply
 *     hook while the reconciler injects an inbound reply.
 *   • Full-ticket-delete is NOT implemented — there is no DeleteTicket path.
 *
 * @package WHMCS\Module\Addon\GoodDaySync
 */

namespace WHMCS\Module\Addon\GoodDaySync;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class SyncState
{
    /** @var array */
    private $cfg;
    /** @var GoodDayClient */
    private $gd;
    /** @var bool */
    public $dryRun;

    /**
     * Same-process guard: while the reconciler injects a GoodDay→WHMCS reply,
     * the TicketUserReply/TicketAdminReply hook must NOT push it back. Keyed by
     * ticketid → list of canonical bodies currently being injected.
     */
    public static $inboundGuard = [];

    public function __construct(array $cfg, GoodDayClient $gd, $dryRun = true)
    {
        $this->cfg    = $cfg;
        $this->gd     = $gd;
        $this->dryRun = (bool) $dryRun;
    }

    public static function fromSettings(array $settings)
    {
        $dry = self::flag($settings, 'dry_run', true);
        $gd  = new GoodDayClient(
            $settings,
            $dry,
            function ($e, $d, $l) {
                Db::log($e, $d, $l);
            },
            // persist a freshly auto-logged-in JWT so subsequent requests/crons
            // reuse it until it expires (one login per token lifetime, not per run)
            function ($token) {
                \WHMCS\Database\Capsule::table('tbladdonmodules')
                    ->where('module', 'gooddaysync')
                    ->where('setting', 'gd_access_token')
                    ->update(['value' => (string) $token]);
            }
        );
        return new self($settings, $gd, $dry);
    }

    /* ------------------------------------------------------------------ */
    /* Config helpers                                                     */
    /* ------------------------------------------------------------------ */

    public static function flag(array $cfg, $key, $default = false)
    {
        if (!array_key_exists($key, $cfg)) {
            return $default;
        }
        $v = $cfg[$key];
        if (is_bool($v)) {
            return $v;
        }
        $v = strtolower(trim((string) $v));
        return in_array($v, ['1', 'on', 'yes', 'true'], true);
    }

    private function cfg($key, $default = '')
    {
        return isset($this->cfg[$key]) && $this->cfg[$key] !== '' ? $this->cfg[$key] : $default;
    }

    /* ------------------------------------------------------------------ */
    /* WHMCS access (localAPI only)                                       */
    /* ------------------------------------------------------------------ */

    private function localApi($command, array $post)
    {
        $admin = (string) $this->cfg('whmcs_admin_username', 'support');
        if (!function_exists('localAPI')) {
            require_once __DIR__ . '/../../../../init.php';
        }
        return localAPI($command, $post, $admin);
    }

    /* ------------------------------------------------------------------ */
    /* DRY_RUN-guarded mapping writes                                     */
    /* In dry-run the engine logs what it WOULD do but never mutates the  */
    /* mapping tables — so the shadow phase can never corrupt state (e.g. */
    /* mark a ticket created with a fake DRYRUN task id). state.json      */
    /* import is a separate, deliberate action and always writes.         */
    /* ------------------------------------------------------------------ */

    private function commitTicket($ticketid, array $data)
    {
        if ($this->dryRun) {
            return;
        }
        Db::upsertTicket($ticketid, $data);
    }

    private function commitReply($ticketid, array $data)
    {
        if ($this->dryRun) {
            return null;
        }
        return Db::saveReply($ticketid, $data);
    }

    private function commitDeleteReply($id)
    {
        if ($this->dryRun) {
            return;
        }
        Db::deleteReplyRow($id);
    }

    /** Full ticket + replies (ASC). Returns normalised array or null. */
    public function getTicket($ticketid)
    {
        $r = $this->localApi('GetTicket', ['ticketid' => (int) $ticketid, 'repliessort' => 'ASC']);
        if (($r['result'] ?? '') !== 'success') {
            return null;
        }
        $replies = [];
        if (isset($r['replies']['reply'])) {
            $replies = $r['replies']['reply'];
            if (isset($replies['replyid'])) { // single reply → wrap
                $replies = [$replies];
            }
        }
        return [
            'ticketid'        => (int) ($r['ticketid'] ?? $ticketid),
            'tid'             => $r['tid'] ?? '',
            'subject'         => $r['subject'] ?? '',
            'status'          => $r['status'] ?? '',
            'priority'        => $r['priority'] ?? ($r['urgency'] ?? ''),
            'deptname'        => $r['deptname'] ?? ($r['department'] ?? ''),
            'requestor_name'  => $r['name'] ?? '',
            'requestor_email' => $r['email'] ?? '',
            'userid'          => (int) ($r['userid'] ?? 0),
            'date'            => $r['date'] ?? '',
            'lastreply'       => $r['lastreply'] ?? '',
            'replies'         => is_array($replies) ? $replies : [],
        ];
    }

    private function clientPhone($clientid)
    {
        if (!$clientid) {
            return '';
        }
        $r = $this->localApi('GetClientsDetails', ['clientid' => (int) $clientid, 'stats' => false]);
        if (($r['result'] ?? '') !== 'success') {
            return '';
        }
        return (string) ($r['phonenumber'] ?? ($r['client']['phonenumber'] ?? ''));
    }

    /* ------------------------------------------------------------------ */
    /* Custom fields (§6)                                                 */
    /* ------------------------------------------------------------------ */

    private function buildCustomFields(array $t)
    {
        $map = [
            'gd_cf_ticket'  => $t['tid'],
            'gd_cf_created' => $t['date'],
            'gd_cf_subject' => $t['subject'],
            'gd_cf_dept'    => $t['deptname'],
            'gd_cf_name'    => $t['requestor_name'],
            'gd_cf_email'   => $t['requestor_email'],
        ];
        $fields = [];
        foreach ($map as $cfgKey => $value) {
            $id = trim((string) $this->cfg($cfgKey, ''));
            if ($id !== '') {
                $fields[] = ['id' => $id, 'value' => (string) $value];
            }
        }
        // optional WHMCS status CF
        $statusId = trim((string) $this->cfg('gd_cf_status', ''));
        if ($statusId !== '') {
            $fields[] = ['id' => $statusId, 'value' => (string) $t['status']];
        }
        // optional requestor phone CF
        $phoneId = trim((string) $this->cfg('gd_cf_phone', ''));
        if ($phoneId !== '' && !empty($t['userid'])) {
            $phone = Formatter::normalisePhone($this->clientPhone($t['userid']), $this->cfg('gd_phone_prefix', '+357'));
            if ($phone !== '') {
                $fields[] = ['id' => $phoneId, 'value' => $phone];
            }
        }
        return $fields;
    }

    /* ------------------------------------------------------------------ */
    /* Project selection (§4.1.3)                                         */
    /* ------------------------------------------------------------------ */

    private function projectForDepartment($deptname)
    {
        $mapJson = trim((string) $this->cfg('gd_project_by_department', ''));
        if ($mapJson !== '') {
            $map = json_decode($mapJson, true);
            if (is_array($map)) {
                $key = strtolower(trim((string) $deptname));
                foreach ($map as $k => $v) {
                    if (strtolower(trim($k)) === $key) {
                        return (string) $v;
                    }
                }
            }
        }
        return (string) $this->cfg('gd_project_id', '');
    }

    /* ================================================================== */
    /* WHMCS → GoodDay                                                    */
    /* ================================================================== */

    /** New ticket → GoodDay task (§4.1). Hook: TicketOpen. */
    public function createTaskForTicket($ticketid)
    {
        if (!self::flag($this->cfg, 'sync_create_task', true)) {
            return;
        }
        $ticketid = (int) $ticketid;
        $existing = Db::ticket($ticketid);
        if ($existing && $existing->created) {
            return; // already created (idempotent)
        }
        // anti-recreate guard (§8)
        if (Db::tombstoneActive('id:' . $ticketid)) {
            Db::log('create.skip.tombstone', 'active delete-guard tombstone', 'warn', $ticketid);
            return;
        }

        $t = $this->getTicket($ticketid);
        if ($t === null) {
            if (self::flag($this->cfg, 'whmcs_create_without_details_on_failure', false)) {
                $t = ['ticketid' => $ticketid, 'tid' => '', 'subject' => '', 'status' => '', 'deptname' => '',
                      'requestor_name' => '', 'requestor_email' => '', 'userid' => 0, 'date' => '', 'replies' => []];
            } else {
                Db::log('create.fail', 'GetTicket failed', 'error', $ticketid);
                return;
            }
        }

        $projectId = $this->projectForDepartment($t['deptname']);
        if ($projectId === '') {
            Db::log('create.fail', 'no project for department: ' . $t['deptname'], 'error', $ticketid);
            return;
        }

        $title = Formatter::taskTitle($ticketid, $t['subject']);
        $body  = Formatter::extractInitialMessage($t['replies']);
        if (trim($body) === '') {
            $body = Formatter::ticketIntro($t);
        }
        if (trim($body) === '') {
            $body = 'WHMCS → GoodDay (auto)';
        }

        $res = $this->gd->createTask($projectId, $title, $body);
        $taskId = $res['task_id'] ?? (is_array($res['body']) ? ($res['body']['id'] ?? null) : null);
        if (!$taskId) {
            Db::log('create.fail', 'no task id returned: ' . json_encode($res['status'] ?? ''), 'error', $ticketid);
            return;
        }

        // baseline: newest reply id so old replies don't become spam comments
        $lastReplyId = 0;
        foreach ($t['replies'] as $r) {
            $rid = (int) ($r['replyid'] ?? $r['id'] ?? 0);
            if ($rid > $lastReplyId) {
                $lastReplyId = $rid;
            }
        }

        $this->commitTicket($ticketid, [
            'tid'                => $t['tid'],
            'task_id'            => (string) $taskId,
            'project_id'         => $projectId,
            'created'            => true,
            'baseline_done'      => true,
            'last_reply_id'      => $lastReplyId,
            'last_status_synced' => $t['status'],
        ]);
        Db::log('create.ok', 'task ' . $taskId . ' project ' . $projectId . ($this->dryRun ? ' [DRY_RUN]' : ''), 'info', $ticketid);

        // custom fields
        $this->gd->setCustomFields((string) $taskId, $this->buildCustomFields($t));

        // push existing replies (replyid>0)
        foreach ($t['replies'] as $r) {
            $rid = (int) ($r['replyid'] ?? $r['id'] ?? 0);
            if ($rid > 0) {
                $this->pushReplyComment($ticketid, (string) $taskId, $r, true);
            } else {
                // record the initial pseudo-reply signature (edit detection later)
                $this->commitReply($ticketid, [
                    'whmcs_reply_id' => 0,
                    'signature'      => Formatter::whmcsReplySignature($r),
                    'origin'         => 'whmcs',
                    'kind'           => 'reply',
                ]);
            }
        }
    }

    /** New reply → GoodDay comment (§4.2). Hooks: TicketUserReply/AdminReply. */
    public function pushReply($ticketid, $replyid = null)
    {
        if (!self::flag($this->cfg, 'sync_replies', true)) {
            return;
        }
        $ticketid = (int) $ticketid;
        $trow = Db::ticket($ticketid);
        if (!$trow || !$trow->created) {
            // ticket not mapped yet → create it first (covers first reply race)
            $this->createTaskForTicket($ticketid);
            $trow = Db::ticket($ticketid);
            if (!$trow || !$trow->created) {
                return;
            }
            return; // createTaskForTicket already pushed existing replies
        }

        $t = $this->getTicket($ticketid);
        if ($t === null) {
            return;
        }
        foreach ($t['replies'] as $r) {
            $rid = (int) ($r['replyid'] ?? $r['id'] ?? 0);
            if ($rid <= 0 || $rid <= (int) $trow->last_reply_id) {
                continue; // initial message or already-known reply
            }
            // loop-prevention: reply that came FROM GoodDay must not go back
            if ($this->isInboundReply($ticketid, $r)) {
                Db::log('push.skip.inbound', 'reply ' . $rid . ' originated from GoodDay', 'info', $ticketid);
                $this->commitTicket($ticketid, ['last_reply_id' => max($rid, (int) $trow->last_reply_id)]);
                continue;
            }
            $this->pushReplyComment($ticketid, (string) $trow->task_id, $r, false);
            $this->commitTicket($ticketid, ['last_reply_id' => max($rid, (int) $trow->last_reply_id)]);
            $trow = Db::ticket($ticketid);
        }
    }

    private function pushReplyComment($ticketid, $taskId, array $reply, $isBackfill)
    {
        $rid = (int) ($reply['replyid'] ?? $reply['id'] ?? 0);
        // already mapped? (idempotency key = whmcs_reply_id)
        $existing = Db::replyByWhmcsId($ticketid, $rid);
        if ($existing && $existing->gd_message_id) {
            return;
        }
        $t = $this->getTicket($ticketid);
        $comment = Formatter::replyComment($t ?: ['tid' => '', 'subject' => ''], $reply);

        // attachments → GoodDay via the S3 web-upload flow (§1γ). Text-only fallback.
        $atts = $this->gd->webEnabled() ? $this->replyAttachments($ticketid, $reply) : [];
        $mid  = null;
        if (!empty($atts)) {
            $refs = [];
            foreach ($atts as $a) {
                $ref = $this->gd->uploadAttachment($a['name'], $a['mime'], $a['data']);
                if ($ref) {
                    $refs[] = $ref;
                }
            }
            if (!empty($refs)) {
                $res = $this->gd->webReply($taskId, $comment, $refs);
                $mid = $res['message_id'] ?? null;
            } else {
                // upload failed → never lose the text
                $res = $this->gd->addComment($taskId, $comment);
                $mid = $res['message_id'] ?? null;
                Db::log('push.attFail', 'reply ' . $rid . ' sent text-only (upload failed)', 'warn', $ticketid);
            }
        } else {
            $res = $this->gd->addComment($taskId, $comment);
            $mid = $res['message_id'] ?? null;
        }

        $this->commitReply($ticketid, [
            'whmcs_reply_id' => $rid,
            'gd_message_id'  => $mid,
            'signature'      => Formatter::whmcsReplySignature($reply),
            'origin'         => 'whmcs',
            'kind'           => 'reply',
        ]);
        Db::log('push.reply', 'reply ' . $rid . ' → msg ' . ($mid ?: '?')
            . (count($atts) ? ' +' . count($atts) . 'att' : '')
            . ($this->dryRun ? ' [DRY_RUN]' : ''), 'info', $ticketid);
    }

    /**
     * Fetch a WHMCS reply's attachments as raw bytes (§1β). Handles the 0/1-based
     * GetTicketAttachment index quirk and replyid=0 (ticket-level) attachments.
     * Returns [{name, data(bytes), mime}].
     */
    private function replyAttachments($ticketid, array $reply)
    {
        $out = [];
        $rid   = (int) ($reply['replyid'] ?? $reply['id'] ?? 0);
        $files = Formatter::normaliseAttachments($reply['attachments'] ?? ($reply['attachment'] ?? ''));
        if (empty($files) || $this->dryRun) {
            return $out;
        }
        $type      = ($rid === 0) ? 'ticket' : 'reply';
        $relatedId = ($rid === 0) ? (int) $ticketid : $rid;
        $seen = [];
        for ($i = 0; $i < count($files); $i++) {
            foreach ([$i, $i + 1, $i - 1] as $idx) {   // 0/1-based quirk
                if ($idx < 0) {
                    continue;
                }
                $r = $this->localApi('GetTicketAttachment', ['type' => $type, 'relatedid' => $relatedId, 'index' => $idx]);
                if (($r['result'] ?? '') === 'success' && !empty($r['data'])) {
                    $name = $r['filename'] ?? ('file' . $i);
                    if (isset($seen[$name])) {
                        continue; // already grabbed this file via a different offset
                    }
                    $seen[$name] = true;
                    $out[] = ['name' => $name, 'data' => base64_decode($r['data']), 'mime' => $this->mimeFromName($name)];
                    break;
                }
            }
        }
        return $out;
    }

    private function mimeFromName($name)
    {
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        $map = [
            'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'txt' => 'text/plain', 'csv' => 'text/csv',
            'zip' => 'application/zip', 'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }

    /**
     * WHMCS status change → GoodDay task status (by matching name), plus optional
     * custom-field mirror. Hook: TicketStatusChange.
     */
    public function pushStatus($ticketid, $status)
    {
        if (!self::flag($this->cfg, 'sync_status_to_goodday', true)) {
            return;
        }
        $ticketid = (int) $ticketid;
        $trow = Db::ticket($ticketid);
        if (!$trow || !$trow->created) {
            return;
        }
        // Loop-prevention: if this exact status was just set FROM GoodDay, don't echo it back.
        if ((string) $trow->last_whmcs_status_set_from_gd !== ''
            && (string) $trow->last_whmcs_status_set_from_gd === (string) $status) {
            $this->commitTicket($ticketid, ['last_whmcs_status_set_from_gd' => '']); // consume the guard
            return;
        }
        if ((string) $trow->last_status_synced === (string) $status) {
            return; // dedupe
        }

        // 1) Move the GoodDay task to the status with the matching name.
        $gdStatusId = $this->gdStatusIdForWhmcs($status);
        if ($gdStatusId !== null && (string) $trow->task_id !== '') {
            $this->gd->setTaskStatus((string) $trow->task_id, $gdStatusId);
            Db::log('push.status.task', 'WHMCS "' . $status . '" → GD status ' . $gdStatusId
                . ($this->dryRun ? ' [DRY_RUN]' : ''), 'info', $ticketid);
        }

        // 2) Optional: mirror the WHMCS status into the GoodDay custom field too.
        $cfId = trim((string) $this->cfg('gd_cf_status', ''));
        if ($cfId !== '') {
            $t = $this->getTicket($ticketid);
            if ($t !== null) {
                $t['status'] = $status;
                $this->gd->setCustomFields((string) $trow->task_id, $this->buildCustomFields($t));
            }
        }

        $this->commitTicket($ticketid, [
            'last_status_synced'            => (string) $status,
            'last_gd_status_synced'         => (string) ($gdStatusId ?: $trow->last_gd_status_synced),
            'last_whmcs_status_set_from_gd' => '',
        ]);
        Db::log('push.status', 'status → ' . $status . ($this->dryRun ? ' [DRY_RUN]' : ''), 'info', $ticketid);
    }

    /* ------------------------------------------------------------------ */
    /* Status name mapping (WHMCS titles ↔ GoodDay statuses)              */
    /* ------------------------------------------------------------------ */

    private static $gdStatusById = null;
    private static $gdStatusByName = null;

    private function loadGdStatuses()
    {
        if (self::$gdStatusById !== null) {
            return;
        }
        self::$gdStatusById = [];
        self::$gdStatusByName = [];
        try {
            $res = $this->gd->getStatuses();
            $body = $res['body'] ?? [];
            if (is_array($body)) {
                foreach ($body as $s) {
                    if (!is_array($s) || !isset($s['id'], $s['name'])) {
                        continue;
                    }
                    self::$gdStatusById[(string) $s['id']] = (string) $s['name'];
                    self::$gdStatusByName[self::normStatus($s['name'])] = (string) $s['id'];
                }
            }
        } catch (\Throwable $e) {
        }
    }

    private static function normStatus($s)
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $s)));
    }

    /**
     * Configurable WHMCS-title → GoodDay-name aliases (for statuses whose names
     * differ between the two systems, e.g. "Offer Approved" ↔ "Offer Accepted").
     * gd_status_aliases config is a JSON object {whmcsTitle: goodDayName}.
     * @return array [normWhmcs => normGdName]
     */
    private function statusAliases()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $json = trim((string) $this->cfg('gd_status_aliases', ''));
        if ($json !== '') {
            $map = json_decode($json, true);
            if (is_array($map)) {
                foreach ($map as $whmcsTitle => $gdName) {
                    $cache[self::normStatus($whmcsTitle)] = self::normStatus($gdName);
                }
            }
        }
        return $cache;
    }

    /** WHMCS status title → GoodDay status id (exact name, then alias), or null. */
    private function gdStatusIdForWhmcs($whmcsTitle)
    {
        $this->loadGdStatuses();
        $n = self::normStatus($whmcsTitle);
        if (isset(self::$gdStatusByName[$n])) {
            return self::$gdStatusByName[$n];
        }
        $aliases = $this->statusAliases();
        if (isset($aliases[$n]) && isset(self::$gdStatusByName[$aliases[$n]])) {
            return self::$gdStatusByName[$aliases[$n]];
        }
        return null;
    }

    /** GoodDay status id → WHMCS status title (exact name, then reverse alias), or null. */
    private function whmcsTitleForGdStatusId($gdStatusId)
    {
        $this->loadGdStatuses();
        $name = self::$gdStatusById[(string) $gdStatusId] ?? null;
        if ($name === null) {
            return null;
        }
        $n = self::normStatus($name);
        foreach ($this->whmcsStatuses() as $title) {
            if (self::normStatus($title) === $n) {
                return $title;
            }
        }
        // reverse alias: a GoodDay name that an alias maps a WHMCS title to.
        foreach ($this->statusAliases() as $whmcsNorm => $gdNorm) {
            if ($gdNorm === $n) {
                foreach ($this->whmcsStatuses() as $title) {
                    if (self::normStatus($title) === $whmcsNorm) {
                        return $title;
                    }
                }
            }
        }
        return null;
    }

    /** WHMCS ticket status titles (from tblticketstatuses + core), cached. */
    private function whmcsStatuses()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            foreach (\WHMCS\Database\Capsule::table("tblticketstatuses")->pluck('title') as $t) {
                $cache[] = (string) $t;
            }
        } catch (\Throwable $e) {
        }
        foreach (['Open', 'Answered', 'Customer-Reply', 'Closed', 'In Progress', 'On Hold'] as $t) {
            if (!in_array($t, $cache, true)) {
                $cache[] = $t;
            }
        }
        return $cache;
    }

    /** Current GoodDay task status id, derived from the latest status-change message. */
    private function gdTaskStatusFromMessages(array $messages)
    {
        $latestDate = '';
        $statusId = null;
        foreach ($messages as $m) {
            if (!is_array($m)) {
                continue;
            }
            // status-change events carry taskSystemStatusNew + the new taskStatusId
            if (isset($m['taskSystemStatusNew']) && $m['taskSystemStatusNew'] !== null && !empty($m['taskStatusId'])) {
                $d = (string) ($m['dateCreated'] ?? $m['momentCreated'] ?? '');
                if ($d >= $latestDate) {
                    $latestDate = $d;
                    $statusId = (string) $m['taskStatusId'];
                }
            }
        }
        return $statusId;
    }

    /** GoodDay task status → WHMCS ticket status (called from the reconciler). */
    private function reconcileTicketStatus($trow, $ticketid, array $messages)
    {
        if (!self::flag($this->cfg, 'sync_status_gd_to_whmcs', true)) {
            return;
        }
        $gdStatusId = $this->gdTaskStatusFromMessages($messages);
        if ($gdStatusId === null) {
            return;
        }
        // Loop-prevention: this GD status is the one we just pushed FROM WHMCS.
        if ((string) $trow->last_gd_status_synced === (string) $gdStatusId) {
            return;
        }
        $whmcsTitle = $this->whmcsTitleForGdStatusId($gdStatusId);
        if ($whmcsTitle === null) {
            // No WHMCS equivalent (yet). Do NOT record last_gd_status_synced —
            // otherwise, if a matching WHMCS status/alias is added later, this
            // ticket would never re-sync. Re-derivation is free (messages are
            // already in hand), so rechecking each run costs nothing extra.
            return;
        }
        $current = (string) \WHMCS\Database\Capsule::table('tbltickets')->where('id', $ticketid)->value('status');
        if (self::normStatus($current) === self::normStatus($whmcsTitle)) {
            $this->commitTicket($ticketid, ['last_gd_status_synced' => (string) $gdStatusId]);
            return; // already in sync
        }
        if (!$this->dryRun) {
            // set the guard BEFORE UpdateTicket so the resulting TicketStatusChange hook doesn't echo back
            $this->commitTicket($ticketid, [
                'last_gd_status_synced'         => (string) $gdStatusId,
                'last_whmcs_status_set_from_gd' => $whmcsTitle,
            ]);
            $this->localApi('UpdateTicket', ['ticketid' => $ticketid, 'status' => $whmcsTitle]);
        }
        Db::log('gd.status', 'GD status ' . $gdStatusId . ' (' . (self::$gdStatusById[$gdStatusId] ?? '?')
            . ') → WHMCS "' . $whmcsTitle . '"' . ($this->dryRun ? ' [DRY_RUN]' : ''), 'info', $ticketid);
    }

    /* ------------------------------------------------------------------ */
    /* WHMCS edit/delete detection (§4.3/§4.4) — cron only (no hooks)     */
    /* ------------------------------------------------------------------ */

    public function detectWhmcsEditsAndDeletes($trow)
    {
        $ticketid = (int) $trow->ticketid;
        $t = $this->getTicket($ticketid);
        if ($t === null) {
            return;
        }
        // current WHMCS reply ids (>0)
        $current = [];
        foreach ($t['replies'] as $r) {
            $rid = (int) ($r['replyid'] ?? $r['id'] ?? 0);
            if ($rid > 0) {
                $current[$rid] = $r;
            }
        }
        $known = Db::replies($ticketid);

        // EDITS (§4.3): signature changed for a known whmcs reply
        if (self::flag($this->cfg, 'sync_edits_whmcs_to_goodday', true)) {
            foreach ($known as $row) {
                if ($row->origin !== 'whmcs' || (int) $row->whmcs_reply_id <= 0) {
                    continue;
                }
                $rid = (int) $row->whmcs_reply_id;
                if (!isset($current[$rid])) {
                    continue;
                }
                $sig = Formatter::whmcsReplySignature($current[$rid]);
                if ($row->signature && $sig !== $row->signature && $row->gd_message_id) {
                    $comment = Formatter::replyComment($t, $current[$rid]);
                    $this->gd->editMessage((string) $trow->task_id, $row->gd_message_id, $comment);
                    $this->commitReply($ticketid, [
                        'whmcs_reply_id' => $rid, 'gd_message_id' => $row->gd_message_id,
                        'signature' => $sig, 'origin' => 'whmcs', 'kind' => 'reply',
                    ]);
                    Db::log('edit.whmcs2gd', 'reply ' . $rid . ($this->dryRun ? ' [DRY_RUN]' : ''), 'info', $ticketid);
                }
            }
        }

        // DELETES (§4.4): a known whmcs reply id disappeared
        if (self::flag($this->cfg, 'sync_deletes_whmcs_to_goodday', true)) {
            foreach ($known as $row) {
                if ($row->origin !== 'whmcs' || (int) $row->whmcs_reply_id <= 0) {
                    continue;
                }
                $rid = (int) $row->whmcs_reply_id;
                if (!isset($current[$rid])) {
                    if ($row->gd_message_id) {
                        $this->gd->deleteMessage((string) $trow->task_id, $row->gd_message_id);
                        Db::log('delete.whmcs2gd', 'reply ' . $rid . ' msg ' . $row->gd_message_id . ($this->dryRun ? ' [DRY_RUN]' : ''), 'info', $ticketid);
                    }
                    $this->commitDeleteReply($row->id);
                }
            }
        }
    }

    /* ================================================================== */
    /* GoodDay → WHMCS (§5) — reconciler                                  */
    /* ================================================================== */

    public function reconcileTicket($trow)
    {
        if (!self::flag($this->cfg, 'sync_goodday_to_whmcs', true)) {
            return;
        }
        $ticketid = (int) $trow->ticketid;
        if ((float) $trow->gd_to_whmcs_backoff_until > microtime(true)) {
            return; // backoff
        }

        $res = $this->gd->getTaskMessages((string) $trow->task_id);
        if (!$res['ok']) {
            // §5.0: task missing → LOG ONLY, never delete a WHMCS ticket
            if ($res['status'] === 404) {
                $this->commitTicket($ticketid, ['gd_task_missing_hits' => (int) $trow->gd_task_missing_hits + 1]);
                Db::log('gd.taskMissing', 'task ' . $trow->task_id . ' not found (no action — full-delete disabled)', 'warn', $ticketid);
            } else {
                $this->commitTicket($ticketid, ['gd_to_whmcs_backoff_until' => microtime(true) + 3]);
                Db::log('gd.messagesFail', 'HTTP ' . $res['status'], 'warn', $ticketid);
            }
            return;
        }

        $messages = [];
        if (is_array($res['body'])) {
            $messages = $res['body']['messages'] ?? $res['body'];
        }
        if (!is_array($messages)) {
            $messages = [];
        }

        $prefix   = (string) $this->cfg('gd_public_prefix', '!public');
        $botUser  = (string) $this->cfg('gd_from_user_id', '');
        $current  = [];
        foreach ($messages as $m) {
            if (!Formatter::isSoftDeleted($m, self::flag($this->cfg, 'gd_soft_delete_empty_heuristic', true))) {
                $current[(string) ($m['id'] ?? '')] = true;
            }
        }

        // §5.2 deletes based on mapping (before creates)
        if (self::flag($this->cfg, 'sync_deletes_goodday_to_whmcs', true)) {
            foreach (Db::replies($ticketid) as $row) {
                if (!$row->gd_message_id || $row->kind !== 'public') {
                    continue;
                }
                if (!isset($current[(string) $row->gd_message_id]) && (int) $row->whmcs_reply_id > 0) {
                    $this->deleteWhmcsReply($ticketid, (int) $row->whmcs_reply_id);
                    $this->commitDeleteReply($row->id);
                    Db::log('gd.deleteReply', 'reply ' . $row->whmcs_reply_id . ($this->dryRun ? ' [DRY_RUN]' : ''), 'info', $ticketid);
                }
            }
        }

        // §5.3 per message
        foreach ($this->sortMessages($messages) as $m) {
            $mid  = (string) ($m['id'] ?? '');
            if ($mid === '') {
                continue;
            }
            if (Formatter::isSoftDeleted($m, self::flag($this->cfg, 'gd_soft_delete_empty_heuristic', true))) {
                continue;
            }
            $from = (string) ($m['fromUserId'] ?? $m['userId'] ?? '');
            $text = Formatter::gdMessageText($m);

            // bot echo / mirror (our own "WHMCS Ticket #" comments)
            if (strncmp(ltrim($text), 'WHMCS Ticket #', 14) === 0) {
                continue; // mirror-edit handling is optional; do not re-ingest our echoes
            }
            // our own bot messages that aren't public commands → skip
            $publicBody = Formatter::extractPublicBody($text, $prefix);
            if ($from === $botUser && $publicBody === null) {
                continue;
            }
            if ($publicBody === null) {
                continue; // internal GoodDay chatter stays internal
            }
            $this->applyPublicMessage($trow, $ticketid, $mid, $publicBody, $m);
        }

        // §5.5 status sync: GoodDay task status → WHMCS ticket status (by matching name).
        $this->reconcileTicketStatus($trow, $ticketid, $messages);

        $this->commitTicket($ticketid, ['last_scanned' => date('Y-m-d H:i:s'), 'gd_task_missing_hits' => 0]);
    }

    /** §5.4 public message → WHMCS reply (create/edit). */
    private function applyPublicMessage($trow, $ticketid, $mid, $body, array $m)
    {
        // attachments may live under 'attachments' or 'files' (§2α)
        $gdAttachments = [];
        if (isset($m['attachments']) && is_array($m['attachments'])) {
            $gdAttachments = $m['attachments'];
        } elseif (isset($m['files']) && is_array($m['files'])) {
            $gdAttachments = $m['files'];
        }
        $sig  = Formatter::gdPublicSignature($body, $gdAttachments);
        $row  = Db::replyByGdMessage($ticketid, $mid, 'public');
        $prevSig = $row ? $row->signature : null;

        if ($prevSig !== null && $prevSig === $sig) {
            return; // unchanged
        }

        $body = Formatter::stripRepeatedMarkerPrefix($body, (string) $this->cfg('gd_edit_note_prefix', ''));

        // empty body is OK when the message carries attachments (file-only reply)
        if (trim($body) === '') {
            $body = trim((string) $this->cfg('gd_public_empty_body', ''));
            if ($body === '' && !empty($gdAttachments)) {
                $body = 'Shared files from GoodDay.';
            }
            if ($body === '') {
                return;
            }
        }

        $isEdited = ($prevSig !== null && $prevSig !== $sig);

        if ($isEdited && $row && (int) $row->whmcs_reply_id > 0) {
            if (!self::flag($this->cfg, 'sync_edits_goodday_to_whmcs', true)) {
                $this->commitReply($ticketid, ['whmcs_reply_id' => (int) $row->whmcs_reply_id, 'gd_message_id' => $mid,
                    'signature' => $sig, 'origin' => 'goodday_public', 'kind' => 'public']);
                return;
            }
            $this->updateWhmcsReply($ticketid, (int) $row->whmcs_reply_id, $body);
            $this->setStatusOnPublic($ticketid);
            $this->commitReply($ticketid, ['whmcs_reply_id' => (int) $row->whmcs_reply_id, 'gd_message_id' => $mid,
                'signature' => $sig, 'origin' => 'goodday_public', 'kind' => 'public']);
            Db::log('gd.editReply', 'reply ' . $row->whmcs_reply_id . ($this->dryRun ? ' [DRY_RUN]' : ''), 'info', $ticketid);
            return;
        }

        if ($row && (int) $row->whmcs_reply_id > 0) {
            // known mapping but no prevSig recorded → restart-safe dedupe: only record signature
            $this->commitReply($ticketid, ['whmcs_reply_id' => (int) $row->whmcs_reply_id, 'gd_message_id' => $mid,
                'signature' => $sig, 'origin' => 'goodday_public', 'kind' => 'public']);
            return;
        }

        // CREATE path — download GoodDay attachments and attach to the new reply
        $whmcsAtt = $this->downloadAttachments($ticketid, $gdAttachments);
        $rid = $this->addWhmcsReply($ticketid, $body, $whmcsAtt);
        // ALWAYS record the mapping (even if rid couldn't be resolved) so the reply
        // is never re-created on the next run. commitReply no-ops in dry-run.
        $this->commitReply($ticketid, [
            'whmcs_reply_id' => (int) ($rid ?? 0),
            'gd_message_id'  => $mid,
            'signature'      => $sig,
            'origin'         => 'goodday_public',
            'kind'           => 'public',
        ]);
        if ($rid) {
            $this->setStatusOnPublic($ticketid);
        }
        Db::log('gd.addReply', 'msg ' . $mid . ' → reply ' . ($rid ?: '?')
            . (count($whmcsAtt) ? ' +' . count($whmcsAtt) . 'att' : '')
            . ($this->dryRun ? ' [DRY_RUN]' : ''), 'info', $ticketid);
    }

    /** Download GoodDay attachments → WHMCS AddTicketReply format [{name,data(base64)}]. §9.13 */
    private function downloadAttachments($ticketid, array $gdAttachments)
    {
        $out = [];
        if ($this->dryRun || empty($gdAttachments)) {
            return $out;
        }
        $maxBytes = ((int) $this->cfg('gd_max_attachment_mb', 25)) * 1024 * 1024;
        foreach ($gdAttachments as $a) {
            $url = $a['downloadUrl'] ?? '';
            if ($url === '') {
                continue;
            }
            $bytes = $this->gd->downloadFile($url, $maxBytes);
            if ($bytes === null) {
                Db::log('gd.attFail', 'download failed: ' . ($a['name'] ?? '?'), 'warn', $ticketid);
                continue;
            }
            $out[] = ['name' => $a['name'] ?? ($a['fileName'] ?? 'attachment'), 'data' => base64_encode($bytes)];
        }
        return $out;
    }

    private function setStatusOnPublic($ticketid)
    {
        $status = trim((string) $this->cfg('whmcs_status_on_gd_public', 'Answered'));
        if ($status === '' || $this->dryRun) {
            return;
        }
        $this->localApi('UpdateTicket', ['ticketid' => (int) $ticketid, 'status' => $status]);
    }

    /* ------------------------------------------------------------------ */
    /* WHMCS write helpers (with inbound loop-guard)                      */
    /* ------------------------------------------------------------------ */

    private function addWhmcsReply($ticketid, $message, array $attachments = [])
    {
        if ($this->dryRun) {
            Db::log('dry.addWhmcsReply', ['ticket' => $ticketid, 'len' => strlen($message), 'att' => count($attachments)], 'dry', $ticketid);
            return null; // no real reply created in dry-run
        }
        // guard the reply hook so this inbound reply is not pushed back to GoodDay
        $canon = Formatter::canonical($message);
        self::$inboundGuard[(int) $ticketid][] = $canon;

        $post = [
            'ticketid'      => (int) $ticketid,
            'message'       => $message,
            'adminusername' => (string) $this->cfg('whmcs_admin_username', 'support'),
        ];
        if (!empty($attachments)) {
            // WHMCS: base64(json([{name, data:base64(bytes)}, ...]))
            $post['attachments'] = base64_encode(json_encode($attachments));
        }
        $r = $this->localApi('AddTicketReply', $post);

        // clear guard entry
        if (isset(self::$inboundGuard[(int) $ticketid])) {
            self::$inboundGuard[(int) $ticketid] = array_values(array_diff(self::$inboundGuard[(int) $ticketid], [$canon]));
        }
        if (($r['result'] ?? '') !== 'success') {
            Db::log('addWhmcsReply.fail', $r['message'] ?? 'unknown', 'error', $ticketid);
            return null;
        }
        // WHMCS AddTicketReply does NOT return the reply id → resolve it from the
        // ticket's newest reply matching this body (critical: without an id the
        // mapping can't be saved and the reply gets re-created every run).
        $rid = $r['replyid'] ?? $r['id'] ?? null;
        if (!$rid) {
            $rid = $this->findReplyId($ticketid, $message);
        }
        return $rid ?: null;
    }

    /** Find the reply id WHMCS just created (newest matching body on the ticket). */
    private function findReplyId($ticketid, $message)
    {
        try {
            $canon = Formatter::canonical($message);
            $rows = \WHMCS\Database\Capsule::table('tblticketreplies')
                ->where('tid', (int) $ticketid)->orderBy('id', 'desc')->limit(8)->get();
            foreach ($rows as $rr) {
                if (Formatter::canonical($rr->message) === $canon) {
                    return (int) $rr->id;
                }
            }
            return isset($rows[0]) ? (int) $rows[0]->id : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function updateWhmcsReply($ticketid, $replyid, $message)
    {
        if ($this->dryRun) {
            Db::log('dry.updateWhmcsReply', ['reply' => $replyid], 'dry', $ticketid);
            return;
        }
        $this->localApi('UpdateTicketReply', [
            'replyid'       => (int) $replyid,
            'message'       => $message,
            'adminusername' => (string) $this->cfg('whmcs_admin_username', 'support'),
        ]);
    }

    private function deleteWhmcsReply($ticketid, $replyid)
    {
        if ($this->dryRun) {
            Db::log('dry.deleteWhmcsReply', ['reply' => $replyid], 'dry', $ticketid);
            return;
        }
        $this->localApi('DeleteTicketReply', ['ticketid' => (int) $ticketid, 'replyid' => (int) $replyid]);
    }

    /**
     * Called by the reply hook: is this reply one we are currently injecting
     * from GoodDay (same process), or is it recorded with a GoodDay origin?
     */
    public function isInboundReply($ticketid, array $reply)
    {
        $ticketid = (int) $ticketid;
        // same-process guard
        $canon = Formatter::canonical($reply['message'] ?? '');
        if (!empty(self::$inboundGuard[$ticketid]) && in_array($canon, self::$inboundGuard[$ticketid], true)) {
            return true;
        }
        // persisted origin
        $rid = (int) ($reply['replyid'] ?? $reply['id'] ?? 0);
        if ($rid > 0) {
            $row = Db::replyByWhmcsId($ticketid, $rid);
            if ($row && in_array($row->origin, ['goodday_public', 'mirror'], true)) {
                return true;
            }
        }
        return false;
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function sortMessages(array $messages)
    {
        usort($messages, function ($a, $b) {
            $ka = $a['dateCreated'] ?? ($a['createdAt'] ?? ($a['date'] ?? ($a['id'] ?? '')));
            $kb = $b['dateCreated'] ?? ($b['createdAt'] ?? ($b['date'] ?? ($b['id'] ?? '')));
            return strcmp((string) $ka, (string) $kb);
        });
        return $messages;
    }
}
