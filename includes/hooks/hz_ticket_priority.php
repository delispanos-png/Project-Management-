<?php
/**
 * Ticket list — triage metadata provider for the client-area support tickets
 * list. For each displayed ticket it derives the signals that answer
 * "which ticket do I start with":
 *   urgency, waitingOnUs, waitLabel, waitHours, overdue (SLA), vip, score.
 * The template renders chips from these; the score drives the default sort.
 * SELECT-only, upgrade-safe hook. Scoped to the tids already on the page.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ClientAreaPageSupportTickets', 1, function ($vars) {
    // Collect the ticket display-ids (tid) already authorised for display.
    $tids = [];
    if (!empty($vars['tickets']) && is_array($vars['tickets'])) {
        foreach ($vars['tickets'] as $t) {
            if (isset($t['tid'])) {
                $tids[] = $t['tid'];
            }
        }
    }

    try {
        $q = Capsule::table('tbltickets');
        if ($tids) {
            $q->whereIn('tid', $tids);
        } else {
            $uid = (int) ($_SESSION['uid'] ?? 0);
            if ($uid <= 0) {
                return [];
            }
            $q->where('userid', $uid);
        }
        $rows = $q->get(['tid', 'urgency', 'status', 'lastreply', 'date', 'userid']);
    } catch (\Throwable $e) {
        return [];
    }

    // VIP flag = client has several active services. One grouped query.
    $userIds = [];
    foreach ($rows as $r) {
        if ($r->userid) {
            $userIds[(int) $r->userid] = true;
        }
    }
    $activeByUser = [];
    if ($userIds) {
        try {
            $counts = Capsule::table('tblhosting')
                ->whereIn('userid', array_keys($userIds))
                ->where('domainstatus', 'Active')
                ->groupBy('userid')
                ->selectRaw('userid, COUNT(*) as c')
                ->pluck('c', 'userid');
            foreach ($counts as $uid => $c) {
                $activeByUser[(int) $uid] = (int) $c;
            }
        } catch (\Throwable $e) {
            // non-fatal — VIP simply won't show
        }
    }

    // Support-contract priority (from the Support Contracts & SLA module):
    // priority customers rank higher and get a badge. Optional dependency.
    $contractPrio = [];
    if ($userIds) {
        try {
            if (Capsule::schema()->hasTable('mod_supportcontracts_clients')) {
                $p = Capsule::table('mod_supportcontracts_clients')
                    ->whereIn('userid', array_keys($userIds))
                    ->where('enabled', 1)->where('priority', '>', 0)
                    ->pluck('priority', 'userid');
                foreach ($p as $uid => $pr) {
                    $contractPrio[(int) $uid] = (int) $pr;
                }
            }
        } catch (\Throwable $e) {
            // module not installed — no contract priority
        }
    }

    // SLA thresholds (hours of "waiting on us" before a ticket is overdue).
    $slaHours = ['High' => 4, 'Medium' => 24, 'Low' => 72];
    $now = time();

    $meta = [];
    foreach ($rows as $r) {
        $urgency = $r->urgency ?: 'Medium';
        $s = function_exists('mb_strtolower') ? mb_strtolower(trim((string) $r->status)) : strtolower(trim((string) $r->status));

        $isClosed = ($s === 'closed')
            || (strpos($s, 'κλειστ') !== false)
            || (strpos($s, 'ακυρ') !== false)   // Cancelled / Ακυρώθηκε
            || (strpos($s, 'cancel') !== false);

        // "Waiting on us" = a new ticket or the customer just replied.
        $waitingOnUs = in_array($s, ['open', 'customer-reply'], true)
            || (strpos($s, 'customer') !== false)
            || (strpos($s, 'πελάτ') !== false);

        $ref = strtotime((string) ($r->lastreply ?: $r->date)) ?: $now;
        $hours = max(0, ($now - $ref) / 3600);

        // Compact Greek wait label.
        if ($hours < 1) {
            $mins = (int) round($hours * 60);
            $waitLabel = $mins <= 1 ? 'μόλις τώρα' : $mins . 'λ';
        } elseif ($hours < 24) {
            $waitLabel = ((int) round($hours)) . 'ω';
        } else {
            $waitLabel = ((int) round($hours / 24)) . 'ημ';
        }

        $thr = $slaHours[$urgency] ?? 24;
        $overdue = $waitingOnUs && !$isClosed && $hours > $thr;

        $vip = isset($activeByUser[(int) $r->userid]) && $activeByUser[(int) $r->userid] >= 3;
        $cprio = $contractPrio[(int) $r->userid] ?? 0; // 0 none, 1 high, 2 critical

        // Attention score — higher = start sooner. Drives the default sort.
        if ($isClosed) {
            $score = 0;
        } else {
            $score = 10;
            if ($waitingOnUs) {
                $score += 1000;
            }
            $score += ['High' => 300, 'Medium' => 200, 'Low' => 100][$urgency] ?? 150;
            if ($overdue) {
                $score += 500;
            }
            if ($vip) {
                $score += 150;
            }
            if ($cprio > 0) {
                $score += $cprio * 400; // contract priority: high +400, critical +800
            }
            $score += (int) min($hours, 240); // older waits edge ahead within a tier
        }

        $meta[(string) $r->tid] = [
            'urgency'     => $urgency,
            'waitingOnUs' => $waitingOnUs,
            'waitLabel'   => $waitLabel,
            'overdue'     => $overdue,
            'closed'      => $isClosed,
            'vip'         => $vip,
            'prio'        => $cprio,
            'score'       => $score,
        ];
    }

    return ['hzTicketMeta' => $meta];
});
