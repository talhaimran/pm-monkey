<?php

namespace App\Mcp\Servers;

use App\Models\User;
use App\Services\AgentService;
use App\Services\CurrentWorkspace;
use App\Services\WorkspaceService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

/**
 * stdio variant of PmServer. There is no HTTP request to authenticate, so the process acts as the agent whose token
 * is in PM_MCP_TOKEN (its owner, within the agent's level and projects). PmServer re-checks the agent on every call.
 */
class LocalPmServer extends PmServer
{
    protected function boot(): void
    {
        parent::boot();

        static::authenticate(config('pm.mcp_token'));
    }

    /** Log in as the token's owner, acting as its agent; fails fast so misconfiguration is obvious in client logs. */
    public static function authenticate(?string $plainToken): User
    {
        $token = $plainToken ? PersonalAccessToken::findToken($plainToken)?->loadMissing('tokenable') : null;

        if (! $token || ! $token->tokenable instanceof User || ! $token->agent_id || $token->expires_at?->isPast()) {
            throw new RuntimeException(
                'PM_MCP_TOKEN is missing or invalid. Create an API token at /settings/tokens and set PM_MCP_TOKEN in your MCP client config.'
            );
        }
        $user = $token->tokenable->withAccessToken($token);
        $agents = app(AgentService::class);

        // Same rules as ResolveTokenWorkspace: the agent acts in its workspace, and is revoked when its owner leaves.
        $workspace = $token->workspace_id ? $user->workspaces()->find($token->workspace_id) : null;
        if (! $workspace) {
            $agents->revoke($user->agent(), null, 'owner_left');

            throw new RuntimeException('PM_MCP_TOKEN belongs to a workspace you are no longer a member of, so its agent was revoked. Create a new API token.');
        }

        if ($workspace->deletion_scheduled_at) {
            throw new RuntimeException(app(WorkspaceService::class)->lockedMessage($workspace, $user));
        }
        try {
            $agents->ensureUsable($user->agent());
        } catch (HttpResponseException $e) {
            throw new RuntimeException($e->getResponse()->getData()->message);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        app(CurrentWorkspace::class)->set($workspace);
        Auth::setUser($user);

        return $user;
    }
}
