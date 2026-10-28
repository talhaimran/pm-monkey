<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TokenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'access' => ['required', 'in:read,write'],
            'projects' => ['nullable', 'array'],
            'projects.*' => ['string', 'max:10'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'access.in' => 'Access must be read (read-only) or write (read-write).',
            'expires_in_days.max' => 'Tokens can last at most 365 days; leave it empty for no expiry.',
        ];
    }
}
