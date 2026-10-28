<?php

namespace App\Mcp\Tools;

use App\Http\Resources\IssueResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\IssueService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_issue')]
#[Description('Get an issue by key with its sub-tasks, epic issues and comments (with comment ids).')]
#[IsReadOnly]
class GetIssue extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, IssueService $issues): Response
    {
        $request->validate(['issue' => 'required|string']);
        $issue = $issues->loadFull($issues->resolve($request->user(), $request->get('issue')));

        return $this->result(
            "{$issue->key} [{$issue->type->value}, {$issue->column->name}, {$issue->priority->value}]: {$issue->title}",
            new IssueResource($issue),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return ['issue' => $schema->string()->description('Issue key, e.g. WEB-12.')->required()];
    }
}
