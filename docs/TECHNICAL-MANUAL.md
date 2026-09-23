# CloudOn Project Manager — τεχνικό εγχειρίδιο υποδομής

Τι τρέχει πού, πώς συνδέεται, τι κάνεις όταν χαλάσει.

Για μηχανικό που αναλαμβάνει τη συντήρηση — **όχι** για τους χειριστές· εκείνοι
έχουν τον οδηγό χρήσης μέσα στην εφαρμογή (`projectmanagement/help.js`).

Οι αριθμοί είναι μετρημένοι στις **23/09/2026**. Όπου αλλάζουν, αλλάζει και η
εντολή που τους βγάζει — δίπλα σε κάθε πίνακα.

| | |
|---|---|
| Host | `my.cloudon.gr` (Plesk) |
| WHMCS | **9.0.8-release.1** |
| PHP | **8.3.33** — `/opt/plesk/php/8.3/bin/php` (ionCube· το σκέτο `php` ΔΕΝ κάνει) |
| DB | `mycloudondb`, **utf8mb3** |
| Repo | `delispanos-png/Project-Management-` (private) |

---

## 1. Αρχιτεκτονική

```
WHMCS 9.0.8  (init.php → Capsule, tbl*)
   │
   ├── modules/addons/cloudonprojects/     ΤΟ MODULE — όλη η λογική
   │     ├── cloudonprojects.php           entry του WHMCS addon
   │     ├── hooks.php                     hooks του WHMCS
   │     ├── lib/                          22 κλάσεις (Db, Time, Notify, Catalog…)
   │     ├── lib/Pbx3cx/                   διασύνδεση 3CX
   │     ├── crons/                        4 cron scripts
   │     └── templates/                    admin UI του addon
   │
   └── projectmanagement/                  ΤΟ SPA — /project/
         ├── boot.php     bootstrap + auth (χωρίς WHMCS session)
         ├── api.php      439 ενέργειες JSON — ΟΛΟ το backend του SPA
         ├── index.php    το κέλυφος
         ├── app.js       κορμός: router, helpers, window.CNP
         ├── views2…11.js οι οθόνες
         ├── help.js      ο οδηγός χρήσης (62 κεφάλαια)
         ├── meet.php     CloudOn Meet (WebRTC)
         └── sw.js        service worker (Web Push)
```

**Η λογική ζει στο module, όχι στο SPA.** Το `api.php` καλεί `lib/*` — δεν
ξαναγράφει κανόνες. Ό,τι πρέπει να ισχύει και από cron ή από το admin UI του
WHMCS μπαίνει σε κλάση του `lib/`, ποτέ μόνο στο `api.php`.

### Διαχωρισμός πάνελ

| Panel | Τι ανήκει εκεί |
|---|---|
| **projectmanagement** (`/project/`) | έργα, εργασίες, CRM, tickets, τηλεφωνία, προσωπικό |
| **cloudonadminpanel** (WHMCS addons) | υπηρεσίες, VM, χρεώσεις, τιμολόγηση |

Ο κανόνας τηρείται και αντίστροφα: το PM **δείχνει** ειδοποιήσεις για υπηρεσίες
(π.χ. «Ακυρώσεις υπηρεσιών» στην κάρτα της ημέρας) αλλά **δεν ενεργεί** πάνω
τους. Η μόνη γραφή του PM σε υποδομή είναι το καθάρισμα αντιστοίχισης VM μετά
από διαγραφή, και ζει στο `modules/servers/hetznercloud/lib/Helper.php`.

### Το SPA μοιράζεται helpers μόνο μέσω `window.CNP`

Το `app.js` εξάγει **75** helpers· κάθε `views*.js` τους παίρνει με destructuring.
Helper που χρησιμοποιείται χωρίς import είναι **runtime ReferenceError** — το
`node --check` δεν το πιάνει. Γι' αυτό υπάρχει
`projectmanagement/dev-check-helpers.py` (§11).

---

## 2. Βάση

**110 πίνακες `mod_cpm_*`**, συν 4 `mod_hetzner_*` του άλλου module.

```bash
mysql -N mycloudondb -e "SELECT table_name, table_rows FROM information_schema.tables
  WHERE table_schema='mycloudondb' AND table_name LIKE 'mod_cpm_%' ORDER BY table_rows DESC"
```

Οι μεγάλοι και τι κρατούν:

| Πίνακας | ~Γραμμές | Τι |
|---|---|---|
| `mod_cpm_calls` | 15.470 | **CDR του 3CX** — κλήσεις, χρόνος ομιλίας, χαρακτηρισμός |
| `mod_cpm_cv_job_views` | 3.573 | προβολές αγγελιών (δημόσιο `apply.php`) |
| `mod_cpm_notifications` | 1.530 | το καμπανάκι |
| `mod_cpm_activity` | 1.177 | ιστορικό ενεργειών |
| `mod_cpm_kb` | 931 | βάση γνώσης |
| `mod_cpm_book` / `_phones` | 621 / 730 | **τηλεφωνικός κατάλογος** — η μόνη πηγή ταύτισης καλούντος |
| `mod_cpm_storage` | 683 | μητρώο αρχείων (local + S3) |
| `mod_cpm_cv` | 520 | βιογραφικά |
| `mod_cpm_timelogs` | 352 | χρόνος σε εργασίες |
| `mod_cpm_tasks` | 136 | οι εργασίες |
| `mod_cpm_projects` | 72 | τα έργα |

### Migrations

**Δεν υπάρχει ξεχωριστό migration framework.** Όλο το σχήμα φτιάχνεται και
εξελίσσεται από `Db::install()`, που είναι **προσθετικό και επαναλήψιμο**:

```php
if (!$s->hasTable('…')) { $s->create(…); }
if (!$s->hasColumn('…', '…')) { $s->table(… add column …); }
```

Καλεί με τη σειρά: `Catalog::install()`, `Leave::install()`, `Pool::install()`,
`Catalog::unify()`. Τρέχει σε κάθε φόρτωση του addon και με:

```bash
/opt/plesk/php/8.3/bin/php -r 'define("WHMCS",true); require "init.php";
  require_once "modules/addons/cloudonprojects/lib/Db.php";
  WHMCS\Module\Addon\CloudonProjects\Db::install();'
```

> **Νέα στήλη → πάντα με `hasColumn` guard.** Χωρίς αυτό η δεύτερη εκτέλεση σκάει.

### utf8mb3 — ο κανόνας των emoji

Η σύνδεση και η βάση είναι **utf8mb3**. Τα 4-byte emoji (🆘 📎 ⚠️) γίνονται
`????` στην αποθήκευση. Το `Db::pushNotification()` τα κόβει· **κάθε νέο πεδίο
που δέχεται ελεύθερο κείμενο πρέπει να κάνει το ίδιο**:

```php
preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text)
```

Στο **UI** τα emoji είναι μια χαρά — το πρόβλημα είναι μόνο η αποθήκευση.

---

## 3. API

Όλα σε **ένα** αρχείο: `projectmanagement/api.php` (~1,2 MB, **439 ενέργειες**).
Κλήση: `api.php?a=<action>&t=<token>`, απάντηση JSON μέσω `out()`, σφάλμα μέσω
`fail($msg, $httpCode)`.

### Μοντέλο δικαιωμάτων — deny-by-default

**10 περιοχές** (= ενότητες μενού) × **91 caps**. Μια ενέργεια που δεν είναι
δηλωμένη πουθενά **απορρίπτεται**.

```
cnp_area_defs()     10 περιοχές: clients, support, comms, projects, team,
                    prepaid, reports, finance, hr, admin
cnp_caps()          91 caps, μορφή «περιοχή.κύκλωμα[.edit|.delete]»
cnp_action_cap()    ενέργεια → cap (ή «capA|capB» για εναλλακτικά)
cnp_open_actions()  104 προσωπικές ενέργειες χωρίς cap
cnp_guest_actions() ό,τι επιτρέπεται σε guest token
```

Η τριάδα είναι **Προβολή / Επεξεργασία / Διαγραφή** ανά κύκλωμα, και η
**Διαγραφή δεν κληρονομείται ποτέ** — δηλώνεται ρητά.

### Πώς προσθέτεις νέα ενέργεια

1. **Γράψε το `case`** στο `api.php`, κοντά στις συγγενικές του.
2. **Δήλωσε το cap** στο `cnp_caps()` — ή βάλ' το στο `cnp_open_actions()` αν
   είναι καθαρά προσωπική οθόνη (τα δικά μου, ο χρόνος μου).
3. **Δέσε ενέργεια → cap** με `$add('περιοχή.κύκλωμα', ['η_ενέργειά_σου'])`.
4. Αν φτιάχνεις **νέα οθόνη**: μενού στο `app.js`, κεφάλαιο στο `help.js`,
   εγγραφή στο `VIEW_TO_HELP`.
5. Αν η περιοχή είναι **καινούρια**, δήλωσέ την στο `cnp_area_defs()` — αλλιώς
   τα caps της είναι αόρατα στην οθόνη δικαιωμάτων και **δεν δίνονται σε καμία
   ομάδα** (έχει ξανασυμβεί με το `comms`).

> **Έλεγχος:** ζήτα την ενέργεια ως απλός χειριστής και δες ότι απορρίπτεται με
> κατανοητό μήνυμα, όχι με 500.

---

## 4. Auth

Το SPA **δεν μοιράζεται το session του WHMCS**: το WHMCS κάνει regenerate/destroy
τα admin sessions εκτός του admin path και θα έβγαζε τον διαχειριστή έξω.

```
WHMCS admin  ──(signed token, μία φορά)──▶  /project/?t=…
                                              │
                                      boot.php │  1. init.php με ΑΔΕΙΟ $_COOKIE
                                              │     (throwaway session, κανένα Set-Cookie)
                                              │  2. δικό του session: CNPPMSESS
                                              ▼     scoped στο /projectmanagement/
                                        pm_admin_id()
```

| Μηχανισμός | Πού | Διάρκεια |
|---|---|---|
| `pm_mint_token($adminId, $ttl)` | handoff από WHMCS | 90΄ default |
| `CNPPMSESS` | session του app | όσο το cookie |
| room token (Meet) | `boot.php` | μακρόβιο, ανά δωμάτιο |
| RSVP token | `boot.php` | ανά πρόσκληση |
| cron key | `hash_hmac('sha256','sweep.'.date('YmdHi'), pm_secret())` | 1 λεπτό |

Όλα υπογράφονται με `hash_hmac('sha256', …, pm_secret())` και ελέγχονται με
`hash_equals` (σταθερού χρόνου).

**Guests** (πελάτες σε Meet, RSVP): `$guestOk` επιτρέπει μόνο `rtc_*` μέσα στο
δωμάτιο του token, ή ό,τι είναι στο `cnp_guest_actions()`. Τίποτα άλλο.

---

## 5. Cron

`/etc/cron.d/cloudonprojects` — τρέχει ως `cloudon.gr_chbouao8z8`, κάθε εργασία
με `flock` ώστε να μην τρέχουν δύο μαζί, logs στο `/var/log/cloudonpm/`.

| Πότε | Script | Τι κάνει |
|---|---|---|
| `*/10` | `pulse.php` | προσωπικές υπενθυμίσεις που έφτασε η ώρα τους (καμπανάκι + email), σφυγμός παρουσίας, υπερβάσεις |
| `07:30` | `daily.php` | γεννά επαναλαμβανόμενες εργασίες· στέλνει το ημερήσιο digest εκπρόθεσμων/σημερινών ανά χειριστή |
| `*/5` | `cv_autoeval.php` | αυτόματη αξιολόγηση νέων αιτήσεων από το δημόσιο `apply.php` (HTTP self-call στο `cv_ai`) |
| `*/15` | `cv_migrate_s3.php` | `go 300` μεταφέρει 300 βιογραφικά/φωτο στο S3, μετά `purge` καθαρίζει τοπικά |

```bash
CPM_DRY=1 /opt/plesk/php/8.3/bin/php modules/addons/cloudonprojects/crons/daily.php
```
`CPM_DRY=1` = **dry-run**: καμία εγγραφή, κανένα email.

---

## 6. Storage

Ένα layer, δύο drivers, ένα μητρώο.

```
Storage::put() / get() / url()
   ├── driver «local» → attachments/cloudon-storage
   └── driver «s3»    → Hetzner Object Storage (bucket cloudonstorag)
                         presigned URLs, ΠΟΤΕ public bucket
μητρώο: mod_cpm_storage (driver, bucket, storage_key, metadata)
```

Ρυθμίσεις στο `tbladdonmodules` (module `cloudonprojects`): `storage_driver`,
`s3_endpoint`, `s3_region`, `s3_bucket`, `s3_key`, `s3_secret`, `s3_prefix`.

Παλιά (local) και νέα (S3) αρχεία **συνυπάρχουν** — το μητρώο ξέρει πού είναι το
καθένα, και το serving διαλέγει αυτόματα.

> **PII:** βιογραφικά, φωτογραφίες, συνημμένα πελατών. Τα buckets είναι **πάντα
> private**· σερβίρονται με presigned URL ή proxy μέσω authenticated API.

---

## 7. 3CX

`lib/Pbx3cx/` — `Client` (XAPI), `Report`, `Cdr`, `Presence`, `Route`,
`Blueprint`, `Sync`, `AiTickets`.

**ΠΟΤΕ `CallHistoryView`.** Είναι αρχείο ~123.000 γραμμών και το κατέβασμα ρίχνει
το κέντρο. Οι κλήσεις έρχονται από **`Report`**.

| Κύκλωμα | Πώς |
|---|---|
| Κλήσεις | `Report` → `mod_cpm_calls` (CDR). Χρόνος **ομιλίας** (`talk_seconds`), όχι διάρκεια |
| Ταύτιση καλούντος | **μόνο** ο τηλεφωνικός κατάλογος `mod_cpm_book` |
| Κατάλογος | **διπλή κατεύθυνση**: push άμεσα στο 3CX· pull κάθε 10΄ με σύγκριση `pbxBody` |
| Παρουσία | `Presence` — «Λείπω» από αδράνεια ΔΕΝ κατεβάζει το τηλέφωνο· `mode` = manual/meeting/all |
| Δομή κέντρου | `Blueprint.php` — τμήματα, ουρές, AI ρεσεψιόν (902) **ως κώδικας**, με plan/apply |
| Δρομολόγηση | ξεχωριστό βήμα από το apply — δεν αλλάζει μαζί με τη δομή |

Λεπτομέρειες: **`docs/CLOUDON-AGENT-3CX.md`**.

> **Fanvil 202/203/204/220:** reprovision **μόνο με έγκριση** — έχουν χειροκίνητο
> VPN/STUN που η επαναπρομήθεια σβήνει.

---

## 8. WebRTC — CloudOn Meet

`projectmanagement/meet.php`, signaling μέσω `mod_cpm_rtc_msgs` /
`mod_cpm_rtc_peers` (polling, όχι websocket — δεν χρειάζεται ξεχωριστή υπηρεσία).

ICE: `ice_stun` και `ice_turn` στις ρυθμίσεις του module. **Το TURN εκκρεμεί**
(coturn) — χωρίς αυτό η φωνή δουλεύει εντός LAN αλλά όχι πάντα από έξω.

Η σύνδεση **αυτοϊάται**: peer που χάθηκε ξαναγράφεται από τον σφυγμό· οι νεκροί
peers καθαρίζονται μετά από 120s (ήταν 40s — laptop σε sleep έβγαινε έξω).

---

## 9. Ειδοποιήσεις

Τρία κανάλια, ανεξάρτητα:

| Κανάλι | Πού | Σημείωση |
|---|---|---|
| Καμπανάκι | `mod_cpm_notifications` → `Db::pushNotification()` | κόβει τα 4-byte emoji |
| Web Push | `lib/Push.php` — VAPID ES256, χωρίς κρυπτογραφημένο payload | συνδρομές στο `mod_cpm_push_subs`, `sw.js` |
| Email | `lib/Notify.php` | **δες προειδοποίηση ↓** |

> ### ⚠ `Notify::send()` ΔΕΝ στέλνει τίποτα
> Το email module είναι **κλειστό** — η `send()` επιστρέφει `false` χωρίς να
> στείλει. Για πραγματικό email χρησιμοποίησε **`Notify::sendTo($email, …)`**.
> Αν προσθέσεις ειδοποίηση και «δεν φτάνει mail», αυτό είναι το πρώτο που
> κοιτάς.

---

## 10. Deploy & αναβάθμιση

### Deploy

```bash
cd /var/www/vhosts/cloudon.gr/my.cloudon.gr
git add -A && git commit -m "…" && git push origin master
```

Ο κώδικας τρέχει **από το ίδιο checkout** — δεν υπάρχει build step. Ο browser
παίρνει τη νέα έκδοση **μόνος του**:

```
cnp_asset_version()   filemtime των assets  →  ?v=… σε κάθε <script>
window.CNP_BUILD      το ίδιο, στη σελίδα
api «version»         επιστρέφει «build»
```

Ο σφυγμός (κάθε 12s) συγκρίνει `build` με `CNP_BUILD`· σε διαφορά κάνει ήπιο
reload όταν δεν ενοχλεί. **Τέλος στα cached παλιά JS.**

### Αναβάθμιση WHMCS

**Πριν και μετά**, και σύγκρινε:

```bash
/opt/plesk/php/8.3/bin/php scripts/healthcheck.php   # 0 = όλα καλά, 1 = σφάλμα
```

Τα βήματα, οι παρεμβάσεις μας στο WHMCS και τι να ελέγξεις: **`UPGRADE.md`**.
Κάθε νέα παρέμβαση στον πυρήνα του WHMCS **ενημερώνει και τα δύο**.

> **Staging:** εκκρεμεί πιστό αντίγραφο παραγωγής. Πριν στηθεί, εξουδετέρωσε
> email, cron, πληρωμές και Hetzner tokens — αλλιώς το staging στέλνει αληθινά
> mail και σβήνει αληθινά VM.

---

## 11. Αντιμετώπιση βλαβών

### Πρώτα βήματα

```bash
/opt/plesk/php/8.3/bin/php scripts/healthcheck.php     # τι έσπασε στις παρεμβάσεις μας
tail -50 /var/log/cloudonpm/pulse.log                  # ο σφυγμός
tail -50 /var/log/cloudonpm/daily.log                  # το ημερήσιο
tail -100 /var/www/vhosts/cloudon.gr/my.cloudon.gr/storage/logs/laravel-$(date +%F).log
```

### Έλεγχοι πριν από κάθε commit

```bash
cd projectmanagement
python3 dev-check-helpers.py    # helper χωρίς import → runtime ReferenceError
python3 dev-check-ball.py       # ο κανόνας της μπάλας, σε PHP και JS
node --check app.js             # export PATH=/opt/plesk/node/20/bin:$PATH
```

### Τυπικά συμπτώματα

| Σύμπτωμα | Συνηθέστερη αιτία |
|---|---|
| Οθόνη λευκή, console `X is not a function` | helper χωρίς destructuring από `window.CNP` → `dev-check-helpers.py` |
| «Δεν έχεις δικαίωμα» σε δική σου ενέργεια | η ενέργεια δεν δηλώθηκε σε cap ούτε στο `cnp_open_actions()` |
| Νέο cap αόρατο στην οθόνη δικαιωμάτων | η **περιοχή** του λείπει από το `cnp_area_defs()` |
| Κείμενο αποθηκεύεται με `????` | 4-byte emoji σε utf8mb3 — κόψ' τα πριν την εγγραφή |
| Email δεν φτάνει | `Notify::send()` είναι κλειστή — χρησιμοποίησε `sendTo()` |
| Ο browser δείχνει παλιό JS | `cnp_asset_version()` — έλεγξε `filemtime`, όχι cache του CDN |
| Η ίδια εργασία σε δύο ανθρώπους | παραβίαση του κανόνα της μπάλας → `dev-check-ball.py` |
| CLI PHP: «ionCube» ή class not found | έτρεξες σκέτο `php` — θέλει `/opt/plesk/php/8.3/bin/php` από το **root του WHMCS** |
| `Class "…\Route" not found` | mismatched filename (`lib/Pbx3cx/Route.php`) — θέλει ρητό `require_once` |
| Cron δεν τρέχει | `flock` κρατά παλιό lock: `ls -la /tmp/cpm-*.lock` |
| 3CX: αργεί/πέφτει | κάποιος κάλεσε `CallHistoryView` — απαγορεύεται, δες §7 |

### Χρυσός κανόνας

**Μέτρα πριν ισχυριστείς.** Κάθε «δεν δουλεύει» έχει ερώτημα SQL ή μια γραμμή
log που το επιβεβαιώνει ή το διαψεύδει. Οι μισές «βλάβες» αυτού του συστήματος
αποδείχθηκαν σωστή συμπεριφορά, και οι μισές διορθώσεις ξεκίνησαν από μέτρηση
που δεν ταίριαζε με την υπόθεση.

---

## Συγγενικά έγγραφα

| Αρχείο | Τι καλύπτει |
|---|---|
| `docs/UI-STANDARD.md` | κανόνες οθονών — fbar, σειρά, responsive, έλεγχοι |
| `docs/OFFERS-ARCHITECTURE.md` | προσφορές: τύποι → account → portal → χρεώσεις |
| `docs/POOL-ARCHITECTURE.md` | δεξαμενή εργασιών: ρόλοι, ειδικότητες, αυτόματη μοιρασιά |
| `docs/CLOUDON-AGENT-3CX.md` | 3CX σε βάθος |
| `docs/QA-2026-09-18.md` | ευρήματα ποιότητας |
| `UPGRADE.md` | αναβάθμιση WHMCS, βήμα βήμα |
| `scripts/healthcheck.php` | τι πρέπει να ισχύει μετά από κάθε αναβάθμιση |
