<?php

use App\Models\Issue;
use App\Models\User;

/** Fresh headers and guards per call: withHeader/withToken persist and Sanctum caches the user within a test. */
function asToken(string $token, array $headers = [])
{
    test()->flushHeaders();
    app('auth')->forgetGuards();

    return test()->withToken($token)->withHeaders($headers);
}

beforeEach(function () {
    $this->me = User::factory()->create();
    $this->project = makeProject($this->me, 'WEB');
    $this->token = $this->me->createToken('agent', ['read', 'write'])->plainTextToken;
});

test('idempotency key replays the first response', function () {
    $send = fn (array $body) => asToken($this->token, ['Idempotency-Key' => 'abc-123'])
        ->postJson('/api/v1/projects/WEB/issues', $body);

    $first = $send(['title' => 'Once'])->assertCreated();
    $second = $send(['title' => 'Once'])->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json())->toBe($first->json())->and(Issue::count())->toBe(1);

    $send(['title' => 'Different'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'different request'));
});

test('idempotency keys are per token and optional', function () {
    $other = $this->me->createToken('other', ['read', 'write'])->plainTextToken;

    foreach ([$this->token, $other] as $token) {
        asToken($token, ['Idempotency-Key' => 'same'])->postJson('/api/v1/projects/WEB/issues', ['title' => 'x'])->assertCreated();
    }
    asToken($this->token)->postJson('/api/v1/projects/WEB/issues', ['title' => 'x'])->assertCreated();
    asToken($this->token)->postJson('/api/v1/projects/WEB/issues', ['title' => 'x'])->assertCreated();

    expect(Issue::count())->toBe(4);
});

test('failed requests are not stored for replay', function () {
    $send = fn (array $body) => asToken($this->token, ['Idempotency-Key' => 'retry'])->postJson('/api/v1/projects/WEB/issues', $body);

    $send(['title' => 'x', 'status' => 'Nope'])->assertUnprocessable();
    $send(['title' => 'x', 'status' => 'Nope'])->assertUnprocessable()->assertHeaderMissing('Idempotent-Replayed');
});

test('rate limit is per token', function () {
    config(['pm.api_rate_limit' => 3]);
    $other = $this->me->createToken('other', ['read'])->plainTextToken;

    foreach (range(1, 3) as $i) {
        asToken($this->token)->getJson('/api/v1/projects')->assertOk();
    }
    asToken($this->token)->getJson('/api/v1/projects')
        ->assertStatus(429)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Too many requests'));
    asToken($other)->getJson('/api/v1/projects')->assertOk();
});
