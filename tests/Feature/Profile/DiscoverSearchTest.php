<?php

use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('discover searches profiles by display name', function () {
    $viewer = searchViewer();
    searchProfile('CaBlue', 'cablue', 'Berlin');
    searchProfile('Fodi13', 'fodi13', 'Hamburg');

    assertSearchResults($viewer, 'blue', ['cablue']);
});

test('discover searches profiles by username', function () {
    $viewer = searchViewer();
    searchProfile('Community Member', 'special_fodi_17', 'Berlin');
    searchProfile('Other Member', 'other_member', 'Berlin');

    assertSearchResults($viewer, 'fodi_17', ['special_fodi_17']);
});

test('discover searches profiles by visible region', function () {
    $viewer = searchViewer();
    searchProfile('Hamburg One', 'hamburg_one', 'Hamburg');
    searchProfile('Hamburg Two', 'hamburg_two', 'hamburg');
    searchProfile('Berlin Member', 'berlin_member', 'Berlin');

    assertSearchResults($viewer, 'hamburg', [
        'hamburg_one',
        'hamburg_two',
    ]);
});

test('discover search allows partial case insensitive matches', function () {
    $viewer = searchViewer();
    searchProfile('Fodi13', 'first_profile', 'Berlin');
    searchProfile('fODI15', 'second_profile', 'Berlin');
    searchProfile('Other Member', 'third_profile', 'Berlin');

    assertSearchResults($viewer, '  FoDi  ', [
        'first_profile',
        'second_profile',
    ]);
});

test('empty discover search keeps the ranked list', function () {
    $viewer = searchViewer(['region' => 'Berlin']);
    searchProfile('Zulu Region Match', 'region_match', 'Berlin');
    searchProfile('Alpha Other Region', 'other_region', 'Hamburg');

    assertSearchResults($viewer, '   ', [
        'region_match',
        'other_region',
    ], '');
});

test('discover search keeps ranking within filtered results', function () {
    $viewer = searchViewer(['region' => 'Berlin']);
    searchProfile('Fodi Zulu Match', 'fodi_match', 'Berlin');
    searchProfile('Fodi Alpha Other', 'fodi_other', 'Hamburg');
    searchProfile('Unrelated', 'unrelated', 'Berlin');

    assertSearchResults($viewer, 'fodi', [
        'fodi_match',
        'fodi_other',
    ]);
});

test('discover search returns an empty result set when nothing matches', function () {
    $viewer = searchViewer();
    searchProfile('Visible Member', 'visible_member', 'Berlin');

    $this->actingAs($viewer)
        ->get(route('discover', ['q' => 'missing']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('search', 'missing')
            ->has('profiles.data', 0),
        );
});

test('discover search does not match a hidden region', function () {
    $viewer = searchViewer();
    searchProfile('Private Region', 'private_region', 'Hamburg', [
        'region_visibility' => ProfileVisibility::Private,
    ]);

    assertSearchResults($viewer, 'Hamburg', []);
});

test('discover searches profiles by visible language', function () {
    $viewer = searchViewer();
    $matching = searchProfile(
        'Croatian Speaker',
        'croatian_speaker',
        'Berlin',
    );
    $other = searchProfile('German Speaker', 'german_speaker', 'Berlin');
    attachManagedProfileOptions(
        $matching,
        [['code' => 'hr', 'label' => 'Kroatisch', 'position' => 1]],
    );
    attachManagedProfileOptions(
        $other,
        [['code' => 'de', 'label' => 'Deutsch', 'position' => 1]],
    );

    assertSearchResults($viewer, 'kroatisch', ['croatian_speaker']);
});

test('discover searches profiles by visible interest', function () {
    $viewer = searchViewer();
    $matching = searchProfile('Business Member', 'business_member', 'Berlin');
    $other = searchProfile('Family Member', 'family_member', 'Berlin');
    attachManagedProfileOptions(
        $matching,
        [],
        [['slug' => 'business', 'label' => 'Business / Networking']],
    );
    attachManagedProfileOptions(
        $other,
        [],
        [['slug' => 'family', 'label' => 'Familie']],
    );

    assertSearchResults($viewer, 'networking', ['business_member']);
});

test('discover search does not match hidden languages or interests', function () {
    $viewer = searchViewer();
    $profile = searchProfile('Hidden Options', 'hidden_options', 'Berlin', [
        'languages_visibility' => ProfileVisibility::Private,
        'interests_visibility' => ProfileVisibility::Private,
    ]);
    attachManagedProfileOptions(
        $profile,
        [['code' => 'hr', 'label' => 'Kroatisch', 'position' => 1]],
        [['slug' => 'fitness', 'label' => 'Fitness']],
    );

    assertSearchResults($viewer, 'Kroatisch', []);
    assertSearchResults($viewer, 'Fitness', []);
});

test('discover page provides debounced live search and immediate clearing', function () {
    $page = file_get_contents(resource_path('js/pages/Discover.vue'));

    expect($page)
        ->toContain('Profile durchsuchen')
        ->toContain('placeholder="Name, Benutzername oder Region"')
        ->toContain('type="search"')
        ->toContain('v-model="searchQuery"')
        ->toContain('@input="handleSearchInput"')
        ->toContain('searchTimer = setTimeout(runSearch, 350)')
        ->toContain("if (searchQuery.value.trim() === '')")
        ->toContain('clearSearchTimer()')
        ->toContain('router.get(')
        ->toContain(
            "only: ['profiles', 'search', 'filters', 'filterOptions']",
        )
        ->toContain('preserveState: true')
        ->toContain('replace: true')
        ->toContain('aria-label="Profile suchen"')
        ->toContain('<Search class="size-4"')
        ->toContain('Keine passenden Profile gefunden')
        ->toContain('Versuche einen anderen Suchbegriff.');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function searchViewer(array $attributes = []): User
{
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer, array_merge([
        'region' => 'Viewer Region',
    ], $attributes));

    return $viewer;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function searchProfile(
    string $displayName,
    string $username,
    string $region,
    array $attributes = [],
): Profile {
    return Profile::factory()->create(array_merge([
        'display_name' => $displayName,
        'username' => $username,
        'region' => $region,
        'profile_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Public,
    ], $attributes));
}

/**
 * @param  list<string>  $usernames
 */
function assertSearchResults(
    User $viewer,
    string $query,
    array $usernames,
    ?string $expectedSearch = null,
): void {
    test()->actingAs($viewer)
        ->get(route('discover', ['q' => $query]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (
            $expectedSearch,
            $query,
            $usernames,
        ): Assert {
            $page->where('search', $expectedSearch ?? trim($query))
                ->has('profiles.data', count($usernames));

            foreach ($usernames as $index => $username) {
                $page->where("profiles.data.{$index}.username", $username);
            }

            return $page;
        });
}
