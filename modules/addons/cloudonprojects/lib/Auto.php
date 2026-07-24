<?php
/**
 * CloudOn Projects — Automations engine («όταν Χ τότε Ψ»).
 *
 * Triggers:
 *   task_status   — task μπήκε σε status (tvalue = status id)
 *   ticket_status — ticket άλλαξε κατάσταση (tvalue = όνομα status)
 *   lead_stage    — lead μπήκε σε στάδιο (tvalue = stage key)
 *   sla_breach    — πέρασε η SLA προθεσμία χωρίς απάντηση (από pulse cron)
 *
 * Actions:
 *   assign_task   — ανάθεση task σε admin (avalue = admin id)
 *   ball          — «η μπάλα» σε admin (avalue = admin id)
 *   set_prio      — προτεραιότητα task (avalue = 0|1|2)
 *   notify        — καμπανάκι+email σε admin (avalue = admin id, -1 = διαχειριστές)
 *   assign_ticket — ανάθεση ticket (avalue = admin id)
 *   escalate      — ticket priority → High
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class Auto
{
    public static function all($onlyActive = false)
    {
        try {
            $q = Capsule::table('mod_cpm_automations')->orderBy('id');
            if ($onlyActive) {
                $q->where('active', 1);
            }
            return $q->get();
        } catch (\Throwable $e) {
            return collect([]);
        }
    }

    /** Dedupe ανά (automation, ref) — π.χ. μία φορά ανά SLA breach. */
    protected static function once($autoId, $ref)
    {
        $ex = Capsule::table('mod_cpm_auto_log')
            ->where('auto_id', (int) $autoId)->where('ref', $ref)->exists();
        if ($ex) {
            return false;
        }
        Capsule::table('mod_cpm_auto_log')->insert([
            'auto_id' => (int) $autoId, 'ref' => mb_substr($ref, 0, 64), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    /**
     * Εκτέλεση κανόνων για ένα γεγονός.
     * $ctx: task_status → [taskId, statusId] · ticket_status → [ticketId, status]
     *       lead_stage → [leadId, stage] · sla_breach → [ticketId]
     */
    public static function run($event, array $ctx)
    {
        try {
            foreach (self::all(true) as $a) {
                if ($a->trigger !== $event) {
                    continue;
                }
                $match = true;
                if ($event === 'task_status') {
                    $match = (string) $a->tvalue === (string) $ctx['statusId'];
                } elseif ($event === 'ticket_status') {
                    $match = mb_strtolower((string) $a->tvalue) === mb_strtolower((string) $ctx['status']);
                } elseif ($event === 'lead_stage') {
                    $match = (string) $a->tvalue === (string) $ctx['stage'];
                }
                if (!$match) {
                    continue;
                }
                $ref = $event . ':' . ($ctx['taskId'] ?? $ctx['ticketId'] ?? $ctx['leadId'] ?? 0)
                    . ':' . ($ctx['statusId'] ?? $ctx['status'] ?? $ctx['stage'] ?? '');
                if ($event === 'sla_breach' && !self::once($a->id, 'sla:' . $ctx['ticketId'])) {
                    continue; // SLA: αυστηρά μία φορά ανά ticket
                }
                self::act($a, $event, $ctx);
                if (function_exists('logActivity')) {
                    logActivity('CPM Automation «' . $a->name . '» εκτελέστηκε (' . $ref . ')');
                }
            }
        } catch (\Throwable $e) {
            // ποτέ δεν ρίχνουμε τη ροή για automation
        }
    }

    protected static function act($a, $event, array $ctx)
    {
        $taskId = (int) ($ctx['taskId'] ?? 0);
        $ticketId = (int) ($ctx['ticketId'] ?? 0);
        switch ($a->action) {
            case 'assign_task':
                if ($taskId) {
                    Db::saveTask($taskId, ['assignee' => (int) $a->avalue ?: null], null);
                    Notify::assigned($taskId, (int) $a->avalue, 0);
                }
                break;
            case 'ball':
                if ($taskId) {
                    Db::saveTask($taskId, ['action_user' => (int) $a->avalue ?: null], null);
                    Db::pushNotification((int) $a->avalue, 'action',
                        '⚡ (automation) Απαιτείται ενέργειά σου: ' . (Db::task($taskId)->title ?? ''),
                        'addonmodules.php?module=cloudonprojects&tab=task&id=' . $taskId);
                }
                break;
            case 'set_prio':
                if ($taskId) {
                    Db::saveTask($taskId, ['priority' => min(2, max(0, (int) $a->avalue))], null);
                }
                break;
            case 'notify':
                $title = '🤖 ' . $a->name . ': ' . self::describe($event, $ctx);
                $url = $taskId ? 'addonmodules.php?module=cloudonprojects&tab=task&id=' . $taskId
                    : ($ticketId ? 'supporttickets.php?action=view&id=' . $ticketId : null);
                $targets = ((int) $a->avalue === -1) ? Db::fullAccessAdminIds() : [(int) $a->avalue];
                foreach ($targets as $aid) {
                    if ($aid > 0) {
                        Db::pushNotification($aid, 'info', $title, $url);
                        Notify::send($aid, $a->name, '<p>' . htmlspecialchars($title) . '</p>');
                    }
                }
                break;
            case 'assign_ticket':
                if ($ticketId) {
                    Capsule::table('tbltickets')->where('id', $ticketId)->update(['flag' => (int) $a->avalue]);
                    if (function_exists('run_hook')) {
                        run_hook('TicketFlagged', ['ticketid' => $ticketId, 'adminid' => (int) $a->avalue]);
                    }
                }
                break;
            case 'escalate':
                if ($ticketId) {
                    Capsule::table('tbltickets')->where('id', $ticketId)->update(['urgency' => 'High']);
                }
                break;
        }
    }

    protected static function describe($event, array $ctx)
    {
        if (!empty($ctx['taskId'])) {
            return Db::task($ctx['taskId'])->title ?? ('task #' . $ctx['taskId']);
        }
        if (!empty($ctx['ticketId'])) {
            $tid = Capsule::table('tbltickets')->where('id', $ctx['ticketId'])->value('tid');
            return 'ticket #' . ($tid ?: $ctx['ticketId']);
        }
        return $event;
    }
}
