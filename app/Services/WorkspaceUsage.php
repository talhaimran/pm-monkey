<?php

namespace App\Services;

use App\Enums\Entitlement;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Spec section 4 billing terms. Billed: workspace Owners and Admins, and anyone Member or Admin in a project.
 * Viewers: everyone else. Pending invitations count as the person they would create, once per address.
 */
class WorkspaceUsage
{
    public function billedUsers(Workspace $workspace): int
    {
        return count(array_filter($this->people($workspace)));
    }

    public function viewers(Workspace $workspace): int
    {
        return count($this->people($workspace)) - $this->billedUsers($workspace);
    }

    public function projects(Workspace $workspace): int
    {
        return $workspace->projects()->count();
    }

    /** Whether $email counts as billed (true), as a Viewer (false), or not at all (null), pending invitations included. */
    public function isBilled(Workspace $workspace, string $email): ?bool
    {
        return $this->people($workspace)[strtolower($email)] ?? null;
    }

    /** The same, from membership alone: null when $email isn't a member. */
    public function memberBilled(Workspace $workspace, string $email): ?bool
    {
        return $this->people($workspace, withInvitations: false)[strtolower($email)] ?? null;
    }

    /** @return array<string, array{allowed: int, current: int}> the enforced limits this workspace is past */
    public function overLimit(Workspace $workspace): array
    {
        $entitlements = app(Entitlements::class);
        $over = [];
        foreach ([Entitlement::BilledUsers, Entitlement::Viewers, Entitlement::Projects] as $key) {
            $allowed = $entitlements->get($workspace, $key);
            $current = $this->current($workspace, $key);
            if ($allowed !== null && $current > $allowed) {
                $over[$key->value] = ['allowed' => $allowed, 'current' => $current];
            }
        }

        return $over;
    }

    public function current(Workspace $workspace, Entitlement $key): int
    {
        return match ($key) {
            Entitlement::BilledUsers => $this->billedUsers($workspace),
            Entitlement::Viewers => $this->viewers($workspace),
            Entitlement::Projects => $this->projects($workspace),
        };
    }

    /**
     * @return array<string, bool> lower-case email => billed
     *
     * shortcut: loads every member and invitation of the workspace (three queries); move to SQL if workspaces grow past a few thousand people.
     */
    private function people(Workspace $workspace, bool $withInvitations = true): array
    {
        $billedProjectRoles = [ProjectRole::Member->value, ProjectRole::Admin->value];
        $inBilledProject = DB::table('project_user')->join('projects', 'projects.id', '=', 'project_user.project_id')
            ->where('projects.workspace_id', $workspace->id)->whereIn('project_user.role', $billedProjectRoles)
            ->distinct()->pluck('project_user.user_id')->flip();

        $people = [];
        foreach ($workspace->members()->get(['users.id', 'users.email']) as $member) {
            $people[strtolower($member->email)] = $member->pivot->role !== WorkspaceRole::Member->value || $inBilledProject->has($member->id);
        }
        if (! $withInvitations) {
            return $people;
        }

        foreach ($workspace->invitations()->get(['email', 'role', 'project_role']) as $invitation) {
            $email = strtolower($invitation->email);
            // Accepting keeps a member's workspace role, so only the project role can make them billed.
            $wouldBe = in_array($invitation->project_role?->value, $billedProjectRoles, true)
                || (! isset($people[$email]) && $invitation->role === WorkspaceRole::Admin);
            $people[$email] = ($people[$email] ?? false) || $wouldBe;
        }

        return $people;
    }
}
