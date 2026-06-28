<?php

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\InterestOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open the event creation page or store events', function () {
    $this->get(route('events.create'))
        ->assertRedirect(route('login'));

    $this->post(route('events.store'), eventCreatePayload())
        ->assertRedirect(route('login'));
});

test('onboarded members can open the event creation page with active categories', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $activeCategory = InterestOption::query()->create([
        'slug' => 'event-create-active-category',
        'label' => 'Aktive Event-Kategorie',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    InterestOption::query()->create([
        'slug' => 'event-create-inactive-category',
        'label' => 'Inaktive Event-Kategorie',
        'sort_order' => 1,
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->get(route('events.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Create')
            ->has('categoryOptions', 2)
            ->where('categoryOptions.0.id', $activeCategory->id)
            ->where('categoryOptions.0.label', 'Aktive Event-Kategorie')
            ->where('visibilityOptions.0.value', Event::VISIBILITY_PUBLIC)
            ->where('visibilityOptions.1.value', Event::VISIBILITY_REQUEST),
        );
});

test('event creation page renders expected fields and processing state', function () {
    $page = file_get_contents(resource_path('js/pages/Events/Create.vue'));

    expect($page)
        ->toContain('Event erstellen')
        ->toContain('Erstelle ein regionales Event für deine Community.')
        ->toContain('fallback="/events"')
        ->toContain('label="Zurück zu Events"')
        ->toContain('name="title"')
        ->toContain('name="description"')
        ->toContain('name="starts_at"')
        ->toContain('name="ends_at"')
        ->toContain('name="region"')
        ->toContain('name="postal_code"')
        ->toContain('name="country_code"')
        ->toContain('name="category_interest_option_id"')
        ->toContain(':value="categoryInputValue"')
        ->toContain('<Select v-model="selectedCategoryOption">')
        ->toContain('Keine Kategorie')
        ->toContain('name="visibility"')
        ->toContain('name="max_attendees"')
        ->toContain('Wird erstellt...')
        ->not->toContain('<select')
        ->not->toContain('Teilnehmen')
        ->not->toContain('Beitrittsanfrage');
});

test('onboarded members can create an event without category', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->post(route('events.store'), eventCreatePayload([
            'country_code' => 'de',
        ]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Event wurde erstellt.')
        ->assertRedirect(route('events.show', 'community-treffen-hamburg'));

    $event = Event::query()
        ->where('slug', 'community-treffen-hamburg')
        ->firstOrFail();

    expect($event)
        ->owner_id->toBe($user->id)
        ->title->toBe('Community Treffen Hamburg')
        ->description->toBe('Ein regionales Treffen für die Community.')
        ->region->toBe('Hamburg')
        ->postal_code->toBe('20095')
        ->country_code->toBe('DE')
        ->visibility->toBe(Event::VISIBILITY_PUBLIC)
        ->status->toBe(Event::STATUS_ACTIVE)
        ->category_interest_option_id->toBeNull()
        ->max_attendees->toBe(50);

    expect(EventAttendee::query()->where('event_id', $event->id)->count())
        ->toBe(0);
});

test('onboarded members can create an event with one managed category', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $category = InterestOption::query()->create([
        'slug' => 'event-store-category',
        'label' => 'Kultur',
        'sort_order' => 20,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('events.store'), eventCreatePayload([
            'category_interest_option_id' => (string) $category->id,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('events.show', 'community-treffen-hamburg'));

    $event = Event::query()
        ->where('slug', 'community-treffen-hamburg')
        ->firstOrFail();

    expect($event->category_interest_option_id)->toBe($category->id)
        ->and($event->category->label)->toBe('Kultur');
});

test('event slugs are generated uniquely for duplicate titles', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    Event::factory()->create([
        'title' => 'Community Treffen Hamburg',
        'slug' => 'community-treffen-hamburg',
    ]);

    $this->actingAs($user)
        ->post(route('events.store'), eventCreatePayload([
            'title' => 'Community Treffen Hamburg',
        ]))
        ->assertRedirect(route('events.show', 'community-treffen-hamburg-2'));

    expect(Event::query()->where('slug', 'community-treffen-hamburg-2')->exists())
        ->toBeTrue();
});

test('public and request visibility can be stored', function (string $visibility) {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->post(route('events.store'), eventCreatePayload([
            'visibility' => $visibility,
        ]))
        ->assertSessionHasNoErrors();

    expect(Event::query()->where('visibility', $visibility)->exists())->toBeTrue();
})->with([
    Event::VISIBILITY_PUBLIC,
    Event::VISIBILITY_REQUEST,
]);

test('end time can be empty or after start time', function (?string $endsAt) {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->post(route('events.store'), eventCreatePayload([
            'ends_at' => $endsAt,
        ]))
        ->assertSessionHasNoErrors();

    expect(Event::query()->latest('id')->firstOrFail()->ends_at?->format('Y-m-d H:i'))
        ->toBe($endsAt === null ? null : '2026-07-10 20:00');
})->with([
    null,
    '2026-07-10 20:00',
]);

test('event creation rejects invalid category visibility and end time', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $inactiveCategory = InterestOption::query()->create([
        'slug' => 'event-create-inactive-validation',
        'label' => 'Inaktive Kategorie',
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->post(route('events.store'), eventCreatePayload([
            'category_interest_option_id' => 999999,
            'visibility' => 'private',
            'ends_at' => '2026-07-10 17:00',
        ]))
        ->assertSessionHasErrors([
            'category_interest_option_id',
            'visibility',
            'ends_at',
        ]);

    $this->actingAs($user)
        ->post(route('events.store'), eventCreatePayload([
            'category_interest_option_id' => $inactiveCategory->id,
        ]))
        ->assertSessionHasErrors(['category_interest_option_id']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function eventCreatePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Community Treffen Hamburg',
        'description' => 'Ein regionales Treffen für die Community.',
        'starts_at' => '2026-07-10 18:00',
        'ends_at' => '',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
        'category_interest_option_id' => '',
        'visibility' => Event::VISIBILITY_PUBLIC,
        'max_attendees' => '50',
    ], $overrides);
}
