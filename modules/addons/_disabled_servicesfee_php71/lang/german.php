<?php
$_ADDONLANG['gateway'] = 'Gateway';
$_ADDONLANG['fixed'] = 'Feste Gebühr';
$_ADDONLANG['percentage'] = 'Prozent';
$_ADDONLANG['none'] = 'Nichts';
$_ADDONLANG['free'] = 'Kostenfrei';
$_ADDONLANG['manage'] = 'Verwalten';
$_ADDONLANG['savechanges'] = 'Speichern';
$_ADDONLANG['success'] = 'Erfolgreich!';
$_ADDONLANG['successmessage'] = 'Die Änderungen wurden erfolgreich gespeichert.';
$_ADDONLANG['invoiceitem'] = 'Gateway-Gebühren';
$_ADDONLANG['minamount'] = 'Mindestbetrag der Rechnung';
$_ADDONLANG['minamountdesc'] = 'Leer lassen, um Gateway-Gebühren für alle Rechnungen hinzuzufügen';
$_ADDONLANG['addfeetax'] = 'Zeige Gebühr nach Steuern und Gesamtkalkulation';
$_ADDONLANG['countries'] = 'Länder';
$_ADDONLANG['ccountries'] = 'Eigene Gebühren';
$_ADDONLANG['save'] = 'Speichern';
$_ADDONLANG['back'] = 'Zurück';
$_ADDONLANG['nocc'] = 'Alle Länder';
$_ADDONLANG['country'] = 'Länder';
$_ADDONLANG['delete'] = 'Löschen';
$_ADDONLANG['addnew'] = 'Hinzufügen';
$_ADDONLANG['withtax'] = '(mit Steuer)';
$_ADDONLANG['gateways'] = 'Gateways';
$_ADDONLANG['customizefees'] = 'Gebühren anpassen';
$_ADDONLANG['exemptclients'] = 'Befreite Kunden';
$_ADDONLANG['gatewayfee'] = 'Transaktionsgebühr';
$_ADDONLANG['gatewayfeeorder'] = 'Transaktionsgebühr';
$_ADDONLANG['bilingmethod'] = 'Abrechnungsart';
$_ADDONLANG['standardex'] = 'Standard - Exklusiv für Einkaufswagen insgesamt';
$_ADDONLANG['paypalex'] = 'PayPal - Inklusiv bis zur endgültigen Summe';
$_ADDONLANG['cancelinvoice'] = 'Bearbeitung von Stornierungsanfragen';
$_ADDONLANG['cancelinvoicedesc'] = 'Löschen von Rechnungspositionen bei Kündigungen nicht zulässig';
//4.4.0
$_ADDONLANG['addtaxa'] = 'Add tax to fee amount';
$_ADDONLANG['multicurrency'] = 'Multi Currency';
$_ADDONLANG['checkoutshow'] = 'Checkout Fee Show type';
$_ADDONLANG['checkoutshow1'] = 'Inline (after total amount)';
$_ADDONLANG['checkoutshow2'] = 'Table after payment gateways';
$_ADDONLANG['startfromine'] = 'Enable Old Invoice Skip';
$_ADDONLANG['startfromin'] = 'Add fees to invoices after';
$_ADDONLANG['startfromindet'] = 'Invoice ID, skip invoices before this invoice';
$_ADDONLANG['searchusers'] = 'Start Typing to Search Clients';
$_ADDONLANG['tablegateway'] = 'Name';
$_ADDONLANG['tableprovision'] = 'Services Fee';
$_ADDONLANG['tablecalcluted'] = 'Calculated';
$_ADDONLANG['tablefeetitle'] = 'Transaction Fees';


// overrides including
if (file_exists(__DIR__ . '/overrides/english.php')) {
    include(__DIR__ . '/overrides/english.php');
}