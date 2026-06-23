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
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($navigation)
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
        ->and($sidebar)
        ->toContain('page.props.messages.unreadCount')
        ->toContain('unreadMessagesCount');
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
    'followers.index',
    'following.index',
    'contact-requests.index',
    'contact-requests.sent',
    'blocked-profiles.index',
]);
