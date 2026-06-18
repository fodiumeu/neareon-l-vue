<?php

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function addMessageStoreParticipant(
    Conversation $conversation,
    User $user,
): ConversationParticipant {
    return ConversationParticipant::factory()
        ->for($conversation)
        ->for($user)
        ->create();
}

test('a participant can send a message', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $conversation = Conversation::factory()->create();
    addMessageStoreParticipant($conversation, $sender);
    addMessageStoreParticipant($conversation, User::factory()->create());

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Hallo zusammen',
        ])
        ->assertRedirect(route('messages.show', $conversation));

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'body' => 'Hallo zusammen',
    ]);
});

test('a non-participant cannot send a message', function () {
    $outsider = User::factory()->create();
    createOnboardedProfile($outsider);
    $conversation = Conversation::factory()->create();
    addMessageStoreParticipant($conversation, User::factory()->create());
    addMessageStoreParticipant($conversation, User::factory()->create());

    $this->actingAs($outsider)
        ->post(route('messages.store', $conversation), [
            'message' => 'Nicht erlaubt',
        ])
        ->assertForbidden();

    expect(Message::query()->count())->toBe(0);
});

test('message input is trimmed before it is stored', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $conversation = Conversation::factory()->create();
    addMessageStoreParticipant($conversation, $sender);

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => '  Sauber getrimmt  ',
        ])
        ->assertRedirect(route('messages.show', $conversation));

    expect($conversation->messages()->sole()->body)->toBe('Sauber getrimmt');
});

test('an empty or whitespace-only message is invalid', function (string $message) {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $conversation = Conversation::factory()->create();
    addMessageStoreParticipant($conversation, $sender);

    $this->actingAs($sender)
        ->from(route('messages.show', $conversation))
        ->post(route('messages.store', $conversation), [
            'message' => $message,
        ])
        ->assertRedirect(route('messages.show', $conversation))
        ->assertSessionHasErrors('message');

    expect(Message::query()->count())->toBe(0);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
]);

test('a message may not exceed 5000 characters', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $conversation = Conversation::factory()->create();
    addMessageStoreParticipant($conversation, $sender);

    $this->actingAs($sender)
        ->from(route('messages.show', $conversation))
        ->post(route('messages.store', $conversation), [
            'message' => str_repeat('a', 5001),
        ])
        ->assertRedirect(route('messages.show', $conversation))
        ->assertSessionHasErrors('message');

    expect(Message::query()->count())->toBe(0);
});

test('sending a message updates the conversation timestamp', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $conversation = Conversation::factory()->create();
    addMessageStoreParticipant($conversation, $sender);
    $conversation->forceFill([
        'updated_at' => now()->subHour(),
    ])->saveQuietly();
    $previousUpdatedAt = $conversation->updated_at;

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Neue Aktivität',
        ])
        ->assertRedirect(route('messages.show', $conversation));

    expect($conversation->fresh()->updated_at->greaterThan($previousUpdatedAt))
        ->toBeTrue();
});

test('new messages remain sorted oldest first in the detail view', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $otherUser = User::factory()->create();
    $conversation = Conversation::factory()->create();
    addMessageStoreParticipant($conversation, $sender);
    addMessageStoreParticipant($conversation, $otherUser);
    $olderMessage = Message::factory()
        ->for($conversation)
        ->for($otherUser, 'sender')
        ->create([
            'body' => 'Zuerst',
            'created_at' => now()->subMinute(),
        ]);

    $this->actingAs($sender)
        ->post(route('messages.store', $conversation), [
            'message' => 'Danach',
        ])
        ->assertRedirect(route('messages.show', $conversation));

    $newerMessage = $conversation->messages()
        ->where('body', 'Danach')
        ->sole();

    $this->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation.messages.0.id', $olderMessage->id)
            ->where('conversation.messages.1.id', $newerMessage->id),
        );
});

test('the message store route uses the required middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('messages.store')
        ->gatherMiddleware();

    expect($middleware)->toContain(
        'web',
        'auth',
        'age.gate',
        'verified',
        'onboarding.complete',
    );
});
