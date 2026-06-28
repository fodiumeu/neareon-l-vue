<?php

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\InterestOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login from my events', function () {
    $this->get(route('events.mine'))
        ->assertRedirect(route('login'));
});

test('non onboarded users are redirected by the existing onboarding middleware from my events', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('events.mine'))
        ->assertRedirect(route('onboarding.details'));
});

test('onboarded users can open my events with empty states', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->get(route('events.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/MyEvents')
            ->has('owned_events', 0)
            ->has('attending_events', 0)
            ->has('pending_events', 0),
        );
});

test('my events shows owned active and cancelled events but no foreign owned events', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $other = User::factory()->create();
    createOnboardedProfile($other);
    $category = InterestOption::query()->create([
        'slug' => 'my-events-category',
        'label' => 'Kultur',
        'is_active' => true,
    ]);

    $laterOwned = Event::factory()->for($viewer, 'owner')->create([
        'title' => 'Späteres eigenes Event',
        'slug' => 'spaeteres-eigenes-event',
        'starts_at' => now()->addDays(12),
        'category_interest_option_id' => $category->id,
        'visibility' => Event::VISIBILITY_REQUEST,
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'max_attendees' => 20,
    ]);
    $earlierOwned = Event::factory()->for($viewer, 'owner')->create([
        'title' => 'Früheres eigenes Event',
        'slug' => 'frueheres-eigenes-event',
        'starts_at' => now()->addDays(3),
        'status' => Event::STATUS_CANCELLED,
    ]);
    Event::factory()->for($other, 'owner')->create([
        'title' => 'Fremdes Event',
        'slug' => 'fremdes-event',
        'starts_at' => now()->addDays(1),
    ]);
    EventAttendee::factory()->for($laterOwned)->create();

    $this->actingAs($viewer)
        ->get(route('events.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/MyEvents')
            ->has('owned_events', 2)
            ->where('owned_events.0.id', $earlierOwned->id)
            ->where('owned_events.0.title', 'Früheres eigenes Event')
            ->where('owned_events.0.status', Event::STATUS_CANCELLED)
            ->where('owned_events.0.status_label', 'Abgesagt')
            ->where('owned_events.0.viewer_state', 'owner')
            ->where('owned_events.0.my_events_show_url', route('events.show', [
                'event' => $earlierOwned->slug,
                'from' => 'my-events',
            ]))
            ->where('owned_events.0.edit_url', route('events.edit', $earlierOwned->slug))
            ->where('owned_events.1.id', $laterOwned->id)
            ->where('owned_events.1.category.label', 'Kultur')
            ->where('owned_events.1.visibility_label', 'Anfrage')
            ->where('owned_events.1.region', 'Hamburg')
            ->where('owned_events.1.postal_code', '20095')
            ->where('owned_events.1.active_attendees_count', 1)
            ->missing('owned_events.2'),
        );
});

test('my events separates active attendances and pending requests', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $other = User::factory()->create();
    createOnboardedProfile($other);

    $attendingEvent = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Aktive Teilnahme',
        'slug' => 'aktive-teilnahme',
        'starts_at' => now()->addDays(4),
        'visibility' => Event::VISIBILITY_PUBLIC,
    ]);
    $pendingEvent = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Offene Anfrage',
        'slug' => 'offene-anfrage',
        'starts_at' => now()->addDays(5),
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    $foreignAttendanceEvent = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Andere Teilnahme',
        'slug' => 'andere-teilnahme',
    ]);
    $cancelledAttendanceEvent = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Abgesagte Teilnahme',
        'slug' => 'abgesagte-teilnahme',
        'status' => Event::STATUS_CANCELLED,
    ]);

    EventAttendee::factory()
        ->for($attendingEvent)
        ->for($viewer)
        ->create(['status' => EventAttendee::STATUS_ACTIVE]);
    EventAttendee::factory()
        ->pending()
        ->for($pendingEvent)
        ->for($viewer)
        ->create();
    EventAttendee::factory()
        ->for($foreignAttendanceEvent)
        ->for($other)
        ->create();
    EventAttendee::factory()
        ->for($cancelledAttendanceEvent)
        ->for($viewer)
        ->create(['status' => EventAttendee::STATUS_ACTIVE]);

    $this->actingAs($viewer)
        ->get(route('events.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/MyEvents')
            ->has('attending_events', 1)
            ->where('attending_events.0.id', $attendingEvent->id)
            ->where('attending_events.0.viewer_state', 'active')
            ->where('attending_events.0.show_url', route('events.show', $attendingEvent->slug))
            ->where('attending_events.0.my_events_show_url', route('events.show', [
                'event' => $attendingEvent->slug,
                'from' => 'my-events',
            ]))
            ->where('attending_events.0.edit_url', null)
            ->has('pending_events', 1)
            ->where('pending_events.0.id', $pendingEvent->id)
            ->where('pending_events.0.viewer_state', 'pending')
            ->where('pending_events.0.show_url', route('events.show', $pendingEvent->slug))
            ->where('pending_events.0.my_events_show_url', route('events.show', [
                'event' => $pendingEvent->slug,
                'from' => 'my-events',
            ]))
            ->where('pending_events.0.edit_url', null),
        );
});

test('pending events do not count as active attendance on my events', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $activeUser = User::factory()->create();
    createOnboardedProfile($activeUser);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'pending-counts-cleanly',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);

    EventAttendee::factory()->pending()->for($event)->for($viewer)->create();
    EventAttendee::factory()->for($event)->for($activeUser)->create([
        'status' => EventAttendee::STATUS_ACTIVE,
    ]);

    $this->actingAs($viewer)
        ->get(route('events.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pending_events.0.id', $event->id)
            ->where('pending_events.0.active_attendees_count', 1),
        );
});

test('my events payload does not expose attendance management urls', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'no-management-links',
    ]);
    EventAttendee::factory()->for($event)->for($viewer)->create();

    $response = $this->actingAs($viewer)
        ->get(route('events.mine'));

    $response->assertOk();

    $payload = $response->viewData('page');

    expect(json_encode($payload['props']))
        ->not->toContain('attendance_store_url')
        ->not->toContain('attendance_destroy_url')
        ->not->toContain('accept_url')
        ->not->toContain('decline_url');
});

test('my events page contains the expected empty state and action labels', function () {
    $page = file_get_contents(resource_path('js/pages/Events/MyEvents.vue'));

    expect($page)
        ->toContain('Meine Events')
        ->toContain('Du hast noch kein Event erstellt.')
        ->toContain('Du nimmst aktuell an keinem Event teil.')
        ->toContain('Du hast derzeit keine offenen Teilnahme-Anfragen.')
        ->toContain('Event erstellen')
        ->toContain('Events entdecken')
        ->toContain('Event ansehen')
        ->toContain('Event bearbeiten')
        ->toContain('event.my_events_show_url')
        ->not->toContain('attendance_store_url')
        ->not->toContain('attendance_destroy_url');
});

test('desktop navigation places my events in community while mobile bottom navigation stays unchanged', function () {
    $navigation = file_get_contents(resource_path('js/config/navigation/app-navigation.ts'));
    $mainNavigation = substr(
        $navigation,
        strpos($navigation, "title: 'Hauptbereich'"),
        strpos($navigation, "title: 'Community'") - strpos($navigation, "title: 'Hauptbereich'"),
    );
    $communityNavigation = substr(
        $navigation,
        strpos($navigation, "title: 'Community'"),
        strpos($navigation, "title: 'Kommunikation'") - strpos($navigation, "title: 'Community'"),
    );
    $mobileNavigation = substr(
        $navigation,
        strpos($navigation, 'mobileBottomNavItems'),
    );

    expect($navigation)
        ->toContain("title: 'Meine Events'")
        ->toContain("href: '/my-events'")
        ->and($mainNavigation)
        ->not->toContain("title: 'Meine Events'")
        ->and($communityNavigation)
        ->toContain("title: 'Meine Gruppen'")
        ->toContain("title: 'Meine Events'")
        ->and($mobileNavigation)
        ->not->toContain("title: 'Meine Events'")
        ->not->toContain("href: '/my-events'");
});
