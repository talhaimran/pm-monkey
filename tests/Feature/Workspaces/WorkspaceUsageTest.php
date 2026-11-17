<?php

use App\Enums\Entitlement;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Services\Entitlements;
use App\Services\InvitationService;
use App\Services\ProjectService;
use App\Services\WorkspaceUsage;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->owner = User::factory()->create();
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->web = makeProject($this->owner, 'WEB');
    $this->api = app(ProjectService::class)->create($this->owner, ['key' => 'API', 'name' => 'Api']);
    $this->usage = app(WorkspaceUsage::class);
});

/** [workspace role, project roles by key, billed?] — spec 4: Owners, Admins, and Members or Admins of any project are billed. */
dataset('members', [
    'owner' => ['owner', [], true],
    'admin' => ['admin', [], true],
    'admin, project viewer' => ['admin', ['WEB' => 'viewer'], true],
    'member in no project' => ['member', [], false],
    'member, project viewer' => ['member', ['WEB' => 'viewer'], false],
    'member, viewer in two projects' => ['member', ['WEB' => 'viewer', 'API' => 'viewer'], false],
    'member, project member' => ['member', ['WEB' => 'member'], true],
    'member, project admin' => ['member', ['WEB' => 'admin'], true],
    'member, viewer in one and member in another' => ['member', ['WEB' => 'viewer', 'API' => 'member'], true],
]);

test('each member counts once, as billed or as a Viewer', function (string $role, array $projects, bool $billed) {
    $user = User::factory()->create();
    $this->ws->addMember($user, WorkspaceRole::from($role));
    foreach ($projects as $key => $projectRole) {
        ($key === 'WEB' ? $this->web : $this->api)->members()->attach($user, ['role' => $projectRole]);
    }

    expect($this->usage->billedUsers($this->ws))->toBe($billed ? 2 : 1)
        ->and($this->usage->viewers($this->ws))->toBe($billed ? 0 : 1);
})->with('members');

/** [workspace role, project role, billed?] for an address that isn't a member yet. */
dataset('invitations', [
    'member, no project' => ['member', null, false],
    'member, project viewer' => ['member', 'viewer', false],
    'member, project member' => ['member', 'member', true],
    'member, project admin' => ['member', 'admin', true],
    'admin, no project' => ['admin', null, true],
    'admin, project viewer' => ['admin', 'viewer', true],
]);

test('a pending invitation counts as the person it would create', function (string $role, ?string $projectRole, bool $billed) {
    app(InvitationService::class)->invite($this->ws, $this->owner, 'new@example.com', WorkspaceRole::from($role), $projectRole ? $this->web : null, $projectRole ? ProjectRole::from($projectRole) : null);

    expect($this->usage->billedUsers($this->ws))->toBe($billed ? 2 : 1)
        ->and($this->usage->viewers($this->ws))->toBe($billed ? 0 : 1);
})->with('invitations');

test('a pending invitation for an existing member counts them once, by what they would become', function () {
    $viewer = addMember($this->web, User::factory()->create(['email' => 'vic@example.com']), ProjectRole::Viewer);
    expect([$this->usage->billedUsers($this->ws), $this->usage->viewers($this->ws)])->toBe([1, 1]);

    app(InvitationService::class)->invite($this->ws, $this->owner, 'vic@example.com', WorkspaceRole::Member, $this->api, ProjectRole::Member);
    expect([$this->usage->billedUsers($this->ws), $this->usage->viewers($this->ws)])->toBe([2, 0]);

    expect($viewer->workspaceRole($this->ws))->toBe(WorkspaceRole::Member);
});

test('projects are counted per workspace, and overLimit lists only the keys past their limit', function () {
    makeWorkspace(User::factory()->create(), 'other');
    useWorkspace($this->ws);
    $entitlements = app(Entitlements::class);
    $entitlements->set($this->ws, Entitlement::Projects, '1', 'override');
    $entitlements->set($this->ws, Entitlement::BilledUsers, '5', 'override');

    expect($this->usage->projects($this->ws))->toBe(2)
        ->and($this->usage->overLimit($this->ws))->toBe(['projects' => ['allowed' => 1, 'current' => 2]]);
});

test('the Usage page shows each enforced limit to Owners and Admins, and Members only their own', function () {
    app(Entitlements::class)->set($this->ws, Entitlement::Projects, '3', 'override');
    app(Entitlements::class)->set($this->ws, Entitlement::ApiPerDay, '2000', 'override');
    $member = User::factory()->create();
    $this->ws->addMember($member, WorkspaceRole::Member);

    $this->actingAs($this->owner)->get('/acme/settings/usage')->assertInertia(fn (Assert $page) => $page
        ->component('Workspaces/Settings/Usage')
        ->where('workspaceLimits', [
            ['key' => 'billed_users', 'label' => 'Billed users', 'current' => 1, 'allowed' => null],
            ['key' => 'viewers', 'label' => 'Viewers', 'current' => 1, 'allowed' => null],
            ['key' => 'projects', 'label' => 'Projects', 'current' => 2, 'allowed' => 3],
        ])
        ->where('tokenLimits', ['per_minute' => 120, 'per_day' => 2000]));

    $this->actingAs($member)->get('/acme/settings/usage')->assertInertia(fn (Assert $page) => $page
        ->where('workspaceLimits', null)
        ->where('tokenLimits', ['per_minute' => 120, 'per_day' => 2000]));
    $this->actingAs(User::factory()->create())->get('/acme/settings/usage')->assertNotFound();
});
