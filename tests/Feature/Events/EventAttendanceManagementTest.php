<?php

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('event owner sees active attendees and pending attendance requests on detail page', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $activeUser = User::factory()->create(['name' => 'Fallback Active']);
    createOnboardedProfile($activeUser, [
        'display_name' => 'Aktive Teilnehmerin',
        'username' => 'aktive_teilnehmerin',
    ]);
    $pendingUser = User::factory()->create(['name' => 'Fallback Pending']);
    createOnboardedProfile($pendingUser, [
        'display_name' => 'Pending Person',
        'username' => 'pending_person',
    ]);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'owner-sees-event-requests',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    $activeAttendance = EventAttendee::factory()
        ->for($event)
        ->for($activeUser)
        ->create([
            'joined_at' => '2026-07-10 18:00:00',
        ]);
    $pendingAttendance = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($pendingUser)
        ->create([
            'created_at' => '2026-07-09 12:00:00',
        ]);

    $this->actingAs($owner)
        ->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('event.can_manage_attendance_requests', true)
            ->has('event.attendees_preview', 1)
            ->where('event.attendees_preview.0.id', $activeAttendance->id)
            ->where('event.attendees_preview.0.user.name', 'Aktive Teilnehmerin')
            ->where('event.attendees_preview.0.user.username', 'aktive_teilnehmerin')
            ->where('event.attendees_preview.0.user.profile_url', route('public-profile.show', 'aktive_teilnehmerin'))
            ->where('event.attendees_preview.0.status_label', 'Teilnehmer')
            ->has('event.pending_requests', 1)
            ->where('event.pending_requests.0.id', $pendingAttendance->id)
            ->where('event.pending_requests.0.user.name', 'Pending Person')
            ->where('event.pending_requests.0.user.username', 'pending_person')
            ->where('event.pending_requests.0.user.profile_url', route('public-profile.show', 'pending_person'))
            ->where('event.pending_requests.0.accept_url', route('events.attendance.accept', [
                'event' => $event->slug,
                'attendee' => $pendingAttendance->id,
            ]))
            ->where('event.pending_requests.0.decline_url', route('events.attendance.decline', [
                'event' => $event->slug,
                'attendee' => $pendingAttendance->id,
            ])),
        );
});

test('event owner sees empty states for attendees and pending requests', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'empty-attendance-management',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);

    $this->actingAs($owner)
        ->get(route('events.show', $event->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.can_manage_attendance_requests', true)
            ->has('event.attendees_preview', 0)
            ->has('event.pending_requests', 0),
        );
});

test('non managers do not receive pending attendance request data or management urls', function (string $state) {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $pendingUser = User::factory()->create();
    createOnboardedProfile($pendingUser);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => "hidden-requests-{$state}",
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($pendingUser)
        ->create();

    if ($state === 'active') {
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
        ->get(route('events.show', $event->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.can_manage_attendance_requests', false)
            ->has('event.pending_requests', 0),
        );
})->with([
    'active',
    'pending',
    'none',
]);

test('platform admin can see and manage attendance requests', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    createOnboardedProfile($admin);
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requester = User::factory()->create();
    createOnboardedProfile($requester);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'admin-manages-event-request',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    $attendance = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($requester)
        ->create();

    $this->actingAs($admin)
        ->get(route('events.show', $event->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.can_manage_attendance_requests', true)
            ->has('event.pending_requests', 1),
        );

    $this->actingAs($admin)
        ->patch(route('events.attendance.accept', [$event->slug, $attendance->id]))
        ->assertSessionHas('success', 'Teilnahme-Anfrage angenommen.')
        ->assertRedirect(route('events.show', $event->slug));
});

test('owner can accept a pending attendance request', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requester = User::factory()->create();
    createOnboardedProfile($requester);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'accept-pending-attendance',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    $attendance = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($requester)
        ->create();

    $this->actingAs($owner)
        ->patch(route('events.attendance.accept', [$event->slug, $attendance->id]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Teilnahme-Anfrage angenommen.')
        ->assertRedirect(route('events.show', $event->slug));

    $attendance->refresh();

    expect($attendance)
        ->status->toBe(EventAttendee::STATUS_ACTIVE)
        ->joined_at->not->toBeNull();

    $this->actingAs($owner)
        ->get(route('events.show', $event->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.attendee_count', 1)
            ->has('event.attendees_preview', 1)
            ->where('event.attendees_preview.0.id', $attendance->id)
            ->has('event.pending_requests', 0),
        );
});

test('owner can decline a pending attendance request', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requester = User::factory()->create();
    createOnboardedProfile($requester);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'decline-pending-attendance',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    $attendance = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($requester)
        ->create();

    $this->actingAs($owner)
        ->delete(route('events.attendance.decline', [$event->slug, $attendance->id]))
        ->assertSessionHas('success', 'Teilnahme-Anfrage abgelehnt.')
        ->assertRedirect(route('events.show', $event->slug));

    expect(EventAttendee::query()->whereKey($attendance->id)->exists())->toBeFalse();

    $this->actingAs($owner)
        ->get(route('events.show', $event->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.attendee_count', 0)
            ->has('event.pending_requests', 0),
        );
});

test('non owners cannot accept or decline attendance requests', function (string $method, string $routeName) {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $requester = User::factory()->create();
    createOnboardedProfile($requester);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => "forbidden-{$method}-attendance",
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    $attendance = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($requester)
        ->create();

    $this->actingAs($viewer)
        ->{$method}(route($routeName, [$event->slug, $attendance->id]))
        ->assertForbidden();

    expect($attendance->refresh()->status)->toBe(EventAttendee::STATUS_PENDING);
})->with([
    'accept' => ['patch', 'events.attendance.accept'],
    'decline' => ['delete', 'events.attendance.decline'],
]);

test('foreign event attendance pair cannot be accepted or declined', function (string $method, string $routeName) {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requester = User::factory()->create();
    createOnboardedProfile($requester);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => "foreign-pair-{$method}",
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    $otherEvent = Event::factory()->for($owner, 'owner')->create([
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    $attendance = EventAttendee::factory()
        ->pending()
        ->for($otherEvent)
        ->for($requester)
        ->create();

    $this->actingAs($owner)
        ->{$method}(route($routeName, [$event->slug, $attendance->id]))
        ->assertNotFound();

    expect($attendance->refresh()->status)->toBe(EventAttendee::STATUS_PENDING);
})->with([
    'accept' => ['patch', 'events.attendance.accept'],
    'decline' => ['delete', 'events.attendance.decline'],
]);

test('active attendance and cancelled events cannot be processed as requests', function (array $eventOverrides, string $attendanceStatus, string $method, string $routeName) {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requester = User::factory()->create();
    createOnboardedProfile($requester);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => "unprocessable-{$method}-{$attendanceStatus}",
        'visibility' => Event::VISIBILITY_REQUEST,
        ...$eventOverrides,
    ]);
    $factory = EventAttendee::factory()
        ->for($event)
        ->for($requester);
    $attendance = $attendanceStatus === EventAttendee::STATUS_PENDING
        ? $factory->pending()->create()
        : $factory->create();

    $this->actingAs($owner)
        ->{$method}(route($routeName, [$event->slug, $attendance->id]))
        ->assertNotFound();
})->with([
    'active accept' => [[], EventAttendee::STATUS_ACTIVE, 'patch', 'events.attendance.accept'],
    'active decline' => [[], EventAttendee::STATUS_ACTIVE, 'delete', 'events.attendance.decline'],
    'cancelled accept' => [['status' => Event::STATUS_CANCELLED], EventAttendee::STATUS_PENDING, 'patch', 'events.attendance.accept'],
    'cancelled decline' => [['status' => Event::STATUS_CANCELLED], EventAttendee::STATUS_PENDING, 'delete', 'events.attendance.decline'],
]);

test('public events and owner attendance cannot be processed as attendance requests', function (array $eventOverrides, bool $requestFromOwner) {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requester = $requestFromOwner ? $owner : User::factory()->create();
    if (! $requestFromOwner) {
        createOnboardedProfile($requester);
    }
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'invalid-request-processing',
        'visibility' => Event::VISIBILITY_REQUEST,
        ...$eventOverrides,
    ]);
    $attendance = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($requester)
        ->create();

    $this->actingAs($owner)
        ->patch(route('events.attendance.accept', [$event->slug, $attendance->id]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('events.attendance.decline', [$event->slug, $attendance->id]))
        ->assertNotFound();
})->with([
    'public event' => [['visibility' => Event::VISIBILITY_PUBLIC], false],
    'owner attendance' => [[], true],
]);

test('full event blocks accepting request and keeps pending attendance', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $activeUser = User::factory()->create();
    createOnboardedProfile($activeUser);
    $requester = User::factory()->create();
    createOnboardedProfile($requester);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'full-event-blocks-accept',
        'visibility' => Event::VISIBILITY_REQUEST,
        'max_attendees' => 1,
    ]);
    EventAttendee::factory()
        ->for($event)
        ->for($activeUser)
        ->create();
    $pending = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($requester)
        ->create();

    $this->actingAs($owner)
        ->patch(route('events.attendance.accept', [$event->slug, $pending->id]))
        ->assertSessionHasErrors(['attendance' => 'Dieses Event ist bereits ausgebucht.'])
        ->assertRedirect(route('events.show', $event->slug));

    expect($pending->refresh()->status)->toBe(EventAttendee::STATUS_PENDING);
});

test('event index counts only active attendees and updates after request acceptance', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $requester = User::factory()->create();
    createOnboardedProfile($requester);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'index-counts-accepted-attendance',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    $pending = EventAttendee::factory()
        ->pending()
        ->for($event)
        ->for($requester)
        ->create();

    $this->actingAs($viewer)
        ->get(route('events.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.data.0.attendee_count', 0)
            ->missing('events.data.0.accept_url')
            ->missing('events.data.0.decline_url')
            ->missing('events.data.0.pending_requests'),
        );

    $this->actingAs($owner)
        ->patch(route('events.attendance.accept', [$event->slug, $pending->id]))
        ->assertSessionHas('success', 'Teilnahme-Anfrage angenommen.');

    $this->actingAs($viewer)
        ->get(route('events.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.data.0.attendee_count', 1)
            ->missing('events.data.0.accept_url')
            ->missing('events.data.0.decline_url')
            ->missing('events.data.0.pending_requests'),
        );
});

test('event detail vue contains attendance management copy and no separate attendee page link', function () {
    $page = file_get_contents(resource_path('js/pages/Events/Show.vue'));

    expect($page)
        ->toContain('Teilnehmer')
        ->toContain('Ein Überblick über die aktiven Teilnehmer dieses')
        ->toContain('Events.')
        ->toContain('Aktuell nimmt noch niemand an diesem Event teil.')
        ->toContain('Teilnahme-Anfragen')
        ->toContain('Diese Mitglieder möchten an deinem Event teilnehmen.')
        ->toContain('Aktuell liegen keine Teilnahme-Anfragen vor.')
        ->toContain('Wird angenommen...')
        ->toContain('Teilnahme-Anfrage ablehnen?')
        ->toContain('Diese Anfrage wird abgelehnt')
        ->toContain('Profil ansehen')
        ->not->toContain('/attendees');
});
