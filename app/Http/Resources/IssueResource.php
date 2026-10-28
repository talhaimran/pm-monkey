<?php

namespace App\Http\Resources;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Issue */
class IssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $projectKey = $this->project->key;
        $link = fn (?Issue $i) => $i ? ['key' => "{$projectKey}-{$i->number}", 'title' => $i->title] : null;
        $child = fn (Issue $i) => [...$link($i), 'type' => $i->type->value, 'status' => $i->column->name];

        // Nested collections are resolved to plain arrays: Inertia would wrap nested resources in {data: …}.
        return [
            'id' => $this->id,
            'key' => "{$projectKey}-{$this->number}",
            'project' => $projectKey,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'priority' => $this->priority->value,
            'status' => $this->column->name,
            'assignee' => self::user($this->assignee),
            'reporter' => self::user($this->reporter),
            'labels' => $this->labels->pluck('name')->all(),
            'story_points' => $this->story_points,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'epic' => $link($this->epic),
            'parent' => $link($this->parent),
            'sprint' => $this->sprint ? ['id' => $this->sprint->id, 'name' => $this->sprint->name, 'state' => $this->sprint->state->value] : null,
            'subtasks' => $this->whenLoaded('subtasks', fn () => $this->subtasks->map($child)->all()),
            'epic_issues' => $this->whenLoaded('epicIssues', fn () => $this->epicIssues->map($child)->all()),
            'comments' => $this->whenLoaded('comments', fn () => CommentResource::collection($this->comments)->resolve()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array{id: int, name: string, email: string}|null */
    public static function user(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null;
    }
}
