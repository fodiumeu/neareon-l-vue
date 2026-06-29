<?php

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\InterestOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open the events index', function () {
    $this->get(route('events.index'))
        ->assertRedirect(route('login'));
});

test('non onboarded users are redirected by the existing onboarding middleware', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('events.index'))
        ->assertRedirect(route('onboarding.details'));
});

test('onboarded users can open the events index with active visible events', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create(['name' => 'Event Owner']);
    createOnboardedProfile($owner, [
        'display_name' => 'Owner Anzeige',
        'username' => 'event_owner',
    ]);
    $category = InterestOption::query()->create([
        'slug' => 'event-index-category',
        'label' => 'Kultur',
        'is_active' => true,
    ]);
    $publicEvent = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Public Event',
        'slug' => 'public-event',
        'description' => 'Ein sichtbares Event.',
        'starts_at' => '2026-07-10 18:00',
        'ends_at' => '2026-07-10 20:00',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
        'visibility' => Event::VISIBILITY_PUBLIC,
        'status' => Event::STATUS_ACTIVE,
        'category_interest_option_id' => $category->id,
        'max_attendees' => 50,
    ]);
    EventAttendee::factory()
        ->for($publicEvent)
        ->create();
    $requestEvent = Event::factory()->create([
        'title' => 'Request Event',
        'slug' => 'request-event',
        'starts_at' => '2026-07-11 18:00',
        'region' => 'Hamburg',
        'visibility' => Event::VISIBILITY_REQUEST,
        'status' => Event::STATUS_ACTIVE,
    ]);
    Event::factory()->create([
        'title' => 'Cancelled Event',
        'slug' => 'cancelled-event',
        'status' => Event::STATUS_CANCELLED,
    ]);

    $this->actingAs($viewer)
        ->get(route('events.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Index')
            ->has('events.data', 2)
            ->where('events.data.0.id', $publicEvent->id)
            ->where('events.data.0.title', 'Public Event')
            ->where('events.data.0.description', 'Ein sichtbares Event.')
            ->where('events.data.0.show_url', route('events.show', $publicEvent->slug))
            ->where('events.data.0.region', 'Hamburg')
            ->where('events.data.0.postal_code', '20095')
            ->where('events.data.0.country_code', 'DE')
            ->where('events.data.0.visibility', Event::VISIBILITY_PUBLIC)
            ->where('events.data.0.visibility_label', 'Öffentlich')
            ->where('events.data.0.status', Event::STATUS_ACTIVE)
            ->where('events.data.0.category.label', 'Kultur')
            ->where('events.data.0.attendee_count', 1)
            ->where('events.data.0.max_attendees', 50)
            ->where('events.data.0.owner.name', 'Owner Anzeige')
            ->where('events.data.0.owner.username', 'event_owner')
            ->missing('events.data.0.edit_url')
            ->missing('events.data.0.join_url')
            ->missing('events.data.0.request_url')
            ->missing('events.data.0.attendees_url')
            ->where('events.data.1.id', $requestEvent->id)
            ->where('events.data.1.visibility_label', 'Anfrage')
            ->where('filterOptions.regions.0', 'Hamburg')
            ->where('filterOptions.categories.0.label', 'Kultur')
            ->where('filterOptions.visibilities.0.value', Event::VISIBILITY_PUBLIC)
            ->where('filterOptions.visibilities.1.value', Event::VISIBILITY_REQUEST),
        );
});

test('events index sorts by start date and stable id', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $later = Event::factory()->create([
        'title' => 'Later Event',
        'starts_at' => '2026-07-12 18:00',
    ]);
    $firstSameTime = Event::factory()->create([
        'title' => 'First Same Time',
        'starts_at' => '2026-07-10 18:00',
    ]);
    $secondSameTime = Event::factory()->create([
        'title' => 'Second Same Time',
        'starts_at' => '2026-07-10 18:00',
    ]);

    $this->actingAs($viewer)
        ->get(route('events.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.data.0.id', $firstSameTime->id)
            ->where('events.data.1.id', $secondSameTime->id)
            ->where('events.data.2.id', $later->id),
        );
});

test('events index paginates visible events and keeps query strings', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    Event::factory()->count(13)->sequence(
        fn ($sequence) => [
            'title' => 'Berlin Community '.$sequence->index,
            'region' => 'Berlin',
            'starts_at' => now()->addDays($sequence->index + 1),
            'visibility' => Event::VISIBILITY_PUBLIC,
        ],
    )->create();

    $this->actingAs($viewer)
        ->get(route('events.index', ['q' => 'Berlin']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Index')
            ->has('events.data', 12)
            ->where('events.last_page', 2)
            ->where('filters.q', 'Berlin')
            ->where('events.next_page_url', fn (?string $url): bool => $url !== null && str_contains($url, 'q=Berlin')),
        );
});

test('events index searches visible events by title description region postal code and category', function (string $query, string $expectedTitle) {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $category = InterestOption::query()->create([
        'slug' => 'urban-culture',
        'label' => 'Urban Culture',
        'is_active' => true,
    ]);

    Event::factory()->create([
        'title' => 'Nordic Makers',
        'description' => 'Kreativer Austausch.',
        'region' => 'Kiel',
        'postal_code' => '24103',
        'visibility' => Event::VISIBILITY_PUBLIC,
    ]);
    Event::factory()->create([
        'title' => 'Silent Reading',
        'description' => 'Lesen im Stadtpark.',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'visibility' => Event::VISIBILITY_PUBLIC,
        'category_interest_option_id' => $category->id,
    ]);
    Event::factory()->create([
        'title' => 'Cancelled Urban',
        'description' => 'Soll nicht gefunden werden.',
        'status' => Event::STATUS_CANCELLED,
        'category_interest_option_id' => $category->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('events.index', ['q' => $query]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.title', $expectedTitle),
        );
})->with([
    'title' => ['Nordic', 'Nordic Makers'],
    'description' => ['Stadtpark', 'Silent Reading'],
    'region' => ['Kiel', 'Nordic Makers'],
    'postal code' => ['20095', 'Silent Reading'],
    'category label' => ['Urban Culture', 'Silent Reading'],
    'category slug' => ['urban-culture', 'Silent Reading'],
]);

test('events index filters by region category visibility and combinations', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $category = InterestOption::query()->create([
        'slug' => 'music-events',
        'label' => 'Musik',
        'is_active' => true,
    ]);
    Event::factory()->create([
        'title' => 'Hamburg Musik Public',
        'region' => 'Hamburg',
        'visibility' => Event::VISIBILITY_PUBLIC,
        'category_interest_option_id' => $category->id,
    ]);
    Event::factory()->create([
        'title' => 'Hamburg Request',
        'region' => 'Hamburg',
        'visibility' => Event::VISIBILITY_REQUEST,
    ]);
    Event::factory()->create([
        'title' => 'Berlin Musik',
        'region' => 'Berlin',
        'visibility' => Event::VISIBILITY_PUBLIC,
        'category_interest_option_id' => $category->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('events.index', [
            'region' => 'Hamburg',
            'category' => 'music-events',
            'visibility' => Event::VISIBILITY_PUBLIC,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Hamburg Musik Public')
            ->where('filters.region', 'Hamburg')
            ->where('filters.category', 'music-events')
            ->where('filters.visibility', Event::VISIBILITY_PUBLIC),
        );

    $this->actingAs($viewer)
        ->get(route('events.index', ['visibility' => Event::VISIBILITY_REQUEST]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Hamburg Request'),
        );
});

test('invalid visibility filter does not expose additional events', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    Event::factory()->create([
        'title' => 'Visible Event',
        'visibility' => Event::VISIBILITY_PUBLIC,
    ]);
    Event::factory()->create([
        'title' => 'Cancelled Private Idea',
        'status' => Event::STATUS_CANCELLED,
    ]);

    $this->actingAs($viewer)
        ->get(route('events.index', ['visibility' => 'private']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Visible Event')
            ->where('filters.visibility', ''),
        );
});

test('events index vue contains expected copy and no forbidden event actions', function () {
    $page = file_get_contents(resource_path('js/pages/Events/Index.vue'));

    expect($page)
        ->toContain('Events entdecken')
        ->toContain('← {{ backLink.label }}')
        ->toContain(':href="backLink.href"')
        ->toContain('Entdecke regionale Events aus der NEAREON-Community.')
        ->toContain('Event erstellen')
        ->toContain('Name, Beschreibung, Region oder PLZ')
        ->toContain('Filter zurücksetzen')
        ->toContain('Event ansehen')
        ->toContain('Teilnehmen')
        ->toContain('Teilnahme anfragen')
        ->toContain('Keine passenden Events gefunden')
        ->toContain('Noch keine Events sichtbar.')
        ->not->toContain('Anfrage zurückziehen')
        ->not->toContain('Teilnehmer verwalten')
        ->not->toContain('Event bearbeiten')
        ->not->toContain('edit_url')
        ->not->toContain('join_url')
        ->not->toContain('request_url');
});
