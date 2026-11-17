<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Fortify;

/** E12: Fortify for two-factor only. Its routes are off; routes/auth.php and routes/web.php declare the ones used. */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactorChallenge'));

        // Spec 7.0e: 5 code attempts per minute per user being challenged.
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by('2fa:'.$request->session()->get('login.id')));

        // Fortify replaces a used recovery code inside its challenge controller.
        Event::listen(RecoveryCodeReplaced::class, fn (RecoveryCodeReplaced $e) => $e->user instanceof User
            ? app(AuditLogger::class)->forEveryWorkspaceOf($e->user, '2fa.recovery_code_used')
            : null);
    }
}
