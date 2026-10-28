<?php

namespace App\Mcp\Tools;

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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_column')]
#[Description('Delete a board column (admins only); if it has issues, move_issues_to must name another column.')]
#[IsDestructive]
class DeleteColumn extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, ColumnService $columns): Response
    {
        $data = $request->validate(['project' => 'required|string', 'column' => 'required|string', 'move_issues_to' => 'nullable|string|max:50']);
        $project = $projects->resolve($request->user(), $data['project']);
        Gate::authorize('manage', $project);
        $column = $columns->resolve($project, $data['column']);
        $columns->delete($column, $data['move_issues_to'] ?? null);

        return $this->result("Deleted column \"{$column->name}\" from {$project->key}.", ['deleted' => $column->name]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'column' => $schema->string()->description('Column name.')->required(),
            'move_issues_to' => $schema->string()->description('Column that receives this column\'s issues (required if it has any).'),
        ];
    }
}
