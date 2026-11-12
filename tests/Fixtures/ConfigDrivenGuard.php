<?php

namespace SameOldNick\NotificationPreferences\Tests\Fixtures;

use Illuminate\Contracts\Config\Repository;
use SameOldNick\NotificationPreferences\Contracts\NotificationTypeGuard;

class ConfigDrivenGuard implements NotificationTypeGuard
{
    public function __construct(private readonly Repository $config)
    {
        //
    }

    public function allows(string $notificationType, object $notifiable): bool
    {
        return in_array(
            $notificationType,
            (array) $this->config->get('notification-preferences.test_guard_allowed_types', []),
            true,
        );
    }
}
