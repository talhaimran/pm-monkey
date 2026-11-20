<?php

use App\Enums\WorkspaceRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AgentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'me@example.com']);
    $this->ws = makeWorkspace($this->user, 'acme');
});

describe('setting it up on the profile', function () {
    test('turning it on needs the password, then a valid code; codes are shown once', function () {
        $this->actingAs($this->user)->post('/profile/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');
        expect($this->user->fresh()->two_factor_secret)->toBeNull();

        $this->actingAs($this->user)->post('/profile/two-factor', ['password' => 'password'])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('twoFactor.enabled', false)->where('twoFactor.pending', true)
            ->where('twoFactor.qr', fn ($qr) => str_starts_with($qr, 'data:image/svg+xml;base64,'))
            ->where('twoFactor.secret', fn ($s) => strlen($s) >= 16));

        $this->actingAs($this->user)->post('/profile/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        expect($this->user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();

        $this->actingAs($this->user)->post('/profile/two-factor/confirm', ['code' => otp($this->user)])->assertSessionHasNoErrors();
        $user = $this->user->fresh();
        expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue();

        // Shown once, right after confirming; then neither the codes nor the secret are ever sent again.
        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page->where('flash.recoveryCodes', fn ($codes) => count($codes) === 10));
        $later = $this->actingAs($user)->get('/profile');
        $later->assertInertia(fn (Assert $page) => $page->where('twoFactor', ['enabled' => true, 'pending' => false, 'qr' => null, 'secret' => null])->where('flash.recoveryCodes', null));
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        expect($later->getContent())->not->toContain($secret);
        foreach ($user->recoveryCodes() as $code) {
            expect($later->getContent())->not->toContain($code);
        }

        expect(AuditLog::where('action', '2fa.enabled')->count())->toBe(1);
    });

    test('regenerating codes and turning it off need the password', function () {
        $codes = enableTwoFactor($this->user);
        $this->user = $this->user->fresh();

        $this->actingAs($this->user)->post('/profile/two-factor/recovery-codes', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($this->user)->post('/profile/two-factor/recovery-codes', ['password' => 'password'])
            ->assertSessionHas('recoveryCodes', fn ($new) => count($new) === 10 && ! array_intersect($new, $codes));
        expect(AuditLog::where('action', '2fa.recovery_codes_regenerated')->count())->toBe(1);

        $this->actingAs($this->user)->delete('/profile/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');
        expect($this->user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
        $this->actingAs($this->user)->delete('/profile/two-factor', ['password' => 'password'])->assertSessionHasNoErrors();
        expect($this->user->fresh()->two_factor_secret)->toBeNull();
        expect(AuditLog::where('action', '2fa.disabled')->count())->toBe(1);
    });
});

describe('signing in', function () {
    beforeEach(function () {
        $this->codes = enableTwoFactor($this->user);
        AuditLog::query()->toBase()->delete();
    });

    test('the password alone stops at the challenge; a valid code signs in', function () {
        $this->post('/login', ['email' => 'me@example.com', 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        expect(AuditLog::where('action', 'auth.login')->count())->toBe(0);

        $this->get('/two-factor-challenge')->assertInertia(fn (Assert $page) => $page->component('Auth/TwoFactorChallenge'));
        $this->post('/two-factor-challenge', ['code' => otp($this->user)])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
        expect(AuditLog::where('action', 'auth.login')->count())->toBe(1);
    });

    test('a wrong password still fails before any challenge', function () {
        $this->post('/login', ['email' => 'me@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->get('/two-factor-challenge')->assertRedirect('/login');
    });

    test('a wrong code is refused and the 6th attempt in a minute is throttled', function () {
        $this->post('/login', ['email' => 'me@example.com', 'password' => 'password']);

        foreach (range(1, 5) as $i) {
            $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post('/two-factor-challenge', ['code' => otp($this->user)])->assertStatus(429);
        $this->assertGuest();
    });

    test('review focus 5: a recovery code works once', function () {
        $code = $this->codes[0];
        $this->post('/login', ['email' => 'me@example.com', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $code])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
        expect(AuditLog::where('action', '2fa.recovery_code_used')->count())->toBe(1);

        $this->post('/logout');
        $this->post('/login', ['email' => 'me@example.com', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $code])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
        expect($this->user->fresh()->recoveryCodes())->not->toContain($code);
    });

    test('no secret or recovery code reaches the audit log', function () {
        $this->post('/login', ['email' => 'me@example.com', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $this->codes[1]]);
        $this->post('/profile/two-factor/recovery-codes', ['password' => 'password']);

        $all = json_encode(DB::table('audit_logs')->get());
        foreach ([Fortify::currentEncrypter()->decrypt($this->user->fresh()->two_factor_secret), ...$this->codes, ...$this->user->fresh()->recoveryCodes()] as $secret) {
            expect($all)->not->toContain($secret);
        }
    });
});

describe('a workspace that requires it', function () {
    beforeEach(function () {
        $this->member = User::factory()->create();
        $this->ws->addMember($this->member, WorkspaceRole::Member);
        $this->ws->forceFill(['settings' => ['require_2fa' => true]])->save();
    });

    test('sends members without it to set it up; profile, sign-out and other workspaces stay reachable', function () {
        $this->actingAs($this->member)->get('/acme/dashboard')->assertRedirect('/profile')
            ->assertSessionHas('status', 'Acme requires two-factor authentication. Turn it on below to continue.');
        $this->actingAs($this->member)->post('/acme/projects', ['key' => 'X', 'name' => 'X'])->assertRedirect('/profile');
        $this->actingAs($this->member)->get('/profile')->assertOk();

        makeWorkspace($this->member, 'free');
        $this->actingAs($this->member)->get('/free/dashboard')->assertOk();

        enableTwoFactor($this->member);
        $this->actingAs($this->member->fresh())->get('/acme/dashboard')->assertOk();
        $this->actingAs($this->member)->post('/logout')->assertRedirect('/');
    });

    test('creating a token needs it on; existing tokens keep working', function () {
        $this->ws->forceFill(['settings' => []])->save();
        useWorkspace($this->ws);
        $old = app(AgentService::class)->create($this->member, ['name' => 'old', 'level' => 'read'])[1];
        $this->ws->forceFill(['settings' => ['require_2fa' => true]])->save();

        expect(fn () => app(AgentService::class)->create($this->member, ['name' => 'new', 'level' => 'read']))
            ->toThrow(ValidationException::class, 'Acme requires two-factor authentication. Turn it on in your profile before creating an agent.');
        $this->withToken($old)->getJson('/api/v1/projects')->assertOk();

        enableTwoFactor($this->member);
        expect(app(AgentService::class)->create($this->member->fresh(), ['name' => 'new', 'level' => 'read'])[1])->toBeString();
    });
});

describe('the Security settings page', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create();
        $this->ws->addMember($this->admin, WorkspaceRole::Admin);
        $this->ws->addMember(User::factory()->create(), WorkspaceRole::Member);
    });

    test('shows how many members lack 2FA; Members cannot open it', function () {
        enableTwoFactor($this->admin);

        $this->actingAs($this->admin)->get('/acme/settings/security')->assertInertia(fn (Assert $page) => $page
            ->component('Workspaces/Settings/Security')
            ->where('require2fa', false)->where('membersWithout2fa', 2)->where('members', 3));
        $member = $this->ws->members()->wherePivot('role', 'member')->first();
        $this->actingAs($member)->get('/acme/settings/security')->assertForbidden();
        $this->actingAs($member)->patch('/acme/settings/security', ['require_2fa' => true])->assertForbidden();
    });

    test('turning it on needs 2FA on your own account first, and is audited', function () {
        $this->actingAs($this->admin)->patch('/acme/settings/security', ['require_2fa' => true])
            ->assertSessionHasErrors(['require_2fa' => 'Turn on two-factor authentication for your own account first, or you would be locked out.']);
        expect($this->ws->fresh()->setting('require_2fa'))->toBeFalse();

        enableTwoFactor($this->admin);
        $this->actingAs($this->admin->fresh())->patch('/acme/settings/security', ['require_2fa' => true])->assertSessionHasNoErrors();
        expect($this->ws->fresh()->setting('require_2fa'))->toBeTrue()
            ->and(AuditLog::where('action', 'workspace.settings_changed')->latest('id')->first()->metadata)
            ->toEqual(['before' => ['require_2fa' => false], 'after' => ['require_2fa' => true]]);
    });

    test('the Members page shows who has 2FA', function () {
        enableTwoFactor($this->admin);

        $this->actingAs($this->admin->fresh())->get('/acme/settings/members')->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => collect($members)->firstWhere('id', $this->admin->id)['two_factor'] === true
                && collect($members)->firstWhere('id', $this->user->id)['two_factor'] === false));
    });
});
