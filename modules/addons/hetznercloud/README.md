# Hetzner Cloud για WHMCS (white-label)

Πλήρες σύστημα μίσθωσης & διαχείρισης cloud servers (και storage boxes) με backend
το Hetzner Cloud API — **χωρίς ο πελάτης να βλέπει ποτέ το όνομα "Hetzner"**.

## Τι περιλαμβάνει

| Module | Τύπος | Ρόλος |
|--------|-------|------|
| `modules/addons/hetznercloud` | Addon | Κέντρο ελέγχου: API token, branding, αυτόματη τιμολόγηση (markup %), availability dashboard, import υπαρχόντων υπηρεσιών, ημερήσιο cron sync |
| `modules/servers/hetznercloud` | Provisioning | Cloud servers: create/suspend/unsuspend/terminate/resize + πλήρες white-label control panel (power, reboot, rebuild, console VNC, γραφήματα, rDNS, snapshots, reset password) |
| `modules/servers/hetznerstorage` | Provisioning | Storage Boxes: create/suspend/terminate + client area (στοιχεία σύνδεσης, reset password) |

## Εγκατάσταση (βήμα-βήμα)

1. **API token**: Hetzner Cloud Console → επίλεξε/φτιάξε project → Security → API Tokens →
   δημιούργησε token με **Read & Write**.

2. **Ενεργοποίηση addon**: WHMCS Admin → *System Settings → Addon Modules* →
   «Hetzner Cloud Control Centre» → **Activate** → **Configure**:
   - `Hetzner API Token`: επικόλλησε το token
   - `Brand Name`: π.χ. `Cloudon Cloud` (αυτό βλέπει ο πελάτης)
   - `Default Markup %`: π.χ. `40`
   - `Fully Automatic Pricing`: ✓ (το cron ενημερώνει τιμές μόνο του)
   - Απόθήκευση. Δώσε πρόσβαση στους admin roles.

3. **Server entry** (μία εγγραφή = ένα Hetzner project/token): WHMCS Admin →
   *System Settings → Servers* → **Add New Server**:
   - Name: `Hetzner` (εσωτερικό)
   - Module: **Cloud Servers** (hetznercloud)
   - **Access Hash**: βάλε ξανά το ίδιο API token (ανά-project token)
   - Test Connection → πρέπει «Successful».

4. **Προϊόντα**:
   - *Products/Services* → φτιάξε προϊόν, τύπος **Cloud Servers** module, ανάθεσε στον server.
   - Στο tab *Module Settings* διάλεξε Server Type / OS / Location / δικαιώματα πελάτη.

5. **Τιμολόγηση**: Addon → **Pricing & Mapping** → αντιστοίχισε κάθε προϊόν με ένα
   Hetzner type (+ location + markup override αν θες) → **Sync prices now**. Από εκεί
   και πέρα το ημερήσιο cron ενημερώνει αυτόματα.

6. **Ήδη πουλημένες υπηρεσίες** (adopt χωρίς recreation): Addon → **Import / Adopt** →
   κάθε ενεργή υπηρεσία ταιριάζεται αυτόματα με τον υπάρχοντα Hetzner server (μέσω IP ή
   ονόματος) → **Link**. Αμέσως αποκτά πλήρες control panel.
   > Προϋπόθεση: το προϊόν της υπηρεσίας να χρησιμοποιεί το `hetznercloud` module.
   > Αν σήμερα πουλάς μέσω άλλου module, άλλαξε το module type του προϊόντος σε
   > «Cloud Servers» (δεν χάνονται δεδομένα) και μετά κάνε Link.

## Πώς αποθηκεύεται η σύνδεση υπηρεσίας↔server

Αν το προϊόν έχει custom field `hetzner_server_id` χρησιμοποιείται αυτό· αλλιώς το id
αποθηκεύεται στο πεδίο *Username* της υπηρεσίας ως `hz-<id>` (fallback, δουλεύει αμέσως).

## Σημειώσεις white-label

- Όλα τα client-facing κείμενα χρησιμοποιούν το `Brand Name`.
- Το VNC console ανοίγει σε δικό μας popup (δικό μας brand). Το μόνο σημείο που τεχνικά
  φαίνεται το domain του Hetzner είναι το websocket στο devtools του browser.
- Τα ονόματα των servers στο Hetzner (`whmcs-<serviceid>`) τα βλέπει μόνο ο admin.

## Cron

Το pricing sync τρέχει στο υπάρχον WHMCS *System Cron* (DailyCronJob). Δεν χρειάζεται
ξεχωριστό cron. Χειροκίνητο sync από το Pricing tab όποτε θες.
