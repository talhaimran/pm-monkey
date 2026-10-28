<?php

use App\Enums\ProjectRole;
use App\Models\ProjectInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('projects index shows my projects and my pending invitations', function () {
    $me = User::factory()->create(['email' => 'me@example.com']);
    makeProject($me, 'MINE');
    $other = makeProject($owner = User::factory()->create(), 'THEIRS');
    ProjectInvitation::create(['project_id' => $other->id, 'email' => 'me@example.com', 'role' => 'member', 'invited_by' => $owner->id]);

    $this->actingAs($me)->get('/projects')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects', 1)
            ->where('projects.0.key', 'MINE')
            ->where('invitations.0.project.key', 'THEIRS'));
});

test('creating a project from the web redirects to its settings', function () {
    $me = User::factory()->create();

    $this->actingAs($me)->post('/projects', ['key' => 'web', 'name' => 'Website'])
        ->assertRedirect('/projects/WEB/settings');
});

test('invited user accepts and joins with the invited role', function () {
    $owner = User::factory()->create();
    $project = makeProject($owner);
    $me = User::factory()->create(['email' => 'me@example.com']);
    $invitation = ProjectInvitation::create(['project_id' => $project->id, 'email' => 'me@example.com', 'role' => 'viewer', 'invited_by' => $owner->id]);

    $this->actingAs($me)->post("/invitations/{$invitation->id}/accept")->assertRedirect();

    expect($project->roleFor($me))->toBe(ProjectRole::Viewer)
        ->and(ProjectInvitation::count())->toBe(0);
});

test('a different user cannot accept someone else\'s invitation', function () {
    $owner = User::factory()->create();
    $project = makeProject($owner);
    $invitation = ProjectInvitation::create(['project_id' => $project->id, 'email' => 'me@example.com', 'role' => 'admin', 'invited_by' => $owner->id]);

    $this->actingAs(User::factory()->create())->post("/invitations/{$invitation->id}/accept")->assertForbidden();
});

test('viewers can open settings but not update', function () {
    $project = makeProject(User::factory()->create());
    $viewer = addMember($project, User::factory()->create(), ProjectRole::Viewer);

    $this->actingAs($viewer)->get('/projects/WEB/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Settings')
            ->where('can.manage', false)
            ->has('project.members', 2)
            ->where('project.members', fn ($m) => collect($m)->pluck('role')->sort()->values()->all() === ['admin', 'viewer']));
    $this->actingAs($viewer)->patch('/projects/WEB', ['name' => 'X'])->assertForbidden();
});

test('non-members cannot open settings', function () {
    makeProject(User::factory()->create());

    $this->actingAs(User::factory()->create())->get('/projects/WEB/settings')->assertForbidden();
});

test('admins can delete from the web with confirmation', function () {
    $admin = User::factory()->create();
    makeProject($admin);

    $this->actingAs($admin)->delete('/projects/WEB', ['confirm' => true])->assertRedirect('/projects');
    $this->assertDatabaseCount('projects', 0);
});
