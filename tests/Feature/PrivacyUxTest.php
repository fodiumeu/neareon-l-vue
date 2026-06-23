<?php

use App\Enums\ContactPermission;
use App\Enums\FollowPermission;
use App\Enums\ProfileVisibility;
use App\Models\Block;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('follow disabled state is provided to profile and discover views', function (
    string $routeName,
    string $propPrefix,
) {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = createOnboardedProfile($owner, [
        'username' => 'follow_disabled',
        'profile_visibility' => ProfileVisibility::Public,
        'follow_permission' => FollowPermission::Nobody,
    ]);

    $route = $routeName === 'discover'
        ? route('discover')
        : route('public-profile.show', $profile->username);

    $this->actingAs($viewer)
        ->get($route)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where("{$propPrefix}.can_follow", false),
        );
})->with([
    'profile' => ['public-profile.show', 'profile'],
    'discover' => ['discover', 'profiles.data.0'],
]);

test('disabled contact requests are provided to profile and discover views', function (
    string $routeName,
    string $propPrefix,
) {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = createOnboardedProfile($owner, [
        'username' => 'contact_disabled',
        'profile_visibility' => ProfileVisibility::Public,
        'contact_permission' => ContactPermission::Nobody,
    ]);

    $route = $routeName === 'discover'
        ? route('discover')
        : route('public-profile.show', $profile->username);

    $this->actingAs($viewer)
        ->get($route)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where("{$propPrefix}.can_send_contact_request", false)
            ->where(
                "{$propPrefix}.contact_request_unavailable_reason",
                'disabled',
            ),
        );
})->with([
    'profile' => ['public-profile.show', 'profile'],
    'discover' => ['discover', 'profiles.data.0'],
]);

test('followers-only contact requests provide a follow-required hint', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = createOnboardedProfile($owner, [
        'username' => 'contact_followers_only',
        'contact_permission' => ContactPermission::Followers,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.can_send_contact_request', false)
            ->where(
                'profile.contact_request_unavailable_reason',
                'follow_required',
            ),
        );

    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $owner->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.can_send_contact_request', true)
            ->where('profile.contact_request_unavailable_reason', null),
        );
});

test('contact action UI renders disabled privacy states', function () {
    $actions = file_get_contents(
        resource_path('js/components/ContactActions.vue'),
    );

    expect($actions)
        ->toContain('relationshipActionUnavailableText.followDisabled')
        ->toContain('relationshipActionUnavailableText.contactRequestDisabled')
        ->toContain('relationshipActionUnavailableText.contactRequestFollowRequired')
        ->toContain('v-if="isFollowing || canFollow"')
        ->toContain("status === 'none' && canSendContactRequest");
});

test('blocked profiles page lists profiles blocked by the current user', function () {
    Storage::fake('public');

    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $blockedUser = User::factory()->create();
    $photoPath = 'profile-photos/blocked-profile.jpg';
    $blockedProfile = createOnboardedProfile($blockedUser, [
        'username' => 'listed_blocked_profile',
        'display_name' => 'Blockiertes Profil',
        'profile_photo_path' => $photoPath,
    ]);
    $block = Block::factory()
        ->for($viewer, 'blocker')
        ->for($blockedUser, 'blocked')
        ->create();

    $this->actingAs($viewer)
        ->get(route('blocked-profiles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BlockedProfiles/Index')
            ->has('blockedProfiles', 1)
            ->where('blockedProfiles.0.display_name', 'Blockiertes Profil')
            ->where('blockedProfiles.0.username', $blockedProfile->username)
            ->where(
                'blockedProfiles.0.profile_photo_url',
                "/storage/{$photoPath}",
            )
            ->where(
                'blockedProfiles.0.blocked_at',
                $block->created_at->toIso8601String(),
            ),
        );
});

test('blocked profiles are sorted newest first', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $olderUser = User::factory()->create();
    createOnboardedProfile($olderUser, ['username' => 'older_blocked']);
    $newerUser = User::factory()->create();
    createOnboardedProfile($newerUser, ['username' => 'newer_blocked']);

    Block::factory()
        ->for($viewer, 'blocker')
        ->for($olderUser, 'blocked')
        ->create(['created_at' => now()->subHour()]);
    Block::factory()
        ->for($viewer, 'blocker')
        ->for($newerUser, 'blocked')
        ->create(['created_at' => now()]);

    $this->actingAs($viewer)
        ->get(route('blocked-profiles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('blockedProfiles', 2)
            ->where('blockedProfiles.0.username', 'newer_blocked')
            ->where('blockedProfiles.1.username', 'older_blocked'),
        );
});

test('a user can unblock a profile from the blocked profiles page', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $blockedUser = User::factory()->create();
    $blockedProfile = createOnboardedProfile($blockedUser);
    Block::factory()
        ->for($viewer, 'blocker')
        ->for($blockedUser, 'blocked')
        ->create();

    $this->actingAs($viewer)
        ->from(route('blocked-profiles.index'))
        ->delete(route('public-profile.unblock', $blockedProfile->username))
        ->assertRedirect(route('blocked-profiles.index'))
        ->assertSessionHas('success', 'Blockierung wurde aufgehoben.');

    expect($viewer->hasBlocked($blockedUser))->toBeFalse();

    $this->actingAs($viewer)
        ->get(route('blocked-profiles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('blockedProfiles', 0),
        );
});

test('blocked profiles page uses the shared polished card ux and confirmation dialog', function () {
    $page = file_get_contents(
        resource_path('js/pages/BlockedProfiles/Index.vue'),
    );

    expect($page)
        ->toContain('class="size-16 shrink-0 shadow-sm"')
        ->toContain('profile.profile_photo_url')
        ->toContain('formatContactRelativeTime(')
        ->toContain('formatContactRelativeTimeTitle(')
        ->toContain('md:hover:border-primary/35')
        ->toContain('motion-reduce:transition-none')
        ->toContain('overflow-x-hidden')
        ->toContain('Blockierung aufheben?')
        ->toContain('euren Profileinstellungen')
        ->toContain('Frühere Follow- und')
        ->toContain('Kontaktbeziehungen werden nicht')
        ->toContain('automatisch wiederhergestellt.')
        ->toContain('Abbrechen')
        ->toContain('Keine blockierten Profile')
        ->toContain('ShieldCheck');
});

test('the blocked profiles index uses the required middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('blocked-profiles.index')
        ->gatherMiddleware();

    expect($middleware)->toContain(
        'web',
        'auth',
        'age.gate',
        'verified',
        'onboarding.complete',
    );
});

test('onboarding middleware protects the blocked profiles index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('blocked-profiles.index'))
        ->assertRedirect(route('onboarding.details'));
});

test('blocked profiles navigation entry is placed before messages without a badge', function () {
    $navigation = file_get_contents(
        resource_path('js/config/navigation/app-navigation.ts'),
    );
    $blockedPosition = strpos($navigation, "title: 'Blockierte Profile'");
    $messagesPosition = strpos($navigation, "title: 'Nachrichten'");
    $blockedNavigationItem = substr(
        $navigation,
        $blockedPosition,
        $messagesPosition - $blockedPosition,
    );

    expect($navigation)
        ->toContain("href: '/blocked-profiles'")
        ->and($blockedPosition)->not->toBeFalse()
        ->and($messagesPosition)->not->toBeFalse()
        ->and($blockedPosition)->toBeLessThan($messagesPosition)
        ->and($blockedNavigationItem)->not->toContain('badge:');
});

test('guests cannot open the blocked profiles page', function () {
    $this->get(route('blocked-profiles.index'))
        ->assertRedirect(route('login'));
});
