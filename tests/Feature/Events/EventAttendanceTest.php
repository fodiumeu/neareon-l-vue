<?php

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('onboarded user can join a public event directly', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => 'public-join-event',
        'visibility' => Event::VISIBILITY_PUBLIC,
        'status' => Event::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
        ->post(route('events.attendance.store', $event->slug))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Du nimmst am Event teil.')
        ->assertRedirect(route('events.show', $event->slug));

    $attendance = EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($attendance)
        ->status->toBe(EventAttendee::STATUS_ACTIVE)
        ->joined_at->not->toBeNull();
});

test('joining a public event twice does not create duplicate attendance', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => 'duplicate-public-join',
        'visibility' => Event::VISIBILITY_PUBLIC,
    ]);
    EventAttendee::factory()
        ->for($event)
        ->for($user)
        ->create();

    $this->actingAs($user)
        ->post(route('events.attendance.store', $event->slug))
        ->assertSessionHas('success', 'Du nimmst bereits am Event teil.')
        ->assertRedirect(route('events.show', $event->slug));

    expect(EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->count())->toBe(1);
});

test('pending attendance on a public event is promoted to active when joining', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => 'pending-promoted-public',
        'visibility' => Event::VISIBILITY_PUBLIC,
    ]);
    EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($user)
        ->create();

    $this->actingAs($user)
        ->post(route('events.attendance.store', $event->slug))
        ->assertSessionHas('success', 'Du nimmst am Event teil.');

    $attendance = EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($attendance)
        ->status->toBe(EventAttendee::STATUS_ACTIVE)
        ->joined_at->not->toBeNull();
});

test('onboarded user can request participation for a request event', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => 'request-attendance-event',
        'visibility' => Event::VISIBILITY_REQUEST,
        'status' => Event::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
        ->post(route('events.attendance.store', $event->slug))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Deine Teilnahme-Anfrage wurde gesendet.')
        ->assertRedirect(route('events.show', $event->slug));

    $attendance = EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($attendance)
        ->status->toBe(EventAttendee::STATUS_PENDING)
        ->joined_at->toBeNull();
});

test('requesting participation twice does not create duplicate attendance', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => 'duplicate-request-attendance',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($user)
        ->create();

    $this->actingAs($user)
        ->post(route('events.attendance.store', $event->slug))
        ->assertSessionHas('success', 'Deine Teilnahme-Anfrage wartet bereits auf Bestätigung.');

    expect(EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->count())->toBe(1);
});

test('active participant can leave an event and pending user can withdraw request', function (string $status, string $message) {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => "destroy-attendance-{$status}",
    ]);
    $factory = EventAttendee::factory()
        ->for($event)
        ->for($user);

    if ($status === EventAttendee::STATUS_PENDING) {
        $factory->pending()->create();
    } else {
        $factory->create();
    }

    $this->actingAs($user)
        ->delete(route('events.attendance.destroy', $event->slug))
        ->assertSessionHas('success', $message)
        ->assertRedirect(route('events.show', $event->slug));

    expect(EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();
})->with([
    'active attendance' => [
        EventAttendee::STATUS_ACTIVE,
        'Du nimmst nicht mehr am Event teil.',
    ],
    'pending request' => [
        EventAttendee::STATUS_PENDING,
        'Deine Teilnahme-Anfrage wurde zurückgezogen.',
    ],
]);

test('user cannot delete another users attendance', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $other = User::factory()->create();
    $event = Event::factory()->create([
        'slug' => 'foreign-attendance-destroy',
    ]);
    EventAttendee::factory()
        ->for($event)
        ->for($other)
        ->create();

    $this->actingAs($user)
        ->delete(route('events.attendance.destroy', $event->slug))
        ->assertNotFound();

    expect(EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $other->id)
        ->exists())->toBeTrue();
});

test('event owner cannot join own event and sees organizer state', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'owner-cannot-join-event',
        'visibility' => Event::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($owner)
        ->post(route('events.attendance.store', $event->slug))
        ->assertForbidden();

    expect(EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $owner->id)
        ->exists())->toBeFalse();

    $this->actingAs($owner)
        ->get(route('events.show', $event->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.viewer_event_role', 'owner')
            ->where('event.viewer_attendance_status', null)
            ->where('event.can_join', false)
            ->where('event.can_request', false)
            ->where('event.can_leave', false)
            ->where('event.attendance_store_url', null)
            ->where('event.attendance_destroy_url', null),
        );
});

test('guests and non onboarded users cannot use attendance routes directly', function () {
    $event = Event::factory()->create([
        'slug' => 'auth-attendance-event',
    ]);
    $user = User::factory()->create();

    $this->post(route('events.attendance.store', $event->slug))
        ->assertRedirect(route('login'));

    $this->delete(route('events.attendance.destroy', $event->slug))
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->post(route('events.attendance.store', $event->slug))
        ->assertRedirect(route('onboarding.details'));
});

test('cancelled event and invalid visibility cannot be joined', function (array $overrides) {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => 'blocked-attendance-event',
        ...$overrides,
    ]);

    $this->actingAs($user)
        ->post(route('events.attendance.store', $event->slug))
        ->assertNotFound();

    expect(EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();
})->with([
    'cancelled' => [[
        'status' => Event::STATUS_CANCELLED,
    ]],
    'invalid visibility' => [[
        'visibility' => 'private',
    ]],
]);

test('past public and request events cannot receive new attendance', function (string $visibility) {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => "past-{$visibility}-attendance",
        'visibility' => $visibility,
        'status' => Event::STATUS_ACTIVE,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->post(route('events.attendance.store', $event->slug))
        ->assertSessionHasErrors(['attendance' => 'Dieses Event ist bereits vorbei.'])
        ->assertRedirect(route('events.show', $event->slug));

    expect(EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();
})->with([
    Event::VISIBILITY_PUBLIC,
    Event::VISIBILITY_REQUEST,
]);

test('full public and request events reject new attendance but keep existing active attendee visible', function (string $visibility) {
    $existing = User::factory()->create();
    createOnboardedProfile($existing);
    $newUser = User::factory()->create();
    createOnboardedProfile($newUser);
    $event = Event::factory()->create([
        'slug' => "full-{$visibility}-event",
        'visibility' => $visibility,
        'max_attendees' => 1,
    ]);
    EventAttendee::factory()
        ->for($event)
        ->for($existing)
        ->create();

    $this->actingAs($newUser)
        ->post(route('events.attendance.store', $event->slug))
        ->assertSessionHasErrors(['attendance' => 'Dieses Event ist bereits ausgebucht.'])
        ->assertRedirect(route('events.show', $event->slug));

    expect(EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $newUser->id)
        ->exists())->toBeFalse();

    $this->actingAs($existing)
        ->get(route('events.show', $event->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.is_full', true)
            ->where('event.viewer_attendance_status', EventAttendee::STATUS_ACTIVE)
            ->where('event.viewer_event_role', 'attendee')
            ->where('event.can_join', false)
            ->where('event.can_request', false)
            ->where('event.can_leave', true)
            ->where('event.attendance_store_url', null)
            ->where('event.attendance_destroy_url', route('events.attendance.destroy', $event->slug)),
        );
})->with([
    Event::VISIBILITY_PUBLIC,
    Event::VISIBILITY_REQUEST,
]);

test('events without attendee limit can be joined regardless of active attendee count', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => 'unlimited-attendance-event',
        'visibility' => Event::VISIBILITY_PUBLIC,
        'max_attendees' => null,
    ]);
    EventAttendee::factory()
        ->count(3)
        ->for($event)
        ->create();

    $this->actingAs($user)
        ->post(route('events.attendance.store', $event->slug))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Du nimmst am Event teil.');

    expect(EventAttendee::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->where('status', EventAttendee::STATUS_ACTIVE)
        ->exists())->toBeTrue();
});

test('event index and detail expose attendance props according to viewer state', function (string $status, string $role, ?string $storeUrl, ?string $destroyUrl) {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => "attendance-props-{$status}",
        'visibility' => Event::VISIBILITY_PUBLIC,
    ]);

    if ($status === EventAttendee::STATUS_ACTIVE) {
        EventAttendee::factory()
            ->for($event)
            ->for($user)
            ->create();
    } elseif ($status === EventAttendee::STATUS_PENDING) {
        EventAttendee::factory()
            ->pending()
            ->for($event)
            ->for($user)
            ->create();
    }

    $expectedStoreUrl = $storeUrl === 'store'
        ? route('events.attendance.store', $event->slug)
        : null;
    $expectedDestroyUrl = $destroyUrl === 'destroy'
        ? route('events.attendance.destroy', $event->slug)
        : null;

    $this->actingAs($user)
        ->get(route('events.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.data.0.viewer_attendance_status', $status === 'none' ? null : $status)
            ->where('events.data.0.viewer_event_role', $role)
            ->where('events.data.0.attendance_store_url', $expectedStoreUrl)
            ->where('events.data.0.attendance_destroy_url', $expectedDestroyUrl)
            ->missing('events.data.0.edit_url')
            ->missing('events.data.0.attendees_url'),
        );

    $this->actingAs($user)
        ->get(route('events.show', $event->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.viewer_attendance_status', $status === 'none' ? null : $status)
            ->where('event.viewer_event_role', $role)
            ->where('event.attendance_store_url', $expectedStoreUrl)
            ->where('event.attendance_destroy_url', $expectedDestroyUrl),
        );
})->with([
    'none' => ['none', 'none', 'store', null],
    'active' => [EventAttendee::STATUS_ACTIVE, 'attendee', null, 'destroy'],
    'pending' => [EventAttendee::STATUS_PENDING, 'pending', null, 'destroy'],
]);

test('past event detail keeps page visible but hides attendance actions', function (string $status, string $role) {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => "past-detail-{$status}",
        'visibility' => Event::VISIBILITY_PUBLIC,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
    ]);

    if ($status === EventAttendee::STATUS_ACTIVE) {
        EventAttendee::factory()
            ->for($event)
            ->for($user)
            ->create();
    } elseif ($status === EventAttendee::STATUS_PENDING) {
        EventAttendee::factory()
            ->pending()
            ->for($event)
            ->for($user)
            ->create();
    }

    $this->actingAs($user)
        ->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.is_past', true)
            ->where('event.viewer_event_role', $role)
            ->where('event.can_join', false)
            ->where('event.can_request', false)
            ->where('event.can_leave', false)
            ->where('event.attendance_store_url', null)
            ->where('event.attendance_destroy_url', null),
        );
})->with([
    'none' => ['none', 'none'],
    'active' => [EventAttendee::STATUS_ACTIVE, 'attendee'],
    'pending' => [EventAttendee::STATUS_PENDING, 'pending'],
]);

test('event attendance vue pages contain expected action copy and dialogs', function () {
    $index = file_get_contents(resource_path('js/pages/Events/Index.vue'));
    $show = file_get_contents(resource_path('js/pages/Events/Show.vue'));

    expect($index)
        ->toContain('Teilnehmen')
        ->toContain('Teilnahme anfragen')
        ->toContain('Teilnehmer')
        ->toContain('Anfrage gesendet')
        ->toContain('Veranstalter')
        ->toContain('Ausgebucht')
        ->not->toContain('Anfrage zurückziehen')
        ->not->toContain('Teilnahme absagen')
        ->not->toContain('edit_url')
        ->and($show)
        ->toContain('Teilnahme absagen?')
        ->toContain('Du wirst aus der Teilnehmerliste')
        ->toContain('dieses Events entfernt.')
        ->toContain('Anfrage zurückziehen?')
        ->toContain('Deine Teilnahme-Anfrage wird')
        ->toContain('zurückgezogen.')
        ->toContain('Du bist Veranstalter dieses Events.')
        ->toContain('Du kannst direkt an diesem Event teilnehmen.')
        ->toContain('Sende eine Teilnahme-Anfrage, um an diesem Event teilzunehmen.')
        ->toContain('Dieses Event ist bereits vorbei.')
        ->toContain('Dieses Event ist bereits ausgebucht.');
});
