<?php

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\InterestOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open or update the group edit page', function () {
    $group = Group::factory()->create([
        'slug' => 'guest-edit-group',
    ]);

    $this->get(route('groups.edit', $group->slug))
        ->assertRedirect(route('login'));

    $this->patch(route('groups.update', $group->slug), groupUpdatePayload())
        ->assertRedirect(route('login'));
});

test('group owner can open the edit page with active category options', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $category = InterestOption::query()->create([
        'slug' => 'group-edit-active-category',
        'label' => 'Aktive Edit-Kategorie',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    InterestOption::query()->create([
        'slug' => 'group-edit-inactive-category',
        'label' => 'Inaktive Edit-Kategorie',
        'sort_order' => 1,
        'is_active' => false,
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Edit Group',
        'slug' => 'edit-group',
        'description' => 'Altbeschreibung',
        'category_interest_option_id' => $category->id,
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);

    $this->actingAs($owner)
        ->get(route('groups.edit', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Edit')
            ->where('group.name', 'Edit Group')
            ->where('group.slug', 'edit-group')
            ->where('group.description', 'Altbeschreibung')
            ->where('group.category_interest_option_id', $category->id)
            ->where('group.visibility', Group::VISIBILITY_REQUEST)
            ->has('categoryOptions', 2)
            ->where('categoryOptions.0.id', $category->id)
            ->where('categoryOptions.0.label', 'Aktive Edit-Kategorie')
            ->where('visibilityOptions.0.value', Group::VISIBILITY_PUBLIC),
        );
});

test('platform admins can open the edit page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);
    createOnboardedProfile($admin);
    $group = Group::factory()->create([
        'slug' => 'admin-editable-group',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);

    $this->actingAs($admin)
        ->get(route('groups.edit', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Edit')
            ->where('group.slug', 'admin-editable-group'),
        );
});

test('members and non members cannot open the edit page', function () {
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $nonMember = User::factory()->create();
    createOnboardedProfile($nonMember);
    $group = Group::factory()->create([
        'slug' => 'member-edit-forbidden',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_ACTIVE,
        ]);

    $this->actingAs($member)
        ->get(route('groups.edit', $group->slug))
        ->assertForbidden();

    $this->actingAs($nonMember)
        ->get(route('groups.edit', $group->slug))
        ->assertForbidden();
});

test('group owner can update editable fields while slug and owner stay unchanged', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $category = InterestOption::query()->create([
        'slug' => 'group-update-category',
        'label' => 'Update Kategorie',
        'is_active' => true,
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Alter Name',
        'slug' => 'stabiler-slug',
        'description' => 'Alte Beschreibung',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($owner)
        ->patch(route('groups.update', $group->slug), groupUpdatePayload([
            'name' => 'Neuer Name',
            'description' => 'Neue Beschreibung',
            'region' => 'Berlin',
            'postal_code' => '10115',
            'country_code' => 'de',
            'category_interest_option_id' => (string) $category->id,
            'visibility' => Group::VISIBILITY_PRIVATE,
            'owner_id' => User::factory()->create()->id,
            'slug' => 'neuer-slug',
        ]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Gruppe wurde aktualisiert.')
        ->assertRedirect(route('groups.show', 'stabiler-slug'));

    $group->refresh();

    expect($group)
        ->owner_id->toBe($owner->id)
        ->slug->toBe('stabiler-slug')
        ->name->toBe('Neuer Name')
        ->description->toBe('Neue Beschreibung')
        ->region->toBe('Berlin')
        ->postal_code->toBe('10115')
        ->country_code->toBe('DE')
        ->category_interest_option_id->toBe($category->id)
        ->visibility->toBe(Group::VISIBILITY_PRIVATE);
});

test('group owner can remove the category', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $category = InterestOption::query()->create([
        'slug' => 'group-remove-category',
        'label' => 'Entfernbare Kategorie',
        'is_active' => true,
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'remove-category-group',
        'category_interest_option_id' => $category->id,
    ]);

    $this->actingAs($owner)
        ->patch(route('groups.update', $group->slug), groupUpdatePayload([
            'category_interest_option_id' => '',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('groups.show', 'remove-category-group'));

    expect($group->refresh()->category_interest_option_id)->toBeNull();
});

test('group update validates visibility and active managed category', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $inactiveCategory = InterestOption::query()->create([
        'slug' => 'group-update-inactive-category',
        'label' => 'Inaktive Update-Kategorie',
        'is_active' => false,
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'invalid-update-group',
    ]);

    $this->actingAs($owner)
        ->patch(route('groups.update', $group->slug), groupUpdatePayload([
            'visibility' => 'hidden',
            'category_interest_option_id' => $inactiveCategory->id,
        ]))
        ->assertSessionHasErrors([
            'visibility',
            'category_interest_option_id',
        ]);
});

test('group edit page renders expected form controls and custom category select', function () {
    $page = file_get_contents(resource_path('js/pages/Groups/Edit.vue'));

    expect($page)
        ->toContain('Gruppe bearbeiten')
        ->toContain('Aktualisiere die Angaben deiner Gruppe.')
        ->toContain('name="_method" value="patch"')
        ->toContain('name="name"')
        ->toContain('name="description"')
        ->toContain('name="region"')
        ->toContain('name="postal_code"')
        ->toContain('name="country_code"')
        ->toContain('name="category_interest_option_id"')
        ->toContain(':value="categoryInputValue"')
        ->toContain('<Select v-model="selectedCategoryOption">')
        ->toContain('Keine Kategorie')
        ->toContain('name="visibility"')
        ->toContain('Änderungen speichern')
        ->toContain('Wird gespeichert...')
        ->not->toContain('<select')
        ->not->toContain('Gruppe löschen')
        ->not->toContain('Beitreten');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function groupUpdatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Aktualisierte Gruppe',
        'description' => 'Aktualisierte Beschreibung.',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
        'category_interest_option_id' => '',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ], $overrides);
}
