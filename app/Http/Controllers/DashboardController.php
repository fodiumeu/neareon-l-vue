<?php

namespace App\Http\Controllers;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('profile');

        return Inertia::render('Dashboard', [
            'home' => [
                'user' => $this->userSummary($user),
                'openItems' => $this->openItems($user),
                'upcomingEvents' => $this->upcomingEvents($user),
                'groups' => $this->groups($user),
            ],
        ]);
    }

    /**
     * @return array{name: string, username: string|null, region: string|null}
     */
    private function userSummary(User $user): array
    {
        return [
            'name' => $user->profile?->display_name ?: $user->name,
            'username' => $user->profile?->username,
            'region' => $user->profile?->region,
        ];
    }

    /**
     * @return list<array{key: string, label: string, count: int, href: string}>
     */
    private function openItems(User $user): array
    {
        return collect([
            [
                'key' => 'contact_requests',
                'label' => 'Kontaktanfragen',
                'count' => ContactRequest::query()
                    ->where('receiver_id', $user->id)
                    ->where('status', ContactRequestStatus::Pending)
                    ->count(),
                'href' => route('contact-requests.index', ['from' => 'home'], absolute: false),
            ],
            [
                'key' => 'notifications',
                'label' => 'Ungelesene Benachrichtigungen',
                'count' => $user->unreadNotifications()->count(),
                'href' => route('notifications.index', ['from' => 'home'], absolute: false),
            ],
            [
                'key' => 'group_requests',
                'label' => 'Gruppen-Beitrittsanfragen',
                'count' => $this->pendingGroupRequestCount($user),
                'href' => route('groups.mine', ['from' => 'home'], absolute: false),
            ],
            [
                'key' => 'event_requests',
                'label' => 'Event-Teilnahmeanfragen',
                'count' => $this->pendingEventRequestCount($user),
                'href' => route('events.mine', ['from' => 'home'], absolute: false),
            ],
        ])
            ->filter(fn (array $item): bool => $item['count'] > 0)
            ->values()
            ->all();
    }

    private function pendingGroupRequestCount(User $user): int
    {
        return GroupMember::query()
            ->where('status', GroupMember::STATUS_PENDING)
            ->where('role', GroupMember::ROLE_MEMBER)
            ->whereHas('group', function (Builder $query) use ($user): void {
                $query->active();

                if (! $user->canAccessAdmin()) {
                    $query->where('owner_id', $user->id);
                }
            })
            ->count();
    }

    private function pendingEventRequestCount(User $user): int
    {
        return EventAttendee::query()
            ->where('status', EventAttendee::STATUS_PENDING)
            ->whereHas('event', function (Builder $query) use ($user): void {
                $query->active();

                if (! $user->canAccessAdmin()) {
                    $query->where('owner_id', $user->id);
                }
            })
            ->count();
    }

    /**
     * @return list<array{id: int, title: string, starts_at: string|null, region: string|null, href: string}>
     */
    private function upcomingEvents(User $user): array
    {
        return Event::query()
            ->active()
            ->upcoming()
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('owner_id', $user->id)
                    ->orWhereHas('attendees', function (Builder $attendeeQuery) use ($user): void {
                        $attendeeQuery
                            ->where('user_id', $user->id)
                            ->where('status', EventAttendee::STATUS_ACTIVE);
                    });
            })
            ->oldest('starts_at')
            ->oldest('id')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'starts_at', 'region'])
            ->map(fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at?->toISOString(),
                'region' => $event->region,
                'href' => route('events.show', [
                    'event' => $event->slug,
                    'from' => 'home',
                ], absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, region: string|null, category: string|null, members_count: int, href: string}>
     */
    private function groups(User $user): array
    {
        return Group::query()
            ->active()
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('owner_id', $user->id)
                    ->orWhereHas('members', function (Builder $memberQuery) use ($user): void {
                        $memberQuery
                            ->where('user_id', $user->id)
                            ->where('status', GroupMember::STATUS_ACTIVE);
                    });
            })
            ->with('category:id,label')
            ->withCount('activeMembers')
            ->latest('updated_at')
            ->latest('id')
            ->limit(3)
            ->get(['id', 'name', 'slug', 'region', 'category_interest_option_id', 'updated_at'])
            ->map(fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'region' => $group->region,
                'category' => $group->category?->label,
                'members_count' => $group->active_members_count,
                'href' => route('groups.show', [
                    'group' => $group->slug,
                    'from' => 'home',
                ], absolute: false),
            ])
            ->all();
    }
}
