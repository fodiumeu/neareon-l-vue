<?php

use App\Exceptions\ConversationParticipantAccessDenied;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\ConversationReadService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

function createReadStateParticipant(
    Conversation $conversation,
    User $user,
    ?Carbon $lastReadAt = null,
): ConversationParticipant {
    return ConversationParticipant::factory()
        ->for($conversation)
        ->for($user)
        ->create([
            'last_read_at' => $lastReadAt,
        ]);
}

function createReadStateMessage(
    Conversation $conversation,
    User $sender,
    string $createdAt,
): Message {
    return Message::factory()
        ->for($conversation)
        ->for($sender, 'sender')
        ->create([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
}

test('mark as read sets a datetime timestamp for the participant', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $participant = createReadStateParticipant($conversation, $user);
    $now = Carbon::parse('2026-06-18 12:00:00');

    Carbon::setTestNow($now);

    try {
        app(ConversationReadService::class)
            ->markAsRead($conversation, $user);
    } finally {
        Carbon::setTestNow();
    }

    $participant->refresh();

    expect($participant->last_read_at)
        ->toBeInstanceOf(CarbonInterface::class)
        ->and($participant->last_read_at->equalTo($now))->toBeTrue();
});

test('non-participants cannot access conversation read state', function (
    string $operation,
) {
    $conversation = Conversation::factory()->create();
    $outsider = User::factory()->create();
    $service = app(ConversationReadService::class);

    if ($operation === 'mark') {
        $service->markAsRead($conversation, $outsider);

        return;
    }

    $service->countUnreadMessages($conversation, $outsider);
})->throws(ConversationParticipantAccessDenied::class)->with([
    'mark as read' => 'mark',
    'count unread' => 'count',
]);

test('a null last read timestamp counts all foreign messages', function () {
    $viewer = User::factory()->create();
    $sender = User::factory()->create();
    $conversation = Conversation::factory()->create();
    createReadStateParticipant($conversation, $viewer);
    createReadStateMessage($conversation, $sender, '2026-06-18 10:00:00');
    createReadStateMessage($conversation, $sender, '2026-06-18 11:00:00');

    $count = app(ConversationReadService::class)
        ->countUnreadMessages($conversation, $viewer);

    expect($count)->toBe(2);
});

test('a users own messages are not counted as unread', function () {
    $viewer = User::factory()->create();
    $sender = User::factory()->create();
    $conversation = Conversation::factory()->create();
    createReadStateParticipant($conversation, $viewer);
    createReadStateMessage($conversation, $viewer, '2026-06-18 10:00:00');
    createReadStateMessage($conversation, $sender, '2026-06-18 11:00:00');

    $count = app(ConversationReadService::class)
        ->countUnreadMessages($conversation, $viewer);

    expect($count)->toBe(1);
});

test('only foreign messages newer than last read are counted', function () {
    $viewer = User::factory()->create();
    $sender = User::factory()->create();
    $conversation = Conversation::factory()->create();
    createReadStateParticipant(
        $conversation,
        $viewer,
        Carbon::parse('2026-06-18 11:00:00'),
    );
    createReadStateMessage($conversation, $sender, '2026-06-18 10:00:00');
    createReadStateMessage($conversation, $sender, '2026-06-18 11:00:00');
    createReadStateMessage($conversation, $sender, '2026-06-18 12:00:00');
    createReadStateMessage($conversation, $viewer, '2026-06-18 13:00:00');

    $count = app(ConversationReadService::class)
        ->countUnreadMessages($conversation, $viewer);

    expect($count)->toBe(1);
});

test('messages from other conversations are ignored', function () {
    $viewer = User::factory()->create();
    $sender = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $otherConversation = Conversation::factory()->create();
    createReadStateParticipant($conversation, $viewer);
    createReadStateMessage($conversation, $sender, '2026-06-18 10:00:00');
    createReadStateMessage(
        $otherConversation,
        $sender,
        '2026-06-18 11:00:00',
    );

    $count = app(ConversationReadService::class)
        ->countUnreadMessages($conversation, $viewer);

    expect($count)->toBe(1);
});

test('the unread count is zero when no foreign message is unread', function () {
    $viewer = User::factory()->create();
    $sender = User::factory()->create();
    $conversation = Conversation::factory()->create();
    createReadStateParticipant(
        $conversation,
        $viewer,
        Carbon::parse('2026-06-18 12:00:00'),
    );
    createReadStateMessage($conversation, $sender, '2026-06-18 11:00:00');
    createReadStateMessage($conversation, $viewer, '2026-06-18 13:00:00');

    $count = app(ConversationReadService::class)
        ->countUnreadMessages($conversation, $viewer);

    expect($count)->toBe(0);
});

test('unread messages are counted for multiple conversations at once', function () {
    $viewer = User::factory()->create();
    $sender = User::factory()->create();
    $firstConversation = Conversation::factory()->create();
    $secondConversation = Conversation::factory()->create();
    createReadStateParticipant($firstConversation, $viewer);
    createReadStateParticipant(
        $secondConversation,
        $viewer,
        Carbon::parse('2026-06-18 11:00:00'),
    );
    createReadStateMessage(
        $firstConversation,
        $sender,
        '2026-06-18 10:00:00',
    );
    createReadStateMessage(
        $secondConversation,
        $sender,
        '2026-06-18 10:00:00',
    );
    createReadStateMessage(
        $secondConversation,
        $sender,
        '2026-06-18 12:00:00',
    );

    $counts = app(ConversationReadService::class)
        ->countUnreadMessagesFor(
            new Collection([
                $firstConversation,
                $secondConversation,
            ]),
            $viewer,
        );

    expect($counts)->toBe([
        $firstConversation->id => 1,
        $secondConversation->id => 1,
    ]);
});

test('all unread messages for a user are counted across conversations', function () {
    $viewer = User::factory()->create();
    $sender = User::factory()->create();
    $firstConversation = Conversation::factory()->create();
    $secondConversation = Conversation::factory()->create();
    createReadStateParticipant($firstConversation, $viewer);
    createReadStateParticipant(
        $secondConversation,
        $viewer,
        Carbon::parse('2026-06-18 11:00:00'),
    );
    createReadStateMessage(
        $firstConversation,
        $sender,
        '2026-06-18 10:00:00',
    );
    createReadStateMessage(
        $firstConversation,
        $viewer,
        '2026-06-18 11:00:00',
    );
    createReadStateMessage(
        $secondConversation,
        $sender,
        '2026-06-18 10:00:00',
    );
    createReadStateMessage(
        $secondConversation,
        $sender,
        '2026-06-18 12:00:00',
    );
    $unrelatedConversation = Conversation::factory()->create();
    createReadStateMessage(
        $unrelatedConversation,
        $sender,
        '2026-06-18 13:00:00',
    );

    $count = app(ConversationReadService::class)
        ->countUnreadMessagesForUser($viewer);

    expect($count)->toBe(2);
});

test('the global unread count is zero without unread messages', function () {
    $viewer = User::factory()->create();

    expect(
        app(ConversationReadService::class)
            ->countUnreadMessagesForUser($viewer),
    )->toBe(0);
});

test('the read state migration can be rolled back and applied again', function () {
    $migration = require database_path(
        'migrations/2026_06_18_000010_add_last_read_at_to_conversation_participants_table.php',
    );

    $migration->down();

    expect(Schema::hasColumn(
        'conversation_participants',
        'last_read_at',
    ))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn(
        'conversation_participants',
        'last_read_at',
    ))->toBeTrue();
});
