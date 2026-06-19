<?php

namespace App\Http\Controllers;

use App\Enums\InternalNotificationType;
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

        return Inertia::render('Notifications/Index', [
            'notificationItems' => $this->presentNotifications($notifications),
        ]);
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
     * @return Collection<int, array<string, mixed>>
     */
    private function presentNotifications(Collection $notifications): Collection
    {
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
            ->map(function (Collection $group): array {
                /** @var DatabaseNotification $latest */
                $latest = $group->first();
                $type = $latest->data['type'] ?? null;

                if ($type === InternalNotificationType::NewMessage->value) {
                    return $this->messageGroupData($group);
                }

                if ($group->count() === 1) {
                    return $this->notificationData($latest);
                }

                return match ($type) {
                    InternalNotificationType::NewFollower->value => $this
                        ->activityGroupData(
                            $group,
                            'follower-group',
                            "{$group->count()} neue Follower",
                            'Diese Profile folgen dir jetzt:',
                            ' folgt dir jetzt.',
                            route('discover', absolute: false),
                        ),
                    InternalNotificationType::ContactRequestReceived->value => $this
                        ->activityGroupData(
                            $group,
                            'contact-request-group',
                            "{$group->count()} neue Kontaktanfragen",
                            'Kontaktanfragen von:',
                            ' hat dir eine Kontaktanfrage gesendet.',
                            route('contact-requests.index', absolute: false),
                        ),
                    default => $this->notificationData($latest),
                };
            })
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * Preserve the existing per-conversation message grouping.
     *
     * @param  Collection<int, DatabaseNotification>  $group
     * @return array<string, mixed>
     */
    private function messageGroupData(Collection $group): array
    {
        /** @var DatabaseNotification $latest */
        $latest = $group->first();
        $count = $group->count();

        return [
            ...$this->notificationData($latest),
            'id' => "message-group:{$latest->id}",
            'title' => $this->messageSenderName(
                (string) ($latest->data['message'] ?? ''),
            ),
            'message' => $count === 1
                ? '1 neue Nachricht'
                : "{$count} neue Nachrichten",
            'read_at' => $this->groupReadAt($group),
            'notification_count' => $count,
            'is_message_group' => true,
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
    ): array {
        /** @var DatabaseNotification $latest */
        $latest = $group->first();

        return [
            ...$this->notificationData($latest),
            'id' => "{$idPrefix}:{$latest->id}",
            'title' => $title,
            'message' => $message,
            'target_url' => $targetUrl,
            'read_at' => $this->groupReadAt($group),
            'notification_count' => $group->count(),
            'is_activity_group' => true,
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
    private function notificationData(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->data['type'],
            'title' => $notification->data['title'],
            'message' => $notification->data['message'],
            'target_url' => $notification->data['target_url'],
            'created_at' => $notification->created_at->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
            'notification_count' => 1,
            'is_message_group' => false,
            'is_activity_group' => false,
            'actors' => [],
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
}
