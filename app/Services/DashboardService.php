<?php

namespace App\Services;

use App\Enums\SprintState;
use App\Models\Column;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    /** Open issues assigned to the user across their projects: priority desc, due date (nulls last), key. */
    public function myIssues(User $user, int $limit = 50): Collection
    {
        $query = Issue::whereIn('project_id', $user->projects()->select('projects.id'))
            ->where('assignee_id', $user->id)
            ->where(fn ($q) => $this->notDone($q))
            ->with(IssueService::DISPLAY);

        return $query
            ->orderByRaw("FIELD(priority, 'lowest', 'low', 'medium', 'high', 'highest') desc")
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('project_id')
            ->orderBy('number')
            ->limit($limit)
            ->get();
    }

    /**
     * Per-project status counts, type counts, open/overdue counts and active sprint progress,
     * computed with a fixed number of queries regardless of how many projects there are.
     *
     * @param  Collection<int, Project>  $projects
     * @return array<int, array> keyed by project id
     */
    public function summaries(Collection $projects): array
    {
        $ids = $projects->pluck('id');
        $columns = Column::whereIn('project_id', $ids)->orderBy('position')->get()->groupBy('project_id');
        $doneIds = $columns->map(fn ($c) => $c->last()->id);

        $byColumn = Issue::whereIn('project_id', $ids)
            ->selectRaw('column_id, count(*) as total')->groupBy('column_id')->pluck('total', 'column_id');
        $byType = Issue::whereIn('project_id', $ids)
            ->selectRaw('project_id, type, count(*) as total')->groupBy('project_id', 'type')->get()->groupBy('project_id');
        $overdue = Issue::whereIn('project_id', $ids)
            ->where(fn ($q) => $this->notDone($q))
            ->whereDate('due_date', '<', today())
            ->selectRaw('project_id, count(*) as total')->groupBy('project_id')->pluck('total', 'project_id');

        $sprints = Sprint::whereIn('project_id', $ids)
            ->where('state', SprintState::Active->value)
            ->withCount(['issues', 'issues as issues_done' => fn ($q) => $this->done($q)])
            ->withSum('issues as points_total', 'story_points')
            ->withSum(['issues as points_done' => fn ($q) => $this->done($q)], 'story_points')
            ->get()
            ->keyBy('project_id');

        return $projects->mapWithKeys(function ($project) use ($columns, $doneIds, $byColumn, $byType, $overdue, $sprints) {
            $statuses = ($columns[$project->id] ?? collect())
                ->map(fn ($c) => ['name' => $c->name, 'count' => (int) ($byColumn[$c->id] ?? 0)])->values();
            $done = (int) ($byColumn[$doneIds[$project->id] ?? 0] ?? 0);
            $sprint = $sprints[$project->id] ?? null;

            return [$project->id => [
                'statuses' => $statuses->all(),
                'types' => ($byType[$project->id] ?? collect())->mapWithKeys(fn ($r) => [$r->type->value => (int) $r->total])->all(),
                'total' => $statuses->sum('count'),
                'open' => $statuses->sum('count') - $done,
                'overdue' => (int) ($overdue[$project->id] ?? 0),
                'active_sprint' => $sprint ? [
                    'name' => $sprint->name,
                    'start_date' => $sprint->start_date?->format('Y-m-d'),
                    'end_date' => $sprint->end_date?->format('Y-m-d'),
                    'issue_count' => (int) $sprint->issues_count,
                    'issues_done' => (int) $sprint->issues_done,
                    'points_total' => (int) $sprint->points_total,
                    'points_done' => (int) $sprint->points_done,
                ] : null,
            ]];
        })->all();
    }

    /** Issue sits in its project's last column. */
    private function done(Builder $query): Builder
    {
        return $query->whereRaw('issues.column_id = (select c.id from columns c where c.project_id = issues.project_id order by c.position desc limit 1)');
    }

    private function notDone(Builder $query): Builder
    {
        return $query->whereRaw('issues.column_id <> (select c.id from columns c where c.project_id = issues.project_id order by c.position desc limit 1)');
    }
}
