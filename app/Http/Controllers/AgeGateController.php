<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgeGateRequest;
use App\Support\NextUserRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgeGateController extends Controller
{
    /**
     * Show the age gate form.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->birthdate !== null && $user->age_gate_passed_at !== null) {
            return NextUserRoute::redirect($user);
        }

        return Inertia::render('AgeGate');
    }

    /**
     * Store the age gate result for the authenticated user.
     */
    public function store(StoreAgeGateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'birthdate' => $request->validated('birthdate'),
            'age_gate_passed_at' => now(),
        ])->save();

        return NextUserRoute::redirect($user);
    }
}
