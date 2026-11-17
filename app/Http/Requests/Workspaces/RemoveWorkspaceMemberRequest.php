<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

/** new_admins: project key => email of the member who takes over where the removed member was the only Admin. */
class RemoveWorkspaceMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'new_admins' => ['nullable', 'array', 'max:100'],
            'new_admins.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
