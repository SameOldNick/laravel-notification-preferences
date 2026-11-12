<?php

namespace SameOldNick\NotificationPreferences\Providers;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalogForNotifiable;
use SameOldNick\NotificationPreferences\Services\NotificationTypeGuardResolver;

class NotificationCatalogConfigProvider implements NotificationCatalogForNotifiable
{
    /**
     * Create a new instance of the NotificationCatalogConfigProvider.
     *
     * @return void
     */
    public function __construct(
        private readonly Repository $config,
        private readonly NotificationTypeGuardResolver $typeGuardResolver,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function notificationTypes(): array
    {
        return $this->config->get('notification-preferences.types', []);
    }

    /**
     * {@inheritDoc}
     */
    public function notificationTypesFor(object $notifiable): array
    {
        $guard = $this->typeGuardResolver->resolve();
        $types = [];

        foreach ($this->notificationTypes() as $key => $type) {
            if ($guard === null || $guard->allows($key, $notifiable)) {
                $types[$key] = $type;
            }
        }

        return $types;
    }

    /**
     * {@inheritDoc}
     */
    public function notificationChannels(): array
    {
        return $this->config->get('notification-preferences.channels', []);
    }

    /**
     * {@inheritDoc}
     */
    public function availableNotificationChannels(): array
    {
        return $this->config->get('notification-preferences.available_channels', []);
    }

    /**
     * {@inheritDoc}
     */
    public function requiredNotificationChannels(): array
    {
        return $this->config->get('notification-preferences.required_channels', []);
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
        $configKey = "notification-preferences.types.{$notificationType}.{$key}";
        $channels = $this->config->get($configKey);

        if ($channels === null) {
            $configKey = "notification-preferences.{$key}";
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
