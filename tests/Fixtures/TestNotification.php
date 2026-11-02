<?php

namespace SameOldNick\NotificationPreferences\Tests\Fixtures;

use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use SameOldNick\NotificationPreferences\Concerns\RoutesViaNotifiablePreferences;

class TestNotification extends Notification
{
    use Queueable;
    use RoutesViaNotifiablePreferences { notificationType as parentNotificationType; }

    /**
     * Create a new notification instance.
     *
     * @param array|(Closure($notifiable): array) $data The data to be stored in the notification's database representation. This can be an array or a closure that returns an array based on the notifiable.
     * @param  string|null  $notificationType  The notification type, which can be used to look up user preferences. If null, the class name will be used.
     */
    public function __construct(
        public readonly array|Closure $data,
        public readonly ?string $notificationType = null,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function notificationType(): string
    {
        return $this->notificationType ?: $this->parentNotificationType();
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return value($this->data, $notifiable);
    }
}
