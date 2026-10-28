<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function rules(): array
    {
        // MySQL's case-insensitive collation makes "unique" catch "web" vs "WEB"; the service uppercases.
        return [
            'key' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9]{1,9}$/', 'unique:projects,key'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'The key must be 2-10 characters: a letter followed by letters or digits (e.g. WEB).',
            'key.unique' => 'The key is already used by another project. Pick a different key.',
        ];
    }
}
