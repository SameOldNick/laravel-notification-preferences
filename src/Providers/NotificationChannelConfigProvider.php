<?php

namespace SameOldNick\NotificationPreferences\Providers;

use SameOldNick\NotificationPreferences\Contracts\NotificationChannelProvider;

class NotificationChannelConfigProvider implements NotificationChannelProvider
{
    /**
     * {@inheritDoc}
     */
    public function notificationChannels(): array
    {
        return config('notifications.channels', []);
    }

    /**
     * {@inheritDoc}
     */
    public function availableNotificationChannels(): array
    {
        return config('notifications.available_channels', []);
    }

    /**
     * {@inheritDoc}
     */
    public function requiredNotificationChannels(): array
    {
        return config('notifications.required_channels', []);
    }

    /**
     * {@inheritDoc}
     */
    public function availableNotificationChannelsFor(string $notificationType, object $notifiable): array
    {
        return config('notifications.available_channels', []);
    }

    /**
     * {@inheritDoc}
     */
    public function requiredNotificationChannelsFor(string $notificationType, object $notifiable): array
    {
        return config('notifications.required_channels', []);
    }
}
