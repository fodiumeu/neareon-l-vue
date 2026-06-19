<?php

namespace App\Services;

use App\Enums\ContactRequestStatus;
use App\Enums\InternalNotificationType;
use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\InternalNotification;

class InternalNotificationService
{
    public function contactRequestReceived(User $sender, User $receiver): void
    {
        $receiver->notify(new InternalNotification(
            InternalNotificationType::ContactRequestReceived,
            'Neue Kontaktanfrage',
            "{$this->displayName($sender)} hat dir eine Kontaktanfrage gesendet.",
            route('contact-requests.index', absolute: false),
        ));
    }

    public function contactRequestResponded(
        ContactRequest $contactRequest,
        ContactRequestStatus $status,
    ): void {
        $receiver = $contactRequest->receiver;
        $accepted = $status === ContactRequestStatus::Accepted;

        $contactRequest->sender->notify(new InternalNotification(
            $accepted
                ? InternalNotificationType::ContactRequestAccepted
                : InternalNotificationType::ContactRequestDeclined,
            $accepted
                ? 'Kontaktanfrage angenommen'
                : 'Kontaktanfrage abgelehnt',
            sprintf(
                '%s hat deine Kontaktanfrage %s.',
                $this->displayName($receiver),
                $accepted ? 'angenommen' : 'abgelehnt',
            ),
            route('contact-requests.sent', absolute: false),
        ));
    }

    public function newFollower(User $follower, User $followed): void
    {
        $followerProfile = $follower->profile;

        if ($followerProfile === null) {
            return;
        }

        $followed->notify(new InternalNotification(
            InternalNotificationType::NewFollower,
            'Neuer Follower',
            "{$this->displayName($follower)} folgt dir jetzt.",
            route(
                'public-profile.show',
                $followerProfile->username,
                absolute: false,
            ),
        ));
    }

    public function newMessage(
        User $sender,
        User $receiver,
        Conversation $conversation,
    ): void {
        $receiver->notify(new InternalNotification(
            InternalNotificationType::NewMessage,
            'Neue Nachricht',
            "{$this->displayName($sender)} hat dir eine Nachricht gesendet.",
            route('messages.show', $conversation, absolute: false),
        ));
    }

    private function displayName(User $user): string
    {
        return $user->profile?->display_name ?? $user->name;
    }
}
