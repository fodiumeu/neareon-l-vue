<?php

namespace App\Http\Controllers;

use App\Enums\ProfileVisibility;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Profile;
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
        $storedLanguages = $profile->languages ?? [];
        $storedInterests = $profile->interests ?? [];
        $languageOptions = LanguageOption::query()
            ->where(function ($query) use ($storedLanguages): void {
                $query->where('is_active', true)
                    ->when(
                        $storedLanguages !== [],
                        fn ($query) => $query
                            ->orWhereIn('code', $storedLanguages)
                            ->orWhereIn('label', $storedLanguages),
                    );
            })
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['code', 'label', 'native_label', 'is_active']);
        $interestOptions = InterestOption::query()
            ->where(function ($query) use ($storedInterests): void {
                $query->where('is_active', true)
                    ->when(
                        $storedInterests !== [],
                        fn ($query) => $query
                            ->orWhereIn('slug', $storedInterests)
                            ->orWhereIn('label', $storedInterests),
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
                'languages' => collect($storedLanguages)
                    ->map(fn (string $value): ?string => $languageOptions
                        ->first(fn (LanguageOption $option): bool => in_array($value, [
                            $option->code,
                            $option->label,
                        ], true))
                        ?->code)
                    ->filter()
                    ->values(),
                'interests' => collect($storedInterests)
                    ->map(fn (string $value): ?string => $interestOptions
                        ->first(fn (InterestOption $option): bool => in_array($value, [
                            $option->slug,
                            $option->label,
                        ], true))
                        ?->slug)
                    ->filter()
                    ->values(),
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

        $profile->update($request->validated());

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
            ->with('user')
            ->where('username', $username)
            ->firstOrFail();

        return Inertia::render('Profile/Show', [
            'profile' => $this->profileVisibility->visibleProfileData($profile, $viewer),
        ]);
    }
}
