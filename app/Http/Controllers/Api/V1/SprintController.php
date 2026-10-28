<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sprints\CompleteSprintRequest;
use App\Http\Requests\Sprints\SprintRequest;
use App\Http\Requests\Sprints\StartSprintRequest;
use App\Http\Resources\SprintResource;
use App\Services\ProjectService;
use App\Services\SprintService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SprintController extends Controller
{
    public function __construct(private ProjectService $projects, private SprintService $sprints) {}

    /** List sprints, newest first. Optional `state`: planned, active or completed. */
    public function index(Request $request, string $key): AnonymousResourceCollection
    {
        $project = $this->projects->resolve($request->user(), $key);
        $query = $this->sprints->withStats($project->sprints()->getQuery(), $project)
            ->when($request->query('state'), fn ($q, $state) => $q->where('state', $state))
            ->orderByDesc('id');

        return SprintResource::collection($query->paginate(min($request->integer('per_page', 25), 100)));
    }

    /** Create a planned sprint. Name defaults to "{KEY} Sprint N". */
    public function store(SprintRequest $request, string $key): SprintResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('contribute', $project);

        return new SprintResource($this->sprints->create($project, array_filter($request->validated(), fn ($v) => $v !== null)));
    }

    /** Get a sprint by id or name, with issue count and story points (total and done). */
    public function show(Request $request, string $key, string $sprint): SprintResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        $sprint = $this->sprints->resolve($project, $sprint);

        return new SprintResource($this->sprints->withStats($project->sprints()->getQuery(), $project)->find($sprint->id));
    }

    /** Edit a planned or active sprint's name, goal or dates. */
    public function update(SprintRequest $request, string $key, string $sprint): SprintResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('contribute', $project);

        return new SprintResource($this->sprints->update($this->sprints->resolve($project, $sprint), $request->validated()));
    }

    /** Delete a planned sprint; its issues return to the backlog. */
    public function destroy(Request $request, string $key, string $sprint): Response
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('contribute', $project);
        $this->sprints->delete($this->sprints->resolve($project, $sprint));

        return response()->noContent();
    }

    /** Start a planned sprint. Dates default to today and +14 days. Only one sprint can be active. */
    public function start(StartSprintRequest $request, string $key, string $sprint): SprintResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('contribute', $project);

        return new SprintResource($this->sprints->start($this->sprints->resolve($project, $sprint), $request->validated()));
    }

    /**
     * Complete the active sprint (requires confirm: true).
     *
     * Unfinished issues (not in the last column) move to `move_open_issues_to`: "backlog" or a planned sprint name.
     */
    public function complete(CompleteSprintRequest $request, string $key, string $sprint): SprintResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('contribute', $project);
        $sprint = $this->sprints->resolve($project, $sprint);
        $moved = $this->sprints->complete($sprint, $request->user(), $request->validated('move_open_issues_to'));

        return (new SprintResource($sprint->refresh()))->additional(['meta' => ['moved_issues' => $moved]]);
    }
}
