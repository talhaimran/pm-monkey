<?php

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\InvitationService;
use App\Services\TokenService;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/** Step 0 PR 8 scan, F8/F10/F11: who may see, change and revoke a pending invitation. */
beforeEach(function () {
    Mail::fake();
    $this->owner = User::factory()->create();
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->web = makeProject($this->owner, 'WEB');
    $this->api = makeProject($this->owner, 'API');
    $this->projectAdmin = User::factory()->create();
    $this->ws->addMember($this->projectAdmin, WorkspaceRole::Member);
    addMember($this->web, $this->projectAdmin, ProjectRole::Admin);
});

test('F8: a project admin cannot revoke a workspace-Admin invitation that names their project', function () {
    $invitation = app(InvitationService::class)->invite($this->ws, $this->owner, 'boss@example.com', WorkspaceRole::Admin, $this->web, ProjectRole::Admin);

    $this->actingAs($this->projectAdmin)->delete(w("/projects/WEB/invitations/{$invitation->id}"))->assertForbidden();
    expect(WorkspaceInvitation::find($invitation->id))->not->toBeNull();

    // Their own project's Member invitations they can.
    $mine = makeInvitation($this->web, $this->projectAdmin, 'dev@example.com', 'member');
    $this->actingAs($this->projectAdmin)->delete(w("/projects/WEB/invitations/{$mine->id}"))->assertRedirect();
    expect(WorkspaceInvitation::find($mine->id))->toBeNull();
});

test('F11: a token scoped to one project cannot overwrite invitations outside it', function () {
    makeInvitation($this->api, $this->owner, 'x@example.com', 'member');
    app(InvitationService::class)->invite($this->ws, $this->owner, 'y@example.com', WorkspaceRole::Admin);
    useWorkspace($this->ws);
    $token = app(TokenService::class)->create($this->owner, 'scoped', true, ['WEB'])->plainTextToken;

    foreach (['x@example.com', 'y@example.com'] as $email) {
        $this->withToken($token)->postJson('/api/v1/projects/WEB/invitations', ['email' => $email, 'role' => 'member'])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
    expect(WorkspaceInvitation::firstWhere('email', 'x@example.com')->project_id)->toBe($this->api->id)
        ->and(WorkspaceInvitation::firstWhere('email', 'y@example.com')->role)->toBe(WorkspaceRole::Admin);
});

test('F10: pending invitations are listed only to a verified address', function () {
    makeInvitation($this->web, $this->owner, 'claimed@example.com', 'member');
    $squatter = User::factory()->unverified()->create(['email' => 'claimed@example.com']);
    makeWorkspace($squatter, 'mine');

    $this->actingAs($squatter)->get('/mine/projects')->assertInertia(fn (Assert $page) => $page->where('invitations', []));
    $squatter->markEmailAsVerified();
    $this->actingAs($squatter)->get('/mine/projects')->assertInertia(fn (Assert $page) => $page->has('invitations', 1));
});
