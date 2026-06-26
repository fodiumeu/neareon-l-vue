<?php

use App\Enums\InternalNotificationType;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\NavigationBadgeService;
use Inertia\Testing\AssertableInertia as Assert;

test('request group join creates notification for group owner once', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer, [
        'display_name' => 'Requesting Member',
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Request Notify Group',
        'slug' => 'request-notify-group',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug))
        ->assertSessionHas('success', 'Deine Beitrittsanfrage wurde gesendet.');

    $notification = $owner->notifications()->sole();

    expect($notification->data)
        ->type->toBe(InternalNotificationType::GroupJoinRequestReceived->value)
        ->title->toBe('Neue Beitrittsanfrage')
        ->message->toBe('Requesting Member möchte deiner Gruppe Request Notify Group beitreten.')
        ->target_url->toBe(route('groups.show', $group->slug, absolute: false))
        ->actor_id->toBe($viewer->id)
        ->group_id->toBe($group->id)
        ->group_name->toBe('Request Notify Group')
        ->group_slug->toBe('request-notify-group');

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug))
        ->assertSessionHas('success', 'Deine Beitrittsanfrage wurde bereits gesendet.');

    expect($owner->notifications()->count())->toBe(1);
});

test('accepting group join request notifies applicant', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner, [
        'display_name' => 'Group Owner',
    ]);
    $applicant = User::factory()->create();
    createOnboardedProfile($applicant);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Accepted Group',
        'slug' => 'accepted-group',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($applicant)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($owner)
        ->patch(route('groups.requests.accept', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertSessionHas('success', 'Anfrage angenommen.');

    $membership->refresh();
    $notification = $applicant->notifications()->sole();

    expect($membership->status)
        ->toBe(GroupMember::STATUS_ACTIVE)
        ->and($notification->data)
        ->type->toBe(InternalNotificationType::GroupJoinRequestAccepted->value)
        ->title->toBe('Beitrittsanfrage angenommen')
        ->message->toBe('Du bist jetzt Mitglied in Accepted Group.')
        ->target_url->toBe(route('groups.show', $group->slug, absolute: false))
        ->actor_id->toBe($owner->id)
        ->group_id->toBe($group->id);
});

test('declining group join request notifies applicant with safe target', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $applicant = User::factory()->create();
    createOnboardedProfile($applicant);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Declined Group',
        'slug' => 'declined-group',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($applicant)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($owner)
        ->delete(route('groups.requests.decline', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]))
        ->assertSessionHas('success', 'Anfrage abgelehnt.');

    $notification = $applicant->notifications()->sole();

    expect(GroupMember::query()->whereKey($membership->id)->exists())
        ->toBeFalse()
        ->and($notification->data)
        ->type->toBe(InternalNotificationType::GroupJoinRequestDeclined->value)
        ->title->toBe('Beitrittsanfrage abgelehnt')
        ->message->toBe('Deine Anfrage für Declined Group wurde nicht angenommen.')
        ->target_url->toBe(route('groups.index', absolute: false))
        ->actor_id->toBe($owner->id)
        ->group_id->toBe($group->id);
});

test('public join creates group member joined notification for owner without duplicates', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer, [
        'display_name' => 'Public Member',
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Public Notify Group',
        'slug' => 'public-notify-group',
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug))
        ->assertSessionHas('success', 'Du bist der Gruppe beigetreten.');

    $notification = $owner->notifications()->sole();

    expect($notification->data)
        ->type->toBe(InternalNotificationType::GroupMemberJoined->value)
        ->title->toBe('Neues Gruppenmitglied')
        ->message->toBe('Public Member ist deiner Gruppe Public Notify Group beigetreten.')
        ->target_url->toBe(route('groups.show', $group->slug, absolute: false));

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug))
        ->assertSessionHas('success', 'Du bist bereits Mitglied dieser Gruppe.');

    expect($owner->notifications()->count())->toBe(1);
});

test('private invite join creates group member joined notification for owner', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer, [
        'display_name' => 'Invite Member',
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Invite Notify Group',
        'slug' => 'invite-notify-group',
        'visibility' => Group::VISIBILITY_PRIVATE,
    ]);
    $group->rotateInviteToken();

    $this->actingAs($viewer)
        ->post(route('groups.invite.join', $group->invite_token))
        ->assertSessionHas('success', 'Du bist der Gruppe beigetreten.');

    $notification = $owner->notifications()->sole();

    expect($notification->data)
        ->type->toBe(InternalNotificationType::GroupMemberJoined->value)
        ->message->toBe('Invite Member ist deiner Gruppe Invite Notify Group beigetreten.')
        ->target_url->toBe(route('groups.show', $group->slug, absolute: false))
        ->group_id->toBe($group->id);
});

test('removing a group member notifies the removed user with a safe discovery target', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Removal Notify Group',
        'slug' => 'removal-notify-group',
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
        ->assertSessionHas('success', 'Mitglied wurde aus der Gruppe entfernt.');

    $notification = $member->notifications()->sole();

    expect($owner->notifications()->count())
        ->toBe(0)
        ->and($notification->data)
        ->type->toBe(InternalNotificationType::GroupMemberRemoved->value)
        ->title->toBe('Aus Gruppe entfernt')
        ->message->toBe('Du wurdest aus der Gruppe Removal Notify Group entfernt.')
        ->target_url->toBe(route('groups.index', absolute: false))
        ->actor_id->toBe($owner->id)
        ->group_id->toBe($group->id)
        ->group_name->toBe('Removal Notify Group')
        ->group_slug->toBe('removal-notify-group');
});

test('leaving a group does not create a management removal notification', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'self-leave-notify-group',
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create(['role' => GroupMember::ROLE_OWNER]);
    GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create(['role' => GroupMember::ROLE_MEMBER]);

    $this->actingAs($member)
        ->delete(route('groups.membership.destroy', $group->slug))
        ->assertSessionHas('success', 'Du hast die Gruppe verlassen.');

    expect($member->notifications()->count())->toBe(0)
        ->and($owner->notifications()->count())->toBe(0);
});

test('promoting a member to moderator notifies the affected user', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Promote Notify Group',
        'slug' => 'promote-notify-group',
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
        ->patch(route('groups.members.role.update', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]), [
            'role' => GroupMember::ROLE_MODERATOR,
        ])
        ->assertSessionHas('success', 'Mitglied wurde zum Moderator gemacht.');

    $notification = $member->notifications()->sole();

    expect($membership->refresh()->role)
        ->toBe(GroupMember::ROLE_MODERATOR)
        ->and($notification->data)
        ->type->toBe(InternalNotificationType::GroupModeratorPromoted->value)
        ->title->toBe('Moderatorrolle erhalten')
        ->message->toBe('Du wurdest in der Gruppe Promote Notify Group zum Moderator gemacht.')
        ->target_url->toBe(route('groups.show', $group->slug, absolute: false))
        ->actor_id->toBe($owner->id)
        ->group_id->toBe($group->id);
});

test('demoting a moderator to member notifies the affected user', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $moderator = User::factory()->create();
    createOnboardedProfile($moderator);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Demote Notify Group',
        'slug' => 'demote-notify-group',
    ]);
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
        ->assertSessionHas('success', 'Moderator wurde zum Mitglied zurückgestuft.');

    $notification = $moderator->notifications()->sole();

    expect($membership->refresh()->role)
        ->toBe(GroupMember::ROLE_MEMBER)
        ->and($notification->data)
        ->type->toBe(InternalNotificationType::GroupModeratorDemoted->value)
        ->title->toBe('Moderatorrolle entfernt')
        ->message->toBe('Du bist in der Gruppe Demote Notify Group wieder Mitglied.')
        ->target_url->toBe(route('groups.show', $group->slug, absolute: false))
        ->actor_id->toBe($owner->id)
        ->group_id->toBe($group->id);
});

test('failed group member management actions do not create notifications', function () {
    $regular = User::factory()->create();
    createOnboardedProfile($regular);
    $target = User::factory()->create();
    createOnboardedProfile($target);
    $group = Group::factory()->create([
        'visibility' => Group::VISIBILITY_PUBLIC,
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($regular)
        ->create(['role' => GroupMember::ROLE_MEMBER]);
    $targetMembership = GroupMember::factory()
        ->for($group)
        ->for($target)
        ->create(['role' => GroupMember::ROLE_MEMBER]);

    $this->actingAs($regular)
        ->delete(route('groups.members.destroy', [
            'group' => $group->slug,
            'member' => $targetMembership->id,
        ]))
        ->assertForbidden();

    $this->actingAs($regular)
        ->patch(route('groups.members.role.update', [
            'group' => $group->slug,
            'member' => $targetMembership->id,
        ]), [
            'role' => GroupMember::ROLE_MODERATOR,
        ])
        ->assertForbidden();

    expect($target->notifications()->count())->toBe(0);
});

test('notifications page renders group notifications and marks them read', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer, [
        'display_name' => 'Visible Applicant',
    ]);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Rendered Group',
        'slug' => 'rendered-group',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);

    $this->actingAs($viewer)
        ->post(route('groups.join', $group->slug));

    expect(app(NavigationBadgeService::class)
        ->countsFor($owner)['unreadNotifications'])->toBe(1);

    $this->actingAs($owner)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notificationItems', 1)
            ->where('notificationItems.0.type', InternalNotificationType::GroupJoinRequestReceived->value)
            ->where('notificationItems.0.title', 'Neue Beitrittsanfrage')
            ->where('notificationItems.0.message', 'Visible Applicant möchte deiner Gruppe Rendered Group beitreten.')
            ->where('notificationItems.0.cta_label', 'Gruppe öffnen')
            ->where('notificationItems.0.actor.display_name', 'Visible Applicant'),
        );

    expect(app(NavigationBadgeService::class)
        ->countsFor($owner)['unreadNotifications'])->toBe(0);
});

test('declined group notification renders groups discovery cta', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $applicant = User::factory()->create();
    createOnboardedProfile($applicant);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Rendered Decline Group',
        'slug' => 'rendered-decline-group',
        'visibility' => Group::VISIBILITY_REQUEST,
    ]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($applicant)
        ->create([
            'status' => GroupMember::STATUS_PENDING,
            'joined_at' => null,
        ]);

    $this->actingAs($owner)
        ->delete(route('groups.requests.decline', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]));

    $this->actingAs($applicant)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('notificationItems.0.type', InternalNotificationType::GroupJoinRequestDeclined->value)
            ->where('notificationItems.0.title', 'Beitrittsanfrage abgelehnt')
            ->where('notificationItems.0.cta_label', 'Gruppen entdecken')
            ->where('notificationItems.0.target_url', route('groups.index', absolute: false)),
        );
});

test('member management notifications render contextual ctas', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $member = User::factory()->create();
    createOnboardedProfile($member);
    $removedMember = User::factory()->create();
    createOnboardedProfile($removedMember);
    $group = Group::factory()->for($owner, 'owner')->create([
        'name' => 'Rendered Member Management Group',
        'slug' => 'rendered-member-management-group',
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($owner)
        ->create(['role' => GroupMember::ROLE_OWNER]);
    $membership = GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create(['role' => GroupMember::ROLE_MEMBER]);
    $removedMembership = GroupMember::factory()
        ->for($group)
        ->for($removedMember)
        ->create(['role' => GroupMember::ROLE_MEMBER]);

    $this->actingAs($owner)
        ->patch(route('groups.members.role.update', [
            'group' => $group->slug,
            'member' => $membership->id,
        ]), [
            'role' => GroupMember::ROLE_MODERATOR,
        ]);

    $this->actingAs($member)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('notificationItems.0.type', InternalNotificationType::GroupModeratorPromoted->value)
            ->where('notificationItems.0.title', 'Moderatorrolle erhalten')
            ->where('notificationItems.0.cta_label', 'Gruppe öffnen')
            ->where('notificationItems.0.target_url', route('groups.show', $group->slug, absolute: false)),
        );

    $this->actingAs($owner)
        ->delete(route('groups.members.destroy', [
            'group' => $group->slug,
            'member' => $removedMembership->id,
        ]));

    $this->actingAs($removedMember)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('notificationItems.0.type', InternalNotificationType::GroupMemberRemoved->value)
            ->where('notificationItems.0.title', 'Aus Gruppe entfernt')
            ->where('notificationItems.0.cta_label', 'Gruppen entdecken')
            ->where('notificationItems.0.target_url', route('groups.index', absolute: false)),
        );
});
