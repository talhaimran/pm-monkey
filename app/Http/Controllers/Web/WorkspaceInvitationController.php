<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The link in the invitation email. Holding the token proves the mailbox, so no verified email is needed. */
class WorkspaceInvitationController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function show(Request $request, string $token): Response
    {
        $invitation = $this->invitations->findByToken($token);
        $error = $this->invitations->refusal($invitation, $request->user());
        if (! $request->user()) {
            // After logging in, come back here to accept; a provider sign-up can join with the token.
            $request->session()->put(['url.intended' => $request->fullUrl(), 'invitation.token' => $token]);
        }

        return Inertia::render('Invitations/Show', [
            'token' => $token,
            'error' => $error,
            'signedIn' => (bool) $request->user(),
            // Details only for a link that still works: a dead token reveals nothing.
            'invitation' => $invitation && $error !== InvitationService::GONE && $error !== InvitationService::EXPIRED ? [
                'workspace' => $invitation->workspace->name,
                'project' => $invitation->project?->key,
                'projectName' => $invitation->project?->name,
                'role' => $invitation->project_role?->value ?? $invitation->role->value,
                'email' => $invitation->email,
                'inviter' => $invitation->inviter?->name,
            ] : null,
        ]);
    }

    public function join(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->findByToken($token);
        if ($reason = $this->invitations->refusal($invitation, $request->user())) {
            return back()->withErrors(['invitation' => $reason]);
        }

        $user = $request->user();
        $workspace = $this->invitations->accept($invitation, $user);
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect(self::landing($workspace, $invitation->project));
    }

    /** Where someone lands after joining: the project's board, else the workspace's dashboard. */
    public static function landing(Workspace $workspace, ?Project $project): string
    {
        return $project
            ? route('projects.show', ['workspace' => $workspace->slug, 'project' => $project->key], false)
            : route('dashboard', ['workspace' => $workspace->slug], false);
    }
}
