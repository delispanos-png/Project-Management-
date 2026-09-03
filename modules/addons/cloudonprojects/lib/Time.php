<?php
/**
 * CloudOn Project Manager — γέφυρα χρόνου εργασίας → Support Contracts (Φ2.2).
 *
 * Κάθε καταχώρηση χρόνου σε task περνάει στο mod_supportcontracts_worklog
 * (χρεώσιμη ΚΑΙ μη — και οι δύο μπαίνουν στο εβδομαδιαίο report), και αν είναι
 * χρεώσιμη αφαιρεί την προαγορά με το ίδιο βήμα χρέωσης του supportcontracts
 * (charge_step_minutes / min_charge_hours).
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\SupportContracts\Db as ScDb;

require_once __DIR__ . '/Cover.php';

class Time
{
    /** Το supportcontracts είναι εγκατεστημένο και φορτώσιμο; */
    public static function scReady()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $lib = dirname(__DIR__, 2) . '/supportcontracts/lib/Db.php';
            if (!class_exists(ScDb::class) && is_file($lib)) {
                require_once $lib;
            }
            $ready = class_exists(ScDb::class)
                && Capsule::schema()->hasTable('mod_supportcontracts_worklog');
        } catch (\Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    /** Πελάτης που αφορά το task: project.clientid, αλλιώς userid του linked ticket. */
    public static function clientForTask($task)
    {
        if (!$task) {
            return 0;
        }
        $p = Db::project($task->project_id);
        if ($p && $p->clientid) {
            return (int) $p->clientid;
        }
        if ($task->ticketid) {
            return (int) Capsule::table('tbltickets')->where('id', (int) $task->ticketid)->value('userid');
        }
        return 0;
    }

    /** Βήμα/ελάχιστο χρέωσης από τη ρύθμιση του supportcontracts. */
    public static function chargeFor($minutes, $billable)
    {
        if (!$billable || $minutes <= 0) {
            return 0;
        }
        $step = max(1, (int) (Capsule::table('tbladdonmodules')->where('module', 'supportcontracts')
            ->where('setting', 'charge_step_minutes')->value('value') ?: 15));
        $minCharge = (int) round(((float) (Capsule::table('tbladdonmodules')->where('module', 'supportcontracts')
            ->where('setting', 'min_charge_hours')->value('value') ?: 0)) * 60);
        return max($minCharge, (int) ceil($minutes / $step) * $step);
    }

    /**
     * Push μιας κλεισμένης καταχώρησης χρόνου στο supportcontracts.
     * Idempotent μέσω sc_worklog_id (δεύτερο push = no-op).
     */
    /**
     * @param int $clientHint Πελάτης που ξέρει ο καλών, όταν η εργασία δεν τον
     *   φανερώνει μόνη της. Μια εργασία που γεννήθηκε από **τηλεφώνημα** δεν
     *   έχει ούτε έργο ούτε ticket — χωρίς αυτό ο χρεώσιμος χρόνος έμενε
     *   αχρέωτος, σαν να ήταν εσωτερική δουλειά.
     */
    public static function push($entryId, $clientHint = 0)
    {
        $e = Db::timelog($entryId);
        if (!$e || $e->running || $e->minutes <= 0 || $e->sc_worklog_id || !self::scReady()) {
            return false;
        }
        $task = Db::task($e->task_id);
        $uid = self::clientForTask($task) ?: (int) $clientHint;
        if (!$uid) {
            return false; // εσωτερική εργασία — μένει μόνο στο CPM
        }
        $charge = self::chargeFor((int) $e->minutes, (int) $e->billable);
        $note = 'Task #' . $e->task_id . ' – ' . mb_substr($task->title ?? '', 0, 150)
            . ((int) $e->billable ? '' : ' (χωρίς χρέωση)')
            . ($e->note ? ' · ' . mb_substr($e->note, 0, 60) : '');
        $wid = ScDb::addWork($uid, $task->ticketid ?: null, (int) $e->minutes, $charge,
            (int) $e->billable, $note, $e->admin_id);
        /* Από πού πληρώνεται ο χρόνος: πρώτα η προσφορά του έργου, μετά η
           προαγορά, και ό,τι περισσέψει μένει ακάλυπτο για την επόμενη
           προσφορά. Δες Cover.php. */
        $cov = ['cover' => null, 'offer' => null, 'covered' => 0];
        if ((int) $e->billable && $charge > 0) {
            $ln = $note . ($charge > $e->minutes
                ? ' (εργασία ' . self::fmt($e->minutes) . ', χρέωση ' . self::fmt($charge) . ')' : '');
            $cov = Cover::draw($uid, $task, $charge, $entryId, $ln);
        }
        Db::updateTimelog($entryId, [
            'charged_minutes' => $charge,
            'sc_userid'       => $uid,
            'sc_worklog_id'   => is_numeric($wid) ? (int) $wid : null,
            'cover'           => $cov['cover'],
            'cover_offer_id'  => $cov['offer'],
            'cover_minutes'   => $cov['covered'],
        ]);
        return true;
    }

    /** Αναίρεση push (πριν από διαγραφή καταχώρησης): επιστροφή χρέωσης + σβήσιμο worklog. */
    public static function reverse($entryId)
    {
        $e = Db::timelog($entryId);
        if (!$e || !self::scReady()) {
            return;
        }
        if ($e->sc_worklog_id) {
            Capsule::table('mod_supportcontracts_worklog')->where('id', (int) $e->sc_worklog_id)->delete();
        }
        Cover::undraw($e);

    }

    public static function fmt($mins)
    {
        $mins = (int) $mins;
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        if ($h && $m) { return $h . 'ω ' . $m . '΄'; }
        if ($h) { return $h . 'ω'; }
        return $m . '΄';
    }
}
