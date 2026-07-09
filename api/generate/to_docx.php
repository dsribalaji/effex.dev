<?php
/**
 * Convert markdown text to DOCX using DocxWriter.
 */
require_once __DIR__ . '/../../lib/docx_writer.php';

function generate_docx(string $markdown): string
{
    $docx = new DocxWriter();
    $lines = preg_split('/\r?\n/', $markdown);
    $inCode = false;
    $inList = false;
    $inTable = false;
    $tableHeaders = [];
    $tableRows = [];
    $i = 0;

    while ($i < count($lines)) {
        $line = rtrim($lines[$i]);

        if (preg_match('/^```/', $line)) {
            $inCode = !$inCode;
            $i++;
            continue;
        }

        if ($inCode) {
            $docx->addParagraph($line);
            $i++;
            continue;
        }

        if (preg_match('/^# (.+)/', $line, $m)) {
            $docx->addHeading($m[1], 1);
            $inList = false;
        } elseif (preg_match('/^## (.+)/', $line, $m)) {
            $docx->addHeading($m[1], 2);
            $inList = false;
        } elseif (preg_match('/^### (.+)/', $line, $m)) {
            $docx->addHeading($m[1], 3);
            $inList = false;
        } elseif (preg_match('/^[-*] (.+)/', $line, $m)) {
            $docx->addBullet($m[1]);
            $inList = true;
        } elseif (preg_match('/^\\|(.+)\\|/', $line)) {
            if (preg_match('/^[-:| ]+$/', $line)) { $i++; continue; }
            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (empty($tableHeaders)) {
                $tableHeaders = $cells;
            } else {
                $tableRows[] = $cells;
            }
            $inTable = true;
        } elseif (trim($line) === '') {
            if ($inTable && $tableHeaders) {
                $docx->addTable($tableHeaders, $tableRows);
                $tableHeaders = [];
                $tableRows = [];
                $inTable = false;
            }
            $inList = false;
        } else {
            $text = strip_tags($line);
            if ($text !== '') {
                $docx->addParagraph($text);
            }
            $inList = false;
        }

        $i++;
    }

    // Flush remaining table
    if ($inTable && $tableHeaders) {
        $docx->addTable($tableHeaders, $tableRows);
    }

    return $docx->build();
}
