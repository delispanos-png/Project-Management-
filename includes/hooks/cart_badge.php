<?php
/**
 * Μετρητής προϊόντων καλαθιού για την πάνω μπάρα.
 *
 * Χωρίς αυτόν ο πελάτης δεν έχει καμία ένδειξη ότι άφησε προϊόντα στο καλάθι —
 * πρέπει να μπει στο ίδιο το καλάθι για να το ανακαλύψει. Δίνει τη μεταβλητή
 * {$cnpCartCount} στο πρότυπο, την οποία διαβάζει το header.tpl.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/** Πόσα αντικείμενα υπάρχουν αυτή τη στιγμή στο καλάθι. */
function cnp_cart_count()
{
    $cart = $_SESSION['cart'] ?? null;
    if (!is_array($cart)) {
        return 0;
    }

    $n = 0;
    foreach (['products', 'domains', 'addons', 'renewals'] as $bucket) {
        if (!empty($cart[$bucket]) && is_array($cart[$bucket])) {
            $n += count($cart[$bucket]);
        }
    }

    return $n;
}

$cnpCartCountHook = function () {
    return ['cnpCartCount' => cnp_cart_count()];
};

// ClientAreaPage καλύπτει την περιοχή πελάτη· ShoppingCartValidateProductUpdate
// και οι σελίδες του καλαθιού περνούν από ClientAreaPageCart.
add_hook('ClientAreaPage', 1, $cnpCartCountHook);
add_hook('ClientAreaPageCart', 1, $cnpCartCountHook);
add_hook('ClientAreaPageHome', 1, $cnpCartCountHook);
add_hook('ClientAreaPageLogin', 1, $cnpCartCountHook);
