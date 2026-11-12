<?php

namespace SameOldNick\NotificationPreferences\Tests\Fixtures;

use SameOldNick\NotificationPreferences\Contracts\NotificationTypeGuard;

class NotifiablePropertyGuard implements NotificationTypeGuard
{
    public function allows(string $notificationType, object $notifiable): bool
    {
        return (bool) ($notifiable->receives_notifications ?? false);
    }
}
