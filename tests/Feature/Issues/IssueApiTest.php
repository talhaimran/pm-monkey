<?php

use App\Enums\ProjectRole;
use App\Models\Activity;
use App\Models\Issue;
use App\Models\Label;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->project = makeProject($this->admin, 'WEB');
});

test('new projects get the default columns in order', function () {
    expect($this->project->columns()->orderBy('position')->pluck('name')->all())
        ->toBe(['To Do', 'In Progress', 'In Review', 'Done']);
});

test('issues are numbered per project with sensible defaults', function () {
    apiAs($this->admin);

    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'First'])
        ->assertCreated()
        ->assertJsonPath('data.key', 'WEB-1')
        ->assertJsonPath('data.type', 'task')
        ->assertJsonPath('data.priority', 'medium')
        ->assertJsonPath('data.status', 'To Do')
        ->assertJsonPath('data.reporter.email', 'admin@example.com');

    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'Second'])->assertJsonPath('data.key', 'WEB-2');

    $other = makeProject($this->admin, 'API');
    expect(makeIssue($other, $this->admin)->key)->toBe('API-1');
});

test('issue numbers are never reused after deletion', function () {
    makeIssue($this->project, $this->admin);
    $second = makeIssue($this->project, $this->admin);
    $second->delete();

    expect(makeIssue($this->project, $this->admin)->key)->toBe('WEB-3');
});

test('create accepts names for status, assignee and labels', function () {
    $dev = addMember($this->project, User::factory()->create(['email' => 'dev@example.com']), ProjectRole::Member);
    Label::create(['project_id' => $this->project->id, 'name' => 'frontend', 'color' => '#3b82f6']);
    apiAs($this->admin);

    $this->postJson('/api/v1/projects/WEB/issues', [
        'title' => 'Fix login',
        'description' => '**Bold**',
        'type' => 'bug',
        'priority' => 'highest',
        'status' => 'in progress',
        'assignee' => 'DEV@example.com',
        'labels' => ['Frontend'],
        'story_points' => 3,
        'due_date' => '2026-12-01',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'In Progress')
        ->assertJsonPath('data.assignee.email', 'dev@example.com')
        ->assertJsonPath('data.labels', ['frontend'])
        ->assertJsonPath('data.story_points', 3)
        ->assertJsonPath('data.due_date', '2026-12-01');
});

test('unknown names produce errors that list the valid options', function () {
    apiAs($this->admin);
    Label::create(['project_id' => $this->project->id, 'name' => 'backend', 'color' => '#000000']);

    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'X', 'status' => 'Doing'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', fn ($m) => str_contains($m, 'To Do, In Progress, In Review, Done'));

    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'X', 'assignee' => 'stranger@example.com'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.assignee.0', fn ($m) => str_contains($m, 'admin@example.com'));

    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'X', 'labels' => ['nope']])
        ->assertUnprocessable()
        ->assertJsonPath('errors.labels.0', fn ($m) => str_contains($m, 'backend'));

    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'X', 'type' => 'saga'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.type.0', fn ($m) => str_contains($m, 'task, bug, story, epic'));
});

test('issues are addressable by key, case-insensitively', function () {
    makeIssue($this->project, $this->admin, ['title' => 'Hello']);
    apiAs($this->admin);

    $this->getJson('/api/v1/issues/web-1')->assertOk()->assertJsonPath('data.title', 'Hello');
    $this->getJson('/api/v1/issues/WEB-99')->assertNotFound();
    $this->getJson('/api/v1/issues/nonsense')
        ->assertNotFound()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'WEB-12'));
});

test('non-members cannot see issues', function () {
    makeIssue($this->project, $this->admin);

    apiAs(User::factory()->create());
    $this->getJson('/api/v1/issues/WEB-1')->assertNotFound();
});

test('epic links require an epic and epics cannot be linked', function () {
    $epic = makeIssue($this->project, $this->admin, ['title' => 'Epic', 'type' => 'epic']);
    $task = makeIssue($this->project, $this->admin, ['title' => 'Task']);
    apiAs($this->admin);

    $this->patchJson('/api/v1/issues/WEB-2', ['epic' => 'WEB-1'])->assertOk()->assertJsonPath('data.epic.key', 'WEB-1');
    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'X', 'epic' => 'WEB-2'])
        ->assertUnprocessable()->assertJsonValidationErrors('epic');
    $this->patchJson('/api/v1/issues/WEB-1', ['epic' => 'WEB-1'])
        ->assertUnprocessable()->assertJsonValidationErrors('epic');

    $this->getJson('/api/v1/issues/WEB-1')->assertJsonPath('data.epic_issues.0.key', 'WEB-2');
});

test('sub-tasks are one level deep', function () {
    makeIssue($this->project, $this->admin, ['title' => 'Story', 'type' => 'story']);
    apiAs($this->admin);

    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'Sub', 'parent' => 'WEB-1'])
        ->assertCreated()->assertJsonPath('data.parent.key', 'WEB-1');

    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'Sub-sub', 'parent' => 'WEB-2'])
        ->assertUnprocessable()->assertJsonValidationErrors('parent');

    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'Epic', 'type' => 'epic', 'parent' => 'WEB-1'])
        ->assertUnprocessable()->assertJsonValidationErrors('parent');

    // WEB-1 has a sub-task, so it can't become a sub-task itself.
    makeIssue($this->project, $this->admin, ['title' => 'Other']);
    $this->patchJson('/api/v1/issues/WEB-1', ['parent' => 'WEB-3'])
        ->assertUnprocessable()->assertJsonValidationErrors('parent');

    $this->getJson('/api/v1/issues/WEB-1')->assertJsonPath('data.subtasks.0.key', 'WEB-2');
});

test('links must stay inside the project', function () {
    $other = makeProject($this->admin, 'API');
    makeIssue($other, $this->admin, ['type' => 'epic']);
    makeIssue($this->project, $this->admin);
    apiAs($this->admin);

    $this->patchJson('/api/v1/issues/WEB-1', ['epic' => 'API-1'])
        ->assertUnprocessable()->assertJsonValidationErrors('epic');
});

test('updates are logged with display values and the token name', function () {
    addMember($this->project, User::factory()->create(['email' => 'dev@example.com']), ProjectRole::Member);
    makeIssue($this->project, $this->admin, ['title' => 'Old']);
    $token = $this->admin->createToken('claude-agent', ['read', 'write'])->plainTextToken;

    $this->withToken($token)->patchJson('/api/v1/issues/WEB-1', [
        'title' => 'New', 'status' => 'Done', 'assignee' => 'dev@example.com',
    ])->assertOk();

    $activity = Activity::where('action', 'updated')->sole();
    expect($activity->via)->toBe('api')
        ->and($activity->token_name)->toBe('claude-agent')
        ->and($activity->user_id)->toBe($this->admin->id)
        // toEqual: MySQL JSON columns don't preserve key order.
        ->and($activity->changes)->toEqual([
            'title' => ['from' => 'Old', 'to' => 'New'],
            'status' => ['from' => 'To Do', 'to' => 'Done'],
            'assignee' => ['from' => null, 'to' => 'dev@example.com'],
        ]);
});

test('updates that change nothing are not logged', function () {
    makeIssue($this->project, $this->admin, ['title' => 'Same']);
    apiAs($this->admin);

    $this->patchJson('/api/v1/issues/WEB-1', ['title' => 'Same'])->assertOk();

    expect(Activity::where('action', 'updated')->count())->toBe(0);
});

test('activity endpoint lists newest first', function () {
    makeIssue($this->project, $this->admin);
    apiAs($this->admin);
    $this->patchJson('/api/v1/issues/WEB-1', ['priority' => 'high']);

    $this->getJson('/api/v1/issues/WEB-1/activity')
        ->assertOk()
        ->assertJsonPath('data.0.action', 'updated')
        ->assertJsonPath('data.1.action', 'created');
});

test('viewers and read-only tokens cannot write issues', function () {
    makeIssue($this->project, $this->admin);
    $viewer = addMember($this->project, User::factory()->create(), ProjectRole::Viewer);

    apiAs($viewer);
    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'X'])->assertForbidden();
    $this->getJson('/api/v1/issues/WEB-1')->assertOk();

    apiAs($this->admin, ['read']);
    $this->patchJson('/api/v1/issues/WEB-1', ['title' => 'X'])->assertForbidden();
});

test('deleting an issue requires confirm true', function () {
    makeIssue($this->project, $this->admin);
    apiAs($this->admin);

    $this->deleteJson('/api/v1/issues/WEB-1')->assertUnprocessable()->assertJsonValidationErrors('confirm');
    $this->deleteJson('/api/v1/issues/WEB-1', ['confirm' => true])->assertNoContent();

    expect(Issue::count())->toBe(0);
});

test('deleting a parent deletes its sub-tasks; deleting an epic unlinks its issues', function () {
    $epic = makeIssue($this->project, $this->admin, ['type' => 'epic']);
    $story = makeIssue($this->project, $this->admin, ['type' => 'story', 'epic' => 'WEB-1']);
    makeIssue($this->project, $this->admin, ['parent' => 'WEB-2']);
    apiAs($this->admin);

    $this->deleteJson('/api/v1/issues/WEB-1', ['confirm' => true])->assertNoContent();
    expect($story->fresh()->epic_id)->toBeNull();

    $this->deleteJson('/api/v1/issues/WEB-2', ['confirm' => true])->assertNoContent();
    expect(Issue::count())->toBe(0);
});

test('deleting a project with issues, labels and comments works', function () {
    Label::create(['project_id' => $this->project->id, 'name' => 'x', 'color' => '#000000']);
    makeIssue($this->project, $this->admin, ['type' => 'epic', 'labels' => ['x']]);
    makeIssue($this->project, $this->admin, ['epic' => 'WEB-1']);
    makeIssue($this->project, $this->admin, ['parent' => 'WEB-2']);
    apiAs($this->admin);

    $this->deleteJson('/api/v1/projects/WEB', ['confirm' => true])->assertNoContent();
    expect(Issue::count())->toBe(0);
});
