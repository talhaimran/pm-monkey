<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Projects\DestroyProjectRequest;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_project')]
#[Description('Permanently delete a project and all its issues (admins only; requires confirm: true).')]
#[IsDestructive]
class DeleteProject extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        Gate::authorize('delete', $project);
        $rules = new DestroyProjectRequest;
        $request->validate($rules->rules(), $rules->messages());
        $projects->delete($project);

        return $this->result("Deleted project {$project->key}.", ['deleted' => $project->key]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'confirm' => $schema->boolean()->description('Must be true. Deletion cannot be undone.')->required(),
        ];
    }
}
