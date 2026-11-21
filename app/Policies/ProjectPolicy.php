<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CurrentWorkspace;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class ProjectPolicy
{
    public function view(User $user, Project $project): Response
    {
        return $project->roleFor($user) || $this->seesAll($user, $project)
            ? Response::allow()
            : Response::deny("You are not a member of {$project->key}.");
    }

    /** Spec 7.0b: a workspace Owner or Admin joins any project in it as Admin. Web only. */
    public function join(User $user, Project $project): Response
    {
        if ($denied = $this->writable($user)) {
            return $denied;
        }

        return Gate::forUser($user)->inspect('viewAllProjects', $this->workspaceOf($project));
    }

    public function create(User $user): Response
    {
        if ($denied = $this->writable($user)) {
            return $denied;
        }

        $current = app(CurrentWorkspace::class);
        if (! $current->id()) {
            return Response::deny('You are not a member of any workspace yet. Ask an admin to invite you.');
        }

        return $user->tokenProjectIds() === null
            ? Gate::forUser($user)->inspect('createProject', $current->get())
            : Response::deny('This agent is limited to specific projects, so it cannot create projects. Use an agent with access to all projects.');
    }

    /** Create and edit issues, comments and labels: members and admins with a writable token. */
    public function contribute(User $user, Project $project): Response
    {
        if ($denied = $this->writable($user)) {
            return $denied;
        }

        $role = $project->roleFor($user);

        return match (true) {
            (bool) $role?->canWrite() => Response::allow(),
            ! $role && $this->seesAll($user, $project) => $this->joinFirst($project),
            default => Response::deny("Your role in {$project->key} is ".($role?->value ?? 'none')."; viewers can't make changes."),
        };
    }

    /** Edit settings, manage members and invitations. */
    public function manage(User $user, Project $project): Response
    {
        if ($denied = $this->writable($user)) {
            return $denied;
        }

        $role = $project->roleFor($user);

        return match (true) {
            (bool) $role?->canManage() => Response::allow(),
            ! $role && $this->seesAll($user, $project) => $this->joinFirst($project),
            default => Response::deny("Your role in {$project->key} is ".($role?->value ?? 'none').'; only admins can do this.'),
        };
    }

    public function update(User $user, Project $project): Response
    {
        return $this->manage($user, $project);
    }

    public function delete(User $user, Project $project): Response
    {
        return $this->deletable($user, $this->manage($user, $project));
    }

    /** Delete issues, comments, labels and sprints: contribute, and an agent needs the Delete level. */
    public function remove(User $user, Project $project): Response
    {
        return $this->deletable($user, $this->contribute($user, $project));
    }

    /** Delete columns: manage, and an agent needs the Delete level. */
    public function removeColumn(User $user, Project $project): Response
    {
        return $this->deletable($user, $this->manage($user, $project));
    }

    /** Workspace Owners and Admins see every project, read-only until they join it (spec 7.0b). */
    private function seesAll(User $user, Project $project): bool
    {
        return Gate::forUser($user)->allows('viewAllProjects', $this->workspaceOf($project));
    }

    /** The current workspace when it is the project's (always, for routed lookups), so policy checks don't lazy-load. */
    private function workspaceOf(Project $project): Workspace
    {
        $current = app(CurrentWorkspace::class);

        return $current->id() === $project->workspace_id ? $current->get() : $project->workspace()->firstOrFail();
    }

    private function joinFirst(Project $project): Response
    {
        return Response::deny("You haven't joined {$project->key}. Join it as Admin to make changes.");
    }

    /** Owner's role first ($allowed), then the agent's level (spec 8, A1). */
    private function deletable(User $user, Response $allowed): Response
    {
        return $allowed->denied() || $user->canDelete()
            ? $allowed
            : Response::deny("This agent can't delete. Its owner can allow deletes in Settings → Agents.");
    }

    private function writable(User $user): ?Response
    {
        return $user->canWrite()
            ? null
            : Response::deny('This agent is read-only. Its owner can change its level in Settings → Agents.');
    }
}
