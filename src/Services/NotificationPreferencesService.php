<?php

namespace SameOldNick\NotificationPreferences\Services;

use InvalidArgumentException;
use SameOldNick\NotificationPreferences\Contracts\NotificationChannelProvider;
use SameOldNick\NotificationPreferences\Contracts\NotificationTypeProvider;

use function Illuminate\Support\enum_value;

class NotificationPreferencesService
{
    public function __construct(
        private readonly NotificationChannelProvider $notificationChannelProvider,
        private readonly NotificationTypeProvider $notificationTypeProvider,
    ) {
        //
    }

    /**
     * Get the notification preferences for a given notifiable.
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

        // Define channels that can be modified by the user
        $channels = $this->notificationChannelProvider->availableNotificationChannels();

        $notificationTypes = [];

        foreach ($this->notificationTypeProvider->notificationTypes() as $type => $notificationType) {
            foreach ($channels as $channel) {
                $key = $channel.':'.$type;

                $notificationTypes[$key] = [
                    'enabled' => $notificationPreferences[$key] ?? true, // Default to true if no preference is set
                    'key' => $type,
                    'label' => $notificationType['label'],
                    'category' => $notificationType['category'],
                    'channel' => $channel,
                ];
            }
        }

        return $notificationTypes;
    }

    /**
     * Update the notification preferences for a given notifiable.
     *
     * @param  object  $notifiable  The notifiable entity (e.g., User model).
     * @param  array<string, array{channels: array<string, bool>}>  $preferences  An associative array of notification preferences to update.
     */
    public function updatePreferencesForNotifiable(object $notifiable, array $preferences): void
    {
        if (! $this->isNotifableSupported($notifiable)) {
            throw new InvalidArgumentException('The provided notifiable does not support notification preferences.');
        }

        foreach ($preferences as $key => $type) {
            foreach ($type['channels'] as $channel => $enabled) {
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
