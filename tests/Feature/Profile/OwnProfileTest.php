<?php

use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open their own profile page', function () {
    $this->get(route('neareon-profile.show'))
        ->assertRedirect(route('login'));
});

test('users without age gate are redirected from their own profile page to age gate', function () {
    $user = User::factory()->withoutAgeGate()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->get(route('neareon-profile.show'))
        ->assertRedirect(route('age-gate.show'));
});

test('users with age gate but without onboarding are redirected from their own profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('neareon-profile.show'))
        ->assertRedirect(route('onboarding.details'));
});

test('onboarded users can open their own profile page', function () {
    $user = User::factory()->create();
    $profile = createOnboardedProfile($user, [
        'username' => 'own_route_profile',
        'display_name' => 'Own Route Profile',
    ]);

    $this->actingAs($user)
        ->get(route('neareon-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.username', 'own_route_profile')
            ->where('profile.display_name', 'Own Route Profile')
            ->where('profile.isOwnProfile', true),
        );
});

test('own profile page shows private own fields and edit link', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'username' => 'private_own_route',
        'display_name' => 'Private Own Route',
        'bio' => 'Nur fuer mich sichtbar.',
        'region' => 'Berlin',
        'languages' => ['Deutsch', 'Englisch'],
        'interests' => ['Musik', 'Events'],
        'profile_visibility' => ProfileVisibility::Private,
        'region_visibility' => ProfileVisibility::Private,
        'languages_visibility' => ProfileVisibility::Private,
        'interests_visibility' => ProfileVisibility::Private,
    ]);
    attachManagedProfileOptions(
        $profile,
        [
            ['code' => 'de', 'label' => 'Deutsch', 'position' => 1],
            ['code' => 'en', 'label' => 'Englisch', 'position' => 2],
        ],
        [
            ['slug' => 'music', 'label' => 'Musik'],
            ['slug' => 'events', 'label' => 'Events'],
        ],
    );

    $this->actingAs($user)
        ->get(route('neareon-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.isOwnProfile', true)
            ->where('profile.display_name', 'Private Own Route')
            ->where('profile.bio', 'Nur fuer mich sichtbar.')
            ->where('profile.region', 'Berlin')
            ->where('profile.languages', ['Deutsch', 'Englisch'])
            ->where('profile.interests', ['Musik', 'Events'])
            ->where('editProfileHref', '/profile/edit'),
        );
});

test('public username profile route still works without own edit link', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $profile = Profile::factory()->create([
        'username' => 'still_public_username',
        'display_name' => 'Still Public Username',
        'profile_visibility' => ProfileVisibility::Public,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.username', 'still_public_username')
            ->where('profile.display_name', 'Still Public Username')
            ->where('profile.isOwnProfile', false)
            ->missing('editProfileHref'),
        );
});

test('main navigation config contains the profile entry', function () {
    $navigation = file_get_contents(resource_path('js/config/navigation/app-navigation.ts'));

    expect($navigation)
        ->toContain("title: 'Profil'")
        ->toContain("href: '/profile'");
});
