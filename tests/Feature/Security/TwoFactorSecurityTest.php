<?php

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

/** Step 0 PR 6 security review probes. */
beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'me@example.com']);
    makeWorkspace($this->user, 'acme');
});

test('after the password step alone, nothing in the app is reachable', function () {
    $service = app(TwoFactorService::class);
    $service->enable($this->user);
    $service->confirm($this->user->fresh(), otp($this->user));
    Cache::flush();

    $this->post('/login', ['email' => 'me@example.com', 'password' => 'password'])->assertRedirect('/two-factor-challenge');

    foreach (['/acme/dashboard', '/acme/projects', '/profile', '/dashboard'] as $path) {
        $this->get($path)->assertRedirect('/login');
    }
    $this->assertGuest();
});

test('the shared auth.user never carries the secret or recovery codes, even during setup', function () {
    app(TwoFactorService::class)->enable($this->user);

    $this->actingAs($this->user->fresh())->get('/acme/dashboard')->assertInertia(fn (Assert $page) => $page
        ->missing('auth.user.two_factor_secret')
        ->missing('auth.user.two_factor_recovery_codes'));
});

/** Step 0 PR 8 scan, F9/F13: the profile's history entries are encrypted, and signing out clears them. */
test('the profile page encrypts its browser history and signing out clears it', function () {
    app(TwoFactorService::class)->enable($this->user);

    // Other pages stay unencrypted. (Checked first: the Inertia factory is a singleton for the whole test.)
    expect($this->actingAs($this->user)->get('/acme/dashboard')->viewData('page')['encryptHistory'])->toBeFalse()
        ->and($this->get('/profile')->viewData('page')['encryptHistory'])->toBeTrue();

    $this->post('/logout');
    expect($this->get('/')->viewData('page')['clearHistory'])->toBeTrue();
});
