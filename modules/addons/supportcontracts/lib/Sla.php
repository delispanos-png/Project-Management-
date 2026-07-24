<?php
/**
 * Support Contracts & SLA — business-hours SLA clock.
 *
 * Computes the first-response deadline for a ticket by advancing the ticket's
 * open time by the agreed response window, counting ONLY minutes that fall
 * inside the client's business days/hours (in the client's timezone). Time
 * outside business hours is "frozen" and does not count against the SLA.
 *
 * @package WHMCS\Module\Addon\SupportContracts
 */

namespace WHMCS\Module\Addon\SupportContracts;

class Sla
{
    /**
     * @param object $contract  a mod_supportcontracts_clients row
     * @return int  the agreed response window expressed in BUSINESS minutes
     */
    public static function responseBusinessMinutes($contract)
    {
        $perDay = self::minutesPerBusinessDay($contract->biz_start, $contract->biz_end);
        $val = max(0, (int) $contract->sla_response_value);
        if ($contract->sla_response_unit === 'days') {
            return $val * $perDay;
        }
        return $val * 60; // hours
    }

    /** Business minutes available in one working day (biz_end - biz_start). */
    public static function minutesPerBusinessDay($start, $end)
    {
        [$sh, $sm] = self::hm($start);
        [$eh, $em] = self::hm($end);
        return max(0, ($eh * 60 + $em) - ($sh * 60 + $sm));
    }

    /**
     * Deadline for first response.
     *
     * @param string $openedAt  ticket open datetime ('Y-m-d H:i:s', server/WHMCS tz assumed = client tz for simplicity unless a tz is set)
     * @param object $contract
     * @return \DateTime|null  the SLA-due moment (in the contract timezone), or null if misconfigured
     */
    public static function dueDate($openedAt, $contract)
    {
        $minutes = self::responseBusinessMinutes($contract);
        if ($minutes <= 0) {
            return null;
        }
        $days = self::days($contract->biz_days);
        if (!$days) {
            return null;
        }
        try {
            $tz = new \DateTimeZone($contract->biz_tz ?: 'Europe/Athens');
            $cursor = new \DateTime($openedAt, $tz);
        } catch (\Throwable $e) {
            return null;
        }

        [$sh, $sm] = self::hm($contract->biz_start);
        [$eh, $em] = self::hm($contract->biz_end);

        $remaining = $minutes;
        $guard = 0;
        while ($remaining > 0 && $guard++ < 5000) {
            $dow = (int) $cursor->format('N'); // 1=Mon..7=Sun
            $dayStart = (clone $cursor)->setTime($sh, $sm, 0);
            $dayEnd   = (clone $cursor)->setTime($eh, $em, 0);

            // Not a business day, or past today's window → jump to next day's open.
            if (!in_array($dow, $days, true) || $cursor >= $dayEnd) {
                $cursor->modify('+1 day')->setTime($sh, $sm, 0);
                continue;
            }
            // Before today's window → move to open.
            if ($cursor < $dayStart) {
                $cursor = $dayStart;
            }
            $available = (int) round(($dayEnd->getTimestamp() - $cursor->getTimestamp()) / 60);
            if ($remaining <= $available) {
                $cursor->modify('+' . $remaining . ' minutes');
                $remaining = 0;
                break;
            }
            $remaining -= $available;
            $cursor = $dayEnd; // consumed to end of window; loop advances to next day
        }

        return $remaining === 0 ? $cursor : null;
    }

    /** Is the given moment (now by default) still within the SLA window? */
    public static function humanResponse($contract)
    {
        $val = (int) $contract->sla_response_value;
        $unit = $contract->sla_response_unit === 'days' ? 'ημέρες' : 'ώρες';
        return $val . ' ' . $unit;
    }

    /** Human business-hours line, e.g. "Δευτ–Παρ 09:00–17:00". */
    public static function humanHours($contract)
    {
        $names = [1 => 'Δευ', 2 => 'Τρι', 3 => 'Τετ', 4 => 'Πεμ', 5 => 'Παρ', 6 => 'Σαβ', 7 => 'Κυρ'];
        $days = self::days($contract->biz_days);
        $label = '';
        if ($days) {
            // contiguous range → "Δευ–Παρ", else comma list
            $isRange = ($days === range($days[0], $days[count($days) - 1]));
            $label = $isRange && count($days) > 1
                ? $names[$days[0]] . '–' . $names[end($days)]
                : implode(',', array_map(fn($d) => $names[$d], $days));
        }
        return trim($label . ' ' . $contract->biz_start . '–' . $contract->biz_end);
    }

    /* -------- helpers -------- */

    private static function hm($t)
    {
        $p = explode(':', (string) $t);
        return [(int) ($p[0] ?? 0), (int) ($p[1] ?? 0)];
    }

    private static function days($csv)
    {
        $out = [];
        foreach (explode(',', (string) $csv) as $d) {
            $d = (int) trim($d);
            if ($d >= 1 && $d <= 7) {
                $out[] = $d;
            }
        }
        sort($out);
        return array_values(array_unique($out));
    }
}
