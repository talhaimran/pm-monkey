<?php

namespace App\Http\Requests\Board;

use Illuminate\Foundation\Http\FormRequest;

class DeleteColumnRequest extends FormRequest
{
    public function rules(): array
    {
        return ['move_issues_to' => ['nullable', 'string', 'max:50']];
    }
}
