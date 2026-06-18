<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Services\ConversationReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MessageController extends Controller
{
    public function __construct(
        private readonly ConversationReadService $conversationReads,
    ) {}

    /**
     * Show the authenticated user's conversation list.
     */
    public function index(Request $request): Response
    {
        $viewer = $request->user();

        $conversations = $viewer->conversations()
            ->select('conversations.*')
            ->withCount('participants')
            ->with([
                'participants:id,conversation_id,user_id',
                'participants.user:id,name',
                'participants.user.profile:user_id,display_name,username',
            ])
            ->orderByDesc('conversations.updated_at')
            ->get();
        $unreadCounts = $this->conversationReads
            ->countUnreadMessagesFor($conversations, $viewer);

        $conversations = $conversations
            ->map(function (Conversation $conversation) use ($viewer, $unreadCounts): array {
                $otherParticipant = $conversation->participants->first(
                    fn (ConversationParticipant $participant): bool => $participant->user_id !== $viewer->id,
                );
                $otherUser = $otherParticipant?->user;

                return [
                    'conversation_id' => $conversation->id,
                    'participant_count' => $conversation->participants_count,
                    'created_at' => $conversation->created_at->toIso8601String(),
                    'updated_at' => $conversation->updated_at->toIso8601String(),
                    'unread_count' => $unreadCounts[$conversation->id],
                    'other_participant' => [
                        'display_name' => $otherUser?->profile?->display_name
                            ?? $otherUser?->name,
                        'username' => $otherUser?->profile?->username,
                    ],
                ];
            });

        return Inertia::render('Messages/Index', [
            'conversations' => $conversations,
        ]);
    }

    /**
     * Show a conversation to one of its participants.
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        $viewer = $request->user();

        abort_unless(
            $conversation->participants()
                ->where('user_id', $viewer->id)
                ->exists(),
            HttpResponse::HTTP_FORBIDDEN,
        );

        $this->conversationReads->markAsRead($conversation, $viewer);

        $conversation->load([
            'participants:id,conversation_id,user_id',
            'participants.user:id,name',
            'participants.user.profile:user_id,display_name,username',
            'messages' => fn ($query) => $query
                ->select([
                    'id',
                    'conversation_id',
                    'sender_id',
                    'body',
                    'created_at',
                ])
                ->oldest(),
            'messages.sender:id,name',
            'messages.sender.profile:user_id,display_name,username',
        ]);

        $otherParticipant = $conversation->participants->first(
            fn (ConversationParticipant $participant): bool => $participant->user_id !== $viewer->id,
        );
        $otherUser = $otherParticipant?->user;

        return Inertia::render('Messages/Show', [
            'conversation' => [
                'conversation_id' => $conversation->id,
                'other_participant' => [
                    'display_name' => $otherUser?->profile?->display_name
                        ?? $otherUser?->name,
                    'username' => $otherUser?->profile?->username,
                ],
                'messages' => $conversation->messages
                    ->map(fn ($message): array => [
                        'id' => $message->id,
                        'body' => $message->body,
                        'created_at' => $message->created_at->toIso8601String(),
                        'sender' => [
                            'display_name' => $message->sender->profile?->display_name
                                ?? $message->sender->name,
                            'username' => $message->sender->profile?->username,
                        ],
                    ]),
            ],
        ]);
    }

    /**
     * Store a new message in a conversation.
     */
    public function store(
        StoreMessageRequest $request,
        Conversation $conversation,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $conversation): void {
            $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $request->validated('message'),
            ]);

            $conversation->touch();
        });

        return to_route('messages.show', $conversation);
    }
}
