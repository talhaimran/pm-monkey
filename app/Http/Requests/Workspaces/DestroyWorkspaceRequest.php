<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class DestroyWorkspaceRequest extends FormRequest
{
    public function rules(): array
    {
        return ['confirm_slug' => ['required', 'string', 'max:40']];
    }

    public function messages(): array
    {
        return ['confirm_slug.required' => 'Type the workspace address to confirm.'];
    }
}
