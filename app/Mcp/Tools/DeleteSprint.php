<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesSprint;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\ProjectService;
use App\Services\SprintService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_sprint')]
#[Description('Delete a planned sprint; its issues return to the backlog.')]
#[IsDestructive]
class DeleteSprint extends Tool
{
    use ResolvesSprint, RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, SprintService $sprints): Response
    {
        [, $sprint] = $this->projectAndSprint($request, $projects, $sprints);
        $sprints->delete($sprint);

        return $this->result("Deleted {$sprint->name}; its issues are back in the backlog.", ['deleted' => $sprint->name]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'sprint' => $schema->string()->description('Sprint name or id.')->required(),
        ];
    }
}
