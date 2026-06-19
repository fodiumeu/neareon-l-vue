<?php

use App\Enums\ContactPermission;
use App\Enums\ContactRequestStatus;
use App\Enums\FollowPermission;
use App\Enums\ProfileVisibility;
use App\Models\Block;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function relationshipStateUsers(array $profileAttributes = []): array
{
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $other = User::factory()->create();
    $profile = createOnboardedProfile($other, [
        'profile_visibility' => ProfileVisibility::Public,
        ...$profileAttributes,
    ]);

    return [$viewer, $other, $profile];
}

function assertProfileRelationshipState(
    $test,
    User $viewer,
    Profile $profile,
    array $expected,
): void {
    $test->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($expected): void {
            foreach ($expected as $key => $value) {
                $page->where("profile.{$key}", $value);
            }
        });
}

test('profile relationship states are derived from current follows and pending requests', function (
    string $state,
) {
    [$viewer, $other, $profile] = relationshipStateUsers();

    match ($state) {
        'following' => Follow::query()->create([
            'follower_id' => $viewer->id,
            'followed_id' => $other->id,
        ]),
        'followed_by' => Follow::query()->create([
            'follower_id' => $other->id,
            'followed_id' => $viewer->id,
        ]),
        'connected' => [
            Follow::query()->create([
                'follower_id' => $viewer->id,
                'followed_id' => $other->id,
            ]),
            Follow::query()->create([
                'follower_id' => $other->id,
                'followed_id' => $viewer->id,
            ]),
        ],
        'outgoing_request' => ContactRequest::factory()
            ->for($viewer, 'sender')
            ->for($other, 'receiver')
            ->create(),
        'incoming_request' => ContactRequest::factory()
            ->for($other, 'sender')
            ->for($viewer, 'receiver')
            ->create(),
        default => null,
    };

    $expected = match ($state) {
        'none' => [
            'contact_status' => 'none',
            'is_following' => false,
            'is_followed_by' => false,
            'is_mutual' => false,
        ],
        'following' => [
            'contact_status' => 'none',
            'is_following' => true,
            'is_followed_by' => false,
            'is_mutual' => false,
        ],
        'followed_by' => [
            'contact_status' => 'none',
            'is_following' => false,
            'is_followed_by' => true,
            'is_mutual' => false,
        ],
        'connected' => [
            'contact_status' => 'connected',
            'is_following' => true,
            'is_followed_by' => true,
            'is_mutual' => true,
        ],
        'outgoing_request' => [
            'contact_status' => 'outgoing_request',
            'is_following' => false,
            'is_followed_by' => false,
            'is_mutual' => false,
        ],
        'incoming_request' => [
            'contact_status' => 'incoming_request',
            'is_following' => false,
            'is_followed_by' => false,
            'is_mutual' => false,
        ],
    };

    assertProfileRelationshipState($this, $viewer, $profile, $expected);
})->with([
    'none',
    'following',
    'followed_by',
    'connected',
    'outgoing_request',
    'incoming_request',
]);

test('terminal request history does not create an active relationship state', function (
    ContactRequestStatus $status,
) {
    [$viewer, $other, $profile] = relationshipStateUsers();
    ContactRequest::factory()
        ->for($viewer, 'sender')
        ->for($other, 'receiver')
        ->create([
            'status' => $status,
            'responded_at' => now(),
        ]);

    assertProfileRelationshipState($this, $viewer, $profile, [
        'contact_status' => 'none',
        'is_following' => false,
        'is_followed_by' => false,
        'is_mutual' => false,
    ]);
})->with([
    'accepted' => ContactRequestStatus::Accepted,
    'declined' => ContactRequestStatus::Declined,
    'closed' => ContactRequestStatus::Closed,
]);

test('removed connection with restrictive privacy exposes no active actions', function () {
    [$viewer, $other, $profile] = relationshipStateUsers([
        'follow_permission' => FollowPermission::Nobody,
        'contact_permission' => ContactPermission::Nobody,
    ]);
    $acceptedRequest = ContactRequest::factory()
        ->for($viewer, 'sender')
        ->for($other, 'receiver')
        ->create([
            'status' => ContactRequestStatus::Accepted,
            'responded_at' => now()->subDay(),
        ]);

    assertProfileRelationshipState($this, $viewer, $profile, [
        'contact_status' => 'none',
        'is_following' => false,
        'is_followed_by' => false,
        'is_mutual' => false,
        'can_follow' => false,
        'can_send_contact_request' => false,
        'contact_request_unavailable_reason' => 'disabled',
    ]);

    $this->actingAs($viewer)
        ->post(route('public-profile.follow', $profile->username))
        ->assertForbidden();
    $this->actingAs($viewer)
        ->post(route('contact-requests.store'), [
            'receiver_id' => $other->id,
        ])
        ->assertForbidden();

    expect($acceptedRequest->refresh()->status)
        ->toBe(ContactRequestStatus::Accepted);
});

test('follow nobody preserves an existing follow only as a removable factual state', function () {
    [$viewer, $other, $profile] = relationshipStateUsers([
        'follow_permission' => FollowPermission::Nobody,
    ]);
    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $other->id,
    ]);

    assertProfileRelationshipState($this, $viewer, $profile, [
        'contact_status' => 'none',
        'is_following' => true,
        'is_followed_by' => false,
        'is_mutual' => false,
        'can_follow' => false,
    ]);

    $this->actingAs($viewer)
        ->delete(route('public-profile.unfollow', $profile->username))
        ->assertRedirect(route('public-profile.show', $profile->username));

    expect($viewer->isFollowing($other))->toBeFalse();
});

test('an existing pending request remains visible after contact permission is disabled', function () {
    [$viewer, $other, $profile] = relationshipStateUsers([
        'contact_permission' => ContactPermission::Nobody,
    ]);
    ContactRequest::factory()
        ->for($viewer, 'sender')
        ->for($other, 'receiver')
        ->create();

    assertProfileRelationshipState($this, $viewer, $profile, [
        'contact_status' => 'outgoing_request',
        'can_send_contact_request' => false,
        'contact_request_unavailable_reason' => 'disabled',
    ]);
});

test('accepted history can be reactivated after a removed connection when privacy allows it', function () {
    [$viewer, $other] = relationshipStateUsers();
    $acceptedRequest = ContactRequest::factory()
        ->for($viewer, 'sender')
        ->for($other, 'receiver')
        ->create([
            'status' => ContactRequestStatus::Accepted,
            'responded_at' => now()->subDay(),
        ]);

    $this->actingAs($viewer)
        ->post(route('contact-requests.store'), [
            'receiver_id' => $other->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Kontaktanfrage gesendet.');

    expect($acceptedRequest->refresh()->status)
        ->toBe(ContactRequestStatus::Pending)
        ->and(ContactRequest::query()->count())->toBe(1);
});

test('block overrides relationship and privacy presentation', function () {
    [$viewer, $other, $profile] = relationshipStateUsers([
        'follow_permission' => FollowPermission::Everyone,
        'contact_permission' => ContactPermission::Everyone,
    ]);
    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $other->id,
    ]);
    Block::factory()
        ->for($other, 'blocker')
        ->for($viewer, 'blocked')
        ->create();

    assertProfileRelationshipState($this, $viewer, $profile, [
        'contact_status' => 'none',
        'interaction_blocked' => true,
        'can_follow' => false,
        'can_send_contact_request' => false,
    ]);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('profiles', 0));
});

test('profile and discover use identical field visibility decisions', function () {
    [$viewer, $other, $profile] = relationshipStateUsers([
        'region' => 'Berlin',
        'region_visibility' => ProfileVisibility::Followers,
        'languages_visibility' => ProfileVisibility::Followers,
        'interests_visibility' => ProfileVisibility::Followers,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('profile.region')
            ->missing('profile.languages')
            ->missing('profile.interests'),
        );
    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('profiles.0.region')
            ->missing('profiles.0.languages')
            ->missing('profiles.0.interests'),
        );

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $other->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.region', 'Berlin')
            ->has('profile.languages')
            ->has('profile.interests'),
        );
    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profiles.0.region', 'Berlin')
            ->has('profiles.0.languages')
            ->has('profiles.0.interests'),
        );
});
