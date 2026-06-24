<?php

test('community subpages include a stable link back to the community overview', function (string $page) {
    $content = file_get_contents(resource_path("js/pages/{$page}"));

    expect($content)
        ->toContain("import CommunityBackLink from '@/components/CommunityBackLink.vue'")
        ->toContain('<CommunityBackLink />');
})->with([
    'contacts' => 'Contacts/Index.vue',
    'followers' => 'Followers/Index.vue',
    'following' => 'Following/Index.vue',
    'contact requests' => 'ContactRequests/Index.vue',
    'sent contact requests' => 'ContactRequests/Sent.vue',
    'blocked profiles' => 'BlockedProfiles/Index.vue',
]);

test('the community backlink points to the hub without relying on browser history', function () {
    $component = file_get_contents(resource_path('js/components/CommunityBackLink.vue'));

    expect($component)
        ->toContain('href="/community"')
        ->toContain('Zurück zur Community')
        ->toContain('variant="ghost"')
        ->toContain('focus-visible:ring-primary/45')
        ->not->toContain('navigateBack');
});

test('mobile bottom navigation keeps the community target and uses a green focus ring', function () {
    $navigation = file_get_contents(resource_path('js/config/navigation/app-navigation.ts'));
    $footer = file_get_contents(resource_path('js/components/MobileBottomNavigation.vue'));

    expect($navigation)
        ->toContain("title: 'Community'")
        ->toContain("href: '/community'")
        ->and($footer)
        ->toContain("path === '/community'")
        ->toContain("'/community'")
        ->toContain('focus-visible:border-primary/60')
        ->toContain('focus-visible:ring-primary/45')
        ->toContain('focus-visible:outline-none');
});
