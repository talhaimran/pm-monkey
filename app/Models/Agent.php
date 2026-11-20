<?php

namespace App\Models;

use App\Enums\AgentLevel;
use App\Enums\AgentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Spec 8 (A1): a named identity owned by one person. Its token is a Sanctum token of the owner (tokenable = User,
 * decision A-D1) carrying agent_id, so every policy checks the owner's role first. No projects = all of the owner's.
 */
#[Fillable(['workspace_id', 'owner_user_id', 'name', 'description', 'permission_level', 'status', 'last_used_at'])]
class Agent extends Model
{
    protected function casts(): array
    {
        return [
            'permission_level' => AgentLevel::class,
            'status' => AgentStatus::class,
            'last_used_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'agent_projects');
    }

    public function token(): HasOne
    {
        return $this->hasOne(PersonalAccessToken::class);
    }

    public function label(): string
    {
        return $this->name;
    }
}
