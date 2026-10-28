<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Board\BoardFilterRequest;
use App\Http\Requests\Board\MoveIssueRequest;
use App\Http\Resources\BoardResource;
use App\Http\Resources\IssueResource;
use App\Services\BoardService;
use App\Services\IssueService;
use App\Services\ProjectService;
use Illuminate\Support\Facades\Gate;

class BoardController extends Controller
{
    public function __construct(private ProjectService $projects, private IssueService $issues, private BoardService $board) {}

    /**
     * The project's board: columns in order with issue cards sorted by rank (epics hidden).
     *
     * Filters: `assignee` (email or "unassigned"), `type`, `priority`, `label` (name), `q` (title text or key).
     */
    public function show(BoardFilterRequest $request, string $key): BoardResource
    {
        $project = $this->projects->resolve($request->user(), $key);

        return new BoardResource($this->board->board($project, $request->validated()));
    }

    /**
     * Move an issue to a column and/or place in it.
     *
     * `status` is the destination column name (default: current). Place it with `position` (0-based index
     * in the destination column, default bottom) or `after` (key of an issue in that column).
     */
    public function move(MoveIssueRequest $request, string $key): IssueResource
    {
        $issue = $this->issues->resolve($request->user(), $key);
        Gate::authorize('update', $issue);

        return new IssueResource($this->board->move($issue, $request->user(), $request->validated()));
    }
}
