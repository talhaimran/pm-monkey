<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    public function __construct(private Entitlements $entitlements, private WorkspaceUsage $usage) {}

    public function changeRole(Project $project, User $member, ProjectRole $role): void
    {
        // Spec 7.0c: a Viewer becoming Member or Admin needs a billed seat.
        $workspace = $project->workspace()->first();
        $this->entitlements->locked($workspace, function () use ($workspace, $project, $member, $role) {
            $now = $this->usage->isBilled($workspace, $member->email);
            $this->entitlements->ensurePerson($workspace, $now, $now === true || $role !== ProjectRole::Viewer);
            if ($role !== ProjectRole::Admin) {
                $this->ensureNotLastAdmin($project, $member, 'role');
            }
            $project->members()->updateExistingPivot($member->id, ['role' => $role->value]);
        });
    }

    public function remove(Project $project, User $member): void
    {
        DB::transaction(function () use ($project, $member) {
            $this->ensureNotLastAdmin($project, $member, 'member');
            $project->members()->detach($member->id);
        });
    }

    /** Keys of projects where $user is the only admin (they'd be left unmanageable without them), optionally in one workspace. */
    public function soleAdminProjectKeys(User $user, ?Workspace $workspace = null): array
    {
        return $user->projects()
            ->when($workspace, fn ($q) => $q->where('projects.workspace_id', $workspace->id))
            ->wherePivot('role', ProjectRole::Admin->value)
            ->withCount(['members as admin_count' => fn ($q) => $q->where('project_user.role', ProjectRole::Admin->value)])
            ->orderBy('key')
            ->get()
            ->where('admin_count', 1)
            ->pluck('key')
            ->all();
    }

    /** Find a project member by email; 404 lists the members so callers can correct the input. */
    public function resolveMember(Project $project, string $email): User
    {
        $member = $project->members()->where('email', strtolower($email))->first();

        if ($member) {
            return $member;
        }

        $emails = $project->members()->orderBy('email')->limit(50)->pluck('email')->implode(', ');

        throw ValidationException::withMessages([
            'member' => "No member with email \"{$email}\" in {$project->key}. Members: {$emails}.",
        ])->status(404);
    }

    private function ensureNotLastAdmin(Project $project, User $member, string $field): void
    {
        // Lock admin rows so two concurrent demotions can't both pass the check.
        $adminIds = $project->members()
            ->wherePivot('role', ProjectRole::Admin->value)
            ->lockForUpdate()
            ->pluck('users.id');

        if ($adminIds->count() === 1 && $adminIds->first() === $member->id) {
            throw ValidationException::withMessages([
                $field => "{$project->key} must keep at least one admin. Make another member an admin first.",
            ]);
        }
    }
}
