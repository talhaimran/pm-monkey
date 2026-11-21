<?php

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\CurrentWorkspace;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Mail;

// Claude Security scan of step 0 PR 1: findings F1/F2 (retargeting another project's invitation) and F3/F4 (expiry not enforced).

beforeEach(fn () => Mail::fake());

test('an admin of one project cannot take over another project\'s pending invitation', function () {
    $alice = User::factory()->create();
    $secret = makeProject($alice, 'SEC');
    makeInvitation($secret, $alice, 'carol@example.com', 'admin');

    $mallory = User::factory()->create();
    addMember($secret, $mallory, ProjectRole::Viewer);
    makeProject($mallory, 'MAL');

    apiAsPerson($mallory);  // invitations are people-only on REST (A-D2)
    $this->postJson('/api/v1/projects/MAL/invitations', ['email' => 'carol@example.com', 'role' => 'viewer'])
        ->assertStatus(422)->assertJsonValidationErrors('email');

    expect(WorkspaceInvitation::sole()->project_id)->toBe($secret->id);
});

test('an admin of both projects may move the invitation', function () {
    $alice = User::factory()->create();
    $secret = makeProject($alice, 'SEC');
    $other = makeProject($alice, 'OTH');
    makeInvitation($secret, $alice, 'carol@example.com', 'admin');

    makeInvitation($other, $alice, 'carol@example.com', 'member');

    expect(WorkspaceInvitation::sole()->project_id)->toBe($other->id);
});

test('an expired invitation cannot be accepted', function () {
    $alice = User::factory()->create();
    $project = makeProject($alice, 'WEB');
    $invitation = makeInvitation($project, $alice, 'carol@example.com', 'admin');
    $invitation->forceFill(['expires_at' => now()->subMinute()])->save();
    $carol = User::factory()->create(['email' => 'carol@example.com']);

    $this->actingAs($carol)->post("/invitations/{$invitation->id}/accept")->assertForbidden();

    expect($carol->fresh()->workspaces()->exists())->toBeFalse()
        ->and($project->members()->where('users.id', $carol->id)->exists())->toBeFalse();
});

// Step 0 PR 2 review: the same takeover through a workspace-level invitation (no project), e.g. an Owner inviting an Admin.
test('a project admin cannot overwrite a pending workspace-level invitation', function () {
    $owner = User::factory()->create();
    makeProject($owner, 'SEC');
    $workspace = app(CurrentWorkspace::class)->get();
    app(InvitationService::class)->invite($workspace, $owner, 'dana@example.com', WorkspaceRole::Admin);

    $mallory = User::factory()->create();
    makeProject($mallory, 'MAL');

    apiAsPerson($mallory);  // invitations are people-only on REST (A-D2)
    $this->postJson('/api/v1/projects/MAL/invitations', ['email' => 'dana@example.com', 'role' => 'viewer'])
        ->assertStatus(422)->assertJsonValidationErrors('email');

    $invitation = WorkspaceInvitation::sole();
    expect($invitation->role)->toBe(WorkspaceRole::Admin)->and($invitation->project_id)->toBeNull();
});
