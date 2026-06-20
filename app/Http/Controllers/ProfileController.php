<?php

namespace App\Http\Controllers;

use App\Enums\ContactRequestStatus;
use App\Enums\ProfileVisibility;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Profile;
use App\Services\PrivacyService;
use App\Services\ProfileOptionSyncService;
use App\Services\ProfileVisibilityService;
use App\Support\NextUserRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileVisibilityService $profileVisibility,
        private readonly ProfileOptionSyncService $profileOptions,
        private readonly PrivacyService $privacy,
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
        $this->loadSocialCounts($profile);

        return Inertia::render('Profile/Show', [
            'profile' => $this->profileVisibility->visibleProfileData(
                $profile,
                $user,
                includeSocialCounts: true,
                includeProfileMetadata: true,
            ),
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
            ['value' => ProfileVisibility::Public->value, 'label' => 'Öffentlich'],
            ['value' => ProfileVisibility::Members->value, 'label' => 'Mitglieder'],
            ['value' => ProfileVisibility::Contacts->value, 'label' => 'Kontakte'],
        ];

        if ($profile->profile_visibility === ProfileVisibility::Private) {
            $profileVisibilityOptions[] = [
                'value' => ProfileVisibility::Private->value,
                'label' => 'Nur ich (bisherige Einstellung)',
            ];
        }
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
                'profile_photo_url' => $profile->profilePhotoUrl(),
                'region' => $profile->region,
                'languages' => $profile->languageOptions->pluck('code')->values(),
                'interests' => $profile->interestOptions->pluck('slug')->values(),
                'profile_visibility' => $profile->profile_visibility->value,
                'follow_permission' => $profile->follow_permission->value,
                'contact_permission' => $profile->contact_permission->value,
                'message_permission' => $profile->message_permission->value,
                'online_status_visibility' => $profile->online_status_visibility->value,
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
            'followPermissionOptions' => [
                ['value' => 'everyone', 'label' => 'Alle'],
                ['value' => 'members', 'label' => 'Mitglieder'],
                ['value' => 'nobody', 'label' => 'Niemand'],
            ],
            'contactPermissionOptions' => [
                ['value' => 'everyone', 'label' => 'Alle'],
                ['value' => 'followers', 'label' => 'Follower'],
                ['value' => 'nobody', 'label' => 'Niemand'],
            ],
            'messagePermissionOptions' => [
                ['value' => 'contacts_only', 'label' => 'Nur Kontakte'],
                ['value' => 'existing_conversations', 'label' => 'Bestehende Unterhaltungen'],
            ],
            'onlineStatusVisibilityOptions' => [
                ['value' => 'nobody', 'label' => 'Niemand'],
                ['value' => 'contacts', 'label' => 'Kontakte'],
                ['value' => 'mutual_contacts', 'label' => 'Gegenseitige Kontakte'],
            ],
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

        $attributes = $request->validated();
        $removeProfilePhoto = (bool) ($attributes['remove_profile_photo'] ?? false);
        unset($attributes['profile_photo'], $attributes['remove_profile_photo']);

        $newPhotoPath = null;
        $oldPhotoPath = $profile->profile_photo_path;

        if ($request->hasFile('profile_photo')) {
            $newPhotoPath = $request->file('profile_photo')
                ->store('profile-photos', 'public');

            if ($newPhotoPath === false) {
                throw new RuntimeException('Das Profilbild konnte nicht gespeichert werden.');
            }
        }

        try {
            DB::transaction(function () use (
                $attributes,
                $newPhotoPath,
                $profile,
                $removeProfilePhoto,
            ): void {
                $this->profileOptions->update($profile, $attributes);

                if ($newPhotoPath !== null) {
                    $profile->update(['profile_photo_path' => $newPhotoPath]);
                } elseif ($removeProfilePhoto) {
                    $profile->update(['profile_photo_path' => null]);
                }
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath !== null) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if (($newPhotoPath !== null || $removeProfilePhoto)
            && $oldPhotoPath !== null) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

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

        $viewerProfile->loadMissing(['languageOptions', 'interestOptions']);

        $viewer->loadMissing([
            'sentContactRequests' => fn ($query) => $query
                ->where('status', ContactRequestStatus::Pending->value)
                ->select(['id', 'sender_id', 'receiver_id', 'status']),
            'receivedContactRequests' => fn ($query) => $query
                ->where('status', ContactRequestStatus::Pending->value)
                ->select(['id', 'sender_id', 'receiver_id', 'status']),
            'blockingRelationships:id,blocker_id,blocked_id',
            'blockedByRelationships:id,blocker_id,blocked_id',
        ]);

        $profile = Profile::query()
            ->with(['user', 'languageOptions', 'interestOptions'])
            ->where('username', $username)
            ->firstOrFail();

        if (in_array($profile->profile_visibility, [
            ProfileVisibility::Members,
            ProfileVisibility::Contacts,
        ], true)) {
            abort_unless($this->privacy->canViewProfile($profile, $viewer), 403);
        }

        $this->loadSocialCounts($profile);

        return Inertia::render('Profile/Show', [
            'profile' => $this->profileVisibility->visibleProfileData(
                $profile,
                $viewer,
                includeSocialCounts: true,
                includeProfileMetadata: true,
            ),
        ]);
    }

    /**
     * Load profile statistics from the existing follow relationships.
     */
    private function loadSocialCounts(Profile $profile): void
    {
        $owner = $profile->user;

        $owner->loadCount([
            'followers',
            'following as contacts_count' => fn ($query) => $query
                ->whereHas(
                    'followingRelationships',
                    fn ($query) => $query->where('followed_id', $owner->id),
                )
                ->whereDoesntHave(
                    'blockingRelationships',
                    fn ($query) => $query->where('blocked_id', $owner->id),
                )
                ->whereDoesntHave(
                    'blockedByRelationships',
                    fn ($query) => $query->where('blocker_id', $owner->id),
                ),
        ]);
    }
}
