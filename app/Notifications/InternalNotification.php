<?php

namespace App\Notifications;

use App\Enums\InternalNotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InternalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InternalNotificationType $notificationType,
        public readonly string $title,
        public readonly string $message,
        public readonly string $targetUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->notificationType->value,
            'title' => $this->title,
            'message' => $this->message,
            'target_url' => $this->targetUrl,
        ];
    }
}
