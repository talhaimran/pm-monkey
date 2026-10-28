<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ActivityResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\IssueService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_issue_activity')]
#[Description('Change history of an issue, newest first: who changed what, when, and via web, api or mcp.')]
#[IsReadOnly]
class GetIssueActivity extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, IssueService $issues): Response
    {
        $data = $request->validate(['issue' => 'required|string', 'page' => 'integer|min:1', 'per_page' => 'integer|min:1|max:50']);
        $issue = $issues->resolve($request->user(), $data['issue']);
        $page = $issue->activities()->with('user')->latest('id')->paginate($data['per_page'] ?? 25, page: $data['page'] ?? 1);

        return $this->paginated("activity entries for {$issue->key}", $page, ActivityResource::class);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue' => $schema->string()->description('Issue key, e.g. WEB-12.')->required(),
            'page' => $schema->integer()->min(1),
            'per_page' => $schema->integer()->min(1)->max(50),
        ];
    }
}
