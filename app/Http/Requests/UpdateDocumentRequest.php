<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    /** Roughly 1 MB of HTML: plenty for a document, small enough to reject abuse. */
    public const MAX_CONTENT_LENGTH = 1_000_000;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('document'));
    }

    public function rules(): array
    {
        return [
            // The version the client last saw; a mismatch means someone else saved first.
            'version' => ['required', 'integer', 'min:1'],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'content' => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_CONTENT_LENGTH],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Give the document a title.',
            'title.max' => 'Titles can be up to 200 characters.',
            'content.max' => 'This document is too large to save (limit is about 1 MB).',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim(preg_replace('/\s+/u', ' ', $this->input('title')))]);
        }
    }
}
