<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class DestroyProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return ['confirm' => ['accepted']];
    }

    public function messages(): array
    {
        return ['confirm.accepted' => 'Deleting a project permanently deletes all of its issues. Resend with confirm: true to proceed.'];
    }
}
