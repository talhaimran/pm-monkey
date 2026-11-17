<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * API tokens are Sanctum tokens whose abilities encode access (read/write) and project scope (project:{id}).
 * Each token belongs to the workspace it was made in; listing and revoking only see that workspace's tokens.
 */
class TokenService
{
    public function __construct(private CurrentWorkspace $current, private AuditLogger $audit) {}

    /**
     * @param  list<string>|null  $projectKeys  null or empty = all of the user's projects
     * @param  int|null  $expiresInDays  null = never expires
     */
    public function create(User $user, string $name, bool $write, ?array $projectKeys, ?int $expiresInDays = null): NewAccessToken
    {
        $workspaceId = $this->current->id() ?? throw ValidationException::withMessages([
            'name' => 'You are not a member of any workspace yet, so there is nothing a token could reach.',
        ]);
        $abilities = $write ? ['read', 'write'] : ['read'];

        if ($projectKeys) {
            $keys = collect($projectKeys)->map(fn ($k) => strtoupper(trim($k)))->unique();
            $projects = $user->accessibleProjects()->whereIn('key', $keys)->pluck('projects.id', 'key');
            $unknown = $keys->diff($projects->keys());

            if ($unknown->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'projects' => 'You are not a member of: '.$unknown->implode(', ').'. Your projects: '.$user->accessibleProjects()->orderBy('key')->pluck('key')->implode(', ').'.',
                ]);
            }

            $abilities = [...$abilities, ...$projects->values()->map(fn ($id) => "project:{$id}")];
        }

        $token = $user->createToken(trim($name), $abilities, $expiresInDays ? now()->addDays($expiresInDays) : null);
        $token->accessToken->forceFill(['workspace_id' => $workspaceId])->save();
        $this->audit->log('token.created', $token->accessToken, ['write' => $write, 'projects' => $projectKeys ? $keys->values()->all() : null], actor: $user);

        return $token;
    }

    public function revoke(User $user, int $id): void
    {
        $token = $this->tokensHere($user)->whereKey($id)->firstOrFail();
        $token->delete();
        $this->audit->log('token.revoked', $token, actor: $user);
    }

    /** Tokens for display: access level, project keys (null = all), last use. */
    public function list(User $user): array
    {
        $keys = $user->accessibleProjects()->pluck('key', 'projects.id');

        return $this->tokensHere($user)->latest()->get()->map(function (PersonalAccessToken $token) use ($keys) {
            $projectIds = collect($token->abilities)->filter(fn ($a) => str_starts_with($a, 'project:'))->map(fn ($a) => (int) substr($a, 8));

            return [
                'id' => $token->id,
                'name' => $token->name,
                'access' => in_array('write', $token->abilities, true) ? 'write' : 'read',
                // A scoped token keeps only projects that still exist and that the user still belongs to.
                'projects' => $projectIds->isEmpty() ? null : $projectIds->map(fn ($id) => $keys[$id] ?? null)->filter()->values()->all(),
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
            ];
        })->all();
    }

    private function tokensHere(User $user): MorphMany
    {
        return $user->tokens()->where('workspace_id', $this->current->id() ?? 0);
    }
}
