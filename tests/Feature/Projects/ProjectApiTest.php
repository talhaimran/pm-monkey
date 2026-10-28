<?php

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

test('creating a project uppercases the key and makes the creator admin', function () {
    $user = apiAs(User::factory()->create());

    $this->postJson('/api/v1/projects', ['key' => 'web', 'name' => 'Website'])
        ->assertCreated()
        ->assertJsonPath('data.key', 'WEB')
        ->assertJsonPath('data.my_role', 'admin');

    expect(Project::first()->roleFor($user))->toBe(ProjectRole::Admin);
});

test('project key must be valid and unique', function () {
    $user = apiAs(User::factory()->create());
    makeProject($user, 'WEB');

    $this->postJson('/api/v1/projects', ['key' => 'WEB', 'name' => 'Dup'])
        ->assertUnprocessable()->assertJsonValidationErrors('key');

    $this->postJson('/api/v1/projects', ['key' => '1-bad', 'name' => 'Bad'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.key.0', fn ($m) => str_contains($m, '2-10'));
});

test('index lists only projects the user belongs to', function () {
    $me = User::factory()->create();
    makeProject($me, 'MINE');
    makeProject(User::factory()->create(), 'THEIRS');

    apiAs($me);
    $this->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.key', 'MINE');
});

test('non-members get a 404 that lists accessible project keys', function () {
    $me = User::factory()->create();
    makeProject($me, 'MINE');
    makeProject(User::factory()->create(), 'THEIRS');

    apiAs($me);
    $this->getJson('/api/v1/projects/THEIRS')
        ->assertNotFound()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'MINE') && ! str_contains($m, 'THEIRS,'));
});

test('show returns members and accepts lowercase keys', function () {
    $admin = User::factory()->create();
    $project = makeProject($admin, 'WEB');
    addMember($project, User::factory()->create(['email' => 'v@example.com']), ProjectRole::Viewer);

    apiAs($admin);
    $this->getJson('/api/v1/projects/web')
        ->assertOk()
        ->assertJsonPath('data.key', 'WEB')
        ->assertJsonCount(2, 'data.members');
});

test('viewers cannot update a project', function () {
    $project = makeProject(User::factory()->create());
    $viewer = addMember($project, User::factory()->create(), ProjectRole::Viewer);

    apiAs($viewer);
    $this->patchJson('/api/v1/projects/WEB', ['name' => 'New'])->assertForbidden();
});

test('read-only tokens cannot write even as admin', function () {
    $admin = User::factory()->create();
    makeProject($admin);

    apiAs($admin, ['read']);
    $this->patchJson('/api/v1/projects/WEB', ['name' => 'New'])->assertForbidden();
    $this->postJson('/api/v1/projects', ['key' => 'NEW', 'name' => 'New'])->assertForbidden();
});

test('admins can update name and description', function () {
    $admin = User::factory()->create();
    makeProject($admin);

    apiAs($admin);
    $this->patchJson('/api/v1/projects/WEB', ['name' => 'Renamed', 'description' => 'Hi'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.description', 'Hi');
});

test('deleting a project requires confirm true', function () {
    $admin = User::factory()->create();
    makeProject($admin);

    apiAs($admin);
    $this->deleteJson('/api/v1/projects/WEB')
        ->assertUnprocessable()
        ->assertJsonPath('errors.confirm.0', fn ($m) => str_contains($m, 'confirm: true'));

    $this->deleteJson('/api/v1/projects/WEB', ['confirm' => true])->assertNoContent();
    expect(Project::count())->toBe(0);
});

test('members cannot delete a project', function () {
    $project = makeProject(User::factory()->create());
    $member = addMember($project, User::factory()->create(), ProjectRole::Member);

    apiAs($member);
    $this->deleteJson('/api/v1/projects/WEB', ['confirm' => true])->assertForbidden();
});

test('guests are rejected', function () {
    $this->getJson('/api/v1/projects')->assertUnauthorized();
});
