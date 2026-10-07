<?php
/**
 * CloudOn Billing — έλεγχος συμφωνίας WHMCS ↔ cob_*.
 * Η Φάση 0 κλείνει μόνο όταν εδώ βγαίνουν ΜΗΔΕΝ διαφορές: πλήθη, ποσά ανά κατάσταση,
 * υπόλοιπα ανά πελάτη και κάθε τιμολόγιο ένα-προς-ένα.
 */

namespace CloudOn\Billing;

final class Verify
{
    private $rows = [];
    private $diffs = 0;

    private function check($what, $whmcs, $cob, $money = false)
    {
        $ok = $money ? abs((float) $whmcs - (float) $cob) < 0.005 : (string) $whmcs === (string) $cob;
        if (!$ok) { $this->diffs++; }
        $this->rows[] = [$ok ? 'OK ' : 'ΔΙΑΦΟΡΑ', $what, $money ? number_format((float) $whmcs, 2, '.', '') : $whmcs,
            $money ? number_format((float) $cob, 2, '.', '') : $cob];
    }

    public function run()
    {
        /* ── πλήθη ── */
        $pairs = [
            ['πελάτες', 'SELECT COUNT(*) FROM tblclients', 'SELECT COUNT(*) FROM cob_clients WHERE whmcs_id IS NOT NULL'],
            ['προϊόντα', 'SELECT COUNT(*) FROM tblproducts', 'SELECT COUNT(*) FROM cob_products WHERE whmcs_id IS NOT NULL'],
            ['υπηρεσίες', 'SELECT COUNT(*) FROM tblhosting', "SELECT COUNT(*) FROM cob_services WHERE source = 'service'"],
            ['addons', 'SELECT COUNT(*) FROM tblhostingaddons', "SELECT COUNT(*) FROM cob_services WHERE source = 'addon'"],
            ['τιμολόγια', 'SELECT COUNT(*) FROM tblinvoices', 'SELECT COUNT(*) FROM cob_invoices WHERE whmcs_id IS NOT NULL'],
            /* Μόνο γραμμές ΥΠΑΡΚΤΩΝ τιμολογίων: το WHMCS κρατά γραμμές από διαγραμμένα τιμολόγια
               (βλ. «ορφανές» πιο κάτω) που δεν εμφανίζει πουθενά — δεν μεταφέρονται. */
            ['γραμμές τιμολογίων', 'SELECT COUNT(*) FROM tblinvoiceitems it JOIN tblinvoices i ON i.id = it.invoiceid', 'SELECT COUNT(*) FROM cob_invoice_items WHERE whmcs_id IS NOT NULL'],
            ['κινήσεις πληρωμών', 'SELECT COUNT(*) FROM tblaccounts', 'SELECT COUNT(*) FROM cob_payments WHERE whmcs_id IS NOT NULL'],
            ['κινήσεις πίστωσης', 'SELECT COUNT(*) FROM tblcredit', 'SELECT COUNT(*) FROM cob_credit_log WHERE whmcs_id IS NOT NULL'],
        ];
        foreach ($pairs as [$w, $a, $b]) { $this->check('πλήθος ' . $w, Db::val($a), Db::val($b)); }

        /* ── ποσά ── */
        $w = [];
        foreach (Db::all('SELECT status, SUM(total) t, COUNT(*) n FROM tblinvoices GROUP BY status') as $r) { $w[$r['status']] = $r; }
        $c = [];
        foreach (Db::all('SELECT status, SUM(total) t, COUNT(*) n FROM cob_invoices GROUP BY status') as $r) { $c[$r['status']] = $r; }
        foreach (array_unique(array_merge(array_keys($w), array_keys($c))) as $st) {
            $this->check('τιμολόγια «' . $st . '» σύνολο', $w[$st]['t'] ?? 0, $c[$st]['t'] ?? 0, true);
        }
        $this->check('γραμμές τιμολογίων σύνολο', Db::val('SELECT SUM(it.amount) FROM tblinvoiceitems it JOIN tblinvoices i ON i.id = it.invoiceid'), Db::val('SELECT SUM(amount) FROM cob_invoice_items'), true);
        $orph = Db::one('SELECT COUNT(*) n, SUM(it.amount) s FROM tblinvoiceitems it LEFT JOIN tblinvoices i ON i.id = it.invoiceid WHERE i.id IS NULL');
        $this->rows[] = ['ΣΗΜ.', 'ορφανές γραμμές WHMCS (τιμολόγιο σβησμένο) — δεν μεταφέρονται', (int) $orph['n'] . ' γραμμές', number_format((float) $orph['s'], 2, '.', '')];
        $this->check('πληρωμές εισερχόμενα', Db::val('SELECT SUM(amountin) FROM tblaccounts'), Db::val('SELECT SUM(amount_in) FROM cob_payments'), true);
        $this->check('πληρωμές εξερχόμενα', Db::val('SELECT SUM(amountout) FROM tblaccounts'), Db::val('SELECT SUM(amount_out) FROM cob_payments'), true);
        $this->check('πληρωμές προμήθειες', Db::val('SELECT SUM(fees) FROM tblaccounts'), Db::val('SELECT SUM(fees) FROM cob_payments'), true);
        $this->check('πιστώσεις (κινήσεις)', Db::val('SELECT SUM(amount) FROM tblcredit'), Db::val('SELECT SUM(amount) FROM cob_credit_log'), true);
        $this->check('πίστωση πελατών (υπόλοιπο)', Db::val('SELECT SUM(credit) FROM tblclients'), Db::val('SELECT SUM(credit) FROM cob_clients'), true);
        $this->check('ενεργές υπηρεσίες — μηνιαίο ποσό', Db::val("SELECT SUM(amount) FROM tblhosting WHERE domainstatus = 'Active'"),
            Db::val("SELECT SUM(amount) FROM cob_services WHERE source = 'service' AND status = 'Active'"), true);

        /* ── ανά πελάτη: ανεξόφλητο + πίστωση ── */
        $bad = 0;
        $wu = [];
        foreach (Db::all("SELECT userid, SUM(total) t FROM tblinvoices WHERE status = 'Unpaid' GROUP BY userid") as $r) { $wu[(int) $r['userid']] = (float) $r['t']; }
        $cu = [];
        foreach (Db::all("SELECT c.whmcs_id u, SUM(i.total) t FROM cob_invoices i JOIN cob_clients c ON c.id = i.client_id WHERE i.status = 'Unpaid' GROUP BY c.whmcs_id") as $r) { $cu[(int) $r['u']] = (float) $r['t']; }
        foreach (array_unique(array_merge(array_keys($wu), array_keys($cu))) as $u) {
            if (abs(($wu[$u] ?? 0) - ($cu[$u] ?? 0)) >= 0.005) { $bad++; }
        }
        $this->check('πελάτες με διαφορά ανεξόφλητου', 0, $bad);
        $bad = (int) Db::val('SELECT COUNT(*) FROM tblclients w JOIN cob_clients c ON c.whmcs_id = w.id WHERE ABS(w.credit - c.credit) >= 0.005');
        $this->check('πελάτες με διαφορά πίστωσης', 0, $bad);

        /* ── κάθε τιμολόγιο ένα-προς-ένα (πελάτης, ποσό, κατάσταση, ημερομηνία) ── */
        $bad = (int) Db::val("SELECT COUNT(*) FROM tblinvoices w
            LEFT JOIN cob_invoices i ON i.whmcs_id = w.id LEFT JOIN cob_clients c ON c.id = i.client_id
            WHERE i.id IS NULL OR c.whmcs_id <> w.userid OR ABS(i.total - w.total) >= 0.005 OR i.status <> w.status
               OR NOT (i.date <=> NULLIF(w.date, '0000-00-00'))");
        $this->check('τιμολόγια που δεν ταιριάζουν 1-προς-1', 0, $bad);
        $bad = (int) Db::val("SELECT COUNT(*) FROM tblhosting w LEFT JOIN cob_services s ON s.source = 'service' AND s.whmcs_id = w.id
            LEFT JOIN cob_clients c ON c.id = s.client_id
            WHERE s.id IS NULL OR c.whmcs_id <> w.userid OR ABS(s.amount - w.amount) >= 0.005 OR s.status <> w.domainstatus OR s.cycle <> w.billingcycle");
        $this->check('υπηρεσίες που δεν ταιριάζουν 1-προς-1', 0, $bad);

        return ['rows' => $this->rows, 'diffs' => $this->diffs];
    }
}
