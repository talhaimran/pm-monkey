<?php

use App\Enums\Entitlement;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Exceptions\PlanLimitReached;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Entitlements;
use App\Services\ProjectService;
use App\Services\WorkspaceMemberService;

/** Step 0 PR 5 security review probes: ways around the plan limits. */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->project = makeProject($this->owner, 'WEB');
    $this->members = app(WorkspaceMemberService::class);
});

test('handing a removed member’s projects to a Viewer needs a billed seat', function () {
    $leaver = User::factory()->create();
    $this->ws->addMember($leaver, WorkspaceRole::Member);
    $solo = app(ProjectService::class)->create($leaver, ['key' => 'SOLO', 'name' => 'Solo']);
    $viewer = addMember($this->project, User::factory()->create(['email' => 'vic@example.com']), ProjectRole::Viewer);
    // Owner, leaver and one more billed person fill the plan.
    $third = User::factory()->create();
    $this->ws->addMember($third, WorkspaceRole::Admin);
    app(Entitlements::class)->set($this->ws, Entitlement::BilledUsers, '3', 'override');

    // The leaver's seat frees up as the Viewer takes it: allowed.
    $this->members->remove($this->ws, $this->owner, $leaver, ['SOLO' => 'vic@example.com']);
    expect($solo->roleFor($viewer))->toBe(ProjectRole::Admin);
});

test('a Viewer handover is refused when it would add a billed person', function () {
    $leaver = User::factory()->create();
    $this->ws->addMember($leaver, WorkspaceRole::Member);
    $one = app(ProjectService::class)->create($leaver, ['key' => 'ONE', 'name' => 'One']);
    app(ProjectService::class)->create($leaver, ['key' => 'TWO', 'name' => 'Two']);
    addMember($this->project, User::factory()->create(['email' => 'v1@example.com']), ProjectRole::Viewer);
    addMember($this->project, User::factory()->create(['email' => 'v2@example.com']), ProjectRole::Viewer);
    app(Entitlements::class)->set($this->ws, Entitlement::BilledUsers, '2', 'override');

    // Two Viewers would become billed while only the leaver's seat frees up.
    expect(fn () => $this->members->remove($this->ws, $this->owner, $leaver, ['ONE' => 'v1@example.com', 'TWO' => 'v2@example.com']))
        ->toThrow(PlanLimitReached::class);
    expect($leaver->workspaceRole($this->ws))->toBe(WorkspaceRole::Member)
        ->and($one->members()->wherePivot('role', 'admin')->pluck('email')->all())->toBe([$leaver->email]);
});

/** Step 0 PR 8 scan, F5: creating a project makes you its Admin, a billed seat. */
test('a Viewer who creates a project needs a billed seat for it', function () {
    $viewer = addMember($this->project, User::factory()->create(), ProjectRole::Viewer);
    app(Entitlements::class)->set($this->ws, Entitlement::BilledUsers, '1', 'override');
    useWorkspace($this->ws);

    expect(fn () => app(ProjectService::class)->create($viewer, ['key' => 'MINE', 'name' => 'Mine']))
        ->toThrow(PlanLimitReached::class, 'Your plan allows 1 billed user.');
    expect($this->ws->projects()->where('key', 'MINE')->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'plan_limit.reached')->count())->toBe(1);

    // Someone already billed takes no new seat.
    expect(app(ProjectService::class)->create($this->owner, ['key' => 'OWN', 'name' => 'Own'])->key)->toBe('OWN');
});
