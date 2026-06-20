<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Services\ContactRequestLifecycleService;
use App\Services\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ContactController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly ContactRequestLifecycleService $contactRequests,
    ) {}

    /**
     * Show the authenticated user's mutual follows.
     */
    public function index(Request $request): Response
    {
        $viewer = $request->user();

        $directConversations = $viewer->conversations()
            ->select('conversations.*')
            ->with([
                'participants:id,conversation_id,user_id',
            ])
            ->has('participants', '=', 2)
            ->get()
            ->mapWithKeys(function (Conversation $conversation) use ($viewer): array {
                $otherParticipant = $conversation->participants->first(
                    fn (ConversationParticipant $participant): bool => $participant->user_id !== $viewer->id,
                );

                return $otherParticipant === null
                    ? []
                    : [$otherParticipant->user_id => $conversation];
            });

        $contacts = $viewer->following()
            ->select('users.*')
            ->whereHas(
                'followingRelationships',
                fn ($query) => $query->where('followed_id', $viewer->id),
            )
            ->whereDoesntHave(
                'blockingRelationships',
                fn ($query) => $query->where('blocked_id', $viewer->id),
            )
            ->whereDoesntHave(
                'blockedByRelationships',
                fn ($query) => $query->where('blocker_id', $viewer->id),
            )
            ->with([
                'profile',
                'followingRelationships' => fn ($query) => $query
                    ->where('followed_id', $viewer->id),
            ])
            ->get()
            ->map(function (User $contact) use ($directConversations): array {
                $conversation = $directConversations->get($contact->id);
                $incomingFollow = $contact->followingRelationships->first();
                $connectedAt = collect([
                    $contact->pivot->created_at,
                    $incomingFollow?->created_at,
                ])->filter()->max();

                return [
                    'id' => $contact->id,
                    'display_name' => $contact->profile?->display_name ?? $contact->name,
                    'username' => $contact->profile?->username,
                    'profile_photo_url' => $contact->profile?->profilePhotoUrl(),
                    'status' => 'connected',
                    'connected_at' => $connectedAt?->toIso8601String(),
                    'conversation_id' => $conversation?->id,
                    'last_activity_at' => $conversation?->updated_at->toIso8601String(),
                ];
            })
            ->filter(fn (array $contact): bool => $contact['username'] !== null)
            ->sortBy('display_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
        ]);
    }

    /**
     * Remove one direction of an authenticated user's mutual follow.
     */
    public function destroy(Request $request, User $contact): RedirectResponse
    {
        $viewer = $request->user();

        abort_unless(
            ! $viewer->hasBlockWith($contact)
                && $viewer->isMutualWith($contact),
            HttpResponse::HTTP_FORBIDDEN,
        );

        DB::transaction(function () use ($viewer, $contact): void {
            $viewer->followingRelationships()
                ->where('followed_id', $contact->id)
                ->delete();

            $this->contactRequests->closeAcceptedBetween($viewer, $contact);
        });

        return to_route('contacts.index')
            ->with('success', 'Verbindung wurde entfernt.');
    }

    /**
     * Open or create the direct conversation for a mutual contact.
     */
    public function message(Request $request, User $contact): RedirectResponse
    {
        $viewer = $request->user();

        abort_unless(
            $viewer->isMutualWith($contact),
            HttpResponse::HTTP_FORBIDDEN,
        );

        $conversation = $this->conversations
            ->getOrCreateDirectConversation($viewer, $contact);

        return to_route('messages.show', $conversation);
    }
}
