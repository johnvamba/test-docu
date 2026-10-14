<?php

namespace App\Http\Requests;

use App\Enums\DocumentRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShareDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('share', $this->route('document'));
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', Rule::exists('users', 'email')],
            'role' => ['required', Rule::in(DocumentRole::shareable())],
        ];
    }

    public function messages(): array
    {
        return [
            'email.exists' => 'No Ajaia account uses that email address.',
            'role.in' => 'Choose viewer or editor.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (strcasecmp((string) $this->input('email'), $this->user()->email) === 0) {
                    $validator->errors()->add('email', 'You already own this document.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }
}
