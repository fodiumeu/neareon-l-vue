<?php

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('messaging foundation tables have the expected columns', function () {
    expect(Schema::hasColumns('conversations', [
        'id',
        'created_at',
        'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('conversation_participants', [
            'id',
            'conversation_id',
            'user_id',
            'joined_at',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('messages', [
            'id',
            'conversation_id',
            'sender_id',
            'body',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('conversation participant relationships work', function () {
    $conversation = Conversation::factory()->create();
    $users = User::factory()->count(2)->create();
    $participants = $users->map(
        fn (User $user) => ConversationParticipant::factory()
            ->for($conversation)
            ->for($user)
            ->create(),
    );

    expect($conversation->participants)->toHaveCount(2)
        ->and($conversation->participants->modelKeys())
        ->toEqualCanonicalizing($participants->modelKeys())
        ->and($users[0]->conversationParticipants->first()->is($participants[0]))
        ->toBeTrue()
        ->and($users[0]->conversations->first()->is($conversation))->toBeTrue();
});

test('conversation participant belongs to a user and conversation', function () {
    $participant = ConversationParticipant::factory()->create();

    expect($participant->conversation)->toBeInstanceOf(Conversation::class)
        ->and($participant->user)->toBeInstanceOf(User::class)
        ->and($participant->joined_at)->not->toBeNull();
});

test('message belongs to its sender and conversation', function () {
    $message = Message::factory()->create();

    expect($message->conversation)->toBeInstanceOf(Conversation::class)
        ->and($message->sender)->toBeInstanceOf(User::class)
        ->and($message->conversation->messages->first()->is($message))->toBeTrue()
        ->and($message->sender->sentMessages->first()->is($message))->toBeTrue();
});

test('a user can only participate once in a conversation', function () {
    $conversation = Conversation::factory()->create();
    $user = User::factory()->create();

    ConversationParticipant::factory()
        ->for($conversation)
        ->for($user)
        ->create();
    ConversationParticipant::factory()
        ->for($conversation)
        ->for($user)
        ->create();
})->throws(QueryException::class);

test('messaging factories create valid records', function () {
    $conversation = Conversation::factory()->create();
    $participant = ConversationParticipant::factory()->create();
    $message = Message::factory()->create();

    expect($conversation)->toBeInstanceOf(Conversation::class)
        ->and($participant)->toBeInstanceOf(ConversationParticipant::class)
        ->and($participant->conversation)->toBeInstanceOf(Conversation::class)
        ->and($participant->user)->toBeInstanceOf(User::class)
        ->and($message)->toBeInstanceOf(Message::class)
        ->and($message->conversation)->toBeInstanceOf(Conversation::class)
        ->and($message->sender)->toBeInstanceOf(User::class)
        ->and($message->body)->not->toBeEmpty();
});

test('messaging migrations can be rolled back and applied again', function () {
    $conversationMigration = require database_path(
        'migrations/2026_06_18_000007_create_conversations_table.php',
    );
    $participantMigration = require database_path(
        'migrations/2026_06_18_000008_create_conversation_participants_table.php',
    );
    $messageMigration = require database_path(
        'migrations/2026_06_18_000009_create_messages_table.php',
    );

    $messageMigration->down();
    $participantMigration->down();
    $conversationMigration->down();

    expect(Schema::hasTable('messages'))->toBeFalse()
        ->and(Schema::hasTable('conversation_participants'))->toBeFalse()
        ->and(Schema::hasTable('conversations'))->toBeFalse();

    $conversationMigration->up();
    $participantMigration->up();
    $messageMigration->up();

    expect(Schema::hasTable('conversations'))->toBeTrue()
        ->and(Schema::hasTable('conversation_participants'))->toBeTrue()
        ->and(Schema::hasTable('messages'))->toBeTrue();
});
