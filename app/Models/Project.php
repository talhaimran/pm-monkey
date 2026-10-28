<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'description'])]
class Project extends Model
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function columns(): HasMany
    {
        return $this->hasMany(Column::class)->orderBy('position');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class)->orderBy('name');
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /** Issues in the last column count as done (sprint progress, backlog, dashboard). */
    public function doneColumnId(): ?int
    {
        return $this->columns()->reorder('position', 'desc')->value('id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    public function roleFor(User $user): ?ProjectRole
    {
        // Reuse loaded members to avoid a query per check on pages that already have them.
        $member = $this->relationLoaded('members')
            ? $this->members->firstWhere('id', $user->id)
            : $this->members()->whereKey($user->id)->first();

        return $member ? ProjectRole::from($member->pivot->role) : null;
    }
}
