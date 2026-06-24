<?php

namespace App\Http\Controllers;

use App\Services\NavigationBadgeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function __invoke(
        Request $request,
        NavigationBadgeService $badges,
    ): Response {
        $counts = $badges->countsFor($request->user());

        return Inertia::render('Community/Index', [
            'communityCounts' => [
                'pendingContactRequests' => $counts['pendingContactRequests'],
            ],
        ]);
    }
}
