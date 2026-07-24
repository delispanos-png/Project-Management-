<?php
/**
 * CloudOn — Τιμολόγιο (invoice PDF)
 * Ίδια εταιρική ταυτότητα με την Προσφορά (quotepdf.tpl):
 * μπλε brand κεφαλίδα, καθαρός πίνακας, ανάδειξη συνόλου, σφραγίδα κατάστασης.
 */

$brand  = [0, 144, 221];    // CloudOn μπλε #0090dd
$ink    = [21, 34, 56];
$mut    = [122, 135, 156];
$lineBg = '#f4f8fc';
$brandHex = sprintf('#%02x%02x%02x', $brand[0], $brand[1], $brand[2]);

$pdf->setPrintFooter(false);   // χωρίς "Powered by TCPDF"

# ─── Λογότυπο ───
$logoFilename = 'placeholder.png';
if (file_exists(ROOTDIR . '/assets/img/logo.png')) {
    $logoFilename = 'logo.png';
} elseif (file_exists(ROOTDIR . '/assets/img/logo.jpg')) {
    $logoFilename = 'logo.jpg';
}
$pdf->Image(ROOTDIR . '/assets/img/' . $logoFilename, 20, 22, 62);

# ─── Τίτλος + ημερομηνίες δεξιά ───
$pdf->SetY(22);
$pdf->SetTextColor($brand[0], $brand[1], $brand[2]);
$pdf->SetFont($pdfFont, 'B', 24);
$pdf->Cell(0, 10, mb_strtoupper(preg_replace('/[#\d].*$/u', '', $pagetitle) ?: $pagetitle, 'UTF-8'), 0, 1, 'R');
$pdf->SetFont($pdfFont, '', 10);
$pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
$pdf->Cell(0, 5, $pagetitle, 0, 1, 'R');
$pdf->SetTextColor($mut[0], $mut[1], $mut[2]);
$pdf->SetFont($pdfFont, '', 9);
$pdf->Cell(0, 4.5, Lang::trans('invoicesdatecreated') . ': ' . $datecreated, 0, 1, 'R');
$pdf->SetFont($pdfFont, 'B', 9);
$pdf->SetTextColor(200, 60, 60);
$pdf->Cell(0, 4.5, Lang::trans('invoicesdatedue') . ': ' . $duedate, 0, 1, 'R');

# ─── Badge κατάστασης (PAID/UNPAID/…) δεξιά, κάτω από τις ημερομηνίες ───
if ($status == 'Draft' || $status == 'Cancelled') {
    $pdf->SetFillColor(185, 195, 208);
} elseif ($status == 'Paid') {
    $pdf->SetFillColor(45, 189, 110);
} elseif ($status == 'Refunded') {
    $pdf->SetFillColor(131, 182, 218);
} elseif ($status == 'Collections') {
    $pdf->SetFillColor(60, 60, 60);
} else {
    $pdf->SetFillColor(223, 85, 74);
}
$statusLabel = ($status == 'Payment Pending')
    ? strtoupper(Lang::trans('invoices' . str_replace(' ', '', $status)))
    : strtoupper(Lang::trans('invoices' . strtolower($status)));
$pdf->Ln(2);
$pdf->SetFont($pdfFont, 'B', 11);
$pdf->SetTextColor(255);
$pdf->SetX(150);
$pdf->Cell(40, 8, $statusLabel, 0, 1, 'C', true);

# ─── Στοιχεία εταιρείας κάτω από το λογότυπο ───
$pdf->SetXY(20, 38);
$pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
$pdf->SetFont($pdfFont, 'B', 9);
$pdf->Cell(100, 4, trim($companyaddress[0]), 0, 1, 'L');
$pdf->SetX(20);
$pdf->SetFont($pdfFont, '', 8);
$pdf->SetTextColor($mut[0], $mut[1], $mut[2]);
for ($i = 1; $i < count($companyaddress); $i++) {
    if (trim($companyaddress[$i]) !== '') {
        $pdf->Cell(100, 3.6, trim($companyaddress[$i]), 0, 1, 'L');
        $pdf->SetX(20);
    }
}
if ($taxCode) {
    $pdf->Cell(100, 3.6, $taxIdLabel . ': ' . trim($taxCode), 0, 1, 'L');
}

# ─── brand διαχωριστικό ───
$pdf->Ln(4);
$pdf->SetDrawColor($brand[0], $brand[1], $brand[2]);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->SetLineWidth(0.2);
$pdf->Ln(6);

$startpage = $pdf->GetPage();

# ─── Παραλήπτης ───
$pdf->SetX(20);
$pdf->SetTextColor($mut[0], $mut[1], $mut[2]);
$pdf->SetFont($pdfFont, 'B', 8);
$pdf->Cell(0, 4, mb_strtoupper(Lang::trans('invoicesinvoicedto'), 'UTF-8'), 0, 1);
$pdf->SetX(20);
$pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
$pdf->SetFont($pdfFont, 'B', 10);
if ($clientsdetails['companyname']) {
    $pdf->Cell(0, 5, $clientsdetails['companyname'], 0, 1);
    $pdf->SetX(20);
    $pdf->SetFont($pdfFont, '', 9);
    $pdf->Cell(0, 4, Lang::trans('invoicesattn') . ': ' . $clientsdetails['firstname'] . ' ' . $clientsdetails['lastname'], 0, 1);
} else {
    $pdf->Cell(0, 5, $clientsdetails['firstname'] . ' ' . $clientsdetails['lastname'], 0, 1);
    $pdf->SetFont($pdfFont, '', 9);
}
$addr = array_filter([$clientsdetails['address1'], $clientsdetails['address2'],
    trim($clientsdetails['city'] . ' ' . $clientsdetails['postcode']), $clientsdetails['country']]);
$pdf->SetX(20);
$pdf->SetTextColor($mut[0], $mut[1], $mut[2]);
$pdf->SetFont($pdfFont, '', 8.5);
$pdf->Cell(0, 4, implode('  ·  ', $addr), 0, 1);
if (array_key_exists('tax_id', $clientsdetails) && $clientsdetails['tax_id']) {
    $pdf->SetX(20);
    $pdf->Cell(0, 4, $taxIdLabel . ': ' . $clientsdetails['tax_id'], 0, 1);
}
if ($customfields) {
    foreach ($customfields as $customfield) {
        $pdf->SetX(20);
        $pdf->Cell(0, 4, $customfield['fieldname'] . ': ' . $customfield['value'], 0, 1);
    }
}
$pdf->Ln(7);

# ─── Πίνακας χρεώσεων ───
$tblhtml = '<table width="100%" cellspacing="0" cellpadding="6" border="0">
    <tr bgcolor="' . $brandHex . '" style="font-weight:bold;color:#ffffff;">
        <td width="78%">' . Lang::trans('invoicesdescription') . '</td>
        <td width="22%" align="right">' . Lang::trans('quotelinetotal') . '</td>
    </tr>';
$rowNo = 0;
foreach ($invoiceitems as $item) {
    $bg = (++$rowNo % 2) ? '#ffffff' : $lineBg;
    $desc = nl2br($item['description']);
    $parts = explode('<br />', $desc, 2);
    $descHtml = '<b>' . $parts[0] . '</b>'
        . (isset($parts[1]) ? '<br /><font size="7" color="#7a879c">' . $parts[1] . '</font>' : '');
    $tblhtml .= '
    <tr bgcolor="' . $bg . '">
        <td width="78%">' . $descHtml . '</td>
        <td width="22%" align="right"><b>' . $item['amount'] . '</b></td>
    </tr>';
}
$tblhtml .= '</table>';
$pdf->SetFont($pdfFont, '', 8.5);
$pdf->writeHTML($tblhtml, true, false, false, false, '');

# ─── Σύνολα ───
$totrows = '<tr><td width="60%"></td><td width="25%" align="right" style="color:#7a879c">'
    . Lang::trans('invoicessubtotal') . '</td><td width="15%" align="right">' . $subtotal . '</td></tr>';
if ($taxname) {
    $totrows .= '<tr><td></td><td align="right" style="color:#7a879c">' . $taxname . ' ' . $taxrate . '%</td>'
        . '<td align="right">' . $tax . '</td></tr>';
}
if ($taxname2) {
    $totrows .= '<tr><td></td><td align="right" style="color:#7a879c">' . $taxname2 . ' ' . $taxrate2 . '%</td>'
        . '<td align="right">' . $tax2 . '</td></tr>';
}
if ((float) preg_replace('/[^\d.]/', '', (string) $credit) > 0) {
    $totrows .= '<tr><td></td><td align="right" style="color:#7a879c">' . Lang::trans('invoicescredit') . '</td>'
        . '<td align="right">' . $credit . '</td></tr>';
}
$totrows .= '<tr><td></td><td align="right" bgcolor="' . $brandHex . '" style="color:#ffffff;font-weight:bold;">'
    . Lang::trans('invoicestotal') . '</td>'
    . '<td align="right" bgcolor="' . $brandHex . '" style="color:#ffffff;font-weight:bold;">' . $total . '</td></tr>';
$pdf->SetFont($pdfFont, '', 9.5);
$pdf->writeHTML('<table width="100%" cellspacing="0" cellpadding="5" border="0">' . $totrows . '</table>', true, false, false, false, '');

# ─── Συναλλαγές (μόνο αν υπάρχουν) ───
if (count($transactions)) {
    $pdf->Ln(4);
    $pdf->SetX(20);
    $pdf->SetFont($pdfFont, 'B', 9.5);
    $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
    $pdf->Cell(0, 5, Lang::trans('invoicestransactions'), 0, 1);
    $tblhtml = '<table width="100%" cellspacing="0" cellpadding="5" border="0">
        <tr bgcolor="' . $lineBg . '" style="font-weight:bold;color:#152238;">
            <td width="25%">' . Lang::trans('invoicestransdate') . '</td>
            <td width="27%">' . Lang::trans('invoicestransgateway') . '</td>
            <td width="28%">' . Lang::trans('invoicestransid') . '</td>
            <td width="20%" align="right">' . Lang::trans('invoicestransamount') . '</td>
        </tr>';
    foreach ($transactions as $trans) {
        $tblhtml .= '
        <tr>
            <td>' . $trans['date'] . '</td>
            <td>' . $trans['gateway'] . '</td>
            <td>' . $trans['transid'] . '</td>
            <td align="right">' . $trans['amount'] . '</td>
        </tr>';
    }
    $tblhtml .= '
        <tr style="font-weight:bold;">
            <td colspan="3" align="right">' . Lang::trans('invoicesbalance') . '</td>
            <td align="right">' . $balance . '</td>
        </tr>
    </table>';
    $pdf->SetFont($pdfFont, '', 8.5);
    $pdf->writeHTML($tblhtml, true, false, false, false, '');
}

# ─── Σημειώσεις ───
if ($notes) {
    $pdf->Ln(4);
    $pdf->SetX(20);
    $pdf->SetFont($pdfFont, 'B', 8.5);
    $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
    $pdf->Cell(0, 4, Lang::trans('invoicesnotes'), 0, 1);
    $pdf->SetX(20);
    $pdf->SetFont($pdfFont, '', 8);
    $pdf->SetTextColor(70, 85, 110);
    $pdf->MultiCell(170, 4.2, $notes, 0, 'L');
}

# ─── Υποσέλιδο ───
$pdf->Ln(7);
$pdf->SetFont($pdfFont, '', 8);
$pdf->SetTextColor($brand[0], $brand[1], $brand[2]);
$pdf->Cell(0, 4, 'Ευχαριστούμε για την εμπιστοσύνη σας! · Thank you for your business!', 0, 1, 'C');
$pdf->SetFont($pdfFont, '', 7);
$pdf->SetTextColor($mut[0], $mut[1], $mut[2]);
$pdf->Cell(0, 4, Lang::trans('invoicepdfgenerated') . ' ' . getTodaysDate(1), 0, 1, 'C');
