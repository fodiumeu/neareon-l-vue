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
        public readonly ?int $actorId = null,
        public readonly ?int $conversationId = null,
        public readonly array $extraData = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->notificationType->value,
            'title' => $this->title,
            'message' => $this->message,
            'target_url' => $this->targetUrl,
            'actor_id' => $this->actorId,
            'conversation_id' => $this->conversationId,
        ] + $this->extraData;
    }
}
