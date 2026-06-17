<?php

use App\Enums\ProfileVisibility;
use App\Models\Follow;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open public profiles', function () {
    $profile = Profile::factory()->create([
        'username' => 'public_guest',
    ]);

    $this->get(route('public-profile.show', $profile->username))
        ->assertRedirect(route('login'));
});

test('users without their own profile are redirected to onboarding from public profiles', function () {
    $viewer = User::factory()->create();
    $profile = Profile::factory()->create([
        'username' => 'public_onboarding',
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertRedirect(route('onboarding.details'));
});

test('users with a profile can open another public profile', function () {
    $viewer = User::factory()->create();
    Profile::factory()->for($viewer)->create();

    $profile = Profile::factory()->create([
        'username' => 'public_member',
        'display_name' => 'Public Member',
        'bio' => 'Oeffentliche Kurzinfo.',
        'profile_visibility' => ProfileVisibility::Public,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.username', 'public_member')
            ->where('profile.display_name', 'Public Member')
            ->where('profile.bio', 'Oeffentliche Kurzinfo.'),
        );
});

test('missing public profile usernames return not found', function () {
    $viewer = User::factory()->create();
    Profile::factory()->for($viewer)->create();

    $this->actingAs($viewer)
        ->get(route('public-profile.show', 'missing_user'))
        ->assertNotFound();
});

test('users can see their own profile fields fully', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'username' => 'own_profile',
        'display_name' => 'Own Profile',
        'bio' => 'Private own bio.',
        'region' => 'Berlin',
        'languages' => ['Deutsch', 'Englisch'],
        'interests' => ['Musik', 'Events'],
        'profile_visibility' => ProfileVisibility::Private,
        'region_visibility' => ProfileVisibility::Private,
        'languages_visibility' => ProfileVisibility::Private,
        'interests_visibility' => ProfileVisibility::Private,
    ]);

    $this->actingAs($user)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.isOwnProfile', true)
            ->where('profile.display_name', 'Own Profile')
            ->where('profile.bio', 'Private own bio.')
            ->where('profile.region', 'Berlin')
            ->where('profile.languages', ['Deutsch', 'Englisch'])
            ->where('profile.interests', ['Musik', 'Events']),
        );
});

test('other users can see public profile fields', function () {
    $viewer = User::factory()->create();
    Profile::factory()->for($viewer)->create();

    $profile = Profile::factory()->create([
        'username' => 'all_public',
        'display_name' => 'All Public',
        'bio' => 'Alles sichtbar.',
        'region' => 'Muenchen',
        'languages' => ['Deutsch'],
        'interests' => ['Community'],
        'profile_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Public,
        'languages_visibility' => ProfileVisibility::Public,
        'interests_visibility' => ProfileVisibility::Public,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.isOwnProfile', false)
            ->where('profile.display_name', 'All Public')
            ->where('profile.bio', 'Alles sichtbar.')
            ->where('profile.region', 'Muenchen')
            ->where('profile.languages', ['Deutsch'])
            ->where('profile.interests', ['Community']),
        );
});

test('other users cannot receive private profile fields', function () {
    $viewer = User::factory()->create();
    Profile::factory()->for($viewer)->create();

    $profile = Profile::factory()->create([
        'username' => 'private_fields',
        'display_name' => 'Private Fields',
        'bio' => 'Nicht ausliefern.',
        'region' => 'Koeln',
        'languages' => ['Deutsch'],
        'interests' => ['Privat'],
        'profile_visibility' => ProfileVisibility::Private,
        'region_visibility' => ProfileVisibility::Private,
        'languages_visibility' => ProfileVisibility::Private,
        'interests_visibility' => ProfileVisibility::Private,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.username', 'private_fields')
            ->missing('profile.display_name')
            ->missing('profile.bio')
            ->missing('profile.region')
            ->missing('profile.languages')
            ->missing('profile.interests'),
        );
});

test('other users cannot receive mutual fields before follow exists', function () {
    $viewer = User::factory()->create();
    Profile::factory()->for($viewer)->create();

    $profile = Profile::factory()->create([
        'username' => 'mutual_fields',
        'display_name' => 'Mutual Fields',
        'bio' => 'Noch nicht sichtbar.',
        'region' => 'Leipzig',
        'languages' => ['Deutsch'],
        'interests' => ['Netzwerk'],
        'profile_visibility' => ProfileVisibility::Mutuals,
        'region_visibility' => ProfileVisibility::Mutuals,
        'languages_visibility' => ProfileVisibility::Mutuals,
        'interests_visibility' => ProfileVisibility::Mutuals,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.username', 'mutual_fields')
            ->missing('profile.display_name')
            ->missing('profile.bio')
            ->missing('profile.region')
            ->missing('profile.languages')
            ->missing('profile.interests'),
        );
});

test('other users cannot receive followers fields before follow exists', function () {
    $viewer = User::factory()->create();
    Profile::factory()->for($viewer)->create();

    $profile = Profile::factory()->create([
        'username' => 'followers_hidden_fields',
        'display_name' => 'Followers Hidden Fields',
        'bio' => 'Basis sichtbar.',
        'region' => 'Dortmund',
        'languages' => ['Deutsch'],
        'interests' => ['Community'],
        'profile_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Followers,
        'languages_visibility' => ProfileVisibility::Followers,
        'interests_visibility' => ProfileVisibility::Followers,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.username', 'followers_hidden_fields')
            ->where('profile.display_name', 'Followers Hidden Fields')
            ->where('profile.bio', 'Basis sichtbar.')
            ->missing('profile.region')
            ->missing('profile.languages')
            ->missing('profile.interests'),
        );
});

test('other users can receive followers fields after following', function () {
    $viewer = User::factory()->create();
    Profile::factory()->for($viewer)->create();

    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create([
        'username' => 'followers_visible_fields',
        'display_name' => 'Followers Visible Fields',
        'bio' => 'Basis sichtbar.',
        'region' => 'Dortmund',
        'languages' => ['Deutsch'],
        'interests' => ['Community'],
        'profile_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Followers,
        'languages_visibility' => ProfileVisibility::Followers,
        'interests_visibility' => ProfileVisibility::Followers,
    ]);

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $owner->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.username', 'followers_visible_fields')
            ->where('profile.display_name', 'Followers Visible Fields')
            ->where('profile.bio', 'Basis sichtbar.')
            ->where('profile.region', 'Dortmund')
            ->where('profile.languages', ['Deutsch'])
            ->where('profile.interests', ['Community']),
        );
});

test('public profile props do not include private user data', function () {
    $viewer = User::factory()->create();
    Profile::factory()->for($viewer)->create();

    $owner = User::factory()->create([
        'email' => 'owner@example.com',
        'birthdate' => '2000-06-16',
        'age_gate_passed_at' => now(),
    ]);
    $profile = Profile::factory()->for($owner)->create([
        'username' => 'safe_public',
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('profile.email')
            ->missing('profile.birthdate')
            ->missing('profile.age_gate_passed_at')
            ->missing('profile.role')
            ->missing('profile.user'),
        );
});

test('public profile props do not include technical ids', function () {
    $viewer = User::factory()->create();
    Profile::factory()->for($viewer)->create();

    $profile = Profile::factory()->create([
        'username' => 'no_ids',
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('profile.id')
            ->missing('profile.user_id'),
        );
});
