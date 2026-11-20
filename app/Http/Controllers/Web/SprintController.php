<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sprints\CompleteSprintRequest;
use App\Http\Requests\Sprints\RankIssueRequest;
use App\Http\Requests\Sprints\SprintRequest;
use App\Http\Requests\Sprints\StartSprintRequest;
use App\Http\Resources\BacklogResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Sprint;
use App\Services\BacklogService;
use App\Services\IssueService;
use App\Services\ProjectService;
use App\Services\SprintService;
use App\Services\UndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SprintController extends Controller
{
    public function __construct(
        private ProjectService $projects,
        private IssueService $issues,
        private SprintService $sprints,
        private BacklogService $backlog,
        private UndoService $undo,
    ) {}

    public function backlog(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('Projects/Backlog', [
            'project' => (new ProjectResource($project))->resolve(),
            'backlog' => (new BacklogResource($this->backlog->backlog($project)))->resolve(),
            'options' => $this->projects->issueOptions($project),
            'can' => ['contribute' => $request->user()->can('contribute', $project)],
        ]);
    }

    public function store(SprintRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('contribute', $project);
        $this->sprints->create($project, array_filter($request->validated(), fn ($v) => $v !== null));

        return back();
    }

    public function update(SprintRequest $request, Project $project, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('contribute', $project);
        $this->sprints->update($sprint->setRelation('project', $project), $request->validated());

        return back();
    }

    public function destroy(Project $project, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('remove', $project);
        $this->sprints->delete($sprint->setRelation('project', $project));

        return back();
    }

    public function start(StartSprintRequest $request, Project $project, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('contribute', $project);
        $this->sprints->start($sprint->setRelation('project', $project), $request->validated());

        return back();
    }

    public function complete(CompleteSprintRequest $request, Project $project, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('contribute', $project);
        $this->sprints->complete($sprint->setRelation('project', $project), $request->user(), $request->validated('move_open_issues_to'));

        return back();
    }

    public function rank(RankIssueRequest $request, string $key): RedirectResponse
    {
        $issue = $this->issues->resolve($request->user(), $key);
        Gate::authorize('update', $issue);
        $undoOf = $request->validate(['undo_of' => ['nullable', 'integer']])['undo_of'] ?? null;

        if ($undoOf) {
            $this->undo->guard($issue, (int) $undoOf);
            $this->backlog->rank($issue, $request->user(), $request->validated(), 'undid');

            return back();
        }

        $before = $this->undo->forRank($issue);
        $issue = $this->backlog->rank($issue, $request->user(), $request->validated());
        $now = $issue->sprint?->name ?? 'backlog';

        return back()->with('undo', [
            'message' => $before['sprint'] === $now ? "{$issue->key} moved" : "{$issue->key} moved to ".($issue->sprint?->name ?? 'the backlog'),
            'method' => 'post',
            'url' => route('issues.rank', $issue->key),
            'data' => [...$before, 'undo_of' => $this->undo->token($issue)],
        ]);
    }
}
