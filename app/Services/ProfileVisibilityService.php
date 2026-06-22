<?php

namespace App\Services;

use App\Enums\ContactPermission;
use App\Enums\ContactStatus;
use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;

class ProfileVisibilityService
{
    public function __construct(
        private readonly ContactStatusService $contactStatus,
        private readonly PrivacyService $privacy,
    ) {}

    /**
     * Build profile props without leaking hidden fields.
     *
     * @return array<string, mixed>
     */
    public function visibleProfileData(
        Profile $profile,
        User $viewer,
        bool $includeSocialCounts = false,
        bool $includeProfileMetadata = false,
        bool $includeCommonalities = false,
        ?bool $isFollowing = null,
        ?bool $isFollowedBy = null,
        int $commonLanguagesLimit = 3,
        int $commonInterestsLimit = 4,
    ): array {
        $isOwnProfile = $profile->user->is($viewer);
        $isFollowing = ! $isOwnProfile
            && ($isFollowing ?? $viewer->isFollowing($profile->user));
        $isFollowedBy = ! $isOwnProfile
            && ($isFollowedBy ?? $profile->user->isFollowing($viewer));
        $isMutual = $isFollowing && $isFollowedBy;
        $isBlockedByViewer = ! $isOwnProfile
            && $viewer->hasBlocked($profile->user);
        $interactionBlocked = ! $isOwnProfile
            && ($isBlockedByViewer || $viewer->isBlockedBy($profile->user));

        $contactStatus = $interactionBlocked
            ? ContactStatus::None
            : $this->contactStatus
                ->between($viewer, $profile->user, $isFollowing, $isFollowedBy);

        $data = [
            'username' => $profile->username,
            'isOwnProfile' => $isOwnProfile,
            'is_following' => $isFollowing,
            'is_followed_by' => $isFollowedBy,
            'is_mutual' => $isMutual,
            'contact_status' => $contactStatus->value,
            'interaction_blocked' => $interactionBlocked,
            'is_blocked_by_viewer' => $isBlockedByViewer,
            'profile_photo_url' => $profile->profilePhotoUrl(),
        ];

        if (! $isOwnProfile) {
            $data['contact_user_id'] = $profile->user_id;
            $data['can_follow'] = $this->privacy
                ->canFollow($viewer, $profile->user);
            $data['can_send_contact_request'] = $this->privacy
                ->canSendContactRequest(
                    $viewer,
                    $profile->user,
                    $isFollowing,
                );
            $data['contact_request_unavailable_reason'] = match (true) {
                $profile->contact_permission === ContactPermission::Nobody => 'disabled',
                $profile->contact_permission === ContactPermission::Followers
                    && ! $isFollowing => 'follow_required',
                default => null,
            };
        }

        if (! $interactionBlocked
            && $contactStatus === ContactStatus::IncomingRequest) {
            $data['incoming_contact_request_id'] = $viewer
                ->receivedContactRequests
                ->first(
                    fn ($contactRequest): bool => $contactRequest->sender_id === $profile->user_id,
                )?->id;
        }

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

        if ($includeSocialCounts
            && $this->canView(
                $profile->social_counts_visibility,
                $isOwnProfile,
                $isFollowing,
                $isMutual,
            )) {
            $data['followers_count'] = (int) $profile->user->followers_count;
            $data['contacts_count'] = (int) $profile->user->contacts_count;
        }

        if ($includeProfileMetadata) {
            $data['member_since'] = $profile->user->created_at
                ->locale('de')
                ->translatedFormat('F Y');
        }

        if (($includeProfileMetadata || $includeCommonalities) && ! $isOwnProfile) {
            $viewerProfile = $viewer->profile;

            if ($viewerProfile !== null
                && $this->canView(
                    $profile->languages_visibility,
                    false,
                    $isFollowing,
                    $isMutual,
                )) {
                $viewerLanguageIds = $viewerProfile->languageOptions->modelKeys();
                $commonLanguages = $profile->languageOptions
                    ->filter(
                        fn ($language): bool => in_array(
                            $language->id,
                            $viewerLanguageIds,
                            true,
                        ),
                    )
                    ->take($commonLanguagesLimit)
                    ->pluck('label')
                    ->values()
                    ->all();

                if ($commonLanguages !== []) {
                    $data['common_languages'] = $commonLanguages;
                }
            }

            if ($viewerProfile !== null
                && $this->canView(
                    $profile->interests_visibility,
                    false,
                    $isFollowing,
                    $isMutual,
                )) {
                $viewerInterestIds = $viewerProfile->interestOptions->modelKeys();
                $commonInterests = $profile->interestOptions
                    ->sortBy('sort_order')
                    ->filter(
                        fn ($interest): bool => in_array(
                            $interest->id,
                            $viewerInterestIds,
                            true,
                        ),
                    )
                    ->take($commonInterestsLimit)
                    ->pluck('label')
                    ->values()
                    ->all();

                if ($commonInterests !== []) {
                    $data['common_interests'] = $commonInterests;
                }
            }
        }

        return $data;
    }

    /**
     * Determine whether a profile should appear in discover for the viewer.
     */
    public function isDiscoverVisible(
        Profile $profile,
        User $viewer,
        ?bool $isFollowing = null,
        ?bool $isFollowedBy = null,
    ): bool {
        if ($profile->user->is($viewer)) {
            return false;
        }

        return $this->privacy->canViewProfile(
            $profile,
            $viewer,
            $isFollowing,
            $isFollowedBy,
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
            ProfileVisibility::Public,
            ProfileVisibility::Members => true,
            ProfileVisibility::Followers => $isFollowing,
            ProfileVisibility::Contacts,
            ProfileVisibility::Mutuals => $isMutual,
            ProfileVisibility::Private => false,
        };
    }
}
