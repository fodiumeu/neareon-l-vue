<?php

namespace App\Http\Controllers;

use App\Enums\ProfileVisibility;
use App\Http\Requests\StoreOnboardingDetailsRequest;
use App\Http\Requests\StoreOnboardingInterestsRequest;
use App\Http\Requests\StoreOnboardingLanguagesRequest;
use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Services\ProfileOptionSyncService;
use App\Support\NextUserRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly ProfileOptionSyncService $profileOptions,
    ) {}

    /**
     * Redirect to the next required onboarding step.
     */
    public function index(Request $request): RedirectResponse
    {
        return NextUserRoute::redirect($request->user());
    }

    /**
     * Show the profile details step.
     */
    public function details(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->profile()->exists()) {
            return NextUserRoute::redirect($user);
        }

        return Inertia::render('Onboarding/Details');
    }

    /**
     * Store the initial profile details.
     */
    public function storeDetails(StoreOnboardingDetailsRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile()->exists()) {
            return NextUserRoute::redirect($user);
        }

        $user->profile()->create([
            ...$request->validated(),
            'profile_visibility' => ProfileVisibility::Public,
            'interests_visibility' => ProfileVisibility::Public,
            'languages_visibility' => ProfileVisibility::Public,
            'region_visibility' => ProfileVisibility::Public,
            'social_counts_visibility' => ProfileVisibility::Public,
        ]);

        return to_route('onboarding.interests');
    }

    /**
     * Show the interests step.
     */
    public function interests(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $profile = $user->profile()->first();

        $hasInterests = $profile !== null && $profile->interests !== null && $profile->interests !== [];

        if ($profile === null || $hasInterests) {
            return NextUserRoute::redirect($user);
        }

        return Inertia::render('Onboarding/Interests', [
            'interests' => InterestOption::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->pluck('label'),
        ]);
    }

    /**
     * Store selected interests.
     */
    public function storeInterests(StoreOnboardingInterestsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->profile()->first();

        $hasInterests = $profile !== null && $profile->interests !== null && $profile->interests !== [];

        if ($profile === null || $hasInterests) {
            return NextUserRoute::redirect($user);
        }

        $this->profileOptions->update($profile, [
            'interests' => $request->validated('interests'),
        ]);

        return to_route('onboarding.languages');
    }

    /**
     * Show the languages step.
     */
    public function languages(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $profile = $user->profile()->first();

        $hasInterests = $profile !== null && $profile->interests !== null && $profile->interests !== [];
        $hasLanguages = $profile !== null && $profile->languages !== null && $profile->languages !== [];

        if (! $hasInterests || $hasLanguages) {
            return NextUserRoute::redirect($user);
        }

        return Inertia::render('Onboarding/Languages', [
            'languages' => LanguageOption::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->pluck('label'),
        ]);
    }

    /**
     * Store selected languages.
     */
    public function storeLanguages(StoreOnboardingLanguagesRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->profile()->first();

        $hasInterests = $profile !== null && $profile->interests !== null && $profile->interests !== [];
        $hasLanguages = $profile !== null && $profile->languages !== null && $profile->languages !== [];

        if (! $hasInterests || $hasLanguages) {
            return NextUserRoute::redirect($user);
        }

        $this->profileOptions->update($profile, [
            'languages' => $request->validated('languages'),
        ]);

        return to_route('dashboard');
    }
}
