<?php

use App\Enums\AgentLevel;
use App\Enums\AgentStatus;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Agent;
use App\Models\User;
use App\Services\AgentService;
use App\Services\CommentService;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Talha Imran']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->web = makeProject($this->owner, 'WEB');
    $this->admin = User::factory()->create(['name' => 'Alex Admin']);
    $this->ws->addMember($this->admin, WorkspaceRole::Admin);
    $this->member = addMember($this->web, User::factory()->create(['name' => 'Sam Member']), ProjectRole::Member);
    asWorkspaceMember($this->member);
});

test('the page lists my agents with level, projects, status, last used and expiry', function () {
    $this->actingAs($this->member);
    [$mine] = app(AgentService::class)->create($this->member, ['name' => 'deploy-bot', 'level' => 'write', 'projects' => ['WEB'], 'expires_in_days' => 30]);
    makeAgent($this->owner, AgentLevel::Read, name: 'owners-bot');

    $this->get(w('/settings/agents'))->assertInertia(fn (Assert $page) => $page
        ->component('Agents/Index')
        ->has('agents', 1)
        ->where('agents.0.name', 'deploy-bot')
        ->where('agents.0.level', 'write')
        ->where('agents.0.status', 'active')
        ->where('agents.0.projects', ['WEB'])
        ->where('agents.0.last_used_at', null)
        ->where('agents.0.expires_at', fn ($v) => $v !== null)
        ->has('others', 0)
        ->where('can.manageAll', false));
});

test('a workspace Admin also sees every agent in the workspace, to pause, resume or revoke but not edit or regenerate', function () {
    [$theirs] = makeAgent($this->member, AgentLevel::Write, name: 'sams-bot');
    $this->actingAs($this->admin);

    $this->get(w('/settings/agents'))->assertInertia(fn (Assert $page) => $page
        ->has('agents', 0)
        ->has('others', 1)
        ->where('others.0.name', 'sams-bot')
        ->where('others.0.owner.name', 'Sam Member')
        ->where('can.manageAll', true));

    $this->post(w("/settings/agents/{$theirs->id}/pause"))->assertRedirect();
    expect($theirs->fresh()->status)->toBe(AgentStatus::Paused);
    $this->post(w("/settings/agents/{$theirs->id}/resume"))->assertRedirect();
    expect($theirs->fresh()->status)->toBe(AgentStatus::Active);
    $this->patch(w("/settings/agents/{$theirs->id}"), ['name' => 'renamed', 'level' => 'read'])->assertForbidden();
    $this->post(w("/settings/agents/{$theirs->id}/regenerate"))->assertForbidden();
    $this->delete(w("/settings/agents/{$theirs->id}"))->assertRedirect();
    expect($theirs->fresh()->status)->toBe(AgentStatus::Revoked);
});

test('a Member cannot pause, resume, edit, regenerate or revoke someone else\'s agent', function () {
    [$ownersAgent] = makeAgent($this->owner, AgentLevel::Write, name: 'owners-bot');
    $this->actingAs($this->member);

    $this->post(w("/settings/agents/{$ownersAgent->id}/pause"))->assertForbidden();
    $this->post(w("/settings/agents/{$ownersAgent->id}/resume"))->assertForbidden();
    $this->patch(w("/settings/agents/{$ownersAgent->id}"), ['name' => 'x', 'level' => 'read'])->assertForbidden();
    $this->post(w("/settings/agents/{$ownersAgent->id}/regenerate"))->assertForbidden();
    $this->delete(w("/settings/agents/{$ownersAgent->id}"))->assertForbidden();
    expect($ownersAgent->fresh()->status)->toBe(AgentStatus::Active);
});

test('creating shows the token exactly once and never again on reload', function () {
    $this->actingAs($this->owner);

    $this->post(w('/settings/agents'), ['name' => 'claude', 'level' => 'write', 'projects' => ['WEB'], 'expires_in_days' => 90])
        ->assertRedirect()->assertSessionHas('token')->assertSessionHas('token_agent', 'claude');
    $agent = Agent::sole();
    expect($agent->permission_level)->toBe(AgentLevel::Write)->and($agent->projects()->pluck('key')->all())->toBe(['WEB']);
    $plain = session('token');

    $this->get(w('/settings/agents'))->assertInertia(fn (Assert $page) => $page->where('flash.token', $plain));
    $this->get(w('/settings/agents'))->assertInertia(fn (Assert $page) => $page->where('flash.token', null)->missing('agents.0.token'));
    expect(json_encode($this->get(w('/settings/agents'))->viewData('page')))->not->toContain(explode('|', $plain)[1]);
});

test('a bad level, a missing name and a foreign project are validation errors', function () {
    $this->actingAs($this->member);

    $this->post(w('/settings/agents'), ['name' => '', 'level' => 'god'])->assertSessionHasErrors(['name', 'level']);
    makeProject(User::factory()->create(), 'SECRET');
    asWorkspaceMember($this->member);
    $this->post(w('/settings/agents'), ['name' => 'x', 'level' => 'read', 'projects' => ['SECRET']])->assertSessionHasErrors('projects');
    expect(Agent::count())->toBe(0);
});

test('the owner edits, regenerates and the new token is shown once', function () {
    [$agent, $old] = makeAgent($this->owner, AgentLevel::Read, name: 'bot');
    $this->actingAs($this->owner);

    $this->patch(w("/settings/agents/{$agent->id}"), ['name' => 'bot2', 'description' => 'Ships', 'level' => 'write', 'projects' => ['WEB']])->assertRedirect();
    expect($agent->fresh()->name)->toBe('bot2')->and($agent->fresh()->permission_level)->toBe(AgentLevel::Write);

    $this->post(w("/settings/agents/{$agent->id}/regenerate"))->assertRedirect()->assertSessionHas('token')->assertSessionHas('token_agent', 'bot2');
    expect(session('token'))->not->toBe($old)->and(PersonalAccessToken::count())->toBe(1);
});

test('an agent of another workspace is a 404, not a 403', function () {
    $other = User::factory()->create();
    $otherWs = makeWorkspace($other, 'beta');
    [$foreign] = makeAgent($other, AgentLevel::Read, name: 'foreign');
    $this->actingAs($this->owner);
    useWorkspace($this->ws);

    $this->post(w("/settings/agents/{$foreign->id}/pause"))->assertNotFound();
    expect($foreign->fresh()->status)->toBe(AgentStatus::Active)->and($otherWs->id)->not->toBe($this->ws->id);
});

test('/settings/tokens permanently redirects to /settings/agents', function () {
    $this->actingAs($this->owner)->get('/acme/settings/tokens')->assertStatus(301)->assertRedirect('/acme/settings/agents');
});

test('an agent token cannot use any settings/agents route', function () {
    [$agent, $plain] = makeAgent($this->owner, AgentLevel::Delete, name: 'bot');

    foreach ([['get', ''], ['post', ''], ['patch', "/{$agent->id}"], ['post', "/{$agent->id}/pause"], ['post', "/{$agent->id}/resume"], ['post', "/{$agent->id}/regenerate"], ['delete', "/{$agent->id}"]] as [$verb, $suffix]) {
        asToken($plain, ['Accept' => 'application/json'])->{$verb.'Json'}("/acme/settings/agents{$suffix}", ['name' => 'x', 'level' => 'read'])->assertUnauthorized();
    }
    expect($agent->fresh()->status)->toBe(AgentStatus::Active);
});

test('activity and comments written by an agent carry the agent label and the owner, not just the owner\'s name', function () {
    $issue = makeIssue($this->web, $this->owner, ['title' => 'Thing']);
    $plain = makeAgent($this->owner, AgentLevel::Write, name: 'deploy-bot')[1];
    asToken($plain)->postJson('/api/v1/issues/WEB-1/comments', ['body' => 'from the bot'])->assertCreated();
    asToken($plain)->patchJson('/api/v1/issues/WEB-1', ['priority' => 'high'])->assertOk();
    app(CommentService::class)->add($issue, $this->owner, 'from a person');

    app('auth')->forgetGuards();
    $this->flushHeaders();
    $this->actingAs($this->owner)->get(w('/issues/WEB-1'))->assertInertia(fn (Assert $page) => $page
        ->where('issue.comments.0.agent.name', 'deploy-bot')
        ->where('issue.comments.0.agent.owner', 'Talha Imran')
        ->where('issue.comments.1.agent', null)
        ->where('activity', fn ($rows) => collect($rows)->where('agent.name', 'deploy-bot')->where('agent.owner', 'Talha Imran')->count() >= 1
            && collect($rows)->whereNull('agent')->count() >= 1));

    $this->get(w('/settings/audit-log'))->assertInertia(fn (Assert $page) => $page
        ->where('entries.data', fn ($rows) => collect($rows)->where('action', 'agent.created')->isNotEmpty()));
});

test('agents never appear in the assignee picker props', function () {
    makeAgent($this->owner, AgentLevel::Write, name: 'deploy-bot');
    makeIssue($this->web, $this->owner);

    $this->actingAs($this->owner)->get(w('/issues/WEB-1'))->assertInertia(fn (Assert $page) => $page
        ->where('options.members', fn ($members) => collect($members)->pluck('name')->doesntContain('deploy-bot')));
    $this->get(w('/projects/WEB'))->assertOk()->assertDontSee('deploy-bot');
});
