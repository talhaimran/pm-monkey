<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return ['role' => ['required', Rule::enum(ProjectRole::class)]];
    }

    public function messages(): array
    {
        return ['role.enum' => 'The role must be one of: '.implode(', ', ProjectRole::values()).'.'];
    }
}
