<?php
/**
 * Προσθέτει «Απομακρυσμένη υποστήριξη» στο μενού Υποστήριξης του πελάτη,
 * ώστε να βρίσκει μόνος του τη σελίδα λήψης (my.cloudon.gr/remote) χωρίς να
 * του στέλνουμε κάθε φορά link.
 */

use WHMCS\View\Menu\Item as MenuItem;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/** Το κείμενο στη γλώσσα του πελάτη. */
function hz_remote_label()
{
    $lang = strtolower((string) ($_SESSION['Language'] ?? 'greek'));
    return (strpos($lang, 'english') !== false)
        ? ['Remote support', 'Download our remote support tool', 'en']
        : ['Απομακρυσμένη υποστήριξη', 'Κατέβασε το εργαλείο απομακρυσμένης υποστήριξης', 'el'];
}

add_hook('ClientAreaPrimaryNavbar', 100, function (MenuItem $navbar) {
    [$label, $tip, $lang] = hz_remote_label();
    $url = '/remote' . ($lang === 'en' ? '?lang=en' : '');

    // Προτίμησε το μενού «Υποστήριξη»· αν δεν βρεθεί, βάλ’ το στο πρώτο επίπεδο.
    $support = $navbar->getChild('Support');
    if (!$support) {
        foreach ((array) $navbar->getChildren() as $child) {
            if (stripos((string) $child->getLabel(), 'ποστήριξ') !== false
                || stripos((string) $child->getLabel(), 'support') !== false) {
                $support = $child;
                break;
            }
        }
    }

    $parent = $support ?: $navbar;
    if ($parent->getChild('Remote Support')) {
        return;
    }
    $parent->addChild('Remote Support', [
        'label' => $label,
        'uri'   => $url,
        'order' => 25,
        'icon'  => 'fas fa-desktop',
        'attributes' => ['title' => $tip],
    ]);
});
