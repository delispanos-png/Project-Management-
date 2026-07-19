<?php
$_ADDONLANG['gateway'] = 'Forma de pago';
$_ADDONLANG['fixed'] = 'Importe Fijo';
$_ADDONLANG['percentage'] = 'Porcentaje';
$_ADDONLANG['none'] = 'Ninguno';
$_ADDONLANG['free'] = 'Gratis';
$_ADDONLANG['manage'] = 'Editar';
$_ADDONLANG['savechanges'] = 'Guardar';
$_ADDONLANG['success'] = 'Perfecto!';
$_ADDONLANG['successmessage'] = 'Los cambios han sido guardados.';
$_ADDONLANG['invoiceitem'] = 'Costo administrativo';
$_ADDONLANG['minamount'] = 'Importe mínimo de factura';
$_ADDONLANG['minamountdesc'] = 'Dejar en blanco para agregar un fee a todas las facturas';
$_ADDONLANG['addfeetax'] = 'Aplicar el fee luego de calcular impuestos y total de factura';
$_ADDONLANG['countries'] = 'Países';
$_ADDONLANG['ccountries'] = 'Cargos específicos';
$_ADDONLANG['save'] = 'Guardar';
$_ADDONLANG['back'] = 'Volver';
$_ADDONLANG['nocc'] = 'Todos los países';
$_ADDONLANG['country'] = 'País';
$_ADDONLANG['delete'] = 'Eliminar';
$_ADDONLANG['addnew'] = 'Añadir nuevo';
$_ADDONLANG['withtax'] = ' (with tax)';
$_ADDONLANG['gateways'] = 'Formas de pago';
$_ADDONLANG['customizefees'] = 'Customizar fees';
$_ADDONLANG['exemptclients'] = 'Exceptuar Clientes';
$_ADDONLANG['gatewayfee'] = 'Costo administrativo';
$_ADDONLANG['gatewayfeeorder'] = 'Costo administrativo';
$_ADDONLANG['bilingmethod'] = 'Fee Billing Type';
$_ADDONLANG['standardex'] = 'Standard - Exclusive to cart total';
$_ADDONLANG['paypalex'] = 'PayPal - Inclusive to final total';
$_ADDONLANG['cancelinvoice'] = 'Cancellation Request Handling';
$_ADDONLANG['cancelinvoicedesc'] = ' Disallow to delete invoice items on cancellation request submited';
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