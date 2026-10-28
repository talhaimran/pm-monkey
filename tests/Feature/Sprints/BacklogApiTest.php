<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->project = makeProject($this->admin, 'WEB');
    apiAs($this->admin);
    $this->postJson('/api/v1/projects/WEB/sprints', []);
});

function sectionKeys(array $data, ?string $sprint): array
{
    $issues = $sprint === null
        ? $data['backlog']['issues']
        : collect($data['sprints'])->firstWhere('name', $sprint)['issues'];

    return collect($issues)->pluck('key')->all();
}

test('backlog shows open sprints and unscheduled issues, hiding epics and done issues', function () {
    makeIssue($this->project, $this->admin, ['sprint' => 'WEB Sprint 1']);
    makeIssue($this->project, $this->admin);
    makeIssue($this->project, $this->admin, ['type' => 'epic']);
    makeIssue($this->project, $this->admin, ['status' => 'Done']);
    makeIssue($this->project, $this->admin);

    $data = $this->getJson('/api/v1/projects/WEB/backlog')->assertOk()->json('data');

    expect(sectionKeys($data, 'WEB Sprint 1'))->toBe(['WEB-1'])
        ->and(sectionKeys($data, null))->toBe(['WEB-2', 'WEB-5'])
        ->and($data['sprints'][0]['state'])->toBe('planned');
});

test('rank_backlog reorders within the backlog', function () {
    makeIssue($this->project, $this->admin);
    makeIssue($this->project, $this->admin);

    $this->postJson('/api/v1/issues/WEB-2/rank', ['position' => 0])->assertOk();

    expect(sectionKeys($this->getJson('/api/v1/projects/WEB/backlog')->json('data'), null))->toBe(['WEB-2', 'WEB-1']);
});

test('ranking into a sprint and back to the backlog', function () {
    makeIssue($this->project, $this->admin, ['sprint' => 'WEB Sprint 1']);
    makeIssue($this->project, $this->admin);

    $this->postJson('/api/v1/issues/WEB-2/rank', ['sprint' => 'WEB Sprint 1', 'position' => 0])
        ->assertOk()
        ->assertJsonPath('data.sprint.name', 'WEB Sprint 1');
    expect(sectionKeys($this->getJson('/api/v1/projects/WEB/backlog')->json('data'), 'WEB Sprint 1'))->toBe(['WEB-2', 'WEB-1']);

    $this->postJson('/api/v1/issues/WEB-1/rank', ['sprint' => 'backlog'])->assertOk()->assertJsonPath('data.sprint', null);
});

test('board and filters understand sprint=active and sprint=backlog', function () {
    makeIssue($this->project, $this->admin, ['sprint' => 'WEB Sprint 1']);
    makeIssue($this->project, $this->admin);
    $this->postJson('/api/v1/projects/WEB/sprints/WEB Sprint 1/start');

    $keys = fn (string $q) => collect($this->getJson("/api/v1/projects/WEB/board?{$q}")->json('data.columns'))
        ->flatMap(fn ($c) => collect($c['issues'])->pluck('key'))->all();

    expect($keys('sprint=active'))->toBe(['WEB-1'])
        ->and($keys('sprint=backlog'))->toBe(['WEB-2'])
        ->and($keys('sprint=WEB Sprint 1'))->toBe(['WEB-1']);
});
