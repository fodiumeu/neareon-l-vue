<?php

use App\Enums\ProfileVisibility;
use App\Models\Block;
use App\Models\Follow;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('discover ranks the same region before another region', function () {
    $viewer = rankingViewer(['region' => 'Berlin']);
    rankingProfile('Zulu Same Region', ['region' => ' berlin ']);
    rankingProfile('Alpha Other Region', ['region' => 'Hamburg']);

    assertDiscoverOrder($viewer, [
        'zulu_same_region',
        'alpha_other_region',
    ]);
});

test('discover ranks more common languages before fewer common languages', function () {
    $viewer = rankingViewer();
    attachManagedProfileOptions(
        $viewer->profile,
        [
            ['code' => 'de', 'label' => 'Deutsch', 'position' => 1],
            ['code' => 'en', 'label' => 'Englisch', 'position' => 2],
        ],
        [['slug' => 'community', 'label' => 'Community']],
    );
    $more = rankingProfile('Zulu More Languages');
    $fewer = rankingProfile('Alpha Fewer Languages');
    attachManagedProfileOptions(
        $more,
        [
            ['code' => 'de', 'label' => 'Deutsch', 'position' => 1],
            ['code' => 'en', 'label' => 'Englisch', 'position' => 2],
        ],
    );
    attachManagedProfileOptions(
        $fewer,
        [['code' => 'de', 'label' => 'Deutsch', 'position' => 1]],
    );

    assertDiscoverOrder($viewer, [
        'zulu_more_languages',
        'alpha_fewer_languages',
    ]);
});

test('discover ranks more common interests before fewer common interests', function () {
    $viewer = rankingViewer();
    attachManagedProfileOptions(
        $viewer->profile,
        [['code' => 'de', 'label' => 'Deutsch', 'position' => 1]],
        [
            ['slug' => 'music', 'label' => 'Musik'],
            ['slug' => 'travel', 'label' => 'Reisen'],
        ],
    );
    $more = rankingProfile('Zulu More Interests');
    $fewer = rankingProfile('Alpha Fewer Interests');
    attachManagedProfileOptions(
        $more,
        [],
        [
            ['slug' => 'music', 'label' => 'Musik'],
            ['slug' => 'travel', 'label' => 'Reisen'],
        ],
    );
    attachManagedProfileOptions(
        $fewer,
        [],
        [['slug' => 'music', 'label' => 'Musik']],
    );

    assertDiscoverOrder($viewer, [
        'zulu_more_interests',
        'alpha_fewer_interests',
    ]);
});

test('discover uses mutual follow as a ranking signal', function () {
    $viewer = rankingViewer();
    $mutual = rankingProfile('Zulu Mutual');
    rankingProfile('Alpha No Relationship');

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $mutual->user_id,
    ]);
    Follow::query()->create([
        'follower_id' => $mutual->user_id,
        'followed_id' => $viewer->id,
    ]);

    assertDiscoverOrder($viewer, [
        'zulu_mutual',
        'alpha_no_relationship',
    ]);
});

test('discover sorts equal ranking scores alphabetically by display name', function () {
    $viewer = rankingViewer();
    rankingProfile('Zulu Equal');
    rankingProfile('Alpha Equal');

    assertDiscoverOrder($viewer, ['alpha_equal', 'zulu_equal']);
});

test('discover ranking excludes blocked and own profiles', function () {
    $viewer = rankingViewer([
        'username' => 'ranking_viewer',
        'display_name' => 'Ranking Viewer',
        'region' => 'Berlin',
    ]);
    $blocked = rankingProfile('Blocked Perfect Match', [
        'region' => 'Berlin',
    ]);
    rankingProfile('Visible Profile');
    Block::factory()
        ->for($viewer, 'blocker')
        ->for($blocked->user, 'blocked')
        ->create();

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles.data', 1)
            ->where('profiles.data.0.username', 'visible_profile')
            ->where('profiles.data.0.display_name', 'Visible Profile')
            ->missing('profiles.data.0.score'),
        );
});

test('hidden profile fields do not influence discover ranking', function () {
    $viewer = rankingViewer(['region' => 'Berlin']);
    rankingProfile('Zulu Hidden Match', [
        'region' => 'Berlin',
        'region_visibility' => ProfileVisibility::Private,
    ]);
    rankingProfile('Alpha No Match', ['region' => 'Hamburg']);

    assertDiscoverOrder($viewer, [
        'alpha_no_match',
        'zulu_hidden_match',
    ]);
});

test('discover ranking query count stays constant as profiles increase', function () {
    $viewer = rankingViewer();
    rankingProfile('Single Profile');

    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->actingAs($viewer)->get(route('discover'))->assertOk();
    $singleProfileQueries = count(DB::getQueryLog());

    foreach (range(1, 5) as $index) {
        rankingProfile("Additional Profile {$index}");
    }

    DB::flushQueryLog();
    $this->actingAs($viewer)->get(route('discover'))->assertOk();
    $multipleProfileQueries = count(DB::getQueryLog());

    expect($multipleProfileQueries)
        ->toBeLessThanOrEqual($singleProfileQueries);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function rankingViewer(array $attributes = []): User
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
function rankingProfile(string $displayName, array $attributes = []): Profile
{
    $user = User::factory()->create();

    return Profile::factory()->for($user)->create(array_merge([
        'username' => str($displayName)->snake()->toString(),
        'display_name' => $displayName,
        'region' => 'Other Region',
        'profile_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Public,
        'languages_visibility' => ProfileVisibility::Public,
        'interests_visibility' => ProfileVisibility::Public,
    ], $attributes));
}

/**
 * @param  list<string>  $usernames
 */
function assertDiscoverOrder(User $viewer, array $usernames): void
{
    test()->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($usernames): Assert {
            $page->has('profiles.data', count($usernames));

            foreach ($usernames as $index => $username) {
                $page->where("profiles.data.{$index}.username", $username)
                    ->missing("profiles.data.{$index}.score");
            }

            return $page;
        });
}
