<?php

namespace App\Http\Controllers;

use App\Enums\ContactRequestStatus;
use App\Models\Block;
use App\Models\Profile;
use App\Services\ContactRequestLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BlockController extends Controller
{
    public function __construct(
        private readonly ContactRequestLifecycleService $contactRequests,
    ) {}

    public function index(Request $request): Response
    {
        $blockedProfiles = $request->user()
            ->blockingRelationships()
            ->with([
                'blocked:id,name',
                'blocked.profile:user_id,username,display_name,profile_photo_path',
            ])
            ->latest()
            ->get()
            ->map(fn (Block $block): array => [
                'display_name' => $block->blocked->profile?->display_name
                    ?? $block->blocked->name,
                'username' => $block->blocked->profile?->username,
                'profile_photo_url' => $block->blocked->profile?->profilePhotoUrl(),
                'blocked_at' => $block->created_at->toIso8601String(),
            ])
            ->filter(fn (array $profile): bool => $profile['username'] !== null)
            ->values();

        return Inertia::render('BlockedProfiles/Index', [
            'blockedProfiles' => $blockedProfiles,
        ]);
    }

    public function store(Request $request, string $username): RedirectResponse
    {
        $blocker = $request->user();
        $blocked = Profile::query()
            ->where('username', $username)
            ->firstOrFail()
            ->user;

        abort_if($blocker->is($blocked), 422);

        DB::transaction(function () use ($blocker, $blocked): void {
            Block::query()->firstOrCreate([
                'blocker_id' => $blocker->id,
                'blocked_id' => $blocked->id,
            ]);

            $blocker->followingRelationships()
                ->where('followed_id', $blocked->id)
                ->delete();
            $blocked->followingRelationships()
                ->where('followed_id', $blocker->id)
                ->delete();

            $blocker->sentContactRequests()
                ->where('receiver_id', $blocked->id)
                ->where('status', ContactRequestStatus::Pending->value)
                ->delete();
            $blocked->sentContactRequests()
                ->where('receiver_id', $blocker->id)
                ->where('status', ContactRequestStatus::Pending->value)
                ->delete();

            $this->contactRequests
                ->closeAcceptedBetween($blocker, $blocked);
        });

        $blocker->unsetRelation('blockingRelationships');
        $blocker->unsetRelation('blockedByRelationships');

        return back()->with('success', 'Benutzer wurde blockiert.');
    }

    public function destroy(Request $request, string $username): RedirectResponse
    {
        $blocker = $request->user();
        $blocked = Profile::query()
            ->where('username', $username)
            ->firstOrFail()
            ->user;

        $blocker->blockingRelationships()
            ->where('blocked_id', $blocked->id)
            ->delete();

        $blocker->unsetRelation('blockingRelationships');

        return back()->with('success', 'Blockierung wurde aufgehoben.');
    }
}
