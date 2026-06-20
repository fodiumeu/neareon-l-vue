<?php

test('app layout provides a sticky blurred mobile header and unchanged desktop header', function () {
    $header = file_get_contents(
        resource_path('js/components/AppSidebarHeader.vue'),
    );
    $layout = file_get_contents(
        resource_path('js/layouts/app/AppSidebarLayout.vue'),
    );

    expect($header)
        ->toContain('data-test="mobile-sticky-header"')
        ->toContain('sticky top-0 z-30')
        ->toContain('bg-background/90')
        ->toContain('backdrop-blur')
        ->toContain('md:hidden')
        ->toContain('data-test="desktop-app-header"')
        ->toContain('hidden')
        ->toContain('md:flex')
        ->and($layout)
        ->toContain('overflow-x-clip pb-24 md:pb-0')
        ->not->toContain('overflow-x-hidden pb-24 md:pb-0');
});

test('mobile header always shows the current page name', function () {
    $header = file_get_contents(
        resource_path('js/components/AppSidebarHeader.vue'),
    );

    expect($header)
        ->toContain("props.breadcrumbs.at(-1)?.title ?? 'NEAREON'")
        ->toContain('{{ pageName }}')
        ->toContain('truncate text-sm font-semibold');
});

test('mobile detail routes use the central app back button with safe fallbacks', function () {
    $header = file_get_contents(
        resource_path('js/components/AppSidebarHeader.vue'),
    );
    $navigation = file_get_contents(
        resource_path(
            'js/config/navigation/mobile-header-navigation.ts',
        ),
    );

    expect($header)
        ->toContain('<AppBackButton')
        ->toContain(':fallback="mobileBack.fallback"')
        ->toContain('v-if="mobileBack"')
        ->and($navigation)
        ->toContain("path === '/profile/edit'")
        ->toContain("fallback: '/profile'")
        ->toContain('/^\\/u\\/[^/]+$/')
        ->toContain("fallback: '/discover'")
        ->toContain('/^\\/messages\\/[^/]+$/')
        ->toContain("fallback: '/messages'");
});

test('main mobile pages keep the menu trigger instead of showing a back action', function () {
    $navigation = file_get_contents(
        resource_path(
            'js/config/navigation/mobile-header-navigation.ts',
        ),
    );
    $header = file_get_contents(
        resource_path('js/components/AppSidebarHeader.vue'),
    );

    expect($navigation)
        ->not->toContain("path === '/notifications'")
        ->not->toContain("path === '/contact-requests'")
        ->and($header)
        ->toContain('<SidebarTrigger v-else');
});

test('existing page back buttons remain available on desktop only', function (
    string $page,
) {
    $content = file_get_contents(resource_path("js/pages/{$page}"));

    expect($content)
        ->toContain('<AppBackButton')
        ->toContain('class="hidden md:inline-flex"');
})->with([
    'public profile' => 'Profile/Show.vue',
    'profile editing' => 'Profile/Edit.vue',
    'conversation' => 'Messages/Show.vue',
]);

test('mobile footer navigation remains unchanged by sticky header behavior', function () {
    $layout = file_get_contents(
        resource_path('js/layouts/app/AppSidebarLayout.vue'),
    );
    $footer = file_get_contents(
        resource_path('js/components/MobileBottomNavigation.vue'),
    );

    expect($layout)->toContain('<MobileBottomNavigation />')
        ->and($footer)
        ->toContain('fixed inset-x-0 bottom-0')
        ->toContain('md:hidden');
});
