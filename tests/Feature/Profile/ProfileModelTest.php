<?php

use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can have exactly one profile', function () {
    $user = User::factory()->create();

    $profile = Profile::factory()->for($user)->create();

    expect($user->fresh()->profile->is($profile))->toBeTrue();

    Profile::factory()->for($user)->create();
})->throws(QueryException::class);

test('a profile belongs to a user', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    expect($profile->user->is($user))->toBeTrue();
});

test('profile usernames are unique', function () {
    Profile::factory()->create([
        'username' => 'same_username',
    ]);

    Profile::factory()->create([
        'username' => 'same_username',
    ]);
})->throws(QueryException::class);

test('profile user ids are unique', function () {
    $user = User::factory()->create();

    Profile::factory()->for($user)->create();

    Profile::factory()->for($user)->create();
})->throws(QueryException::class);

test('profile visibility fields use database defaults', function () {
    $user = User::factory()->create();

    $profile = Profile::query()->create([
        'user_id' => $user->id,
        'username' => 'default_visibility',
        'display_name' => 'Default Visibility',
    ])->fresh();

    expect($profile->profile_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->interests_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->languages_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->region_visibility)->toBe(ProfileVisibility::Mutuals)
        ->and($profile->social_counts_visibility)->toBe(ProfileVisibility::Public);
});

test('profile languages and interests are cast to arrays', function () {
    $profile = Profile::factory()->create([
        'languages' => ['de', 'en'],
        'interests' => ['music', 'technology'],
    ])->fresh();

    expect($profile->languages)->toBe(['de', 'en'])
        ->and($profile->interests)->toBe(['music', 'technology']);
});

test('profile optional fields can be null', function () {
    $profile = Profile::factory()->create([
        'bio' => null,
        'region' => null,
        'languages' => null,
        'interests' => null,
    ])->fresh();

    expect($profile->bio)->toBeNull()
        ->and($profile->region)->toBeNull()
        ->and($profile->languages)->toBeNull()
        ->and($profile->interests)->toBeNull();
});

test('deleting a user cascades to the related profile', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $user->delete();

    expect(Profile::query()->whereKey($profile->id)->exists())->toBeFalse();
});

test('profile factory creates a valid profile', function () {
    $profile = Profile::factory()->create();

    expect($profile)->toBeInstanceOf(Profile::class)
        ->and($profile->user)->toBeInstanceOf(User::class)
        ->and($profile->username)->not->toBeEmpty()
        ->and($profile->display_name)->not->toBeEmpty()
        ->and($profile->profile_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->interests_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->languages_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->region_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->social_counts_visibility)->toBe(ProfileVisibility::Public);
});
