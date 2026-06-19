<?php

namespace App\Services;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\User;

class ContactRequestLifecycleService
{
    public function closeAcceptedBetween(User $userA, User $userB): void
    {
        ContactRequest::query()
            ->where('status', ContactRequestStatus::Accepted->value)
            ->where(function ($query) use ($userA, $userB): void {
                $query->where([
                    'sender_id' => $userA->id,
                    'receiver_id' => $userB->id,
                ])->orWhere([
                    'sender_id' => $userB->id,
                    'receiver_id' => $userA->id,
                ]);
            })
            ->update([
                'status' => ContactRequestStatus::Closed->value,
                'responded_at' => now(),
            ]);
    }
}
