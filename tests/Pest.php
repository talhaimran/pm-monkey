<?php

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\CurrentWorkspace;
use App\Services\InvitationService;
use App\Services\IssueService;
use App\Services\ProjectService;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Tests that spawn real processes need committed rows, so they truncate instead of using transactions.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** A workspace owned by $owner, made current for service calls and for the owner's web requests. */
function makeWorkspace(User $owner, string $slug): Workspace
{
    // Only Cloud allows a second workspace.
    if (Workspace::exists()) {
        config(['pm.edition' => 'cloud']);
    }

    $workspace = app(WorkspaceService::class)->create($owner, ucfirst($slug), $slug);
    // Tests run unlimited unless they set a limit; D6's Cloud defaults are tested in EntitlementEnforcementTest.
    $workspace->entitlements()->delete();

    return useWorkspace($workspace, $owner);
}

function useWorkspace(Workspace $workspace, ?User $user = null): Workspace
{
    app(CurrentWorkspace::class)->set($workspace);
    $user?->forceFill(['current_workspace_id' => $workspace->id])->save();

    return $workspace;
}

/**
 * Puts $user in the test's workspace, like a migrated install where everyone shares one: the current workspace
 * if one is set ($user joins as Member), else a new one $user owns.
 */
function joinWorkspace(User $user): Workspace
{
    $current = app(CurrentWorkspace::class);
    if (! $current->id()) {
        return makeWorkspace($user, 'ws-'.$user->id);
    }

    $workspace = $current->get();
    if (! $user->workspaceRole($workspace)) {
        $workspace->addMember($user, WorkspaceRole::Member);
    }
    if (! $user->current_workspace_id) {
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    }

    return $workspace;
}

/** Makes $user a plain Member of the current workspace: they see only their own projects (Owners and Admins see all, spec 7.0b). */
function asWorkspaceMember(User $user): User
{
    app(CurrentWorkspace::class)->get()->members()->updateExistingPivot($user->id, ['role' => WorkspaceRole::Member->value]);

    return $user;
}

/** Creates the project in the current workspace (making one on first use), with $admin as its Admin. */
function makeProject(User $admin, string $key = 'WEB', string $name = 'Website'): Project
{
    joinWorkspace($admin);

    return app(ProjectService::class)->create($admin, ['key' => $key, 'name' => $name]);
}

function addMember(Project $project, User $user, ProjectRole $role): User
{
    if (! $user->workspaceRole($project->workspace)) {
        $project->workspace->addMember($user, WorkspaceRole::Member);
    }
    if (! $user->current_workspace_id) {
        $user->forceFill(['current_workspace_id' => $project->workspace_id])->save();
    }
    $project->members()->attach($user, ['role' => $role->value]);

    return $user;
}

/** A pending invitation to $project, as a project admin sends it. */
function makeInvitation(Project $project, User $inviter, string $email, string $role): WorkspaceInvitation
{
    return app(InvitationService::class)->invite($project->workspace, $inviter, $email, WorkspaceRole::Member, $project, ProjectRole::from($role));
}

/** E10: a web path inside the test's workspace, e.g. w('/projects/WEB') → '/ws-1/projects/WEB'. */
function w(string $path): string
{
    $current = app(CurrentWorkspace::class);

    return '/'.($current->id() ? $current->get() : Workspace::orderBy('id')->firstOrFail())->slug.$path;
}

function makeIssue(Project $project, User $reporter, array $data = []): Issue
{
    return app(IssueService::class)->create($project, $reporter, ['title' => 'Issue', ...$data]);
}

/** Authenticate as an API token holder with the given abilities. */
function apiAs(User $user, array $abilities = ['read', 'write']): User
{
    if (! $user->workspaces()->exists()) {
        joinWorkspace($user);
    }
    Sanctum::actingAs($user, $abilities);

    return $user;
}
