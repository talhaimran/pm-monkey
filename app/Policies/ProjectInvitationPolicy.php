<?php

namespace App\Policies;

use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectInvitationPolicy
{
    public function accept(User $user, ProjectInvitation $invitation): Response
    {
        if (strtolower($user->email) !== $invitation->email) {
            return Response::deny('This invitation was sent to a different email address.');
        }

        // Without this, anyone could register with an invited address and take the role.
        return $user->hasVerifiedEmail()
            ? Response::allow()
            : Response::deny('Verify your email address before accepting invitations: use "Send verification link" on the Projects page.');
    }
}
