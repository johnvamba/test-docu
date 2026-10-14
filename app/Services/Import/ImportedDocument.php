<?php

namespace App\Services\Import;

final readonly class ImportedDocument
{
    public function __construct(
        public string $title,
        public string $html,
        public string $sourceFilename,
    ) {}
}
