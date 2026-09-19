<?php
/**
 * CloudOn Agent — ΔΙΧΤΥ ΑΣΦΑΛΕΙΑΣ για τα αιτήματα της AI ρεσεψιόν.
 *
 * ΤΟ ΠΡΟΒΛΗΜΑ (μετρήθηκε 20/09/2026 01:57): η ρεσεψιόν είπε «το μήνυμα εστάλη»
 * και δεν έφτασε τίποτα — ούτε email, ούτε chat, ούτε ticket. Ο πελάτης θα
 * περίμενε απάντηση που δεν θα ερχόταν ποτέ.
 *
 * Η ΛΥΣΗ δεν βασίζεται στο τι κάνει το μοντέλο: κάθε κλήση στη ρεσεψιόν
 * ηχογραφείται και απομαγνητοφωνείται. Εδώ διαβάζουμε τα κείμενα, και για κάθε
 * κλήση όπου ο καλών ζήτησε ticket / μήνυμα / επανάκληση και δεν μίλησε με
 * άνθρωπο, ελέγχουμε αν υπάρχει ticket στο WHMCS. Αν όχι, το ανοίγουμε ΕΜΕΙΣ,
 * με το κείμενο της συνομιλίας. Τρέχει από τον παλμό (/10΄).
 *
 * @package WHMCS\Module\Addon\CloudonProjects
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

class AiTickets
{
    const DEPT = 2;                                  // WHMCS «Support Department»
    const NOREPLY = 'pbx-noreply@cloudon.gr';        // αποστολέας για μη-πελάτες (το WHMCS πετά μόνο τη διεύθυνση του τμήματος)
    const LOOKBACK_H = 6;                            // πόσο πίσω κοιτάμε για κλήσεις χωρίς απόφαση
    const MIN_AGE_MIN = 10;                          // πόσο περιμένουμε πριν αποφασίσουμε (να προλάβει η απομαγνητοφώνηση και τυχόν email θυρίδας)

    /** Λέξεις που σημαίνουν «ζήτησε κάτι που πρέπει να φτάσει σε άνθρωπο». */
    const WANT = '/ticket|τικετ|τίκετ|αίτημ|αιτημ|μήνυμ|μηνυμ|επανάκλ|επανακλ|να με καλέσ|να με καλεσ|καλέστε με|να σας καλέσ|θυρίδ|voicemail|καταχωρ/iu';
    const SPAM = '/spam|τηλεπωλ|telemarket|scam|απάτ/iu';
    const HOSTILE = '/υβρι|ύβρι|απειλ|προσβλητ|hostil|abus|threat/iu';

    /** Ο κύκλος: διάβασε τις πρόσφατες κλήσεις της ρεσεψιόν και κλείσε τα κενά. */
    public static function sweep()
    {
        $res = ['checked' => 0, 'created' => 0, 'linked' => 0, 'skipped' => 0, 'errors' => []];
        if (!Pbx3cxClient::configured()) { return $res; }
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - self::LOOKBACK_H * 3600);
        try {
            $j = Pbx3cxClient::xapi('Recordings', ['$top' => 50, '$orderby' => 'StartTime desc',
                '$filter' => "ToDn eq '" . Pbx3cxBlueprint::AI_DN . "' and StartTime ge " . $since,
                '$select' => 'Id,StartTime,EndTime,CallType,FromCallerNumber,FromDisplayName,IsTranscribed,Transcription,Summary,RecordingUrl']);
        } catch (\Throwable $e) {
            $res['errors'][] = 'Recordings: ' . $e->getMessage();
            return $res;
        }
        /* Από την ΠΑΛΑΙΟΤΕΡΗ προς τη νεότερη: αλλιώς το ticket που ανοίγουμε για
           μια νέα κλήση «βρίσκεται» ως υπάρχον για την προηγούμενη (μετρήθηκε). */
        $rows = $j['value'] ?? [];
        usort($rows, function ($a, $b) { return strcmp((string) $a['StartTime'], (string) $b['StartTime']); });
        foreach ($rows as $r) {
            $rid = (int) $r['Id'];
            if (Capsule::table('mod_cpm_ai_calls')->where('recording_id', $rid)->exists()) { continue; }
            $res['checked']++;
            $text = trim((string) ($r['Transcription'] ?? ''));
            $sum = trim((string) ($r['Summary'] ?? ''));
            /* Χωρίς κείμενο ακόμη — η απομαγνητοφώνηση θέλει λίγα λεπτά. Ξανά στον επόμενο παλμό. */
            if ($text === '' && $sum === '') { continue; }
            /* ΟΧΙ ΔΙΠΛΑ: αν ο καλών άφησε φωνητικό μήνυμα, το ticket του έρχεται από
               το email της θυρίδας μέσα σε λίγα λεπτά. Περιμένουμε 15΄ από το τέλος
               της κλήσης ώστε, όταν ψάξουμε «υπάρχει ticket;», να υπάρχει ήδη. */
            if (strtotime((string) ($r['EndTime'] ?: $r['StartTime'])) > time() - self::MIN_AGE_MIN * 60) { continue; }
            try {
                $d = self::decide($r, $text, $sum);
                if ($d['decision'] === 'ticket') {
                    $d['ticket_id'] = self::openTicket($r, $d, $text, $sum);
                    $res['created']++;
                } elseif ($d['decision'] === 'linked') { $res['linked']++; }
                else { $res['skipped']++; }
                self::remember($r, $d);
            } catch (\Throwable $e) {
                $res['errors'][] = '#' . $rid . ': ' . $e->getMessage();
                Pbx3cxClient::log('ai_net', 'error', 'Ηχογράφηση #' . $rid . ': ' . $e->getMessage());
            }
        }
        if ($res['created'] || $res['errors']) {
            Pbx3cxClient::log('ai_net', $res['errors'] ? 'error' : 'ok',
                'Δίχτυ αιτημάτων: ' . $res['created'] . ' tickets ανοίχτηκαν από εμάς, ' . $res['linked'] . ' βρέθηκαν ήδη'
                . ($res['errors'] ? ' · σφάλματα: ' . count($res['errors']) : ''));
        }
        return $res;
    }

    /** Τι πρέπει να γίνει με αυτή την κλήση. */
    private static function decide(array $r, $text, $sum)
    {
        $e164 = Pbx3cxCdr::e164((string) ($r['FromCallerNumber'] ?? ''));
        $start = strtotime((string) $r['StartTime']);
        $all = $sum . "\n" . $text;
        $out = ['e164' => $e164, 'book' => 0, 'name' => '', 'clientid' => 0, 'decision' => 'none', 'reason' => '', 'ticket_id' => 0];
        $hit = $e164 !== '' ? Book::resolve($e164) : null;
        if ($hit) {
            $b = Capsule::table('mod_cpm_book')->where('id', (int) $hit['id'])->first();
            $out['book'] = (int) $b->id; $out['name'] = Book::label((array) $b); $out['clientid'] = (int) $b->clientid;
        }
        if (($r['CallType'] ?? '') === 'Local') { $out['reason'] = 'εσωτερική δοκιμή'; return $out; }
        if (preg_match(self::SPAM, $sum)) { $out['decision'] = 'spam'; $out['reason'] = 'spam κατά την περίληψη'; return $out; }
        /* Υβριστικός καλών: η ρεσεψιόν έκλεισε επίτηδες — δεν ανοίγει ticket. */
        if (preg_match(self::HOSTILE, $sum)) { $out['decision'] = 'hostile'; $out['reason'] = 'υβριστικός καλών — η κλήση τερματίστηκε επίτηδες'; return $out; }
        if (stripos($sum, 'No meaningful conversation') !== false && mb_strlen($text) < 200) {
            $out['decision'] = 'empty'; $out['reason'] = 'χωρίς ουσιαστική συνομιλία'; return $out;
        }
        /* Μίλησε με άνθρωπο; Τότε ο άνθρωπος καταγράφει, όχι εμείς. */
        if ($e164 !== '' && Capsule::table('mod_cpm_calls')->where('other_e164', $e164)->where('answered', 1)
            ->whereBetween('started_at', [date('Y-m-d H:i:s', $start - 120), date('Y-m-d H:i:s', $start + 900)])->exists()) {
            $out['decision'] = 'human'; $out['reason'] = 'απάντησε συνάδελφος'; return $out;
        }
        if (!preg_match(self::WANT, $all)) { $out['reason'] = 'δεν ζητήθηκε αίτημα'; return $out; }
        /* Υπάρχει ήδη ticket για αυτόν τον αριθμό γύρω από την κλήση (από το email της ρεσεψιόν ή το voicemail); */
        $digits = substr(preg_replace('/\D/', '', $e164), -9);
        $q = Capsule::table('tbltickets')->whereBetween('date', [date('Y-m-d H:i:s', $start - 60), date('Y-m-d H:i:s', $start + 1200)]);
        if ($digits !== '') {
            $q->where(function ($w) use ($digits) { $w->where('title', 'like', '%' . $digits . '%')->orWhere('message', 'like', '%' . $digits . '%'); });
        } else {
            $q->where('email', self::NOREPLY);
        }
        /* Τα tickets που ανοίξαμε ΕΜΕΙΣ για άλλες κλήσεις δεν μετράνε ως «υπάρχον». */
        $ours = Capsule::table('mod_cpm_ai_calls')->whereNotNull('ticket_id')->where('decision', 'ticket')->pluck('ticket_id')->all();
        if ($ours) { $q->whereNotIn('id', $ours); }
        $ex = $q->orderBy('id', 'asc')->first(['id', 'tid']);
        if ($ex) { $out['decision'] = 'linked'; $out['ticket_id'] = (int) $ex->id; $out['reason'] = 'υπάρχει ticket #' . $ex->tid; return $out; }
        $out['decision'] = 'ticket'; $out['reason'] = 'ζήτησε αίτημα και δεν βρέθηκε ticket';
        return $out;
    }

    /** Ανοίγει το ticket στο WHMCS με το κείμενο της κλήσης. Επιστρέφει το id. */
    private static function openTicket(array $r, array $d, $text, $sum)
    {
        $when = date('d/m/Y H:i', strtotime((string) $r['StartTime']));
        $who = $d['name'] !== '' ? $d['name'] . ' (' . $d['e164'] . ')' : ($d['e164'] ?: 'ανώνυμος');
        /* ΜΟΝΟ η σύνοψη της AI (απόφαση 20/09/2026): η ηχογράφηση δεν προσθέτει
           τίποτα και η πλήρης απομαγνητοφώνηση υπάρχει στην κάρτα «AI ρεσεψιόν».
           Χωρίς σύνοψη, μπαίνει το κείμενο ως εφεδρεία. */
        $body = "Αίτημα από την AI ρεσεψιόν.\n"
            . "Κλήση: " . $when . " από " . $who . "\n\n"
            . ($sum !== '' ? $sum : "Απομαγνητοφώνηση:\n" . $text) . "\n";
        $params = ['deptid' => self::DEPT, 'subject' => 'Αίτημα από ρεσεψιόν: ' . ($d['name'] !== '' ? $d['name'] : $d['e164']),
            'message' => $body, 'priority' => 'Medium', 'markdown' => false];
        if ($d['clientid'] > 0) { $params['clientid'] = $d['clientid']; }
        else { $params['name'] = $d['name'] !== '' ? $d['name'] : ('Καλών ' . $d['e164']); $params['email'] = self::NOREPLY; }
        if (!function_exists('localAPI')) { throw new \RuntimeException('localAPI δεν είναι διαθέσιμο'); }
        $api = localAPI('OpenTicket', $params);
        if (($api['result'] ?? '') !== 'success') { throw new \RuntimeException('OpenTicket: ' . ($api['message'] ?? 'άγνωστο σφάλμα')); }
        $tid = (int) ($api['id'] ?? 0);
        Pbx3cxClient::log('ai_net', 'ok', 'Άνοιξε ticket #' . ($api['tid'] ?? $tid) . ' από την κλήση ' . $when . ' (' . $who . ')');
        if ($d['book']) {
            try {
                Capsule::table('mod_cpm_interactions')->insert(['book_id' => $d['book'], 'clientid' => $d['clientid'] ?: null,
                    'kind' => 'ticket', 'summary' => 'Ticket από τη ρεσεψιόν (δίχτυ ασφαλείας) #' . ($api['tid'] ?? $tid),
                    'detail' => mb_substr($sum ?: $text, 0, 1000), 'phone' => $d['e164'], 'direction' => 'in',
                    'ticketid' => $tid, 'happened_at' => date('Y-m-d H:i:s', strtotime((string) $r['StartTime'])),
                    'created_at' => date('Y-m-d H:i:s')]);
            } catch (\Throwable $e) { /* το χρονολόγιο δεν χαλάει το ticket */ }
        }
        return $tid;
    }

    private static function remember(array $r, array $d)
    {
        Capsule::table('mod_cpm_ai_calls')->insert(['recording_id' => (int) $r['Id'],
            'started_at' => date('Y-m-d H:i:s', strtotime((string) $r['StartTime'])),
            'e164' => mb_substr($d['e164'], 0, 24), 'book_id' => $d['book'] ?: null,
            'decision' => $d['decision'], 'reason' => mb_substr($d['reason'], 0, 200),
            'ticket_id' => $d['ticket_id'] ?: null, 'created_at' => date('Y-m-d H:i:s')]);
    }
}
