<?php

use App\Models\Activity;
use App\Models\User;

beforeEach(function () {
    $this->me = User::factory()->create();
    $this->project = makeProject($this->me, 'WEB');
});

/** POST one JSON-RPC message to /mcp with a bearer token; fresh headers/guards each call. */
function rpc(?string $token, string $method, array $params = [], int $id = 1)
{
    test()->flushHeaders();
    app('auth')->forgetGuards();
    $client = $token ? test()->withToken($token) : test();

    return $client->withHeader('Accept', 'application/json, text/event-stream')
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => (object) $params]);
}

test('requests without a token get 401 with a bearer challenge', function () {
    rpc(null, 'tools/list')->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"');
});

test('initialize and tools/list describe the server and every tool in one page', function () {
    $token = $this->me->createToken('agent', ['read'])->plainTextToken;

    rpc($token, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'PM Monkey')
        ->assertJsonPath('result.instructions', fn ($i) => str_contains($i, 'list_projects'));

    $tools = rpc($token, 'tools/list')->assertOk()->json('result.tools');
    $names = collect($tools)->pluck('name');

    expect($names)->toContain('create_issue', 'move_issue', 'rank_backlog', 'complete_sprint', 'search_issues', 'my_issues', 'project_summary', 'bulk_update_issues')
        ->and($names->count())->toBeGreaterThan(30)
        ->and(collect($tools)->firstWhere('name', 'create_issue')['inputSchema']['required'])->toContain('project', 'title')
        ->and(collect($tools)->firstWhere('name', 'delete_issue')['annotations']['destructiveHint'])->toBeTrue();
});

test('tools/call writes as the token user and logs via mcp with the token name', function () {
    $token = $this->me->createToken('claude-desktop', ['read', 'write'])->plainTextToken;

    rpc($token, 'tools/call', ['name' => 'create_issue', 'arguments' => ['project' => 'WEB', 'title' => 'Over HTTP']])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.content.0.text', fn ($t) => str_starts_with($t, 'Created WEB-1: Over HTTP'));

    $activity = Activity::sole();
    expect($activity->via)->toBe('mcp')->and($activity->token_name)->toBe('claude-desktop')->and($activity->user_id)->toBe($this->me->id);
});

test('read-only and scoped tokens are enforced over mcp', function () {
    $other = makeProject($this->me, 'API');
    $readOnly = $this->me->createToken('ro', ['read'])->plainTextToken;
    $scoped = $this->me->createToken('scoped', ['read', 'write', "project:{$this->project->id}"])->plainTextToken;

    rpc($readOnly, 'tools/call', ['name' => 'create_issue', 'arguments' => ['project' => 'WEB', 'title' => 'x']])
        ->assertJsonPath('result.isError', true)
        ->assertJsonPath('result.content.0.text', fn ($t) => str_contains($t, 'read-only'));

    rpc($scoped, 'tools/call', ['name' => 'get_project', 'arguments' => ['project' => 'API']])
        ->assertJsonPath('result.isError', true)
        ->assertJsonPath('result.content.0.text', fn ($t) => str_contains($t, 'Projects you can access: WEB.'));
});

test('resources are listed and readable by uri', function () {
    $token = $this->me->createToken('agent', ['read'])->plainTextToken;
    makeIssue($this->project, $this->me, ['title' => 'Readable']);

    $templates = rpc($token, 'resources/templates/list')->assertOk()->json('result.resourceTemplates');
    expect(collect($templates)->pluck('uriTemplate'))->toContain('issue://{key}', 'project://{key}');

    rpc($token, 'resources/read', ['uri' => 'issue://WEB-1'])
        ->assertOk()
        ->assertJsonPath('result.contents.0.text', fn ($t) => str_contains($t, '"title":"Readable"'));
});

test('the mcp endpoint shares the per-token rate limit', function () {
    config(['pm.api_rate_limit' => 2]);
    $token = $this->me->createToken('agent', ['read'])->plainTextToken;

    rpc($token, 'tools/list')->assertOk();
    rpc($token, 'tools/list')->assertOk();
    rpc($token, 'tools/list')->assertStatus(429);
});
