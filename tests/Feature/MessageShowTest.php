<?php

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function participateInConversation(
    Conversation $conversation,
    User $user,
): ConversationParticipant {
    return ConversationParticipant::factory()
        ->for($conversation)
        ->for($user)
        ->create();
}

test('a participant can open a conversation and sees the other participant', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $otherUser = User::factory()->create();
    Profile::factory()->for($otherUser)->create([
        'display_name' => 'Other Participant',
        'username' => 'other_participant',
    ]);
    $conversation = Conversation::factory()->create();
    participateInConversation($conversation, $viewer);
    participateInConversation($conversation, $otherUser);

    $this->actingAs($viewer)
        ->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Messages/Show')
            ->where('conversation.conversation_id', $conversation->id)
            ->where('conversation.other_participant.display_name', 'Other Participant')
            ->where('conversation.other_participant.username', 'other_participant'),
        );
});

test('a non-participant receives forbidden for a conversation', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $conversation = Conversation::factory()->create();
    participateInConversation($conversation, User::factory()->create());
    participateInConversation($conversation, User::factory()->create());

    $this->actingAs($viewer)
        ->get(route('messages.show', $conversation))
        ->assertForbidden();
});

test('conversation messages are loaded oldest first with sender data', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer, [
        'display_name' => 'Viewing Participant',
        'username' => 'viewing_participant',
    ]);
    $otherUser = User::factory()->create();
    Profile::factory()->for($otherUser)->create([
        'display_name' => 'Sending Participant',
        'username' => 'sending_participant',
    ]);
    $conversation = Conversation::factory()->create();
    participateInConversation($conversation, $viewer);
    participateInConversation($conversation, $otherUser);
    $newerMessage = Message::factory()
        ->for($conversation)
        ->for($viewer, 'sender')
        ->create([
            'body' => 'Neuere Nachricht',
            'created_at' => now(),
        ]);
    $olderMessage = Message::factory()
        ->for($conversation)
        ->for($otherUser, 'sender')
        ->create([
            'body' => 'Ältere Nachricht',
            'created_at' => now()->subMinute(),
        ]);

    $this->actingAs($viewer)
        ->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversation.messages', 2)
            ->where('conversation.messages.0.id', $olderMessage->id)
            ->where('conversation.messages.0.body', 'Ältere Nachricht')
            ->where('conversation.messages.0.sender.display_name', 'Sending Participant')
            ->where('conversation.messages.0.sender.username', 'sending_participant')
            ->where('conversation.messages.1.id', $newerMessage->id)
            ->where('conversation.messages.1.body', 'Neuere Nachricht')
            ->where('conversation.messages.1.sender.display_name', 'Viewing Participant'),
        );
});

test('a conversation without messages returns an empty message collection', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $conversation = Conversation::factory()->create();
    participateInConversation($conversation, $viewer);
    participateInConversation($conversation, User::factory()->create());

    $this->actingAs($viewer)
        ->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Messages/Show')
            ->has('conversation.messages', 0),
        );
});

test('the conversation detail route uses the required middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('messages.show')
        ->gatherMiddleware();

    expect($middleware)->toContain(
        'web',
        'auth',
        'age.gate',
        'verified',
        'onboarding.complete',
    );
});
