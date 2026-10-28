<?php

namespace App\Http\Requests\Board;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoardFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'assignee' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(IssueType::class)],
            'priority' => ['nullable', Rule::enum(IssuePriority::class)],
            'label' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.enum' => 'The type filter must be one of: '.implode(', ', IssueType::values()).'.',
            'priority.enum' => 'The priority filter must be one of: '.implode(', ', IssuePriority::values()).'.',
        ];
    }
}
