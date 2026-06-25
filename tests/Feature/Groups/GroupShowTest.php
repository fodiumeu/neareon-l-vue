<?php

use App\Models\Group;
use App\Models\GroupMember;
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
            ->where('group.member_count', 0),
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
            ->where('group.visibility_label', 'Anfrage'),
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
            ->has('group.members', 2)
            ->where('group.members.0.user.name', 'Other Member')
            ->where('group.members.0.role_label', 'Moderator'),
        );
});

test('group detail page keeps future actions as informational read only hints', function () {
    $page = file_get_contents(resource_path('js/pages/Groups/Show.vue'));

    expect($page)
        ->toContain('Zurück zu Gruppen')
        ->toContain('Standort')
        ->toContain('locationLabel(group)')
        ->toContain('group.postal_code')
        ->toContain('Weitere Gruppenfunktionen wie Beitritt, Chat und Events')
        ->toContain('Neueste Mitglieder')
        ->not->toContain('Gruppe bearbeiten')
        ->not->toContain('Beitreten');
});
