<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'email', 'role', 'project_id', 'project_role', 'invited_by', 'token', 'expires_at'])]
#[Hidden(['token'])]
class WorkspaceInvitation extends Model
{
    protected function casts(): array
    {
        return ['role' => WorkspaceRole::class, 'project_role' => ProjectRole::class, 'expires_at' => 'datetime'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
