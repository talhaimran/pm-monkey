<?php

namespace App\Mcp\Tools;

use App\Http\Resources\BacklogResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\BacklogService;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_backlog')]
#[Description('Show open sprints (active first) and the backlog, each with issues in rank order.')]
#[IsReadOnly]
class GetBacklog extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, BacklogService $backlog): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        $data = (new BacklogResource($backlog->backlog($project)))->resolve();

        $summary = collect($data['sprints'])
            ->map(fn ($s) => "{$s['name']} ({$s['state']}): ".count($s['issues']))
            ->push('Backlog: '.count($data['backlog']['issues']))
            ->implode('; ');

        return $this->result("{$project->key} — {$summary}.", $data);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['project' => $schema->string()->description('Project key, e.g. WEB.')->required()];
    }
}
