<?php

namespace App\Services\Import;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

/**
 * Minimal .docx -> HTML reader covering exactly what the editor supports:
 * paragraphs, headings 1-3, bold / italic / underline / strikethrough,
 * bulleted and numbered lists, line breaks and table cell text.
 *
 * Deliberately not supported: images, nested list levels (flattened),
 * tables as tables, comments, tracked changes, fonts and colours.
 */
class DocxConverter
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Zip-bomb guard: refuse document.xml larger than this once decompressed. */
    private const MAX_XML_BYTES = 20 * 1024 * 1024;

    /** @var array<string, string> styleId => lowercase style name */
    private array $styles = [];

    /** @var array<string, array<string, string>> numId => [ilvl => numFmt] */
    private array $numbering = [];

    public function convert(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new ImportException('That .docx file could not be opened. Is it a valid Word document?');
        }

        try {
            $documentXml = $this->entry($zip, 'word/document.xml');
            if ($documentXml === null) {
                throw new ImportException('That .docx file has no document body.');
            }
            $this->styles = $this->parseStyles($this->entry($zip, 'word/styles.xml'));
            $this->numbering = $this->parseNumbering($this->entry($zip, 'word/numbering.xml'));
        } finally {
            $zip->close();
        }

        $xpath = $this->xpath($documentXml);
        $body = $xpath->query('/w:document/w:body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new ImportException('That .docx file has no document body.');
        }

        return $this->renderBlocks($xpath, $this->paragraphsIn($xpath, $body));
    }

    /** Top-level paragraphs, with table cells flattened into the flow. */
    private function paragraphsIn(DOMXPath $xpath, DOMElement $body): array
    {
        $out = [];
        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            if ($node->localName === 'p') {
                $out[] = $node;
            } elseif ($node->localName === 'tbl') {
                foreach ($xpath->query('.//w:tc/w:p', $node) as $p) {
                    $out[] = $p;
                }
            } elseif ($node->localName === 'sdt') { // content controls wrap paragraphs
                foreach ($xpath->query('./w:sdtContent/w:p', $node) as $p) {
                    $out[] = $p;
                }
            }
        }

        return $out;
    }

    /** @param DOMElement[] $paragraphs */
    private function renderBlocks(DOMXPath $xpath, array $paragraphs): string
    {
        $html = '';
        $openList = null; // 'ul' | 'ol' | null

        foreach ($paragraphs as $p) {
            $inline = $this->renderRuns($xpath, $p);
            $listType = $this->listType($xpath, $p);

            if ($listType !== $openList && $openList !== null) {
                $html .= "</{$openList}>";
                $openList = null;
            }

            if ($listType !== null) {
                if ($openList === null) {
                    $html .= "<{$listType}>";
                    $openList = $listType;
                }
                $html .= '<li><p>'.$inline.'</p></li>';

                continue;
            }

            if (trim(strip_tags($inline)) === '') {
                continue; // Word uses empty paragraphs for spacing
            }

            $tag = $this->headingTag($xpath, $p) ?? 'p';
            $html .= "<{$tag}>{$inline}</{$tag}>";
        }

        if ($openList !== null) {
            $html .= "</{$openList}>";
        }

        return $html;
    }

    private function renderRuns(DOMXPath $xpath, DOMElement $p): string
    {
        $html = '';
        // Runs directly in the paragraph, inside hyperlinks, or inside simple fields.
        foreach ($xpath->query('./w:r | ./w:hyperlink/w:r | ./w:fldSimple/w:r | ./w:ins/w:r', $p) as $run) {
            $text = '';
            foreach ($run->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }
                $text .= match ($child->localName) {
                    't' => e($child->textContent),
                    'tab' => ' ',
                    'br', 'cr' => '<br>',
                    default => '',
                };
            }
            if ($text === '') {
                continue;
            }

            if ($this->flag($xpath, $run, 'strike')) {
                $text = "<s>{$text}</s>";
            }
            if ($this->flag($xpath, $run, 'u')) {
                $text = "<u>{$text}</u>";
            }
            if ($this->flag($xpath, $run, 'i')) {
                $text = "<em>{$text}</em>";
            }
            if ($this->flag($xpath, $run, 'b')) {
                $text = "<strong>{$text}</strong>";
            }
            $html .= $text;
        }

        return $html;
    }

    /** Toggle properties are on when present, unless w:val is false/0/none. */
    private function flag(DOMXPath $xpath, DOMElement $run, string $name): bool
    {
        $node = $xpath->query("./w:rPr/w:{$name}", $run)->item(0);
        if (! $node instanceof DOMElement) {
            return false;
        }
        $val = strtolower($node->getAttributeNS(self::W, 'val'));

        return ! in_array($val, ['0', 'false', 'none', 'off'], true);
    }

    private function headingTag(DOMXPath $xpath, DOMElement $p): ?string
    {
        $styleId = $xpath->query('./w:pPr/w:pStyle/@w:val', $p)->item(0)?->nodeValue;
        if ($styleId === null) {
            return null;
        }
        $name = $this->styles[$styleId] ?? strtolower($styleId);

        if ($name === 'title') {
            return 'h1';
        }
        if (preg_match('/^heading\s?(\d)$/', $name, $m)) {
            return 'h'.min(3, max(1, (int) $m[1]));
        }

        return null;
    }

    private function listType(DOMXPath $xpath, DOMElement $p): ?string
    {
        $numId = $xpath->query('./w:pPr/w:numPr/w:numId/@w:val', $p)->item(0)?->nodeValue;
        if ($numId === null || $numId === '0') {
            // List styles applied by name, without explicit numbering properties.
            $styleId = $xpath->query('./w:pPr/w:pStyle/@w:val', $p)->item(0)?->nodeValue;
            $name = $styleId ? ($this->styles[$styleId] ?? strtolower($styleId)) : '';

            return match (true) {
                str_starts_with($name, 'list bullet') => 'ul',
                str_starts_with($name, 'list number') => 'ol',
                default => null,
            };
        }
        $level = $xpath->query('./w:pPr/w:numPr/w:ilvl/@w:val', $p)->item(0)?->nodeValue ?? '0';
        $format = $this->numbering[$numId][$level] ?? $this->numbering[$numId]['0'] ?? 'bullet';

        return $format === 'bullet' ? 'ul' : 'ol';
    }

    private function parseStyles(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }
        $xpath = $this->xpath($xml);
        $styles = [];
        foreach ($xpath->query('//w:style[@w:styleId]') as $style) {
            $name = $xpath->query('./w:name/@w:val', $style)->item(0)?->nodeValue;
            if ($name !== null) {
                $styles[$style->getAttributeNS(self::W, 'styleId')] = strtolower($name);
            }
        }

        return $styles;
    }

    private function parseNumbering(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }
        $xpath = $this->xpath($xml);

        $abstract = [];
        foreach ($xpath->query('//w:abstractNum') as $node) {
            $levels = [];
            foreach ($xpath->query('./w:lvl', $node) as $lvl) {
                $levels[$lvl->getAttributeNS(self::W, 'ilvl')] =
                    $xpath->query('./w:numFmt/@w:val', $lvl)->item(0)?->nodeValue ?? 'decimal';
            }
            $abstract[$node->getAttributeNS(self::W, 'abstractNumId')] = $levels;
        }

        $map = [];
        foreach ($xpath->query('//w:num') as $num) {
            $abstractId = $xpath->query('./w:abstractNumId/@w:val', $num)->item(0)?->nodeValue;
            $map[$num->getAttributeNS(self::W, 'numId')] = $abstract[$abstractId] ?? [];
        }

        return $map;
    }

    private function entry(ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            return null;
        }
        if ($stat['size'] > self::MAX_XML_BYTES) {
            throw new ImportException('That .docx file is too large to import.');
        }
        $content = $zip->getFromName($name);

        return $content === false ? null : $content;
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // LIBXML_NONET: never fetch external resources while parsing untrusted XML.
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new ImportException('That .docx file is damaged and could not be read.');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::W);

        return $xpath;
    }
}
