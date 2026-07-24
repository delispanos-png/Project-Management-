<?php
/**
 * GoodDay HTTP client — two layers (spec §3):
 *   • Official API v2  (api.goodday.work/2.0, header gd-api-token) — reads + writes.
 *   • Private web API  (www.goodday.work/api/app/…, scraped JWT)  — ONLY message
 *     edit/delete + attachments, behind $webEnabled. Official is preferred.
 *
 * Uses curl directly (no autoload assumptions). Every WRITE is gated by the
 * caller-supplied DRY_RUN flag: in dry-run it logs the intended call and
 * returns a simulated result — it NEVER touches the shared GoodDay workspace.
 *
 * @package WHMCS\Module\Addon\GoodDaySync
 */

namespace WHMCS\Module\Addon\GoodDaySync;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class GoodDayClient
{
    /** @var array module settings */
    private $cfg;
    /** @var bool */
    public $dryRun;
    /** @var callable|null logger(event, detail, level) */
    private $logger;
    /** @var callable|null tokenSaver(jwt) — persists a freshly-obtained web JWT */
    private $tokenSaver;

    /** cached web JWT + expiry */
    private $webToken = null;
    private $webTokenExp = 0;
    /** cached GET /statuses response */
    private $statusesCache = null;

    public function __construct(array $cfg, $dryRun = true, $logger = null, $tokenSaver = null)
    {
        $this->cfg        = $cfg;
        $this->dryRun     = (bool) $dryRun;
        $this->logger     = $logger;
        $this->tokenSaver = $tokenSaver;
    }

    private function log($event, $detail = '', $level = 'info')
    {
        if (is_callable($this->logger)) {
            call_user_func($this->logger, $event, $detail, $level);
        }
    }

    private function officialBase()
    {
        return rtrim($this->cfg['gd_api_base'] ?? 'https://api.goodday.work/2.0', '/');
    }

    private function webOrigin()
    {
        return rtrim($this->cfg['gd_web_origin'] ?? 'https://www.goodday.work', '/');
    }

    /* ================================================================== */
    /* Low-level HTTP (curl)                                              */
    /* ================================================================== */

    /**
     * @return array [ok(bool), status(int), body(mixed decoded), raw(string), error(string)]
     */
    private function http($method, $url, array $headers = [], $body = null, $connectTimeout = 6, $timeout = 12)
    {
        $ch = curl_init($url);
        $h  = [];
        foreach ($headers as $k => $v) {
            $h[] = $k . ': ' . $v;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $h,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        }
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $decoded = $raw; // non-JSON (e.g. "OK")
            }
        }
        $ok = ($err === '' && $status >= 200 && $status < 300);
        return [
            'ok'     => $ok,
            'status' => $status,
            'body'   => $decoded,
            'raw'    => (string) $raw,
            'error'  => $err,
        ];
    }

    private function officialHeaders()
    {
        return [
            'gd-api-token' => (string) ($this->cfg['gd_api_token'] ?? ''),
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    /* ================================================================== */
    /* Official API v2                                                    */
    /* ================================================================== */

    /** GET /project/{id}/tasks (read) — for reachability test + rebuild. */
    public function getProjectTasks($projectId)
    {
        return $this->http('GET', $this->officialBase() . '/project/' . rawurlencode($projectId) . '/tasks',
            $this->officialHeaders(), null, 6, 10);
    }

    /** GET /task/{id} (read). */
    public function getTask($taskId)
    {
        return $this->http('GET', $this->officialBase() . '/task/' . rawurlencode($taskId),
            $this->officialHeaders(), null, 6, 8);
    }

    /** GET /task/{id}/messages (read). */
    public function getTaskMessages($taskId)
    {
        return $this->http('GET', $this->officialBase() . '/task/' . rawurlencode($taskId) . '/messages',
            $this->officialHeaders(), null, 6, 8);
    }

    /** POST /tasks → task id.  WRITE (idempotency-sensitive: no auto retry). */
    public function createTask($projectId, $title, $message, $extra = [])
    {
        $payload = array_merge([
            'projectId'  => (string) $projectId,
            'taskTypeId' => (string) ($this->cfg['gd_task_type_id'] ?? 'hB9A7F'),
            'fromUserId' => (string) ($this->cfg['gd_from_user_id'] ?? ''),
            'title'      => (string) $title,
            'message'    => (string) $message,
        ], $extra);
        $toUser = trim((string) ($this->cfg['gd_to_user_id'] ?? ''));
        if ($toUser !== '') {
            $payload['toUserId'] = $toUser;
        }

        if ($this->dryRun) {
            $this->log('dry.createTask', $payload, 'dry');
            return ['ok' => true, 'status' => 0, 'body' => ['id' => 'DRYRUN-TASK'], 'dry' => true];
        }
        $r = $this->http('POST', $this->officialBase() . '/tasks', $this->officialHeaders(), $payload, 6, 12);
        $r['task_id'] = is_array($r['body']) ? ($r['body']['id'] ?? null) : null;
        return $r;
    }

    /** POST /task/{id}/comment → message id.  WRITE. */
    public function addComment($taskId, $message)
    {
        $payload = [
            'userId'  => (string) ($this->cfg['gd_from_user_id'] ?? ''),
            'message' => (string) $message,
        ];
        if ($this->dryRun) {
            $this->log('dry.addComment', ['task' => $taskId, 'len' => strlen($message)], 'dry');
            return ['ok' => true, 'status' => 0, 'body' => ['id' => 'DRYRUN-MSG'], 'message_id' => 'DRYRUN-MSG', 'dry' => true];
        }
        $r = $this->http('POST', $this->officialBase() . '/task/' . rawurlencode($taskId) . '/comment',
            $this->officialHeaders(), $payload, 6, 12);
        $r['message_id'] = $this->extractMessageId($r['body']);
        return $r;
    }

    /** PUT /task/{id}/custom-fields.  WRITE. */
    public function setCustomFields($taskId, array $fields)
    {
        $payload = ['customFields' => array_values($fields)];
        if ($this->dryRun) {
            $this->log('dry.setCustomFields', ['task' => $taskId, 'fields' => $fields], 'dry');
            return ['ok' => true, 'status' => 0, 'body' => 'OK', 'dry' => true];
        }
        return $this->http('PUT', $this->officialBase() . '/task/' . rawurlencode($taskId) . '/custom-fields',
            $this->officialHeaders(), $payload, 6, 12);
    }

    /** PUT /task/{id}/status — move a task to a GoodDay status. WRITE. */
    public function setTaskStatus($taskId, $statusId)
    {
        $payload = [
            'userId'   => (string) ($this->cfg['gd_from_user_id'] ?? ''),
            'statusId' => (string) $statusId,
        ];
        if ($this->dryRun) {
            $this->log('dry.setTaskStatus', ['task' => $taskId, 'statusId' => $statusId], 'dry');
            return ['ok' => true, 'status' => 0, 'body' => 'OK', 'dry' => true];
        }
        return $this->http('PUT', $this->officialBase() . '/task/' . rawurlencode($taskId) . '/status',
            $this->officialHeaders(), $payload, 6, 12);
    }

    /** GET /statuses — the workspace's task statuses (id ↔ name). Read-only, cached. */
    public function getStatuses()
    {
        if ($this->statusesCache !== null) {
            return $this->statusesCache;
        }
        $r = $this->http('GET', $this->officialBase() . '/statuses', $this->officialHeaders(), null, 6, 10);
        $this->statusesCache = $r;
        return $r;
    }

    /** Best-effort message id extraction from a comment response (§9.2). */
    private function extractMessageId($body)
    {
        if (!is_array($body)) {
            return null;
        }
        foreach (['taskMessageId', 'messageId', 'id'] as $k) {
            if (!empty($body[$k]) && !is_array($body[$k])) {
                return (string) $body[$k];
            }
        }
        if (isset($body['taskMessage']['id'])) {
            return (string) $body['taskMessage']['id'];
        }
        // web reply returns a session[] broadcast: [{taskMessage:{id}, object:'task-message', data:{id}}, ...]
        if (isset($body['session']) && is_array($body['session'])) {
            foreach ($body['session'] as $entry) {
                if (isset($entry['taskMessage']['id'])) {
                    return (string) $entry['taskMessage']['id'];
                }
                if (($entry['object'] ?? '') === 'task-message' && isset($entry['data']['id']) && !is_array($entry['data']['id'])) {
                    return (string) $entry['data']['id'];
                }
            }
        }
        if (isset($body['message']) && isset($body['taskId']) && !is_array($body['message'])) {
            return (string) ($body['id'] ?? '');
        }
        return null;
    }

    /* ================================================================== */
    /* Private web API (edit/delete/attachments) — behind flag           */
    /* ================================================================== */

    public function webEnabled()
    {
        return !empty($this->cfg['gd_web_enabled']);
    }

    /** Obtain a web JWT: static ACCESS_TOKEN, else login (email/password). */
    private function webAccessToken()
    {
        $static = trim((string) ($this->cfg['gd_access_token'] ?? ''));
        if ($static !== '' && !$this->jwtExpired($static)) {
            return $static;
        }
        if ($this->webToken && $this->webTokenExp > time() + 60) {
            return $this->webToken;
        }
        // login flow (email/password → fresh JWT). Endpoint verified: POST
        // /api/auth/login returns 403 "Incorrect email/password" on bad creds,
        // and the accessToken in the body on success (no CSRF required).
        $email = trim((string) ($this->cfg['gd_login_email'] ?? ''));
        $pass  = (string) ($this->cfg['gd_login_password'] ?? '');
        if ($email === '' || $pass === '') {
            $this->log('web.noAuth', 'No valid access token and no login credentials', 'warn');
            return null;
        }
        $r = $this->http('POST', $this->webOrigin() . '/api/auth/login', [
            'Content-Type'     => 'application/json',
            'Accept'           => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'Origin'           => $this->webOrigin(),
            'Referer'          => $this->webOrigin() . '/login',
        ], ['email' => $email, 'password' => $pass, 'rememberMe' => true], 6, 15);

        $tok = ($r['status'] >= 200 && $r['status'] < 300) ? $this->extractJwt($r) : null;
        if ($tok) {
            $this->webToken    = $tok;
            $this->webTokenExp = $this->jwtExpiry($tok);
            $this->cfg['gd_access_token'] = $tok;
            // persist so crons/subsequent requests reuse it until expiry (one login/hour, not per-run)
            if (is_callable($this->tokenSaver)) {
                try {
                    call_user_func($this->tokenSaver, $tok);
                } catch (\Throwable $e) {
                    $this->log('web.tokenSaveFailed', $e->getMessage(), 'warn');
                }
            }
            $this->log('web.login', 'obtained fresh JWT (exp ' . gmdate('H:i', $this->webTokenExp) . ' UTC)', 'info');
            return $tok;
        }
        $this->log('web.loginFailed', 'status ' . $r['status'] . ' ' . substr((string) $r['raw'], 0, 120), 'error');
        return null;
    }

    /** Pull a JWT out of a login response: known fields first, then raw scan. */
    private function extractJwt(array $r)
    {
        $b = $r['body'] ?? null;
        if (is_array($b)) {
            $paths = [['accessToken'], ['token'], ['data', 'accessToken'], ['data', 'token'],
                      ['result', 'accessToken'], ['session', 'accessToken']];
            foreach ($paths as $path) {
                $v = $b;
                foreach ($path as $k) {
                    $v = (is_array($v) && isset($v[$k])) ? $v[$k] : null;
                }
                if (is_string($v) && strncmp($v, 'eyJ', 3) === 0) {
                    return $v;
                }
            }
        }
        // last resort: a JWT anywhere in the raw body (survives field renames)
        if (!empty($r['raw']) && preg_match('/eyJ[A-Za-z0-9_-]+\.eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/', (string) $r['raw'], $m)) {
            return $m[0];
        }
        return null;
    }

    private function jwtExpiry($jwt)
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return time() + 3600;
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        return isset($payload['exp']) ? (int) $payload['exp'] : (time() + 3600);
    }

    private function jwtExpired($jwt)
    {
        return $this->jwtExpiry($jwt) <= time() + 60;
    }

    private function webHeaders($token)
    {
        return [
            'Gd-Access-Token'  => $token,
            'gd-cid'           => (string) ($this->cfg['gd_company_id'] ?? ''),
            'X-Requested-With' => 'XMLHttpRequest',
            'Origin'           => $this->webOrigin(),
            'Referer'          => $this->webOrigin() . '/',
            'Content-Type'     => 'application/json',
        ];
    }

    private function webBaseBody()
    {
        return [
            'cid'       => (string) ($this->cfg['gd_company_id'] ?? ''),
            'companyId' => (string) ($this->cfg['gd_company_id'] ?? ''),
            'zz'        => (int) ($this->cfg['gd_tz_offset'] ?? 0),
        ];
    }

    /** PUT web message (edit). WRITE. Returns [ok,...]. */
    public function editMessage($taskId, $messageId, $message)
    {
        if ($this->dryRun) {
            $this->log('dry.editMessage', ['task' => $taskId, 'mid' => $messageId], 'dry');
            return ['ok' => true, 'status' => 0, 'dry' => true];
        }
        if (!$this->webEnabled()) {
            $this->log('web.disabled', 'editMessage skipped (web API disabled)', 'warn');
            return ['ok' => false, 'error' => 'web-disabled'];
        }
        $token = $this->webAccessToken();
        if (!$token) {
            return ['ok' => false, 'error' => 'no-web-token'];
        }
        $body = array_merge($this->webBaseBody(), [
            'message'    => (string) $message,
            'messageRTF' => Formatter::lexicalRtf($message),
        ]);
        return $this->http('PUT',
            $this->webOrigin() . '/api/app/task/' . rawurlencode($taskId) . '/message/' . rawurlencode($messageId),
            $this->webHeaders($token), $body, 6, 12);
    }

    /** DELETE web message. WRITE. 404 → idempotent not-found. */
    public function deleteMessage($taskId, $messageId)
    {
        if ($this->dryRun) {
            $this->log('dry.deleteMessage', ['task' => $taskId, 'mid' => $messageId], 'dry');
            return ['ok' => true, 'status' => 0, 'dry' => true];
        }
        if (!$this->webEnabled()) {
            $this->log('web.disabled', 'deleteMessage skipped (web API disabled)', 'warn');
            return ['ok' => false, 'error' => 'web-disabled'];
        }
        $token = $this->webAccessToken();
        if (!$token) {
            return ['ok' => false, 'error' => 'no-web-token'];
        }
        $r = $this->http('DELETE',
            $this->webOrigin() . '/api/app/task/' . rawurlencode($taskId) . '/message/' . rawurlencode($messageId),
            $this->webHeaders($token), $this->webBaseBody(), 6, 12);
        if ($r['status'] === 404) {
            $r['ok'] = true;
            $r['body'] = ['result' => 'not-found'];
        }
        return $r;
    }

    /* ---- Attachment upload (WHMCS → GoodDay, §1γ / §3.2) --------------- */

    private static function uuidV4()
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    /**
     * Upload a file to GoodDay's S3 via the 3-step presigned flow. Returns a
     * file ref for the web reply, or null on failure. WRITE (web API).
     */
    public function uploadAttachment($fileName, $mime, $bytes)
    {
        if ($this->dryRun) {
            $this->log('dry.upload', ['file' => $fileName, 'size' => strlen($bytes)], 'dry');
            return null;
        }
        if (!$this->webEnabled()) {
            return null;
        }
        $token = $this->webAccessToken();
        if (!$token) {
            $this->log('upload.noToken', $fileName, 'error');
            return null;
        }
        $fileId   = self::uuidV4();
        $companyId = (string) ($this->cfg['gd_company_id'] ?? '');
        $endpoint = trim((string) ($this->cfg['gd_upload_url_endpoint'] ?? 'https://7vrzn6fzld.execute-api.eu-central-1.amazonaws.com/generate-upload-url'));

        // 1) request a presigned S3 PUT url
        $r = $this->http('POST', $endpoint, [
            'Content-Type'    => 'text/plain;charset=UTF-8',
            'Gd-Access-Token' => $token,
            'gd-cid'          => $companyId,
            'Origin'          => $this->webOrigin(),
            'Referer'         => $this->webOrigin() . '/',
        ], json_encode([
            'fileId'          => $fileId,
            'companyId'       => $companyId,
            'storageProvider' => 12,
            'fileName'        => $fileName,
            'contentType'     => $mime,
        ]), 10, 30);
        $uploadUrl = is_array($r['body']) ? ($r['body']['uploadUrl'] ?? null) : null;
        if (!$uploadUrl) {
            $this->log('upload.noUrl', ['file' => $fileName, 'status' => $r['status']], 'error');
            return null;
        }

        // 2) PUT the bytes with the signed headers taken from the presigned url query
        $headers = ['Content-Type' => $mime];
        parse_str((string) parse_url($uploadUrl, PHP_URL_QUERY), $params); // parse_str url-decodes
        foreach (['x-amz-acl', 'x-amz-meta-companyid', 'x-amz-meta-filename'] as $h) {
            if (isset($params[$h]) && $params[$h] !== '') {
                $headers[$h] = $params[$h];
            }
        }
        $put = $this->http('PUT', $uploadUrl, $headers, $bytes, 10, 120);
        if (!$put['ok']) {
            $this->log('upload.putFail', ['file' => $fileName, 'status' => $put['status'], 'err' => substr((string) $put['raw'], 0, 120)], 'error');
            return null;
        }

        // 3) return the file ref for the web reply payload
        return [
            'fileId'          => $fileId,
            'name'            => $fileName,
            'storageProvider' => 12,
            'fileType'        => 0,
            'isFlagged'       => false,
            'preview'         => 2,
            'size'            => strlen($bytes),
            'mime'            => $mime,
        ];
    }

    /** POST a web reply carrying uploaded file refs. Returns [ok, message_id]. WRITE. */
    public function webReply($taskId, $message, array $fileRefs)
    {
        if ($this->dryRun) {
            $this->log('dry.webReply', ['task' => $taskId, 'files' => count($fileRefs)], 'dry');
            return ['ok' => true, 'message_id' => 'DRYRUN-MSG', 'dry' => true];
        }
        if (!$this->webEnabled()) {
            return ['ok' => false, 'error' => 'web-disabled'];
        }
        $token = $this->webAccessToken();
        if (!$token) {
            return ['ok' => false, 'error' => 'no-web-token'];
        }
        $body = array_merge($this->webBaseBody(), [
            'message'     => (string) $message,
            'messageRTF'  => Formatter::lexicalRtf($message),
            'attachments' => array_values($fileRefs),
            'files'       => array_values($fileRefs),
        ]);
        $r = $this->http('POST', $this->webOrigin() . '/api/app/task/' . rawurlencode($taskId) . '/reply',
            $this->webHeaders($token), $body, 10, 30);
        $r['message_id'] = $this->extractMessageId($r['body']);
        return $r;
    }

    /**
     * Download an attachment from its (presigned) URL. Returns the raw bytes,
     * or null on failure / oversize. §9.13 — cap default 25MB.
     */
    public function downloadFile($url, $maxBytes = 26214400)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_BUFFERSIZE     => 65536,
            CURLOPT_NOPROGRESS     => false,
            CURLOPT_PROGRESSFUNCTION => function ($ch, $dlTotal, $dlNow) use ($maxBytes) {
                return ($dlTotal > $maxBytes || $dlNow > $maxBytes) ? 1 : 0; // abort if oversize
            },
        ]);
        $data = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($err !== '' || $code < 200 || $code >= 300 || !is_string($data) || $data === '') {
            return null;
        }
        if (strlen($data) > $maxBytes) {
            return null;
        }
        return $data;
    }

    /* ================================================================== */
    /* Reachability test (read-only)                                     */
    /* ================================================================== */

    public function testConnection()
    {
        $token = trim((string) ($this->cfg['gd_api_token'] ?? ''));
        if ($token === '') {
            return ['ok' => false, 'message' => 'No GOODDAY_API_TOKEN configured.'];
        }
        $projectId = trim((string) ($this->cfg['gd_project_id'] ?? ''));
        if ($projectId === '') {
            return ['ok' => false, 'message' => 'No default project id configured.'];
        }
        $r = $this->getProjectTasks($projectId);
        if ($r['ok']) {
            $n = is_array($r['body']) ? count($r['body']) : 0;
            return ['ok' => true, 'message' => "Connected. Project {$projectId} returned {$n} tasks."];
        }
        return ['ok' => false, 'message' => 'GoodDay API error: HTTP ' . $r['status'] . ' ' . substr((string) $r['raw'], 0, 200)];
    }
}
