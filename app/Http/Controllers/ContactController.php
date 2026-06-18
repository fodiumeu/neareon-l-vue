<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Show the authenticated user's mutual follows.
     */
    public function index(Request $request): Response
    {
        $viewer = $request->user();

        $contacts = $viewer->following()
            ->select('users.*')
            ->whereHas(
                'followingRelationships',
                fn ($query) => $query->where('followed_id', $viewer->id),
            )
            ->with('profile')
            ->get()
            ->map(fn (User $contact): array => [
                'display_name' => $contact->profile?->display_name ?? $contact->name,
                'username' => $contact->profile?->username,
            ])
            ->filter(fn (array $contact): bool => $contact['username'] !== null)
            ->sortBy('display_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
        ]);
    }
}
