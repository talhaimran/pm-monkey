<?php

namespace App\Http\Middleware;

use App\Services\CurrentWorkspace;
use App\Services\WorkspaceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web requests act in the signed-in user's current workspace.
 * shortcut: no URL prefix yet, replaced by ResolveWorkspace (/{workspace}) in step 0 PR 2.
 */
class UseCurrentWorkspace
{
    public function __construct(private CurrentWorkspace $current, private WorkspaceService $workspaces) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $workspace = $this->workspaces->currentFor($user);
            $workspace ? $this->current->set($workspace) : $this->current->forget();
        }

        return $next($request);
    }
}
