<?php

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
        ->post('/settings/tokens', ['name' => 'claude', 'access' => 'read'])
        ->assertRedirect()
        ->assertSessionHas('token');

    $token = PersonalAccessToken::sole();
    expect($token->name)->toBe('claude')->and($token->abilities)->toBe(['read']);

    $this->actingAs($this->me)->get('/settings/tokens')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tokens/Index')
            ->where('tokens.0.name', 'claude')
            ->where('tokens.0.access', 'read')
            ->where('tokens.0.projects', null)
            ->where('projects.0.key', 'API'));
});

test('read-write tokens can be scoped to projects', function () {
    $this->actingAs($this->me)->post('/settings/tokens', ['name' => 'ci', 'access' => 'write', 'projects' => ['WEB']]);

    expect(PersonalAccessToken::sole()->abilities)->toBe(['read', 'write', "project:{$this->web->id}"]);
});

test('tokens cannot be scoped to projects you are not in', function () {
    makeProject(User::factory()->create(), 'SECRET');

    $this->actingAs($this->me)->post('/settings/tokens', ['name' => 'x', 'access' => 'read', 'projects' => ['SECRET']])
        ->assertSessionHasErrors('projects');
});

test('tokens are revocable by their owner only', function () {
    $mine = $this->me->createToken('mine', ['read'])->accessToken;
    $theirs = User::factory()->create()->createToken('theirs', ['read'])->accessToken;

    $this->actingAs($this->me)->delete("/settings/tokens/{$theirs->id}")->assertNotFound();
    $this->actingAs($this->me)->delete("/settings/tokens/{$mine->id}")->assertRedirect();

    expect(PersonalAccessToken::pluck('name')->all())->toBe(['theirs']);
});

test('real tokens record last use and read-only ones cannot write', function () {
    $plain = $this->me->createToken('ro', ['read'])->plainTextToken;

    $this->withToken($plain)->getJson('/api/v1/projects')->assertOk();
    $this->withToken($plain)->postJson('/api/v1/projects/WEB/issues', ['title' => 'x'])
        ->assertForbidden()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'read-only'));

    expect(PersonalAccessToken::sole()->last_used_at)->not->toBeNull();
});

test('scoped tokens only see their projects', function () {
    $plain = $this->me->createToken('scoped', ['read', 'write', "project:{$this->web->id}"])->plainTextToken;
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
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'limited to specific projects'));
    $api()->postJson('/api/v1/projects/WEB/issues', ['title' => 'ok'])->assertCreated();
});

test('scoped tokens cannot reach comments in other projects by id', function () {
    $comment = app(CommentService::class)->add(Issue::where('number', 1)->where('project_id', $this->api->id)->first(), $this->me, 'hidden');
    $plain = $this->me->createToken('scoped', ['read', 'write', "project:{$this->web->id}"])->plainTextToken;

    $this->withToken($plain)->patchJson("/api/v1/comments/{$comment->id}", ['body' => 'x'])->assertNotFound();
});
