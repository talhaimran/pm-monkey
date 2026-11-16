<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\WorkspaceRole;
use App\Services\CurrentWorkspace;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withPivot('role')->withTimestamps();
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')->withPivot('role', 'last_active_at')->withTimestamps();
    }

    public function workspaceRole(Workspace $workspace): ?WorkspaceRole
    {
        $role = $this->workspaces()->whereKey($workspace->id)->value('workspace_user.role');

        return $role ? WorkspaceRole::from($role) : null;
    }

    /**
     * Projects this request may touch: the user's projects in the current workspace, narrowed to the token's
     * "project:{id}" abilities when the token has any. No current workspace, no projects.
     * Use this, not projects(), for every lookup.
     */
    public function accessibleProjects(): BelongsToMany
    {
        return $this->projects()
            ->where('projects.workspace_id', app(CurrentWorkspace::class)->id() ?? 0)
            ->when($this->tokenProjectIds(), fn ($q, $ids) => $q->whereIn('projects.id', $ids));
    }

    /** @return list<int>|null project ids the current token is limited to; null when unrestricted */
    public function tokenProjectIds(): ?array
    {
        $token = $this->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return null;
        }

        $ids = collect($token->abilities)
            ->filter(fn ($a) => str_starts_with($a, 'project:'))
            ->map(fn ($a) => (int) substr($a, 8))
            ->values()
            ->all();

        return $ids ?: null;
    }

    /** Session users (no token) may write; token users need the "write" ability. */
    public function canWrite(): bool
    {
        return $this->currentAccessToken() === null || $this->tokenCan('write');
    }
}
