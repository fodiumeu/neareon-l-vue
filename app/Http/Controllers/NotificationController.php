<?php

namespace App\Http\Controllers;

use App\Enums\InternalNotificationType;
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
        $actors = User::query()
            ->with('profile:user_id,display_name,profile_photo_path')
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

    public function open(
        Request $request,
        string $notification,
    ): RedirectResponse {
        /** @var DatabaseNotification $storedNotification */
        $storedNotification = $request->user()
            ->notifications()
            ->findOrFail($notification);

        $this->markDisplayedGroupAsRead($request, $storedNotification);

        $targetUrl = $storedNotification->data['target_url'] ?? null;

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
            'visual_kind' => match ($latest->data['type'] ?? null) {
                InternalNotificationType::NewFollower->value => 'followers',
                InternalNotificationType::ContactRequestReceived->value => 'contact-requests',
                default => 'actor',
            },
            'cta_label' => $this->ctaLabel(
                (string) ($latest->data['type'] ?? ''),
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
            'target_url' => $notification->data['target_url'],
            'created_at' => $notification->created_at->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
            'notification_count' => 1,
            'is_message_group' => false,
            'is_activity_group' => false,
            'actors' => [],
            'visual_kind' => match ($type) {
                InternalNotificationType::NewFollower->value => 'followers',
                InternalNotificationType::ContactRequestReceived->value => 'contact-requests',
                InternalNotificationType::NewMessage->value => 'message',
                default => 'actor',
            },
            'cta_label' => $this->ctaLabel($type),
            'actor' => $actor === null
                ? null
                : [
                    'display_name' => $this->displayName($actor),
                    'profile_photo_url' => $actor->profile?->profilePhotoUrl(),
                    'initials' => mb_strtoupper(mb_substr(
                        $this->displayName($actor),
                        0,
                        1,
                    )),
                ],
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
