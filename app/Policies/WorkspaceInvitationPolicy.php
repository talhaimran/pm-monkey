<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Auth\Access\Response;

class WorkspaceInvitationPolicy
{
    public function accept(User $user, WorkspaceInvitation $invitation): Response
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
