<?php

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Mail\ProjectInvitationMail;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->owner = User::factory()->create(['name' => 'Olive']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->project = makeProject($this->owner, 'WEB');
});

/** Invites $email to WEB as a project Member and returns the plain token from the email. */
function inviteAndGetToken(string $email = 'carol@example.com'): string
{
    $test = test();
    app(InvitationService::class)->invite($test->ws, $test->owner, $email, WorkspaceRole::Member, $test->project, ProjectRole::Member);
    $token = null;
    Mail::assertSent(ProjectInvitationMail::class, function (ProjectInvitationMail $mail) use ($email, &$token) {
        $token = $mail->token;

        return $mail->hasTo($email);
    });

    return $token;
}

test('an invitation stores only a hash of its token and expires in 14 days', function () {
    $this->freezeSecond();
    $token = inviteAndGetToken();
    $invitation = WorkspaceInvitation::sole();

    expect($invitation->token)->toBe(hash('sha256', $token))->not->toBe($token)
        ->and($invitation->expires_at->equalTo(now()->addDays(14)))->toBeTrue();
    Mail::assertSent(ProjectInvitationMail::class, fn ($mail) => str_contains($mail->render(), route('invitations.show', $token)));
});

test('a guest opening the link is asked to register or log in', function () {
    $token = inviteAndGetToken();

    $this->get(route('invitations.show', $token))->assertInertia(fn (Assert $page) => $page->component('Invitations/Show')
        ->where('invitation.workspace', 'Acme')->where('invitation.project', 'WEB')->where('invitation.email', 'carol@example.com')
        ->where('invitation.inviter', 'Olive')->where('error', null)->where('signedIn', false));
});

test('registering from the link joins the workspace and project, verified, without creating a workspace', function () {
    config(['pm.edition' => 'cloud']);
    $token = inviteAndGetToken();

    $this->get('/register?invitation='.$token)->assertInertia(fn (Assert $page) => $page->where('invitation.email', 'carol@example.com')->where('createsWorkspace', false));
    $this->post('/register', ['name' => 'Carol', 'email' => 'carol@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'invitation' => $token])
        ->assertRedirect('/acme/projects/WEB');

    $carol = User::firstWhere('email', 'carol@example.com');
    expect($carol->workspaceRole($this->ws))->toBe(WorkspaceRole::Member)
        ->and($this->project->members()->where('users.id', $carol->id)->value('role'))->toBe('member')
        ->and($carol->hasVerifiedEmail())->toBeTrue()
        ->and($carol->workspaces()->count())->toBe(1)
        ->and(WorkspaceInvitation::count())->toBe(0);
});

test('on self-hosted an invitation opens registration after the first user', function () {
    config(['pm.edition' => 'self_hosted']);
    $token = inviteAndGetToken();

    $this->post('/register', ['name' => 'Carol', 'email' => 'carol@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'invitation' => $token])
        ->assertRedirect('/acme/projects/WEB');
});

test('registering with an invitation for another address is refused', function () {
    $token = inviteAndGetToken();

    $this->post('/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'invitation' => $token])
        ->assertSessionHasErrors(['email' => 'This invitation was sent to a different email address.']);
    expect(User::where('email', 'eve@example.com')->exists())->toBeFalse();
});

test('accepting while signed in joins and switches', function () {
    $carol = User::factory()->create(['email' => 'carol@example.com']);
    $other = makeWorkspace($carol, 'carols');
    $token = inviteAndGetToken();

    $this->actingAs($carol)->get(route('invitations.show', $token))->assertInertia(fn (Assert $page) => $page->where('signedIn', true)->where('error', null));
    $this->actingAs($carol)->post(route('invitations.join', $token))->assertRedirect('/acme/projects/WEB');

    expect($carol->fresh()->current_workspace_id)->toBe($this->ws->id)
        ->and($carol->workspaceRole($other))->toBe(WorkspaceRole::Owner)
        ->and($carol->workspaceRole($this->ws))->toBe(WorkspaceRole::Member);
});

test('a late or wrong acceptance is refused with the reason and creates no membership', function (Closure $setup, string $message) {
    $carol = User::factory()->create(['email' => 'carol@example.com']);
    $token = inviteAndGetToken();
    $actor = $setup($carol);

    $this->actingAs($actor)->get(route('invitations.show', $token))->assertInertia(fn (Assert $page) => $page->where('error', $message));
    $this->actingAs($actor)->post(route('invitations.join', $token))->assertSessionHasErrors(['invitation' => $message]);

    expect($this->ws->members()->count())->toBe(1)
        ->and($this->project->members()->count())->toBe(1);
})->with([
    'expired' => [function (User $carol) {
        WorkspaceInvitation::query()->update(['expires_at' => now()->subSecond()]);

        return $carol;
    }, 'This invitation has expired. Ask an admin to invite you again.'],
    'revoked' => [function (User $carol) {
        app(InvitationService::class)->revoke($sole = WorkspaceInvitation::sole(), $sole->inviter);

        return $carol;
    }, 'This invitation is no longer valid. Ask an admin to invite you again.'],
    'other email' => [fn () => User::factory()->create(['email' => 'eve@example.com']), 'This invitation was sent to a different email address.'],
]);

test('an unknown token looks like a revoked one', function () {
    $this->get(route('invitations.show', str_repeat('a', 40)))
        ->assertInertia(fn (Assert $page) => $page->where('error', 'This invitation is no longer valid. Ask an admin to invite you again.')->where('invitation', null));
});

test('resending issues a new token and a fresh 14 days', function () {
    $old = inviteAndGetToken();
    WorkspaceInvitation::query()->update(['expires_at' => now()->addDay()]);
    Mail::fake();

    app(InvitationService::class)->resend(WorkspaceInvitation::sole());

    $new = null;
    Mail::assertSent(ProjectInvitationMail::class, function ($mail) use (&$new) {
        $new = $mail->token;

        return true;
    });
    expect($new)->not->toBe($old)
        ->and(WorkspaceInvitation::sole()->token)->toBe(hash('sha256', $new))
        ->and(WorkspaceInvitation::sole()->expires_at->isAfter(now()->addDays(13)))->toBeTrue();
});

test('Owners and Admins invite with a workspace role and no project', function () {
    app(InvitationService::class)->invite($this->ws, $this->owner, 'dan@example.com', WorkspaceRole::Admin);
    $invitation = WorkspaceInvitation::sole();

    expect($invitation->role)->toBe(WorkspaceRole::Admin)->and($invitation->project_id)->toBeNull();
});

test('a workspace invitation cannot make an Owner', function () {
    expect(fn () => app(InvitationService::class)->invite($this->ws, $this->owner, 'dan@example.com', WorkspaceRole::Owner))
        ->toThrow(ValidationException::class);
});

test('project admins still invite to their project through the web, and the rate limit holds', function () {
    config(['pm.invite_rate_limit' => 2]);

    foreach (['a', 'b'] as $n) {
        $this->actingAs($this->owner)->post('/acme/projects/WEB/invitations', ['email' => "{$n}@example.com", 'role' => 'viewer'])->assertRedirect();
    }
    $this->actingAs($this->owner)->post('/acme/projects/WEB/invitations', ['email' => 'c@example.com', 'role' => 'viewer'])->assertSessionHasErrors();

    expect(WorkspaceInvitation::where('project_id', $this->project->id)->where('role', 'member')->where('project_role', 'viewer')->count())->toBe(2);
});
