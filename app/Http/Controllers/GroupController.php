<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\InterestOption;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    private const PER_PAGE = 12;

    private const SOURCE_GROUPS = 'groups';

    private const SOURCE_MY_GROUPS = 'my-groups';

    /**
     * Show public discoverable groups.
     */
    public function index(Request $request): Response
    {
        $viewer = $request->user();

        $groups = $this->discoverGroupsQuery()
            ->with([
                'category',
                'owner.profile',
                'members' => fn ($query) => $query
                    ->where('user_id', $viewer->id)
                    ->select(['id', 'group_id', 'user_id', 'role', 'status']),
            ])
            ->withCount('activeMembers')
            ->orderByDesc('created_at')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Group $group): array => $this->groupSummary($group, $viewer, self::SOURCE_GROUPS));

        return Inertia::render('Groups/Index', [
            'groups' => $groups,
        ]);
    }

    /**
     * Show the group creation form.
     */
    public function create(): Response
    {
        return Inertia::render('Groups/Create', [
            'categoryOptions' => $this->categoryOptions(),
            'visibilityOptions' => $this->visibilityOptions(),
        ]);
    }

    /**
     * Store a newly created group.
     */
    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $viewer = $request->user();
        $attributes = $request->validated();

        $group = DB::transaction(function () use ($attributes, $viewer): Group {
            $group = Group::query()->create([
                ...$attributes,
                'owner_id' => $viewer->id,
                'slug' => $this->uniqueSlug($attributes['name']),
                'status' => Group::STATUS_ACTIVE,
            ]);

            GroupMember::query()->create([
                'group_id' => $group->id,
                'user_id' => $viewer->id,
                'role' => GroupMember::ROLE_OWNER,
                'status' => GroupMember::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            return $group;
        });

        return to_route('groups.show', ['group' => $group->slug])
            ->with('success', 'Gruppe wurde erstellt.');
    }

    /**
     * Show the group edit form.
     */
    public function edit(Request $request, Group $group): Response
    {
        $viewer = $request->user();

        abort_unless($this->canManageGroup($group, $viewer), 403);

        $group->load(['category']);

        return Inertia::render('Groups/Edit', [
            'categoryOptions' => $this->categoryOptions(),
            'group' => $this->groupFormData($group),
            'visibilityOptions' => $this->visibilityOptions(),
        ]);
    }

    /**
     * Update an existing group.
     */
    public function update(UpdateGroupRequest $request, Group $group): RedirectResponse
    {
        $viewer = $request->user();

        abort_unless($this->canManageGroup($group, $viewer), 403);

        $group->update($request->validated());

        return to_route('groups.show', ['group' => $group->slug])
            ->with('success', 'Gruppe wurde aktualisiert.');
    }

    /**
     * Join a public group or request access to a request-based group.
     */
    public function join(Request $request, Group $group): RedirectResponse
    {
        $viewer = $request->user();

        abort_unless($group->status === Group::STATUS_ACTIVE, 404);
        abort_if($group->owner_id === $viewer->id, 403);
        abort_unless(in_array($group->visibility, [
            Group::VISIBILITY_PUBLIC,
            Group::VISIBILITY_REQUEST,
        ], true), 404);

        $targetStatus = $group->visibility === Group::VISIBILITY_PUBLIC
            ? GroupMember::STATUS_ACTIVE
            : GroupMember::STATUS_PENDING;

        $membership = GroupMember::query()->firstOrCreate(
            [
                'group_id' => $group->id,
                'user_id' => $viewer->id,
            ],
            [
                'role' => GroupMember::ROLE_MEMBER,
                'status' => $targetStatus,
                'joined_at' => $targetStatus === GroupMember::STATUS_ACTIVE
                    ? now()
                    : null,
            ],
        );

        if (! $membership->wasRecentlyCreated) {
            return to_route('groups.show', $this->showRouteParameters($group, $this->requestSource($request)))
                ->with('success', $this->existingMembershipMessage($membership));
        }

        return to_route('groups.show', $this->showRouteParameters($group, $this->requestSource($request)))
            ->with('success', $targetStatus === GroupMember::STATUS_ACTIVE
            ? 'Du bist der Gruppe beigetreten.'
            : 'Deine Beitrittsanfrage wurde gesendet.');
    }

    /**
     * Leave a group or withdraw the current user's pending membership request.
     */
    public function leave(Request $request, Group $group): RedirectResponse
    {
        $viewer = $request->user();

        $membership = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $viewer->id)
            ->first();

        abort_unless($membership instanceof GroupMember, 404);
        abort_if($group->owner_id === $viewer->id || $membership->role === GroupMember::ROLE_OWNER, 403);
        abort_unless(in_array($membership->status, [
            GroupMember::STATUS_ACTIVE,
            GroupMember::STATUS_PENDING,
        ], true), 404);

        $wasPending = $membership->status === GroupMember::STATUS_PENDING;

        $membership->delete();

        return $wasPending
            ? to_route('groups.index')->with('success', 'Deine Beitrittsanfrage wurde zurückgezogen.')
            : to_route('groups.mine')->with('success', 'Du hast die Gruppe verlassen.');
    }

    /**
     * Accept a pending membership request for a group.
     */
    public function acceptRequest(Request $request, Group $group, GroupMember $member): RedirectResponse
    {
        $viewer = $request->user();

        abort_unless($this->canManageGroup($group, $viewer), 403);
        $this->ensurePendingRequestBelongsToGroup($group, $member);

        $member->forceFill([
            'status' => GroupMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ])->save();

        return to_route('groups.show', ['group' => $group->slug])
            ->with('success', 'Anfrage angenommen.');
    }

    /**
     * Decline a pending membership request for a group.
     */
    public function declineRequest(Request $request, Group $group, GroupMember $member): RedirectResponse
    {
        $viewer = $request->user();

        abort_unless($this->canManageGroup($group, $viewer), 403);
        $this->ensurePendingRequestBelongsToGroup($group, $member);

        $member->delete();

        return to_route('groups.show', ['group' => $group->slug])
            ->with('success', 'Anfrage abgelehnt.');
    }

    /**
     * Show groups owned by or attached to the current user.
     */
    public function mine(Request $request): Response
    {
        $viewer = $request->user();

        $groups = $this->myGroupsQuery($viewer)
            ->with([
                'category',
                'owner.profile',
                'members' => fn ($query) => $query
                    ->where('user_id', $viewer->id)
                    ->select(['id', 'group_id', 'user_id', 'role', 'status']),
            ])
            ->withCount('activeMembers')
            ->orderByDesc('created_at')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Group $group): array => $this->groupSummary($group, $viewer, self::SOURCE_MY_GROUPS));

        return Inertia::render('Groups/MyGroups', [
            'groups' => $groups,
        ]);
    }

    /**
     * Show a visible group.
     */
    public function show(Request $request, Group $group): Response
    {
        $viewer = $request->user();

        abort_unless($this->canViewGroup($group, $viewer), 404);

        $group->load([
            'category',
            'owner.profile',
            'members' => fn ($query) => $query
                ->where('user_id', $viewer->id)
                ->select(['id', 'group_id', 'user_id', 'role', 'status']),
            'activeMembers' => fn ($query) => $query
                ->with(['user.profile'])
                ->latest('joined_at')
                ->latest('id')
                ->limit(6),
        ])->loadCount('activeMembers');

        $pendingRequests = $this->canManageGroup($group, $viewer)
            ? $this->pendingRequestsData($group)
            : [];

        return Inertia::render('Groups/Show', [
            'group' => array_merge($this->groupSummary($group, $viewer, $this->requestSource($request)), [
                ...$this->backlinkData($group, $viewer, $this->requestSource($request)),
                'members' => $group->activeMembers
                    ->map(fn (GroupMember $membership): array => $this->memberData($membership))
                    ->values(),
                'pending_requests' => $pendingRequests,
            ]),
        ]);
    }

    /**
     * @return Builder<Group>
     */
    private function discoverGroupsQuery(): Builder
    {
        return Group::query()
            ->active()
            ->whereIn('visibility', [
                Group::VISIBILITY_PUBLIC,
                Group::VISIBILITY_REQUEST,
            ]);
    }

    /**
     * @return Builder<Group>
     */
    private function myGroupsQuery(User $viewer): Builder
    {
        return Group::query()
            ->active()
            ->where(function (Builder $query) use ($viewer): void {
                $query
                    ->where('owner_id', $viewer->id)
                    ->orWhereHas('members', fn (Builder $memberQuery) => $memberQuery
                        ->where('user_id', $viewer->id)
                        ->whereIn('status', [
                            GroupMember::STATUS_ACTIVE,
                            GroupMember::STATUS_PENDING,
                        ]));
            });
    }

    private function canViewGroup(Group $group, User $viewer): bool
    {
        if ($group->status !== Group::STATUS_ACTIVE) {
            return false;
        }

        if (in_array($group->visibility, [
            Group::VISIBILITY_PUBLIC,
            Group::VISIBILITY_REQUEST,
        ], true)) {
            return true;
        }

        if ($group->visibility !== Group::VISIBILITY_PRIVATE) {
            return false;
        }

        if ($group->owner_id === $viewer->id || $viewer->canAccessAdmin()) {
            return true;
        }

        return $group->activeMembers()
            ->where('user_id', $viewer->id)
            ->exists();
    }

    private function requestSource(Request $request): ?string
    {
        $source = $request->string('return_to')->toString()
            ?: $request->string('from')->toString();

        return in_array($source, [
            self::SOURCE_GROUPS,
            self::SOURCE_MY_GROUPS,
        ], true)
            ? $source
            : null;
    }

    /**
     * @return array<string, string>
     */
    private function showRouteParameters(Group $group, ?string $source = null): array
    {
        $parameters = ['group' => $group->slug];

        if (in_array($source, [
            self::SOURCE_GROUPS,
            self::SOURCE_MY_GROUPS,
        ], true)) {
            $parameters['from'] = $source;
        }

        return $parameters;
    }

    /**
     * @return array{back_url: string, back_label: string, back_source: string}
     */
    private function backlinkData(Group $group, User $viewer, ?string $source): array
    {
        $backSource = $source;

        if ($backSource === null) {
            $hasMembership = $group->owner_id === $viewer->id
                || $group->members->isNotEmpty();

            $backSource = $hasMembership
                ? self::SOURCE_MY_GROUPS
                : self::SOURCE_GROUPS;
        }

        return $backSource === self::SOURCE_MY_GROUPS
            ? [
                'back_url' => route('groups.mine'),
                'back_label' => 'Zurück zu Meine Gruppen',
                'back_source' => self::SOURCE_MY_GROUPS,
            ]
            : [
                'back_url' => route('groups.index'),
                'back_label' => 'Zurück zu Gruppen entdecken',
                'back_source' => self::SOURCE_GROUPS,
            ];
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'gruppe';
        $slug = $baseSlug;
        $suffix = 2;

        while (Group::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function groupSummary(Group $group, User $viewer, ?string $source = null): array
    {
        $membership = $group->members->first();
        $membershipData = $membership !== null
            ? [
                'role' => $membership->role,
                'role_label' => $this->roleLabel($membership->role),
                'status' => $membership->status,
                'status_label' => $this->membershipStatusLabel($membership->status),
            ]
            : null;

        if ($membershipData === null && $group->owner_id === $viewer->id) {
            $membershipData = [
                'role' => GroupMember::ROLE_OWNER,
                'role_label' => $this->roleLabel(GroupMember::ROLE_OWNER),
                'status' => GroupMember::STATUS_ACTIVE,
                'status_label' => $this->membershipStatusLabel(GroupMember::STATUS_ACTIVE),
            ];
        }

        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'description' => $group->description,
            'region' => $group->region,
            'postal_code' => $group->postal_code,
            'country_code' => $group->country_code,
            'visibility' => $group->visibility,
            'visibility_label' => $this->visibilityLabel($group->visibility),
            'member_count' => $group->active_members_count ?? 0,
            'owner' => $this->userData($group->owner),
            'membership' => $membershipData,
            'can_edit' => $this->canManageGroup($group, $viewer),
            'can_join' => $this->canJoinGroup($group, $membershipData, $viewer),
            'join_label' => $this->joinLabel($group, $membershipData, $viewer),
            'join_url' => $this->canJoinGroup($group, $membershipData, $viewer)
                ? route('groups.join', ['group' => $group->slug])
                : null,
            'can_leave' => $this->canLeaveGroup($group, $membershipData, $viewer),
            'leave_label' => $this->leaveLabel($membershipData),
            'leave_url' => $this->canLeaveGroup($group, $membershipData, $viewer)
                ? route('groups.membership.destroy', ['group' => $group->slug])
                : null,
            'viewer_membership_status' => $membershipData['status'] ?? null,
            'viewer_role' => $membershipData['role'] ?? null,
            'category' => $group->category !== null
                ? $this->categoryData($group->category)
                : null,
            'edit_url' => route('groups.edit', ['group' => $group->slug]),
            'url' => route('groups.show', $this->showRouteParameters($group, $source)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function groupFormData(Group $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'description' => $group->description,
            'region' => $group->region,
            'postal_code' => $group->postal_code,
            'country_code' => $group->country_code,
            'visibility' => $group->visibility,
            'category_interest_option_id' => $group->category_interest_option_id,
            'category' => $group->category !== null
                ? $this->categoryData($group->category)
                : null,
            'url' => route('groups.show', ['group' => $group->slug]),
        ];
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
                'value' => Group::VISIBILITY_PUBLIC,
                'label' => 'Öffentlich',
                'description' => 'Alle Mitglieder können die Gruppe finden und ansehen.',
            ],
            [
                'value' => Group::VISIBILITY_REQUEST,
                'label' => 'Anfrage',
                'description' => 'Mitglieder können die Gruppe finden; Beitritt erfolgt später per Anfrage.',
            ],
            [
                'value' => Group::VISIBILITY_PRIVATE,
                'label' => 'Privat',
                'description' => 'Nur Mitglieder können die Gruppe sehen.',
            ],
        ];
    }

    private function canManageGroup(Group $group, User $viewer): bool
    {
        return $group->owner_id === $viewer->id || $viewer->canAccessAdmin();
    }

    /**
     * @param  array<string, string>|null  $membershipData
     */
    private function canJoinGroup(Group $group, ?array $membershipData, User $viewer): bool
    {
        return $membershipData === null
            && $group->owner_id !== $viewer->id
            && ! $viewer->canAccessAdmin()
            && in_array($group->visibility, [
                Group::VISIBILITY_PUBLIC,
                Group::VISIBILITY_REQUEST,
            ], true);
    }

    /**
     * @param  array<string, string>|null  $membershipData
     */
    private function joinLabel(Group $group, ?array $membershipData, User $viewer): ?string
    {
        if (! $this->canJoinGroup($group, $membershipData, $viewer)) {
            return null;
        }

        return $group->visibility === Group::VISIBILITY_PUBLIC
            ? 'Gruppe beitreten'
            : 'Beitrittsanfrage senden';
    }

    /**
     * @param  array<string, string>|null  $membershipData
     */
    private function canLeaveGroup(Group $group, ?array $membershipData, User $viewer): bool
    {
        if ($membershipData === null || $group->owner_id === $viewer->id) {
            return false;
        }

        if ($membershipData['role'] === GroupMember::ROLE_OWNER) {
            return false;
        }

        return in_array($membershipData['status'], [
            GroupMember::STATUS_ACTIVE,
            GroupMember::STATUS_PENDING,
        ], true);
    }

    /**
     * @param  array<string, string>|null  $membershipData
     */
    private function leaveLabel(?array $membershipData): ?string
    {
        if ($membershipData === null || $membershipData['role'] === GroupMember::ROLE_OWNER) {
            return null;
        }

        return $membershipData['status'] === GroupMember::STATUS_PENDING
            ? 'Anfrage zurückziehen'
            : 'Gruppe verlassen';
    }

    private function existingMembershipMessage(GroupMember $membership): string
    {
        return $membership->status === GroupMember::STATUS_PENDING
            ? 'Deine Beitrittsanfrage wurde bereits gesendet.'
            : 'Du bist bereits Mitglied dieser Gruppe.';
    }

    private function ensurePendingRequestBelongsToGroup(Group $group, GroupMember $member): void
    {
        abort_unless($member->group_id === $group->id, 404);
        abort_unless($member->status === GroupMember::STATUS_PENDING, 404);
        abort_unless($member->role === GroupMember::ROLE_MEMBER, 404);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingRequestsData(Group $group): array
    {
        return GroupMember::query()
            ->where('group_id', $group->id)
            ->where('status', GroupMember::STATUS_PENDING)
            ->where('role', GroupMember::ROLE_MEMBER)
            ->with(['user.profile'])
            ->oldest('created_at')
            ->oldest('id')
            ->get()
            ->map(fn (GroupMember $membership): array => [
                'id' => $membership->id,
                'requested_at' => $membership->created_at?->toISOString(),
                'user' => $this->userData($membership->user),
                'accept_url' => route('groups.requests.accept', [
                    'group' => $group->slug,
                    'member' => $membership->id,
                ]),
                'decline_url' => route('groups.requests.decline', [
                    'group' => $group->slug,
                    'member' => $membership->id,
                ]),
                'profile_url' => $membership->user->profile?->username !== null
                    ? route('public-profile.show', $membership->user->profile->username)
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function memberData(GroupMember $membership): array
    {
        return [
            'id' => $membership->id,
            'role' => $membership->role,
            'role_label' => $this->roleLabel($membership->role),
            'joined_at' => $membership->joined_at?->toISOString(),
            'user' => $this->userData($membership->user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userData(User $user): array
    {
        /** @var Profile|null $profile */
        $profile = $user->profile;

        return [
            'id' => $user->id,
            'name' => $profile?->display_name ?? $user->name,
            'username' => $profile?->username,
            'profile_photo_url' => $profile?->profilePhotoUrl(),
        ];
    }

    private function visibilityLabel(string $visibility): string
    {
        return match ($visibility) {
            Group::VISIBILITY_REQUEST => 'Anfrage',
            Group::VISIBILITY_PRIVATE => 'Privat',
            default => 'Öffentlich',
        };
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            GroupMember::ROLE_OWNER => 'Besitzer',
            GroupMember::ROLE_MODERATOR => 'Moderator',
            default => 'Mitglied',
        };
    }

    private function membershipStatusLabel(string $status): string
    {
        return match ($status) {
            GroupMember::STATUS_PENDING => 'Anfrage ausstehend',
            default => 'Mitglied',
        };
    }
}
