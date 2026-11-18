<?php

use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/** Step 0 PR 7 security review probes: ways a provider account could take over a user. */
beforeEach(function () {
    config(['services.google' => ['client_id' => 'g', 'client_secret' => 's', 'redirect' => '/auth/google/callback']]);
    $this->owner = User::factory()->create(['email' => 'owner@example.com']);
    makeWorkspace($this->owner, 'acme');
});

test('an email_verified flag that is anything but a real "true" never links an account', function (mixed $flag) {
    Socialite::fake('google', SocialiteUser::fake(['id' => 'g-1', 'email' => 'owner@example.com', 'email_verified' => $flag]));

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(SocialAccount::count())->toBe(0);
})->with(['string false' => 'false', 'string 0' => '0', 'missing' => null, 'empty' => '']);

test('a provider sign-in never remembers the session, so a remembered session is always a password one', function () {
    Socialite::fake('google', SocialiteUser::fake(['id' => 'g-1', 'email' => 'owner@example.com', 'email_verified' => true]));

    $response = $this->get('/auth/google/callback');
    $this->assertAuthenticatedAs($this->owner);
    expect(collect($response->headers->getCookies())->contains(fn ($c) => str_starts_with($c->getName(), 'remember_web_')))->toBeFalse();
});

/** Step 0 PR 8 scan, F1/F2/F4: whoever registered an address without proving it never gets a provider linked in. */
test('a provider sign-in never links to an account whose email was never verified', function () {
    $squatter = User::factory()->unverified()->create(['email' => 'victim@example.com']);
    Socialite::fake('google', SocialiteUser::fake(['id' => 'g-1', 'email' => 'victim@example.com', 'email_verified' => true]));

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors(['email' => 'An account with victim@example.com exists, but its email address was never verified, so Google can’t sign in to it. Sign in with its password and verify the address, then link Google from your profile.']);
    $this->assertGuest();
    expect($squatter->socialAccounts()->count())->toBe(0);
});

/** F3: linking needs a verified email, and a session made with a method every one of your workspaces allows. */
test('linking from the profile needs a verified email', function () {
    $user = User::factory()->unverified()->create(['email' => 'me@example.com']);
    Socialite::fake('google', SocialiteUser::fake(['id' => 'g-1', 'email' => 'me@example.com', 'email_verified' => true]));

    $this->actingAs($user)->get('/auth/google/callback')->assertSessionHasErrors('social');
    expect($user->socialAccounts()->count())->toBe(0);
});

test('a password session cannot add a provider or change the email while a workspace refuses passwords', function () {
    $this->owner->workspaces()->sole()->forceFill(['settings' => ['sign_in_methods' => ['google']]])->save();
    Socialite::fake('google', SocialiteUser::fake(['id' => 'g-9', 'email' => 'owner@example.com', 'email_verified' => true]));
    $this->actingAs($this->owner)->withSession(['auth.method' => 'password']);

    $this->get('/auth/google/callback')->assertSessionHasErrors(['social' => 'Acme allows sign-in with Google only. Sign in that way to link Google.']);
    $this->patch('/profile', ['name' => 'X', 'email' => 'attacker@example.com'])->assertSessionHasErrors(['email' => 'Acme allows sign-in with Google only. Sign in that way to change your email.']);
    expect($this->owner->fresh()->email)->toBe('owner@example.com')
        ->and(SocialAccount::count())->toBe(0);

    // A name change alone is fine.
    $this->patch('/profile', ['name' => 'Olive', 'email' => 'owner@example.com'])->assertSessionHasNoErrors();
});
