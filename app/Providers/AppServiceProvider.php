<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CurrentWorkspace;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentWorkspace::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Model::preventLazyLoading(! $this->app->isProduction());

        // Every token belongs to the workspace it was made in (spec 7.0a); one made outside a workspace is never accepted.
        PersonalAccessToken::creating(fn (PersonalAccessToken $token) => $token->workspace_id ??= app(CurrentWorkspace::class)->id());

        // Per-token API budget; falls back to user, then IP.
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $token = $user?->currentAccessToken();
            $by = $token instanceof PersonalAccessToken ? "token:{$token->id}" : ($user ? "user:{$user->id}" : "ip:{$request->ip()}");
            $limit = (int) config('pm.api_rate_limit');

            return Limit::perMinute($limit)->by($by)->response(fn (Request $request, array $headers) => response()->json([
                'message' => "Too many requests: the limit is {$limit} per minute per token. Retry after {$headers['Retry-After']} seconds.",
            ], 429, $headers));
        });

        RateLimiter::for('invites', function (Request $request) {
            $limit = (int) config('pm.invite_rate_limit');
            $message = "Too many invitations: the limit is {$limit} per hour. Try again later.";

            return Limit::perHour($limit)->by('invites:'.$request->user()?->id)->response(fn (Request $request, array $headers) => $request->expectsJson()
                ? response()->json(['message' => $message], 429, $headers)
                : back()->withErrors(['email' => $message]));
        });

        // API docs are public so agents can discover the API without a token.
        Gate::define('viewApiDocs', fn (?User $user = null) => true);

        // Spec 7.0d: sign-in events go to every workspace the user belongs to. Never the credentials themselves.
        $audit = fn (string $action, $user, array $metadata = []) => app(AuditLogger::class)->forEveryWorkspaceOf($user instanceof User ? $user : null, $action, $metadata);
        Event::listen(Login::class, fn (Login $e) => $audit('auth.login', $e->user));
        Event::listen(Logout::class, fn (Logout $e) => $e->user ? $audit('auth.logout', $e->user) : null);
        Event::listen(PasswordReset::class, fn (PasswordReset $e) => $audit('auth.password_reset', $e->user));
        // An unknown address has no workspace: one unscoped row naming the address tried.
        Event::listen(Failed::class, fn (Failed $e) => $audit('auth.login_failed', $e->user, $e->user ? [] : ['email' => Str::limit((string) ($e->credentials['email'] ?? ''), 255, '')]));
    }
}
