<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Issues\BulkStoreIssueRequest;
use App\Http\Resources\IssueCardResource;
use App\Mcp\Tools\Concerns\IssueFields;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\IssueService;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('bulk_create_issues')]
#[Description('Create up to 50 issues in one project in one call; all or nothing (errors name the item, e.g. [issues.3]).')]
class BulkCreateIssues extends Tool
{
    use IssueFields, RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, IssueService $issues): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $projects->resolve($request->user(), $request->get('project'));
        Gate::authorize('contribute', $project);
        $rules = new BulkStoreIssueRequest;
        $created = $issues->createMany($project, $request->user(), $request->validate($rules->rules(), $rules->messages())['issues']);

        return $this->result(
            "Created {$created->count()} ".Str::plural('issue', $created->count()).': '.$created->pluck('key')->implode(', ').'.',
            IssueCardResource::collection($created)->resolve(),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key, e.g. WEB.')->required(),
            'issues' => $schema->array()
                ->items($schema->object(['title' => $schema->string()->max(255)->required(), ...$this->issueFields($schema)]))
                ->min(1)->max(BulkStoreIssueRequest::MAX)
                ->description('Issues to create, same fields as create_issue.')
                ->required(),
        ];
    }
}
