<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sprints\RankIssueRequest;
use App\Http\Resources\BacklogResource;
use App\Http\Resources\IssueResource;
use App\Services\BacklogService;
use App\Services\IssueService;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BacklogController extends Controller
{
    public function __construct(private ProjectService $projects, private IssueService $issues, private BacklogService $backlog) {}

    /** Open sprints (active first) with their issues, then the backlog, each ordered by rank. */
    public function show(Request $request, string $key): BacklogResource
    {
        return new BacklogResource($this->backlog->backlog($this->projects->resolve($request->user(), $key)));
    }

    /**
     * Rank an issue in the backlog or a sprint.
     *
     * `sprint`: destination sprint name, or "backlog" (omit to stay). Place with `position` (0-based) or `after` (issue key).
     */
    public function rank(RankIssueRequest $request, string $key): IssueResource
    {
        $issue = $this->issues->resolve($request->user(), $key);
        Gate::authorize('update', $issue);

        return new IssueResource($this->backlog->rank($issue, $request->user(), $request->validated()));
    }
}
