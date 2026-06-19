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
     * Group message notifications for display without changing stored records.
     *
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return Collection<int, array<string, mixed>>
     */
    private function presentNotifications(Collection $notifications): Collection
    {
        return $notifications
            ->groupBy(function (DatabaseNotification $notification): string {
                if (($notification->data['type'] ?? null)
                    !== InternalNotificationType::NewMessage->value) {
                    return "notification:{$notification->id}";
                }

                $targetUrl = $notification->data['target_url'] ?? null;

                return is_string($targetUrl) && $targetUrl !== ''
                    ? "message:{$targetUrl}"
                    : "notification:{$notification->id}";
            })
            ->map(function (Collection $group): array {
                /** @var DatabaseNotification $latest */
                $latest = $group->first();
                $isMessageGroup = ($latest->data['type'] ?? null)
                    === InternalNotificationType::NewMessage->value;

                if (! $isMessageGroup) {
                    return $this->notificationData($latest);
                }

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
                    'read_at' => $group->contains(
                        fn (DatabaseNotification $notification): bool => $notification->read_at === null,
                    )
                        ? null
                        : $latest->read_at?->toIso8601String(),
                    'notification_count' => $count,
                    'is_message_group' => true,
                ];
            })
            ->sortByDesc('created_at')
            ->values();
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
        ];
    }

    private function messageSenderName(string $message): string
    {
        $suffix = ' hat dir eine Nachricht gesendet.';

        if (str_ends_with($message, $suffix)) {
            return mb_substr($message, 0, -mb_strlen($suffix));
        }

        return 'Neue Nachrichten';
    }
}
