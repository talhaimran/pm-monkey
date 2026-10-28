<?php

use App\Enums\ProjectRole;
use App\Models\User;

beforeEach(function () {
    $this->me = User::factory()->create(['email' => 'me@example.com', 'name' => 'Me']);
    $this->project = makeProject($this->me, 'WEB');
    apiAs($this->me);
});

test('list is paginated, includes epics and defaults to newest first', function () {
    makeIssue($this->project, $this->me, ['type' => 'epic']);
    foreach (range(1, 4) as $i) {
        makeIssue($this->project, $this->me);
    }

    $this->getJson('/api/v1/projects/WEB/issues?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.key', 'WEB-5')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.last_page', 3);

    $this->getJson('/api/v1/projects/WEB/issues?type=epic')->assertJsonPath('data.0.key', 'WEB-1');
});

test('list sorts by priority, status and title', function () {
    makeIssue($this->project, $this->me, ['title' => 'b', 'priority' => 'low', 'status' => 'Done']);
    makeIssue($this->project, $this->me, ['title' => 'c', 'priority' => 'highest', 'status' => 'To Do']);
    makeIssue($this->project, $this->me, ['title' => 'a', 'priority' => 'medium', 'status' => 'In Review']);

    $keys = fn (string $sort) => $this->getJson("/api/v1/projects/WEB/issues?sort={$sort}")->assertOk()->json('data.*.key');

    expect($keys('-priority'))->toBe(['WEB-2', 'WEB-3', 'WEB-1'])
        ->and($keys('status'))->toBe(['WEB-2', 'WEB-3', 'WEB-1'])
        ->and($keys('title'))->toBe(['WEB-3', 'WEB-1', 'WEB-2'])
        ->and($keys('key'))->toBe(['WEB-1', 'WEB-2', 'WEB-3']);
});

test('invalid sort lists valid options', function () {
    $this->getJson('/api/v1/projects/WEB/issues?sort=color')
        ->assertUnprocessable()
        ->assertJsonPath('errors.sort.0', fn ($m) => str_contains($m, 'priority') && str_contains($m, '-updated'));
});

test('search spans my projects only and understands keys', function () {
    $api = makeProject($this->me, 'API');
    makeIssue($this->project, $this->me, ['title' => 'Login page']);
    makeIssue($api, $this->me, ['title' => 'Login endpoint']);
    $other = makeProject(User::factory()->create(), 'SECRET');
    makeIssue($other, $other->members()->first(), ['title' => 'Login secret']);

    expect($this->getJson('/api/v1/issues?q=login')->assertOk()->json('data.*.key'))->toEqualCanonicalizing(['WEB-1', 'API-1'])
        ->and($this->getJson('/api/v1/issues?q=api-1')->json('data.*.key'))->toBe(['API-1'])
        ->and($this->getJson('/api/v1/issues?q=login&project=API')->json('data.*.key'))->toBe(['API-1'])
        ->and($this->getJson('/api/v1/issues?q=SECRET-1')->json('data'))->toBe([]);
});

test('my issues are assigned to me, open, and ordered by priority then due date', function () {
    $api = makeProject($this->me, 'API');
    makeIssue($this->project, $this->me, ['assignee' => 'me@example.com', 'priority' => 'low']);
    makeIssue($this->project, $this->me, ['assignee' => 'me@example.com', 'status' => 'Done']);
    makeIssue($api, $this->me, ['assignee' => 'me@example.com', 'priority' => 'high', 'due_date' => '2026-12-01']);
    makeIssue($api, $this->me, ['assignee' => 'me@example.com', 'priority' => 'high', 'due_date' => '2026-11-01']);
    makeIssue($api, $this->me);

    expect($this->getJson('/api/v1/me/issues')->assertOk()->json('data.*.key'))->toBe(['API-2', 'API-1', 'WEB-1']);
});

test('project summary has status counts and active sprint progress', function () {
    addMember($this->project, User::factory()->create(), ProjectRole::Member);
    $this->postJson('/api/v1/projects/WEB/sprints', []);
    makeIssue($this->project, $this->me, ['sprint' => 'WEB Sprint 1', 'story_points' => 5, 'status' => 'Done']);
    makeIssue($this->project, $this->me, ['sprint' => 'WEB Sprint 1', 'story_points' => 3]);
    makeIssue($this->project, $this->me, ['type' => 'bug', 'due_date' => '2020-01-01']);
    $this->postJson('/api/v1/projects/WEB/sprints/WEB Sprint 1/start');

    $this->getJson('/api/v1/projects/WEB/summary')
        ->assertOk()
        ->assertJsonPath('data.statuses', [
            ['name' => 'To Do', 'count' => 2],
            ['name' => 'In Progress', 'count' => 0],
            ['name' => 'In Review', 'count' => 0],
            ['name' => 'Done', 'count' => 1],
        ])
        ->assertJsonPath('data.types.bug', 1)
        ->assertJsonPath('data.open', 2)
        ->assertJsonPath('data.overdue', 1)
        ->assertJsonPath('data.active_sprint.name', 'WEB Sprint 1')
        ->assertJsonPath('data.active_sprint.points_total', 8)
        ->assertJsonPath('data.active_sprint.points_done', 5)
        ->assertJsonPath('data.active_sprint.issues_done', 1)
        ->assertJsonPath('data.active_sprint.issue_count', 2);
});
