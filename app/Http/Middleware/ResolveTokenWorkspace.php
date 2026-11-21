<?php

namespace App\Http\Middleware;

use App\Services\AgentService;
use App\Services\CurrentWorkspace;
use App\Services\WorkspaceService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * API and MCP over HTTP act in the token's workspace (spec 7.0a). A token whose owner has left that workspace has its
 * agent revoked and the request is refused; a paused agent gets agent_paused. Session-authenticated calls use the
 * current workspace.
 */
class ResolveTokenWorkspace
{
    public function __construct(private CurrentWorkspace $current, private WorkspaceService $workspaces, private AgentService $agents) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && $token->exists) {
            // Every token is an agent's since step A; one without (made outside AgentService) is never accepted.
            if (! $token->agent_id) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            $workspace = $token->workspace_id ? $user->workspaces()->find($token->workspace_id) : null;

            if (! $workspace) {
                $this->agents->revoke($user->agent(), null, 'owner_left');

                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            if ($workspace->deletion_scheduled_at) {
                return response()->json(['message' => $this->workspaces->lockedMessage($workspace, $user)], 403);
            }
            $this->agents->ensureUsable($user->agent());
        } else {
            $workspace = $user ? $this->workspaces->currentFor($user) : null;
        }

        $workspace ? $this->current->set($workspace) : $this->current->forget();

        return $next($request);
    }
}
