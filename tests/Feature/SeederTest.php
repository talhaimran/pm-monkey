<?php

use App\Enums\IssueType;
use App\Enums\SprintState;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;

test('demo seed builds a usable project and prints a working token', function () {
    $this->artisan('db:seed')
        ->expectsOutputToContain('demo@example.com')
        ->expectsOutputToContain('Demo API token')
        ->expectsOutputToContain('PM_MCP_TOKEN')
        ->assertSuccessful();

    $demo = User::where('email', 'demo@example.com')->sole();
    $project = Project::where('key', 'WEB')->sole();
    $active = $project->sprints()->where('state', SprintState::Active)->sole();

    expect($project->roleFor($demo)?->value)->toBe('admin')
        ->and($project->members()->count())->toBe(3)
        ->and(Issue::count())->toBeGreaterThanOrEqual(28)->toBeLessThanOrEqual(35)
        ->and(Issue::where('type', IssueType::Epic)->count())->toBe(3)
        ->and(Issue::whereNotNull('parent_id')->count())->toBeGreaterThan(0)
        ->and($active->issues()->count())->toBeGreaterThanOrEqual(8)
        ->and($project->sprints()->where('state', SprintState::Planned)->count())->toBe(1)
        ->and(Issue::where('assignee_id', $demo->id)->count())->toBeGreaterThan(0)
        ->and(PersonalAccessToken::where('name', 'Demo token')->sole()->abilities)->toBe(['read', 'write']);
});

test('the printed demo token works against the api', function () {
    Artisan::call('db:seed');
    preg_match('/Demo API token: (\S+)/', Artisan::output(), $m);

    $this->withToken($m[1])->getJson('/api/v1/projects/WEB/summary')
        ->assertOk()
        ->assertJsonPath('data.active_sprint.name', 'WEB Sprint 1');
});

test('seeding twice skips instead of failing', function () {
    $this->artisan('db:seed')->assertSuccessful();
    $this->artisan('db:seed')->expectsOutputToContain('already seeded')->assertSuccessful();

    expect(User::where('email', 'demo@example.com')->count())->toBe(1);
});
