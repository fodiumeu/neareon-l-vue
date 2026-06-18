<?php

namespace App\Services;

use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;

class ProfileVisibilityService
{
    /**
     * Build profile props without leaking hidden fields.
     *
     * @return array<string, mixed>
     */
    public function visibleProfileData(Profile $profile, User $viewer): array
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

        if ($this->canView($profile->profile_visibility, $isOwnProfile, $isFollowing, $isMutual)) {
            $data['display_name'] = $profile->display_name;
            $data['bio'] = $profile->bio;
        }

        if ($this->canView($profile->region_visibility, $isOwnProfile, $isFollowing, $isMutual)) {
            $data['region'] = $profile->region;
        }

        if ($this->canView($profile->languages_visibility, $isOwnProfile, $isFollowing, $isMutual)) {
            $data['languages'] = $profile->languageOptions
                ->pluck('label')
                ->values()
                ->all();
        }

        if ($this->canView($profile->interests_visibility, $isOwnProfile, $isFollowing, $isMutual)) {
            $data['interests'] = $profile->interestOptions
                ->sortBy('sort_order')
                ->pluck('label')
                ->values()
                ->all();
        }

        return $data;
    }

    /**
     * Determine whether a profile should appear in discover for the viewer.
     */
    public function isDiscoverVisible(Profile $profile, User $viewer): bool
    {
        if ($profile->user->is($viewer)) {
            return false;
        }

        return $this->canView(
            $profile->profile_visibility,
            isOwnProfile: false,
            isFollowing: $viewer->isFollowing($profile->user),
            isMutual: $viewer->isMutualWith($profile->user),
        );
    }

    private function canView(
        ProfileVisibility $visibility,
        bool $isOwnProfile,
        bool $isFollowing,
        bool $isMutual,
    ): bool {
        if ($isOwnProfile) {
            return true;
        }

        return match ($visibility) {
            ProfileVisibility::Public => true,
            ProfileVisibility::Followers => $isFollowing,
            ProfileVisibility::Mutuals => $isMutual,
            ProfileVisibility::Private => false,
        };
    }
}
