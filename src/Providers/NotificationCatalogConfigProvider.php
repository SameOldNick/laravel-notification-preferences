<?php

namespace SameOldNick\NotificationPreferences\Providers;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalogForNotifiable;
use SameOldNick\NotificationPreferences\Contracts\NotificationChannelDefaults;
use SameOldNick\NotificationPreferences\Services\NotificationTypeGuardResolver;

class NotificationCatalogConfigProvider implements NotificationCatalogForNotifiable, NotificationChannelDefaults
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
        return $this->globalChannels('available_channels');
    }

    /**
     * {@inheritDoc}
     */
    public function requiredNotificationChannels(): array
    {
        return $this->globalChannels('required_channels');
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
     * {@inheritDoc}
     */
    public function channelEnabledByDefault(string $notificationType, string $channel, object $notifiable): bool
    {
        $defaultChannelsKey = "notification-preferences.types.{$notificationType}.default_channels";
        $defaultChannels = $this->config->get($defaultChannelsKey);

        if ($defaultChannels !== null) {
            if (! is_array($defaultChannels)) {
                throw new InvalidArgumentException(sprintf(
                    'The [%s] configuration value must be an array or null, %s given.',
                    $defaultChannelsKey,
                    get_debug_type($defaultChannels),
                ));
            }

            return in_array($channel, $defaultChannels, true);
        }

        $enabledByDefaultKey = "notification-preferences.channels.{$channel}.enabled_by_default";
        $enabledByDefault = $this->config->get($enabledByDefaultKey);

        if ($enabledByDefault !== null) {
            if (! is_bool($enabledByDefault)) {
                throw new InvalidArgumentException(sprintf(
                    'The [%s] configuration value must be a boolean or null, %s given.',
                    $enabledByDefaultKey,
                    get_debug_type($enabledByDefault),
                ));
            }

            return $enabledByDefault;
        }

        return true;
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
            return $this->globalChannels($key);
        }

        return $this->resolveChannels($configKey, $channels, $key === 'available_channels');
    }

    /**
     * Resolve a global channel list.
     *
     * The available list defaults to every registered channel when it is not configured,
     * so the channels map remains the single source of truth for what can be offered.
     *
     * @return list<string>
     */
    private function globalChannels(string $key): array
    {
        $configKey = "notification-preferences.{$key}";
        $channels = $this->config->get($configKey);

        if ($channels === null) {
            return $key === 'available_channels'
                ? array_keys($this->notificationChannels())
                : [];
        }

        return $this->resolveChannels($configKey, $channels, $key === 'available_channels');
    }

    /**
     * Validate a configured channel list and reindex it.
     *
     * Available channels must be registered in the channels map so they have a label and a
     * default; required channels may name channels the application delivers without offering
     * them to notifiables, so they are not required to be registered.
     *
     * @return list<string>
     */
    private function resolveChannels(string $configKey, mixed $channels, bool $mustBeRegistered): array
    {
        if (! is_array($channels)) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] configuration value must be an array or null, %s given.',
                $configKey,
                get_debug_type($channels),
            ));
        }

        $channels = array_values($channels);

        if (! $mustBeRegistered) {
            return $channels;
        }

        $registered = array_keys($this->notificationChannels());

        foreach ($channels as $channel) {
            if (! in_array($channel, $registered, true)) {
                throw new InvalidArgumentException(sprintf(
                    'The [%s] configuration value contains the channel [%s], which is not defined in the [notification-preferences.channels] configuration.',
                    $configKey,
                    is_string($channel) ? $channel : get_debug_type($channel),
                ));
            }
        }

        return $channels;
    }
}
