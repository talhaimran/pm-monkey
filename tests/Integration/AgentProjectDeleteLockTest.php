<?php

use App\Enums\AgentLevel;
use App\Enums\AgentStatus;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Scan F2 (step A, PR 1): deleting a project locks the rows of the agents scoped to it before counting their other
 * projects, so two deletes of an agent's last two projects run one after the other and the second revokes it.
 * A second connection holds the agent row the way the other delete's transaction would.
 */
test('deleting a project waits on the lock of each agent scoped to it', function () {
    $owner = User::factory()->create();
    makeWorkspace($owner, 'acme');
    $web = makeProject($owner, 'WEB');
    $api = makeProject($owner, 'API');
    [$agent] = makeAgent($owner, AgentLevel::Write, ['WEB', 'API']);

    config(['database.connections.locker' => config('database.connections.'.config('database.default'))]);
    $locker = DB::connection('locker');
    $locker->beginTransaction();
    $locker->table('agents')->where('id', $agent->id)->lockForUpdate()->first();
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    try {
        expect(fn () => app(ProjectService::class)->delete($web))->toThrow(QueryException::class, 'Lock wait timeout');
    } finally {
        $locker->rollBack();
    }

    app(ProjectService::class)->delete($web->fresh());
    expect($agent->fresh()->status)->toBe(AgentStatus::Active);
    app(ProjectService::class)->delete($api->fresh());
    expect($agent->fresh()->status)->toBe(AgentStatus::Revoked);
});
