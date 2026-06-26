<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Profile;
use App\Models\User;
use App\Services\ContactRequestLifecycleService;
use App\Services\InternalNotificationService;
use App\Services\PrivacyService;
use App\Support\NextUserRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FollowController extends Controller
{
    public function __construct(
        private readonly PrivacyService $privacy,
        private readonly ContactRequestLifecycleService $contactRequests,
        private readonly InternalNotificationService $notifications,
    ) {}

    /**
     * Follow the profile owner identified by username.
     */
    public function store(Request $request, string $username): RedirectResponse
    {
        $user = $request->user();

        if (! $user->profile()->exists()) {
            return NextUserRoute::redirect($user);
        }

        $profile = Profile::query()
            ->where('username', $username)
            ->firstOrFail();

        if ($user->is($profile->user)) {
            return to_route('public-profile.show', $profile->username)
                ->with('error', 'Du kannst dir nicht selbst folgen.');
        }

        abort_if($user->hasBlockWith($profile->user), 403);
        abort_unless($this->privacy->canFollow($user, $profile->user), 403);

        $follow = $user->followingRelationships()->firstOrCreate([
            'followed_id' => $profile->user_id,
        ]);

        if ($follow->wasRecentlyCreated) {
            $this->notifications->newFollower($user, $profile->user);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Du folgst diesem Profil jetzt.',
        ]);

        if ($request->string('context')->toString() === 'discover') {
            return back();
        }

        return to_route('public-profile.show', $this->profileRouteParameters($request, $profile));
    }

    /**
     * Unfollow the profile owner identified by username.
     */
    public function destroy(Request $request, string $username): RedirectResponse
    {
        $user = $request->user();

        if (! $user->profile()->exists()) {
            return NextUserRoute::redirect($user);
        }

        $profile = Profile::query()
            ->where('username', $username)
            ->firstOrFail();

        abort_if($user->hasBlockWith($profile->user), 403);

        DB::transaction(function () use ($user, $profile): void {
            $user->followingRelationships()
                ->where('followed_id', $profile->user_id)
                ->delete();

            $this->contactRequests
                ->closeAcceptedBetween($user, $profile->user);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Du folgst diesem Profil nicht mehr.',
        ]);

        if ($request->string('context')->toString() === 'discover') {
            return back();
        }

        return to_route('public-profile.show', $this->profileRouteParameters($request, $profile));
    }

    /**
     * @return array<string, string>
     */
    private function profileRouteParameters(Request $request, Profile $profile): array
    {
        $parameters = ['username' => $profile->username];

        if ($request->string('from')->toString() !== 'group-members') {
            return $parameters;
        }

        $groupSlug = $request->string('group')->toString();

        if ($groupSlug === '') {
            return $parameters;
        }

        $group = Group::query()
            ->where('slug', $groupSlug)
            ->first();

        if ($group === null || ! $this->canViewGroupMembers($group, $request->user())) {
            return $parameters;
        }

        return [
            ...$parameters,
            'from' => 'group-members',
            'group' => $group->slug,
        ];
    }

    private function canViewGroupMembers(Group $group, User $viewer): bool
    {
        if ($group->status !== Group::STATUS_ACTIVE) {
            return false;
        }

        if ($group->owner_id === $viewer->id || $viewer->canAccessAdmin()) {
            return true;
        }

        return $group->activeMembers()
            ->where('user_id', $viewer->id)
            ->exists();
    }
}
