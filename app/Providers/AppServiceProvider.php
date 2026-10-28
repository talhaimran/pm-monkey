<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Model::preventLazyLoading(! $this->app->isProduction());

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

        // API docs are public so agents can discover the API without a token.
        Gate::define('viewApiDocs', fn (?User $user = null) => true);
    }
}
