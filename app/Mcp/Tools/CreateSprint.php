<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Sprints\SprintRequest;
use App\Http\Resources\SprintResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\ProjectService;
use App\Services\SprintService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_sprint')]
#[Description('Create a planned sprint (name defaults to "{KEY} Sprint N"); add issues with update_issue or rank_backlog.')]
class CreateSprint extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, SprintService $sprints): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        Gate::authorize('contribute', $project);
        $rules = new SprintRequest;
        $data = array_filter($request->validate($rules->rules(), $rules->messages()), fn ($v) => $v !== null);
        $sprint = $sprints->create($project, $data);

        return $this->result("Created {$sprint->name} (planned).", new SprintResource($sprint));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'name' => $schema->string()->max(100)->description('Unique in the project; "backlog" and "active" are reserved.'),
            'goal' => $schema->string(),
            'start_date' => $schema->string()->format('date')->description('YYYY-MM-DD.'),
            'end_date' => $schema->string()->format('date')->description('YYYY-MM-DD, on or after start_date.'),
        ];
    }
}
