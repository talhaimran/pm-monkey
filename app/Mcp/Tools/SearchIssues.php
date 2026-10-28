<?php

namespace App\Mcp\Tools;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Http\Requests\Lists\IssueListRequest;
use App\Http\Resources\IssueCardResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\IssueService;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_issues')]
#[Description('Search and list issues across your projects (or one project) with filters and sorting; paginated.')]
#[IsReadOnly]
class SearchIssues extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects, IssueService $issues): Response
    {
        $rules = new IssueListRequest;
        $params = $request->validate([...$rules->rules(), 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']], $rules->messages());
        $project = isset($params['project']) ? $projects->resolve($request->user(), $params['project']) : null;

        return $this->paginated('issues', $issues->list($request->user(), $project, $params), IssueCardResource::class);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Text in the title, or an issue key like WEB-12.'),
            'project' => $schema->string()->description('Limit to one project key; omit to search all your projects.'),
            'assignee' => $schema->string()->description('Member email, or "unassigned".'),
            'type' => $schema->string()->enum(IssueType::class),
            'priority' => $schema->string()->enum(IssuePriority::class),
            'label' => $schema->string()->description('Label name.'),
            'sprint' => $schema->string()->description('Sprint name, "active" or "backlog".'),
            'sort' => $schema->string()->enum(IssueListRequest::sorts())->description('Default -key (newest first). "-" means descending.'),
            'page' => $schema->integer()->min(1),
            'per_page' => $schema->integer()->min(1)->max(50)->description('Default 25.'),
        ];
    }
}
