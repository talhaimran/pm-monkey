<?php

use App\Enums\AgentLevel;
use App\Enums\ProjectRole;
use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\CreateColumn;
use App\Mcp\Tools\CreateProject;
use App\Mcp\Tools\DeleteColumn;
use App\Mcp\Tools\DeleteProject;
use App\Mcp\Tools\UpdateColumn;
use App\Mcp\Tools\UpdateProject;
use App\Models\Project;
use App\Models\User;
use App\Services\MembershipService;

/*
 * Step A2 (decision A-D2): project settings, columns, members and invitations are people-only on REST and MCP.
 * The owner's role is checked first, then the agent rule. Labels and sprints stay open at Write; deletes need Delete.
 */

const PEOPLE_ONLY = 'Only people can change project settings. Do this in the web app.';

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Talha Imran']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->web = makeProject($this->owner, 'WEB');
    $this->sam = addMember($this->web, User::factory()->create(['email' => 'sam@example.com']), ProjectRole::Member);
    makeIssue($this->web, $this->owner);
});

test('an agent of a project Admin cannot change project settings, columns, members or invitations over REST', function (string $method, string $uri, array $body) {
    [, $plain] = makeAgent($this->owner, AgentLevel::Delete);

    asToken($plain)->json($method, $uri, $body)->assertForbidden()->assertJsonPath('message', PEOPLE_ONLY);
})->with([
    'create project' => ['POST', '/api/v1/projects', ['key' => 'NEW', 'name' => 'New']],
    'update project' => ['PATCH', '/api/v1/projects/WEB', ['name' => 'Renamed']],
    'delete project' => ['DELETE', '/api/v1/projects/WEB', ['confirm' => true]],
    'create column' => ['POST', '/api/v1/projects/WEB/columns', ['name' => 'Blocked']],
    'update column' => ['PATCH', '/api/v1/projects/WEB/columns/Done', ['name' => 'Shipped']],
    'delete column' => ['DELETE', '/api/v1/projects/WEB/columns/In Review', ['confirm' => true]],
    'invite' => ['POST', '/api/v1/projects/WEB/invitations', ['email' => 'new@example.com', 'role' => 'member']],
    'change a member' => ['PATCH', '/api/v1/projects/WEB/members/sam@example.com', ['role' => 'viewer']],
    'remove a member' => ['DELETE', '/api/v1/projects/WEB/members/sam@example.com', ['confirm' => true]],
]);

test('nothing changed after the refused REST calls', function () {
    [, $plain] = makeAgent($this->owner, AgentLevel::Delete);
    asToken($plain)->patchJson('/api/v1/projects/WEB', ['name' => 'Renamed'])->assertForbidden();
    asToken($plain)->deleteJson('/api/v1/projects/WEB', ['confirm' => true])->assertForbidden();

    expect(Project::sole()->name)->toBe('Website')
        ->and($this->web->columns()->count())->toBe(4)
        ->and($this->web->roleFor($this->sam))->toBe(ProjectRole::Member);
});

test('the matching MCP tools stay registered and refuse agents', function (string $tool, array $args) {
    apiAs($this->owner);

    PmServer::tool($tool, $args)->assertHasErrors([PEOPLE_ONLY]);
})->with([
    'create_project' => [CreateProject::class, ['key' => 'NEW', 'name' => 'New']],
    'update_project' => [UpdateProject::class, ['project' => 'WEB', 'name' => 'Renamed']],
    'delete_project' => [DeleteProject::class, ['project' => 'WEB', 'confirm' => true]],
    'create_column' => [CreateColumn::class, ['project' => 'WEB', 'name' => 'Blocked']],
    'update_column' => [UpdateColumn::class, ['project' => 'WEB', 'column' => 'Done', 'name' => 'Shipped']],
    'delete_column' => [DeleteColumn::class, ['project' => 'WEB', 'column' => 'In Review', 'confirm' => true]],
]);

test("the owner's role comes first: a Member's agent gets the Member message, not the agent one", function () {
    [, $plain] = makeAgent($this->sam, AgentLevel::Delete);

    asToken($plain)->patchJson('/api/v1/projects/WEB', ['name' => 'x'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Your role in WEB is member; only admins can do this.');
});

test('the owner keeps doing all of it in the web app', function () {
    $this->actingAs($this->owner)->patch(w('/projects/WEB'), ['name' => 'Renamed'])->assertSessionHasNoErrors();
    $this->post(w('/projects/WEB/columns'), ['name' => 'Blocked'])->assertSessionHasNoErrors();

    expect($this->web->fresh()->name)->toBe('Renamed')->and($this->web->columns()->count())->toBe(5);
});

test('labels and sprints still work at Write; their deletes need the Delete level', function () {
    [, $write] = makeAgent($this->owner, AgentLevel::Write);
    [, $delete] = makeAgent($this->owner, AgentLevel::Delete);

    asToken($write)->postJson('/api/v1/projects/WEB/labels', ['name' => 'bug'])->assertCreated();
    asToken($write)->patchJson('/api/v1/projects/WEB/labels/bug', ['name' => 'defect'])->assertOk();
    asToken($write)->postJson('/api/v1/projects/WEB/sprints', ['name' => 'Sprint 1'])->assertCreated();
    asToken($write)->postJson('/api/v1/projects/WEB/sprints/Sprint 1/start')->assertOk();
    asToken($write)->postJson('/api/v1/projects/WEB/sprints/Sprint 1/complete', ['confirm' => true, 'move_open_issues_to' => 'backlog'])->assertOk();

    asToken($write)->deleteJson('/api/v1/projects/WEB/labels/defect', ['confirm' => true])
        ->assertForbidden()->assertJsonPath('message', "This agent can't delete. Its owner can allow deletes in Settings → Agents.");
    asToken($delete)->deleteJson('/api/v1/projects/WEB/labels/defect', ['confirm' => true])->assertNoContent();
});

test('Review Focus 1: the owner demoted to Viewer between two agent calls makes the second write fail at once', function () {
    $alex = addMember($this->web, User::factory()->create(), ProjectRole::Member);
    [, $plain] = makeAgent($alex, AgentLevel::Write);

    asToken($plain)->patchJson('/api/v1/issues/WEB-1', ['title' => 'First'])->assertOk();

    app(MembershipService::class)->changeRole($this->web, $alex, ProjectRole::Viewer);

    asToken($plain)->patchJson('/api/v1/issues/WEB-1', ['title' => 'Second'])
        ->assertForbidden()
        ->assertJsonPath('message', "Your role in WEB is viewer; viewers can't make changes.");
    expect($this->web->issues()->sole()->title)->toBe('First');
});
