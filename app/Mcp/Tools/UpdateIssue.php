<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Issues\UpdateIssueRequest;
use App\Http\Resources\IssueResource;
use App\Mcp\Tools\Concerns\IssueFields;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\IssueService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_issue')]
#[Description('Update an issue by key; send only the fields to change (null clears assignee, epic, parent, points, due date).')]
#[IsIdempotent]
class UpdateIssue extends Tool
{
    use IssueFields, RespondsWithJson;

    public function handle(Request $request, IssueService $issues): Response
    {
        $request->validate(['issue' => 'required|string']);
        $issue = $issues->resolve($request->user(), $request->get('issue'));
        Gate::authorize('update', $issue);
        $rules = new UpdateIssueRequest;
        $issue = $issues->update($issue, $request->user(), $request->validate($rules->rules(), $rules->messages()));

        return $this->result("Updated {$issue->key}.", new IssueResource($issue));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue' => $schema->string()->description('Issue key, e.g. WEB-12.')->required(),
            'title' => $schema->string()->max(255),
            ...$this->issueFields($schema),
        ];
    }
}
