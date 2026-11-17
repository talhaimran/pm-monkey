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
