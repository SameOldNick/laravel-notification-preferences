<?php

namespace SameOldNick\NotificationPreferences\Services;

class NotificationChannelResolver
{
    /**
     * Determine the channels to route to, based on the notifiable's preferences for one notification type.
     *
     * A channel is dropped only when the notifiable recorded a disabled preference for it; a channel
     * with no preference row is kept, so a newly added channel still reaches existing users.
     *
     * @param  ?object  $notifiable  The notifiable entity to check preferences for
     * @param  string  $type  The notification type to check preferences for
     * @param  array<int, string>  $availableChannels  The channels that the notification can be sent to
     * @return array<int, string>
     */
    public function viaNotifiablePreferences(
        ?object $notifiable,
        string $type,
        array $availableChannels = [],
    ): array {
        if (! $notifiable) {
            return [];
        }

        $channels = $this->normaliseChannels($availableChannels);

        if ($channels === [] || ! $this->notifiableHasPreferences($notifiable)) {
            return $channels;
        }

        $channels = array_unique($availableChannels);

        $disabledChannels = $notifiable->notificationPreferences()
            ->where('notification_type', $type)
            ->whereIn('channel', $channels)
            ->where('enabled', false)
            ->pluck('channel')
            ->all();

        return array_values(array_diff($channels, $this->normaliseChannels($disabledChannels)));
    }

    /**
     * Similar to viaNotifiablePreferences but appends channels that are included regardless of preferences.
     *
     * @param  array<int, string>  $availableChannels
     * @param  array<int, string>  $defaultChannels
     * @return array<int, string>
     */
    public function viaNotifiablePreferencesWithDefaults(object $notifiable, string $type, array $availableChannels = [], array $defaultChannels = []): array
    {
        return array_values(array_unique([
            ...$this->viaNotifiablePreferences($notifiable, $type, $availableChannels),
            ...$this->normaliseChannels($defaultChannels),
        ]));
    }

    /**
     * Resolve every channel to its string value, so callers always receive routable channel names.
     *
     * @param  array<int, string>  $channels
     * @return list<string>
     */
    private function normaliseChannels(array $channels): array
    {
        return array_values($channels);
    }

    /**
     * Check if notifiable has preferences
     */
    private function notifiableHasPreferences(object $notifiable): bool
    {
        return method_exists($notifiable, 'notificationPreferences');
    }
}
