<?php

namespace App\Policies;

use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectInvitationPolicy
{
    public function accept(User $user, ProjectInvitation $invitation): Response
    {
        return strtolower($user->email) === $invitation->email
            ? Response::allow()
            : Response::deny('This invitation was sent to a different email address.');
    }
}
