<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\InviteMemberRequest;
use App\Http\Requests\Projects\UpdateMemberRoleRequest;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectMemberController extends Controller
{
    public function __construct(private MembershipService $memberships) {}

    public function invite(InviteMemberRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $this->memberships->invite($project, $request->user(), $request->validated('email'), ProjectRole::from($request->validated('role')));

        return back();
    }

    public function revoke(Project $project, ProjectInvitation $invitation): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $this->memberships->revoke($invitation);

        return back();
    }

    public function update(UpdateMemberRoleRequest $request, Project $project, User $user): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $member = $this->memberships->resolveMember($project, $user->email);
        $this->memberships->changeRole($project, $member, ProjectRole::from($request->validated('role')));

        return back();
    }

    public function destroy(Request $request, Project $project, User $user): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $this->memberships->remove($project, $this->memberships->resolveMember($project, $user->email));

        return back();
    }
}
