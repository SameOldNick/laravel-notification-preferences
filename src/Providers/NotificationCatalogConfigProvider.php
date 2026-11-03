<?php

namespace SameOldNick\NotificationPreferences\Providers;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
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
        return $this->channelsForType($notificationType, 'available_channels');
    }

    /**
     * {@inheritDoc}
     */
    public function requiredNotificationChannelsFor(string $notificationType, object $notifiable): array
    {
        return $this->channelsForType($notificationType, 'required_channels');
    }

    /**
     * Resolve a channel list for a notification type, falling back to the global list when the type value is null.
     *
     * An empty array is an explicit override that opts out of the global list, while any other
     * non-array value is treated as a misconfiguration so it fails loudly instead of silently.
     *
     * @return list<string>
     */
    private function channelsForType(string $notificationType, string $key): array
    {
        $configKey = "notifications.types.{$notificationType}.{$key}";
        $channels = $this->config->get($configKey);

        if ($channels === null) {
            $configKey = "notifications.{$key}";
            $channels = $this->config->get($configKey, []);
        }

        if (! is_array($channels)) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] configuration value must be an array or null, %s given.',
                $configKey,
                get_debug_type($channels),
            ));
        }

        return array_values($channels);
    }
}
