<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owner sees pending membership requests on group detail', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser, [
        'display_name' => 'Requesting Member',
        'username' => 'requesting_member',
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'owner-sees-requests',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($requestingUser)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
            'created_at' => now()->subMinutes(15),
        ]);

    $this->actingAs($owner)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->has('group.pending_requests', 1)
            ->where('group.pending_requests.0.id', $membership->id)
            ->where('group.pending_requests.0.user.name', 'Requesting Member')
            ->where('group.pending_requests.0.user.username', 'requesting_member')
            ->where('group.pending_requests.0.accept_url', route('groups.requests.accept', [
                'group' => $group->slug,
                'member' => $membership->id,
            ]))
            ->where('group.pending_requests.0.decline_url', route('groups.requests.decline', [
                'group' => $group->slug,
                'member' => $membership->id,
            ]))
            ->where('group.pending_requests.0.profile_url', route('public-profile.show', 'requesting_member')),
        );
});

test('non owners do not receive pending membership request data', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser, [
        'display_name' => 'Hidden Requester',
        'username' => 'hidden_requester',
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'requests-hidden-from-viewer',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($requestingUser)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->has('group.pending_requests', 0),
        );
});

test('active moderators see pending membership request data without owner admin props', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser, [
        'display_name' => 'Moderator Visible Requester',
        'username' => 'moderator_visible_requester',
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'moderator-sees-requests',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($moderator)
        ->create([
            'role' => GroupMember::ROLE_MODERATOR,
            'status' => GroupMember::STATUS_ACTIVE,
        ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($requestingUser)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($moderator)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.can_edit', false)
            ->where('group.edit_url', null)
            ->where('group.can_manage_invite', false)
            ->where('group.invite_url', null)
            ->where('group.invite_token_url', null)
            ->where('group.can_manage_requests', true)
            ->has('group.pending_requests', 1)
            ->where('group.pending_requests.0.id', $membership->id)
            ->where('group.pending_requests.0.user.name', 'Moderator Visible Requester')
            ->where('group.pending_requests.0.accept_url', route('groups.requests.accept', [
                'group' => $group->slug,
                'member' => $membership->id,
            ]))
            ->where('group.pending_requests.0.decline_url', route('groups.requests.decline', [
                'group' => $group->slug,
                'member' => $membership->id,
            ])),
        );
});

test('active members do not receive pending membership request data', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'requests-hidden-from-member',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create([
            'status' => GroupMember::STATUS_ACTIVE,
        ]);
    GroupMember::factory()
        ->for($group)
        ->for($requestingUser)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($member)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->has('group.pending_requests', 0),
        );
});

test('owner can accept a pending membership request', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'accept-pending-request',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($requestingUser)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($owner)
        ->patch(route('groups.requests.accept', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertSessionHas('success', 'Anfrage angenommen.')
        ->assertRedirect(route('groups.show', $group->slug));

    $membership->refresh();

    expect($membership)
        ->status->toBe(GroupMember::STATUS_ACTIVE)
        ->role->toBe(GroupMember::ROLE_MEMBER)
        ->joined_at->not->toBeNull();

    $this->actingAs($requestingUser)
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->has('groups.data', 1)
            ->where('groups.data.0.id', $group->id)
            ->where('groups.data.0.membership.status', GroupMember::STATUS_ACTIVE)
            ->where('groups.data.0.membership.status_label', 'Mitglied'),
        );
});

test('active moderator can accept a pending membership request', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'moderator-accept-pending-request',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($moderator)
        ->create([
            'role' => GroupMember::ROLE_MODERATOR,
            'status' => GroupMember::STATUS_ACTIVE,
        ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($requestingUser)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($moderator)
        ->patch(route('groups.requests.accept', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertSessionHas('success', 'Anfrage angenommen.')
        ->assertRedirect(route('groups.show', $group->slug));

    expect($membership->refresh())
        ->status->toBe(GroupMember::STATUS_ACTIVE)
        ->role->toBe(GroupMember::ROLE_MEMBER)
        ->joined_at->not->toBeNull();
});

test('owner can decline a pending membership request', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'decline-pending-request',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($requestingUser)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($owner)
        ->delete(route('groups.requests.decline', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertSessionHas('success', 'Anfrage abgelehnt.')
        ->assertRedirect(route('groups.show', $group->slug));

    expect(GroupMember::query()->whereKey($membership->id)->exists())->toBeFalse();

    $this->actingAs($requestingUser)
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->has('groups.data', 0),
        );
});

test('active moderator can decline a pending membership request', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'moderator-decline-pending-request',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($moderator)
        ->create([
            'role' => GroupMember::ROLE_MODERATOR,
            'status' => GroupMember::STATUS_ACTIVE,
        ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($requestingUser)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($moderator)
        ->delete(route('groups.requests.decline', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertSessionHas('success', 'Anfrage abgelehnt.')
        ->assertRedirect(route('groups.show', $group->slug));

    expect(GroupMember::query()->whereKey($membership->id)->exists())->toBeFalse();
});

test('membership requests for archived groups are not processed', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'archived-request-not-processed',
        'status' => Group::STATUS_ARCHIVED,
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($requestingUser)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($owner)
        ->patch(route('groups.requests.accept', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('groups.requests.decline', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertNotFound();

    expect($membership->refresh()->status)->toBe(GroupMember::STATUS_PENDING);
});

test('non owners cannot accept or decline membership requests', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $pending = User::factory()->create();
    createOnboardedProfile($pending);
    $nonMember = User::factory()->create();
    createOnboardedProfile($nonMember);
    $otherModerator = User::factory()->create();
    createOnboardedProfile($otherModerator);
    $pendingModerator = User::factory()->create();
    createOnboardedProfile($pendingModerator);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'non-owner-cannot-manage-requests',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $otherGroup = Group::factory()->create([
        'slug' => 'other-moderated-request-group',
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create(['status' => GroupMember::STATUS_ACTIVE]);
    GroupMember::factory()
        ->for($group)
        ->for($pending)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);
    GroupMember::factory()
        ->for($otherGroup)
        ->for($otherModerator)
        ->create([
            'role' => GroupMember::ROLE_MODERATOR,
            'status' => GroupMember::STATUS_ACTIVE,
        ]);
    GroupMember::factory()
        ->for($group)
        ->for($pendingModerator)
        ->create([
            'role' => GroupMember::ROLE_MODERATOR,
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($requestingUser)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    foreach ([$member, $pending, $nonMember, $otherModerator, $pendingModerator] as $viewer) {
        $this->actingAs($viewer)
            ->patch(route('groups.requests.accept', [
                'group' => $group->slug,
                'member' => $membership->id,
            ]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('groups.requests.decline', [
                'group' => $group->slug,
                'member' => $membership->id,
            ]))
            ->assertForbidden();
    }

    $membership->refresh();

    expect($membership->status)->toBe(GroupMember::STATUS_PENDING);
});

test('guests cannot manage membership requests', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'guest-cannot-manage-requests',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($requestingUser)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->patch(route('groups.requests.accept', [
        'group' => $group->slug,
        'member' => $membership->id,
    ]))->assertRedirect(route('login'));

    $this->delete(route('groups.requests.decline', [
        'group' => $group->slug,
        'member' => $membership->id,
    ]))->assertRedirect(route('login'));
});

test('membership requests from another group are not processed', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $requestingUser = User::factory()->create();
    createOnboardedProfile($requestingUser);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'target-group',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $otherGroup = Group::factory()->create([
        'slug' => 'other-group',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $membership = GroupMember::factory()
        ->for($otherGroup)
        ->for($requestingUser)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($owner)
        ->patch(route('groups.requests.accept', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('groups.requests.decline', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertNotFound();

    $membership->refresh();

    expect($membership->status)->toBe(GroupMember::STATUS_PENDING);
});

test('non pending memberships are not processed as requests', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $memberUser = User::factory()->create();
    createOnboardedProfile($memberUser);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'active-member-not-a-request',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($memberUser)
        ->create([
            'status' => GroupMember::STATUS_ACTIVE,
        ]);

    $this->actingAs($owner)
        ->patch(route('groups.requests.accept', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('groups.requests.decline', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertNotFound();

    expect(GroupMember::query()->whereKey($membership->id)->exists())->toBeTrue();
});

test('group request management ui exposes forms and processing labels', function () {
    $page = file_get_contents(resource_path('js/pages/Groups/Show.vue'));

    expect($page)
        ->toContain('Beitrittsanfragen')
        ->toContain('Diese Mitglieder möchten deiner Gruppe beitreten.')
        ->toContain('group.can_manage_requests')
        ->toContain('group.pending_requests')
        ->toContain('request.accept_url')
        ->toContain('request.decline_url')
        ->toContain('Wird angenommen...')
        ->toContain('Wird abgelehnt...')
        ->toContain('Annehmen')
        ->toContain('Ablehnen')
        ->toContain('Profil ansehen')
        ->toContain('Aktuell liegen keine Beitrittsanfragen vor.');
});
