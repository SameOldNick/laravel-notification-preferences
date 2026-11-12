<?php

namespace SameOldNick\NotificationPreferences\Services;

use SameOldNick\NotificationPreferences\Contracts\NotificationCatalog;
use SameOldNick\NotificationPreferences\Contracts\NotificationChannelDefaults;

class NotificationChannelResolver
{
    public function __construct(
        private readonly NotificationCatalog $notificationCatalog,
    ) {
        //
    }

    /**
     * Determine the channels to route to, based on the notifiable's preferences for one notification type.
     *
     * A channel is dropped when the notifiable recorded a disabled preference for it, or when it has no
     * preference row and the catalog disables it by default. A channel with no preference row is otherwise
     * kept, so a newly added channel still reaches existing users.
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

        $channels = array_values(array_unique($channels));

        $preferences = $notifiable->notificationPreferences()
            ->where('notification_type', $type)
            ->whereIn('channel', $channels)
            ->pluck('enabled', 'channel');

        return array_values(array_filter(
            $channels,
            fn (string $channel): bool => (bool) ($preferences->get($channel)
                ?? $this->channelEnabledByDefault($type, $channel, $notifiable)),
        ));
    }

    /**
     * Similar to viaNotifiablePreferences but appends channels that are included regardless of preferences.
     *
     * @param  string  $notificationType  The notification type to check preferences for
     * @param  object  $notifiable  The notifiable entity to check preferences for
     * @param  array<int, string>  $availableChannels  Available channels that the notification can be sent to
     * @param  array<int, string>  $requiredChannels  Channels that should always be included, even if the notifiable has disabled them.
     * @return array<int, string>
     */
    public function viaNotifiablePreferencesWithRequiredChannels(object $notifiable, string $notificationType, array $availableChannels = [], array $requiredChannels = []): array
    {
        return array_values(array_unique([
            ...$this->viaNotifiablePreferences($notifiable, $notificationType, $availableChannels),
            ...$this->normaliseChannels($requiredChannels),
        ]));
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
