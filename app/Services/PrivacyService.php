<?php

namespace App\Services;

use App\Enums\ContactPermission;
use App\Enums\FollowPermission;
use App\Enums\MessagePermission;
use App\Enums\OnlineStatusVisibility;
use App\Enums\ProfileVisibility;
use App\Models\Conversation;
use App\Models\Profile;
use App\Models\User;

class PrivacyService
{
    public function canViewProfile(
        Profile $profile,
        User $viewer,
        ?bool $isFollowing = null,
        ?bool $isFollowedBy = null,
    ): bool {
        $owner = $profile->user;

        if ($owner->is($viewer)) {
            return true;
        }

        if ($viewer->hasBlockWith($owner)) {
            return false;
        }

        $isFollowing ??= $viewer->isFollowing($owner);
        $isFollowedBy ??= $owner->isFollowing($viewer);

        return match ($profile->profile_visibility) {
            ProfileVisibility::Public,
            ProfileVisibility::Members => true,
            ProfileVisibility::Contacts,
            ProfileVisibility::Mutuals => $isFollowing && $isFollowedBy,
            ProfileVisibility::Followers => $isFollowing,
            ProfileVisibility::Private => false,
        };
    }

    public function canFollow(User $follower, User $target): bool
    {
        if ($follower->is($target) || $follower->hasBlockWith($target)) {
            return false;
        }

        return match ($target->profile?->follow_permission) {
            FollowPermission::Everyone,
            FollowPermission::Members,
            null => true,
            FollowPermission::Nobody => false,
        };
    }

    public function canSendContactRequest(
        User $sender,
        User $receiver,
        ?bool $isFollowing = null,
    ): bool {
        if ($sender->is($receiver) || $sender->hasBlockWith($receiver)) {
            return false;
        }

        $isFollowing ??= $sender->isFollowing($receiver);

        return match ($receiver->profile?->contact_permission) {
            ContactPermission::Everyone,
            null => true,
            ContactPermission::Followers => $isFollowing,
            ContactPermission::Nobody => false,
        };
    }

    public function canSendMessage(
        User $sender,
        User $receiver,
        Conversation $conversation,
    ): bool {
        if ($sender->is($receiver) || $sender->hasBlockWith($receiver)) {
            return false;
        }

        return match ($receiver->profile?->message_permission) {
            MessagePermission::ContactsOnly,
            null => $sender->isMutualWith($receiver),
            MessagePermission::ExistingConversations => $sender->isMutualWith($receiver)
                && $this->usersParticipate(
                    $conversation,
                    $sender,
                    $receiver,
                ),
        };
    }

    public function canViewOnlineStatus(User $viewer, User $target): bool
    {
        if ($viewer->is($target)) {
            return true;
        }

        if ($viewer->hasBlockWith($target)) {
            return false;
        }

        return match ($target->profile?->online_status_visibility) {
            OnlineStatusVisibility::Nobody,
            null => false,
            OnlineStatusVisibility::Contacts,
            OnlineStatusVisibility::MutualContacts => $viewer->isMutualWith($target),
        };
    }

    private function usersParticipate(
        Conversation $conversation,
        User $userA,
        User $userB,
    ): bool {
        return $conversation->participants()
            ->whereIn('user_id', [$userA->id, $userB->id])
            ->distinct()
            ->count('user_id') === 2;
    }
}
