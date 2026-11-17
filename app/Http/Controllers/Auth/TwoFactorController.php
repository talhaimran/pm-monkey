<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Profile → two-factor (spec 7.0e). Turning it on, regenerating codes and turning it off need the password. */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $this->twoFactor->enable($request->user());

        return back();
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        return back()->with('recoveryCodes', $this->twoFactor->confirm($request->user(), $request->string('code')->trim()->toString()));
    }

    public function recoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        return back()->with('recoveryCodes', $this->twoFactor->regenerateRecoveryCodes($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $this->twoFactor->disable($request->user());

        return back();
    }
}
