<?php
/**
 * CloudOn — Εμπορική Προσφορά (quote PDF)
 * Σχεδιασμένη ως πρόταση συνεργασίας, όχι ως τιμολόγιο:
 * ευχαριστήριο εισαγωγικό, καθαρός πίνακας, ισχύς προσφοράς, μπλοκ αποδοχής.
 */

$brand   = [0, 144, 221];    // CloudOn μπλε #0090dd
$ink     = [21, 34, 56];     // σκούρο κείμενο
$mut     = [122, 135, 156];  // γκρι
$lineBg  = '#f4f8fc';

$pdf->setPrintFooter(false);   // χωρίς "Powered by TCPDF"

# ─── Κεφαλίδα: λογότυπο αριστερά, «ΠΡΟΣΦΟΡΑ» δεξιά ───
if (file_exists(ROOTDIR.'/assets/img/logo.png')) $pdf->Image(ROOTDIR.'/assets/img/logo.png', 20, 22, 62);
elseif (file_exists(ROOTDIR.'/assets/img/logo.jpg')) $pdf->Image(ROOTDIR.'/assets/img/logo.jpg', 20, 22, 62);

$pdf->SetY(22);
$pdf->SetTextColor($brand[0], $brand[1], $brand[2]);
$pdf->SetFont($pdfFont, 'B', 26);
$pdf->Cell(0, 10, 'ΠΡΟΣΦΟΡΑ', 0, 1, 'R');
$pdf->SetFont($pdfFont, '', 10);
$pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
$pdf->Cell(0, 5, '№ ' . $quotenumber . '  ·  ' . $datecreated, 0, 1, 'R');
$pdf->SetTextColor(200, 60, 60);
$pdf->SetFont($pdfFont, 'B', 10);
$pdf->Cell(0, 5, 'Ισχύει έως ' . $validuntil, 0, 1, 'R');
$pdf->SetTextColor($mut[0], $mut[1], $mut[2]);

# ─── Στοιχεία εταιρείας (μικρά, κάτω από το λογότυπο) ───
$pdf->SetY(38);
$pdf->SetFont($pdfFont, 'B', 9);
$pdf->Cell(100, 4, trim($companyaddress[0]), 0, 1, 'L');
$pdf->SetFont($pdfFont, '', 8);
for ($i = 1; $i < count($companyaddress); $i++) {
    if (trim($companyaddress[$i]) !== '') {
        $pdf->Cell(100, 3.6, trim($companyaddress[$i]), 0, 1, 'L');
    }
}

# ─── γραμμή-διαχωριστικό στο χρώμα της μάρκας ───
$pdf->Ln(4);
$pdf->SetDrawColor($brand[0], $brand[1], $brand[2]);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->SetLineWidth(0.2);
$pdf->Ln(6);

# ─── Παραλήπτης + εισαγωγικό ───
$pdf->SetTextColor($mut[0], $mut[1], $mut[2]);
$pdf->SetFont($pdfFont, 'B', 8);
$pdf->Cell(0, 4, 'ΠΡΟΣ', 0, 1);
$pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
$pdf->SetFont($pdfFont, 'B', 10);
$contactName = trim($clientsdetails['firstname'] . ' ' . $clientsdetails['lastname']);
if ($clientsdetails['companyname']) {
    $pdf->Cell(0, 5, $clientsdetails['companyname'], 0, 1);
    $pdf->SetFont($pdfFont, '', 9);
    $pdf->Cell(0, 4, 'Υπόψη: ' . $contactName, 0, 1);
} else {
    $pdf->Cell(0, 5, $contactName, 0, 1);
    $pdf->SetFont($pdfFont, '', 9);
}
$addr = array_filter([$clientsdetails['address1'], $clientsdetails['address2'],
    trim($clientsdetails['city'] . ' ' . $clientsdetails['postcode']), $clientsdetails['country']]);
$pdf->SetTextColor($mut[0], $mut[1], $mut[2]);
$pdf->Cell(0, 4, implode('  ·  ', $addr), 0, 1);

$pdf->Ln(5);
$pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
$pdf->SetFont($pdfFont, 'B', 12);
$pdf->MultiCell(170, 6, $subject, 0, 'L');
$pdf->Ln(1);
$pdf->SetFont($pdfFont, '', 9);
$pdf->SetTextColor(70, 85, 110);
$pdf->MultiCell(170, 4.6,
    'Σας ευχαριστούμε για το ενδιαφέρον σας. Με βάση τις ανάγκες που συζητήσαμε, σας παραθέτουμε '
    . 'την πρότασή μας. Παραμένουμε στη διάθεσή σας για οποιαδήποτε διευκρίνιση ή προσαρμογή.', 0, 'L');
$pdf->Ln(4);

# ─── Ελεύθερο κείμενο πρότασης (αν έχει συμπληρωθεί στο quote) ───
if ($proposal) {
    $pdf->SetFont($pdfFont, '', 9);
    $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
    $pdf->MultiCell(170, 4.8, $proposal, 0, 'L');
    $pdf->Ln(4);
}

# ─── Πίνακας πρότασης ───
$brandHex = sprintf('#%02x%02x%02x', $brand[0], $brand[1], $brand[2]);
$tblhtml = '<table width="100%" cellspacing="0" cellpadding="6" border="0">
    <tr bgcolor="' . $brandHex . '" style="font-weight:bold;color:#ffffff;">
        <td width="52%">Τι περιλαμβάνεται</td>
        <td width="8%" align="center">Ποσ.</td>
        <td width="15%" align="right">Τιμή μονάδας</td>
        <td width="10%" align="right">Έκπτ.</td>
        <td width="15%" align="right">Σύνολο</td>
    </tr>';
$rowNo = 0;
foreach ($lineitems as $item) {
    $bg = (++$rowNo % 2) ? '#ffffff' : $lineBg;
    $desc = nl2br($item['description']);
    // πρώτη γραμμή της περιγραφής = τίτλος, οι υπόλοιπες = χαρακτηριστικά
    $parts = explode('<br />', $desc, 2);
    $descHtml = '<b>' . $parts[0] . '</b>'
        . (isset($parts[1]) ? '<br /><font size="7" color="#7a879c">' . $parts[1] . '</font>' : '');
    $discount = trim((string) $item['discount']);
    $showDisc = ($discount !== '' && (float) str_replace(['%', ','], ['', '.'], $discount) > 0) ? $discount : '—';
    $tblhtml .= '
    <tr bgcolor="' . $bg . '">
        <td width="52%">' . $descHtml . '</td>
        <td width="8%" align="center">' . $item['qty'] . '</td>
        <td width="15%" align="right">' . $item['unitprice'] . '</td>
        <td width="10%" align="right">' . $showDisc . '</td>
        <td width="15%" align="right"><b>' . $item['total'] . '</b></td>
    </tr>';
}
$tblhtml .= '</table>';
$pdf->SetFont($pdfFont, '', 8.5);
$pdf->writeHTML($tblhtml, true, false, false, false, '');

# ─── Σύνολα (δεξιά, με ανάδειξη τελικού) ───
$totrows = '<tr><td width="60%"></td><td width="25%" align="right" style="color:#7a879c">'
    . $_LANG['invoicessubtotal'] . '</td><td width="15%" align="right">' . $subtotal . '</td></tr>';
if ($taxlevel1['rate'] > 0) {
    $totrows .= '<tr><td></td><td align="right" style="color:#7a879c">' . $taxlevel1['name'] . ' ' . $taxlevel1['rate'] . '%</td>'
        . '<td align="right">' . $tax1 . '</td></tr>';
}
if ($taxlevel2['rate'] > 0) {
    $totrows .= '<tr><td></td><td align="right" style="color:#7a879c">' . $taxlevel2['name'] . ' ' . $taxlevel2['rate'] . '%</td>'
        . '<td align="right">' . $tax2 . '</td></tr>';
}
$totrows .= '<tr><td></td><td align="right" bgcolor="' . $brandHex . '" style="color:#ffffff;font-weight:bold;">Σύνολο προσφοράς</td>'
    . '<td align="right" bgcolor="' . $brandHex . '" style="color:#ffffff;font-weight:bold;">' . $total . '</td></tr>';
$pdf->SetFont($pdfFont, '', 9.5);
$pdf->writeHTML('<table width="100%" cellspacing="0" cellpadding="5" border="0">' . $totrows . '</table>', true, false, false, false, '');

# ─── Σημειώσεις / όροι ───
if ($notes) {
    $pdf->Ln(4);
    $pdf->SetFont($pdfFont, 'B', 8.5);
    $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
    $pdf->Cell(0, 4, 'Όροι & σημειώσεις', 0, 1);
    $pdf->SetFont($pdfFont, '', 8);
    $pdf->SetTextColor(70, 85, 110);
    $pdf->MultiCell(170, 4.2, $notes, 0, 'L');
}

# ─── Ισχύς + Αποδοχή ───
$pdf->Ln(6);
$pdf->SetFont($pdfFont, '', 8.5);
$pdf->SetTextColor($mut[0], $mut[1], $mut[2]);
$pdf->MultiCell(170, 4.4,
    'Η παρούσα προσφορά ισχύει έως ' . $validuntil . '. Οι τιμές δεν αποτελούν τιμολόγιο· '
    . 'τιμολόγηση γίνεται μετά την αποδοχή. Για αποδοχή, υπογράψτε παρακάτω ή απαντήστε στο email της προσφοράς.', 0, 'L');
$pdf->Ln(6);

$y = $pdf->GetY();
if ($y > 240) {           // χώρος για υπογραφές — αλλιώς νέα σελίδα
    $pdf->AddPage();
    $y = $pdf->GetY() + 4;
}
$pdf->SetDrawColor(190, 200, 214);
$pdf->SetFont($pdfFont, 'B', 8.5);
$pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
$pdf->SetXY(20, $y);
$pdf->Cell(80, 5, 'Αποδοχή προσφοράς — για τον πελάτη', 0, 0, 'L');
$pdf->SetX(110);
$pdf->Cell(80, 5, 'Για την ' . trim($companyaddress[0]), 0, 1, 'L');
$pdf->Ln(14);
$ys = $pdf->GetY();
$pdf->Line(20, $ys, 95, $ys);
$pdf->Line(110, $ys, 185, $ys);
$pdf->SetFont($pdfFont, '', 7.5);
$pdf->SetTextColor($mut[0], $mut[1], $mut[2]);
$pdf->SetXY(20, $ys + 1);
$pdf->Cell(80, 4, 'Υπογραφή & σφραγίδα · Ημερομηνία', 0, 0, 'L');
$pdf->SetX(110);
$pdf->Cell(80, 4, 'Υπογραφή & σφραγίδα · Ημερομηνία', 0, 1, 'L');

# ─── Υποσέλιδο ───
$pdf->Ln(8);
$pdf->SetFont($pdfFont, '', 8);
$pdf->SetTextColor($brand[0], $brand[1], $brand[2]);
$pdf->Cell(0, 4, 'Ευχαριστούμε για την εμπιστοσύνη σας — θα χαρούμε να συνεργαστούμε!', 0, 1, 'C');
