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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_label')]
#[Description('Rename or recolor a label, addressed by its current name.')]
#[IsIdempotent]
class UpdateLabel extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, LabelService $labels): Response
    {
        $request->validate(['project' => 'required|string', 'label' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        Gate::authorize('contribute', $project);
        $label = $labels->update(
            $labels->resolve($project, $request->get('label')),
            $request->validate(LabelRequest::rulesFor(creating: false), (new LabelRequest)->messages()),
        );

        return $this->result("Updated label \"{$label->name}\".", new LabelResource($label));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'label' => $schema->string()->description('Current label name.')->required(),
            'name' => $schema->string()->max(50)->description('New name.'),
            'color' => $schema->string()->pattern('^#[0-9a-fA-F]{6}$')->description('New hex color.'),
        ];
    }
}
