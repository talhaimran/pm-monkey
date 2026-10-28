<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\InviteMemberRequest;
use App\Http\Requests\Projects\UpdateMemberRoleRequest;
use App\Http\Resources\MemberResource;
use App\Http\Resources\ProjectInvitationResource;
use App\Services\MembershipService;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProjectMemberController extends Controller
{
    public function __construct(private ProjectService $projects, private MembershipService $memberships) {}

    /** List members and pending invitations. */
    public function index(Request $request, string $key): JsonResponse
    {
        $project = $this->projects->resolve($request->user(), $key)->load(['members', 'invitations']);

        return response()->json(['data' => [
            'members' => MemberResource::collection($project->members),
            'invitations' => ProjectInvitationResource::collection($project->invitations),
        ]]);
    }

    /** Invite someone by email with a role (admins). Re-inviting updates the role. */
    public function invite(InviteMemberRequest $request, string $key): ProjectInvitationResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('manage', $project);

        return new ProjectInvitationResource($this->memberships->invite(
            $project, $request->user(), $request->validated('email'), ProjectRole::from($request->validated('role')),
        ));
    }

    /** Change a member's role, addressed by email (admins). */
    public function update(UpdateMemberRoleRequest $request, string $key, string $email): MemberResource
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('manage', $project);
        $member = $this->memberships->resolveMember($project, $email);
        $this->memberships->changeRole($project, $member, ProjectRole::from($request->validated('role')));

        return new MemberResource($this->memberships->resolveMember($project, $email));
    }

    /** Remove a member, addressed by email (admins). */
    public function destroy(Request $request, string $key, string $email): Response
    {
        $project = $this->projects->resolve($request->user(), $key);
        Gate::authorize('manage', $project);
        $this->memberships->remove($project, $this->memberships->resolveMember($project, $email));

        return response()->noContent();
    }
}
