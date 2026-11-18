<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SocialSignInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/** Spec 7.0f: Google and GitHub sign-in, and linking from the profile when signed in. */
class SocialSignInController extends Controller
{
    public function __construct(private SocialSignInService $social) {}

    public function redirect(string $provider): SymfonyRedirect
    {
        $this->ensureConfigured($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureConfigured($provider);
        $name = SocialSignInService::PROVIDERS[$provider];
        $back = Auth::check() ? redirect()->route('profile.edit') : redirect()->route('login');

        try {
            $profile = Socialite::driver($provider)->user();
        } catch (Throwable) {
            // Cancelled on the provider's page, an expired state, or the provider was unreachable.
            return $back->withErrors([Auth::check() ? 'social' : 'email' => "Signing in with {$name} didn’t finish. Try again."]);
        }

        try {
            if ($user = $request->user()) {
                $this->social->link($user, $provider, $profile, $request->session()->get('auth.method', 'password'));

                return $back->with('status', "{$name} is linked. You can now sign in with it.");
            }
            [$user, $landing] = $this->social->signIn($provider, $profile, $request->session()->pull('invitation.token'));
        } catch (ValidationException $e) {
            return $back->withErrors($e->errors());
        }

        // The method this session was made with; workspaces may allow only some (ResolveWorkspace).
        $request->session()->put('auth.method', $provider);
        if ($user->hasEnabledTwoFactorAuthentication()) {
            // No "remember me" for provider sign-ins, so a remembered session is always a password one.
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $landing ? redirect($landing) : redirect()->intended('/dashboard');
    }

    public function destroy(Request $request, string $provider): RedirectResponse
    {
        abort_unless(isset(SocialSignInService::PROVIDERS[$provider]), 404);
        $this->social->unlink($request->user(), $provider);

        return back()->with('status', SocialSignInService::PROVIDERS[$provider].' is unlinked.');
    }

    private function ensureConfigured(string $provider): void
    {
        abort_unless(in_array($provider, $this->social->configured(), true), 404);
    }
}
