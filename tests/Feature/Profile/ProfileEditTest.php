<?php

use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function validProfileUpdatePayload(array $overrides = []): array
{
    return array_merge([
        'display_name' => 'Updated Member',
        'bio' => 'Eine kurze aktualisierte Info.',
        'region' => 'Hamburg',
        'languages' => 'Deutsch, Englisch',
        'interests' => 'Musik, Events',
        'profile_visibility' => ProfileVisibility::Public->value,
        'interests_visibility' => ProfileVisibility::Public->value,
        'languages_visibility' => ProfileVisibility::Public->value,
        'region_visibility' => ProfileVisibility::Mutuals->value,
        'social_counts_visibility' => ProfileVisibility::Public->value,
    ], $overrides);
}

test('guests cannot open profile editing', function () {
    $this->get(route('neareon-profile.edit'))
        ->assertRedirect(route('login'));
});

test('users without a profile are redirected to onboarding from profile editing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertRedirect(route('onboarding.create'));
});

test('users with a profile can open profile editing', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'display_name' => 'Existing Member',
        'languages' => ['Deutsch', 'Englisch'],
        'interests' => ['Musik', 'Events'],
        'profile_visibility' => ProfileVisibility::Mutuals,
        'interests_visibility' => ProfileVisibility::Private,
        'languages_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Private,
        'social_counts_visibility' => ProfileVisibility::Mutuals,
    ]);

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('profile.display_name', 'Existing Member')
            ->where('profile.languages', 'Deutsch, Englisch')
            ->where('profile.interests', 'Musik, Events')
            ->where('profile.profile_visibility', 'mutuals')
            ->where('profile.interests_visibility', 'private')
            ->where('profile.languages_visibility', 'public')
            ->where('profile.region_visibility', 'private')
            ->where('profile.social_counts_visibility', 'mutuals'),
        );
});

test('users can update display name bio and region', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'display_name' => 'Neuer Anzeigename',
            'bio' => 'Neue Kurzinfo.',
            'region' => 'Koeln',
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $profile->refresh();

    expect($profile->display_name)->toBe('Neuer Anzeigename')
        ->and($profile->bio)->toBe('Neue Kurzinfo.')
        ->and($profile->region)->toBe('Koeln');
});

test('users can update languages and interests', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'languages' => ['Deutsch', 'Englisch', 'Spanisch'],
            'interests' => ['Community', 'Technik'],
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $profile->refresh();

    expect($profile->languages)->toBe(['Deutsch', 'Englisch', 'Spanisch'])
        ->and($profile->interests)->toBe(['Community', 'Technik']);
});

test('comma separated languages and interests are stored as arrays', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'languages' => 'Deutsch, Englisch, , Deutsch',
            'interests' => 'Musik, Events, Technik',
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $profile->refresh();

    expect($profile->languages)->toBe(['Deutsch', 'Englisch'])
        ->and($profile->interests)->toBe(['Musik', 'Events', 'Technik']);
});

test('users can update visibility fields', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'profile_visibility' => ProfileVisibility::Private->value,
            'interests_visibility' => ProfileVisibility::Mutuals->value,
            'languages_visibility' => ProfileVisibility::Private->value,
            'region_visibility' => ProfileVisibility::Public->value,
            'social_counts_visibility' => ProfileVisibility::Mutuals->value,
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $profile->refresh();

    expect($profile->profile_visibility)->toBe(ProfileVisibility::Private)
        ->and($profile->interests_visibility)->toBe(ProfileVisibility::Mutuals)
        ->and($profile->languages_visibility)->toBe(ProfileVisibility::Private)
        ->and($profile->region_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->social_counts_visibility)->toBe(ProfileVisibility::Mutuals);
});

test('saved visibility fields are returned after updating and reloading profile editing', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'profile_visibility' => ProfileVisibility::Mutuals->value,
            'interests_visibility' => ProfileVisibility::Private->value,
            'languages_visibility' => ProfileVisibility::Mutuals->value,
            'region_visibility' => ProfileVisibility::Private->value,
            'social_counts_visibility' => ProfileVisibility::Public->value,
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('profile.profile_visibility', 'mutuals')
            ->where('profile.interests_visibility', 'private')
            ->where('profile.languages_visibility', 'mutuals')
            ->where('profile.region_visibility', 'private')
            ->where('profile.social_counts_visibility', 'public'),
        );
});

test('invalid visibility values are rejected', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'profile_visibility' => ProfileVisibility::Public,
    ]);

    $this->actingAs($user)
        ->from(route('neareon-profile.edit'))
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'profile_visibility' => 'friends',
        ]))
        ->assertRedirect(route('neareon-profile.edit'))
        ->assertSessionHasErrors('profile_visibility');

    expect($profile->refresh()->profile_visibility)->toBe(ProfileVisibility::Public);
});

test('username is not changed by profile editing', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'username' => 'stable_name',
    ]);

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'username' => 'changed_name',
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    expect($profile->refresh()->username)->toBe('stable_name');
});

test('birthdate is not changed by profile editing', function () {
    $user = User::factory()->create([
        'birthdate' => '2000-06-16',
    ]);
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'birthdate' => '1990-01-01',
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    expect($user->refresh()->birthdate->toDateString())->toBe('2000-06-16');
});

test('users cannot edit another users profile', function () {
    $user = User::factory()->create();
    $ownProfile = Profile::factory()->for($user)->create([
        'display_name' => 'Own Profile',
    ]);

    $otherUser = User::factory()->create();
    $otherProfile = Profile::factory()->for($otherUser)->create([
        'display_name' => 'Other Profile',
    ]);

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'user_id' => $otherUser->id,
            'display_name' => 'Updated Own Profile',
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    expect($ownProfile->refresh()->display_name)->toBe('Updated Own Profile')
        ->and($otherProfile->refresh()->display_name)->toBe('Other Profile');
});
