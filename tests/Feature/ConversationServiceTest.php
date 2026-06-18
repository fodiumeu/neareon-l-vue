<?php

use App\Exceptions\ConversationAccessDenied;
use App\Exceptions\SelfConversationNotAllowed;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Follow;
use App\Models\User;
use App\Services\ConversationService;

function createMutualFollow(User $userA, User $userB): void
{
    Follow::query()->create([
        'follower_id' => $userA->id,
        'followed_id' => $userB->id,
    ]);
    Follow::query()->create([
        'follower_id' => $userB->id,
        'followed_id' => $userA->id,
    ]);
}

test('the service creates a direct conversation with both participants', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    createMutualFollow($userA, $userB);

    $conversation = app(ConversationService::class)
        ->getOrCreateDirectConversation($userA, $userB);

    expect($conversation)->toBeInstanceOf(Conversation::class)
        ->and($conversation->participants)->toHaveCount(2)
        ->and($conversation->participants->pluck('user_id')->all())
        ->toEqualCanonicalizing([$userA->id, $userB->id])
        ->and($conversation->participants->every(
            fn (ConversationParticipant $participant): bool => $participant->joined_at !== null,
        ))->toBeTrue();
});

test('the service reuses an existing direct conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    createMutualFollow($userA, $userB);
    $existingConversation = Conversation::factory()->create();
    ConversationParticipant::factory()
        ->for($existingConversation)
        ->for($userA)
        ->create();
    ConversationParticipant::factory()
        ->for($existingConversation)
        ->for($userB)
        ->create();

    $conversation = app(ConversationService::class)
        ->getOrCreateDirectConversation($userA, $userB);

    expect($conversation->is($existingConversation))->toBeTrue()
        ->and(Conversation::query()->count())->toBe(1)
        ->and(ConversationParticipant::query()->count())->toBe(2);
});

test('reversing user order returns the same direct conversation without duplicates', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    createMutualFollow($userA, $userB);
    $service = app(ConversationService::class);

    $conversationAB = $service->getOrCreateDirectConversation($userA, $userB);
    $conversationBA = $service->getOrCreateDirectConversation($userB, $userA);

    expect($conversationBA->is($conversationAB))->toBeTrue()
        ->and(Conversation::query()->count())->toBe(1)
        ->and(ConversationParticipant::query()->count())->toBe(2);
});

test('a group conversation is not reused as a direct conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();
    createMutualFollow($userA, $userB);
    $groupConversation = Conversation::factory()->create();

    foreach ([$userA, $userB, $userC] as $participant) {
        ConversationParticipant::factory()
            ->for($groupConversation)
            ->for($participant)
            ->create();
    }

    $directConversation = app(ConversationService::class)
        ->getOrCreateDirectConversation($userA, $userB);

    expect($directConversation->is($groupConversation))->toBeFalse()
        ->and($directConversation->participants)->toHaveCount(2)
        ->and(Conversation::query()->count())->toBe(2);
});

test('self conversations are rejected with a domain exception', function () {
    $user = User::factory()->create();

    app(ConversationService::class)
        ->getOrCreateDirectConversation($user, $user);
})->throws(SelfConversationNotAllowed::class);

test('users without a mutual follow cannot access a direct conversation', function (
    string $followDirection,
) {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    if ($followDirection === 'a-to-b') {
        Follow::query()->create([
            'follower_id' => $userA->id,
            'followed_id' => $userB->id,
        ]);
    }

    app(ConversationService::class)
        ->getOrCreateDirectConversation($userA, $userB);
})->throws(ConversationAccessDenied::class)->with([
    'no follow' => 'none',
    'one-way follow' => 'a-to-b',
]);

test('conversation creation is rolled back when participant creation fails', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    createMutualFollow($userA, $userB);
    $participantCreations = 0;

    ConversationParticipant::creating(
        function () use (&$participantCreations): void {
            $participantCreations++;

            if ($participantCreations === 2) {
                throw new LogicException('Simulated participant creation failure.');
            }
        },
    );

    try {
        app(ConversationService::class)
            ->getOrCreateDirectConversation($userA, $userB);
    } catch (LogicException $exception) {
        expect($exception->getMessage())
            ->toBe('Simulated participant creation failure.');
    } finally {
        ConversationParticipant::flushEventListeners();
    }

    expect(Conversation::query()->exists())->toBeFalse()
        ->and(ConversationParticipant::query()->exists())->toBeFalse();
});
