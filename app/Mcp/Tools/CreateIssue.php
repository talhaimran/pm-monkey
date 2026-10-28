<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Issues\StoreIssueRequest;
use App\Http\Resources\IssueResource;
use App\Mcp\Tools\Concerns\IssueFields;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\IssueService;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_issue')]
#[Description('Create an issue, sub-task (set parent) or epic in a project; returns its key, e.g. WEB-12.')]
class CreateIssue extends Tool
{
    use IssueFields, RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, IssueService $issues): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        Gate::authorize('contribute', $project);
        $rules = new StoreIssueRequest;
        $issue = $issues->create($project, $request->user(), $request->validate($rules->rules(), $rules->messages()));

        return $this->result("Created {$issue->key}: {$issue->title}", new IssueResource($issue));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'title' => $schema->string()->max(255)->required(),
            ...$this->issueFields($schema),
        ];
    }
}
