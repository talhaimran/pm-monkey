<?php

use App\Enums\ProjectRole;
use App\Models\Activity;
use App\Models\Issue;
use App\Models\Sprint;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->project = makeProject($this->admin, 'WEB');
    apiAs($this->admin);
});

test('sprints get default names and names are unique', function () {
    $this->postJson('/api/v1/projects/WEB/sprints', [])
        ->assertCreated()
        ->assertJsonPath('data.name', 'WEB Sprint 1')
        ->assertJsonPath('data.state', 'planned');
    $this->postJson('/api/v1/projects/WEB/sprints', ['goal' => 'Ship login'])->assertJsonPath('data.name', 'WEB Sprint 2');

    $this->postJson('/api/v1/projects/WEB/sprints', ['name' => 'web sprint 1'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/api/v1/projects/WEB/sprints', ['name' => 'Backlog'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

test('issues join sprints by name and the change is logged', function () {
    $this->postJson('/api/v1/projects/WEB/sprints', []);
    makeIssue($this->project, $this->admin);

    $this->patchJson('/api/v1/issues/WEB-1', ['sprint' => 'web sprint 1'])
        ->assertOk()
        ->assertJsonPath('data.sprint.name', 'WEB Sprint 1');

    expect(Activity::where('action', 'updated')->sole()->changes)
        ->toEqual(['sprint' => ['from' => null, 'to' => 'WEB Sprint 1']]);

    $this->patchJson('/api/v1/issues/WEB-1', ['sprint' => 'Nope'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.sprint.0', fn ($m) => str_contains($m, 'WEB Sprint 1'));

    $this->patchJson('/api/v1/issues/WEB-1', ['sprint' => null])->assertJsonPath('data.sprint', null);
});

test('starting a sprint defaults dates and only one can be active', function () {
    $this->postJson('/api/v1/projects/WEB/sprints', []);
    $this->postJson('/api/v1/projects/WEB/sprints', []);

    $this->postJson('/api/v1/projects/WEB/sprints/WEB Sprint 1/start')
        ->assertOk()
        ->assertJsonPath('data.state', 'active')
        ->assertJsonPath('data.start_date', now()->toDateString())
        ->assertJsonPath('data.end_date', now()->addDays(14)->toDateString());

    $this->postJson('/api/v1/projects/WEB/sprints/WEB Sprint 2/start')
        ->assertUnprocessable()
        ->assertJsonPath('errors.sprint.0', fn ($m) => str_contains($m, 'WEB Sprint 1 is already active'));
});

test('sprint dates must be in order', function () {
    $this->postJson('/api/v1/projects/WEB/sprints', ['start_date' => '2026-11-10', 'end_date' => '2026-11-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('end_date');
});

test('completing requires confirm and moves unfinished issues to the backlog', function () {
    $sprint = $this->postJson('/api/v1/projects/WEB/sprints', [])->json('data.name');
    makeIssue($this->project, $this->admin, ['sprint' => $sprint, 'status' => 'Done', 'story_points' => 3]);
    makeIssue($this->project, $this->admin, ['sprint' => $sprint, 'story_points' => 5]);
    $this->postJson("/api/v1/projects/WEB/sprints/{$sprint}/start");

    $this->getJson("/api/v1/projects/WEB/sprints/{$sprint}")
        ->assertJsonPath('data.points_total', 8)
        ->assertJsonPath('data.points_done', 3);

    $this->postJson("/api/v1/projects/WEB/sprints/{$sprint}/complete", ['move_open_issues_to' => 'backlog'])
        ->assertUnprocessable()->assertJsonValidationErrors('confirm');

    $this->postJson("/api/v1/projects/WEB/sprints/{$sprint}/complete", ['confirm' => true, 'move_open_issues_to' => 'backlog'])
        ->assertOk()
        ->assertJsonPath('data.state', 'completed');

    expect(Issue::where('number', 1)->value('sprint_id'))->not->toBeNull()
        ->and(Issue::where('number', 2)->value('sprint_id'))->toBeNull();
});

test('completing can move unfinished issues into a planned sprint', function () {
    $this->postJson('/api/v1/projects/WEB/sprints', []);
    $this->postJson('/api/v1/projects/WEB/sprints', []);
    makeIssue($this->project, $this->admin, ['sprint' => 'WEB Sprint 1']);
    $this->postJson('/api/v1/projects/WEB/sprints/WEB Sprint 1/start');

    $this->postJson('/api/v1/projects/WEB/sprints/WEB Sprint 1/complete', ['confirm' => true, 'move_open_issues_to' => 'Nope'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.move_open_issues_to.0', fn ($m) => str_contains($m, 'backlog, WEB Sprint 2'));

    $this->postJson('/api/v1/projects/WEB/sprints/WEB Sprint 1/complete', ['confirm' => true, 'move_open_issues_to' => 'WEB Sprint 2'])->assertOk();

    $this->getJson('/api/v1/issues/WEB-1')->assertJsonPath('data.sprint.name', 'WEB Sprint 2');
});

test('only active sprints complete and completed sprints are closed', function () {
    $this->postJson('/api/v1/projects/WEB/sprints', []);
    makeIssue($this->project, $this->admin);

    $this->postJson('/api/v1/projects/WEB/sprints/WEB Sprint 1/complete', ['confirm' => true, 'move_open_issues_to' => 'backlog'])
        ->assertUnprocessable()->assertJsonValidationErrors('sprint');

    $this->postJson('/api/v1/projects/WEB/sprints/WEB Sprint 1/start');
    $this->postJson('/api/v1/projects/WEB/sprints/WEB Sprint 1/complete', ['confirm' => true, 'move_open_issues_to' => 'backlog']);

    $this->patchJson('/api/v1/issues/WEB-1', ['sprint' => 'WEB Sprint 1'])
        ->assertUnprocessable()->assertJsonValidationErrors('sprint');
    $this->patchJson('/api/v1/projects/WEB/sprints/WEB Sprint 1', ['goal' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('sprint');
});

test('only planned sprints can be deleted and their issues return to the backlog', function () {
    $this->postJson('/api/v1/projects/WEB/sprints', []);
    makeIssue($this->project, $this->admin, ['sprint' => 'WEB Sprint 1']);

    $this->deleteJson('/api/v1/projects/WEB/sprints/WEB Sprint 1')->assertNoContent();
    expect(Issue::first()->sprint_id)->toBeNull();

    $this->postJson('/api/v1/projects/WEB/sprints', ['name' => 'Live']);
    $this->postJson('/api/v1/projects/WEB/sprints/Live/start');
    $this->deleteJson('/api/v1/projects/WEB/sprints/Live')->assertUnprocessable();
});

test('sprints are listed and addressable by id', function () {
    $id = $this->postJson('/api/v1/projects/WEB/sprints', [])->json('data.id');

    $this->getJson('/api/v1/projects/WEB/sprints')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->getJson("/api/v1/projects/WEB/sprints/{$id}")->assertOk()->assertJsonPath('data.name', 'WEB Sprint 1');
    $this->getJson('/api/v1/projects/WEB/sprints/Nope')
        ->assertNotFound()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'WEB Sprint 1'));
});

test('viewers cannot manage sprints', function () {
    $viewer = addMember($this->project, User::factory()->create(), ProjectRole::Viewer);

    apiAs($viewer);
    $this->postJson('/api/v1/projects/WEB/sprints', [])->assertForbidden();
    expect(Sprint::count())->toBe(0);
});
