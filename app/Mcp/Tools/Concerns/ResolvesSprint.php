<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Project;
use App\Models\Sprint;
use App\Services\ProjectService;
use App\Services\SprintService;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;

trait ResolvesSprint
{
    /** Validate project + sprint arguments, authorize writes, and return both. @return array{0: Project, 1: Sprint} */
    protected function projectAndSprint(Request $request, ProjectService $projects, SprintService $sprints, bool $write = true): array
    {
        $request->validate(['project' => 'required|string', 'sprint' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        if ($write) {
            Gate::authorize('contribute', $project);
        }

        return [$project, $sprints->resolve($project, (string) $request->get('sprint'))];
    }
}
