<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Http\Requests\StoreReportRequest;
use App\Models\Profile;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function store(StoreReportRequest $request, string $username): RedirectResponse
    {
        $reportedUser = Profile::query()
            ->where('username', $username)
            ->firstOrFail()
            ->user;

        abort_if(
            $request->user()->is($reportedUser),
            Response::HTTP_FORBIDDEN,
        );

        Report::query()->create([
            ...$request->validated(),
            'reporter_user_id' => $request->user()->id,
            'reported_user_id' => $reportedUser->id,
            'status' => ReportStatus::Open,
        ]);

        return back()->with('success', 'Meldung wurde gesendet.');
    }
}
