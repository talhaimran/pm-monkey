<?php

use App\Enums\AgentLevel;
use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

// Runs real migrations (DDL commits in MySQL), so it lives with the truncating Integration tests, like WorkspaceMigrationTest.

function migrateToBeforeStepA(): void
{
    $paths = collect(File::files(database_path('migrations')))
        ->map(fn ($f) => $f->getPathname())
        ->filter(fn ($p) => basename($p) < '2026_11_19')
        ->sort()->values()->all();

    Artisan::call('migrate:fresh', ['--path' => $paths, '--realpath' => true, '--force' => true]);
}

/** A workspace with demo (Admin of WEB and OPS) and the ids needed to insert step 0 tokens. */
function stepZeroInstall(): array
{
    $now = now();
    $demo = DB::table('users')->insertGetId(['name' => 'demo', 'email' => 'demo@example.com', 'password' => bcrypt('password'), 'created_at' => $now, 'updated_at' => $now]);
    $ws = DB::table('workspaces')->insertGetId(['name' => 'Acme', 'slug' => 'acme', 'created_at' => $now, 'updated_at' => $now]);
    $other = DB::table('workspaces')->insertGetId(['name' => 'Other', 'slug' => 'other', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('workspace_user')->insert(['workspace_id' => $ws, 'user_id' => $demo, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now]);
    $project = function (string $key, int $workspace) use ($demo, $now) {
        $id = DB::table('projects')->insertGetId(['workspace_id' => $workspace, 'key' => $key, 'name' => $key, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('project_user')->insert(['project_id' => $id, 'user_id' => $demo, 'role' => 'admin', 'created_at' => $now, 'updated_at' => $now]);

        return $id;
    };

    return ['demo' => $demo, 'ws' => $ws, 'web' => $project('WEB', $ws), 'ops' => $project('OPS', $ws), 'elsewhere' => $project('ELSE', $other)];
}

function stepZeroToken(array $install, string $name, array $abilities, ?int $workspace = null, string $plain = 'x'): int
{
    $now = now();

    return DB::table('personal_access_tokens')->insertGetId([
        'tokenable_type' => User::class, 'tokenable_id' => $install['demo'], 'workspace_id' => $workspace ?? $install['ws'],
        'name' => $name, 'token' => hash('sha256', $plain), 'abilities' => json_encode($abilities),
        'last_used_at' => $now->copy()->subDay(), 'created_at' => $now, 'updated_at' => $now,
    ]);
}

test('each workspace token becomes an agent with the same name, owner, workspace, level and projects', function () {
    migrateToBeforeStepA();
    $i = stepZeroInstall();
    $read = stepZeroToken($i, 'Read WEB', ['read', "project:{$i['web']}", "project:{$i['elsewhere']}"], plain: 'read-plain');
    $write = stepZeroToken($i, 'CI', ['read', 'write'], plain: 'write-plain');
    $star = stepZeroToken($i, 'Star', ['*'], plain: 'star-plain');
    // Scoped only to projects that are gone or elsewhere: it reached nothing, so it must not become "every project".
    $orphan = stepZeroToken($i, 'Orphan', ['read', 'write', 'project:999999', "project:{$i['elsewhere']}"], plain: 'orphan-plain');
    $noWorkspace = DB::table('personal_access_tokens')->insertGetId(['tokenable_type' => User::class, 'tokenable_id' => $i['demo'], 'name' => 'Loose', 'token' => hash('sha256', 'loose'), 'abilities' => '["read"]', 'created_at' => now(), 'updated_at' => now()]);

    Artisan::call('migrate', ['--force' => true]);

    $agentOf = fn (int $token) => Agent::with('projects')->findOrFail(DB::table('personal_access_tokens')->where('id', $token)->value('agent_id'));
    $readAgent = $agentOf($read);
    expect($readAgent->only(['workspace_id', 'owner_user_id', 'name']))->toBe(['workspace_id' => $i['ws'], 'owner_user_id' => $i['demo'], 'name' => 'Read WEB'])
        ->and($readAgent->permission_level)->toBe(AgentLevel::Read)
        ->and($readAgent->status)->toBe(AgentStatus::Active)
        ->and($readAgent->last_used_at)->not->toBeNull()
        ->and($readAgent->projects->pluck('key')->all())->toBe(['WEB']);

    $writeAgent = $agentOf($write);
    expect($writeAgent->permission_level)->toBe(AgentLevel::Write)
        ->and($writeAgent->name)->toBe('CI')
        ->and($writeAgent->projects)->toBeEmpty()
        ->and($agentOf($star)->permission_level)->toBe(AgentLevel::Write);

    expect(DB::table('personal_access_tokens')->where('id', $orphan)->exists())->toBeFalse()
        ->and(Agent::where('name', 'Orphan')->sole()->status)->toBe(AgentStatus::Revoked)
        ->and(DB::table('personal_access_tokens')->where('id', $noWorkspace)->value('agent_id'))->toBeNull()
        ->and(Agent::count())->toBe(4);

    // Existing token strings keep working.
    $this->withToken("{$read}|read-plain")->getJson('/api/v1/projects')->assertOk()->assertJsonPath('data.*.key', ['WEB']);
});

test('rolling back restores the step 0 schema and the tokens\' abilities', function () {
    migrateToBeforeStepA();
    $i = stepZeroInstall();
    $read = stepZeroToken($i, 'Read WEB', ['read', "project:{$i['web']}"]);
    $write = stepZeroToken($i, 'CI', ['read', 'write'], plain: 'y');
    $paused = stepZeroToken($i, 'Paused', ['read'], plain: 'z');
    Artisan::call('migrate', ['--force' => true]);
    DB::table('agents')->where('name', 'CI')->update(['permission_level' => 'delete']);
    // Step 0 has no pause, so a paused agent's token must not come back to life.
    DB::table('agents')->where('name', 'Paused')->update(['status' => 'paused']);

    expect(Artisan::call('migrate:rollback', ['--force' => true]))->toBe(0);

    expect(Schema::hasTable('agents'))->toBeFalse()
        ->and(Schema::hasTable('agent_projects'))->toBeFalse();
    foreach (['personal_access_tokens', 'activities', 'comments', 'audit_logs'] as $table) {
        expect(Schema::hasColumn($table, 'agent_id'))->toBeFalse();
    }
    expect(json_decode(DB::table('personal_access_tokens')->where('id', $read)->value('abilities')))->toEqual(['read', "project:{$i['web']}"])
        ->and(json_decode(DB::table('personal_access_tokens')->where('id', $write)->value('abilities')))->toEqual(['read', 'write'])
        ->and(DB::table('personal_access_tokens')->where('id', $paused)->exists())->toBeFalse();

    Artisan::call('migrate', ['--force' => true]);
});
