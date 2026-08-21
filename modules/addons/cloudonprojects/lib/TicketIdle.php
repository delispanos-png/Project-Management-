<?php

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

require_once __DIR__ . '/Db.php';

/**
 * Αυτόματο κλείσιμο tickets που περιμένουν τον πελάτη.
 *
 * Ένα ticket όπου η τελευταία απάντηση είναι ΔΙΚΗ ΜΑΣ περιμένει τον πελάτη. Αν
 * σιωπήσει, μένει ανοιχτό στο άπειρο και μολύνει κάθε μέτρηση: φαίνεται ως
 * εκκρεμότητα ενώ δεν είναι.
 *
 * Κλίμακα (ημέρες σιωπής του πελάτη):
 *   WARN_DAY  → υπενθύμιση «περιμένουμε την απάντησή σας»
 *   CLOSE_DAY → απάντηση κλεισίματος + status Closed
 *
 * Ο πελάτης μπορεί πάντα να απαντήσει· το WHMCS ξανανοίγει το ticket μόνο του
 * και η εγγραφή εδώ μηδενίζεται, οπότε ο κύκλος ξεκινά από την αρχή.
 *
 * ΠΡΟΣΟΧΗ: στέλνει μηνύματα σε πελάτες. Ελέγχεται από τη ρύθμιση
 * `ticket_autoclose` του addon και είναι ΚΛΕΙΣΤΟ όσο δεν έχει οριστεί ρητά.
 */
class TicketIdle
{
    /** Προεπιλογές· παρακάμπτονται από τις ρυθμίσεις του addon. */
    const CLOSE_DAY = 5;
    const WARN_DAY = 4;

    /** Τμήματα/καταστάσεις που δεν αγγίζουμε ποτέ. */
    const SKIP_STATUS = ['Closed', 'Cancelled'];

    public static function enabled()
    {
        $v = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', 'ticket_autoclose')->value('value');
        return $v === 'on';
    }

    private static function cfg($key, $default)
    {
        $v = Capsule::table('tbladdonmodules')->where('module', 'cloudonprojects')
            ->where('setting', $key)->value('value');
        return ($v !== null && $v !== '' && (int) $v > 0) ? (int) $v : $default;
    }

    /**
     * @param bool $dry Δεν στέλνει και δεν κλείνει — επιστρέφει τι θα έκανε.
     * @return array{warned:array,closed:array,skipped:int}
     */
    public static function run($dry = false)
    {
        $closeDay = self::cfg('ticket_autoclose_days', self::CLOSE_DAY);
        $warnDay = max(1, $closeDay - 1);
        $out = ['warned' => [], 'closed' => [], 'skipped' => 0];

        foreach (Capsule::table('tbltickets')->whereNotIn('status', self::SKIP_STATUS)
                     ->get(['id', 'tid', 'did', 'userid', 'name', 'email', 'title', 'message', 'date', 'status']) as $t) {
            $last = Capsule::table('tblticketreplies')->where('tid', $t->id)
                ->orderBy('id', 'desc')->first(['admin', 'date']);
            // Χωρίς δική μας απάντηση, το ticket περιμένει ΕΜΑΣ — δεν το κλείνουμε.
            if (!$last || $last->admin === '' || $last->admin === null) {
                $out['skipped']++;
                continue;
            }
            $lastOn = substr((string) $last->date, 0, 10);
            $days = (int) floor((strtotime('today') - strtotime($lastOn)) / 86400);

            $row = Capsule::table('mod_cpm_ticket_idle')->where('ticket_id', $t->id)->first();
            /* Αν ο πελάτης απάντησε στο μεταξύ, η τελευταία απάντηση άλλαξε
               ημερομηνία — μηδενίζουμε ό,τι είχαμε καταγράψει. */
            if ($row && (string) $row->last_reply_on !== $lastOn) {
                Capsule::table('mod_cpm_ticket_idle')->where('id', $row->id)->delete();
                $row = null;
            }

            if ($days >= $closeDay && (!$row || !$row->closed_at)) {
                if (!$dry) { self::act($t, 'close', $closeDay); }
                $out['closed'][] = ['tid' => $t->tid, 'title' => $t->title, 'days' => $days];
                continue;
            }
            if ($days >= $warnDay && $days < $closeDay && (!$row || !$row->warned_at)) {
                if (!$dry) { self::act($t, 'warn', $closeDay); }
                $out['warned'][] = ['tid' => $t->tid, 'title' => $t->title, 'days' => $days];
            }
        }
        return $out;
    }

    /** Γλώσσα του πελάτη, από τα δικά του μηνύματα. */
    private static function lang($t)
    {
        $txt = (string) $t->message;
        foreach (Capsule::table('tblticketreplies')->where('tid', $t->id)->orderBy('id')->get(['admin', 'message']) as $r) {
            if ($r->admin === '' || $r->admin === null) { $txt .= "\n" . $r->message; }
        }
        $letters = preg_replace('/[^\p{L}]+/u', '', $txt);
        $greek = preg_match_all('/\p{Greek}/u', $letters);
        return ($greek >= 10) ? 'el' : 'en';
    }

    private static function act($t, $what, $closeDay)
    {
        $el = self::lang($t) === 'el';
        $deadline = date('d/m/Y', strtotime('+1 day'));

        if ($what === 'warn') {
            $msg = $el
                ? "Καλησπέρα σας,\n\nΠεριμένουμε την απάντησή σας για να προχωρήσουμε με το αίτημά σας.\n\n"
                    . "Αν δεν λάβουμε απάντηση μέχρι τις " . $deadline . ", θα θεωρήσουμε το θέμα ολοκληρωμένο "
                    . "και το αίτημα θα κλείσει.\n\nΑν το θέμα παραμένει, απαντήστε σε αυτό το μήνυμα."
                : "Hello,\n\nWe are waiting for your reply in order to proceed with your request.\n\n"
                    . "If we do not hear back by " . $deadline . ", we will consider the matter resolved "
                    . "and the ticket will be closed.\n\nIf the issue persists, simply reply to this message.";
            localAPI('AddTicketReply', ['ticketid' => (int) $t->id, 'message' => $msg,
                'adminusername' => 'pdelis', 'status' => $t->status], 'pdelis');
            Capsule::table('mod_cpm_ticket_idle')->updateOrInsert(
                ['ticket_id' => (int) $t->id],
                ['warned_at' => date('Y-m-d H:i:s'),
                 'last_reply_on' => substr((string) Capsule::table('tblticketreplies')->where('tid', $t->id)
                     ->orderBy('id', 'desc')->value('date'), 0, 10),
                 'created_at' => date('Y-m-d H:i:s')]
            );
            return;
        }

        $msg = $el
            ? "Καλησπέρα σας,\n\nΕπειδή δεν λάβαμε απάντηση τις τελευταίες " . $closeDay . " ημέρες, "
                . "θεωρούμε το θέμα ολοκληρωμένο και κλείνουμε το αίτημα.\n\n"
                . "Αν χρειάζεστε κάτι ακόμη, απαντήστε σε αυτό το μήνυμα και το αίτημα θα ανοίξει ξανά "
                . "με το ιστορικό του."
            : "Hello,\n\nAs we have not received a reply for " . $closeDay . " days, we consider the matter "
                . "resolved and are closing this ticket.\n\n"
                . "If you still need assistance, just reply to this message and the ticket will reopen "
                . "with its full history.";
        localAPI('AddTicketReply', ['ticketid' => (int) $t->id, 'message' => $msg,
            'adminusername' => 'pdelis', 'status' => 'Closed'], 'pdelis');
        localAPI('UpdateTicket', ['ticketid' => (int) $t->id, 'status' => 'Closed'], 'pdelis');

        Capsule::table('mod_cpm_ticket_idle')->updateOrInsert(
            ['ticket_id' => (int) $t->id],
            ['closed_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')]
        );
        logActivity('CloudOn PM: αυτόματο κλείσιμο ticket #' . $t->tid . ' — καμία απάντηση πελάτη '
            . $closeDay . ' ημέρες');
    }
}
