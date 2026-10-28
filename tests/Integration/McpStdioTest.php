<?php

use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Process;

/**
 * Spawns the real stdio server (`php artisan mcp:start pm`) against the test database.
 * Uses DatabaseTruncation (see tests/Pest.php) so the child process sees committed rows.
 */
function runStdio(array $messages, array $env): array
{
    $input = collect($messages)->map(fn ($m) => json_encode($m))->implode("\n")."\n";

    $result = Process::path(base_path())
        ->env([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            ...$env,
        ])
        ->input($input)
        ->timeout(60)
        ->run([PHP_BINARY, 'artisan', 'mcp:start', 'pm']);

    return [
        $result,
        collect(preg_split('/\R/', trim($result->output())))->filter()->map(fn ($l) => json_decode($l, true))->filter()->keyBy('id'),
    ];
}

test('stdio server answers real JSON-RPC calls as the token user', function () {
    $user = User::factory()->create();
    makeProject($user, 'WEB');
    $token = $user->createToken('stdio-agent', ['read', 'write'])->plainTextToken;

    [$result, $responses] = runStdio([
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'pest', 'version' => '1']]],
        ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
        ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'create_issue', 'arguments' => ['project' => 'WEB', 'title' => 'Over stdio']]],
        ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'get_issue', 'arguments' => ['issue' => 'WEB-1']]],
    ], ['PM_MCP_TOKEN' => $token]);

    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and($responses[1]['result']['serverInfo']['name'])->toBe('PM Monkey')
        ->and(collect($responses[2]['result']['tools'])->pluck('name'))->toContain('create_issue', 'complete_sprint')
        ->and($responses[3]['result']['content'][0]['text'])->toStartWith('Created WEB-1: Over stdio')
        ->and($responses[4]['result']['content'][0]['text'])->toContain('"title":"Over stdio"');

    $activity = Activity::sole();
    expect($activity->via)->toBe('mcp')->and($activity->token_name)->toBe('stdio-agent');
});

test('stdio server refuses to start without a valid token', function () {
    [$result] = runStdio([['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']], ['PM_MCP_TOKEN' => 'not-a-token']);

    expect($result->successful())->toBeFalse()
        ->and($result->errorOutput().$result->output())->toContain('PM_MCP_TOKEN');
});
