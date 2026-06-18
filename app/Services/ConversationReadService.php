<?php

namespace App\Services;

use App\Exceptions\ConversationParticipantAccessDenied;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

class ConversationReadService
{
    /**
     * Mark the conversation as read by the given participant.
     */
    public function markAsRead(Conversation $conversation, User $user): void
    {
        $participant = $this->participant($conversation, $user);

        $participant->forceFill([
            'last_read_at' => now(),
        ])->save();
    }

    /**
     * Count messages the given participant has not read yet.
     */
    public function countUnreadMessages(
        Conversation $conversation,
        User $user,
    ): int {
        $participant = $this->participant($conversation, $user);

        return $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->when(
                $participant->last_read_at !== null,
                fn ($query) => $query->where(
                    'created_at',
                    '>',
                    $participant->last_read_at,
                ),
            )
            ->count();
    }

    /**
     * Get the user's participant record or reject access.
     */
    private function participant(
        Conversation $conversation,
        User $user,
    ): ConversationParticipant {
        $participant = $conversation->participants()
            ->where('user_id', $user->id)
            ->first();

        if ($participant === null) {
            throw new ConversationParticipantAccessDenied;
        }

        return $participant;
    }
}
