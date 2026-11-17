<?php

use App\Enums\WorkspaceRole;
use App\Mail\ProjectInvitationMail;
use App\Models\AuditLog;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\InvitationService;
use App\Services\TwoFactorService;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    Mail::fake();
    config([
        'services.google' => ['client_id' => 'g-id', 'client_secret' => 'g-secret', 'redirect' => '/auth/google/callback'],
        'services.github' => ['client_id' => 'h-id', 'client_secret' => 'h-secret', 'redirect' => '/auth/github/callback'],
    ]);
    $this->owner = User::factory()->create(['email' => 'owner@example.com', 'name' => 'Olive']);
    $this->ws = makeWorkspace($this->owner, 'acme');
});

/** What Google returns: email_verified is Google's own flag. */
function google(string $email, bool $verified = true, string $id = 'g-1'): SocialiteUser
{
    return SocialiteUser::fake(['id' => $id, 'name' => 'Gina Google', 'email' => $email, 'email_verified' => $verified, 'token' => 'GOOGLE-SECRET-TOKEN', 'refreshToken' => 'GOOGLE-SECRET-REFRESH']);
}

/** What GitHub returns through Socialite: the email is set only when it is the primary verified one. */
function github(?string $email, string $id = 'h-1'): SocialiteUser
{
    return SocialiteUser::fake(['id' => $id, 'name' => 'Hal Hub', 'email' => $email, 'token' => 'GITHUB-SECRET-TOKEN']);
}

test('only configured providers have buttons and routes', function () {
    config(['services.github.client_id' => null]);

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('socialProviders', ['google']));
    $this->get('/auth/google/redirect')->assertRedirect();
    $this->get('/auth/github/redirect')->assertNotFound();
    $this->get('/auth/github/callback')->assertNotFound();
    $this->get('/auth/twitter/redirect')->assertNotFound();
});

test('a verified matching email links the existing user and signs them in', function () {
    Socialite::fake('google', google('Owner@Example.com'));

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->owner);
    expect(session('auth.method'))->toBe('google');
    expect(SocialAccount::sole()->only('user_id', 'provider', 'provider_user_id', 'email'))
        ->toBe(['user_id' => $this->owner->id, 'provider' => 'google', 'provider_user_id' => 'g-1', 'email' => 'owner@example.com']);
    expect(AuditLog::where('action', 'auth.social_linked')->count())->toBe(1)
        ->and(AuditLog::where('action', 'auth.login')->count())->toBe(1);
});

test('a linked account signs in by its provider id even after the email changes', function () {
    $this->owner->socialAccounts()->create(['provider' => 'github', 'provider_user_id' => 'h-1', 'email' => 'owner@example.com']);
    Socialite::fake('github', github('new-address@example.com'));

    $this->get('/auth/github/callback')->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->owner);
    expect(SocialAccount::count())->toBe(1);
});

test('an unverified provider email is refused with the reason', function (string $provider, SocialiteUser $profile, string $message) {
    Socialite::fake($provider, $profile);

    $this->get("/auth/{$provider}/callback")->assertRedirect('/login')->assertSessionHasErrors(['email' => $message]);
    $this->assertGuest();
    expect(SocialAccount::count())->toBe(0);
})->with([
    'google, unverified' => fn () => ['google', google('owner@example.com', verified: false), 'Your Google email address isn’t verified, so it can’t be used to sign in. Verify it with Google, or sign in with your password.'],
    'github, no verified primary email' => fn () => ['github', github(null), 'Your GitHub account has no verified primary email address, so it can’t be used to sign in. Verify one on GitHub, or sign in with your password.'],
]);

test('on Cloud an unknown verified email creates the user and their workspace', function () {
    config(['pm.edition' => 'cloud']);
    Socialite::fake('google', google('gina@example.com'));

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

    $user = User::firstWhere('email', 'gina@example.com');
    $this->assertAuthenticatedAs($user);
    expect($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->workspaces()->sole()->name)->toBe("Gina's workspace")
        ->and($user->workspaceRole($user->workspaces()->sole()))->toBe(WorkspaceRole::Owner);
});

test('on self-hosted an unknown email needs an invitation', function () {
    Socialite::fake('google', google('gina@example.com'));

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors(['email' => 'Ask an admin of Acme to invite you.']);
    expect(User::where('email', 'gina@example.com')->exists())->toBeFalse();

    $invitation = app(InvitationService::class)->invite($this->ws, $this->owner, 'gina@example.com', WorkspaceRole::Member);
    $token = null;
    Mail::assertSent(ProjectInvitationMail::class, function ($mail) use (&$token) {
        $token = $mail->token;

        return true;
    });
    $this->get("/invitations/{$token}")->assertOk();
    $this->get('/auth/google/callback')->assertRedirect('/acme/dashboard');

    $gina = User::firstWhere('email', 'gina@example.com');
    expect($gina->workspaceRole($this->ws))->toBe(WorkspaceRole::Member)
        ->and(WorkspaceInvitation::find($invitation->id))->toBeNull();
});

test('on self-hosted the very first person can sign up with a provider', function () {
    app(WorkspaceService::class)->purge($this->ws);
    Socialite::fake('github', github('first@example.com'));

    $this->get('/auth/github/callback')->assertRedirect('/dashboard');
    expect(User::firstWhere('email', 'first@example.com')->workspaces()->count())->toBe(1);
});

test('review focus 5: a user with two-factor still meets the challenge after a provider sign-in', function () {
    app(TwoFactorService::class)->enable($this->owner);
    app(TwoFactorService::class)->confirm($this->owner->fresh(), otp($this->owner));
    Cache::flush();
    Socialite::fake('google', google('owner@example.com'));

    $this->get('/auth/google/callback')->assertRedirect('/two-factor-challenge');
    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => otp($this->owner)])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->owner);
    expect(session('auth.method'))->toBe('google');
});

test('signed in, linking from the profile needs the same verified email', function () {
    Socialite::fake('github', github('someone-else@example.com'));
    $this->actingAs($this->owner)->get('/auth/github/callback')->assertRedirect('/profile')
        ->assertSessionHasErrors(['social' => 'That GitHub account’s verified email is someone-else@example.com, not owner@example.com. Sign in to GitHub with owner@example.com to link it.']);

    Socialite::fake('github', github('owner@example.com'));
    $this->actingAs($this->owner)->get('/auth/github/callback')->assertRedirect('/profile');
    expect($this->owner->socialAccounts()->pluck('provider')->all())->toBe(['github']);

    // A provider account already linked to someone else is refused.
    $other = User::factory()->create(['email' => 'other@example.com']);
    Socialite::fake('github', github('other@example.com'));
    $this->actingAs($other)->get('/auth/github/callback')->assertSessionHasErrors('social');
    expect($other->socialAccounts()->count())->toBe(0);
});

test('unlinking never removes the last sign-in method', function () {
    $social = User::factory()->create(['email' => 'gina@example.com', 'password' => null]);
    $this->ws->addMember($social, WorkspaceRole::Member);
    $social->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'g-1', 'email' => 'gina@example.com']);

    $this->actingAs($social)->delete('/profile/social/google')
        ->assertSessionHasErrors(['social' => 'Google is your only way to sign in. Set a password or link GitHub before unlinking it.']);
    expect($social->socialAccounts()->count())->toBe(1);

    $social->socialAccounts()->create(['provider' => 'github', 'provider_user_id' => 'h-1', 'email' => 'gina@example.com']);
    $this->actingAs($social)->delete('/profile/social/google')->assertSessionHasNoErrors();
    expect($social->socialAccounts()->pluck('provider')->all())->toBe(['github'])
        ->and(AuditLog::where('action', 'auth.social_unlinked')->count())->toBe(1);

    // With a password, the last provider can go.
    $this->owner->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'g-9', 'email' => 'owner@example.com']);
    $this->actingAs($this->owner)->delete('/profile/social/google')->assertSessionHasNoErrors();
});

test('an account without a password cannot sign in with one, but can set one by email', function () {
    $social = User::factory()->create(['email' => 'gina@example.com', 'password' => null]);

    $this->post('/login', ['email' => 'gina@example.com', 'password' => ''])->assertSessionHasErrors();
    $this->post('/login', ['email' => 'gina@example.com', 'password' => 'anything'])->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->get('/profile');
    expect($social->fresh()->password)->toBeNull();
});

test('a workspace that allows only passwords refuses a Google session, naming what it allows', function () {
    $this->ws->forceFill(['settings' => ['sign_in_methods' => ['password']]])->save();
    Socialite::fake('google', google('owner@example.com'));
    $this->get('/auth/google/callback');

    $this->get('/acme/dashboard')->assertForbidden()->assertInertia(fn (Assert $page) => $page
        ->component('Workspaces/NoAccess')
        ->where('message', 'Acme allows sign-in with a password only. Sign out, then sign in that way.'));

    // A password session gets in.
    app('auth')->forgetGuards();
    $this->post('/logout');
    $this->post('/login', ['email' => 'owner@example.com', 'password' => 'password']);
    $this->get('/acme/dashboard')->assertOk();
});

test('the Security page edits sign-in methods: at least one, and never the one you are using', function () {
    $this->actingAs($this->owner)->patch('/acme/settings/sign-in-methods', ['sign_in_methods' => []])->assertSessionHasErrors('sign_in_methods');
    $this->actingAs($this->owner)->patch('/acme/settings/sign-in-methods', ['sign_in_methods' => ['google']])
        ->assertSessionHasErrors(['sign_in_methods' => 'You signed in with a password. Keep it on, or sign in another way first.']);
    $this->actingAs($this->owner)->patch('/acme/settings/sign-in-methods', ['sign_in_methods' => ['password', 'github']])->assertSessionHasNoErrors();

    expect($this->ws->fresh()->setting('sign_in_methods'))->toBe(['password', 'github']);
    $this->actingAs($this->owner)->get('/acme/settings/security')->assertInertia(fn (Assert $page) => $page
        ->where('signInMethods', ['password', 'github'])->where('configured', ['google', 'github']));
});

test('no provider token is stored or logged', function () {
    config(['pm.edition' => 'cloud']);
    Socialite::fake('google', google('gina@example.com'));
    $this->get('/auth/google/callback');
    Socialite::fake('google', google('owner@example.com', id: 'g-2'));
    app('auth')->forgetGuards();
    $this->post('/logout');
    $this->get('/auth/google/callback');

    $everything = json_encode([DB::table('social_accounts')->get(), DB::table('audit_logs')->get(), DB::table('users')->get()]);
    expect($everything)->not->toContain('GOOGLE-SECRET-TOKEN')->not->toContain('GOOGLE-SECRET-REFRESH');
});
