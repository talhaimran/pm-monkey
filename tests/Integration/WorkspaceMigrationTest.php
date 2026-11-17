<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\CurrentWorkspace;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Runs real migrations (DDL commits in MySQL), so it lives with the truncating Integration tests, not in Feature.

function migrateToBeforeStepZero(): void
{
    $paths = collect(File::files(database_path('migrations')))
        ->map(fn ($f) => $f->getPathname())
        ->filter(fn ($p) => basename($p) < '2026_11_16')
        ->sort()->values()->all();

    Artisan::call('migrate:fresh', ['--path' => $paths, '--realpath' => true, '--force' => true]);
}

/** What each user could reach before step 0: projects through project_user, their issues, their tokens. */
function reachBefore(int $userId): array
{
    $projects = DB::table('projects')->join('project_user', 'project_user.project_id', '=', 'projects.id')
        ->where('project_user.user_id', $userId)->orderBy('key')->pluck('projects.key', 'projects.id');

    return [
        'projects' => $projects->values()->all(),
        'issues' => DB::table('issues')->join('projects', 'projects.id', '=', 'issues.project_id')
            ->whereIn('project_id', $projects->keys())->orderBy('issues.id')
            ->selectRaw("concat(projects.key, '-', issues.number) as k")->pluck('k')->all(),
        'tokens' => DB::table('personal_access_tokens')->where('tokenable_id', $userId)->orderBy('id')->pluck('id')->all(),
    ];
}

function reachAfter(User $user): array
{
    app(CurrentWorkspace::class)->set(Workspace::findOrFail($user->current_workspace_id));
    // Projects they are in: since PR 3 Owners and Admins also see the rest read-only (spec 7.0b).
    $projects = $user->accessibleProjects()->whereHas('members', fn ($m) => $m->whereKey($user->id))->orderBy('key')->pluck('projects.key', 'projects.id');

    return [
        'projects' => $projects->values()->all(),
        'issues' => DB::table('issues')->join('projects', 'projects.id', '=', 'issues.project_id')
            ->whereIn('project_id', $projects->keys())->orderBy('issues.id')
            ->selectRaw("concat(projects.key, '-', issues.number) as k")->pluck('k')->all(),
        'tokens' => $user->tokens()->where('workspace_id', $user->current_workspace_id)->orderBy('id')->pluck('id')->all(),
    ];
}

test('an existing install moves into one workspace and everyone keeps what they could reach', function () {
    migrateToBeforeStepZero();
    $now = now();
    $user = fn ($name) => DB::table('users')->insertGetId(['name' => $name, 'email' => "{$name}@example.com", 'password' => bcrypt('password'), 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    [$demo, $alex, $sam] = [$user('demo'), $user('alex'), $user('sam')];

    $project = function (string $key, array $roles) use ($now) {
        $id = DB::table('projects')->insertGetId(['key' => $key, 'name' => $key, 'issue_counter' => 2, 'created_at' => $now, 'updated_at' => $now]);
        foreach ($roles as $userId => $role) {
            DB::table('project_user')->insert(['project_id' => $id, 'user_id' => $userId, 'role' => $role, 'created_at' => $now, 'updated_at' => $now]);
        }
        $column = DB::table('columns')->insertGetId(['project_id' => $id, 'name' => 'To Do', 'position' => 0, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([1, 2] as $n) {
            DB::table('issues')->insert(['project_id' => $id, 'number' => $n, 'title' => "{$key} {$n}", 'type' => 'task', 'priority' => 'medium', 'column_id' => $column, 'rank' => $n * 1024, 'created_at' => $now, 'updated_at' => $now]);
        }

        return $id;
    };
    $web = $project('WEB', [$demo => 'admin', $alex => 'member', $sam => 'viewer']);
    $ops = $project('OPS', [$alex => 'admin']);

    DB::table('personal_access_tokens')->insert(['tokenable_type' => User::class, 'tokenable_id' => $demo, 'name' => 'Demo token', 'token' => hash('sha256', 'plain'), 'abilities' => json_encode(['read', 'write', "project:{$web}"]), 'created_at' => $now, 'updated_at' => $now]);
    // Same email invited to two projects: the new table holds one row per workspace and email, so the newest wins.
    DB::table('project_invitations')->insert(['project_id' => $ops, 'email' => 'new@example.com', 'role' => 'admin', 'invited_by' => $alex, 'created_at' => $now->copy()->subDay(), 'updated_at' => $now->copy()->subDay()]);
    DB::table('project_invitations')->insert(['project_id' => $web, 'email' => 'new@example.com', 'role' => 'member', 'invited_by' => $demo, 'created_at' => $now, 'updated_at' => $now]);

    $before = collect([$demo, $alex, $sam])->mapWithKeys(fn ($id) => [$id => reachBefore($id)]);

    Artisan::call('migrate', ['--force' => true]);

    $workspace = Workspace::sole();
    expect($workspace->name)->toBe(config('app.name'))
        ->and($workspace->slug)->toBe(Str::slug(config('app.name')))
        ->and(User::find($demo)->workspaceRole($workspace))->toBe(WorkspaceRole::Owner)
        ->and(User::find($alex)->workspaceRole($workspace))->toBe(WorkspaceRole::Admin)
        ->and(User::find($sam)->workspaceRole($workspace))->toBe(WorkspaceRole::Member)
        ->and(DB::table('projects')->pluck('workspace_id')->unique()->all())->toBe([$workspace->id])
        ->and(DB::table('personal_access_tokens')->pluck('workspace_id')->unique()->all())->toBe([$workspace->id])
        ->and(User::pluck('current_workspace_id')->unique()->all())->toBe([$workspace->id])
        ->and(Schema::hasTable('project_invitations'))->toBeFalse();

    foreach (User::all() as $u) {
        expect(reachAfter($u))->toEqual($before[$u->id]);
    }

    $invitation = WorkspaceInvitation::sole();
    expect($invitation->workspace_id)->toBe($workspace->id)
        ->and($invitation->email)->toBe('new@example.com')
        ->and($invitation->project_id)->toBe($web)
        ->and($invitation->project_role->value)->toBe('member')
        ->and($invitation->role)->toBe(WorkspaceRole::Member)
        ->and($invitation->invited_by)->toBe($demo)
        ->and($invitation->expires_at->isFuture())->toBeTrue();
});

test('a fresh install gets no workspace from the migration', function () {
    migrateToBeforeStepZero();

    Artisan::call('migrate', ['--force' => true]);

    expect(Workspace::count())->toBe(0)
        ->and(Schema::hasColumns('projects', ['workspace_id']))->toBeTrue();
});

test('rolling back restores the pre-step-0 shape, invitations included', function () {
    migrateToBeforeStepZero();
    $now = now();
    $owner = DB::table('users')->insertGetId(['name' => 'o', 'email' => 'o@example.com', 'password' => 'x', 'created_at' => $now, 'updated_at' => $now]);
    $project = DB::table('projects')->insertGetId(['key' => 'WEB', 'name' => 'Web', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('project_invitations')->insert(['project_id' => $project, 'email' => 'new@example.com', 'role' => 'viewer', 'invited_by' => $owner, 'created_at' => $now, 'updated_at' => $now]);
    Artisan::call('migrate', ['--force' => true]);

    // Every step 0 migration ran in that one batch, so rolling back the batch undoes all of them.
    expect(Artisan::call('migrate:rollback', ['--force' => true]))->toBe(0);

    expect(Schema::hasTable('workspaces'))->toBeFalse()
        ->and(Schema::hasColumn('projects', 'workspace_id'))->toBeFalse()
        ->and(DB::table('project_invitations')->get(['project_id', 'email', 'role'])->map(fn ($r) => (array) $r)->all())
        ->toBe([['project_id' => $project, 'email' => 'new@example.com', 'role' => 'viewer']]);

    Artisan::call('migrate', ['--force' => true]);
});
