<?php

namespace App\Services;

use App\Enums\ContactRequestStatus;
use App\Enums\InternalNotificationType;
use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Group;
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

    public function eventAttendanceRequestReceived(
        User $actor,
        Event $event,
    ): void {
        $owner = $event->owner;

        if ($owner === null || $owner->is($actor)) {
            return;
        }

        $owner->notify(new InternalNotification(
            InternalNotificationType::EventAttendanceRequestReceived,
            'Neue Teilnahme-Anfrage',
            "{$this->displayName($actor)} möchte an deinem Event {$event->title} teilnehmen.",
            route('events.show', $event->slug, absolute: false),
            $actor->id,
            extraData: $this->eventData($event),
        ));
    }

    public function eventAttendanceRequestAccepted(
        User $actor,
        User $recipient,
        Event $event,
    ): void {
        if ($recipient->is($actor)) {
            return;
        }

        $recipient->notify(new InternalNotification(
            InternalNotificationType::EventAttendanceRequestAccepted,
            'Teilnahme-Anfrage angenommen',
            "Deine Anfrage für {$event->title} wurde angenommen.",
            route('events.show', $event->slug, absolute: false),
            $actor->id,
            extraData: $this->eventData($event),
        ));
    }

    public function eventAttendanceRequestDeclined(
        User $actor,
        User $recipient,
        Event $event,
    ): void {
        if ($recipient->is($actor)) {
            return;
        }

        $recipient->notify(new InternalNotification(
            InternalNotificationType::EventAttendanceRequestDeclined,
            'Teilnahme-Anfrage abgelehnt',
            "Deine Anfrage für {$event->title} wurde nicht angenommen.",
            route('events.index', absolute: false),
            $actor->id,
            extraData: $this->eventData($event),
        ));
    }

    public function eventAttendeeJoined(
        User $actor,
        Event $event,
    ): void {
        $owner = $event->owner;

        if ($owner === null || $owner->is($actor)) {
            return;
        }

        $owner->notify(new InternalNotification(
            InternalNotificationType::EventAttendeeJoined,
            'Neuer Event-Teilnehmer',
            "{$this->displayName($actor)} nimmt an deinem Event {$event->title} teil.",
            route('events.show', $event->slug, absolute: false),
            $actor->id,
            extraData: $this->eventData($event),
        ));
    }

    public function groupJoinRequestReceived(
        User $actor,
        Group $group,
    ): void {
        $owner = $group->owner;

        if ($owner === null || $owner->is($actor)) {
            return;
        }

        $owner->notify(new InternalNotification(
            InternalNotificationType::GroupJoinRequestReceived,
            'Neue Beitrittsanfrage',
            "{$this->displayName($actor)} möchte deiner Gruppe {$group->name} beitreten.",
            route('groups.show', $group->slug, absolute: false),
            $actor->id,
            extraData: $this->groupData($group),
        ));
    }

    public function groupJoinRequestAccepted(
        User $actor,
        User $recipient,
        Group $group,
    ): void {
        $recipient->notify(new InternalNotification(
            InternalNotificationType::GroupJoinRequestAccepted,
            'Beitrittsanfrage angenommen',
            "Du bist jetzt Mitglied in {$group->name}.",
            route('groups.show', $group->slug, absolute: false),
            $actor->id,
            extraData: $this->groupData($group),
        ));
    }

    public function groupJoinRequestDeclined(
        User $actor,
        User $recipient,
        Group $group,
    ): void {
        $recipient->notify(new InternalNotification(
            InternalNotificationType::GroupJoinRequestDeclined,
            'Beitrittsanfrage abgelehnt',
            "Deine Anfrage für {$group->name} wurde nicht angenommen.",
            route('groups.index', absolute: false),
            $actor->id,
            extraData: $this->groupData($group),
        ));
    }

    public function groupMemberJoined(
        User $actor,
        Group $group,
    ): void {
        $owner = $group->owner;

        if ($owner === null || $owner->is($actor)) {
            return;
        }

        $owner->notify(new InternalNotification(
            InternalNotificationType::GroupMemberJoined,
            'Neues Gruppenmitglied',
            "{$this->displayName($actor)} ist deiner Gruppe {$group->name} beigetreten.",
            route('groups.show', $group->slug, absolute: false),
            $actor->id,
            extraData: $this->groupData($group),
        ));
    }

    public function groupMemberRemoved(
        User $actor,
        User $recipient,
        Group $group,
    ): void {
        if ($recipient->is($actor)) {
            return;
        }

        $recipient->notify(new InternalNotification(
            InternalNotificationType::GroupMemberRemoved,
            'Aus Gruppe entfernt',
            "Du wurdest aus der Gruppe {$group->name} entfernt.",
            route('groups.index', absolute: false),
            $actor->id,
            extraData: $this->groupData($group),
        ));
    }

    public function groupModeratorPromoted(
        User $actor,
        User $recipient,
        Group $group,
    ): void {
        if ($recipient->is($actor)) {
            return;
        }

        $recipient->notify(new InternalNotification(
            InternalNotificationType::GroupModeratorPromoted,
            'Moderatorrolle erhalten',
            "Du wurdest in der Gruppe {$group->name} zum Moderator gemacht.",
            route('groups.show', $group->slug, absolute: false),
            $actor->id,
            extraData: $this->groupData($group),
        ));
    }

    public function groupModeratorDemoted(
        User $actor,
        User $recipient,
        Group $group,
    ): void {
        if ($recipient->is($actor)) {
            return;
        }

        $recipient->notify(new InternalNotification(
            InternalNotificationType::GroupModeratorDemoted,
            'Moderatorrolle entfernt',
            "Du bist in der Gruppe {$group->name} wieder Mitglied.",
            route('groups.show', $group->slug, absolute: false),
            $actor->id,
            extraData: $this->groupData($group),
        ));
    }

    private function displayName(User $user): string
    {
        return $user->profile?->display_name ?? $user->name;
    }

    /**
     * @return array{event_id: int, event_name: string, event_slug: string}
     */
    private function eventData(Event $event): array
    {
        return [
            'event_id' => $event->id,
            'event_name' => $event->title,
            'event_slug' => $event->slug,
        ];
    }

    /**
     * @return array{group_id: int, group_name: string, group_slug: string}
     */
    private function groupData(Group $group): array
    {
        return [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'group_slug' => $group->slug,
        ];
    }
}
