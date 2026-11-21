<?php

use App\Enums\WorkspaceRole;
use App\Mcp\Servers\LocalPmServer;
use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\DeleteProject;
use App\Mcp\Tools\UpdateIssue;
use App\Models\User;
use App\Services\AgentService;
use App\Services\InvitationService;
use App\Services\ProjectService;
use Illuminate\Support\Facades\Mail;

/** Step 0 PR 3 security review probes: roles, view-all and scheduled deletion. */
beforeEach(function () {
    Mail::fake();
    $this->owner = User::factory()->create();
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->admin = User::factory()->create();
    $this->ws->addMember($this->admin, WorkspaceRole::Admin);
    $this->member = User::factory()->create();
    $this->ws->addMember($this->member, WorkspaceRole::Member);
    $this->secret = app(ProjectService::class)->create($this->member, ['key' => 'SEC', 'name' => 'Secret']);
    makeIssue($this->secret, $this->member);
});

test('an Admin cannot make themselves an Owner', function () {
    $this->actingAs($this->admin)->patch("/acme/settings/members/{$this->admin->id}", ['role' => 'owner'])
        ->assertSessionHasErrors(['role' => 'Only Owners can add, change or remove Owners.']);
    expect($this->admin->workspaceRole($this->ws))->toBe(WorkspaceRole::Admin);
});

test('seeing every project gives a workspace Admin no write on API or MCP until they join', function () {
    apiAs($this->admin);

    $this->patchJson('/api/v1/issues/SEC-1', ['title' => 'x'])->assertForbidden();
    $this->deleteJson('/api/v1/projects/SEC', ['confirm' => true])->assertForbidden();
    PmServer::tool(UpdateIssue::class, ['issue' => 'SEC-1', 'title' => 'x'])->assertHasErrors(["You haven't joined SEC."]);
    PmServer::tool(DeleteProject::class, ['project' => 'SEC', 'confirm' => true])->assertHasErrors(["You haven't joined SEC."]);
    expect($this->secret->fresh())->not->toBeNull()->and($this->secret->roleFor($this->admin))->toBeNull();
});

test('a scheduled workspace refuses the stdio MCP token too', function () {
    $token = app(AgentService::class)->create($this->member, ['name' => 'agent', 'level' => 'write'])[1];
    $this->ws->forceFill(['deletion_scheduled_at' => now()->addDays(7)])->save();

    expect(fn () => LocalPmServer::authenticate($token))->toThrow(RuntimeException::class, 'Acme is scheduled for deletion on');
});

test('an invitation into a workspace scheduled for deletion cannot be accepted', function () {
    $invitation = app(InvitationService::class)->invite($this->ws, $this->owner, 'late@example.com', WorkspaceRole::Member);
    $this->ws->forceFill(['deletion_scheduled_at' => now()->addDays(7)])->save();
    $late = User::factory()->create(['email' => 'late@example.com']);

    expect(fn () => app(InvitationService::class)->accept($invitation, $late))->toThrow(Exception::class, InvitationService::GONE);
    expect($late->workspaceRole($this->ws))->toBeNull();
});
