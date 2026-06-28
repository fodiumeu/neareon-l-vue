<?php

use App\Enums\InternalNotificationType;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function eventNotificationUsers(): array
{
    $actor = User::factory()->create();
    createOnboardedProfile($actor, [
        'display_name' => 'Event Actor',
        'username' => 'event_actor',
    ]);
    $owner = User::factory()->create();
    createOnboardedProfile($owner, [
        'display_name' => 'Event Owner',
        'username' => 'event_owner',
    ]);

    return [$actor, $owner];
}

test('new event attendance request notifies the event owner once', function () {
    [$actor, $owner] = eventNotificationUsers();
    $event = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Community Abend',
        'slug' => 'community-abend',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);

    $this->actingAs($actor)
        ->post(route('events.attendance.store', $event->slug))
        ->assertSessionHas('success', 'Deine Teilnahme-Anfrage wurde gesendet.');
    $this->actingAs($actor)
        ->post(route('events.attendance.store', $event->slug))
        ->assertSessionHas('success', 'Deine Teilnahme-Anfrage wartet bereits auf Bestätigung.');

    $notification = $owner->notifications()->sole();

    expect($notification->data['type'])
        ->toBe(InternalNotificationType::EventAttendanceRequestReceived->value)
        ->and($notification->data['title'])->toBe('Neue Teilnahme-Anfrage')
        ->and($notification->data['message'])
        ->toBe('Event Actor möchte an deinem Event Community Abend teilnehmen.')
        ->and($notification->data['target_url'])
        ->toBe(route('events.show', $event->slug, absolute: false))
        ->and($notification->data['actor_id'])->toBe($actor->id)
        ->and($notification->data['event_id'])->toBe($event->id)
        ->and($notification->data['event_name'])->toBe('Community Abend')
        ->and($notification->data['event_slug'])->toBe('community-abend');
});

test('event attendance request payload keeps only internal event data', function () {
    [$actor, $owner] = eventNotificationUsers();
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'safe-event-payload',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);

    $this->actingAs($actor)
        ->post(route('events.attendance.store', $event->slug));

    $data = $owner->notifications()->sole()->data;

    expect($data['target_url'])
        ->toStartWith('/')
        ->not->toStartWith('//')
        ->and($data)->toHaveKeys([
            'event_id',
            'event_name',
            'event_slug',
            'actor_id',
            'target_url',
        ])
        ->and($data)->not->toHaveKey('email')
        ->and($data)->not->toHaveKey('profile')
        ->and($data)->not->toHaveKey('invite_token');
});

test('public event join notifies owner once and pending request does not create joined notification', function () {
    [$actor, $owner] = eventNotificationUsers();
    $publicEvent = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Offenes Event',
        'slug' => 'offenes-event',
        'visibility' => Event::VISIBILITY_PUBLIC,
    ]);
    $requestEvent = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'pending-is-not-joined',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);

    $this->actingAs($actor)
        ->post(route('events.attendance.store', $publicEvent->slug))
        ->assertSessionHas('success', 'Du nimmst am Event teil.');
    $this->actingAs($actor)
        ->post(route('events.attendance.store', $publicEvent->slug))
        ->assertSessionHas('success', 'Du nimmst bereits am Event teil.');
    $this->actingAs($actor)
        ->post(route('events.attendance.store', $requestEvent->slug));

    expect($owner->notifications()
        ->where('data->type', InternalNotificationType::EventAttendeeJoined->value)
        ->count())->toBe(1);

    $notification = $owner->notifications()
        ->where('data->type', InternalNotificationType::EventAttendeeJoined->value)
        ->firstOrFail();

    expect($notification->data['message'])
        ->toBe('Event Actor nimmt an deinem Event Offenes Event teil.')
        ->and($notification->data['target_url'])
        ->toBe(route('events.show', $publicEvent->slug, absolute: false));
});

test('leaving an event or withdrawing a request does not create event notifications', function () {
    [$actor, $owner] = eventNotificationUsers();
    $publicEvent = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'leave-notification-event',
        'visibility' => Event::VISIBILITY_PUBLIC,
    ]);
    $requestEvent = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'withdraw-notification-event',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    EventAttendee::factory()
        ->for($publicEvent)
        ->for($actor)
        ->create();
    EventAttendee::factory()
        ->pending()
        ->for($requestEvent)
        ->for($actor)
        ->create();

    $this->actingAs($actor)
        ->delete(route('events.attendance.destroy', $publicEvent->slug));
    $this->actingAs($actor)
        ->delete(route('events.attendance.destroy', $requestEvent->slug));

    expect($owner->notifications()->count())->toBe(0)
        ->and($actor->notifications()->count())->toBe(0);
});

test('accepting an attendance request notifies the requester and blocked accepts do not notify', function () {
    [$actor, $owner] = eventNotificationUsers();
    $event = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Anfrage Event',
        'slug' => 'accepted-request-event',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    $attendance = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($actor)
        ->create();

    $this->actingAs($owner)
        ->patch(route('events.attendance.accept', [$event->slug, $attendance->id]))
        ->assertSessionHas('success', 'Teilnahme-Anfrage angenommen.');

    $notification = $actor->notifications()->sole();

    expect($notification->data['type'])
        ->toBe(InternalNotificationType::EventAttendanceRequestAccepted->value)
        ->and($notification->data['message'])
        ->toBe('Deine Anfrage für Anfrage Event wurde angenommen.')
        ->and($notification->data['target_url'])
        ->toBe(route('events.show', $event->slug, absolute: false))
        ->and($notification->data['actor_id'])->toBe($owner->id);

    $fullEvent = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'full-accepted-notification-block',
        'visibility' => Event::VISIBILITY_REQUEST,
        'max_attendees' => 1,
    ]);
    EventAttendee::factory()->for($fullEvent)->create();
    $blocked = EventAttendee::factory()
        ->pending()
        ->for($fullEvent)
        ->for($actor)
        ->create();

    $this->actingAs($owner)
        ->patch(route('events.attendance.accept', [$fullEvent->slug, $blocked->id]))
        ->assertSessionHasErrors(['attendance']);

    expect($actor->notifications()
        ->where('data->type', InternalNotificationType::EventAttendanceRequestAccepted->value)
        ->count())->toBe(1);
});

test('declining an attendance request notifies requester before deleting attendance', function () {
    [$actor, $owner] = eventNotificationUsers();
    $event = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Declined Event',
        'slug' => 'declined-event',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    $attendance = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($actor)
        ->create();
    $attendanceId = $attendance->id;

    $this->actingAs($owner)
        ->delete(route('events.attendance.decline', [$event->slug, $attendance->id]))
        ->assertSessionHas('success', 'Teilnahme-Anfrage abgelehnt.');

    $notification = $actor->notifications()->sole();

    expect(EventAttendee::query()->whereKey($attendanceId)->exists())->toBeFalse()
        ->and($notification->data['type'])
        ->toBe(InternalNotificationType::EventAttendanceRequestDeclined->value)
        ->and($notification->data['message'])
        ->toBe('Deine Anfrage für Declined Event wurde nicht angenommen.')
        ->and($notification->data['target_url'])
        ->toBe(route('events.index', absolute: false))
        ->and($notification->data['actor_id'])->toBe($owner->id);
});

test('failed event attendance actions do not create notifications', function (array $eventOverrides, string $action) {
    [$actor, $owner] = eventNotificationUsers();
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => "failed-event-notification-{$action}",
        'visibility' => Event::VISIBILITY_REQUEST,
        ...$eventOverrides,
    ]);

    if ($action === 'store') {
        $this->actingAs($actor)
            ->post(route('events.attendance.store', $event->slug));
    } else {
        $attendance = EventAttendee::factory()
            ->pending()
            ->for($event)
            ->for($actor)
            ->create();

        $this->actingAs($owner)
            ->{$action}(route(
                $action === 'patch'
                    ? 'events.attendance.accept'
                    : 'events.attendance.decline',
                [$event->slug, $attendance->id],
            ));
    }

    expect($actor->notifications()->count())->toBe(0)
        ->and($owner->notifications()->count())->toBe(0);
})->with([
    'cancelled request store' => [['status' => Event::STATUS_CANCELLED], 'store'],
    'cancelled accept' => [['status' => Event::STATUS_CANCELLED], 'patch'],
    'cancelled decline' => [['status' => Event::STATUS_CANCELLED], 'delete'],
]);

test('notification page renders event notification ctas', function (
    InternalNotificationType $type,
    string $expectedCta,
    string $expectedTarget,
    string $expectedTitle,
    string $expectedActor,
) {
    [$actor, $owner] = eventNotificationUsers();
    $event = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Rendered Event',
        'slug' => 'rendered-event',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);

    match ($type) {
        InternalNotificationType::EventAttendanceRequestReceived => $this
            ->actingAs($actor)
            ->post(route('events.attendance.store', $event->slug)),
        InternalNotificationType::EventAttendeeJoined => $this
            ->actingAs($actor)
            ->post(route('events.attendance.store', tap($event)->update([
                'visibility' => Event::VISIBILITY_PUBLIC,
            ]) ? $event->slug : $event->slug)),
        InternalNotificationType::EventAttendanceRequestAccepted => $this
            ->actingAs($owner)
            ->patch(route('events.attendance.accept', [
                $event->slug,
                EventAttendee::factory()
                    ->pending()
                    ->for($event)
                    ->for($actor)
                    ->create()
                    ->id,
            ])),
        InternalNotificationType::EventAttendanceRequestDeclined => $this
            ->actingAs($owner)
            ->delete(route('events.attendance.decline', [
                $event->slug,
                EventAttendee::factory()
                    ->pending()
                    ->for($event)
                    ->for($actor)
                    ->create()
                    ->id,
            ])),
        default => throw new InvalidArgumentException('Unsupported type'),
    };

    $recipient = in_array($type, [
        InternalNotificationType::EventAttendanceRequestReceived,
        InternalNotificationType::EventAttendeeJoined,
    ], true)
        ? $owner
        : $actor;

    $this->actingAs($recipient)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notificationItems', 1)
            ->where('notificationItems.0.type', $type->value)
            ->where('notificationItems.0.title', $expectedTitle)
            ->where('notificationItems.0.cta_label', $expectedCta)
            ->where('notificationItems.0.target_url', $expectedTarget)
            ->where('notificationItems.0.actor.display_name', $expectedActor),
        );
})->with([
    'request received' => [
        InternalNotificationType::EventAttendanceRequestReceived,
        'Event öffnen',
        '/events/rendered-event',
        'Neue Teilnahme-Anfrage',
        'Event Actor',
    ],
    'request accepted' => [
        InternalNotificationType::EventAttendanceRequestAccepted,
        'Event öffnen',
        '/events/rendered-event',
        'Teilnahme-Anfrage angenommen',
        'Event Owner',
    ],
    'request declined' => [
        InternalNotificationType::EventAttendanceRequestDeclined,
        'Events entdecken',
        '/events',
        'Teilnahme-Anfrage abgelehnt',
        'Event Owner',
    ],
    'public join' => [
        InternalNotificationType::EventAttendeeJoined,
        'Event öffnen',
        '/events/rendered-event',
        'Neuer Event-Teilnehmer',
        'Event Actor',
    ],
]);
