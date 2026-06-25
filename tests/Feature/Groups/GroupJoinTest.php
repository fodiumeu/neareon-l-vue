<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot join groups', function () {
    $group = Group::factory()->create([
        'slug' => 'guest-join-group',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->post(route('groups.join', $group->slug))
        ->assertRedirect(route('login'));
});

test('authenticated members can join public groups directly', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'public-join-group',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug))
        ->assertSessionHas('success', 'Du bist der Gruppe beigetreten.')
        ->assertRedirect(route('groups.show', $group->slug));

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
            ->where('groups.data.0.membership.role_label', 'Mitglied')
            ->where('groups.data.0.membership.status', GroupMember::STATUS_ACTIVE),
        );
});

test('authenticated members can request access to request groups', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'request-join-group',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug))
        ->assertSessionHas('success', 'Deine Beitrittsanfrage wurde gesendet.')
        ->assertRedirect(route('groups.show', $group->slug));

    $membership = GroupMember::query()
        ->where('group_id', $group->id)
        ->where('user_id', $viewer->id)
        ->firstOrFail();

    expect($membership)
        ->role->toBe(GroupMember::ROLE_MEMBER)
        ->status->toBe(GroupMember::STATUS_PENDING)
        ->joined_at->toBeNull();

    $this->actingAs($viewer)
        ->get(route('groups.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MyGroups')
            ->has('groups.data', 1)
            ->where('groups.data.0.id', $group->id)
            ->where('groups.data.0.membership.status_label', 'Anfrage ausstehend'),
        );
});

test('public join from groups index keeps groups backlink context', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'public-context-join',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug), [
            'return_to' => 'groups',
        ])
        ->assertSessionHas('success', 'Du bist der Gruppe beigetreten.')
        ->assertRedirect(route('groups.show', [
            'group' => $group->slug,
            'from' => 'groups',
        ]));

    $this->actingAs($viewer)
        ->get(route('groups.show', [
            'group' => $group->slug,
            'from' => 'groups',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.back_url', route('groups.index'))
            ->where('group.back_label', 'Zurück zu Gruppen entdecken'),
        );
});

test('request join from groups index keeps groups backlink context', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'request-context-join',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug), [
            'return_to' => 'groups',
        ])
        ->assertSessionHas('success', 'Deine Beitrittsanfrage wurde gesendet.')
        ->assertRedirect(route('groups.show', [
            'group' => $group->slug,
            'from' => 'groups',
        ]));

    $this->actingAs($viewer)
        ->get(route('groups.show', [
            'group' => $group->slug,
            'from' => 'groups',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('group.back_url', route('groups.index'))
            ->where('group.back_label', 'Zurück zu Gruppen entdecken'),
        );
});

test('join ignores invalid backlink context values', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'invalid-context-join',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug), [
            'return_to' => 'https://example.com/evil',
        ])
        ->assertSessionHas('success', 'Du bist der Gruppe beigetreten.')
        ->assertRedirect(route('groups.show', $group->slug));
});

test('private groups cannot be joined by non members', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'private-join-forbidden',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Index')
            ->has('groups.data', 0),
        );

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug))
        ->assertNotFound();

    $this->actingAs($owner)
        ->get(route('groups.show', $group->slug))
        ->assertOk();
});

test('existing active memberships are not duplicated by join attempts', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'duplicate-active-join',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($viewer)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_ACTIVE,
        ]);

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug))
        ->assertSessionHas('success', 'Du bist bereits Mitglied dieser Gruppe.')
        ->assertRedirect(route('groups.show', $group->slug));

    expect(GroupMember::query()
        ->where('group_id', $group->id)
        ->where('user_id', $viewer->id)
        ->count())->toBe(1);
});

test('existing pending memberships are not duplicated by request attempts', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->create([
        'slug' => 'duplicate-pending-request',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($viewer)
        ->create([
            'role' => GroupMember::ROLE_MEMBER,
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug))
        ->assertSessionHas('success', 'Deine Beitrittsanfrage wurde bereits gesendet.')
        ->assertRedirect(route('groups.show', $group->slug));

    expect(GroupMember::query()
        ->where('group_id', $group->id)
        ->where('user_id', $viewer->id)
        ->count())->toBe(1);
});

test('group owners cannot join their own groups', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'owner-cannot-join',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($owner)
        ->post(route('groups.join', $group->slug))
        ->assertForbidden();

    expect(GroupMember::query()
        ->where('group_id', $group->id)
        ->where('user_id', $owner->id)
        ->where('role', GroupMember::ROLE_MEMBER)
        ->exists())->toBeFalse();
});
