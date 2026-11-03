<?php

namespace SameOldNick\NotificationPreferences\Providers;

use Illuminate\Contracts\Config\Repository;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;

class NotificationCatalogConfigProvider implements NotificationCatalog
{
    /**
     * Create a new instance of the NotificationCatalogConfigProvider.
     *
     * @return void
     */
    public function __construct(private readonly Repository $config)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function notificationTypes(): array
    {
        return $this->config->get('notifications.types', []);
    }

    /**
     * {@inheritDoc}
     */
    public function notificationChannels(): array
    {
        return $this->config->get('notifications.channels', []);
    }

    /**
     * {@inheritDoc}
     */
    public function availableNotificationChannels(): array
    {
        return $this->config->get('notifications.available_channels', []);
    }

    /**
     * {@inheritDoc}
     */
    public function requiredNotificationChannels(): array
    {
        return $this->config->get('notifications.required_channels', []);
    }

    /**
     * {@inheritDoc}
     */
    public function availableNotificationChannelsFor(string $notificationType, object $notifiable): array
    {
        if (($notificationChannels = $this->config->get("notifications.types.$notificationType.available_channels")) !== null) {
            return $notificationChannels;
        }

        return $this->config->get('notifications.available_channels', []);
    }

    /**
     * {@inheritDoc}
     */
    public function requiredNotificationChannelsFor(string $notificationType, object $notifiable): array
    {
        if (($notificationChannels = $this->config->get("notifications.types.$notificationType.required_channels")) !== null) {
            return $notificationChannels;
        }

        return $this->config->get('notifications.required_channels', []);
    }
}
