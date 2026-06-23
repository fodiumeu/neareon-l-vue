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

class FollowingController extends Controller
{
    private const PER_PAGE = 12;

    public function __construct(
        private readonly ProfileVisibilityService $profileVisibility,
    ) {}

    /**
     * Show the profiles followed by the authenticated user.
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

        $following = $viewer->following()
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
            ->orderByPivot('followed_id', 'desc')
            ->paginate(self::PER_PAGE);

        $followedByIds = $viewer->followerRelationships()
            ->whereIn('follower_id', $following->getCollection()->modelKeys())
            ->pluck('follower_id')
            ->flip();

        $following->through(function (User $followed) use (
            $followedByIds,
            $viewer,
        ): array {
            $isFollowedBy = $followedByIds->has($followed->id);
            $followed->profile->setRelation('user', $followed);
            $visibleProfile = $this->profileVisibility->visibleProfileData(
                $followed->profile,
                $viewer,
                isFollowing: true,
                isFollowedBy: $isFollowedBy,
            );

            return [
                'id' => $followed->id,
                'display_name' => $visibleProfile['display_name']
                    ?? "@{$followed->profile->username}",
                'username' => $followed->profile->username,
                'profile_photo_url' => $visibleProfile['profile_photo_url'],
                'followed_at' => $followed->pivot->created_at
                    ->toIso8601String(),
                'is_followed_by' => $isFollowedBy,
                'contact_status' => $visibleProfile['contact_status'],
            ];
        });

        return Inertia::render('Following/Index', [
            'following' => $following,
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
                        ProfileVisibility::Followers->value,
                    ])
                    ->orWhere(function (Builder $query) use ($viewer): void {
                        $query->whereIn('profile_visibility', [
                            ProfileVisibility::Contacts->value,
                            ProfileVisibility::Mutuals->value,
                        ]);
                        $this->whereTargetFollowsViewer($query, $viewer);
                    });
            });
    }

    private function whereTargetFollowsViewer(
        Builder $query,
        User $viewer,
    ): void {
        $query->whereExists(function ($query) use ($viewer): void {
            $query
                ->selectRaw('1')
                ->from('follows')
                ->whereColumn(
                    'follows.follower_id',
                    'profiles.user_id',
                )
                ->where('follows.followed_id', $viewer->id);
        });
    }
}
