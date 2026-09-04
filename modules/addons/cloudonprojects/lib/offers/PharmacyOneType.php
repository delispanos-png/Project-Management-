<?php
/**
 * Τύπος προσφοράς PharmacyOne — adapter γύρω από το lib/Pharmacy.php.
 *
 * Δεν αντιγράφει καμία λογική: όλα τα delegate στην υπάρχουσα engine, ώστε ο
 * υπολογισμός να μένει «μόνο στον server, μία πηγή». Προσθέτει μόνο το
 * lineItems() που μεταφράζει τα annual/oneoff σε γραμμές χρέωσης.
 *
 * @package WHMCS\Module\Addon\CloudonProjects\Offers
 */

namespace WHMCS\Module\Addon\CloudonProjects\Offers;

use WHMCS\Module\Addon\CloudonProjects\Pharmacy;

class PharmacyOneType implements OfferType
{
    public function key(): string { return 'pharmacyone'; }
    public function label(): string { return 'PharmacyOne (Soft1)'; }
    public function normalize(array $cfg): array { return Pharmacy::normalize($cfg); }
    public function amount(array $cfg): float { return Pharmacy::offerAmount($cfg); }
    public function docHtml(array $cfg): string { return Pharmacy::docHtml($cfg); }
    public function docCss(): string { return Pharmacy::docCss(); }

    public function summary(array $cfg): string
    {
        $c = Pharmacy::normalize($cfg);
        $ed = Pharmacy::defaultEditions()[$c['sel']] ?? null;
        $mods = count(Pharmacy::activeModules($cfg));
        return ($ed['name'] ?? 'PharmacyOne') . ' — ' . $mods . ' modules';
    }

    /**
     * Δύο (ή τρεις) γραμμές: ετήσια συνδρομή (annually), εφάπαξ στήσιμο
     * (onetime), και —αν υπάρχει— η έκπτωση. Το άθροισμα ισούται με το
     * offerAmount (πρώτο έτος μετά την έκπτωση) ώστε quote & προσφορά να
     * συμφωνούν πάντα.
     */
    public function lineItems(array $cfg): array
    {
        $res = Pharmacy::calc($cfg);
        $c = $res['cfg'];
        $sel = (int) $c['sel'];
        $ed = Pharmacy::defaultEditions()[$sel] ?? ['key' => 'X', 'name' => 'PharmacyOne', 'soft1' => ''];
        $t = $res['totals'][$sel];
        $items = [];
        if ($t['annual'] > 0.005) {
            $items[] = ['sku' => 'PHARMACYONE-' . $ed['key'],
                'desc' => $ed['name'] . ' — ετήσια συνδρομή' . ($ed['soft1'] ? ' (' . $ed['soft1'] . ')' : ''),
                'qty' => 1.0, 'unit' => round($t['annual'], 2), 'cycle' => 'annually',
                'taxable' => true, 'productId' => null];
        }
        if ($t['oneoff'] > 0.005) {
            $items[] = ['sku' => 'PHARMACYONE-SETUP',
                'desc' => 'Εφάπαξ εγκατάσταση & παραμετροποίηση',
                'qty' => 1.0, 'unit' => round($t['oneoff'], 2), 'cycle' => 'onetime',
                'taxable' => true, 'productId' => null];
        }
        $disc = (float) ($c['o']['discount'] ?? 0);
        if ($disc > 0.005) {
            $items[] = ['sku' => 'PHARMACYONE-DISCOUNT', 'desc' => 'Έκπτωση προσφοράς',
                'qty' => 1.0, 'unit' => -round($disc, 2), 'cycle' => 'onetime',
                'taxable' => true, 'productId' => null];
        }
        return $items;
    }
}
