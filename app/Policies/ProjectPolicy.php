<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function view(User $user, Project $project): Response
    {
        return $project->roleFor($user)
            ? Response::allow()
            : Response::deny("You are not a member of {$project->key}.");
    }

    public function create(User $user): Response
    {
        return $this->writable($user) ?? Response::allow();
    }

    /** Edit settings, manage members and invitations. */
    public function manage(User $user, Project $project): Response
    {
        if ($denied = $this->writable($user)) {
            return $denied;
        }

        $role = $project->roleFor($user);

        return $role?->canManage()
            ? Response::allow()
            : Response::deny("Your role in {$project->key} is ".($role?->value ?? 'none').'; only admins can do this.');
    }

    public function update(User $user, Project $project): Response
    {
        return $this->manage($user, $project);
    }

    public function delete(User $user, Project $project): Response
    {
        return $this->manage($user, $project);
    }

    private function writable(User $user): ?Response
    {
        return $user->canWrite()
            ? null
            : Response::deny('This API token is read-only. Use a read-write token to make changes.');
    }
}
