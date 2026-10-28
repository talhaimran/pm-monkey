<?php

namespace App\Models;

use App\Enums\SprintState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'name', 'goal', 'start_date', 'end_date', 'state', 'started_at', 'completed_at', 'active_project_id'])]
class Sprint extends Model
{
    protected function casts(): array
    {
        return [
            'state' => SprintState::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}
