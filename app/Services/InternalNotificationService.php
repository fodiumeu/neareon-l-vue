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
            $sender->id,
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
            $receiver->id,
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
            $follower->id,
        ));
    }

    public function newMessage(
        User $sender,
        User $receiver,
        Conversation $conversation,
    ): void {
        $notification = new InternalNotification(
            InternalNotificationType::NewMessage,
            'Neue Nachricht',
            "Neue Nachricht von {$this->displayName($sender)}.",
            route('messages.show', $conversation, absolute: false),
            $sender->id,
            $conversation->id,
        );

        $existingNotification = $receiver->unreadNotifications()
            ->where('type', InternalNotification::class)
            ->get()
            ->first(function ($storedNotification) use ($conversation): bool {
                $conversationId = $storedNotification->data['conversation_id'] ?? null;
                $targetUrl = $storedNotification->data['target_url'] ?? null;

                return ($storedNotification->data['type'] ?? null)
                    === InternalNotificationType::NewMessage->value
                    && (
                        (int) $conversationId === $conversation->id
                        || $targetUrl === route(
                            'messages.show',
                            $conversation,
                            absolute: false,
                        )
                    );
            });

        if ($existingNotification === null) {
            $receiver->notify($notification);

            return;
        }

        $existingNotification->forceFill([
            'data' => $notification->toDatabase($receiver),
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();
    }

    private function displayName(User $user): string
    {
        return $user->profile?->display_name ?? $user->name;
    }
}
