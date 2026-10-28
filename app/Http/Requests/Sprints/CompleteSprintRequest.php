<?php

namespace App\Http\Requests\Sprints;

use Illuminate\Foundation\Http\FormRequest;

class CompleteSprintRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'confirm' => ['accepted'],
            'move_open_issues_to' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.accepted' => 'Completing a sprint closes it for good and moves its unfinished issues. Resend with confirm: true to proceed.',
            'move_open_issues_to.required' => 'Say where unfinished issues go: "backlog" or the name of a planned sprint.',
        ];
    }
}
