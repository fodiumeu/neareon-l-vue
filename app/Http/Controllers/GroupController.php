<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
     * Show groups owned by or attached to the current user.
     */
    public function mine(Request $request): Response
    {
        $viewer = $request->user();

        $groups = $this->myGroupsQuery($viewer)
            ->with([
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
            'visibility' => $group->visibility,
            'visibility_label' => $this->visibilityLabel($group->visibility),
            'member_count' => $group->active_members_count ?? 0,
            'owner' => $this->userData($group->owner),
            'membership' => $membershipData,
            'url' => route('groups.show', ['group' => $group->slug]),
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
