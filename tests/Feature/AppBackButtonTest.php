<?php

test('the shared back button uses internal history with a safe fallback', function () {
    $component = file_get_contents(
        resource_path('js/components/AppBackButton.vue'),
    );
    $history = file_get_contents(
        resource_path('js/lib/appNavigationHistory.ts'),
    );

    expect($component)
        ->toContain('data-test="app-back-button"')
        ->toContain('navigateBack(props.fallback)')
        ->and($history)
        ->toContain('window.history.back()')
        ->toContain('window.history.length > 1')
        ->toContain('router.visit(normalizedFallback')
        ->toContain('replace: true')
        ->toContain('url.origin !== window.location.origin')
        ->toContain('previous !== current');
});

test('foreign profiles use discover as fallback while the own profile has no back button', function () {
    $profilePage = file_get_contents(
        resource_path('js/pages/Profile/Show.vue'),
    );

    expect($profilePage)
        ->toContain('v-if="!props.profile.isOwnProfile"')
        ->toContain('fallback="/discover"')
        ->toContain('label="Zurück zur Übersicht"');
});

test('message conversations use the message list as fallback', function () {
    $messagePage = file_get_contents(
        resource_path('js/pages/Messages/Show.vue'),
    );

    expect($messagePage)
        ->toContain('<AppBackButton')
        ->toContain('fallback="/messages"')
        ->toContain('label="Zurück"');
});

test('profile editing uses the own profile as fallback', function () {
    $profileEditPage = file_get_contents(
        resource_path('js/pages/Profile/Edit.vue'),
    );

    expect($profileEditPage)
        ->toContain('<AppBackButton')
        ->toContain('fallback="/profile"')
        ->toContain('label="Zurück zum Profil"');
});

test('admin user details use the admin user overview as fallback', function () {
    $adminUserPage = file_get_contents(
        resource_path('js/pages/admin/Users/Show.vue'),
    );

    expect($adminUserPage)
        ->toContain('<AppBackButton')
        ->toContain('fallback="/admin#benutzer"')
        ->toContain('label="Zurück zur Benutzerübersicht"');
});

test('natural app entry pages do not render the back button', function (
    string $page,
) {
    $content = file_get_contents(resource_path("js/pages/{$page}"));

    expect($content)->not->toContain('AppBackButton');
})->with([
    'home' => 'Dashboard.vue',
    'discover' => 'Discover.vue',
    'contacts' => 'Contacts/Index.vue',
    'received contact requests' => 'ContactRequests/Index.vue',
    'sent contact requests' => 'ContactRequests/Sent.vue',
    'blocked profiles' => 'BlockedProfiles/Index.vue',
    'messages' => 'Messages/Index.vue',
    'notifications' => 'Notifications/Index.vue',
]);

test('groups foundation exposes real routes while events remain ready for later integration', function () {
    expect(Route::has('groups.index'))->toBeTrue()
        ->and(Route::has('groups.show'))->toBeTrue()
        ->and(Route::has('events.index'))->toBeFalse();
});
