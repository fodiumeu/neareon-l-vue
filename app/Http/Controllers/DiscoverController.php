<?php

namespace App\Http\Controllers;

use App\Enums\ContactRequestStatus;
use App\Models\Follow;
use App\Models\Profile;
use App\Services\DiscoverRankingService;
use App\Services\ProfileVisibilityService;
use App\Support\NextUserRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscoverController extends Controller
{
    public function __construct(
        private readonly ProfileVisibilityService $profileVisibility,
        private readonly DiscoverRankingService $ranking,
    ) {}

    /**
     * Show visible profiles for the authenticated user.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $viewer = $request->user();

        $viewerProfile = $viewer->profile()
            ->with(['languageOptions:id', 'interestOptions:id'])
            ->first();

        if ($viewerProfile === null) {
            return NextUserRoute::redirect($viewer);
        }

        $viewer->setRelation('profile', $viewerProfile);
        $viewer->loadMissing([
            'sentContactRequests' => fn ($query) => $query
                ->where('status', ContactRequestStatus::Pending->value)
                ->select(['id', 'sender_id', 'receiver_id', 'status']),
            'receivedContactRequests' => fn ($query) => $query
                ->where('status', ContactRequestStatus::Pending->value)
                ->select(['id', 'sender_id', 'receiver_id', 'status']),
            'blockingRelationships:id,blocker_id,blocked_id',
            'blockedByRelationships:id,blocker_id,blocked_id',
        ]);

        $followingIds = Follow::query()
            ->where('follower_id', $viewer->id)
            ->pluck('followed_id')
            ->all();
        $followerIds = Follow::query()
            ->where('followed_id', $viewer->id)
            ->pluck('follower_id')
            ->all();
        $followingLookup = array_fill_keys($followingIds, true);
        $followerLookup = array_fill_keys($followerIds, true);
        $viewerLanguageIds = $viewerProfile->languageOptions->modelKeys();
        $viewerInterestIds = $viewerProfile->interestOptions->modelKeys();

        $profiles = Profile::query()
            ->with([
                'user.profile',
                'languageOptions',
                'interestOptions',
            ])
            ->where('user_id', '!=', $viewer->id)
            ->whereDoesntHave(
                'user.blockingRelationships',
                fn ($query) => $query->where('blocked_id', $viewer->id),
            )
            ->whereDoesntHave(
                'user.blockedByRelationships',
                fn ($query) => $query->where('blocker_id', $viewer->id),
            )
            ->get()
            ->map(function (Profile $profile) use (
                $followerLookup,
                $followingLookup,
                $viewer,
                $viewerInterestIds,
                $viewerLanguageIds,
                $viewerProfile,
            ): ?array {
                $isFollowing = isset($followingLookup[$profile->user_id]);
                $isFollowedBy = isset($followerLookup[$profile->user_id]);

                if (! $this->profileVisibility->isDiscoverVisible(
                    $profile,
                    $viewer,
                    $isFollowing,
                    $isFollowedBy,
                )) {
                    return null;
                }

                $data = $this->profileVisibility->visibleProfileData(
                    $profile,
                    $viewer,
                    isFollowing: $isFollowing,
                    isFollowedBy: $isFollowedBy,
                );

                return [
                    'data' => $data,
                    'display_name' => $data['display_name']
                        ?? $profile->username,
                    'score' => $this->ranking->score(
                        $viewerProfile,
                        $profile,
                        $data,
                        $viewerLanguageIds,
                        $viewerInterestIds,
                    ),
                ];
            })
            ->filter()
            ->sort(function (array $left, array $right): int {
                $scoreOrder = $right['score'] <=> $left['score'];

                return $scoreOrder !== 0
                    ? $scoreOrder
                    : strcasecmp(
                        $left['display_name'],
                        $right['display_name'],
                    );
            })
            ->pluck('data')
            ->values();

        return Inertia::render('Discover', [
            'profiles' => $profiles,
        ]);
    }
}
