<?php

namespace App\Http\Resources;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Wraps BoardService::board(): columns with compact issue cards. */
class BoardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'columns' => $this->resource['columns']->map(fn ($column) => [
                'name' => $column->name,
                'position' => $column->position,
                'issues' => $column->issues->map(fn (Issue $i) => self::card($i))->values()->all(),
            ])->values()->all(),
            'truncated' => $this->resource['truncated'],
        ];
    }

    public static function card(Issue $issue): array
    {
        return [
            'key' => $issue->key,
            'title' => $issue->title,
            'type' => $issue->type->value,
            'priority' => $issue->priority->value,
            'assignee' => $issue->assignee ? ['name' => $issue->assignee->name, 'email' => $issue->assignee->email] : null,
            'labels' => $issue->labels->pluck('name')->all(),
            'story_points' => $issue->story_points,
            'due_date' => $issue->due_date?->format('Y-m-d'),
            'parent' => $issue->parent ? $issue->project->key.'-'.$issue->parent->number : null,
        ];
    }
}
