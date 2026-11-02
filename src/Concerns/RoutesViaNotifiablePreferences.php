<?php

namespace SameOldNick\NotificationPreferences\Concerns;

use Illuminate\Notifications\AnonymousNotifiable;
use SameOldNick\NotificationPreferences\Contracts\NotificationChannelProvider;
use SameOldNick\NotificationPreferences\Services\NotificationChannelResolver;

trait RoutesViaNotifiablePreferences
{
    /**
     * Get the specific notification type for this notification.
     *
     * This is used to look up the user's preferences for this notification.
     * This should correspond to a key in the notification types configuration.
     * By default, this will return the class name of the notification, but can be overridden in the notification class if needed.
     */
    public function notificationType(): string
    {
        return get_class($this);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable = null): array
    {
        if ($notifiable === null) {
            return [];
        }

        return app(NotificationChannelResolver::class)->viaNotifiablePreferencesWithDefaults(
            $notifiable,
            $this->notificationType(),
            $this->getAvailableChannels($notifiable),
            $this->getDefaultChannels($notifiable),
        );
    }

    /**
     * Get the available channels the notification can be sent to
     */
    protected function getAvailableChannels(mixed $notifiable): array
    {
        return app(NotificationChannelProvider::class)->availableNotificationChannels();
    }

    /**
     * Get the default channels for a given notification type, which can be overridden by the notification class.
     *
     * @return array<int, string>
     */
    public function getDefaultChannels(mixed $notifiable): array
    {
        return ! $notifiable instanceof AnonymousNotifiable ? app(NotificationChannelProvider::class)->defaultNotificationChannels() : [];
    }
}
