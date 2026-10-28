<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_project')]
#[Description('Update a project\'s name or description (admins only).')]
#[IsIdempotent]
class UpdateProject extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        Gate::authorize('update', $project);
        $project = $projects->update($project, $request->validate((new UpdateProjectRequest)->rules()));

        return $this->result("Updated project {$project->key}.", new ProjectResource($project));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'name' => $schema->string()->max(100)->description('New display name.'),
            'description' => $schema->string()->max(10000)->description('New Markdown description.'),
        ];
    }
}
