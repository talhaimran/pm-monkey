<?php

use App\Enums\ProjectRole;
use App\Models\User;
use App\Models\WorkspaceInvitation;
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

    apiAs($mallory);
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
