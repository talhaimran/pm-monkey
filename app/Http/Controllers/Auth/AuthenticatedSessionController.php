<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\SocialSignInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'socialProviders' => app(SocialSignInService::class)->configured(),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Two-factor users finish at Fortify's challenge, which reads login.id and signs them in.
        $request->session()->put('auth.method', 'password');
        if ($user = $request->authenticate()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => $request->boolean('remember')]);

            return redirect()->route('two-factor.login');
        }

        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
        // Drops the encrypted history (the profile's 2FA codes) so Back can't show it to the next person.
        Inertia::clearHistory();

        return redirect('/');
    }
}
