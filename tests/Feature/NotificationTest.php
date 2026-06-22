<?php

use App\Enums\InternalNotificationType;
use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\InternalNotification;
use Inertia\Testing\AssertableInertia as Assert;

function notificationUsers(): array
{
    $actor = User::factory()->create();
    createOnboardedProfile($actor, [
        'display_name' => 'Actor User',
        'username' => 'actor_user',
    ]);
    $recipient = User::factory()->create();
    createOnboardedProfile($recipient, [
        'display_name' => 'Recipient User',
        'username' => 'recipient_user',
    ]);

    return [$actor, $recipient];
}

function allowNotificationMessageExchange(
    User $userA,
    User $userB,
): Conversation {
    Follow::query()->create([
        'follower_id' => $userA->id,
        'followed_id' => $userB->id,
    ]);
    Follow::query()->create([
        'follower_id' => $userB->id,
        'followed_id' => $userA->id,
    ]);
    $conversation = Conversation::factory()->create();
    ConversationParticipant::factory()
        ->for($conversation)
        ->for($userA)
        ->create();
    ConversationParticipant::factory()
        ->for($conversation)
        ->for($userB)
        ->create();

    return $conversation;
}

test('a new contact request creates an internal notification', function () {
    [$sender, $receiver] = notificationUsers();

    $this->actingAs($sender)
        ->post(route('contact-requests.store'), [
            'receiver_id' => $receiver->id,
        ])
        ->assertRedirect();

    $notification = $receiver->notifications()->sole();

    expect($notification->data['type'])
        ->toBe(InternalNotificationType::ContactRequestReceived->value)
        ->and($notification->data['target_url'])
        ->toBe(route('contact-requests.index', absolute: false))
        ->and($notification->read_at)->toBeNull();
});

test('duplicate pending requests do not create duplicate notifications', function () {
    [$sender, $receiver] = notificationUsers();

    $this->actingAs($sender)
        ->post(route('contact-requests.store'), [
            'receiver_id' => $receiver->id,
        ])
        ->assertRedirect();
    $this->actingAs($sender)
        ->post(route('contact-requests.store'), [
            'receiver_id' => $receiver->id,
        ])
        ->assertRedirect();

    expect($receiver->notifications()->count())->toBe(1);
});

test('contact request responses notify the sender', function (
    string $action,
    InternalNotificationType $type,
) {
    [$sender, $receiver] = notificationUsers();
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    $this->actingAs($receiver)
        ->patch(route("contact-requests.{$action}", $contactRequest))
        ->assertRedirect();

    expect($sender->notifications()
        ->where('data->type', $type->value)
        ->count())->toBe(1);
})->with([
    'accepted' => [
        'accept',
        InternalNotificationType::ContactRequestAccepted,
    ],
    'declined' => [
        'decline',
        InternalNotificationType::ContactRequestDeclined,
    ],
]);

test('a new follow creates one follower notification', function () {
    [$follower, $followed] = notificationUsers();

    $this->actingAs($follower)
        ->post(route('public-profile.follow', $followed->profile->username))
        ->assertRedirect();
    $this->actingAs($follower)
        ->post(route('public-profile.follow', $followed->profile->username))
        ->assertRedirect();

    $notification = $followed->notifications()->sole();

    expect($notification->data['type'])
        ->toBe(InternalNotificationType::NewFollower->value)
        ->and($notification->data['target_url'])
        ->toBe(route(
            'public-profile.show',
            $follower->profile->username,
            absolute: false,
        ));
});

test('accepting a contact request notifies only newly created follow directions', function () {
    [$sender, $receiver] = notificationUsers();
    Follow::query()->create([
        'follower_id' => $sender->id,
        'followed_id' => $receiver->id,
    ]);
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    $this->actingAs($receiver)
        ->patch(route('contact-requests.accept', $contactRequest))
        ->assertRedirect();

    expect($sender->notifications()
        ->where('data->type', InternalNotificationType::NewFollower->value)
        ->count())->toBe(1)
        ->and($receiver->notifications()
            ->where('data->type', InternalNotificationType::NewFollower->value)
            ->count())->toBe(0);
});

test('a new message notifies the other participant', function () {
    [$sender, $receiver] = notificationUsers();
    $conversation = allowNotificationMessageExchange($sender, $receiver);

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Neue Nachricht',
        ])
        ->assertRedirect(route('messages.show', $conversation));

    $notification = $receiver->notifications()->sole();

    expect($notification->data['type'])
        ->toBe(InternalNotificationType::NewMessage->value)
        ->and($notification->data['actor_id'])->toBe($sender->id)
        ->and($notification->data['conversation_id'])->toBe($conversation->id)
        ->and($notification->data['message'])
        ->toBe('Neue Nachricht von Actor User.')
        ->and($notification->data['target_url'])
        ->toBe(route('messages.show', $conversation, absolute: false));
});

test('an unread message notification is reused per conversation', function () {
    [$sender, $receiver] = notificationUsers();
    $conversation = allowNotificationMessageExchange($sender, $receiver);

    foreach (['Erste Nachricht', 'Zweite Nachricht'] as $message) {
        $this->actingAs($sender)
            ->post(route('messages.store', $conversation), [
                'message' => $message,
            ])
            ->assertRedirect(route('messages.show', $conversation));
    }

    expect($receiver->unreadNotifications()->count())->toBe(1)
        ->and($receiver->notifications()->sole()->data['message'])
        ->toBe('Neue Nachricht von Actor User.');
});

test('a read message notification permits one new unread notification', function () {
    [$sender, $receiver] = notificationUsers();
    $conversation = allowNotificationMessageExchange($sender, $receiver);

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Erste Nachricht',
        ]);

    $receiver->notifications()->sole()->markAsRead();

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Zweite Nachricht',
        ]);

    expect($receiver->notifications()->count())->toBe(2)
        ->and($receiver->unreadNotifications()->count())->toBe(1);
});

test('notification page lists only the authenticated users notifications', function () {
    [$user, $otherUser] = notificationUsers();
    $user->notify(new InternalNotification(
        InternalNotificationType::NewFollower,
        'Eigene Benachrichtigung',
        'Eigene Nachricht',
        '/u/actor_user',
    ));
    $otherUser->notify(new InternalNotification(
        InternalNotificationType::NewMessage,
        'Fremde Benachrichtigung',
        'Fremde Nachricht',
        '/messages',
    ));

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notificationItems', 1)
            ->where(
                'notificationItems.0.title',
                'Diese Mitglieder folgen dir jetzt',
            )
            ->where('notificationItems.0.read_at', null)
            ->where('notificationItems.0.target_url', '/u/actor_user'),
        );
});

test('one message notification is presented as one message group', function () {
    [$sender, $receiver] = notificationUsers();
    $receiver->notify(new InternalNotification(
        InternalNotificationType::NewMessage,
        'Neue Nachricht',
        "{$sender->profile->display_name} hat dir eine Nachricht gesendet.",
        '/messages/10',
    ));

    $this->actingAs($receiver)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('notificationItems', 1)
            ->where('notificationItems.0.title', 'Actor User')
            ->where('notificationItems.0.message', '1 neue Nachricht')
            ->where('notificationItems.0.notification_count', 1)
            ->where('notificationItems.0.is_message_group', true)
            ->where('notificationItems.0.cta_label', 'Unterhaltung öffnen')
            ->where('notificationItems.0.visual_kind', 'message')
            ->where('notificationItems.0.target_url', '/messages/10'),
        );
});

test('message notification presents the sender profile photo', function () {
    [$sender, $receiver] = notificationUsers();
    $sender->profile->update([
        'profile_photo_path' => 'profile-photos/message-sender.webp',
    ]);
    $conversation = allowNotificationMessageExchange($sender, $receiver);

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Nachricht mit Profilbild',
        ]);

    $this->actingAs($receiver)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationItems.0.visual_kind', 'message')
            ->where('notificationItems.0.actor.display_name', 'Actor User')
            ->where(
                'notificationItems.0.actor.profile_photo_url',
                '/storage/profile-photos/message-sender.webp',
            )
            ->where('notificationItems.0.actor.initials', 'A'),
        );
});

test('message notification keeps initials fallback without a profile photo', function () {
    [$sender, $receiver] = notificationUsers();
    $conversation = allowNotificationMessageExchange($sender, $receiver);

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Nachricht ohne Profilbild',
        ]);

    $this->actingAs($receiver)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationItems.0.visual_kind', 'message')
            ->where('notificationItems.0.actor.profile_photo_url', null)
            ->where('notificationItems.0.actor.initials', 'A'),
        );
});

test('legacy message notification resolves the sender photo from its conversation', function () {
    [$sender, $receiver] = notificationUsers();
    $sender->profile->update([
        'profile_photo_path' => 'profile-photos/legacy-sender.webp',
    ]);
    $conversation = allowNotificationMessageExchange($sender, $receiver);
    $receiver->notify(new InternalNotification(
        InternalNotificationType::NewMessage,
        'Neue Nachricht',
        'Actor User hat dir eine Nachricht gesendet.',
        route('messages.show', $conversation, absolute: false),
    ));

    $this->actingAs($receiver)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationItems.0.actor.display_name', 'Actor User')
            ->where(
                'notificationItems.0.actor.profile_photo_url',
                '/storage/profile-photos/legacy-sender.webp',
            ),
        );
});

test('legacy accepted contact request notification resolves the actor photo without persisting an actor id', function () {
    [$sender, $receiver] = notificationUsers();
    $receiver->profile->update([
        'profile_photo_path' => 'profile-photos/legacy-contact.webp',
    ]);
    ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    $sender->notify(new InternalNotification(
        InternalNotificationType::ContactRequestAccepted,
        'Kontaktanfrage angenommen',
        'Recipient User hat deine Kontaktanfrage angenommen.',
        '/contact-requests/sent',
    ));
    $notification = $sender->notifications()->sole();
    $data = $notification->data;
    unset($data['actor_id'], $data['conversation_id']);
    $notification->forceFill(['data' => $data])->save();

    $this->actingAs($sender)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationItems.0.actor.display_name', 'Recipient User')
            ->where(
                'notificationItems.0.actor.profile_photo_url',
                '/storage/profile-photos/legacy-contact.webp',
            ),
        );

    expect($notification->fresh()->data)->not->toHaveKey('actor_id');
});

test('accepted contact request notification with an actor id keeps the existing actor resolution', function () {
    [$sender, $receiver] = notificationUsers();
    $receiver->profile->update([
        'profile_photo_path' => 'profile-photos/current-contact.webp',
    ]);
    $sender->notify(new InternalNotification(
        InternalNotificationType::ContactRequestAccepted,
        'Kontaktanfrage angenommen',
        'Recipient User hat deine Kontaktanfrage angenommen.',
        '/contact-requests/sent',
        $receiver->id,
    ));

    $this->actingAs($sender)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationItems.0.actor.display_name', 'Recipient User')
            ->where(
                'notificationItems.0.actor.profile_photo_url',
                '/storage/profile-photos/current-contact.webp',
            ),
        );

    expect($sender->notifications()->sole()->data['actor_id'])
        ->toBe($receiver->id);
});

test('legacy accepted contact request notification keeps the generic avatar when the actor is not uniquely resolvable', function (
    string $case,
) {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $actorName = 'Mehrdeutiger Kontakt';

    if ($case === 'ambiguous') {
        foreach (range(1, 2) as $index) {
            $receiver = User::factory()->create();
            createOnboardedProfile($receiver, [
                'display_name' => $actorName,
                'username' => "ambiguous_contact_{$index}",
            ]);
            ContactRequest::factory()
                ->for($sender, 'sender')
                ->for($receiver, 'receiver')
                ->create([
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]);
        }
    }

    $sender->notify(new InternalNotification(
        InternalNotificationType::ContactRequestAccepted,
        'Kontaktanfrage angenommen',
        "{$actorName} hat deine Kontaktanfrage angenommen.",
        '/contact-requests/sent',
    ));
    $notification = $sender->notifications()->sole();
    $data = $notification->data;
    unset($data['actor_id'], $data['conversation_id']);
    $notification->forceFill(['data' => $data])->save();

    $this->actingAs($sender)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationItems.0.title', $actorName)
            ->where('notificationItems.0.actor', null),
        );

    expect($notification->fresh()->data)->not->toHaveKey('actor_id');
})->with([
    'ambiguous actor' => ['ambiguous'],
    'missing actor' => ['missing'],
]);

test('five message notifications from one sender are presented as one group', function () {
    [$sender, $receiver] = notificationUsers();

    foreach (range(1, 5) as $index) {
        $receiver->notify(new InternalNotification(
            InternalNotificationType::NewMessage,
            'Neue Nachricht',
            "{$sender->profile->display_name} hat dir eine Nachricht gesendet.",
            '/messages/10',
        ));
    }

    $this->actingAs($receiver)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('notificationItems', 1)
            ->where('notificationItems.0.title', 'Actor User')
            ->where('notificationItems.0.message', '5 neue Nachrichten')
            ->where('notificationItems.0.notification_count', 5)
            ->where('notificationItems.0.is_message_group', true),
        );
});

test('message notifications from different senders remain separate groups', function () {
    [$senderA, $receiver] = notificationUsers();
    $senderB = User::factory()->create();
    createOnboardedProfile($senderB, [
        'display_name' => 'Second Actor',
        'username' => 'second_actor',
    ]);

    foreach ([
        [$senderA, '/messages/10'],
        [$senderB, '/messages/11'],
    ] as [$sender, $targetUrl]) {
        $receiver->notify(new InternalNotification(
            InternalNotificationType::NewMessage,
            'Neue Nachricht',
            "{$sender->profile->display_name} hat dir eine Nachricht gesendet.",
            $targetUrl,
        ));
    }

    $this->actingAs($receiver)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('notificationItems', 2)
            ->where('notificationItems.0.notification_count', 1)
            ->where('notificationItems.1.notification_count', 1)
            ->where('notificationItems.0.is_message_group', true)
            ->where('notificationItems.1.is_message_group', true),
        );
});

test('contact request notifications are presented as one group with actors', function () {
    [$sender, $receiver] = notificationUsers();
    $secondSender = User::factory()->create();
    createOnboardedProfile($secondSender, [
        'display_name' => 'Second Actor',
        'username' => 'second_actor',
    ]);

    foreach ([$sender, $secondSender] as $actor) {
        $receiver->notify(new InternalNotification(
            InternalNotificationType::ContactRequestReceived,
            'Neue Kontaktanfrage',
            "{$actor->profile->display_name} hat dir eine Kontaktanfrage gesendet.",
            '/contact-requests',
        ));
    }

    $this->actingAs($receiver)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('notificationItems', 1)
            ->where(
                'notificationItems.0.title',
                'Du hast neue Kontaktanfragen erhalten',
            )
            ->where('notificationItems.0.message', '2 offene Anfragen')
            ->where('notificationItems.0.notification_count', 2)
            ->where('notificationItems.0.is_activity_group', true)
            ->where(
                'notificationItems.0.cta_label',
                'Kontaktanfragen ansehen',
            )
            ->where(
                'notificationItems.0.visual_kind',
                'contact-requests',
            )
            ->where('notificationItems.0.actors', [
                'Actor User',
                'Second Actor',
            ])
            ->where('notificationItems.0.target_url', '/contact-requests'),
        );
});

test('follower notifications are presented as one group with actors', function () {
    [$follower, $followed] = notificationUsers();
    $secondFollower = User::factory()->create();
    createOnboardedProfile($secondFollower, [
        'display_name' => 'Second Follower',
        'username' => 'second_follower',
    ]);

    foreach ([$follower, $secondFollower] as $actor) {
        $followed->notify(new InternalNotification(
            InternalNotificationType::NewFollower,
            'Neuer Follower',
            "{$actor->profile->display_name} folgt dir jetzt.",
            "/u/{$actor->profile->username}",
        ));
    }

    $this->actingAs($followed)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('notificationItems', 1)
            ->where(
                'notificationItems.0.title',
                'Diese Mitglieder folgen dir jetzt',
            )
            ->where('notificationItems.0.message', '2 neue Follower')
            ->where('notificationItems.0.notification_count', 2)
            ->where('notificationItems.0.is_activity_group', true)
            ->where('notificationItems.0.cta_label', null)
            ->where('notificationItems.0.open_url', null)
            ->where('notificationItems.0.visual_kind', 'followers')
            ->where('notificationItems.0.actors', [
                'Actor User',
                'Second Follower',
            ])
            ->where('notificationItems.0.target_url', '/discover'),
        );
});

test('follower notification groups expose up to three newest actor previews', function () {
    $followed = User::factory()->create();
    createOnboardedProfile($followed);
    $followers = collect(range(1, 4))->map(function (int $index): User {
        $follower = User::factory()->create();
        createOnboardedProfile($follower, [
            'display_name' => "Follower {$index}",
            'profile_photo_path' => "profile-photos/follower-{$index}.webp",
            'username' => "follower_{$index}",
        ]);

        return $follower;
    });

    $baseTime = now()->subHour();

    foreach ($followers as $index => $follower) {
        $followed->notify(new InternalNotification(
            InternalNotificationType::NewFollower,
            'Neuer Follower',
            "{$follower->profile->display_name} folgt dir jetzt.",
            "/u/{$follower->profile->username}",
            $follower->id,
        ));
        $followed->notifications()
            ->where('data->actor_id', $follower->id)
            ->firstOrFail()
            ->forceFill([
                'created_at' => $baseTime->copy()->addMinutes($index),
            ])
            ->save();
    }

    $this->actingAs($followed)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notificationItems.0.actor_previews', 3)
            ->where(
                'notificationItems.0.actor_previews.0.display_name',
                'Follower 4',
            )
            ->where(
                'notificationItems.0.actor_previews.0.profile_photo_url',
                '/storage/profile-photos/follower-4.webp',
            )
            ->where(
                'notificationItems.0.actor_previews.1.display_name',
                'Follower 3',
            )
            ->where(
                'notificationItems.0.actor_previews.2.display_name',
                'Follower 2',
            ),
        );
});

test('activity group is unread when at least one notification is unread', function () {
    [$follower, $followed] = notificationUsers();

    foreach (range(1, 2) as $index) {
        $followed->notify(new InternalNotification(
            InternalNotificationType::NewFollower,
            'Neuer Follower',
            "{$follower->profile->display_name} folgt dir jetzt.",
            '/u/actor_user',
        ));
    }

    $followed->notifications()->latest()->firstOrFail()->markAsRead();

    $this->actingAs($followed)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationItems.0.read_at', null),
        );
});

test('activity group is read when all notifications are read', function () {
    [$sender, $receiver] = notificationUsers();

    foreach (range(1, 2) as $index) {
        $receiver->notify(new InternalNotification(
            InternalNotificationType::ContactRequestReceived,
            'Neue Kontaktanfrage',
            "{$sender->profile->display_name} hat dir eine Kontaktanfrage gesendet.",
            '/contact-requests',
        ));
    }

    $receiver->unreadNotifications()->update(['read_at' => now()]);

    $this->actingAs($receiver)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->whereNot('notificationItems.0.read_at', null),
        );
});

test('all notifications can be marked as read', function () {
    [$user] = notificationUsers();

    foreach (range(1, 2) as $index) {
        $user->notify(new InternalNotification(
            InternalNotificationType::NewFollower,
            "Benachrichtigung {$index}",
            'Nachricht',
            '/notifications',
        ));
    }

    $this->actingAs($user)
        ->patch(route('notifications.read-all'))
        ->assertRedirect(route('notifications.index'))
        ->assertSessionHas(
            'success',
            'Alle Benachrichtigungen wurden als gelesen markiert.',
        );

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($user->notifications()
            ->whereNull('read_at')
            ->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unreadCount', 0),
        );
});

test('opening a notification marks it as read and redirects to its target', function () {
    [$user, $actor] = notificationUsers();
    $user->notify(new InternalNotification(
        InternalNotificationType::ContactRequestAccepted,
        'Kontaktanfrage angenommen',
        'Actor User hat deine Kontaktanfrage angenommen.',
        '/contact-requests/sent',
        $actor->id,
    ));
    $notification = $user->notifications()->sole();

    $this->actingAs($user)
        ->get(route('notifications.open', $notification))
        ->assertRedirect('/contact-requests/sent');

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($user->unreadNotifications()->count())->toBe(0);
});

test('users cannot open another users notification', function () {
    [$owner, $otherUser] = notificationUsers();
    $owner->notify(new InternalNotification(
        InternalNotificationType::NewFollower,
        'Neuer Follower',
        'Actor User folgt dir jetzt.',
        '/discover',
    ));
    $notification = $owner->notifications()->sole();

    $this->actingAs($otherUser)
        ->get(route('notifications.open', $notification))
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('notification items are sorted newest first and contain actor presentation data', function () {
    [$actor, $recipient] = notificationUsers();
    $actor->profile->update([
        'profile_photo_path' => 'profile-photos/actor.webp',
    ]);
    $recipient->notify(new InternalNotification(
        InternalNotificationType::ContactRequestAccepted,
        'Ältere Benachrichtigung',
        'Actor User hat deine Kontaktanfrage angenommen.',
        '/contact-requests/sent',
        $actor->id,
    ));
    $older = $recipient->notifications()->sole();
    $older->forceFill(['created_at' => now()->subDay()])->save();

    $recipient->notify(new InternalNotification(
        InternalNotificationType::ContactRequestDeclined,
        'Neuere Benachrichtigung',
        'Actor User hat deine Kontaktanfrage abgelehnt.',
        '/contact-requests/sent',
        $actor->id,
    ));

    $this->actingAs($recipient)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notificationItems', 2)
            ->where('notificationItems.0.title', 'Actor User')
            ->where(
                'notificationItems.0.message',
                'hat deine Kontaktanfrage abgelehnt',
            )
            ->where('notificationItems.0.cta_label', 'Details ansehen')
            ->where('notificationItems.0.actor.display_name', 'Actor User')
            ->where('notificationItems.0.actor.initials', 'A')
            ->where(
                'notificationItems.0.actor.profile_photo_url',
                '/storage/profile-photos/actor.webp',
            )
            ->where('notificationItems.1.title', 'Actor User')
            ->where(
                'notificationItems.1.message',
                'hat deine Kontaktanfrage angenommen',
            )
            ->where('notificationItems.1.cta_label', 'Kontakt ansehen'),
        );
});

test('notification center provides clickable accessible cards and polished ux', function () {
    $page = file_get_contents(
        resource_path('js/pages/Notifications/Index.vue'),
    );

    expect($page)
        ->toContain('Noch keine Benachrichtigungen')
        ->toContain('Hier erscheinen neue Aktivitäten aus deiner Community.')
        ->toContain('formatContactRelativeTime(')
        ->toContain('formatContactRelativeTimeTitle(')
        ->toContain(':title="')
        ->toContain('border-primary/60')
        ->toContain('<ProfileAvatar')
        ->toContain('<Users')
        ->toContain('<Handshake')
        ->toContain(":is=\"notification.open_url ? Link : 'div'\"")
        ->toContain(':href="notification.open_url ?? undefined"')
        ->toContain('@keydown.space.prevent="openNotification(notification)"')
        ->toContain('focus-visible:ring-[3px]')
        ->toContain('motion-reduce:transition-none')
        ->toContain('notification.cta_label')
        ->toContain('overflow-x-hidden')
        ->toContain('Alle als gelesen markieren')
        ->toContain('Wird als gelesen markiert …')
        ->toContain(':aria-busy="processing"')
        ->toContain(':disabled="processing"')
        ->toContain('class="px-2.5 py-1"');
});

test('follower groups use actor previews with an icon fallback while contact request groups keep their icon', function () {
    $page = file_get_contents(
        resource_path('js/pages/Notifications/Index.vue'),
    );

    expect($page)
        ->toContain('notification.actor_previews.length > 0')
        ->toContain(') in notification.actor_previews"')
        ->toContain(':photo-url="actor.profile_photo_url"')
        ->toContain("notification.visual_kind === 'followers'")
        ->toContain('<Users class="size-6 sm:size-7"')
        ->toContain("'contact-requests'")
        ->toContain('<Handshake class="size-6 sm:size-7"')
        ->toContain('v-else-if="notification.actor"')
        ->toContain(':photo-url="')
        ->toContain('notification.actor.profile_photo_url');
});

test('follower notification groups render without a misleading profile action', function () {
    $page = file_get_contents(
        resource_path('js/pages/Notifications/Index.vue'),
    );

    expect($page)
        ->toContain('v-if="notification.cta_label"')
        ->toContain("notification.open_url ? Link : 'div'")
        ->toContain('notification.open_url ?? undefined');
});

test('single notification types use personal texts and contextual ctas', function (
    InternalNotificationType $type,
    string $storedTitle,
    string $storedMessage,
    string $expectedTitle,
    string $expectedMessage,
    string $expectedCta,
) {
    [$actor, $recipient] = notificationUsers();
    $recipient->notify(new InternalNotification(
        $type,
        $storedTitle,
        $storedMessage,
        '/notifications',
        $actor->id,
    ));

    $this->actingAs($recipient)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationItems.0.title', $expectedTitle)
            ->where('notificationItems.0.message', $expectedMessage)
            ->where('notificationItems.0.cta_label', $expectedCta),
        );
})->with([
    'follower' => [
        InternalNotificationType::NewFollower,
        'Neuer Follower',
        'Actor User folgt dir jetzt.',
        'Diese Mitglieder folgen dir jetzt',
        '1 neuer Follower',
        'Profile ansehen',
    ],
    'contact request' => [
        InternalNotificationType::ContactRequestReceived,
        'Neue Kontaktanfrage',
        'Actor User hat dir eine Kontaktanfrage gesendet.',
        'Du hast neue Kontaktanfragen erhalten',
        '1 offene Anfrage',
        'Kontaktanfragen ansehen',
    ],
    'accepted request' => [
        InternalNotificationType::ContactRequestAccepted,
        'Kontaktanfrage angenommen',
        'Actor User hat deine Kontaktanfrage angenommen.',
        'Actor User',
        'hat deine Kontaktanfrage angenommen',
        'Kontakt ansehen',
    ],
    'declined request' => [
        InternalNotificationType::ContactRequestDeclined,
        'Kontaktanfrage abgelehnt',
        'Actor User hat deine Kontaktanfrage abgelehnt.',
        'Actor User',
        'hat deine Kontaktanfrage abgelehnt',
        'Details ansehen',
    ],
]);

test('the shared unread notification count and navigation entry are available', function () {
    [$user] = notificationUsers();
    $user->notify(new InternalNotification(
        InternalNotificationType::NewFollower,
        'Ungelesen',
        'Nachricht',
        '/notifications',
    ));
    $readNotification = $user->notifications()->firstOrFail();
    $readNotification->markAsRead();
    $user->notify(new InternalNotification(
        InternalNotificationType::NewMessage,
        'Ungelesen',
        'Nachricht',
        '/messages',
    ));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unreadCount', 1),
        );

    $navigation = file_get_contents(
        resource_path('js/config/navigation/app-navigation.ts'),
    );

    expect($navigation)
        ->toContain("title: 'Benachrichtigungen'")
        ->toContain("href: '/notifications'")
        ->toContain('unreadNotificationsCount');
});

test('guests cannot access notification routes', function (string $routeName) {
    $response = match ($routeName) {
        'notifications.read-all' => $this->patch(route($routeName)),
        'notifications.open' => $this->get(route($routeName, 'notification-id')),
        default => $this->get(route($routeName)),
    };

    $response->assertRedirect(route('login'));
})->with([
    'notifications.index',
    'notifications.open',
    'notifications.read-all',
]);
