<?php

namespace App\Http\Controllers;

use App\Enums\ProfileVisibility;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Profile;
use App\Services\ProfileOptionSyncService;
use App\Services\ProfileVisibilityService;
use App\Support\NextUserRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileVisibilityService $profileVisibility,
        private readonly ProfileOptionSyncService $profileOptions,
    ) {}

    /**
     * Show the authenticated user's own NEAREON profile.
     */
    public function me(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if ($profile === null) {
            return NextUserRoute::redirect($user);
        }

        $profile->loadMissing(['user', 'languageOptions', 'interestOptions']);

        return Inertia::render('Profile/Show', [
            'profile' => $this->profileVisibility->visibleProfileData($profile, $user),
            'editProfileHref' => '/profile/edit',
        ]);
    }

    /**
     * Show the edit form for the authenticated user's NEAREON profile.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $profile = $request->user()->profile;

        if ($profile === null) {
            return NextUserRoute::redirect($request->user());
        }

        $profile->load(['languageOptions', 'interestOptions']);

        $profileVisibilityOptions = [
            ['value' => ProfileVisibility::Public->value, 'label' => 'Alle'],
            ['value' => ProfileVisibility::Mutuals->value, 'label' => 'Gegenseitige Kontakte'],
            ['value' => ProfileVisibility::Private->value, 'label' => 'Nur ich'],
        ];
        $fieldVisibilityOptions = [
            ['value' => ProfileVisibility::Public->value, 'label' => 'Alle'],
            ['value' => ProfileVisibility::Followers->value, 'label' => 'Follower'],
            ['value' => ProfileVisibility::Mutuals->value, 'label' => 'Gegenseitige Kontakte'],
            ['value' => ProfileVisibility::Private->value, 'label' => 'Nur ich'],
        ];
        $selectedLanguageIds = $profile->languageOptions->modelKeys();
        $selectedInterestIds = $profile->interestOptions->modelKeys();
        $languageOptions = LanguageOption::query()
            ->where(function ($query) use ($selectedLanguageIds): void {
                $query->where('is_active', true)
                    ->when(
                        $selectedLanguageIds !== [],
                        fn ($query) => $query->orWhereIn('id', $selectedLanguageIds),
                    );
            })
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['code', 'label', 'native_label', 'is_active']);
        $interestOptions = InterestOption::query()
            ->where(function ($query) use ($selectedInterestIds): void {
                $query->where('is_active', true)
                    ->when(
                        $selectedInterestIds !== [],
                        fn ($query) => $query->orWhereIn('id', $selectedInterestIds),
                    );
            })
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['slug', 'label', 'is_active']);

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'display_name' => $profile->display_name,
                'bio' => $profile->bio,
                'region' => $profile->region,
                'languages' => $profile->languageOptions->pluck('code')->values(),
                'interests' => $profile->interestOptions->pluck('slug')->values(),
                'profile_visibility' => $profile->profile_visibility->value,
                'interests_visibility' => $profile->interests_visibility->value,
                'languages_visibility' => $profile->languages_visibility->value,
                'region_visibility' => $profile->region_visibility->value,
                'social_counts_visibility' => $profile->social_counts_visibility->value,
            ],
            'languageOptions' => $languageOptions
                ->map(fn (LanguageOption $language): array => [
                    'value' => $language->code,
                    'label' => $language->native_label !== null
                        && $language->native_label !== $language->label
                            ? "{$language->label} ({$language->native_label})"
                            : $language->label,
                    'is_active' => $language->is_active,
                ]),
            'interestOptions' => $interestOptions
                ->map(fn (InterestOption $interest): array => [
                    'value' => $interest->slug,
                    'label' => $interest->label,
                    'is_active' => $interest->is_active,
                ]),
            'fieldVisibilityOptions' => $fieldVisibilityOptions,
            'profileVisibilityOptions' => $profileVisibilityOptions,
        ]);
    }

    /**
     * Update the authenticated user's NEAREON profile.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $profile = $request->user()->profile;

        if ($profile === null) {
            return NextUserRoute::redirect($request->user());
        }

        $this->profileOptions->update($profile, $request->validated());

        return to_route('neareon-profile.edit')
            ->with('success', 'Profil wurde gespeichert.');
    }

    /**
     * Show a public profile with server-side privacy filtering.
     */
    public function show(Request $request, string $username): Response|RedirectResponse
    {
        $viewer = $request->user();
        $viewerProfile = $viewer->profile;

        if ($viewerProfile === null) {
            return NextUserRoute::redirect($viewer);
        }

        $profile = Profile::query()
            ->with(['user', 'languageOptions', 'interestOptions'])
            ->where('username', $username)
            ->firstOrFail();

        return Inertia::render('Profile/Show', [
            'profile' => $this->profileVisibility->visibleProfileData($profile, $viewer),
        ]);
    }
}
