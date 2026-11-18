<?php

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\MembershipService;
use App\Services\ProjectService;
use App\Services\WorkspaceMemberService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Step 0 PR 8 scan, F6: every action that adds to a counted thing checks the plan limit under the workspace row lock,
 * so two simultaneous adds can't both pass. A second connection holds the row the way a concurrent request would.
 */
test('plan-limit checks wait on the workspace row lock', function (string $action) {
    Mail::fake();
    $owner = User::factory()->create();
    $ws = makeWorkspace($owner, 'acme');
    useWorkspace($ws);
    $project = makeProject($owner, 'WEB');
    $other = User::factory()->create();
    $ws->addMember($other, WorkspaceRole::Member);
    $project->members()->attach($other, ['role' => ProjectRole::Viewer->value]);
    $run = match ($action) {
        'create project' => fn () => app(ProjectService::class)->create($owner, ['key' => 'NEW', 'name' => 'New']),
        'invite' => fn () => app(InvitationService::class)->invite($ws, $owner, 'new@example.com', WorkspaceRole::Member),
        'project role' => fn () => app(MembershipService::class)->changeRole($project, $other, ProjectRole::Member),
        'workspace role' => fn () => app(WorkspaceMemberService::class)->changeRole($ws, $owner, $other, WorkspaceRole::Admin),
    };

    config(['database.connections.locker' => config('database.connections.'.config('database.default'))]);
    $locker = DB::connection('locker');
    $locker->beginTransaction();
    $locker->table('workspaces')->where('id', $ws->id)->lockForUpdate()->first();
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    try {
        expect($run)->toThrow(QueryException::class, 'Lock wait timeout');
    } finally {
        $locker->rollBack();
    }
    $run();
})->with(['create project', 'invite', 'project role', 'workspace role']);
