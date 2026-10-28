<?php

use App\Enums\ProjectRole;
use App\Mail\ProjectInvitationMail;
use App\Mcp\Servers\LocalPmServer;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;

test('unverified accounts cannot accept invitations, verified ones can', function () {
    $owner = User::factory()->create();
    $project = makeProject($owner);
    $invitation = ProjectInvitation::create(['project_id' => $project->id, 'email' => 'cfo@victim.test', 'role' => 'admin', 'invited_by' => $owner->id]);

    $this->post('/register', ['name' => 'Attacker', 'email' => 'cfo@victim.test', 'password' => 'password123', 'password_confirmation' => 'password123']);
    $user = User::where('email', 'cfo@victim.test')->sole();

    $this->post("/invitations/{$invitation->id}/accept")->assertForbidden();
    expect($project->roleFor($user))->toBeNull();

    $user->markEmailAsVerified();
    $this->actingAs($user->fresh())->post("/invitations/{$invitation->id}/accept")->assertRedirect();
    expect($project->roleFor($user))->toBe(ProjectRole::Admin);
});

test('the sole admin of a project cannot delete their account', function () {
    $owner = User::factory()->create();
    $project = makeProject($owner, 'WEB');
    makeProject($owner, 'API');
    addMember($project, User::factory()->create(), ProjectRole::Member);

    $this->actingAs($owner)->delete('/profile', ['password' => 'password'])
        ->assertSessionHasErrors(['password' => 'You are the only admin of API, WEB. Make another member an admin or delete those projects before deleting your account.']);
    expect($owner->fresh())->not->toBeNull();

    // With another admin everywhere, deletion works.
    foreach ([$project, Project::where('key', 'API')->sole()] as $p) {
        addMember($p, User::factory()->create(), ProjectRole::Admin);
    }
    $this->actingAs($owner)->delete('/profile', ['password' => 'password'])->assertRedirect('/');
    expect($owner->fresh())->toBeNull();
});

test('invitations are rate limited per user', function () {
    Mail::fake();
    config(['pm.invite_rate_limit' => 2]);
    $admin = User::factory()->create();
    makeProject($admin);

    apiAs($admin);
    $this->postJson('/api/v1/projects/WEB/invitations', ['email' => 'a@x.test', 'role' => 'member'])->assertCreated();
    $this->postJson('/api/v1/projects/WEB/invitations', ['email' => 'b@x.test', 'role' => 'member'])->assertCreated();
    $this->postJson('/api/v1/projects/WEB/invitations', ['email' => 'c@x.test', 'role' => 'member'])
        ->assertStatus(429)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'invitations'));

    $this->actingAs($admin)->post('/projects/WEB/invitations', ['email' => 'd@x.test', 'role' => 'member'])
        ->assertRedirect()
        ->assertSessionHasErrors('email');
    Mail::assertSent(ProjectInvitationMail::class, 2);
});

test('tokens can expire and expired tokens are rejected', function () {
    $me = User::factory()->create();
    makeProject($me);

    $this->actingAs($me)->post('/settings/tokens', ['name' => 'ci', 'access' => 'read', 'expires_in_days' => 30])->assertRedirect();
    $token = PersonalAccessToken::sole();
    expect($token->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();

    $this->actingAs($me)->post('/settings/tokens', ['name' => 'bad', 'access' => 'read', 'expires_in_days' => 5000])
        ->assertSessionHasErrors('expires_in_days');

    $expired = $me->createToken('old', ['read'], now()->subMinute())->plainTextToken;
    app('auth')->forgetGuards(); // drop the web-session user set by actingAs; real API calls have no session
    $this->withToken($expired)->getJson('/api/v1/projects')->assertUnauthorized();

    $this->actingAs($me)->get('/settings/tokens')->assertInertia(fn ($page) => $page
        ->where('tokens', fn ($tokens) => collect($tokens)->firstWhere('name', 'ci')['expires_at'] !== null));
});

test('the stdio server rejects expired tokens', function () {
    $plain = User::factory()->create()->createToken('old', ['read'], now()->subMinute())->plainTextToken;

    expect(fn () => LocalPmServer::authenticate($plain))->toThrow(RuntimeException::class, 'PM_MCP_TOKEN');
});
