<?php

namespace App\Http\Controllers;

use App\Enums\ContactRequestStatus;
use App\Models\Profile;
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
    ) {}

    /**
     * Show visible profiles for the authenticated user.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $viewer = $request->user();

        if (! $viewer->profile()->exists()) {
            return NextUserRoute::redirect($viewer);
        }

        $viewer->loadMissing([
            'sentContactRequests' => fn ($query) => $query
                ->where('status', ContactRequestStatus::Pending->value)
                ->select(['id', 'sender_id', 'receiver_id', 'status']),
            'receivedContactRequests' => fn ($query) => $query
                ->where('status', ContactRequestStatus::Pending->value)
                ->select(['id', 'sender_id', 'receiver_id', 'status']),
        ]);

        $profiles = Profile::query()
            ->with(['user', 'languageOptions', 'interestOptions'])
            ->where('user_id', '!=', $viewer->id)
            ->orderBy('display_name')
            ->get()
            ->filter(fn (Profile $profile): bool => $this->profileVisibility->isDiscoverVisible($profile, $viewer))
            ->map(fn (Profile $profile): array => $this->profileVisibility->visibleProfileData($profile, $viewer))
            ->values();

        return Inertia::render('Discover', [
            'profiles' => $profiles,
        ]);
    }
}
