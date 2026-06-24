<?php

test('the NEAREON design foundation exposes the refreshed dark brand tokens', function () {
    $styles = file_get_contents(resource_path('css/app.css'));
    $appShell = file_get_contents(resource_path('views/app.blade.php'));

    expect($styles)
        ->toContain('--neareon-green: #a7ff52')
        ->toContain('--background: #030318')
        ->toContain('--secondary: #05051f')
        ->toContain('--muted: #070720')
        ->toContain('--primary: #a7ff52')
        ->toContain('--action-primary: #5a45c8')
        ->toContain('--action-primary-hover: #6d58e8')
        ->toContain('.dark body')
        ->toContain('color-mix(in oklab, var(--brand-energy) 12%, transparent)')
        ->and($appShell)
        ->toContain('background-color: #030318');
});

test('shared UI primitives carry the refreshed brand surface styling', function () {
    $button = file_get_contents(resource_path('js/components/ui/button/index.ts'));
    $badge = file_get_contents(resource_path('js/components/ui/badge/index.ts'));
    $card = file_get_contents(resource_path('js/components/ui/card/Card.vue'));
    $messages = file_get_contents(resource_path('js/pages/Messages/Show.vue'));

    expect($button)
        ->toContain('bg-action-primary text-action-primary-foreground')
        ->toContain('hover:bg-action-primary-hover')
        ->toContain('hover:border-primary/40')
        ->toContain('hover:shadow-action-primary/20')
        ->and($badge)
        ->toContain('shadow-primary/15')
        ->toContain('border-border/70 bg-secondary/90')
        ->and($card)
        ->toContain('backdrop-blur-md')
        ->toContain('dark:border-border/70')
        ->and($messages)
        ->toContain('bg-action-primary')
        ->not->toContain('bg-[color-mix(in_oklab,var(--primary),black_12%)]');
});

test('profile edit native selects keep purple selection and muted green hover as a fallback', function () {
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($styles)
        ->toContain('.profile-edit-form select option:hover')
        ->toContain('.profile-edit-form select option:checked')
        ->toContain('.profile-edit-form select option:focus')
        ->toContain('.profile-edit-form select option:active')
        ->toContain('background-color: var(--action-primary)')
        ->toContain('color: var(--action-primary-foreground)')
        ->toContain('var(--neareon-green) 55%')
        ->toContain('var(--popover)');
});

test('discover filters use the shared custom select styling instead of native option hover', function () {
    $discover = file_get_contents(resource_path('js/pages/Discover.vue'));

    expect($discover)
        ->toContain("from '@/components/ui/select'")
        ->toContain('<Select v-model="selectedRegionOption">')
        ->toContain('<Select v-model="selectedLanguageOption">')
        ->toContain('<Select v-model="selectedInterestOption">')
        ->toContain("const allFilterValue = '__all__'")
        ->toContain('data-[state=checked]:bg-action-primary')
        ->toContain('data-[highlighted]:bg-[color-mix(in_oklab,var(--neareon-green)_55%,var(--popover))]')
        ->toContain('focus-visible:border-ring')
        ->not->toContain('<select');
});

test('desktop and mobile navigation use the refreshed active and badge states', function () {
    $sidebarButton = file_get_contents(resource_path('js/components/ui/sidebar/index.ts'));
    $sidebarLabel = file_get_contents(resource_path('js/components/ui/sidebar/SidebarGroupLabel.vue'));
    $sidebarBadge = file_get_contents(resource_path('js/components/ui/sidebar/SidebarMenuBadge.vue'));
    $mobileNavigation = file_get_contents(resource_path('js/components/MobileBottomNavigation.vue'));

    expect($sidebarButton)
        ->toContain('data-[active=true]:bg-sidebar-primary/15')
        ->toContain('data-[active=true]:text-sidebar-primary')
        ->and($sidebarLabel)
        ->toContain('tracking-[0.16em] uppercase')
        ->and($sidebarBadge)
        ->toContain('bg-sidebar-primary/15 text-sidebar-primary')
        ->and($mobileNavigation)
        ->toContain('dark:border-primary/15')
        ->toContain('border border-primary/25 bg-primary/15 text-primary');
});
