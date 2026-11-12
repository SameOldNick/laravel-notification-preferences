<?php

namespace SameOldNick\NotificationPreferences\Contracts;

interface NotificationChannelDefaults
{
    /**
     * Determine if the given channel is enabled by default for the given notification type and notifiable.
     *
     * @param  string  $notificationType  The notification type.
     * @param  string  $channel  The channel name.
     * @param  object  $notifiable  The notifiable entity.
     * @return bool True if the channel is enabled by default, false otherwise.
     */
    public function channelEnabledByDefault(string $notificationType, string $channel, object $notifiable): bool;
}
