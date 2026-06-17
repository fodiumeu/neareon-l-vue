<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Support\NextUserRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
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

        $user->followingRelationships()->firstOrCreate([
            'followed_id' => $profile->user_id,
        ]);

        return to_route('public-profile.show', $profile->username);
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

        $user->followingRelationships()
            ->where('followed_id', $profile->user_id)
            ->delete();

        return to_route('public-profile.show', $profile->username);
    }
}
