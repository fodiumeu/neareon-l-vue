<?php

namespace App\Http\Controllers;

use App\Enums\ContactRequestStatus;
use App\Enums\InternalNotificationType;
use App\Models\ContactRequest;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->get();
        $this->hydrateLegacyMessageActorIds(
            $notifications,
            $request->user(),
        );
        $this->hydrateLegacyAcceptedContactRequestActorIds(
            $notifications,
            $request->user(),
        );
        $this->markNotificationsAsRead($notifications);
        $actors = User::query()
            ->with('profile:user_id,username,display_name,profile_photo_path')
            ->whereKey(
                $notifications
                    ->pluck('data')
                    ->pluck('actor_id')
                    ->filter()
                    ->unique()
                    ->values(),
            )
            ->get()
            ->keyBy('id');

        return Inertia::render('Notifications/Index', [
            'notificationItems' => $this->presentNotifications(
                $notifications,
                $actors,
            ),
        ]);
    }

    /**
     * Mark the displayed notifications as read while keeping the rendered
     * collection in sync with the database state.
     *
     * @param  Collection<int, DatabaseNotification>  $notifications
     */
    private function markNotificationsAsRead(Collection $notifications): void
    {
        $unreadNotifications = $notifications
            ->filter(fn (DatabaseNotification $notification): bool => $notification->read_at === null);

        if ($unreadNotifications->isEmpty()) {
            return;
        }

        $readAt = now();

        DatabaseNotification::query()
            ->whereIn('id', $unreadNotifications->pluck('id'))
            ->update(['read_at' => $readAt]);

        $unreadNotifications->each(
            fn (DatabaseNotification $notification) => $notification
                ->setAttribute('read_at', $readAt),
        );
    }

    public function open(
        Request $request,
        string $notification,
    ): RedirectResponse {
        /** @var DatabaseNotification $storedNotification */
        $storedNotification = $request->user()
            ->notifications()
            ->findOrFail($notification);

        $this->markDisplayedGroupAsRead($request, $storedNotification);

        $targetUrl = $this->acceptedContactProfileUrl(
            $storedNotification,
            $request->user(),
        ) ?? ($storedNotification->data['target_url'] ?? null);

        if (
            ! is_string($targetUrl)
            || ! str_starts_with($targetUrl, '/')
            || str_starts_with($targetUrl, '//')
        ) {
            return to_route('notifications.index');
        }

        return redirect()->to($targetUrl);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return to_route('notifications.index')
            ->with('success', 'Alle Benachrichtigungen wurden als gelesen markiert.');
    }

    /**
     * Group selected notifications for display without changing stored records.
     *
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @param  Collection<int, User>  $actors
     * @return Collection<int, array<string, mixed>>
     */
    private function presentNotifications(
        Collection $notifications,
        Collection $actors,
    ): Collection {
        return $notifications
            ->groupBy(function (DatabaseNotification $notification): string {
                $type = $notification->data['type'] ?? null;

                if ($type === InternalNotificationType::NewMessage->value) {
                    $targetUrl = $notification->data['target_url'] ?? null;

                    return is_string($targetUrl) && $targetUrl !== ''
                        ? "message:{$targetUrl}"
                        : "notification:{$notification->id}";
                }

                return match ($type) {
                    InternalNotificationType::NewFollower->value => 'followers',
                    InternalNotificationType::ContactRequestReceived->value => 'contact-requests',
                    default => "notification:{$notification->id}",
                };
            })
            ->map(function (Collection $group) use ($actors): array {
                /** @var DatabaseNotification $latest */
                $latest = $group->first();
                $type = $latest->data['type'] ?? null;

                if ($type === InternalNotificationType::NewMessage->value) {
                    return $this->messageGroupData($group, $actors);
                }

                if ($group->count() === 1) {
                    return $this->notificationData($latest, $actors);
                }

                return match ($type) {
                    InternalNotificationType::NewFollower->value => $this
                        ->activityGroupData(
                            $group,
                            'follower-group',
                            'Diese Mitglieder folgen dir jetzt',
                            $group->count() === 1
                                ? '1 neuer Follower'
                                : "{$group->count()} neue Follower",
                            ' folgt dir jetzt.',
                            route('discover', absolute: false),
                            $actors,
                        ),
                    InternalNotificationType::ContactRequestReceived->value => $this
                        ->activityGroupData(
                            $group,
                            'contact-request-group',
                            'Du hast neue Kontaktanfragen erhalten',
                            $group->count() === 1
                                ? '1 offene Anfrage'
                                : "{$group->count()} offene Anfragen",
                            ' hat dir eine Kontaktanfrage gesendet.',
                            route('contact-requests.index', absolute: false),
                            $actors,
                        ),
                    default => $this->notificationData($latest, $actors),
                };
            })
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * Older message notifications did not store an actor id. Resolve those
     * senders in one query for presentation without modifying stored records.
     *
     * @param  Collection<int, DatabaseNotification>  $notifications
     */
    private function hydrateLegacyMessageActorIds(
        Collection $notifications,
        User $viewer,
    ): void {
        $conversationIds = $notifications
            ->filter(fn (DatabaseNotification $notification): bool => (
                $notification->data['type'] ?? null
            ) === InternalNotificationType::NewMessage->value
                && empty($notification->data['actor_id']))
            ->map(fn (DatabaseNotification $notification): ?int => $this
                ->conversationId($notification))
            ->filter()
            ->unique()
            ->values();

        if ($conversationIds->isEmpty()) {
            return;
        }

        $actorIdsByConversation = ConversationParticipant::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('user_id', '!=', $viewer->id)
            ->get(['conversation_id', 'user_id'])
            ->pluck('user_id', 'conversation_id');

        $notifications->each(function (
            DatabaseNotification $notification,
        ) use ($actorIdsByConversation): void {
            if (
                ($notification->data['type'] ?? null)
                    !== InternalNotificationType::NewMessage->value
                || ! empty($notification->data['actor_id'])
            ) {
                return;
            }

            $conversationId = $this->conversationId($notification);
            $actorId = $conversationId === null
                ? null
                : $actorIdsByConversation->get($conversationId);

            if ($actorId === null) {
                return;
            }

            $data = $notification->data;
            $data['actor_id'] = $actorId;
            $notification->setAttribute('data', $data);
        });
    }

    private function conversationId(
        DatabaseNotification $notification,
    ): ?int {
        $storedConversationId = $notification->data['conversation_id'] ?? null;

        if (is_numeric($storedConversationId)) {
            return (int) $storedConversationId;
        }

        $targetUrl = $notification->data['target_url'] ?? null;

        if (
            is_string($targetUrl)
            && preg_match('#^/messages/(\d+)$#', $targetUrl, $matches) === 1
        ) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Older accepted contact request notifications did not store an actor id.
     * Resolve an actor by request history, direct conversations or follow
     * relationships only when the stored name identifies exactly one user,
     * without modifying the stored notification.
     *
     * @param  Collection<int, DatabaseNotification>  $notifications
     */
    private function hydrateLegacyAcceptedContactRequestActorIds(
        Collection $notifications,
        User $viewer,
    ): void {
        $legacyNotifications = $notifications
            ->filter(fn (DatabaseNotification $notification): bool => (
                $notification->data['type'] ?? null
            ) === InternalNotificationType::ContactRequestAccepted->value
                && empty($notification->data['actor_id']));
        $actorNames = $legacyNotifications
            ->map(fn (DatabaseNotification $notification): ?string => $this
                ->actorName(
                    (string) ($notification->data['message'] ?? ''),
                    ' hat deine Kontaktanfrage angenommen.',
                ))
            ->filter()
            ->unique()
            ->values();

        if ($actorNames->isEmpty()) {
            return;
        }

        $actorIdsByName = ContactRequest::query()
            ->where('sender_id', $viewer->id)
            ->whereIn('status', [
                ContactRequestStatus::Accepted->value,
                ContactRequestStatus::Closed->value,
            ])
            ->with([
                'receiver:id,name',
                'receiver.profile:user_id,display_name',
            ])
            ->get(['id', 'receiver_id'])
            ->map(fn (ContactRequest $contactRequest): array => [
                'actor_id' => $contactRequest->receiver_id,
                'actor_name' => $this->displayName($contactRequest->receiver),
            ])
            ->filter(fn (array $actor): bool => $actorNames
                ->contains($actor['actor_name']))
            ->groupBy('actor_name')
            ->map(fn (Collection $actors): ?int => $actors
                ->pluck('actor_id')
                ->unique()
                ->count() === 1
                    ? (int) $actors->first()['actor_id']
                    : null)
            ->filter();

        $this->hydrateAcceptedContactRequestActorIds(
            $legacyNotifications,
            $actorIdsByName,
        );

        $unresolvedActorNames = $this->acceptedContactRequestActorNames(
            $legacyNotifications,
        );

        if ($unresolvedActorNames->isNotEmpty()) {
            $conversationActors = ConversationParticipant::query()
                ->where('user_id', '!=', $viewer->id)
                ->whereHas(
                    'conversation',
                    fn ($query) => $query
                        ->whereHas(
                            'participants',
                            fn ($query) => $query->where(
                                'user_id',
                                $viewer->id,
                            ),
                        )
                        ->has('participants', '=', 2),
                )
                ->with([
                    'user:id,name',
                    'user.profile:user_id,display_name',
                ])
                ->get()
                ->pluck('user')
                ->filter();

            $this->hydrateAcceptedContactRequestActorIds(
                $legacyNotifications,
                $this->uniqueActorIdsByName(
                    $conversationActors,
                    $unresolvedActorNames,
                ),
            );
        }

        $unresolvedActorNames = $this->acceptedContactRequestActorNames(
            $legacyNotifications,
        );

        if ($unresolvedActorNames->isNotEmpty()) {
            $followActors = User::query()
                ->whereKeyNot($viewer->id)
                ->where(function ($query) use ($viewer): void {
                    $query
                        ->whereHas(
                            'followingRelationships',
                            fn ($query) => $query->where(
                                'followed_id',
                                $viewer->id,
                            ),
                        )
                        ->orWhereHas(
                            'followerRelationships',
                            fn ($query) => $query->where(
                                'follower_id',
                                $viewer->id,
                            ),
                        );
                })
                ->with('profile:user_id,display_name')
                ->get(['id', 'name']);

            $this->hydrateAcceptedContactRequestActorIds(
                $legacyNotifications,
                $this->uniqueActorIdsByName(
                    $followActors,
                    $unresolvedActorNames,
                ),
            );
        }
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return Collection<int, string>
     */
    private function acceptedContactRequestActorNames(
        Collection $notifications,
    ): Collection {
        return $notifications
            ->filter(fn (DatabaseNotification $notification): bool => empty(
                $notification->data['actor_id']
            ))
            ->map(fn (DatabaseNotification $notification): ?string => $this
                ->actorName(
                    (string) ($notification->data['message'] ?? ''),
                    ' hat deine Kontaktanfrage angenommen.',
                ))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, User>  $actors
     * @param  Collection<int, string>  $actorNames
     * @return Collection<string, int>
     */
    private function uniqueActorIdsByName(
        Collection $actors,
        Collection $actorNames,
    ): Collection {
        return $actors
            ->map(fn (User $actor): array => [
                'actor_id' => $actor->id,
                'actor_name' => $this->displayName($actor),
            ])
            ->filter(fn (array $actor): bool => $actorNames
                ->contains($actor['actor_name']))
            ->groupBy('actor_name')
            ->map(fn (Collection $matchingActors): ?int => $matchingActors
                ->pluck('actor_id')
                ->unique()
                ->count() === 1
                    ? (int) $matchingActors->first()['actor_id']
                    : null)
            ->filter();
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @param  Collection<string, int>  $actorIdsByName
     */
    private function hydrateAcceptedContactRequestActorIds(
        Collection $notifications,
        Collection $actorIdsByName,
    ): void {
        $notifications->each(function (
            DatabaseNotification $notification,
        ) use ($actorIdsByName): void {
            if (! empty($notification->data['actor_id'])) {
                return;
            }

            $actorName = $this->actorName(
                (string) ($notification->data['message'] ?? ''),
                ' hat deine Kontaktanfrage angenommen.',
            );
            $actorId = $actorName === null
                ? null
                : $actorIdsByName->get($actorName);

            if ($actorId === null) {
                return;
            }

            $data = $notification->data;
            $data['actor_id'] = $actorId;
            $notification->setAttribute('data', $data);
        });
    }

    /**
     * Preserve the existing per-conversation message grouping.
     *
     * @param  Collection<int, DatabaseNotification>  $group
     * @param  Collection<int, User>  $actors
     * @return array<string, mixed>
     */
    private function messageGroupData(
        Collection $group,
        Collection $actors,
    ): array {
        /** @var DatabaseNotification $latest */
        $latest = $group->first();
        $count = $group->count();
        $notificationData = $this->notificationData($latest, $actors);

        return [
            ...$notificationData,
            'id' => "message-group:{$latest->id}",
            'title' => $notificationData['actor']['display_name']
                ?? $this->messageSenderName(
                    (string) ($latest->data['message'] ?? ''),
                ),
            'message' => $count === 1
                ? '1 neue Nachricht'
                : "{$count} neue Nachrichten",
            'read_at' => $this->groupReadAt($group),
            'notification_count' => $count,
            'is_message_group' => true,
            'visual_kind' => 'message',
            'cta_label' => 'Unterhaltung öffnen',
        ];
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $group
     * @return array<string, mixed>
     */
    private function activityGroupData(
        Collection $group,
        string $idPrefix,
        string $title,
        string $message,
        string $actorSuffix,
        string $targetUrl,
        Collection $actors,
    ): array {
        /** @var DatabaseNotification $latest */
        $latest = $group->first();
        $type = $latest->data['type'] ?? null;
        $isFollowerGroup = $type
            === InternalNotificationType::NewFollower->value;

        return [
            ...$this->notificationData($latest, $actors),
            'id' => "{$idPrefix}:{$latest->id}",
            'title' => $title,
            'message' => $message,
            'target_url' => $targetUrl,
            'read_at' => $this->groupReadAt($group),
            'notification_count' => $group->count(),
            'is_activity_group' => true,
            'actor' => null,
            'visual_kind' => match ($type) {
                InternalNotificationType::NewFollower->value => 'followers',
                InternalNotificationType::ContactRequestReceived->value => 'contact-requests',
                default => 'actor',
            },
            'cta_label' => $isFollowerGroup
                ? null
                : $this->ctaLabel((string) $type),
            'open_url' => $isFollowerGroup
                ? null
                : route(
                    'notifications.open',
                    $latest->id,
                    absolute: false,
                ),
            'actors' => $group
                ->map(fn (DatabaseNotification $notification): ?string => $this
                    ->actorName(
                        (string) ($notification->data['message'] ?? ''),
                        $actorSuffix,
                    ))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'actor_previews' => $group
                ->map(function (DatabaseNotification $notification) use (
                    $actors,
                ): ?User {
                    $actorId = $notification->data['actor_id'] ?? null;

                    return $actorId === null
                        ? null
                        : $actors->get((int) $actorId);
                })
                ->filter()
                ->unique('id')
                ->take(3)
                ->map(fn (User $actor): array => $this
                    ->actorPresentation($actor))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationData(
        DatabaseNotification $notification,
        Collection $actors,
    ): array {
        $actorId = $notification->data['actor_id'] ?? null;
        /** @var User|null $actor */
        $actor = $actorId === null ? null : $actors->get((int) $actorId);
        $type = (string) ($notification->data['type'] ?? '');
        $title = (string) ($notification->data['title'] ?? '');
        $message = (string) ($notification->data['message'] ?? '');
        $targetUrl = $notification->data['target_url'] ?? null;

        if ($type === InternalNotificationType::NewFollower->value) {
            $title = 'Diese Mitglieder folgen dir jetzt';
            $message = '1 neuer Follower';
        } elseif ($type === InternalNotificationType::ContactRequestReceived->value) {
            $title = 'Du hast neue Kontaktanfragen erhalten';
            $message = '1 offene Anfrage';
        } elseif ($type === InternalNotificationType::ContactRequestAccepted->value) {
            $title = $actor === null
                ? $this->actorName($message, ' hat deine Kontaktanfrage angenommen.')
                    ?? $title
                : $this->displayName($actor);
            $message = 'hat deine Kontaktanfrage angenommen';
            $targetUrl = $actor?->profile?->username === null
                ? $targetUrl
                : route(
                    'public-profile.show',
                    $actor->profile->username,
                    absolute: false,
                );
        } elseif ($type === InternalNotificationType::ContactRequestDeclined->value) {
            $title = $actor === null
                ? $this->actorName($message, ' hat deine Kontaktanfrage abgelehnt.')
                    ?? $title
                : $this->displayName($actor);
            $message = 'hat deine Kontaktanfrage abgelehnt';
        }

        return [
            'id' => $notification->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'target_url' => $targetUrl,
            'created_at' => $notification->created_at->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
            'notification_count' => 1,
            'is_message_group' => false,
            'is_activity_group' => false,
            'actors' => [],
            'actor_previews' => [],
            'visual_kind' => match ($type) {
                InternalNotificationType::NewFollower->value => 'followers',
                InternalNotificationType::ContactRequestReceived->value => 'contact-requests',
                InternalNotificationType::NewMessage->value => 'message',
                default => 'actor',
            },
            'cta_label' => $this->ctaLabel($type),
            'actor' => $actor === null
                ? null
                : $this->actorPresentation($actor),
            'open_url' => route(
                'notifications.open',
                $notification->id,
                absolute: false,
            ),
        ];
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $group
     */
    private function groupReadAt(Collection $group): ?string
    {
        if ($group->contains(
            fn (DatabaseNotification $notification): bool => $notification->read_at === null,
        )) {
            return null;
        }

        /** @var DatabaseNotification $latest */
        $latest = $group->first();

        return $latest->read_at?->toIso8601String();
    }

    private function messageSenderName(string $message): string
    {
        if (
            str_starts_with($message, 'Neue Nachricht von ')
            && str_ends_with($message, '.')
        ) {
            return mb_substr(
                $message,
                mb_strlen('Neue Nachricht von '),
                -1,
            );
        }

        return $this->actorName(
            $message,
            ' hat dir eine Nachricht gesendet.',
        ) ?? 'Neue Nachrichten';
    }

    private function actorName(string $message, string $suffix): ?string
    {
        if (str_ends_with($message, $suffix)) {
            return mb_substr($message, 0, -mb_strlen($suffix));
        }

        return null;
    }

    private function displayName(User $user): string
    {
        return $user->profile?->display_name ?? $user->name;
    }

    /**
     * @return array{
     *     display_name: string,
     *     profile_photo_url: string|null,
     *     initials: string
     * }
     */
    private function actorPresentation(User $actor): array
    {
        $displayName = $this->displayName($actor);

        return [
            'display_name' => $displayName,
            'profile_photo_url' => $actor->profile?->profilePhotoUrl(),
            'initials' => mb_strtoupper(mb_substr($displayName, 0, 1)),
        ];
    }

    private function acceptedContactProfileUrl(
        DatabaseNotification $notification,
        User $viewer,
    ): ?string {
        if (
            ($notification->data['type'] ?? null)
            !== InternalNotificationType::ContactRequestAccepted->value
        ) {
            return null;
        }

        if (empty($notification->data['actor_id'])) {
            $this->hydrateLegacyAcceptedContactRequestActorIds(
                collect([$notification]),
                $viewer,
            );
        }

        $actorId = $notification->data['actor_id'] ?? null;

        if ($actorId === null) {
            return null;
        }

        $actor = User::query()
            ->with('profile:user_id,username')
            ->find((int) $actorId);

        return $actor?->profile?->username === null
            ? null
            : route(
                'public-profile.show',
                $actor->profile->username,
                absolute: false,
            );
    }

    private function ctaLabel(string $type): string
    {
        return match ($type) {
            InternalNotificationType::NewMessage->value => 'Unterhaltung öffnen',
            InternalNotificationType::ContactRequestReceived->value => 'Kontaktanfragen ansehen',
            InternalNotificationType::NewFollower->value => 'Profile ansehen',
            InternalNotificationType::ContactRequestAccepted->value => 'Kontakt ansehen',
            InternalNotificationType::ContactRequestDeclined->value => 'Details ansehen',
            default => 'Details ansehen',
        };
    }

    private function markDisplayedGroupAsRead(
        Request $request,
        DatabaseNotification $notification,
    ): void {
        $type = $notification->data['type'] ?? null;

        if (
            $type === InternalNotificationType::NewFollower->value
            || $type === InternalNotificationType::ContactRequestReceived->value
        ) {
            $request->user()
                ->unreadNotifications()
                ->where('data->type', $type)
                ->update(['read_at' => now()]);

            return;
        }

        if ($type === InternalNotificationType::NewMessage->value) {
            $conversationId = $notification->data['conversation_id'] ?? null;
            $targetUrl = $notification->data['target_url'] ?? null;

            $request->user()
                ->unreadNotifications()
                ->get()
                ->filter(fn (DatabaseNotification $candidate): bool => (
                    $candidate->data['type'] ?? null
                ) === InternalNotificationType::NewMessage->value
                    && (
                        (
                            $conversationId !== null
                            && (int) ($candidate->data['conversation_id'] ?? 0)
                                === (int) $conversationId
                        )
                        || (
                            ($candidate->data['target_url'] ?? null)
                            === $targetUrl
                        )
                    ))
                ->each->markAsRead();

            return;
        }

        $notification->markAsRead();
    }
}
