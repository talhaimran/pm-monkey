<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Issues\LabelRequest;
use App\Http\Resources\LabelResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\LabelService;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_label')]
#[Description('Create a label in a project (names are unique per project, case-insensitive).')]
class CreateLabel extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, LabelService $labels): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        Gate::authorize('contribute', $project);
        $label = $labels->create($project, $request->validate(LabelRequest::rulesFor(creating: true), (new LabelRequest)->messages()));

        return $this->result("Created label \"{$label->name}\" in {$project->key}.", new LabelResource($label));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'name' => $schema->string()->max(50)->required(),
            'color' => $schema->string()->pattern('^#[0-9a-fA-F]{6}$')->description('Hex color like #3b82f6. Default gray.'),
        ];
    }
}
