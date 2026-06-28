<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Models\InterestOption;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Show the event creation form.
     */
    public function create(): Response
    {
        return Inertia::render('Events/Create', [
            'categoryOptions' => $this->categoryOptions(),
            'visibilityOptions' => $this->visibilityOptions(),
        ]);
    }

    /**
     * Store a newly created event.
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        $viewer = $request->user();
        $attributes = $request->validated();

        $event = Event::query()->create([
            ...$attributes,
            'owner_id' => $viewer->id,
            'slug' => $this->uniqueSlug($attributes['title']),
            'status' => Event::STATUS_ACTIVE,
        ]);

        return to_route('events.edit', ['event' => $event->slug])
            ->with('success', 'Event wurde erstellt.');
    }

    /**
     * Show the event edit form.
     */
    public function edit(Request $request, Event $event): Response
    {
        abort_unless($this->canManageEvent($event, $request->user()), 403);

        $event->load('category');

        return Inertia::render('Events/Edit', [
            'categoryOptions' => $this->categoryOptions(),
            'event' => $this->eventFormData($event),
            'visibilityOptions' => $this->visibilityOptions(),
        ]);
    }

    /**
     * Update an existing event.
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        abort_unless($this->canManageEvent($event, $request->user()), 403);

        $event->update($request->validated());

        return to_route('events.edit', ['event' => $event->slug])
            ->with('success', 'Event wurde aktualisiert.');
    }

    private function canManageEvent(Event $event, User $viewer): bool
    {
        return $event->owner_id === $viewer->id || $viewer->canAccessAdmin();
    }

    private function uniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'event';
        $slug = $baseSlug;
        $suffix = 2;

        while (Event::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return list<array{id: int, slug: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return InterestOption::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'slug', 'label'])
            ->map(fn (InterestOption $option): array => $this->categoryData($option))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, slug: string, label: string}
     */
    private function categoryData(InterestOption $option): array
    {
        return [
            'id' => $option->id,
            'slug' => $option->slug,
            'label' => $option->label,
        ];
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    private function visibilityOptions(): array
    {
        return [
            [
                'value' => Event::VISIBILITY_PUBLIC,
                'label' => 'Öffentlich',
                'description' => 'Alle Mitglieder können das Event finden und ansehen.',
            ],
            [
                'value' => Event::VISIBILITY_REQUEST,
                'label' => 'Anfrage',
                'description' => 'Mitglieder können das Event finden; Teilnahme erfolgt später per Anfrage.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventFormData(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'starts_at' => $event->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $event->ends_at?->format('Y-m-d\TH:i'),
            'region' => $event->region,
            'postal_code' => $event->postal_code,
            'country_code' => $event->country_code,
            'visibility' => $event->visibility,
            'category_interest_option_id' => $event->category_interest_option_id,
            'category' => $event->category !== null
                ? $this->categoryData($event->category)
                : null,
            'max_attendees' => $event->max_attendees,
            'edit_url' => route('events.edit', ['event' => $event->slug]),
        ];
    }
}
