<?php

use App\Enums\ProjectRole;
use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\DeleteComment;
use App\Mcp\Tools\DeleteIssue;
use App\Mcp\Tools\GetBacklog;
use App\Mcp\Tools\GetBoard;
use App\Mcp\Tools\GetIssue;
use App\Mcp\Tools\ListColumns;
use App\Mcp\Tools\MyIssues;
use App\Mcp\Tools\ProjectSummary;
use App\Mcp\Tools\SearchIssues;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;
use App\Services\ColumnService;
use App\Services\CommentService;
use App\Services\IssueService;
use App\Services\ProjectService;
use App\Services\SprintService;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->project = makeProject($this->admin, 'WEB');
    $this->issues = app(IssueService::class);
});

test('deleting keeps the row, logs it and hides the issue', function () {
    $issue = makeIssue($this->project, $this->admin, ['title' => 'Gone soon']);

    $this->actingAs($this->admin)->delete(w('/issues/WEB-1'), ['confirm' => true])->assertRedirect(w('/projects/WEB'));

    expect(Issue::count())->toBe(0)
        ->and(Issue::withTrashed()->sole()->deleted_at)->not->toBeNull()
        ->and(Activity::where('issue_id', $issue->id)->latest('id')->first())
        ->action->toBe('deleted')->user_id->toBe($this->admin->id)->via->toBe('web');
});

test('a deleted issue is absent from every web screen', function () {
    makeIssue($this->project, $this->admin, ['title' => 'Stays', 'assignee' => 'admin@example.com']);
    $gone = makeIssue($this->project, $this->admin, ['title' => 'Vanishes', 'assignee' => 'admin@example.com', 'story_points' => 5]);
    $this->issues->delete($gone, $this->admin);
    $this->actingAs($this->admin);

    foreach ([w('/projects/WEB'), w('/projects/WEB/backlog'), w('/projects/WEB/issues'), w('/search?q=Vanish'), w('/dashboard'), w('/projects')] as $url) {
        $this->get($url)->assertOk()->assertDontSee('Vanishes');
    }
    $this->get(w('/issues/WEB-2'))->assertSessionHasErrors('issue');
    // Project settings lists it only under the Admin's "Deleted issues" (see DeletedIssuesTest); counts leave it out.
    $this->get(w('/projects/WEB/settings'))->assertInertia(fn (Assert $page) => $page->where('columns.0.issue_count', 1));
});

test('a deleted issue is absent from the API and MCP', function () {
    makeIssue($this->project, $this->admin, ['title' => 'Stays', 'assignee' => 'admin@example.com']);
    $gone = makeIssue($this->project, $this->admin, ['title' => 'Vanishes', 'assignee' => 'admin@example.com']);
    $this->issues->delete($gone, $this->admin);
    apiAs($this->admin);

    $this->getJson('/api/v1/issues/WEB-2')->assertNotFound();
    $this->patchJson('/api/v1/issues/WEB-2', ['title' => 'Back?'])->assertNotFound();
    foreach (['/api/v1/projects/WEB/issues', '/api/v1/projects/WEB/board', '/api/v1/projects/WEB/backlog', '/api/v1/issues?q=Vanish'] as $url) {
        $this->getJson($url)->assertOk()->assertDontSee('Vanishes');
    }

    PmServer::tool(GetIssue::class, ['issue' => 'WEB-2'])->assertHasErrors(['was not found']);
    foreach ([[GetBoard::class, ['project' => 'WEB']], [GetBacklog::class, ['project' => 'WEB']], [SearchIssues::class, ['project' => 'WEB']], [MyIssues::class, []], [ProjectSummary::class, ['project' => 'WEB']]] as [$tool, $args]) {
        PmServer::tool($tool, $args)->assertOk()->assertDontSee('WEB-2')->assertDontSee('Vanishes');
    }
    PmServer::tool(ListColumns::class, ['project' => 'WEB'])->assertOk()->assertSee('"issue_count":1')->assertDontSee('"issue_count":2');
});

test('API and MCP deletes are soft and still need confirm', function () {
    makeIssue($this->project, $this->admin);
    makeIssue($this->project, $this->admin);
    apiAs($this->admin);

    $this->deleteJson('/api/v1/issues/WEB-1')->assertStatus(422);
    $this->deleteJson('/api/v1/issues/WEB-1', ['confirm' => true])->assertNoContent();
    PmServer::tool(DeleteIssue::class, ['issue' => 'WEB-2'])->assertHasErrors(['confirm: true']);
    PmServer::tool(DeleteIssue::class, ['issue' => 'WEB-2', 'confirm' => true])->assertOk();

    expect(Issue::count())->toBe(0)->and(Issue::onlyTrashed()->count())->toBe(2)
        ->and(Activity::where('action', 'deleted')->pluck('via')->all())->toEqual(['api', 'mcp']);
});

test('a deleted key is never reused', function () {
    $this->issues->delete(makeIssue($this->project, $this->admin), $this->admin);

    expect(makeIssue($this->project, $this->admin)->key)->toBe('WEB-2');
});

test('restore brings back the issue and its sub-tasks', function () {
    $parent = makeIssue($this->project, $this->admin, ['title' => 'Parent']);
    makeIssue($this->project, $this->admin, ['title' => 'Child', 'parent' => 'WEB-1']);
    $this->issues->delete($parent, $this->admin);
    expect(Issue::count())->toBe(0);

    $this->actingAs($this->admin)->post(w('/issues/WEB-1/restore'))->assertRedirect();

    expect(Issue::count())->toBe(2)
        ->and(Activity::where('issue_id', $parent->id)->latest('id')->first()->action)->toBe('restored');
});

test('a sub-task deleted on its own stays deleted when its parent is restored', function () {
    $parent = makeIssue($this->project, $this->admin);
    $child = makeIssue($this->project, $this->admin, ['parent' => 'WEB-1']);
    $this->issues->delete($child, $this->admin);
    $this->travel(1)->minutes();
    $this->issues->delete($parent, $this->admin);

    $this->issues->restore($this->issues->resolveDeleted($this->admin, 'WEB-1'), $this->admin);

    expect(Issue::pluck('number')->all())->toBe([1]);
});

test('a sub-task cannot be restored while its parent is deleted', function () {
    $parent = makeIssue($this->project, $this->admin);
    makeIssue($this->project, $this->admin, ['parent' => 'WEB-1']);
    $this->issues->delete($parent, $this->admin);

    $this->actingAs($this->admin)->post(w('/issues/WEB-2/restore'))->assertSessionHasErrors('issue');
    expect(session('errors')->first('issue'))->toContain('WEB-1');
    expect(Issue::count())->toBe(0);
});

test('deleting a column moves its deleted issues too', function () {
    $gone = makeIssue($this->project, $this->admin, ['status' => 'In Review']);
    $this->issues->delete($gone, $this->admin);
    $column = $this->project->columns()->where('name', 'In Review')->first();

    app(ColumnService::class)->delete($column, null);

    $restored = $this->issues->restore($this->issues->resolveDeleted($this->admin, 'WEB-1'), $this->admin);
    expect($restored->column->name)->toBe('To Do');
});

test('completing a sprint ignores deleted issues', function () {
    $sprints = app(SprintService::class);
    $sprint = $sprints->create($this->project, ['name' => 'S1']);
    makeIssue($this->project, $this->admin, ['sprint' => 'S1']);
    $gone = makeIssue($this->project, $this->admin, ['sprint' => 'S1']);
    $sprints->start($sprint, []);
    $this->issues->delete($gone, $this->admin);

    expect($sprints->complete($sprint->refresh(), $this->admin, 'backlog'))->toBe(1)
        ->and(Issue::withTrashed()->find($gone->id)->sprint_id)->toBe($sprint->id);
});

test('viewers, read-only tokens and outsiders cannot delete or restore', function () {
    $issue = makeIssue($this->project, $this->admin);
    $viewer = addMember($this->project, User::factory()->create(), ProjectRole::Viewer);
    $outsider = User::factory()->create();
    joinWorkspace($outsider); // in the workspace, not in the project

    $this->actingAs($viewer)->delete(w('/issues/WEB-1'), ['confirm' => true])->assertForbidden();
    $this->issues->delete($issue, $this->admin);
    $this->actingAs($viewer)->post(w('/issues/WEB-1/restore'))->assertForbidden();
    // Web forms report a 404 lookup as a validation error on redirect.
    $this->actingAs($outsider)->post(w('/issues/WEB-1/restore'))->assertSessionHasErrors('project');
    expect(Issue::count())->toBe(0);

    $this->issues->restore($issue, $this->admin);
    $this->flushHeaders();
    app('auth')->forgetGuards();
    apiAs($this->admin, ['read']);
    $this->deleteJson('/api/v1/issues/WEB-1', ['confirm' => true])->assertForbidden();
    expect(Issue::count())->toBe(1);
});

test('comments are soft deleted and can be restored', function () {
    $issue = makeIssue($this->project, $this->admin);
    $comment = app(CommentService::class)->add($issue, $this->admin, 'Secret note');
    apiAs($this->admin);

    PmServer::tool(DeleteComment::class, ['comment_id' => $comment->id])->assertOk();

    expect(Comment::count())->toBe(0)->and(Comment::withTrashed()->count())->toBe(1);
    $this->getJson('/api/v1/issues/WEB-1')->assertOk()->assertDontSee('Secret note');
    PmServer::tool(GetIssue::class, ['issue' => 'WEB-1'])->assertOk()->assertDontSee('Secret note');
    $this->actingAs($this->admin)->get(w('/issues/WEB-1'))->assertOk()->assertDontSee('Secret note');

    $outsider = User::factory()->create();
    joinWorkspace($outsider); // in the workspace, not in the project
    $this->actingAs($outsider)->post(w("/comments/{$comment->id}/restore"))->assertSessionHasErrors('comment');
    expect(Comment::count())->toBe(0);
    $this->actingAs($this->admin)->post(w("/comments/{$comment->id}/restore"))->assertRedirect();
    expect(Comment::count())->toBe(1);
});

test('deleted_at cannot be set through issue input', function () {
    makeIssue($this->project, $this->admin);
    apiAs($this->admin);

    $this->patchJson('/api/v1/issues/WEB-1', ['title' => 'Still here', 'deleted_at' => now()->toDateTimeString()])->assertOk();

    expect(Issue::sole()->deleted_at)->toBeNull();
});

test('issues:purge removes what was deleted more than 30 days ago and logs it', function () {
    Log::spy();
    $old = makeIssue($this->project, $this->admin, ['title' => 'Old']);
    $recent = makeIssue($this->project, $this->admin, ['title' => 'Recent']);
    $kept = makeIssue($this->project, $this->admin, ['title' => 'Kept']);
    $comments = app(CommentService::class);
    $oldComment = $comments->add($kept, $this->admin, 'old comment');

    $this->travelTo(now()->subDays(31), function () use ($old, $oldComment, $comments) {
        $this->issues->delete($old, $this->admin);
        $comments->delete($oldComment);
    });
    $this->issues->delete($recent, $this->admin);

    $this->artisan('issues:purge')->assertSuccessful();

    expect(Issue::withTrashed()->pluck('title')->all())->toEqual(['Recent', 'Kept'])
        ->and(Comment::withTrashed()->count())->toBe(0);
    Log::shouldHaveReceived('info')->once()->withArgs(fn ($message, $context) => $message === 'issue.purged' && $context['key'] === 'WEB-1');
});

test('deleting a project removes its live and deleted issues for good', function () {
    makeIssue($this->project, $this->admin);
    $this->issues->delete(makeIssue($this->project, $this->admin), $this->admin);
    makeIssue($this->project, $this->admin, ['parent' => 'WEB-1']);

    app(ProjectService::class)->delete($this->project);

    expect(Issue::withTrashed()->count())->toBe(0);
});
