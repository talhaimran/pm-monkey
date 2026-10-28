<?php

use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Services\IssueService;
use App\Services\ProjectService;
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

function makeProject(User $admin, string $key = 'WEB', string $name = 'Website'): Project
{
    return app(ProjectService::class)->create($admin, ['key' => $key, 'name' => $name]);
}

function addMember(Project $project, User $user, ProjectRole $role): User
{
    $project->members()->attach($user, ['role' => $role->value]);

    return $user;
}

function makeIssue(Project $project, User $reporter, array $data = []): Issue
{
    return app(IssueService::class)->create($project, $reporter, ['title' => 'Issue', ...$data]);
}

/** Authenticate as an API token holder with the given abilities. */
function apiAs(User $user, array $abilities = ['read', 'write']): User
{
    Sanctum::actingAs($user, $abilities);

    return $user;
}
