<?php

namespace SameOldNick\NotificationPreferences\Providers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalogForNotifiable;
use SameOldNick\NotificationPreferences\Contracts\NotificationTypeGuard;

class NotificationCatalogConfigProvider implements NotificationCatalogForNotifiable
{
    /**
     * Create a new instance of the NotificationCatalogConfigProvider.
     *
     * @return void
     */
    public function __construct(
        private readonly Repository $config,
        private readonly Container $container,
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
        $guard = $this->typeGuard();
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
     * Resolve the configured notification type guard, if one is configured.
     *
     * A null or missing guard leaves every type unguarded. Any other value must be a
     * class-string implementing the contract, so misconfiguration fails loudly. Naming
     * the contract itself resolves whichever implementation is bound to it.
     */
    private function typeGuard(): ?NotificationTypeGuard
    {
        $guard = $this->config->get('notification-preferences.guard');

        if ($guard === null) {
            return null;
        }

        if (! is_string($guard) || ! is_a($guard, NotificationTypeGuard::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'The [notification-preferences.guard] configuration value must be a class-string implementing [%s], %s given.',
                NotificationTypeGuard::class,
                is_string($guard) ? $guard : get_debug_type($guard),
            ));
        }

        if ($guard === NotificationTypeGuard::class && ! $this->container->bound($guard)) {
            throw new InvalidArgumentException(sprintf(
                'The [notification-preferences.guard] configuration value is the [%s] contract, but no implementation is bound to the container. Bind an implementation or set the guard to null to leave types unguarded.',
                NotificationTypeGuard::class,
            ));
        }

        $instance = $this->container->make($guard);

        if (! $instance instanceof NotificationTypeGuard) {
            throw new InvalidArgumentException(sprintf(
                'The [notification-preferences.guard] configuration value resolved to [%s], which does not implement [%s].',
                get_debug_type($instance),
                NotificationTypeGuard::class,
            ));
        }

        return $instance;
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
