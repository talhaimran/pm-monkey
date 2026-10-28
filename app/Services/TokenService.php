<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/** API tokens are Sanctum tokens whose abilities encode access (read/write) and project scope (project:{id}). */
class TokenService
{
    /**
     * @param  list<string>|null  $projectKeys  null or empty = all of the user's projects
     * @param  int|null  $expiresInDays  null = never expires
     */
    public function create(User $user, string $name, bool $write, ?array $projectKeys, ?int $expiresInDays = null): NewAccessToken
    {
        $abilities = $write ? ['read', 'write'] : ['read'];

        if ($projectKeys) {
            $keys = collect($projectKeys)->map(fn ($k) => strtoupper(trim($k)))->unique();
            $projects = $user->projects()->whereIn('key', $keys)->pluck('projects.id', 'key');
            $unknown = $keys->diff($projects->keys());

            if ($unknown->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'projects' => 'You are not a member of: '.$unknown->implode(', ').'. Your projects: '.$user->projects()->orderBy('key')->pluck('key')->implode(', ').'.',
                ]);
            }

            $abilities = [...$abilities, ...$projects->values()->map(fn ($id) => "project:{$id}")];
        }

        return $user->createToken(trim($name), $abilities, $expiresInDays ? now()->addDays($expiresInDays) : null);
    }

    public function revoke(User $user, int $id): void
    {
        $user->tokens()->whereKey($id)->firstOrFail()->delete();
    }

    /** Tokens for display: access level, project keys (null = all), last use. */
    public function list(User $user): array
    {
        $keys = $user->projects()->pluck('key', 'projects.id');

        return $user->tokens()->latest()->get()->map(function (PersonalAccessToken $token) use ($keys) {
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
}
