<?php

namespace App\Http\Requests\Workspaces;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A workspace role (Admin or Member; Owners are made from Members) and optionally a project and project role. */
class InviteToWorkspaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in([WorkspaceRole::Admin->value, WorkspaceRole::Member->value])],
            'project' => ['nullable', 'string', 'max:10'],
            'project_role' => ['nullable', Rule::enum(ProjectRole::class)],
        ];
    }

    public function messages(): array
    {
        return ['role.in' => 'Invite as admin or member. Make someone an Owner from Members once they have joined.'];
    }
}
