# CloudOn Project Manager — τεχνικό εγχειρίδιο

Ό,τι χρειάζεται κάποιος που θα πιάσει τον κώδικα αύριο: πού ζει τι, γιατί είναι έτσι,
και ποιες παγίδες έχουν ήδη κοστίσει χρόνο.

Συμπληρωματικά αρχεία:
- **`UPGRADE.md`** — τι έχουμε αλλάξει στο WHMCS και τι πρέπει να ελεγχθεί σε κάθε αναβάθμιση.
  Ιστορικό αλλαγών με το *γιατί*.
- **Οδηγός χρήσης** — μέσα στην εφαρμογή (`projectmanagement/help.js`), για τον χειριστή.

---

## 1. Τι είναι και πού τρέχει

Μια **standalone εφαρμογή διαχείρισης** πάνω στο WHMCS 9.0.6 της `my.cloudon.gr`.
Δεν είναι θέμα του WHMCS ούτε σελίδα admin: είναι δικό της SPA που δανείζεται τη
συνεδρία και τη βάση του WHMCS.

| | |
|---|---|
| Διεύθυνση | `https://my.cloudon.gr/project/` |
| Κώδικας SPA | `projectmanagement/` (εκτός του WHMCS tree) |
| Λογική & δεδομένα | `modules/addons/cloudonprojects/` (κανονικό WHMCS addon) |
| PHP | `/opt/plesk/php/8.3/bin/php` |
| Βάση | η βάση του WHMCS, πίνακες `mod_cpm_*` (77) |
| Μέγεθος | ~28.000 γραμμές — 10.500 στο `api.php`, 12.000 στα views |

Ο λόγος του χωρισμού: το `projectmanagement/` σερβίρεται απευθείας από τον web server
και **δεν περνά από το ionCube ούτε από τα templates του WHMCS**, οπότε δουλεύει με
κανονικά αρχεία που μπορείς να διορθώσεις χωρίς να ξαναχτίσεις τίποτα. Το addon υπάρχει
για ό,τι πρέπει να ζει μέσα στο WHMCS: δικαιώματα ρόλων, hooks, cron, εγκατάσταση πινάκων.

### Σημεία εισόδου

| Αρχείο | Τι κάνει |
|---|---|
| `index.php` | Το κέλυφος του SPA. Ελέγχει συνεδρία, φορτώνει τα js/css με cache-busting από `filemtime`. |
| `api.php` | **Όλη η λογική.** Ένα `switch ($action)` με ~260 ενέργειες. |
| `boot.php` | Απομονωμένο bootstrap· `pm_admin_id()`, `pm_mint_token()` για δοκιμές. |
| `share.php` | Δημόσια προβολή έργου με υπογεγραμμένο token — **χωρίς credentials**. |
| `meet.php` | Τηλεδιάσκεψη P2P (WebRTC mesh) με διαμοιρασμό οθόνης. |
| `apply.php` | Δημόσια σελίδα καριέρας, δίγλωσση. Γράφει στο `mod_cpm_cv`. |
| `afm.php` | Δημόσιο endpoint άντλησης στοιχείων επιχείρησης από ΑΑΔΕ. |

---

## 2. Αρχιτεκτονική του SPA

Δεν υπάρχει framework — ούτε build step. Καθαρά ES modules που μοιράζονται βοηθούς
μέσω ενός **αντικειμένου `window.CNP`**.

```
app.js      κέλυφος, δρομολογητής, μενού, task modal, κοινοί βοηθοί → window.CNP
views2.js   λίστα, ημερολόγιο, χρόνος, προσφορές, CRM, πελάτης 360°, κερδοφορία,
            πληρωμές, ομάδες, έργα, προφίλ, κωδικοί
views3.js   tickets (inbox), ρυθμίσεις
views4.js   συντομεύσεις, πλάνο ημέρας, γνώση, chat, ανάλυση ριζών, standup,
            βιβλιοθήκη, το πλάνο μου, προσλήψεις, καταγραφή κλήσης
views5.js   gantt, αναστολές, απόδοση, departments, modules
views6.js   προαγορά χρόνου, δραστηριότητα, παράπονα
help.js     ο οδηγός χρήσης (το «user manual»)
i18n.js     μεταφράσεις EL/EN
```

### Ο κανόνας του `window.CNP`

Κάθε views αρχείο ξεκινά με destructuring:

```js
const {S, api, esc, toast, setTop, cnpDenied, cnpCan, I, $, $$} = window.CNP;
const R = window.R;
```

**Αν προσθέσεις κοινό βοηθό, πρέπει να τον βάλεις και στο `window.CNP` του `app.js`
ΚΑΙ στο destructuring κάθε αρχείου που τον χρησιμοποιεί.** Αλλιώς σκάει σιωπηλά με
`ReferenceError` μόνο όταν φτάσει η εκτέλεση εκεί. Έχει συμβεί τρεις φορές.

### Δρομολόγηση

Κάθε οθόνη είναι μια συνάρτηση `R.<όνομα>`. Το hash `#/board/12` καλεί `R.board(12)`.
Μερικές είναι getters (`Object.assign(window.R, {get board(){…}})`) — μη ψάχνεις
`R.board =` και συμπεράνεις ότι λείπει.

### Νέο αρχείο views

Πρέπει να μπει σε **δύο** σημεία του `index.php`: στη λίστα `filemtime` (cache-busting)
και σε `<script type="module">`. Αν ξεχάσεις το πρώτο, οι χρήστες βλέπουν παλιό κώδικα.

---

## 3. Δικαιώματα — ενότητες και δυνατότητες

Το σημαντικότερο υποσύστημα. Τρεις κανόνες που δεν πρέπει να σπάσουν:

1. **Το μενού ΕΙΝΑΙ τα δικαιώματα.** Κάθε ενότητα του μενού κρατά ένα δικαίωμα με το
   ίδιο όνομα. Ο διαχειριστής τσεκάρει «Πελάτες» και εμφανίζεται η ενότητα «Πελάτες».
2. **Κάθε ενότητα σπάει σε δυνατότητες** (42 σήμερα): μία ανά οθόνη (`kind: screen`),
   συν όσες ενέργειες έχουν σοβαρή συνέπεια (`kind: power`).
3. **Η ομάδα κρατά τα δικαιώματα, οι χρήστες μπαίνουν στην ομάδα.** Δεν υπάρχει
   ξεχωριστή οντότητα «ρόλος».

### Πώς αποθηκεύεται

`mod_cpm_teams.areas` (TEXT) — csv που μπορεί να περιέχει:
- `projects` → **ΟΛΗ** η ενότητα, και ό,τι προστεθεί σε αυτήν αύριο
- `projects.board` → μόνο αυτή η δυνατότητα

Η `cnp_perm_clean()` **μαζεύει** πίσω στο κλειδί της ενότητας όταν έχουν τσεκαριστεί
όλες οι δυνατότητές της — αλλιώς η ομάδα θα έμενε πίσω σε κάθε νέα προσθήκη.

### Οι συναρτήσεις

| | |
|---|---|
| `cnp_area_defs()` | Οι 8 ενότητες με δικαίωμα (+2 χωρίς: «Τα δικά μου», «Η ομάδα») |
| `cnp_caps()` | Το μητρώο των 42 δυνατοτήτων: `[kind, όνομα, περιγραφή, προϋπόθεση]` |
| `cnp_action_cap($action)` | Ενέργεια API → δυνατότητα. `'α\|β'` = φτάνει η μία |
| `cnp_has_cap($id,$full,$cap)` | Ο έλεγχος. Δέχεται και σκέτη ενότητα |
| `cnp_admin_caps($id,$full)` | Όλες οι δυνατότητες ενός χειριστή — πάει στο `boot` |
| `cnp_perm_clean(array)` | Καθαρισμός + μάζεμα πριν την αποθήκευση |

### Η πύλη

Πριν το `switch ($action)` στο `api.php`:

```php
$needCap = cnp_action_cap($action);
if ($needCap !== null) { … αν δεν έχεις καμία από τις «α|β» → 403 με το όνομα που λείπει }
```

**Allow-by-default**: ό,τι δεν είναι στον πίνακα είναι ελεύθερο.

> **Κανόνας που έχει σπάσει δύο φορές:** μην χαρτογραφήσεις ποτέ ενέργεια που καλείται
> από **ελεύθερη οθόνη** («Η μέρα μου», «Το πλάνο μου», standup, chat). Κάθε φόρτωση
> έτρωγε 403 και η εφαρμογή έδειχνε άδεια. Οι προσωπικές ενέργειες (`task`, `save_task`,
> `comment`, `time`, `timer_*`, `check_toggle`, `watch`) είναι **επίτηδες εκτός πύλης** —
> τις φυλάει το `Db::canSeeTask()`, που είναι στενότερο.

> **Δεύτερος κανόνας:** μη βάζεις εσωτερικό `if (!$FULL)` σε ενέργεια που έχει ήδη
> δυνατότητα. Ακυρώνει το δικαίωμα που μόλις έδωσες. Έχουν αφαιρεθεί **58** τέτοιοι
> διπλοί έλεγχοι σε τρεις γύρους.

**Μένουν μόνο για Full Administrator** οι ενέργειες που πιάνουν λογαριασμό WHMCS:
`user_save`, `user_pass`, `user_toggle`, `user_del`, `addon_access_grant`.

### Στη διεπαφή

`cnpCan('finance.suspend_do')` κρύβει ό,τι δεν μπορείς να πατήσεις. **Χωρίς αυτό το
λεπτομερές δικαίωμα είναι απλώς κουμπιά που πέφτουν σε 403.**

### Πρόσβαση στο ίδιο το addon

Δύο ανεξάρτητα πράγματα, και τα δύο απαραίτητα:
1. `tbladdonmodules` (module=cloudonprojects, setting=`access`) — λίστα roleid
2. `tbladminperms` **permid 46** = «Addon Modules» για τον ρόλο

Αν λείπει το δεύτερο, ο χειριστής πέφτει σε `accessdenied.php?permid=46` και το μήνυμα
δεν λέει τίποτα χρήσιμο.

---

## 4. Μοντέλο δεδομένων

77 πίνακες `mod_cpm_*`, όλοι στη βάση του WHMCS. Δημιουργούνται και μεταπίπτουν
**αποκλειστικά** από `Db::install()` — idempotent, τρέχει με:

```bash
php -r 'require "init.php"; require_once "modules/addons/cloudonprojects/lib/Db.php";
        \WHMCS\Module\Addon\CloudonProjects\Db::install();'
```

### Κατά ομάδα

**Έργα & εργασίες** — `projects`, `tasks`, `statuses`, `task_types`, `comments`,
`checklist`, `deps`, `activity`, `watchers`, `timelogs`, `recurring`, `expenses`,
`project_members`, `project_teams`, `project_shares`, `share_comments`, `project_todos`,
`snapshots`, `templates`, `template_steps`, `project_modules`

**Υποστήριξη** — `kb`, `ticket_cats`, `ticket_class`, `ticket_idle`, `ticket_usage`, `canned`

**Πελάτες & πωλήσεις** — `leads`, `lead_fields`, `lead_products`, `lead_tasks`,
`lead_values`, `field_values`, `fields`, `people`, `interactions`, `offers`, `campaigns`,
`campaign_leads`, `product_targets`, `client_package`, **`complaints`**, **`complaint_notes`**

**Ομάδα & πρόσβαση** — `teams`, `team_members`, `team_depts`, `prefs`, **`perm_presets`**

**Προσλήψεις** — `cv`, `cv_jobs`, `cv_comms`, `cv_job_views`

**Υποδομή** — `notifications`, `chat`, `chat_groups`, `chat_reads`, `events`, `event_rsvp`,
`library`, `vault`, `files`, `storage`, `automations`, `auto_log`, `reminders`,
`deadline_alerts`, `remote_sessions`, `client_remote`, `rtc_peers`, `rtc_msgs`,
`suspend_actions`, `suspend_notices`, `support_packages`, `afm_cache`, `afm_hits`,
`todos`, `worknote`

### Σχέσεις που δεν είναι προφανείς

- **`tasks.project_id` είναι nullable.** Μια εργασία μπορεί να ζει χωρίς έργο (γεννήθηκε
  από ticket ή τηλεφώνημα). Ο βοηθός `cnp_pn()` δίνει «Χωρίς έργο» — 13 σημεία τον
  χρησιμοποιούν. Μη γράψεις κώδικα που υποθέτει έργο.
- **`tasks.dept_id` → `tblticketdepartments`.** Τα department **δεν** έχουν δικό μας
  πίνακα: είναι τα ticket departments του WHMCS. Έχει ξαναγίνει το λάθος ενός τρίτου
  μητρώου (`mod_cpm_units`) και χρειάστηκε να διαγραφεί.
- **Ομάδα ≠ department.** Το department είναι *πού απευθύνεται* το αίτημα· η ομάδα είναι
  *ποιοι το αναλαμβάνουν*. Σχέση many-to-many μέσω `team_depts`.
- **`mod_cpm_statuses` έχει στήλη `title`, όχι `name`.** Το `pluck('name','id')` σκάει με 500.

---

## 5. Χρόνος, κάλυψη και χρέωση

Η πιο μπλεγμένη αλυσίδα, γιατί περνά από τρία modules.

```
καταχώρηση χρόνου σε task
   └─ Db::addTime()                    γράφει mod_cpm_timelogs
       └─ Time::push($entryId, $clientHint)
           ├─ βρίσκει πελάτη: έργο → ticket → clientHint
           ├─ ScDb::addWork()          worklog του supportcontracts
           └─ Cover::draw()            ΠΟΙΑ ΠΗΓΗ ΠΛΗΡΩΝΕΙ
               ├─ 1. εγκεκριμένη προσφορά του έργου (offer_id + covered_minutes)
               ├─ 2. προαγορά (mod_supportcontracts_clients.balance_minutes)
               └─ 3. ακάλυπτο
```

**Το υπόλοιπο δεν πάει ποτέ αρνητικό.** Αρνητικό υπόλοιπο κρύβει το τι πρέπει να
τιμολογηθεί· ο ακάλυπτος χρόνος είναι λίστα από την οποία βγαίνει προσφορά.

**Μερική άντληση**: αν μένουν 10΄ και η χρέωση είναι 30΄, παίρνει τα 10΄ και τα 20΄
μένουν ακάλυπτα — δεν μένουν ορφανά λεπτά σε καμία πηγή.

Το `$clientHint` υπάρχει γιατί μια εργασία γεννημένη από **τηλεφώνημα** δεν έχει ούτε
έργο ούτε ticket· χωρίς αυτό ο χρεώσιμος χρόνος έμενε αχρέωτος.

Στρογγυλοποίηση: `charge_step_minutes` (15΄) και `min_charge_hours` του **supportcontracts**.

---

## 6. Ενσωματώσεις

| Με τι | Πώς | Πού |
|---|---|---|
| **WHMCS** | `localAPI()` για OpenTicket/AddClient/SendEmail· απευθείας Capsule για τα υπόλοιπα | παντού |
| **supportcontracts** | Το μητρώο προαγοράς. Φορτώνεται δυναμικά, `Time::scReady()` ελέγχει | `lib/Time.php`, `lib/Cover.php` |
| **Claude API** | Αξιολόγηση βιογραφικών, προσχέδια απαντήσεων, ταξινόμηση tickets | `ai_api_key`, μοντέλο `cv_ai_model` |
| **Hetzner S3** | Αποθήκευση αρχείων· abstraction με local fallback | `lib/Storage.php` |
| **ΑΑΔΕ RgWsPublic2** | Άντληση επωνυμίας/διεύθυνσης/ΔΟΥ από ΑΦΜ (SOAP 1.2) | `lib/Aade.php`, `afm.php` |
| **RustDesk / Guacamole** | Απομακρυσμένες συνδέσεις | ρυθμίσεις `rustdesk_*`, `guac_*` |
| **WebRTC** | Τηλεδιάσκεψη P2P, χωρίς server μέσου | `meet.php`, `rtc_peers`, `rtc_msgs` |

---

## 7. Cron

| Πότε | Τι | Αρχείο |
|---|---|---|
| κάθε 5΄ | αυτόματη αξιολόγηση νέων αιτήσεων | `crons/cv_autoeval.php` |
| κάθε 10΄ | σφυγμός: υπενθυμίσεις, προθεσμίες, αδρανή tickets | `crons/pulse.php` |
| κάθε 15΄ | μεταφορά βιογραφικών σε S3 + καθάρισμα | `crons/cv_migrate_s3.php` |
| 07:30 | ημερήσιο: κλιμακώσεις, αυτόματο κλείσιμο tickets | `crons/daily.php` |
| Παρ 18:00 | εβδομαδιαία αναφορά προαγοράς | `crons/prepaid_report.php weekly` |
| 1η μηνός 08:00 | μηνιαία αναφορά προαγοράς | `crons/prepaid_report.php monthly` |

Τα τέσσερα πρώτα σε `/etc/cron.d/cloudonprojects` με `flock`. Τα δύο τελευταία στο
crontab του root.

> Το `supportcontracts/crons/weekly_report.php` **αντικαταστάθηκε** από το
> `prepaid_report.php` (μηνιαίο + εβδομαδιαίο, ομαδοποίηση ανά έργο, γνώση της κάλυψης).
> Η εγγραφή `/etc/cron.d/supportcontracts-weekly` αφαιρέθηκε και η ρύθμιση
> `weekly_report` μπήκε `off`. **Μην το ξαναβάλεις** — θα σταλεί δεύτερο email.

Δοκιμή χωρίς αποστολή: `CPM_DRY=1 php crons/prepaid_report.php monthly`

---

## 8. Παγίδες που έχουν ήδη κοστίσει

**Η βάση είναι utf8mb3.** Emoji γίνονται `????`. Μη γράφεις emoji σε στήλες — μόνο σε
κώδικα και σε ό,τι πάει απευθείας στον browser.

**Το `views3.js` διαβάζεται ως binary.** Πάντα `grep -a`, αλλιώς νομίζεις ότι είναι άδειο.

**`save_project` ξαναχτίζει ΟΛΑ τα πεδία από το input.** Μερική κλήση μηδενίζει
προϋπολογισμό, ημερομηνίες, υπεύθυνο. Για μερική ενημέρωση φτιάξε ξεχωριστή ενέργεια
(δες `project_pm_notes`).

**Δήλωση `function` ανάμεσα σε `case`** δεν εκτελείται ποτέ → «undefined function» στην
πρώτη κλήση. Οι βοηθοί πάνε **πριν** το `switch`.

**Το `<button>` κεντράρει το κείμενό του.** Φαίνεται μόνο όταν σπάσει σε δύο γραμμές.
Τα κουμπιά-επικεφαλίδες θέλουν ρητό `text-align:left`.

**CSS μεταβλητή που δεν ορίζεται** αποτυγχάνει σιωπηλά — το χρώμα πέφτει στο
κληρονομούμενο. Το `--info` χρησιμοποιούνταν επί μήνες χωρίς να υπάρχει.

**Το WHMCS 9 επιτρέπει αλλαγή γραμμών τιμολογίου μόνο σε Draft.**
`$_ADMINLANG['invoices']['immutableModification']`.

**Οι απαντήσεις σε tickets κρατούν ΟΝΟΜΑ, όχι id.** Χρησιμοποίησε `cnp_admin_by_name()`.

**Λογαριασμοί-ρομπότ**: `cnp_is_bot()`. Χωρίς αυτό, ειδοποιήσεις και λίστες ομάδας
γεμίζουν με «CloudOn Support Team Team».

**Το `.catch(() => null)` καταπίνει το μήνυμα του server.** Χρησιμοποίησε
`.catch(e => ({ok: false, error: e && e.message}))` και δείξε το με `cnpDenied(err)`.

---

## 9. Πώς προσθέτεις

### Νέα οθόνη

1. `cnp_caps()` → νέα δυνατότητα `ενότητα.όνομα` με `kind: 'screen'`
2. `cnp_action_cap()` → οι ενέργειές της
3. `app.js` → στοιχείο στο `groups` με το κλειδί της δυνατότητας ως 4ο στοιχείο
4. `app.js` → σύντομη ετικέτα στο `SHORT` (κάτω μπάρα κινητού)
5. `R.<όνομα>` σε ένα views αρχείο
6. `help.js` → κεφάλαιο **και** εγγραφή στο `VIEW_TO_HELP`
7. `i18n.js` → αγγλική απόδοση

### Νέα δυνατότητα-ενέργεια

`cnp_caps()` με `kind: 'power'` και **`needs`** (η οθόνη που προϋποθέτει), ώστε η καρτέλα
της ομάδας να τα τσεκάρει μαζί. Μετά κρύψε το κουμπί με `cnpCan()`.

### Νέος πίνακας

Μόνο μέσα στο `Db::install()`, με `hasTable`/`hasColumn`. Ποτέ χειροκίνητο ALTER —
δεν θα υπάρχει στο επόμενο περιβάλλον.

---

## 10. Έλεγχος

Δεν υπάρχουν unit tests· ο έλεγχος γίνεται με **πραγματικό browser** μέσω Playwright.

```bash
# διακριτικό για δοκιμή ως συγκεκριμένος χειριστής
php -r 'require "init.php"; require_once "projectmanagement/boot.php"; echo pm_mint_token(2, 3600);'
# → https://my.cloudon.gr/project/?t=<TOKEN>

cd /opt/cloudon-visual-qa
NODE_PATH=/opt/cloudon-visual-qa/node_modules node <script>
```

Ο κανόνας: **36 οθόνες × 2 ρόλους, μηδέν σφάλματα κονσόλας**, πριν από κάθε commit που
αγγίζει το SPA. Οι έλεγχοι δηλώνουν ισχυρισμούς πάνω στο DOM, όχι screenshots.

Συντακτικός έλεγχος:
```bash
/opt/plesk/php/8.3/bin/php -l αρχείο.php
$(ls /opt/plesk/node/*/bin/node | tail -1) --check αρχείο.js
```

### Καθάρισε πίσω σου

Ό,τι φτιάχνεις για δοκιμή — προσωρινοί χειριστές, ομάδες, συμβόλαια, εργασίες,
καταχωρήσεις χρόνου — **σβήσ' το**. Είναι σύστημα παραγωγής· δοκιμαστικά δεδομένα
μπερδεύουν τους ανθρώπους που το χρησιμοποιούν, και οι προσωρινοί λογαριασμοί
εμφανίζονται ως συνδεδεμένο προσωπικό.
