<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\InterestOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('public group detail is visible for onboarded members', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create(['name' => 'Owner Account']);
    createOnboardedProfile($owner, [
        'display_name' => 'Owner Profile',
        'username' => 'owner_profile',
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Local Running',
        'slug' => 'local-running',
        'description' => 'Gemeinsam laufen in der Region.',
        'region' => 'Berlin',
        'postal_code' => '10115',
        'country_code' => 'DE',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    $category = InterestOption::query()->create([
        'slug' => 'group-show-category',
        'label' => 'Fitness',
        'is_active' => true,
    ]);
    $group->forceFill([
        'category_interest_option_id' => $category->id,
    ])->save();

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.name', 'Local Running')
            ->where('group.description', 'Gemeinsam laufen in der Region.')
            ->where('group.region', 'Berlin')
            ->where('group.postal_code', '10115')
            ->where('group.country_code', 'DE')
            ->where('group.visibility_label', 'Öffentlich')
            ->where('group.owner.name', 'Owner Profile')
            ->where('group.member_count', 0)
            ->where('group.can_edit', false)
            ->where('group.can_join', true)
            ->where('group.join_label', 'Gruppe beitreten')
            ->where('group.category.label', 'Fitness')
            ->where('group.membership', null),
        );
});

test('group detail exposes owner membership for my groups backlink', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'owned-public-group',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($owner)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.can_edit', true)
            ->where('group.can_join', false)
            ->where('group.edit_url', route('groups.edit', $group->slug))
            ->where('group.membership.role_label', 'Besitzer')
            ->where('group.membership.status', GroupMember::STATUS_ACTIVE),
        );
});

test('request group detail is visible', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'request-visible',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.visibility_label', 'Anfrage')
            ->where('group.can_join', true)
            ->where('group.join_label', 'Beitrittsanfrage senden'),
        );
});

test('request group detail shows pending membership state', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'request-pending-visible',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($viewer)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.can_join', false)
            ->where('group.viewer_membership_status', GroupMember::STATUS_PENDING)
            ->where('group.membership.status_label', 'Anfrage ausstehend'),
        );
});

test('private group detail is hidden from non members', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'hidden-private',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->slug))
        ->assertNotFound();
});

test('private group detail is visible for active members and shows newest members', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer, [
        'display_name' => 'Viewer Member',
        'username' => 'viewer_member',
    ]);
    $other = User::factory()->create();
    createOnboardedProfile($other, [
        'display_name' => 'Other Member',
        'username' => 'other_member',
    ]);
    $group = Group::factory()->create([
        'name' => 'Private Members',
        'slug' => 'private-members',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($viewer)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'joined_at' => now()->subDay(),
        ]);
    GroupMember::factory()
        ->for($group)
        ->for($other)
        ->create([
            'role' => GroupMember::ROLE_MODERATOR,
            'joined_at' => now(),
        ]);

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.name', 'Private Members')
            ->where('group.visibility_label', 'Privat')
            ->where('group.member_count', 2)
            ->where('group.membership.role_label', 'Mitglied')
            ->where('group.membership.status', GroupMember::STATUS_ACTIVE)
            ->has('group.members', 2)
            ->where('group.members.0.user.name', 'Other Member')
            ->where('group.members.0.role_label', 'Moderator'),
        );
});

test('group detail page uses stable membership based backlinks', function () {
    $page = file_get_contents(resource_path('js/pages/Groups/Show.vue'));

    expect($page)
        ->toContain('backHref')
        ->toContain("'/my-groups'")
        ->toContain("'/groups'")
        ->toContain('← Zurück zu Meine Gruppen')
        ->toContain('← Zurück zu Gruppen entdecken')
        ->toContain(':href="backHref"')
        ->not->toContain('AppBackButton');
});

test('group detail page keeps future actions as informational read only hints', function () {
    $page = file_get_contents(resource_path('js/pages/Groups/Show.vue'));

    expect($page)
        ->toContain('Standort')
        ->toContain('locationLabel(group)')
        ->toContain('group.postal_code')
        ->toContain('Kategorie')
        ->toContain('group.category')
        ->toContain('group.category.label')
        ->toContain('Weitere Gruppenfunktionen wie Beitritt, Chat und Events')
        ->toContain('Neueste Mitglieder')
        ->toContain('Gruppe bearbeiten')
        ->toContain('Anfrage gesendet')
        ->toContain('group.can_edit')
        ->toContain('group.can_join')
        ->toContain('group.edit_url')
        ->toContain('group.join_label')
        ->toContain('group.join_url');
});
