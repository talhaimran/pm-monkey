<?php

namespace App\Http\Requests\Sprints;

use Illuminate\Foundation\Http\FormRequest;

class RankIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sprint' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'integer', 'min:0', 'prohibits:after'],
            'after' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return ['position.prohibits' => 'Send either position or after, not both.'];
    }
}
