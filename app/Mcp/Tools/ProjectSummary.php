<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\DashboardService;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('project_summary')]
#[Description('Project health: issue counts per status and type, open/overdue counts, and active sprint progress.')]
#[IsReadOnly]
class ProjectSummary extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, DashboardService $dashboard): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        $summary = $dashboard->summaries(collect([$project]))[$project->id];

        $statuses = collect($summary['statuses'])->map(fn ($s) => "{$s['name']}: {$s['count']}")->implode(', ');
        $sprint = $summary['active_sprint']
            ? "Active sprint {$summary['active_sprint']['name']}: {$summary['active_sprint']['points_done']}/{$summary['active_sprint']['points_total']} points done."
            : 'No active sprint.';

        return $this->result(
            "{$project->key}: {$summary['open']} open, {$summary['overdue']} overdue. {$statuses}. {$sprint}",
            ['project' => $project->key, ...$summary],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return ['project' => $schema->string()->description('Project key, e.g. WEB.')->required()];
    }
}
