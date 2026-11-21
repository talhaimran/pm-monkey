<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\TokenRequest;
use App\Models\Agent;
use App\Services\AgentService;
use App\Services\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The tokens page, now over the requester's own agents; Settings → Agents (step A, Task 2) replaces it. */
class TokenController extends Controller
{
    public function __construct(private AgentService $agents, private CurrentWorkspace $current) {}

    public function index(Request $request): Response
    {
        $mine = collect($this->agents->listFor($request->user(), $this->current->get()))->where('mine', true)->values();

        return Inertia::render('Tokens/Index', [
            'tokens' => $mine->map(fn (array $agent) => [
                'id' => $agent['id'],
                'name' => $agent['name'],
                'access' => $agent['level'] === 'read' ? 'read' : 'write',
                'projects' => $agent['projects'],
                'last_used_at' => $agent['last_used_at'],
                'expires_at' => $agent['expires_at'],
                'created_at' => $agent['created_at'],
            ])->all(),
            'projects' => $request->user()->accessibleProjects()->orderBy('key')->get(['projects.id', 'key', 'name'])
                ->map(fn ($p) => ['key' => $p->key, 'name' => $p->name])->all(),
            'endpoints' => ['api' => url('/api/v1'), 'docs' => url('/docs/api'), 'mcp' => url('/mcp')],
        ]);
    }

    public function store(TokenRequest $request): RedirectResponse
    {
        [, $token] = $this->agents->create($request->user(), [
            'name' => $request->validated('name'),
            'level' => $request->validated('access'),
            'projects' => $request->validated('projects'),
            'expires_in_days' => $request->validated('expires_in_days'),
        ]);

        // Shown once; only the hash is stored.
        return back()->with('token', $token);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $agent = Agent::where('workspace_id', $this->current->id())->where('owner_user_id', $request->user()->id)->findOrFail($token);
        $this->agents->revoke($agent, $request->user());

        return back();
    }
}
