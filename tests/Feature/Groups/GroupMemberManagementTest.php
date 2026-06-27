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
            ->where('members.data.0.can_promote', false)
            ->where('members.data.0.can_demote', false)
            ->where('members.data.0.can_remove', false)
            ->where('members.data.0.role_update_url', null)
            ->where('members.data.0.remove_url', null)
            ->where('members.data.1.id', $moderatorMembership->id)
            ->where('members.data.1.can_promote', false)
            ->where('members.data.1.can_demote', true)
            ->where('members.data.1.can_remove', false)
            ->where('members.data.1.role_update_url', route('groups.members.role.update', [
                'group' => $group->slug,
                'member' => $moderatorMembership->id,
            ]))
            ->where('members.data.1.remove_url', null)
            ->where('members.data.2.id', $memberMembership->id)
            ->where('members.data.2.can_promote', true)
            ->where('members.data.2.can_demote', false)
            ->where('members.data.2.can_remove', true)
            ->where('members.data.2.role_update_url', route('groups.members.role.update', [
                'group' => $group->slug,
                'member' => $memberMembership->id,
            ]))
            ->where('members.data.2.remove_url', route('groups.members.destroy', [
                'group' => $group->slug,
                'member' => $memberMembership->id,
            ])),
        );
});

test('owner can promote a regular active member to moderator', function () {
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
        ->patch(route('groups.members.role.update', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]), [
            'role' => GroupMember::ROLE_MODERATOR,
        ])
        ->assertRedirect(route('groups.members.index', $group->slug))
        ->assertSessionHas('success', 'Mitglied wurde zum Moderator gemacht.');

    expect($membership->refresh()->role)->toBe(GroupMember::ROLE_MODERATOR);
});

test('owner can demote a moderator back to member', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $group = Group::factory()->for($owner, 'owner')->create();
    GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create(['role' => GroupMember::ROLE_OWNER]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($moderator)
        ->create(['role' => GroupMember::ROLE_MODERATOR]);

    $this->actingAs($owner)
        ->patch(route('groups.members.role.update', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]), [
            'role' => GroupMember::ROLE_MEMBER,
        ])
        ->assertRedirect(route('groups.members.index', $group->slug))
        ->assertSessionHas('success', 'Moderator wurde zum Mitglied zurückgestuft.');

    expect($membership->refresh()->role)->toBe(GroupMember::ROLE_MEMBER);
});

test('platform admin can change member roles', function () {
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
        ->patch(route('groups.members.role.update', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]), [
            'role' => GroupMember::ROLE_MODERATOR,
        ])
        ->assertRedirect(route('groups.members.index', $group->slug));

    expect($membership->refresh()->role)->toBe(GroupMember::ROLE_MODERATOR);
});

test('regular members moderators and non members cannot change roles', function () {
    $regular = User::factory()->create();
    createOnboardedProfile($regular);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $nonMember = User::factory()->create();
    createOnboardedProfile($nonMember);
    $target = User::factory()->create();
    createOnboardedProfile($target);
    $group = Group::factory()->create();
    GroupMember::factory()
        ->for($group)
        ->for($regular)
        ->create(['role' => GroupMember::ROLE_MEMBER]);
    GroupMember::factory()
        ->for($group)
        ->for($moderator)
        ->create(['role' => GroupMember::ROLE_MODERATOR]);
    $targetMembership = GroupMember::factory()
        ->for($group)
        ->for($target)
        ->create(['role' => GroupMember::ROLE_MEMBER]);

    foreach ([$regular, $moderator, $nonMember] as $viewer) {
        $this->actingAs($viewer)
            ->patch(route('groups.members.role.update', [
                'group' => $group->slug,
                'member' => $targetMembership->id,
            ]), [
                'role' => GroupMember::ROLE_MODERATOR,
            ])
            ->assertForbidden();
    }

    expect($targetMembership->refresh()->role)->toBe(GroupMember::ROLE_MEMBER);
});

test('owner cannot change pending owner self or wrong group memberships', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $pending = User::factory()->create();
    createOnboardedProfile($pending);
    $targetOwner = User::factory()->create();
    createOnboardedProfile($targetOwner);
    $otherMember = User::factory()->create();
    createOnboardedProfile($otherMember);
    $group = Group::factory()->for($owner, 'owner')->create();
    $otherGroup = Group::factory()->create();
    $selfMembership = GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create(['role' => GroupMember::ROLE_OWNER]);
    $ownerMembership = GroupMember::factory()
        ->for($group)
        ->for($targetOwner)
        ->create(['role' => GroupMember::ROLE_OWNER]);
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

    foreach ([$ownerMembership, $pendingMembership, $wrongGroupMembership] as $membership) {
        $this->actingAs($owner)
            ->patch(route('groups.members.role.update', [
                'group' => $group->slug,
                'member' => $membership->id,
            ]), [
                'role' => GroupMember::ROLE_MODERATOR,
            ])
            ->assertNotFound();
    }

    $this->actingAs($owner)
        ->patch(route('groups.members.role.update', [
            'group' => $group->slug,
            'member' => $selfMembership->id,
        ]), [
            'role' => GroupMember::ROLE_MEMBER,
        ])
        ->assertNotFound();
});

test('invalid member role update values are rejected', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $group = Group::factory()->for($owner, 'owner')->create();
    $membership = GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create(['role' => GroupMember::ROLE_MEMBER]);

    foreach ([GroupMember::ROLE_OWNER, 'unknown', ''] as $role) {
        $this->actingAs($owner)
            ->patch(route('groups.members.role.update', [
                'group' => $group->slug,
                'member' => $membership->id,
            ]), [
                'role' => $role,
            ])
            ->assertSessionHasErrors('role');
    }

    expect($membership->refresh()->role)->toBe(GroupMember::ROLE_MEMBER);
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

test('active moderator can remove a regular active member', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $group = Group::factory()->for($owner, 'owner')->create();
    GroupMember::factory()
        ->for($group)
        ->for($moderator)
        ->create(['role' => GroupMember::ROLE_MODERATOR]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create(['role' => GroupMember::ROLE_MEMBER]);

    $this->actingAs($moderator)
        ->delete(route('groups.members.destroy', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertRedirect(route('groups.members.index', $group->slug))
        ->assertSessionHas('success', 'Mitglied wurde aus der Gruppe entfernt.');

    expect(GroupMember::query()->whereKey($membership->id)->exists())->toBeFalse();
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

test('moderator cannot remove owners other moderators self pending or wrong group memberships', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $otherModerator = User::factory()->create();
    createOnboardedProfile($otherModerator);
    $pending = User::factory()->create();
    createOnboardedProfile($pending);
    $otherMember = User::factory()->create();
    createOnboardedProfile($otherMember);
    $group = Group::factory()->for($owner, 'owner')->create();
    $otherGroup = Group::factory()->create();
    $ownerMembership = GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create(['role' => GroupMember::ROLE_OWNER]);
    $moderatorMembership = GroupMember::factory()
        ->for($group)
        ->for($moderator)
        ->create(['role' => GroupMember::ROLE_MODERATOR]);
    $otherModeratorMembership = GroupMember::factory()
        ->for($group)
        ->for($otherModerator)
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

    foreach ([
        $ownerMembership,
        $moderatorMembership,
        $otherModeratorMembership,
        $pendingMembership,
        $wrongGroupMembership,
    ] as $membership) {
        $this->actingAs($moderator)
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

test('moderator receives remove urls only for regular members and no role urls', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $otherModerator = User::factory()->create();
    createOnboardedProfile($otherModerator);
    $member = User::factory()->create();
    createOnboardedProfile($member);
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
    $otherModeratorMembership = GroupMember::factory()
        ->for($group)
        ->for($otherModerator)
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

    $this->actingAs($moderator)
        ->get(route('groups.members.index', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Members')
            ->where('group.can_manage_members', true)
            ->where('group.can_manage_roles', false)
            ->has('members.data', 4)
            ->where('members.data.0.id', $ownerMembership->id)
            ->where('members.data.0.can_remove', false)
            ->where('members.data.0.remove_url', null)
            ->where('members.data.0.can_promote', false)
            ->where('members.data.0.can_demote', false)
            ->where('members.data.0.role_update_url', null)
            ->where('members.data.1.id', $moderatorMembership->id)
            ->where('members.data.1.can_remove', false)
            ->where('members.data.1.remove_url', null)
            ->where('members.data.1.role_update_url', null)
            ->where('members.data.2.id', $otherModeratorMembership->id)
            ->where('members.data.2.can_remove', false)
            ->where('members.data.2.remove_url', null)
            ->where('members.data.2.role_update_url', null)
            ->where('members.data.3.id', $memberMembership->id)
            ->where('members.data.3.can_remove', true)
            ->where('members.data.3.remove_url', route('groups.members.destroy', [
                'group' => $group->slug,
                'member' => $memberMembership->id,
            ]))
            ->where('members.data.3.can_promote', false)
            ->where('members.data.3.can_demote', false)
            ->where('members.data.3.role_update_url', null),
        );
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
            ->where('group.can_manage_roles', false)
            ->where('members.data.0.can_promote', false)
            ->where('members.data.0.can_demote', false)
            ->where('members.data.0.can_remove', false)
            ->where('members.data.0.role_update_url', null)
            ->where('members.data.0.remove_url', null)
            ->where('members.data.1.can_promote', false)
            ->where('members.data.1.can_demote', false)
            ->where('members.data.1.can_remove', false)
            ->where('members.data.1.role_update_url', null)
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
        ->toContain('method="delete"')
        ->toContain('member.can_promote')
        ->toContain('member.can_demote')
        ->toContain('member.role_update_url')
        ->toContain('Moderator ernennen?')
        ->toContain('Moderator zurückstufen?')
        ->toContain('Zum Moderator machen')
        ->toContain('Zum Mitglied machen')
        ->toContain('Wird aktualisiert...')
        ->toContain('method="patch"');
});
