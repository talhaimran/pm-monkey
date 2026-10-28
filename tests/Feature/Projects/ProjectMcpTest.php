<?php

use App\Enums\ProjectRole;
use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\CreateProject;
use App\Mcp\Tools\DeleteProject;
use App\Mcp\Tools\GetProject;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\UpdateProject;
use App\Models\Project;
use App\Models\User;

test('create_project creates and summarizes', function () {
    $user = apiAs(User::factory()->create());

    PmServer::tool(CreateProject::class, ['key' => 'web', 'name' => 'Website'])
        ->assertOk()
        ->assertSee(['Created project WEB', '"key":"WEB"']);

    expect(Project::first()->roleFor($user))->toBe(ProjectRole::Admin);
});

test('list_projects returns only my projects', function () {
    $me = User::factory()->create();
    makeProject($me, 'MINE');
    makeProject(User::factory()->create(), 'THEIRS');

    apiAs($me);
    PmServer::tool(ListProjects::class)
        ->assertOk()
        ->assertSee('MINE')
        ->assertDontSee('THEIRS');
});

test('get_project includes members and errors helpfully on unknown key', function () {
    $me = User::factory()->create(['email' => 'me@example.com']);
    makeProject($me, 'WEB');

    apiAs($me);
    PmServer::tool(GetProject::class, ['project' => 'WEB'])->assertOk()->assertSee('me@example.com');
    PmServer::tool(GetProject::class, ['project' => 'NOPE'])->assertHasErrors(['Projects you can access: WEB']);
});

test('update_project needs write ability', function () {
    $me = User::factory()->create();
    makeProject($me);

    apiAs($me, ['read']);
    PmServer::tool(UpdateProject::class, ['project' => 'WEB', 'name' => 'X'])->assertHasErrors();

    apiAs($me);
    PmServer::tool(UpdateProject::class, ['project' => 'WEB', 'name' => 'X'])->assertOk()->assertSee('"name":"X"');
});

test('delete_project requires confirm true', function () {
    $me = User::factory()->create();
    makeProject($me);

    apiAs($me);
    PmServer::tool(DeleteProject::class, ['project' => 'WEB'])->assertHasErrors(['confirm: true']);
    PmServer::tool(DeleteProject::class, ['project' => 'WEB', 'confirm' => true])->assertOk();

    expect(Project::count())->toBe(0);
});
