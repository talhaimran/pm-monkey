<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(private WorkspaceService $workspaces) {}

    public function create(): Response
    {
        abort_unless(config('pm.edition') === 'cloud', 404);

        return Inertia::render('Workspaces/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('pm.edition') === 'cloud', 404);
        $data = $request->validate(['name' => 'required|string|max:100', 'slug' => 'nullable|string|max:40']);

        $workspace = $this->workspaces->create($request->user(), $data['name'], $data['slug'] ?? null);
        $request->user()->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect(route('dashboard', ['workspace' => $workspace->slug], false));
    }

    /** D11: signed in, but in no workspace. */
    public function noAccess(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($workspace = $this->workspaces->currentFor($user)) {
            return redirect(route('dashboard', ['workspace' => $workspace->slug], false));
        }

        $left = $user->current_workspace_id ? Workspace::find($user->current_workspace_id) : null;
        if ($left?->deletion_scheduled_at && $user->workspaceRole($left)) {
            return self::scheduled($left, $user);
        }

        return Inertia::render('Workspaces/NoAccess', [
            'message' => $left
                ? "You are no longer a member of {$left->name}. Ask an admin to invite you again."
                : 'You are not a member of any workspace yet. Ask an admin to invite you.',
            'canCreate' => config('pm.edition') === 'cloud',
            'restoreUrl' => null,
        ]);
    }

    /** D2: every page of a workspace scheduled for deletion; its Owner gets Undo. */
    public static function scheduled(Workspace $workspace, User $user): Response
    {
        $owner = $user->can('delete', $workspace);

        return Inertia::render('Workspaces/NoAccess', [
            'message' => app(WorkspaceService::class)->lockedMessage($workspace, $user),
            'canCreate' => config('pm.edition') === 'cloud',
            'restoreUrl' => $owner ? route('workspaces.restore', $workspace->slug) : null,
        ]);
    }

    public function restore(Request $request, string $slug): RedirectResponse
    {
        $workspace = $request->user()->workspaces()->where('slug', $slug)->firstOrFail();
        Gate::authorize('delete', $workspace);
        $this->workspaces->cancelDeletion($workspace, $request->user());
        $request->user()->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect(route('dashboard', ['workspace' => $workspace->slug], false));
    }

    /** E3: an old unprefixed URL goes to the same page in the current workspace, query string kept. */
    public function legacy(Request $request, string $legacy): RedirectResponse
    {
        $workspace = $this->workspaces->currentFor($request->user());
        if (! $workspace) {
            return redirect()->route('workspaces.no-access');
        }
        $query = $request->getQueryString();

        return redirect("/{$workspace->slug}/{$legacy}".($query ? "?{$query}" : ''));
    }
}
