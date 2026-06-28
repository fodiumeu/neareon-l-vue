<?php

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\InterestOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open or update the event edit page', function () {
    $event = Event::factory()->create([
        'slug' => 'guest-edit-event',
    ]);

    $this->get(route('events.edit', $event->slug))
        ->assertRedirect(route('login'));

    $this->patch(route('events.update', $event->slug), eventUpdatePayload())
        ->assertRedirect(route('login'));
});

test('event owner can open the edit page with active category options', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $category = InterestOption::query()->create([
        'slug' => 'event-edit-active-category',
        'label' => 'Aktive Edit-Kategorie',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    InterestOption::query()->create([
        'slug' => 'event-edit-inactive-category',
        'label' => 'Inaktive Edit-Kategorie',
        'sort_order' => 1,
        'is_active' => false,
    ]);
    $event = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Edit Event',
        'slug' => 'edit-event',
        'description' => 'Altbeschreibung',
        'category_interest_option_id' => $category->id,
        'visibility' => Event::VISIBILITY_REQUEST,
        'starts_at' => '2026-07-10 18:00',
        'ends_at' => '2026-07-10 20:00',
    ]);

    $this->actingAs($owner)
        ->get(route('events.edit', $event->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Edit')
            ->where('event.title', 'Edit Event')
            ->where('event.slug', 'edit-event')
            ->where('event.description', 'Altbeschreibung')
            ->where('event.category_interest_option_id', $category->id)
            ->where('event.visibility', Event::VISIBILITY_REQUEST)
            ->where('event.starts_at', '2026-07-10T18:00')
            ->where('event.ends_at', '2026-07-10T20:00')
            ->has('categoryOptions', 2)
            ->where('categoryOptions.0.id', $category->id)
            ->where('categoryOptions.0.label', 'Aktive Edit-Kategorie')
            ->where('visibilityOptions.0.value', Event::VISIBILITY_PUBLIC)
            ->where('visibilityOptions.1.value', Event::VISIBILITY_REQUEST),
        );
});

test('platform admins can open the event edit page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);
    createOnboardedProfile($admin);
    $event = Event::factory()->create([
        'slug' => 'admin-editable-event',
    ]);

    $this->actingAs($admin)
        ->get(route('events.edit', $event->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Edit')
            ->where('event.slug', 'admin-editable-event'),
        );
});

test('non owners cannot open the event edit page', function () {
    $nonOwner = User::factory()->create();
    createOnboardedProfile($nonOwner);
    $event = Event::factory()->create([
        'slug' => 'non-owner-edit-forbidden',
    ]);

    $this->actingAs($nonOwner)
        ->get(route('events.edit', $event->slug))
        ->assertForbidden();
});

test('event owner can update editable fields while slug owner and status stay unchanged', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $category = InterestOption::query()->create([
        'slug' => 'event-update-category',
        'label' => 'Update Kategorie',
        'is_active' => true,
    ]);
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create([
        'title' => 'Alter Eventname',
        'slug' => 'stabiler-event-slug',
        'description' => 'Alte Beschreibung',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
        'visibility' => Event::VISIBILITY_PUBLIC,
        'status' => Event::STATUS_ACTIVE,
        'max_attendees' => null,
    ]);

    $this->actingAs($owner)
        ->patch(route('events.update', $event->slug), eventUpdatePayload([
            'title' => 'Neuer Eventname',
            'description' => 'Neue Beschreibung',
            'starts_at' => '2026-08-10 18:00',
            'ends_at' => '2026-08-10 21:00',
            'region' => 'Berlin',
            'postal_code' => '10115',
            'country_code' => 'de',
            'category_interest_option_id' => (string) $category->id,
            'visibility' => Event::VISIBILITY_REQUEST,
            'max_attendees' => '120',
            'owner_id' => $otherUser->id,
            'slug' => 'neuer-event-slug',
            'status' => Event::STATUS_CANCELLED,
        ]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Event wurde aktualisiert.')
        ->assertRedirect(route('events.edit', 'stabiler-event-slug'));

    $event->refresh();

    expect($event)
        ->owner_id->toBe($owner->id)
        ->slug->toBe('stabiler-event-slug')
        ->status->toBe(Event::STATUS_ACTIVE)
        ->title->toBe('Neuer Eventname')
        ->description->toBe('Neue Beschreibung')
        ->region->toBe('Berlin')
        ->postal_code->toBe('10115')
        ->country_code->toBe('DE')
        ->category_interest_option_id->toBe($category->id)
        ->visibility->toBe(Event::VISIBILITY_REQUEST)
        ->max_attendees->toBe(120)
        ->and($event->starts_at->format('Y-m-d H:i'))->toBe('2026-08-10 18:00')
        ->and($event->ends_at->format('Y-m-d H:i'))->toBe('2026-08-10 21:00');
});

test('event owner can remove category and max attendees', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $category = InterestOption::query()->create([
        'slug' => 'event-remove-category',
        'label' => 'Entfernbare Kategorie',
        'is_active' => true,
    ]);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'remove-event-category',
        'category_interest_option_id' => $category->id,
        'max_attendees' => 80,
    ]);

    $this->actingAs($owner)
        ->patch(route('events.update', $event->slug), eventUpdatePayload([
            'category_interest_option_id' => '',
            'max_attendees' => '',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('events.edit', 'remove-event-category'));

    expect($event->refresh())
        ->category_interest_option_id->toBeNull()
        ->max_attendees->toBeNull();
});

test('non owners cannot update events', function () {
    $nonOwner = User::factory()->create();
    createOnboardedProfile($nonOwner);
    $event = Event::factory()->create([
        'slug' => 'non-owner-update-forbidden',
        'title' => 'Unverändert',
    ]);

    $this->actingAs($nonOwner)
        ->patch(route('events.update', $event->slug), eventUpdatePayload([
            'title' => 'Nicht erlaubt',
        ]))
        ->assertForbidden();

    expect($event->refresh()->title)->toBe('Unverändert');
});

test('event update does not change attendee records', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'attendees-stay-event',
    ]);
    $attendance = EventAttendee::factory()
        ->for($event)
        ->create();

    $this->actingAs($owner)
        ->patch(route('events.update', $event->slug), eventUpdatePayload())
        ->assertSessionHasNoErrors();

    expect(EventAttendee::query()->where('event_id', $event->id)->count())
        ->toBe(1)
        ->and(EventAttendee::query()->first()->is($attendance))->toBeTrue();
});

test('event update validates visibility active category and max attendees', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $inactiveCategory = InterestOption::query()->create([
        'slug' => 'event-update-inactive-category',
        'label' => 'Inaktive Update-Kategorie',
        'is_active' => false,
    ]);
    $event = Event::factory()->for($owner, 'owner')->create([
        'slug' => 'invalid-update-event',
    ]);

    $this->actingAs($owner)
        ->patch(route('events.update', $event->slug), eventUpdatePayload([
            'visibility' => 'private',
            'category_interest_option_id' => $inactiveCategory->id,
            'max_attendees' => 0,
        ]))
        ->assertSessionHasErrors([
            'visibility',
            'category_interest_option_id',
            'max_attendees',
        ]);
});

test('event edit page renders expected form controls and custom category select', function () {
    $page = file_get_contents(resource_path('js/pages/Events/Edit.vue'));

    expect($page)
        ->toContain('Event bearbeiten')
        ->toContain('Aktualisiere die Angaben deines Events.')
        ->toContain('name="_method" value="patch"')
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
        ->toContain('Der Slug des Events bleibt beim Bearbeiten')
        ->toContain('unverändert.')
        ->toContain('Änderungen speichern')
        ->toContain('Wird gespeichert...')
        ->not->toContain('<select')
        ->not->toContain('Event löschen')
        ->not->toContain('Teilnehmen');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function eventUpdatePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Aktualisiertes Event',
        'description' => 'Aktualisierte Beschreibung.',
        'starts_at' => '2026-07-10 18:00',
        'ends_at' => '',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
        'category_interest_option_id' => '',
        'visibility' => Event::VISIBILITY_PUBLIC,
        'max_attendees' => '',
    ], $overrides);
}
