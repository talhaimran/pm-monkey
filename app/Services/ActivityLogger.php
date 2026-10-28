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
        $token = $user?->currentAccessToken();
        $token = $token instanceof PersonalAccessToken ? $token : null;

        return $issue->activities()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'changes' => $changes,
            'via' => app()->bound(self::VIA_BINDING) ? app(self::VIA_BINDING) : ($token ? 'api' : 'web'),
            'token_name' => $token?->name,
        ]);
    }
}
