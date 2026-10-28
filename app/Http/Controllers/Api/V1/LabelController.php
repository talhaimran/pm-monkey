<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\LabelRequest;
use App\Http\Resources\LabelResource;
use App\Services\LabelService;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LabelController extends Controller
{
    public function __construct(private ProjectService $projects, private LabelService $labels) {}

    /** List a project's labels. */
    public function index(Request $request, string $key): AnonymousResourceCollection
    {
        return LabelResource::collection($this->projects->resolve($request->user(), $key)->labels()->get());
    }

    /** Create a label (names are unique per project, case-insensitive). */
    public function store(LabelRequest $request, string $key): LabelResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('contribute', $project);

        return new LabelResource($this->labels->create($project, $request->validated()));
    }

    /** Rename or recolor a label, addressed by its current name. */
    public function update(LabelRequest $request, string $key, string $name): LabelResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('contribute', $project);

        return new LabelResource($this->labels->update($this->labels->resolve($project, $name), $request->validated()));
    }

    /** Delete a label; it is removed from all issues. */
    public function destroy(Request $request, string $key, string $name): Response
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('contribute', $project);
        $this->labels->delete($this->labels->resolve($project, $name));

        return response()->noContent();
    }
}
