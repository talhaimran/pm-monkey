<?php

use App\Enums\ProjectRole;
use App\Mcp\Resources\IssueResource as IssueMcpResource;
use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\AddComment;
use App\Mcp\Tools\CreateIssue;
use App\Mcp\Tools\CreateLabel;
use App\Mcp\Tools\DeleteComment;
use App\Mcp\Tools\DeleteIssue;
use App\Mcp\Tools\DeleteLabel;
use App\Mcp\Tools\GetIssue;
use App\Mcp\Tools\GetIssueActivity;
use App\Mcp\Tools\ListLabels;
use App\Mcp\Tools\UpdateComment;
use App\Mcp\Tools\UpdateIssue;
use App\Mcp\Tools\UpdateLabel;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->project = makeProject($this->admin, 'WEB');
    apiAs($this->admin);
});

test('create_issue creates, summarizes and logs via mcp', function () {
    PmServer::tool(CreateIssue::class, ['project' => 'WEB', 'title' => 'From agent', 'type' => 'bug', 'status' => 'In Review'])
        ->assertOk()
        ->assertSee(['Created WEB-1', '"status":"In Review"']);

    expect(Activity::sole()->via)->toBe('mcp');
});

test('create_issue errors list valid columns', function () {
    PmServer::tool(CreateIssue::class, ['project' => 'WEB', 'title' => 'X', 'status' => 'Doing'])
        ->assertHasErrors(['To Do, In Progress, In Review, Done']);
});

test('get_issue and update_issue work by key', function () {
    makeIssue($this->project, $this->admin, ['title' => 'Old']);

    PmServer::tool(GetIssue::class, ['issue' => 'web-1'])->assertOk()->assertSee('"title":"Old"');
    PmServer::tool(UpdateIssue::class, ['issue' => 'WEB-1', 'status' => 'done', 'priority' => 'high'])
        ->assertOk()
        ->assertSee(['Updated WEB-1', '"status":"Done"']);
});

test('update_issue can clear fields with null', function () {
    addMember($this->project, User::factory()->create(['email' => 'dev@example.com']), ProjectRole::Member);
    makeIssue($this->project, $this->admin, ['assignee' => 'dev@example.com']);

    PmServer::tool(UpdateIssue::class, ['issue' => 'WEB-1', 'assignee' => null])->assertOk();

    expect(Issue::first()->assignee_id)->toBeNull();
});

test('delete_issue requires confirm', function () {
    makeIssue($this->project, $this->admin);

    PmServer::tool(DeleteIssue::class, ['issue' => 'WEB-1'])->assertHasErrors(['confirm: true']);
    PmServer::tool(DeleteIssue::class, ['issue' => 'WEB-1', 'confirm' => true])->assertOk();
    expect(Issue::count())->toBe(0);
});

test('comment tools', function () {
    makeIssue($this->project, $this->admin);

    PmServer::tool(AddComment::class, ['issue' => 'WEB-1', 'body' => 'hi'])->assertOk()->assertSee('Commented on WEB-1');
    $id = Comment::sole()->id;
    PmServer::tool(UpdateComment::class, ['comment_id' => $id, 'body' => 'edited'])->assertOk()->assertSee('edited');
    PmServer::tool(DeleteComment::class, ['comment_id' => $id])->assertOk();

    expect(Comment::count())->toBe(0);
});

test('label tools', function () {
    PmServer::tool(CreateLabel::class, ['project' => 'WEB', 'name' => 'ui', 'color' => '#ff0000'])->assertOk();
    PmServer::tool(ListLabels::class, ['project' => 'WEB'])->assertOk()->assertSee('"name":"ui"');
    PmServer::tool(UpdateLabel::class, ['project' => 'WEB', 'label' => 'ui', 'name' => 'frontend'])->assertOk();
    PmServer::tool(DeleteLabel::class, ['project' => 'WEB', 'label' => 'frontend'])->assertOk();
    PmServer::tool(ListLabels::class, ['project' => 'WEB'])->assertDontSee('frontend');
});

test('get_issue_activity shows the history', function () {
    makeIssue($this->project, $this->admin);
    PmServer::tool(UpdateIssue::class, ['issue' => 'WEB-1', 'title' => 'Renamed']);

    PmServer::tool(GetIssueActivity::class, ['issue' => 'WEB-1'])
        ->assertOk()
        ->assertSee(['"action":"updated"', '"via":"mcp"']);
});

test('issue resource reads by key', function () {
    makeIssue($this->project, $this->admin, ['title' => 'Readable']);

    PmServer::resource(IssueMcpResource::class, ['key' => 'WEB-1'])->assertOk()->assertSee('Readable');
});

test('read-only tokens cannot create issues over mcp', function () {
    apiAs($this->admin, ['read']);

    PmServer::tool(CreateIssue::class, ['project' => 'WEB', 'title' => 'X'])->assertHasErrors(['read-only']);
});
