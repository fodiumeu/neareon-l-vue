<?php

use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('profile shows the membership month with a german month name', function () {
    $user = User::factory()->create([
        'created_at' => Carbon::parse('2024-03-15 12:00:00'),
    ]);
    createOnboardedProfile($user, [
        'username' => 'member_since_profile',
    ]);

    $this->actingAs($user)
        ->get(route('neareon-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.member_since', 'März 2024'),
        );
});

test('public profile shows one common language', function () {
    [$viewer, $profile] = profilesWithOptions(
        [['code' => 'de', 'label' => 'Deutsch', 'position' => 1]],
        [
            ['code' => 'de', 'label' => 'Deutsch', 'position' => 1],
            ['code' => 'en', 'label' => 'Englisch', 'position' => 2],
        ],
    );

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.common_languages', ['Deutsch']),
        );
});

test('public profile limits multiple common languages to three', function () {
    $languages = [
        ['code' => 'de', 'label' => 'Deutsch', 'position' => 1],
        ['code' => 'hr', 'label' => 'Kroatisch', 'position' => 2],
        ['code' => 'en', 'label' => 'Englisch', 'position' => 3],
        ['code' => 'fr', 'label' => 'Französisch', 'position' => 4],
    ];
    [$viewer, $profile] = profilesWithOptions($languages, $languages);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.common_languages', [
                'Deutsch',
                'Kroatisch',
                'Englisch',
            ]),
        );
});

test('public profile limits common interests to four', function () {
    $interests = [
        ['slug' => 'music', 'label' => 'Musik', 'sort_order' => 1],
        ['slug' => 'travel', 'label' => 'Reisen', 'sort_order' => 2],
        ['slug' => 'events', 'label' => 'Events', 'sort_order' => 3],
        ['slug' => 'business', 'label' => 'Business', 'sort_order' => 4],
        ['slug' => 'family', 'label' => 'Familie', 'sort_order' => 5],
    ];
    [$viewer, $profile] = profilesWithOptions([], [], $interests, $interests);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.common_interests', [
                'Musik',
                'Reisen',
                'Events',
                'Business',
            ]),
        );
});

test('public profile omits common metadata when there are no matches', function () {
    [$viewer, $profile] = profilesWithOptions(
        [['code' => 'de', 'label' => 'Deutsch', 'position' => 1]],
        [['code' => 'en', 'label' => 'Englisch', 'position' => 1]],
        [['slug' => 'music', 'label' => 'Musik']],
        [['slug' => 'travel', 'label' => 'Reisen']],
    );

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('profile.common_languages')
            ->missing('profile.common_interests'),
        );
});

test('own profile omits common languages and interests', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user, [
        'username' => 'own_metadata_profile',
    ]);

    $this->actingAs($user)
        ->get(route('neareon-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('profile.common_languages')
            ->missing('profile.common_interests'),
        );
});

test('hidden profile fields do not leak common languages or interests', function () {
    $languages = [
        ['code' => 'de', 'label' => 'Deutsch', 'position' => 1],
    ];
    $interests = [
        ['slug' => 'music', 'label' => 'Musik'],
    ];
    [$viewer, $profile] = profilesWithOptions(
        $languages,
        $languages,
        $interests,
        $interests,
    );
    $profile->update([
        'languages_visibility' => ProfileVisibility::Private,
        'interests_visibility' => ProfileVisibility::Private,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('profile.common_languages')
            ->missing('profile.common_interests'),
        );
});

test('discover remains free of profile-only metadata', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $owner = User::factory()->create();
    createOnboardedProfile($owner, [
        'username' => 'discover_without_metadata',
    ]);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profiles.data.0.username', 'discover_without_metadata')
            ->missing('profiles.data.0.member_since')
            ->missing('profiles.data.0.common_languages')
            ->missing('profiles.data.0.common_interests'),
        );
});

/**
 * @param  list<array{code: string, label: string, position: int}>  $viewerLanguages
 * @param  list<array{code: string, label: string, position: int}>  $ownerLanguages
 * @param  list<array{slug: string, label: string, sort_order?: int}>  $viewerInterests
 * @param  list<array{slug: string, label: string, sort_order?: int}>  $ownerInterests
 * @return array{User, Profile}
 */
function profilesWithOptions(
    array $viewerLanguages,
    array $ownerLanguages,
    array $viewerInterests = [],
    array $ownerInterests = [],
): array {
    $viewerLanguages = $viewerLanguages !== [] ? $viewerLanguages : [
        ['code' => 'viewer-language', 'label' => 'Viewer-Sprache', 'position' => 1],
    ];
    $ownerLanguages = $ownerLanguages !== [] ? $ownerLanguages : [
        ['code' => 'owner-language', 'label' => 'Owner-Sprache', 'position' => 1],
    ];
    $viewerInterests = $viewerInterests !== [] ? $viewerInterests : [
        ['slug' => 'viewer-interest', 'label' => 'Viewer-Interesse'],
    ];
    $ownerInterests = $ownerInterests !== [] ? $ownerInterests : [
        ['slug' => 'owner-interest', 'label' => 'Owner-Interesse'],
    ];

    $viewer = User::factory()->create();
    $viewerProfile = Profile::factory()->for($viewer)->create();
    attachManagedProfileOptions(
        $viewerProfile,
        $viewerLanguages,
        $viewerInterests,
    );

    $owner = User::factory()->create();
    $ownerProfile = Profile::factory()->for($owner)->create([
        'profile_visibility' => ProfileVisibility::Public,
        'languages_visibility' => ProfileVisibility::Public,
        'interests_visibility' => ProfileVisibility::Public,
    ]);
    attachManagedProfileOptions(
        $ownerProfile,
        $ownerLanguages,
        $ownerInterests,
    );

    return [$viewer, $ownerProfile];
}
