<?php

namespace SameOldNick\NotificationPreferences\Providers;

use SameOldNick\NotificationPreferences\Contracts\NotificationTypeProvider;

class NotificationTypeConfigProvider implements NotificationTypeProvider
{
    /**
     * {@inheritDoc}
     */
    public function notificationTypes(): array
    {
        return config('notifications.types', []);
    }
}
