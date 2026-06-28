<?php

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests and non onboarded users cannot cancel events', function () {
    $event = Event::factory()->create([
        'slug' => 'auth-cancel-event',
    ]);
    $user = User::factory()->create();

    $this->patch(route('events.cancel', $event->slug))
        ->assertRedirect(route('login'));

    $this->patch(route('events.restore', $event->slug))
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->patch(route('events.cancel', $event->slug))
        ->assertRedirect(route('onboarding.details'));

    $this->actingAs($user)
        ->patch(route('events.restore', $event->slug))
        ->assertRedirect(route('onboarding.details'));

    expect($event->refresh()->status)->toBe(Event::STATUS_ACTIVE);
});

test('event owner can cancel an active event without deleting attendees or notifications', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $activeUser = User::factory()->create();
    createOnboardedProfile($activeUser);
    $pendingUser = User::factory()->create();
    createOnboardedProfile($pendingUser);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'owner-cancel-event',
        'visibility' => Event::VISIBILITY_REQUEST,
        'status' => Event::STATUS_ACTIVE,
    ]);
    $activeAttendance = EventAttendee::factory()
        ->for($event)
        ->for($activeUser)
        ->create();
    $pendingAttendance = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($pendingUser)
        ->create();

    $this->actingAs($owner)
        ->patch(route('events.cancel', $event->slug))
        ->assertSessionHas('success', 'Event wurde abgesagt.')
        ->assertRedirect(route('events.show', $event->slug));

    expect($event->refresh()->status)->toBe(Event::STATUS_CANCELLED)
        ->and(EventAttendee::query()->whereKey($activeAttendance->id)->exists())->toBeTrue()
        ->and(EventAttendee::query()->whereKey($pendingAttendance->id)->exists())->toBeTrue()
        ->and($owner->notifications()->count())->toBe(0)
        ->and($activeUser->notifications()->count())->toBe(0)
        ->and($pendingUser->notifications()->count())->toBe(0);
});

test('platform admins and owners can cancel events', function (string $roleFactory) {
    $user = User::factory()->{$roleFactory}()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => "platform-{$roleFactory}-cancel-event",
    ]);

    $this->actingAs($user)
        ->patch(route('events.cancel', $event->slug))
        ->assertSessionHas('success', 'Event wurde abgesagt.')
        ->assertRedirect(route('events.show', $event->slug));

    expect($event->refresh()->status)->toBe(Event::STATUS_CANCELLED);
})->with([
    'admin' => ['admin'],
    'owner role' => ['owner'],
]);

test('non owners participants pending users and unrelated users cannot cancel events', function (string $state) {
    $eventOwner = User::factory()->create();
    createOnboardedProfile($eventOwner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $event = Event::factory()->for($eventOwner, 'owner')->create([
        'slug' => "forbidden-{$state}-cancel-event",
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);

    if ($state === 'participant') {
        EventAttendee::factory()
            ->for($event)
            ->for($viewer)
            ->create();
    } elseif ($state === 'pending') {
        EventAttendee::factory()
            ->pending()
            ->for($event)
            ->for($viewer)
            ->create();
    }

    $this->actingAs($viewer)
        ->patch(route('events.cancel', $event->slug))
        ->assertForbidden();

    expect($event->refresh()->status)->toBe(Event::STATUS_ACTIVE);
})->with([
    'participant',
    'pending',
    'unrelated',
]);

test('already cancelled events can be cancelled again safely', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'already-cancelled-event',
        'status' => Event::STATUS_CANCELLED,
    ]);

    $this->actingAs($owner)
        ->patch(route('events.cancel', $event->slug))
        ->assertSessionHas('success', 'Event ist bereits abgesagt.')
        ->assertRedirect(route('events.show', $event->slug));

    expect($event->refresh()->status)->toBe(Event::STATUS_CANCELLED);
});

test('event owner can restore a cancelled event without deleting attendees or notifications', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $activeUser = User::factory()->create();
    createOnboardedProfile($activeUser);
    $pendingUser = User::factory()->create();
    createOnboardedProfile($pendingUser);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'owner-restore-event',
        'visibility' => Event::VISIBILITY_REQUEST,
        'status' => Event::STATUS_CANCELLED,
    ]);
    $activeAttendance = EventAttendee::factory()
        ->for($event)
        ->for($activeUser)
        ->create();
    $pendingAttendance = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($pendingUser)
        ->create();

    $this->actingAs($owner)
        ->patch(route('events.restore', $event->slug))
        ->assertSessionHas('success', 'Event wurde wieder aktiviert.')
        ->assertRedirect(route('events.show', $event->slug));

    expect($event->refresh()->status)->toBe(Event::STATUS_ACTIVE)
        ->and(EventAttendee::query()->whereKey($activeAttendance->id)->exists())->toBeTrue()
        ->and(EventAttendee::query()->whereKey($pendingAttendance->id)->exists())->toBeTrue()
        ->and($owner->notifications()->count())->toBe(0)
        ->and($activeUser->notifications()->count())->toBe(0)
        ->and($pendingUser->notifications()->count())->toBe(0);
});

test('platform admins and owners can restore events', function (string $roleFactory) {
    $user = User::factory()->{$roleFactory}()->create();
    createOnboardedProfile($user);
    $event = Event::factory()->create([
        'slug' => "platform-{$roleFactory}-restore-event",
        'status' => Event::STATUS_CANCELLED,
    ]);

    $this->actingAs($user)
        ->patch(route('events.restore', $event->slug))
        ->assertSessionHas('success', 'Event wurde wieder aktiviert.')
        ->assertRedirect(route('events.show', $event->slug));

    expect($event->refresh()->status)->toBe(Event::STATUS_ACTIVE);
})->with([
    'admin' => ['admin'],
    'owner role' => ['owner'],
]);

test('non owners participants pending users and unrelated users cannot restore events', function (string $state) {
    $eventOwner = User::factory()->create();
    createOnboardedProfile($eventOwner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $event = Event::factory()->for($eventOwner, 'owner')->create([
        'slug' => "forbidden-{$state}-restore-event",
        'visibility' => Event::VISIBILITY_REQUEST,
        'status' => Event::STATUS_CANCELLED,
    ]);

    if ($state === 'participant') {
        EventAttendee::factory()
            ->for($event)
            ->for($viewer)
            ->create();
    } elseif ($state === 'pending') {
        EventAttendee::factory()
            ->pending()
            ->for($event)
            ->for($viewer)
            ->create();
    }

    $this->actingAs($viewer)
        ->patch(route('events.restore', $event->slug))
        ->assertForbidden();

    expect($event->refresh()->status)->toBe(Event::STATUS_CANCELLED);
})->with([
    'participant',
    'pending',
    'unrelated',
]);

test('already active events can be restored again safely', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'already-active-restore-event',
        'status' => Event::STATUS_ACTIVE,
    ]);

    $this->actingAs($owner)
        ->patch(route('events.restore', $event->slug))
        ->assertSessionHas('success', 'Event ist bereits aktiv.')
        ->assertRedirect(route('events.show', $event->slug));

    expect($event->refresh()->status)->toBe(Event::STATUS_ACTIVE);
});

test('restored events appear in discover search filters and my events as active', function (string $visibility) {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $event = Event::factory()->for($owner, 'owner')->create([
        'title' => "Restored {$visibility} Event",
        'slug' => "restored-{$visibility}-event",
        'region' => 'Berlin',
        'visibility' => $visibility,
        'status' => Event::STATUS_CANCELLED,
    ]);

    $this->actingAs($owner)
        ->patch(route('events.restore', $event->slug));

    $this->actingAs($owner)
        ->get(route('events.index', [
            'q' => "Restored {$visibility}",
            'region' => 'Berlin',
            'visibility' => $visibility,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.id', $event->id)
            ->where('events.data.0.status', Event::STATUS_ACTIVE)
            ->where('filterOptions.regions.0', 'Berlin'),
        );

    $this->actingAs($owner)
        ->get(route('events.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/MyEvents')
            ->has('owned_events', 1)
            ->where('owned_events.0.id', $event->id)
            ->where('owned_events.0.status', Event::STATUS_ACTIVE)
            ->where('owned_events.0.status_label', 'Aktiv'),
        );
})->with([
    Event::VISIBILITY_PUBLIC,
    Event::VISIBILITY_REQUEST,
]);

test('cancelled events stay out of event discover including search filters and options', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    Event::factory()->create([
        'title' => 'Aktives Hamburg Event',
        'slug' => 'active-hamburg-event',
        'region' => 'Hamburg',
        'visibility' => Event::VISIBILITY_PUBLIC,
        'status' => Event::STATUS_ACTIVE,
    ]);
    Event::factory()->create([
        'title' => 'Abgesagtes Berlin Event',
        'slug' => 'cancelled-berlin-event',
        'region' => 'Berlin',
        'visibility' => Event::VISIBILITY_REQUEST,
        'status' => Event::STATUS_CANCELLED,
    ]);

    $this->actingAs($viewer)
        ->get(route('events.index', ['q' => 'Abgesagtes', 'region' => 'Berlin']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 0)
            ->where('filterOptions.regions.0', 'Hamburg')
            ->missing('filterOptions.regions.1'),
        );
});

test('own cancelled events remain visible in my events created area', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $event = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Abgesagtes eigenes Event',
        'slug' => 'cancelled-owned-my-event',
        'status' => Event::STATUS_CANCELLED,
    ]);

    $this->actingAs($owner)
        ->get(route('events.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/MyEvents')
            ->has('owned_events', 1)
            ->where('owned_events.0.id', $event->id)
            ->where('owned_events.0.status', Event::STATUS_CANCELLED)
            ->where('owned_events.0.status_label', 'Abgesagt')
            ->where('owned_events.0.my_events_show_url', route('events.show', [
                'event' => $event->slug,
                'from' => 'my-events',
            ]))
            ->where('owned_events.0.edit_url', route('events.edit', $event->slug)),
        );
});

test('cancelled event detail exposes cancelled state without attendance actions or request management', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requester = User::factory()->create();
    createOnboardedProfile($requester);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'cancelled-detail-state',
        'visibility' => Event::VISIBILITY_REQUEST,
        'status' => Event::STATUS_CANCELLED,
    ]);
    EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($requester)
        ->create();

    $this->actingAs($owner)
        ->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.status', Event::STATUS_CANCELLED)
            ->where('event.status_label', 'Abgesagt')
            ->where('event.can_edit', true)
            ->where('event.can_cancel', false)
            ->where('event.cancel_url', null)
            ->where('event.can_restore', true)
            ->where('event.restore_url', route('events.restore', $event->slug))
            ->where('event.can_join', false)
            ->where('event.can_request', false)
            ->where('event.can_leave', false)
            ->where('event.attendance_store_url', null)
            ->where('event.attendance_destroy_url', null)
            ->where('event.can_manage_attendance_requests', false)
            ->has('event.pending_requests', 0),
        );
});

test('join request and accept remain blocked for cancelled events', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $requester = User::factory()->create();
    createOnboardedProfile($requester);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'blocked-cancelled-attendance-actions',
        'visibility' => Event::VISIBILITY_REQUEST,
        'status' => Event::STATUS_CANCELLED,
    ]);
    $attendance = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($requester)
        ->create();

    $this->actingAs($viewer)
        ->post(route('events.attendance.store', $event->slug))
        ->assertNotFound();

    $this->actingAs($owner)
        ->patch(route('events.attendance.accept', [$event->slug, $attendance->id]))
        ->assertNotFound();

    expect($attendance->refresh()->status)->toBe(EventAttendee::STATUS_PENDING)
        ->and(EventAttendee::query()
            ->where('event_id', $event->id)
            ->where('user_id', $viewer->id)
            ->exists())->toBeFalse();
});

test('event cancel vue contains the confirmation dialog and cancelled state copy', function () {
    $page = file_get_contents(resource_path('js/pages/Events/Show.vue'));

    expect($page)
        ->toContain('Event absagen')
        ->toContain('Event absagen?')
        ->toContain('Dieses Event wird als abgesagt markiert')
        ->toContain('erscheint nicht mehr unter „Events')
        ->toContain('entdecken“.')
        ->toContain('Wird abgesagt...')
        ->toContain('Event wieder aktivieren')
        ->toContain('Event wieder aktivieren?')
        ->toContain('Dieses Event wird wieder als aktiv markiert')
        ->toContain('kann erneut unter „Events entdecken“')
        ->toContain('Wird aktiviert...')
        ->toContain('Dieses Event wurde abgesagt.')
        ->toContain("event.status === 'active'")
        ->toContain('event.can_manage_attendance_requests');
});
