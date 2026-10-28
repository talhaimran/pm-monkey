<?php

namespace App\Services;

use App\Enums\IssueType;
use App\Enums\SprintState;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BacklogService
{
    /** shortcut: one capped query for all open sprints plus backlog; paginate sections if projects outgrow it. */
    public const LIMIT = 1000;

    public function __construct(private IssueService $issues, private RankService $ranks, private SprintService $sprints) {}

    /**
     * Open sprints (active first, then planned) with their issues, then unscheduled issues.
     * Epics are hidden; done issues are hidden from the backlog section. Everything is ordered by rank.
     *
     * @return array{sprints: Collection, backlog: Collection, truncated: bool}
     */
    public function backlog(Project $project): array
    {
        $sprints = $this->sprints->withStats($project->sprints()->getQuery(), $project)
            ->where('state', '!=', SprintState::Completed->value)
            ->orderByRaw('state = ? desc', [SprintState::Active->value])
            ->orderBy('id')
            ->get();
        $done = $project->doneColumnId();

        $issues = $project->issues()
            ->where('type', '!=', IssueType::Epic->value)
            ->where(fn ($q) => $q->whereIn('sprint_id', $sprints->pluck('id'))
                ->orWhere(fn ($b) => $b->whereNull('sprint_id')->where('column_id', '!=', $done)))
            ->with(['assignee', 'labels', 'parent', 'column'])
            ->orderBy('rank')
            ->limit(self::LIMIT + 1)
            ->get()
            ->each->setRelation('project', $project);

        $bySprint = $issues->take(self::LIMIT)->groupBy(fn ($i) => $i->sprint_id ?? 'backlog');

        return [
            'sprints' => $sprints->each(fn ($s) => $s->setRelation('issues', $bySprint->get($s->id, collect()))),
            'backlog' => $bySprint->get('backlog', collect()),
            'truncated' => $issues->count() > self::LIMIT,
        ];
    }

    /**
     * Rank an issue in the backlog or a sprint. $input: sprint? (name, or "backlog"), position?, after?
     * Moving between sections is logged through IssueService::update; reordering alone isn't.
     */
    public function rank(Issue $issue, User $actor, array $input): Issue
    {
        return DB::transaction(function () use ($issue, $actor, $input) {
            Project::whereKey($issue->project_id)->lockForUpdate()->first();

            if (array_key_exists('sprint', $input)) {
                $target = $input['sprint'] === null || strcasecmp($input['sprint'], 'backlog') === 0 ? null : $input['sprint'];
                $issue = $this->issues->update($issue, $actor, ['sprint' => $target]);
            }
            $issue->loadMissing(IssueService::DISPLAY);

            $siblings = Issue::where('project_id', $issue->project_id)
                ->whereKeyNot($issue->id)
                ->where('type', '!=', IssueType::Epic->value)
                ->when(
                    $issue->sprint_id,
                    fn ($q, $id) => $q->where('sprint_id', $id),
                    fn ($q) => $q->whereNull('sprint_id')->where('column_id', '!=', $issue->project->doneColumnId()),
                );

            $position = match (true) {
                isset($input['after']) => $this->ranks->positionAfter($issue, $siblings, $input['after'], $issue->sprint->name ?? 'the backlog'),
                isset($input['position']) => (int) $input['position'],
                default => PHP_INT_MAX,
            };

            $this->ranks->place($issue, $siblings, $position);

            return $issue;
        });
    }
}
