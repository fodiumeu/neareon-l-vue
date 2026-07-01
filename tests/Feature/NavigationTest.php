<?php

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('main navigation config contains the mobile bottom navigation targets', function () {
    $navigation = file_get_contents(resource_path('js/config/navigation/app-navigation.ts'));
    $mobileNavigation = substr(
        $navigation,
        strpos($navigation, 'mobileBottomNavItems'),
    );

    expect($navigation)
        ->toContain('mobileBottomNavItems')
        ->and($mobileNavigation)
        ->toContain("title: 'Home'")
        ->toContain("title: 'Entdecken'")
        ->toContain("href: '/explore'")
        ->toContain("title: 'Community'")
        ->toContain("href: '/community'")
        ->toContain("title: 'Nachrichten'")
        ->toContain("href: '/messages'")
        ->toContain("title: 'Profil'")
        ->toContain("href: '/profile'")
        ->not->toContain("title: 'Mitglieder entdecken'")
        ->not->toContain("title: 'Einstellungen'")
        ->and($navigation)
        ->not->toContain("title: 'Chats'");
});

test('desktop navigation is grouped for community communication profile and admin areas', function () {
    $navigation = file_get_contents(resource_path('js/config/navigation/app-navigation.ts'));
    $navMain = file_get_contents(resource_path('js/components/NavMain.vue'));
    $mainNavigation = substr(
        $navigation,
        strpos($navigation, "title: 'Hauptbereich'"),
        strpos($navigation, "title: 'Community'") - strpos($navigation, "title: 'Hauptbereich'"),
    );
    $communityNavigation = substr(
        $navigation,
        strpos($navigation, "title: 'Community'"),
        strpos($navigation, "title: 'Kommunikation'") - strpos($navigation, "title: 'Community'"),
    );

    expect($navigation)
        ->toContain('getMainNavGroups')
        ->toContain("title: 'Hauptbereich'")
        ->toContain("title: 'Community'")
        ->toContain("title: 'Kommunikation'")
        ->toContain("title: 'Profil & Konto'")
        ->toContain("title: 'Admin'")
        ->toContain("title: 'Entdecken'")
        ->toContain("href: '/explore'")
        ->toContain("title: 'Übersicht'")
        ->toContain("href: '/community'")
        ->toContain("title: 'Mitglieder entdecken'")
        ->toContain("href: '/discover'")
        ->toContain("title: 'Gruppen entdecken'")
        ->toContain("href: '/groups'")
        ->toContain("title: 'Events entdecken'")
        ->toContain("href: '/events'")
        ->toContain("title: 'Meine Events'")
        ->toContain("href: '/my-events'")
        ->toContain("title: 'Meine Gruppen'")
        ->toContain("href: '/my-groups'")
        ->toContain("title: 'Kontakte'")
        ->toContain("href: '/contacts'")
        ->toContain("title: 'Follower'")
        ->toContain("href: '/followers'")
        ->toContain("title: 'Ich folge'")
        ->toContain("href: '/following'")
        ->toContain("title: 'Kontaktanfragen'")
        ->toContain("href: '/contact-requests'")
        ->toContain("title: 'Gesendete Anfragen'")
        ->toContain("href: '/contact-requests/sent'")
        ->toContain("title: 'Blockierte Profile'")
        ->toContain("href: '/blocked-profiles'")
        ->toContain('badge: pendingContactRequestsCount')
        ->toContain("title: 'Nachrichten'")
        ->toContain("href: '/messages'")
        ->toContain('badge:')
        ->toContain("'99+'")
        ->toContain("href: '/notifications'")
        ->toContain('unreadNotificationsCount')
        ->toContain("title: 'Einstellungen'")
        ->toContain('editSettingsProfile()')
        ->toContain("title: 'Nutzer / Rollen'")
        ->toContain("href: '/admin#benutzer'")
        ->toContain("title: 'Moderation / Reports'")
        ->toContain("href: '/admin/reports'")
        ->toContain("title: 'Stammdaten'")
        ->toContain("href: '/admin/options'")
        ->toContain("title: 'Sprachen'")
        ->toContain("href: '/admin/options/languages'")
        ->toContain("title: 'Interessen'")
        ->toContain("href: '/admin/options/interests'")
        ->and($navMain)
        ->toContain('v-for="group in groups"')
        ->toContain('<SidebarGroupLabel>{{ group.title }}</SidebarGroupLabel>')
        ->toContain('SidebarMenuBadge')
        ->toContain('useSidebar')
        ->toContain('setOpenMobile(false)')
        ->toContain('@click="closeMobileSidebar"');

    expect($mainNavigation)
        ->not->toContain("title: 'Meine Events'");

    expect($communityNavigation)
        ->toContain("title: 'Meine Gruppen'")
        ->toContain("title: 'Meine Events'")
        ->toContain("href: '/my-events'");
});

test('sidebar preserves badge props and allows admin navigation for admins and owners only', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $navUser = file_get_contents(resource_path('js/components/NavUser.vue'));
    $userMenuContent = file_get_contents(
        resource_path('js/components/UserMenuContent.vue'),
    );

    expect($sidebar)
        ->toContain('page.props.messages.unreadCount')
        ->toContain('unreadMessagesCount')
        ->toContain('page.props.notifications.unreadCount')
        ->toContain('unreadNotificationsCount')
        ->toContain("user?.role === 'admin' || user?.role === 'owner'")
        ->toContain('@click="closeMobileSidebar"')
        ->and($navUser)
        ->toContain('setOpenMobile(false)')
        ->toContain('@navigate="closeMobileSidebar"')
        ->and($userMenuContent)
        ->toContain('navigate')
        ->toContain("@click=\"emit('navigate')\"");
});

test('mobile navigation keeps a compact five item structure', function () {
    $footer = file_get_contents(
        resource_path('js/components/MobileBottomNavigation.vue'),
    );
    $navigation = file_get_contents(resource_path('js/config/navigation/app-navigation.ts'));
    $mobileNavigation = substr(
        $navigation,
        strpos($navigation, 'mobileBottomNavItems'),
    );

    expect($footer)
        ->toContain('grid-cols-5')
        ->toContain("path === '/community'")
        ->toContain("'/community'")
        ->toContain("'/my-groups'")
        ->toContain("'/my-events'")
        ->toContain("'/followers'")
        ->toContain("'/following'")
        ->toContain("'/contact-requests'")
        ->toContain("'/contact-requests/sent'")
        ->toContain("'/blocked-profiles'")
        ->toContain("path === '/messages'")
        ->and($mobileNavigation)
        ->toContain("title: 'Community'")
        ->toContain("href: '/community'")
        ->toContain("title: 'Entdecken'")
        ->toContain("href: '/explore'")
        ->toContain("title: 'Nachrichten'")
        ->toContain("href: '/messages'")
        ->not->toContain("title: 'Events entdecken'")
        ->not->toContain("title: 'Meine Events'")
        ->not->toContain("title: 'Einstellungen'");
});

test('the shared messages prop sums unread messages across conversations', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $sender = User::factory()->create();
    $firstConversation = Conversation::factory()->create();
    $secondConversation = Conversation::factory()->create();

    foreach ([$firstConversation, $secondConversation] as $conversation) {
        ConversationParticipant::factory()
            ->for($conversation)
            ->for($user)
            ->create();
        ConversationParticipant::factory()
            ->for($conversation)
            ->for($sender)
            ->create();
    }

    Message::factory()
        ->count(2)
        ->for($firstConversation)
        ->for($sender, 'sender')
        ->create();
    Message::factory()
        ->count(3)
        ->for($secondConversation)
        ->for($sender, 'sender')
        ->create();
    Message::factory()
        ->for($firstConversation)
        ->for($user, 'sender')
        ->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.unreadCount', 5),
        );
});

test('the shared messages prop is zero without unread messages', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.unreadCount', 0),
        );
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
    'explore.index',
    'discover',
    'groups.index',
    'groups.mine',
    'events.index',
    'events.mine',
    'followers.index',
    'following.index',
    'contact-requests.index',
    'contact-requests.sent',
    'blocked-profiles.index',
]);
