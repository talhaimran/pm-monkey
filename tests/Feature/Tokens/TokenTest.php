<?php

use App\Enums\AgentLevel;
use App\Enums\AgentStatus;
use App\Enums\ProjectRole;
use App\Models\Agent;
use App\Models\Issue;
use App\Models\User;
use App\Services\CommentService;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->me = User::factory()->create();
    $this->web = makeProject($this->me, 'WEB');
    $this->api = makeProject($this->me, 'API');
    makeIssue($this->web, $this->me, ['title' => 'Web thing']);
    makeIssue($this->api, $this->me, ['title' => 'Api thing']);
});

test('creating a read-only token flashes the plain token once', function () {
    $this->actingAs($this->me)
        ->post(w('/settings/agents'), ['name' => 'claude', 'level' => 'read'])
        ->assertRedirect()
        ->assertSessionHas('token');

    $agent = Agent::sole();
    expect($agent->name)->toBe('claude')->and($agent->permission_level)->toBe(AgentLevel::Read)
        ->and(PersonalAccessToken::sole()->agent_id)->toBe($agent->id);

    $this->actingAs($this->me)->get(w('/settings/agents'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Agents/Index')
            ->where('agents.0.name', 'claude')
            ->where('agents.0.level', 'read')
            ->where('agents.0.projects', null)
            ->where('projects.0.key', 'API'));
});

test('read-write tokens can be scoped to projects', function () {
    $this->actingAs($this->me)->post(w('/settings/agents'), ['name' => 'ci', 'level' => 'write', 'projects' => ['WEB']]);

    $agent = Agent::sole();
    expect($agent->permission_level)->toBe(AgentLevel::Write)->and($agent->projects()->pluck('key')->all())->toBe(['WEB']);
});

test('tokens cannot be scoped to projects you are not in', function () {
    makeProject(User::factory()->create(), 'SECRET');
    asWorkspaceMember($this->me);

    $this->actingAs($this->me)->post(w('/settings/agents'), ['name' => 'x', 'level' => 'read', 'projects' => ['SECRET']])
        ->assertSessionHasErrors('projects');
});

test('a Member can revoke only their own agents', function () {
    [$mine] = makeAgent($this->me, AgentLevel::Read, name: 'mine');
    asWorkspaceMember($this->me); // an Owner or Admin may revoke anyone's
    [$theirs] = makeAgent(addMember($this->web, User::factory()->create(), ProjectRole::Member), AgentLevel::Read, name: 'theirs');

    $this->actingAs($this->me)->delete(w("/settings/agents/{$theirs->id}"))->assertForbidden();
    $this->actingAs($this->me)->delete(w("/settings/agents/{$mine->id}"))->assertRedirect();

    expect(PersonalAccessToken::pluck('name')->all())->toBe(['theirs'])
        ->and($mine->fresh()->status)->toBe(AgentStatus::Revoked);
});

test('real tokens record last use and read-only ones cannot write', function () {
    $plain = makeAgent($this->me, AgentLevel::Read, name: 'ro')[1];

    $this->withToken($plain)->getJson('/api/v1/projects')->assertOk();
    $this->withToken($plain)->postJson('/api/v1/projects/WEB/issues', ['title' => 'x'])
        ->assertForbidden()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'read-only'));

    expect(PersonalAccessToken::sole()->last_used_at)->not->toBeNull();
});

test('scoped tokens only see their projects', function () {
    $plain = makeAgent($this->me, AgentLevel::Write, ['WEB'], name: 'scoped')[1];
    $api = fn () => $this->withToken($plain);

    expect($api()->getJson('/api/v1/projects')->json('data.*.key'))->toBe(['WEB'])
        ->and($api()->getJson('/api/v1/issues?q=thing')->json('data.*.key'))->toBe(['WEB-1'])
        ->and($api()->getJson('/api/v1/me/issues')->assertOk()->json('data'))->toBe([]);

    $api()->getJson('/api/v1/projects/API')
        ->assertNotFound()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Projects you can access: WEB.'));
    $api()->getJson('/api/v1/issues/API-1')->assertNotFound();
    $api()->postJson('/api/v1/projects', ['key' => 'NEW', 'name' => 'New'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only people can change project settings. Do this in the web app.');
    $api()->postJson('/api/v1/projects/WEB/issues', ['title' => 'ok'])->assertCreated();
});

test('scoped tokens cannot reach comments in other projects by id', function () {
    $comment = app(CommentService::class)->add(Issue::where('number', 1)->where('project_id', $this->api->id)->first(), $this->me, 'hidden');
    $plain = makeAgent($this->me, AgentLevel::Write, ['WEB'], name: 'scoped')[1];

    $this->withToken($plain)->patchJson("/api/v1/comments/{$comment->id}", ['body' => 'x'])->assertNotFound();
});
