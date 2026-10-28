<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orders issues with one project-wide integer rank, spaced by GAP. Callers must hold the
 * project row lock (lockForUpdate) inside a transaction so concurrent moves serialise.
 */
class RankService
{
    public const GAP = 1024;

    /** Rank for a new issue at the bottom of the project. */
    public function bottom(Project $project): int
    {
        return (int) $project->issues()->max('rank') + self::GAP;
    }

    /**
     * Move $issue to $position (0-based) among $siblings, which must not include $issue.
     * Only the issue's rank changes, unless the gap is exhausted and the project is renumbered.
     */
    public function place(Issue $issue, Builder $siblings, int $position): void
    {
        $ordered = (clone $siblings)->orderBy('rank')->pluck('rank')->values();
        $position = max(0, min($position, $ordered->count()));
        $before = $ordered[$position - 1] ?? null;
        $after = $ordered[$position] ?? null;

        $rank = match (true) {
            $before === null && $after === null => $issue->rank,
            $before === null => $after - self::GAP,
            $after === null => $before + self::GAP,
            $after - $before > 1 => intdiv($before + $after, 2),
            default => null,
        };

        if ($rank === null) {
            $this->renumber($issue->project_id);
            $this->place($issue->refresh(), $siblings, $position);

            return;
        }

        $issue->forceFill(['rank' => $rank])->save();
    }

    /** Respread every rank in the project, keeping the current order. */
    private function renumber(int $projectId): void
    {
        Issue::where('project_id', $projectId)->orderBy('rank')->orderBy('id')->pluck('id')
            ->each(fn ($id, $i) => Issue::whereKey($id)->update(['rank' => ($i + 1) * self::GAP]));
    }
}
