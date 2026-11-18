<?php

namespace App\Services;

use App\Enums\Entitlement;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Mail\ProjectInvitationMail;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Workspace invitations (spec 7.0a): 14 days, emailed token stored hashed, one per workspace and email. */
class InvitationService
{
    public function __construct(private AuditLogger $audit, private Entitlements $entitlements, private WorkspaceUsage $usage) {}

    public const DAYS = 14;

    public const GONE = 'This invitation is no longer valid. Ask an admin to invite you again.';

    public const EXPIRED = 'This invitation has expired. Ask an admin to invite you again.';

    public const OTHER_EMAIL = 'This invitation was sent to a different email address.';

    /** A project invitation is a Member invitation to the project's workspace. */
    public function invite(Workspace $workspace, User $inviter, string $email, WorkspaceRole $role, ?Project $project = null, ?ProjectRole $projectRole = null): WorkspaceInvitation
    {
        $email = strtolower(trim($email));

        if ($role === WorkspaceRole::Owner) {
            throw ValidationException::withMessages(['role' => 'Invite as Admin or Member. Make someone an Owner from Members once they have joined.']);
        }
        if ($project?->members()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => "{$email} is already a member of {$project->key}. Change their role instead.",
            ]);
        }

        // One pending invitation per address: only whoever could have sent it may change it.
        $pending = WorkspaceInvitation::with('workspace', 'project')->where(['workspace_id' => $workspace->id, 'email' => $email])->first();
        if ($pending && ! $this->mayChange($pending, $inviter)) {
            throw ValidationException::withMessages([
                'email' => "{$email} already has a pending invitation. Ask the admin who sent it to revoke it first.",
            ]);
        }

        // Spec 7.0c: pending invitations count, so room is needed now, for the person this would create.
        $projectRole = $project ? ($projectRole ?? ProjectRole::Member) : null;
        $billed = $this->wouldBeBilled($workspace, $email, $role, $projectRole);
        $token = Str::random(40);
        $invitation = $this->entitlements->locked($workspace, function () use ($workspace, $email, $billed, $role, $project, $projectRole, $inviter, $token) {
            $this->entitlements->ensurePerson($workspace, $this->usage->isBilled($workspace, $email), $billed);

            return WorkspaceInvitation::updateOrCreate(
                ['workspace_id' => $workspace->id, 'email' => $email],
                [
                    'role' => $role, 'project_id' => $project?->id, 'project_role' => $projectRole,
                    'invited_by' => $inviter->id, 'token' => hash('sha256', $token), 'expires_at' => now()->addDays(self::DAYS),
                ],
            );
        });
        $this->send($invitation, $token);
        $this->audit->log('member.invited', $invitation, array_filter([
            'role' => $role->value, 'project' => $project?->key, 'project_role' => $invitation->project_role?->value,
        ]), $workspace, $inviter);

        return $invitation;
    }

    /** A new token and a fresh 14 days; the old link stops working. */
    public function resend(WorkspaceInvitation $invitation): void
    {
        $token = Str::random(40);
        $workspace = $invitation->workspace()->first();
        // An expired invitation stopped counting, so bringing it back needs room again.
        $this->entitlements->locked($workspace, function () use ($workspace, $invitation, $token) {
            $billed = $this->wouldBeBilled($workspace, $invitation->email, $invitation->role, $invitation->project_role);
            $this->entitlements->ensurePerson($workspace, $this->usage->isBilled($workspace, $invitation->email), $billed);
            $invitation->forceFill(['token' => hash('sha256', $token), 'expires_at' => now()->addDays(self::DAYS)])->save();
        });
        $this->send($invitation, $token);
    }

    /**
     * Refuses an accept when the workspace is now over the limit. Sign-up calls it before opening its own
     * transaction, so the plan_limit.reached row isn't rolled back with the new user.
     */
    public function ensureRoom(WorkspaceInvitation $invitation): void
    {
        $this->entitlements->locked($invitation->workspace, fn () => $this->checkRoom($invitation));
    }

    /**
     * Workspace Owners and Admins may change any pending invitation; a project admin only their project's, never a
     * workspace Admin invitation. A project-scoped token reaches only invitations to its projects.
     */
    public function mayChange(WorkspaceInvitation $pending, User $actor): bool
    {
        $scope = $actor->tokenProjectIds();
        if ($scope === null && in_array($actor->workspaceRole($pending->workspace), [WorkspaceRole::Owner, WorkspaceRole::Admin], true)) {
            return true;
        }

        return $pending->project && $pending->role !== WorkspaceRole::Admin
            && ($scope === null || in_array($pending->project_id, $scope, true))
            && Gate::forUser($actor)->allows('manage', $pending->project);
    }

    public function revoke(WorkspaceInvitation $invitation, User $actor): void
    {
        if (! $this->mayChange($invitation, $actor)) {
            throw new AuthorizationException("Only an Owner or Admin of {$invitation->workspace->name} can revoke this invitation.");
        }
        $invitation->delete();
        $this->audit->log('member.invitation_revoked', $invitation, [], $invitation->workspace()->first());
    }

    public function findByToken(string $token): ?WorkspaceInvitation
    {
        return WorkspaceInvitation::with('workspace', 'project', 'inviter')->firstWhere('token', hash('sha256', $token));
    }

    /** Why $user can't accept $invitation, or null if they can. */
    public function refusal(?WorkspaceInvitation $invitation, ?User $user): ?string
    {
        return match (true) {
            // A workspace scheduled for deletion (D2) takes no new members.
            ! $invitation, (bool) $invitation->workspace->deletion_scheduled_at => self::GONE,
            $invitation->expires_at->isPast() => self::EXPIRED,
            $user && strtolower($user->email) !== $invitation->email => self::OTHER_EMAIL,
            default => null,
        };
    }

    /**
     * Joins the workspace (keeping any role already held there) and the project, and switches to the workspace.
     * Locks the invitation so a revoke or a second accept at the same moment can't both win.
     */
    public function accept(WorkspaceInvitation $invitation, User $user): Workspace
    {
        return $this->entitlements->locked($invitation->workspace, function () use ($invitation, $user) {
            $locked = WorkspaceInvitation::with('workspace', 'project')->lockForUpdate()->find($invitation->id);
            if ($reason = $this->refusal($locked, $user)) {
                throw ValidationException::withMessages(['invitation' => $reason]);
            }
            $this->checkRoom($locked);

            $role = $user->workspaceRole($locked->workspace);
            if (! $role) {
                $locked->workspace->addMember($user, $role = $locked->role);
            }
            $locked->project?->members()->syncWithoutDetaching([$user->id => ['role' => $locked->project_role->value]]);
            $this->audit->log('member.joined', $user, array_filter(['role' => $role->value, 'project' => $locked->project?->key]), $locked->workspace, $user);
            $user->forceFill(['current_workspace_id' => $locked->workspace_id])->save();
            $locked->delete();

            return $locked->workspace;
        });
    }

    /** Review focus 3: the invitation already counts, so this refuses only when the workspace is now over the limit. */
    private function checkRoom(WorkspaceInvitation $invitation): void
    {
        $billed = $this->usage->isBilled($invitation->workspace, $invitation->email);
        $this->entitlements->ensure($invitation->workspace, $billed ? Entitlement::BilledUsers : Entitlement::Viewers, 0);
    }

    /** Accepting keeps a member's workspace role, so for a member only the project role can make them billed. */
    private function wouldBeBilled(Workspace $workspace, string $email, WorkspaceRole $role, ?ProjectRole $projectRole): bool
    {
        $asMember = $this->usage->memberBilled($workspace, $email);

        return $asMember === true || in_array($projectRole, [ProjectRole::Member, ProjectRole::Admin], true) || ($asMember === null && $role === WorkspaceRole::Admin);
    }

    private function send(WorkspaceInvitation $invitation, string $token): void
    {
        $invitation->loadMissing('workspace', 'project', 'inviter');
        Mail::to($invitation->email)->send(new ProjectInvitationMail($invitation, $token));
    }
}
