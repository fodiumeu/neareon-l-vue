<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Reports/Index', [
            'reports' => Report::query()
                ->with([
                    'reporter:id,name,email',
                    'reportedUser:id,name,email',
                ])
                ->latest()
                ->get()
                ->map(fn (Report $report): array => [
                    'id' => $report->id,
                    'created_at' => $report->created_at->toISOString(),
                    'reporter' => $report->reporter->only(['id', 'name', 'email']),
                    'reported_user' => $report->reportedUser->only(['id', 'name', 'email']),
                    'reason' => $report->reason->value,
                    'reason_label' => $report->reason->label(),
                    'description' => $report->description,
                    'status' => $report->status->value,
                ]),
        ]);
    }

    public function toggleStatus(Report $report): RedirectResponse
    {
        $report->update([
            'status' => $report->status === ReportStatus::Open
                ? ReportStatus::Closed
                : ReportStatus::Open,
        ]);

        return to_route('admin.reports')
            ->with(
                'success',
                $report->status === ReportStatus::Closed
                    ? 'Die Meldung wurde geschlossen.'
                    : 'Die Meldung wurde wieder geöffnet.',
            );
    }
}
