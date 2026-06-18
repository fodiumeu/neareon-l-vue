<?php

namespace App\Services;

use App\Exceptions\ConversationParticipantAccessDenied;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;

class ConversationReadService
{
    /**
     * Mark the conversation as read by the given participant.
     */
    public function markAsRead(Conversation $conversation, User $user): void
    {
        $updated = $conversation->participants()
            ->where('user_id', $user->id)
            ->update([
                'last_read_at' => now(),
            ]);

        if ($updated === 0) {
            throw new ConversationParticipantAccessDenied;
        }
    }

    /**
     * Count messages the given participant has not read yet.
     */
    public function countUnreadMessages(
        Conversation $conversation,
        User $user,
    ): int {
        return $this->countUnreadMessagesFor(
            new Collection([$conversation]),
            $user,
        )[$conversation->id];
    }

    /**
     * Count unread messages for multiple conversations without N+1 queries.
     *
     * @param  Collection<int, Conversation>  $conversations
     * @return array<int, int>
     */
    public function countUnreadMessagesFor(
        Collection $conversations,
        User $user,
    ): array {
        if ($conversations->isEmpty()) {
            return [];
        }

        $conversationIds = $conversations->modelKeys();
        $participantConversationIds = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->whereIn('conversation_id', $conversationIds)
            ->pluck('conversation_id');

        if ($participantConversationIds->count() !== count($conversationIds)) {
            throw new ConversationParticipantAccessDenied;
        }

        $unreadCounts = Message::query()
            ->selectRaw('messages.conversation_id, COUNT(*) as unread_count')
            ->join(
                'conversation_participants as read_participant',
                function (JoinClause $join) use ($user): void {
                    $join->on(
                        'read_participant.conversation_id',
                        '=',
                        'messages.conversation_id',
                    )->where('read_participant.user_id', $user->id);
                },
            )
            ->whereIn('messages.conversation_id', $conversationIds)
            ->where('sender_id', '!=', $user->id)
            ->where(
                fn ($query) => $query
                    ->whereNull('read_participant.last_read_at')
                    ->orWhereColumn(
                        'messages.created_at',
                        '>',
                        'read_participant.last_read_at',
                    ),
            )
            ->groupBy('messages.conversation_id')
            ->pluck('unread_count', 'messages.conversation_id');

        return collect($conversationIds)
            ->mapWithKeys(fn (int $conversationId): array => [
                $conversationId => (int) $unreadCounts->get(
                    $conversationId,
                    0,
                ),
            ])
            ->all();
    }
}
