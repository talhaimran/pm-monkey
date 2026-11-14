<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Board\BoardFilterRequest;
use App\Http\Requests\Board\ColumnRequest;
use App\Http\Requests\Board\DeleteColumnRequest;
use App\Http\Requests\Board\MoveIssueRequest;
use App\Http\Resources\BoardResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SprintResource;
use App\Models\Column;
use App\Models\Project;
use App\Services\BoardService;
use App\Services\ColumnService;
use App\Services\IssueService;
use App\Services\ProjectService;
use App\Services\SprintService;
use App\Services\UndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function __construct(
        private ProjectService $projects,
        private IssueService $issues,
        private BoardService $board,
        private ColumnService $columns,
        private SprintService $sprints,
        private UndoService $undo,
    ) {}

    public function show(BoardFilterRequest $request, Project $project): Response
    {
        Gate::authorize('view', $project);
        $filters = array_filter($request->validated());
        $sprint = $this->sprints->active($project);

        return Inertia::render('Projects/Board', [
            'project' => (new ProjectResource($project))->resolve(),
            'board' => (new BoardResource($this->board->board($project, $filters)))->resolve(),
            'active_sprint' => $sprint ? (new SprintResource($sprint))->resolve() : null,
            'filters' => (object) $filters,
            'options' => $this->projects->issueOptions($project),
            'can' => ['contribute' => $request->user()->can('contribute', $project)],
        ]);
    }

    public function move(MoveIssueRequest $request, string $key): RedirectResponse
    {
        $issue = $this->issues->resolve($request->user(), $key);
        Gate::authorize('update', $issue);
        $undoOf = $request->validate(['undo_of' => ['nullable', 'integer']])['undo_of'] ?? null;

        if ($undoOf) {
            $this->undo->guard($issue, (int) $undoOf);
            $this->board->move($issue, $request->user(), $request->validated(), 'undid');

            return back();
        }

        $before = $this->undo->forMove($issue);
        $issue = $this->board->move($issue, $request->user(), $request->validated());

        return back()->with('undo', [
            'message' => $before['status'] === $issue->column->name ? "{$issue->key} moved" : "{$issue->key} moved to {$issue->column->name}",
            'method' => 'post',
            'url' => route('issues.move', $issue->key),
            'data' => [...$before, 'undo_of' => $this->undo->token($issue)],
        ]);
    }

    public function storeColumn(ColumnRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $this->columns->create($project, $request->validated());

        return back();
    }

    public function updateColumn(ColumnRequest $request, Project $project, Column $column): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $this->columns->update($column->setRelation('project', $project), $request->validated());

        return back();
    }

    public function destroyColumn(DeleteColumnRequest $request, Project $project, Column $column): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $this->columns->delete($column->setRelation('project', $project), $request->validated('move_issues_to'));

        return back();
    }
}
