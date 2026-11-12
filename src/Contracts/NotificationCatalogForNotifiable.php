<?php

namespace SameOldNick\NotificationPreferences\Contracts;

interface NotificationCatalogForNotifiable extends NotificationCatalog
{
    /**
     * Get the notification types available to the given notifiable.
     *
     * Catalogs that can vary by notifiable should implement this interface, so
     * types hidden from a notifiable stay out of preference lists and updates.
     * Catalogs that only implement NotificationCatalog are treated as unguarded.
     *
     * @param  object  $notifiable  The notifiable entity (e.g., User model).
     * @return array<string, array{label: string, category: string, available_channels?: list<string>, required_channels?: list<string>}> The available notification types with their labels and categories.
     */
    public function notificationTypesFor(object $notifiable): array;
}
