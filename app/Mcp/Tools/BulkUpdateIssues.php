<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Issues\BulkStoreIssueRequest;
use App\Http\Requests\Issues\BulkUpdateIssueRequest;
use App\Http\Resources\ApprovalRequestResource;
use App\Http\Resources\IssueCardResource;
use App\Mcp\Tools\Concerns\IssueFields;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\IssueService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('bulk_update_issues')]
#[Description('Update up to 50 issues by key in one call; all or nothing (errors name the item, e.g. [updates.2]).')]
class BulkUpdateIssues extends Tool
{
    use IssueFields, RespondsWithJson;

    public function handle(Request $request, IssueService $issues): Response
    {
        $rules = new BulkUpdateIssueRequest;
        $updated = $issues->updateMany($request->user(), $request->validate($rules->rules(), $rules->messages())['updates']);
        $summary = "Updated {$updated->count()} ".Str::plural('issue', $updated->count()).': '.$updated->pluck('key')->implode(', ').'.';
        $cards = IssueCardResource::collection($updated)->resolve();
        $held = $updated->pluck('heldApproval')->filter()->values();

        return $held->isEmpty() ? $this->result($summary, $cards) : $this->result(
            $summary.' '.$held->map(fn ($approval) => ApprovalRequestResource::sentence($approval))->implode(' '),
            ['status' => 'pending_approval', 'approval_requests' => ApprovalRequestResource::collection($held)->resolve(), 'data' => $cards],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'updates' => $schema->array()
                ->items($schema->object([
                    'key' => $schema->string()->description('Issue key, e.g. WEB-12.')->required(),
                    'title' => $schema->string()->max(255),
                    ...$this->issueFields($schema),
                    'reason' => $schema->string()->max(200)->description('Why, when asking a person to approve a move to Done.'),
                ]))
                ->min(1)->max(BulkStoreIssueRequest::MAX)
                ->description('Each item names an issue key plus only the fields to change.')
                ->required(),
        ];
    }
}
