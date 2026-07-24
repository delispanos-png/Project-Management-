<?php
/**
 * Schema + persistence layer for the GoodDay Sync addon.
 *
 * Replaces the Python service's state.json (see spec §7) with transactional
 * WHMCS DB tables. All ticket/reply/message mappings live here.
 *
 * @package WHMCS\Module\Addon\GoodDaySync
 */

namespace WHMCS\Module\Addon\GoodDaySync;

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class Db
{
    const T_TICKETS    = 'mod_gooddaysync_tickets';
    const T_REPLIES    = 'mod_gooddaysync_replies';
    const T_TOMBSTONES = 'mod_gooddaysync_tombstones';
    const T_LOG        = 'mod_gooddaysync_log';

    /* ------------------------------------------------------------------ */
    /* Schema                                                             */
    /* ------------------------------------------------------------------ */

    public static function install()
    {
        if (!Capsule::schema()->hasTable(self::T_TICKETS)) {
            Capsule::schema()->create(self::T_TICKETS, function ($t) {
                $t->increments('id');
                $t->integer('ticketid')->unique();           // internal WHMCS ticket id (primary key everywhere)
                $t->string('tid', 32)->nullable();           // public masked ticket number
                $t->string('task_id', 64)->nullable();       // GoodDay task id
                $t->string('project_id', 64)->nullable();
                $t->boolean('created')->default(false);
                $t->boolean('baseline_done')->default(false);
                $t->boolean('deleted_in_whmcs')->default(false);
                $t->integer('last_reply_id')->default(0);
                $t->integer('gd_task_missing_hits')->default(0);
                $t->string('last_status_synced', 64)->nullable();            // WHMCS->GD dedupe
                $t->string('last_gd_status_synced', 64)->nullable();         // GD->WHMCS dedupe
                $t->string('last_whmcs_status_set_from_gd', 64)->nullable();
                $t->double('gd_to_whmcs_backoff_until')->default(0);         // epoch seconds
                $t->timestamp('last_scanned')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->index('task_id');
                $t->index('deleted_in_whmcs');
            });
        }

        if (!Capsule::schema()->hasTable(self::T_REPLIES)) {
            Capsule::schema()->create(self::T_REPLIES, function ($t) {
                $t->increments('id');
                $t->integer('ticketid')->index();
                $t->integer('whmcs_reply_id')->default(0);   // 0 = pseudo/initial (kept for signatures)
                $t->string('gd_message_id', 64)->nullable();
                $t->string('signature', 80)->nullable();     // sha256 hex of reply content
                // origin: whmcs = created in WHMCS; goodday_public = came from a GoodDay !public message;
                //         mirror = GoodDay-side edit of a mirrored WHMCS comment.
                $t->string('origin', 20)->default('whmcs');
                $t->string('kind', 20)->default('reply');    // reply|public|mirror|pending
                $t->text('extra')->nullable();               // json: pending canonical text, gd signatures, etc.
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->index(['ticketid', 'whmcs_reply_id']);
                $t->index(['ticketid', 'gd_message_id']);
                $t->index('origin');
            });
        }

        if (!Capsule::schema()->hasTable(self::T_TOMBSTONES)) {
            Capsule::schema()->create(self::T_TOMBSTONES, function ($t) {
                $t->increments('id');
                $t->string('tkey', 64)->unique();            // "id:<ticketid>" | "tid:<tid>"
                $t->integer('ticketid')->nullable();
                $t->string('tid', 32)->nullable();
                $t->string('task_id', 64)->nullable();
                $t->string('source', 32)->nullable();
                $t->double('expires_at')->default(0);        // epoch seconds
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable(self::T_LOG)) {
            Capsule::schema()->create(self::T_LOG, function ($t) {
                $t->increments('id');
                $t->timestamp('ts')->nullable();
                $t->string('level', 12)->default('info');
                $t->integer('ticketid')->nullable()->index();
                $t->string('event', 48)->nullable();
                $t->text('detail')->nullable();
            });
        }
    }

    public static function uninstall()
    {
        // Intentionally keep tables on deactivate to avoid losing mappings.
        // Drop manually only.
    }

    /* ------------------------------------------------------------------ */
    /* Logging (never throws)                                             */
    /* ------------------------------------------------------------------ */

    public static function log($event, $detail = '', $level = 'info', $ticketid = null)
    {
        try {
            Capsule::table(self::T_LOG)->insert([
                'ts'       => date('Y-m-d H:i:s'),
                'level'    => $level,
                'ticketid' => $ticketid !== null ? (int) $ticketid : null,
                'event'    => substr((string) $event, 0, 48),
                'detail'   => is_string($detail) ? $detail : json_encode($detail),
            ]);
        } catch (\Throwable $e) {
            // logging must never break the caller
        }
    }

    public static function recentLogs($limit = 60, $ticketid = null)
    {
        try {
            $q = Capsule::table(self::T_LOG)->orderBy('id', 'desc')->limit($limit);
            if ($ticketid !== null) {
                $q->where('ticketid', (int) $ticketid);
            }
            return $q->get();
        } catch (\Throwable $e) {
            return collect([]);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Ticket mapping                                                     */
    /* ------------------------------------------------------------------ */

    public static function ticket($ticketid)
    {
        return Capsule::table(self::T_TICKETS)->where('ticketid', (int) $ticketid)->first();
    }

    public static function ticketByTask($taskId)
    {
        return Capsule::table(self::T_TICKETS)->where('task_id', (string) $taskId)->first();
    }

    public static function upsertTicket($ticketid, array $data)
    {
        $ticketid = (int) $ticketid;
        $data['updated_at'] = date('Y-m-d H:i:s');
        if (Capsule::table(self::T_TICKETS)->where('ticketid', $ticketid)->exists()) {
            Capsule::table(self::T_TICKETS)->where('ticketid', $ticketid)->update($data);
        } else {
            $data['ticketid']   = $ticketid;
            $data['created_at'] = date('Y-m-d H:i:s');
            Capsule::table(self::T_TICKETS)->insert($data);
        }
    }

    /** All mapped, non-deleted tickets (optionally due for a scan). */
    public static function activeTickets($limit = 500)
    {
        return Capsule::table(self::T_TICKETS)
            ->where('created', 1)
            ->where('deleted_in_whmcs', 0)
            ->orderBy('last_scanned', 'asc')
            ->limit($limit)
            ->get();
    }

    public static function countTickets()
    {
        return (int) Capsule::table(self::T_TICKETS)->count();
    }

    /* ------------------------------------------------------------------ */
    /* Reply / message mapping                                            */
    /* ------------------------------------------------------------------ */

    public static function replies($ticketid)
    {
        return Capsule::table(self::T_REPLIES)->where('ticketid', (int) $ticketid)->get();
    }

    public static function replyByWhmcsId($ticketid, $rid)
    {
        return Capsule::table(self::T_REPLIES)
            ->where('ticketid', (int) $ticketid)
            ->where('whmcs_reply_id', (int) $rid)
            ->first();
    }

    public static function replyByGdMessage($ticketid, $mid, $kind = null)
    {
        $q = Capsule::table(self::T_REPLIES)
            ->where('ticketid', (int) $ticketid)
            ->where('gd_message_id', (string) $mid);
        if ($kind !== null) {
            $q->where('kind', $kind);
        }
        return $q->first();
    }

    public static function saveReply($ticketid, array $data)
    {
        $ticketid = (int) $ticketid;
        $data['ticketid']   = $ticketid;
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Uniqueness key: (ticketid, whmcs_reply_id, kind) OR (ticketid, gd_message_id, kind)
        $existing = null;
        if (!empty($data['whmcs_reply_id'])) {
            $existing = Capsule::table(self::T_REPLIES)
                ->where('ticketid', $ticketid)
                ->where('whmcs_reply_id', (int) $data['whmcs_reply_id'])
                ->where('kind', $data['kind'] ?? 'reply')
                ->first();
        }
        if (!$existing && !empty($data['gd_message_id'])) {
            // include kind: the same GoodDay message id can appear both as a
            // whmcs-reply mapping and as a mirror signature (different aspects).
            $existing = Capsule::table(self::T_REPLIES)
                ->where('ticketid', $ticketid)
                ->where('gd_message_id', (string) $data['gd_message_id'])
                ->where('kind', $data['kind'] ?? 'reply')
                ->first();
        }

        if ($existing) {
            Capsule::table(self::T_REPLIES)->where('id', $existing->id)->update($data);
            return $existing->id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        return Capsule::table(self::T_REPLIES)->insertGetId($data);
    }

    public static function deleteReplyRow($id)
    {
        Capsule::table(self::T_REPLIES)->where('id', (int) $id)->delete();
    }

    /* ------------------------------------------------------------------ */
    /* Tombstones (delete-recreate guard, spec §8)                        */
    /* ------------------------------------------------------------------ */

    public static function setTombstone($tkey, array $data, $ttlSeconds)
    {
        $data['tkey']       = $tkey;
        $data['expires_at'] = time() + (int) $ttlSeconds;
        $data['created_at'] = date('Y-m-d H:i:s');
        if (Capsule::table(self::T_TOMBSTONES)->where('tkey', $tkey)->exists()) {
            Capsule::table(self::T_TOMBSTONES)->where('tkey', $tkey)->update($data);
        } else {
            Capsule::table(self::T_TOMBSTONES)->insert($data);
        }
    }

    public static function tombstoneActive($tkey)
    {
        $row = Capsule::table(self::T_TOMBSTONES)->where('tkey', $tkey)->first();
        return $row && (float) $row->expires_at > time();
    }

    public static function cleanupTombstones()
    {
        try {
            Capsule::table(self::T_TOMBSTONES)->where('expires_at', '<', time())->delete();
        } catch (\Throwable $e) {
        }
    }

    /* ------------------------------------------------------------------ */
    /* state.json migration (spec §7)  — one-time import                  */
    /* ------------------------------------------------------------------ */

    /**
     * Import a Python-era state.json structure into the DB tables.
     * Returns ['tickets'=>n, 'replies'=>n, 'tombstones'=>n]. Idempotent:
     * re-running updates existing rows rather than duplicating.
     */
    public static function importState(array $state)
    {
        $counts = ['tickets' => 0, 'replies' => 0, 'tombstones' => 0];

        foreach (($state['tickets'] ?? []) as $ticketid => $e) {
            $ticketid = (int) $ticketid;
            self::upsertTicket($ticketid, [
                'tid'                          => isset($e['whmcs_tid']) ? (string) $e['whmcs_tid'] : null,
                'task_id'                      => $e['task_id'] ?? null,
                'project_id'                   => $e['project_id'] ?? null,
                'created'                      => !empty($e['created']),
                'baseline_done'                => !empty($e['baseline_done']),
                'deleted_in_whmcs'             => !empty($e['deleted_in_whmcs']),
                'last_reply_id'                => (int) ($e['last_reply_id'] ?? 0),
                'gd_task_missing_hits'         => (int) ($e['gd_task_missing_hits'] ?? 0),
                'last_status_synced'           => $e['last_status_synced'] ?? null,
                'last_gd_status_synced'        => $e['last_gd_status_synced'] ?? null,
                'last_whmcs_status_set_from_gd' => $e['last_whmcs_status_set_from_gd'] ?? null,
                'gd_to_whmcs_backoff_until'    => (float) ($e['gd_to_whmcs_backoff_until'] ?? 0),
            ]);
            $counts['tickets']++;

            // reply signatures (WHMCS reply id -> sha256)
            foreach (($e['reply_signatures'] ?? []) as $rid => $sig) {
                $mid = $e['whmcs_reply_to_gd_message_ids'][(string) $rid] ?? null;
                self::saveReply($ticketid, [
                    'whmcs_reply_id' => (int) $rid,
                    'gd_message_id'  => $mid,
                    'signature'      => $sig,
                    'origin'         => 'whmcs',
                    'kind'           => 'reply',
                ]);
                $counts['replies']++;
            }
            // GoodDay !public -> WHMCS reply mappings
            foreach (($e['gd_public_to_whmcs_reply_ids'] ?? []) as $mid => $rid) {
                self::saveReply($ticketid, [
                    'whmcs_reply_id' => (int) $rid,
                    'gd_message_id'  => (string) $mid,
                    'signature'      => $e['gd_public_message_signatures'][(string) $mid] ?? null,
                    'origin'         => 'goodday_public',
                    'kind'           => 'public',
                ]);
                $counts['replies']++;
            }
            // mirror signatures (GoodDay-side edits of mirrored WHMCS comments)
            foreach (($e['gd_mirror_message_signatures'] ?? []) as $mid => $sig) {
                $rid = $e['gd_message_to_whmcs_reply_ids'][(string) $mid] ?? 0;
                self::saveReply($ticketid, [
                    'whmcs_reply_id' => (int) $rid,
                    'gd_message_id'  => (string) $mid,
                    'signature'      => $sig,
                    'origin'         => 'mirror',
                    'kind'           => 'mirror',
                ]);
                $counts['replies']++;
            }
            // pending public reply signatures (recovery)
            foreach (($e['gd_pending_public_reply_signatures'] ?? []) as $mid => $canonical) {
                self::saveReply($ticketid, [
                    'whmcs_reply_id' => 0,
                    'gd_message_id'  => (string) $mid,
                    'origin'         => 'goodday_public',
                    'kind'           => 'pending',
                    'extra'          => json_encode(['canonical' => $canonical]),
                ]);
                $counts['replies']++;
            }
        }

        foreach (($state['deleted_ticket_tombstones'] ?? []) as $tkey => $tv) {
            self::setTombstone(
                (string) $tkey,
                [
                    'ticketid' => isset($tv['ticket_id']) ? (int) $tv['ticket_id'] : null,
                    'tid'      => isset($tv['whmcs_tid']) ? (string) $tv['whmcs_tid'] : null,
                    'task_id'  => $tv['task_id'] ?? null,
                    'source'   => $tv['source'] ?? 'import',
                ],
                86400 * 7 // give imported tombstones a generous window
            );
            $counts['tombstones']++;
        }

        return $counts;
    }
}
