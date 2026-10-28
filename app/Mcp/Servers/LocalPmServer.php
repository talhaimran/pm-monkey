<?php

namespace App\Mcp\Servers;

use App\Models\User;
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

        $token->forceFill(['last_used_at' => now()])->save();
        $user = $token->tokenable->withAccessToken($token);
        Auth::setUser($user);

        return $user;
    }
}
