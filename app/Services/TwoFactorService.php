<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

/**
 * Spec 7.0e on top of Fortify's actions: set up with a code, 10 one-time recovery codes (Fortify makes 8), and the
 * audit events. Recovery codes are returned once, for the page to show; they are stored encrypted.
 */
class TwoFactorService
{
    public const RECOVERY_CODES = 10;

    public function __construct(private AuditLogger $audit) {}

    /** Starts setup: a secret to scan, not active until confirm(). A pending setup keeps its secret. */
    public function enable(User $user): void
    {
        if ($user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages(['password' => 'Two-factor authentication is already on.']);
        }
        app(EnableTwoFactorAuthentication::class)($user);
    }

    /** @return list<string> the new recovery codes */
    public function confirm(User $user, string $code): array
    {
        try {
            app(ConfirmTwoFactorAuthentication::class)($user, $code);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['code' => 'That code didn’t match. Check the time on your phone and try the newest code.']);
        }
        $codes = $this->newRecoveryCodes($user);
        $this->audit->forEveryWorkspaceOf($user, '2fa.enabled');

        return $codes;
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages(['password' => 'Two-factor authentication is off.']);
        }
        $codes = $this->newRecoveryCodes($user);
        $this->audit->forEveryWorkspaceOf($user, '2fa.recovery_codes_regenerated');

        return $codes;
    }

    /** Turns it off, or cancels a setup that was never confirmed. */
    public function disable(User $user): void
    {
        $wasOn = $user->hasEnabledTwoFactorAuthentication();
        app(DisableTwoFactorAuthentication::class)($user);
        if ($wasOn) {
            $this->audit->forEveryWorkspaceOf($user, '2fa.disabled');
        }
    }

    /** The setup QR code with a white quiet zone of 4 modules inside the image, so it scans on the dark theme too. */
    public function qrCodeSvg(User $user): string
    {
        $fill = Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(45, 55, 72));

        return (new Writer(new ImageRenderer(new RendererStyle(192, 4, null, null, $fill), new SvgImageBackEnd)))
            ->writeString($user->twoFactorQrCodeUrl());
    }

    /** @return list<string> */
    private function newRecoveryCodes(User $user): array
    {
        $codes = Collection::times(self::RECOVERY_CODES, fn () => RecoveryCode::generate())->all();
        $user->forceFill(['two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($codes))])->save();

        return $codes;
    }
}
