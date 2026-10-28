<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Sprints\StartSprintRequest;
use App\Http\Resources\SprintResource;
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

#[Name('start_sprint')]
#[Description('Start a planned sprint (only one active per project); dates default to today and +14 days.')]
class StartSprint extends Tool
{
    use ResolvesSprint, RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, SprintService $sprints): Response
    {
        [, $sprint] = $this->projectAndSprint($request, $projects, $sprints);
        $sprint = $sprints->start($sprint, $request->validate((new StartSprintRequest)->rules()));

        return $this->result(
            "Started {$sprint->name}: {$sprint->start_date->toDateString()} to {$sprint->end_date->toDateString()}.",
            new SprintResource($sprint),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'sprint' => $schema->string()->description('Sprint name or id.')->required(),
            'start_date' => $schema->string()->format('date')->description('YYYY-MM-DD; default today or the planned date.'),
            'end_date' => $schema->string()->format('date')->description('YYYY-MM-DD; default start + 14 days.'),
        ];
    }
}
