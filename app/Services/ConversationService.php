<?php

namespace App\Services;

use App\Exceptions\ConversationAccessDenied;
use App\Exceptions\SelfConversationNotAllowed;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    /**
     * Get or create the direct conversation between two mutually following users.
     */
    public function getOrCreateDirectConversation(
        User $userA,
        User $userB,
    ): Conversation {
        if ($userA->is($userB)) {
            throw new SelfConversationNotAllowed;
        }

        return DB::transaction(function () use ($userA, $userB): Conversation {
            User::query()
                ->whereKey([$userA->id, $userB->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (! $userA->isMutualWith($userB)) {
                throw new ConversationAccessDenied;
            }

            $conversation = $this->findDirectConversation($userA, $userB);

            if ($conversation !== null) {
                return $conversation;
            }

            $conversation = Conversation::query()->create();
            $joinedAt = now();

            $conversation->participants()->createMany([
                [
                    'user_id' => $userA->id,
                    'joined_at' => $joinedAt,
                ],
                [
                    'user_id' => $userB->id,
                    'joined_at' => $joinedAt,
                ],
            ]);

            return $conversation;
        });
    }

    private function findDirectConversation(User $userA, User $userB): ?Conversation
    {
        return Conversation::query()
            ->whereHas(
                'participants',
                fn ($query) => $query->where('user_id', $userA->id),
            )
            ->whereHas(
                'participants',
                fn ($query) => $query->where('user_id', $userB->id),
            )
            ->has('participants', '=', 2)
            ->first();
    }
}
