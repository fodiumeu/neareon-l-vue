<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRequest;
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
            ->through(fn (Group $group): array => $this->groupSummary($group, $viewer));

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
            'visibilityOptions' => [
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
            ],
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
            ->through(fn (Group $group): array => $this->groupSummary($group, $viewer));

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

        return Inertia::render('Groups/Show', [
            'group' => array_merge($this->groupSummary($group, $viewer), [
                'members' => $group->activeMembers
                    ->map(fn (GroupMember $membership): array => $this->memberData($membership))
                    ->values(),
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
    private function groupSummary(Group $group, User $viewer): array
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
            GroupMember::STATUS_PENDING => 'Ausstehend',
            default => 'Mitglied',
        };
    }
}
