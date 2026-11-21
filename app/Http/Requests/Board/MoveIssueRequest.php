<?php

namespace App\Http\Requests\Board;

use Illuminate\Foundation\Http\FormRequest;

class MoveIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'integer', 'min:0', 'prohibits:after'],
            'after' => ['nullable', 'string', 'max:30'],
            // A-L5: why an agent asks to move to Done, shown to the person who decides.
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return ['position.prohibits' => 'Send either position or after, not both.'];
    }
}
