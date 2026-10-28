<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Board\BoardFilterRequest;
use App\Http\Requests\Board\ColumnRequest;
use App\Http\Requests\Board\DeleteColumnRequest;
use App\Http\Requests\Board\MoveIssueRequest;
use App\Http\Resources\BoardResource;
use App\Http\Resources\ProjectResource;
use App\Models\Column;
use App\Models\Project;
use App\Services\BoardService;
use App\Services\ColumnService;
use App\Services\IssueService;
use App\Services\ProjectService;
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
    ) {}

    public function show(BoardFilterRequest $request, Project $project): Response
    {
        Gate::authorize('view', $project);
        $filters = array_filter($request->validated());

        return Inertia::render('Projects/Board', [
            'project' => (new ProjectResource($project))->resolve(),
            'board' => (new BoardResource($this->board->board($project, $filters)))->resolve(),
            'filters' => (object) $filters,
            'options' => $this->projects->issueOptions($project),
            'can' => ['contribute' => $request->user()->can('contribute', $project)],
        ]);
    }

    public function move(MoveIssueRequest $request, string $key): RedirectResponse
    {
        $issue = $this->issues->resolve($request->user(), $key);
        Gate::authorize('update', $issue);
        $this->board->move($issue, $request->user(), $request->validated());

        return back();
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
