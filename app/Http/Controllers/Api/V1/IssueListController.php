<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lists\IssueListRequest;
use App\Http\Resources\IssueResource;
use App\Services\DashboardService;
use App\Services\IssueService;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IssueListController extends Controller
{
    public function __construct(private ProjectService $projects, private IssueService $issues, private DashboardService $dashboard) {}

    /**
     * List a project's issues (epics included), paginated.
     *
     * Filters: assignee (email or "unassigned"), type, priority, label, sprint (name, "active" or "backlog"), q.
     * `sort`: key, title, type, priority, status, assignee, story_points, due_date, created, updated; prefix "-" for descending (default -key).
     */
    public function index(IssueListRequest $request, string $key): AnonymousResourceCollection
    {
        $project = $this->projects->resolve($request->user(), $key);

        return IssueResource::collection($this->issues->list($request->user(), $project, $request->validated()));
    }

    /** Search issues across all your projects (or one, with `project`). Same filters and sorts as the project list; `q` also matches keys like WEB-12. */
    public function search(IssueListRequest $request): AnonymousResourceCollection
    {
        $project = $request->validated('project') ? $this->projects->resolve($request->user(), $request->validated('project')) : null;

        return IssueResource::collection($this->issues->list($request->user(), $project, $request->validated()));
    }

    /** Open issues assigned to you across projects, by priority then due date (max 100). */
    public function mine(Request $request): AnonymousResourceCollection
    {
        return IssueResource::collection($this->dashboard->myIssues($request->user(), 100));
    }

    /** Status counts (column order), counts by type, open and overdue counts, and active sprint progress. */
    public function summary(Request $request, string $key): JsonResponse
    {
        $project = $this->projects->resolve($request->user(), $key);

        return response()->json(['data' => ['project' => $project->key, ...$this->dashboard->summaries(collect([$project]))[$project->id]]]);
    }
}
