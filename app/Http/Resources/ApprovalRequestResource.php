<?php

namespace App\Http\Resources;

use App\Models\ApprovalRequest;
use App\Models\Column;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An approval request (A2). Needs issue.project, issue.column and agent.owner loaded. `from` is where the issue was
 * when the agent asked, `now` where it is; `moved` says it moved since, so approving needs a second confirm.
 *
 * @mixin ApprovalRequest
 */
class ApprovalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $issue = $this->issue;
        $moved = $issue->column_id !== $this->action['from_column_id'];

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'issue' => $issue->key,
            'title' => $issue->title,
            // shortcut: one query per moved request for the old column's name; store it in `action` if lists grow.
            'from' => $moved ? Column::whereKey($this->action['from_column_id'])->value('name') : $issue->column->name,
            'to' => $this->action['to_column'],
            'now' => $issue->column->name,
            'moved' => $moved,
            'reason' => $this->action['reason'] ?? null,
            'agent' => IssueResource::agent($this->agent),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }

    /** REST 202 for a move held for a person: the request and the issue as it is now (other fields may have changed, A-L7). */
    public static function pending(ApprovalRequest $approval, JsonResource $issue): JsonResponse
    {
        return response()->json(['status' => 'pending_approval', 'approval_request' => self::make($approval)->resolve(), 'issue' => $issue->resolve()], 202);
    }

    /** "WEB-12 needs a person's approval to move to Done (request 41, expires …)." for MCP summaries. */
    public static function sentence(ApprovalRequest $approval): string
    {
        return "{$approval->issue->key} needs a person's approval to move to {$approval->action['to_column']} (request {$approval->id}, expires {$approval->expires_at->toIso8601String()}).";
    }
}
