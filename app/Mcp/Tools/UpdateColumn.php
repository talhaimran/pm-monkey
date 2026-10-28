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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_column')]
#[Description('Rename and/or reposition a board column, addressed by its current name (admins only).')]
#[IsIdempotent]
class UpdateColumn extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, ColumnService $columns): Response
    {
        $request->validate(['project' => 'required|string', 'column' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        Gate::authorize('manage', $project);
        $column = $columns->update(
            $columns->resolve($project, $request->get('column')),
            $request->validate(ColumnRequest::rulesFor(creating: false)),
        );

        return $this->result("Updated column \"{$column->name}\" (position {$column->position}).", new ColumnResource($column));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'column' => $schema->string()->description('Current column name.')->required(),
            'name' => $schema->string()->max(50)->description('New name.'),
            'position' => $schema->integer()->min(0)->description('New 0-based position.'),
        ];
    }
}
