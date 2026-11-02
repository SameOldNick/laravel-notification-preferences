<?php

namespace SameOldNick\NotificationPreferences\Services;

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
        $notificationTypes = [];

        $notificationPreferences = $notifiable->notificationPreferences->mapWithKeys(fn ($preference) => [
            enum_value($preference->channel).':'.enum_value($preference->notification_type) => $preference->enabled,
        ]);

        // Define channels that can be modified by the user
        $channels = $this->notificationChannelProvider->availableNotificationChannels();

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
}
