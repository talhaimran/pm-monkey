<?php

use App\Enums\AgentLevel;
use App\Enums\ApprovalStatus;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\ApprovalRequest;
use App\Models\Issue;
use App\Models\User;
use App\Services\ApprovalService;

/*
 * Step A2: only an agent that may move the issue can open a request (owner's role → level → project list), only a
 * person who may decide sees or decides it, and nobody learns about requests in projects they can't see.
 */

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Talha Imran']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->web = makeProject($this->owner, 'WEB');
    makeProject($this->owner, 'API');
    makeIssue($this->web, $this->owner);
});

test('an agent that could not move the issue opens no request', function (Closure $agent, int $status) {
    [, $plain] = $agent($this);

    asToken($plain)->postJson('/api/v1/issues/WEB-1/move', ['status' => 'Done'])->assertStatus($status);
    expect(ApprovalRequest::count())->toBe(0);
})->with([
    'read-only agent' => [fn ($t) => makeAgent($t->owner, AgentLevel::Read), 403],
    "a Viewer's agent" => [fn ($t) => makeAgent(addMember($t->web, User::factory()->create(), ProjectRole::Viewer), AgentLevel::Write), 403],
    'agent limited to another project' => [fn ($t) => makeAgent($t->owner, AgentLevel::Write, ['API']), 404],
]);

test('an agent token cannot decide: the approve and reject routes are web-only', function () {
    [, $plain] = makeAgent($this->owner, AgentLevel::Delete);
    asToken($plain)->postJson('/api/v1/issues/WEB-1/move', ['status' => 'Done'])->assertStatus(202);
    $id = ApprovalRequest::sole()->id;

    // A fresh request would start on the web guard; the API call above switched this test's default to sanctum.
    asToken($plain);
    app('auth')->shouldUse('web');
    $this->post(w("/approvals/{$id}/approve"))->assertRedirect(route('login'));
    $this->post(w("/approvals/{$id}/reject"))->assertRedirect(route('login'));
    expect(ApprovalRequest::sole()->status)->toBe(ApprovalStatus::Pending)->and(Issue::sole()->column->name)->toBe('To Do');
});

test('a person who cannot see the project gets the same answer as for a request that does not exist', function () {
    [$agent] = makeAgent($this->owner, AgentLevel::Write);
    $request = app(ApprovalService::class)->requestDone(Issue::sole()->setRelation('project', $this->web), $this->owner, $agent, $this->web->columns()->where('name', 'Done')->sole(), null);
    $outsider = asWorkspaceMember(addMember(makeProject($this->owner, 'OPS'), User::factory()->create(), ProjectRole::Admin));

    $notFound = fn (int $id) => ['approval' => "Approval request {$id} was not found. Open your dashboard to see the requests you can decide."];
    $this->actingAs($outsider)->post(w("/approvals/{$request->id}/approve"))->assertSessionHasErrors($notFound($request->id));
    $this->post(w("/approvals/{$request->id}/reject"))->assertSessionHasErrors($notFound($request->id));
    $this->post(w('/approvals/999999/approve'))->assertSessionHasErrors($notFound(999999));

    expect($request->fresh()->status)->toBe(ApprovalStatus::Pending);
});

test('a workspace Admin who has not joined the project cannot decide its requests', function () {
    $wsAdmin = User::factory()->create();
    $this->ws->addMember($wsAdmin, WorkspaceRole::Admin);
    [$agent] = makeAgent($this->owner, AgentLevel::Write);
    $request = app(ApprovalService::class)->requestDone(Issue::sole()->setRelation('project', $this->web), $this->owner, $agent, $this->web->columns()->where('name', 'Done')->sole(), null);

    $this->actingAs($wsAdmin)->post(w("/approvals/{$request->id}/approve"))->assertForbidden();
    expect($request->fresh()->status)->toBe(ApprovalStatus::Pending);
});

test('the agent reason is stored as plain text and never rendered as HTML', function () {
    [, $plain] = makeAgent($this->owner, AgentLevel::Write);
    asToken($plain)->postJson('/api/v1/issues/WEB-1/move', ['status' => 'Done', 'reason' => '<img src=x onerror=alert(1)>'])->assertStatus(202);

    $this->flushHeaders();
    app('auth')->forgetGuards();
    $props = $this->actingAs($this->owner)->get(w('/dashboard'))->viewData('page')['props'];
    expect($props['pendingApprovals'][0]['reason'])->toBe('<img src=x onerror=alert(1)>')
        ->and(collect($props['pendingApprovals'][0])->keys()->filter(fn ($k) => str_ends_with($k, '_html'))->all())->toBe([]);
});
