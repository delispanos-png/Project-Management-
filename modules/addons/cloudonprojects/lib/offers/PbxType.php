<?php
/**
 * Τύπος προσφοράς «Τηλεφωνικό κέντρο» (3CX / Yeastar) — adapter γύρω από lib/Pbx.php.
 *
 * Όλα delegate στη μηχανή· εδώ μόνο το lineItems(): κάθε ετήσιο είδος γίνεται
 * γραμμή annually, τα εφάπαξ onetime, και η έκπτωση (αν υπάρχει) μία αρνητική
 * γραμμή — ώστε quote & έγγραφο να συμφωνούν πάντα στο ποσό του 1ου έτους.
 *
 * @package WHMCS\Module\Addon\CloudonProjects\Offers
 */

namespace WHMCS\Module\Addon\CloudonProjects\Offers;

use WHMCS\Module\Addon\CloudonProjects\Pbx;

class PbxType implements OfferType
{
    public function key(): string { return 'pbx'; }
    public function label(): string { return 'Τηλεφωνικό κέντρο (3CX / Yeastar)'; }
    public function normalize(array $cfg): array { return Pbx::normalize($cfg); }
    public function amount(array $cfg): float { return Pbx::offerAmount($cfg); }
    public function docHtml(array $cfg): string { return Pbx::docHtml($cfg); }
    public function docCss(): string { return Pbx::docCss(); }

    public function summary(array $cfg): string
    {
        $r = Pbx::calc($cfg);
        $p = Pbx::platforms()[$r['cfg']['plat']]['name'];
        return 'Τηλεφωνικό κέντρο ' . $p . ' — ' . (int) $r['cfg']['ext'] . ' εσωτερικά, '
            . (int) $r['cfg']['sc'] . ' ταυτόχρονες κλήσεις';
    }

    public function lineItems(array $cfg): array
    {
        $r = Pbx::calc($cfg);
        $items = [];
        foreach ($r['lines'] as $l) {
            if (!$l['on'] || $l['qty'] <= 0 || $l['price'] <= 0) { continue; }
            $items[] = ['sku' => 'PBX-' . strtoupper($l['id']), 'desc' => $l['name'] . ($l['note'] !== '' ? ' — ' . $l['note'] : ''),
                'qty' => (float) $l['qty'], 'unit' => round($l['price'], 2),
                'cycle' => $l['rec'] ? 'annually' : 'onetime', 'taxable' => true, 'productId' => null];
        }
        if ($r['discAmt'] > 0.005) {
            $items[] = ['sku' => 'PBX-DISCOUNT', 'desc' => 'Έκπτωση προσφοράς ' . rtrim(rtrim(number_format($r['cfg']['disc'], 2, ',', '.'), '0'), ',') . '%',
                'qty' => 1.0, 'unit' => -round($r['discAmt'], 2), 'cycle' => 'onetime', 'taxable' => true, 'productId' => null];
        }
        return $items;
    }
}
