<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_project')]
#[Description('Get one project by key, including its members (name, email, role).')]
#[IsReadOnly]
class GetProject extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'))->load('members');

        return $this->result("Project {$project->key}: {$project->name} ({$project->members->count()} members).", new ProjectResource($project));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
        ];
    }
}
