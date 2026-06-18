<?php

namespace App\Http\Controllers;

use App\Enums\ContactRequestStatus;
use App\Http\Requests\StoreContactRequestRequest;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ContactRequestController extends Controller
{
    /**
     * Show the authenticated user's pending received contact requests.
     */
    public function index(Request $request): Response
    {
        $contactRequests = $request->user()
            ->receivedContactRequests()
            ->where('status', ContactRequestStatus::Pending->value)
            ->with('sender.profile')
            ->latest()
            ->get()
            ->map(fn (ContactRequest $contactRequest): array => [
                'id' => $contactRequest->id,
                'message' => $contactRequest->message,
                'created_at' => $contactRequest->created_at->toIso8601String(),
                'sender' => [
                    'display_name' => $contactRequest->sender->profile?->display_name
                        ?? $contactRequest->sender->name,
                    'username' => $contactRequest->sender->profile?->username,
                ],
            ]);

        return Inertia::render('ContactRequests/Index', [
            'contactRequests' => $contactRequests,
        ]);
    }

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

    /**
     * Accept a pending received contact request.
     */
    public function accept(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        $this->respondTo($request, $contactRequest, ContactRequestStatus::Accepted);

        return back()->with('success', 'Kontaktanfrage angenommen.');
    }

    /**
     * Decline a pending received contact request.
     */
    public function decline(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        $this->respondTo($request, $contactRequest, ContactRequestStatus::Declined);

        return back()->with('success', 'Kontaktanfrage abgelehnt.');
    }

    private function respondTo(
        Request $request,
        ContactRequest $contactRequest,
        ContactRequestStatus $status,
    ): void {
        DB::transaction(function () use ($request, $contactRequest, $status): void {
            $lockedContactRequest = ContactRequest::query()
                ->lockForUpdate()
                ->findOrFail($contactRequest->id);

            abort_unless(
                $lockedContactRequest->receiver_id === $request->user()->id,
                HttpResponse::HTTP_FORBIDDEN,
            );

            abort_unless(
                $lockedContactRequest->status === ContactRequestStatus::Pending,
                HttpResponse::HTTP_CONFLICT,
            );

            $lockedContactRequest->update([
                'status' => $status,
                'responded_at' => now(),
            ]);
        });
    }

    private function hasPendingRequest(User $sender, User $receiver): bool
    {
        return $sender->sentContactRequests()
            ->where('receiver_id', $receiver->id)
            ->where('status', ContactRequestStatus::Pending->value)
            ->exists();
    }
}
