<?php

namespace App\Services\Import;

use App\Support\HtmlSanitizer;
use Illuminate\Http\UploadedFile;

/**
 * Turns an uploaded file into editor-ready HTML.
 *
 * Supported: .txt, .md / .markdown, .docx. Everything is passed through the
 * same sanitizer as editor saves, so an import can never smuggle in markup the
 * editor would not produce itself.
 */
class DocumentImporter
{
    public const EXTENSIONS = ['txt', 'md', 'markdown', 'docx'];

    public const MAX_KILOBYTES = 5120;

    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly DocxConverter $docx,
    ) {}

    public function import(UploadedFile $file): ImportedDocument
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $html = match ($extension) {
            'txt' => $this->fromText($this->readText($file)),
            'md', 'markdown' => $this->fromMarkdown($this->readText($file)),
            'docx' => $this->docx->convert($file->getRealPath()),
            default => throw new ImportException('Unsupported file type. Upload a .txt, .md or .docx file.'),
        };

        $html = $this->sanitizer->clean($html);

        if (HtmlSanitizer::toText($html) === '') {
            throw new ImportException('That file has no readable text to import.');
        }

        return new ImportedDocument(
            title: $this->titleFrom($file->getClientOriginalName()),
            html: $html,
            sourceFilename: mb_substr($file->getClientOriginalName(), 0, 255),
        );
    }

    public function fromText(string $text): string
    {
        $blocks = preg_split('/\R{2,}/u', trim($text)) ?: [];

        return collect($blocks)
            ->map(fn (string $block) => '<p>'.preg_replace('/\R/u', '<br>', e(trim($block))).'</p>')
            ->implode('');
    }

    public function fromMarkdown(string $markdown): string
    {
        return \Illuminate\Support\Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    private function readText(UploadedFile $file): string
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // UTF-8 BOM

        if (! mb_check_encoding($raw, 'UTF-8')) {
            // Most non-UTF-8 text files in the wild are Windows-1252.
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return str_replace("\r\n", "\n", $raw);
    }

    private function titleFrom(string $filename): string
    {
        $title = trim(preg_replace('/[_\s]+/u', ' ', pathinfo($filename, PATHINFO_FILENAME)));

        return mb_substr($title !== '' ? $title : 'Imported document', 0, 200);
    }
}
