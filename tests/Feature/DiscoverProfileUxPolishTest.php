<?php

use App\Models\Follow;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('following from discover returns to discover and refreshes the relationship state', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $target = User::factory()->create();
    $profile = Profile::factory()->for($target)->create([
        'username' => 'discover_follow_target',
        'display_name' => 'Discover Follow Target',
    ]);

    $this->actingAs($viewer)
        ->from(route('discover'))
        ->post(route('public-profile.follow', $profile->username), [
            'context' => 'discover',
        ])
        ->assertRedirect(route('discover'));

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast', [
                'type' => 'success',
                'message' => 'Du folgst diesem Profil jetzt.',
            ])
            ->where('profiles.data.0.username', $profile->username)
            ->where('profiles.data.0.is_following', true),
        );

    expect($viewer->isFollowing($target))->toBeTrue();
});

test('unfollowing from discover returns to discover and provides a toast', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $target = User::factory()->create();
    $profile = Profile::factory()->for($target)->create([
        'username' => 'discover_unfollow_target',
    ]);
    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $target->id,
    ]);

    $this->actingAs($viewer)
        ->from(route('discover'))
        ->delete(route('public-profile.unfollow', $profile->username), [
            'context' => 'discover',
        ])
        ->assertRedirect(route('discover'));

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast', [
                'type' => 'success',
                'message' => 'Du folgst diesem Profil nicht mehr.',
            ])
            ->where('profiles.data.0.is_following', false),
        );

    expect($viewer->isFollowing($target))->toBeFalse();
});

test('discover pagination resets scroll while mobile infinite scroll stays enabled', function () {
    $page = file_get_contents(resource_path('js/pages/Discover.vue'));

    expect($page)
        ->toContain('<InfiniteScroll')
        ->toContain(':manual="!isMobile"')
        ->toContain('<Link :href="pageUrl(page)">')
        ->not->toContain(':href="pageUrl(page)" preserve-scroll')
        ->not->toContain(':href="profiles.prev_page_url" preserve-scroll')
        ->not->toContain(':href="profiles.next_page_url" preserve-scroll');
});

test('discover uses the polished privacy placeholder and inline follow context', function () {
    $page = file_get_contents(resource_path('js/pages/Discover.vue'));
    $normalizedPage = preg_replace('/\s+/', ' ', $page);
    $actions = file_get_contents(
        resource_path('js/components/ContactActions.vue'),
    );

    expect($normalizedPage)
        ->toContain(
            'Einige Profilinformationen sind nur für Kontakte sichtbar.',
        )
        ->toContain('stay-on-page')
        ->not->toContain(
            'Weitere Angaben sind für Discover aktuell nicht sichtbar.',
        )
        ->and($actions)
        ->toContain('name="context"')
        ->toContain('value="discover"');
});

test('discover empty state offers a clear reset path for active searches and filters', function () {
    $page = file_get_contents(resource_path('js/pages/Discover.vue'));
    $normalizedPage = preg_replace('/\s+/', ' ', $page);

    expect($page)
        ->toContain('const hasActiveFilters = computed')
        ->toContain('const hasActiveDiscoverQuery = computed')
        ->toContain(':disabled="!hasActiveFilters"')
        ->toContain('v-if="hasActiveDiscoverQuery"')
        ->toContain('v-if="hasActiveFilters"')
        ->toContain('href="/discover"')
        ->and($normalizedPage)
        ->toContain(
            'Passe deine Suche oder Filter an. Du kannst auch wieder alle sichtbaren Profile anzeigen.',
        )
        ->toContain('Alle Profile anzeigen');
});

test('contact removal is hidden behind the more actions menu', function () {
    $page = file_get_contents(resource_path('js/pages/Contacts/Index.vue'));

    expect($page)
        ->toContain('<DropdownMenu>')
        ->toContain('<MoreHorizontal')
        ->toContain('Weitere Aktionen')
        ->toContain('<DropdownMenuItem')
        ->toContain('variant="destructive"')
        ->toContain('Verbindung entfernen?')
        ->toContain('disconnectingContactId = contact.id');
});

test('discover uses custom filter selects while profile edit keeps readable native select styling', function () {
    $discover = file_get_contents(resource_path('js/pages/Discover.vue'));
    $profile = file_get_contents(resource_path('js/pages/Profile/Edit.vue'));
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($discover)
        ->toContain('class="discover-filter-controls"')
        ->toContain("from '@/components/ui/select'")
        ->toContain('<Select v-model="selectedRegionOption">')
        ->toContain('<Select v-model="selectedLanguageOption">')
        ->toContain('<Select v-model="selectedInterestOption">')
        ->not->toContain('<select')
        ->and($profile)
        ->toContain('class="profile-edit-form space-y-6"')
        ->and($styles)
        ->toContain('.profile-edit-form select')
        ->toContain('option:checked')
        ->toContain('option:hover')
        ->toContain('focus-visible:ring-[3px]');
});
