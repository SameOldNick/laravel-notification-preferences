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
     * Guards control which types a notifiable can see, configure, and receive.
     * A type the guard rejects resolves no delivery channels, including required
     * ones, so it is not sent.
     */
    public function allows(string $notificationType, object $notifiable): bool;
}
