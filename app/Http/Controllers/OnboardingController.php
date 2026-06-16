<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOnboardingProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Show the onboarding form for users without a profile.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->profile()->exists()) {
            return to_route('dashboard');
        }

        return Inertia::render('Onboarding');
    }

    /**
     * Store the initial profile for the authenticated user.
     */
    public function store(StoreOnboardingProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile()->exists()) {
            return to_route('dashboard');
        }

        $user->profile()->create($request->validated());

        return to_route('dashboard');
    }
}
