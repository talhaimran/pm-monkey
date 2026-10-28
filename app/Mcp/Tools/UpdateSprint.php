<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Sprints\SprintRequest;
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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_sprint')]
#[Description('Rename a sprint or change its goal or dates (completed sprints are read-only).')]
#[IsIdempotent]
class UpdateSprint extends Tool
{
    use ResolvesSprint, RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, SprintService $sprints): Response
    {
        [, $sprint] = $this->projectAndSprint($request, $projects, $sprints);
        $rules = new SprintRequest;
        $data = array_filter($request->validate($rules->rules(), $rules->messages()), fn ($v) => $v !== null);
        $sprint = $sprints->update($sprint, $data);

        return $this->result("Updated {$sprint->name}.", new SprintResource($sprint));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'sprint' => $schema->string()->description('Current sprint name or id.')->required(),
            'name' => $schema->string()->max(100),
            'goal' => $schema->string(),
            'start_date' => $schema->string()->format('date'),
            'end_date' => $schema->string()->format('date'),
        ];
    }
}
