<?php
/**
 * Καθάρισμα των tickets που ανοίγει το τηλεφωνικό κέντρο.
 *
 * ΤΟ ΠΕΡΙΣΤΑΤΙΚΟ (20/09/2026): το πρώτο αληθινό ticket από την AI ρεσεψιόν
 * (#255418) ήρθε σωστά — όνομα, εταιρεία, τηλέφωνο επιστροφής, περίληψη — αλλά
 * από κάτω κουβαλούσε ΟΛΟΚΛΗΡΗ την απομαγνητοφώνηση της συνομιλίας, γραμμή
 * γραμμή με χρονοσημάνσεις. Σαράντα σειρές «Receptionist CloudOn: …» που
 * κανείς δεν διαβάζει, πριν φτάσεις στο ουσιαστικό.
 *
 * Η απομαγνητοφώνηση ΔΕΝ χάνεται: μένει στο ίδιο το 3CX, μαζί με την
 * ηχογράφηση. Εδώ απλώς δεν τη δείχνουμε στο ticket.
 *
 * ΓΙΑΤΙ ΕΔΩ ΚΑΙ ΟΧΙ ΣΤΟ 3CX: το πρότυπο του κέντρου είναι κοινό για κάθε
 * παραλήπτη και αλλάζει με κάθε αναβάθμιση. Ο κανόνας είναι δικός μας, ισχύει
 * μόνο για ό,τι μπαίνει στο δικό μας σύστημα, και φαίνεται στον κώδικα.
 *
 * @package WHMCS
 */

use WHMCS\Database\Capsule;

if (!function_exists('cnp_pbx_strip_transcript')) {
    /**
     * Κόβει το τμήμα της απομαγνητοφώνησης, κρατώντας ό,τι είναι πριν.
     * Επιστρέφει null αν δεν υπάρχει τίποτα να κοπεί — ώστε ο καλών να ξέρει
     * ότι δεν χρειάζεται εγγραφή στη βάση.
     */
    function cnp_pbx_strip_transcript($body)
    {
        $body = (string) $body;
        if ($body === '' || stripos($body, 'FULL CALL TRANSCRIPT') === false) { return null; }

        /* Κόβουμε από την επικεφαλίδα και μετά. Το «Best regards» του 3CX που
           ακολουθεί φεύγει κι αυτό — δεν προσθέτει τίποτα σε ticket. */
        $cut = preg_split('/(<br\s*\/?>|\n|\r)*\s*(─|-|=){0,80}\s*FULL CALL TRANSCRIPT/i', $body, 2);
        if (!isset($cut[0])) { return null; }

        $kept = rtrim($cut[0]);
        /* Καθάρισμα από τη γραμμή διαχωρισμού που έμεινε κρεμασμένη στο τέλος. */
        $kept = preg_replace('/(?:<br\s*\/?>|\s)*(?:─|—|-|=){3,}\s*$/u', '', $kept);
        $note = '<br><br><i>Η πλήρης απομαγνητοφώνηση και η ηχογράφηση μένουν στο '
              . 'τηλεφωνικό κέντρο.</i>';
        return rtrim($kept) . $note;
    }
}

/**
 * Τρέχει μόλις ανοίξει ticket — και από εισαγωγή email.
 * Δεν πετάει ποτέ: ένα σφάλμα εδώ δεν πρέπει να εμποδίσει το ticket.
 */
add_hook('TicketOpen', 1, function ($vars) {
    try {
        $id = (int) ($vars['ticketid'] ?? 0);
        if (!$id) { return; }
        $t = Capsule::table('tbltickets')->where('id', $id)->first();
        if (!$t) { return; }

        $clean = cnp_pbx_strip_transcript($t->message);
        if ($clean === null) { return; }

        Capsule::table('tbltickets')->where('id', $id)->update(['message' => $clean]);
    } catch (\Throwable $e) {
        if (function_exists('logActivity')) {
            logActivity('CPM: καθάρισμα ticket κέντρου απέτυχε — ' . $e->getMessage());
        }
    }
});

/** Το ίδιο και για απαντήσεις που έρχονται από το κέντρο. */
add_hook('TicketUserReply', 1, function ($vars) {
    try {
        $rid = (int) ($vars['replyid'] ?? 0);
        if (!$rid) { return; }
        $r = Capsule::table('tblticketreplies')->where('id', $rid)->first();
        if (!$r) { return; }
        $clean = cnp_pbx_strip_transcript($r->message);
        if ($clean === null) { return; }
        Capsule::table('tblticketreplies')->where('id', $rid)->update(['message' => $clean]);
    } catch (\Throwable $e) { /* σιωπηλά — δεν μπλοκάρουμε απάντηση πελάτη */ }
});
