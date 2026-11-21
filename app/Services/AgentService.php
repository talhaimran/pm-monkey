<?php

namespace App\Services;

use App\Enums\AgentLevel;
use App\Enums\AgentStatus;
use App\Enums\Entitlement;
use App\Enums\WorkspaceRole;
use App\Models\Agent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Spec 8 (A1): agents and their one token each. The token stays a Sanctum token of the owner with agent_id
 * (A-D1), shown once and stored hashed. Every change writes an agent.* audit event, never with the token in it.
 */
class AgentService
{
    public function __construct(private CurrentWorkspace $current, private AuditLogger $audit, private Entitlements $entitlements) {}

    /** Input rules for create; update takes the same fields, each optional, except the expiry. */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'level' => ['required', Rule::enum(AgentLevel::class)],
            'projects' => ['nullable', 'array'],
            'projects.*' => ['string', 'max:10'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * An agent of $owner in the current workspace. projects are keys (empty = all of the owner's).
     *
     * @return array{0: Agent, 1: string} the agent and its plain token, shown once
     */
    public function create(User $owner, array $data): array
    {
        $data = Validator::make($data, self::rules())->validate();
        $workspace = $this->current->id() ? $this->current->get() : throw ValidationException::withMessages([
            'name' => 'You are not a member of any workspace yet, so there is nothing an agent could reach.',
        ]);
        if ($workspace->setting('require_2fa') && ! $owner->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages(['name' => "{$workspace->name} requires two-factor authentication. Turn it on in your profile before creating an agent."]);
        }
        $name = trim($data['name']);
        $projectIds = $this->projectIds($owner, $data['projects'] ?? []);

        return $this->entitlements->locked($workspace, function () use ($owner, $workspace, $data, $name, $projectIds) {
            $mine = $this->agentsOf($workspace->id, $owner->id);
            $this->ensureUniqueName($mine, $name);
            $this->entitlements->ensureCount($workspace, Entitlement::AgentsPerUser, $mine->count());

            $agent = Agent::create([
                'workspace_id' => $workspace->id,
                'owner_user_id' => $owner->id,
                'name' => $name,
                'description' => $data['description'] ?? null,
                'permission_level' => $data['level'],
                'status' => AgentStatus::Active,
            ]);
            $agent->projects()->attach($projectIds);
            $plain = $this->issueToken($agent, $owner, isset($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null);
            $this->log('agent.created', $agent, $owner, ['level' => $agent->permission_level->value, 'projects' => $this->projectKeys($agent) ?: null]);

            return [$agent, $plain];
        });
    }

    /** Name, description, level and projects; only the fields given change. */
    public function update(Agent $agent, User $actor, array $data): Agent
    {
        $rules = collect(self::rules())->except('expires_in_days')->map(fn ($rule) => ['sometimes', ...$rule])->all();
        $data = Validator::make($data, $rules)->validate();
        $owner = $agent->owner()->firstOrFail();
        $projectIds = array_key_exists('projects', $data) ? $this->projectIds($owner, $data['projects'] ?? []) : null;

        return $this->entitlements->locked($agent->workspace()->firstOrFail(), function () use ($agent, $actor, $data, $projectIds) {
            $this->lockFresh($agent);
            $this->ensureNotRevoked($agent);
            $before = ['name' => $agent->name, 'level' => $agent->permission_level->value, 'projects' => $this->projectKeys($agent)];
            if (isset($data['name'])) {
                $this->ensureUniqueName($this->agentsOf($agent->workspace_id, $agent->owner_user_id)->whereKeyNot($agent->id), trim($data['name']));
                $agent->name = trim($data['name']);
            }
            if (array_key_exists('description', $data)) {
                $agent->description = $data['description'];
            }
            if (isset($data['level'])) {
                $agent->permission_level = $data['level'];
            }
            $dirty = $agent->isDirty();
            $agent->save();
            // The token carries the name into activity rows (token_name).
            $agent->token()->update(['name' => $agent->name]);
            if ($projectIds !== null) {
                $agent->projects()->sync($projectIds);
            }

            $after = ['name' => $agent->name, 'level' => $agent->permission_level->value, 'projects' => $this->projectKeys($agent)];
            $diff = collect($before)->filter(fn ($value, $field) => $value !== $after[$field])
                ->map(fn ($value, $field) => ['before' => $value, 'after' => $after[$field]])->all();
            if ($dirty || $diff) {
                $this->log('agent.updated', $agent, $actor, $diff);
            }

            return $agent;
        });
    }

    public function pause(Agent $agent, User $actor): void
    {
        $this->setStatus($agent, $actor, AgentStatus::Paused, 'agent.paused');
    }

    public function resume(Agent $agent, User $actor): void
    {
        $this->setStatus($agent, $actor, AgentStatus::Active, 'agent.resumed');
    }

    /** For good: the token is deleted, the agent row stays (history). A null actor is the system. */
    public function revoke(Agent $agent, ?User $actor, string $reason = 'manual'): void
    {
        DB::transaction(function () use ($agent, $actor, $reason) {
            $this->lockFresh($agent);
            $agent->token()->delete();
            if ($agent->status !== AgentStatus::Revoked) {
                $agent->update(['status' => AgentStatus::Revoked]);
                $this->log('agent.revoked', $agent, $actor, ['by' => $this->by($agent, $actor), 'reason' => $reason]);
            }
        });
    }

    /** A new token for the same agent, with the old one's lifetime; the old one stops working at once. */
    public function regenerateToken(Agent $agent, User $actor): string
    {
        return DB::transaction(function () use ($agent, $actor) {
            $this->lockFresh($agent);
            $this->ensureNotRevoked($agent);
            $old = $agent->token()->first();
            $agent->token()->delete();
            $plain = $this->issueToken($agent, $agent->owner()->firstOrFail(), $old?->expires_at ? now()->add($old->created_at->diff($old->expires_at)) : null);
            $this->log('agent.token_regenerated', $agent, $actor);

            return $plain;
        });
    }

    /** Spec 8: an owner leaving the workspace takes their agents with them (history kept). */
    public function revokeAllOf(User $owner, Workspace $workspace): void
    {
        $this->agentsOf($workspace->id, $owner->id)->get()->each(fn (Agent $agent) => $this->revoke($agent, null, 'owner_left'));
    }

    /** Revoked agents are left out. Owners and Admins see every agent in the workspace, `mine` flags their own. */
    public function listFor(User $viewer, Workspace $workspace): array
    {
        $seesAll = in_array($viewer->workspaceRole($workspace), [WorkspaceRole::Owner, WorkspaceRole::Admin], true);

        return Agent::with(['owner:id,name,email', 'projects:projects.id,key', 'token'])
            ->where('workspace_id', $workspace->id)
            ->where('status', '!=', AgentStatus::Revoked)
            ->unless($seesAll, fn ($q) => $q->where('owner_user_id', $viewer->id))
            ->orderBy('name')->orderBy('id')->get()
            ->map(fn (Agent $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'description' => $agent->description,
                'level' => $agent->permission_level->value,
                'status' => $agent->status->value,
                'projects' => $agent->projects->isEmpty() ? null : $agent->projects->pluck('key')->sort()->values()->all(),
                'owner' => ['id' => $agent->owner->id, 'name' => $agent->owner->name, 'email' => $agent->owner->email],
                'mine' => $agent->owner_user_id === $viewer->id,
                'last_used_at' => $agent->last_used_at,
                'expires_at' => $agent->token?->expires_at,
                'created_at' => $agent->created_at,
            ])->all();
    }

    /**
     * Refuses a paused or revoked agent (REST, MCP over HTTP, and every stdio tool call). Re-reads the agent, so a
     * long-lived process sees a pause, revoke or level change at its next call. Bumps last_used_at at most once a minute.
     */
    public function ensureUsable(?Agent $agent): void
    {
        if (! $agent) {
            return;
        }
        try {
            $agent->refresh();
        } catch (ModelNotFoundException) {
            $agent->status = AgentStatus::Revoked;
        }

        $deny = fn (int $status, string $error, string $message) => throw new HttpResponseException(response()->json(['error' => $error, 'message' => $message], $status));
        $workspace = $agent->workspace()->first();
        if ($workspace && ! $workspace->setting('agents_enabled')) {
            $deny(403, 'agents_disabled', "Agents are turned off in {$workspace->name}. An Owner or Admin can turn them on in Settings → Security.");
        }
        match ($agent->status) {
            AgentStatus::Paused => $deny(403, 'agent_paused', "{$agent->label()} is paused. Its owner or a workspace Admin can resume it in Settings → Agents."),
            AgentStatus::Revoked => $deny(401, 'agent_revoked', "{$agent->label()} was revoked. Its owner can create a new agent in Settings → Agents."),
            AgentStatus::Active => null,
        };

        if (! $agent->last_used_at?->gt(now()->subMinute())) {
            // A query, not save(): last use is not an edit, so updated_at stays.
            $agent->last_used_at = now();
            Agent::whereKey($agent->id)->update(['last_used_at' => $agent->last_used_at]);
            $agent->syncOriginal();
        }
    }

    private function setStatus(Agent $agent, User $actor, AgentStatus $status, string $action): void
    {
        DB::transaction(function () use ($agent, $actor, $status, $action) {
            $this->lockFresh($agent);
            $this->ensureNotRevoked($agent);
            if ($agent->status !== $status) {
                $agent->update(['status' => $status]);
                $this->log($action, $agent, $actor, ['by' => $this->by($agent, $actor)]);
            }
        });
    }

    /** Locks the agent row and re-reads it into $agent, so a caller's stale copy can't undo a concurrent revoke. */
    private function lockFresh(Agent $agent): void
    {
        $agent->setRawAttributes(Agent::whereKey($agent->id)->lockForUpdate()->firstOrFail()->getAttributes(), true);
    }

    private function issueToken(Agent $agent, User $owner, ?Carbon $expiresAt): string
    {
        // Authority is the agent row (level, projects, status), not the token's abilities.
        $token = $owner->createToken($agent->name, ['*'], $expiresAt);
        $token->accessToken->forceFill(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id])->save();

        return $token->plainTextToken;
    }

    /** @return list<int> ids of the given keys, which must all be projects $owner can reach here */
    private function projectIds(User $owner, array $keys): array
    {
        $keys = collect($keys)->map(fn ($k) => strtoupper(trim($k)))->unique();
        if ($keys->isEmpty()) {
            return [];
        }
        $projects = $owner->accessibleProjects()->whereIn('key', $keys)->pluck('projects.id', 'key');
        $unknown = $keys->diff($projects->keys());
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'projects' => 'You are not a member of: '.$unknown->implode(', ').'. Your projects: '.$owner->accessibleProjects()->orderBy('key')->pluck('key')->implode(', ').'.',
            ]);
        }

        return $projects->values()->all();
    }

    /** @return list<string> */
    private function projectKeys(Agent $agent): array
    {
        return $agent->projects()->orderBy('key')->pluck('key')->all();
    }

    /** An owner's agents in a workspace that still count (active or paused). */
    private function agentsOf(int $workspaceId, int $ownerId): Builder
    {
        return Agent::where('workspace_id', $workspaceId)->where('owner_user_id', $ownerId)->where('status', '!=', AgentStatus::Revoked);
    }

    private function ensureUniqueName(Builder $agents, string $name): void
    {
        if ((clone $agents)->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => "You already have an agent named {$name}. Your agents: ".(clone $agents)->orderBy('name')->pluck('name')->implode(', ').'.',
            ]);
        }
    }

    private function ensureNotRevoked(Agent $agent): void
    {
        if ($agent->status === AgentStatus::Revoked) {
            throw ValidationException::withMessages(['agent' => "{$agent->label()} was revoked. Create a new agent instead."]);
        }
    }

    private function by(Agent $agent, ?User $actor): string
    {
        return match (true) {
            $actor === null => 'system',
            $actor->id === $agent->owner_user_id => 'owner',
            default => 'admin',
        };
    }

    private function log(string $action, Agent $agent, ?User $actor, array $metadata = []): void
    {
        $workspace = $agent->workspace()->first();
        $actor
            ? $this->audit->log($action, $agent, $metadata, $workspace, $actor)
            : $this->audit->system($action, $agent, $workspace, $metadata);
    }
}
