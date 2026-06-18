<?php

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function addConversationParticipant(
    Conversation $conversation,
    User $user,
): ConversationParticipant {
    return ConversationParticipant::factory()
        ->for($conversation)
        ->for($user)
        ->create();
}

test('guests cannot view conversations', function () {
    $this->get(route('messages.index'))
        ->assertRedirect(route('login'));
});

test('users only see conversations they participate in', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $otherUser = User::factory()->create();
    Profile::factory()->for($otherUser)->create([
        'display_name' => 'Visible Participant',
        'username' => 'visible_participant',
    ]);
    $visibleConversation = Conversation::factory()->create();
    addConversationParticipant($visibleConversation, $viewer);
    addConversationParticipant($visibleConversation, $otherUser);

    $hiddenConversation = Conversation::factory()->create();
    addConversationParticipant($hiddenConversation, User::factory()->create());
    addConversationParticipant($hiddenConversation, User::factory()->create());

    $this->actingAs($viewer)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Messages/Index')
            ->has('conversations', 1)
            ->where('conversations.0.conversation_id', $visibleConversation->id)
            ->where('conversations.0.other_participant.display_name', 'Visible Participant')
            ->where('conversations.0.other_participant.username', 'visible_participant')
            ->where('conversations.0.participant_count', 2)
            ->where('conversations.0.unread_count', 0)
            ->has('conversations.0.created_at')
            ->has('conversations.0.updated_at'),
        );
});

test('conversation list provides the correct unread count', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $otherUser = User::factory()->create();
    $conversation = Conversation::factory()->create();
    addConversationParticipant($conversation, $viewer);
    addConversationParticipant($conversation, $otherUser);
    Message::factory()
        ->count(3)
        ->for($conversation)
        ->for($otherUser, 'sender')
        ->create();
    Message::factory()
        ->for($conversation)
        ->for($viewer, 'sender')
        ->create();

    $otherConversation = Conversation::factory()->create();
    Message::factory()
        ->count(2)
        ->for($otherConversation)
        ->for($otherUser, 'sender')
        ->create();

    $this->actingAs($viewer)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.unread_count', 3),
        );
});

test('conversation list returns zero when no message is unread', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $conversation = Conversation::factory()->create();
    addConversationParticipant($conversation, $viewer);
    addConversationParticipant($conversation, User::factory()->create());

    $this->actingAs($viewer)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.unread_count', 0),
        );
});

test('conversation list delivers counts above the visual badge limit', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $otherUser = User::factory()->create();
    $conversation = Conversation::factory()->create();
    addConversationParticipant($conversation, $viewer);
    addConversationParticipant($conversation, $otherUser);
    Message::factory()
        ->count(100)
        ->for($conversation)
        ->for($otherUser, 'sender')
        ->create();

    $this->actingAs($viewer)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.unread_count', 100),
        );
});

test('conversations are sorted by latest activity first', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $olderConversation = Conversation::factory()->create([
        'updated_at' => now()->subHour(),
    ]);
    addConversationParticipant($olderConversation, $viewer);
    addConversationParticipant($olderConversation, User::factory()->create());
    $newerConversation = Conversation::factory()->create([
        'updated_at' => now(),
    ]);
    addConversationParticipant($newerConversation, $viewer);
    addConversationParticipant($newerConversation, User::factory()->create());

    $this->actingAs($viewer)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversations', 2)
            ->where('conversations.0.conversation_id', $newerConversation->id)
            ->where('conversations.1.conversation_id', $olderConversation->id),
        );
});

test('the conversation index returns an empty collection without conversations', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $this->actingAs($viewer)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Messages/Index')
            ->has('conversations', 0),
        );
});

test('age gate middleware protects the conversation index', function () {
    $user = User::factory()->withoutAgeGate()->create();

    $this->actingAs($user)
        ->get(route('messages.index'))
        ->assertRedirect(route('age-gate.show'));
});

test('onboarding middleware protects the conversation index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('messages.index'))
        ->assertRedirect(route('onboarding.details'));
});

test('the conversation index uses the required middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('messages.index')
        ->gatherMiddleware();

    expect($middleware)->toContain(
        'web',
        'auth',
        'age.gate',
        'verified',
        'onboarding.complete',
    );
});
