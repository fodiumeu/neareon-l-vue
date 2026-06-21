<?php

use App\Enums\ContactPermission;
use App\Enums\FollowPermission;
use App\Enums\MessagePermission;
use App\Enums\OnlineStatusVisibility;
use App\Enums\ProfileVisibility;
use App\Models\Block;
use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Follow;
use App\Models\Message;
use App\Models\Profile;
use App\Models\User;
use App\Services\PrivacyService;
use Inertia\Testing\AssertableInertia as Assert;

function createPrivacyConversation(User $userA, User $userB): Conversation
{
    $conversation = Conversation::factory()->create();

    ConversationParticipant::factory()
        ->for($conversation)
        ->for($userA)
        ->create();
    ConversationParticipant::factory()
        ->for($conversation)
        ->for($userB)
        ->create();

    return $conversation;
}

function createPrivacyFollow(User $follower, User $followed): Follow
{
    return Follow::query()->create([
        'follower_id' => $follower->id,
        'followed_id' => $followed->id,
    ]);
}

test('privacy fields use the required defaults', function () {
    $user = User::factory()->create();
    $profile = Profile::query()->create([
        'user_id' => $user->id,
        'username' => 'privacy_defaults',
        'display_name' => 'Privacy Defaults',
    ])->fresh();

    expect($profile->profile_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->follow_permission)->toBe(FollowPermission::Everyone)
        ->and($profile->contact_permission)->toBe(ContactPermission::Everyone)
        ->and($profile->message_permission)->toBe(MessagePermission::ExistingConversations)
        ->and($profile->online_status_visibility)->toBe(OnlineStatusVisibility::MutualContacts);
});

test('users can update their privacy settings', function () {
    $user = User::factory()->create();
    $profile = createOnboardedProfile($user);

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), [
            'display_name' => $profile->display_name,
            'bio' => $profile->bio,
            'region' => $profile->region,
            'languages' => $profile->languageOptions()->pluck('code')->all(),
            'interests' => $profile->interestOptions()->pluck('slug')->all(),
            'profile_visibility' => ProfileVisibility::Members->value,
            'follow_permission' => FollowPermission::Nobody->value,
            'contact_permission' => ContactPermission::Followers->value,
            'message_permission' => MessagePermission::ContactsOnly->value,
            'online_status_visibility' => OnlineStatusVisibility::Nobody->value,
            'interests_visibility' => $profile->interests_visibility->value,
            'languages_visibility' => $profile->languages_visibility->value,
            'region_visibility' => $profile->region_visibility->value,
            'social_counts_visibility' => $profile->social_counts_visibility->value,
        ])
        ->assertRedirect(route('neareon-profile.edit'));

    $profile->refresh();

    expect($profile->profile_visibility)->toBe(ProfileVisibility::Members)
        ->and($profile->follow_permission)->toBe(FollowPermission::Nobody)
        ->and($profile->contact_permission)->toBe(ContactPermission::Followers)
        ->and($profile->message_permission)->toBe(MessagePermission::ContactsOnly)
        ->and($profile->online_status_visibility)->toBe(OnlineStatusVisibility::Nobody);
});

test('contact-only profiles are hidden from discover and direct urls for non-contacts', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = createOnboardedProfile($owner, [
        'username' => 'contacts_only_profile',
        'profile_visibility' => ProfileVisibility::Contacts,
    ]);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('profiles.data', 0));

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertForbidden();

    createPrivacyFollow($viewer, $owner);
    createPrivacyFollow($owner, $viewer);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk();
});

test('follow permission is enforced server side', function (
    FollowPermission $permission,
    int $expectedStatus,
) {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = createOnboardedProfile($owner, [
        'follow_permission' => $permission,
    ]);

    $this->actingAs($viewer)
        ->post(route('public-profile.follow', $profile->username))
        ->assertStatus($expectedStatus);

    expect($viewer->isFollowing($owner))
        ->toBe($expectedStatus !== 403);
})->with([
    'everyone' => [FollowPermission::Everyone, 302],
    'members' => [FollowPermission::Members, 302],
    'nobody' => [FollowPermission::Nobody, 403],
]);

test('contact permission allows followers and rejects other users', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver, [
        'contact_permission' => ContactPermission::Followers,
    ]);

    $this->actingAs($sender)
        ->post(route('contact-requests.store'), [
            'receiver_id' => $receiver->id,
        ])
        ->assertForbidden();

    createPrivacyFollow($sender, $receiver);

    $this->actingAs($sender)
        ->post(route('contact-requests.store'), [
            'receiver_id' => $receiver->id,
        ])
        ->assertRedirect();

    expect(ContactRequest::query()->count())->toBe(1);
});

test('contact permission nobody rejects requests', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver, [
        'contact_permission' => ContactPermission::Nobody,
    ]);
    createPrivacyFollow($sender, $receiver);

    $this->actingAs($sender)
        ->post(route('contact-requests.store'), [
            'receiver_id' => $receiver->id,
        ])
        ->assertForbidden();

    expect(ContactRequest::query()->exists())->toBeFalse();
});

test('contacts-only messaging requires a mutual contact', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver, [
        'message_permission' => MessagePermission::ContactsOnly,
    ]);
    $conversation = createPrivacyConversation($sender, $receiver);

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Nicht erlaubt',
        ])
        ->assertForbidden();

    createPrivacyFollow($sender, $receiver);
    createPrivacyFollow($receiver, $sender);

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Jetzt erlaubt',
        ])
        ->assertRedirect(route('messages.show', $conversation));

    expect(Message::query()->count())->toBe(1);
});

test('existing-conversation messaging still requires an active contact', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver, [
        'message_permission' => MessagePermission::ExistingConversations,
    ]);
    $conversation = createPrivacyConversation($sender, $receiver);

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Bestehende Unterhaltung',
        ])
        ->assertForbidden();

    expect(Message::query()->count())->toBe(0);

    createPrivacyFollow($sender, $receiver);
    createPrivacyFollow($receiver, $sender);

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Bestehende Unterhaltung',
        ])
        ->assertRedirect(route('messages.show', $conversation));

    expect(Message::query()->count())->toBe(1);
});

test('message history remains readable when sending is forbidden', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $otherUser = User::factory()->create();
    createOnboardedProfile($otherUser, [
        'message_permission' => MessagePermission::ContactsOnly,
    ]);
    $conversation = createPrivacyConversation($viewer, $otherUser);
    Message::factory()
        ->for($conversation)
        ->for($otherUser, 'sender')
        ->create(['body' => 'Bleibt sichtbar']);

    $this->actingAs($viewer)
        ->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation.can_send_messages', false)
            ->where('conversation.messages.0.body', 'Bleibt sichtbar'),
        );
});

test('blocks override permissive privacy settings', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = createOnboardedProfile($owner, [
        'profile_visibility' => ProfileVisibility::Public,
        'follow_permission' => FollowPermission::Everyone,
        'contact_permission' => ContactPermission::Everyone,
        'message_permission' => MessagePermission::ExistingConversations,
    ]);
    $conversation = createPrivacyConversation($viewer, $owner);
    Block::factory()
        ->for($owner, 'blocker')
        ->for($viewer, 'blocked')
        ->create();

    $this->actingAs($viewer)
        ->post(route('public-profile.follow', $profile->username))
        ->assertForbidden();
    $this->actingAs($viewer)
        ->post(route('contact-requests.store'), [
            'receiver_id' => $owner->id,
        ])
        ->assertForbidden();
    $this->actingAs($viewer)
        ->post(route('messages.store', $conversation), [
            'message' => 'Trotzdem nicht erlaubt',
        ])
        ->assertForbidden();

    expect(Follow::query()->exists())->toBeFalse()
        ->and(ContactRequest::query()->exists())->toBeFalse()
        ->and(Message::query()->exists())->toBeFalse();
});

test('online status visibility logic is prepared without exposing live status', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    createOnboardedProfile($owner, [
        'online_status_visibility' => OnlineStatusVisibility::MutualContacts,
    ]);
    $privacy = app(PrivacyService::class);

    expect($privacy->canViewOnlineStatus($viewer, $owner))->toBeFalse();

    createPrivacyFollow($viewer, $owner);
    createPrivacyFollow($owner, $viewer);

    expect($privacy->canViewOnlineStatus($viewer, $owner))->toBeTrue();
});
