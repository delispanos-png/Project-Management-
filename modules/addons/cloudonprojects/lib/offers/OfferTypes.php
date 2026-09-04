<?php
/**
 * Μητρώο τύπων προσφοράς. Ένα σημείο εγγραφής· η υπόλοιπη εφαρμογή ζητά
 * OfferTypes::get($kind) και δεν ξέρει τίποτα για τον συγκεκριμένο τύπο.
 *
 * @package WHMCS\Module\Addon\CloudonProjects\Offers
 */

namespace WHMCS\Module\Addon\CloudonProjects\Offers;

class OfferTypes
{
    /** @var array<string,OfferType>|null */
    private static $reg = null;

    private static function boot(): void
    {
        if (self::$reg !== null) { return; }
        self::$reg = [];
        foreach ([new PharmacyOneType(), new PlainType()] as $t) {
            self::$reg[$t->key()] = $t;
        }
    }

    /** Ο τύπος για ένα kind· fallback στη γενική προσφορά αν άγνωστος. */
    public static function get(string $kind): OfferType
    {
        self::boot();
        return self::$reg[$kind] ?? self::$reg['plain'];
    }

    public static function has(string $kind): bool
    {
        self::boot();
        return isset(self::$reg[$kind]);
    }

    /** @return OfferType[] */
    public static function all(): array
    {
        self::boot();
        return array_values(self::$reg);
    }
}
