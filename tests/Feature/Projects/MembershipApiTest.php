<?php

use App\Enums\ProjectRole;
use App\Mail\ProjectInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

// Project settings, columns and members are people-only on REST (A-D2); these cover the shared logic as a person.

test('admins invite by email and the invitation is mailed', function () {
    Mail::fake();
    $admin = User::factory()->create();
    makeProject($admin);

    apiAsPerson($admin);
    $this->postJson('/api/v1/projects/WEB/invitations', ['email' => 'New@Example.com', 'role' => 'member'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'new@example.com')
        ->assertJsonPath('data.role', 'member');

    Mail::assertSent(ProjectInvitationMail::class, fn ($mail) => $mail->hasTo('new@example.com'));
});

test('inviting an existing member is rejected', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    makeProject($admin);

    apiAsPerson($admin);
    $this->postJson('/api/v1/projects/WEB/invitations', ['email' => 'admin@example.com', 'role' => 'member'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('invalid role errors list valid options', function () {
    $admin = User::factory()->create();
    makeProject($admin);

    apiAsPerson($admin);
    $this->postJson('/api/v1/projects/WEB/invitations', ['email' => 'x@example.com', 'role' => 'owner'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.role.0', fn ($m) => str_contains($m, 'admin, member, viewer'));
});

test('members cannot invite', function () {
    $project = makeProject(User::factory()->create());
    $member = addMember($project, User::factory()->create(), ProjectRole::Member);

    apiAsPerson($member);
    $this->postJson('/api/v1/projects/WEB/invitations', ['email' => 'x@example.com', 'role' => 'viewer'])
        ->assertForbidden();
});

test('members endpoint lists members and pending invitations', function () {
    $admin = User::factory()->create();
    $project = makeProject($admin);
    makeInvitation($project, $admin, 'p@example.com', 'viewer');

    apiAsPerson($admin);
    $this->getJson('/api/v1/projects/WEB/members')
        ->assertOk()
        ->assertJsonCount(1, 'data.members')
        ->assertJsonPath('data.invitations.0.email', 'p@example.com');
});

test('admins change roles by member email', function () {
    $admin = User::factory()->create();
    $project = makeProject($admin);
    $user = addMember($project, User::factory()->create(['email' => 'm@example.com']), ProjectRole::Member);

    apiAsPerson($admin);
    $this->patchJson('/api/v1/projects/WEB/members/m@example.com', ['role' => 'viewer'])->assertOk();

    expect($project->roleFor($user))->toBe(ProjectRole::Viewer);
});

test('unknown member email returns 404 listing members', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    makeProject($admin);

    apiAsPerson($admin);
    $this->patchJson('/api/v1/projects/WEB/members/nobody@example.com', ['role' => 'viewer'])
        ->assertNotFound()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'admin@example.com'));
});

test('the last admin cannot be demoted or removed', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    makeProject($admin);

    apiAsPerson($admin);
    $this->patchJson('/api/v1/projects/WEB/members/admin@example.com', ['role' => 'member'])
        ->assertUnprocessable()->assertJsonValidationErrors('role');
    $this->deleteJson('/api/v1/projects/WEB/members/admin@example.com')
        ->assertUnprocessable();
});

test('admins remove members', function () {
    $admin = User::factory()->create();
    $project = makeProject($admin);
    $user = addMember($project, User::factory()->create(['email' => 'm@example.com']), ProjectRole::Member);

    apiAsPerson($admin);
    $this->deleteJson('/api/v1/projects/WEB/members/m@example.com')->assertNoContent();

    expect($project->roleFor($user))->toBeNull();
});
