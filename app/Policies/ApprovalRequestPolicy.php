<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;

class ApprovalRequestPolicy
{
    /** Spec 8 (A2): the agent's owner or an Admin of the issue's project, who must be allowed to make the move themselves. */
    public function decide(User $user, ApprovalRequest $approval): Response
    {
        if ($user->currentAccessToken() instanceof PersonalAccessToken) {
            return Response::deny('Only people can decide approval requests. Do this in the web app.');
        }

        $project = $approval->issue->project;
        if ($approval->owner_user_id !== $user->id && ! $project->roleFor($user)?->canManage()) {
            return Response::deny("Only {$approval->agent->owner->name} or an Admin of {$project->key} can decide this request.");
        }

        return Gate::forUser($user)->inspect('update', $approval->issue);
    }
}
