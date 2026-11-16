<?php

namespace App\Http\Middleware;

use App\Services\CurrentWorkspace;
use App\Services\WorkspaceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every web request starts in the signed-in user's current workspace, so pages outside /{workspace} (profile,
 * invitations) can still link into it. ResolveWorkspace then takes the slug from the URL where there is one.
 */
class UseCurrentWorkspace
{
    public function __construct(private CurrentWorkspace $current, private WorkspaceService $workspaces) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $workspace = $this->workspaces->currentFor($user);
            $workspace ? $this->current->set($workspace) : $this->current->forget();
            if ($workspace) {
                URL::defaults(['workspace' => $workspace->slug]);
            }
        }

        return $next($request);
    }
}
