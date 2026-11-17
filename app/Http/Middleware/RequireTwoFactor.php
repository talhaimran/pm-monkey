<?php

namespace App\Http\Middleware;

use App\Services\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spec 7.0e: a workspace with require_2fa sends members without two-factor to set it up. Runs after ResolveWorkspace
 * on /{workspace} pages only, so the profile, sign-out and other workspaces stay reachable. API tokens are unaffected.
 */
class RequireTwoFactor
{
    public function __construct(private CurrentWorkspace $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $this->current->get();
        if ($workspace->setting('require_2fa') && ! $request->user()->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('profile.edit')->with('status', "{$workspace->name} requires two-factor authentication. Turn it on below to continue.");
        }

        return $next($request);
    }
}
