<?php

namespace SameOldNick\NotificationPreferences\Contracts;

interface NotificationChannelProvider
{
    /**
     * Get the notification channels that a notifiable can configure preferences for.
     *
     * Each channel exposes its own label, so callers can display the returned
     * channels without a second lookup.
     *
     * @return array<string, array{label: string}> The list of notification channels with their labels.
     */
    public function notificationChannels(): array;

    /**
     * Get the notification channels that are available for use.
     *
     * @return list<string> The list of available notification channel keys.
     */
    public function availableNotificationChannels(): array;

    /**
     * Get the notification channels that are required for all notifications, regardless of user preferences.
     *
     * @return list<string> The list of default notification channel keys.
     */
    public function requiredNotificationChannels(): array;
}
