<?php

use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('discover paginates profiles with twelve items per page', function () {
    $viewer = paginationViewer();
    createPaginationProfiles(25);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles.data', 12)
            ->where('profiles.current_page', 1)
            ->where('profiles.last_page', 3)
            ->where('profiles.per_page', 12)
            ->where('profiles.total', 25),
        );
});

test('discover pagination keeps ranking before slicing pages', function () {
    $viewer = paginationViewer(['region' => 'Berlin']);
    createPaginationProfiles(12, region: 'Hamburg');
    paginationProfile(99, 'Berlin', 'Zulu Region Match');

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profiles.data.0.username', 'pagination_099')
            ->where('profiles.data.0.display_name', 'Zulu Region Match')
            ->has('profiles.data', 12)
            ->where('profiles.last_page', 2),
        );
});

test('discover search results remain paginated', function () {
    $viewer = paginationViewer();

    foreach (range(1, 13) as $index) {
        paginationProfile(
            $index,
            displayName: sprintf('Fit Member %03d', $index),
        );
    }
    paginationProfile(99, displayName: 'Other Member');

    $this->actingAs($viewer)
        ->get(route('discover', ['q' => 'fit', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles.data', 1)
            ->where('profiles.current_page', 2)
            ->where('profiles.total', 13)
            ->where('search', 'fit')
            ->where('profiles.data.0.username', 'pagination_013'),
        );
});

test('discover filter results remain paginated', function () {
    $viewer = paginationViewer();
    createPaginationProfiles(13, region: 'Hamburg');
    paginationProfile(99, 'Berlin');

    $this->actingAs($viewer)
        ->get(route('discover', ['region' => 'Hamburg', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles.data', 1)
            ->where('profiles.current_page', 2)
            ->where('profiles.total', 13)
            ->where('filters.region', 'Hamburg'),
        );
});

test('discover combines search filters and pagination', function () {
    $viewer = paginationViewer();

    foreach (range(1, 13) as $index) {
        paginationProfile(
            $index,
            'Hamburg',
            "Family Match {$index}",
        );
    }
    paginationProfile(99, 'Hamburg', 'Other Hamburg');
    paginationProfile(100, 'Berlin', 'Family Berlin');

    $this->actingAs($viewer)
        ->get(route('discover', [
            'q' => 'family',
            'region' => 'Hamburg',
            'page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles.data', 1)
            ->where('profiles.current_page', 2)
            ->where('profiles.total', 13)
            ->where('search', 'family')
            ->where('filters.region', 'Hamburg'),
        );
});

test('discover last page has no next page', function () {
    $viewer = paginationViewer();
    createPaginationProfiles(13);

    $this->actingAs($viewer)
        ->get(route('discover', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles.data', 1)
            ->where('profiles.current_page', 2)
            ->where('profiles.next_page_url', null)
            ->where('profiles.last_page', 2),
        );
});

test('discover empty results retain pagination metadata', function () {
    $viewer = paginationViewer();
    paginationProfile(1);

    $this->actingAs($viewer)
        ->get(route('discover', ['q' => 'missing']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles.data', 0)
            ->where('profiles.current_page', 1)
            ->where('profiles.total', 0)
            ->where('profiles.last_page', 1),
        );
});

test('discover pages contain no duplicate profiles', function () {
    $viewer = paginationViewer();
    createPaginationProfiles(24);

    $firstPage = $this->actingAs($viewer)->get(route('discover'));
    $secondPage = $this->actingAs($viewer)
        ->get(route('discover', ['page' => 2]));
    $firstUsernames = collect(
        $firstPage->inertiaProps('profiles.data'),
    )->pluck('username');
    $secondUsernames = collect(
        $secondPage->inertiaProps('profiles.data'),
    )->pluck('username');

    expect($firstUsernames)
        ->toHaveCount(12)
        ->and($secondUsernames)->toHaveCount(12)
        ->and($firstUsernames->intersect($secondUsernames))->toBeEmpty();
});

test('discover page provides desktop pagination and mobile infinite scroll', function () {
    $page = file_get_contents(resource_path('js/pages/Discover.vue'));

    expect($page)
        ->toContain('<InfiniteScroll')
        ->toContain('data="profiles"')
        ->toContain('only-next')
        ->toContain(':manual="!isMobile"')
        ->toContain("useMediaQuery('(max-width: 767px)')")
        ->toContain('Lade weitere Profile...')
        ->toContain('md:hidden')
        ->toContain('← Vorherige')
        ->toContain('Nächste →')
        ->toContain('profiles.last_page > 1')
        ->toContain('hidden items-center justify-center gap-2 md:flex')
        ->toContain("reset: ['profiles']");
});

/**
 * @param  array<string, mixed>  $attributes
 */
function paginationViewer(array $attributes = []): User
{
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer, array_merge([
        'region' => 'Viewer Region',
    ], $attributes));

    return $viewer;
}

function createPaginationProfiles(
    int $count,
    string $region = 'Other Region',
): void {
    foreach (range(1, $count) as $index) {
        paginationProfile($index, $region);
    }
}

function paginationProfile(
    int $index,
    string $region = 'Other Region',
    ?string $displayName = null,
): Profile {
    return Profile::factory()->create([
        'username' => sprintf('pagination_%03d', $index),
        'display_name' => $displayName ?? sprintf(
            'Pagination Member %03d',
            $index,
        ),
        'region' => $region,
        'profile_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Public,
    ]);
}
