<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\InterestOption;
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
        'region' => 'Berlin',
        'postal_code' => '10115',
        'country_code' => 'DE',
        'visibility' => Group::VISIBILITY_PRIVATE,
        'created_at' => now()->subMinutes(2),
    ]);
    $category = InterestOption::query()->create([
        'slug' => 'my-groups-category',
        'label' => 'Reisen',
        'is_active' => true,
    ]);
    $ownedGroup->forceFill([
        'category_interest_option_id' => $category->id,
    ])->save();
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
            ->where('groups.data.0.url', route('groups.show', [
                'group' => $pendingGroup->slug,
                'from' => 'my-groups',
            ]))
            ->where('groups.data.0.membership.status_label', 'Anfrage ausstehend')
            ->where('groups.data.1.name', $activeGroup->name)
            ->where('groups.data.1.url', route('groups.show', [
                'group' => $activeGroup->slug,
                'from' => 'my-groups',
            ]))
            ->where('groups.data.1.membership.role_label', 'Mitglied')
            ->where('groups.data.2.name', $ownedGroup->name)
            ->where('groups.data.2.url', route('groups.show', [
                'group' => $ownedGroup->slug,
                'from' => 'my-groups',
            ]))
            ->where('groups.data.2.region', 'Berlin')
            ->where('groups.data.2.postal_code', '10115')
            ->where('groups.data.2.country_code', 'DE')
            ->where('groups.data.2.category.label', 'Reisen')
            ->where('groups.data.2.membership.role_label', 'Besitzer'),
        );
});

test('my groups from home keeps the home origin on group links', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->for($viewer, 'owner')->create([
        'name' => 'Home My Group',
        'slug' => 'home-my-group',
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.mine', ['from' => 'home']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->where('groups.data.0.url', route('groups.show', [
                'group' => $group->slug,
                'from' => 'my-groups',
                'origin' => 'home',
            ])),
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
        ->toContain('Sobald du Gruppen erstellst oder Gruppen')
        ->toContain('beitrittst, findest du sie hier.')
        ->toContain('Starte deine Community')
        ->toContain('Erstelle eine eigene Gruppe oder entdecke passende Gruppen aus der NEAREON-Community.')
        ->toContain('href="/groups/create"')
        ->toContain('Gruppe erstellen')
        ->toContain('href="/groups"')
        ->toContain('Gruppen entdecken')
        ->toContain('group.category')
        ->toContain('group.category.label')
        ->toContain('group.postal_code')
        ->toContain('PLZ {{ group.postal_code }}')
        ->toContain('grid min-w-0 grid-cols-1 gap-4')
        ->toContain('max-w-full min-w-0 w-full')
        ->not->toContain('Beitreten');
});

test('my groups shows the action card below existing groups', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    Group::factory()->for($viewer, 'owner')->create([
        'name' => 'Existing Group',
        'slug' => 'existing-group',
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->has('groups.data', 1)
            ->where('groups.data.0.name', 'Existing Group'),
        );

    $page = file_get_contents(resource_path('js/pages/Groups/MyGroups.vue'));
    $gridPosition = strpos($page, 'v-for="group in groups.data"');
    $actionCardPosition = strpos($page, 'Weitere Gruppen');
    $createLinkPosition = strpos($page, 'href="/groups/create"');
    $discoverLinkPosition = strpos($page, 'href="/groups"');

    expect($createLinkPosition)
        ->not->toBeFalse()
        ->and($discoverLinkPosition)
        ->not->toBeFalse()
        ->and($actionCardPosition)
        ->not->toBeFalse()
        ->toBeGreaterThan($gridPosition)
        ->and($page)
        ->toContain('Weitere Gruppen')
        ->toContain('Erstelle eine weitere Gruppe oder entdecke neue Communities.')
        ->toContain('Starte deine Community');
});
