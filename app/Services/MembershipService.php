<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Mail\ProjectInvitationMail;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    public function invite(Project $project, User $inviter, string $email, ProjectRole $role): ProjectInvitation
    {
        $email = strtolower(trim($email));

        if ($project->members()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => "{$email} is already a member of {$project->key}. Change their role instead.",
            ]);
        }

        $invitation = ProjectInvitation::updateOrCreate(
            ['project_id' => $project->id, 'email' => $email],
            ['role' => $role, 'invited_by' => $inviter->id],
        );

        Mail::to($email)->send(new ProjectInvitationMail($invitation->setRelation('project', $project)->setRelation('inviter', $inviter)));

        return $invitation;
    }

    public function accept(ProjectInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user) {
            $invitation->loadMissing('project')->project->members()->syncWithoutDetaching([$user->id => ['role' => $invitation->role->value]]);
            $invitation->delete();
        });
    }

    public function revoke(ProjectInvitation $invitation): void
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
