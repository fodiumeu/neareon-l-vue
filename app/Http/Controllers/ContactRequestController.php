<?php

namespace App\Http\Controllers;

use App\Enums\ContactRequestStatus;
use App\Http\Requests\StoreContactRequestRequest;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ContactRequestController extends Controller
{
    /**
     * Store a new contact request.
     */
    public function store(StoreContactRequestRequest $request): RedirectResponse
    {
        $sender = $request->user();
        $receiver = User::query()->findOrFail($request->integer('receiver_id'));

        if ($sender->is($receiver)) {
            return back()->with('error', 'Du kannst dir nicht selbst eine Kontaktanfrage senden.');
        }

        if ($sender->isMutualWith($receiver)) {
            return back()->with('error', 'Ihr folgt euch bereits gegenseitig.');
        }

        if ($sender->isFollowing($receiver)) {
            return back()->with('error', 'Du folgst diesem Benutzer bereits.');
        }

        if ($this->hasPendingRequest($sender, $receiver)) {
            return back()->with('error', 'Du hast diesem Benutzer bereits eine Kontaktanfrage gesendet.');
        }

        if ($this->hasPendingRequest($receiver, $sender)) {
            return back()->with('error', 'Dieser Benutzer hat dir bereits eine Kontaktanfrage gesendet.');
        }

        ContactRequest::query()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'message' => $request->validated('message'),
            'status' => ContactRequestStatus::Pending,
            'responded_at' => null,
        ]);

        return back()->with('success', 'Kontaktanfrage gesendet.');
    }

    private function hasPendingRequest(User $sender, User $receiver): bool
    {
        return $sender->sentContactRequests()
            ->where('receiver_id', $receiver->id)
            ->where('status', ContactRequestStatus::Pending->value)
            ->exists();
    }
}
