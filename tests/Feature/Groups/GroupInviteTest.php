<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owner can create and rotate an invite token for a private group', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'private-invite-token',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);

    $this->actingAs($owner)
        ->post(route('groups.invite-token.store', $group->slug))
        ->assertSessionHas('success', 'Einladungslink wurde erstellt.')
        ->assertRedirect(route('groups.show', [
            'group' => $group->slug,
            'from' => 'my-groups',
        ]));

    $group->refresh();
    $firstToken = $group->invite_token;

    expect($firstToken)
        ->toBeString()
        ->toHaveLength(48)
        ->and($group->invite_token_created_at)->not->toBeNull();

    $this->actingAs($owner)
        ->post(route('groups.invite-token.store', $group->slug))
        ->assertSessionHas('success', 'Einladungslink wurde erneuert.');

    $group->refresh();

    expect($group->invite_token)
        ->toBeString()
        ->toHaveLength(48)
        ->not->toBe($firstToken);
});

test('admin can create an invite token for a private group', function () {
    $admin = User::factory()->admin()->create();
    createOnboardedProfile($admin);
    $group = Group::factory()->create([
        'slug' => 'admin-private-invite-token',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);

    $this->actingAs($admin)
        ->post(route('groups.invite-token.store', $group->slug))
        ->assertSessionHas('success', 'Einladungslink wurde erstellt.');

    expect($group->refresh()->invite_token)
        ->toBeString()
        ->toHaveLength(48);
});

test('non owners cannot create invite tokens and public groups do not expose invite management', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $privateGroup = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'non-owner-private-invite',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    $publicGroup = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'public-invite-not-supported',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->post(route('groups.invite-token.store', $privateGroup->slug))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('groups.invite-token.store', $publicGroup->slug))
        ->assertNotFound();

    $this->actingAs($owner)
        ->get(route('groups.show', $publicGroup->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.can_manage_invite', false)
            ->where('group.invite_url', null)
            ->where('group.invite_token_url', null),
        );
});

test('private group remains hidden without invite and visible with valid invite token', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'private-visible-via-invite',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    $group->rotateInviteToken();

    $this->actingAs($viewer)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 0),
        );

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->slug))
        ->assertNotFound();

    $this->actingAs($viewer)
        ->get(route('groups.invite.show', $group->invite_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.id', $group->id)
            ->where('group.visibility', Group::VISIBILITY_PRIVATE)
            ->where('group.invite_context', true)
            ->where('group.can_join', true)
            ->where('group.join_label', 'Gruppe beitreten')
            ->where('group.join_url', route('groups.invite.join', $group->invite_token)),
        );
});

test('invite visitor receives no owner or request management capabilities', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $pendingUser = User::factory()->create();
    createOnboardedProfile($pendingUser);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'invite-visitor-no-management',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    $group->rotateInviteToken();
    GroupMember::factory()
        ->for($group)
        ->for($pendingUser)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($viewer)
        ->get(route('groups.invite.show', $group->invite_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('auth.user.role', 'member')
            ->where('group.invite_context', true)
            ->where('group.can_edit', false)
            ->where('group.edit_url', null)
            ->where('group.can_manage_requests', false)
            ->where('group.can_manage_invite', false)
            ->where('group.invite_url', null)
            ->where('group.invite_token_url', null)
            ->where('group.can_leave', false)
            ->where('group.leave_url', null)
            ->where('group.viewer_role', null)
            ->where('group.viewer_membership_status', null)
            ->has('group.pending_requests', 0),
        );

    $this->actingAs($viewer)
        ->get(route('groups.edit', $group->slug))
        ->assertForbidden();
});

test('invalid invite token is not found and guests are redirected to login', function () {
    $group = Group::factory()->create([
        'slug' => 'guest-private-invite',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    $group->rotateInviteToken();

    $this->get(route('groups.invite.show', $group->invite_token))
        ->assertRedirect(route('login'));

    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $this->actingAs($viewer)
        ->get(route('groups.invite.show', 'invalid-token'))
        ->assertNotFound();
});

test('invited member can join private group through invite link', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'join-private-via-invite',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    $group->rotateInviteToken();

    $this->actingAs($viewer)
        ->post(route('groups.invite.join', $group->invite_token))
        ->assertSessionHas('success', 'Du bist der Gruppe beigetreten.')
        ->assertRedirect(route('groups.show', [
            'group' => $group->slug,
            'from' => 'my-groups',
        ]));

    $membership = GroupMember::query()
        ->where('group_id', $group->id)
        ->where('user_id', $viewer->id)
        ->firstOrFail();

    expect($membership)
        ->role->toBe(GroupMember::ROLE_MEMBER)
        ->status->toBe(GroupMember::STATUS_ACTIVE)
        ->joined_at->not->toBeNull();

    $this->actingAs($viewer)
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->has('groups.data', 1)
            ->where('groups.data.0.id', $group->id)
            ->where('groups.data.0.membership.status', GroupMember::STATUS_ACTIVE),
        );
});

test('invite join does not duplicate active members and upgrades existing pending records', function () {
    $activeMember = User::factory()->create();
    createOnboardedProfile($activeMember);
    $pendingMember = User::factory()->create();
    createOnboardedProfile($pendingMember);
    $group = Group::factory()->create([
        'slug' => 'invite-no-duplicates',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    $group->rotateInviteToken();
    GroupMember::factory()
        ->for($group)
        ->for($activeMember)
        ->create([
            'status' => GroupMember::STATUS_ACTIVE,
        ]);
    $pendingMembership = GroupMember::factory()
        ->for($group)
        ->for($pendingMember)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($activeMember)
        ->post(route('groups.invite.join', $group->invite_token))
        ->assertSessionHas('success', 'Du bist bereits Mitglied dieser Gruppe.');

    expect(GroupMember::query()
        ->where('group_id', $group->id)
        ->where('user_id', $activeMember->id)
        ->count())->toBe(1);

    $this->actingAs($pendingMember)
        ->post(route('groups.invite.join', $group->invite_token))
        ->assertSessionHas('success', 'Du bist der Gruppe beigetreten.');

    $pendingMembership->refresh();

    expect($pendingMembership)
        ->status->toBe(GroupMember::STATUS_ACTIVE)
        ->joined_at->not->toBeNull();
});

test('owner cannot join through own invite link', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'owner-own-invite',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    $group->rotateInviteToken();

    $this->actingAs($owner)
        ->post(route('groups.invite.join', $group->invite_token))
        ->assertForbidden();

    expect(GroupMember::query()
        ->where('group_id', $group->id)
        ->where('user_id', $owner->id)
        ->where('role', GroupMember::ROLE_MEMBER)
        ->exists())->toBeFalse();
});

test('rotated invite token invalidates the old invite link', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'rotated-invite',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);

    $group->rotateInviteToken();
    $oldToken = $group->invite_token;
    $group->rotateInviteToken();

    $this->actingAs($viewer)
        ->get(route('groups.invite.show', $oldToken))
        ->assertNotFound();

    $this->actingAs($viewer)
        ->get(route('groups.invite.show', $group->invite_token))
        ->assertOk();
});

test('group detail UI exposes invite management only for private group managers', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'private-invite-ui',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    $group->rotateInviteToken();

    $this->actingAs($owner)
        ->get(route('groups.show', $group->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.can_manage_invite', true)
            ->where('group.invite_url', route('groups.invite.show', $group->invite_token))
            ->where('group.invite_token_url', route('groups.invite-token.store', $group->slug)),
        );

    $this->actingAs($viewer)
        ->get(route('groups.invite.show', $group->invite_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.can_manage_invite', false)
            ->where('group.can_edit', false)
            ->where('group.edit_url', null)
            ->where('group.invite_url', null)
            ->where('group.invite_token_url', null),
        );
});

test('admin navigation remains tied to global user role and not invite access', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($sidebar)
        ->toContain("user?.role === 'admin' || user?.role === 'owner'")
        ->not->toContain('invite_context')
        ->not->toContain('can_edit');
});

test('group invite UI contains owner and invited visitor copy', function () {
    $page = file_get_contents(resource_path('js/pages/Groups/Show.vue'));

    expect($page)
        ->toContain('Einladungslink')
        ->toContain('Teile diesen Link mit Personen')
        ->toContain('Gruppe beitreten sollen.')
        ->toContain('Link kopieren')
        ->toContain('Link erneuern')
        ->toContain('Einladungslink erstellen')
        ->toContain('Du wurdest über einen Einladungslink eingeladen.')
        ->toContain('group.invite_context')
        ->toContain('group.invite_token_url')
        ->toContain('group.invite_url');
});
