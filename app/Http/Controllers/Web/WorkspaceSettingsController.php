<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\DestroyWorkspaceRequest;
use App\Http\Requests\Workspaces\InviteToWorkspaceRequest;
use App\Http\Requests\Workspaces\RemoveWorkspaceMemberRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceMemberRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\CurrentWorkspace;
use App\Services\InvitationService;
use App\Services\SocialSignInService;
use App\Services\WorkspaceMemberService;
use App\Services\WorkspaceService;
use App\Services\WorkspaceUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** /{workspace}/settings/general and /members (spec 7.0b). Web only: tokens never reach settings. */
class WorkspaceSettingsController extends Controller
{
    public function __construct(
        private CurrentWorkspace $current,
        private WorkspaceService $workspaces,
        private WorkspaceMemberService $members,
        private InvitationService $invitations,
    ) {}

    public function general(Request $request): Response
    {
        $workspace = $this->current->get();
        Gate::authorize('update', $workspace);

        return Inertia::render('Workspaces/Settings/General', [
            'settings' => [
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'members_can_create_projects' => (bool) $workspace->setting('members_can_create_projects'),
            ],
            'deletionScheduledAt' => $workspace->deletion_scheduled_at,
            'can' => ['delete' => $request->user()->can('delete', $workspace)],
        ]);
    }

    public function update(UpdateWorkspaceRequest $request): RedirectResponse
    {
        $workspace = $this->current->get();
        Gate::authorize('update', $workspace);
        $data = $request->validated();

        $this->workspaces->rename($workspace, $data['name'], $data['slug']);
        $this->workspaces->updateSettings($workspace, ['members_can_create_projects' => (bool) $data['members_can_create_projects']]);

        return redirect(route('settings.general', ['workspace' => $workspace->slug], false));
    }

    public function destroy(DestroyWorkspaceRequest $request): RedirectResponse
    {
        $workspace = $this->current->get();
        Gate::authorize('delete', $workspace);
        $this->workspaces->scheduleDeletion($workspace, $request->user(), $request->validated('confirm_slug'));

        return redirect(route('dashboard', ['workspace' => $workspace->slug], false));
    }

    public function security(): Response
    {
        $workspace = $this->current->get();
        Gate::authorize('manageSecurity', $workspace);

        return Inertia::render('Workspaces/Settings/Security', [
            'require2fa' => (bool) $workspace->setting('require_2fa'),
            'members' => $workspace->members()->count(),
            'membersWithout2fa' => $workspace->members()->whereNull('two_factor_confirmed_at')->count(),
            'signInMethods' => array_values($workspace->setting('sign_in_methods')),
            'configured' => app(SocialSignInService::class)->configured(),
        ]);
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $workspace = $this->current->get();
        Gate::authorize('manageSecurity', $workspace);
        $on = $request->validate(['require_2fa' => ['required', 'boolean']])['require_2fa'];
        // Whoever turns it on would otherwise be sent to setup on their next page.
        if ($on && ! $request->user()->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages(['require_2fa' => 'Turn on two-factor authentication for your own account first, or you would be locked out.']);
        }
        $this->workspaces->updateSettings($workspace, ['require_2fa' => (bool) $on]);

        return back();
    }

    /** Spec 7.0f: which sign-in methods may enter; at least one, and never the one this session used. */
    public function updateSignInMethods(Request $request): RedirectResponse
    {
        $workspace = $this->current->get();
        Gate::authorize('manageSecurity', $workspace);
        $all = ['password', ...array_keys(SocialSignInService::PROVIDERS)];
        $methods = $request->validate([
            'sign_in_methods' => ['required', 'array', 'min:1'],
            'sign_in_methods.*' => ['string', Rule::in($all)],
        ], ['sign_in_methods.required' => 'Keep at least one way to sign in.', 'sign_in_methods.min' => 'Keep at least one way to sign in.'])['sign_in_methods'];
        $mine = $request->session()->get('auth.method', 'password');
        if (! in_array($mine, $methods, true)) {
            $how = $mine === 'password' ? 'a password' : SocialSignInService::PROVIDERS[$mine];
            throw ValidationException::withMessages(['sign_in_methods' => "You signed in with {$how}. Keep it on, or sign in another way first."]);
        }
        $this->workspaces->updateSettings($workspace, ['sign_in_methods' => array_values(array_intersect($all, $methods))]);

        return back();
    }

    public function members(Request $request, WorkspaceUsage $usage): Response
    {
        $workspace = $this->current->get();
        Gate::authorize('manageMembers', $workspace);

        $members = $workspace->members()
            ->with(['projects' => fn ($q) => $q->where('projects.workspace_id', $workspace->id)->orderBy('key')])
            ->orderBy('name')->get();
        $billed = $usage->people($workspace, withInvitations: false);
        $projects = $workspace->projects()->orderBy('key')
            ->with(['members' => fn ($q) => $q->where('project_user.role', ProjectRole::Admin->value)])->get();

        return Inertia::render('Workspaces/Settings/Members', [
            'members' => $members->map(fn (User $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'role' => $m->pivot->role,
                'billed' => $billed[strtolower($m->email)],
                'projects' => $m->projects->map(fn ($p) => ['key' => $p->key, 'role' => $p->pivot->role])->all(),
                'last_active_at' => $m->pivot->last_active_at,
                'two_factor' => $m->two_factor_confirmed_at !== null,
            ])->all(),
            'invitations' => $workspace->invitations()->with('project', 'inviter')->orderBy('email')->get()
                ->map(fn (WorkspaceInvitation $i) => [
                    'id' => $i->id,
                    'email' => $i->email,
                    'role' => $i->role->value,
                    'project' => $i->project?->key,
                    'project_role' => $i->project_role?->value,
                    'invited_by' => $i->inviter?->name,
                    'expires_at' => $i->expires_at,
                ])->all(),
            'projects' => $projects->pluck('key')->all(),
            // Who needs a new project admin picked before they can be removed.
            'soleAdminOf' => (object) $projects->filter(fn ($p) => $p->members->count() === 1)
                ->groupBy(fn ($p) => $p->members->first()->id)->map(fn ($ps) => $ps->pluck('key')->values()->all())->all(),
            'can' => ['manageOwners' => $request->user()->can('manageOwners', $workspace)],
        ]);
    }

    public function invite(InviteToWorkspaceRequest $request): RedirectResponse
    {
        $workspace = $this->current->get();
        Gate::authorize('manageMembers', $workspace);
        $data = $request->validated();

        $project = null;
        if (filled($data['project'] ?? null)) {
            $project = $workspace->projects()->where('key', strtoupper($data['project']))->first()
                ?? throw ValidationException::withMessages(['project' => "No project {$data['project']} in {$workspace->name}. Projects: ".$workspace->projects()->orderBy('key')->pluck('key')->implode(', ').'.']);
        }
        $this->invitations->invite(
            $workspace, $request->user(), $data['email'], WorkspaceRole::from($data['role']),
            $project, $project ? ProjectRole::from($data['project_role'] ?? ProjectRole::Member->value) : null,
        );

        return back();
    }

    public function resend(int $invitation): RedirectResponse
    {
        $workspace = $this->current->get();
        Gate::authorize('manageMembers', $workspace);
        $this->invitations->resend($workspace->invitations()->findOrFail($invitation));

        return back();
    }

    public function revoke(Request $request, int $invitation): RedirectResponse
    {
        $workspace = $this->current->get();
        Gate::authorize('manageMembers', $workspace);
        $this->invitations->revoke($workspace->invitations()->findOrFail($invitation), $request->user());

        return back();
    }

    public function updateMember(UpdateWorkspaceMemberRequest $request, int $user): RedirectResponse
    {
        $workspace = $this->current->get();
        Gate::authorize('manageMembers', $workspace);
        $this->members->changeRole($workspace, $request->user(), $this->member($workspace, $user), WorkspaceRole::from($request->validated('role')));

        return back();
    }

    public function removeMember(RemoveWorkspaceMemberRequest $request, int $user): RedirectResponse
    {
        $workspace = $this->current->get();
        Gate::authorize('manageMembers', $workspace);
        $this->members->remove($workspace, $request->user(), $this->member($workspace, $user), array_change_key_case($request->validated('new_admins') ?? [], CASE_UPPER));

        return back();
    }

    private function member(Workspace $workspace, int $id): User
    {
        return $workspace->members()->findOrFail($id);
    }
}
