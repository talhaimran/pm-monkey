<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ApprovalRequest;
use App\Models\Issue;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class ActivityLogger
{
    /** Container key set by the MCP server so activity records "mcp" instead of "api". */
    public const VIA_BINDING = 'pm.via';

    /** Container key set while a person decides an approval request, so the entry links the agent and the request. */
    private const APPROVAL_BINDING = 'pm.approval';

    /** @param array<string, array{from: mixed, to: mixed}>|null $changes */
    public function log(Issue $issue, ?User $user, string $action, ?array $changes = null): Activity
    {
        $approval = app()->bound(self::APPROVAL_BINDING) ? app(self::APPROVAL_BINDING) : null;
        if ($approval) {
            // shortcut: the request link lives in `changes` (no activities column in step A); move it to a column with agent_run_id (step B).
            $changes = [...($changes ?? []), 'approval_request' => ['id' => $approval->id, 'to' => $approval->action['to_column']]];
        }

        return $issue->activities()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'changes' => $changes,
            'via' => self::via($user),
            'token_name' => self::token($user)?->name,
            'agent_id' => $approval?->agent_id ?? $user?->agent()?->id,
        ]);
    }

    /** Run $decide with its activity entries made by the decider but linked to the request and the agent that asked (A2). */
    public static function forApproval(ApprovalRequest $approval, callable $decide): mixed
    {
        app()->instance(self::APPROVAL_BINDING, $approval);
        try {
            return $decide();
        } finally {
            app()->forgetInstance(self::APPROVAL_BINDING);
        }
    }

    /** How the change came in: mcp (set by the MCP server), api (a personal access token), else web. AuditLogger uses it too. */
    public static function via(?User $user): string
    {
        return app()->bound(self::VIA_BINDING) ? app(self::VIA_BINDING) : (self::token($user) ? 'api' : 'web');
    }

    public static function token(?User $user): ?PersonalAccessToken
    {
        $token = $user?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token : null;
    }
}
