<?php

namespace App\Http\Middleware;

use App\Services\CurrentWorkspace;
use App\Services\WorkspaceService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * API and MCP over HTTP act in the token's workspace (spec 7.0a). A token whose owner has left that
 * workspace is deleted and the request is refused. Session-authenticated calls use the current workspace.
 */
class ResolveTokenWorkspace
{
    public function __construct(private CurrentWorkspace $current, private WorkspaceService $workspaces) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && $token->exists) {
            $workspace = $token->workspace_id ? $user->workspaces()->find($token->workspace_id) : null;

            if (! $workspace) {
                $token->delete();

                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        } else {
            $workspace = $user ? $this->workspaces->currentFor($user) : null;
        }

        $workspace ? $this->current->set($workspace) : $this->current->forget();

        return $next($request);
    }
}
