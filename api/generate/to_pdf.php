<?php
/**
 * Convert markdown text to PDF using FPDF.
 */
require_once __DIR__ . '/../../lib/fpdf/fpdf.php';

function generate_pdf(string $markdown): string
{
    $pdf = new FPDF();
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 11);

    $lines = preg_split('/\r?\n/', $markdown);
    $inCode = false;
    $inList = false;

    foreach ($lines as $line) {
        $line = rtrim($line);

        // Code block toggle
        if (preg_match('/^```/', $line)) {
            $inCode = !$inCode;
            if (!$inCode) $pdf->Ln(2);
            continue;
        }

        if ($inCode) {
            $pdf->SetFont('Courier', '', 9);
            $pdf->Cell(0, 5, html_entity_decode($line, ENT_QUOTES, 'UTF-8'));
            $pdf->Ln();
            continue;
        }

        $pdf->SetFont('Helvetica', '', 11);

        // Headings
        if (preg_match('/^# (.+)/', $line, $m)) {
            $pdf->SetFont('Helvetica', 'B', 18);
            $pdf->Ln(4);
            $pdf->Cell(0, 10, $m[1]);
            $pdf->Ln(8);
        } elseif (preg_match('/^## (.+)/', $line, $m)) {
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->Ln(3);
            $pdf->Cell(0, 8, $m[1]);
            $pdf->Ln(6);
        } elseif (preg_match('/^### (.+)/', $line, $m)) {
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->Ln(2);
            $pdf->Cell(0, 7, $m[1]);
            $pdf->Ln(5);
        } elseif (preg_match('/^[-*] (.+)/', $line, $m)) {
            $inList = true;
            $pdf->Cell(5);
            $pdf->Cell(0, 6, '- ' . $m[1]);
            $pdf->Ln();
        } elseif (trim($line) === '') {
            if ($inList) { $pdf->Ln(2); $inList = false; }
            else $pdf->Ln(4);
        } elseif (preg_match('/^\\|/', $line)) {
            // Table row — skip header separator lines
            if (preg_match('/^[-:| ]+$/', $line)) continue;
            $cells = explode('|', trim($line, '|'));
            foreach ($cells as $cell) {
                $pdf->Cell(40, 6, trim(strip_tags($cell)), 1);
            }
            $pdf->Ln();
        } elseif (preg_match('/^---/', $line)) {
            $pdf->Ln(2);
            $pdf->Cell(0, 0, str_repeat('_', 180));
            $pdf->Ln(4);
        } else {
            // Normal paragraph
            $text = strip_tags($line);
            $pdf->MultiCell(0, 5.5, $text);
            $pdf->Ln(1);
        }
    }

    return $pdf->Output('S');
}
