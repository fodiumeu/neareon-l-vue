<?php

use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('discover filters by visible region', function () {
    $viewer = discoverFilterViewer();
    discoverFilterProfile('Hamburg Member', 'hamburg_member', 'Hamburg');
    discoverFilterProfile('Berlin Member', 'berlin_member', 'Berlin');

    assertFilteredDiscover($viewer, ['region' => 'Hamburg'], [
        'hamburg_member',
    ], fn (Assert $page) => $page
        ->where('filterOptions.regions', ['Berlin', 'Hamburg']),
    );
});

test('discover filters by visible language', function () {
    $viewer = discoverFilterViewer();
    $croatian = discoverFilterProfile(
        'Croatian Member',
        'croatian_member',
        'Berlin',
    );
    $german = discoverFilterProfile(
        'German Member',
        'german_member',
        'Berlin',
    );
    attachManagedProfileOptions(
        $croatian,
        [['code' => 'hr', 'label' => 'Kroatisch', 'position' => 1]],
    );
    attachManagedProfileOptions(
        $german,
        [['code' => 'de', 'label' => 'Deutsch', 'position' => 1]],
    );

    assertFilteredDiscover($viewer, ['language' => 'Kroatisch'], [
        'croatian_member',
    ], fn (Assert $page) => $page
        ->where('filterOptions.languages', ['Deutsch', 'Kroatisch']),
    );
});

test('discover filters by visible interest', function () {
    $viewer = discoverFilterViewer();
    $family = discoverFilterProfile(
        'Family Member',
        'family_member',
        'Berlin',
    );
    $fitness = discoverFilterProfile(
        'Fitness Member',
        'fitness_member',
        'Berlin',
    );
    attachManagedProfileOptions(
        $family,
        [],
        [['slug' => 'family', 'label' => 'Familie']],
    );
    attachManagedProfileOptions(
        $fitness,
        [],
        [['slug' => 'fitness', 'label' => 'Fitness']],
    );

    assertFilteredDiscover($viewer, ['interest' => 'Familie'], [
        'family_member',
    ], fn (Assert $page) => $page
        ->where('filterOptions.interests', ['Familie', 'Fitness']),
    );
});

test('discover combines all filters with logical and', function () {
    $viewer = discoverFilterViewer();
    $matching = discoverFilterProfile(
        'Matching Member',
        'matching_member',
        'Hamburg',
    );
    $wrongInterest = discoverFilterProfile(
        'Wrong Interest',
        'wrong_interest',
        'Hamburg',
    );
    $wrongRegion = discoverFilterProfile(
        'Wrong Region',
        'wrong_region',
        'Berlin',
    );

    foreach ([$matching, $wrongInterest, $wrongRegion] as $profile) {
        attachManagedProfileOptions(
            $profile,
            [['code' => 'hr', 'label' => 'Kroatisch', 'position' => 1]],
            [[
                'slug' => $profile->is($wrongInterest) ? 'fitness' : 'family',
                'label' => $profile->is($wrongInterest) ? 'Fitness' : 'Familie',
            ]],
        );
    }

    assertFilteredDiscover($viewer, [
        'region' => 'Hamburg',
        'language' => 'Kroatisch',
        'interest' => 'Familie',
    ], ['matching_member']);
});

test('discover combines filters with search', function () {
    $viewer = discoverFilterViewer();
    $matching = discoverFilterProfile(
        'Fit Hamburg',
        'fit_hamburg',
        'Hamburg',
    );
    $wrongSearch = discoverFilterProfile(
        'Other Hamburg',
        'other_hamburg',
        'Hamburg',
    );

    foreach ([$matching, $wrongSearch] as $profile) {
        attachManagedProfileOptions(
            $profile,
            [['code' => 'de', 'label' => 'Deutsch', 'position' => 1]],
        );
    }

    assertFilteredDiscover($viewer, [
        'q' => 'fit',
        'region' => 'Hamburg',
        'language' => 'Deutsch',
    ], ['fit_hamburg']);
});

test('discover keeps ranking within filtered profiles', function () {
    $viewer = discoverFilterViewer(['region' => 'Hamburg']);
    $rankedFirst = discoverFilterProfile(
        'Zulu Region Match',
        'zulu_region_match',
        'Hamburg',
    );
    discoverFilterProfile(
        'Alpha Other Region',
        'alpha_region_match',
        'Hamburg',
    );
    discoverFilterProfile(
        'Excluded Munich',
        'excluded_munich',
        'München',
    );
    attachManagedProfileOptions(
        $rankedFirst,
        [['code' => 'de', 'label' => 'Deutsch', 'position' => 1]],
    );

    assertFilteredDiscover($viewer, [
        'region' => 'Hamburg',
    ], ['zulu_region_match', 'alpha_region_match']);
});

test('hidden profile fields are absent from options and cannot filter matches', function () {
    $viewer = discoverFilterViewer();
    $hidden = discoverFilterProfile(
        'Hidden Profile',
        'hidden_profile',
        'Secret Region',
        [
            'region_visibility' => ProfileVisibility::Private,
            'languages_visibility' => ProfileVisibility::Private,
            'interests_visibility' => ProfileVisibility::Private,
        ],
    );
    attachManagedProfileOptions(
        $hidden,
        [['code' => 'hr', 'label' => 'Kroatisch', 'position' => 1]],
        [['slug' => 'family', 'label' => 'Familie']],
    );

    $this->actingAs($viewer)
        ->get(route('discover', [
            'region' => 'Secret Region',
            'language' => 'Kroatisch',
            'interest' => 'Familie',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles.data', 0)
            ->where('filterOptions.regions', [])
            ->where('filterOptions.languages', [])
            ->where('filterOptions.interests', []),
        );
});

test('discover filter UI is responsive and resets filters without search', function () {
    $page = file_get_contents(resource_path('js/pages/Discover.vue'));

    expect($page)
        ->toContain('Filter anzeigen')
        ->toContain('Filter ausblenden')
        ->toContain('md:hidden')
        ->toContain("filtersOpen ? 'grid md:grid' : 'hidden md:grid'")
        ->toContain('name="region"')
        ->toContain('name="language"')
        ->toContain('name="interest"')
        ->toContain('Filter zurücksetzen')
        ->toContain("selectedRegion.value = ''")
        ->toContain("selectedLanguage.value = ''")
        ->toContain("selectedInterest.value = ''")
        ->toContain('const query = searchQuery.value.trim()');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function discoverFilterViewer(array $attributes = []): User
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
function discoverFilterProfile(
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
        'languages_visibility' => ProfileVisibility::Public,
        'interests_visibility' => ProfileVisibility::Public,
    ], $attributes));
}

/**
 * @param  array<string, string>  $parameters
 * @param  list<string>  $usernames
 */
function assertFilteredDiscover(
    User $viewer,
    array $parameters,
    array $usernames,
    ?Closure $additionalAssertions = null,
): void {
    test()->actingAs($viewer)
        ->get(route('discover', $parameters))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (
            $additionalAssertions,
            $parameters,
            $usernames,
        ): Assert {
            $page->has('profiles.data', count($usernames))
                ->where('filters.region', $parameters['region'] ?? '')
                ->where('filters.language', $parameters['language'] ?? '')
                ->where('filters.interest', $parameters['interest'] ?? '');

            foreach ($usernames as $index => $username) {
                $page->where("profiles.data.{$index}.username", $username);
            }

            if ($additionalAssertions !== null) {
                $additionalAssertions($page);
            }

            return $page;
        });
}
