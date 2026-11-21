<?php

use App\Enums\AgentLevel;
use App\Enums\AgentStatus;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\CreateProject;
use App\Models\Agent;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ProjectService;
use App\Services\WorkspaceMemberService;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create(['email' => 'owner@example.com']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->member = User::factory()->create(['email' => 'member@example.com']);
    $this->ws->addMember($this->admin, WorkspaceRole::Admin);
    $this->ws->addMember($this->member, WorkspaceRole::Member);
    $this->project = makeProject($this->owner, 'WEB');
});

/** Spec section 4, the abilities step 0 covers: [ability, owner, admin, member]. */
dataset('abilities', [
    ['openBilling', true, false, false],
    ['delete', true, false, false],
    ['manageOwners', true, false, false],
    ['update', true, true, false],
    ['manageMembers', true, true, false],
    ['manageSecurity', true, true, false],
    ['viewAuditLog', true, true, false],
    ['viewAllProjects', true, true, false],
    ['manageProjectCreation', true, true, false],
    ['viewUsage', true, true, true],
    ['createProject', true, true, true],
]);

test('each role gets the abilities in the spec table, and outsiders none', function (string $ability, bool $owner, bool $admin, bool $member) {
    $outsider = User::factory()->create();

    expect(Gate::forUser($this->owner)->allows($ability, $this->ws))->toBe($owner)
        ->and(Gate::forUser($this->admin)->allows($ability, $this->ws))->toBe($admin)
        ->and(Gate::forUser($this->member)->allows($ability, $this->ws))->toBe($member)
        ->and(Gate::forUser($outsider)->allows($ability, $this->ws))->toBeFalse();
})->with('abilities');

test('members create projects only while the setting allows it, on web, API and MCP', function () {
    $this->ws->forceFill(['settings' => ['members_can_create_projects' => false]])->save();

    $this->actingAs($this->member)->post(w('/projects'), ['key' => 'MEM', 'name' => 'Member'])->assertForbidden();
    apiAs($this->member);
    $this->postJson('/api/v1/projects', ['key' => 'MEM', 'name' => 'Member'])
        ->assertForbidden()->assertJsonPath('message', 'Only Owners and Admins can create projects in Acme.');
    PmServer::tool(CreateProject::class, ['key' => 'MEM', 'name' => 'Member'])->assertHasErrors(['Only Owners and Admins can create projects in Acme.']);

    app('auth')->forgetGuards();
    $this->actingAs($this->admin)->post(w('/projects'), ['key' => 'ADM', 'name' => 'Admin'])->assertRedirect();
    expect($this->ws->projects()->pluck('key')->sort()->values()->all())->toBe(['ADM', 'WEB']);
});

test('admins change roles up to Admin but never touch Owners', function () {
    $this->actingAs($this->admin)->patch(w("/settings/members/{$this->member->id}"), ['role' => 'admin'])->assertSessionHasNoErrors();
    expect($this->member->workspaceRole($this->ws))->toBe(WorkspaceRole::Admin);

    $this->actingAs($this->admin)->patch(w("/settings/members/{$this->member->id}"), ['role' => 'owner'])
        ->assertSessionHasErrors(['role' => 'Only Owners can add, change or remove Owners.']);
    $this->actingAs($this->admin)->patch(w("/settings/members/{$this->owner->id}"), ['role' => 'member'])
        ->assertSessionHasErrors(['role' => 'Only Owners can add, change or remove Owners.']);
    $this->actingAs($this->admin)->delete(w("/settings/members/{$this->owner->id}"))
        ->assertSessionHasErrors(['member' => 'Only Owners can add, change or remove Owners.']);
    expect($this->owner->workspaceRole($this->ws))->toBe(WorkspaceRole::Owner);
});

test('members cannot open the settings pages or change anyone', function () {
    $this->actingAs($this->member)->get(w('/settings/members'))->assertForbidden();
    $this->actingAs($this->member)->get(w('/settings/general'))->assertForbidden();
    $this->actingAs($this->member)->patch(w("/settings/members/{$this->admin->id}"), ['role' => 'member'])->assertForbidden();
    $this->actingAs($this->member)->delete(w("/settings/members/{$this->admin->id}"))->assertForbidden();
});

test('owners add and demote Owners, but the last Owner cannot be demoted, removed or leave', function () {
    $service = app(WorkspaceMemberService::class);
    $last = 'A workspace needs at least one Owner. Make someone else an Owner first.';

    expect(fn () => $service->changeRole($this->ws, $this->owner, $this->owner, WorkspaceRole::Admin))->toThrow(Exception::class, $last);
    expect(fn () => $service->remove($this->ws, $this->owner, $this->owner))->toThrow(Exception::class, $last);

    $this->actingAs($this->owner)->patch(w("/settings/members/{$this->admin->id}"), ['role' => 'owner'])->assertSessionHasNoErrors();
    expect($this->admin->workspaceRole($this->ws))->toBe(WorkspaceRole::Owner);

    // Now there are two, so either can step down; then the other is last again.
    $service->changeRole($this->ws, $this->admin, $this->owner, WorkspaceRole::Member);
    expect($this->owner->workspaceRole($this->ws))->toBe(WorkspaceRole::Member);
    expect(fn () => $service->remove($this->ws, $this->admin, $this->admin))->toThrow(Exception::class, $last);
});

test('a demoted Owner cannot act as one afterwards (the role is re-read under the lock)', function () {
    $this->ws->members()->updateExistingPivot($this->admin->id, ['role' => 'owner']);
    $service = app(WorkspaceMemberService::class);

    // Two Owners demote each other: the first wins, the second is no longer an Owner when it runs.
    $service->changeRole($this->ws, $this->owner, $this->admin, WorkspaceRole::Member);
    expect(fn () => $service->changeRole($this->ws, $this->admin, $this->owner, WorkspaceRole::Member))
        ->toThrow(Exception::class, 'Only Owners and Admins of Acme can do this.');
    expect($this->ws->members()->wherePivot('role', 'owner')->count())->toBe(1);
});

test('a member can leave; removal takes them out of every project here and revokes their agents here only', function () {
    addMember($this->project, $this->member, ProjectRole::Member);
    $other = makeWorkspace($this->member, 'other');
    $otherProject = makeProject($this->member, 'OTH');
    useWorkspace($this->ws);
    makeAgent($this->member, AgentLevel::Read, name: 'here');
    useWorkspace($other);
    makeAgent($this->member, AgentLevel::Read, name: 'there');
    useWorkspace($this->ws);

    $this->actingAs($this->admin)->delete(w("/settings/members/{$this->member->id}"))->assertSessionHasNoErrors();

    expect($this->member->workspaceRole($this->ws))->toBeNull()
        ->and($this->project->roleFor($this->member))->toBeNull()
        ->and($otherProject->roleFor($this->member))->toBe(ProjectRole::Admin)
        ->and($this->member->tokens()->pluck('name')->all())->toBe(['there'])
        ->and(Agent::where('name', 'here')->sole()->status)->toBe(AgentStatus::Revoked);

    $leaver = User::factory()->create();
    $this->ws->addMember($leaver, WorkspaceRole::Member);
    app(WorkspaceMemberService::class)->remove($this->ws, $leaver, $leaver);
    expect($leaver->workspaceRole($this->ws))->toBeNull();
});

test("removing a project's only admin needs a new admin for it first", function () {
    $other = User::factory()->create(['email' => 'other@example.com']);
    $this->ws->addMember($other, WorkspaceRole::Member);
    $solo = app(ProjectService::class)->create($this->member, ['key' => 'SOLO', 'name' => 'Solo']);

    $this->actingAs($this->owner)->delete(w("/settings/members/{$this->member->id}"))
        ->assertSessionHasErrors(['new_admins' => 'member@example.com is the only admin of SOLO. Pick a new admin for each first.']);
    $this->actingAs($this->owner)->delete(w("/settings/members/{$this->member->id}"), ['new_admins' => ['SOLO' => 'stranger@example.com']])
        ->assertSessionHasErrors('new_admins.SOLO');
    expect($this->member->workspaceRole($this->ws))->toBe(WorkspaceRole::Member);

    $this->actingAs($this->owner)->delete(w("/settings/members/{$this->member->id}"), ['new_admins' => ['SOLO' => 'other@example.com']])
        ->assertSessionHasNoErrors();
    expect($solo->roleFor($other))->toBe(ProjectRole::Admin)->and($solo->roleFor($this->member))->toBeNull();
});

test('review focus 1: removed from A while A is current, the next request lands in B and A tokens are gone', function () {
    $b = makeWorkspace($this->member, 'bravo');
    useWorkspace($this->ws, $this->member);
    [, $token] = makeAgent($this->member, AgentLevel::Read);
    expect($this->member->fresh()->current_workspace_id)->toBe($this->ws->id);

    app(WorkspaceMemberService::class)->remove($this->ws, $this->owner, $this->member);

    $this->actingAs($this->member->fresh())->get('/dashboard')->assertRedirect('/bravo/dashboard');
    $this->actingAs($this->member->fresh())->get('/acme/dashboard')->assertNotFound();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/projects')->assertUnauthorized();
    expect($this->member->tokens()->count())->toBe(0);
});

test('owners and admins see every project read-only until they join as Admin', function () {
    $secret = app(ProjectService::class)->create($this->member, ['key' => 'SEC', 'name' => 'Secret']);
    makeIssue($secret, $this->member, ['title' => 'Hidden plan']);

    $this->actingAs($this->admin)->get(w('/projects'))->assertInertia(fn (Assert $page) => $page
        ->where('projects.0.key', 'SEC')->where('projects.0.my_role', null)->where('can.join', true));
    $this->actingAs($this->admin)->get(w('/projects/SEC'))->assertOk();
    $this->actingAs($this->admin)->get(w('/issues/SEC-1'))->assertOk();
    $this->actingAs($this->admin)->post(w('/projects/SEC/issues'), ['title' => 'Nope'])->assertForbidden();
    $this->actingAs($this->admin)->patch(w('/projects/SEC'), ['name' => 'Mine'])->assertForbidden();

    // A Member doesn't see projects they aren't in.
    $this->actingAs($this->owner)->get(w('/projects'))->assertOk();
    $this->actingAs(User::factory()->create())->get(w('/projects/SEC'))->assertNotFound();
    $outsiderMember = User::factory()->create();
    $this->ws->addMember($outsiderMember, WorkspaceRole::Member);
    $this->actingAs($outsiderMember)->get(w('/projects/SEC'))->assertForbidden();
    $this->actingAs($outsiderMember)->post(w('/projects/SEC/join'))->assertForbidden();

    $this->actingAs($this->admin)->post(w('/projects/SEC/join'))->assertRedirect(w('/projects/SEC'));
    expect($secret->roleFor($this->admin))->toBe(ProjectRole::Admin);
    $this->actingAs($this->admin)->post(w('/projects/SEC/issues'), ['title' => 'Now I can'])->assertRedirect();
});

test('an admin token reads every project but cannot write to one it has not joined', function () {
    app(ProjectService::class)->create($this->member, ['key' => 'SEC', 'name' => 'Secret']);
    apiAs($this->admin);

    $this->getJson('/api/v1/projects')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/projects/SEC')->assertOk()->assertJsonPath('data.my_role', null);
    $this->postJson('/api/v1/projects/SEC/issues', ['title' => 'Nope'])->assertForbidden()
        ->assertJsonPath('message', "You haven't joined SEC. Join it as Admin to make changes.");
});

test('deleting your account is blocked while you are the last Owner of a workspace', function () {
    $this->actingAs($this->owner)->delete('/profile', ['password' => 'password'])
        ->assertSessionHasErrors(['password' => 'You are the last Owner of Acme. Make someone else an Owner or delete those workspaces first.']);
    expect(User::find($this->owner->id))->not->toBeNull();
    expect(Workspace::find($this->ws->id))->not->toBeNull();
});
