<?php

namespace App\Http\Requests;

use App\Services\Import\DocumentImporter;
use Illuminate\Foundation\Http\FormRequest;

class ImportFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document === null || $this->user()->can('update', $document);
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                // Checked against the client extension: .md files have no reliable MIME type.
                'extensions:'.implode(',', DocumentImporter::EXTENSIONS),
                'max:'.DocumentImporter::MAX_KILOBYTES,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choose a file to import.',
            'file.extensions' => 'Unsupported file type. Upload a .txt, .md or .docx file.',
            'file.max' => 'Files can be up to 5 MB.',
        ];
    }
}
