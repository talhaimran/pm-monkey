<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProjectInvitation;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    public function accept(Request $request, ProjectInvitation $invitation, MembershipService $memberships): RedirectResponse
    {
        Gate::authorize('accept', $invitation);
        $key = $invitation->load('project')->project->key;
        $memberships->accept($invitation, $request->user());

        return redirect()->route('projects.settings', $key);
    }
}
