<?php

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\InterestOption;
use App\Models\User;
use Illuminate\Database\QueryException;

test('event can be created with an owner', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();

    expect($event->owner->is($owner))->toBeTrue()
        ->and($owner->ownedEvents()->first()->is($event))->toBeTrue();
});

test('event can use one managed interest option as its main category', function () {
    $category = InterestOption::query()->create([
        'slug' => 'event-category-music',
        'label' => 'Musik',
        'sort_order' => 10,
        'is_active' => true,
    ]);
    $event = Event::factory()->create([
        'category_interest_option_id' => $category->id,
    ]);

    expect($event->refresh()->category->is($category))->toBeTrue()
        ->and($category->events()->first()->is($event))->toBeTrue();
});

test('event can exist without a main category', function () {
    $event = Event::factory()->create([
        'category_interest_option_id' => null,
    ]);

    expect($event->refresh()->category)->toBeNull();
});

test('event stores regional location fields', function () {
    $event = Event::factory()->create([
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
    ]);

    expect($event->refresh())
        ->region->toBe('Hamburg')
        ->postal_code->toBe('20095')
        ->country_code->toBe('DE');
});

test('event stores start time and optional end time', function () {
    $startsAt = now()->addWeek()->startOfMinute();
    $endsAt = $startsAt->copy()->addHours(2);
    $eventWithEnd = Event::factory()->create([
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);
    $eventWithoutEnd = Event::factory()->create([
        'starts_at' => $startsAt->copy()->addDay(),
        'ends_at' => null,
    ]);

    expect($eventWithEnd->refresh()->starts_at->equalTo($startsAt))->toBeTrue()
        ->and($eventWithEnd->ends_at->equalTo($endsAt))->toBeTrue()
        ->and($eventWithoutEnd->refresh()->starts_at)->not->toBeNull()
        ->and($eventWithoutEnd->ends_at)->toBeNull();
});

test('event attendee relationships expose attendees and their users', function () {
    $event = Event::factory()->create();
    $user = User::factory()->create();
    $attendance = EventAttendee::factory()
        ->for($event)
        ->for($user)
        ->create();

    expect($event->attendees()->first()->is($attendance))->toBeTrue()
        ->and($attendance->event->is($event))->toBeTrue()
        ->and($attendance->user->is($user))->toBeTrue()
        ->and($user->eventAttendances()->first()->is($attendance))->toBeTrue();
});

test('active and pending attendees are filtered separately', function () {
    $event = Event::factory()->create();
    $activeAttendance = EventAttendee::factory()
        ->for($event)
        ->create([
            'status' => EventAttendee::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
    $pendingAttendance = EventAttendee::factory()
        ->for($event)
        ->pending()
        ->create();

    expect($event->activeAttendees()->pluck('id')->all())
        ->toBe([$activeAttendance->id])
        ->and($event->pendingAttendees()->pluck('id')->all())
        ->toBe([$pendingAttendance->id]);
});

test('a user can only have one attendance record per event', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    EventAttendee::factory()
        ->for($event)
        ->for($user)
        ->create();

    expect(fn () => EventAttendee::factory()
        ->for($event)
        ->for($user)
        ->create())->toThrow(QueryException::class);
});

test('a user can attend different events', function () {
    $user = User::factory()->create();
    $firstEvent = Event::factory()->create();
    $secondEvent = Event::factory()->create();

    EventAttendee::factory()
        ->for($firstEvent)
        ->for($user)
        ->create();
    EventAttendee::factory()
        ->for($secondEvent)
        ->for($user)
        ->create();

    expect($user->eventAttendances()->count())->toBe(2);
});

test('visible for discover scope returns only active public or request events', function () {
    $publicEvent = Event::factory()->create([
        'visibility' => Event::VISIBILITY_PUBLIC,
        'status' => Event::STATUS_ACTIVE,
    ]);
    $requestEvent = Event::factory()->create([
        'visibility' => Event::VISIBILITY_REQUEST,
        'status' => Event::STATUS_ACTIVE,
    ]);
    Event::factory()->create([
        'visibility' => Event::VISIBILITY_PUBLIC,
        'status' => Event::STATUS_CANCELLED,
    ]);

    $visibleEventIds = Event::query()
        ->visibleForDiscover()
        ->pluck('id')
        ->all();

    expect($visibleEventIds)
        ->toContain($publicEvent->id)
        ->toContain($requestEvent->id)
        ->toHaveCount(2);
});

test('event creator is not automatically an attendee', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();

    expect($event->attendees()->count())->toBe(0)
        ->and($owner->eventAttendances()->count())->toBe(0);
});
