<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceInvitation;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    /** Accept from the pending list in the app (the emailed link goes through WorkspaceInvitationController). */
    public function accept(Request $request, WorkspaceInvitation $invitation, InvitationService $invitations): RedirectResponse
    {
        Gate::authorize('accept', $invitation);
        $project = $invitation->load('project')->project;
        $workspace = $invitations->accept($invitation, $request->user());

        return redirect(WorkspaceInvitationController::landing($workspace, $project));
    }
}
