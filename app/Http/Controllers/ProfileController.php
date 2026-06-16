<?php

namespace App\Http\Controllers;

use App\Enums\ProfileVisibility;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the edit form for the authenticated user's NEAREON profile.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $profile = $request->user()->profile;

        if ($profile === null) {
            return to_route('onboarding.create');
        }

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'display_name' => $profile->display_name,
                'bio' => $profile->bio,
                'region' => $profile->region,
                'languages' => implode(', ', $profile->languages ?? []),
                'interests' => implode(', ', $profile->interests ?? []),
                'profile_visibility' => $profile->profile_visibility->value,
                'interests_visibility' => $profile->interests_visibility->value,
                'languages_visibility' => $profile->languages_visibility->value,
                'region_visibility' => $profile->region_visibility->value,
                'social_counts_visibility' => $profile->social_counts_visibility->value,
            ],
            'visibilityOptions' => [
                ['value' => ProfileVisibility::Public->value, 'label' => 'Alle'],
                ['value' => ProfileVisibility::Mutuals->value, 'label' => 'Gegenseitige Kontakte'],
                ['value' => ProfileVisibility::Private->value, 'label' => 'Nur ich'],
            ],
        ]);
    }

    /**
     * Update the authenticated user's NEAREON profile.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $profile = $request->user()->profile;

        if ($profile === null) {
            return to_route('onboarding.create');
        }

        $profile->update($request->validated());

        return to_route('neareon-profile.edit')
            ->with('success', 'Profil wurde gespeichert.');
    }
}
