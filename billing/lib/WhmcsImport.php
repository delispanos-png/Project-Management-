<?php
/**
 * CloudOn Billing — αντιγραφή WHMCS → cob_*.
 *
 * ΜΟΝΟ ΑΝΑΓΝΩΣΗ από τους πίνακες tbl* του WHMCS. Επαναλήψιμη: κάθε γραμμή ταυτίζεται με
 * whmcs_id (upsert), άρα δεύτερο τρέξιμο ενημερώνει, δεν διπλασιάζει. Με $dry δεν γράφει
 * τίποτα — μόνο μετρά τι θα έκανε.
 */

namespace CloudOn\Billing;

final class WhmcsImport
{
    private $dry;
    private $stats = [];
    private $map = ['client' => [], 'product' => [], 'service' => [], 'invoice' => []];

    public function __construct($dry = false)
    {
        $this->dry = (bool) $dry;
    }

    private function bump($k, $n = 1)
    {
        $this->stats[$k] = ($this->stats[$k] ?? 0) + $n;
    }

    private static function d($v)
    {
        $v = (string) $v;
        return ($v === '' || strpos($v, '0000-00-00') === 0) ? null : $v;
    }

    private function put($table, array $row, $key = 'whmcs_id')
    {
        $this->bump($table);
        return $this->dry ? 0 : Db::upsert($table, $row, $key);
    }

    public function run()
    {
        $t0 = date('Y-m-d H:i:s');
        $now = $t0;
        $pdo = Db::pdo();
        if (!$this->dry) { $pdo->beginTransaction(); }
        try {
            $this->clients($now);
            $this->products($now);
            $this->services($now);
            $this->invoices($now);
            $this->payments();
            $this->credits();
            if (!$this->dry) {
                Db::exec('INSERT INTO cob_sync_log (started_at, finished_at, mode, stats) VALUES (?,?,?,?)',
                    [$t0, date('Y-m-d H:i:s'), 'import', json_encode($this->stats)]);
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$this->dry && $pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
        return $this->stats;
    }

    private function clients($now)
    {
        $cf = [];
        foreach (Db::all('SELECT fieldid, relid, value FROM tblcustomfieldsvalues WHERE fieldid IN (1, 82)') as $r) {
            $cf[(int) $r['relid']][(int) $r['fieldid']] = (string) $r['value'];
        }
        $cur = [];
        foreach (Db::all('SELECT id, code FROM tblcurrencies') as $r) { $cur[(int) $r['id']] = $r['code']; }
        foreach (Db::all('SELECT * FROM tblclients') as $c) {
            $id = (int) $c['id'];
            $this->map['client'][$id] = $this->put('cob_clients', [
                'whmcs_id' => $id, 'uuid' => (string) $c['uuid'],
                'kind' => trim((string) $c['companyname']) !== '' ? 'company' : 'person',
                'first_name' => (string) $c['firstname'], 'last_name' => (string) $c['lastname'],
                'company' => (string) $c['companyname'], 'email' => (string) $c['email'],
                'phone' => (string) $c['phonenumber'], 'address1' => (string) $c['address1'],
                'address2' => (string) $c['address2'], 'city' => (string) $c['city'], 'state' => (string) $c['state'],
                'postcode' => (string) $c['postcode'], 'country' => substr((string) $c['country'], 0, 2),
                'vat_id' => (string) ($cf[$id][1] ?? $c['tax_id'] ?? ''), 'tax_office' => (string) ($cf[$id][82] ?? ''),
                'currency' => $cur[(int) $c['currency']] ?? 'EUR', 'credit' => (string) $c['credit'],
                'tax_exempt' => (int) $c['taxexempt'], 'status' => (string) $c['status'],
                'language' => (string) $c['language'], 'default_gateway' => (string) $c['defaultgateway'],
                'notes' => (string) $c['notes'], 'created_at' => self::d($c['datecreated']), 'synced_at' => $now,
            ]);
        }
    }

    private function products($now)
    {
        $groups = [];
        foreach (Db::all('SELECT id, name FROM tblproductgroups') as $g) { $groups[(int) $g['id']] = $g['name']; }
        $cur = [];
        foreach (Db::all('SELECT id, code FROM tblcurrencies') as $r) { $cur[(int) $r['id']] = $r['code']; }
        foreach (Db::all('SELECT * FROM tblproducts') as $p) {
            $pid = $this->put('cob_products', [
                'whmcs_id' => (int) $p['id'], 'group_name' => (string) ($groups[(int) $p['gid']] ?? ''),
                'name' => (string) $p['name'], 'type' => (string) $p['type'], 'module' => (string) $p['servertype'],
                'pay_type' => (string) $p['paytype'], 'auto_setup' => (string) $p['autosetup'],
                'taxed' => (int) $p['tax'], 'hidden' => (int) $p['hidden'], 'retired' => (int) $p['retired'],
                'synced_at' => $now,
            ]);
            $this->map['product'][(int) $p['id']] = $pid;
        }
        if ($this->dry) { return; }
        $cycles = ['monthly' => 'msetupfee', 'quarterly' => 'qsetupfee', 'semiannually' => 'ssetupfee',
            'annually' => 'asetupfee', 'biennially' => 'bsetupfee', 'triennially' => 'tsetupfee'];
        foreach (Db::all("SELECT * FROM tblpricing WHERE type = 'product'") as $pr) {
            $pid = $this->map['product'][(int) $pr['relid']] ?? null;
            if (!$pid) { continue; }
            foreach ($cycles as $cy => $setupCol) {
                if ((float) $pr[$cy] < 0) { continue; }   // -1 = ο κύκλος δεν προσφέρεται
                Db::exec('INSERT INTO cob_product_prices (product_id, currency, cycle, setup_fee, price) VALUES (?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE setup_fee = VALUES(setup_fee), price = VALUES(price)',
                    [$pid, $cur[(int) $pr['currency']] ?? 'EUR', $cy, max(0, (float) $pr[$setupCol]), $pr[$cy]]);
                $this->bump('cob_product_prices');
            }
        }
    }

    private function services($now)
    {
        $opts = [];
        foreach (Db::all('SELECT hc.relid, o.optionname, s.optionname AS sub, hc.qty
                FROM tblhostingconfigoptions hc JOIN tblproductconfigoptions o ON o.id = hc.configid
                LEFT JOIN tblproductconfigoptionssub s ON s.id = hc.optionid') as $o) {
            $name = explode('|', (string) $o['optionname'])[0];
            $opts[(int) $o['relid']][$name] = (int) $o['qty'] ? (int) $o['qty'] : explode('|', (string) $o['sub'])[0];
        }
        foreach (Db::all('SELECT * FROM tblhosting') as $h) {
            $cid = $this->map['client'][(int) $h['userid']] ?? 0;
            if (!$cid && !$this->dry) { $this->bump('skip_service_no_client'); continue; }
            $sid = $this->put('cob_services', [
                'whmcs_id' => (int) $h['id'], 'source' => 'service', 'client_id' => $cid,
                'product_id' => $this->map['product'][(int) $h['packageid']] ?? null, 'parent_service_id' => null,
                'label' => (string) $h['domain'], 'status' => (string) $h['domainstatus'], 'cycle' => (string) $h['billingcycle'],
                'qty' => max(1, (int) $h['qty']), 'first_amount' => (string) $h['firstpaymentamount'], 'amount' => (string) $h['amount'],
                'setup_fee' => 0, 'reg_date' => self::d($h['regdate']), 'next_due' => self::d($h['nextduedate']),
                'next_invoice' => self::d($h['nextinvoicedate']), 'terminated_at' => self::d($h['termination_date']),
                'payment_method' => (string) $h['paymentmethod'], 'module_ref' => (string) $h['username'],
                'dedicated_ip' => (string) $h['dedicatedip'], 'assigned_ips' => (string) $h['assignedips'],
                'options' => isset($opts[(int) $h['id']]) ? json_encode($opts[(int) $h['id']], JSON_UNESCAPED_UNICODE) : null,
                'notes' => (string) $h['notes'], 'synced_at' => $now,
            ], 'id');
            $this->map['service'][(int) $h['id']] = $sid;
        }
        foreach (Db::all('SELECT a.*, ad.name AS addon_name FROM tblhostingaddons a LEFT JOIN tbladdons ad ON ad.id = a.addonid') as $a) {
            $cid = $this->map['client'][(int) $a['userid']] ?? 0;
            if (!$cid && !$this->dry) { $this->bump('skip_addon_no_client'); continue; }
            $this->put('cob_services', [
                'whmcs_id' => (int) $a['id'], 'source' => 'addon', 'client_id' => $cid, 'product_id' => null,
                'parent_service_id' => $this->map['service'][(int) $a['hostingid']] ?? null,
                'label' => (string) ($a['name'] ?: $a['addon_name']), 'status' => (string) $a['status'],
                'cycle' => (string) $a['billingcycle'], 'qty' => max(1, (int) $a['qty']),
                'first_amount' => (string) $a['firstpaymentamount'], 'amount' => (string) $a['recurring'],
                'setup_fee' => (string) $a['setupfee'], 'reg_date' => self::d($a['regdate']), 'next_due' => self::d($a['nextduedate']),
                'next_invoice' => self::d($a['nextinvoicedate']), 'terminated_at' => self::d($a['termination_date']),
                'payment_method' => (string) $a['paymentmethod'], 'module_ref' => '', 'dedicated_ip' => '', 'assigned_ips' => null,
                'options' => null, 'notes' => (string) $a['notes'], 'synced_at' => $now,
            ], 'id');
        }
    }

    private function invoices($now)
    {
        foreach (Db::all('SELECT * FROM tblinvoices') as $i) {
            $cid = $this->map['client'][(int) $i['userid']] ?? 0;
            if (!$cid && !$this->dry) { $this->bump('skip_invoice_no_client'); continue; }
            $this->map['invoice'][(int) $i['id']] = $this->put('cob_invoices', [
                'whmcs_id' => (int) $i['id'], 'client_id' => $cid, 'number' => (string) ($i['invoicenum'] ?: $i['id']),
                'date' => self::d($i['date']), 'due_date' => self::d($i['duedate']), 'paid_at' => self::d($i['datepaid']),
                'cancelled_at' => self::d($i['date_cancelled']), 'refunded_at' => self::d($i['date_refunded']),
                'status' => (string) $i['status'], 'subtotal' => (string) $i['subtotal'], 'credit' => (string) $i['credit'],
                'tax' => (string) $i['tax'], 'tax2' => (string) $i['tax2'], 'total' => (string) $i['total'],
                'tax_rate' => (string) $i['taxrate'], 'tax_rate2' => (string) $i['taxrate2'],
                'payment_method' => (string) $i['paymentmethod'], 'notes' => (string) $i['notes'], 'synced_at' => $now,
            ]);
        }
        foreach (Db::all('SELECT * FROM tblinvoiceitems') as $it) {
            $iid = $this->map['invoice'][(int) $it['invoiceid']] ?? 0;
            if (!$iid && !$this->dry) { $this->bump('skip_item_no_invoice'); continue; }
            $isSvc = in_array($it['type'], ['Hosting', 'Upgrade', 'Setup'], true);
            $this->put('cob_invoice_items', [
                'whmcs_id' => (int) $it['id'], 'invoice_id' => $iid, 'type' => (string) $it['type'],
                'service_id' => $isSvc ? ($this->map['service'][(int) $it['relid']] ?? null) : null,
                'ref_whmcs' => (int) $it['relid'] ?: null, 'description' => (string) $it['description'],
                'amount' => (string) $it['amount'], 'taxed' => (int) $it['taxed'], 'due_date' => self::d($it['duedate']),
            ]);
        }
    }

    private function payments()
    {
        foreach (Db::all('SELECT * FROM tblaccounts') as $a) {
            $this->put('cob_payments', [
                'whmcs_id' => (int) $a['id'], 'client_id' => $this->map['client'][(int) $a['userid']] ?? null,
                'invoice_id' => $this->map['invoice'][(int) $a['invoiceid']] ?? null, 'gateway' => (string) $a['gateway'],
                'date' => self::d($a['date']), 'description' => (string) $a['description'],
                'amount_in' => (string) $a['amountin'], 'fees' => (string) $a['fees'], 'amount_out' => (string) $a['amountout'],
                'trans_id' => (string) $a['transid'], 'refund_of' => (int) $a['refundid'] ?: null,
            ]);
        }
    }

    private function credits()
    {
        foreach (Db::all('SELECT * FROM tblcredit') as $c) {
            $cid = $this->map['client'][(int) $c['clientid']] ?? 0;
            if (!$cid && !$this->dry) { $this->bump('skip_credit_no_client'); continue; }
            $this->put('cob_credit_log', [
                'whmcs_id' => (int) $c['id'], 'client_id' => $cid, 'date' => self::d($c['date']),
                'description' => (string) $c['description'], 'amount' => (string) $c['amount'],
                'ref_invoice_whmcs' => (int) $c['relid'] ?: null,
            ]);
        }
    }
}
