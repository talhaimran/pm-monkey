<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Issue;
use Illuminate\Validation\ValidationException;

/**
 * Undo for the web UI (spec 5.1): a new change in the opposite direction through the same services.
 * The toast sends back the request data built here, plus `undo_of`: the id of the activity its change wrote.
 */
class UndoService
{
    // Activity actions that change an issue. Comments don't, so a new comment never blocks an undo.
    private const CHANGES = ['updated', 'undid', 'deleted', 'restored'];

    /** The marker an undo carries: the newest activity on the issue right after the change. */
    public function token(Issue $issue): int
    {
        return (int) $issue->activities()->max('id');
    }

    /**
     * Stop when the issue changed after the change being undone, saying what changed instead of overwriting it.
     * shortcut: a reorder that only changes rank writes no activity, so it isn't detected; edit-conflict versions (step 1) replace this.
     */
    public function guard(Issue $issue, int $activityId): void
    {
        if (! $issue->activities()->whereKey($activityId)->exists()) {
            throw ValidationException::withMessages(['undo' => "Can't undo: that change doesn't belong to {$issue->key}."]);
        }

        $newer = $issue->activities()->with('user')->where('id', '>', $activityId)->whereIn('action', self::CHANGES)->latest('id')->first();

        if ($newer) {
            throw ValidationException::withMessages(['undo' => "Can't undo: {$this->describe($newer, $issue)}."])->status(409);
        }
    }

    /**
     * Request data that puts $fields back to what they are on $issue now, in the shared input shape.
     * Call it before the update.
     *
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function forUpdate(Issue $issue, array $fields): array
    {
        $issue->loadMissing(IssueService::DISPLAY);
        $key = fn (?Issue $linked) => $linked ? "{$issue->project->key}-{$linked->number}" : null;

        $current = [
            'title' => $issue->title,
            'description' => $issue->description,
            'type' => $issue->type->value,
            'priority' => $issue->priority->value,
            'status' => $issue->column->name,
            'assignee' => $issue->assignee?->email,
            'labels' => $issue->labels->pluck('name')->values()->all(),
            'story_points' => $issue->story_points,
            'due_date' => $issue->due_date?->format('Y-m-d'),
            'epic' => $key($issue->epic),
            'parent' => $key($issue->parent),
            'sprint' => $issue->sprint?->name,
        ];

        return array_intersect_key($current, array_flip($fields));
    }

    /**
     * Request data for the move endpoint that returns the issue to its column and place. Call it before the move.
     *
     * @return array<string, mixed>
     */
    public function forMove(Issue $issue): array
    {
        $issue->loadMissing(IssueService::DISPLAY);
        $previous = Issue::where('column_id', $issue->column_id)
            ->where('type', '!=', $issue->type->value === 'epic' ? '' : 'epic')
            ->where('rank', '<', $issue->rank)
            ->orderByDesc('rank')
            ->first();

        return ['status' => $issue->column->name] + ($previous ? ['after' => "{$issue->project->key}-{$previous->number}"] : ['position' => 0]);
    }

    /**
     * Request data for the rank endpoint that returns the issue to its sprint (or the backlog) and place. Call it before the move.
     *
     * @return array<string, mixed>
     */
    public function forRank(Issue $issue): array
    {
        $issue->loadMissing(IssueService::DISPLAY);
        // The same neighbours BacklogService::rank places against: the sprint's issues, or the open backlog.
        $previous = Issue::where('project_id', $issue->project_id)
            ->where('type', '!=', 'epic')
            ->when(
                $issue->sprint_id,
                fn ($q, $id) => $q->where('sprint_id', $id),
                fn ($q) => $q->whereNull('sprint_id')->where('column_id', '!=', $issue->project->doneColumnId()),
            )
            ->where('rank', '<', $issue->rank)
            ->orderByDesc('rank')
            ->first();

        return ['sprint' => $issue->sprint?->name ?? 'backlog'] + ($previous ? ['after' => "{$issue->project->key}-{$previous->number}"] : ['position' => 0]);
    }

    private function describe(Activity $activity, Issue $issue): string
    {
        $who = $activity->user?->name ?? 'Someone';
        $status = $activity->changes['status']['to'] ?? null;

        return match (true) {
            $activity->action === 'deleted' => "{$who} deleted {$issue->key}",
            $activity->action === 'restored' => "{$who} restored {$issue->key}",
            $status !== null => "{$who} moved {$issue->key} to {$status}",
            default => "{$who} changed ".implode(', ', array_keys($activity->changes ?? []) ?: ['it'])." on {$issue->key}",
        };
    }
}
