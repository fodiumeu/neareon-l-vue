<?php

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owner receives remove capability only for regular active members', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $pending = User::factory()->create();
    createOnboardedProfile($pending);
    $group = Group::factory()->for($owner, 'owner')->create();

    $ownerMembership = GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create(['role' => GroupMember::ROLE_OWNER]);
    $moderatorMembership = GroupMember::factory()
        ->for($group)
        ->for($moderator)
        ->create(['role' => GroupMember::ROLE_MODERATOR]);
    $memberMembership = GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create(['role' => GroupMember::ROLE_MEMBER]);
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
            ->where('group.can_manage_members', true)
            ->has('members.data', 3)
            ->where('members.data.0.id', $ownerMembership->id)
            ->where('members.data.0.can_remove', false)
            ->where('members.data.0.remove_url', null)
            ->where('members.data.1.id', $moderatorMembership->id)
            ->where('members.data.1.can_remove', false)
            ->where('members.data.1.remove_url', null)
            ->where('members.data.2.id', $memberMembership->id)
            ->where('members.data.2.can_remove', true)
            ->where('members.data.2.remove_url', route('groups.members.destroy', [
                'group' => $group->slug,
                'member' => $memberMembership->id,
            ])),
        );
});

test('owner can remove a regular active member', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $group = Group::factory()->for($owner, 'owner')->create();
    GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create(['role' => GroupMember::ROLE_OWNER]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create(['role' => GroupMember::ROLE_MEMBER]);

    $this->actingAs($owner)
        ->delete(route('groups.members.destroy', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertRedirect(route('groups.members.index', $group->slug))
        ->assertSessionHas('success', 'Mitglied wurde aus der Gruppe entfernt.');

    expect(GroupMember::query()->whereKey($membership->id)->exists())->toBeFalse();

    $this->actingAs($member)
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups.data', 0),
        );
});

test('removed member cannot view a private group anymore', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $group = Group::factory()->for($owner, 'owner')->create([
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create(['role' => GroupMember::ROLE_OWNER]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create(['role' => GroupMember::ROLE_MEMBER]);

    $this->actingAs($owner)
        ->delete(route('groups.members.destroy', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertRedirect(route('groups.members.index', $group->slug));

    $this->actingAs($member)
        ->get(route('groups.show', $group->slug))
        ->assertNotFound();
});

test('owner cannot remove owners moderators pending memberships or wrong group memberships', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $targetOwner = User::factory()->create();
    createOnboardedProfile($targetOwner);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $pending = User::factory()->create();
    createOnboardedProfile($pending);
    $otherMember = User::factory()->create();
    createOnboardedProfile($otherMember);
    $group = Group::factory()->for($owner, 'owner')->create();
    $otherGroup = Group::factory()->create();
    GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create(['role' => GroupMember::ROLE_OWNER]);
    $ownerMembership = GroupMember::factory()
        ->for($group)
        ->for($targetOwner)
        ->create(['role' => GroupMember::ROLE_OWNER]);
    $moderatorMembership = GroupMember::factory()
        ->for($group)
        ->for($moderator)
        ->create(['role' => GroupMember::ROLE_MODERATOR]);
    $pendingMembership = GroupMember::factory()
        ->for($group)
        ->for($pending)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);
    $wrongGroupMembership = GroupMember::factory()
        ->for($otherGroup)
        ->for($otherMember)
        ->create();

    foreach ([$ownerMembership, $moderatorMembership, $pendingMembership, $wrongGroupMembership] as $membership) {
        $this->actingAs($owner)
            ->delete(route('groups.members.destroy', [
                'group' => $group->slug,
                'member' => $membership->id,
            ]))
            ->assertNotFound();

        expect(GroupMember::query()->whereKey($membership->id)->exists())->toBeTrue();
    }
});

test('regular pending and non members cannot remove group members', function () {
    $regular = User::factory()->create();
    createOnboardedProfile($regular);
    $pending = User::factory()->create();
    createOnboardedProfile($pending);
    $nonMember = User::factory()->create();
    createOnboardedProfile($nonMember);
    $target = User::factory()->create();
    createOnboardedProfile($target);
    $group = Group::factory()->create([
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($regular)
        ->create();
    GroupMember::factory()
        ->for($group)
        ->for($pending)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);
    $targetMembership = GroupMember::factory()
        ->for($group)
        ->for($target)
        ->create();

    foreach ([$regular, $pending, $nonMember] as $viewer) {
        $this->actingAs($viewer)
            ->delete(route('groups.members.destroy', [
                'group' => $group->slug,
                'member' => $targetMembership->id,
            ]))
            ->assertForbidden();
    }

    expect(GroupMember::query()->whereKey($targetMembership->id)->exists())->toBeTrue();
});

test('platform admin can remove a regular active member', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    createOnboardedProfile($admin);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $group = Group::factory()->create();
    $membership = GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create(['role' => GroupMember::ROLE_MEMBER]);

    $this->actingAs($admin)
        ->delete(route('groups.members.destroy', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertRedirect(route('groups.members.index', $group->slug));

    expect(GroupMember::query()->whereKey($membership->id)->exists())->toBeFalse();
});

test('non managers do not receive remove urls in members props', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $group = Group::factory()->create();
    GroupMember::factory()
        ->for($group)
        ->for($viewer)
        ->create();
    GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create();

    $this->actingAs($viewer)
        ->get(route('groups.members.index', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Members')
            ->where('group.can_manage_members', false)
            ->where('members.data.0.can_remove', false)
            ->where('members.data.0.remove_url', null)
            ->where('members.data.1.can_remove', false)
            ->where('members.data.1.remove_url', null),
        );
});

test('members page renders remove confirmation only from removable props', function () {
    $page = file_get_contents(resource_path('js/pages/Groups/Members.vue'));

    expect($page)
        ->toContain('member.can_remove')
        ->toContain('member.remove_url')
        ->toContain('Aus Gruppe entfernen')
        ->toContain('Mitglied entfernen?')
        ->toContain('Dieses Mitglied wird aus der')
        ->toContain('Gruppe entfernt und sieht die')
        ->toContain('Wird entfernt...')
        ->toContain('method="delete"');
});
