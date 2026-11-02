<?php

namespace SameOldNick\NotificationPreferences\Contracts;

interface NotificationTypeProvider
{
    /**
     * Get the notification types that a notifiable can configure preferences for.
     *
     * Each type exposes its own label and category, so callers can group the
     * returned types by category without a second lookup.
     *
     * @return array<string, array{label: string, category: string}>
     */
    public function notificationTypes(): array;
}
