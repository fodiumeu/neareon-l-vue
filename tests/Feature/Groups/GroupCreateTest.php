<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\InterestOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open the group creation page or store groups', function () {
    $this->get(route('groups.create'))
        ->assertRedirect(route('login'));

    $this->post(route('groups.store'), groupCreatePayload())
        ->assertRedirect(route('login'));
});

test('onboarded members can open the group creation page', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $activeCategory = InterestOption::query()->create([
        'slug' => 'group-create-active-category',
        'label' => 'Aktive Kategorie',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    InterestOption::query()->create([
        'slug' => 'group-create-inactive-category',
        'label' => 'Inaktive Kategorie',
        'sort_order' => 1,
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->get(route('groups.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Create')
            ->has('categoryOptions', 2)
            ->where('categoryOptions.0.id', $activeCategory->id)
            ->where('categoryOptions.0.label', 'Aktive Kategorie')
            ->where('visibilityOptions.0.value', Group::VISIBILITY_PUBLIC)
            ->where('visibilityOptions.1.value', Group::VISIBILITY_REQUEST)
            ->where('visibilityOptions.2.value', Group::VISIBILITY_PRIVATE),
        );
});

test('group creation page renders the expected fields and processing state', function () {
    $page = file_get_contents(resource_path('js/pages/Groups/Create.vue'));

    expect($page)
        ->toContain('Gruppe erstellen')
        ->toContain('Erstelle eine regionale oder thematische Gruppe')
        ->toContain('name="name"')
        ->toContain('name="description"')
        ->toContain('name="region"')
        ->toContain('name="postal_code"')
        ->toContain('name="country_code"')
        ->toContain('name="visibility"')
        ->toContain('Kategorie')
        ->toContain('name="category_interest_option_id"')
        ->toContain(':value="categoryInputValue"')
        ->toContain('Keine Kategorie')
        ->toContain('categoryOptions')
        ->toContain('<Select v-model="selectedCategoryOption">')
        ->toContain('SelectTrigger')
        ->toContain('SelectContent')
        ->toContain('SelectItem')
        ->toContain('groupSelectTriggerClass')
        ->toContain('groupSelectContentClass')
        ->toContain('groupSelectItemClass')
        ->toContain('Wähle ein Hauptthema')
        ->toContain('value="DE"')
        ->toContain('Wird erstellt...')
        ->toContain('InputError')
        ->not->toContain('<select')
        ->not->toContain('name="categories[]"')
        ->not->toContain('maximal 5 Kategorien');
});

test('onboarded members can create a group and become the active owner member', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->post(route('groups.store'), groupCreatePayload([
            'name' => 'Laufgruppe Hamburg',
            'country_code' => 'de',
        ]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Gruppe wurde erstellt.')
        ->assertRedirect(route('groups.show', 'laufgruppe-hamburg'));

    $group = Group::query()->where('slug', 'laufgruppe-hamburg')->firstOrFail();

    expect($group)
        ->owner_id->toBe($user->id)
        ->name->toBe('Laufgruppe Hamburg')
        ->description->toBe('Wir laufen gemeinsam an der Alster.')
        ->region->toBe('Hamburg')
        ->postal_code->toBe('20095')
        ->country_code->toBe('DE')
        ->visibility->toBe(Group::VISIBILITY_PUBLIC);

    $membership = GroupMember::query()
        ->where('group_id', $group->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($membership)
        ->role->toBe(GroupMember::ROLE_OWNER)
        ->status->toBe(GroupMember::STATUS_ACTIVE)
        ->joined_at->not->toBeNull();

    expect($group->category)->toBeNull();
});

test('onboarded members can create a group with one managed category', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $fitness = InterestOption::query()->create([
        'slug' => 'group-store-fitness',
        'label' => 'Fitness',
        'sort_order' => 20,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('groups.store'), groupCreatePayload([
            'category_interest_option_id' => (string) $fitness->id,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('groups.show', 'laufgruppe-hamburg'));

    $group = Group::query()->where('slug', 'laufgruppe-hamburg')->firstOrFail();

    expect($group->category_interest_option_id)->toBe($fitness->id)
        ->and($group->category->label)->toBe('Fitness');
});

test('group slugs are generated uniquely for duplicate names', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    Group::factory()->create([
        'name' => 'Laufgruppe Hamburg',
        'slug' => 'laufgruppe-hamburg',
    ]);

    $this->actingAs($user)
        ->post(route('groups.store'), groupCreatePayload([
            'name' => 'Laufgruppe Hamburg',
        ]))
        ->assertRedirect(route('groups.show', 'laufgruppe-hamburg-2'));

    expect(Group::query()->where('slug', 'laufgruppe-hamburg-2')->exists())
        ->toBeTrue();
});

test('group creation validation rejects missing and invalid fields', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->post(route('groups.store'), groupCreatePayload([
            'name' => '',
            'visibility' => '',
            'postal_code' => str_repeat('1', 21),
            'country_code' => 'DEU',
        ]))
        ->assertSessionHasErrors([
            'name',
            'visibility',
            'postal_code',
            'country_code',
        ]);

    $this->actingAs($user)
        ->post(route('groups.store'), groupCreatePayload([
            'visibility' => 'hidden',
        ]))
        ->assertSessionHasErrors(['visibility']);
});

test('group creation validates single active managed category', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $inactiveCategory = InterestOption::query()->create([
        'slug' => 'group-inactive-category',
        'label' => 'Inaktive Kategorie',
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->post(route('groups.store'), groupCreatePayload([
            'category_interest_option_id' => [$inactiveCategory->id],
        ]))
        ->assertSessionHasErrors(['category_interest_option_id']);

    $this->actingAs($user)
        ->post(route('groups.store'), groupCreatePayload([
            'category_interest_option_id' => $inactiveCategory->id,
        ]))
        ->assertSessionHasErrors(['category_interest_option_id']);

    $this->actingAs($user)
        ->post(route('groups.store'), groupCreatePayload([
            'category_interest_option_id' => 999999,
        ]))
        ->assertSessionHasErrors(['category_interest_option_id']);
});

test('created group visibility respects discover and my groups semantics', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->post(route('groups.store'), groupCreatePayload([
            'name' => 'Private Schreibgruppe',
            'visibility' => Group::VISIBILITY_PRIVATE,
        ]))
        ->assertRedirect(route('groups.show', 'private-schreibgruppe'));

    $group = Group::query()
        ->where('slug', 'private-schreibgruppe')
        ->firstOrFail();

    $this->actingAs($user)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 0),
        );

    $this->actingAs($user)
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->has('groups.data', 1)
            ->where('groups.data.0.id', $group->id)
            ->where('groups.data.0.membership.role_label', 'Besitzer'),
        );

    $this->actingAs($user)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.id', $group->id)
            ->where('group.membership.role_label', 'Besitzer'),
        );
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function groupCreatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Laufgruppe Hamburg',
        'description' => 'Wir laufen gemeinsam an der Alster.',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ], $overrides);
}
