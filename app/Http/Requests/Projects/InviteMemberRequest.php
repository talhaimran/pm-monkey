<?php

namespace App\Http\Requests\Projects;

class InviteMemberRequest extends UpdateMemberRoleRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'email' => ['required', 'email', 'max:255']];
    }
}
