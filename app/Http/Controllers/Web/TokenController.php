<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\TokenRequest;
use App\Services\TokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TokenController extends Controller
{
    public function __construct(private TokenService $tokens) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Tokens/Index', [
            'tokens' => $this->tokens->list($request->user()),
            'projects' => $request->user()->projects()->orderBy('key')->get(['projects.id', 'key', 'name'])
                ->map(fn ($p) => ['key' => $p->key, 'name' => $p->name])->all(),
            'endpoints' => ['api' => url('/api/v1'), 'docs' => url('/docs/api'), 'mcp' => url('/mcp')],
        ]);
    }

    public function store(TokenRequest $request): RedirectResponse
    {
        $token = $this->tokens->create(
            $request->user(),
            $request->validated('name'),
            $request->validated('access') === 'write',
            $request->validated('projects'),
            $request->validated('expires_in_days'),
        );

        // Shown once; only the hash is stored.
        return back()->with('token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $this->tokens->revoke($request->user(), $token);

        return back();
    }
}
