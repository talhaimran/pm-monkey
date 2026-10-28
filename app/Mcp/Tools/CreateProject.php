<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_project')]
#[Description('Create a project with a unique short key; you become its admin.')]
class CreateProject extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects): Response
    {
        Gate::authorize('create', Project::class);
        $rules = new StoreProjectRequest;
        $project = $projects->create($request->user(), $request->validate($rules->rules(), $rules->messages()));

        return $this->result("Created project {$project->key}.", new ProjectResource($project));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->pattern('^[A-Za-z][A-Za-z0-9]{1,9}$')->description('2-10 chars, letter first, e.g. WEB. Stored uppercase; cannot be changed later.')->required(),
            'name' => $schema->string()->max(100)->description('Display name.')->required(),
            'description' => $schema->string()->max(10000)->description('Markdown description.'),
        ];
    }
}
