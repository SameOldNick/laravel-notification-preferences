<?php

namespace SameOldNick\NotificationPreferences\Concerns;

use SameOldNick\NotificationPreferences\Models\NotifiableNotificationPreference;

trait HasNotificationPreferences
{
    /**
     * Get the notification preferences for the user.
     */
    public function notificationPreferences()
    {
        return $this->morphMany(NotifiableNotificationPreference::class, 'notifiable');
    }
}
