<?php

use App\Enums\ProjectRole;
use App\Models\Activity;
use App\Models\Label;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->project = makeProject($this->admin, 'WEB');
    $this->issue = makeIssue($this->project, $this->admin);
    $this->member = addMember($this->project, User::factory()->create(), ProjectRole::Member);
});

test('members comment and the comment is logged', function () {
    apiAs($this->member);

    $this->postJson('/api/v1/issues/WEB-1/comments', ['body' => 'Looks good'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Looks good')
        ->assertJsonPath('data.author.id', $this->member->id);

    expect(Activity::where('action', 'commented')->count())->toBe(1);
    $this->getJson('/api/v1/issues/WEB-1')->assertJsonPath('data.comments.0.body', 'Looks good');
});

test('only the author edits a comment', function () {
    apiAs($this->member);
    $id = $this->postJson('/api/v1/issues/WEB-1/comments', ['body' => 'v1'])->json('data.id');

    $this->patchJson("/api/v1/comments/{$id}", ['body' => 'v2'])->assertOk()->assertJsonPath('data.body', 'v2');

    apiAs($this->admin);
    $this->patchJson("/api/v1/comments/{$id}", ['body' => 'hijack'])->assertForbidden();
});

test('authors and admins delete comments, other members cannot', function () {
    apiAs($this->admin);
    $id = $this->postJson('/api/v1/issues/WEB-1/comments', ['body' => 'admin note'])->json('data.id');

    apiAs($this->member);
    $this->deleteJson("/api/v1/comments/{$id}")->assertForbidden();
    $mine = $this->postJson('/api/v1/issues/WEB-1/comments', ['body' => 'mine'])->json('data.id');
    $this->deleteJson("/api/v1/comments/{$mine}")->assertNoContent();

    apiAs($this->admin);
    $this->deleteJson("/api/v1/comments/{$id}")->assertNoContent();
});

test('comments by non-members are hidden', function () {
    apiAs(User::factory()->create());
    $this->postJson('/api/v1/issues/WEB-1/comments', ['body' => 'x'])->assertNotFound();
});

test('labels are created, listed, renamed and deleted', function () {
    apiAs($this->member);

    $this->postJson('/api/v1/projects/WEB/labels', ['name' => 'bug', 'color' => '#ef4444'])->assertCreated();
    $this->postJson('/api/v1/projects/WEB/labels', ['name' => 'BUG'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->getJson('/api/v1/projects/WEB/labels')->assertOk()->assertJsonPath('data.0.name', 'bug');

    $this->patchJson('/api/v1/issues/WEB-1', ['labels' => ['bug']])->assertJsonPath('data.labels', ['bug']);
    $this->patchJson('/api/v1/projects/WEB/labels/bug', ['name' => 'defect'])->assertOk();
    $this->getJson('/api/v1/issues/WEB-1')->assertJsonPath('data.labels', ['defect']);

    $this->deleteJson('/api/v1/projects/WEB/labels/defect')->assertNoContent();
    $this->getJson('/api/v1/issues/WEB-1')->assertJsonPath('data.labels', []);
});

test('unknown label in the url lists valid labels', function () {
    Label::create(['project_id' => $this->project->id, 'name' => 'ui', 'color' => '#000000']);
    apiAs($this->member);

    $this->patchJson('/api/v1/projects/WEB/labels/nope', ['name' => 'x'])
        ->assertNotFound()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'ui'));
});

test('viewers cannot manage labels or comment', function () {
    $viewer = addMember($this->project, User::factory()->create(), ProjectRole::Viewer);
    apiAs($viewer);

    $this->postJson('/api/v1/projects/WEB/labels', ['name' => 'x'])->assertForbidden();
    $this->postJson('/api/v1/issues/WEB-1/comments', ['body' => 'x'])->assertForbidden();
});
