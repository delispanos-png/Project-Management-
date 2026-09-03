<?php
/**
 * CloudOn Project Manager — κάλυψη χρεώσιμου χρόνου.
 *
 * Κάθε χρεώσιμο λεπτό πρέπει να προέρχεται από κάπου. Δύο πηγές:
 *
 *   1. **Εγκεκριμένη προσφορά** — έχει προηγηθεί κοστολόγηση, ο πελάτης δέχτηκε,
 *      η προσφορά καλύπτει Χ ώρες για συγκεκριμένο έργο. Καταναλώνεται πρώτη,
 *      γιατί πουλήθηκε γι' αυτή τη δουλειά.
 *   2. **Προαγορά** (`mod_supportcontracts_clients.balance_minutes`) — η γενική
 *      τράπεζα του πελάτη για ό,τι προκύψει.
 *
 * Ό,τι δεν καλύπτεται μένει **ακάλυπτο**. Δεν βυθίζουμε την προαγορά σε αρνητικό:
 * το αρνητικό υπόλοιπο κρύβει το τι πρέπει να τιμολογηθεί, ενώ ο ακάλυπτος χρόνος
 * είναι λίστα — από εκεί βγαίνει η επόμενη προσφορά προς τον πελάτη.
 *
 * Μια καταχώρηση αντλεί από **μία** πηγή, αλλά μπορεί να την αντλήσει μερικώς:
 * αν μένουν 10΄ και η χρέωση είναι 30΄, παίρνει τα 10΄ και τα 20΄ μένουν
 * ακάλυπτα. Έτσι δεν μένουν ορφανά λεπτά σε καμία πηγή.
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\SupportContracts\Db as ScDb;

class Cover
{
    /** Στάδιο προσφοράς που μετράει ως κάλυψη. */
    const OFFER_OK = 'accepted';

    /* ══════════════ πηγές ══════════════ */

    /** Η εγκεκριμένη προσφορά που καλύπτει το έργο μιας εργασίας, ή null. */
    public static function projectOffer($task)
    {
        if (!$task || !$task->project_id) {
            return null;
        }
        $oid = (int) Capsule::table('mod_cpm_projects')->where('id', (int) $task->project_id)->value('offer_id');
        if (!$oid) {
            return null;
        }
        $o = Capsule::table('mod_cpm_offers')->where('id', $oid)->first();
        if (!$o || $o->stage !== self::OFFER_OK || !(int) $o->covered_minutes) {
            return null;
        }
        return $o;
    }

    /** Πόσα λεπτά της προσφορά έχουν ήδη αναλωθεί. */
    public static function offerUsed($offerId)
    {
        return (int) Capsule::table('mod_cpm_timelogs')
            ->where('cover', 'offer')->where('cover_offer_id', (int) $offerId)
            ->sum('cover_minutes');
    }

    /** Πόσα λεπτά μένουν στην προσφορά (ποτέ αρνητικό). */
    public static function offerLeft($offer)
    {
        if (!$offer) {
            return 0;
        }
        return max(0, (int) $offer->covered_minutes - self::offerUsed($offer->id));
    }

    /** Υπόλοιπο προαγοράς του πελάτη (0 αν δεν έχει συμβόλαιο). */
    public static function prepaidLeft($userid)
    {
        if (!Time::scReady()) {
            return 0;
        }
        $c = ScDb::contract($userid);
        return $c ? max(0, (int) $c->balance_minutes) : 0;
    }

    /* ══════════════ άντληση ══════════════ */

    /**
     * Αντλεί `$charge` λεπτά για την καταχώρηση `$entryId`.
     *
     * @return array{cover:string, offer:?int, covered:int}
     *         cover: 'offer' | 'prepaid' | 'none'
     */
    public static function draw($userid, $task, $charge, $entryId, $note)
    {
        $charge = (int) $charge;
        if ($charge <= 0) {
            return ['cover' => 'none', 'offer' => null, 'covered' => 0];
        }
        // 1. η προσφορά του έργου
        $offer = self::projectOffer($task);
        $left = self::offerLeft($offer);
        if ($left > 0) {
            $take = min($left, $charge);
            return ['cover' => 'offer', 'offer' => (int) $offer->id, 'covered' => $take];
        }
        // 2. η προαγορά — μόνο αν υπάρχει συμβόλαιο· η κίνηση γράφεται στο ledger
        $bal = self::prepaidLeft($userid);
        if ($bal > 0) {
            $take = min($bal, $charge);
            ScDb::applyMovement($userid, -$take, 'usage', $note,
                $task && $task->ticketid ? (int) $task->ticketid : null, null, 'cpm-time-' . (int) $entryId);
            return ['cover' => 'prepaid', 'offer' => null, 'covered' => $take];
        }
        return ['cover' => 'none', 'offer' => null, 'covered' => 0];
    }

    /** Αναίρεση άντλησης — επιστρέφει ό,τι είχε παρθεί από την προαγορά. */
    public static function undraw($entry)
    {
        if (!$entry || $entry->cover !== 'prepaid' || !(int) $entry->cover_minutes || !$entry->sc_userid) {
            return;   // η προσφορά ελευθερώνεται μόνη της: το άθροισμα ξαναϋπολογίζεται
        }
        if (!Time::scReady() || !ScDb::contract($entry->sc_userid)
            || ScDb::refExists('cpm-time-rev-' . (int) $entry->id)) {
            return;
        }
        ScDb::applyMovement((int) $entry->sc_userid, abs((int) $entry->cover_minutes), 'adjust',
            'Αναίρεση καταχώρησης χρόνου (task #' . (int) $entry->task_id . ')',
            null, null, 'cpm-time-rev-' . (int) $entry->id);
    }

    /* ══════════════ εικόνα πελάτη ══════════════ */

    /**
     * Πού βρίσκεται ο πελάτης: προαγορά, ενεργές προσφορές, ακάλυπτος χρόνος.
     */
    public static function clientState($userid)
    {
        $userid = (int) $userid;
        $c = Time::scReady() ? ScDb::contract($userid) : null;
        $offers = [];
        $offerLeft = 0;
        foreach (Capsule::table('mod_cpm_offers')->where('clientid', $userid)
            ->where('stage', self::OFFER_OK)->where('covered_minutes', '>', 0)
            ->orderBy('id', 'desc')->get() as $o) {
            $used = self::offerUsed($o->id);
            $left = max(0, (int) $o->covered_minutes - $used);
            $offerLeft += $left;
            $offers[] = ['id' => (int) $o->id, 'title' => $o->title, 'amount' => (float) $o->amount,
                'covered' => (int) $o->covered_minutes, 'used' => $used, 'left' => $left,
                'project' => Capsule::table('mod_cpm_projects')->where('offer_id', $o->id)->value('name')];
        }
        return [
            'pending'    => self::pendingTotal($userid),
            'contract'   => $c ? true : false,
            'enabled'    => $c ? (bool) $c->enabled : false,
            'balance'    => $c ? (int) $c->balance_minutes : 0,
            'offerLeft'  => $offerLeft,
            'offers'     => $offers,
            'uncovered'  => self::uncoveredTotal($userid),
            'label'      => $c ? $c->label : null,
            'reportFreq' => $c ? ($c->report_freq ?: 'monthly') : 'monthly',
            'reportMail' => $c ? $c->report_email : null,
        ];
    }

    /**
     * Χρόνος που έχει μπει σε προσφορά η οποία **δεν έχει γίνει ακόμη δεκτή**.
     * Δεν είναι ακάλυπτος (υπάρχει πρόταση) αλλά ούτε εξασφαλισμένος — αν η
     * προσφορά χαθεί, ο χρόνος ξαναγίνεται ακάλυπτος.
     */
    public static function pendingTotal($userid)
    {
        return (int) Capsule::table('mod_cpm_timelogs as tl')
            ->join('mod_cpm_offers as o', 'o.id', '=', 'tl.cover_offer_id')
            ->where('tl.sc_userid', (int) $userid)
            ->whereIn('o.stage', ['draft', 'sent'])
            ->sum('tl.cover_minutes');
    }

    /** Σύνολο ακάλυπτων λεπτών που δεν έχουν ακόμη μπει σε προσφορά. */
    public static function uncoveredTotal($userid)
    {
        return (int) Capsule::table('mod_cpm_timelogs')
            ->where('sc_userid', (int) $userid)->where('billable', 1)
            ->whereRaw('COALESCE(charged_minutes,0) > COALESCE(cover_minutes,0)')
            ->sum(Capsule::raw('COALESCE(charged_minutes,0) - COALESCE(cover_minutes,0)'));
    }

    /** Οι ακάλυπτες καταχωρήσεις ενός πελάτη, με έργο και εργασία. */
    public static function uncoveredRows($userid, $limit = 300)
    {
        $rows = Capsule::table('mod_cpm_timelogs as tl')
            ->leftJoin('mod_cpm_tasks as t', 't.id', '=', 'tl.task_id')
            ->leftJoin('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
            ->where('tl.sc_userid', (int) $userid)->where('tl.billable', 1)
            ->whereRaw('COALESCE(tl.charged_minutes,0) > COALESCE(tl.cover_minutes,0)')
            ->orderBy('tl.created_at', 'desc')->limit($limit)
            ->get(['tl.id', 'tl.task_id', 'tl.minutes', 'tl.charged_minutes', 'tl.cover_minutes',
                'tl.cover', 'tl.note', 'tl.admin_id', 'tl.created_at',
                't.title as task_title', 't.ticketid', 'p.name as project_name', 'p.id as project_id']);
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int) $r->id, 'task' => (int) $r->task_id, 'title' => $r->task_title,
                'project' => $r->project_name, 'projectId' => (int) $r->project_id,
                'ticket' => (int) $r->ticketid, 'at' => $r->created_at,
                'worked' => (int) $r->minutes, 'charged' => (int) $r->charged_minutes,
                'covered' => (int) $r->cover_minutes,
                'open' => (int) $r->charged_minutes - (int) $r->cover_minutes,
                'by' => Db::adminName($r->admin_id), 'note' => $r->note];
        }
        return $out;
    }

    /* ══════════════ ανάλυση περιόδου (report) ══════════════ */

    /**
     * Ο χρόνος μιας περιόδου, ομαδοποιημένος ανά έργο/υπηρεσία, με τις
     * επιμέρους εγγραφές από κάτω. Αυτό διαβάζει ο πελάτης.
     *
     * @return array{groups:array, totals:array}
     */
    public static function breakdown($userid, $from, $to)
    {
        $rows = Capsule::table('mod_cpm_timelogs as tl')
            ->leftJoin('mod_cpm_tasks as t', 't.id', '=', 'tl.task_id')
            ->leftJoin('mod_cpm_projects as p', 'p.id', '=', 't.project_id')
            ->where('tl.sc_userid', (int) $userid)
            ->where('tl.created_at', '>=', $from)->where('tl.created_at', '<', $to)
            ->where('tl.running', 0)
            ->orderBy('tl.created_at')
            ->get(['tl.id', 'tl.minutes', 'tl.charged_minutes', 'tl.cover_minutes', 'tl.cover',
                'tl.cover_offer_id', 'tl.billable', 'tl.note', 'tl.created_at',
                't.title as task_title', 't.ticketid', 'p.name as project_name']);

        $groups = [];
        $tot = ['worked' => 0, 'charged' => 0, 'prepaid' => 0, 'offer' => 0, 'open' => 0, 'free' => 0];
        foreach ($rows as $r) {
            $key = $r->project_name ?: ($r->ticketid ? 'Αιτήματα υποστήριξης' : 'Μεμονωμένες εργασίες');
            if (!isset($groups[$key])) {
                $groups[$key] = ['name' => $key, 'worked' => 0, 'charged' => 0, 'prepaid' => 0,
                    'offer' => 0, 'open' => 0, 'free' => 0, 'items' => []];
            }
            $g = &$groups[$key];
            $worked = (int) $r->minutes;
            $charged = (int) $r->charged_minutes;
            $covered = (int) $r->cover_minutes;
            $open = max(0, $charged - $covered);
            $tick = null;
            if ($r->ticketid) {
                $tk = Capsule::table('tbltickets')->where('id', (int) $r->ticketid)->first(['tid', 'title']);
                if ($tk) {
                    $tick = '#' . (int) $tk->tid . ' ' . $tk->title;
                }
            }
            $g['worked'] += $worked;
            $tot['worked'] += $worked;
            if ((int) $r->billable) {
                $g['charged'] += $charged;
                $tot['charged'] += $charged;
                if ($r->cover === 'prepaid') { $g['prepaid'] += $covered; $tot['prepaid'] += $covered; }
                if ($r->cover === 'offer')   { $g['offer'] += $covered;   $tot['offer'] += $covered; }
                $g['open'] += $open;
                $tot['open'] += $open;
            } else {
                $g['free'] += $worked;
                $tot['free'] += $worked;
            }
            $g['items'][] = ['at' => $r->created_at, 'what' => $tick ?: ($r->task_title ?: 'Εργασία'),
                'note' => $r->note, 'worked' => $worked, 'charged' => (int) $r->billable ? $charged : 0,
                'billable' => (bool) $r->billable, 'cover' => $r->cover, 'open' => $open];
            unset($g);
        }
        // Οι ομάδες με τον περισσότερο χρεώσιμο χρόνο πρώτες — εκεί πάει η προσοχή.
        uasort($groups, function ($a, $b) { return $b['charged'] <=> $a['charged']; });
        return ['groups' => array_values($groups), 'totals' => $tot];
    }

    /** Οι πιστώσεις (αγορές/διορθώσεις) της περιόδου. */
    public static function topups($userid, $from, $to)
    {
        if (!Capsule::schema()->hasTable('mod_supportcontracts_ledger')) {
            return [];
        }
        $out = [];
        foreach (Capsule::table('mod_supportcontracts_ledger')
            ->where('userid', (int) $userid)->where('type', '<>', 'usage')
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->orderBy('id')->get() as $r) {
            $out[] = ['at' => $r->created_at, 'type' => $r->type, 'minutes' => (int) $r->minutes,
                'note' => $r->note, 'after' => (int) $r->balance_after];
        }
        return $out;
    }

    /** «2ω 30΄» */
    public static function fmt($mins)
    {
        $mins = (int) $mins;
        $sign = $mins < 0 ? '-' : '';
        $mins = abs($mins);
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        if ($h && $m) { return $sign . $h . 'ω ' . $m . '΄'; }
        if ($h) { return $sign . $h . 'ω'; }
        return $sign . $m . '΄';
    }
}
