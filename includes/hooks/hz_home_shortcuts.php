<?php
/**
 * Enriches the client-area homepage "Shortcuts" panel (shown in the top "Menu"
 * dropdown when logged in) with useful, translated quick links.
 */

use WHMCS\View\Menu\Item as MenuItem;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ClientAreaSecondarySidebar', 1, function (MenuItem $sidebar) {
    // The "Shortcuts" menu only exists on the client-area homepage, so this
    // implicitly limits us to the homepage.
    $shortcuts = $sidebar->getChild('Shortcuts');
    if (!$shortcuts) {
        return;
    }

    $lang = strtolower((string) ($_SESSION['Language'] ?? 'greek'));
    $en = (strpos($lang, 'english') !== false);
    $t = function ($gr, $enText) use ($en) {
        return $en ? $enText : $gr;
    };

    // Useful shortcuts, ordered so they appear above the default Logout.
    $links = [
        ['hzServices', $t('Οι Υπηρεσίες μου', 'My Services'),   'clientarea.php?action=services', 'fas fa-server'],
        ['hzDomains',  $t('Τα Domains μου', 'My Domains'),      'clientarea.php?action=domains',  'fas fa-globe'],
        ['hzInvoices', $t('Τα Τιμολόγιά μου', 'My Invoices'),   'clientarea.php?action=invoices', 'fas fa-file-invoice-dollar'],
        ['hzTicket',   $t('Νέο Ticket', 'Open Ticket'),         'submitticket.php',               'fas fa-life-ring'],
        ['hzKb',       $t('Βάση Γνώσεων', 'Knowledge Base'),    'knowledgebase.php',              'fas fa-book'],
    ];

    $order = 1;
    foreach ($links as $l) {
        // Skip if a child with this name somehow already exists.
        if ($shortcuts->getChild($l[0])) {
            continue;
        }
        $shortcuts->addChild($l[0], [
            'label' => $l[1],
            'uri'   => $l[2],
            'icon'  => $l[3],
            'order' => $order++,
        ]);
    }
});
