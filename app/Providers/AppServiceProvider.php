<?php

namespace App\Providers;

use App\Enums\Entitlement;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AuditLogger;
use App\Services\CurrentWorkspace;
use App\Services\Entitlements;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
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
        // Names people choose (workspace, inviter, project) are echoed into Markdown mail: keep them text, not links.
        Markdown::withSecuredEncoding();
        Model::preventLazyLoading(! $this->app->isProduction());

        // Every token belongs to the workspace it was made in (spec 7.0a); one made outside a workspace is never accepted.
        PersonalAccessToken::creating(fn (PersonalAccessToken $token) => $token->workspace_id ??= app(CurrentWorkspace::class)->id());

        // Per-token API budget (spec 7.0c): api_per_minute and api_per_day of the token's workspace, else
        // PM_API_RATE_LIMIT per minute and no daily limit. Runs before ResolveTokenWorkspace, so it reads the token.
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $token = $user?->currentAccessToken();
            $token = $token instanceof PersonalAccessToken ? $token : null;
            $by = $token ? "token:{$token->id}" : ($user ? "user:{$user->id}" : "ip:{$request->ip()}");
            $workspace = $token?->workspace_id ? Workspace::find($token->workspace_id) : null;
            $entitlements = app(Entitlements::class);
            $perMinute = ($workspace ? $entitlements->get($workspace, Entitlement::ApiPerMinute) : null) ?? (int) config('pm.api_rate_limit');
            $perDay = $workspace ? $entitlements->get($workspace, Entitlement::ApiPerDay) : null;
            $tooMany = fn (int $limit, string $per) => fn (Request $request, array $headers) => response()->json([
                'message' => "Too many requests: the limit is {$limit} per {$per} per token. Retry after {$headers['Retry-After']} seconds.",
            ], 429, $headers);

            return array_filter([
                Limit::perMinute($perMinute)->by($by)->response($tooMany($perMinute, 'minute')),
                // E11: one counter per token per calendar day.
                $perDay === null ? null : Limit::perDay($perDay)->by("pm:api-day:{$by}:".now()->toDateString())->response($tooMany($perDay, 'day')),
            ]);
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
