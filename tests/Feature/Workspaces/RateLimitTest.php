<?php

use App\Enums\Entitlement;
use App\Models\User;
use App\Services\Entitlements;
use App\Services\TokenService;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->ws = makeWorkspace($this->owner, 'acme');
    makeProject($this->owner, 'WEB');
    $this->token = fn () => app(TokenService::class)->create($this->owner, 'agent', false, null)->plainTextToken;
});

function hit(string $token)
{
    test()->flushHeaders();
    app('auth')->forgetGuards();

    return test()->withToken($token)->getJson('/api/v1/projects');
}

test('api_per_minute limits each token of the workspace', function () {
    app(Entitlements::class)->set($this->ws, Entitlement::ApiPerMinute, '3', 'override');
    [$a, $b] = [($this->token)(), ($this->token)()];

    foreach (range(1, 3) as $i) {
        hit($a)->assertOk();
    }
    hit($a)->assertStatus(429)->assertHeader('Retry-After')
        ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Too many requests: the limit is 3 per minute per token. Retry after '));
    hit($b)->assertOk();

    $this->travel(61)->seconds();
    hit($a)->assertOk();
});

test('api_per_day counts per token per calendar day (E11)', function () {
    $this->travelTo(now()->setTime(12, 0));
    app(Entitlements::class)->set($this->ws, Entitlement::ApiPerDay, '2', 'override');
    $a = ($this->token)();

    hit($a)->assertOk();
    $this->travel(2)->minutes();
    hit($a)->assertOk();
    $this->travel(2)->minutes();
    hit($a)->assertStatus(429)->assertHeader('Retry-After')
        ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Too many requests: the limit is 2 per day per token. Retry after '));

    $this->travelTo(now()->addDay()->startOfDay()->addMinute());
    hit($a)->assertOk();
});

test('without rows the limit falls back to PM_API_RATE_LIMIT per minute and no daily limit', function () {
    config(['pm.api_rate_limit' => 2]);
    $a = ($this->token)();

    hit($a)->assertOk();
    hit($a)->assertOk();
    hit($a)->assertStatus(429)->assertJsonPath('message', fn ($m) => str_contains($m, 'the limit is 2 per minute per token'));

    foreach (range(1, 5) as $i) {
        $this->travel(61)->seconds();
        hit($a)->assertOk();
    }
});
