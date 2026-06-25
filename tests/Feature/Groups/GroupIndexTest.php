<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\InterestOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open the groups index', function () {
    $this->get(route('groups.index'))
        ->assertRedirect(route('login'));
});

test('groups index shows public and request groups but hides unrelated private groups', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $publicGroup = Group::factory()->create([
        'name' => 'Public Community',
        'slug' => 'public-community',
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    $category = InterestOption::query()->create([
        'slug' => 'group-index-category',
        'label' => 'Kochen',
        'is_active' => true,
    ]);
    $publicGroup->forceFill([
        'category_interest_option_id' => $category->id,
    ])->save();
    $requestGroup = Group::factory()->create([
        'name' => 'Request Community',
        'slug' => 'request-community',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    Group::factory()->create([
        'name' => 'Private Community',
        'slug' => 'private-community',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 2)
            ->where('groups.data.0.name', $publicGroup->name)
            ->where('groups.data.0.region', 'Hamburg')
            ->where('groups.data.0.postal_code', '20095')
            ->where('groups.data.0.country_code', 'DE')
            ->where('groups.data.0.visibility_label', 'Öffentlich')
            ->where('groups.data.0.category.label', 'Kochen')
            ->where('groups.data.0.can_join', true)
            ->where('groups.data.0.join_label', 'Gruppe beitreten')
            ->where('groups.data.1.name', $requestGroup->name)
            ->where('groups.data.1.category', null)
            ->where('groups.data.1.can_join', true)
            ->where('groups.data.1.join_label', 'Beitrittsanfrage senden')
            ->where('groups.data.1.visibility_label', 'Anfrage'),
        );
});

test('groups index exposes pending and active viewer membership status', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $activeGroup = Group::factory()->create([
        'name' => 'Active Public Group',
        'slug' => 'active-public-group',
        'visibility' => Group::VISIBILITY_PUBLIC,
        'created_at' => now()->subMinute(),
    ]);
    $pendingGroup = Group::factory()->create([
        'name' => 'Pending Request Group',
        'slug' => 'pending-request-group',
        'visibility' => Group::VISIBILITY_REQUEST,
        'created_at' => now(),
    ]);
    GroupMember::factory()
        ->for($activeGroup)
        ->for($viewer)
        ->create([
            'status' => GroupMember::STATUS_ACTIVE,
        ]);
    GroupMember::factory()
        ->for($pendingGroup)
        ->for($viewer)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($viewer)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 2)
            ->where('groups.data.0.name', 'Pending Request Group')
            ->where('groups.data.0.can_join', false)
            ->where('groups.data.0.viewer_membership_status', GroupMember::STATUS_PENDING)
            ->where('groups.data.0.membership.status_label', 'Anfrage ausstehend')
            ->where('groups.data.1.name', 'Active Public Group')
            ->where('groups.data.1.can_join', false)
            ->where('groups.data.1.viewer_membership_status', GroupMember::STATUS_ACTIVE)
            ->where('groups.data.1.membership.role_label', 'Mitglied'),
        );
});

test('groups discover index hides private groups even when the viewer is an active member', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'name' => 'Member Private Group',
        'slug' => 'member-private-group',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($viewer)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_ACTIVE,
        ]);

    $this->actingAs($viewer)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 0),
        );
});

test('groups index paginates visible groups', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    Group::factory()->count(13)->create([
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 12)
            ->where('groups.last_page', 2),
        );
});

test('groups index page includes empty state card and read only group actions', function () {
    $page = file_get_contents(resource_path('js/pages/Groups/Index.vue'));

    expect($page)
        ->toContain('Gruppen entdecken')
        ->toContain('Entdecke öffentliche und offene Gruppen')
        ->toContain('Noch keine Gruppen zum Entdecken sichtbar.')
        ->toContain('group.postal_code')
        ->toContain('group.category')
        ->toContain('group.category.label')
        ->toContain('Gruppe ansehen')
        ->toContain('Anfrage gesendet')
        ->toContain('group.can_join')
        ->toContain('group.join_label')
        ->toContain('group.join_url')
        ->toContain('visibility_label')
        ->toContain('member_count')
        ->not->toContain('Gruppe erstellen')
        ->not->toContain('Gruppe bearbeiten')
        ->not->toContain('Gruppe verlassen');
});
