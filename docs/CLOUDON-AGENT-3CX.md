# CloudOn Agent — 3CX Integration
## Discovery & Technical Architecture (καμία υλοποίηση ακόμη)

> Κατάσταση: **ΠΡΟΣ ΕΓΚΡΙΣΗ**. Δεν έχει γραφτεί production code.
> Ημερομηνία: 19/09/2026 · Συντάκτης: ανάλυση πάνω στο ζωντανό σύστημα.

---

## ΦΑΣΗ 0 — ΕΚΤΕΛΕΣΤΗΚΕ (19/09/2026, με πραγματικά credentials)

**PBX: 3CX v20.0.10.1621** · 17 extensions · 5/5 trunks. Το ερώτημα της έκδοσης έκλεισε.

| Έλεγχος | Αποτέλεσμα |
|---|---|
| Ταυτοποίηση (client_credentials) | ✔ |
| Έκδοση / SystemStatus | ✔ v20.0.10.1621 |
| Users / Groups / Queues / Trunks / DidNumbers | ✔ |
| **Χαρτογράφηση DN → χειριστές** | ✔ **8/15 αυτόματα με email** |
| ActiveCalls | ✔ |
| **AI** | ✔ **ενεργό · openai · gpt-5.2 + gpt-realtime** |
| **CDR** | ✔ **ενεργό · SingleFileForAllCalls** |
| Call Control API | ✔ διαθέσιμο |
| **CallHistoryView / Recordings** | ✖ **403 — δεν το επιτρέπει ο ρόλος** |

### ΕΝΗΜΕΡΩΣΗ μετά την αλλαγή ρόλου (10:34)

Με τον νέο ρόλο στο service principal 1001, **7/7 κρίσιμοι έλεγχοι**:

```
✔ Ιστορικό κλήσεων   123.086 segments · τελευταία κλήση 2026-09-18 15:31
✔ Χαρτογράφηση       9 από 13 extensions ταυτίζονται αυτόματα με email
✔ AI ενεργό · openai      ✔ Call Control API διαθέσιμο
– Έκδοση PBX (403)        – CDR (403)        ← προαιρετικά, μόνο διαγνωστικά
```

**Ο ρόλος είναι trade-off, όχι κλίμακα.** Με «System Administrator» διαβάζαμε
SystemStatus/CDRSettings αλλά ΟΧΙ ιστορικό. Με τον τωρινό, διαβάζουμε ιστορικό
και χάνουμε δύο διαγνωστικές αναγνώσεις. **Το ιστορικό είναι ασύγκριτα
σημαντικότερο** — είναι η πηγή αλήθειας. Τα δύο που χάθηκαν δεν χρειάζονται
για τη λειτουργία, γι' αυτό σημειώνονται «προαιρετικά» και δεν μετράνε στο σκορ.

Το scope ΔΕΝ είναι ο μοχλός: δοκιμάστηκαν `xapi`, `reports`, `xapi reports` —
το αποτέλεσμα ήταν ίδιο. Ο ρόλος του API client αποφασίζει.

### Όρια που μετρήθηκαν στο CallHistoryView

| Ερώτημα | Αποτέλεσμα |
|---|---|
| `$top`, `$count` | ✔ |
| `$filter=SegmentStartTime ge …` | **HTTP 500** |
| `$orderby=SegmentStartTime desc` | **timeout** (123k εγγραφές) |

Άρα ο συγχρονισμός **δεν** μπορεί να στηριχτεί σε φιλτράρισμα αυτής της όψης.
Η σωστή διαδρομή είναι η bound function `ReportCallLogData/Pbx.GetCallLogData(
periodFrom, periodTo, …)`, που πλέον απαντά **200** — αλλά με τις παραμέτρους
που δοκιμάστηκαν γυρίζει 0 εγγραφές ενώ υπάρχουν κλήσεις. Η σημασία των enum
παραμέτρων (`sourceType`, `callsType`, `callTimeFilterType`) **μένει να λυθεί
στη Φάση 5** — δεν την μαντεύουμε.

### Τι σημαίνει πρακτικά

- **Φάση 3 (configuration sync) μπορεί να ξεκινήσει σήμερα.** Όλα τα δεδομένα
  δομής διαβάζονται, και 8 στους 15 χειριστές ταυτίζονται μόνοι τους από το
  email. Οι υπόλοιποι 7 είναι συσκευές (reception, door phone), εξωτερικοί
  συνεργάτες και system extensions — δηλαδή **όλοι οι πραγματικοί άνθρωποι
  ταυτίζονται**.
- **Φάση 5 (ιστορικό) ΜΠΛΟΚΑΡΕΤΑΙ** από δικαίωμα, όχι από τεχνικό εμπόδιο. Ο
  ρόλος του API client (DN 1001) δεν έχει πρόσβαση σε αναφορές/ηχογραφήσεις.
  **Χρειάζεται αλλαγή ρόλου στο 3CX.**
- Το AI επιβεβαιώθηκε μετρημένα: `gpt-5.2` για completions. Άρα `Transcription`
  και `Summary` θα υπάρχουν στις εγγραφές — μόλις ξεκλειδώσει η πρόσβαση.

---

## ΦΑΣΗ 5 — ΣΤΑΜΑΤΗΣΕ ΣΕ ΕΜΠΟΔΙΟ (19/09/2026)

**Δεν χτίστηκε συγχρονισμός.** Ο λόγος δεν είναι τεχνικός — είναι ότι το
ιστορικό που μας εκθέτει το PBX **σταματά στις 15/04/2025**, ενώ το ίδιο το PBX
δηλώνει τελευταία κλήση **18/09/2026**. Λείπουν 17 μήνες, δηλαδή ακριβώς τα
δεδομένα που χρειάζεται η καθημερινή λειτουργία.

### Τι μετρήθηκε

| Διαδρομή | Αποτέλεσμα |
|---|---|
| `CallHistoryView` | ✔ 123.086 segments · **2018-06 → 2025-04-15** |
| `LastCdrAndChatMessageTimestamp` | τελευταία κλήση **2026-09-18 15:31** |
| `ReportCallLogData/Pbx.GetCallLogData(...)` GET | **200 αλλά 0 εγγραφές** σε κάθε εύρος, ακόμη και 2015→2027 |
| `ReportOldCallLogData/Pbx.GetOldCallLogData(...)` GET | **405** |
| Και τα δύο με POST | 405 / 404 |

Το όριο των 2025-04-15 επιβεβαιώθηκε **δύο ανεξάρτητους τρόπους**:
`$orderby=SegmentStartTime desc` και `$skip=123080` (φυσική σειρά) δίνουν και τα
δύο 15/04/2025 ως τελευταία εγγραφή.

Το metadata έχει **ξεχωριστές** συναρτήσεις `GetCallLogData` και
`GetOldCallLogData` — το 3CX χωρίζει ρητά «τρέχον» από «αρχείο». Το
`CallHistoryView` είναι προφανώς το αρχείο. Το τρέχον δεν μας επιστρέφεται.

### Τι ΔΕΝ ξέρουμε ακόμη

Γιατί το τρέχον log είναι κενό για εμάς. Πιθανά: αλλαγή αποθήκευσης σε
αναβάθμιση γύρω στον Απρίλιο 2025 · ρύθμιση retention · δικαίωμα που δεν
καλύπτεται από τον ρόλο.

**Δεν το μαντεύουμε.** Ένας συγχρονισμός πάνω στο `CallHistoryView` θα έδειχνε
«επιτυχία» και θα έχανε σιωπηλά 17 μήνες — το χειρότερο δυνατό αποτέλεσμα για
σύστημα που θα τροφοδοτεί χρόνο και χρέωση.

### Η πρόταση: CDR Active Socket

Το `CDRSettings` δείχνει **ενεργό CDR** με `LogType=SingleFileForAllCalls`
(γράφει αρχείο στο PBX). Το enum `TypeOfCDRLog` δίνει:

```
SingleFileForAllCalls=0 · SingleFileForEachCall=1 · PassiveSocket=2 · ActiveSocket=3
```

Με **ActiveSocket**, το PBX σπρώχνει κάθε CDR record σε δικό μας listener. Αυτό:

- καλύπτει τις **τρέχουσες** κλήσεις, που είναι το ζητούμενο
- είναι ο τεκμηριωμένος δρόμος του 3CX για εξαγωγή κλήσεων σε τρίτο σύστημα
- ταιριάζει με την εγκεκριμένη μόνιμη διεργασία (Φάση 4)
- αφήνει το `CallHistoryView` για **εφάπαξ backfill** του 2018→2025

Δηλαδή το αρχείο και το τρέχον έρχονται από διαφορετικές πηγές — που είναι
ούτως ή άλλως ο διαχωρισμός που κάνει το ίδιο το 3CX.

---

## 0. Τι επαληθεύτηκε, τι όχι

Όλα τα παρακάτω για το 3CX **δεν** προέρχονται από tutorials. Τραβήχτηκαν από το
**δικό μας PBX** (`cloudon.3cx.gr`) με ανώνυμα GET, και είναι ο πιο αυθεντικός
κατάλογος δυνατοτήτων που μπορούμε να έχουμε:

| Τι | Πώς επαληθεύτηκε | Αποτέλεσμα |
|---|---|---|
| XAPI (Configuration/Reports) | `GET /xapi/v1/$metadata` | **200** · 291 KB OData EDMX, ~110 entity sets |
| Call Control API | `GET /callcontrol` | **401** → υπάρχει, θέλει auth |
| OAuth/OIDC | `GET /.well-known/openid-configuration` | **200** |
| Έκδοση PBX | — | **ΕΚΚΡΕΜΕΙ** (θέλει token· `GetVersionType` υπάρχει στο metadata) |
| Swagger Call Control | `GET /callcontrol/swagger.json` | **401** → θα τραβηχτεί στη Φάση 0 |

Η παρουσία XAPI + OData + πεδίων AI δείχνει γενιά **V20**. Η ακριβής έκδοση
είναι το **πρώτο** πράγμα που κλειδώνει η Φάση 0 — δεν σχεδιάζουμε πάνω σε εικασία.

### Το auth είναι λυμένο και τεκμηριωμένο

```json
"token_endpoint": "https://cloudon.3cx.gr/connect/token",
"grant_types_supported": ["authorization_code","refresh_token","client_credentials"],
"token_endpoint_auth_methods_supported": ["none","client_secret_post","private_key_jwt"],
"scopes_supported": ["openid","profile","email","offline_access","mcp"]
```

→ Για server-to-server: **`client_credentials`** με `client_secret_post`.
Το `private_key_jwt` είναι διαθέσιμο και προτιμότερο μακροπρόθεσμα (δεν ταξιδεύει
ποτέ secret). Το scope **`mcp`** αξίζει ξεχωριστή διερεύνηση για το CloudOn Agent.

---

## 01 — 3CX API Capability Matrix

Από τα ~110 entity sets του metadata, αυτά μας αφορούν:

| Ανάγκη | 3CX API / Entity | Real-time | Historical | Τι δίνει | Σημείωση |
|---|---|:--:|:--:|---|---|
| Live κατάσταση κλήσης | **Call Control WS** | ✔ | ✖ | call state, participants, DTMF | Το μόνο πραγματικό push |
| Live λίστα κλήσεων | XAPI `ActiveCalls` | ~poll | ✖ | Id, Caller, Callee, Status, EstablishedAt | **Μόνο 7 πεδία** — φτωχό |
| Ιστορικό (αυθεντικό) | XAPI `ReportCallLogData` / `CallLogData` | ✖ | ✔ | 37 πεδία (βλ. κάτω) | **Πηγή αλήθειας** |
| Legs / segments | XAPI `CallHistoryView` | ✖ | ✔ | SegmentId, Src/DstParticipantId, SegmentType | Το μοντέλο των legs |
| Ηχογραφήσεις | XAPI `Recordings`, `DownloadRecording` | ✖ | ✔ | αρχείο + metadata | Πάει στο δικό μας Storage |
| Απομαγνητοφώνηση | `TranscribeRecordings`, `GetTranscribeLanguages` | ✖ | ✔ | κείμενο | **Το κάνει το 3CX** |
| Extensions/χρήστες | XAPI `Users`, `Groups` | ✖ | ✔ | DN, όνομα, email, τμήμα | Configuration sync |
| Ουρές / ring groups | XAPI `Queues`, `RingGroups` | ✖ | ✔ | μέλη, πολιτική | |
| DID / trunks | XAPI `DidNumbers`, `Trunks` | ✖ | ✔ | αριθμοί, πάροχοι | |
| Κόστος γραμμής | XAPI `CallCostSettings`, `ExportCallCosts` | ✖ | ✔ | κόστος τηλεπικοινωνίας | **Διαφορετικό** από το κόστος εργασίας μας |
| Ενέργειες | `MakeCall`, `DropCall`, Call Control | ✔ | — | **Φάση 20**, όχι τώρα | |

### Το εύρημα που αλλάζει τον σχεδιασμό

Το `CallLogData` **περιέχει ήδη**:

```
TalkingDuration, RingingDuration, CallCost, Direction, CallType, Status,
Reason, RecordingUrl, SegmentId, CdrId, MainCallHistoryId, Indent,
SentimentScore, Summary, Transcription
```

**Το 3CX V20 παράγει μόνο του περίληψη, απομαγνητοφώνηση και sentiment.**
Άρα το §15 του αιτήματος **δεν είναι δική μας υποδομή AI** — είναι *κατανάλωση*
έτοιμου πεδίου, με το δικό μας AI να κάνει μόνο το βήμα που λείπει:
μετατροπή σε *Customer Request / Action Taken / Next Action / προτεινόμενη χρέωση*.

Αυτό κόβει ολόκληρη φάση δουλειάς — αρκεί να επιβεβαιωθεί ότι η άδειά μας το
ενεργοποιεί (Φάση 0).

---

## 02 — Call Lifecycle

```
                  ┌───────────────── ΠΗΓΗ ΑΛΗΘΕΙΑΣ ─────────────────┐
3CX ─ WS event ──▶ ΠΡΟΣΩΡΙΝΟ (live UX)            XAPI CallLogData ─┴─▶ ΟΡΙΣΤΙΚΟ
     ringing          │                                  ▲
     answered         │  δείχνει κάρτα, ανοίγει πελάτη    │ reconciliation
     transferred      │  ΔΕΝ γράφει οριστικά δεδομένα     │ κάθε 2΄ + nightly
     ended ───────────┘──────────────────────────────────┘
                                   │
                                   ▼
                    normalize αριθμού → ταύτιση πελάτη
                                   │
                    extension → CloudOn χειριστής
                                   │
                    ┌──────────────┴──────────────┐
                    ▼                             ▼
            mod_cpm_calls (τεχνικό)      mod_cpm_interactions (ανθρώπινο)
            legs, χρόνοι, κόστος         summary, κατηγορία, χρέωση, follow-up
                    │                             │
                    └──────────────┬──────────────┘
                                   ▼
                   Χρόνος επικοινωνίας → Ημέρα/Κόστος/Χρέωση
                                   ▼
                   Task / Ticket / Customer timeline / Analytics
```

**Ο κανόνας:** το WebSocket δεν γράφει ποτέ οριστική εγγραφή. Είναι εμπειρία
χρήστη. Η λογιστική αλήθεια έρχεται πάντα από το XAPI. Έτσι, αν πέσει το
WebSocket, χάνουμε την *κάρτα*, **ποτέ την κλήση**.

---

## 03 — Event Model

```
mod_cpm_pbx_events   (append-only, raw)
  id, source='3cx', channel='ws'|'xapi', event_type, external_id,
  payload JSON, received_at, processed_at, status, attempts, error
```

- Ό,τι έρχεται γράφεται **πρώτα ωμό**, μετά επεξεργάζεται. Αν αλλάξει η λογική,
  ξαναπαίζουμε το ιστορικό χωρίς να ρωτήσουμε ξανά το 3CX.
- `status`: `received → processed | failed | dead`. Τα `dead` είναι το
  dead-letter: ορατά στο dashboard, όχι σιωπηλά χαμένα.

---

## 04 — Database Model

**Δεν φτιάχνουμε δεύτερο σύστημα.** Το CloudOn έχει ήδη 92 πίνακες `mod_cpm_*`,
και — κρίσιμο — έχει ήδη **οντότητα επικοινωνίας**:

`mod_cpm_interactions` : `kind, clientid, admin_id, phone, caller, direction,
minutes, summary, detail, happened_at, followup_*, task_id, ticketid, offer_id`

Αυτή **παραμένει** το ανθρώπινο στρώμα. Το 3CX **τη γεμίζει**, δεν την αντικαθιστά.

Νέοι πίνακες — μόνο ό,τι δεν χωράει:

```
mod_cpm_calls            μία γραμμή ανά κλήση (3CX call)
  id, pbx_call_id, cdr_id UNIQUE, main_history_id,
  direction, call_type, status, reason,
  started_at, answered_at, ended_at,
  ring_seconds, talk_seconds,
  from_number, from_name, to_number, to_name,
  from_e164, to_e164,                    ← κανονικοποιημένα
  clientid, client_match ('exact'|'contact'|'manual'|'none'),
  admin_id, extension,
  queue_dn, did_number, trunk,
  pbx_cost DECIMAL,                      ← κόστος γραμμής από 3CX
  transcript TEXT, pbx_summary TEXT, sentiment INT,   ← μόνο κείμενο· ΟΧΙ αρχείο ήχου
  interaction_id,                        ← γέφυρα στο ανθρώπινο στρώμα
  synced_at, source

mod_cpm_call_segments    τα legs — transfers, ουρές, πολλοί συμμετέχοντες
  id, call_id, segment_id, seq, segment_type, action_type,
  src_dn, src_number, src_display, src_participant_id,
  dst_dn, dst_number, dst_display, dst_participant_id,
  started_at, ended_at, talk_seconds, answered
  UNIQUE (call_id, segment_id)

mod_cpm_pbx_map          3CX DN → CloudOn χειριστής (ΟΧΙ δεύτερο directory)
  id, dn UNIQUE, dn_type ('extension'|'queue'|'ringgroup'),
  admin_id, display_name, active, synced_at

mod_cpm_pbx_sync         υγεία & δείκτες συγχρονισμού
  key UNIQUE, cursor_at, last_ok_at, last_error, stats JSON

mod_cpm_cost_rates       κόστος ανά άνθρωπο, με ισχύ από ημερομηνία
  id, admin_id, cost_per_hour, valid_from, note
```

**Idempotency.** Κλειδί: `cdr_id` (UNIQUE) για την κλήση,
`(call_id, segment_id)` για το leg. Κάθε εγγραφή είναι `updateOrInsert` πάνω σε
αυτά. Το ίδιο event να έρθει δέκα φορές — μία γραμμή θα υπάρχει.

---

## 05 — Integration Architecture

```
lib/Pbx3cx/Client.php      OAuth client_credentials, token cache, retry/backoff
lib/Pbx3cx/Xapi.php        OData queries (CallLogData, Users, Queues…)
lib/Pbx3cx/Ws.php          WebSocket listener (ξεχωριστή διεργασία)
lib/Pbx3cx/Ingest.php      event → mod_cpm_pbx_events → domain
lib/Pbx3cx/Reconcile.php   περιοδικό pull· self-healing
lib/Pbx3cx/Match.php       τηλέφωνο → πελάτης · DN → χειριστής
lib/Calls.php              ΤΟ ΜΟΝΟ API που βλέπει η υπόλοιπη εφαρμογή
```

Η υπόλοιπη εφαρμογή καλεί **μόνο** `Calls::`. Αν αύριο μπει Yeastar (έχουμε ήδη
τιμοκατάλογο για αυτό), αλλάζει ο φάκελος `Pbx3cx`, τίποτε άλλο.

**Η διεργασία WebSocket** δεν μπορεί να ζει σε PHP-FPM request. Χρειάζεται
systemd service ή supervised worker — αυτό είναι απόφαση υποδομής που πρέπει να
εγκριθεί, γιατί είναι το μόνο κομμάτι εκτός του υπάρχοντος μοντέλου (cron + web).

---

## 06 — Security Model

- `client_credentials`, με **δικό του 3CX ρόλο ελάχιστων δικαιωμάτων** (read).
- Secrets στο `tbladdonmodules` όπως τα υπόλοιπα του module — **ποτέ στο repo**.
  Ιδανικά κρυπτογραφημένα με κλειδί εκτός DB (απόφαση προς έγκριση).
- Token cache με refresh πριν τη λήξη· ποτέ token σε log.
- Νέα κυκλώματα στα δικαιώματα (ΥΠΟΧΡΕΩΤΙΚΟ — κανόνας του project):
  `comms.calls` (προβολή) · `comms.calls.edit` (post-call, χαρακτηρισμός) ·
  `comms.pbx` (ρυθμίσεις/υγεία) · `comms.control` (**μελλοντικές ενέργειες**).
- **Ηχογραφήσεις: ΑΠΟΦΑΣΙΣΤΗΚΕ ΟΧΙ.** Δεν κατεβαίνουν, δεν αποθηκεύονται.
  Κρατάμε μόνο το κείμενο που παράγει το PBX. Λιγότερα προσωπικά δεδομένα στο
  CloudOn, λιγότερη έκθεση.

---

## 07 — Error / Retry Model

| Αστοχία | Απάντηση |
|---|---|
| WS αποσύνδεση | reconnect με exponential backoff (1s→60s), μετά reconcile του κενού |
| Token λήξη/401 | ανανέωση μία φορά, μετά alert |
| XAPI 5xx / timeout | 3 retries με backoff· το event μένει `received` |
| Διπλό event | UNIQUE key το απορροφά αθόρυβα |
| CloudOn downtime | reconciliation με χρονικό παράθυρο· **τίποτα δεν χάνεται** |
| Επίμονη αποτυχία | `dead` + ορατό στο dashboard υγείας |

**Το reconciliation είναι ο κανόνας, όχι το δίχτυ.** Τρέχει κάθε 2 λεπτά για το
τελευταίο 15λεπτο και κάθε βράδυ για τις 3 τελευταίες ημέρες. Το σύστημα
συγκλίνει στο σωστό ακόμη κι αν χαθεί κάθε live event.

---

## 08 — Customer Matching

### Μετρήθηκε στα πραγματικά δεδομένα

210 από 223 πελάτες έχουν τηλέφωνο — σε **47 διαφορετικές μορφές**:

```
NNNNNNNNNN            ×62     (σκέτο 10ψήφιο)
+NN.NN NNNN NNNN      ×35     (μορφή WHMCS: χώρα, τελεία, κενά)
+NN.NNN NNN NNNN      ×19
+NN.NNNN NNNNNN       ×9
NNNNNNNNN             ×9      (9ψήφιο — λείπει ψηφίο)
…και άλλες 42 μορφές
```

Χωρίς κανονικοποίηση, η ταύτιση θα αποτύγχανε στη συντριπτική πλειοψηφία. Αυτό
δεν είναι θεωρητικό ρίσκο — είναι η σημερινή κατάσταση της βάσης.

```
ωμός αριθμός → strip μη-ψηφίων → E.164
  0030… | +30… | 00… → +30…
  69…(10ψ) → +3069…      210…(10ψ) → +30210…
  9ψήφια → σημαία «ύποπτο», ταύτιση με suffix match
```

Αποθηκεύουμε το κανονικοποιημένο δίπλα στο αρχικό — δεν πειράζουμε τα δεδομένα
του WHMCS.

Ταύτιση με σειρά βεβαιότητας:
1. `mod_cpm_client_contacts` (kind=phone) — **υπάρχει ήδη, δεν φτιάχνουμε νέο**
   (σήμερα **άδειος** — θα γεμίσει από τις ταυτίσεις των κλήσεων)
2. `tblclients.phonenumber` κανονικοποιημένο
3. contacts του WHMCS
4. ιστορικό: ίδιος αριθμός ταυτίστηκε χειροκίνητα ξανά → μαθαίνει

Αλλιώς **Άγνωστος καλών**, με ένα κλικ «σύνδεσε με πελάτη» που **γράφει** τον
αριθμό στα στοιχεία επικοινωνίας, ώστε να μη ρωτηθεί δεύτερη φορά.

---

## 09 — Productivity Model · το πιο σημαντικό κομμάτι

### Υπάρχον πρόβλημα που πρέπει να λυθεί πρώτα

Σήμερα, στο `call_log`:

```php
if ($mins9 > 0 && $taskId9) { Db::addTime(...); }
out([... 'billNeedsTask' => $mins9 > 0 && !$taskId9 && !empty($in['billable'])]);
```

**Ο χρόνος κλήσης καταγράφεται ΜΟΝΟ αν δημιουργηθεί εργασία.** Μια κλήση 20
λεπτών χωρίς task δεν υπάρχει πουθενά στον χρόνο της ημέρας. Το ίδιο το API το
παραδέχεται με το `billNeedsTask`.

### Η λύση: δύο είδη χρόνου, ένα άθροισμα

| | Πού ζει | Τι είναι |
|---|---|---|
| **Communication time** | `mod_cpm_calls.talk_seconds` | ο χρόνος στο ακουστικό |
| **Execution time** | `mod_cpm_timelogs` | η δουλειά μετά την κλήση |

Η «Μέρα» αθροίζει **και τα δύο**, από διαφορετικές πηγές. Έτσι:

```
10:00–10:20  κλήση            20΄ communication
10:20–11:00  διερεύνηση       40΄ execution
             ───────────────────────────────
             σύνολο           60΄   (όχι 80΄)
```

**Δεν σπρώχνουμε ποτέ τα λεπτά της κλήσης μέσα σε timelog εργασίας.** Αυτό
ακριβώς θα προκαλούσε το διπλομέτρημα του §12 — και είναι ο λόγος που ο
σημερινός κώδικας απαιτεί task για να μετρήσει χρόνο. Το διορθώνουμε
αντιστρέφοντας τη σχέση: ο χρόνος υπάρχει από μόνος του, η εργασία είναι
προαιρετική συνέχεια.

---

## 10 — Cost Model

Σήμερα υπάρχει **ένα** `cost_per_hour` για όλη την εταιρεία (module setting).

Επέκταση: `mod_cpm_cost_rates` ανά άνθρωπο με `valid_from`, ώστε το ιστορικό
κόστος να μένει σωστό όταν αλλάξει μισθός.

```
Κόστος κλήσης = (talk_seconds/3600) × rate(admin, ημερομηνία κλήσης)
Κόστος γραμμής = mod_cpm_calls.pbx_cost            (από το 3CX, ξεχωριστά)
```

Τα δύο **δεν αθροίζονται σιωπηλά** — είναι διαφορετικά πράγματα: ο χρόνος του
ανθρώπου και το τέλος του παρόχου. Εμφανίζονται χωριστά.

---

## 11 — Billing Model

Ο χαρακτηρισμός γίνεται στο ανθρώπινο στρώμα (`mod_cpm_interactions`):
`billable | non-billable | contract | internal | warranty` + λόγος.

Ο χρεώσιμος χρόνος κλήσης περνά από την **υπάρχουσα** μηχανή `Time::push()` —
την ίδια που καλύπτει προαγορά/προσφορά — ώστε να μην υπάρξει δεύτερος δρόμος
χρέωσης. Η `Time::` δέχεται ήδη `clientHint`, που είναι ακριβώς αυτό που
χρειάζεται μια κλήση χωρίς έργο.

**Το AI προτείνει, ο άνθρωπος αποφασίζει.** Καμία αυτόματη χρέωση.

---

## 12 — AI Model

```
3CX Transcription + Summary + Sentiment   (έτοιμα, από το PBX)
        ↓
CloudOn AI: δομεί σε Αίτημα / Ενέργεια / Επόμενο βήμα / κατηγορία / πρόταση χρέωσης
        ↓
Εμφανίζεται ως «Προτάθηκε από το CloudOn Agent»
        ↓
Ο χειριστής επιβεβαιώνει ή αλλάζει  →  οριστική εγγραφή
```

---

## 31 — Απόφαση αρχιτεκτονικής

| | Τι δίνει | Γιατί όχι / ναι |
|---|---|---|
| **A** WS + REST | live UX, ενέργειες | **Όχι.** Χωρίς ιστορικό, κάθε downtime = μόνιμη απώλεια |
| **B** CDR + REST | αξιόπιστο ιστορικό | **Όχι.** Μηδέν real-time· δεν υπάρχει κάρτα εισερχόμενης |
| **C** WS + REST + CDR | και τα δύο | Κοντά, αλλά ο χάρτης του PBX μένει χειροκίνητος |
| **D** + Configuration API | ✔ | **ΠΡΟΤΕΙΝΕΤΑΙ** |

### Γιατί D — και με ποια κατανομή αρμοδιοτήτων

Το λάθος που πρέπει να αποφύγουμε είναι να ζητήσουμε από ένα API να τα κάνει όλα.

- **Call Control WebSocket = μόνο εμπειρία.** Κάρτα εισερχόμενης, ζωντανός
  χρόνος, άνοιγμα post-call. Δεν γράφει ποτέ λογιστική εγγραφή.
- **XAPI `CallLogData` = μόνη πηγή αλήθειας.** Έχει `CdrId`, οριστικές
  διάρκειες, κόστος, λόγο τερματισμού, transcript. Ό,τι καταλήγει σε χρέωση
  βγαίνει από εδώ.
- **XAPI `CallHistoryView` = τα legs.** Transfers και ουρές λύνονται με
  `SegmentId`/`ParticipantId`, όχι με ερμηνεία των live events.
- **Configuration API = ο χάρτης.** Extensions, ουρές, DID, trunks
  συγχρονίζονται μόνα τους. Κανένας δεν πληκτρολογεί extensions σε δύο μέρη.

**Transfers:** μία `mod_cpm_calls` (το `MainCallHistoryId`), πολλά
`mod_cpm_call_segments`. Ο χρόνος κάθε χειριστή = το άθροισμα των δικών του
segments — έτσι μια κλήση που πέρασε από τρεις ανθρώπους χρεώνει σωστά τον καθένα.

**Χαμένα events:** το reconciliation δεν ρωτά «τι έχασα;» — ξαναδιαβάζει το
παράθυρο και κάνει upsert. Δεν χρειάζεται να ξέρει τι έχασε.

---

## 33 — Φάσεις (αναθεωρημένες)

Προστίθεται **Φάση 0**, που δεν υπήρχε στο αρχικό αίτημα και είναι η πιο κρίσιμη:

| Φάση | Τι | Γιατί |
|---|---|---|
| **0** | **Credentials + πάγωμα συμβολαίου** | token → `GetVersionType`, `/callcontrol/swagger.json`, δείγμα `CallLogData`. **Χωρίς αυτό σχεδιάζουμε στα τυφλά** |
| 1–2 | Σύνδεση + auth (`Client.php`) | |
| 3 | Configuration sync (Users/Queues/DID) + χάρτης DN→χειριστής | |
| 5 | **Ιστορικό πρώτα**, όχι το WebSocket | Δίνει αξία από μέρα 1 χωρίς νέα υποδομή |
| 6–7 | Normalization + domain model | |
| 8–9 | Ταύτιση πελάτη / χειριστή | |
| 4 | **WebSocket μετά** | Θέλει systemd worker — ξεχωριστή απόφαση |
| 10–13 | Timeline, ημέρα, post-call, tasks | |
| 14–15 | Κόστος + χρέωση | |
| 16–17 | Dashboards + analytics | |
| 18–19 | CloudOn Agent + AI | |
| 20 | Ενέργειες προς 3CX | Ξεχωριστό cap, ξεχωριστή απόφαση |

**Η αλλαγή σειράς 4↔5 είναι σκόπιμη.** Το ιστορικό δίνει αμέσως «πόσες κλήσεις,
πόσος χρόνος, ποιος πελάτης» χωρίς μόνιμη διεργασία. Το real-time είναι η
γυαλάδα· το ιστορικό είναι η ουσία.

---

## Κατάσταση υλοποίησης

| Φάση | Κατάσταση |
|---|---|
| **0** Credentials + πάγωμα συμβολαίου | **έτοιμη** — λείπουν μόνο τα credentials |
| **1–2** Σύνδεση + auth | **έγινε** (`lib/Pbx3cx/Client.php`) |
| 3+ | αναμονή έγκρισης συμβολαίου |

**Αποφάσεις που κλείδωσαν (19/09/2026):**

1. Οθόνη ρυθμίσεων → **έγινε**: Σύστημα → Διασύνδεση 3CX.
2. Άδεια AI → **ναι**, άρα καταναλώνουμε `Transcription`/`Summary` του 3CX.
3. Μόνιμη διεργασία → **επιτρέπεται**, άρα WebSocket στη Φάση 4 (μετά το ιστορικό).
4. Ηχογραφήσεις → **ΔΕΝ κατεβαίνουν**. Δεν υλοποιείται `DownloadRecording`,
   δεν αποθηκεύεται αρχείο ήχου. Κρατάμε μόνο κείμενο (transcript/summary) που
   παράγει το ίδιο το PBX. Το πεδίο `recording_storage_id` **αφαιρείται** από το
   μοντέλο του §04.
5. Κόστη → **ευέλικτα**, από την ίδια οθόνη, ανά χειριστή, με ισχύ από ημερομηνία.

## Τι χρειάζομαι για να προχωρήσω

1. **3CX credentials** — Client ID + Secret από 3CX Admin → Integrations →
   API, με ρόλο **read-only**.
2. **Άδεια/έκδοση** — επιβεβαίωση ότι η άδειά μας δίνει Call Control API και AI
   transcription (Φάση 0 το απαντά σε 10 λεπτά με token).
3. **Απόφαση υποδομής** — επιτρέπεται μόνιμη διεργασία (systemd) για WebSocket;
   Αν όχι, μένουμε σε XAPI polling και το real-time γίνεται «κάθε 30 δευτερόλεπτα».
4. **Απόφαση ηχογραφήσεων** — τις κατεβάζουμε; Ποιος τις ακούει; Πόσο μένουν;
5. **Κόστος ανά άνθρωπο** — ποιος τα ορίζει και ποιος τα βλέπει.
