<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Spec 8 (A1): the owner manages their agent; a workspace Owner or Admin may also pause, resume or revoke it. */
class AgentPolicy
{
    public function update(User $user, Agent $agent): Response
    {
        return $this->ownerOnly($user, $agent);
    }

    public function regenerate(User $user, Agent $agent): Response
    {
        return $this->ownerOnly($user, $agent);
    }

    public function pause(User $user, Agent $agent): Response
    {
        return $this->ownerOrAdmin($user, $agent);
    }

    public function resume(User $user, Agent $agent): Response
    {
        return $this->ownerOrAdmin($user, $agent);
    }

    public function revoke(User $user, Agent $agent): Response
    {
        return $this->ownerOrAdmin($user, $agent);
    }

    private function ownerOnly(User $user, Agent $agent): Response
    {
        return $user->id === $agent->owner_user_id ? Response::allow() : Response::deny("Only {$agent->owner->name} can change {$agent->label()}.");
    }

    private function ownerOrAdmin(User $user, Agent $agent): Response
    {
        $role = $user->workspaceRole($agent->workspace);

        return match (true) {
            $user->id === $agent->owner_user_id && $role !== null => Response::allow(),
            in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true) => Response::allow(),
            default => Response::deny("Only {$agent->owner->name} or an Owner or Admin of {$agent->workspace->name} can do this."),
        };
    }
}
