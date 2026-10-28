<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Issues\DestroyIssueRequest;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\IssueService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_issue')]
#[Description('Permanently delete an issue with its sub-tasks, comments and history (requires confirm: true).')]
#[IsDestructive]
class DeleteIssue extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, IssueService $issues): Response
    {
        $request->validate(['issue' => 'required|string']);
        $issue = $issues->resolve($request->user(), $request->get('issue'));
        Gate::authorize('delete', $issue);
        $rules = new DestroyIssueRequest;
        $request->validate($rules->rules(), $rules->messages());
        $key = $issue->key;
        $issues->delete($issue);

        return $this->result("Deleted {$key}.", ['deleted' => $key]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue' => $schema->string()->description('Issue key, e.g. WEB-12.')->required(),
            'confirm' => $schema->boolean()->description('Must be true. Deletion cannot be undone.')->required(),
        ];
    }
}
