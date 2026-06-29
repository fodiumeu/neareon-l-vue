<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\InterestOption;
use App\Models\User;
use App\Services\InternalNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const PER_PAGE = 12;

    private const ATTENDEES_PREVIEW_LIMIT = 6;

    public function __construct(
        private readonly InternalNotificationService $notifications,
    ) {}

    /**
     * Show discoverable events.
     */
    public function index(Request $request): Response
    {
        $filters = $this->eventDiscoverFilters($request);

        $events = $this->applyEventDiscoverFilters(
            $this->discoverEventsQuery(),
            $filters,
        )
            ->with(['category', 'owner.profile'])
            ->with([
                'attendees' => fn ($query) => $query
                    ->where('user_id', $request->user()->id)
                    ->select(['id', 'event_id', 'user_id', 'status', 'joined_at']),
            ])
            ->withCount('activeAttendees')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Event $event): array => $this->eventSummaryData($event));

        return Inertia::render('Events/Index', [
            'backLink' => $this->discoverBackLink($request),
            'events' => $events,
            'filters' => $filters,
            'filterOptions' => [
                'regions' => $this->eventDiscoverRegionOptions(),
                'categories' => $this->eventDiscoverCategoryOptions(),
                'visibilities' => $this->eventDiscoverVisibilityOptions(),
            ],
        ]);
    }

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
     * Show the current user's event overview.
     */
    public function mine(Request $request): Response
    {
        $viewer = $request->user();

        $ownedEvents = Event::query()
            ->where('owner_id', $viewer->id)
            ->with('category')
            ->withCount('activeAttendees')
            ->orderByRaw(
                'case when starts_at >= ? then 0 else 1 end',
                [now()],
            )
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Event $event): array => $this->myEventData($event, 'owner'))
            ->values()
            ->all();

        $attendingEvents = Event::query()
            ->where('status', Event::STATUS_ACTIVE)
            ->whereHas('attendees', fn (Builder $query) => $query
                ->where('user_id', $viewer->id)
                ->where('status', EventAttendee::STATUS_ACTIVE))
            ->with('category')
            ->withCount('activeAttendees')
            ->orderByRaw(
                'case when starts_at >= ? then 0 else 1 end',
                [now()],
            )
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Event $event): array => $this->myEventData($event, 'active'))
            ->values()
            ->all();

        $pendingEvents = Event::query()
            ->where('status', Event::STATUS_ACTIVE)
            ->whereHas('attendees', fn (Builder $query) => $query
                ->where('user_id', $viewer->id)
                ->where('status', EventAttendee::STATUS_PENDING))
            ->with('category')
            ->withCount('activeAttendees')
            ->orderByRaw(
                'case when starts_at >= ? then 0 else 1 end',
                [now()],
            )
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Event $event): array => $this->myEventData($event, 'pending'))
            ->values()
            ->all();

        return Inertia::render('Events/MyEvents', [
            'backLink' => $this->communityBackLink($request),
            'owned_events' => $ownedEvents,
            'attending_events' => $attendingEvents,
            'pending_events' => $pendingEvents,
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

        return to_route('events.show', ['event' => $event->slug])
            ->with('success', 'Event wurde erstellt.');
    }

    /**
     * Show a visible event.
     */
    public function show(Request $request, Event $event): Response
    {
        $viewer = $request->user();

        abort_unless($this->canViewEvent($event, $viewer), 404);

        $event->load(['category', 'owner.profile'])
            ->load([
                'attendees' => fn ($query) => $query
                    ->where('user_id', $viewer->id)
                    ->select(['id', 'event_id', 'user_id', 'status', 'joined_at']),
            ])
            ->loadCount('activeAttendees');

        $canEdit = $this->canManageEvent($event, $viewer);
        $canManageAttendanceRequests = $this->canManageAttendanceRequests($event, $viewer)
            && $event->status === Event::STATUS_ACTIVE;
        $backLink = $this->eventBackLink($request);

        return Inertia::render('Events/Show', [
            'event' => array_merge($this->eventDetailData($event, $canEdit), [
                'back_url' => $backLink['url'],
                'back_label' => $backLink['label'],
                'attendees_preview' => $this->attendeesPreview($event),
                'pending_requests' => $canManageAttendanceRequests
                    ? $this->pendingAttendanceRequests($event)
                    : [],
                'can_manage_attendance_requests' => $canManageAttendanceRequests,
            ]),
        ]);
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

        return to_route('events.show', ['event' => $event->slug])
            ->with('success', 'Event wurde aktualisiert.');
    }

    /**
     * Mark an event as cancelled without deleting attendees or requests.
     */
    public function cancel(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->canManageEvent($event, $request->user()), 403);

        if ($event->status === Event::STATUS_CANCELLED) {
            return to_route('events.show', ['event' => $event->slug])
                ->with('success', 'Event ist bereits abgesagt.');
        }

        $event->forceFill([
            'status' => Event::STATUS_CANCELLED,
        ])->save();

        return to_route('events.show', ['event' => $event->slug])
            ->with('success', 'Event wurde abgesagt.');
    }

    /**
     * Mark a cancelled event as active again without changing attendees.
     */
    public function restore(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->canManageEvent($event, $request->user()), 403);

        if ($event->status === Event::STATUS_ACTIVE) {
            return to_route('events.show', ['event' => $event->slug])
                ->with('success', 'Event ist bereits aktiv.');
        }

        $event->forceFill([
            'status' => Event::STATUS_ACTIVE,
        ])->save();

        return to_route('events.show', ['event' => $event->slug])
            ->with('success', 'Event wurde wieder aktiviert.');
    }

    /**
     * Join a public event or request participation for a request-based event.
     */
    public function storeAttendance(Request $request, Event $event): RedirectResponse
    {
        $viewer = $request->user();

        abort_unless($this->canUseAttendance($event), 404);
        abort_if($event->owner_id === $viewer->id, 403);

        $attendance = EventAttendee::query()
            ->where('event_id', $event->id)
            ->where('user_id', $viewer->id)
            ->first();

        if ($attendance instanceof EventAttendee) {
            if ($attendance->status === EventAttendee::STATUS_ACTIVE) {
                return to_route('events.show', ['event' => $event->slug])
                    ->with('success', 'Du nimmst bereits am Event teil.');
            }

            if ($attendance->status === EventAttendee::STATUS_PENDING
                && $event->visibility === Event::VISIBILITY_REQUEST) {
                return to_route('events.show', ['event' => $event->slug])
                    ->with('success', 'Deine Teilnahme-Anfrage wartet bereits auf Bestätigung.');
            }
        }

        if ($this->eventIsFull($event)) {
            return to_route('events.show', ['event' => $event->slug])
                ->withErrors(['attendance' => 'Dieses Event ist bereits ausgebucht.']);
        }

        if ($event->visibility === Event::VISIBILITY_PUBLIC) {
            $attendance = EventAttendee::query()->updateOrCreate(
                [
                    'event_id' => $event->id,
                    'user_id' => $viewer->id,
                ],
                [
                    'status' => EventAttendee::STATUS_ACTIVE,
                    'joined_at' => now(),
                ],
            );

            if ($attendance->wasRecentlyCreated) {
                $this->notifications->eventAttendeeJoined($viewer, $event);
            }

            return to_route('events.show', ['event' => $event->slug])
                ->with('success', 'Du nimmst am Event teil.');
        }

        $attendance = EventAttendee::query()->firstOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $viewer->id,
            ],
            [
                'status' => EventAttendee::STATUS_PENDING,
                'joined_at' => null,
            ],
        );

        if ($attendance->wasRecentlyCreated) {
            $this->notifications->eventAttendanceRequestReceived(
                $viewer,
                $event,
            );
        }

        return to_route('events.show', ['event' => $event->slug])
            ->with('success', 'Deine Teilnahme-Anfrage wurde gesendet.');
    }

    /**
     * Leave an event or withdraw the current user's pending participation request.
     */
    public function destroyAttendance(Request $request, Event $event): RedirectResponse
    {
        $viewer = $request->user();

        $attendance = EventAttendee::query()
            ->where('event_id', $event->id)
            ->where('user_id', $viewer->id)
            ->first();

        abort_unless($attendance instanceof EventAttendee, 404);
        abort_unless(in_array($attendance->status, [
            EventAttendee::STATUS_ACTIVE,
            EventAttendee::STATUS_PENDING,
        ], true), 404);

        $wasPending = $attendance->status === EventAttendee::STATUS_PENDING;

        $attendance->delete();

        return to_route('events.show', ['event' => $event->slug])
            ->with('success', $wasPending
                ? 'Deine Teilnahme-Anfrage wurde zurückgezogen.'
                : 'Du nimmst nicht mehr am Event teil.');
    }

    /**
     * Accept a pending attendance request.
     */
    public function acceptAttendanceRequest(Request $request, Event $event, EventAttendee $attendee): RedirectResponse
    {
        $viewer = $request->user();

        abort_unless($this->canManageAttendanceRequests($event, $viewer), 403);
        $this->ensureProcessableAttendanceRequest($event, $attendee);

        if ($this->eventIsFull($event)) {
            return to_route('events.show', ['event' => $event->slug])
                ->withErrors(['attendance' => 'Dieses Event ist bereits ausgebucht.']);
        }

        $attendee->forceFill([
            'status' => EventAttendee::STATUS_ACTIVE,
            'joined_at' => now(),
        ])->save();

        $attendee->loadMissing('user');
        $this->notifications->eventAttendanceRequestAccepted(
            $viewer,
            $attendee->user,
            $event,
        );

        return to_route('events.show', ['event' => $event->slug])
            ->with('success', 'Teilnahme-Anfrage angenommen.');
    }

    /**
     * Decline a pending attendance request.
     */
    public function declineAttendanceRequest(Request $request, Event $event, EventAttendee $attendee): RedirectResponse
    {
        $viewer = $request->user();

        abort_unless($this->canManageAttendanceRequests($event, $viewer), 403);
        $this->ensureProcessableAttendanceRequest($event, $attendee);

        $attendee->loadMissing('user');
        $this->notifications->eventAttendanceRequestDeclined(
            $viewer,
            $attendee->user,
            $event,
        );

        $attendee->delete();

        return to_route('events.show', ['event' => $event->slug])
            ->with('success', 'Teilnahme-Anfrage abgelehnt.');
    }

    private function canManageEvent(Event $event, User $viewer): bool
    {
        return $event->owner_id === $viewer->id || $viewer->canAccessAdmin();
    }

    private function canManageAttendanceRequests(Event $event, User $viewer): bool
    {
        return $this->canManageEvent($event, $viewer);
    }

    private function canViewEvent(Event $event, User $viewer): bool
    {
        if ($this->canManageEvent($event, $viewer)) {
            return true;
        }

        return $event->status === Event::STATUS_ACTIVE
            && in_array($event->visibility, [
                Event::VISIBILITY_PUBLIC,
                Event::VISIBILITY_REQUEST,
            ], true);
    }

    private function canUseAttendance(Event $event): bool
    {
        return $event->status === Event::STATUS_ACTIVE
            && in_array($event->visibility, [
                Event::VISIBILITY_PUBLIC,
                Event::VISIBILITY_REQUEST,
            ], true);
    }

    private function eventIsFull(Event $event): bool
    {
        if ($event->max_attendees === null) {
            return false;
        }

        $activeAttendeesCount = $event->active_attendees_count
            ?? $event->activeAttendees()->count();

        return $activeAttendeesCount >= $event->max_attendees;
    }

    private function ensureProcessableAttendanceRequest(Event $event, EventAttendee $attendee): void
    {
        abort_unless($attendee->event_id === $event->id, 404);
        abort_unless($event->status === Event::STATUS_ACTIVE, 404);
        abort_unless($event->visibility === Event::VISIBILITY_REQUEST, 404);
        abort_unless($attendee->status === EventAttendee::STATUS_PENDING, 404);
        abort_if($attendee->user_id === $event->owner_id, 404);
    }

    /**
     * @return array{url: string, label: string}
     */
    private function eventBackLink(Request $request): array
    {
        return match ($request->query('from')) {
            'home' => [
                'url' => route('dashboard'),
                'label' => 'Zurück zu Home',
            ],
            'my-events' => [
                'url' => route('events.mine'),
                'label' => 'Zurück zu Meine Events',
            ],
            default => [
                'url' => route('events.index'),
                'label' => 'Zurück zu Events',
            ],
        };
    }

    /**
     * @return array{href: string, label: string, source: string|null}
     */
    private function discoverBackLink(Request $request): array
    {
        return match ($request->string('from')->toString()) {
            'home' => [
                'href' => route('dashboard', absolute: false),
                'label' => 'Zurück zu Home',
                'source' => 'home',
            ],
            'explore' => [
                'href' => route('explore.index', absolute: false),
                'label' => 'Zurück zu Entdecken',
                'source' => 'explore',
            ],
            default => [
                'href' => route('explore.index', absolute: false),
                'label' => 'Zurück zu Entdecken',
                'source' => null,
            ],
        };
    }

    /**
     * @return array{href: string, label: string, source: string|null}
     */
    private function communityBackLink(Request $request): array
    {
        return $request->string('from')->toString() === 'home'
            ? [
                'href' => route('dashboard', absolute: false),
                'label' => 'Zurück zu Home',
                'source' => 'home',
            ]
            : [
                'href' => route('community.index', absolute: false),
                'label' => 'Zurück zur Community',
                'source' => null,
            ];
    }

    /**
     * @return Builder<Event>
     */
    private function discoverEventsQuery(): Builder
    {
        return Event::query()->visibleForDiscover();
    }

    /**
     * @return array{q: string, region: string, category: string, visibility: string}
     */
    private function eventDiscoverFilters(Request $request): array
    {
        $visibility = trim($request->string('visibility')->toString());

        if (! in_array($visibility, [
            Event::VISIBILITY_PUBLIC,
            Event::VISIBILITY_REQUEST,
        ], true)) {
            $visibility = '';
        }

        return [
            'q' => trim($request->string('q')->toString()),
            'region' => trim($request->string('region')->toString()),
            'category' => trim($request->string('category')->toString()),
            'visibility' => $visibility,
        ];
    }

    /**
     * @param  Builder<Event>  $query
     * @param  array{q: string, region: string, category: string, visibility: string}  $filters
     * @return Builder<Event>
     */
    private function applyEventDiscoverFilters(Builder $query, array $filters): Builder
    {
        if ($filters['q'] !== '') {
            $like = $this->databaseLikeTerm($filters['q']);

            $query->where(function (Builder $searchQuery) use ($like): void {
                $searchQuery
                    ->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('region', 'like', $like)
                    ->orWhere('postal_code', 'like', $like)
                    ->orWhereHas('category', function (Builder $categoryQuery) use ($like): void {
                        $categoryQuery
                            ->where('label', 'like', $like)
                            ->orWhere('slug', 'like', $like);
                    });
            });
        }

        if ($filters['region'] !== '') {
            $query->where('region', $filters['region']);
        }

        if ($filters['category'] !== '') {
            $query->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery
                ->where('is_active', true)
                ->where('slug', $filters['category']));
        }

        if ($filters['visibility'] !== '') {
            $query->where('visibility', $filters['visibility']);
        }

        return $query;
    }

    private function databaseLikeTerm(string $value): string
    {
        return '%'.addcslashes($value, '\\%_').'%';
    }

    /**
     * @return list<string>
     */
    private function eventDiscoverRegionOptions(): array
    {
        return $this->discoverEventsQuery()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, slug: string, label: string}>
     */
    private function eventDiscoverCategoryOptions(): array
    {
        return InterestOption::query()
            ->where('is_active', true)
            ->whereHas('events', fn (Builder $eventQuery) => $eventQuery
                ->visibleForDiscover())
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'slug', 'label'])
            ->map(fn (InterestOption $option): array => $this->categoryData($option))
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function eventDiscoverVisibilityOptions(): array
    {
        return [
            [
                'value' => Event::VISIBILITY_PUBLIC,
                'label' => 'Öffentlich',
            ],
            [
                'value' => Event::VISIBILITY_REQUEST,
                'label' => 'Anfrage',
            ],
        ];
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
            'show_url' => route('events.show', ['event' => $event->slug]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventSummaryData(Event $event): array
    {
        $description = $event->description;
        $attendanceState = $this->eventAttendanceState($event);

        return array_merge([
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $description !== null && mb_strlen($description) > 180
                ? Str::limit($description, 180)
                : $description,
            'show_url' => route('events.show', ['event' => $event->slug]),
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'region' => $event->region,
            'postal_code' => $event->postal_code,
            'country_code' => $event->country_code,
            'visibility' => $event->visibility,
            'visibility_label' => $this->visibilityLabel($event->visibility),
            'status' => $event->status,
            'category' => $event->category !== null
                ? $this->categoryData($event->category)
                : null,
            'attendee_count' => $event->active_attendees_count ?? 0,
            'max_attendees' => $event->max_attendees,
            'owner' => [
                'name' => $event->owner->profile?->display_name
                    ?? $event->owner->name,
                'username' => $event->owner->profile?->username,
            ],
        ], $attendanceState);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventDetailData(Event $event, bool $canEdit): array
    {
        $attendanceState = $this->eventAttendanceState($event);

        return array_merge([
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'region' => $event->region,
            'postal_code' => $event->postal_code,
            'country_code' => $event->country_code,
            'visibility' => $event->visibility,
            'visibility_label' => $this->visibilityLabel($event->visibility),
            'status' => $event->status,
            'status_label' => $this->statusLabel($event->status),
            'category' => $event->category !== null
                ? $this->categoryData($event->category)
                : null,
            'max_attendees' => $event->max_attendees,
            'owner' => [
                ...$this->eventUserData($event->owner),
            ],
            'attendee_count' => $event->active_attendees_count ?? 0,
            'can_edit' => $canEdit,
            'edit_url' => $canEdit
                ? route('events.edit', ['event' => $event->slug])
                : null,
            'can_cancel' => $canEdit && $event->status === Event::STATUS_ACTIVE,
            'cancel_url' => $canEdit && $event->status === Event::STATUS_ACTIVE
                ? route('events.cancel', ['event' => $event->slug])
                : null,
            'can_restore' => $canEdit && $event->status === Event::STATUS_CANCELLED,
            'restore_url' => $canEdit && $event->status === Event::STATUS_CANCELLED
                ? route('events.restore', ['event' => $event->slug])
                : null,
        ], $attendanceState);
    }

    /**
     * @return array<string, mixed>
     */
    private function myEventData(Event $event, string $viewerState): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'show_url' => route('events.show', ['event' => $event->slug]),
            'my_events_show_url' => route('events.show', [
                'event' => $event->slug,
                'from' => 'my-events',
            ]),
            'edit_url' => $viewerState === 'owner'
                ? route('events.edit', ['event' => $event->slug])
                : null,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'region' => $event->region,
            'postal_code' => $event->postal_code,
            'country_code' => $event->country_code,
            'category' => $event->category !== null
                ? $this->categoryData($event->category)
                : null,
            'visibility' => $event->visibility,
            'visibility_label' => $this->visibilityLabel($event->visibility),
            'status' => $event->status,
            'status_label' => $this->statusLabel($event->status),
            'active_attendees_count' => $event->active_attendees_count ?? 0,
            'max_attendees' => $event->max_attendees,
            'viewer_state' => $viewerState,
        ];
    }

    /**
     * @return array{
     *     is_full: bool,
     *     viewer_attendance_status: string|null,
     *     viewer_event_role: string,
     *     can_join: bool,
     *     can_request: bool,
     *     can_leave: bool,
     *     attendance_store_url: string|null,
     *     attendance_destroy_url: string|null
     * }
     */
    private function eventAttendanceState(Event $event): array
    {
        $attendance = $event->attendees->first();
        $attendanceStatus = $attendance instanceof EventAttendee
            ? $attendance->status
            : null;
        $isOwner = auth()->id() !== null && $event->owner_id === auth()->id();
        $isFull = $this->eventIsFull($event);
        $canUseAttendance = $this->canUseAttendance($event);
        $canJoin = $canUseAttendance
            && ! $isOwner
            && $attendanceStatus === null
            && ! $isFull
            && $event->visibility === Event::VISIBILITY_PUBLIC;
        $canRequest = $canUseAttendance
            && ! $isOwner
            && $attendanceStatus === null
            && ! $isFull
            && $event->visibility === Event::VISIBILITY_REQUEST;
        $canLeave = $canUseAttendance && in_array($attendanceStatus, [
            EventAttendee::STATUS_ACTIVE,
            EventAttendee::STATUS_PENDING,
        ], true);

        return [
            'is_full' => $isFull,
            'viewer_attendance_status' => $attendanceStatus,
            'viewer_event_role' => $isOwner
                ? 'owner'
                : match ($attendanceStatus) {
                    EventAttendee::STATUS_ACTIVE => 'attendee',
                    EventAttendee::STATUS_PENDING => 'pending',
                    default => 'none',
                },
            'can_join' => $canJoin,
            'can_request' => $canRequest,
            'can_leave' => $canLeave,
            'attendance_store_url' => $canJoin || $canRequest
                ? route('events.attendance.store', ['event' => $event->slug])
                : null,
            'attendance_destroy_url' => $canLeave
                ? route('events.attendance.destroy', ['event' => $event->slug])
                : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attendeesPreview(Event $event): array
    {
        return EventAttendee::query()
            ->where('event_id', $event->id)
            ->where('status', EventAttendee::STATUS_ACTIVE)
            ->with('user.profile')
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->limit(self::ATTENDEES_PREVIEW_LIMIT)
            ->get()
            ->map(fn (EventAttendee $attendee): array => [
                'id' => $attendee->id,
                'user' => $this->eventUserData($attendee->user),
                'joined_at' => $attendee->joined_at?->toIso8601String(),
                'status_label' => 'Teilnehmer',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingAttendanceRequests(Event $event): array
    {
        return EventAttendee::query()
            ->where('event_id', $event->id)
            ->where('status', EventAttendee::STATUS_PENDING)
            ->where('user_id', '!=', $event->owner_id)
            ->with('user.profile')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (EventAttendee $attendee): array => [
                'id' => $attendee->id,
                'user' => $this->eventUserData($attendee->user),
                'requested_at' => $attendee->created_at?->toIso8601String(),
                'accept_url' => route('events.attendance.accept', [
                    'event' => $event->slug,
                    'attendee' => $attendee->id,
                ]),
                'decline_url' => route('events.attendance.decline', [
                    'event' => $event->slug,
                    'attendee' => $attendee->id,
                ]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, username: string|null, profile_photo_url: string|null, profile_url: string|null}
     */
    private function eventUserData(User $user): array
    {
        $profile = $user->profile;

        return [
            'id' => $user->id,
            'name' => $profile?->display_name ?? $user->name,
            'username' => $profile?->username,
            'profile_photo_url' => $profile?->profilePhotoUrl(),
            'profile_url' => $profile?->username !== null
                ? route('public-profile.show', ['username' => $profile->username])
                : null,
        ];
    }

    private function visibilityLabel(string $visibility): string
    {
        return match ($visibility) {
            Event::VISIBILITY_REQUEST => 'Anfrage',
            default => 'Öffentlich',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Event::STATUS_CANCELLED => 'Abgesagt',
            default => 'Aktiv',
        };
    }
}
