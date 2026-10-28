<?php

use App\Enums\ProjectRole;
use App\Models\Activity;
use App\Models\Issue;
use App\Models\Label;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->project = makeProject($this->admin, 'WEB');
    apiAs($this->admin);
});

/** Keys in a board column, top to bottom. */
function columnKeys(array $board, string $column): array
{
    return collect(collect($board['columns'])->firstWhere('name', $column)['issues'])->pluck('key')->all();
}

test('board lists columns in order with issues bottom-appended and epics hidden', function () {
    makeIssue($this->project, $this->admin, ['title' => 'A']);
    makeIssue($this->project, $this->admin, ['title' => 'B']);
    makeIssue($this->project, $this->admin, ['title' => 'Epic', 'type' => 'epic']);
    makeIssue($this->project, $this->admin, ['title' => 'C', 'status' => 'Done']);

    $board = $this->getJson('/api/v1/projects/WEB/board')->assertOk()->json('data');

    expect(collect($board['columns'])->pluck('name')->all())->toBe(['To Do', 'In Progress', 'In Review', 'Done'])
        ->and(columnKeys($board, 'To Do'))->toBe(['WEB-1', 'WEB-2'])
        ->and(columnKeys($board, 'Done'))->toBe(['WEB-4'])
        ->and($board['truncated'])->toBeFalse();
});

test('board filters by assignee, type, priority, label and search', function () {
    addMember($this->project, User::factory()->create(['email' => 'dev@example.com']), ProjectRole::Member);
    Label::create(['project_id' => $this->project->id, 'name' => 'ui', 'color' => '#000000']);
    makeIssue($this->project, $this->admin, ['title' => 'Login bug', 'type' => 'bug', 'assignee' => 'dev@example.com']);
    makeIssue($this->project, $this->admin, ['title' => 'Signup page', 'priority' => 'high', 'labels' => ['ui']]);
    makeIssue($this->project, $this->admin, ['title' => 'Refactor']);

    $keys = fn (string $query) => collect($this->getJson("/api/v1/projects/WEB/board?{$query}")->assertOk()->json('data.columns'))
        ->flatMap(fn ($c) => collect($c['issues'])->pluck('key'))->all();

    expect($keys('assignee=dev@example.com'))->toBe(['WEB-1'])
        ->and($keys('assignee=unassigned'))->toBe(['WEB-2', 'WEB-3'])
        ->and($keys('type=bug'))->toBe(['WEB-1'])
        ->and($keys('priority=high'))->toBe(['WEB-2'])
        ->and($keys('label=UI'))->toBe(['WEB-2'])
        ->and($keys('q=sign'))->toBe(['WEB-2'])
        ->and($keys('q=web-3'))->toBe(['WEB-3']);
});

test('invalid filter values list the valid options', function () {
    $this->getJson('/api/v1/projects/WEB/board?type=saga')
        ->assertUnprocessable()
        ->assertJsonPath('errors.type.0', fn ($m) => str_contains($m, 'task, bug, story, epic'));
});

test('moving to another column at a position changes status and logs it', function () {
    makeIssue($this->project, $this->admin, ['status' => 'In Progress']);
    makeIssue($this->project, $this->admin, ['status' => 'In Progress']);
    makeIssue($this->project, $this->admin);

    $this->postJson('/api/v1/issues/WEB-3/move', ['status' => 'in progress', 'position' => 1])
        ->assertOk()
        ->assertJsonPath('data.status', 'In Progress');

    $board = $this->getJson('/api/v1/projects/WEB/board')->json('data');
    expect(columnKeys($board, 'In Progress'))->toBe(['WEB-1', 'WEB-3', 'WEB-2'])
        ->and(Activity::where('action', 'updated')->sole()->changes)->toEqual(['status' => ['from' => 'To Do', 'to' => 'In Progress']]);
});

test('reordering within a column is not logged', function () {
    makeIssue($this->project, $this->admin);
    makeIssue($this->project, $this->admin);

    $this->postJson('/api/v1/issues/WEB-2/move', ['position' => 0])->assertOk();

    expect(columnKeys($this->getJson('/api/v1/projects/WEB/board')->json('data'), 'To Do'))->toBe(['WEB-2', 'WEB-1'])
        ->and(Activity::where('action', 'updated')->count())->toBe(0);
});

test('after places an issue directly after another', function () {
    foreach (range(1, 3) as $i) {
        makeIssue($this->project, $this->admin);
    }

    $this->postJson('/api/v1/issues/WEB-1/move', ['after' => 'WEB-2'])->assertOk();

    expect(columnKeys($this->getJson('/api/v1/projects/WEB/board')->json('data'), 'To Do'))->toBe(['WEB-2', 'WEB-1', 'WEB-3']);
});

test('repeated moves into the same gap stay ordered (renumbering)', function () {
    foreach (range(1, 3) as $i) {
        makeIssue($this->project, $this->admin);
    }

    // Each move takes a midpoint between WEB-1 and the previously moved issue; gaps run out after ~10 moves.
    foreach (range(1, 15) as $i) {
        $moving = $i % 2 ? 'WEB-3' : 'WEB-2';
        $this->postJson("/api/v1/issues/{$moving}/move", ['position' => 1])->assertOk();
    }

    $keys = columnKeys($this->getJson('/api/v1/projects/WEB/board')->json('data'), 'To Do');
    expect($keys)->toBe(['WEB-1', 'WEB-3', 'WEB-2'])
        ->and(Issue::pluck('rank')->unique()->count())->toBe(3);
});

test('move errors explain the fix', function () {
    makeIssue($this->project, $this->admin);
    makeIssue($this->project, $this->admin, ['status' => 'Done']);

    $this->postJson('/api/v1/issues/WEB-1/move', ['status' => 'Doing'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', fn ($m) => str_contains($m, 'To Do, In Progress, In Review, Done'));

    $this->postJson('/api/v1/issues/WEB-1/move', ['status' => 'In Review', 'after' => 'WEB-2'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('after');

    $this->postJson('/api/v1/issues/WEB-1/move', ['position' => 0, 'after' => 'WEB-2'])
        ->assertUnprocessable();
});

test('viewers cannot move issues', function () {
    makeIssue($this->project, $this->admin);
    $viewer = addMember($this->project, User::factory()->create(), ProjectRole::Viewer);

    apiAs($viewer);
    $this->getJson('/api/v1/projects/WEB/board')->assertOk();
    $this->postJson('/api/v1/issues/WEB-1/move', ['status' => 'Done'])->assertForbidden();
});
