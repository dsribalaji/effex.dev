<?php
/**
 * Minimal DOCX (OOXML) writer using PHP's built-in ZipArchive.
 * No external dependencies, no Composer.
 */
class DocxWriter
{
    private array $paragraphs = [];

    public function addHeading(string $text, int $level = 1): void
    {
        $style = 'Heading' . min($level, 9);
        $this->paragraphs[] = [
            'text' => $text,
            'style' => $style,
            'bold' => true,
            'size' => [44, 36, 28, 24, 22, 20, 18, 16, 14][min($level - 1, 8)],
        ];
    }

    public function addParagraph(string $text): void
    {
        $this->paragraphs[] = [
            'text' => $text,
            'style' => 'Normal',
            'bold' => false,
            'size' => 22,
        ];
    }

    public function addBullet(string $text): void
    {
        $this->paragraphs[] = [
            'text' => $text,
            'style' => 'ListBullet',
            'bold' => false,
            'size' => 22,
            'bullet' => true,
        ];
    }

    public function addTable(array $headers, array $rows): void
    {
        $this->paragraphs[] = [
            'type' => 'table',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Generate and return the .docx file contents as a string.
     */
    public function build(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx_');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());

        // _rels/.rels
        $zip->addFromString('_rels/.rels', $this->relsXml());

        // word/_rels/document.xml.rels
        $zip->addFromString('word/_rels/document.xml.rels', $this->wordRelsXml());

        // word/document.xml
        $zip->addFromString('word/document.xml', $this->documentXml());

        // word/styles.xml
        $zip->addFromString('word/styles.xml', $this->stylesXml());

        $zip->close();

        $content = file_get_contents($path);
        unlink($path);
        return $content;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
    }

    private function wordRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:pPr><w:spacing w:after="200" w:line="276"/></w:pPr>
    <w:rPr><w:sz w:val="22"/><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:pPr><w:spacing w:before="360" w:after="120"/></w:pPr>
    <w:rPr><w:b/><w:sz w:val="36"/><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
    <w:pPr><w:spacing w:before="240" w:after="120"/></w:pPr>
    <w:rPr><w:b/><w:sz w:val="28"/><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading3">
    <w:name w:val="heading 3"/>
    <w:pPr><w:spacing w:before="200" w:after="80"/></w:pPr>
    <w:rPr><w:b/><w:sz w:val="24"/><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="ListBullet">
    <w:name w:val="List Paragraph"/>
    <w:pPr><w:spacing w:after="80"/><w:ind w:left="720" w:hanging="360"/></w:pPr>
    <w:rPr><w:sz w:val="22"/><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr>
  </w:style>
</w:styles>';
    }

    private function documentXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>';

        foreach ($this->paragraphs as $p) {
            if (($p['type'] ?? '') === 'table') {
                $xml .= '<w:tbl>';
                $xml .= '<w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/></w:tblPr>';

                // Header row
                $xml .= '<w:tr>';
                foreach ($p['headers'] as $h) {
                    $xml .= '<w:tc><w:p><w:r><w:rPr><w:b/><w:sz w:val="20"/></w:rPr><w:t>' . htmlspecialchars($h) . '</w:t></w:r></w:p></w:tc>';
                }
                $xml .= '</w:tr>';

                // Data rows
                foreach ($p['rows'] as $row) {
                    $xml .= '<w:tr>';
                    foreach ($row as $cell) {
                        $xml .= '<w:tc><w:p><w:r><w:rPr><w:sz w:val="20"/></w:rPr><w:t>' . htmlspecialchars($cell) . '</w:t></w:r></w:p></w:tc>';
                    }
                    $xml .= '</w:tr>';
                }

                $xml .= '</w:tbl>';
                continue;
            }

            $styleId = $p['style'] ?? 'Normal';
            $text = htmlspecialchars($p['text'] ?? '');

            $xml .= '<w:p>';
            $xml .= '<w:pPr><w:pStyle w:val="' . $styleId . '"/>';
            if (!empty($p['bullet'])) {
                $xml .= '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>';
            }
            $xml .= '</w:pPr>';
            $xml .= '<w:r>';
            if (!empty($p['bold'])) {
                $xml .= '<w:rPr><w:b/></w:rPr>';
            }
            $xml .= '<w:t>' . $text . '</w:t>';
            $xml .= '</w:r>';
            $xml .= '</w:p>';
        }

        $xml .= '</w:body></w:document>';
        return $xml;
    }
}
