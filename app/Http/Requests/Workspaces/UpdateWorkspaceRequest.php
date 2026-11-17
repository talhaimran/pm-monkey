<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

/** General settings: name, slug (checked by WorkspaceService) and who can create projects. */
class UpdateWorkspaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:40'],
            'members_can_create_projects' => ['required', 'boolean'],
        ];
    }
}
