<?php

use App\Enums\AgentLevel;
use App\Enums\AgentStatus;
use App\Enums\Entitlement;
use App\Enums\ProjectRole;
use App\Exceptions\PlanLimitReached;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AgentService;
use App\Services\Entitlements;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Talha Imran']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->web = makeProject($this->owner, 'WEB');
    $this->api = makeProject($this->owner, 'API');
    $this->agents = app(AgentService::class);
});

test('create returns a token that works on the API, stored hashed on a token row linked to the agent', function () {
    [$agent, $plain] = $this->agents->create($this->owner, ['name' => 'deploy-bot', 'description' => 'Ships things', 'level' => 'write']);

    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($agent->workspace_id)->toBe($this->ws->id)
        ->and($agent->owner_user_id)->toBe($this->owner->id)
        ->and($agent->permission_level)->toBe(AgentLevel::Write)
        ->and($agent->status)->toBe(AgentStatus::Active)
        ->and($agent->label())->toBe('deploy-bot');

    $token = PersonalAccessToken::sole();
    expect($token->agent_id)->toBe($agent->id)
        ->and($token->workspace_id)->toBe($this->ws->id)
        ->and($token->token)->toBe(hash('sha256', explode('|', $plain, 2)[1]))
        ->and($token->token)->not->toContain(explode('|', $plain, 2)[1]);

    asToken($plain)->getJson('/api/v1/projects')->assertOk()->assertJsonCount(2, 'data');
});

test('agents_per_user refuses a third active or paused agent; revoked ones do not count', function () {
    app(Entitlements::class)->set($this->ws, Entitlement::AgentsPerUser, '2', 'override');
    [$first] = $this->agents->create($this->owner, ['name' => 'one', 'level' => 'read']);
    [$second] = $this->agents->create($this->owner, ['name' => 'two', 'level' => 'read']);
    $this->agents->pause($second, $this->owner);

    expect(fn () => $this->agents->create($this->owner, ['name' => 'three', 'level' => 'read']))
        ->toThrow(PlanLimitReached::class, 'Your plan allows 2 agents.');
    expect(AuditLog::where('action', 'plan_limit.reached')->sole()->metadata)->toEqual(['limit' => 'agents_per_user', 'allowed' => 2, 'current' => 2]);

    $this->agents->revoke($first, $this->owner);
    [$third] = $this->agents->create($this->owner, ['name' => 'three', 'level' => 'read']);
    expect($third->exists)->toBeTrue();

    // Someone else's agents don't count against mine.
    $alex = User::factory()->create();
    addMember($this->web, $alex, ProjectRole::Member);
    expect($this->agents->create($alex, ['name' => 'one', 'level' => 'read'])[0]->exists)->toBeTrue();
});

test('names are unique per owner in a workspace, and the error lists the owner\'s agent names', function () {
    $this->agents->create($this->owner, ['name' => 'deploy-bot', 'level' => 'read']);
    $this->agents->create($this->owner, ['name' => 'claude', 'level' => 'read']);

    expect(fn () => $this->agents->create($this->owner, ['name' => 'Deploy-Bot', 'level' => 'write']))
        ->toThrow(ValidationException::class, 'You already have an agent named Deploy-Bot. Your agents: claude, deploy-bot.');

    // Another owner may reuse the name.
    $alex = User::factory()->create();
    addMember($this->web, $alex, ProjectRole::Member);
    expect($this->agents->create($alex, ['name' => 'deploy-bot', 'level' => 'read'])[0]->name)->toBe('deploy-bot');
});

test('projects must be ones the owner can access; the error lists them', function () {
    makeProject(User::factory()->create(), 'SECRET');
    asWorkspaceMember($this->owner);

    expect(fn () => $this->agents->create($this->owner, ['name' => 'x', 'level' => 'read', 'projects' => ['SECRET', 'web']]))
        ->toThrow(ValidationException::class, 'You are not a member of: SECRET. Your projects: API, WEB.');

    [$agent] = $this->agents->create($this->owner, ['name' => 'scoped', 'level' => 'read', 'projects' => ['web']]);
    expect($agent->projects()->pluck('key')->all())->toBe(['WEB']);
});

test('every agent change writes its audit event once, without the token', function () {
    [$agent, $plain] = $this->agents->create($this->owner, ['name' => 'bot', 'level' => 'read', 'projects' => ['WEB']]);
    $this->agents->update($agent, $this->owner, ['level' => 'write', 'projects' => ['WEB', 'API']]);
    $this->agents->pause($agent, $this->owner);
    $this->agents->resume($agent, $this->owner);
    $new = $this->agents->regenerateToken($agent, $this->owner);
    $this->agents->revoke($agent, $this->owner);

    $rows = AuditLog::where('action', 'like', 'agent.%')->orderBy('id')->get();
    expect($rows->pluck('action')->all())->toBe(['agent.created', 'agent.updated', 'agent.paused', 'agent.resumed', 'agent.token_regenerated', 'agent.revoked'])
        ->and($rows->pluck('target_type')->unique()->all())->toBe(['agent'])
        ->and($rows->pluck('target_label')->unique()->all())->toBe(['bot'])
        ->and($rows->pluck('actor_user_id')->unique()->all())->toBe([$this->owner->id])
        ->and($rows[1]->metadata)->toEqual(['level' => ['before' => 'read', 'after' => 'write'], 'projects' => ['before' => ['WEB'], 'after' => ['API', 'WEB']]])
        ->and($rows[2]->metadata)->toEqual(['by' => 'owner'])
        ->and($rows[5]->metadata)->toEqual(['by' => 'owner', 'reason' => 'manual']);

    $everything = json_encode(AuditLog::all()->toArray());
    foreach ([$plain, $new] as $token) {
        expect($everything)->not->toContain(explode('|', $token, 2)[1]);
    }
});

test('an Admin revoking someone else\'s agent is recorded as by admin', function () {
    $alex = User::factory()->create();
    addMember($this->web, $alex, ProjectRole::Member);
    [$agent] = $this->agents->create($alex, ['name' => 'alex bot', 'level' => 'read']);

    $this->agents->revoke($agent, $this->owner);

    expect(AuditLog::where('action', 'agent.revoked')->sole()->metadata)->toEqual(['by' => 'admin', 'reason' => 'manual']);
});

test('regenerating makes the old token stop working', function () {
    [$agent, $old] = $this->agents->create($this->owner, ['name' => 'bot', 'level' => 'read']);
    $new = $this->agents->regenerateToken($agent, $this->owner);

    asToken($old)->getJson('/api/v1/projects')->assertUnauthorized();
    asToken($new)->getJson('/api/v1/projects')->assertOk();
    expect(PersonalAccessToken::where('agent_id', $agent->id)->count())->toBe(1);
});

test('listFor shows a Member their own agents and an Owner every agent, flagging their own', function () {
    $alex = User::factory()->create();
    addMember($this->web, $alex, ProjectRole::Member);
    $this->agents->create($this->owner, ['name' => 'mine', 'level' => 'read', 'projects' => ['WEB']]);
    $this->agents->create($alex, ['name' => 'alex bot', 'level' => 'write']);

    expect(collect($this->agents->listFor($alex, $this->ws))->pluck('name')->all())->toBe(['alex bot']);

    $all = collect($this->agents->listFor($this->owner, $this->ws))->keyBy('name');
    expect($all->keys()->sort()->values()->all())->toBe(['alex bot', 'mine'])
        ->and($all['mine']['mine'])->toBeTrue()
        ->and($all['mine']['projects'])->toBe(['WEB'])
        ->and($all['alex bot']['mine'])->toBeFalse()
        ->and($all['alex bot']['projects'])->toBeNull()
        ->and($all['alex bot']['level'])->toBe('write')
        ->and($all['alex bot']['owner']['email'])->toBe($alex->email);
});
