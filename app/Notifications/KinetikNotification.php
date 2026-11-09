<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A bell notification from the Kinetik weekly flow. The bell only shows the
 * message and opens the url, so one class serves every Kinetik event.
 */
class KinetikNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly ?string $url = null,
        /** Extra fields stored with the notice (for example the pairs an admin asked about). */
        public readonly array $extra = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => $this->type, 'message' => $this->message, 'url' => $this->url] + $this->extra;
    }
}
