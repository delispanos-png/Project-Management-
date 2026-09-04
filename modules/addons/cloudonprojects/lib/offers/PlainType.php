<?php
/**
 * Γενική προσφορά (ελεύθερο κείμενο + ποσό) — ο απλός τύπος «plain».
 *
 * Δεν έχει configurator· τα δεδομένα έρχονται από τη γραμμή της προσφοράς
 * (title/amount/descr) και τα στοιχεία εγγράφου στο cfg['o']. Δίνει ένα λιτό,
 * μονοσέλιδο έγγραφο ώστε ΚΑΘΕ προσφορά να έχει PDF & γραμμή χρέωσης, ακόμη κι
 * όταν δεν είναι εξειδικευμένου τύπου.
 *
 * @package WHMCS\Module\Addon\CloudonProjects\Offers
 */

namespace WHMCS\Module\Addon\CloudonProjects\Offers;

class PlainType implements OfferType
{
    public function key(): string { return 'plain'; }
    public function label(): string { return 'Γενική προσφορά'; }

    public function normalize(array $cfg): array
    {
        $o = is_array($cfg['o'] ?? null) ? $cfg['o'] : [];
        return [
            'title' => (string) ($cfg['title'] ?? 'Προσφορά'),
            'amount' => round((float) ($cfg['amount'] ?? 0), 2),
            'descr' => (string) ($cfg['descr'] ?? ''),
            'o' => [
                'client' => (string) ($o['client'] ?? ''),
                'cemail' => (string) ($o['cemail'] ?? ''),
                'cphone' => (string) ($o['cphone'] ?? ''),
                'afm' => (string) ($o['afm'] ?? ''),
                'attn' => (string) ($o['attn'] ?? ''),
                'address' => (string) ($o['address'] ?? ''),
                'city' => (string) ($o['city'] ?? ''),
                'doy' => (string) ($o['doy'] ?? ''),
                'protocol' => (string) ($o['protocol'] ?? ''),
                'vat' => (float) ($o['vat'] ?? 24),
                'validDays' => (int) ($o['validDays'] ?? 30),
                'discount' => (float) ($o['discount'] ?? 0),
            ],
        ];
    }

    public function amount(array $cfg): float
    {
        $c = $this->normalize($cfg);
        return max(0, round($c['amount'] - (float) $c['o']['discount'], 2));
    }

    public function summary(array $cfg): string
    {
        $c = $this->normalize($cfg);
        return $c['title'];
    }

    public function docCss(): string
    {
        return '@page{size:A4;margin:18mm 16mm}'
            . 'body{font:13px/1.6 Arial,Helvetica,sans-serif;color:#243447}'
            . '.h{display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #0097e4;padding-bottom:10px;margin-bottom:18px}'
            . '.h img{height:34px}.h .p{font-size:11px;color:#6b7a90;text-align:right}'
            . 'h1{font-size:19px;margin:0 0 4px}.mut{color:#6b7a90;font-size:12px}'
            . '.box{border:1px solid #dfe6ef;border-radius:10px;padding:12px 16px;margin:14px 0}'
            . '.kv{display:flex;gap:8px;margin:2px 0}.kv .k{color:#6b7a90;min-width:120px}'
            . '.amt{font-size:24px;font-weight:700;color:#0b1f3a}'
            . 'table{width:100%;border-collapse:collapse;margin-top:10px}'
            . 'td,th{padding:8px 10px;border-bottom:1px solid #eef2f7;text-align:left}'
            . 'th{font-size:11px;text-transform:uppercase;letter-spacing:.3px;color:#6b7a90}'
            . '.r{text-align:right}.tot{font-weight:700;border-top:2px solid #0b1f3a}';
    }

    public function docHtml(array $cfg): string
    {
        $c = $this->normalize($cfg);
        $o = $c['o'];
        $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        $money = function ($n) { return number_format((float) $n, 2, ',', '.') . ' €'; };
        $net = $c['amount'];
        $disc = (float) $o['discount'];
        $afterDisc = max(0, $net - $disc);
        $vat = $afterDisc * ((float) $o['vat'] / 100);
        $gross = $afterDisc + $vat;
        $rows = '<tr><td>' . $e($c['title']) . '</td><td class="r">' . $money($net) . '</td></tr>';
        if ($disc > 0.005) {
            $rows .= '<tr><td>Έκπτωση</td><td class="r">-' . $money($disc) . '</td></tr>';
        }
        $rows .= '<tr><td>ΦΠΑ ' . $e((string) $o['vat']) . '%</td><td class="r">' . $money($vat) . '</td></tr>'
            . '<tr class="tot"><td>Σύνολο</td><td class="r">' . $money($gross) . '</td></tr>';
        $descr = $c['descr'] !== '' ? '<div class="box">' . nl2br($e($c['descr'])) . '</div>' : '';
        return '<div class="h"><img src="/project/doc-assets/cloudon.svg" alt="CloudOn">'
            . '<div class="p">' . ($o['protocol'] ? 'Αρ. πρωτοκόλλου ' . $e($o['protocol']) . '<br>' : '')
            . $e(date('d/m/Y')) . '</div></div>'
            . '<h1>Οικονομική Προσφορά</h1>'
            . '<div class="mut">' . $e($o['client']) . ($o['afm'] ? ' · ΑΦΜ ' . $e($o['afm']) : '') . '</div>'
            . ($o['attn'] ? '<div class="mut">Υπόψη: ' . $e($o['attn']) . '</div>' : '')
            . '<table><thead><tr><th>Περιγραφή</th><th class="r">Ποσό</th></tr></thead><tbody>'
            . $rows . '</tbody></table>'
            . $descr
            . '<p class="mut" style="margin-top:16px">Ισχύς προσφοράς: ' . (int) $o['validDays'] . ' ημέρες.</p>';
    }

    public function lineItems(array $cfg): array
    {
        $c = $this->normalize($cfg);
        $items = [['sku' => 'GENERAL', 'desc' => $c['title'], 'qty' => 1.0,
            'unit' => round((float) $c['amount'], 2), 'cycle' => 'onetime',
            'taxable' => true, 'productId' => null]];
        $disc = (float) $c['o']['discount'];
        if ($disc > 0.005) {
            $items[] = ['sku' => 'GENERAL-DISCOUNT', 'desc' => 'Έκπτωση προσφοράς', 'qty' => 1.0,
                'unit' => -round($disc, 2), 'cycle' => 'onetime', 'taxable' => true, 'productId' => null];
        }
        return $items;
    }
}
