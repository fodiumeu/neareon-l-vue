<?php

namespace App\Http\Controllers;

use App\Enums\ProfileVisibility;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the edit form for the authenticated user's NEAREON profile.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $profile = $request->user()->profile;

        if ($profile === null) {
            return to_route('onboarding.create');
        }

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'display_name' => $profile->display_name,
                'bio' => $profile->bio,
                'region' => $profile->region,
                'languages' => implode(', ', $profile->languages ?? []),
                'interests' => implode(', ', $profile->interests ?? []),
                'profile_visibility' => $profile->profile_visibility->value,
                'interests_visibility' => $profile->interests_visibility->value,
                'languages_visibility' => $profile->languages_visibility->value,
                'region_visibility' => $profile->region_visibility->value,
                'social_counts_visibility' => $profile->social_counts_visibility->value,
            ],
            'visibilityOptions' => [
                ['value' => ProfileVisibility::Public->value, 'label' => 'Alle'],
                ['value' => ProfileVisibility::Mutuals->value, 'label' => 'Gegenseitige Kontakte'],
                ['value' => ProfileVisibility::Private->value, 'label' => 'Nur ich'],
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
            return to_route('onboarding.create');
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
            return to_route('onboarding.create');
        }

        $profile = Profile::query()
            ->with('user')
            ->where('username', $username)
            ->firstOrFail();

        return Inertia::render('Profile/Show', [
            'profile' => $this->visibleProfileData($profile, $viewer),
        ]);
    }

    /**
     * Build public profile props without leaking hidden fields.
     *
     * @return array<string, mixed>
     */
    private function visibleProfileData(Profile $profile, User $viewer): array
    {
        $isOwnProfile = $profile->user->is($viewer);
        $isFollowing = ! $isOwnProfile && $viewer->isFollowing($profile->user);
        $isFollowedBy = ! $isOwnProfile && $profile->user->isFollowing($viewer);
        $isMutual = $isFollowing && $isFollowedBy;

        $data = [
            'username' => $profile->username,
            'isOwnProfile' => $isOwnProfile,
            'is_following' => $isFollowing,
            'is_followed_by' => $isFollowedBy,
            'is_mutual' => $isMutual,
        ];

        if ($this->canView($profile->profile_visibility, $isOwnProfile, $isMutual)) {
            $data['display_name'] = $profile->display_name;
            $data['bio'] = $profile->bio;
        }

        if ($this->canView($profile->region_visibility, $isOwnProfile, $isMutual)) {
            $data['region'] = $profile->region;
        }

        if ($this->canView($profile->languages_visibility, $isOwnProfile, $isMutual)) {
            $data['languages'] = $profile->languages;
        }

        if ($this->canView($profile->interests_visibility, $isOwnProfile, $isMutual)) {
            $data['interests'] = $profile->interests;
        }

        return $data;
    }

    private function canView(ProfileVisibility $visibility, bool $isOwnProfile, bool $isMutual): bool
    {
        if ($isOwnProfile) {
            return true;
        }

        return match ($visibility) {
            ProfileVisibility::Public => true,
            ProfileVisibility::Mutuals => $isMutual,
            ProfileVisibility::Private => false,
        };
    }
}
