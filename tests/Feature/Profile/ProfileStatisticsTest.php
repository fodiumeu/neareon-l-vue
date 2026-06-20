<?php

use App\Enums\ProfileVisibility;
use App\Models\Block;
use App\Models\Follow;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('public profile shows follower and mutual contact counts', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create([
        'username' => 'profile_statistics',
        'social_counts_visibility' => ProfileVisibility::Public,
    ]);
    $follower = User::factory()->create();
    $firstContact = User::factory()->create();
    $secondContact = User::factory()->create();

    follow($follower, $owner);
    follow($firstContact, $owner);
    follow($owner, $firstContact);
    follow($secondContact, $owner);
    follow($owner, $secondContact);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.followers_count', 3)
            ->where('profile.contacts_count', 2),
        );
});

test('own profile shows zero social counts', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user, [
        'username' => 'zero_profile_statistics',
    ]);

    $this->actingAs($user)
        ->get(route('neareon-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.followers_count', 0)
            ->where('profile.contacts_count', 0),
        );
});

test('blocked mutual follows are not counted as active contacts', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create([
        'username' => 'blocked_profile_statistics',
        'social_counts_visibility' => ProfileVisibility::Public,
    ]);
    $blockedContact = User::factory()->create();

    follow($blockedContact, $owner);
    follow($owner, $blockedContact);
    Block::factory()
        ->for($owner, 'blocker')
        ->for($blockedContact, 'blocked')
        ->create();

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.followers_count', 1)
            ->where('profile.contacts_count', 0),
        );
});

test('social counts remain hidden when their profile visibility forbids access', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create([
        'username' => 'private_profile_statistics',
        'social_counts_visibility' => ProfileVisibility::Private,
    ]);
    follow(User::factory()->create(), $owner);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('profile.followers_count')
            ->missing('profile.contacts_count'),
        );
});

function follow(User $follower, User $followed): void
{
    Follow::query()->create([
        'follower_id' => $follower->id,
        'followed_id' => $followed->id,
    ]);
}
