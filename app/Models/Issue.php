<?php

namespace App\Models;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id', 'number', 'title', 'description', 'type', 'priority', 'column_id',
    'assignee_id', 'reporter_id', 'story_points', 'due_date', 'epic_id', 'parent_id',
])]
class Issue extends Model
{
    protected function casts(): array
    {
        return [
            'type' => IssueType::class,
            'priority' => IssuePriority::class,
            'due_date' => 'date:Y-m-d',
            'story_points' => 'integer',
        ];
    }

    /** "WEB-12". Needs the project relation loaded. */
    protected function key(): Attribute
    {
        return Attribute::get(fn () => $this->project->key.'-'.$this->number);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'epic_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Issue::class, 'parent_id');
    }

    public function epicIssues(): HasMany
    {
        return $this->hasMany(Issue::class, 'epic_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
