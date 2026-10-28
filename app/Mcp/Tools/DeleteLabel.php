<?php

namespace App\Mcp\Tools;

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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_label')]
#[Description('Delete a label; it is removed from every issue that has it.')]
#[IsDestructive]
class DeleteLabel extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, LabelService $labels): Response
    {
        $request->validate(['project' => 'required|string', 'label' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        Gate::authorize('contribute', $project);
        $label = $labels->resolve($project, $request->get('label'));
        $labels->delete($label);

        return $this->result("Deleted label \"{$label->name}\" from {$project->key}.", ['deleted' => $label->name]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'label' => $schema->string()->description('Label name.')->required(),
        ];
    }
}
