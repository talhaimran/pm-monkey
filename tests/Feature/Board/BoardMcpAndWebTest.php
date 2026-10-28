<?php

use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\CreateColumn;
use App\Mcp\Tools\DeleteColumn;
use App\Mcp\Tools\GetBoard;
use App\Mcp\Tools\ListColumns;
use App\Mcp\Tools\MoveIssue;
use App\Mcp\Tools\UpdateColumn;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->project = makeProject($this->admin, 'WEB');
});

test('get_board and move_issue over mcp', function () {
    apiAs($this->admin);
    makeIssue($this->project, $this->admin, ['title' => 'Card']);

    PmServer::tool(GetBoard::class, ['project' => 'WEB'])->assertOk()->assertSee(['"name":"To Do"', 'WEB-1']);
    PmServer::tool(MoveIssue::class, ['issue' => 'WEB-1', 'status' => 'Done'])
        ->assertOk()
        ->assertSee('Moved WEB-1 to Done');
    PmServer::tool(MoveIssue::class, ['issue' => 'WEB-1', 'status' => 'Nope'])->assertHasErrors(['Valid statuses']);
});

test('column tools over mcp', function () {
    apiAs($this->admin);

    PmServer::tool(CreateColumn::class, ['project' => 'WEB', 'name' => 'Blocked', 'position' => 0])->assertOk();
    PmServer::tool(UpdateColumn::class, ['project' => 'WEB', 'column' => 'Blocked', 'name' => 'On hold'])->assertOk();
    PmServer::tool(ListColumns::class, ['project' => 'WEB'])->assertOk()->assertSee('On hold');
    PmServer::tool(DeleteColumn::class, ['project' => 'WEB', 'column' => 'On hold'])->assertOk();
    PmServer::tool(ListColumns::class, ['project' => 'WEB'])->assertDontSee('On hold');
});

test('project page is the board and accepts filters', function () {
    makeIssue($this->project, $this->admin, ['title' => 'Alpha', 'type' => 'bug']);
    makeIssue($this->project, $this->admin, ['title' => 'Beta']);

    $this->actingAs($this->admin)->get('/projects/WEB?type=bug')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Board')
            ->has('board.columns', 4)
            ->has('board.columns.0.issues', 1)
            ->where('board.columns.0.issues.0.key', 'WEB-1')
            ->where('filters.type', 'bug'));
});

test('web move endpoint and column management', function () {
    makeIssue($this->project, $this->admin);

    $this->actingAs($this->admin)->post('/issues/WEB-1/move', ['status' => 'Done'])->assertRedirect();
    $this->actingAs($this->admin)->post('/projects/WEB/columns', ['name' => 'Blocked'])->assertRedirect();

    expect($this->project->columns()->pluck('name')->last())->toBe('Blocked');
});
