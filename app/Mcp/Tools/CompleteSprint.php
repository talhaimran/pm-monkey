<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Sprints\CompleteSprintRequest;
use App\Http\Resources\SprintResource;
use App\Mcp\Tools\Concerns\ResolvesSprint;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\ProjectService;
use App\Services\SprintService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('complete_sprint')]
#[Description('Complete the active sprint; unfinished issues move to the backlog or a planned sprint (requires confirm: true).')]
#[IsDestructive]
class CompleteSprint extends Tool
{
    use ResolvesSprint, RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, SprintService $sprints): Response
    {
        [, $sprint] = $this->projectAndSprint($request, $projects, $sprints);
        $rules = new CompleteSprintRequest;
        $data = $request->validate($rules->rules(), $rules->messages());
        $moved = $sprints->complete($sprint, $request->user(), $data['move_open_issues_to']);
        $target = strcasecmp(trim($data['move_open_issues_to']), 'backlog') === 0 ? 'the backlog' : $data['move_open_issues_to'];

        return $this->result(
            "Completed {$sprint->name}; {$moved} unfinished ".Str::plural('issue', $moved)." moved to {$target}.",
            [...(new SprintResource($sprint->refresh()))->resolve(), 'moved_issues' => $moved],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'sprint' => $schema->string()->description('Name or id of the active sprint.')->required(),
            'move_open_issues_to' => $schema->string()->description('"backlog" or the name of a planned sprint.')->required(),
            'confirm' => $schema->boolean()->description('Must be true. A completed sprint cannot be reopened.')->required(),
        ];
    }
}
