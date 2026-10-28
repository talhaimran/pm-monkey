<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Board\ColumnRequest;
use App\Http\Requests\Board\DeleteColumnRequest;
use App\Http\Resources\ColumnResource;
use App\Services\ColumnService;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ColumnController extends Controller
{
    public function __construct(private ProjectService $projects, private ColumnService $columns) {}

    /** Board columns (issue statuses) in order, with issue counts. */
    public function index(Request $request, string $key): AnonymousResourceCollection
    {
        $project = $this->projects->resolve($request->user(), $key);

        return ColumnResource::collection($project->columns()->withCount('issues')->get());
    }

    /** Add a column (admins). Without `position` it is appended. */
    public function store(ColumnRequest $request, string $key): ColumnResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('manage', $project);

        return new ColumnResource($this->columns->create($project, $request->validated()));
    }

    /** Rename and/or move a column, addressed by its current name (admins). */
    public function update(ColumnRequest $request, string $key, string $name): ColumnResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('manage', $project);

        return new ColumnResource($this->columns->update($this->columns->resolve($project, $name), $request->validated()));
    }

    /** Delete a column (admins). If it has issues, `move_issues_to` must name another column. */
    public function destroy(DeleteColumnRequest $request, string $key, string $name): Response
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('manage', $project);
        $this->columns->delete($this->columns->resolve($project, $name), $request->validated('move_issues_to'));

        return response()->noContent();
    }
}
