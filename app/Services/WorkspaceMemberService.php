<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Workspace roles and removal (spec 7.0b). Every change locks the workspace row and re-reads both roles, so two
 * Owners demoting or removing each other at the same moment can't leave the workspace without one.
 */
class WorkspaceMemberService
{
    public const LAST_OWNER = 'A workspace needs at least one Owner. Make someone else an Owner first.';

    public const OWNERS_ONLY = 'Only Owners can add, change or remove Owners.';

    public function __construct(private MembershipService $memberships) {}

    public function changeRole(Workspace $workspace, User $actor, User $member, WorkspaceRole $role): void
    {
        DB::transaction(function () use ($workspace, $actor, $member, $role) {
            $current = $this->lockAndCheck($workspace, $actor, $member, $role === WorkspaceRole::Owner, 'role');
            if ($current === WorkspaceRole::Owner && $role !== WorkspaceRole::Owner) {
                $this->ensureAnotherOwner($workspace, $member, 'role');
            }
            $workspace->members()->updateExistingPivot($member->id, ['role' => $role->value]);
        });
    }

    /**
     * Takes $member out of the workspace, every project in it and their tokens for it. Anyone may leave; removing
     * someone else needs an Owner or Admin. $newAdmins (project key => email) names a new Admin for each project
     * $member is the only Admin of.
     *
     * @param  array<string, string>  $newAdmins
     */
    public function remove(Workspace $workspace, User $actor, User $member, array $newAdmins = []): void
    {
        DB::transaction(function () use ($workspace, $actor, $member, $newAdmins) {
            if ($this->lockAndCheck($workspace, $actor, $member, false, 'member') === WorkspaceRole::Owner) {
                $this->ensureAnotherOwner($workspace, $member, 'member');
            }

            $keys = $this->memberships->soleAdminProjectKeys($member, $workspace);
            $missing = array_values(array_diff($keys, array_keys(array_filter($newAdmins))));
            if ($missing) {
                throw ValidationException::withMessages([
                    'new_admins' => "{$member->email} is the only admin of ".implode(', ', $missing).'. Pick a new admin for each first.',
                ]);
            }
            foreach ($keys as $key) {
                $admin = $workspace->members()->where('email', strtolower(trim($newAdmins[$key])))->first();
                if (! $admin || $admin->is($member)) {
                    throw ValidationException::withMessages([
                        "new_admins.{$key}" => "Pick a member of {$workspace->name} other than {$member->email} as the new admin of {$key}.",
                    ]);
                }
                $workspace->projects()->firstWhere('key', $key)->members()->syncWithoutDetaching([$admin->id => ['role' => ProjectRole::Admin->value]]);
            }

            $member->projects()->detach($workspace->projects()->pluck('id'));
            $member->tokens()->where('workspace_id', $workspace->id)->delete();
            $workspace->members()->detach($member->id);
        });
    }

    /** Spec 7.0b: a workspace Owner or Admin joins any project as its Admin (authorized by ProjectPolicy::join). */
    public function joinProjectAsAdmin(Project $project, User $actor): void
    {
        $project->members()->syncWithoutDetaching([$actor->id => ['role' => ProjectRole::Admin->value]]);
    }

    /** E7: names of workspaces $user is the only Owner of; their account can't be deleted while this isn't empty. */
    public function soleOwnerOf(User $user): array
    {
        return $user->workspaces()->wherePivot('role', WorkspaceRole::Owner->value)
            ->withCount(['members as owner_count' => fn ($q) => $q->where('workspace_user.role', WorkspaceRole::Owner->value)])
            ->orderBy('name')->get()->where('owner_count', 1)->pluck('name')->all();
    }

    /** Locks the workspace, then checks the rules on fresh roles; returns $member's current role. */
    private function lockAndCheck(Workspace $workspace, User $actor, User $member, bool $makesOwner, string $field): WorkspaceRole
    {
        Workspace::whereKey($workspace->id)->lockForUpdate()->first();
        $actorRole = $actor->workspaceRole($workspace);
        $memberRole = $member->workspaceRole($workspace);

        $deny = fn (string $message, int $status = 422) => ValidationException::withMessages([$field => $message])->status($status);
        if (! $memberRole) {
            throw $deny("{$member->email} is not a member of {$workspace->name}.", 404);
        }
        $leaving = $field === 'member' && $actor->is($member);
        if (! $leaving && ! in_array($actorRole, [WorkspaceRole::Owner, WorkspaceRole::Admin], true)) {
            throw $deny("Only Owners and Admins of {$workspace->name} can do this.", 403);
        }
        if (! $leaving && ($makesOwner || $memberRole === WorkspaceRole::Owner) && $actorRole !== WorkspaceRole::Owner) {
            throw $deny(self::OWNERS_ONLY);
        }

        return $memberRole;
    }

    private function ensureAnotherOwner(Workspace $workspace, User $member, string $field): void
    {
        $others = $workspace->members()->wherePivot('role', WorkspaceRole::Owner->value)->whereKeyNot($member->id)->count();
        if ($others === 0) {
            throw ValidationException::withMessages([$field => self::LAST_OWNER]);
        }
    }
}
