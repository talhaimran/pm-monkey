<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ColumnResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_columns')]
#[Description('List a project\'s board columns (valid issue statuses) in order, with issue counts.')]
#[IsReadOnly]
class ListColumns extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        $columns = $project->columns()->withCount('issues')->get();

        return $this->result("{$project->key} columns: ".$columns->pluck('name')->implode(', ').'.', ColumnResource::collection($columns)->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return ['project' => $schema->string()->description('Project key, e.g. WEB.')->required()];
    }
}
