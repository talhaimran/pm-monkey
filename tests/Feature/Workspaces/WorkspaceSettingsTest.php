<?php

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Mail\ProjectInvitationMail;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\InvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->owner = User::factory()->create(['email' => 'owner@example.com', 'name' => 'Olive']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->project = makeProject($this->owner, 'WEB');
});

function general(array $overrides = []): array
{
    return ['name' => 'Acme', 'slug' => 'acme', 'members_can_create_projects' => true, ...$overrides];
}

test('the General page shows the workspace to Owners and Admins', function () {
    $this->actingAs($this->owner)->get('/acme/settings/general')->assertInertia(fn (Assert $page) => $page
        ->component('Workspaces/Settings/General')
        ->where('settings', ['name' => 'Acme', 'slug' => 'acme', 'members_can_create_projects' => true])
        ->where('can.delete', true)->where('deletionScheduledAt', null));
});

test('renaming keeps the old slug as a redirect and moves the settings URL', function () {
    $this->actingAs($this->owner)->patch('/acme/settings/general', general(['name' => 'Acme Corp', 'slug' => 'acme-corp']))
        ->assertRedirect('/acme-corp/settings/general');

    expect($this->ws->fresh()->only('name', 'slug'))->toBe(['name' => 'Acme Corp', 'slug' => 'acme-corp']);
    $this->actingAs($this->owner)->get('/acme/projects/WEB?x=1')->assertRedirect('/acme-corp/projects/WEB?x=1');
});

test('slug rules: format, reserved words and another workspace are refused', function (string $slug, string $error) {
    makeWorkspace(User::factory()->create(), 'taken');
    useWorkspace($this->ws);

    $this->actingAs($this->owner)->patch('/acme/settings/general', general(['slug' => $slug]))->assertSessionHasErrors(['slug' => $error]);
    expect($this->ws->fresh()->slug)->toBe('acme');
})->with([
    ['ab', 'The address must be 3–40 lowercase letters, digits or hyphens.'],
    ['Has Space', 'The address must be 3–40 lowercase letters, digits or hyphens.'],
    ['settings', '"settings" is reserved. Choose another address.'],
    ['taken', '"taken" is already taken. Choose another address.'],
]);

test("another workspace's old slug can be claimed, which ends its redirect", function () {
    $other = makeWorkspace(User::factory()->create(), 'other');
    DB::table('workspace_slug_redirects')->insert(['old_slug' => 'old-other', 'workspace_id' => $other->id, 'created_at' => now()]);
    useWorkspace($this->ws);

    $this->actingAs($this->owner)->patch('/acme/settings/general', general(['slug' => 'old-other']))->assertSessionHasNoErrors();

    expect(DB::table('workspace_slug_redirects')->where('old_slug', 'old-other')->exists())->toBeFalse();
    $this->actingAs($this->owner)->get('/old-other/dashboard')->assertOk();
});

test('renaming back to your own old slug works and keeps the newer one as a redirect', function () {
    $this->actingAs($this->owner)->patch('/acme/settings/general', general(['slug' => 'acme-two']))->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->patch('/acme-two/settings/general', general(['slug' => 'acme']))->assertSessionHasNoErrors();

    expect($this->ws->fresh()->slug)->toBe('acme')
        ->and(DB::table('workspace_slug_redirects')->pluck('old_slug')->all())->toBe(['acme-two']);
});

test('who can create projects is saved with the other settings', function () {
    $this->actingAs($this->owner)->patch('/acme/settings/general', general(['members_can_create_projects' => false]))->assertSessionHasNoErrors();

    expect($this->ws->fresh()->setting('members_can_create_projects'))->toBeFalse()
        ->and($this->ws->fresh()->setting('require_2fa'))->toBeFalse();
});

test('admins can rename, but members cannot', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $this->ws->addMember($admin, WorkspaceRole::Admin);
    $this->ws->addMember($member, WorkspaceRole::Member);

    $this->actingAs($member)->patch('/acme/settings/general', general(['name' => 'Nope']))->assertForbidden();
    $this->actingAs($admin)->patch('/acme/settings/general', general(['name' => 'Renamed']))->assertSessionHasNoErrors();
    $this->actingAs($admin)->get('/acme/settings/general')->assertInertia(fn (Assert $page) => $page->where('can.delete', false));
});

test('the Members page lists role, billed or Viewer, projects and last active, plus pending invitations', function () {
    $viewer = addMember($this->project, User::factory()->create(['email' => 'viewer@example.com', 'name' => 'Vic']), ProjectRole::Viewer);
    $this->ws->members()->updateExistingPivot($this->owner->id, ['last_active_at' => now()]);
    makeInvitation($this->project, $this->owner, 'new@example.com', 'member');

    $this->actingAs($this->owner)->get('/acme/settings/members')->assertInertia(fn (Assert $page) => $page
        ->component('Workspaces/Settings/Members')
        ->has('members', 2)
        ->where('members.0.email', 'owner@example.com')->where('members.0.role', 'owner')->where('members.0.billed', true)
        ->where('members.0.projects', [['key' => 'WEB', 'role' => 'admin']])
        ->whereNot('members.0.last_active_at', null)
        ->where('members.1.email', 'viewer@example.com')->where('members.1.billed', false)->where('members.1.last_active_at', null)
        ->has('invitations', 1)->where('invitations.0.email', 'new@example.com')->where('invitations.0.project', 'WEB')
        ->where('projects', ['WEB'])
        ->where('soleAdminOf', [$this->owner->id => ['WEB']]));
});

test('owners and admins invite from the Members page, resend and revoke', function () {
    $this->actingAs($this->owner)->post('/acme/settings/members/invitations', [
        'email' => 'Boss@Example.com', 'role' => 'admin', 'project' => 'WEB', 'project_role' => 'member',
    ])->assertSessionHasNoErrors();

    $invitation = WorkspaceInvitation::firstWhere('email', 'boss@example.com');
    expect($invitation->role)->toBe(WorkspaceRole::Admin)->and($invitation->project_id)->toBe($this->project->id);
    Mail::assertSent(ProjectInvitationMail::class, 1);

    $this->travel(3)->days();
    $oldToken = $invitation->token;
    $this->actingAs($this->owner)->post("/acme/settings/members/invitations/{$invitation->id}/resend")->assertSessionHasNoErrors();
    expect($invitation->fresh()->token)->not->toBe($oldToken)
        ->and($invitation->fresh()->expires_at->isAfter(now()->addDays(13)))->toBeTrue();
    Mail::assertSent(ProjectInvitationMail::class, 2);

    $this->actingAs($this->owner)->delete("/acme/settings/members/invitations/{$invitation->id}")->assertSessionHasNoErrors();
    expect(WorkspaceInvitation::count())->toBe(0);
});

test("invitations and members of another workspace can't be reached through this one", function () {
    $stranger = User::factory()->create();
    $other = makeWorkspace($stranger, 'other');
    $foreign = app(InvitationService::class)->invite($other, $stranger, 'x@example.com', WorkspaceRole::Member);
    useWorkspace($this->ws);

    $this->actingAs($this->owner)->post("/acme/settings/members/invitations/{$foreign->id}/resend")->assertNotFound();
    $this->actingAs($this->owner)->delete("/acme/settings/members/invitations/{$foreign->id}")->assertNotFound();
    $this->actingAs($this->owner)->patch("/acme/settings/members/{$stranger->id}", ['role' => 'member'])->assertNotFound();
    $this->actingAs($this->owner)->delete("/acme/settings/members/{$stranger->id}")->assertNotFound();
    expect($foreign->fresh())->not->toBeNull()->and($stranger->workspaceRole($other))->toBe(WorkspaceRole::Owner);
});

test('the invite role must be Admin or Member and the project must be in this workspace', function () {
    $this->actingAs($this->owner)->post('/acme/settings/members/invitations', ['email' => 'a@example.com', 'role' => 'owner'])
        ->assertSessionHasErrors('role');
    $this->actingAs($this->owner)->post('/acme/settings/members/invitations', ['email' => 'a@example.com', 'role' => 'member', 'project' => 'NOPE'])
        ->assertSessionHasErrors('project');
    expect(WorkspaceInvitation::count())->toBe(0);
});
