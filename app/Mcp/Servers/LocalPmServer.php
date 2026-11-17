<?php

namespace App\Mcp\Servers;

use App\Models\User;
use App\Services\CurrentWorkspace;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

/**
 * stdio variant of PmServer. There is no HTTP request to authenticate, so the process acts as the owner
 * of the API token in PM_MCP_TOKEN, keeping its read-only/project-scope limits.
 */
class LocalPmServer extends PmServer
{
    protected function boot(): void
    {
        parent::boot();

        static::authenticate(config('pm.mcp_token'));
    }

    /** Log in as the token's owner for this process; fails fast so misconfiguration is obvious in client logs. */
    public static function authenticate(?string $plainToken): User
    {
        $token = $plainToken ? PersonalAccessToken::findToken($plainToken)?->loadMissing('tokenable') : null;

        if (! $token || ! $token->tokenable instanceof User || $token->expires_at?->isPast()) {
            throw new RuntimeException(
                'PM_MCP_TOKEN is missing or invalid. Create an API token at /settings/tokens and set PM_MCP_TOKEN in your MCP client config.'
            );
        }

        // Same rule as ResolveTokenWorkspace: the token acts in its workspace, and dies with the membership.
        $workspace = $token->workspace_id ? $token->tokenable->workspaces()->find($token->workspace_id) : null;
        if (! $workspace) {
            $token->delete();

            throw new RuntimeException('PM_MCP_TOKEN belongs to a workspace you are no longer a member of, so it was deleted. Create a new API token.');
        }

        if ($workspace->deletion_scheduled_at) {
            throw new RuntimeException(app(WorkspaceService::class)->lockedMessage($workspace, $token->tokenable));
        }

        $token->forceFill(['last_used_at' => now()])->save();
        app(CurrentWorkspace::class)->set($workspace);
        $user = $token->tokenable->withAccessToken($token);
        Auth::setUser($user);

        return $user;
    }
}
