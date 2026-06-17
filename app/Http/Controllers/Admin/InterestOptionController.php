<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInterestOptionRequest;
use App\Http\Requests\Admin\UpdateInterestOptionRequest;
use App\Models\InterestOption;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InterestOptionController extends Controller
{
    /**
     * Display the managed interest option catalog.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Options/Interests', [
            'interests' => InterestOption::query()
                ->select([
                    'id',
                    'slug',
                    'label',
                    'sort_order',
                    'is_active',
                ])
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(),
        ]);
    }

    /**
     * Store a new interest option.
     */
    public function store(StoreInterestOptionRequest $request): RedirectResponse
    {
        InterestOption::query()->create($request->validated());

        return to_route('admin.options.interests')
            ->with('success', 'Das Interesse wurde angelegt.');
    }

    /**
     * Update an existing interest option.
     */
    public function update(
        UpdateInterestOptionRequest $request,
        InterestOption $interestOption,
    ): RedirectResponse {
        $interestOption->update($request->validated());

        return to_route('admin.options.interests')
            ->with('success', 'Das Interesse wurde aktualisiert.');
    }

    /**
     * Toggle the active status of an interest option.
     */
    public function toggleStatus(InterestOption $interestOption): RedirectResponse
    {
        $interestOption->update([
            'is_active' => ! $interestOption->is_active,
        ]);

        return to_route('admin.options.interests')
            ->with(
                'success',
                $interestOption->is_active
                    ? 'Das Interesse wurde aktiviert.'
                    : 'Das Interesse wurde deaktiviert.',
            );
    }
}
