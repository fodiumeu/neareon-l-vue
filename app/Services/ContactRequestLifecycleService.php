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
            ->betweenUsers($userA, $userB)
            ->update([
                'status' => ContactRequestStatus::Closed->value,
                'responded_at' => now(),
            ]);
    }
}
