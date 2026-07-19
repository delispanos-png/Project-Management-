<?php
$_ADDONLANG['gateway'] = 'Gateway';
$_ADDONLANG['fixed'] = 'Fixed Fee';
$_ADDONLANG['percentage'] = 'Percentage';
$_ADDONLANG['none'] = 'None';
$_ADDONLANG['free'] = 'Free';
$_ADDONLANG['manage'] = 'Manage';
$_ADDONLANG['savechanges'] = 'Save Changes';
$_ADDONLANG['success'] = 'Success!';
$_ADDONLANG['successmessage'] = 'You changes have been saved successfully.';
$_ADDONLANG['invoiceitem'] = 'Payment Gateway Fees';
$_ADDONLANG['minamount'] = 'Minimum invoice total';
$_ADDONLANG['minamountdesc'] = 'Leave blank to add gateway fee for all invoices';
$_ADDONLANG['addfeetax'] = 'Apply fee after tax and total calculation';
$_ADDONLANG['countries'] = 'Countries';
$_ADDONLANG['ccountries'] = 'Custom fees';
$_ADDONLANG['save'] = 'Save';
$_ADDONLANG['back'] = 'Back';
$_ADDONLANG['nocc'] = 'All countries';
$_ADDONLANG['country'] = 'Country';
$_ADDONLANG['delete'] = 'Delete';
$_ADDONLANG['addnew'] = 'Add New';
$_ADDONLANG['withtax'] = ' (with tax)';
$_ADDONLANG['gateways'] = 'Gateways';
$_ADDONLANG['customizefees'] = 'Customize fees';
$_ADDONLANG['exemptclients'] = 'Exempt Clients';
$_ADDONLANG['gatewayfee'] = 'fee'; // display on invoice
$_ADDONLANG['gatewayfeeorder'] = 'fee'; //display on order process
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