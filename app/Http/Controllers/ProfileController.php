<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\MembershipService;
use App\Services\TwoFactorService;
use App\Services\WorkspaceMemberService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        // The secret and QR code are shown only while setup waits for a code; never once it is confirmed.
        $pending = $user->two_factor_secret && ! $user->hasEnabledTwoFactorAuthentication();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'twoFactor' => [
                'enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'pending' => (bool) $pending,
                'qr' => $pending ? 'data:image/svg+xml;base64,'.base64_encode(app(TwoFactorService::class)->qrCodeSvg($user)) : null,
                'secret' => $pending ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret) : null,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request, MembershipService $memberships, WorkspaceMemberService $workspaceMembers): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // E7: a workspace always keeps an Owner.
        if ($names = $workspaceMembers->soleOwnerOf($user)) {
            throw ValidationException::withMessages([
                'password' => 'You are the last Owner of '.implode(', ', $names).'. Make someone else an Owner or delete those workspaces first.',
            ]);
        }

        if ($keys = $memberships->soleAdminProjectKeys($user)) {
            throw ValidationException::withMessages([
                'password' => 'You are the only admin of '.implode(', ', $keys).'. Make another member an admin or delete those projects before deleting your account.',
            ]);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
