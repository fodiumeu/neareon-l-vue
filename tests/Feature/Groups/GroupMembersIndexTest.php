<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owner can view the active group members overview', function () {
    $owner = User::factory()->create(['name' => 'Owner Account']);
    createOnboardedProfile($owner, [
        'display_name' => 'Owner Profile',
        'username' => 'owner_profile',
    ]);
    $member = User::factory()->create(['name' => 'Member Account']);
    createOnboardedProfile($member, [
        'display_name' => 'Member Profile',
        'username' => 'member_profile',
    ]);
    $pending = User::factory()->create();
    createOnboardedProfile($pending, [
        'display_name' => 'Pending Profile',
        'username' => 'pending_profile',
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Member Overview Group',
        'slug' => 'member-overview-group',
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create([
            'role' => GroupMember::ROLE_OWNER,
            'joined_at' => now()->subDays(3),
        ]);
    GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'joined_at' => now()->subDay(),
        ]);
    GroupMember::factory()
        ->for($group)
        ->for($pending)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($owner)
        ->get(route('groups.members.index', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Members')
            ->where('group.name', 'Member Overview Group')
            ->where('group.url', route('groups.show', [
                'group' => $group->slug,
                'from' => 'my-groups',
            ]))
            ->has('members.data', 2)
            ->where('members.data.0.role_label', 'Besitzer')
            ->where('members.data.0.user.name', 'Owner Profile')
            ->where('members.data.0.user.username', 'owner_profile')
            ->where('members.data.0.user.profile_url', route('public-profile.show', 'owner_profile'))
            ->where('members.data.1.role_label', 'Mitglied')
            ->where('members.data.1.user.name', 'Member Profile'),
        );
});

test('active members can view owner moderators and members in role order', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner, ['display_name' => 'Owner']);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator, ['display_name' => 'Moderator']);
    $member = User::factory()->create();
    createOnboardedProfile($member, ['display_name' => 'Member']);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer, ['display_name' => 'Viewer']);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'role-order-group',
    ]);

    GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'joined_at' => now()->subDays(5),
        ]);
    GroupMember::factory()
        ->for($group)
        ->for($viewer)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'joined_at' => now()->subDays(4),
        ]);
    GroupMember::factory()
        ->for($group)
        ->for($moderator)
        ->create([
            'role' => GroupMember::ROLE_MODERATOR,
            'joined_at' => now()->subDay(),
        ]);
    GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create([
            'role' => GroupMember::ROLE_OWNER,
            'joined_at' => now(),
        ]);

    $this->actingAs($viewer)
        ->get(route('groups.members.index', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Members')
            ->has('members.data', 4)
            ->where('members.data.0.role', GroupMember::ROLE_OWNER)
            ->where('members.data.1.role', GroupMember::ROLE_MODERATOR)
            ->where('members.data.2.role', GroupMember::ROLE_MEMBER)
            ->where('members.data.3.role', GroupMember::ROLE_MEMBER),
        );
});

test('pending members and non members cannot view the members overview', function () {
    $pending = User::factory()->create();
    createOnboardedProfile($pending);
    $nonMember = User::factory()->create();
    createOnboardedProfile($nonMember);
    $group = Group::factory()->create([
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($pending)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($pending)
        ->get(route('groups.members.index', $group->slug))
        ->assertForbidden();

    $this->actingAs($nonMember)
        ->get(route('groups.show', $group->slug))
        ->assertOk();

    $this->actingAs($nonMember)
        ->get(route('groups.members.index', $group->slug))
        ->assertForbidden();
});

test('private group members overview stays hidden from non members', function () {
    $nonMember = User::factory()->create();
    createOnboardedProfile($nonMember);
    $activeMember = User::factory()->create();
    createOnboardedProfile($activeMember);
    $group = Group::factory()->create([
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($activeMember)
        ->create();

    $this->actingAs($nonMember)
        ->get(route('groups.members.index', $group->slug))
        ->assertNotFound();

    $this->actingAs($activeMember)
        ->get(route('groups.members.index', $group->slug))
        ->assertOk();
});

test('group detail links to members overview only for active viewers', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $pending = User::factory()->create();
    createOnboardedProfile($pending);
    $nonMember = User::factory()->create();
    createOnboardedProfile($nonMember);
    $group = Group::factory()->for($owner, 'owner')->create([
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create([
            'role' => GroupMember::ROLE_OWNER,
        ]);
    GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create();
    GroupMember::factory()
        ->for($group)
        ->for($pending)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($owner)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.can_view_members', true)
            ->where('group.members_url', route('groups.members.index', $group->slug)),
        );

    $this->actingAs($member)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.can_view_members', true)
            ->where('group.members_url', route('groups.members.index', $group->slug)),
        );

    $this->actingAs($pending)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.can_view_members', false)
            ->where('group.members_url', null),
        );

    $this->actingAs($nonMember)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.can_view_members', false)
            ->where('group.members_url', null),
        );
});

test('group members overview paginates active members', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $group = Group::factory()->for($owner, 'owner')->create();
    GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create([
            'role' => GroupMember::ROLE_OWNER,
        ]);

    User::factory()
        ->count(24)
        ->create()
        ->each(function (User $user) use ($group): void {
            createOnboardedProfile($user);
            GroupMember::factory()
                ->for($group)
                ->for($user)
                ->create();
        });

    $this->actingAs($owner)
        ->get(route('groups.members.index', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Members')
            ->has('members.data', 24)
            ->where('members.last_page', 2),
        );
});

test('group members page renders the expected read only ui', function () {
    $page = file_get_contents(resource_path('js/pages/Groups/Members.vue'));
    $show = file_get_contents(resource_path('js/pages/Groups/Show.vue'));

    expect($page)
        ->toContain('Alle aktiven Mitglieder dieser Gruppe.')
        ->toContain('← Zurück zur Gruppe')
        ->toContain('Profil ansehen')
        ->toContain('Mitglied seit')
        ->toContain('Noch keine aktiven Mitglieder')
        ->toContain('members.prev_page_url')
        ->toContain('members.next_page_url')
        ->and($show)
        ->toContain('Alle Mitglieder ansehen')
        ->toContain('group.can_view_members')
        ->toContain('group.members_url');
});
