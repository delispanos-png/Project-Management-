# Αναβάθμιση WHMCS — τι έχουμε αλλάξει και τι πρέπει να ελεγχθεί μετά

**Τρέχουσα έκδοση:** WHMCS 9.0.6-release.1
**PHP:** 8.3 (`/opt/plesk/php/8.3/bin/php`)
**Πρότυπο πελάτη:** `horn` (τρίτου κατασκευαστή) · **Φόρμα παραγγελίας:** `horn`
**Διαδρομή admin:** `cloudonadminpanel` (`customadminpath` στο `configuration.php`)
**Κώδικας:** GitHub private — `delispanos-png/Project-Management-`, κλάδος `master`

---

## 1. Η πιο σημαντική πληροφορία

**Δεν έχει τροποποιηθεί κανένα αρχείο πυρήνα του WHMCS.** Επαληθεύτηκε με σύγκριση
όλου του ιστορικού git από το baseline (19/07/2026) μέχρι σήμερα: καμία αλλαγή σε
`includes/` (πλην `includes/hooks/`), `admin/`, `vendor/`, `resources/` ή στα php
αρχεία της ρίζας.

Πρακτικά αυτό σημαίνει ότι **η αναβάθμιση δεν θα σβήσει λειτουργικότητα** — θα
σβήσει μόνο ό,τι πατάει σε συμβάσεις που ενδέχεται να αλλάξουν (ονόματα hooks,
συναρτήσεις, δομή προτύπου).

---

## 2. Τι επιβιώνει και τι κινδυνεύει

| Περιοχή | Επιβιώνει; | Κίνδυνος |
|---|---|---|
| `includes/hooks/*.php` | ✅ τα αρχεία μένουν | ⚠️ **μπορεί να αλλάξουν ονόματα/παράμετροι hooks** |
| `modules/gateways/vivapayments*` | ✅ | ⚠️ αλλαγές στο API των gateway modules |
| `modules/servers/*`, `modules/addons/*` (δικά μας) | ✅ | ⚠️ αλλαγές στο API των modules |
| `lang/overrides/*.php` | ✅ | χαμηλός |
| `templates/horn/**` | ⚠️ **μόνο αν δεν ενημερωθεί το πρότυπο** | 🔴 **ο μεγαλύτερος κίνδυνος** |
| Δικοί μας φάκελοι ρίζας (`projectmanagement/`, `remote/`, `project/`) | ✅ | κανένας |
| Πίνακες `mod_*` στη βάση | ✅ | κανένας |
| Ρυθμίσεις στη βάση (gateways, αρίθμηση) | ✅ | ⚠️ μπορεί να επανέλθουν προεπιλογές |

**Το μεγάλο ρίσκο είναι το `horn`.** Είναι πρότυπο τρίτου κατασκευαστή με
**23 αρχεία τροποποιημένα από εμάς**. Αν το ενημερώσεις με νέα έκδοση του
κατασκευαστή, **όλες οι δικές μας αλλαγές χάνονται**. Δες την ενότητα 4.

---

## 3. Τι έχουμε προσθέσει — αναλυτικά

### 3.1 Hooks (`includes/hooks/`)

Δικά μας αρχεία και τι θα σπάσει αν αλλάξει το hook που χρησιμοποιούν:

| Αρχείο | Hooks που χρησιμοποιεί | Τι κάνει | Αν σπάσει |
|---|---|---|---|
| `paypal_surcharge.php` | `InvoiceChangeGateway`, `InvoiceCreated`, `ClientAreaFooterOutput` | Χρέωση διεκπεραίωσης PayPal (5,4% + 0,35 € με αναγωγή) | Χάνεται η ανάκτηση προμήθειας PayPal — **οικονομική διαρροή** |
| `viva_reconcile.php` | `ClientAreaPage`, `AfterCronJob` | Άμεσος έλεγχος & συμφωνία πληρωμών Viva | **Πληρωμές δεν καταχωρούνται** — κρίσιμο |
| `cart_badge.php` | `ClientAreaPage`, `ClientAreaPageCart`, `ClientAreaPageHome`, `ClientAreaPageLogin` | Μετρητής προϊόντων στο εικονίδιο καλαθιού | Απλώς δεν φαίνεται ο αριθμός |
| `hz_home_shortcuts.php` | `ClientAreaSecondarySidebar` | Συντομεύσεις αρχικής | Αισθητικό |
| `hz_dashboard_panel.php`, `hz_dashboard_data.php` | `ClientAreaHomepagePanels` κ.ά. | Πίνακας ελέγχου πελάτη | Αισθητικό |
| `hz_auto_accept_orders.php` | `AfterShoppingCartCheckout` | Αυτόματη αποδοχή παραγγελιών | Παραγγελίες μένουν σε αναμονή |
| `hz_terminate_on_status.php` | αλλαγή κατάστασης υπηρεσίας | Τερματισμός υπηρεσιών | Υπηρεσίες δεν τερματίζονται |
| `hz_ticket_priority.php`, `hz_menu_availability.php`, `hz_location_filter.php`, `hz_service_icons.php`, `hz_homepage_categories.php`, `hz_mobile_css.php`, `hz_a11y_forms.php`, `hz_nav_a11y.php` | διάφορα ClientArea | Προσαρμογές εμφάνισης/ροής | Αισθητικό |
| `mask_admin_passwords.php` | admin | Απόκρυψη κωδικών στο admin | Ασφάλεια — εμφανίζονται κωδικοί |

**`example.php`, `index.php`, `zz_debug_productsave.php`** δεν είναι δικά μας ή
είναι διαγνωστικά· μπορούν να αγνοηθούν.

### 3.2 Modules που φτιάξαμε

| Module | Τύπος | Πίνακες βάσης | Σημείωση |
|---|---|---|---|
| `vivapayments` | gateway | `mod_viva_orders`, `mod_viva_tokens`, `mod_viva_log` | Πλήρες κύκλωμα Viva Smart Checkout |
| `scaleway` | server + addon | `mod_scaleway_*` | Φτιαγμένο, **δεν έχει δοκιμαστεί ζωντανά** |
| `hetznercloud`, `hetznerstorage` | server + addon | `mod_hetzner_*` | Ενεργό σε παραγωγή |
| `cloudonprojects` | addon | `mod_cpm_*` (60+ πίνακες) | Το Project Management |
| `supportcontracts` | addon | `mod_supportcontracts_*` | Τράπεζα χρόνου υποστήριξης |
| `gooddaysync` | addon | `mod_gooddaysync_*` | Συγχρονισμός GoodDay |
| `staffboard`, `servicesfee`, `ai_copilot`, `bulkpricingupdater` | addon | διάφοροι | |

### 3.3 Αρχεία γλώσσας

`lang/overrides/greek.php` και `lang/overrides/english.php` — περιέχουν κλειδιά με
πρόθεμα `cnp_`:

- `cnp_remote_nav` — «Απομακρυσμένη υποστήριξη» στο μενού
- `cnp_afm_*` — άντληση στοιχείων από ΑΑΔΕ στη φόρμα εγγραφής
- `cnp_cf_<id>` — μεταφράσεις custom fields (το WHMCS **δεν** τα μεταφράζει μόνο του)

⚠️ **Μην γράψεις ποτέ `$_LANG['customfield']['1']`** — το κλειδί `customfield`
προϋπάρχει ως **string** και η ανάθεση δείκτη προκαλεί **fatal error** (λευκή σελίδα).

### 3.4 Δικοί μας φάκελοι εκτός WHMCS

| Φάκελος | Τι είναι |
|---|---|
| `projectmanagement/` | Η εφαρμογή Project Management (SPA) |
| `project/` | Alias που σερβίρει το παραπάνω |
| `remote/` | Δημόσια σελίδα λήψης εργαλείου απομακρυσμένης υποστήριξης |
| `cloudonadminpanel/` | Το admin (μετονομασμένο) |
| `sys_cron_k7m2/` | Ο φάκελος cron (μετονομασμένος) |
| `tpl_cache_k7m2/` | Cache προτύπων (μετονομασμένο) |

⚠️ Οι μετονομασμένοι φάκελοι (`cloudonadminpanel`, `sys_cron_k7m2`, `tpl_cache_k7m2`)
ορίζονται στο `configuration.php`. **Μετά την αναβάθμιση επιβεβαίωσε ότι το
`configuration.php` δεν αντικαταστάθηκε.**

---

## 4. Το πρότυπο `horn` — διάβασέ το πριν κάνεις οτιδήποτε

23 αρχεία του προτύπου έχουν δικές μας αλλαγές. Οι σημαντικότερες:

| Αρχείο | Τι αλλάξαμε | Αν χαθεί |
|---|---|---|
| `header.tpl` | Μετρητής καλαθιού (`cnp-cart-link`, `cnp-cart-badge`) | Χάνεται ο μετρητής |
| `assets/layout/menu.tpl` | Στοιχείο «Απομακρυσμένη υποστήριξη» | Χάνεται από το μενού |
| `login.tpl` | Δίγλωσσο, λογότυπο, προβολή εγγραφής | Επιστρέφει στα αγγλικά |
| `clientregister.tpl` | Νομική μορφή + άντληση ΑΑΔΕ | **Χάνεται η αυτόματη συμπλήρωση ΑΦΜ** |
| `css/custom.css` | Όλες οι προσαρμογές εμφάνισης + μετρητής καλαθιού | Σπάει η εμφάνιση |
| `css/tokens.css`, `css/foundation.css` | Χρώματα/τυπογραφία | Σπάει η εμφάνιση |
| `clientareahome.tpl`, `clientareaproducts.tpl` | Πίνακας ελέγχου, λίστα υπηρεσιών | Επιστρέφουν στο αρχικό |
| `viewticket.tpl`, `supportticket*.tpl` | Ροή αιτημάτων | Επιστρέφει στο αρχικό |
| `invoicepdf.tpl`, `quotepdf.tpl` | Μορφή PDF | Επιστρέφει στο αρχικό |
| `knowledgebase*.tpl` | Βάση γνώσης | Επιστρέφει στο αρχικό |

**Διαδικασία όταν έρθει νέα έκδοση του horn:**

1. `git commit` όλα πριν ξεκινήσεις — καθαρό working tree.
2. Αντίγραφο: `cp -a templates/horn templates/horn.backup-ΗΗΜΜΕΕ`
3. Εγκατάσταση της νέας έκδοσης του horn.
4. `git diff templates/horn` → δείχνει **ακριβώς** τι έσβησε ο κατασκευαστής.
5. Επανάφερε τις δικές μας αλλαγές μία-μία από το git.

Το git είναι εδώ ο σωτήρας: **κάθε δική μας αλλαγή είναι σε commit** με ελληνικό
μήνυμα που εξηγεί το γιατί.

---

## 5. Ρυθμίσεις βάσης που πρέπει να επαληθευτούν

Αυτές δεν είναι σε αρχεία — ζουν στη βάση και ενδέχεται να επανέλθουν σε
προεπιλογή μετά από αναβάθμιση.

### 5.1 Τρόποι πληρωμής

| Gateway | Ρυθμίσεις που πρέπει να υπάρχουν |
|---|---|
| `vivapayments` | `clientId`, `clientSecret`, `merchantId`, `apiKey`, `sourceCode` (**3425**), `environment=Παραγωγή`, `disableCash=on` |
| `banktransfer` | `instructions` (IBAN Alpha + Eurobank), όνομα |
| `paypal` | κανονικές ρυθμίσεις PayPal |

⚠️ Τα `clientSecret` και `apiKey` είναι **κρυπτογραφημένα**. Αν χαθούν, πρέπει να
ξαναμπούν από το Viva portal — το Client Secret **δεν ανακτάται**, θέλει νέα
δημιουργία credentials.

⚠️ Το πεδίο `instructions` της τραπεζικής κατάθεσης **δεν γράφεται από τη γραμμή
εντολών** — δοκιμάστηκαν 4 τρόποι. Μόνο μέσα από τη φόρμα του admin.

### 5.2 Αρίθμηση παραστατικών

| Ρύθμιση | Τιμή |
|---|---|
| `SequentialInvoiceNumbering` | `1` |
| `SequentialInvoiceNumberFormat` | `{YEAR}{NUMBER}` |
| `TaxCustomInvoiceNumbering` | `1` |
| `TaxCustomInvoiceNumberFormat` | `PF{YEAR}{NUMBER}` |

⚠️ **Οι μετρητές (`SequentialInvoiceNumberValue`, `TaxNextCustomInvoiceNumber`)
είναι λογιστικά κρίσιμοι.** Σημείωσέ τους πριν την αναβάθμιση και επιβεβαίωσέ τους
μετά. Αν πάνε πίσω, θα εκδοθούν διπλοί αριθμοί παραστατικών.

### 5.3 Λοιπά

| Ρύθμιση | Τιμή |
|---|---|
| `Template` / `OrderFormTemplate` | `horn` / `horn` |
| `Language` | `greek` |
| `TaxEnabled` | `on` (ΦΠΑ 24%, τύπος Exclusive) |
| `AutoRedirectoInvoice` | `gateway` |

### 5.4 Cron

```
*/5 * * * * (/opt/plesk/php/8.3/bin/php -f '.../sys_cron_k7m2/cron.php') > /dev/null
```
Χρήστης: `cloudon.gr_chbouao8z8` (Plesk). Ο φάκελος είναι **μετονομασμένος** —
αν η αναβάθμιση επαναφέρει `crons/`, διόρθωσε τη γραμμή.

---

## 6. Λίστα ελέγχου μετά την αναβάθμιση

Με σειρά προτεραιότητας. Τα 🔴 είναι αυτά που κοστίζουν χρήματα ή σπάνε πωλήσεις.

### 🔴 Πληρωμές — έλεγξε πρώτα

- [ ] Άνοιξε ανεξόφλητο τιμολόγιο → εμφανίζονται και οι 3 μέθοδοι πληρωμής;
- [ ] Επίλεξε **Viva** → πάει στη σελίδα πληρωμής της Viva με λογότυπο CloudOn;
- [ ] Πλήρωσε **1 €** δοκιμαστικά → το τιμολόγιο γίνεται Paid μέσα σε δευτερόλεπτα;
- [ ] Στο ταμείο, επίλεξε **PayPal** → ανεβαίνει το σύνολο με τη χρέωση διεκπεραίωσης;
- [ ] Ολοκλήρωσε παραγγελία με PayPal → το τιμολόγιο έχει **μία** γραμμή χρέωσης και σωστό σύνολο;
- [ ] Δοκίμασε **επιστροφή χρημάτων** από το admin (κουμπί Refund).

Αν οι πληρωμές Viva δεν καταχωρούνται:
```
/opt/plesk/php/8.3/bin/php modules/gateways/vivapayments/crons/reconcile.php
```
Αν αυτό δουλεύει αλλά η αυτόματη καταχώρηση όχι → έσπασε το `viva_reconcile.php`
(πιθανότατα άλλαξε το hook `ClientAreaPage` ή `AfterCronJob`).

### 🔴 Παραγγελίες

- [ ] Πρόσθεσε προϊόν στο καλάθι → εμφανίζεται ο **μετρητής** στο εικονίδιο;
- [ ] Ολοκλήρωσε παραγγελία → γίνεται **αυτόματη αποδοχή**; (`hz_auto_accept_orders`)
- [ ] Δημιουργείται η υπηρεσία στο Hetzner;

### 🟠 Εγγραφή & γλώσσα

- [ ] Φόρμα εγγραφής: εμφανίζεται η επιλογή **Ιδιώτης / Επιχείρηση**;
- [ ] Με ΑΦΜ επιχείρησης → **συμπληρώνονται αυτόματα** επωνυμία/διεύθυνση/ΔΟΥ;
- [ ] Εναλλαγή EL/EN στη σύνδεση δουλεύει;
- [ ] Τα custom fields εμφανίζονται στα ελληνικά;

### 🟠 Μενού & σελίδες

- [ ] Στο μενού πελάτη υπάρχει **«Απομακρυσμένη υποστήριξη»**;
- [ ] `https://my.cloudon.gr/remote` φορτώνει και κατεβάζει ZIP;
- [ ] `https://my.cloudon.gr/project/` (Project Management) φορτώνει;

### 🟡 Εμφάνιση

- [ ] Πίνακας ελέγχου πελάτη με τα πλακίδια;
- [ ] Λίστα υπηρεσιών με εικονίδια και σωστές καταστάσεις;
- [ ] Εμφάνιση σε κινητό (μενού, καλάθι, τιμολόγια);
- [ ] PDF τιμολογίου με το σωστό λογότυπο και στοιχεία;

### ⚪ Τεχνικά

- [ ] `SequentialInvoiceNumberValue` και `TaxNextCustomInvoiceNumber` **δεν πήγαν πίσω**;
- [ ] Το cron τρέχει (Utilities → System → System Health);
- [ ] Δεν υπάρχουν σφάλματα στο `/var/www/vhosts/system/my.cloudon.gr/logs/error_log`;

---

## 7. Πριν την αναβάθμιση — υποχρεωτικά

```bash
cd /var/www/vhosts/cloudon.gr/my.cloudon.gr

# 1. Καθαρό git
git status                    # πρέπει να είναι clean
git add -A && git commit -m "Πριν την αναβάθμιση WHMCS" && git push

# 2. Αντίγραφο βάσης
mysqldump -u USER -p DBNAME | gzip > /root/whmcs-pre-upgrade-$(date +%F).sql.gz

# 3. Αντίγραφο αρχείων που δεν είναι στο git
cp -a configuration.php /root/configuration.php.bak
crontab -l -u cloudon.gr_chbouao8z8 > /root/crontab-pre-upgrade.bak

# 4. Κατέγραψε τους μετρητές παραστατικών
/opt/plesk/php/8.3/bin/php -r 'require "init.php";
 foreach (["SequentialInvoiceNumberValue","TaxNextCustomInvoiceNumber"] as $s)
  echo "$s = ".WHMCS\Database\Capsule::table("tblconfiguration")->where("setting",$s)->value("value")."\n";'

# 5. Έλεγχος υγείας ΠΡΙΝ, για σύγκριση
/opt/plesk/php/8.3/bin/php scripts/healthcheck.php > /root/health-before.txt
```

---

## 8. Γνωστές παγίδες αυτής της εγκατάστασης

Πράγματα που μας κόστισαν ώρες. Μην τα ξαναψάξεις.

| Παγίδα | Λεπτομέρεια |
|---|---|
| **Το horn δεν χρησιμοποιεί τον navbar του WHMCS** | Το μενού είναι hardcoded στο `assets/layout/menu.tpl`. Τα hooks `ClientAreaPrimaryNavbar` **δεν κάνουν τίποτα**. |
| **Το horn δεν εμφανίζει προσαρμογές καλαθιού** | Το `CartTotalAdjustment` αλλάζει το σύνολο **αόρατα**. Γι' αυτό η χρέωση PayPal γίνεται με JavaScript. |
| **`updateInvoiceTotal()` δεν ξαναϋπολογίζει το υποσύνολο** | Γραμμές που αθροίζουν 11,92 άφηναν υποσύνολο 10,79. Χρησιμοποιούμε δικό μας `cnp_invoice_recalc()`. |
| **`encrypt()` παράγει μορφή `2$…`** | Το `getGatewayVariables()` διαβάζει μόνο την παλιά μορφή `hex.hex`. Πεδία gateway **δεν γράφονται από CLI**. |
| **Η βάση είναι utf8mb3** | **Μην αποθηκεύεις emoji** — γίνονται `????`. Μόνο κείμενο. |
| **`preg_split` χωρίς `/u`** | Κόβει σε **bytes** και σπάει τα ελληνικά (το `·` συγκρούεται με το «η»). |
| **Το radio του ταμείου δεν πυροδοτεί `change`** | Το πρότυπο το επιλέγει μέσω JS. Η χρέωση PayPal ελέγχει με χρονομετρητή. |
| **Cron ανά λεπτό ρίχνει τον server** | Δοκιμάστηκε: φορτίο 15, cron στο 75% CPU. **Μην βάλεις ξανά.** |
| **Endpoints Viva** | Δημιουργία παραγγελίας → `api.vivapayments.com`. Επιστροφές, κατάσταση παραγγελίας, κλειδί webhook → `www.vivapayments.com` με Basic auth. |
| **Πηγή πληρωμής Viva μέσω API** | Δημιουργείται ελλιπής — **δεν δέχεται λογότυπο**. Φτιάξ' την από τη φόρμα του portal. |

---

## 9. Ανοιχτά θέματα (Ιούλιος 2026)

- **Webhook Viva**: δεν φτάνει ποτέ. Πιθανή αιτία: ο λογαριασμός Viva είναι κοινός
  με το RxVision, το οποίο κρατά τη θέση webhook. Δεν είναι κρίσιμο — η εξόφληση
  γίνεται σε 1-2" από τον άμεσο έλεγχο.
- **Scaleway**: το module είναι έτοιμο αλλά **δεν έχει δοκιμαστεί με πραγματικά
  κλειδιά**. Ο addon δεν είναι ενεργοποιημένος.
- **Οδηγίες τραπεζικής κατάθεσης**: παραμένουν στα αγγλικά. Θέλουν επικόλληση
  δίγλωσσου κειμένου από τη φόρμα του admin.
- **Ποσοστό PayPal**: ρυθμισμένο σε 5,4% + 0,35 € από παλινδρόμηση σε 27
  συναλλαγές. Χρειάζεται επαλήθευση με πραγματικές πληρωμές — αν το πραγματικό
  κόστος είναι χαμηλότερο, **πρέπει να μειωθεί** (η προσαύξηση δεν επιτρέπεται
  να ξεπερνά το κόστος).
