<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CurrentWorkspace;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;

class ProjectPolicy
{
    /** Decision A-D2: project settings, columns, members and invitations are never available to agents. */
    public const PEOPLE_ONLY = 'Only people can change project settings. Do this in the web app.';

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
        $current = app(CurrentWorkspace::class);
        if (! $current->id()) {
            return Response::deny('You are not a member of any workspace yet. Ask an admin to invite you.');
        }

        return $this->peopleOnly($user, Gate::forUser($user)->inspect('createProject', $current->get()));
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

    /** Edit settings and columns, manage members and invitations: project admins, and only people (A-D2). */
    public function manage(User $user, Project $project): Response
    {
        $role = $project->roleFor($user);

        return $this->peopleOnly($user, match (true) {
            (bool) $role?->canManage() => Response::allow(),
            ! $role && $this->seesAll($user, $project) => $this->joinFirst($project),
            default => Response::deny("Your role in {$project->key} is ".($role?->value ?? 'none').'; only admins can do this.'),
        });
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

    /** Owner's role first ($allowed), then A-D2: any agent token is refused, whatever its level. */
    private function peopleOnly(User $user, Response $allowed): Response
    {
        return $allowed->denied() || ! $user->currentAccessToken() instanceof PersonalAccessToken
            ? $allowed
            : Response::deny(self::PEOPLE_ONLY);
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
