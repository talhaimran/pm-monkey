<?php

namespace App\Http\Requests\Issues;

use Illuminate\Foundation\Http\FormRequest;

class DestroyIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return ['confirm' => ['accepted']];
    }

    public function messages(): array
    {
        return ['confirm.accepted' => 'Deleting an issue also deletes its sub-tasks, comments and history. Resend with confirm: true to proceed.'];
    }
}
