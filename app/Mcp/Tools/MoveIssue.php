<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Board\MoveIssueRequest;
use App\Http\Resources\IssueResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\BoardService;
use App\Services\IssueService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('move_issue')]
#[Description('Move an issue to a board column (status) and/or a position in that column.')]
class MoveIssue extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, IssueService $issues, BoardService $board): Response
    {
        $request->validate(['issue' => 'required|string']);
        $issue = $issues->resolve($request->user(), $request->get('issue'));
        Gate::authorize('update', $issue);
        $rules = new MoveIssueRequest;
        $issue = $board->move($issue, $request->user(), $request->validate($rules->rules(), $rules->messages()));

        return $this->result("Moved {$issue->key} to {$issue->column->name}.", new IssueResource($issue));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue' => $schema->string()->description('Issue key, e.g. WEB-12.')->required(),
            'status' => $schema->string()->description('Destination column name; omit to reorder within the current column.'),
            'position' => $schema->integer()->min(0)->description('0-based index in the destination column; default bottom. Do not combine with after.'),
            'after' => $schema->string()->description('Place directly after this issue key (must be in the destination column).'),
        ];
    }
}
