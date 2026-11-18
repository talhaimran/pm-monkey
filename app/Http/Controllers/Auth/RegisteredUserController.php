<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\WorkspaceInvitationController;
use App\Models\User;
use App\Models\Workspace;
use App\Services\InvitationService;
use App\Services\SocialSignInService;
use App\Services\WorkspaceService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Spec 7.0a sign-up: with an invitation link you join that workspace; on Cloud you otherwise create one;
 * on self-hosted the first person creates the install's workspace and everyone after needs an invitation.
 */
class RegisteredUserController extends Controller
{
    public function __construct(private WorkspaceService $workspaces, private InvitationService $invitations) {}

    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        $invitation = $request->filled('invitation') ? $this->invitations->findByToken((string) $request->query('invitation')) : null;
        $invitation = $this->invitations->refusal($invitation, null) ? null : $invitation;

        return Inertia::render('Auth/Register', [
            'invitation' => $invitation ? ['token' => $request->query('invitation'), 'email' => $invitation->email, 'workspace' => $invitation->workspace->name] : null,
            'createsWorkspace' => ! $invitation && config('pm.edition') === 'cloud',
            'closedMessage' => $invitation ? null : $this->closedMessage(),
            'socialProviders' => app(SocialSignInService::class)->configured(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $cloud = config('pm.edition') === 'cloud';
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'invitation' => 'nullable|string|max:64',
            'workspace_name' => [$cloud && ! $request->filled('invitation') ? 'required' : 'nullable', 'string', 'max:100'],
            'workspace_slug' => 'nullable|string|max:40',
        ]);

        $invitation = null;
        if (filled($data['invitation'] ?? null)) {
            $invitation = $this->invitations->findByToken($data['invitation']);
            if ($reason = $this->invitations->refusal($invitation, null)) {
                throw ValidationException::withMessages(['invitation' => $reason]);
            }
            if ($invitation->email !== $data['email']) {
                throw ValidationException::withMessages(['email' => InvitationService::OTHER_EMAIL]);
            }
            $this->invitations->ensureRoom($invitation);
        } elseif ($closed = $this->closedMessage()) {
            throw ValidationException::withMessages(['email' => $closed]);
        }

        [$user, $landing] = DB::transaction(function () use ($data, $invitation, $cloud) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            if ($invitation) {
                // The token came to this mailbox, so the address is proven.
                $user->markEmailAsVerified();

                return [$user, WorkspaceInvitationController::landing($this->invitations->accept($invitation, $user), $invitation->project)];
            }

            try {
                $cloud
                    ? $this->workspaces->create($user, $data['workspace_name'], $data['workspace_slug'] ?? null)
                    : $this->workspaces->create($user, config('app.name') ?: 'Workspace');
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ["workspace_{$k}" => $m])->all());
            }

            return [$user, '/dashboard'];
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->put('auth.method', 'password');

        return redirect($landing);
    }

    /** Self-hosted after the first user: registration needs an invitation. */
    private function closedMessage(): ?string
    {
        return $this->workspaces->canCreate() ? null : 'Ask an admin of '.Workspace::value('name').' to invite you.';
    }
}
