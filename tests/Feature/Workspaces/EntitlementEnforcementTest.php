<?php

use App\Enums\Entitlement;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Exceptions\PlanLimitReached;
use App\Mail\ProjectInvitationMail;
use App\Mcp\Servers\PmServer;
use App\Mcp\Tools\CreateProject;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\Entitlements;
use App\Services\InvitationService;
use App\Services\WorkspaceMemberService;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function () {
    Mail::fake();
    config(['pm.billing_url' => 'https://billing.example.test/acme']);
    $this->owner = User::factory()->create(['email' => 'owner@example.com']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->project = makeProject($this->owner, 'WEB');
    $entitlements = app(Entitlements::class);
    $entitlements->set($this->ws, Entitlement::BilledUsers, '2', 'override');
    $entitlements->set($this->ws, Entitlement::Viewers, '3', 'override');
    $entitlements->set($this->ws, Entitlement::Projects, '1', 'override');
    $this->invitations = app(InvitationService::class);
});

function planLimitJson(string $limit, int $allowed, int $current, string $message): array
{
    return ['error' => 'plan_limit', 'limit' => $limit, 'allowed' => $allowed, 'current' => $current, 'message' => $message, 'upgrade_url' => 'https://billing.example.test/acme'];
}

test('projects: the second project is refused on web, API and MCP', function () {
    $message = 'Your plan allows 1 project. Upgrade to add more.';

    $this->actingAs($this->owner)->from('/acme/projects')->post('/acme/projects', ['key' => 'TWO', 'name' => 'Two'])
        ->assertRedirect('/acme/projects')
        ->assertSessionHas('planLimit', planLimitJson('projects', 1, 1, $message))
        ->assertSessionHasErrors(['plan_limit' => $message]);

    apiAsPerson($this->owner);  // projects, invitations and members are people-only (A-D2)
    $this->postJson('/api/v1/projects', ['key' => 'TWO', 'name' => 'Two'])->assertForbidden()->assertExactJson(planLimitJson('projects', 1, 1, $message));
    PmServer::tool(CreateProject::class, ['key' => 'TWO', 'name' => 'Two'])->assertHasErrors([$message]);

    expect($this->ws->projects()->count())->toBe(1);
    expect(AuditLog::where('action', 'plan_limit.reached')->count())->toBe(3)
        ->and(AuditLog::where('action', 'plan_limit.reached')->first()->metadata)->toEqual(['limit' => 'projects', 'allowed' => 1, 'current' => 1]);
});

test('billed users: pending invitations count, so the next billed invitation is refused on web and API', function () {
    $this->invitations->invite($this->ws, $this->owner, 'admin@example.com', WorkspaceRole::Admin);
    $message = 'Your plan allows 2 billed users. Upgrade to add more.';

    $this->actingAs($this->owner)->from('/acme/settings/members')
        ->post('/acme/settings/members/invitations', ['email' => 'b@example.com', 'role' => 'admin'])
        ->assertSessionHas('planLimit', planLimitJson('billed_users', 2, 2, $message));
    apiAsPerson($this->owner);  // projects, invitations and members are people-only (A-D2)
    $this->postJson('/api/v1/projects/WEB/invitations', ['email' => 'c@example.com', 'role' => 'member'])
        ->assertForbidden()->assertExactJson(planLimitJson('billed_users', 2, 2, $message));

    // A Viewer invitation still fits.
    $this->postJson('/api/v1/projects/WEB/invitations', ['email' => 'v@example.com', 'role' => 'viewer'])->assertCreated();
    expect(WorkspaceInvitation::pluck('email')->sort()->values()->all())->toBe(['admin@example.com', 'v@example.com']);
});

test('viewers: the fourth Viewer is refused', function () {
    foreach (['v1', 'v2', 'v3'] as $v) {
        $this->invitations->invite($this->ws, $this->owner, "{$v}@example.com", WorkspaceRole::Member);
    }

    expect(fn () => $this->invitations->invite($this->ws, $this->owner, 'v4@example.com', WorkspaceRole::Member))
        ->toThrow(PlanLimitReached::class, 'Your plan allows 3 Viewers. Upgrade to add more.');
    expect(WorkspaceInvitation::count())->toBe(3);
});

test('a Viewer becoming a project Member or Admin needs a billed seat (web and API)', function () {
    $this->ws->addMember($admin = User::factory()->create(), WorkspaceRole::Admin);
    $viewer = addMember($this->project, User::factory()->create(['email' => 'vic@example.com']), ProjectRole::Viewer);

    $this->actingAs($this->owner)->from('/acme/projects/WEB/settings')->patch("/acme/projects/WEB/members/{$viewer->id}", ['role' => 'member'])
        ->assertSessionHas('planLimit');
    apiAsPerson($this->owner);  // projects, invitations and members are people-only (A-D2)
    $this->patchJson('/api/v1/projects/WEB/members/vic@example.com', ['role' => 'admin'])->assertForbidden()->assertJsonPath('limit', 'billed_users');
    expect($this->project->roleFor($viewer))->toBe(ProjectRole::Viewer);

    // Someone already billed changes freely.
    $this->patchJson('/api/v1/projects/WEB/members/'.urlencode($this->owner->email), ['role' => 'admin'])->assertOk();
});

test('a workspace Member becoming Admin needs a billed seat', function () {
    $this->ws->addMember(User::factory()->create(), WorkspaceRole::Admin);
    $member = User::factory()->create();
    $this->ws->addMember($member, WorkspaceRole::Member);

    $this->actingAs($this->owner)->from('/acme/settings/members')->patch("/acme/settings/members/{$member->id}", ['role' => 'admin'])
        ->assertSessionHas('planLimit', planLimitJson('billed_users', 2, 2, 'Your plan allows 2 billed users. Upgrade to add more.'));
    expect($member->workspaceRole($this->ws))->toBe(WorkspaceRole::Member);
    expect(fn () => app(WorkspaceMemberService::class)->changeRole($this->ws, $this->owner, $member, WorkspaceRole::Owner))->toThrow(PlanLimitReached::class);
});

test('review focus 3: an invitation is refused at accept time when the workspace is now over the limit', function () {
    $invitation = $this->invitations->invite($this->ws, $this->owner, 'late@example.com', WorkspaceRole::Admin);
    // The plan dropped after it was sent: now one billed user too many.
    app(Entitlements::class)->set($this->ws, Entitlement::BilledUsers, '1', 'plan');
    $late = User::factory()->create(['email' => 'late@example.com']);

    expect(fn () => $this->invitations->accept($invitation, $late))->toThrow(PlanLimitReached::class, 'Your plan allows 1 billed user.');
    expect($late->workspaceRole($this->ws))->toBeNull()->and($invitation->fresh())->not->toBeNull();

    $this->actingAs($late)->post("/invitations/{$invitation->id}/accept")->assertSessionHas('planLimit');
    expect($late->workspaceRole($this->ws))->toBeNull();
});

test('no rows means unlimited', function () {
    $this->ws->entitlements()->delete();
    apiAsPerson($this->owner);  // projects, invitations and members are people-only (A-D2)

    foreach (['TWO', 'THREE'] as $key) {
        $this->postJson('/api/v1/projects', ['key' => $key, 'name' => $key])->assertCreated();
    }
});

test('a message for an Owner on self-hosted has no upgrade link', function () {
    config(['pm.billing_url' => null]);
    apiAsPerson($this->owner);  // projects, invitations and members are people-only (A-D2)

    $this->postJson('/api/v1/projects', ['key' => 'TWO', 'name' => 'Two'])->assertForbidden()->assertJsonPath('upgrade_url', null);
});

test('D6: a new Cloud workspace starts with the Free values; self-hosted gets no rows', function () {
    app(WorkspaceService::class)->purge($this->ws);
    config(['pm.edition' => 'self_hosted']);
    $first = app(WorkspaceService::class)->create(User::factory()->create(), 'First');
    expect($first->entitlements()->count())->toBe(0);

    config(['pm.edition' => 'cloud']);
    $cloud = app(WorkspaceService::class)->create(User::factory()->create(), 'Cloudy');
    $rows = $cloud->entitlements()->pluck('value', 'key')->all();
    expect($rows)->toMatchArray(['billed_users' => '2', 'viewers' => '3', 'projects' => '1', 'api_per_minute' => '60', 'api_per_day' => '2000', 'webhooks' => '0'])
        ->and($cloud->entitlements()->distinct()->pluck('source')->all())->toBe(['default'])
        ->and(app(Entitlements::class)->get($cloud, Entitlement::Webhooks))->toBeFalse();
});

/** The invitation token from the last invitation mail sent. */
function lastInvitationToken(): string
{
    $token = null;
    Mail::assertSent(ProjectInvitationMail::class, function ($mail) use (&$token) {
        $token = $mail->token;

        return true;
    });

    return $token;
}

test('an expired invitation no longer holds a seat, and resending it needs one again', function () {
    $old = $this->invitations->invite($this->ws, $this->owner, 'old@example.com', WorkspaceRole::Admin);
    $this->travel(InvitationService::DAYS + 1)->days();

    $this->invitations->invite($this->ws, $this->owner, 'new@example.com', WorkspaceRole::Admin);

    expect(fn () => $this->invitations->resend($old->fresh()))->toThrow(PlanLimitReached::class, 'Your plan allows 2 billed users.');
    expect($old->fresh()->expires_at->isPast())->toBeTrue();
});

test('a sign-up refused at accept time leaves its plan_limit.reached row (password and provider)', function () {
    config(['services.google' => ['client_id' => 'g-id', 'client_secret' => 'g-secret', 'redirect' => '/auth/google/callback']]);
    $this->invitations->invite($this->ws, $this->owner, 'late@example.com', WorkspaceRole::Admin);
    $token = lastInvitationToken();
    app(Entitlements::class)->set($this->ws, Entitlement::BilledUsers, '1', 'plan');

    $this->post('/register', ['name' => 'Late', 'email' => 'late@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'invitation' => $token])
        ->assertSessionHas('planLimit');
    expect(User::where('email', 'late@example.com')->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'plan_limit.reached')->count())->toBe(1);

    Socialite::fake('google', Laravel\Socialite\Two\User::fake(['id' => 'g-9', 'name' => 'Late', 'email' => 'late@example.com', 'email_verified' => true]));
    $this->get("/invitations/{$token}")->assertOk();
    $this->get('/auth/google/callback')->assertSessionHas('planLimit');
    expect(User::where('email', 'late@example.com')->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'plan_limit.reached')->count())->toBe(2);
});
