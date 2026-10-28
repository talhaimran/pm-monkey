<?php

use App\Enums\ProjectRole;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->project = makeProject($this->admin, 'WEB');
    apiAs($this->admin);
});

$names = fn () => test()->getJson('/api/v1/projects/WEB/columns')->json('data.*.name');

test('columns are listed in order with issue counts', function () {
    makeIssue($this->project, $this->admin);

    $this->getJson('/api/v1/projects/WEB/columns')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'To Do')
        ->assertJsonPath('data.0.issue_count', 1)
        ->assertJsonPath('data.3.name', 'Done');
});

test('columns are appended or inserted at a position', function () use ($names) {
    $this->postJson('/api/v1/projects/WEB/columns', ['name' => 'Blocked'])->assertCreated();
    $this->postJson('/api/v1/projects/WEB/columns', ['name' => 'Ready', 'position' => 1])->assertCreated();

    expect($names())->toBe(['To Do', 'Ready', 'In Progress', 'In Review', 'Done', 'Blocked']);
});

test('column names are unique per project', function () {
    $this->postJson('/api/v1/projects/WEB/columns', ['name' => 'done'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

test('columns are renamed and reordered by name', function () use ($names) {
    $this->patchJson('/api/v1/projects/WEB/columns/in review', ['name' => 'QA', 'position' => 0])->assertOk();

    expect($names())->toBe(['QA', 'To Do', 'In Progress', 'Done']);
});

test('deleting a column with issues requires a target column', function () use ($names) {
    makeIssue($this->project, $this->admin, ['status' => 'In Review']);

    $this->deleteJson('/api/v1/projects/WEB/columns/In Review')
        ->assertUnprocessable()
        ->assertJsonPath('errors.move_issues_to.0', fn ($m) => str_contains($m, 'To Do, In Progress, Done'));

    $this->deleteJson('/api/v1/projects/WEB/columns/In Review', ['move_issues_to' => 'Done'])->assertNoContent();

    expect($names())->toBe(['To Do', 'In Progress', 'Done']);
    $this->getJson('/api/v1/issues/WEB-1')->assertJsonPath('data.status', 'Done');
});

test('empty columns delete directly but the last column stays', function () {
    foreach (['In Progress', 'In Review', 'Done'] as $name) {
        $this->deleteJson("/api/v1/projects/WEB/columns/{$name}")->assertNoContent();
    }

    $this->deleteJson('/api/v1/projects/WEB/columns/To Do')
        ->assertUnprocessable()->assertJsonValidationErrors('column');
});

test('unknown column names list the valid ones', function () {
    $this->patchJson('/api/v1/projects/WEB/columns/Nope', ['name' => 'X'])
        ->assertNotFound()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'To Do, In Progress, In Review, Done'));
});

test('only admins manage columns', function () {
    $member = addMember($this->project, User::factory()->create(), ProjectRole::Member);

    apiAs($member);
    $this->getJson('/api/v1/projects/WEB/columns')->assertOk();
    $this->postJson('/api/v1/projects/WEB/columns', ['name' => 'X'])->assertForbidden();
});
