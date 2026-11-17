<?php

use Laravel\Fortify\Features;

/*
 * E12: Fortify provides two-factor authentication only. Breeze keeps sign-in, registration and password reset,
 * Fortify's own routes are off (FortifyServiceProvider::register), and passkeys are not enabled.
 */
return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/dashboard',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],
    // Spec 7.0e: 5 code attempts per minute per user (defined in FortifyServiceProvider).
    'limiters' => ['two-factor' => 'two-factor'],
    'views' => false,
    'features' => [
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
    ],
];
