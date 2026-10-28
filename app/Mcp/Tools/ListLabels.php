<?php

namespace App\Mcp\Tools;

use App\Http\Resources\LabelResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_labels')]
#[Description('List the labels defined in a project.')]
#[IsReadOnly]
class ListLabels extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        $labels = $project->labels()->limit(200)->get();

        return $this->result("{$project->key} has {$labels->count()} labels.", LabelResource::collection($labels)->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return ['project' => $schema->string()->description('Project key, e.g. WEB.')->required()];
    }
}
