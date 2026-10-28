<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\LabelRequest;
use App\Models\Label;
use App\Models\Project;
use App\Services\LabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LabelController extends Controller
{
    public function __construct(private LabelService $labels) {}

    public function store(LabelRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('contribute', $project);
        $this->labels->create($project, $request->validated());

        return back();
    }

    public function update(LabelRequest $request, Project $project, Label $label): RedirectResponse
    {
        Gate::authorize('contribute', $project);
        $this->labels->update($label->setRelation('project', $project), $request->validated());

        return back();
    }

    public function destroy(Project $project, Label $label): RedirectResponse
    {
        Gate::authorize('contribute', $project);
        $this->labels->delete($label);

        return back();
    }
}
