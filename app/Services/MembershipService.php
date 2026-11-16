<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Mail\ProjectInvitationMail;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    /** A project invitation is a Member invitation to the project's workspace (spec 7.0a). */
    public function invite(Project $project, User $inviter, string $email, ProjectRole $role): WorkspaceInvitation
    {
        $email = strtolower(trim($email));

        if ($project->members()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => "{$email} is already a member of {$project->key}. Change their role instead.",
            ]);
        }

        // A pending invitation belongs to its project's admins; don't let another project's admin retarget it.
        $pending = WorkspaceInvitation::with('project')->where(['workspace_id' => $project->workspace_id, 'email' => $email])->first();
        if ($pending?->project && $pending->project_id !== $project->id && Gate::forUser($inviter)->denies('manage', $pending->project)) {
            throw ValidationException::withMessages([
                'email' => "{$email} already has a pending invitation to another project. Ask that project's admin to revoke it first.",
            ]);
        }

        // shortcut: one invitation per workspace and email, so re-inviting to another project replaces it; PR 2 adds InvitationService.
        $invitation = WorkspaceInvitation::updateOrCreate(
            ['workspace_id' => $project->workspace_id, 'email' => $email],
            [
                'role' => WorkspaceRole::Member, 'project_id' => $project->id, 'project_role' => $role, 'invited_by' => $inviter->id,
                'token' => hash('sha256', Str::random(40)), 'expires_at' => now()->addDays(14),
            ],
        );

        Mail::to($email)->send(new ProjectInvitationMail($invitation->setRelation('project', $project)->setRelation('inviter', $inviter)));

        return $invitation;
    }

    /** Joins the workspace (keeping any role already held there) and the project, and switches to that workspace. */
    public function accept(WorkspaceInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user) {
            $invitation->loadMissing('workspace', 'project');
            if (! $user->workspaceRole($invitation->workspace)) {
                $invitation->workspace->addMember($user, $invitation->role);
            }
            $invitation->project?->members()->syncWithoutDetaching([$user->id => ['role' => $invitation->project_role->value]]);
            $user->forceFill(['current_workspace_id' => $invitation->workspace_id])->save();
            $invitation->delete();
        });
    }

    public function revoke(WorkspaceInvitation $invitation): void
    {
        $invitation->delete();
    }

    public function changeRole(Project $project, User $member, ProjectRole $role): void
    {
        DB::transaction(function () use ($project, $member, $role) {
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

    /** Keys of projects where $user is the only admin (they'd be left unmanageable without them). */
    public function soleAdminProjectKeys(User $user): array
    {
        return $user->projects()
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
