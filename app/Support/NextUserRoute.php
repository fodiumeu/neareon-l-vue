<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Route;

class NextUserRoute
{
    /**
     * Determine the next route for a signed-in user.
     */
    public static function name(User $user): string
    {
        if ($user->birthdate === null || $user->age_gate_passed_at === null) {
            return 'age-gate.show';
        }

        if (
            $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()
            && Route::has('verification.notice')
        ) {
            return 'verification.notice';
        }

        $profile = $user->profile()->first();

        if ($profile === null) {
            return 'onboarding.details';
        }

        if (! $profile->interestOptions()->exists()) {
            return 'onboarding.interests';
        }

        if (! $profile->languageOptions()->exists()) {
            return 'onboarding.languages';
        }

        return 'dashboard';
    }

    /**
     * Determine whether the user has completed the required onboarding steps.
     */
    public static function onboardingComplete(User $user): bool
    {
        return self::name($user) === 'dashboard';
    }

    /**
     * Redirect to the next route for a signed-in user.
     */
    public static function redirect(User $user): Redirector|RedirectResponse
    {
        return to_route(self::name($user));
    }
}
