<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open my groups', function () {
    $this->get(route('groups.mine'))
        ->assertRedirect(route('login'));
});

test('my groups shows owned active and pending memberships', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $ownedGroup = Group::factory()->for($viewer, 'owner')->create([
        'name' => 'Owned Group',
        'slug' => 'owned-group',
        'visibility' => Group::VISIBILITY_PRIVATE,
        'created_at' => now()->subMinutes(2),
    ]);
    $activeGroup = Group::factory()->create([
        'name' => 'Active Member Group',
        'slug' => 'active-member-group',
        'visibility' => Group::VISIBILITY_PRIVATE,
        'created_at' => now()->subMinute(),
    ]);
    $pendingGroup = Group::factory()->create([
        'name' => 'Pending Group',
        'slug' => 'pending-group',
        'visibility' => Group::VISIBILITY_REQUEST,
        'created_at' => now(),
    ]);
    Group::factory()->create([
        'name' => 'Unrelated Private Group',
        'slug' => 'unrelated-private-group',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    GroupMember::factory()
        ->for($activeGroup)
        ->for($viewer)
        ->create();
    GroupMember::factory()
        ->for($pendingGroup)
        ->for($viewer)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
        ]);

    $this->actingAs($viewer)
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->has('groups.data', 3)
            ->where('groups.data.0.name', $pendingGroup->name)
            ->where('groups.data.0.membership.status_label', 'Ausstehend')
            ->where('groups.data.1.name', $activeGroup->name)
            ->where('groups.data.1.membership.role_label', 'Mitglied')
            ->where('groups.data.2.name', $ownedGroup->name)
            ->where('groups.data.2.membership.role_label', 'Besitzer'),
        );
});

test('my groups empty state links to group discovery', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $this->actingAs($viewer)
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->has('groups.data', 0),
        );

    $page = file_get_contents(resource_path('js/pages/Groups/MyGroups.vue'));

    expect($page)
        ->toContain('Du bist noch in keiner Gruppe.')
        ->toContain('Entdecke Gruppen und finde passende Communities.')
        ->toContain('href="/groups"')
        ->toContain('Gruppen entdecken')
        ->not->toContain('Gruppe erstellen')
        ->not->toContain('Beitreten');
});
