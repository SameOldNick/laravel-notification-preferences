<?php

namespace SameOldNick\NotificationPreferences\Contracts;

interface NotificationTypeGuard
{
    /**
     * Determine if the notifiable may use the given notification type.
     *
     * One guard is configured for the whole catalog, so it receives the type
     * instead of each type declaring its own guard.
     *
     * Guards control which types a notifiable can see and configure. They do not
     * prevent the application from sending a notification of that type.
     */
    public function allows(string $notificationType, object $notifiable): bool;
}
