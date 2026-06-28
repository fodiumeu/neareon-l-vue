<?php

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open the community hub', function () {
    $this->get(route('community.index'))
        ->assertRedirect(route('login'));
});

test('community hub renders the existing community entry points', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community/Index')
            ->where('communityCounts.pendingContactRequests', 0),
        );

    $page = file_get_contents(resource_path('js/pages/Community/Index.vue'));

    expect($page)
        ->toContain('Meine Gruppen')
        ->toContain("href: '/my-groups'")
        ->toContain('Gruppen, denen du angehörst oder zu denen du eingeladen wurdest.')
        ->toContain('Meine Gruppen öffnen')
        ->toContain('Meine Events')
        ->toContain("href: '/my-events'")
        ->toContain('Events, die du erstellt hast oder an denen du teilnimmst.')
        ->toContain('Meine Events öffnen')
        ->toContain('Kontakte')
        ->toContain("href: '/contacts'")
        ->toContain('Follower')
        ->toContain("href: '/followers'")
        ->toContain('Ich folge')
        ->toContain("href: '/following'")
        ->toContain('Kontaktanfragen')
        ->toContain("href: '/contact-requests'")
        ->toContain('Gesendete Anfragen')
        ->toContain("href: '/contact-requests/sent'")
        ->toContain('Blockierte Profile')
        ->toContain("href: '/blocked-profiles'")
        ->not->toContain("href: '/admin'");
});

test('community hub exposes the pending contact request count from existing badge data', function () {
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $sender = User::factory()->create();
    createOnboardedProfile($sender);

    ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create([
            'status' => ContactRequestStatus::Pending,
        ]);

    $this->actingAs($receiver)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community/Index')
            ->where('communityCounts.pendingContactRequests', 1),
        );
});

test('community navigation points to existing routes and keeps the contact request badge', function () {
    $navigation = file_get_contents(resource_path('js/config/navigation/app-navigation.ts'));
    $mobileNavigation = substr(
        $navigation,
        strpos($navigation, 'mobileBottomNavItems'),
    );

    expect($navigation)
        ->toContain("title: 'Übersicht'")
        ->toContain("href: '/community'")
        ->toContain("title: 'Gruppen entdecken'")
        ->toContain("href: '/groups'")
        ->toContain("title: 'Meine Gruppen'")
        ->toContain("href: '/my-groups'")
        ->toContain("title: 'Meine Events'")
        ->toContain("href: '/my-events'")
        ->toContain("href: '/contacts'")
        ->toContain("href: '/followers'")
        ->toContain("href: '/following'")
        ->toContain("href: '/contact-requests'")
        ->toContain("href: '/contact-requests/sent'")
        ->toContain("href: '/blocked-profiles'")
        ->toContain('badge: pendingContactRequestsCount')
        ->and($mobileNavigation)
        ->toContain("title: 'Community'")
        ->toContain("href: '/community'")
        ->not->toContain("title: 'Meine Events'")
        ->not->toContain("href: '/my-events'");
});
