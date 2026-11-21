<?php

use App\Enums\AgentLevel;
use App\Enums\WorkspaceRole;
use App\Models\AuditLog;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Talha Imran']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->web = makeProject($this->owner, 'WEB');
    $this->admin = User::factory()->create();
    $this->ws->addMember($this->admin, WorkspaceRole::Admin);
    $this->member = User::factory()->create();
    $this->ws->addMember($this->member, WorkspaceRole::Member);
});

test('agent policy abilities: update is owner only, pause resume revoke add workspace Owners and Admins', function () {
    [$agent] = makeAgent($this->member, AgentLevel::Read, name: 'sams');
    $other = User::factory()->create();
    $this->ws->addMember($other, WorkspaceRole::Member);
    $outsider = User::factory()->create();

    foreach (['update', 'regenerate'] as $ability) {
        expect($this->member->can($ability, $agent))->toBeTrue()
            ->and($this->admin->can($ability, $agent))->toBeFalse()
            ->and($this->owner->can($ability, $agent))->toBeFalse()
            ->and($other->can($ability, $agent))->toBeFalse();
    }
    foreach (['pause', 'resume', 'revoke'] as $ability) {
        expect($this->member->can($ability, $agent))->toBeTrue()
            ->and($this->admin->can($ability, $agent))->toBeTrue()
            ->and($this->owner->can($ability, $agent))->toBeTrue()
            ->and($other->can($ability, $agent))->toBeFalse()
            ->and($outsider->can($ability, $agent))->toBeFalse();
    }
});

test('with agents turned off every agent call is 403 agents_disabled on REST, HTTP MCP and the service', function () {
    $plain = makeAgent($this->owner, AgentLevel::Write, name: 'bot')[1];
    asToken($plain)->getJson('/api/v1/projects')->assertOk();

    $this->ws->forceFill(['settings' => [...$this->ws->settings ?? [], 'agents_enabled' => false]])->save();

    asToken($plain)->getJson('/api/v1/projects')->assertForbidden()->assertJsonPath('error', 'agents_disabled');
    asToken($plain, ['Accept' => 'application/json, text/event-stream'])
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => (object) []])
        ->assertForbidden()->assertJsonPath('error', 'agents_disabled');
    // People are unaffected.
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $this->actingAs($this->owner)->get(w('/projects/WEB'))->assertOk();
});

test('Owners and Admins turn agents off and on; it logs agent_policy.changed once with before and after', function () {
    $this->actingAs($this->admin)->patch(w('/settings/agent-policy'), ['agents_enabled' => false])->assertRedirect();
    expect($this->ws->fresh()->setting('agents_enabled'))->toBeFalse();

    $log = AuditLog::where('action', 'agent_policy.changed')->sole();
    expect($log->metadata)->toEqual(['before' => ['agents_enabled' => true], 'after' => ['agents_enabled' => false]])
        ->and($log->actor_user_id)->toBe($this->admin->id);

    // Saving the same value again changes nothing and logs nothing.
    $this->patch(w('/settings/agent-policy'), ['agents_enabled' => false])->assertRedirect();
    expect(AuditLog::where('action', 'agent_policy.changed')->count())->toBe(1);

    $this->patch(w('/settings/agent-policy'), ['agents_enabled' => true]);
    expect($this->ws->fresh()->setting('agents_enabled'))->toBeTrue()->and(AuditLog::where('action', 'agent_policy.changed')->count())->toBe(2);
});

test('a Member cannot change the agent policy, and the page shows the setting to Admins', function () {
    $this->actingAs($this->member)->patch(w('/settings/agent-policy'), ['agents_enabled' => false])->assertForbidden();
    expect($this->ws->fresh()->setting('agents_enabled'))->toBeTrue();

    $this->actingAs($this->admin)->get(w('/settings/security'))->assertInertia(fn ($page) => $page->where('agentsEnabled', true));
});

test('with agents off the Agents page still opens but says so and refuses new agents', function () {
    $this->ws->forceFill(['settings' => ['agents_enabled' => false]])->save();
    $this->actingAs($this->owner)->get(w('/settings/agents'))->assertInertia(fn ($page) => $page->where('agentsEnabled', false));
    $this->post(w('/settings/agents'), ['name' => 'x', 'level' => 'read'])->assertSessionHasErrors('name');
});
