<?php

use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\MyIssues;
use App\Mcp\Tools\ProjectSummary;
use App\Mcp\Tools\SearchIssues;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->me = User::factory()->create(['email' => 'me@example.com']);
    $this->project = makeProject($this->me, 'WEB');
});

test('search_issues, my_issues and project_summary over mcp', function () {
    apiAs($this->me);
    makeIssue($this->project, $this->me, ['title' => 'Checkout bug', 'assignee' => 'me@example.com']);
    makeIssue($this->project, $this->me, ['title' => 'Docs']);

    PmServer::tool(SearchIssues::class, ['q' => 'checkout'])->assertOk()->assertSee(['1 of 1', 'WEB-1'])->assertDontSee('WEB-2');
    PmServer::tool(SearchIssues::class, ['project' => 'WEB', 'sort' => '-key', 'per_page' => 1])->assertOk()->assertSee(['WEB-2', 'page=2']);
    PmServer::tool(SearchIssues::class, ['sort' => 'nope'])->assertHasErrors(['-updated']);
    PmServer::tool(MyIssues::class)->assertOk()->assertSee('WEB-1')->assertDontSee('WEB-2');
    PmServer::tool(ProjectSummary::class, ['project' => 'WEB'])->assertOk()->assertSee(['To Do: 2', '"open":2']);
});

test('list page renders with filters and sort', function () {
    makeIssue($this->project, $this->me, ['title' => 'Alpha', 'type' => 'bug']);
    makeIssue($this->project, $this->me, ['title' => 'Beta']);

    $this->actingAs($this->me)->get('/projects/WEB/issues?type=bug&sort=title')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Issues')
            ->has('issues.data', 1)
            ->where('issues.data.0.key', 'WEB-1')
            ->where('filters.sort', 'title')
            ->has('sorts'));
});

test('search page jumps to exact keys and lists other matches', function () {
    makeIssue($this->project, $this->me, ['title' => 'Login']);

    $this->actingAs($this->me)->get('/search?q=web-1')->assertRedirect('/issues/WEB-1');
    $this->actingAs($this->me)->get('/search?q=log')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Search')->where('issues.data.0.key', 'WEB-1'));
});

test('dashboard shows my issues and project summaries', function () {
    makeIssue($this->project, $this->me, ['assignee' => 'me@example.com']);

    $this->actingAs($this->me)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('myIssues.0.key', 'WEB-1')
            ->where('projects.0.key', 'WEB')
            ->where('projects.0.summary.open', 1));
});
