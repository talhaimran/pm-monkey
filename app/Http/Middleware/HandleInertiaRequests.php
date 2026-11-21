<?php

namespace App\Http\Middleware;

use App\Services\CurrentWorkspace;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Spec 7.0a: the workspace the page acts in and the switcher's list.
            'workspace' => function () use ($request) {
                $workspace = app(CurrentWorkspace::class)->id() ? app(CurrentWorkspace::class)->get() : null;

                return $workspace && $request->user() ? [
                    'id' => $workspace->id, 'name' => $workspace->name, 'slug' => $workspace->slug,
                    'role' => $request->user()->workspaceRole($workspace)?->value,
                ] : null;
            },
            'workspaces' => fn () => $request->user()?->workspaces()->orderBy('name')->get(['name', 'slug'])
                ->map(fn ($w) => ['name' => $w->name, 'slug' => $w->slug])->all() ?? [],
            'edition' => config('pm.edition'),
            'flash' => [
                'token' => fn () => $request->session()->get('token'),
                'tokenAgent' => fn () => $request->session()->get('token_agent'),
                // Spec 7.0c: what the plan-limit dialog shows after a refused action.
                'planLimit' => fn () => $request->session()->get('planLimit'),
                // Spec 7.0e: shown once, right after they are made.
                'recoveryCodes' => fn () => $request->session()->get('recoveryCodes'),
                // {message, method, url, data}: what the toast sends back when the person picks Undo (UndoService).
                'undo' => fn () => $request->session()->get('undo'),
            ],
        ];
    }
}
