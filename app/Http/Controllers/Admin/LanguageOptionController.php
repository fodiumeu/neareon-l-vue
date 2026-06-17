<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLanguageOptionRequest;
use App\Http\Requests\Admin\UpdateLanguageOptionRequest;
use App\Models\LanguageOption;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LanguageOptionController extends Controller
{
    /**
     * Display the managed language option catalog.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Options/Languages', [
            'languages' => LanguageOption::query()
                ->select([
                    'id',
                    'code',
                    'label',
                    'native_label',
                    'sort_order',
                    'is_active',
                ])
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(),
        ]);
    }

    /**
     * Store a new language option.
     */
    public function store(StoreLanguageOptionRequest $request): RedirectResponse
    {
        LanguageOption::query()->create($request->validated());

        return to_route('admin.options.languages')
            ->with('success', 'Die Sprache wurde angelegt.');
    }

    /**
     * Update an existing language option.
     */
    public function update(
        UpdateLanguageOptionRequest $request,
        LanguageOption $languageOption,
    ): RedirectResponse {
        $languageOption->update($request->validated());

        return to_route('admin.options.languages')
            ->with('success', 'Die Sprache wurde aktualisiert.');
    }

    /**
     * Toggle the active status of a language option.
     */
    public function toggleStatus(LanguageOption $languageOption): RedirectResponse
    {
        $languageOption->update([
            'is_active' => ! $languageOption->is_active,
        ]);

        return to_route('admin.options.languages')
            ->with(
                'success',
                $languageOption->is_active
                    ? 'Die Sprache wurde aktiviert.'
                    : 'Die Sprache wurde deaktiviert.',
            );
    }
}
