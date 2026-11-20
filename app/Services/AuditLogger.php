<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Spec 7.0d: admin and security events, append-only. Issue changes stay in ActivityLogger. Callers pass labels and
 * metadata, never a password, token, secret or recovery code (AuditLogTest sweeps for them).
 */
class AuditLogger
{
    public function __construct(private CurrentWorkspace $current) {}

    /** Workspace defaults to the current one; actor to the signed-in user unless $actorType is "system". */
    public function log(string $action, ?Model $target = null, array $metadata = [], ?Workspace $workspace = null, ?User $actor = null, string $actorType = 'user'): void
    {
        $actor ??= $actorType === 'user' ? auth()->user() : null;
        $workspace ??= $this->current->id() ? $this->current->get() : null;

        $this->write($workspace?->id, $action, $target, $metadata, $actor, $actorType);
    }

    /** Scheduled jobs: no actor and an explicit workspace (null for rows that outlive it, like workspace.deleted). */
    public function system(string $action, ?Model $target, ?Workspace $workspace, array $metadata = []): void
    {
        $this->write($workspace?->id, $action, $target, $metadata, null, 'system');
    }

    /** Sign-in events go to every workspace the user belongs to; with no user or no workspace, one unscoped row. */
    public function forEveryWorkspaceOf(?User $user, string $action, array $metadata = []): void
    {
        $ids = $user ? $user->workspaces()->pluck('workspaces.id')->all() : [];
        foreach ($ids ?: [null] as $id) {
            $this->write($id, $action, $user, $metadata, $user, 'user');
        }
    }

    private function write(?int $workspaceId, string $action, ?Model $target, array $metadata, ?User $actor, string $actorType): void
    {
        $request = app()->bound('request') ? request() : null;

        AuditLog::create([
            'workspace_id' => $workspaceId,
            'actor_user_id' => $actor?->id,
            'actor_type' => $actorType,
            'agent_id' => $actor?->agent()?->id,
            'via' => $actorType === 'system' ? 'system' : ActivityLogger::via($actor),
            'action' => $action,
            'target_type' => $target ? $this->type($target) : null,
            'target_id' => $target?->getKey(),
            'target_label' => $target ? $this->label($target) : null,
            'metadata' => $metadata ?: null,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 500, '') : null,
        ]);
    }

    private function type(Model $target): string
    {
        return match (true) {
            $target instanceof PersonalAccessToken => 'token',
            $target instanceof WorkspaceInvitation => 'invitation',
            default => Str::snake(class_basename($target)),
        };
    }

    /** Kept on the row, so the log still reads after the target is deleted. */
    private function label(Model $target): ?string
    {
        return match (true) {
            $target instanceof Workspace => $target->name,
            $target instanceof Project, $target instanceof Issue => $target->key,
            $target instanceof User, $target instanceof WorkspaceInvitation => $target->email,
            $target instanceof PersonalAccessToken => $target->name,
            $target instanceof Agent => $target->label(),
            default => null,
        };
    }
}
