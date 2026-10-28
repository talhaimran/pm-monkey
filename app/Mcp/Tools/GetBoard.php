<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Board\BoardFilterRequest;
use App\Http\Resources\BoardResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\BoardService;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_board')]
#[Description('Show a project board: columns in order with issue cards sorted by rank (epics hidden), optionally filtered.')]
#[IsReadOnly]
class GetBoard extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, BoardService $board): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        $rules = new BoardFilterRequest;
        $data = (new BoardResource($board->board($project, $request->validate($rules->rules(), $rules->messages()))))->resolve();

        $summary = collect($data['columns'])->map(fn ($c) => "{$c['name']}: ".count($c['issues']))->implode(', ');

        return $this->result("{$project->key} board — {$summary}.".($data['truncated'] ? ' Truncated; add filters to narrow it.' : ''), $data);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'assignee' => $schema->string()->description('Member email, or "unassigned".'),
            'type' => $schema->string()->enum(['task', 'bug', 'story']),
            'priority' => $schema->string()->enum(['lowest', 'low', 'medium', 'high', 'highest']),
            'label' => $schema->string()->description('Label name.'),
            'q' => $schema->string()->description('Text in the title, or an issue key.'),
            'sprint' => $schema->string()->description('Sprint name, "active" or "backlog".'),
        ];
    }
}
