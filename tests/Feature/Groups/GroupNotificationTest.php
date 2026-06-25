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
