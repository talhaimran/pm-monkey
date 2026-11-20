<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Issue;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class ActivityLogger
{
    /** Container key set by the MCP server so activity records "mcp" instead of "api". */
    public const VIA_BINDING = 'pm.via';

    /** @param array<string, array{from: mixed, to: mixed}>|null $changes */
    public function log(Issue $issue, ?User $user, string $action, ?array $changes = null): Activity
    {
        return $issue->activities()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'changes' => $changes,
            'via' => self::via($user),
            'token_name' => self::token($user)?->name,
            'agent_id' => $user?->agent()?->id,
        ]);
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
