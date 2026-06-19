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
        ->and($notification->data['target_url'])
        ->toBe(route('messages.show', $conversation, absolute: false));
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
            ->where('notificationItems.0.title', 'Eigene Benachrichtigung')
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
            ->where('notificationItems.0.target_url', '/messages/10'),
        );
});

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

test('contact request notifications remain separate entries', function () {
    [$sender, $receiver] = notificationUsers();

    foreach (range(1, 2) as $index) {
        $receiver->notify(new InternalNotification(
            InternalNotificationType::ContactRequestReceived,
            'Neue Kontaktanfrage',
            "{$sender->profile->display_name} hat dir eine Kontaktanfrage gesendet.",
            '/contact-requests',
        ));
    }

    $this->actingAs($receiver)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('notificationItems', 2)
            ->where('notificationItems.0.is_message_group', false)
            ->where('notificationItems.1.is_message_group', false)
            ->where('notificationItems.0.notification_count', 1)
            ->where('notificationItems.1.notification_count', 1),
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
    $response = $routeName === 'notifications.read-all'
        ? $this->patch(route($routeName))
        : $this->get(route($routeName));

    $response->assertRedirect(route('login'));
})->with([
    'notifications.index',
    'notifications.read-all',
]);
