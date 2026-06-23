<?php

namespace App\Http\Controllers;

use App\Enums\ContactRequestStatus;
use App\Enums\ProfileVisibility;
use App\Models\User;
use App\Services\ProfileVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowerController extends Controller
{
    private const PER_PAGE = 12;

    public function __construct(
        private readonly ProfileVisibilityService $profileVisibility,
    ) {}

    /**
     * Show the authenticated user's followers.
     */
    public function index(Request $request): Response
    {
        $viewer = $request->user();
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

        $followers = $viewer->followers()
            ->select('users.*')
            ->whereDoesntHave(
                'blockingRelationships',
                fn ($query) => $query->where('blocked_id', $viewer->id),
            )
            ->whereDoesntHave(
                'blockedByRelationships',
                fn ($query) => $query->where('blocker_id', $viewer->id),
            )
            ->whereHas(
                'profile',
                fn (Builder $query) => $this
                    ->whereProfileVisible($query, $viewer),
            )
            ->with('profile')
            ->orderByPivot('created_at', 'desc')
            ->orderByPivot('id', 'desc')
            ->paginate(self::PER_PAGE);

        $followingIds = $viewer->followingRelationships()
            ->whereIn('followed_id', $followers->getCollection()->modelKeys())
            ->pluck('followed_id')
            ->flip();

        $followers->through(function (User $follower) use (
            $followingIds,
            $viewer,
        ): array {
            $isFollowing = $followingIds->has($follower->id);
            $follower->profile->setRelation('user', $follower);
            $visibleProfile = $this->profileVisibility->visibleProfileData(
                $follower->profile,
                $viewer,
                isFollowing: $isFollowing,
                isFollowedBy: true,
            );

            return [
                'id' => $follower->id,
                'display_name' => $visibleProfile['display_name']
                    ?? "@{$follower->profile->username}",
                'username' => $follower->profile->username,
                'profile_photo_url' => $visibleProfile['profile_photo_url'],
                'followed_at' => $follower->pivot->created_at
                    ->toIso8601String(),
                'is_following' => $isFollowing,
                'contact_status' => $visibleProfile['contact_status'],
            ];
        });

        return Inertia::render('Followers/Index', [
            'followers' => $followers,
        ]);
    }

    private function whereProfileVisible(
        Builder $query,
        User $viewer,
    ): void {
        $query
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->where(function (Builder $query) use ($viewer): void {
                $query
                    ->whereIn('profile_visibility', [
                        ProfileVisibility::Public->value,
                        ProfileVisibility::Members->value,
                    ])
                    ->orWhere(function (Builder $query) use ($viewer): void {
                        $query->whereIn('profile_visibility', [
                            ProfileVisibility::Followers->value,
                            ProfileVisibility::Contacts->value,
                            ProfileVisibility::Mutuals->value,
                        ]);
                        $this->whereViewerFollows($query, $viewer);
                    });
            });
    }

    private function whereViewerFollows(
        Builder $query,
        User $viewer,
    ): void {
        $query->whereExists(function ($query) use ($viewer): void {
            $query
                ->selectRaw('1')
                ->from('follows')
                ->where('follows.follower_id', $viewer->id)
                ->whereColumn(
                    'follows.followed_id',
                    'profiles.user_id',
                );
        });
    }
}
