<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'max:'.UpdateDocumentRequest::MAX_CONTENT_LENGTH],
        ];
    }
}
