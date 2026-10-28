<?php

namespace App\Http\Requests\Issues;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'type' => [Rule::enum(IssueType::class)],
            'priority' => [Rule::enum(IssuePriority::class)],
            'status' => ['string', 'max:50'],
            'assignee' => ['nullable', 'string', 'max:255'],
            'labels' => ['nullable', 'array', 'max:20'],
            'labels.*' => ['string', 'max:50'],
            'story_points' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'epic' => ['nullable', 'string', 'max:30'],
            'parent' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.enum' => 'The type must be one of: '.implode(', ', IssueType::values()).'.',
            'priority.enum' => 'The priority must be one of: '.implode(', ', IssuePriority::values()).'.',
            'due_date.date_format' => 'The due date must be a date like 2026-12-31.',
        ];
    }
}
