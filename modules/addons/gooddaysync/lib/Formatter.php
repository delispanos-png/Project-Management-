<?php
/**
 * String formats, signatures and parsers — a faithful port of app.py §9.
 * Reproduce these EXACTLY so signatures/markers match the historical data.
 *
 * @package WHMCS\Module\Addon\GoodDaySync
 */

namespace WHMCS\Module\Addon\GoodDaySync;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class Formatter
{
    /* ---- Task title (§1) ------------------------------------------------ */

    /** GoodDay task title uses the INTERNAL ticketid, not the public tid. */
    public static function taskTitle($ticketid, $subject)
    {
        $subject = trim((string) $subject);
        if ($subject === '') {
            $subject = '(no subject)';
        }
        return '[WHMCS #' . (int) $ticketid . '] ' . $subject;
    }

    /* ---- §9.3 extract_initial_message ---------------------------------- */

    /** Initial customer message (replyid=0, else replies[0]) → task body. */
    public static function extractInitialMessage(array $replies)
    {
        $initial = null;
        foreach ($replies as $r) {
            if ((int) ($r['replyid'] ?? $r['id'] ?? -1) === 0) {
                $initial = $r;
                break;
            }
        }
        if ($initial === null && !empty($replies)) {
            $initial = $replies[0];
        }
        if ($initial === null) {
            return '';
        }
        $author  = self::replyAuthor($initial);
        $date    = (string) ($initial['date'] ?? '');
        $message = self::whmcsMessageToText($initial['message'] ?? '');
        if (trim($message) === '') {
            return '';
        }
        return "\n—\nΑρχικό μήνυμα πελάτη (WHMCS):\nΑπό: {$author} | {$date}\n\n{$message}";
    }

    /* ---- §9.4 fmt_ticket_intro (fallback body) ------------------------- */

    public static function ticketIntro(array $t)
    {
        $tid       = $t['tid'] ?? '';
        $subject   = $t['subject'] ?? '';
        $dept      = $t['deptname'] ?? ($t['department'] ?? '');
        $status    = $t['status'] ?? '';
        $priority  = $t['priority'] ?? ($t['urgency'] ?? '');
        $name      = $t['requestor_name'] ?? ($t['name'] ?? '');
        $email     = $t['requestor_email'] ?? ($t['email'] ?? '');
        $created   = $t['date'] ?? '';
        $lastreply = $t['lastreply'] ?? '';
        return "WHMCS → GoodDay (auto)\n\n"
            . "Ticket: #{$tid}\n"
            . "Subject: {$subject}\n"
            . "Department: {$dept}\n"
            . "Status: {$status} | Priority: {$priority}\n"
            . "Requestor: {$name} <{$email}>\n"
            . "Created: {$created}\n"
            . "Last reply: {$lastreply}";
    }

    /* ---- §9.5 fmt_reply_comment ---------------------------------------- */

    /**
     * Comment body pushed to GoodDay for a WHMCS reply. The "WHMCS Ticket #"
     * header is what the reconciler later detects as a bot-echo / mirror.
     */
    public static function replyComment(array $t, array $reply)
    {
        $tid     = $t['tid'] ?? '';
        $subject = $t['subject'] ?? '';
        $rid     = (int) ($reply['replyid'] ?? $reply['id'] ?? 0);
        $date    = (string) ($reply['date'] ?? '');
        $from    = self::replyAuthor($reply);
        $message = self::whmcsMessageToText($reply['message'] ?? '');
        if (trim($message) === '') {
            $message = '(empty)';
        }
        return "WHMCS Ticket #{$tid}\nSubject: {$subject}\n\n"
            . "Reply ID: {$rid}\nDate: {$date}\nFrom: {$from}\n\n{$message}";
    }

    /** From: admin | name | contactname | Unknown. */
    public static function replyAuthor(array $reply)
    {
        foreach (['admin', 'name', 'contactname'] as $k) {
            if (!empty($reply[$k])) {
                return trim((string) $reply[$k]);
            }
        }
        return 'Unknown';
    }

    /* ---- §9.6 edit comment marker -------------------------------------- */

    public static function replyEditComment($base, $editPrefix)
    {
        $editPrefix = trim((string) $editPrefix);
        if ($editPrefix === '') {
            return $base;
        }
        return $editPrefix . "\n\n" . $base;
    }

    /* ---- §9.8 _whmcs_reply_signature ----------------------------------- */

    /** sha256(json({message, attachments:[{index,filename}] sorted})). */
    public static function whmcsReplySignature(array $reply)
    {
        $attachments = [];
        foreach (self::normaliseAttachments($reply['attachments'] ?? []) as $i => $name) {
            $attachments[] = ['index' => (int) $i, 'filename' => (string) $name];
        }
        usort($attachments, function ($a, $b) {
            return [$a['index'], $a['filename']] <=> [$b['index'], $b['filename']];
        });
        $payload = [
            'message'     => self::whmcsMessageToText($reply['message'] ?? ''),
            'attachments' => $attachments,
        ];
        return hash('sha256', self::jsonSorted($payload));
    }

    /* ---- §9.12 _gd_public_message_signature ---------------------------- */

    public static function gdPublicSignature($body, array $attachments = [])
    {
        $att = [];
        foreach ($attachments as $a) {
            // NOTE: downloadUrl is a presigned S3 URL regenerated on every API
            // call — it must NOT be part of the signature or the message would
            // look "edited" on every run. fileId + name + size are stable.
            $att[] = [
                'fileId' => $a['fileId'] ?? ($a['id'] ?? ''),
                'name'   => $a['name'] ?? ($a['fileName'] ?? ''),
                'size'   => $a['size'] ?? 0,
            ];
        }
        usort($att, function ($a, $b) {
            return [$a['fileId'], $a['name']] <=> [$b['fileId'], $b['name']];
        });
        return hash('sha256', self::jsonSorted(['body' => (string) $body, 'attachments' => $att]));
    }

    /* ---- §9.11 _extract_public_body ------------------------------------ */

    /**
     * Returns the text AFTER the prefix if the message is a public command,
     * else null (=internal). Matches when: (1) text starts with prefix, or
     * (2) any line (after stripping -*>• markers) starts with prefix, or
     * (3) the prefix appears anywhere → text after its first occurrence.
     */
    public static function extractPublicBody($text, $prefix)
    {
        $text   = (string) $text;
        $prefix = (string) $prefix;
        if ($prefix === '') {
            return null;
        }
        $stripped = ltrim($text);
        if (strncmp($stripped, $prefix, strlen($prefix)) === 0) {
            return ltrim(substr($stripped, strlen($prefix)));
        }
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $l = ltrim($line, " \t-*>•");
            if (strncmp($l, $prefix, strlen($prefix)) === 0) {
                // text from this occurrence onward (single line body)
                return ltrim(substr($l, strlen($prefix)));
            }
        }
        $pos = strpos($text, $prefix);
        if ($pos !== false) {
            return ltrim(substr($text, $pos + strlen($prefix)));
        }
        return null;
    }

    /* ---- §9.14 / §9.15 mirror body + reply id -------------------------- */

    /** Everything after the first non-empty line following a "From:" line. */
    public static function extractMirrorBody($text)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $text);
        $afterFrom = false;
        $out = [];
        foreach ($lines as $line) {
            if (!$afterFrom) {
                if (preg_match('/^\s*From:/i', $line)) {
                    $afterFrom = true;
                }
                continue;
            }
            if (empty($out) && trim($line) === '') {
                continue; // skip blank lines right after From:
            }
            $out[] = $line;
        }
        return trim(implode("\n", $out));
    }

    public static function extractMirrorReplyId($text)
    {
        if (preg_match('/Reply ID:\s*(\d+)/', (string) $text, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    /* ---- §9.16 phone normalize ----------------------------------------- */

    public static function normalisePhone($phone, $countryPrefix = '+357')
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }
        // keep a leading +, convert 00 -> +, else prepend the country prefix
        $digits = preg_replace('/[^\d+]/', '', $phone);
        if (strpos($digits, '+') === 0) {
            return $digits;
        }
        if (strpos($digits, '00') === 0) {
            return '+' . substr($digits, 2);
        }
        $digits = ltrim($digits, '0');
        return rtrim($countryPrefix, '+') === '' ? $digits : ($countryPrefix . $digits);
    }

    /* ---- §9.17 _whmcs_message_to_text (HTML → plain) ------------------- */

    public static function whmcsMessageToText($html)
    {
        $s = (string) $html;
        if ($s === '') {
            return '';
        }
        // <br> and block tags -> newlines
        $s = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $s);
        $s = preg_replace('/<\/\s*(p|div|tr|li|h[1-6])\s*>/i', "\n", $s);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // collapse 3+ blank lines to 2
        $s = preg_replace("/\n{3,}/", "\n\n", $s);
        return trim($s);
    }

    /* ---- §9.10 _gd_message_text ---------------------------------------- */

    /** GoodDay message text: 'message' field, else gather text nodes of RTF. */
    public static function gdMessageText(array $m)
    {
        if (isset($m['message']) && is_string($m['message']) && $m['message'] !== '') {
            return $m['message'];
        }
        $rtf = $m['messageRTF'] ?? null;
        if (is_string($rtf)) {
            $rtf = json_decode($rtf, true);
        }
        if (is_array($rtf)) {
            $texts = [];
            self::collectRtfText($rtf, $texts);
            return trim(implode('', $texts));
        }
        return '';
    }

    private static function collectRtfText($node, array &$out)
    {
        if (is_array($node)) {
            if (isset($node['type']) && $node['type'] === 'text' && isset($node['text'])) {
                $out[] = $node['text'];
            }
            foreach ($node as $v) {
                if (is_array($v)) {
                    self::collectRtfText($v, $out);
                }
            }
        }
    }

    /* ---- §9.9 soft-deleted message detection --------------------------- */

    public static function isSoftDeleted(array $m, $emptyHeuristic = true)
    {
        foreach (['isDeleted', 'deleted', 'isTrashed', 'trashed'] as $f) {
            if (!empty($m[$f])) {
                return true;
            }
        }
        foreach (['deletedAt', 'deleted_at', 'trashedAt'] as $f) {
            if (!empty($m[$f])) {
                return true;
            }
        }
        $st = strtolower((string) ($m['status'] ?? $m['state'] ?? ''));
        if (in_array($st, ['deleted', 'trashed', 'removed'], true)) {
            return true;
        }
        if ($emptyHeuristic) {
            $hasText = trim(self::gdMessageText($m)) !== '';
            $hasAtt  = !empty($m['attachments']) || !empty($m['files']);
            if (!$hasText && !$hasAtt) {
                return true;
            }
        }
        return false;
    }

    /* ---- §9.1 lexical RTF ---------------------------------------------- */

    public static function lexicalRtf($message)
    {
        return [
            'v' => 'lexical-1',
            'content' => [
                'root' => [
                    'type' => 'root', 'version' => 1, 'format' => '', 'indent' => 0, 'direction' => 'ltr',
                    'children' => [[
                        'type' => 'custom-paragraph', 'version' => 1, 'format' => '', 'indent' => 0, 'direction' => 'ltr',
                        'children' => [[
                            'type' => 'text', 'version' => 1, 'text' => (string) $message,
                            'format' => 0, 'style' => '', 'detail' => 0, 'mode' => 'normal',
                        ]],
                    ]],
                ],
            ],
        ];
    }

    /* ---- §9.18 marker helpers ------------------------------------------ */

    public static function stripRepeatedMarkerPrefix($text, $marker)
    {
        $marker = trim((string) $marker);
        if ($marker === '') {
            return (string) $text;
        }
        $t = (string) $text;
        while (strncmp(ltrim($t), $marker, strlen($marker)) === 0) {
            $t = ltrim(substr(ltrim($t), strlen($marker)));
        }
        return $t;
    }

    public static function applySingleMarkerPrefix($text, $marker)
    {
        $clean = self::stripRepeatedMarkerPrefix($text, $marker);
        $marker = trim((string) $marker);
        return $marker === '' ? $clean : ($marker . "\n\n" . $clean);
    }

    /* ---- helpers ------------------------------------------------------- */

    /** WHMCS attachments come as CSV, JSON array, or array — normalise to list. */
    public static function normaliseAttachments($att)
    {
        if (is_array($att)) {
            return array_values($att);
        }
        $s = trim((string) $att);
        if ($s === '') {
            return [];
        }
        $j = json_decode($s, true);
        if (is_array($j)) {
            return array_values($j);
        }
        return array_map('trim', explode('|', str_replace(',', '|', $s)));
    }

    /** Canonical JSON (sorted keys) to keep signatures stable across runs. */
    public static function jsonSorted($data)
    {
        $sorter = function (&$v) use (&$sorter) {
            if (is_array($v)) {
                if (array_keys($v) !== range(0, count($v) - 1)) {
                    ksort($v);
                }
                foreach ($v as &$vv) {
                    $sorter($vv);
                }
            }
        };
        $sorter($data);
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Normalised text for restart-safe dedupe comparison. */
    public static function canonical($text)
    {
        $t = self::whmcsMessageToText($text);
        $t = preg_replace('/\s+/', ' ', $t);
        return trim(mb_strtolower($t, 'UTF-8'));
    }
}
