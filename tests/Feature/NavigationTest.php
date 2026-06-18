<?php

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

test('main navigation contains all contact destinations', function () {
    $navigation = file_get_contents(resource_path('js/config/navigation/app-navigation.ts'));

    expect($navigation)
        ->toContain("title: 'Kontakte'")
        ->toContain("href: '/contacts'")
        ->toContain("title: 'Kontaktanfragen'")
        ->toContain("href: '/contact-requests'")
        ->toContain("title: 'Gesendete Anfragen'")
        ->toContain("href: '/contact-requests/sent'")
        ->toContain('badge: pendingContactRequestsCount');
});

test('the navigation badge only counts received pending contact requests', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $otherUsers = User::factory()->count(4)->create();

    ContactRequest::factory()
        ->for($otherUsers[0], 'sender')
        ->for($user, 'receiver')
        ->create();
    ContactRequest::factory()
        ->for($otherUsers[1], 'sender')
        ->for($user, 'receiver')
        ->create([
            'status' => ContactRequestStatus::Accepted,
            'responded_at' => now(),
        ]);
    ContactRequest::factory()
        ->for($otherUsers[2], 'sender')
        ->for($user, 'receiver')
        ->create([
            'status' => ContactRequestStatus::Declined,
            'responded_at' => now(),
        ]);
    ContactRequest::factory()
        ->for($user, 'sender')
        ->for($otherUsers[3], 'receiver')
        ->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contactRequests.pendingReceivedCount', 1),
        );
});

test('guests remain protected from contact navigation destinations', function (
    string $routeName,
) {
    $this->get(route($routeName))
        ->assertRedirect(route('login'));
})->with([
    'contacts.index',
    'contact-requests.index',
    'contact-requests.sent',
]);
