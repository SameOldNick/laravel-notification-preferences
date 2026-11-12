<?php

namespace SameOldNick\NotificationPreferences\Services;

use InvalidArgumentException;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;
use SameOldNick\NotificationPreferences\Contracts\NotificationCatalogForNotifiable;
use SameOldNick\NotificationPreferences\Contracts\NotificationChannelDefaults;

use function Illuminate\Support\enum_value;

class NotificationPreferencesService
{
    public function __construct(
        private readonly NotificationCatalog $notificationCatalog,
    ) {
        //
    }

    /**
     * Get the notification types available to a given notifiable.
     *
     * Catalogs implementing NotificationCatalogForNotifiable can hide types from
     * specific notifiables; any other catalog is treated as unguarded.
     *
     * @param  object  $notifiable  The notifiable entity (e.g., User model).
     * @return array<string, array{label: string, category: string, available_channels?: list<string>, required_channels?: list<string>}> The available notification types.
     */
    public function getNotificationTypesForNotifiable(object $notifiable): array
    {
        if (! $this->isNotifableSupported($notifiable)) {
            throw new InvalidArgumentException('The provided notifiable does not support notification preferences.');
        }

        return $this->notificationCatalog instanceof NotificationCatalogForNotifiable
            ? $this->notificationCatalog->notificationTypesFor($notifiable)
            : $this->notificationCatalog->notificationTypes();
    }

    /**
     * Get the notification preferences for a given notifiable.
     *
     * The catalog is consulted once per configured notification type, since
     * each type may be deliverable through a different set of channels.
     *
     * @param  object  $notifiable  The notifiable entity (e.g., User model).
     * @return array An associative array of notification preferences.
     */
    public function getPreferencesForNotifiable(object $notifiable): array
    {
        if (! $this->isNotifableSupported($notifiable)) {
            throw new InvalidArgumentException('The provided notifiable does not support notification preferences.');
        }

        $notificationPreferences = $notifiable->notificationPreferences->mapWithKeys(fn ($preference) => [
            enum_value($preference->channel).':'.enum_value($preference->notification_type) => $preference->enabled,
        ]);

        $notificationTypes = [];

        foreach ($this->getNotificationTypesForNotifiable($notifiable) as $type => $notificationType) {
            // Resolved per type, so a type can offer a different channel set than its siblings.
            $channels = $this->notificationCatalog->availableNotificationChannelsFor($type, $notifiable);

            foreach ($channels as $channel) {
                $key = $channel.':'.$type;

                $notificationTypes[$key] = [
                    'enabled' => $notificationPreferences[$key] ?? $this->channelEnabledByDefault($type, $channel, $notifiable),
                    'key' => $type,
                    'label' => __($notificationType['label']),
                    'category' => __($notificationType['category']),
                    'channel' => $channel,
                ];
            }
        }

        return $notificationTypes;
    }

    /**
     * Update the notification preferences for a given notifiable.
     *
     * Types and channels that are not available to the notifiable are rejected
     * before anything is written, so a payload is applied as a whole or not at all.
     *
     * @param  object  $notifiable  The notifiable entity (e.g., User model).
     * @param  array<string, array{channels?: array<string, bool>}>  $preferences  An associative array of notification preferences to update.
     */
    public function updatePreferencesForNotifiable(object $notifiable, array $preferences): void
    {
        if (! $this->isNotifableSupported($notifiable)) {
            throw new InvalidArgumentException('The provided notifiable does not support notification preferences.');
        }

        $this->validatePreferencesForNotifiable($notifiable, $preferences);

        foreach ($preferences as $key => $type) {
            foreach ($type['channels'] ?? [] as $channel => $enabled) {
                $existing = $notifiable->notificationPreferences()
                    ->where('notification_type', $key)
                    ->where('channel', $channel)
                    ->first();

                if ($existing) {
                    if ($existing->enabled === $enabled) {
                        // No change needed, skip to next
                        continue;
                    }

                    // Update existing preference
                    $existing->update(['enabled' => $enabled]);
                } else {
                    $notifiable->notificationPreferences()->create([
                        'notification_type' => $key,
                        'channel' => $channel,
                        'enabled' => $enabled,
                    ]);
                }
            }
        }
    }

    /**
     * Ensure the submitted types and channels are available to the notifiable.
     *
     * Required channels are accepted even when they cannot be toggled, because the
     * type is genuinely deliverable through them.
     *
     * @param  array<string, array{channels?: array<string, bool>}>  $preferences
     */
    private function validatePreferencesForNotifiable(object $notifiable, array $preferences): void
    {
        $types = $this->getNotificationTypesForNotifiable($notifiable);

        foreach ($preferences as $type => $submitted) {
            $type = (string) $type;

            if (! array_key_exists($type, $types)) {
                throw new InvalidArgumentException(sprintf(
                    'The notification type [%s] is not available for this notifiable.',
                    $type,
                ));
            }

            $channels = array_unique([
                ...$this->notificationCatalog->availableNotificationChannelsFor($type, $notifiable),
                ...$this->notificationCatalog->requiredNotificationChannelsFor($type, $notifiable),
            ]);

            foreach (array_keys($submitted['channels'] ?? []) as $channel) {
                $channel = (string) $channel;

                if (! in_array($channel, $channels, true)) {
                    throw new InvalidArgumentException(sprintf(
                        'The notification channel [%s] is not available for notification type [%s].',
                        $channel,
                        $type,
                    ));
                }
            }
        }
    }

    /**
     * Determine if a channel is enabled by default, falling back to enabled when the catalog has no defaults.
     */
    private function channelEnabledByDefault(string $notificationType, string $channel, object $notifiable): bool
    {
        if (! $this->notificationCatalog instanceof NotificationChannelDefaults) {
            return true;
        }

        return $this->notificationCatalog->channelEnabledByDefault($notificationType, $channel, $notifiable);
    }

    /**
     * Check if the notifiable entity supports notification preferences.
     *
     * @param  object  $notifiable  The notifiable entity to check.
     * @return bool True if the notifiable supports notification preferences, false otherwise.
     */
    protected function isNotifableSupported(object $notifiable): bool
    {
        return method_exists($notifiable, 'notificationPreferences');
    }
}
