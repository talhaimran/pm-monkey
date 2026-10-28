<?php

use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\CompleteSprint;
use App\Mcp\Tools\CreateSprint;
use App\Mcp\Tools\DeleteSprint;
use App\Mcp\Tools\GetBacklog;
use App\Mcp\Tools\ListSprints;
use App\Mcp\Tools\RankBacklog;
use App\Mcp\Tools\StartSprint;
use App\Mcp\Tools\UpdateIssue;
use App\Mcp\Tools\UpdateSprint;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->project = makeProject($this->admin, 'WEB');
});

test('sprint lifecycle over mcp', function () {
    apiAs($this->admin);
    makeIssue($this->project, $this->admin);

    PmServer::tool(CreateSprint::class, ['project' => 'WEB', 'goal' => 'Login'])->assertOk()->assertSee('Created WEB Sprint 1');
    PmServer::tool(UpdateSprint::class, ['project' => 'WEB', 'sprint' => 'WEB Sprint 1', 'name' => 'Alpha'])->assertOk();
    PmServer::tool(UpdateIssue::class, ['issue' => 'WEB-1', 'sprint' => 'Alpha'])->assertOk()->assertSee('"name":"Alpha"');
    PmServer::tool(StartSprint::class, ['project' => 'WEB', 'sprint' => 'Alpha'])->assertOk()->assertSee('Started Alpha');
    PmServer::tool(ListSprints::class, ['project' => 'WEB'])->assertOk()->assertSee('"state":"active"');
    PmServer::tool(CompleteSprint::class, ['project' => 'WEB', 'sprint' => 'Alpha', 'move_open_issues_to' => 'backlog'])
        ->assertHasErrors(['confirm: true']);
    PmServer::tool(CompleteSprint::class, ['project' => 'WEB', 'sprint' => 'Alpha', 'move_open_issues_to' => 'backlog', 'confirm' => true])
        ->assertOk()
        ->assertSee('1 unfinished issue moved to the backlog');
});

test('backlog tools over mcp', function () {
    apiAs($this->admin);
    makeIssue($this->project, $this->admin);
    makeIssue($this->project, $this->admin);

    PmServer::tool(RankBacklog::class, ['issue' => 'WEB-2', 'position' => 0])->assertOk();
    PmServer::tool(GetBacklog::class, ['project' => 'WEB'])->assertOk()->assertSee('Backlog: 2');
    PmServer::tool(CreateSprint::class, ['project' => 'WEB', 'name' => 'Temp']);
    PmServer::tool(DeleteSprint::class, ['project' => 'WEB', 'sprint' => 'Temp'])->assertOk();
});

test('backlog page renders sprints and backlog', function () {
    makeIssue($this->project, $this->admin);

    $this->actingAs($this->admin)->post('/projects/WEB/sprints', [])->assertRedirect();
    $this->actingAs($this->admin)->get('/projects/WEB/backlog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Backlog')
            ->where('backlog.sprints.0.name', 'WEB Sprint 1')
            ->where('backlog.backlog.issues.0.key', 'WEB-1')
            ->has('options.columns'));
});

test('web start, rank and complete', function () {
    makeIssue($this->project, $this->admin);
    $this->actingAs($this->admin)->post('/projects/WEB/sprints', []);
    $sprint = $this->project->sprints()->first();

    $this->actingAs($this->admin)->post('/issues/WEB-1/rank', ['sprint' => 'WEB Sprint 1'])->assertRedirect();
    $this->actingAs($this->admin)->post("/projects/WEB/sprints/{$sprint->id}/start", [])->assertRedirect();
    $this->actingAs($this->admin)->post("/projects/WEB/sprints/{$sprint->id}/complete", ['confirm' => true, 'move_open_issues_to' => 'backlog'])
        ->assertRedirect();

    expect($sprint->fresh()->state->value)->toBe('completed');
});
