<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
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
            ->get()
            ->map(function (Conversation $conversation) use ($viewer): array {
                $otherParticipant = $conversation->participants->first(
                    fn (ConversationParticipant $participant): bool => $participant->user_id !== $viewer->id,
                );
                $otherUser = $otherParticipant?->user;

                return [
                    'conversation_id' => $conversation->id,
                    'participant_count' => $conversation->participants_count,
                    'created_at' => $conversation->created_at->toIso8601String(),
                    'updated_at' => $conversation->updated_at->toIso8601String(),
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
}
