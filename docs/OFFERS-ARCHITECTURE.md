# Αρχιτεκτονική Προσφορών — CloudOn Project Manager

> Ζωντανό σχέδιο. Στόχος: **πολλαπλοί τύποι προσφορών** (PharmacyOne, e-commerce, …),
> ο καθένας με δικό του έγγραφο/PDF, με ενιαία ροή **αποστολή → account → portal →
> accepted → αυτόματες χρεώσεις**. Ενημερώνεται με κάθε φάση.

## 1. Αρχές

1. **Ένας τύπος = ένα plugin.** Κάθε είδος προσφοράς (PharmacyOne, e-commerce, γενική)
   υλοποιεί το **ίδιο interface**. Προσθήκη νέου τύπου = μία νέα κλάση, χωρίς αγγίγματα
   στη ροή αποστολής/portal/billing.
2. **Οι τιμές μόνο στον server.** Ο πελάτης δεν βλέπει ποτέ τιμή που δεν επικυρώθηκε
   server-side (ισχύει ήδη για PharmacyOne — καμία εφεύρεση τιμής).
3. **Το WHMCS quote = η πηγή αλήθειας** για listing/accept/decline. Εμείς από πάνω:
   branded PDF, σχόλια, tracking, αυτόματες χρεώσεις.
4. **Idempotent παντού.** Ξαναστέλνω προσφορά → δεν διπλο-δημιουργεί account/quote/χρεώσεις.
5. **Μη-καταστροφικό στο WHMCS.** Δεν πειράζουμε ποτέ λογιστικά παραστατικά αναδρομικά.

## 2. Αφαίρεση τύπου προσφοράς (`OfferType`)

Νέο interface στο `modules/addons/cloudonprojects/lib/offers/OfferType.php`:

```
interface OfferType {
  key(): string;                 // 'pharmacyone' | 'ecommerce' | 'plain'
  label(): string;               // «PharmacyOne (Soft1)»
  normalize(array $cfg): array;  // validate/κανονικοποίηση (ΠΟΤΕ δεν εμπιστεύεται client)
  amount(array $cfg): float;     // συνολικό ποσό προσφοράς (πρώτο έτος)
  summary(array $cfg): string;   // μία γραμμή περίληψη
  docHtml(array $cfg): string;   // το branded έγγραφο (HTML)
  docCss(): string;              // στυλ εγγράφου
  lineItems(array $cfg): array;  // δομημένες γραμμές χρέωσης (βλ. §3)
}
```

- **Registry** `OfferTypes::get(string $kind): OfferType` + `OfferTypes::all()`.
- `PharmacyOneType` = adapter γύρω από το υπάρχον `lib/Pharmacy.php` (μηδέν αλλαγή λογικής).
- `PlainType` = ελεύθερο κείμενο + ένα ποσό (το σημερινό `kind='plain'`).
- `EcommerceType` = νέος τύπος (Φάση 5) — δικός του κατάλογος/έγγραφο.
- Κοινό «κέλυφος» εγγράφου (logos CloudOn/συνεργάτη, cover, οικονομική πρόταση, όροι)
  σε base helper ώστε κάθε τύπος να ορίζει μόνο το «σώμα» του (reuse `lib/Cover.php`).

**Generic API actions** (dispatch by kind), με τα `pharmacy_*` ως back-compat alias:
`offer_defs`, `offer_calc`, `offer_save`, `offer_doc`, `offer_email`. Το frontend
(`views7.js`) γίνεται σταδιακά type-aware (φορτώνει τον σωστό configurator ανά kind).

## 3. Μοντέλο χρέωσης (`lineItems`) — η γέφυρα προς το billing

Κάθε τύπος επιστρέφει **δομημένες** γραμμές — μία πηγή για (α) το quote, (β) τις χρεώσεις:

```
[
  { sku:'S1-EXPRESS-BG', desc:'Soft1 Express ΒΓ — ετήσια συνδρομή',
    qty:1, unit:3350.00, cycle:'annually', taxable:true, productId:null },
  { sku:'SETUP',        desc:'Εφάπαξ εγκατάσταση/παραμετροποίηση',
    qty:1, unit:2592.00, cycle:'onetime',  taxable:true, productId:null },
  ...
]
```

- `cycle`: `onetime | monthly | quarterly | semiannually | annually | biennially`.
- `productId`: αν το είδος αντιστοιχεί σε **WHMCS product** (για provisioning υπηρεσίας)·
  αλλιώς `null` → μπαίνει ως ελεύθερη γραμμή τιμολογίου.
- `sku`: σταθερό κλειδί ανά τύπο/έκδοση → χρησιμοποιείται στο §7 mapping.

## 4. Έγγραφα/PDF ανά τύπο

- Παραγωγή PDF: υπάρχει ήδη — Chromium/Playwright (`lib/pdf-render.js`, βλ.
  `pdf-rendering-chromium` memory). Το `offer_doc` καλεί `OfferType::docHtml/docCss`.
- PharmacyOne: υπάρχον 8-σέλιδο έγγραφο (ENTERSOFTONE branding).
- E-commerce: δικό του έγγραφο (Φάση 5) — ίδιο κέλυφος, διαφορετικό σώμα/κατάλογος.

## 5. Ροή αποστολής (send pipeline) — γενικευμένη

`offer_email` (σημερινό `pharmacy_email`, γενικευμένο):

1. **Account** — `cnp_offer_ensure_client()` (✅ Φάση 1): find-by-email αλλιώς AddClient +
   αυτόματοι κωδικοί MyCloudOn. Idempotent. Σύνδεση `offer.clientid`.
2. **Quote** — `offer_ensure_quote()`: αν δεν υπάρχει, `CreateQuote` με `lineItems($cfg)`
   (γενίκευση του σημερινού `create_quote`). Έτσι φαίνεται native στο portal «Quotes».
3. **PDF email** — το branded PDF ως συνημμένο (✅ υπάρχει).
4. **Tracking** — stage new/draft → sent, `sent_at`, quote→Delivered (✅ υπάρχει).

## 6. Portal πελάτη (MyCloudOn) — Φάση 2

- **Listing/accept/decline**: native WHMCS Quotes (δωρεάν).
- **`templates/cloudon/viewquote.tpl`**: όταν το quote είναι δεμένο με προσφορά PM
  (lookup `mod_cpm_offers.quoteid`), εμφανίζει:
  - Κουμπί **«Προβολή/Λήψη PDF»** → client-authenticated endpoint που παράγει το branded
    PDF του σωστού τύπου (δικαίωμα: μόνο ο ιδιοκτήτης του quote).
  - **Νήμα σχολίων/ερωτήσεων** πελάτη ↔ ομάδας (§8 `mod_cpm_offer_comments`).
- **Ειδοποιήσεις**: νέα ερώτηση πελάτη → PM bell/push (μηχανισμός `pushNotification` ήδη
  υπάρχει). Απάντηση ομάδας → email στον πελάτη + εμφάνιση στο νήμα.

## 7. Accepted → αυτόματες χρεώσεις — Φάση 4 (η «καρδιά»)

**Απόφαση (5/9/2026):** η ετήσια συνδρομή = **recurring WHMCS service** (auto-invoice κάθε χρόνο)· το setup = εφάπαξ γραμμή.

**Σκανδάλη**: WHMCS hook **`QuoteStatusChange`** (ήδη χρησιμοποιούμε hooks). Όταν το
status ενός quote γίνει **Accepted** και είναι δεμένο με προσφορά PM:

1. **Εξασφάλιση WHMCS products** (auto-create «είδη»): πίνακας `mod_cpm_offer_products`
   (sku → productId). Αν το sku του `lineItems` δεν έχει product, δημιουργείται
   αυτόματα (localAPI `AddProduct`, σε group π.χ. «CloudOn — Auto») ώστε να μπορεί να
   κρεμαστεί υπηρεσία. Idempotent (μία φορά ανά sku).
2. **Δημιουργία υπηρεσιών/χρεώσεων**:
   - Γραμμές με `productId` → **AddOrder** (recurring services, σωστό cycle) στον πελάτη,
     με τιμή override από το `lineItems` (η προσφορά υπερισχύει του τιμοκαταλόγου).
   - Γραμμές χωρίς product (εφάπαξ) → γραμμές στο πρώτο **τιμολόγιο** της παραγγελίας.
   - Εναλλακτικά, όπου αρκεί: native «convert quote to invoice».
3. **Παρακολούθηση**: offer stage → accepted (ήδη συγχρονίζεται), δημιουργία **έργου
   παράδοσης** (`project_from_offer` υπάρχει), καταγραφή στο activity log.
4. **Ασφαλιστικές δικλείδες**: idempotent ανά quote (πίνακας «provisioned quotes»)· ποτέ
   διπλή χρέωση· αν αποτύχει το AddOrder, ειδοποίηση admin (όχι σιωπηλή αποτυχία).

> Σημείωση: το «τι γίνεται order/service vs invoice line» το ορίζει ΚΑΘΕ τύπος μέσω των
> `lineItems` (`productId`/`cycle`). Έτσι PharmacyOne και e-commerce χρεώνουν σωστά χωρίς
> ειδικό κώδικα στο billing.

## 8. Σχήμα ΒΔ (νέοι πίνακες)

- `mod_cpm_offer_comments` — `id, offer_id, quoteid, by_type(client|admin), by_id, body,
  created_at, read_by_client_at, read_by_team_at`. (Φάση 3)
- `mod_cpm_offer_products` — `id, sku UNIQUE, product_id, created_at`. (Φάση 4)
- `mod_cpm_offer_provisioned` — `quoteid UNIQUE, order_id, invoice_id, at`. (Φάση 4, anti-διπλοχρέωση)
- (υπάρχει) `mod_cpm_offer_dismissed` — tombstones για διαγραφή από pipeline.

## 9. Δικαιώματα & ασφάλεια

- Admin: δυνατότητες `clients.offers` (διαχείριση), `clients.offer_delete` (διαγραφή),
  `clients.new` (έμμεσα, δημιουργία account). Το accept→billing απαιτεί έλεγχο ιδιοκτησίας.
- Client endpoints (portal): **μόνο** για τον ιδιοκτήτη του quote (session πελάτη WHMCS)·
  το PDF/σχόλια δεν διαρρέουν σε άλλον πελάτη.
- Κωδικοί: Φάση 1 = auto-generated στο email (απόφαση χρήστη). Μελλοντικά προαιρετικά invite.

## 10. Φάσεις υλοποίησης (build order)

| Φάση | Περιεχόμενο | Κατάσταση |
|---|---|---|
| 1 | Auto-account + κωδικοί με την αποστολή | ✅ LIVE |
| 2 | Portal: quote (2a) + branded PDF στο `viewquote` + secure `offer-view.php` (2b) | ✅ |
| 3 | Σχόλια/ερωτήσεις πελάτη ↔ ομάδας (+ειδοποιήσεις) | ✅ |
| 4 | Accepted → auto products + χρεώσεις (hook `QuoteStatusChange`) | ⏳ |
| F0 | **Foundation**: `OfferType` interface + registry + PharmacyOne/Plain adapters, doc/email δρομολογημένα μέσω registry, `lineItems` έτοιμα | ✅ (η γενίκευση `create_quote` πάει με Φάση 2) |
| 5 | Νέος τύπος **E-commerce** (κατάλογος + έγγραφο) | ⏳ |

**Σειρά εκτέλεσης**: F0 (μη-καταστροφικό, ξεκλειδώνει τα υπόλοιπα) → 2 → 3 → 4 → 5.
Το `pharmacy_*` μένει λειτουργικό ως alias μέχρι να μεταφερθεί πλήρως το UI.
