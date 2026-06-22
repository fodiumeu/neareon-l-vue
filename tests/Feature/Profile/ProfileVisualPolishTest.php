<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('discover receives existing visible commonalities for profile cards', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = createOnboardedProfile($owner, [
        'username' => 'visual_common_profile',
    ]);

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profiles.data.0.username', $profile->username)
            ->where('profiles.data.0.common_languages', ['Deutsch'])
            ->where('profiles.data.0.common_interests', ['Community']),
        );
});

test('discover cards render limited commonality badges and polished card styling', function () {
    $discover = file_get_contents(resource_path('js/pages/Discover.vue'));
    $normalizedDiscover = preg_replace('/\s+/', ' ', $discover);

    expect($normalizedDiscover)
        ->toContain('profile.common_languages.slice( 0, 2, )')
        ->toContain('profile.common_interests.slice( 0, 3, )')
        ->toContain('Gemeinsame Sprachen')
        ->toContain('Gemeinsame Interessen')
        ->toContain('class="size-16 shrink-0 shadow-sm"')
        ->toContain('text-lg font-bold')
        ->toContain('md:hover:-translate-y-0.5')
        ->toContain('motion-reduce:transition-none')
        ->toContain('class="flex h-full flex-col')
        ->toContain('class="mt-auto space-y-3"');
});

test('discover cards compact visible languages and interests after three items', function () {
    $discover = file_get_contents(resource_path('js/pages/Discover.vue'));
    $normalizedDiscover = preg_replace('/\s+/', ' ', $discover);

    expect($normalizedDiscover)
        ->toContain('profile.languages.slice( 0, 3, )')
        ->toContain('v-if="profile.languages.length > 3"')
        ->toContain('+{{ profile.languages.length - 3 }} weitere')
        ->toContain('profile.interests.slice( 0, 3, )')
        ->toContain('v-if="profile.interests.length > 3"')
        ->toContain('+{{ profile.interests.length - 3 }} weitere')
        ->toContain('profile.common_languages.slice( 0, 2, )')
        ->toContain('profile.common_interests.slice( 0, 3, )');
});

test('profile commonalities render as badges while metadata remains available', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = createOnboardedProfile($owner, [
        'username' => 'visual_profile_badges',
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.common_languages', ['Deutsch'])
            ->where('profile.common_interests', ['Community'])
            ->has('profile.member_since'),
        );

    $profilePage = file_get_contents(
        resource_path('js/pages/Profile/Show.vue'),
    );

    expect($profilePage)
        ->toContain('v-for="language in props.profile')
        ->toContain('v-for="interest in props.profile')
        ->toContain('<Badge')
        ->toContain('lg:size-28')
        ->toContain('text-base font-semibold');
});

test('discover and profile use the same friendly empty bio state', function () {
    $friendlyText = 'Dieses Mitglied hat noch keine Bio hinterlegt.';

    foreach ([
        resource_path('js/pages/Discover.vue'),
        resource_path('js/pages/Profile/Show.vue'),
    ] as $page) {
        $contents = preg_replace(
            '/\s+/',
            ' ',
            file_get_contents($page),
        );

        expect($contents)
            ->toContain($friendlyText)
            ->toContain('border-dashed');
    }
});
