<?php
/**
 * Pricing calculator: turns a raw Hetzner cost into a WHMCS sell price by
 * applying a percentage markup, with optional rounding and VAT handling.
 *
 * @package WHMCS\Module\Server\HetznerCloud
 */

namespace WHMCS\Module\Server\HetznerCloud;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class Pricing
{
    /**
     * Apply a percentage markup to a cost.
     *
     * @param float  $cost        Hetzner cost (net or gross, caller decides).
     * @param float  $markupPct   e.g. 40 for +40%.
     * @param string $rounding    none|up_cent|up_10cent|up_euro|nearest_cent|psych_99
     * @return float
     */
    public static function sell($cost, $markupPct, $rounding = 'up_cent')
    {
        $cost = (float) $cost;
        $price = $cost * (1 + ((float) $markupPct / 100));
        return self::round($price, $rounding);
    }

    /**
     * Category-aware markup: a per-category override wins over the default.
     *
     * @param float  $cost
     * @param string $category   e.g. server_type slug, "storage_box", "traffic"
     * @param float  $defaultPct
     * @param array  $overrides  ['storage_box' => 25, 'cx22' => 60, ...]
     * @param string $rounding
     */
    public static function sellFor($cost, $category, $defaultPct, array $overrides = [], $rounding = 'up_cent')
    {
        $pct = $defaultPct;
        if ($category !== '' && array_key_exists($category, $overrides) && $overrides[$category] !== '') {
            $pct = (float) $overrides[$category];
        }
        return self::sell($cost, $pct, $rounding);
    }

    public static function round($price, $rounding)
    {
        $price = (float) $price;
        switch ($rounding) {
            case 'none':
                return round($price, 4);
            case 'nearest_cent':
                return round($price, 2);
            case 'up_10cent':
                return ceil($price * 10) / 10;
            case 'up_euro':
                return ceil($price);
            case 'psych_99':
                // Round up to the next whole unit then subtract a cent: 4.12 -> 4.99
                return max(0, ceil($price) - 0.01);
            case 'up_cent':
            default:
                return ceil($price * 100) / 100;
        }
    }

    /**
     * Parse a Hetzner price node. Hetzner returns strings with high precision
     * and separate net/gross figures.
     *
     * @param array  $priceNode  e.g. ['net' => '3.7900', 'gross' => '4.5104']
     * @param string $basis      net|gross
     * @return float
     */
    public static function fromNode(array $priceNode, $basis = 'net')
    {
        if (isset($priceNode[$basis])) {
            return (float) $priceNode[$basis];
        }
        if (isset($priceNode['net'])) {
            return (float) $priceNode['net'];
        }
        return 0.0;
    }
}
