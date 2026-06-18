<?php

use App\Enums\ProfileVisibility;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open discover', function () {
    $this->get(route('discover'))
        ->assertRedirect(route('login'));
});

test('users without a profile are redirected to onboarding from discover', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('discover'))
        ->assertRedirect(route('onboarding.details'));
});

test('users with a profile can open discover', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Discover')
            ->has('profiles'),
        );
});

test('discover does not list the current users own profile', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user, [
        'username' => 'own_discover',
        'display_name' => 'Own Discover',
    ]);

    $this->actingAs($user)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles', 0),
        );
});

test('discover lists public profiles', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    Profile::factory()->create([
        'username' => 'public_discover',
        'display_name' => 'Public Discover',
        'profile_visibility' => ProfileVisibility::Public,
    ]);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles', 1)
            ->where('profiles.0.username', 'public_discover')
            ->where('profiles.0.display_name', 'Public Discover')
            ->where('profiles.0.contact_status', 'none'),
        );
});

test('discover includes outgoing and incoming contact request statuses', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $outgoingUser = User::factory()->create();
    Profile::factory()->for($outgoingUser)->create([
        'username' => 'outgoing_status',
        'display_name' => 'Outgoing Status',
        'profile_visibility' => ProfileVisibility::Public,
    ]);
    $incomingUser = User::factory()->create();
    Profile::factory()->for($incomingUser)->create([
        'username' => 'incoming_status',
        'display_name' => 'Incoming Status',
        'profile_visibility' => ProfileVisibility::Public,
    ]);
    ContactRequest::factory()
        ->for($viewer, 'sender')
        ->for($outgoingUser, 'receiver')
        ->create();
    ContactRequest::factory()
        ->for($incomingUser, 'sender')
        ->for($viewer, 'receiver')
        ->create();

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profiles.0.username', 'incoming_status')
            ->where('profiles.0.contact_status', 'incoming_request')
            ->where('profiles.1.username', 'outgoing_status')
            ->where('profiles.1.contact_status', 'outgoing_request'),
        );
});

test('discover does not list private profiles', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    Profile::factory()->create([
        'username' => 'private_discover',
        'display_name' => 'Private Discover',
        'profile_visibility' => ProfileVisibility::Private,
    ]);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles', 0),
        );
});

test('discover does not list mutual profiles without mutual follow', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $target = User::factory()->create();
    Profile::factory()->for($target)->create([
        'username' => 'mutual_hidden_discover',
        'display_name' => 'Mutual Hidden Discover',
        'profile_visibility' => ProfileVisibility::Mutuals,
    ]);

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $target->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles', 0),
        );
});

test('discover lists mutual profiles with mutual follow', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $target = User::factory()->create();
    Profile::factory()->for($target)->create([
        'username' => 'mutual_visible_discover',
        'display_name' => 'Mutual Visible Discover',
        'profile_visibility' => ProfileVisibility::Mutuals,
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
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles', 1)
            ->where('profiles.0.username', 'mutual_visible_discover')
            ->where('profiles.0.display_name', 'Mutual Visible Discover')
            ->where('profiles.0.is_mutual', true),
        );
});

test('discover does not deliver private profile fields', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    Profile::factory()->create([
        'username' => 'public_private_fields',
        'display_name' => 'Public Private Fields',
        'bio' => 'Nicht ausliefern.',
        'region' => 'Bonn',
        'profile_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Private,
        'languages_visibility' => ProfileVisibility::Private,
        'interests_visibility' => ProfileVisibility::Private,
    ]);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles', 1)
            ->where('profiles.0.username', 'public_private_fields')
            ->where('profiles.0.display_name', 'Public Private Fields')
            ->where('profiles.0.bio', 'Nicht ausliefern.')
            ->missing('profiles.0.region')
            ->missing('profiles.0.languages')
            ->missing('profiles.0.interests'),
        );
});

test('discover does not deliver followers fields before follow exists', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    Profile::factory()->create([
        'username' => 'followers_hidden_discover',
        'display_name' => 'Followers Hidden Discover',
        'bio' => 'Basis sichtbar.',
        'region' => 'Bremen',
        'profile_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Followers,
        'languages_visibility' => ProfileVisibility::Followers,
        'interests_visibility' => ProfileVisibility::Followers,
    ]);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles', 1)
            ->where('profiles.0.username', 'followers_hidden_discover')
            ->where('profiles.0.display_name', 'Followers Hidden Discover')
            ->where('profiles.0.bio', 'Basis sichtbar.')
            ->missing('profiles.0.region')
            ->missing('profiles.0.languages')
            ->missing('profiles.0.interests'),
        );
});

test('discover delivers followers fields after follow exists', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $target = User::factory()->create();
    $profile = Profile::factory()->for($target)->create([
        'username' => 'followers_visible_discover',
        'display_name' => 'Followers Visible Discover',
        'bio' => 'Basis sichtbar.',
        'region' => 'Bremen',
        'profile_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Followers,
        'languages_visibility' => ProfileVisibility::Followers,
        'interests_visibility' => ProfileVisibility::Followers,
    ]);
    attachManagedProfileOptions(
        $profile,
        [['code' => 'de', 'label' => 'Deutsch', 'position' => 1]],
        [['slug' => 'community', 'label' => 'Community']],
    );

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $target->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles', 1)
            ->where('profiles.0.username', 'followers_visible_discover')
            ->where('profiles.0.display_name', 'Followers Visible Discover')
            ->where('profiles.0.bio', 'Basis sichtbar.')
            ->where('profiles.0.region', 'Bremen')
            ->where('profiles.0.languages', ['Deutsch'])
            ->where('profiles.0.interests', ['Community']),
        );
});

test('discover delivers managed option labels in language position order', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $profile = Profile::factory()->create([
        'username' => 'visible_fields',
        'display_name' => 'Visible Fields',
        'bio' => 'Sichtbare Kurzinfo.',
        'region' => 'Berlin',
        'profile_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Public,
        'languages_visibility' => ProfileVisibility::Public,
        'interests_visibility' => ProfileVisibility::Public,
    ]);
    attachManagedProfileOptions(
        $profile,
        [
            ['code' => 'en', 'label' => 'Englisch', 'position' => 2],
            ['code' => 'de', 'label' => 'Deutsch', 'position' => 1],
        ],
        [['slug' => 'technology', 'label' => 'Technologie']],
    );

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profiles.0.username', 'visible_fields')
            ->where('profiles.0.display_name', 'Visible Fields')
            ->where('profiles.0.bio', 'Sichtbare Kurzinfo.')
            ->where('profiles.0.region', 'Berlin')
            ->where('profiles.0.languages', ['Deutsch', 'Englisch'])
            ->where('profiles.0.interests', ['Technologie']),
        );
});

test('discover includes follow status for each visible profile', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $target = User::factory()->create();
    Profile::factory()->for($target)->create([
        'username' => 'status_discover',
        'display_name' => 'Status Discover',
        'profile_visibility' => ProfileVisibility::Public,
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
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profiles.0.username', 'status_discover')
            ->where('profiles.0.is_following', true)
            ->where('profiles.0.is_followed_by', true)
            ->where('profiles.0.is_mutual', true)
            ->where('profiles.0.contact_status', 'connected'),
        );
});

test('discover does not deliver sensitive or technical data', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $owner = User::factory()->create([
        'email' => 'discover-owner@example.com',
        'birthdate' => '2000-06-16',
        'age_gate_passed_at' => now(),
    ]);
    Profile::factory()->for($owner)->create([
        'username' => 'safe_discover',
        'display_name' => 'Safe Discover',
        'profile_visibility' => ProfileVisibility::Public,
    ]);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('profiles.0.id')
            ->missing('profiles.0.user_id')
            ->missing('profiles.0.user')
            ->missing('profiles.0.email')
            ->missing('profiles.0.birthdate')
            ->missing('profiles.0.age_gate_passed_at')
            ->missing('profiles.0.role'),
        );
});
