<?php

namespace SameOldNick\NotificationPreferences\Tests\Fixtures;

enum NotificationType: string
{
    case Login = 'login';
    case BackupFailed = 'backup_failed';
}
