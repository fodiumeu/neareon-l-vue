<?php

use App\Enums\ContactRequestStatus;
use App\Enums\ProfileVisibility;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot follow profiles', function () {
    $profile = Profile::factory()->create([
        'username' => 'guest_follow',
    ]);

    $this->post(route('public-profile.follow', $profile->username))
        ->assertRedirect(route('login'));
});

test('users without a profile cannot follow and are redirected to onboarding', function () {
    $viewer = User::factory()->create();
    $profile = Profile::factory()->create([
        'username' => 'needs_onboarding',
    ]);

    $this->actingAs($viewer)
        ->post(route('public-profile.follow', $profile->username))
        ->assertRedirect(route('onboarding.details'));

    expect(Follow::query()->exists())->toBeFalse();
});

test('users with a profile can follow another profile', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $target = User::factory()->create();
    $targetProfile = Profile::factory()->for($target)->create([
        'username' => 'follow_target',
    ]);

    $this->actingAs($viewer)
        ->post(route('public-profile.follow', $targetProfile->username))
        ->assertRedirect(route('public-profile.show', $targetProfile->username));

    expect($viewer->isFollowing($target))->toBeTrue()
        ->and(Follow::query()->count())->toBe(1);
});

test('users can unfollow another profile', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $target = User::factory()->create();
    $targetProfile = Profile::factory()->for($target)->create([
        'username' => 'unfollow_target',
    ]);

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $target->id,
    ]);

    $this->actingAs($viewer)
        ->delete(route('public-profile.unfollow', $targetProfile->username))
        ->assertRedirect(route('public-profile.show', $targetProfile->username));

    expect($viewer->isFollowing($target))->toBeFalse()
        ->and(Follow::query()->count())->toBe(0);
});

test('following from group members keeps the profile backlink context', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $target = User::factory()->create();
    $targetProfile = Profile::factory()->for($target)->create([
        'username' => 'group_context_follow_target',
    ]);
    $group = Group::factory()->for($viewer, 'owner')->create([
        'slug' => 'follow-context-group',
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($viewer)
        ->create([
            'role' => GroupMember::ROLE_OWNER,
        ]);

    $this->actingAs($viewer)
        ->post(route('public-profile.follow', $targetProfile->username), [
            'from' => 'group-members',
            'group' => $group->slug,
        ])
        ->assertRedirect(route('public-profile.show', [
            'username' => $targetProfile->username,
            'from' => 'group-members',
            'group' => $group->slug,
        ]));

    expect($viewer->isFollowing($target))->toBeTrue();
});

test('unfollowing from group members keeps the profile backlink context', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $target = User::factory()->create();
    $targetProfile = Profile::factory()->for($target)->create([
        'username' => 'group_context_unfollow_target',
    ]);
    $group = Group::factory()->for($viewer, 'owner')->create([
        'slug' => 'unfollow-context-group',
    ]);
    GroupMember::factory()
        ->for($group)
        ->for($viewer)
        ->create([
            'role' => GroupMember::ROLE_OWNER,
        ]);
    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $target->id,
    ]);

    $this->actingAs($viewer)
        ->delete(route('public-profile.unfollow', $targetProfile->username), [
            'from' => 'group-members',
            'group' => $group->slug,
        ])
        ->assertRedirect(route('public-profile.show', [
            'username' => $targetProfile->username,
            'from' => 'group-members',
            'group' => $group->slug,
        ]));

    expect($viewer->isFollowing($target))->toBeFalse();
});

test('follow actions ignore invalid backlink context values', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $target = User::factory()->create();
    $targetProfile = Profile::factory()->for($target)->create([
        'username' => 'invalid_group_context_follow_target',
    ]);

    $this->actingAs($viewer)
        ->post(route('public-profile.follow', $targetProfile->username), [
            'from' => 'https://example.com/evil',
            'group' => 'missing-group',
        ])
        ->assertRedirect(route('public-profile.show', $targetProfile->username));

    expect($viewer->isFollowing($target))->toBeTrue();
});

test('unfollowing closes an accepted contact request', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $target = User::factory()->create();
    $targetProfile = Profile::factory()->for($target)->create();
    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $target->id,
    ]);
    Follow::query()->create([
        'follower_id' => $target->id,
        'followed_id' => $viewer->id,
    ]);
    $contactRequest = ContactRequest::factory()
        ->for($viewer, 'sender')
        ->for($target, 'receiver')
        ->create([
            'status' => ContactRequestStatus::Accepted,
            'responded_at' => now()->subDay(),
        ]);

    $this->actingAs($viewer)
        ->delete(route('public-profile.unfollow', $targetProfile->username))
        ->assertRedirect(route('public-profile.show', $targetProfile->username));

    expect($contactRequest->refresh()->status)
        ->toBe(ContactRequestStatus::Closed);
});

test('self follow is prevented', function () {
    $user = User::factory()->create();
    $profile = createOnboardedProfile($user, [
        'username' => 'self_follow',
    ]);

    $this->actingAs($user)
        ->post(route('public-profile.follow', $profile->username))
        ->assertRedirect(route('public-profile.show', $profile->username));

    expect(Follow::query()->exists())->toBeFalse();
});

test('duplicate follow is prevented', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $target = User::factory()->create();
    $targetProfile = Profile::factory()->for($target)->create([
        'username' => 'duplicate_follow',
    ]);

    $this->actingAs($viewer)
        ->post(route('public-profile.follow', $targetProfile->username))
        ->assertRedirect(route('public-profile.show', $targetProfile->username));

    $this->actingAs($viewer)
        ->post(route('public-profile.follow', $targetProfile->username))
        ->assertRedirect(route('public-profile.show', $targetProfile->username));

    expect(Follow::query()
        ->where('follower_id', $viewer->id)
        ->where('followed_id', $target->id)
        ->count())->toBe(1);
});

test('following a missing username returns not found', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $this->actingAs($viewer)
        ->post(route('public-profile.follow', 'missing_follow_target'))
        ->assertNotFound();
});

test('mutual follow is detected', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $target->id,
    ]);
    Follow::query()->create([
        'follower_id' => $target->id,
        'followed_id' => $viewer->id,
    ]);

    expect($viewer->isMutualWith($target))->toBeTrue();
});

test('own public profile does not expose a follow action state', function () {
    $user = User::factory()->create();
    $profile = createOnboardedProfile($user, [
        'username' => 'own_follow_state',
    ]);

    $this->actingAs($user)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.isOwnProfile', true)
            ->where('profile.is_following', false)
            ->where('profile.is_followed_by', false)
            ->where('profile.is_mutual', false),
        );
});

test('public profile follow status props are correct', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $target = User::factory()->create();
    $targetProfile = Profile::factory()->for($target)->create([
        'username' => 'follow_status',
    ]);

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $target->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $targetProfile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.is_following', true)
            ->where('profile.is_followed_by', false)
            ->where('profile.is_mutual', false),
        );

    Follow::query()->create([
        'follower_id' => $target->id,
        'followed_id' => $viewer->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $targetProfile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.is_following', true)
            ->where('profile.is_followed_by', true)
            ->where('profile.is_mutual', true),
        );
});

test('mutual fields are hidden without mutual follow', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $target = User::factory()->create();
    $targetProfile = Profile::factory()->for($target)->create([
        'username' => 'mutual_hidden',
        'display_name' => 'Mutual Hidden',
        'bio' => 'Nur fuer Mutuals.',
        'region' => 'Bremen',
        'profile_visibility' => ProfileVisibility::Mutuals,
        'region_visibility' => ProfileVisibility::Mutuals,
        'languages_visibility' => ProfileVisibility::Mutuals,
        'interests_visibility' => ProfileVisibility::Mutuals,
    ]);
    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $target->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $targetProfile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.is_following', true)
            ->where('profile.is_mutual', false)
            ->missing('profile.display_name')
            ->missing('profile.bio')
            ->missing('profile.region')
            ->missing('profile.languages')
            ->missing('profile.interests'),
        );
});

test('mutual fields are visible with mutual follow', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $target = User::factory()->create();
    $targetProfile = Profile::factory()->for($target)->create([
        'username' => 'mutual_visible',
        'display_name' => 'Mutual Visible',
        'bio' => 'Fuer Mutuals sichtbar.',
        'region' => 'Dresden',
        'profile_visibility' => ProfileVisibility::Mutuals,
        'region_visibility' => ProfileVisibility::Mutuals,
        'languages_visibility' => ProfileVisibility::Mutuals,
        'interests_visibility' => ProfileVisibility::Mutuals,
    ]);
    attachManagedProfileOptions(
        $targetProfile,
        [
            ['code' => 'de', 'label' => 'Deutsch', 'position' => 1],
            ['code' => 'en', 'label' => 'Englisch', 'position' => 2],
        ],
        [['slug' => 'events', 'label' => 'Events']],
    );

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $target->id,
    ]);
    Follow::query()->create([
        'follower_id' => $target->id,
        'followed_id' => $viewer->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $targetProfile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.is_mutual', true)
            ->where('profile.display_name', 'Mutual Visible')
            ->where('profile.bio', 'Fuer Mutuals sichtbar.')
            ->where('profile.region', 'Dresden')
            ->where('profile.languages', ['Deutsch', 'Englisch'])
            ->where('profile.interests', ['Events']),
        );
});

test('private fields remain hidden even with mutual follow', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $target = User::factory()->create();
    $targetProfile = Profile::factory()->for($target)->create([
        'username' => 'private_with_mutual',
        'display_name' => 'Private With Mutual',
        'bio' => 'Bleibt privat.',
        'region' => 'Stuttgart',
        'profile_visibility' => ProfileVisibility::Private,
        'region_visibility' => ProfileVisibility::Private,
        'languages_visibility' => ProfileVisibility::Private,
        'interests_visibility' => ProfileVisibility::Private,
    ]);

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $target->id,
    ]);
    Follow::query()->create([
        'follower_id' => $target->id,
        'followed_id' => $viewer->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $targetProfile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.is_mutual', true)
            ->missing('profile.display_name')
            ->missing('profile.bio')
            ->missing('profile.region')
            ->missing('profile.languages')
            ->missing('profile.interests'),
        );
});
