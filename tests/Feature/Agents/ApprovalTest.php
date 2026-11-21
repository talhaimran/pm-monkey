<?php

use App\Enums\AgentLevel;
use App\Enums\ApprovalStatus;
use App\Enums\ProjectRole;
use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\BulkCreateIssues;
use App\Mcp\Tools\BulkUpdateIssues;
use App\Mcp\Tools\CreateIssue;
use App\Mcp\Tools\GetIssue;
use App\Mcp\Tools\MoveIssue;
use App\Mcp\Tools\UpdateIssue;
use App\Models\Activity;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AgentService;
use App\Services\ApprovalService;
use App\Services\BoardService;
use App\Services\ColumnService;
use App\Services\IssueService;
use App\Services\MembershipService;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Step A2: moving to Done (the project's last column, before step 1) needs a person. Every path an agent has opens
 * one approval request and moves nothing; the agent's owner or a project Admin decides, re-checked when they do.
 */

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Talha Imran']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->web = makeProject($this->owner, 'WEB');
    makeIssue($this->web, $this->owner, ['title' => 'Ship it', 'status' => 'In Review']);
    [$this->agent, $this->plain] = makeAgent($this->owner, AgentLevel::Write, name: "Talha's agent");
});

function issueOne(): Issue
{
    return Issue::withTrashed()->with('column')->where('number', 1)->sole();
}

/** Call an MCP tool as $agent (its owner with the agent's token), like the HTTP and stdio servers do. */
function agentTool($t, string $tool, array $args, $agent = null)
{
    $agent ??= $t->agent;
    app('auth')->forgetGuards();
    Auth::setUser($agent->owner->fresh()->withAccessToken($agent->token()->sole()));
    $response = PmServer::tool($tool, $args);
    // The MCP server marks the container for its own process; later web requests in this test aren't MCP.
    app()->forgetInstance(ActivityLogger::VIA_BINDING);

    return $response;
}

/** Every way an agent can ask for Done; returns the approval request id it was given. */
function askForDone($t, string $path, array $extra = []): int
{
    $mcpId = fn ($response) => (int) (fn () => preg_match('/\(request (\d+), expires /', json_encode($this->content()), $m) ? $m[1] : 0)->call($response->assertOk());

    return match ($path) {
        'move_issue' => $mcpId(agentTool($t, MoveIssue::class, ['issue' => 'WEB-1', 'status' => 'Done', ...$extra])),
        'update_issue' => $mcpId(agentTool($t, UpdateIssue::class, ['issue' => 'WEB-1', 'status' => 'Done', ...$extra])),
        'bulk_update_issues' => $mcpId(agentTool($t, BulkUpdateIssues::class, ['updates' => [['key' => 'WEB-1', 'status' => 'Done', ...$extra]]])),
        'PATCH /issues/{key}' => asToken($t->plain)->patchJson('/api/v1/issues/WEB-1', ['status' => 'Done', ...$extra])->assertStatus(202)->json('approval_request.id'),
        'POST /issues/{key}/move' => asToken($t->plain)->postJson('/api/v1/issues/WEB-1/move', ['status' => 'Done', ...$extra])->assertStatus(202)->json('approval_request.id'),
    };
}

const DONE_PATHS = ['move_issue', 'update_issue', 'bulk_update_issues', 'PATCH /issues/{key}', 'POST /issues/{key}/move'];

test('Review Focus 2: every path to Done by an agent opens one approval request and moves nothing', function (string $path) {
    $rank = issueOne()->rank;

    $id = askForDone($this, $path, ['reason' => 'PR merged']);
    expect(askForDone($this, $path))->toBe($id);

    $request = ApprovalRequest::sole();
    expect(issueOne()->column->name)->toBe('In Review')
        ->and(issueOne()->rank)->toBe($rank)
        ->and($request->id)->toBe($id)
        ->and($request->status)->toBe(ApprovalStatus::Pending)
        ->and($request->agent_id)->toBe($this->agent->id)
        ->and($request->owner_user_id)->toBe($this->owner->id)
        ->and($request->workspace_id)->toBe($this->ws->id)
        ->and($request->issue_version)->toBeNull()
        ->and($request->action)->toEqual([
            'to_column_id' => $this->web->columns()->where('name', 'Done')->value('id'),
            'to_column' => 'Done',
            'from_column_id' => $this->web->columns()->where('name', 'In Review')->value('id'),
            'reason' => 'PR merged',
        ])
        ->and($request->expires_at->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and(Activity::where('action', '!=', 'created')->count())->toBe(0);
})->with(DONE_PATHS);

test('REST answers 202 pending_approval with the request and the issue', function () {
    asToken($this->plain)->postJson('/api/v1/issues/WEB-1/move', ['status' => 'done'])
        ->assertStatus(202)
        ->assertJsonPath('status', 'pending_approval')
        ->assertJsonPath('approval_request.status', 'pending')
        ->assertJsonPath('approval_request.issue', 'WEB-1')
        ->assertJsonPath('approval_request.to', 'Done')
        ->assertJsonPath('approval_request.expires_at', fn ($at) => is_string($at))
        ->assertJsonPath('issue.key', 'WEB-1')
        ->assertJsonPath('issue.status', 'In Review');
});

test('MCP says who has to act and returns the same JSON', function () {
    $id = askForDone($this, 'move_issue');

    agentTool($this, MoveIssue::class, ['issue' => 'WEB-1', 'status' => 'Done'])
        ->assertSee(["WEB-1 needs a person's approval to move to Done (request {$id}, expires ", '"status":"pending_approval"', '"approval_request":{"id":'.$id]);
});

test('A-L5: the reason is optional and at most 200 characters', function () {
    asToken($this->plain)->patchJson('/api/v1/issues/WEB-1', ['status' => 'Done', 'reason' => str_repeat('x', 201)])->assertUnprocessable()->assertJsonValidationErrors('reason');
    agentTool($this, MoveIssue::class, ['issue' => 'WEB-1', 'status' => 'Done', 'reason' => str_repeat('x', 201)])->assertHasErrors();

    expect(ApprovalRequest::count())->toBe(0);
});

test('A-L6: an agent cannot create an issue in the last column', function () {
    $message = "Agents can't create issues in Done. Create it in another column, then move it; a person approves the move.";

    asToken($this->plain)->postJson('/api/v1/projects/WEB/issues', ['title' => 'x', 'status' => 'Done'])
        ->assertUnprocessable()->assertJsonPath('errors.status.0', $message);
    agentTool($this, CreateIssue::class, ['project' => 'WEB', 'title' => 'x', 'status' => 'Done'])->assertHasErrors([$message]);
    agentTool($this, BulkCreateIssues::class, ['project' => 'WEB', 'issues' => [['title' => 'x'], ['title' => 'y', 'status' => 'Done']]])->assertHasErrors(['[issues.1]']);

    expect(Issue::count())->toBe(1)->and(ApprovalRequest::count())->toBe(0);
    asToken($this->plain)->postJson('/api/v1/projects/WEB/issues', ['title' => 'x', 'status' => 'In Review'])->assertCreated();
});

test('A-L7: a mixed update applies the other fields and holds the status', function () {
    asToken($this->plain)->patchJson('/api/v1/issues/WEB-1', ['title' => 'Renamed', 'priority' => 'high', 'status' => 'Done'])
        ->assertStatus(202)
        ->assertJsonPath('issue.title', 'Renamed')
        ->assertJsonPath('issue.priority', 'high')
        ->assertJsonPath('issue.status', 'In Review')
        ->assertJsonPath('approval_request.to', 'Done');

    expect(Activity::where('action', 'updated')->sole()->changes)->toEqual([
        'title' => ['from' => 'Ship it', 'to' => 'Renamed'],
        'priority' => ['from' => 'medium', 'to' => 'high'],
    ]);
});

test('an agent moves to any other column on its own; a person moves to Done on their own', function () {
    asToken($this->plain)->postJson('/api/v1/issues/WEB-1/move', ['status' => 'In Progress'])->assertOk()->assertJsonPath('data.status', 'In Progress');

    $this->flushHeaders();
    app('auth')->forgetGuards();
    $this->actingAs($this->owner)->post(w('/issues/WEB-1/move'), ['status' => 'Done'])->assertSessionHasNoErrors();
    $this->patch(w('/issues/WEB-1'), ['status' => 'In Review'])->assertSessionHasNoErrors();
    $this->patch(w('/issues/WEB-1'), ['status' => 'Done'])->assertSessionHasNoErrors();

    expect(issueOne()->column->name)->toBe('Done')->and(ApprovalRequest::count())->toBe(0);
});

test("the agent's owner approves: the issue moves as the owner, with the agent and request in one activity entry", function () {
    $id = askForDone($this, 'move_issue');
    $audits = AuditLog::count();

    $this->actingAs($this->owner)->post(w("/approvals/{$id}/approve"))->assertSessionHasNoErrors();

    $request = ApprovalRequest::sole();
    $activity = Activity::latest('id')->first();
    expect(issueOne()->column->name)->toBe('Done')
        ->and($request->status)->toBe(ApprovalStatus::Approved)
        ->and($request->decided_by)->toBe($this->owner->id)
        ->and($request->decided_at)->not->toBeNull()
        ->and(Activity::where('action', '!=', 'created')->count())->toBe(1)
        ->and($activity->only(['action', 'user_id', 'agent_id', 'via']))->toEqual(['action' => 'approved', 'user_id' => $this->owner->id, 'agent_id' => $this->agent->id, 'via' => 'web'])
        ->and($activity->changes)->toEqual(['status' => ['from' => 'In Review', 'to' => 'Done'], 'approval_request' => ['id' => $id, 'to' => 'Done']])
        ->and(AuditLog::count())->toBe($audits)
        ->and(session('undo.message'))->toBe('WEB-1 moved to Done');

    $this->get(w('/issues/WEB-1'))->assertInertia(fn (Assert $page) => $page
        ->where('activity.0.action', 'approved')
        ->where('activity.0.user.name', 'Talha Imran')
        ->where('activity.0.agent', ['name' => "Talha's agent", 'owner' => 'Talha Imran'])
        ->where('activity.0.approval_request', ['id' => $id, 'to' => 'Done'])
        ->where('activity.0.changes', ['status' => ['from' => 'In Review', 'to' => 'Done']]));
});

test('undo after approving puts the issue back', function () {
    $id = askForDone($this, 'move_issue');
    $this->actingAs($this->owner)->post(w("/approvals/{$id}/approve"));

    $undo = session('undo');
    $this->post($undo['url'], $undo['data'])->assertSessionHasNoErrors();

    expect(issueOne()->column->name)->toBe('In Review');
});

test('a project Admin decides for a Member\'s agent; another Member cannot; a Viewer owner cannot either', function () {
    $sam = addMember($this->web, User::factory()->create(['name' => 'Sam Member']), ProjectRole::Member);
    $mia = addMember($this->web, User::factory()->create(), ProjectRole::Member);
    [$samAgent] = makeAgent($sam, AgentLevel::Write);
    agentTool($this, MoveIssue::class, ['issue' => 'WEB-1', 'status' => 'Done'], $samAgent)->assertOk();
    $id = ApprovalRequest::sole()->id;

    $this->actingAs($mia)->post(w("/approvals/{$id}/approve"))->assertForbidden();
    $this->actingAs($mia)->post(w("/approvals/{$id}/reject"))->assertForbidden();

    app(MembershipService::class)->changeRole($this->web, $sam, ProjectRole::Viewer);
    $this->actingAs($sam)->post(w("/approvals/{$id}/approve"))->assertForbidden()
        ->assertSee("Your role in WEB is viewer; viewers can't make changes.");
    expect(issueOne()->column->name)->toBe('In Review')->and(ApprovalRequest::sole()->status)->toBe(ApprovalStatus::Pending);

    $this->actingAs($this->owner)->post(w("/approvals/{$id}/approve"))->assertSessionHasNoErrors();
    expect(issueOne()->column->name)->toBe('Done')->and(ApprovalRequest::sole()->decided_by)->toBe($this->owner->id);
});

test('rejecting keeps the issue where it is and logs one activity entry with the note on the request', function () {
    $id = askForDone($this, 'move_issue');
    $audits = AuditLog::count();

    $this->actingAs($this->owner)->post(w("/approvals/{$id}/reject"), ['note' => str_repeat('x', 501)])->assertSessionHasErrors('note');
    $this->post(w("/approvals/{$id}/reject"), ['note' => 'Tests are failing'])->assertSessionHasNoErrors();

    $request = ApprovalRequest::sole();
    expect(issueOne()->column->name)->toBe('In Review')
        ->and($request->status)->toBe(ApprovalStatus::Rejected)
        ->and($request->note)->toBe('Tests are failing')
        ->and($request->decided_by)->toBe($this->owner->id)
        ->and(Activity::where('action', 'rejected')->sole()->only(['user_id', 'agent_id', 'changes']))
        ->toEqual(['user_id' => $this->owner->id, 'agent_id' => $this->agent->id, 'changes' => ['approval_request' => ['id' => $id, 'to' => 'Done']]])
        ->and(AuditLog::count())->toBe($audits);

    $this->post(w("/approvals/{$id}/approve"))->assertSessionHasErrors(['approval' => 'This request was already rejected.']);
    expect(issueOne()->column->name)->toBe('In Review');
});

test('a second decision finds the request already decided', function () {
    $id = askForDone($this, 'move_issue');
    $this->actingAs($this->owner)->post(w("/approvals/{$id}/approve"))->assertSessionHasNoErrors();

    $this->post(w("/approvals/{$id}/approve"))->assertSessionHasErrors(['approval' => 'This request was already approved.']);
    $this->post(w("/approvals/{$id}/reject"))->assertSessionHasErrors(['approval' => 'This request was already approved.']);
    expect(Activity::where('action', 'approved')->count())->toBe(1);
});

test('Review Focus 3: the issue moved since, so approving needs a second confirm', function () {
    $id = askForDone($this, 'move_issue');
    app(BoardService::class)->move(issueOne(), $this->owner, ['status' => 'In Progress']);

    $this->actingAs($this->owner)->post(w("/approvals/{$id}/approve"))
        ->assertSessionHasErrors(['approval' => 'WEB-1 moved from In Review to In Progress since this request. Approve again to move it to Done.']);
    expect(issueOne()->column->name)->toBe('In Progress')->and(ApprovalRequest::sole()->status)->toBe(ApprovalStatus::Pending);

    $this->get(w('/issues/WEB-1'))->assertInertia(fn (Assert $page) => $page
        ->where('issue.pending_approvals.0.moved', true)
        ->where('issue.pending_approvals.0.from', 'In Review')
        ->where('issue.pending_approvals.0.now', 'In Progress')
        ->where('issue.pending_approvals.0.can_decide', true));

    $this->post(w("/approvals/{$id}/approve"), ['confirm_changed' => true])->assertSessionHasNoErrors();
    expect(issueOne()->column->name)->toBe('Done')->and(ApprovalRequest::sole()->status)->toBe(ApprovalStatus::Approved);
});

test('Review Focus 3: a move that is no longer possible marks the request stale and moves nothing', function (Closure $change, string $why) {
    $id = askForDone($this, 'move_issue');
    $change($this);

    $this->actingAs($this->owner)->post(w("/approvals/{$id}/approve"), ['confirm_changed' => true])
        ->assertSessionHasErrors(['approval' => "Can't approve: {$why} The request is now stale."]);

    expect(ApprovalRequest::sole()->status)->toBe(ApprovalStatus::Stale)
        ->and(ApprovalRequest::sole()->note)->toBe($why)
        ->and(Activity::where('action', 'approved')->exists())->toBeFalse();
})->with([
    'issue deleted' => [fn ($t) => app(IssueService::class)->delete(issueOne(), $t->owner), 'WEB-1 was deleted.'],
    'target column deleted' => [fn ($t) => app(ColumnService::class)->delete($t->web->columns()->where('name', 'Done')->sole()->setRelation('project', $t->web), null), 'The column Done was deleted.'],
    'already there' => [fn ($t) => app(BoardService::class)->move(issueOne(), $t->owner, ['status' => 'Done']), 'WEB-1 is already in Done.'],
]);

test('Review Focus 3: a decider who lost Admin gets 403 and the request stays pending', function () {
    $sam = addMember($this->web, User::factory()->create(), ProjectRole::Member);
    $ada = addMember($this->web, User::factory()->create(), ProjectRole::Admin);
    [$samAgent] = makeAgent($sam, AgentLevel::Write);
    agentTool($this, MoveIssue::class, ['issue' => 'WEB-1', 'status' => 'Done'], $samAgent)->assertOk();
    $id = ApprovalRequest::sole()->id;

    app(MembershipService::class)->changeRole($this->web, $ada, ProjectRole::Member);

    $this->actingAs($ada)->post(w("/approvals/{$id}/approve"))->assertForbidden();
    expect(ApprovalRequest::sole()->status)->toBe(ApprovalStatus::Pending)->and(issueOne()->column->name)->toBe('In Review');
});

test('pending requests expire after 7 days; an expired one cannot be approved and the agent can ask again', function () {
    $id = askForDone($this, 'move_issue');

    $this->travel(6)->days();
    $this->artisan('approvals:expire')->assertSuccessful();
    expect(ApprovalRequest::sole()->status)->toBe(ApprovalStatus::Pending);

    $this->travel(1)->days();
    $this->artisan('approvals:expire')->expectsOutputToContain('Expired 1 approval request.')->assertSuccessful();
    expect(ApprovalRequest::sole()->status)->toBe(ApprovalStatus::Expired);

    $this->actingAs($this->owner)->post(w("/approvals/{$id}/approve"))->assertSessionHasErrors(['approval' => 'This request was already expired.']);
    expect(issueOne()->column->name)->toBe('In Review');

    $again = askForDone($this, 'move_issue');
    expect($again)->not->toBe($id)->and(ApprovalRequest::find($again)->status)->toBe(ApprovalStatus::Pending);
});

test('a request past its expiry is not reused or approved even before the hourly sweep', function () {
    $id = askForDone($this, 'move_issue');
    $this->travel(8)->days();

    $this->actingAs($this->owner)->post(w("/approvals/{$id}/approve"))->assertSessionHasErrors(['approval' => 'This request was already expired.']);
    expect(ApprovalRequest::find($id)->status)->toBe(ApprovalStatus::Expired)->and(askForDone($this, 'move_issue'))->not->toBe($id);
});

test('approvals:expire runs hourly', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('approvals:expire');
});

test('get_issue and GET /issues/{key} list the pending approvals', function () {
    $id = askForDone($this, 'move_issue', ['reason' => 'PR merged']);

    agentTool($this, GetIssue::class, ['issue' => 'WEB-1'])->assertSee(['"pending_approvals":[{"id":'.$id, '"to":"Done"', '"reason":"PR merged"']);
    asToken($this->plain)->getJson('/api/v1/issues/WEB-1')
        ->assertJsonPath('data.pending_approvals.0.id', $id)
        ->assertJsonPath('data.pending_approvals.0.agent', ['name' => "Talha's agent", 'owner' => 'Talha Imran']);

    $this->flushHeaders();
    app('auth')->forgetGuards();
    $this->actingAs($this->owner)->post(w("/approvals/{$id}/approve"));
    asToken($this->plain)->getJson('/api/v1/issues/WEB-1')->assertJsonPath('data.pending_approvals', []);
});

test('the issue page and the peek drawer carry the pending approvals for the AI box', function () {
    $id = askForDone($this, 'move_issue');

    $this->actingAs($this->owner)->get(w('/issues/WEB-1'))->assertInertia(fn (Assert $page) => $page
        ->where('issue.pending_approvals.0.id', $id)
        ->where('issue.pending_approvals.0.moved', false)
        ->where('issue.pending_approvals.0.can_decide', true));
    $this->get(w('/projects/WEB?issue=WEB-1'))->assertInertia(fn (Assert $page) => $page->where('peek.issue.pending_approvals.0.id', $id));
});

test('the dashboard lists exactly the requests the viewer can decide, in this workspace only', function () {
    $sam = addMember($this->web, User::factory()->create(), ProjectRole::Member);
    $mia = addMember($this->web, User::factory()->create(), ProjectRole::Member);
    [$samAgent] = makeAgent($sam, AgentLevel::Write);
    makeIssue($this->web, $this->owner, ['title' => 'Second']);
    $own = askForDone($this, 'move_issue');
    agentTool($this, MoveIssue::class, ['issue' => 'WEB-2', 'status' => 'Done'], $samAgent)->assertOk();
    $samRequest = ApprovalRequest::latest('id')->first()->id;

    // Another workspace where Talha is Admin too: never on this dashboard.
    $other = makeWorkspace($this->owner, 'other');
    $ops = makeProject($this->owner, 'OPS');
    makeIssue($ops, $this->owner);
    [$otherAgent] = makeAgent($this->owner, AgentLevel::Write);
    app(ApprovalService::class)->requestDone($ops->issues()->sole()->setRelation('project', $ops), $this->owner, $otherAgent, $ops->columns()->where('name', 'Done')->sole(), null);
    useWorkspace($this->ws, $this->owner);

    $ids = fn (User $user) => collect($this->actingAs($user)->get(w('/dashboard'))->viewData('page')['props']['pendingApprovals'])->pluck('id')->all();

    expect($ids($this->owner))->toEqualCanonicalizing([$own, $samRequest])
        ->and($ids($sam))->toBe([$samRequest])
        ->and($ids($mia))->toBe([]);

    app(MembershipService::class)->changeRole($this->web, $sam, ProjectRole::Viewer);
    expect($ids($sam))->toBe([]);
});

test('revoking an agent marks its pending requests stale at once: off the dashboard and out of get_issue', function () {
    $id = askForDone($this, 'move_issue');

    app(AgentService::class)->revoke($this->agent, $this->owner);

    expect(ApprovalRequest::find($id)->status)->toBe(ApprovalStatus::Stale)
        ->and(ApprovalRequest::find($id)->note)->toBe("Talha's agent was revoked.");
    $this->actingAs($this->owner)->get(w('/dashboard'))->assertInertia(fn (Assert $page) => $page->where('pendingApprovals', []));
    $this->get(w('/issues/WEB-1'))->assertInertia(fn (Assert $page) => $page->where('issue.pending_approvals', []));

    // Review Focus 3: approving afterwards moves nothing.
    $this->post(w("/approvals/{$id}/approve"))->assertSessionHasErrors(['approval' => 'This request was already stale.']);
    expect(issueOne()->column->name)->toBe('In Review');
});

test("undo's conflict message after an approved move names the move, never the request link", function () {
    $sam = addMember($this->web, User::factory()->create(['name' => 'Sam Member']), ProjectRole::Member);
    $this->actingAs($sam)->patch(w('/issues/WEB-1'), ['title' => 'Renamed']);
    $undo = session('undo');
    $id = askForDone($this, 'move_issue');
    $this->actingAs($this->owner)->post(w("/approvals/{$id}/approve"));

    $this->actingAs($sam)->patch($undo['url'], $undo['data'])
        ->assertSessionHasErrors(['undo' => "Can't undo: Talha Imran moved WEB-1 to Done."]);
    expect(session('errors')->getBag('default')->first('undo'))->not->toContain('approval_request');
});
