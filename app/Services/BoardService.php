<?php

namespace App\Services;

use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BoardService
{
    /** shortcut: one capped query for the whole board; paginate per column if projects outgrow it. */
    public const LIMIT = 500;

    public function __construct(private IssueService $issues, private RankService $ranks) {}

    /**
     * Columns in order, each with its non-epic issues sorted by rank.
     *
     * @return array{columns: Collection, truncated: bool}
     */
    public function board(Project $project, array $filters): array
    {
        $issues = $this->issues
            ->filter($project->issues()->where('type', '!=', IssueType::Epic->value)->getQuery(), $filters)
            ->with(['assignee', 'labels', 'parent', 'column'])
            ->orderBy('rank')
            ->limit(self::LIMIT + 1)
            ->get()
            ->each->setRelation('project', $project);

        $byColumn = $issues->take(self::LIMIT)->groupBy('column_id');

        return [
            'columns' => $project->columns()->get()->map(fn ($column) => $column->setRelation('issues', $byColumn->get($column->id, collect()))),
            'truncated' => $issues->count() > self::LIMIT,
        ];
    }

    /**
     * Move an issue to a column (status) and/or a place in it. $input: status?, position?, after?
     * Status changes are logged through IssueService::update; reordering alone isn't logged.
     */
    public function move(Issue $issue, User $actor, array $input): Issue
    {
        return DB::transaction(function () use ($issue, $actor, $input) {
            Project::whereKey($issue->project_id)->lockForUpdate()->first();

            if (isset($input['status'])) {
                $issue = $this->issues->update($issue, $actor, ['status' => $input['status']]);
            }
            $issue->loadMissing(IssueService::DISPLAY);

            $siblings = Issue::where('column_id', $issue->column_id)
                ->whereKeyNot($issue->id)
                ->where('type', '!=', IssueType::Epic->value);

            $position = match (true) {
                isset($input['after']) => $this->ranks->positionAfter($issue, $siblings, $input['after'], $issue->column->name),
                isset($input['position']) => (int) $input['position'],
                default => PHP_INT_MAX,
            };

            $this->ranks->place($issue, $siblings, $position);

            return $issue;
        });
    }
}
