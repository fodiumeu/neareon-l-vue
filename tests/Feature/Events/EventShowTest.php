<?php

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\InterestOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open the event detail page', function () {
    $event = Event::factory()->create([
        'slug' => 'guest-show-event',
    ]);

    $this->get(route('events.show', $event->slug))
        ->assertRedirect(route('login'));
});

test('onboarded users can see active public and request events', function (string $visibility) {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $event = Event::factory()->create([
        'slug' => "visible-{$visibility}-event",
        'visibility' => $visibility,
        'status' => Event::STATUS_ACTIVE,
    ]);

    $this->actingAs($viewer)
        ->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('event.id', $event->id)
            ->where('event.visibility', $visibility)
            ->where('event.can_edit', false)
            ->where('event.edit_url', null)
            ->where('event.back_url', route('events.index'))
            ->where('event.back_label', 'Zurück zu Events'),
        );
})->with([
    Event::VISIBILITY_PUBLIC,
    Event::VISIBILITY_REQUEST,
]);

test('event owner can see own event with edit action', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'owner-show-event',
    ]);

    $this->actingAs($owner)
        ->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('event.id', $event->id)
            ->where('event.can_edit', true)
            ->where('event.edit_url', route('events.edit', $event->slug)),
        );
});

test('event detail uses safe my events backlink context', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $event = Event::factory()->create([
        'slug' => 'my-events-backlink-context',
        'status' => Event::STATUS_ACTIVE,
    ]);

    $this->actingAs($viewer)
        ->get(route('events.show', [
            'event' => $event->slug,
            'from' => 'my-events',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('event.back_url', route('events.mine'))
            ->where('event.back_label', 'Zurück zu Meine Events'),
        );
});

test('event detail ignores invalid backlink context', function (string $from) {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $event = Event::factory()->create([
        'slug' => 'invalid-event-backlink-context',
        'status' => Event::STATUS_ACTIVE,
    ]);

    $this->actingAs($viewer)
        ->get(route('events.show', [
            'event' => $event->slug,
            'from' => $from,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('event.back_url', route('events.index'))
            ->where('event.back_label', 'Zurück zu Events'),
        );
})->with([
    'unknown',
    'https://example.com',
    '//example.com',
]);

test('event detail exposes event information without management urls for non owners', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create([
        'name' => 'Event Owner',
    ]);
    createOnboardedProfile($owner, [
        'display_name' => 'Owner Anzeige',
        'username' => 'owner_event',
        'profile_photo_path' => 'profile-photos/event-owner.webp',
    ]);
    $category = InterestOption::query()->create([
        'slug' => 'event-show-category',
        'label' => 'Kultur',
        'is_active' => true,
    ]);
    $event = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Sommerabend im Park',
        'slug' => 'sommerabend-im-park',
        'description' => 'Ein ruhiger Abend für die Community.',
        'starts_at' => '2026-07-10 18:00',
        'ends_at' => '2026-07-10 20:00',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
        'category_interest_option_id' => $category->id,
        'visibility' => Event::VISIBILITY_PUBLIC,
        'status' => Event::STATUS_ACTIVE,
        'max_attendees' => 50,
    ]);
    EventAttendee::factory()
        ->for($event)
        ->create();

    $this->actingAs($viewer)
        ->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('event.title', 'Sommerabend im Park')
            ->where('event.description', 'Ein ruhiger Abend für die Community.')
            ->where('event.starts_at', fn (string $value): bool => str_starts_with($value, '2026-07-10T18:00:00'))
            ->where('event.ends_at', fn (string $value): bool => str_starts_with($value, '2026-07-10T20:00:00'))
            ->where('event.region', 'Hamburg')
            ->where('event.postal_code', '20095')
            ->where('event.country_code', 'DE')
            ->where('event.visibility_label', 'Öffentlich')
            ->where('event.status_label', 'Aktiv')
            ->where('event.category.label', 'Kultur')
            ->where('event.max_attendees', 50)
            ->where('event.owner.name', 'Owner Anzeige')
            ->where('event.owner.username', 'owner_event')
            ->where('event.owner.profile_photo_url', '/storage/profile-photos/event-owner.webp')
            ->where('event.owner.profile_url', route('public-profile.show', 'owner_event'))
            ->where('event.attendee_count', 1)
            ->where('event.can_edit', false)
            ->where('event.edit_url', null)
            ->missing('event.join_url')
            ->missing('event.request_url')
            ->missing('event.attendees_url'),
        );
});

test('event detail exposes owner avatar fallback data without profile photo', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create([
        'name' => 'Fallback Owner',
    ]);
    createOnboardedProfile($owner, [
        'display_name' => 'Fallback Anzeige',
        'username' => 'fallback_owner',
        'profile_photo_path' => null,
    ]);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'event-owner-avatar-fallback',
    ]);

    $this->actingAs($viewer)
        ->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('event.owner.id', $owner->id)
            ->where('event.owner.name', 'Fallback Anzeige')
            ->where('event.owner.username', 'fallback_owner')
            ->where('event.owner.profile_photo_url', null)
            ->where('event.owner.profile_url', route('public-profile.show', 'fallback_owner')),
        );
});

test('event detail shows empty description state in the vue page', function () {
    $page = file_get_contents(resource_path('js/pages/Events/Show.vue'));

    expect($page)
        ->toContain('Dieses Event hat noch keine Beschreibung.')
        ->toContain('event.owner.profile_photo_url')
        ->toContain('initials(event.owner.name)')
        ->toContain('event.owner.profile_url')
        ->toContain('Teilnahme')
        ->toContain(':href="event.back_url"')
        ->toContain('event.back_label')
        ->not->toContain('name="join"')
        ->not->toContain('join_url')
        ->not->toContain('request_url');
});

test('non owner does not receive edit link while platform admin does', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);
    createOnboardedProfile($admin);
    $event = Event::factory()->create([
        'slug' => 'admin-show-edit-link',
    ]);

    $this->actingAs($viewer)
        ->get(route('events.show', $event->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.can_edit', false)
            ->where('event.edit_url', null),
        );

    $this->actingAs($admin)
        ->get(route('events.show', $event->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.can_edit', true)
            ->where('event.edit_url', route('events.edit', $event->slug)),
        );
});

test('foreign cancelled event is hidden from normal users but visible to owner and admin', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);
    createOnboardedProfile($admin);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'cancelled-hidden-event',
        'status' => Event::STATUS_CANCELLED,
    ]);

    $this->actingAs($viewer)
        ->get(route('events.show', $event->slug))
        ->assertNotFound();

    $this->actingAs($owner)
        ->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.status', Event::STATUS_CANCELLED)
            ->where('event.status_label', 'Abgesagt'),
        );

    $this->actingAs($admin)
        ->get(route('events.show', $event->slug))
        ->assertOk();
});
