<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\StoreAgentRequest;
use App\Http\Requests\Agents\UpdateAgentRequest;
use App\Models\Agent;
use App\Services\AgentService;
use App\Services\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Settings → Agents (spec 8, A1): thin over AgentService. Web only, for people; an agent's token never reaches these routes. */
class AgentController extends Controller
{
    public function __construct(private AgentService $agents, private CurrentWorkspace $current) {}

    public function index(Request $request): Response
    {
        $workspace = $this->current->get();
        $all = collect($this->agents->listFor($request->user(), $workspace));

        return Inertia::render('Agents/Index', [
            'agents' => $all->where('mine', true)->values(),
            // Owners and Admins only: listFor returns nothing else of other people's.
            'others' => $all->where('mine', false)->values(),
            'projects' => $request->user()->accessibleProjects()->orderBy('key')->get(['projects.id', 'key', 'name'])
                ->map(fn ($p) => ['key' => $p->key, 'name' => $p->name])->all(),
            'agentsEnabled' => (bool) $workspace->setting('agents_enabled'),
            'can' => ['manageAll' => $all->contains('mine', false)],
            'endpoints' => ['api' => url('/api/v1'), 'docs' => url('/docs/api'), 'mcp' => url('/mcp')],
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        if (! $this->current->get()->setting('agents_enabled')) {
            throw ValidationException::withMessages(['name' => 'Agents are turned off in '.$this->current->get()->name.'. An Owner or Admin can turn them on in Settings → Security.']);
        }
        [$agent, $token] = $this->agents->create($request->user(), $request->validated());

        // Shown once; only the hash is stored.
        return back()->with(['token' => $token, 'token_agent' => $agent->name]);
    }

    public function update(UpdateAgentRequest $request, int $agent): RedirectResponse
    {
        $agent = $this->find($agent);
        Gate::authorize('update', $agent);
        $this->agents->update($agent, $request->user(), $request->validated());

        return back();
    }

    public function pause(Request $request, int $agent): RedirectResponse
    {
        $this->agents->pause($this->authorized($agent, 'pause'), $request->user());

        return back();
    }

    public function resume(Request $request, int $agent): RedirectResponse
    {
        $this->agents->resume($this->authorized($agent, 'resume'), $request->user());

        return back();
    }

    public function revoke(Request $request, int $agent): RedirectResponse
    {
        $this->agents->revoke($this->authorized($agent, 'revoke'), $request->user());

        return back();
    }

    public function regenerate(Request $request, int $agent): RedirectResponse
    {
        $agent = $this->authorized($agent, 'regenerate');

        return back()->with(['token' => $this->agents->regenerateToken($agent, $request->user()), 'token_agent' => $agent->name]);
    }

    private function authorized(int $id, string $ability): Agent
    {
        $agent = $this->find($id);
        Gate::authorize($ability, $agent);

        return $agent;
    }

    /** This workspace's agents only: another workspace's id is a 404. */
    private function find(int $id): Agent
    {
        return Agent::with(['owner', 'workspace'])->where('workspace_id', $this->current->id())->findOrFail($id);
    }
}
