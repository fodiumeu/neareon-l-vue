<?php

namespace App\Services;

use App\Enums\ContactRequestStatus;
use App\Models\User;

class NavigationBadgeService
{
    public function __construct(
        private readonly ConversationReadService $conversationReads,
    ) {}

    /**
     * @return array{
     *     unreadMessages: int,
     *     unreadNotifications: int,
     *     pendingContactRequests: int
     * }
     */
    public function countsFor(User $user): array
    {
        return [
            'unreadMessages' => $this->conversationReads
                ->countUnreadMessagesForUser($user),
            'unreadNotifications' => $user->unreadNotifications()->count(),
            'pendingContactRequests' => $user->receivedContactRequests()
                ->where('status', ContactRequestStatus::Pending->value)
                ->count(),
        ];
    }
}
