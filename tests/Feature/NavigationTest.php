<?php

test('main navigation config contains the mobile bottom navigation targets', function () {
    $navigation = file_get_contents(resource_path('js/config/navigation/app-navigation.ts'));

    expect($navigation)
        ->toContain('mobileBottomNavItems')
        ->toContain("title: 'Home'")
        ->toContain("title: 'Entdecken'")
        ->toContain("title: 'Profil'")
        ->toContain("title: 'Einstellungen'")
        ->toContain("href: '/discover'")
        ->toContain("href: '/profile'")
        ->toContain('editSettingsProfile()')
        ->not->toContain("title: 'Chats'");
});
