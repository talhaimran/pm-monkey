<?php

namespace App\Mcp\Tools;

use App\Enums\SprintState;
use App\Http\Resources\SprintResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\ProjectService;
use App\Services\SprintService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_sprints')]
#[Description('List a project\'s sprints (newest first) with state, dates, issue count and story points.')]
#[IsReadOnly]
class ListSprints extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, SprintService $sprints): Response
    {
        $data = $request->validate(['project' => 'required|string', 'state' => 'nullable|in:planned,active,completed', 'page' => 'integer|min:1']);
        $project = $projects->resolve($request->user(), $data['project']);
        $page = $sprints->withStats($project->sprints()->getQuery(), $project)
            ->when($data['state'] ?? null, fn ($q, $state) => $q->where('state', $state))
            ->orderByDesc('id')
            ->paginate(25, page: $data['page'] ?? 1);

        return $this->paginated("sprints in {$project->key}", $page, SprintResource::class);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'state' => $schema->string()->enum(SprintState::class),
            'page' => $schema->integer()->min(1),
        ];
    }
}
