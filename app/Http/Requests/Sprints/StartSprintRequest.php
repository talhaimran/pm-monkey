<?php

namespace App\Http\Requests\Sprints;

use Illuminate\Foundation\Http\FormRequest;

class StartSprintRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }
}
