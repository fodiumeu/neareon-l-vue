<?php

namespace App\Services;

use App\Enums\ContactRequestStatus;
use App\Enums\ContactStatus;
use App\Models\ContactRequest;
use App\Models\User;

class ContactStatusService
{
    /**
     * Determine the contact status from the viewer's perspective.
     */
    public function between(
        User $viewer,
        User $otherUser,
        ?bool $isFollowing = null,
        ?bool $isFollowedBy = null,
    ): ContactStatus {
        if ($viewer->is($otherUser)) {
            return ContactStatus::None;
        }

        $isFollowing ??= $viewer->isFollowing($otherUser);
        $isFollowedBy ??= $otherUser->isFollowing($viewer);

        if ($isFollowing && $isFollowedBy) {
            return ContactStatus::Connected;
        }

        if ($this->hasPendingRequest($viewer, $otherUser)) {
            return ContactStatus::OutgoingRequest;
        }

        if ($this->hasPendingRequest($otherUser, $viewer)) {
            return ContactStatus::IncomingRequest;
        }

        return ContactStatus::None;
    }

    private function hasPendingRequest(User $sender, User $receiver): bool
    {
        if ($sender->relationLoaded('sentContactRequests')) {
            return $sender->sentContactRequests->contains(
                fn (ContactRequest $contactRequest): bool => $contactRequest->receiver_id === $receiver->id
                    && $contactRequest->status === ContactRequestStatus::Pending,
            );
        }

        if ($receiver->relationLoaded('receivedContactRequests')) {
            return $receiver->receivedContactRequests->contains(
                fn (ContactRequest $contactRequest): bool => $contactRequest->sender_id === $sender->id
                    && $contactRequest->status === ContactRequestStatus::Pending,
            );
        }

        return $sender->sentContactRequests()
            ->where('receiver_id', $receiver->id)
            ->where('status', ContactRequestStatus::Pending->value)
            ->exists();
    }
}
