<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\BulkStoreIssueRequest;
use App\Http\Requests\Issues\BulkUpdateIssueRequest;
use App\Http\Requests\Issues\DestroyIssueRequest;
use App\Http\Requests\Issues\StoreIssueRequest;
use App\Http\Requests\Issues\UpdateIssueRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\IssueResource;
use App\Services\IssueService;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class IssueController extends Controller
{
    public function __construct(private ProjectService $projects, private IssueService $issues) {}

    /**
     * Create an issue in a project.
     *
     * Use names, not IDs: `status` is a column name, `assignee` a member email, `labels` label names,
     * `epic` and `parent` issue keys (e.g. WEB-3). Unknown values return 422 listing valid options.
     */
    public function store(StoreIssueRequest $request, string $key): IssueResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('contribute', $project);

        return new IssueResource($this->issues->create($project, $request->user(), $request->validated()));
    }

    /**
     * Create up to 50 issues in one project, all or nothing.
     *
     * Body: `{"issues": [{...same fields as create}, ...]}`. Errors are keyed `issues.{index}.{field}`.
     */
    public function bulkStore(BulkStoreIssueRequest $request, string $key): JsonResponse
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('contribute', $project);

        return IssueResource::collection($this->issues->createMany($project, $request->user(), $request->validated('issues')))
            ->response()->setStatusCode(201);
    }

    /**
     * Update up to 50 issues across your projects, all or nothing.
     *
     * Body: `{"updates": [{"key": "WEB-1", ...fields to change}, ...]}`. Errors are keyed `updates.{index}.{field}`.
     */
    public function bulkUpdate(BulkUpdateIssueRequest $request): AnonymousResourceCollection
    {
        return IssueResource::collection($this->issues->updateMany($request->user(), $request->validated('updates')));
    }

    /** Get an issue by key (e.g. WEB-12) with sub-tasks, epic issues and comments. */
    public function show(Request $request, string $key): IssueResource
    {
        return new IssueResource($this->issues->loadFull($this->issues->resolve($request->user(), $key)));
    }

    /** Update an issue. Send only the fields to change; send null to clear assignee, epic, parent, points or due date. */
    public function update(UpdateIssueRequest $request, string $key): IssueResource
    {
        $issue = $this->issues->resolve($request->user(), $key);
        Gate::authorize('update', $issue);

        return new IssueResource($this->issues->update($issue, $request->user(), $request->validated()));
    }

    /** Delete an issue and its sub-tasks (requires confirm: true). */
    public function destroy(DestroyIssueRequest $request, string $key): Response
    {
        $issue = $this->issues->resolve($request->user(), $key);
        Gate::authorize('delete', $issue);
        $this->issues->delete($issue);

        return response()->noContent();
    }

    /** Activity log for an issue, newest first. `via` is web, api or mcp; `token_name` names the API token used. */
    public function activity(Request $request, string $key): AnonymousResourceCollection
    {
        $issue = $this->issues->resolve($request->user(), $key);

        return ActivityResource::collection(
            $issue->activities()->with('user')->latest('id')->paginate(min($request->integer('per_page', 50), 100))
        );
    }
}
