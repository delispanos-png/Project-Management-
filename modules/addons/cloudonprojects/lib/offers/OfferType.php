<?php
/**
 * CloudOn Project Manager — κοινό interface τύπου προσφοράς.
 *
 * Κάθε είδος προσφοράς (PharmacyOne, e-commerce, γενική) υλοποιεί ΤΟ ΙΔΙΟ
 * interface, ώστε η ροή αποστολής / portal / χρεώσεων να μη γνωρίζει τον τύπο.
 * Προσθήκη νέου τύπου = μία νέα κλάση + εγγραφή στο OfferTypes. Δες
 * docs/OFFERS-ARCHITECTURE.md.
 *
 * @package WHMCS\Module\Addon\CloudonProjects\Offers
 */

namespace WHMCS\Module\Addon\CloudonProjects\Offers;

interface OfferType
{
    /** Σταθερό κλειδί τύπου, ίδιο με mod_cpm_offers.kind (π.χ. 'pharmacyone'). */
    public function key(): string;

    /** Ανθρώπινη ετικέτα (π.χ. «PharmacyOne (Soft1)»). */
    public function label(): string;

    /** Κανονικοποίηση/επικύρωση config — ΠΟΤΕ δεν εμπιστεύεται τον client. */
    public function normalize(array $cfg): array;

    /** Το συνολικό ποσό της προσφοράς (πρώτο έτος, χωρίς ΦΠΑ). */
    public function amount(array $cfg): float;

    /** Μία γραμμή περίληψη για λίστες/ειδοποιήσεις. */
    public function summary(array $cfg): string;

    /** Το branded έγγραφο της προσφοράς ως HTML. */
    public function docHtml(array $cfg): string;

    /** Το στυλ του εγγράφου (κοινό ανά τύπο). */
    public function docCss(): string;

    /**
     * Δομημένες γραμμές χρέωσης — η ΜΙΑ πηγή για quote & αυτόματες χρεώσεις.
     *
     * @return array<int,array{sku:string,desc:string,qty:float,unit:float,
     *   cycle:string,taxable:bool,productId:?int}>
     *   cycle ∈ onetime|monthly|quarterly|semiannually|annually|biennially
     */
    public function lineItems(array $cfg): array;
}
