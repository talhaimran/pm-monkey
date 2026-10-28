<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Board\ColumnRequest;
use App\Http\Resources\ColumnResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\ColumnService;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_column')]
#[Description('Add a board column to a project (admins only); appended unless position is given.')]
class CreateColumn extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, ColumnService $columns): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        Gate::authorize('manage', $project);
        $column = $columns->create($project, $request->validate(ColumnRequest::rulesFor(creating: true)));

        return $this->result("Added column \"{$column->name}\" to {$project->key}.", new ColumnResource($column));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'name' => $schema->string()->max(50)->required(),
            'position' => $schema->integer()->min(0)->description('0-based position; default last.'),
        ];
    }
}
