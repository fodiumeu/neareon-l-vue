<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('active members can leave a group', function () {
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $group = Group::factory()->create([
        'slug' => 'leave-public-group',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_ACTIVE,
        ]);

    $this->actingAs($member)
        ->delete(route('groups.membership.destroy', $group->slug))
        ->assertSessionHas('success', 'Du hast die Gruppe verlassen.')
        ->assertRedirect(route('groups.mine'));

    expect(GroupMember::query()->whereKey($membership->id)->exists())->toBeFalse();

    $this->actingAs($member)
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->has('groups.data', 0),
        );

    $this->actingAs($member)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 1)
            ->where('groups.data.0.id', $group->id)
            ->where('groups.data.0.can_join', true),
        );
});

test('moderators can leave a group like regular members', function () {
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $group = Group::factory()->create([
        'slug' => 'moderator-leaves-group',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($moderator)
        ->create([
            'role' => GroupMember::ROLE_MODERATOR,
            'status' => GroupMember::STATUS_ACTIVE,
        ]);

    $this->actingAs($moderator)
        ->delete(route('groups.membership.destroy', $group->slug))
        ->assertSessionHas('success', 'Du hast die Gruppe verlassen.')
        ->assertRedirect(route('groups.mine'));

    expect(GroupMember::query()->whereKey($membership->id)->exists())->toBeFalse();

    $this->actingAs($moderator)
        ->get(route('groups.show', $group->slug))
        ->assertNotFound();
});

test('pending members can withdraw their membership request', function () {
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $group = Group::factory()->create([
        'slug' => 'withdraw-request-group',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($member)
        ->delete(route('groups.membership.destroy', $group->slug))
        ->assertSessionHas('success', 'Deine Beitrittsanfrage wurde zurückgezogen.')
        ->assertRedirect(route('groups.index'));

    expect(GroupMember::query()->whereKey($membership->id)->exists())->toBeFalse();

    $this->actingAs($member)
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->has('groups.data', 0),
        );

    $this->actingAs($member)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 1)
            ->where('groups.data.0.id', $group->id)
            ->where('groups.data.0.can_join', true),
        );

    $this->actingAs($member)
        ->post(route('groups.join', $group->slug))
        ->assertSessionHas('success', 'Deine Beitrittsanfrage wurde gesendet.')
        ->assertRedirect(route('groups.show', $group->slug));

    expect(GroupMember::query()
        ->where('group_id', $group->id)
        ->where('user_id', $member->id)
        ->where('status', GroupMember::STATUS_PENDING)
        ->exists())->toBeTrue();
});

test('owners cannot leave their own group', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'owner-cannot-leave',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create([
            'role' => GroupMember::ROLE_OWNER,
            'status' => GroupMember::STATUS_ACTIVE,
        ]);

    $this->actingAs($owner)
        ->delete(route('groups.membership.destroy', $group->slug))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.can_edit', true)
            ->where('group.can_leave', false)
            ->where('group.leave_url', null)
            ->where('group.leave_label', null),
        );
});

test('non members cannot leave a group', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'non-member-cannot-leave',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->delete(route('groups.membership.destroy', $group->slug))
        ->assertNotFound();

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.can_join', true)
            ->where('group.can_leave', false)
            ->where('group.leave_url', null)
            ->where('group.leave_label', null),
        );
});

test('guests cannot leave groups', function () {
    $group = Group::factory()->create([
        'slug' => 'guest-cannot-leave',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->delete(route('groups.membership.destroy', $group->slug))
        ->assertRedirect(route('login'));
});

test('group detail exposes leave action for active and pending members', function () {
    $activeMember = User::factory()->create();
    createOnboardedProfile($activeMember);
    $pendingMember = User::factory()->create();
    createOnboardedProfile($pendingMember);
    $activeGroup = Group::factory()->create([
        'slug' => 'active-leave-props',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    $pendingGroup = Group::factory()->create([
        'slug' => 'pending-leave-props',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    GroupMember::factory()
        ->for($activeGroup)
        ->for($activeMember)
        ->create([
            'status' => GroupMember::STATUS_ACTIVE,
        ]);
    GroupMember::factory()
        ->for($pendingGroup)
        ->for($pendingMember)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($activeMember)
        ->get(route('groups.show', $activeGroup->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.can_leave', true)
            ->where('group.leave_label', 'Gruppe verlassen')
            ->where('group.leave_url', route('groups.membership.destroy', $activeGroup->slug)),
        );

    $this->actingAs($pendingMember)
        ->get(route('groups.show', $pendingGroup->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.can_leave', true)
            ->where('group.leave_label', 'Anfrage zurückziehen')
            ->where('group.leave_url', route('groups.membership.destroy', $pendingGroup->slug)),
        );
});

test('my groups keeps leave and withdraw actions on the group detail page only', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $activeGroup = Group::factory()->create([
        'name' => 'Active Leave Card',
        'slug' => 'active-leave-card',
        'visibility' => Group::VISIBILITY_PUBLIC,
        'created_at' => now()->subMinute(),
    ]);
    $pendingGroup = Group::factory()->create([
        'name' => 'Pending Leave Card',
        'slug' => 'pending-leave-card',
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
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->has('groups.data', 2)
            ->where('groups.data.0.id', $pendingGroup->id)
            ->where('groups.data.1.id', $activeGroup->id)
            ->where('groups.data.1.membership.status', GroupMember::STATUS_ACTIVE),
        );
});

test('leave and withdraw ui is only exposed on group detail pages', function () {
    $showPage = file_get_contents(resource_path('js/pages/Groups/Show.vue'));
    $myGroupsPage = file_get_contents(resource_path('js/pages/Groups/MyGroups.vue'));

    expect($showPage)
        ->toContain('Gruppe verlassen?')
        ->toContain('Du verlässt diese Gruppe')
        ->toContain('Anfrage zurückziehen')
        ->toContain('group.leave_url')
        ->toContain('method="delete"')
        ->and($myGroupsPage)
        ->toContain('Gruppe ansehen')
        ->not->toContain('Gruppe verlassen?')
        ->not->toContain('Anfrage zurückziehen')
        ->not->toContain('group.leave_url')
        ->not->toContain('method="delete"');
});
