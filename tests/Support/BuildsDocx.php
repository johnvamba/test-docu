<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/** Builds a small but structurally real .docx (styles + numbering) for import tests. */
trait BuildsDocx
{
    protected function docxUpload(string $name): UploadedFile
    {
        $w = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';

        $document = <<<XML
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <w:document {$w}><w:body>
              <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Quarterly review</w:t></w:r></w:p>
              <w:p/>
              <w:p>
                <w:r><w:t xml:space="preserve">Plain </w:t></w:r>
                <w:r><w:rPr><w:b/></w:rPr><w:t>bold</w:t></w:r>
                <w:r><w:t xml:space="preserve"> </w:t></w:r>
                <w:r><w:rPr><w:i/><w:b w:val="0"/></w:rPr><w:t>italic</w:t></w:r>
                <w:r><w:t xml:space="preserve"> </w:t></w:r>
                <w:r><w:rPr><w:u w:val="single"/></w:rPr><w:t>underlined</w:t></w:r>
              </w:p>
              <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Bullet one</w:t></w:r></w:p>
              <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Bullet two</w:t></w:r></w:p>
              <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr></w:pPr><w:r><w:t>Step one</w:t></w:r></w:p>
              <w:p><w:pPr><w:pStyle w:val="Heading2"/></w:pPr><w:r><w:t>Next steps</w:t></w:r></w:p>
            </w:body></w:document>
            XML;

        $styles = <<<XML
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <w:styles {$w}>
              <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/></w:style>
              <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/></w:style>
            </w:styles>
            XML;

        $numbering = <<<XML
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <w:numbering {$w}>
              <w:abstractNum w:abstractNumId="10"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum>
              <w:abstractNum w:abstractNumId="20"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl></w:abstractNum>
              <w:num w:numId="1"><w:abstractNumId w:val="10"/></w:num>
              <w:num w:numId="2"><w:abstractNumId w:val="20"/></w:num>
            </w:numbering>
            XML;

        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', trim($document));
        $zip->addFromString('word/styles.xml', trim($styles));
        $zip->addFromString('word/numbering.xml', trim($numbering));
        $zip->close();

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }
}
