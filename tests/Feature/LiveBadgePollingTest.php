<?php

use App\Enums\ContactRequestStatus;
use App\Enums\InternalNotificationType;
use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Notifications\InternalNotification;

test('authenticated users can fetch current navigation badge counts', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $conversation = Conversation::factory()->create();

    ConversationParticipant::factory()->for($conversation)->for($user)->create();
    ConversationParticipant::factory()->for($conversation)->for($sender)->create();
    Message::factory()
        ->count(2)
        ->for($conversation)
        ->for($sender, 'sender')
        ->create();
    ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($user, 'receiver')
        ->create(['status' => ContactRequestStatus::Pending]);
    $user->notify(new InternalNotification(
        InternalNotificationType::NewFollower,
        'Neuer Follower',
        'Sender folgt dir jetzt.',
        '/discover',
    ));

    $this->actingAs($user)
        ->getJson(route('navigation.badges'))
        ->assertOk()
        ->assertExactJson([
            'unreadMessages' => 2,
            'unreadNotifications' => 1,
            'pendingContactRequests' => 1,
        ]);
});

test('navigation badge endpoint requires authentication', function () {
    $this->getJson(route('navigation.badges'))
        ->assertUnauthorized();
});

test('live badge polling is central, visibility aware, and limited to badge data', function () {
    $polling = file_get_contents(
        resource_path('js/composables/useLiveBadgePolling.ts'),
    );

    expect($polling)
        ->toContain('const pollingInterval = 30_000')
        ->toContain("document.visibilityState !== 'visible'")
        ->toContain("document.addEventListener('visibilitychange'")
        ->toContain('document.removeEventListener(')
        ->toContain("fetch('/navigation/badges'")
        ->toContain("credentials: 'same-origin'")
        ->toContain('setInterval(')
        ->toContain('clearInterval(interval)')
        ->not->toContain('router.reload')
        ->not->toContain('setInterval(() => router');
});

test('badges pulse only when a polled value increases', function () {
    $polling = file_get_contents(
        resource_path('js/composables/useLiveBadgePolling.ts'),
    );
    $navigation = file_get_contents(
        resource_path('js/components/NavMain.vue'),
    );

    expect($polling)
        ->toContain('next[key] > counts[key]')
        ->toContain('pulse(key)')
        ->toContain('const pulseDuration = 1_000')
        ->and($navigation)
        ->toContain('item.pulseBadge')
        ->toContain('animate-pulse');
});
