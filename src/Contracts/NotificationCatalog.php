<?php

namespace SameOldNick\NotificationPreferences\Contracts;

interface NotificationCatalog
{
    /**
     * Get the notification types that a notifiable can configure preferences for.
     *
     * Each type exposes its own label and category, so callers can group the
     * returned types by category without a second lookup.
     *
     * @return array<string, array{label: string, category: string}>
     */
    public function notificationTypes(): array;

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
     * Get the notification channels that are available for all notifications, regardless of user preferences.
     *
     * @return list<string> The list of available notification channel keys.
     */
    public function availableNotificationChannels(): array;

    /**
     * Get the notification channels that are required for all notifications, regardless of user preferences.
     *
     * @return list<string> The list of required notification channel keys.
     */
    public function requiredNotificationChannels(): array;

    /**
     * Get the notification channels that are available for a given notification type and notifiable.
     *
     * Callers resolve channels per type: the preferences index calls this once for every
     * configured type. Providers are registered as singletons, so don't hold notifiable
     * state on the instance; memoize per request only if resolution is expensive.
     *
     * @param  string  $notificationType  The notification type for which to retrieve available channels.
     * @param  object  $notifiable  The notifiable entity (e.g., User model).
     * @return list<string> The list of available notification channel keys.
     */
    public function availableNotificationChannelsFor(string $notificationType, object $notifiable): array;

    /**
     * Get the notification channels that are required for all notifications, regardless of user preferences.
     *
     * @param  string  $notificationType  The notification type for which to retrieve required channels.
     * @param  object  $notifiable  The notifiable entity (e.g., User model).
     * @return list<string> The list of required notification channel keys.
     */
    public function requiredNotificationChannelsFor(string $notificationType, object $notifiable): array;
}
