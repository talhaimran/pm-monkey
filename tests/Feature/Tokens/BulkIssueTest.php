<?php

use App\Enums\ProjectRole;
use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\BulkCreateIssues;
use App\Mcp\Tools\BulkUpdateIssues;
use App\Models\Activity;
use App\Models\Issue;
use App\Models\User;

beforeEach(function () {
    $this->me = User::factory()->create(['email' => 'me@example.com']);
    $this->project = makeProject($this->me, 'WEB');
    apiAs($this->me);
});

test('bulk create makes all issues in order', function () {
    $this->postJson('/api/v1/projects/WEB/issues/bulk', ['issues' => [
        ['title' => 'One'],
        ['title' => 'Two', 'type' => 'bug'],
        ['title' => 'Three', 'status' => 'Done'],
    ]])
        ->assertCreated()
        ->assertJsonPath('data.*.key', ['WEB-1', 'WEB-2', 'WEB-3']);
});

test('bulk create is all-or-nothing with indexed errors', function () {
    $this->postJson('/api/v1/projects/WEB/issues/bulk', ['issues' => [
        ['title' => 'Good'],
        ['title' => 'Bad', 'status' => 'Doing'],
    ]])
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn ($e) => isset($e['issues.1.status']) && str_contains($e['issues.1.status'][0], 'To Do'));

    $this->postJson('/api/v1/projects/WEB/issues/bulk', ['issues' => [['type' => 'bug']]])
        ->assertUnprocessable()->assertJsonValidationErrors('issues.0.title');

    expect(Issue::count())->toBe(0);
});

test('bulk operations are capped at 50', function () {
    $this->postJson('/api/v1/projects/WEB/issues/bulk', ['issues' => array_fill(0, 51, ['title' => 'x'])])
        ->assertUnprocessable()->assertJsonValidationErrors('issues');
});

test('bulk update changes several issues by key and logs each', function () {
    makeIssue($this->project, $this->me);
    makeIssue($this->project, $this->me);

    $this->patchJson('/api/v1/issues/bulk', ['updates' => [
        ['key' => 'WEB-1', 'status' => 'Done'],
        ['key' => 'web-2', 'priority' => 'high', 'assignee' => 'me@example.com'],
    ]])
        ->assertOk()
        ->assertJsonPath('data.0.status', 'Done')
        ->assertJsonPath('data.1.assignee.email', 'me@example.com');

    expect(Activity::where('action', 'updated')->count())->toBe(2);
});

test('bulk update rolls back on any bad item', function () {
    makeIssue($this->project, $this->me);

    $this->patchJson('/api/v1/issues/bulk', ['updates' => [
        ['key' => 'WEB-1', 'title' => 'Changed'],
        ['key' => 'WEB-99', 'title' => 'Missing'],
    ]])
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn ($e) => isset($e['updates.1.key']));

    expect(Issue::first()->title)->toBe('Issue');
});

test('bulk update checks permission per issue', function () {
    $viewer = addMember($this->project, User::factory()->create(), ProjectRole::Viewer);
    makeIssue($this->project, $this->me);

    apiAs($viewer);
    $this->patchJson('/api/v1/issues/bulk', ['updates' => [['key' => 'WEB-1', 'title' => 'x']]])->assertForbidden();
});

test('bulk tools over mcp', function () {
    PmServer::tool(BulkCreateIssues::class, ['project' => 'WEB', 'issues' => [['title' => 'A'], ['title' => 'B']]])
        ->assertOk()->assertSee('Created 2 issues: WEB-1, WEB-2');
    PmServer::tool(BulkUpdateIssues::class, ['updates' => [['key' => 'WEB-1', 'status' => 'Done'], ['key' => 'WEB-2', 'status' => 'Nope']]])
        ->assertHasErrors(['[updates.1] Unknown status']);
    PmServer::tool(BulkUpdateIssues::class, ['updates' => [['key' => 'WEB-1', 'status' => 'Done']]])
        ->assertOk()->assertSee('Updated 1 issue: WEB-1');
});
