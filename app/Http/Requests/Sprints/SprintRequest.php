<?php

namespace App\Http\Requests\Sprints;

use Illuminate\Foundation\Http\FormRequest;

class SprintRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100', 'not_regex:/^\s*(backlog|active)\s*$/i'],
            'goal' => ['nullable', 'string', 'max:10000'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.not_regex' => '"backlog" and "active" are reserved words; choose another sprint name.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
            'start_date.date_format' => 'Dates look like 2026-12-31.',
            'end_date.date_format' => 'Dates look like 2026-12-31.',
        ];
    }
}
